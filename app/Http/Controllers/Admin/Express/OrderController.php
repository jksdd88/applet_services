<?php
/**
 * Created by PhpStorm.
 * User: wangshiliang@dodoca.com
 * Date: 2018/8/1
 * Time: 9:52
 */

namespace App\Http\Controllers\Admin\Express;

use App\Http\Controllers\Controller;
use App\Models\ExpressOrder;
use App\Models\ExpressShop;
use App\Models\OrderAddr;
use App\Models\OrderInfo;
use App\Services\ExpressService;
use App\Services\UserPrivService;
use App\Utils\CacheKey;
use App\Utils\Dada\DadaOrder;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Config;
use Cache;

class OrderController extends Controller {

    private $request;
    private $merchant_id;
    private $app_key;
    private $app_secret;
    private $merchant_dada;
    private $env;

    public function __construct(Request $request) {
        $this->request = $request;
        $this->app_key  = Config::get('express.app_key');
        $this->app_secret = Config::get('express.app_secret');
        $this->merchant_id =  Auth::user()->merchant_id;
        $if_auth = UserPrivService::getHexiaoPriv(Auth::user()->id,Auth::user()->merchant_id,Auth::user()->is_admin,'setting_appoint_dada');
        if( !isset($if_auth['errcode']) || $if_auth['errcode']!='has_priv' ){
            return ['errcode'=>111302,'errmsg'=>'您没有骑手专送权限'];
        }
        $expressService = new ExpressService();
        $this->merchant_dada = $expressService->getDadaMerchant($this->merchant_id);
        $this->env =  $expressService->getEnv($this->merchant_id);
    }

    //预约发货
    public function subscribe(){
        $orderId = $this->request->get('order_id',0);
        $storeId  = $this->request->get('store_id',0);
        $lng     = $this->request->get('lng',0);
        $lat     = $this->request->get('lat',0);

        if(empty($orderId) || empty($storeId) || empty($lng) || empty($lat) ){
            return Response::json(['errcode'=>1, 'errmsg'=>'参数有误']);
        }
        $expressShop = ExpressShop::get_one('store_id',$storeId);
        if(!isset($expressShop['id']) || $expressShop['merchant_id'] != $this->merchant_id){
            return Response::json(['errcode'=>1, 'errmsg'=>'门店id有误']);
        }
        $orderinfo = OrderInfo::get_data_by_id($orderId,$this->merchant_id);
        if(!isset($orderinfo['id'])){
            return Response::json(['errcode'=>1, 'errmsg'=>'订单id有误']);
        }
        $orderAddr = OrderAddr::get_data_by_id($orderId);
        if(!isset($orderAddr['id'])){
            return Response::json(['errcode'=>1, 'errmsg'=>'订单收货地址有误']);
        }
        $count = ExpressOrder::query()->where(['order_id'=>$orderId])->count();
        $data = [
            'merchant_id' => $this->merchant_id,
            'order_id'    => $orderId,
            'shop_id'     => $expressShop['id'],
            'dada_sn'     => $orderinfo['order_sn'].'_'.$count,
            'city'        => $expressShop['city'],
            'price'       => $orderinfo['amount'],
            'receiver'    => $orderAddr['consignee'],
            'address'     => $orderAddr['province_name'].','.$orderAddr['city_name'].','.$orderAddr['district_name'].','.$orderAddr['address'],
            'mobile'      => $orderAddr['mobile'],
            'lng'         => $lng,
            'lat'         => $lat,
            'store_address' => $expressShop['city_name'].','.$expressShop['address'],
            'remark'      => 'DODOCA',
            'business'    => $expressShop['business'],
            'status'      => -1,
            'is_prepay'   => 0,
            'delivery'    => 0,
            'insurance'   => 0
        ];
        DB::beginTransaction();
        $id = ExpressOrder::insert_data($data);
        $dada = (new DadaOrder())->setConfig($this->app_key, $this->app_secret, $this->merchant_dada, $this->env);
        $callback = (new ExpressService())->getCallbackUrl();
        $response = $dada ->order($data['shop_id'],$data['dada_sn'],$data['city'],$data['price'],$data['is_prepay'],$data['receiver'],$data['address'],$data['lat'],$data['lng'],$data['mobile'],$callback,[
            'mark'      => $data['remark'],
            'mark_no'   => $orderinfo['order_sn'],
            'insurance' => $data['insurance'],
            'delivery'  => $data['delivery'],
        ],'get');

        if(isset($response['code']) && $response['code'] == 0 && isset($response['result']['fee'])){
            $update = [
                'total'=> $response['result']['fee'] ,
                'distance' => $response['result']['distance'] ,
                'freight' =>  $response['result']['deliverFee'],
                'delivery_sn' =>  $response['result']['deliveryNo']
            ];
            if(isset($response['result']['insuranceFee'])) $update['insurance_price'] =  $response['result']['insuranceFee'];
            if(isset($response['result']['tips']))         $update['price_tip']       =  $response['result']['tips'];
            if(isset($response['result']['couponFee']))    $update['coupon']          =  $response['result']['couponFee'];
            ExpressOrder::update_data('id',$id,$update);
            DB::commit();
            return Response::json(['errcode'=>0, 'errmsg'=>'成功','data'=>['id'=>$id,'total'=>round($response['result']['fee'],2)]]);
        }else{
            DB::rollBack();
            return Response::json(['errcode'=>1, 'errmsg'=>isset($response['msg'])?$response['msg']:'系统繁忙','response'=>$response]);
        }
    }

    //重新发货
    public function  resend(){
        $id = $this->request->input('id');
        if(empty($id)){
            return Response::json(['errcode'=>1, 'errmsg'=>'请传入预发布id']);
        }
        $info = ExpressOrder::get_one('id',$id);
        if(!isset($info['id']) || $info['merchant_id'] != $this->merchant_id ){
            return Response::json(['errcode'=>2, 'errmsg'=>'请传入真确的预发布id']);
        }
        if(!in_array($info['status'],[5,7,10])){
            return Response::json(['errcode'=>0, 'errmsg'=>'当前状态不可以重新发送']);
        }
        //添加订单
        $order_sn = explode('_',$info['dada_sn']);
        $callback = (new ExpressService())->getCallbackUrl();
        $data = [
            'merchant_id' => $this->merchant_id,
            'order_id'    => $info['order_id'],
            'package_id'  => $info['package_id'],
            'shop_id'     => $info['shop_id'],
            'dada_sn'     => $info['dada_sn'],
            'city'        => $info['city'],
            'price'       => $info['price'],
            'receiver'    => $info['receiver'],
            'address'     => $info['address'],
            'mobile'      => $info['mobile'],
            'lng'         => $info['lng'],
            'lat'         => $info['lat'],
            'store_address' => $info['store_address'],
            'remark'      => 'DODOCA',
            'business'    => $info['business'],
            'status'      => -1
        ];
        DB::beginTransaction();
        $newid = ExpressOrder::insert_data($data);
        $dada = (new DadaOrder())->setConfig($this->app_key, $this->app_secret, $this->merchant_dada,$this->env);
        $response = $dada ->order($info['shop_id'],$info['dada_sn'],$info['city'],$info['price'],$info['is_prepay'],$info['receiver'],$info['address'],$info['lat'],$info['lng'],$info['mobile'],$callback,['mark'=> $info['remark'],'mark_no'=> $order_sn[0], 'insurance' => $info['insurance'], 'delivery'  => $info['delivery'],   ],'update');
        if(!isset($response['code']) || $response['code'] != 0 || !isset($response['result']['fee'])){
            DB::rollBack();
            return Response::json(['errcode'=>1, 'errmsg'=>'网络通信有误','response'=>$response]);
        }
        $update = [  'total'=> $response['result']['fee'] ,  'distance' => $response['result']['distance'] ,  'freight' =>  $response['result']['deliverFee']   ];
        if(isset($response['result']['insuranceFee'])) $update['insurance_price'] =  $response['result']['insuranceFee'];
        if(isset($response['result']['tips']))         $update['price_tip']       =  $response['result']['tips'];
        if(isset($response['result']['couponFee']))    $update['coupon']          =  $response['result']['couponFee'];
        /*
        //预发布
        $response = $dada ->order($info['shop_id'],$info['dada_sn'],$info['city'],$info['price'],$info['is_prepay'],$info['receiver'],$info['address'],$info['lat'],$info['lng'],$info['mobile'],$callback,[],'get');
        if(!isset($response['code']) || $response['code'] != 0 || !isset($response['result']['deliveryNo'])){
            DB::rollBack();
            return Response::json(['errcode'=>3,  'errmsg'=>'网络通信有误','response'=>$response]);
        }
        $update['delivery_sn'] = $response['result']['deliveryNo'];
        //发布
        $response = $dada -> releaseOrder($response['result']['deliveryNo']);
        if(!isset($response['code']) ||  !in_array($response['code'],[0,2063,2064])){
            DB::rollBack();
            return Response::json(['errcode'=>4, 'errmsg'=>'网络通信有误','response'=>$response]);
        }
        $update['status'] = 1;
        */
        $update['delivery_time'] = date('Y-m-d H:i:s');
        ExpressOrder::update_data('id',$newid,$update);
        ExpressOrder::update_data('id',$id,['status'=>15]);
        DB::commit();
        return Response::json(['errcode'=>0, 'errmsg'=>'已重新发货']);
    }

    //订单查询
    public function details(){
        $order_id = $this->request->get('order_id');
        if(empty($order_id)){
            return Response::json(['errcode'=>1, 'errmsg'=>'请传入订单id']);
        }
        $check = OrderInfo::get_data_by_id($order_id,$this->merchant_id);
        if(!isset($check['id'])){
            return Response::json(['errcode'=>1, 'errmsg'=>'请传正确入订单id']);
        }
        $dada = (new DadaOrder())->setConfig($this->app_key, $this->app_secret, $this->merchant_dada,$this->env);
        $filed = ['id','dada_sn','waybill_sn','package_id','total','status','courier_name','courier_mobile','courier_lng','courier_lat','courier_distance','courier_distance','store_address','delivery_time','accept_time','fetch_time','finish_time','cancel','reason','cancel_time','cancel_price'];
        $list = ExpressOrder::query()->where(['order_id'=>$order_id,'is_delete'=>1])->where('status','>',0)->where('package_id','>',0)->get($filed)->toArray();
        foreach ($list as $k => $v) {
            if( $v['status'] == 2 || $v['status'] == 3 ){
                $cacheKey = CacheKey::get_dada_express($v['id'],'select_details');
                if(Cache::get($cacheKey)){
                    continue;
                }
                $response = $dada -> getOrder($v['dada_sn']);
                if(!isset($response['code']) || $response['code'] != 0 ){
                    $list[$k]['response'] = $response;
                    continue;
                }
                if(isset($response['result']['statusCode']) &&  ($response['result']['statusCode']== 2 || $response['result']['statusCode'] == 3) ){
                    $update = [];
                    if(isset($response['result']['transporterLng']) && !empty($response['result']['transporterLng'])){
                        $list[$k]['courier_lng']  = $response['result']['transporterLng'];
                        $update['courier_lng']   = $response['result']['transporterLng'];
                    }
                    if(isset($response['result']['transporterLat']) && !empty($response['result']['transporterLat'])){
                        $list[$k]['courier_lat']  = $response['result']['transporterLat'];
                        $update['courier_lat']   = $response['result']['transporterLat'];
                    }
                    if(isset($response['result']['distance']) && !empty($response['result']['distance'])){
                        $list[$k]['courier_distance']  = $response['result']['distance'];
                        $update['courier_distance']   = $response['result']['distance'];
                    }
                    if(!empty($update)){
                        ExpressOrder::update_data('id',$v['id'],$update);
                    }
                }
                Cache::put($cacheKey,1,1);
            }else{
                $list[$k]['courier_lat'] = '';
                $list[$k]['courier_lng'] = '';
            }
        }
        return Response::json(['errcode'=>0, 'errmsg'=>'成功','data'=>$list]);
    }

    //取消订单
    public function cancel(){
        $id = $this->request->input('id');
        $reason_id = $this->request->input('reason_id');
        $reason    = $this->request->input('reason','');
        if(empty($id) || empty($reason_id) || empty($reason) ){
            return Response::json(['errcode'=>1, 'errmsg'=>'参数有误']);
        }
        $info = ExpressOrder::get_one('id',$id);
        if(!isset($info['id']) || $info['merchant_id'] != $this->merchant_id ){
            return Response::json(['errcode'=>2, 'errmsg'=>'请传入真确的预发布id']);
        }
        if(!in_array($info['status'],[1,2])){
            return Response::json(['errcode'=>4, 'errmsg'=>'当前配送状态不可以取消']);
        }
        $dada = (new DadaOrder())->setConfig($this->app_key, $this->app_secret, $this->merchant_dada,$this->env);
        $response = $dada ->cancelOrder($info['dada_sn'],$reason_id,$reason);
        if(!isset($response['code']) || $response['code'] != 0){

            return Response::json(['errcode'=>3, 'errmsg'=>isset($response['msg'])?$response['msg']:'通信失败','response'=>$response,'reason'=>$reason]);
        }
        $cancel_price =isset( $response['result']['deduct_fee'])? $response['result']['deduct_fee']:0;
        ExpressOrder::update_data('id',$info['id'],['status'=>5,'cancel'=>2,'cancel_price'=>$cancel_price,'reason'=>$reason,'cancel_time'=>date('Y-m-d H:i:s')]);
        return Response::json(['errcode'=>0, 'errmsg'=>'成功']);
    }

    //追加分配
    public function distribution(){
        return Response::json(['errcode'=>0, 'errmsg'=>'待开发']);
    }

    //取消分配
    public function deldistribution(){
        return Response::json(['errcode'=>0, 'errmsg'=>'待开发']);
    }

    //添加小费
    public function tips(){
        $id = $this->request->input('id');
        $price = $this->request->input('price');
        if(empty($id) || empty($price) || $price < 0.1 ){
            return Response::json(['errcode'=>1, 'errmsg'=>'参数有误']);
        }
        $info = ExpressOrder::get_one('id',$id);
        if(!isset($info['id']) || $info['merchant_id'] != $this->merchant_id ){
            return Response::json(['errcode'=>2, 'errmsg'=>'请传入真确的预发布id']);
        }
        if($info['status'] != 1){
            return Response::json(['errcode'=>3, 'errmsg'=>'只能对未结单状态添加消费']);
        }
        if($info['price_tip'] > $price){
            return Response::json(['errcode'=>4, 'errmsg'=>'小费金额不能小于少一次添加的']);
        }
        $dada = (new DadaOrder())->setConfig($this->app_key, $this->app_secret, $this->merchant_dada,$this->env);
        DB::beginTransaction();
        ExpressOrder::update_data('id',$info['id'],['price_tip'=>$price]);
        $response = $dada -> tipOrder($info['dada_sn'],$price,$info['city']);
        if(!isset($response['code']) || $response['code'] != 0){
            DB::rollBack();
            return Response::json(['errcode'=>3, 'errmsg'=>isset($response['msg'])?$response['msg']:'通信失败','response'=>$response]);
        }
        DB::commit();
        return Response::json(['errcode'=>0, 'errmsg'=>'成功']);
    }

    //投诉
    public function complaints(){

        return Response::json(['errcode'=>0, 'errmsg'=>'待开发']);

    }

    //模拟回调 临时
    public function cacheAction(){
        $type = $this->request->input('type','');
        $reason = $this->request->input('reason','');
        $id = $this->request->input('id');
        $info = ExpressOrder::get_one('id',$id);
        if(!isset($info['id']) || $info['merchant_id'] != $this->merchant_id ){
            return Response::json(['errcode'=>2, 'errmsg'=>'请传入真确的预发布id']);
        }
        if($info['status'] == 15){
            return Response::json(['errcode'=>2, 'errmsg'=>'该订单已锁定']);
        }
        $dada = (new DadaOrder())->setConfig($this->app_key, $this->app_secret, $this->merchant_dada,$this->env);
        if($type == 'accept'){
            $response = $dada->cacheAccept($info['dada_sn']);
        }elseif($type == 'fetch'){
            $response = $dada->cacheFetch($info['dada_sn']);
        }elseif($type == 'finish'){
            $response = $dada->cacheFinish($info['dada_sn']);
        }elseif($type == 'cancel'){
            $response = $dada->cacheCancel($info['dada_sn'],$reason);
        }elseif($type == 'expire'){
            $response = $dada->cacheExpire($info['dada_sn']);
        }else{
            $response = [];
        }
        return Response::json(['errcode'=>0, 'errmsg'=>'ok','type'=>$type,'response'=>$response]);
    }

}

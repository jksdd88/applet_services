<?php

/*
 * 首页后台
 * shangyazhao@dodoca.com
 *
 */

namespace App\Http\Controllers\Weapp\Express;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Response;
use Illuminate\Http\Request;
use App\Facades\Member;
use App\Models\ExpressOrder;
use App\Models\OrderInfo;
use App\Services\ExpressService;
use App\Utils\CacheKey;
use App\Utils\Dada\DadaOrder;
use Config;
use Cache;

class IndexController extends Controller {

    private $request;
    private $member_id;
    private $merchant_id;
    private $appid;
    private $app_key;
    private $app_secret;
    private $merchant_dada;
    private $env;

    public function __construct(Request $request) {
        $this->request = $request;
        $this->member_id = Member::id();
        $this->merchant_id = Member::merchant_id();
        $this->appid = Member::appid();

        $this->app_key  = Config::get('express.app_key');
        $this->app_secret = Config::get('express.app_secret');

        $expressService = new ExpressService();
        $this->merchant_dada = $expressService->getDadaMerchant($this->merchant_id);
        $this->env =  $expressService->getEnv($this->merchant_id);
    }

    public function index(){
        $order_id = $this->request->get('order_id');
        if(empty($order_id)){
            return Response::json(['errcode'=>1, 'errmsg'=>'请传入订单id']);
        }
        $check = OrderInfo::get_data_by_id($order_id,$this->merchant_id);
        if(!isset($check['id']) || $check['member_id'] != $this->member_id){
            return Response::json(['errcode'=>1, 'errmsg'=>'请传正确入订单id']);
        }
        $dada = (new DadaOrder())->setConfig($this->app_key, $this->app_secret, $this->merchant_dada,$this->env);
        $filed = ['id','dada_sn','waybill_sn','package_id','total','status','courier_name','courier_mobile','courier_lng','courier_lat','courier_distance','store_address','delivery_time','accept_time','fetch_time','finish_time','cancel','reason','cancel_time'];
        $list = ExpressOrder::query()->where(['order_id'=>$order_id,'is_delete'=>1])->where('status','>',0)->where('status','<',15)->where('package_id','>',0)->get($filed)->toArray();
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
                    ExpressOrder::update_data('id',$v['id'],[  'courier_lng' =>  $response['result']['transporterLng'], 'courier_lat' =>  $response['result']['transporterLat'], 'courier_distance' => $response['result']['distance']  ]);
                    $list[$k]['courier_distance'] = $response['result']['distance'];
                    $list[$k]['courier_lng'] = $response['result']['transporterLng'];
                    $list[$k]['courier_lat'] = $response['result']['transporterLat'];
                }
                Cache::put($cacheKey,1,1);
            }else{
                $list[$k]['courier_lat'] = '0.000000';
                $list[$k]['courier_lng'] = '0.000000';
            }
        }
        return Response::json(['errcode'=>0, 'errmsg'=>'成功','data'=>$list]);
    }


}

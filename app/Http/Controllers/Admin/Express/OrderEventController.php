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
use App\Models\ExpressOrderLog;
use App\Services\ExpressService;
use Illuminate\Support\Facades\Response;
use Illuminate\Http\Request;
use Config;
use Cache;
use Log;

class OrderEventController extends Controller {

    private $request;

    public function __construct(Request $request) {
        $this->request = $request;
    }

    //回调
    public function callback(){
        $paramtxst = file_get_contents('php://input');
        $param = json_decode($paramtxst,true);
        if(!isset($param['signature']) || !isset($param['order_status']) ||  !isset($param['order_id']) ||  !isset($param['client_id']) || !isset($param['update_time'])){
            return Response::json(['errcode'=>0, 'errmsg'=>'ok']);
        }

        $check = $this->signature($param['signature'],$param['client_id'],$param['order_id'],$param['update_time']);
        if(!$check){
            Log::info('admin_express_event:'.$paramtxst);
            //return Response::json(['errcode'=>0, 'errmsg'=>'ok']);
        }

        if($param['order_status'] > 100){
            return Response::json(['errcode'=>0, 'errmsg'=>'ok']);
        }

        $info = ExpressOrder::get_dada_waybill($param['order_id'],$param['client_id']);
        //事件日志
        ExpressOrderLog::insert_data(['merchant_id'=>isset($info['merchant_id'])?$info['merchant_id']:0,'express_order'=>isset($info['id'])?$info['id']:0,'comment'=>$paramtxst]);
        if(!isset($info['id'])){
            return Response::json(['errcode'=>0, 'errmsg'=>'ok']);
        }
        //更新运单
        if(empty($info['waybill_sn'])){
            ExpressOrder::update_data('id',$info['id'],['waybill_sn'=>$param['client_id']]);
        }
        //更新状态 时间戳队列
        ExpressOrder::update_by_time($info['id'],$param['update_time'],['status'=>$param['order_status']]);
        //取消
        if($param['order_status'] == 5  && isset($param['cancel_from']) && $param['cancel_from'] != 0){
            ExpressOrder::update_data('id',$info['id'],['cancel'=>$param['cancel_from'],'reason'=>$param['cancel_reason'],'cancel_time'=>date('Y-m-d H:i:s',$param['update_time'])]);
        }
        //接单中 更新
        if(in_array($param['order_status'],[2,3,4,8])  && isset($param['dm_id']) &&  !empty($param['dm_id'])){
            $data = [];
            if(isset($param['dm_id'])) $data['courier'] = $param['dm_id'];
            if(isset($param['dm_name'])) $data['courier_name'] = $param['dm_name'];
            if(isset($param['dm_mobile'])) $data['courier_mobile'] = $param['dm_mobile'];

            if($param['order_status'] == 2){
                $data['accept_time']  = date('Y-m-d H:i:s',$param['update_time']);
            }elseif ($param['order_status'] == 3){
                $data['fetch_time']  = date('Y-m-d H:i:s',$param['update_time']);
            }elseif ($param['order_status'] == 4){
                $data['finish_time']  = date('Y-m-d H:i:s',$param['update_time']);
            }
            if(!empty($data)){
                ExpressOrder::update_data('id',$info['id'],$data);
            }
        }
        return Response::json(['errcode'=>0, 'errmsg'=>'ok']);
    }

    private function signature($signature, $client_id, $order_id, $update_time){
        $param = [ $client_id , $order_id , $update_time ];
        sort($param);
        $str = '';
        foreach ($param as $k => $v) {
            $str .= $v;
        }
        $str = md5($str);
        return $signature == $str ;
    }

}

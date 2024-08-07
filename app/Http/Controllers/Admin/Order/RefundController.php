<?php

namespace App\Http\Controllers\Admin\Order;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\BuyService;
use App\Models\OrderRefund;
use App\Models\OrderRefundLog;
use App\Models\OrderPackage;
use Validator;

class RefundController extends Controller
{
    private $request;
    private $merchant_id;
    private $refund_way = [ '仅退款', '退货并退款'];


    public function __construct(Request $request)
    {
        $this->request = $request;
        $this->merchant_id = Auth::user()->merchant_id;
    }

    public function getRefund(){

    }

    public function postRefund(){

        $refund_id   = $this->request->input('refund_id'); //refund_id
        $apply_type  = $this->request->input('apply_type');//1 同意退款 2 拒绝退款 3 确认收货
        $reason      = $this->request->input('reason');//reason
        $address     = $this->request->input('address');//address
        $refundInfo = OrderRefund::get_data_by_id($refund_id,$this->merchant_id);
        if(!$refundInfo || !isset($refundInfo['id'])){
            return ['errcode'=>40061,'errmsg'=>'退款id不合法！'];
        }
        if($apply_type == 1 && $refundInfo['refund_type'] == 0) { //同意 仅退款
            if($refundInfo['status'] != REFUND_AGAIN && $refundInfo['status'] != REFUND && $refundInfo['status'] != REFUND_REFUSE ){
                return ['errcode' => 41009, 'errmsg' => '退款当前状态不可进行此操作'];
            }
            $response = OrderRefund::update_data($refundInfo['id'],$this->merchant_id,['status' => REFUND_AGREE ,'operated_time'=>date('Y-m-d H:i:s')]);
            if(!$response){
                return ['errcode' => 3, 'errmsg' => '操作失败'];
            }
            //退款
            (new BuyService())->orderrefund([  'merchant_id'	=>	$this->merchant_id,	 'order_id'		=>	$refundInfo['order_id'],	  'apply_type'    =>	1 ,  'refund_id'     => $refundInfo['id']]);

            $this->log($refundInfo['id'],'卖家同意退款', [  ['name'=> '同意时间' ,'value'=> date('Y-m-d H:i:s') ] ]);
        }
        else if($apply_type == 1 && $refundInfo['refund_type'] == 1){ //同意 退货退款
            if(empty($address)){
                return  ['errcode'=>40067,'errmsg'=>'收货地址不能为空！'];
            }
            if($refundInfo['status'] != REFUND_AGAIN && $refundInfo['status'] != REFUND && $refundInfo['status'] != REFUND_REFUSE){
                return ['errcode' => 41009, 'errmsg' => '退款当前状态不可进行此操作'];
            }
            $response = OrderRefund::update_data($refundInfo['id'],$this->merchant_id,['address' => $address, 'status' => REFUND_AGREE  ,'operated_time'=>date('Y-m-d H:i:s')]);
            if(!$response){
                return ['errcode' => 1, 'errmsg' => '操作失败'];
            }
            $this->log($refundInfo['id'],'卖家同意退款', [  ['name'=> '退货地址' ,'value'=> $address ] ]);
        }
        else if($apply_type == 2) { //不同意
            if(empty($reason)){
                return  ['errcode'=>40066,'errmsg'=>'拒绝理由不能为空！'];
            }
            if($refundInfo['status'] != REFUND_AGAIN && $refundInfo['status'] != REFUND ){
                return ['errcode' => 41009, 'errmsg' => '退款当前状态不可进行此操作'];
            }
            $response = OrderRefund::update_data($refundInfo['id'],$this->merchant_id,[ 'status' => REFUND_REFUSE ,'operated_time'=>date('Y-m-d H:i:s'),'refuse_time'=>date('Y-m-d H:i:s')]);
            if(!$response){
                return ['errcode' => 1, 'errmsg' => '操作失败'];
            }
            $this->log($refundInfo['id'],'卖家拒绝退款', [  ['name'=> '拒绝原因' ,'value'=> $reason ] ]);
        }
        else if($apply_type == 3 && $refundInfo['refund_type'] == 1){//退货退款  确认收货
            if($refundInfo['status'] != REFUND_SEND){
                return ['errcode' => 41009, 'errmsg' => '退款当前状态不可进行此操作'];
            }
            $response = OrderRefund::update_data($refundInfo['id'],$this->merchant_id,['status' => REFUND_TRADING ,'applyed_time'=>date('Y-m-d H:i:s')]);
            if(!$response){
                return ['errcode' => 1, 'errmsg' => '操作失败'];
            }
            $this->log($refundInfo['id'],'卖家确认收货', [  ['name'=> '确认时间' ,'value'=> date('Y-m-d H:i:s') ] ]);
            //退款
            (new BuyService())->orderrefund([  'merchant_id'	=>	$this->merchant_id,	 'order_id'		=>	$refundInfo['order_id'],	  'apply_type'    =>	1 ,  'refund_id'     => $refundInfo['id']]);

            $this->log($refundInfo['id'],'卖家同意退款', [  ['name'=> '同意时间' ,'value'=> date('Y-m-d H:i:s') ] ]);
        }else{
            return ['errcode'=>40061,'errmsg'=>'apply_type'];
        }
        return ['errcode'=>0,'errmsg'=>'操作成功'];

    }
	
	//结束维权
	public function MerRefundOver(){

        $refund_id   = $this->request->input('refund_id');
        $refundInfo = OrderRefund::get_data_by_id($refund_id,$this->merchant_id);
        if(!$refundInfo || !isset($refundInfo['id'])){
            return ['errcode'=>40061,'errmsg'=>'退款id不合法！'];
        }
		if($refundInfo['status']!=REFUND_REFUSE) {
			return ['errcode'=>40061,'errmsg'=>'该退款不允许手动结束'];
		}
		if(strtotime($refundInfo['refuse_time'])+7*3600>time()) {
			return ['errcode'=>40061,'errmsg'=>'该退款不允许手动结束'];
		}
		
		$response = OrderRefund::update_data($refundInfo['id'],$this->merchant_id,['status' => REFUND_MER_CANCEL]);
		if(!$response){
			return ['errcode' => 1, 'errmsg' => '操作失败'];
		}
		$this->log($refundInfo['id'],'超时未操作，商家手动关闭', [  ['name'=> '操作时间' ,'value'=> date('Y-m-d H:i:s') ] ]);
        return ['errcode'=>0,'errmsg'=>'操作成功'];

    }

    private function log($refund_id,$status_str,$detaill){
        return OrderRefundLog::insert_data([  'merchant_id' => $this->merchant_id,  'order_refund_id' => $refund_id,  'who' => '卖家',  'status_str' => $status_str, 'detaill'  => json_encode($detaill,JSON_UNESCAPED_UNICODE)]);
    }


}

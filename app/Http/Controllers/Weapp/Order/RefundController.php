<?php

namespace App\Http\Controllers\Weapp\Order;

use App\Http\Controllers\Controller;

use App\Services\WeixinService;
use Illuminate\Http\Request;
use App\Facades\Member;
use App\Models\OrderInfo;
use App\Models\OrderGoods;
use App\Models\OrderGoodsUmp;
use App\Models\OrderAddr;
use App\Models\OrderRefund;
use App\Models\OrderRefundLog;
use App\Models\DeliveryCompany;
use App\Models\Member as user;
use App\Models\Merchant;
use App\Models\MerchantDelivery;
use App\Models\WeixinLog;
use App\Models\MemberInfo;
use App\Models\OrderPackage;
use App\Services\ShipmentService;
use App\Services\WeixinMsgService;
use App\Services\VirtualGoodsService;
use Log;

use Config;

class RefundController extends Controller
{
    private $request;
    private $member_id;
    private $merchant_id;
    private $refund_way = [ '仅退款', '退货并退款'];


    public function __construct(Request $request)
    {
        $this->request = $request;
        $this->member_id = Member::id();
        $this->merchant_id = Member::merchant_id();
    }

    public function getRefund(){
        $order_id       = (int)$this->request->get('order_id',0);
        $order_goods_id = (int)$this->request->get('order_goods_id',0);
        $quantity       = (int)$this->request->get('quantity',0);
        $refund_id      = (int)$this->request->get('refund_id',0);
        if($order_id <= 0 || $order_goods_id <= 0 ) {
            return ['errcode' => 41001, 'errmsg' => '缺少退款参数'];
        }
        $orderInfo  = OrderInfo:: get_data_by_id($order_id,$this->merchant_id);
        if(!$orderInfo || $orderInfo['member_id'] != $this->member_id){
            return ['errcode' => 40005, 'errmsg' => '订单不存在' ];
        }
        if($orderInfo['is_finish'] == 1){
            return ['errcode' => 40005, 'errmsg' => '订单已完成，不能在退款哦！' ];
        }
        if($orderInfo['pay_status'] != 1){
            return ['errcode' => 40005, 'errmsg' => '订单未支付，不可以退款哦！' ];
        }
        $orderGoods = OrderGoods::get_data_by_id($order_goods_id,$this->merchant_id);
        if(!$orderGoods || $orderGoods['order_id'] != $order_id || $orderGoods['member_id'] != $this->member_id){
            return ['errcode' => 40005, 'errmsg' => '订单商品不存在'];
        }
        if($orderGoods['quantity'] <= $orderGoods['refund_quantity']){
            return ['errcode' => 41002, 'errmsg' => '退款数量已完成.'];
        }
        if( $refund_id > 0 ){
            $checkRefund = OrderRefund:: get_data_by_id($refund_id,$this->merchant_id);
        }else{
            $checkRefund = OrderRefund::query()->where(['order_id'=>$order_id,'goods_id'=>$orderGoods['goods_id'],'spec_id'=>$orderGoods['spec_id']])->orderBy('id','DESC')->first();
        }
        if(isset($checkRefund) && $checkRefund['id']){
            if(!in_array($checkRefund['status'],[REFUND_REFUSE,REFUND_FINISHED,REFUND_CLOSE,REFUND_CANCEL,REFUND_MER_CANCEL])  ){
                return ['errcode' => 41006, 'errmsg' => '订单退款中'];
            }
            if($checkRefund['status'] == REFUND_REFUSE){
                $refundInfo = ['way'=>$checkRefund['refund_type'],'reason'=>$checkRefund['reason'],'quantity'=>$checkRefund['quantity'],'price'=>$checkRefund['amount'],'describe'=>$checkRefund['memo'],'freight'=>$checkRefund['shipment_fee'],'images'=>$checkRefund['images']];
            }
            $refund_id = $checkRefund['id'];
        }

        $maxQuantity = 0;
        //数量
        if($orderInfo['order_goods_type'] == ORDER_GOODS_VIRTUAL){ //虚拟商品订单，退款数量判断
            $virtualGoodsService = new VirtualGoodsService();
            $maxQuantity = $virtualGoodsService->notHexiaoNum($orderInfo);//未核销次数
            if($maxQuantity == 0){
                return ['errcode' => 41002, 'errmsg' => '退款数量已完成;'];
            }
            $finishHexiaoNum = $virtualGoodsService->finishHexiaoNum($orderInfo);
        }else{ //普通商品
            $maxQuantity = $orderGoods['quantity'] - $orderGoods['refund_quantity'];
        }
        $quantity = ($quantity > $maxQuantity || $quantity <= 0 ) ? $maxQuantity : $quantity;
        //积分
        $orderGoodsUmp = OrderGoodsUmp::query()->where(['order_id'=>$order_id,'goods_id'=>$orderGoods['goods_id'],'spec_id'=>$orderGoods['spec_id'],'ump_type'=>3])->first();//积分
        if($orderGoodsUmp){
            if($quantity == $maxQuantity){
                $integral =  $orderGoodsUmp['credit'] -  OrderRefund::select_integral($orderGoods['order_id'], $orderGoods['goods_id'],$orderGoods['spec_id']);
                if($orderInfo['order_goods_type'] == ORDER_GOODS_VIRTUAL){
                    $integral -= $finishHexiaoNum * intval( $orderGoodsUmp['credit'] /  $orderGoods['quantity']) ;
                }
            }else{
                $integral =  $quantity * intval($orderGoodsUmp['credit'] /  $orderGoods['quantity']) ;//
            }
        }else{
            $integral = 0;
        }
        //运费
        $freight_price = 0;
        $freight_price_all = 0;
		if($orderInfo['delivery_type'] == 3) {	//同城配送
			$is_refund_fee = 1;	//是否能运费
			$order_goods_count = OrderGoods::where(['order_id'=>$order_id])->where('id','!=',$orderGoods['id'])->whereRaw('(quantity-refund_quantity>0)',[])->count();
			if($order_goods_count) {
				$is_refund_fee = 0;
			}
			if($is_refund_fee==1 && $quantity == $maxQuantity){
				$freight_price = $orderInfo['shipment_fee'];
			} else {
				$freight_price = 0;
			}
		} else {
			if($orderInfo['shipment_fee'] > 0 && $orderGoods['is_pinkage'] === 0){
				$freight_responseBF = $this->getShipmentFee($order_id);
				$freight_responseAF = $this->getShipmentFee($order_id,$order_goods_id,$quantity);
				$freight_responseBF['shipment_fee']  = $freight_responseBF['shipment_fee'] > $orderInfo['shipment_fee'] ? $orderInfo['shipment_fee'] :  $freight_responseBF['shipment_fee'];
				if($freight_responseBF['status'] && $freight_responseAF['status'] && ($freight_responseBF['shipment_fee'] - $freight_responseAF['shipment_fee']) > 0){
					$freight_price = $freight_responseBF['shipment_fee'] - $freight_responseAF['shipment_fee'];
				}
			}
		}
        //退款
        if($quantity == $maxQuantity){
            //(new WeixinService())->setLog('lingshi20180411',['order_id'=>$order_id],['pay_price'=>$orderGoods['pay_price'],'refund'=>round($orderGoods['refund_quantity'] * $orderGoods['pay_price'] /  $orderGoods['quantity'],2 )],0,$order_id);
            $refundPrice = $orderGoods['pay_price'] -  $orderGoods['refund_quantity'] *  round($orderGoods['pay_price'] /  $orderGoods['quantity'],2 );
            if($orderInfo['order_goods_type'] == ORDER_GOODS_VIRTUAL){
                $refundPrice -= $finishHexiaoNum *  round($orderGoods['pay_price'] /  $orderGoods['quantity'],2 )  ;
            }
        }else{
            $refundPrice = $quantity *  round($orderGoods['pay_price'] /  $orderGoods['quantity'],2 );
        }
        //退款类型
        if($orderGoods['shipped_quantity'] <= 0){
            unset( $this->refund_way[1]);
        }
        $refund_way = [];
        foreach ($this->refund_way as $k => $v) {
            $refund_way[] = [ 'id' => $k , 'name' => $v ];
        }
        $reason  = [];
        foreach (Config::get('config.refund_reason') as $k => $v) {
            $reason[]  = [ 'id' => $k , 'name' => $v ];
        }
        $data = [
            'goods_name'     => $orderGoods['goods_name'] ,
            'goods_img'      => $orderGoods['goods_img'],
            'goods_props'    => $orderGoods['props'],
            'goods_quantity' => $orderGoods['quantity'],
            'goods_price'    => $orderGoods['price'] ,
            'original_price' => $orderGoods['y_price'],
            'pay_price'      => $orderGoods['pay_price'],
            'pay_type'       => $orderInfo['pay_type'],     //支付方式
            'order_no'       => $orderInfo['order_sn'],
            'order_type'       => $orderInfo['order_type'],
            'order_time'     => substr($orderInfo['created_time']->toDateTimeString(),0,16) ,
            'way'            => $refund_way,
            'reason'         => $reason,
            'refund_quantity'=> $maxQuantity ,
            'refund_price'   => $refundPrice,
            'refund_avg_price'   =>  round( ($orderGoods['pay_price'] /  $orderGoods['quantity']),2 ),
            'integral'      => $integral,
            'freight_price' => $freight_price,
            'freight_responseBF' => isset($freight_responseBF)? $freight_responseBF:'null',
            'freight_responseAF' => isset($freight_responseAF) ? $freight_responseAF:'null',
            'freight_price_all' => $freight_price_all,

        ];
        if(isset($refundInfo)) $data['refundInfo'] = $refundInfo;
        return ['errcode' => 0, 'errmsg' => 'ok', 'data' => $data ];
    }

    public function postRefund(){
        $order_id       = (int)$this->request->input('order_id');
        $order_goods_id = (int)$this->request->input('order_goods_id');
        $refund_id      = (int)$this->request->input('refund_id');
        $way            = (int)$this->request->input('way');
        $reason         = (int)$this->request->input('reason');
        $quantity       = (int)$this->request->input('quantity');
        $price          = (float)$this->request->input('price');
        $image          = json_encode($this->request->input('image',[]));
        $describe       = (string)$this->request->input('describe');
        if($order_id <= 0 ||  $order_goods_id <= 0 || $way < 0 || $reason < 0 || $quantity <= 0  || $price < 0 ) {
            return ['errcode' => 41001, 'errmsg' => '缺少退款参数'];
        }
        $reasonList  = Config::get('config.refund_reason');
        if(!isset($reasonList[$reason])){
            return ['errcode' => 41003, 'errmsg' => '退款原因参数错误'];
        }
        $orderInfo  = OrderInfo::get_data_by_id($order_id,$this->merchant_id);//
        if(!$orderInfo  || $orderInfo['member_id'] != $this->member_id){
            return ['errcode' => 40005, 'errmsg' => '订单不存在' ];
        }
        if($orderInfo['is_finish'] == 1){
            return ['errcode' => 40005, 'errmsg' => '订单已完成，不能在退款哦！' ];
        }
        if($orderInfo['pay_status'] != 1){
            return ['errcode' => 40005, 'errmsg' => '订单未支付，不可以退款哦！' ];
        }
        $orderGoods = OrderGoods::get_data_by_id($order_goods_id,$this->merchant_id);
        if(!$orderGoods || $orderGoods['member_id'] != $this->member_id || $orderGoods['order_id'] != $orderInfo['id']){
            return ['errcode' => 40005, 'errmsg' => '订单商品不存在'];
        }
        //再退款
        if( $refund_id > 0 ){
            $checkRefund = OrderRefund:: get_data_by_id($refund_id,$this->merchant_id);
        }else{
            $checkRefund = OrderRefund::query()->where(['order_id'=>$order_id,'goods_id'=>$orderGoods['goods_id'],'spec_id'=>$orderGoods['spec_id']])->orderBy('id','DESC')->first();
        }
        if(isset($checkRefund) && $checkRefund['id'] ){
            if( !in_array($checkRefund['status'],[REFUND_REFUSE,REFUND_FINISHED,REFUND_CLOSE,REFUND_CANCEL,REFUND_MER_CANCEL])){//,,
                return ['errcode' => 41006, 'errmsg' => '订单退款中'];
            }
            if($checkRefund['status'] == REFUND_REFUSE || $checkRefund['status'] == REFUND_CANCEL || $checkRefund['status'] == REFUND_MER_CANCEL){
                $refund_id = $checkRefund['id'];
            }else{
                $refund_id = 0 ;
            }
        }else{
            $refund_id = 0 ;
        }
        if($orderGoods['shipped_quantity'] <= 0){
            unset( $this->refund_way[1]);
        }

        //虚拟商品订单
        $maxQuantity = 0;//虚拟商品订单，未核销次数
        if($orderInfo['order_goods_type'] == ORDER_GOODS_VIRTUAL){
            $virtualGoodsService = new VirtualGoodsService();
            $maxQuantity = $virtualGoodsService->notHexiaoNum($orderInfo);//未核销次数
            $finishHexiaoNum = $virtualGoodsService->finishHexiaoNum($orderInfo);
        }else{
            $maxQuantity = $orderGoods['quantity'] - $orderGoods['refund_quantity'];
        }
        //退款数量判断
        if($maxQuantity == 0){
            return ['errcode' => 41002, 'errmsg' => '退款数量已完成'];
        }
        if($maxQuantity < $quantity){
            return ['errcode' => 41003, 'errmsg' => '退款数量不能大于'.$maxQuantity ];
        }

        if($quantity == $maxQuantity){
            $maxPrice = $orderGoods['pay_price'] -  $orderGoods['refund_quantity'] *  round($orderGoods['pay_price'] /  $orderGoods['quantity'],2 );
            if($orderInfo['order_goods_type'] == ORDER_GOODS_VIRTUAL){
                $maxPrice -= $finishHexiaoNum *  round($orderGoods['pay_price'] /  $orderGoods['quantity'],2 )  ;
            }
        }else{
            $maxPrice = $quantity * round($orderGoods['pay_price'] /  $orderGoods['quantity'],2 );
        }
        //退款
        if($price >  $maxPrice ){
            return ['errcode' => 41004, 'errmsg' => '退款金额不能大于￥'.$maxPrice];
        }
        
        //运费
        $freight_price =  0 ;
		if($orderInfo['delivery_type'] == 3) {	//同城配送
			$is_refund_fee = 1;	//是否能运费
			$order_goods_count = OrderGoods::where(['order_id'=>$order_id])->where('id','!=',$orderGoods['id'])->whereRaw('(quantity-refund_quantity>0)',[])->count();
			if($order_goods_count) {
				$is_refund_fee = 0;
			}			
			if($is_refund_fee==1 && $quantity == $maxQuantity){
				$freight_price = $orderInfo['shipment_fee'];
			} else {
				$freight_price = 0;
			}
		} else {
			if($orderInfo['shipment_fee'] > 0 &&  $orderGoods['is_pinkage'] === 0){
				$freight_responseBF = $this->getShipmentFee($order_id);
				$freight_responseAF = $this->getShipmentFee($order_id,$order_goods_id,$quantity);
				$freight_responseBF['shipment_fee']  = $freight_responseBF['shipment_fee'] > $orderInfo['shipment_fee'] ? $orderInfo['shipment_fee'] :  $freight_responseBF['shipment_fee'];
				if($freight_responseBF['status'] && $freight_responseAF['status'] && ($freight_responseBF['shipment_fee'] - $freight_responseAF['shipment_fee']) > 0){
					$freight_price = $freight_responseBF['shipment_fee'] - $freight_responseAF['shipment_fee'];
				}
			}
		}
        //积分
        $orderGoodsUmp = OrderGoodsUmp::query()->where(['order_id'=>$order_id,'goods_id'=>$orderGoods['goods_id'],'spec_id'=>$orderGoods['spec_id'],'ump_type'=>3])->first();//积分
        if($orderGoodsUmp){
            if($quantity == $maxQuantity){
                $integral =  $orderGoodsUmp['credit'] -  OrderRefund::select_integral($orderGoods['order_id'], $orderGoods['goods_id'],$orderGoods['spec_id']);
                if($orderInfo['order_goods_type'] == ORDER_GOODS_VIRTUAL){
                    $integral -= $finishHexiaoNum *  intval($orderGoodsUmp['credit'] /  $orderGoods['quantity']) ;
                }
            }else{
                $integral =  $quantity * intval($orderGoodsUmp['credit'] /  $orderGoods['quantity']) ;//
            }
        }else{
            $integral = 0;
        }
        //更新
        if($refund_id){
            $response  = OrderRefund::update_data($refund_id,$this->merchant_id,[ 'refund_type' => $way, 'refund_quantity'  => $quantity, 'amount'  => $price ,  'shipment_fee' => $freight_price , 'integral' => $integral,  'reason' => $reasonList[$reason],'memo' => $describe  , 'images' => $image , 'status' => REFUND_AGAIN]);
            $logTitle = '买家再次发起退款';
        }else{
            $response = $refund_id = OrderRefund::insert_data([ 'merchant_id' => $orderInfo['merchant_id'],  'order_id'  => $orderInfo['id'], 'goods_id'  => $orderGoods['goods_id'],  'spec_id' => $orderGoods['spec_id'],  'member_id' => $orderInfo['member_id'],  'refund_type' => $way,'order_sn' => $orderInfo['order_sn'], 'refund_quantity'  => $quantity, 'amount'  => $price ,  'shipment_fee' =>  $freight_price, 'integral' => $integral,  'reason' => $reasonList[$reason],'memo' => $describe, 'images' => $image ]);
            $logTitle = '发起申请，等待商家处理';
        }
        if($response){//日志
            OrderInfo::update_data($orderInfo['id'],$this->merchant_id,['refund_status'=>1]);
            $this->setlog($refund_id,$logTitle,[
                ['name'=> '退款原因' ,'value'=> $reasonList[$reason] ],
                ['name'=> '期望结果' ,'value'=> $this->refund_way[$way] ],
                ['name'=> '退货数量' ,'value'=> $quantity ],
                ['name'=> '退款金额' ,'value'=> '￥'.$price ],
                ['name'=> '退货运费' ,'value'=> '￥'.$freight_price ],
                ['name'=> '退款说明' ,'value'=> $describe ],
                ['name'=> '退款凭证' ,'value'=> $image ,'type' => 'image'],
                ['name'=> '退货积分' ,'value'=> $integral ]
            ]);
            $member_info = user::get_data_by_id($this->member_id,$this->merchant_id);
            (new WeixinMsgService())->refundApplication([
                'merchant_id'=>$orderInfo['merchant_id'],
                'no'=>$orderInfo['order_sn'],
                'price' => '￥'.($price+$freight_price),
                'buyer' => $member_info['name'] ,
                'reason' => $reasonList[$reason]
            ]);
        }
        return ['errcode' => 0, 'errmsg' => '提交成功', 'data' =>  ['refund_id' => $refund_id] ];
    }

    public function info(){
        $refund_id = $this->request->get('id');
        if(!isset($refund_id)) {
            return ['errcode' => 40060, 'errmsg' => '退款id不能为空'];
        }
        $orderRefund = OrderRefund::get_data_by_id($refund_id,$this->merchant_id);//query()->where(['id'=>$refund_id])->first();
        if(!$orderRefund ||  $orderRefund['member_id'] != $this->member_id){
            return ['errcode' => 40061, 'errmsg' => '退款id数据不合法'];
        }
        $statumsg = Config::get('config.refund_status');
        $refundLog = OrderRefundLog::query()->where(['order_refund_id' =>$refund_id ,'merchant_id'=>$this->merchant_id])-> orderBy('id', 'DESC')->first();
        $ordergoods= OrderGoods::query()->where(['order_id'=>$orderRefund['order_id'],'goods_id'=>$orderRefund['goods_id'],'spec_id'=>$orderRefund['spec_id']])->first();
        if(!isset($ordergoods['id']) || !$ordergoods['id'] || $ordergoods['merchant_id'] != $this->merchant_id || $ordergoods['member_id'] != $this->member_id){
            return ['errcode' => 40061, 'errmsg' => '退款id数据不合法'];
        }
        $tipmsg = $this->tipmsg($orderRefund['status']);

        //再次申请按钮是否显示，1显示  0不显示
        $orderInfo  = OrderInfo::get_data_by_id($orderRefund['order_id'],$this->merchant_id);
        if($orderRefund['status'] == 21 || $orderRefund['status'] == 41 || $orderRefund['status'] == 42){
            $if_again_refund = 1;
        }else{
            $if_again_refund = 0;
        }
        
        //虚拟商品订单
        if($orderInfo['order_goods_type'] == ORDER_GOODS_VIRTUAL){
        
            $virtualGoodsService = new VirtualGoodsService();
        
            $virtual_not_num = $virtualGoodsService->notHexiaoNum($orderInfo);//未核销次数
        
            if($virtual_not_num == 0){
                $if_again_refund = 0;
            }
        }

        $data = [
            'refund_id'      => $refund_id,
            'order_id'      => $orderRefund['order_id'],
            'order_goods_id'   => $ordergoods['id'],
            'status'      => $orderRefund['status'],
            'status_msg'  => $tipmsg['title'],////标题
            'status_test' => ($orderRefund['refund_type'] == 1 &&  $orderRefund['status'] == REFUND_AGREE && isset($tipmsg['tests']))? $tipmsg['tests']:$tipmsg['test'],//描述
            'status_time' => substr($refundLog['created_time']->toDateTimeString(),0,16),
            'status_way'  => $orderRefund['refund_type'],
            'way'         => $this->refund_way[$orderRefund['refund_type']],
            'reason'      => $orderRefund['reason'],
            'quantity'    => $orderRefund['refund_quantity'],
            'price'       => '￥'.$orderRefund['amount'],
            'freight_price' => '￥'.$orderRefund['shipment_fee'],
            'integral'    => $orderRefund['integral'],
            'describe'    => $orderRefund['memo'],
            'if_again_refund' => $if_again_refund
        ];
        return ['errcode' => 0, 'errmsg' => 'ok', 'data' => $data ];
    }

    public function details(){
        $refund_id = $this->request->get('id');
        if(!isset($refund_id)) {
            return ['errcode' => 40060, 'errmsg' => '退款id不能为空'];
        }
        $orderRefund = OrderRefund::get_data_by_id($refund_id,$this->merchant_id);
        if(!$orderRefund  || $orderRefund['member_id'] != $this->member_id ){
            return ['errcode' => 40061, 'errmsg' => '退款id数据不合法'];
        }
        $orderInfo  = OrderInfo::get_data_by_id($orderRefund['order_id'],$this->merchant_id);
        if(!$orderInfo || $orderInfo['merchant_id'] != $this->merchant_id || $orderInfo['member_id'] != $this->member_id){
            return ['errcode' => 40061, 'errmsg' => '退款id数据不合法'];
        }
        $orderGoods = OrderGoods::query()->where(['order_id'=>$orderRefund['order_id'],'goods_id'=>$orderRefund['goods_id'],'spec_id'=>$orderRefund['spec_id']])->first();
        if(!$orderGoods || $orderGoods['member_id'] != $this->member_id){
            return ['errcode' => 40061, 'errmsg' => '退款id数据不合法'];
        }
		if($orderInfo['order_type']==ORDER_APPOINT) {	//预约订单，规格不显示
			$orderGoods['props'] = '';
		}
        $payType = Config::get('varconfig.order_info_pay_type');
        if($orderGoods['shipped_quantity'] <= 0){
            unset( $this->refund_way[1]);
        }
        //运费
        /*
        $freight_price = 0;
        if($orderInfo['shipment_fee'] > 0 && $orderGoods['is_pinkage'] === 0){
            $freight_responseBF = $this->getShipmentFee($orderRefund['order_id']);
            $freight_responseAF = $this->getShipmentFee($orderRefund['order_id'],$orderGoods['id'],$orderGoods['quantity'] );
            if($freight_responseBF['status'] && $freight_responseAF['status'] && ($freight_responseBF['shipment_fee'] - $freight_responseAF['shipment_fee']) > 0){
                $freight_price = $freight_responseBF['shipment_fee'] - $freight_responseAF['shipment_fee'];
            }
        }
        */
        //log
        $record  = [] ;
        $refundLog = OrderRefundLog::query()->where(['order_refund_id' =>$refund_id ,'merchant_id'=>$this->merchant_id])->get()->toArray();
        if($refundLog){
            $buyers = (new user())->get_data_by_id($this->member_id,$this->merchant_id);
            $seller = (new Merchant())->get_data_by_id($this->merchant_id);
            $buyers = isset($buyers->name) ? $buyers->name : '';
            $seller = isset($seller->company) ? $seller->company : '';
            foreach ($refundLog as $k => $v) {
                $detaill = json_decode($v['detaill'],true);
                foreach ($detaill as $ks => $kv) {
                    if(isset($kv['type']) && $kv['type'] == 'image'){
                        $detaill[$ks]['value'] = json_decode($kv['value'],true);
                    }
                }
                $record[] = [  'who'=> $v['who'],'who_type'=> $v['who'] == '买家' ? 1 : 2 , 'who_name'=> $v['who'] == '买家' ? $buyers : $seller  , 'time' => $v['created_time'], 'title' => $v['status_str'] , 'detaill' => $detaill ] ;
            }
        }
        $data = [
            'goods_name'     => $orderGoods['goods_name'] ,
            'goods_img'      => $orderGoods['goods_img'],
            'goods_props'    => $orderGoods['props'],
            'goods_quantity' => $orderGoods['quantity'],
            'goods_price'    => $orderGoods['price'] ,
            'original_price' => $orderGoods['y_price'],
            'pay_price'      => $orderGoods['pay_price'],
            'pay_type'       => $orderInfo['pay_type'],     //支付方式
            'order_no'       => $orderInfo['order_sn'],
            'refund_no'      => $orderRefund['id'],
            'refund_time'    => substr($orderRefund['created_time'],0,16) ,
            'record' => $record
        ];
        return ['errcode' => 0, 'errmsg' => 'ok', 'data' => $data ];
    }

    public function getLogistics(){
        $refund_id = $this->request->get('id');
        if(!isset($refund_id)) {
            return ['errcode' => 40060, 'errmsg' => '退款id不能为空'];
        }
        $orderRefund = OrderRefund::get_data_by_id($refund_id,$this->merchant_id);//query()->where(['id'=>$refund_id])->first();
        if(!$orderRefund || $orderRefund['merchant_id'] != $this->merchant_id || $orderRefund['member_id'] != $this->member_id){
            return ['errcode' => 40061, 'errmsg' => '退款id数据不合法'];
        }
        if($orderRefund['refund_type'] === 0){
            return ['errcode' => 41008, 'errmsg' => '退款类型不需要填写快递单号'];
        }
        if($orderRefund['status'] != REFUND_AGREE){
            return ['errcode' => 41009, 'errmsg' => '退款当前状态不可进行此操作'];
        }
        $statumsg = Config::get('config.refund_status');
        $data = [ "status" =>  $orderRefund['status'] , 'status_msg' => $statumsg[$orderRefund['status']], 'address' => $orderRefund['address'] , 'logistics' => $this->deliveryCompany() ] ;
        return ['errcode' => 0, 'errmsg' => 'ok', 'data' => $data ];
    }

    public function postLogistics(){
        $refund_id = $this->request->input('id');
        $logistics = $this->request->input('logistics');
        $express_no = $this->request->input('express_no');
        if(!isset($refund_id) || !isset($logistics) ||  !isset($express_no) ) {
            return ['errcode' => 41001, 'errmsg' => '缺少发货参数'];
        }
        $logisticslist =  $this->deliveryCompany();
        if(!isset($logisticslist[$logistics])){
            return ['errcode' => 41010, 'errmsg' => '退款选择快递公司有误'];
        }
        $orderRefund = OrderRefund::get_data_by_id($refund_id,$this->merchant_id);//query()->where(['id'=>$refund_id])->first();
        if(!$orderRefund || $orderRefund['member_id'] != $this->member_id ){
            return ['errcode' => 40061, 'errmsg' => '退款id数据不合法'];
        }
        if($orderRefund['refund_type'] === 0){
            return ['errcode' => 41008, 'errmsg' => '退款类型不需要填写快递单号'];
        }
        if($orderRefund['status'] != REFUND_AGREE){
            return ['errcode' => 41009, 'errmsg' => '退款当前状态不可进行此操作'];
        }
        $response = $this->setlog($refund_id,'已退货，等待商家收货',[
            ['name'=> '快递公司' ,'value'=> $logisticslist[$logistics]['name'] ],
            ['name'=> '快递单号' ,'value'=> $express_no ],
        ]);
        if(!$response){
            return ['errcode' => 41002, 'errmsg' => '状态错误'];
        }
        $response = OrderRefund::update_data($orderRefund['id'],$this->merchant_id,['status'=>REFUND_SEND,'shipmented_time'=>date('Y-m-d H:i:s')]);
        if(!$response){
            return ['errcode' => 41002, 'errmsg' => '状态错误'];
        }
        return ['errcode' => 0, 'errmsg' => '提交成功'];
    }

    public function revocation(){
        $refund_id = $this->request->get('id');
        if(!isset($refund_id)) {
            return ['errcode' => 40060, 'errmsg' => '退款id不能为空'];
        }
        $orderRefund =OrderRefund::get_data_by_id($refund_id,$this->merchant_id);
        if(!$orderRefund || $orderRefund['member_id'] != $this->member_id){
            return ['errcode' => 40061, 'errmsg' => '退款id数据不合法'];
        }
        if($orderRefund['status'] != REFUND_REFUSE && $orderRefund['status'] != REFUND && $orderRefund['status'] != REFUND_AGAIN){
            return ['errcode' => 41009, 'errmsg' => '退款当前状态不可进行此操作'];
        }
        $response = OrderRefund::update_data($orderRefund['id'],$this->merchant_id,['status'=>REFUND_CANCEL]);
        if(!$response){
            return ['errcode' => 99003, 'errmsg' => '系统错误。'];
        }
        $this->setlog($refund_id,'买家取消退款',[
            ['name'=> '退款说明' ,'value'=> '买家取消退款' ]
        ]);
        //虚拟商品订单
        $orderInfo  = OrderInfo:: get_data_by_id($orderRefund['order_id'],$this->merchant_id);
        if($orderInfo['order_goods_type'] == ORDER_GOODS_VIRTUAL){
            $virtualGoodsService = new VirtualGoodsService();
            //订单状态非维权关闭，且未核销次数为0，订单完成
            $virtualGoodsService->successOrder($orderInfo);
        }

        return ['errcode' => 0, 'errmsg' => '程序成功'];
    }
    //操作记录
    private function setlog($refund_id,$str_title,$data){
        return OrderRefundLog::insert_data([
            'merchant_id'     => $this->merchant_id,
            'order_refund_id' => $refund_id,
            'who'             => '买家',
            'status_str'      => $str_title,
            'detaill'         => json_encode($data,JSON_UNESCAPED_UNICODE)
        ]);
    }
    //快递公司
    private function deliveryCompany(){
        $logistics = [];
        $deliveryCompany = MerchantDelivery::query()->where(['merchant_id'=>$this->merchant_id,'is_delete'=>1]) ->lists('delivery_company_id');  //$delivery_company_id = array_map(function ($val){ return $val['delivery_company_id']; },$deliveryCompany);
        $deliveryCompany = DeliveryCompany::query()->wherein('id',$deliveryCompany)->get();
        foreach ($deliveryCompany as $k => $v ) {
            $logistics[] = ["id"=>$v['id'],'name'=>$v['name']];
        }
        return $logistics;
    }
    //运费
    private function getShipmentFee($order_id, $ordergoods = 0, $refund = 0 ){
        $order_id = (int) $order_id ;
        if( $order_id <= 0 ) {
            return [ 'status' => false, 'error' => '' ] ;
        }
        $data = [];
        $orderGoodsList = OrderGoods::query()->where(['order_id'=>$order_id,'merchant_id'=>$this->merchant_id,'member_id'=>$this->member_id])->get()->toArray();
        foreach ($orderGoodsList as $k => $v){
            if($v['is_pinkage'] == 1 ){ // 包邮
                continue;
            }
            $refundquantity = 0;
            $refundlist =   OrderRefund::query()->where(['order_id'=>$order_id,'goods_id'=>$v['goods_id'],'spec_id'=>$v['spec_id']])->where('status','!=',REFUND_REFUSE) ->get()->toArray();
            foreach ($refundlist as $kr => $vr) {
                $refundquantity += $vr['refund_quantity'];
            }

            $quantity = $v['quantity'] - $refundquantity -  ($ordergoods == $v['id'] ? $refund : 0 ) ;


            if($quantity <= 0) continue;

            $data[$k] = [
                'id' => $v['goods_id'] ,
                'title' => $v['goods_name'] ,
                'shipment_id' => $v['shipment_id'],
                'postage' => $v['postage'] ,
                'quantity' => $quantity,
                'weight' => $v['weight'],
                'volume' => $v['volume'],
                'shipment_data' => [
                    'valuation_type' => $v['valuation_type'] ,
                    'start_standard' => $v['start_standard'] ,
                    'start_fee' => $v['start_fee'],
                    'add_standard' => $v['add_standard'] ,
                    'add_fee' => $v['add_fee']
                ]
            ];
        }
        if(empty($data)){
            return [ 'status' => true, 'shipment_fee' => 0 ,'data'=>$data] ;
        }
        $orderAddr = OrderAddr::query()->where(['order_id'=>$order_id])->first();
        $response =  ShipmentService::getOrderShipmentFee($data,$orderAddr['province'],$orderAddr['city']);
        //(new WeixinService())->setLog('getOrderShipmentFee',$data,$response,0,$order_id);
        return $response;
    }
    //
    private function getShipmentFeeShow($order_id, $ordergoods  ){
        $order_id = (int) $order_id ;
        if( $order_id <= 0 ) {
            return [ 'status' => false, 'error' => '' ] ;
        }
        $data = [];
        $orderGoodsList = OrderGoods::query()->where(['id'=>$ordergoods,'merchant_id'=>$this->merchant_id,'member_id'=>$this->member_id])->get()->toArray();
        foreach ($orderGoodsList as $k => $v){
            if($v['is_pinkage'] == 1 ){ // 包邮
                continue;
            }
            $data[$k] = [
                'id' => $v['goods_id'] ,
                'title' => $v['goods_name'] ,
                'shipment_id' => $v['shipment_id'],
                'postage' => $v['postage'] ,
                'quantity' => $v['quantity'] ,
                'weight' => $v['weight'],
                'volume' => $v['volume'],
                'shipment_data' => [
                    'valuation_type' => $v['valuation_type'] ,
                    'start_standard' => $v['start_standard'] ,
                    'start_fee' => $v['start_fee'],
                    'add_standard' => $v['add_standard'] ,
                    'add_fee' => $v['add_fee']
                ]
            ];
        }
        if(empty($data)){
            return [ 'status' => true, 'shipment_fee' => 0 ,'data'=>$data] ;
        }
        $orderAddr = OrderAddr::query()->where(['order_id'=>$order_id])->first();
        $response =  ShipmentService::getOrderShipmentFee($data,$orderAddr['province'],$orderAddr['city']);
        return $response;
    }
    //退款状态 提示
    private function tipmsg($status){
        $data =  [
            REFUND		        =>	['title'=>'你的退款申请已提交！','test'=>'请耐心等待商家反馈结果。'],
            REFUND_AGAIN 	    =>	['title'=>'你的退款申请再次提交！','test'=>'请耐心等待商家反馈结果。'],
            REFUND_AGREE		=>	['title'=>'商家已同意退款！','test'=>'请耐心等待退款到账','tests'=>'请将需要退货的商品寄还给商家'],
            REFUND_REFUSE		=>	['title'=>'商家未同意您的退款申请！','test'=>'拒绝理由：点击查看完整协商记录'],
            REFUND_SEND			=>	['title'=>'已退货！','test'=>'请耐心等待商家确认收货。'],
            REFUND_FAIL			=>	['title'=>'第三方退款失败！','test'=>'请联系商家。'],
            REFUND_TRADING		=>	['title'=>'第三方退款中！','test'=>''],
            REFUND_FINISHED		=>	['title'=>'退款成功！','test'=>'期待你的再次光临。'],
            REFUND_CLOSE		=>	['title'=>'退款已关闭！','test'=>'退款已完结。'],
            REFUND_CANCEL		=>  ['title'=>'买家主动撤销退款！','test'=>'退款已关闭。'],
            REFUND_MER_CANCEL	=>  ['title'=>'退款流程关闭！','test'=>'超时未操作，商家手动关闭流程。'],
        ];
        return isset($data[$status]) ? $data[$status] : ['title'=>'','test'=>''];
    }
}

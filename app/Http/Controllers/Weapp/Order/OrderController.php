<?php
/**
 * 订单控制器
 * @author zhangchangchun@dodoca.com
 * date 2017-09-06
 */
 
namespace App\Http\Controllers\Weapp\Order;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Support\Facades\DB;
use App\Utils\CommonApi;
use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Facades\Member;
use App\Services\BuyService;
use App\Services\DiscountService;
use App\Services\ShipmentService;
use App\Services\CouponService;
use App\Services\CreditService;
use App\Services\CartService;
use App\Services\GoodsService;
use App\Services\FightgroupService;
use App\Services\ApptService;
use App\Services\VirtualGoodsService;
use App\Services\UserPrivService;
use App\Services\OrderPrintService;
use App\Models\OrderInfo;
use App\Models\OrderGoods;
use App\Models\OrderUmp;
use App\Models\OrderGoodsUmp;
use App\Models\OrderPackage;
use App\Models\OrderPackageItem;
use App\Models\OrderSelffetch;
use App\Models\MerchantSetting;
use App\Models\FightgroupJoin;
use App\Models\OrderRefund;
use App\Models\CreditRule;
use App\Models\OrderAppt;
use App\Models\OrderAddr;
use App\Models\DeliveryCompany;
use App\Models\OrderComment;
use App\Models\OrderCommentImg;
use App\Models\Cart;
use App\Models\Goods;
use App\Models\GoodsSpec;
use App\Models\Member as MemberModel;
use App\Models\MemberAddress;
use App\Models\MemberCard;
use App\Models\Store;
use App\Models\User;
use App\Models\Priv;
use App\Models\Merchant;
use App\Models\VersionPriv;
use App\Models\UserPriv;
use App\Models\UserRole;
use App\Models\Shop;
use App\Models\RolePriv;
use App\Models\OrderVirtualgoods;
use App\Models\GoodsVirtual;
use App\Models\WeixinSetting;
use App\Jobs\OrderCancel;
use App\Jobs\OrderDelivery;
use App\Jobs\OrderPaySuccess;
use App\Utils\Logistics;
use \Milon\Barcode\DNS1D;
use \Milon\Barcode\DNS2D;

class OrderController extends Controller
{
	use DispatchesJobs;
	
	public function __construct(BuyService $BuyService,OrderInfo $OrderInfo,DiscountService $DiscountService,CreditService $CreditService,FightgroupService $FightgroupService) {
		$this->BuyService = $BuyService;
		$this->OrderInfoModel = $OrderInfo;
		$this->DiscountService = $DiscountService;
		$this->CreditService = $CreditService;
		$this->FightgroupService = $FightgroupService;
		$this->member_id = Member::id();
		$this->merchant_id = Member::merchant_id();
		if(!$this->member_id || !$this->merchant_id) {
			return false;
		}
		$this->varconfig = config('varconfig.order_goods_ump_ump_type');
	}
	
    /**
     * 订单列表
	 * @author zhangchangchun@dodoca.com
	 * date 2017-09-06
     */
    public function getList(Request $request)
    {
		$pagesize = isset($request['pagesize']) ? (int)$request['pagesize'] : 10;
		$page = isset($request['page']) ? (int)$request['page'] : 1;
		$status = isset($request['status']) ? (int)$request['status'] : -1;
		$offset = ($page-1)*$pagesize;
		$data = array();
		$filed = 'id,merchant_id,member_id,order_sn,nickname,amount,goods_amount,shipment_fee,order_type,pay_type,status,comment_status,extend_days,extend_info,remark,memo,pay_status,pay_time,shipments_time,is_finish,finished_time,expire_at,created_time,delivery_type,order_goods_type,appid';
		$goodsfiled = 'id,order_id,goods_id,goods_name,goods_img,spec_id,quantity,price,pay_price,props';
		$query = $this->OrderInfoModel->where(['merchant_id'=>$this->merchant_id,'member_id'=>$this->member_id,'is_valid'=>1]);
		$appid = Member::appid();	//获取小程序appid
		switch($status) {
			case 1:	//待付款
				$query->whereIn('status',[ORDER_SUBMIT,ORDER_TOPAY]);
				break;
			case 2:	//待发货
				$query->whereIn('status',[ORDER_TOSEND]);
				break;
			case 3:	//待收货
				$query->whereIn('status',[ORDER_SEND,ORDER_FORPICKUP]);
				break;
			case 4:	//待评价
				$query->where(['status'=>ORDER_SUCCESS,'comment_status'=>0]);
				break;
			case 5:	//已完成
				$query->where(['status'=>ORDER_SUCCESS]);
				break;
			case 6:	//已取消
				$query->whereIn('status',[ORDER_AUTO_CANCELED,ORDER_BUYERS_CANCELED,ORDER_MERCHANT_CANCEL]);
				break;
			case 9:	//维权
				$query->where(['refund_status'=>1])->where('status','<>',ORDER_REFUND_CANCEL);
				break;
		}
		$query->where('order_type','<>',ORDER_SALEPAY);
		$query->where('order_type','<>',ORDER_KNOWLEDGE);
		$data['_count'] = $query->count();
		$list = $query->select(\DB::raw($filed))->orderBy('id','desc')->skip($offset)->take($pagesize)->get()->toArray();
		$mrSetInfo = MerchantSetting::get_data_by_id($this->merchant_id);
		if($list) {
			foreach($list as $key =>$info) {
				
				//拼团订单，若未成团不显示该订单
				if($info['order_type']==ORDER_FIGHTGROUP) {
					$check = $this->FightgroupService->fightgroupJoinOrder($info['id']);
					if($check['data']['type'] == 0){
						unset($list[$key]);
						continue;
					}
				}
				
				//非本小程序下未支付订单不显示
				if($info['appid']!=$appid && in_array($info['status'],[ORDER_SUBMIT,ORDER_TOPAY])) {
					unset($list[$key]);
						continue;
				}
				
				$list[$key]['status_str'] = $this->orderStatus($info['status'],1,$info);
				if($info['delivery_type']==1) {	//物流配送
					$list[$key]['delivery_str'] = ($mrSetInfo && $mrSetInfo['delivery_alias']) ? $mrSetInfo['delivery_alias'] : '物流配送';
				} else if($info['delivery_type']==2) {	//上门自提
					$list[$key]['delivery_str'] = ($mrSetInfo && $mrSetInfo['selffetch_alias']) ? $mrSetInfo['selffetch_alias'] : '上门自提';
				} else if($info['delivery_type']==3) {	//上门自提
					$list[$key]['delivery_str'] = '同城配送';
				}
				$list[$key]['is_can_cancel'] = in_array($info['status'],[ORDER_SUBMIT,ORDER_TOPAY]) ? 1 : 0;
				$list[$key]['is_can_delivery'] = ($info['order_goods_type']!=ORDER_GOODS_VIRTUAL && $info['order_type']!=ORDER_APPOINT && $info['status']==ORDER_SEND) ? 1 : 0;
				$list[$key]['is_can_comment'] = ($mrSetInfo['is_comment_open']==1 && $info['status']==ORDER_SUCCESS && $info['comment_status']==0) ? 1 : 0;
				$list[$key]['order_goods'] = OrderGoods::get_data_list(['merchant_id'=>$this->merchant_id,'member_id'=>$this->member_id,'order_id'=>$info['id']],$goodsfiled);
				$list[$key]['status'] = $this->orderStatus($info['status'],2);
				//预约订单返回预约信息
				if($info['order_type']==ORDER_APPOINT) {
					$list[$key]['apptinfo'] = OrderAppt::select(['store_id','store_name','customer','customer_mobile','hexiao_code','hexiao_status','appt_date','appt_string'])->where(['order_id'=>$info['id']])->first();
				}
				
				//虚拟商品订单
				$list[$key]['if_virtual'] = 0;//是否虚拟商品订单：0->否，1->是
				if($info['order_goods_type'] == ORDER_GOODS_VIRTUAL){
				    $list[$key]['if_virtual'] = 1;
				}

			}
		}
		$data['data'] = array_splice($list,0,count($list));
		return Response::json(array('errcode' => '0', 'errmsg' => '请求成功', 'data' => $data));		
    }
	
	/**
	 * 订单详情
	 * @author zhangchangchun@dodoca.com
	 * date 2017-09-06
	 */
	public function getInfo($id,Request $request) {
		if(!$id || !is_numeric($id)) {
			return Response::json(array('errcode' => '40001', 'errmsg' => '缺少订单号参数'));
		}
		$is_hexiao = 0;
		if(isset($request['is_hexiao']) && $request['is_hexiao']==1) {
			$is_hexiao = 1;
		}
		if( isset($request['h5token']) && !empty($request['h5token']) ){
		     if( $request['h5token']!=base64_encode(encrypt($id,'E','H5ChargeOff20171110')) ){
    		    $rt['errcode']=100001;
    		    $rt['errmsg']='token不正确';
    		    return Response::json($rt);
    		}
    		$data_OrderInfo_VirtualGoods = $data = OrderInfo::where(['id'=>$id])->first();
    		if(empty($data)){
    		    $rt['errcode']=100001;
    		    $rt['errmsg']='查询不到此订单';
    		    return Response::json($rt);
    		}
    		$this->merchant_id = $data['merchant_id'];
    		$this->member_id = $data['member_id'];
			$is_hexiao = 1;
			
			$data['open_id']=encrypt(base64_decode($request['wx_open_id']),'D','OPEN_WXWEB_AUTH');
			if(!isset($data['open_id']) || empty($data['open_id'])){
			    $rt['errcode']=100001;
			    $rt['errmsg']='没有获取到open_id';
			    return $rt;
			}
			//是否绑定了微信
			$rs_user = User::where(['open_id'=>$data['open_id']])->first();
			if( !isset($rs_user['open_id']) || empty($rs_user['open_id']) ){
			    $rt['errcode']=111301;
			    $rt['errmsg']='';
			    $rt['data']['bigChar'] = '您没有验证权限';
			    $rt['data']['smallChar'] = '请使用具有验证权限的微信扫描再试.';
			    return $rt;
			}
			
			//上门自提H5核销确认
			if($data['delivery_type']==2){
			    //是否本门店
			    if( $rs_user['store_id']!=$data['store_id'] ){
			        $rt['errcode']=111301;
			        $rt['errmsg']='';
			        $rt['data']['bigChar'] = '您没有该门店的核销权限';
			        $rt['data']['smallChar'] = '请让消费者到指定门店进行核销';
			        return $rt;
			    }
			    $rs_orderselffetch = OrderSelffetch::where(['order_id'=>$data['id']])->first();
			    if(empty($rs_orderselffetch)){
			        $rt['errcode']=100001;
			        $rt['errmsg']='没有获取到上门自提订单信息';
			        return $rt;
			    }
			    //验证核销权限位
			    $if_auth = UserPrivService::getHexiaoPriv($rs_user['id'],$rs_user['merchant_id'],$rs_user['is_admin'],'order_chargeoff_selflift');
			    if( !isset($if_auth['errcode']) && $if_auth['errcode']!='has_priv' ){
			        $rt['errcode']=111301;
			        $rt['errmsg']='';
			        $rt['data']['bigChar'] = '您没有验证权限';
			        $rt['data']['smallChar'] = '请使用具有验证权限的微信扫描再试;';
			        return $rt;
			    }
			}
			//预约服务H5核销确认
			else if($data['order_type']==4){
			    //预约服务订单
			    $rs_orderappt = OrderAppt::where(['order_id'=>$data['id']])->first();
			    if(empty($rs_orderappt)){
			        $rt['errcode']=100001;
			        $rt['errmsg']='没有获取到订单id.';
			        return $rt;
			    }
			    //是否本门店
			    if( $rs_user['store_id']!=$rs_orderappt['store_id'] ){
			        $rt['errcode']=111301;
			        $rt['errmsg']='';
			        $rt['data']['bigChar'] = '您没有该门店的核销权限';
			        $rt['data']['smallChar'] = '请让消费者到指定门店进行核销';
			        return $rt;
			    }
			    //预约服务H5核销权限位
			    $if_auth = UserPrivService::getHexiaoPriv($rs_user['id'],$rs_user['merchant_id'],$rs_user['is_admin'],'order_chargeoff_selflift');
			    if( !isset($if_auth['errcode']) && $if_auth['errcode']!='has_priv' ){
			        $rt['errcode']=111302;
			        $rt['errmsg']='';
			        $rt['data']['bigChar'] = '您没有验证权限';
			        $rt['data']['smallChar'] = '请使用具有验证权限的微信扫描再试:';
			        return $rt;
			    }
			}
			//虚拟商品
			else if($data['order_goods_type']==1){
			    $rs_order_virtualgoods = OrderVirtualgoods::where(['order_id'=>$id])->first();
			    if(empty($rs_order_virtualgoods)){
			        $rt['errcode']=100001;
			        $rt['errmsg']='没有获取到订单id;';
			        return $rt;
			    }
			    
			    //预约服务H5核销权限位
			    $if_auth = UserPrivService::getHexiaoPriv($rs_user['id'],$rs_user['merchant_id'],$rs_user['is_admin'],'trade_orderhexiao_virtualgoods');
			    if( !isset($if_auth['errcode']) && $if_auth['errcode']!='has_priv' ){
			        $rt['errcode']=111302;
			        $rt['errmsg']='';
			        $rt['data']['bigChar'] = '您没有验证权限';
			        $rt['data']['smallChar'] = '请使用具有验证权限的微信扫描再试:';
			        return $rt;
			    }
			}
		}else{
		    $data = $this->OrderInfoModel->get_data_by_id($id,$this->merchant_id);
		}
		if(!$data) {
			return Response::json(array('errcode' => '40005', 'errmsg' => '订单不存在'));
		}
		$data = json_decode($data,true);
		
		//验证订单是否过期未支付
		if(in_array($data['status'],[ORDER_SUBMIT,ORDER_TOPAY]) && strtotime($data['expire_at'])<time()) {
			$canceldata = [
				'status'	=>	ORDER_AUTO_CANCELED,
				'explain'	=>	'超时系统取消',
			];
			$result = $this->OrderInfoModel->update_data($id,$this->merchant_id,$canceldata);
			if($result) {
				//发送到队列
				$job = new OrderCancel($data['id'],$data['merchant_id']);
				$this->dispatch($job);
			}
			$data['status'] = ORDER_AUTO_CANCELED;
		}
		
		//拼团订单，若未成团不显示该订单
		if($data['pay_status']==1 && $data['order_type']==ORDER_FIGHTGROUP) {
			$check = $this->FightgroupService->fightgroupJoinOrder($data['id']);
			if($check['data']['type'] == 0){
				return array('errcode'=>40059,'errmsg'=>'拼团中的订单请到“个人中心->我的拼团”中查看');
			}
		}
		
		//商城信息
		$rs_shop = Shop::get_data_by_merchant_id($this->merchant_id);
		$data['shop_name'] = isset($rs_shop['name'])?$rs_shop['name']:'商城名称';
		$data['shop_logo'] = isset($rs_shop['logo'])?$rs_shop['logo']:'';
		
		$mrSetInfo = MerchantSetting::get_data_by_id($this->merchant_id);
		$data['is_support_delivery'] = 0;	//是否支持物流配送
		$data['is_support_selffetch'] = 0;	//是否支持上门自提
		$data['is_support_appoint'] = 0;	//是否支持同城配送
		$data['is_charge_off'] = 0;         //是否能核销
		$data['city_shipment_fee'] = 0;		//同城配送运费
		if($mrSetInfo) {
			$data['is_support_delivery'] = $mrSetInfo['delivery_enabled']==1 ? 1 : 0;
			$data['is_support_selffetch'] = $mrSetInfo['store_enabled']==1 ? 1 : 0;
			$data['is_support_appoint'] = $mrSetInfo['appoint_enabled']==1 ? 1 : 0;
			$data['delivery_str'] = $mrSetInfo['delivery_alias'] ? $mrSetInfo['delivery_alias'] : '物流配送';
			$data['selffetch_str'] = $mrSetInfo['selffetch_alias'] ? $mrSetInfo['selffetch_alias'] : '上门自提';
			$data['city_shipment_fee'] = $mrSetInfo['city_cost'];
		}
		
		$goodsfiled = 'id,order_id,goods_id,goods_name,goods_img,spec_id,quantity,refund_quantity,price,pay_price,props,is_pinkage';
		$data = $this->dateFormat($data);
		$data['is_can_delivery'] = ($data['order_goods_type']!=ORDER_GOODS_VIRTUAL && $data['order_type']!=ORDER_APPOINT && $data['status']==ORDER_SEND) ? 1 : 0;		//是否能收货
		$data['is_can_extend'] = ($data['order_goods_type']!=ORDER_GOODS_VIRTUAL && $data['order_type']!=ORDER_APPOINT && $data['status']==ORDER_SEND && $data['extend_days']==0) ? 1 : 0;		//是否延长收货
		$data['is_can_comment'] = ($mrSetInfo['is_comment_open']==1 && $data['status']==ORDER_SUCCESS && $data['comment_status']==0) ? 1 : 0;	//是否能评价
		$data['is_can_cancel'] = in_array($data['status'],[ORDER_SUBMIT,ORDER_TOPAY]) ? 1 : 0;	//是否能评价
		if($data['delivery_type']==2){
		    $data['is_charge_off'] = in_array($data['status'],[ORDER_FORPICKUP])? 1 : 0;	//是否能核销:上门自提
		}else if($data['order_type']==4){
		    $data['is_charge_off'] = in_array($data['status'],[ORDER_TOSEND,ORDER_SEND])? 1 : 0;	//是否能核销:预约服务
		}
		$data['goods_sum'] = 0;
		$data['confirm_credit'] = 0;	//确认收货送积分
		
		$data['is_can_use_card'] = 0;	//是否能使用优惠券
		if($this->get_reduction_power($data['order_type'],'coupon') && ($data['amount']-$data['shipment_fee']>0)) {
			$data['is_can_use_card'] = 1;
		}
		
		$data['card_amount'] = 0;		//会员卡抵扣
		$data['discount_amount'] = 0;	//满减抵扣
        $data['postage_status'] = 0;	//此订单手否有满包邮优惠  0->没有满包邮；1->已有满包邮优惠
		$data['member_postage'] = 0;	//会员卡是否包邮：1-包邮，0-不包邮
		$data['credit_amount'] = 0;		//能抵扣的积分
		$data['credit_ded_amount'] = 0;	//积分抵扣的金额
		
		//获取会员信息
		$memberinfo = MemberModel::get_data_by_id($this->member_id,$this->merchant_id);
		if(!$memberinfo) {
			return array('errcode'=>40059,'errmsg'=>'会员不存在');
		}
		
		//获取会员卡信息
		if($memberinfo['member_card_id']) {
			$cardinfo = MemberCard::get_data_by_id($memberinfo['member_card_id'],$this->merchant_id);
			if($cardinfo) {
				$data['member_postage'] = $cardinfo['is_postage_free'];
			}
		}
		
        if(in_array($data['status'],[ORDER_SUBMIT,ORDER_TOPAY])) {
			//会员卡抵扣
			$dis_amount = OrderUmp::where(['order_id'=>$id,'ump_type'=>1])->pluck('amount');
			if($dis_amount) {
				$data['card_amount'] = abs((float)$dis_amount);
			}
			
			//满减抵扣			
			$dis_amount = OrderUmp::where(['order_id'=>$id,'ump_type'=>7])->pluck('amount');
			if($dis_amount) {
				$data['discount_amount'] = abs((float)$dis_amount);
			}

            //满包邮
            $postage_count = OrderUmp::where(['order_id'=>$id,'ump_type'=>8])->count('id');
            if(isset($postage_count) && $postage_count >0) {
                //$data['shipment_fee'] = 0;//如果使用满减且订单满包邮运费重置为0 
                $data['postage_status'] = 1;
            }

			//积分抵扣
			$get_use_credit = $this->BuyService->get_credit(['merchant_id'=>$this->merchant_id,'member_id'=>$this->member_id,'amount'=>($data['amount']-$data['shipment_fee'])]);
			if($get_use_credit && isset($get_use_credit['credit_amount']) && isset($get_use_credit['credit_ded_amount'])) {
				$data['credit_amount'] = $get_use_credit['credit_amount'];
				$data['credit_ded_amount'] = $get_use_credit['credit_ded_amount'];
			}
		}
		
		//拼团能否退款(1-能退款，0-不能退）
		$is_fightgroup_refund = 1;
		if($data['order_type']==ORDER_FIGHTGROUP && $data['pay_status']==1) {
			$fightinfo = $this->FightgroupService->fightgroupJoinOrder($id);
			if($fightinfo && isset($fightinfo['errcode']) && $fightinfo['errcode']==0 && isset($fightinfo['data']['type']) && $fightinfo['data']['type']==0) {
				$is_fightgroup_refund = 0;
			}
		}
		//订单中的商品信息
		$order_goods = OrderGoods::get_data_list(['merchant_id'=>$this->merchant_id,'member_id'=>$this->member_id,'order_id'=>$data['id']],$goodsfiled);
		if($order_goods) {
		    
		    //虚拟商品订单，商品件数是否已核销完，核销完不能退款
		    $virtual_finish_num = 0;//虚拟商品订单，已核销次数
		    $virtual_not_num = 0;//虚拟商品订单，未核销次数
		    $is_virtual_refund = 1;//虚拟商品订单能否退款(1-能退款，0-不能退）
		    if($data['order_goods_type'] == ORDER_GOODS_VIRTUAL){
		        $virtualGoodsService = new VirtualGoodsService();
		    
		        $virtual_finish_num = $virtualGoodsService->finishHexiaoNum($data);//已核销次数
		        $virtual_not_num = $virtualGoodsService->notHexiaoNum($data);//未核销次数
		    
		        if($virtual_finish_num >= $order_goods[0]['quantity']){
		            //无退款申请，且数量核销完，不能退款
		            $virtual_refunddata = OrderRefund::select(['id','refund_quantity','status','amount'])->where(['merchant_id'=>$this->merchant_id,'order_id'=>$id])->get()->toArray();
		            if(!$virtual_refunddata){
		                $is_virtual_refund = 0;
		            }
		        }
		    }
		    
			foreach($order_goods as $key => $ginfo) {
				if($ginfo['is_pinkage']==0) {
					$data['member_postage'] = 0;
				}
				$data['goods_sum'] += $ginfo['quantity'];
				$order_goods[$key]['refund_type'] = 0;			//退款状态：0-无退款按钮，1-跳转退款发起页，2-跳转退款详情页，3-弹框
				$order_goods[$key]['refund_id'] = 0;			//退款记录表id，退款状态为2时有效
				$order_goods[$key]['refund_btn'] = '申请退款';	//退款按钮文字
				$order_goods[$key]['refund_goon'] = 0;			//是否继续退款：1-有继续退款按钮，0-无按钮，退款状态为3时有效（弹出层里是否有继续退款按钮）
				$order_goods[$key]['refund_data'] = [];			//退款申请列表，退款状态为3时有效
				
				if($data['status']==ORDER_REFUND_CANCEL) {	//所有维权完成关闭
					$order_goods[$key]['refund_btn'] = '退款完成';
				}
				if(!in_array($data['status'],[ORDER_AUTO_CANCELED,ORDER_BUYERS_CANCELED,ORDER_MERCHANT_CANCEL]) && $is_fightgroup_refund==1 && $is_virtual_refund==1) {
					$refunddata = OrderRefund::select(['id','refund_quantity','status','amount'])->where(['merchant_id'=>$this->merchant_id,'order_id'=>$id,'goods_id'=>$ginfo['goods_id'],'spec_id'=>$ginfo['spec_id']])->get()->toArray();
					if($refunddata) {
						$refndsum = 0;
						foreach($refunddata as $kk => $reinfo) {
							$refndsum += $reinfo['refund_quantity'];
							$refunddata[$kk]['sum'] = $reinfo['refund_quantity'];
							$refunddata[$kk]['msg'] = $this->refundStatus($reinfo['status']);
						}
						$order_goods[$key]['refund_data'] = $refunddata;
						if(count($refunddata)==1) {
							if($refunddata[0]['status']==31) {
								
							    if($data['order_goods_type'] == ORDER_GOODS_VIRTUAL){
							        //虚拟商品订单
							        $order_goods[$key]['refund_btn'] = '退款完成';
							        if($virtual_not_num == 0) {
							            $order_goods[$key]['refund_type'] = 2;
							            $order_goods[$key]['refund_id'] = $refunddata[0]['id'];
							        } else {
							            $order_goods[$key]['refund_type'] = 3;
							            if($data['is_finish']!=1) {
							                $order_goods[$key]['refund_goon'] = 1;
							            }
							        }
							         
							    }else{
							         
							        $order_goods[$key]['refund_btn'] = '退款完成';
							        if($ginfo['quantity']<=$ginfo['refund_quantity']) {
							            $order_goods[$key]['refund_type'] = 2;
							            $order_goods[$key]['refund_id'] = $refunddata[0]['id'];
							        } else {
							            $order_goods[$key]['refund_type'] = 3;
							            if($data['is_finish']!=1) {
							                $order_goods[$key]['refund_goon'] = 1;
							            }
							        }
							         
							    }
							    
							} else {	//有一条退款尚未完结
							    
							    if($data['order_goods_type'] == ORDER_GOODS_VIRTUAL){
							        //虚拟商品订单
							        $order_goods[$key]['refund_type'] = 2;
							        $order_goods[$key]['refund_id'] = $refunddata[0]['id'];
							         
							        if($virtual_not_num == 0 && $refunddata[0]['status'] == 41) {
							            $order_goods[$key]['refund_btn'] = '退款完成';
							        }else{
							            if($refunddata[0]['status']!=41) {
							                $order_goods[$key]['refund_btn'] = '退款中';
							            }
							        }
							         
							    }else{
							        $order_goods[$key]['refund_type'] = 2;
							        $order_goods[$key]['refund_id'] = $refunddata[0]['id'];
							        if($refunddata[0]['status']!=41 && $refunddata[0]['status']!=42) {
							            $order_goods[$key]['refund_btn'] = '退款中';
							        }
							    }
							    
							}
						} else {
						    
						    if($data['order_goods_type'] == ORDER_GOODS_VIRTUAL){
						        //虚拟商品订单
						        $order_goods[$key]['refund_type'] = 3;
						        if($virtual_not_num != 0 && $data['is_finish']!=1) {
						            $order_goods[$key]['refund_goon'] = 1;
						        }
						        foreach($refunddata as $kk => $reinfo) {
						            if($reinfo['status']!=31) {
						                $order_goods[$key]['refund_goon'] = 0;
						                $order_goods[$key]['refund_btn'] = '退款中';
						            }
						    
						            if($virtual_not_num == 0 && ($reinfo['status']==41 || $reinfo['status']==31)) {
						                $order_goods[$key]['refund_goon'] = 0;
						                $order_goods[$key]['refund_btn'] = '退款完成';
						            }
						        }
						    
						    }else{
						        $order_goods[$key]['refund_type'] = 3;
						        if($ginfo['quantity']>$refndsum && $data['is_finish']!=1) {
						            $order_goods[$key]['refund_goon'] = 1;
						        }
						        foreach($refunddata as $kk => $reinfo) {
						            if($reinfo['status']!=31) {
						                $order_goods[$key]['refund_goon'] = 0;
						                $order_goods[$key]['refund_btn'] = '退款中';
						            }
						        }
						    }
						    
						}
					} else if($data['is_valid']==1 && $data['is_finish']!=1 && $data['pay_status']==1) {
						$order_goods[$key]['refund_type'] = 1;
					}
				}
			}
		}
		$data['order_goods'] = $hexiao_goods = $order_goods;
		if($is_hexiao==1 && $data['pay_status']==1) {	//核销使用
			$refunddata = OrderRefund::select(['id','goods_id','spec_id','refund_quantity','status'])->where(['merchant_id'=>$this->merchant_id,'order_id'=>$id])->get()->toArray();
			foreach($hexiao_goods as $key => $ginfo) {
				unset($hexiao_goods[$key]['refund_type']);
				unset($hexiao_goods[$key]['refund_id']);
				unset($hexiao_goods[$key]['refund_btn']);
				unset($hexiao_goods[$key]['refund_goon']);
				unset($hexiao_goods[$key]['refund_data']);				
				$hexiao_goods[$key]['h_refund_goon'] = 0;	//退款中数量
				$hexiao_goods[$key]['h_refund_ok'] = 0;		//退款完成数量
				if($refunddata) {
					foreach($refunddata as $kk => $refundInfo) {
						if($refundInfo['goods_id']==$ginfo['goods_id'] && $refundInfo['spec_id']==$ginfo['spec_id']) {
							if($refundInfo['status']==REFUND_FINISHED) {	//退款完成
								$hexiao_goods[$key]['h_refund_ok'] += $refundInfo['refund_quantity'];
							} else if(!in_array($refundInfo['status'],[REFUND_FINISHED,REFUND_CLOSE,REFUND_CANCEL])) {	//退款中
								$hexiao_goods[$key]['h_refund_goon'] += $refundInfo['refund_quantity'];
							}
						}
					}
				}
				if($hexiao_goods[$key]['h_refund_goon']==0 && $hexiao_goods[$key]['h_refund_ok']==0) {
					$hexiao_goods[$key]['h_refund_order'] = 1;
				} else if($hexiao_goods[$key]['h_refund_goon']>0 && $hexiao_goods[$key]['h_refund_ok']==0) {
					$hexiao_goods[$key]['h_refund_order'] = 2;
				} else if($hexiao_goods[$key]['h_refund_goon']>0 && $hexiao_goods[$key]['h_refund_ok']>0) {
					$hexiao_goods[$key]['h_refund_order'] = 3;
				} else if($hexiao_goods[$key]['h_refund_goon']==0 && $hexiao_goods[$key]['h_refund_ok']>0) {
					$hexiao_goods[$key]['h_refund_order'] = 4;
				} else {
					$hexiao_goods[$key]['h_refund_order'] = 0;
				}
			}
			$data['hexiao_goods'] = mArrSort($hexiao_goods,'h_refund_order','asc');
		}
		
		//订单留言扩展字段（后台设置）
		$data['fields'] = [];
		if($data['status']==ORDER_SUBMIT) {
			$msgfields = MerchantSetting::get_data_by_id($this->merchant_id);
			if($msgfields && isset($msgfields['fields'])) {
				$data['fields'] = json_decode($msgfields['fields'],true);
			}
		}
		
		//订单留言扩展字段（显示使用）
		$data['extend_info'] = $data['extend_info'] ? json_decode($data['extend_info'],true) : [];
		
		//订单优惠数据
		$data['order_ump'] = OrderUmp::get_data_list(['order_id'=>$data['id']],"ump_type,amount,memo");
		
		//确认收货送积分
		if($data['status']==ORDER_SEND) {
			$creditruleinfo = CreditRule::get_data_by_merchantid($this->merchant_id,3,1);
			if($creditruleinfo) {
				$data['confirm_credit'] = $creditruleinfo['credit'];
			}
		}
		
		//发货时间
		$data['delivery_time'] = [];
		
		$data['is_package_dada'] = 0;	//是否包含达达包裹:1-是，0-否
		
		//查看发送包裹
		$data['package'] = [];
		if(in_array($data['status'],[ORDER_TOSEND,ORDER_SUBMITTED,ORDER_SEND,ORDER_SUCCESS,ORDER_REFUND_CANCEL])) {
			$packagelist = OrderPackage::get_data_list(['order_id'=>$data['id']],"id,logis_code,logis_no,created_time,is_no_express");
			if($packagelist) {
				foreach($packagelist as $key => $packageinfo) {
					$data['delivery_time'][] = $packageinfo['created_time'];
					if($packageinfo['is_no_express']==0) {	//需要物流
						$packagelist[$key]['content'] = '无物流信息';
						$packagelist[$key]['date'] = date("Y-m-d H:i:s");
						if($packageinfo['logis_code'] && $packageinfo['logis_no']) {
							//调用物流接口
							$package = Logistics::search_logistic($packageinfo);
							
							//请求物流日志	
							CommonApi::wlog([
								'custom'    	=>    	'request-package_'.$data['id'],
								'merchant_id'   =>    	$data['merchant_id'],
								'member_id'     =>    	$data['member_id'],
								'content'		=>		'require->'.json_encode($packageinfo,JSON_UNESCAPED_UNICODE).',result->'.json_encode($package,JSON_UNESCAPED_UNICODE),
							]);
							
							if(isset($package['status']) && isset($package['data']['data']) && isset($package['data']['data'][0])) {
								if(isset($package['data']['data'])) {
									$package['data']['data'] = array_reverse($package['data']['data']);
									if(isset($package['data']['data'][0]['context'])) {
										$packagelist[$key]['content'] = $package['data']['data'][0]['context'];
									}
									if(isset($package['data']['data'][0]['time'])) {
										$packagelist[$key]['date'] = $package['data']['data'][0]['time'];
									}
								}								
							} else {
								$packagelist[$key]['date'] = '--';
							}
						}
						$data['package'][] = $packagelist[$key];
					} else if($packageinfo['is_no_express']==2) {	//达达包裹
						$data['is_package_dada'] = 1;
						//$data['package'][] = $packageinfo;
					}
				}
			}
		}
		//核销码状态
		//$data['hexiao_status'] = '';
		$data['hexiao_status_msg'] = '';
		
		//查看预约订单数据
		$data['appointment'] = [];
		if($data['order_type']==ORDER_APPOINT) {
			$appoint = OrderAppt::select(['store_id','store_name','customer','customer_mobile','hexiao_code','hexiao_status','appt_date','appt_string','hexiao_time','appt_staff_nickname','appt_date'])->where(['order_id'=>$data['id']])->first();
			if($appoint) {
			    $data['customer_appt'] = $appoint['customer'];
			    $data['customer_mobile_appt'] = $appoint['customer_mobile'];
				if($data['pay_status']==1 && $appoint['hexiao_code']) {
					$token = base64_encode(encrypt($data['id'],'E','H5ChargeOff20171110'));
					$url_H5ChargeOff = urlencode(ENV('APP_URL').'/weapp/order/h5chargeoff.json?order_id='.$data['id'].'&token='.$token);
					$url_qrcode = 'http://open.dodoca.com/wxauth/index?return_url='.$url_H5ChargeOff;
					
                    $appoint['barcode'] = 'data:image/png;base64,' . DNS1D::getBarcodePNG($appoint['hexiao_code'], "CODABAR", "2", "80");
                    $appoint['qrcode'] = 'data:image/png;base64,' . DNS2D::getBarcodePNG($url_qrcode, "QRCODE", "10", "10");
                    
				}
				$storeInfo = Store::get_data_by_id($appoint['store_id'],$this->merchant_id);
				if($storeInfo) {
					$storeInfo['address'] = $storeInfo['province_name'].$storeInfo['city_name'].$storeInfo['district_name'].$storeInfo['address'];
				}
				$appoint['hexiao_time'] = $appoint['hexiao_time']!='0000-00-00 00:00:00' ? $appoint['hexiao_time'] : '';
				$appoint['store'] = $storeInfo;
				$data['appointment'] = $appoint;
			}
		}
		
		//虚拟商品订单数据
		$data['if_virtual'] = 0;//是否虚拟商品订单：0->否，1->是
		if($data['order_goods_type'] == ORDER_GOODS_VIRTUAL){
		    $data['if_virtual'] = 1;
		    
		    $data['virtual_info']['virtual_name'] = $data['member_name']; //姓名
		    $data['virtual_info']['virtual_mobile'] = $data['member_mobile']; //手机
		    
		    //虚拟商品信息
		    $virtualGoodsService = new VirtualGoodsService();
		    $goods_virtual_info = $virtualGoodsService->getVirtualGoodsInfo($order_goods[0]['goods_id'], $this->merchant_id);
		    if($goods_virtual_info['errcode'] == 0){
		        $data['virtual_info']['time_type'] = $goods_virtual_info['data']['goods_virtual_info']['time_type'];
		        $data['virtual_info']['start_time'] = $goods_virtual_info['data']['goods_virtual_info']['start_time'];
		        $data['virtual_info']['end_time'] = $goods_virtual_info['data']['goods_virtual_info']['end_time'];
		    }
		    
		    //剩余核销次数
		    $data['virtual_info']['hexiao_num'] = $virtualGoodsService->residueHexiao($data);
		    
		    //核销码
		    $order_virtualgoods_info = OrderVirtualgoods::select('id','hexiao_code')
                                            		    ->where('merchant_id','=',$this->merchant_id)
                                            		    ->where('order_id','=',$data['id'])
                                            		    ->first();
		    if($order_virtualgoods_info){
		        $order_virtualgoods_info['hexiao_code'] = substr($order_virtualgoods_info['hexiao_code'], 0, 10);//截取前10位
		        $data['virtual_info']['hexiao_number'] = $order_virtualgoods_info['hexiao_code'];
		    }else{
		        $data['virtual_info']['hexiao_number'] = '';
		    }
		    
		    //维权信息
		    if (isset($data_OrderInfo_VirtualGoods) && $data_OrderInfo_VirtualGoods['refund_status']==0) {
		        $data['virtual_info']['refund_status_msg'] = '';
		    }else if(isset($data_OrderInfo_VirtualGoods)){
		        //维权说明
		        //单规格
		        if(empty($order_goods[0]['spec_id'])){
		            //已退款
		            $order_refund_finish_sum = OrderRefund::where(['order_id'=>$data_OrderInfo_VirtualGoods['id'],'status'=>REFUND_FINISHED,'goods_id'=>$order_goods[0]['goods_id']])->sum('refund_quantity');
		            //dd($order_refund_finish_sum);
		            //申请退款中
		            $order_refund_doing_sum = OrderRefund::where(['order_id'=>$data_OrderInfo_VirtualGoods['id'],'goods_id'=>$order_goods[0]['goods_id']])
		            ->whereNotIn('status',array(REFUND_FINISHED,REFUND_CLOSE,REFUND_CANCEL))
		            ->sum('refund_quantity');
		        }
		        //多规格
		        else{
		            //已退款
		            $order_refund_finish_sum = OrderRefund::where(['order_id'=>$data_OrderInfo_VirtualGoods['id'],'order_refund.status'=>REFUND_FINISHED,'goods_id'=>$order_goods[0]['goods_id'],'spec_id'=>$order_goods[0]['spec_id']])->sum('refund_quantity');
		            //申请退款中
		            $order_refund_doing_sum = OrderRefund::where(['order_id'=>$data_OrderInfo_VirtualGoods['id'],'goods_id'=>$order_goods[0]['goods_id'],'spec_id'=>$order_goods[0]['spec_id']])
		            ->whereNotIn('status',array(REFUND_FINISHED,REFUND_CLOSE,REFUND_CANCEL))
		            ->sum('refund_quantity');
		        }
		    
		        if( !empty($order_refund_finish_sum) && $order_goods[0]['quantity']==$order_refund_finish_sum){
		            $data['virtual_info']['refund_status_msg'] = '维权完成';
		        } else {
		            $data['virtual_info']['refund_status_msg'] = '该订单有退款申请';
		            if($order_refund_finish_sum>0){
		                $data['virtual_info']['refund_status_msg'] .= '，其中'.$order_refund_finish_sum.'件退款完成';
		            }
		            if($order_refund_doing_sum>0){
		                $data['virtual_info']['refund_status_msg'] .= '，'.$order_refund_doing_sum.'件退款中';
		            }
		        }
		    }
		    //申请退款中
		    $order_refund_sum = OrderRefund::where(['order_id'=>$data['id']])
        		    ->whereNotIn('status',array(REFUND_FINISHED,REFUND_CLOSE,REFUND_CANCEL))
        		    ->sum('refund_quantity');
		    //核销码
		    $rs_order_virtualgoods = OrderVirtualgoods::select('id','hexiao_code','hexiao_status')
                                            		    ->where('merchant_id','=',$this->merchant_id)
                                            		    ->where('order_id','=',$data['id'])
                                            		    ->get();
            $rs_goods_virtual = GoodsVirtual::where(['merchant_id'=>$this->merchant_id,'goods_id'=>$order_goods[0]['goods_id']])->first();
		    if($rs_order_virtualgoods){
		        $var_hexiao = config('varconfig.order_virtualgoods_hexiao_status');
		        $arr = array();
		        $freeze = 0;
                foreach ($rs_order_virtualgoods as $key=>$val) {
                    $val['hexiao_msg'] = isset($var_hexiao[$val['hexiao_status']])?$var_hexiao[$val['hexiao_status']]:'';
                    if($goods_virtual_info['errcode'] == 0){
                        if($data['virtual_info']['time_type']==1 && date('Y-m-d H:i:s')>=$rs_goods_virtual['end_time'] && $val['hexiao_status']==0){
                            $val['hexiao_msg'] = '已失效';
                            $val['hexiao_status'] = 2;
                        }elseif($data['virtual_info']['time_type']==1 && date('Y-m-d H:i:s')<$rs_goods_virtual['start_time'] && $val['hexiao_status']==0){
                            $val['hexiao_msg'] = '未使用';
                            $val['hexiao_status'] = 2;
                        }
                        if($order_refund_sum>0 && $order_refund_sum>$freeze && $data['virtual_info']['time_type']==1 && in_array($val['hexiao_status'], array(0,2)) ){
                            $freeze++;
                            $val['hexiao_msg'] = ($val['hexiao_msg']=='未使用'||$val['hexiao_msg']=='已失效')?($val['hexiao_msg'].'(维权中)'):'维权中';
                            $val['hexiao_status'] = 4;
                        }
                    }
                    
                    $arr[] = $val;
                }
		    }
		    $data['virtual_info']['order_virtualgoods'] = $arr;
		    //一、二维码
		    $token = base64_encode(encrypt($data['id'],'E','H5ChargeOff20171110'));
		    $url_H5ChargeOff = urlencode(ENV('APP_URL').'/weapp/order/h5chargeoff.json?order_id='.$data['id'].'&token='.$token);
		    $url_qrcode = 'http://open.dodoca.com/wxauth/index?return_url='.$url_H5ChargeOff;
		    	
		    $data['virtual_info']['barcode'] = 'data:image/png;base64,'.DNS1D::getBarcodePNG($data['virtual_info']['hexiao_number'], "CODABAR","2","80");
		    $data['virtual_info']['qrcode'] = 'data:image/png;base64,'.DNS2D::getBarcodePNG($url_qrcode, "QRCODE","10","10");
		}
		
		
		//支持的支付方式数据
		$weixin_onoff = 0;    //是否支持微信支付：0->不支持，1->支持
		$delivery_onoff = 0;  //是否支持货到付款：0->不支持，1->支持
		$wxinfo_id = Member::weapp_id();//小程序id
		$weixin_setting_info = WeixinSetting::get_data_by_id($wxinfo_id, $this->merchant_id);//小程序设置信息
		if($weixin_setting_info){
		    $weixin_onoff = $weixin_setting_info['weixin_onoff'];
		    $delivery_onoff = $weixin_setting_info['delivery_onoff'];
		}
		//秒杀、砍价、优惠买单、预约订单、虚拟商品订单、拼团订单不能使用货到付款
		if($data['order_type'] == ORDER_SECKILL || $data['order_type'] == ORDER_BARGAIN || $data['order_type'] == ORDER_SALEPAY || $data['order_type'] == ORDER_APPOINT || $data['order_type'] == ORDER_FIGHTGROUP || $data['order_goods_type'] == ORDER_GOODS_VIRTUAL){
		    $delivery_onoff = 0;
		}
		$data['weixin_onoff'] = $weixin_onoff;
		$data['delivery_onoff'] = $delivery_onoff;
		
		$data['addr'] = (object)[];		//获取收货地址
		$data['store'] = (object)[];	//获取上门自提门店
		$data['selffetchInfo'] = [];	//获取上门数据
		
		if($data['store_id']>0) {
			$storeInfo = Store::get_data_by_id($data['store_id'],$this->merchant_id);
			if($storeInfo) {
				$storeInfo['address'] = $storeInfo['province_name'].$storeInfo['city_name'].$storeInfo['district_name'].$storeInfo['address'];
			}
			$data['store'] = $storeInfo;
		}
		
		if($data['delivery_type']==1 || $data['delivery_type']==3) {
			$orderaddr = OrderAddr::get_data_by_id($data['id'],"id,address_id,consignee,mobile,country_name,province_name,city_name,district_name,address,zipcode");
			if($orderaddr) {
				$data['addr'] = [
					'id'		=>	$orderaddr['address_id'],
					'consignee'	=>	$orderaddr['consignee'],
					'mobile'	=>	$orderaddr['mobile'],
					'address'	=>	$orderaddr['province_name'].$orderaddr['city_name'].$orderaddr['district_name'].$orderaddr['address'],
				];
			}
		} else if($data['delivery_type']==2) {
			if($data['pay_status']==1) {
				$selffetchInfo = OrderSelffetch::select(['store_id','hexiao_code','hexiao_status','hexiao_time'])->where(['order_id'=>$data['id']])->first();
				if($selffetchInfo) {
					$token = base64_encode(encrypt($data['id'],'E','H5ChargeOff20171110'));
					$url_H5ChargeOff = urlencode(ENV('APP_URL').'/weapp/order/h5chargeoff.json?order_id='.$data['id'].'&token='.$token);
					$url_qrcode = 'http://open.dodoca.com/wxauth/index?return_url='.$url_H5ChargeOff;
					
					$selffetchInfo['barcode'] = 'data:image/png;base64,'.DNS1D::getBarcodePNG($selffetchInfo['hexiao_code'], "CODABAR","2","80");
					$selffetchInfo['qrcode'] = 'data:image/png;base64,'.DNS2D::getBarcodePNG($url_qrcode, "QRCODE","10","10");
					
					$selffetchInfo['hexiao_time'] = $selffetchInfo['hexiao_time']!='0000-00-00 00:00:00' ? $selffetchInfo['hexiao_time'] : '';
				}
				$data['selffetchInfo'] = $selffetchInfo;
			}
		}

		$ntime = isset($param['ntime'])?$param['ntime']:date("Y-m-d H:i:s");
		if($mrSetInfo['appoint_enabled']==1 && $ntime) {
			//可选配送日期
			$distribution_start_ymd = date("Y-m-d", strtotime("+ " . $mrSetInfo['advance_min_hour'] . " hour", strtotime($ntime)));
			$distribution_end_ymd = date("Y-m-d", strtotime("+ " . $mrSetInfo['advance_max_day'] . " day", strtotime($ntime)));  //可选开始日期

			$distribution_start_time = date("Y-m-d H:i", strtotime("+ " . $mrSetInfo['advance_min_hour'] . " hour", strtotime($ntime)));

			$ymd = date("Y-m-d", strtotime($ntime));

			$new_time = array();
			for ($i = $distribution_start_ymd; $i <= $distribution_end_ymd;) {
				$day_time = $i . " " . $mrSetInfo['time_slot_end'] . ":00:00";
				if ($ymd == $i && strtotime($distribution_start_time) > strtotime($day_time)) {
					$i = date("Y-m-d", strtotime('+ 1 day', strtotime($i)));
				}

				for ($j = $mrSetInfo['time_slot_start']; $j <= $mrSetInfo['time_slot_end'];) {
					if ($j == 24) {
						$j++;
						continue;
					}
					if ($j < 10) {
						$new_hi = "0" . $j . ":00";
					} else {
						$new_hi = $j . ":00";
					}
					if (strtotime($distribution_start_time) <= strtotime($i . " " . $new_hi)) {
						if(($j+1)<=$mrSetInfo['time_slot_end']){
							if ($j < 10) {
								$new_hi_end = "-0" . $j . ":30";
							}else{
								$new_hi_end = "-".$j . ":30";
							}
							$new_time[$i][] = $new_hi.$new_hi_end;
						}
					}
					if ($j < $mrSetInfo['time_slot_end']) {
						if ($j < 10) {
							$new_hi = "0" . $j . ":30";
						} else {
							$new_hi = $j . ":30";
						}
						if (strtotime($distribution_start_time) <= strtotime($i . " " . $new_hi)) {
							if (($j+1) < 10) {
								$new_j = "-0" . ($j+1) . ":00";
							}else{
								$new_j = "-".($j+1) . ":00";
							}
							$new_time[$i][] = $new_hi.$new_j;
						}
					}
					$j++;
				}

				$i = date("Y-m-d", strtotime('+ 1 day', strtotime($i)));

			}


			$data['appoint_distribution'] = $new_time;
		}

		$data['status'] = $this->orderStatus($data['status'],2);
		return Response::json(array('errcode' => 0, 'errmsg' => '返回成功', 'data' => $data));
	}
	
	/**
	 * 确认收货
	 * @author zhangchangchun@dodoca.com
	 * date 2017-09-06
	 */
	public function postDelivery($id) {
		if(!$id || !is_numeric($id)) {
			return Response::json(array('errcode' => '40001', 'errmsg' => '缺少订单号参数'));
		}
		$orderinfo = $this->OrderInfoModel->select(['id','status'])->where(['id'=>$id,'member_id'=>$this->member_id,'merchant_id'=>$this->merchant_id])->first();
		if (!$orderinfo) {
			return Response::json(array('errcode' => '40005', 'errmsg' => '订单不存在'));
		}
		if($orderinfo['status']==ORDER_SUCCESS) {
			return Response::json(array('errcode' => '0', 'errmsg' => '订单已确认收货'));
		}
		if($orderinfo['status']!=ORDER_SEND) {
			return Response::json(array('errcode' => '40010', 'errmsg' => '订单非待收货状态，不能收货'));
		}
		
		$orderinfo->status = ORDER_SUCCESS;
		$orderinfo->finished_time = date('Y-m-d H:i:s');
		if($orderinfo->save()){
			//发送到队列
			$job = new OrderDelivery($id,$this->merchant_id);
            $this->dispatch($job);
			return Response::json(array('errcode' => '0', 'errmsg' => '订单已确认收货'));
		} else {
			return Response::json(array('errcode' => '40011', 'errmsg' => '订单收货失败'));
		}
	}
	
	/**
	 * 延长收货
	 * @author zhangchangchun@dodoca.com
	 * date 2017-09-06
	 */
	public function postExtendDelivery($id) {
		if(!$id || !is_numeric($id)) {
			return Response::json(array('errcode' => '40001', 'errmsg' => '缺少订单号参数'));
		}
		$orderinfo = $this->OrderInfoModel->select(['id','status','extend_days'])->where(['id'=>$id,'member_id'=>$this->member_id,'merchant_id'=>$this->merchant_id])->first();
		if (!$orderinfo) {
			return Response::json(array('errcode' => '40005', 'errmsg' => '订单不存在'));
		}
		if($orderinfo['status']!=ORDER_SEND) {
			return Response::json(array('errcode' => '40006', 'errmsg' => '订单非待收货状态，不能延长收货时间'));
		}
		if($orderinfo['extend_days']>0) {
			return Response::json(array('errcode' => '40008', 'errmsg' => '订单已延长过收货时间'));
		}
		
		$orderinfo->extend_days = DELIVERY_EXTEND_DAYS;
		if($orderinfo->save()){
			return Response::json(array('errcode' => '0', 'errmsg' => '成功延期'.DELIVERY_EXTEND_DAYS.'天收货'));
		} else {
			return Response::json(array('errcode' => '40009', 'errmsg' => '订单收货延期失败'));
		}
	}
	
	/**
	 * 查看包裹
	 * @author zhangchangchun@dodoca.com
	 * date 2017-09-06
	 * $id 包裹id
	 */
	public function getPackage($id) {
		if(!$id || !is_numeric($id)) {
			return Response::json(array('errcode' => '40002', 'errmsg' => '缺少包裹id参数'));
		}
		$package = OrderPackage::get_data_by_id($id,'id,order_id,order_sn,logis_code,logis_name,logis_no,is_no_express');
		if(!$package || $package['is_no_express']==1) {
			return Response::json(array('errcode' => '40012', 'errmsg' => '包裹信息不存在'));
		}
		$data = $package = json_decode($package,true);
		$orderinfo = $this->OrderInfoModel->get_data_by_id($package['order_id'],$this->merchant_id,"*");
		if(!$orderinfo || $orderinfo['member_id']!=$this->member_id) {
			return Response::json(array('errcode' => '40005', 'errmsg' => '订单不存在'));
		}
		$packageitem = OrderPackageItem::get_data_list(['package_id'=>$id,'order_id'=>$orderinfo['id']],"order_goods_id,quantity");
		if(!$packageitem) {
			return Response::json(array('errcode' => '40012', 'errmsg' => '包裹信息不存在'));
		}
		//$data['logis_logo'] = DeliveryCompany::where(['code'=>$package['logis_code']])->pluck('waybill_bg');
		//if($data['logis_logo']) {
			$data['logis_logo'] = ENV('STATIC_DOMAIN').'/applet_mch/images/delivery/'.$package['logis_code'].'.png';	//后台逗比写死的
		//}
		foreach($packageitem as $key => $item) {
			$ordergoods = OrderGoods::get_data_by_id($item['order_goods_id'],$this->merchant_id,"id,order_id,goods_id,goods_name,goods_img,spec_id,price,pay_price,quantity,props,refund_status");
			if($ordergoods) {
				$data['goods'][] = json_decode($ordergoods,true);
			}
		}
		$data['package'] = [];
		if($package['logis_code'] && $package['logis_no']) {
			//调用物流接口
			$packagemsg = Logistics::search_logistic($package);
			
			//请求物流日志	
			CommonApi::wlog([
				'custom'    	=>    	'request-package_'.$orderinfo['id'],
				'merchant_id'   =>    	$orderinfo['merchant_id'],
				'member_id'     =>    	$orderinfo['member_id'],
				'content'		=>		'require->'.json_encode($package,JSON_UNESCAPED_UNICODE).',result->'.json_encode($packagemsg,JSON_UNESCAPED_UNICODE),
			]);
			
			if(isset($packagemsg['status']) && isset($packagemsg['data']['data'])) {
				$data['package'] = array_reverse($packagemsg['data']['data']);
			}
		}
		return Response::json(array('errcode' => '0', 'errmsg' => '返回成功', 'data' => $data));
	}
	
	/**
	 * 订单评价（获取订单）
	 * @author zhangchangchun@dodoca.com
	 * date 2017-09-06
	 */
	public function getComment($id) {
		if(!$id || !is_numeric($id)) {
			return Response::json(array('errcode' => '40001', 'errmsg' => '缺少订单号参数'));
		}
		$ApptService = new ApptService;		
		$orderinfo = $this->OrderInfoModel->select(['id','status','order_sn','comment_status'])->where(['id'=>$id,'member_id'=>$this->member_id,'merchant_id'=>$this->merchant_id])->first();
		if (!$orderinfo) {
			return Response::json(array('errcode' => '40005', 'errmsg' => '订单不存在'));
		}
		if($orderinfo['status']!=ORDER_SUCCESS) {
			return Response::json(array('errcode' => '40079', 'errmsg' => '该订单不能评价'));
		}
		$data = json_decode($orderinfo,true);
		/*if($orderinfo['comment_status']!=0) {
			return Response::json(array('errcode' => '40080', 'errmsg' => '该订单已评价过'));
		}*/
		$memberinfo = Member::get();
		$ordergoods = OrderGoods::get_data_list(['order_id'=>$orderinfo['id'],'merchant_id'=>$this->merchant_id],"id,order_id,goods_id,goods_name,goods_img,spec_id,price,pay_price,quantity,props");
		if($ordergoods) {
			foreach($ordergoods as $key => $ginfo) {
				$ginfo['name'] = $memberinfo['name'];
				$ginfo['avatar'] = $memberinfo['avatar'];
				$data['goods'][] = $ginfo;
			}
		}
		return Response::json(array('errcode' => '0', 'errmsg' => '返回成功', 'data' => $data));
	}
	
	/**
	 * 发起订单评价
	 * @author zhangchangchun@dodoca.com
	 * date 2017-09-06
	 */
	public function postComment($id,Request $request) {
		if(!$id || !is_numeric($id)) {
			return Response::json(array('errcode' => '40001', 'errmsg' => '缺少订单号参数'));
		}
		$orderinfo = $this->OrderInfoModel->select(['id','status','comment_status','nickname'])->where(['id'=>$id,'member_id'=>$this->member_id,'merchant_id'=>$this->merchant_id])->first();
		if (!$orderinfo) {
			return Response::json(array('errcode' => '40005', 'errmsg' => '订单不存在'));
		}
		if($orderinfo['status']!=ORDER_SUCCESS) {
			return Response::json(array('errcode' => '40079', 'errmsg' => '该订单不能评价'));
		}
		if($orderinfo['comment_status']!=0) {
			return Response::json(array('errcode' => '40080', 'errmsg' => '该订单已评价过'));
		}
		$mrSetInfo = MerchantSetting::get_data_by_id($this->merchant_id);
		if(!$mrSetInfo || $mrSetInfo['is_comment_open']==0) {
			return Response::json(array('errcode' => '40082', 'errmsg' => '商户尚未开启评价功能'));
		}
		$data = $request->all();
		$goods = $data['data'];
		
		if(!$goods || !is_array($goods)) {
			return Response::json(array('errcode' => '40003', 'errmsg' => '评论不能为空'));
		}
		
		foreach($goods as $key => $ginfo) {
			$ordergoodsinfo = OrderGoods::get_data_by_id($ginfo['id'],$this->merchant_id,"id,member_id,goods_id,goods_name,goods_img,spec_id,quantity,price,pay_price,props");
			if($ordergoodsinfo) {
				$goods[$key]['order_goods'] = json_decode($ordergoodsinfo,true);
			} else {
				return Response::json(array('errcode' => '40005', 'errmsg' => '订单商品不存在'));
			}
		}
		$orderinfo->comment_status = 1;
		if($orderinfo->save()){
			foreach($goods as $key => $ginfo) {
				$comdata = [
					'merchant_id'	=>	$this->merchant_id,
					'order_id'		=>	$orderinfo['id'],
					'order_goods_id'=>	$ginfo['id'],
					'goods_id'		=>	$ginfo['order_goods']['goods_id'],
					'member_id'		=>	$this->member_id,
					'props_str'		=>	$ginfo['order_goods']['props'],
					'nickname'		=>	$orderinfo['nickname'],
					'has_img'		=>	(isset($ginfo['imgages']) && is_array($ginfo['imgages']) && $ginfo['imgages']) ? 1 : 0,
					'content'		=>	isset($ginfo['content']) ? $ginfo['content'] : '',
					'is_show'		=>	$mrSetInfo['comment_type']==1 ? 1 : 0,
					'score'			=>	isset($ginfo['stars']) ? (int)$ginfo['stars'] : 5,
					'nopass'		=>	$mrSetInfo['comment_type']==1 ? 0 : 1,	//审核状态 0:审核通过 1:审核不通过
				];
				$orComId = OrderComment::insert_data($comdata);
				if($orComId && $comdata['has_img']==1) {
					foreach($ginfo['imgages'] as $img) {
						$comimgdata = [
							'merchant_id'	=>	$this->merchant_id,
							'comment_id'	=>	$orComId,
							'goods_id'		=>	$ginfo['order_goods']['goods_id'],
							'img'			=>	$img,
							'is_delete'		=>	1,
						];
						OrderCommentImg::insert_data($comimgdata);
					}
				}
			}
			return Response::json(array('errcode' => '0', 'errmsg' => '评论成功'));
		} else {
			return Response::json(array('errcode' => '40081', 'errmsg' => '评论失败'));
		}
	}
	
	/**
	 * 下单验证数据
	 * @author zhangchangchun@dodoca.com
	 * date 2017-09-06
	 */
	public function getOrderVerify($id,Request $request) {
		if(!$id || !is_numeric($id)) {
			return Response::json(array('errcode' => '40001', 'errmsg' => '缺少订单号参数'));
		}
		$ShipmentService = new ShipmentService;
		$reqdata = $request->all();
		$coupon_id = isset($reqdata['coupon_id']) ? (int)$reqdata['coupon_id'] : 0;	//是否使用优惠券
		$is_discount = isset($reqdata['is_discount']) ? (int)$reqdata['is_discount'] : 0;	//是否使用满减
		$add_id = isset($reqdata['add_id']) ? (int)$reqdata['add_id'] : 0;
		$orderinfo = $this->OrderInfoModel->select(['id','status','amount','shipment_fee','order_type'])->where(['id'=>$id,'member_id'=>$this->member_id,'merchant_id'=>$this->merchant_id])->first();
		if(!$orderinfo) {
			return Response::json(array('errcode' => '40005', 'errmsg' => '订单不存在'));
		}
		$order_goods = OrderGoods::select(['goods_id','spec_id','quantity','price','pay_price','is_pinkage','postage','shipment_id','valuation_type','start_standard','start_fee','add_standard','add_fee'])->where(['order_id'=>$id])->get()->toArray();
		if(!$order_goods) {
			return Response::json(array('errcode' => '40005', 'errmsg' => '订单不存在'));
		}

        if($coupon_id) {	//若使用优惠券，强制关闭满减，做兼容老版本
            $is_discount = 0;
        }
				
		$data = [
			'shipment_fee'		=>	0,	    //运费
			'discount_amount'	=>	0,		//满减抵扣
			'coupon_amount'		=>	0,		//优惠券抵扣金额
			'credit_amount'		=>	0,		//积分数，为0则不显示
			'credit_ded_amount'	=>	0,		//积分抵扣金额，例如：10积分抵扣1元
		];
		//还原运费
        $orderinfo['amount'] = $orderinfo['amount']-$orderinfo['shipment_fee'];
		//若有满减金额，还原实际金额
		foreach($order_goods as $key => $ginfo) {
			$dis_amount = abs(OrderGoodsUmp::where(['order_id'=>$id,'goods_id'=>$ginfo['goods_id'],'spec_id'=>$ginfo['spec_id'],'ump_type'=>7])->pluck('amount'));
			if($dis_amount) {
				$order_goods[$key]['pay_price'] += $dis_amount;
				$orderinfo['amount'] +=  $dis_amount;
			}
		}
		
		//计算运费
		if($add_id) {
			$memberaddr = MemberAddress::get_data_by_id($add_id,$this->member_id);
			if(!$memberaddr) {
				return Response::json(array('errcode' => '110001', 'errmsg' => '收货地址不存在'));
			}
			if($is_discount==0 || ($is_discount==1 && $order_goods[0]['is_pinkage']==0)) {
				$ShipmentGoods = [
					'merchant_id'	=>	$this->merchant_id,
					'member_id'		=>	$this->member_id,
					'order_id'		=>	$id,
					'province'		=>	$memberaddr['province'],
					'city'			=>	$memberaddr['city'],
				];
				$sptresult = $this->BuyService->getShipmentGoods($ShipmentGoods,2);
				if($sptresult && isset($sptresult['errcode']) && $sptresult['errcode']==0) {
					$data['shipment_fee'] = $sptresult['data'];
				} else {
					return Response::json(array('errcode' => '110001', 'errmsg' => $sptresult['errmsg']));
				}
			}			
		}

		//满减抵扣		
		$dis_amount = OrderUmp::where(['order_id'=>$id,'ump_type'=>7])->pluck('amount');
		if($dis_amount) {
			$data['discount_amount'] = abs($dis_amount);
		}

		//优惠券抵扣
		if($coupon_id) {
			if(!$this->get_reduction_power($orderinfo['order_type'],'coupon')) {
				return Response::json(array('errcode' => '110001', 'errmsg' => '订单不能使用优惠券'));
			}
			$CouponService = new CouponService;
			$coupdata = [
				'merchant_id'	=>	$this->merchant_id,
				'member_id'		=>	$this->member_id,
				'coupon_code_id'=>	$coupon_id,
				'order_goods'	=>	$order_goods,
			];
			$couponinfo = $CouponService->getDiscount($coupdata);
			if($couponinfo && isset($couponinfo['errcode']) && $couponinfo['errcode']==0) {
				$order_goods = $this->BuyService->split_coupon_price($order_goods,$couponinfo['data']);
				if($order_goods) {
					foreach($order_goods as $key => $goodinfo) {
						if(isset($goodinfo['coupon_discount_price']) && $goodinfo['coupon_discount_price']>0) {
							$data['coupon_amount'] += $goodinfo['coupon_discount_price'];
						}						
					}
				}
			} else {
				return Response::json(array('errcode' => '110001', 'errmsg' => $couponinfo['errmsg']));
			}
		}
        $amount = $orderinfo['amount'];
        $postageinfo = OrderUmp::where(['order_id'=>$id,'ump_type'=>8])->first();
		if($coupon_id) {	//使用优惠券
			$amount = (float)sprintf('%0.2f',($orderinfo['amount']-$data['coupon_amount']));
			if($postageinfo && $postageinfo['shipment_fee']>0) {	//若满包邮 归还运费
				$data['shipment_fee'] = $postageinfo['shipment_fee'];
			}
		} else {
		    if($is_discount==1){//使用满减
                $amount = (float)sprintf('%0.2f',($orderinfo['amount']-$data['discount_amount']));
            }
            //满包邮
			if($postageinfo) {
				if($is_discount==1) {	//使用满包邮
					$data['shipment_fee'] = 0;//如果使用满减且订单满包邮运费重置为0
				} else if($is_discount==0 && $postageinfo['shipment_fee']>0) {	//不使用包邮优惠
					$data['shipment_fee'] = $postageinfo['shipment_fee'];
				}
			}
		}

		//积分抵扣
		$get_use_credit = $this->BuyService->get_credit(['merchant_id'=>$this->merchant_id,'member_id'=>$this->member_id,'amount'=>$amount]);
		if($get_use_credit && isset($get_use_credit['credit_amount']) && isset($get_use_credit['credit_ded_amount'])) {
			$data['credit_amount'] = $get_use_credit['credit_amount'];
			$data['credit_ded_amount'] = $get_use_credit['credit_ded_amount'];
		}
		
		return Response::json(array('errcode' => '0', 'errmsg' => '返回成功', 'data' => $data));
	}
	
	/**
	 * 确认下单（直接购买，购物车购买）
	 * @author zhangchangchun@dodoca.com
	 * date 2017-09-06
	 */
	public function postOrder(Request $request) {
		$merchant_info = Merchant::get_data_by_id($this->member_id);
		if($merchant_info && $merchant_info['version_id'] == 5){
			if(strtotime('+1 month', strtotime($merchant_info['created_time'])) < time()){
				return ['errcode' => 99007, 'errmsg' => '商家服务已到期，无法下单'];
			}
		}
		$goods = [];
		$type = isset($request['type']) ? (int)$request['type'] : 0;
		$source = isset($request['source']) ? (int)$request['source'] : 0;
		$source_id = isset($request['source_id']) ? (int)$request['source_id'] : 0;
		if(!in_array($type,[1,2])) {
			return Response::json(array('errcode' => '40083', 'errmsg' => '下单类型错误'));
		}
		if($type==1) {	//直接购买
			$goods_id = isset($request['goods_id']) ? (int)$request['goods_id'] : 0;
			$spec_id = isset($request['spec_id']) ? (int)$request['spec_id'] : 0;
			$quantity = isset($request['quantity']) ? (int)$request['quantity'] : 0;
			if(!$goods_id || !$quantity) {
				return Response::json(array('errcode' => '40084', 'errmsg' => '下单参数错误'));
			}
			$goods[] = [
				'goods_id'	=>	$goods_id,
				'spec_id'	=>	$spec_id,
				'sum'		=>	$quantity,
			];
		} else if($type==2) {	//购物车购买
			$cart = isset($request['cart']) ? $request['cart'] : [];
			if(!$cart || !is_array($cart)) {
				return Response::json(array('errcode' => '40084', 'errmsg' => '下单参数错误'));
			}
			$carts = CartService::getLists(['merchant_id'=>$this->merchant_id,'member_id'=>$this->member_id]);
			if($carts  && isset($carts ['errcode']) && $carts ['errcode']==0 && isset($carts ['data'])) {
				if(!$carts ['data']) {
					return Response::json(array('errcode' => '40058', 'errmsg' => '购物车中商品无效'));
				}
				foreach($carts['data'] as $key => $cinfo) {
					if(in_array($key,$cart)) {
						$goods[] = [
							'goods_id'	=>	$cinfo['goods_id'],
							'spec_id'	=>	$cinfo['goods_spec_id'],
							'sum'		=>	$cinfo['quantity'],
						];
					}
				}
			} else {
				return Response::json(array('errcode' => '40084', 'errmsg' => ((isset($carts ['errmsg']) && $carts ['errmsg']) ? $carts ['errmsg'] : '购物车数据为空')));
			}
		}
		if(!$goods) {
			return Response::json(array('errcode' => '40058', 'errmsg' => '购物车数据为空'));
		}
		
		//验证商品有效性
		foreach($goods as $key => $ginfo) {
			$goodsinfo = Goods::get_data_by_id($ginfo['goods_id'],$this->merchant_id);
			if(!$goodsinfo || $goodsinfo['onsale']==0) {
				return Response::json(array('errcode' => '40058', 'errmsg' => '购买商品无效'));
			}
			$goods[$key]['price'] = $goodsinfo['price'];
			if($ginfo['spec_id']) {
				$gdspecinfo = GoodsSpec::get_data_by_id($ginfo['spec_id'],$this->merchant_id);
				if($gdspecinfo) {
					$goods[$key]['price'] = $gdspecinfo['price'];
				} else {
					return Response::json(array('errcode' => '40058', 'errmsg' => '购买商品规格无效'));
				}
			}
		}
		
		$buydata = [
			'merchant_id'	=>	$this->merchant_id,
	 		'member_id'		=>	$this->member_id,
	 		'order_type'	=>	1,
			'goods'			=>	$goods,
			'source'	=>	$source,
			'source_id'		=>	$source_id
		];
		
		DB::beginTransaction();
		$buyinfo = $this->BuyService->createorder($buydata);
		if($buyinfo && isset($buyinfo['errcode']) && $buyinfo['errcode']==0 && $buyinfo['data']) {
			DB::commit();
			//若下单成功，清理购物车
			if($type==2) {
				CartService::delete(['merchant_id'=>$this->merchant_id,'member_id'=>$this->member_id,'ids'=>$cart]);
			}
			
			$data = array(
				'order_id'	=>	$buyinfo['data']['order_id'],
				'order_sn'	=>	$buyinfo['data']['order_sn'],
			);
			return Response::json(array('errcode' => '0', 'errmsg' => '下单成功', 'data' => $data));
		} else {
			DB::rollBack();
			return Response::json(array('errcode' => '40059', 'errmsg' => (isset($buyinfo['errmsg']) ? $buyinfo['errmsg'] : '下单失败，请重试')));
		}		
	}
	
	/**
	 * 提交订单/去支付
	 * @author zhangchangchun@dodoca.com
	 * date 2017-09-06
	 */
	public function buyOrder(Request $request) {
		$data = $request->all();
		$order_id = isset($data['order_id']) ? (int)$data['order_id'] : 0;
		$delivery_type = isset($data['delivery_type']) ? (int)$data['delivery_type'] : 0;	//配送方式：1-物流配送，2-上门自提，3-同城配送
		$addr_id = isset($data['addr_id']) ? (int)$data['addr_id'] : 0;
		$store_id = isset($data['store_id']) ? (int)$data['store_id'] : 0;
		$member_name = isset($data['member_name']) ? $data['member_name'] : '';
		$member_mobile = isset($data['member_mobile']) ? $data['member_mobile'] : '';
		$customer = isset($data['customer']) ? $data['customer'] : '';
		$customer_mobile = isset($data['customer_mobile']) ? $data['customer_mobile'] : '';
		$virtual_name = isset($data['virtual_name']) ? $data['virtual_name'] : '';
		$virtual_mobile = isset($data['virtual_mobile']) ? $data['virtual_mobile'] : '';
		$is_credit = isset($data['is_credit']) ? (int)$data['is_credit'] : 0;
		$coupon_id = isset($data['coupon_id']) ? (int)$data['coupon_id'] : 0;	//是否使用优惠券
		$is_discount = isset($data['is_discount']) ? (int)$data['is_discount'] : 1;	//是否使用满减
		$appoint_distribution_date = isset($data['appoint_distribution_date']) ? $data['appoint_distribution_date'] : '';
		if($coupon_id) {	//若使用优惠券，强制关闭满减，做兼容老版本
			$is_discount = 0;
		}
		$memo = isset($data['memo']) ? $data['memo'] : '';
		$fields = isset($data['fields']) ? json_encode($data['fields'],JSON_UNESCAPED_UNICODE) : '';
		$pay_type = isset($data['pay_type']) ? (int)$data['pay_type'] : ORDER_PAY_WEIXIN;    //支付方式：1-微信支付，2-货到付款
		
		$GoodsService = new GoodsService;
		$CouponService = new CouponService;
		if(!$order_id) {
			return Response::json(array('errcode' => '40001', 'errmsg' => '缺少订单号参数'));
		}
		$field = ['id','merchant_id','order_sn','member_id','order_type','status','amount','shipment_fee','pay_status','pay_type','appid','delivery_type','order_goods_type'];
		$orderinfo = $this->OrderInfoModel->select($field)->where(['id'=>$order_id,'member_id'=>$this->member_id,'merchant_id'=>$this->merchant_id])->first();
		if(!$orderinfo) {
			return Response::json(array('errcode' => '40005', 'errmsg' => '订单不存在'));
		}
		if($orderinfo['status']<ORDER_SUBMIT) {
			return Response::json(array('errcode' => '40091', 'errmsg' => '订单已取消'));
		}
		if($orderinfo['pay_status']==1) {
			return Response::json(array('errcode' => '40089', 'errmsg' => '订单已支付'));
		}
		$order_goods = OrderGoods::select(['id','goods_id','goods_name','spec_id','weight','volume','quantity','price','pay_price','is_pinkage','postage','shipment_id','valuation_type','start_standard','start_fee','add_standard','add_fee','stock_type'])->where(['order_id'=>$order_id])->get()->toArray();
		if(!$order_goods) {
			return Response::json(array('errcode' => '40005', 'errmsg' => '订单不存在'));
		}
		
		//验证支付方式
		if($pay_type != ORDER_PAY_WEIXIN && $pay_type != ORDER_PAY_DELIVERY){
		    return Response::json(array('errcode' => '42001', 'errmsg' => '支付方式不正确'));
		}
		$weixin_onoff = 0;    //是否支持微信支付：0->不支持，1->支持
		$delivery_onoff = 0;  //是否支持货到付款：0->不支持，1->支持
		$wxinfo_id = Member::weapp_id();//小程序id
		$weixin_setting_info = WeixinSetting::get_data_by_id($wxinfo_id, $this->merchant_id);//小程序设置信息
		if($weixin_setting_info){
		    $weixin_onoff = $weixin_setting_info['weixin_onoff'];
		    $delivery_onoff = $weixin_setting_info['delivery_onoff'];
		}
		//秒杀、砍价、优惠买单、预约订单、虚拟商品订单、拼团订单不能使用货到付款
		if($orderinfo['order_type'] == ORDER_SECKILL || $orderinfo['order_type'] == ORDER_BARGAIN || $orderinfo['order_type'] == ORDER_SALEPAY || $orderinfo['order_type'] == ORDER_APPOINT || $orderinfo['order_type'] == ORDER_FIGHTGROUP || $orderinfo['order_goods_type'] == ORDER_GOODS_VIRTUAL){
		    $delivery_onoff = 0;
		}
		if($pay_type == ORDER_PAY_WEIXIN && $weixin_onoff == 0){
		    return Response::json(array('errcode' => '42002', 'errmsg' => '不支持微信支付'));
		}
		if($pay_type == ORDER_PAY_DELIVERY && $delivery_onoff == 0){
		    return Response::json(array('errcode' => '42003', 'errmsg' => '不支持货到付款'));
		}
		
		//验证库存（付款扣库存）
		foreach($order_goods as $key => $ginfo) {
			if($ginfo['stock_type']==0) {
				$stockdata = [
					'merchant_id'	=>	$this->merchant_id,
					'goods_id'		=>	$ginfo['goods_id'],
					'goods_spec_id'	=>	$ginfo['spec_id'],
					'stock_num'		=>	$ginfo['quantity'],
				];
				if($orderinfo['order_type']==ORDER_FIGHTGROUP) {	//拼团订单
					$stockdata['activity'] = 'tuan';
				}
				if($orderinfo['order_type']==ORDER_APPOINT) {	//预约订单
					$stockdata['date'] = OrderAppt::where(['order_id'=>$order_id])->pluck('appt_date');
				}
				$stock = $GoodsService->getGoodsStock($stockdata);
				if($stock && isset($stock['errcode']) && isset($stock['data']) && $stock['errcode']==0) {
					if($stock['data']<$ginfo['quantity']) {
						return array('errcode'=>40059,'errmsg'=>$ginfo['goods_name'].'->商品库存不足');
					}
				} else {
					return array('errcode'=>40059,'errmsg'=>(isset($stock['errmsg']) ? $stock['errmsg'] : '库存获取失败'));
				}
			}
		}

        $amount = $orderinfo['amount']-$orderinfo['shipment_fee'];	//订单金额(实付)
		$coupon_dis_amount = 0;	//券优惠总金额
		$dis_dis_amount = 0;	//满减抵扣金额
		$credit_dis_count = 0;	//抵扣积分数
		$credit_dis_amount = 0;	//积分抵扣金额
		$order_ump = [];
		$order_goods_ump_arr = [];
		
		if($orderinfo['status']==ORDER_SUBMIT) {	//尚未下单
			
			//计算运费
			$shipment_fee = $orderinfo['shipment_fee'];
			
			if($this->get_reduction_power($orderinfo['order_type'],'delivery') && $orderinfo['order_goods_type'] != ORDER_GOODS_VIRTUAL) {
				if($delivery_type==1) {	//物流配送
					if($addr_id) {
						$memberaddr = MemberAddress::get_data_by_id($addr_id,$this->member_id);
						if(!$memberaddr) {
							return Response::json(array('errcode' => '110001', 'errmsg' => '收货地址不存在'));
						}
						if($is_discount==0 || ($is_discount==1 && $order_goods[0]['is_pinkage']==0)) {
							$ShipmentGoods = [
								'merchant_id'	=>	$this->merchant_id,
								'member_id'		=>	$this->member_id,
								'order_id'		=>	$order_id,
								'province'		=>	$memberaddr['province'],
								'city'			=>	$memberaddr['city'],
							];
							$sptresult = $this->BuyService->getShipmentGoods($ShipmentGoods,2);
							if($sptresult && isset($sptresult['errcode']) && $sptresult['errcode']==0) {
								$shipment_fee = $sptresult['data'];
							} else {
								return Response::json(array('errcode' => '110001', 'errmsg' => $sptresult['errmsg']));
							}
						}
					} else {
						return Response::json(array('errcode' => '110001', 'errmsg' => '收货地址不能为空'));
					}
				} else if($delivery_type==2) {	//上门自提
					$shipment_fee = 0; //上门自提运费为0
					if(!$store_id) {
						return Response::json(array('errcode' => '110001', 'errmsg' => '上门自提门店不能为空'));
					}
				} else if($delivery_type==3) {	//同城配送
					$memberaddr = MemberAddress::get_data_by_id($addr_id,$this->member_id);
					if(!$memberaddr) {
						return Response::json(array('errcode' => '110001', 'errmsg' => '收货地址不存在'));
					}
					$shipment_fee = 0;
					if(!$store_id) {
						return Response::json(array('errcode' => '110001', 'errmsg' => '配送门店不能为空'));
					}
				} else {
					return Response::json(array('errcode' => '110001', 'errmsg' => '配送方式不合法'));
				}
			}

			//处理满减--满包邮
            $postageinfo = OrderUmp::where(['order_id'=>$order_id,'ump_type'=>8])->first();
            if($postageinfo) {
				if($is_discount == 1) {
					 $shipment_fee = 0;//如果订单满包邮运费重置为0
				} else if($is_discount==0 && $postageinfo['shipment_fee']>0) {	//不使用满包邮
					$shipment_fee = $postageinfo['shipment_fee'];
				}
			}

			//处理满减 - 不使用满减
			if($is_discount==0) {
				foreach($order_goods as $key => $ginfo) {
					$dis_amount = abs(OrderGoodsUmp::where(['order_id'=>$order_id,'goods_id'=>$ginfo['goods_id'],'spec_id'=>$ginfo['spec_id'],'ump_type'=>7])->pluck('amount'));
					if($dis_amount) {
						$order_goods[$key]['pay_price'] += $dis_amount;
					}
				}
				$dis_amount = abs(OrderUmp::where(['order_id'=>$order_id,'ump_type'=>7])->pluck('amount'));
				if($dis_amount) {
					$dis_dis_amount = $dis_amount;
					$amount = $amount+$dis_dis_amount;
				}

				/*//不使用满减加上本方法开始时$amount减掉的运费
                $amount = $amount + $shipment_fee;*/
			}
			
			//优惠券抵扣
			if($coupon_id) {
				if(!$this->get_reduction_power($orderinfo['order_type'],'coupon')) {
					return Response::json(array('errcode' => '110001', 'errmsg' => '订单不能使用优惠券'));
				}
				
				//处理优惠券
				$coupdata = [
					'merchant_id'	=>	$this->merchant_id,
					'member_id'		=>	$this->member_id,
					'coupon_code_id'=>	$coupon_id,
					'order_goods'	=>	$order_goods,
				];
				$couponinfo = $CouponService->getDiscount($coupdata);
				if($couponinfo && isset($couponinfo['errcode']) && $couponinfo['errcode']==0) {
					$order_goods = $this->BuyService->split_coupon_price($order_goods,$couponinfo['data']);
					if($order_goods) {
						$coupon_dis_amount = 0;
						foreach($order_goods as $key => $v) {
							if(isset($v['marking_only'])) {
								$order_goods_ump_arr[] = $v['marking_only'];
								unset($v['marking_only']);
							}
							if(isset($order_goods[$key]['coupon_discount_price']) && $order_goods[$key]['coupon_discount_price']>0) {
								$coupon_dis_amount += $order_goods[$key]['coupon_discount_price'];
							}
						}
						if($coupon_dis_amount>0) {
							$order_ump[] = [
								'ump_type'	=>	2,
								'amount'	=>	-$coupon_dis_amount,
								'memo'		=>	$this->varconfig[2].''.$coupon_dis_amount.'元',
							];
						}
					}
				} else {
					return Response::json(array('errcode' => '110001', 'errmsg' => (isset($couponinfo['errmsg']) ? $couponinfo['errmsg'] : '获取优惠券失败，请重试')));
				}
			}
						
			//积分抵扣
			if($coupon_id) {
				$crt_amount = (float)sprintf('%0.2f',($amount-$coupon_dis_amount));
			} else if($is_discount==1 && $dis_dis_amount>0) {	//使用满减
				$crt_amount = (float)sprintf('%0.2f',($amount-$dis_dis_amount));
			} else {
				$crt_amount = $amount;
			}
			
			if($is_credit==1) {
				$get_use_credit = $this->BuyService->get_credit(['merchant_id'=>$this->merchant_id,'member_id'=>$this->member_id,'amount'=>$crt_amount]);
				if($get_use_credit && isset($get_use_credit['credit_amount']) && isset($get_use_credit['credit_ded_amount']) && $get_use_credit['credit_amount']>0 && $get_use_credit['credit_ded_amount']>0) {
					$credit_dis_count = $get_use_credit['credit_amount'];
					$credit_dis_amount = $get_use_credit['credit_ded_amount'];
					
					$order_ump[] = [
						'ump_type'	=>	3,
						'amount'	=>	-$get_use_credit['credit_ded_amount'],
						'credit'	=>	$get_use_credit['credit_amount'],
						'memo'		=>	$this->varconfig[3].''.$get_use_credit['credit_ded_amount'].'元',
					];
					
					$order_goods = $this->BuyService->split_credit_price($order_goods,$get_use_credit);
					if($order_goods) {
						foreach($order_goods as $key => $v) {
							if(isset($v['marking_only'])) {
								$order_goods_ump_arr[] = $v['marking_only'];
								unset($v['marking_only']);
							}
						}
					}
				}
			}
						
			//上门自提运费
			if($delivery_type==2) {
				$shipment_fee = 0;
			}
			
			//同城配送计算运费
			if($delivery_type==3 && ($is_discount==0 || ($is_discount==1 && !$postageinfo))) {
				$mrSetInfo = MerchantSetting::get_data_by_id($this->merchant_id);
				if($mrSetInfo['city_cost']>0) {
					$shipment_fee = $mrSetInfo['city_cost'];
					
					//获取会员信息
					$memberinfo = MemberModel::get_data_by_id($this->member_id,$this->merchant_id);
					if(!$memberinfo) {
						return array('errcode'=>40059,'errmsg'=>'会员不存在');
					}
					
					//获取会员卡信息
					if($memberinfo['member_card_id']) {
						$cardinfo = MemberCard::get_data_by_id($memberinfo['member_card_id'],$this->merchant_id);
						if($cardinfo) {
							if($order_goods[0]['is_pinkage']==1 && $cardinfo['is_postage_free']==1) {
								$shipment_fee = 0;
							}
						}
					}
				} else {
					$shipment_fee = 0;
				}
			}
			$amount = (float)sprintf('%0.2f',($amount+$shipment_fee-$coupon_dis_amount-$credit_dis_amount));
			$orderinfo['amount'] = $amount;
			if($amount<0) {
				$amount = 0;
				$orderinfo['amount'] = 0;
			}
			
			if($orderinfo['order_type']==ORDER_APPOINT) {	//预约订单更新数据
				OrderAppt::update_data($orderinfo['id'],$orderinfo['merchant_id'],['customer'=>$customer,'customer_mobile'=>$customer_mobile]);
			}
			
			//虚拟商品订单数据
			if($orderinfo['order_goods_type'] == ORDER_GOODS_VIRTUAL){
			    $member_name = $virtual_name;
			    $member_mobile = $virtual_mobile;
			}

			//提交数据
			$odata = [
				'status'		=>	ORDER_TOPAY,
				'extend_info'	=>	$fields,
				'shipment_fee'	=>	$shipment_fee,
				'amount'		=>	$amount,
				'memo'			=>	$memo,
				'store_id'		=>	$store_id,
				'member_name'	=>	$member_name,
				'member_mobile'	=>	$member_mobile,
				'delivery_type'	=>	$delivery_type,
			];
			
			if($amount==0 || $pay_type==ORDER_PAY_DELIVERY) {
			    
			    $odata['pay_type'] = $pay_type;//支付方式
			    
				$odata['status'] = ORDER_TOSEND;
				$odata['pay_status'] = 1;
				$odata['pay_time'] = date("Y-m-d H:i:s");
				
				//预约订单 待收货
				if($orderinfo['order_type']==ORDER_APPOINT) {
					$odata['status'] = ORDER_SEND;
				}
				
				//优惠买单订单
				if($orderinfo['order_type']==ORDER_SALEPAY) {
					$odata['status'] = ORDER_SUCCESS;
					$odata['finished_time'] = date("Y-m-d H:i:s",time()-7*86400);
				}
				
				//上门自提订单
				if($delivery_type==2) {
					$odata['status'] = ORDER_FORPICKUP;
				}
				
				//虚拟商品订单
				if($orderinfo['order_goods_type'] == ORDER_GOODS_VIRTUAL){
				    $odata['status'] = ORDER_SEND;
				}
				
			}
			//预约配送时间
			if($appoint_distribution_date){
				$odata['appoint_distribution_date'] = $appoint_distribution_date;
				$odata['delivery_type'] = 3;
			}
			
			//获取运费模板数据
			if(isset($addr_id) && $addr_id && isset($memberaddr) && $memberaddr) {
				foreach($order_goods as $key => $ginfo) {
					if(isset($ginfo['shipment_id']) && $ginfo['shipment_id']>0) {
						$ShipmentGoods = [
							'merchant_id'	=>	$this->merchant_id,
							'shipment_id'	=>	$ginfo['shipment_id'],
							'province'		=>	$memberaddr['province'],
							'city'			=>	$memberaddr['city'],
						];
						$sptresult = $this->BuyService->getShipmentInfo($ShipmentGoods);
						if($sptresult && isset($sptresult['errcode']) && $sptresult['errcode']==0) {
							$order_goods[$key]['shipmentdata'] = $sptresult['data'];
						} else {
							return Response::json(array('errcode' => '110001', 'errmsg' => $sptresult['errmsg']));
						}
					}
				}
			}
						
			//启动事务
			DB::beginTransaction();
			try{
				$upid = $this->OrderInfoModel->update_data($order_id,$this->merchant_id,$odata);
				if(isset($memberaddr) && $memberaddr) {
					OrderAddr::where(['order_id'=>$order_id])->delete();
					$addrdata = [
						'order_id'		=>	$order_id,
						'address_id'	=>	$memberaddr['id'],
						'consignee'		=>	$memberaddr['consignee'],
						'mobile'		=>	$memberaddr['mobile'],
						'country'		=>	$memberaddr['country'],
						'province'		=>	$memberaddr['province'],
						'city'			=>	$memberaddr['city'],
						'district'		=>	$memberaddr['district'],
						'country_name'	=>	$memberaddr['country_name'],
						'province_name'	=>	$memberaddr['province_name'],
						'city_name'		=>	$memberaddr['city_name'],
						'district_name'	=>	$memberaddr['district_name'],
						'address'		=>	$memberaddr['address'],
						'zipcode'		=>	$memberaddr['zipcode'],
					];
					OrderAddr::insert_data($addrdata);
				}
				
				//更新订单字表金额
				foreach($order_goods as $kk => $ginfo) {
					$ginfodata = [
						'pay_price'		=>		$ginfo['pay_price'],
					];
					if($shipment_fee>0) {	//如有运费则更新字表不包邮
						$ginfodata['is_pinkage'] = 0;
					}
					if(isset($ginfo['shipmentdata']) && $ginfo['shipmentdata']) {	//更新运费模板数据
						$ginfodata['valuation_type'] = isset($ginfo['shipmentdata']['valuation_type']) ? $ginfo['shipmentdata']['valuation_type'] : 0;
						$ginfodata['start_standard'] = isset($ginfo['shipmentdata']['start_standard']) ? $ginfo['shipmentdata']['start_standard'] : 0;
						$ginfodata['start_fee'] = isset($ginfo['shipmentdata']['start_fee']) ? $ginfo['shipmentdata']['start_fee'] : 0;
						$ginfodata['add_standard'] = isset($ginfo['shipmentdata']['add_standard']) ? $ginfo['shipmentdata']['add_standard'] : 0;
						$ginfodata['add_fee'] = isset($ginfo['shipmentdata']['add_fee']) ? $ginfo['shipmentdata']['add_fee'] : 0;
					}
					OrderGoods::update_data($ginfo['id'],$this->merchant_id,$ginfodata);
				}
				
				//若不使用满减，删除满减
				if($is_discount==0) {
					OrderGoodsUmp::where(['order_id'=>$order_id,'ump_type'=>7])->update(['order_id'=>-$order_id]);
					OrderUmp::where(['order_id'=>$order_id,'ump_type'=>7])->update(['order_id'=>-$order_id]);

                    //如果订单满包邮删除优惠数据
					$postage_count_order = OrderUmp::where(['order_id'=>$order_id,'ump_type'=>8])->count('id');
                    if(isset($postage_count_order) && $postage_count_order >0) {
                        OrderGoodsUmp::where(['order_id'=>$order_id,'ump_type'=>8])->update(['order_id'=>-$order_id]);
                        OrderUmp::where(['order_id'=>$order_id,'ump_type'=>8])->update(['order_id'=>-$order_id]);
                    }
				}
				
				//使用积分
				if($credit_dis_count && $credit_dis_amount) {
					$credit_memo = '购物使用 '.$credit_dis_count.' 积分抵扣 '.$credit_dis_amount.' 元，订单号：'.$orderinfo['order_sn'];
					$result = $this->CreditService->giveCredit($this->merchant_id,$this->member_id,4,['give_credit'=>-$credit_dis_count,'memo'=>$credit_memo]);
					if($result && isset($result['errcode']) && $result['errcode']==0) {
						
					} else {
						DB::rollBack();
						return Response::json(array('errcode' => '40090', 'errmsg' => ((isset($result['errmsg']) && $result['errmsg']) ? $result['errmsg'] : '积分使用失败')));
					}		
				}
				
				//使用优惠券
				if($coupon_id) {
					$use_coupon_data = [
						'merchant_id'	=>	$this->merchant_id,
						'member_id'		=>	$this->member_id,
						'coupon_code_id'=>	$coupon_id,
						'use_member_id'	=>	$this->member_id,
					];
					$result = $CouponService->useCoupon($use_coupon_data);
					if($result && isset($result['errcode']) && $result['errcode']==0) {
						
					} else {
						DB::rollBack();
						return Response::json(array('errcode' => '40090', 'errmsg' => ((isset($result['errmsg']) && $result['errmsg']) ? $result['errmsg'] : '优惠券使用失败')));
					}
				}
				
				//插入主优惠表
				if($order_ump) {
					foreach($order_ump as $key => $umpinfo) {
						$goodsumpdata = array(
							'order_id'	=>	$order_id,
							'ump_type'	=>	$umpinfo['ump_type'],
							'amount'	=>	$umpinfo['amount'],
							'credit'	=>	isset($umpinfo['credit']) ? $umpinfo['credit'] : 0,
							'memo'		=>	$umpinfo['memo'],
						);
						OrderUmp::insert_data($goodsumpdata);
					}
				}
				
				//插入子优惠表
				if($order_goods_ump_arr) {
					foreach($order_goods_ump_arr as $key => $ump) {
						$ump['order_id'] = $order_id;
						OrderGoodsUmp::insert_data($ump);
					}
				}
				
				//事务提交
				DB::commit();
			}catch (\Exception $e) {
				DB::rollBack();
				return Response::json(array('errcode' => '40090', 'errmsg' => '提交失败->'.$e->getMessage()));
			}
			
			if($amount==0 || $pay_type==ORDER_PAY_DELIVERY) {			
				//发送到队列(抵扣完)
				$job = new OrderPaySuccess($orderinfo['id'],$orderinfo['merchant_id']);
				$this->dispatch($job);
				return Response::json(array('errcode' => '0', 'errmsg' => '订单已支付', 'ispay' => 1));
			}
		} else if($orderinfo['status']==ORDER_TOPAY) {	//已经下单，直接发起支付请求
			if($orderinfo['amount']==0 || $pay_type==ORDER_PAY_DELIVERY) {	//若订单金额已抵扣完，直接完结
			    
			    $odata['pay_type'] = $pay_type;//支付方式
			    
				$odata['status'] = ORDER_TOSEND;
				$odata['pay_status'] = 1;
				$odata['pay_time'] = date("Y-m-d H:i:s");
				
				//预约订单 待收货
				if($orderinfo['order_type']==ORDER_APPOINT) {
					$odata['status'] = ORDER_SEND;
				}
				
				//优惠买单订单
				if($orderinfo['order_type']==ORDER_SALEPAY) {
					$odata['status'] = ORDER_SUCCESS;
					$odata['finished_time'] = date("Y-m-d H:i:s",time()-7*86400);
				}
				
				//上门自提订单
				if($orderinfo['delivery_type']==2) {
					$odata['status'] = ORDER_FORPICKUP;
				}
				
				//虚拟商品订单
				if($orderinfo['order_goods_type'] == ORDER_GOODS_VIRTUAL){
				    $odata['status'] = ORDER_SEND;
				}
				
				$upid = $this->OrderInfoModel->update_data($order_id,$this->merchant_id,$odata);
				
				//发送到队列(抵扣完)
				$job = new OrderPaySuccess($orderinfo['id'],$orderinfo['merchant_id']);
				$this->dispatch($job);
				return Response::json(array('errcode' => '0', 'errmsg' => '订单已支付', 'ispay' => 1));
			}
		} else {
			return Response::json(array('errcode' => '40090', 'errmsg' => '订单状态异常'));
		}
		
		$payinfo = $this->BuyService->topay($orderinfo);
		if($payinfo && isset($payinfo['errcode']) && $payinfo['errcode']==0 && $payinfo['data']) {
			return Response::json(array('errcode' => '0', 'errmsg' => '提交成功', 'data' => $payinfo['data']));
		} else {
			return Response::json(array('errcode' => '40090', 'errmsg' => ((isset($payinfo['errmsg']) && $payinfo['errmsg']) ? $payinfo['errmsg'] : '订单生成失败，请重试')));
		}
	}
	
	/**
	 * 取消订单
	 * @author zhangchangchun@dodoca.com
	 * date 2017-09-06
	 */
	public function postOrderCancel($id) {
		if(!$id || !is_numeric($id)) {
			return Response::json(array('errcode' => '40001', 'errmsg' => '缺少订单号参数'));
		}
		
		$orderinfo = $this->OrderInfoModel->select(['id','status','pay_status','order_type'])->where(['id'=>$id,'member_id'=>$this->member_id,'merchant_id'=>$this->merchant_id])->first();
		if(!$orderinfo) {
			return Response::json(array('errcode' => '40005', 'errmsg' => '订单不存在'));
		}
		if($orderinfo['status']==ORDER_AUTO_CANCELED) {
			return Response::json(array('errcode' => '0', 'errmsg' => '订单已取消'));
		}
		if($orderinfo['pay_status']==1) {
			return Response::json(array('errcode' => '40006', 'errmsg' => '订单无需取消'));
		}
		if(!in_array($orderinfo['status'],array(ORDER_SUBMIT,ORDER_TOPAY))) {
			return Response::json(array('errcode' => '40006', 'errmsg' => '订单无需取消'));
		}
		$orderinfo->status = ORDER_BUYERS_CANCELED;
		$orderinfo->explain = '用户手动关闭';
		
		//预约订单特殊处理
		if($orderinfo['order_type']==ORDER_SALEPAY) {
			$orderinfo->status = ORDER_AUTO_CANCELED;
			$orderinfo->explain = '用户取消支付';
		}
		if($orderinfo->save()){
			//发送到队列
			$job = new OrderCancel($id,$this->merchant_id);
            $this->dispatch($job);
			return Response::json(array('errcode' => '0', 'errmsg' => '取消成功'));
		} else {
			return Response::json(array('errcode' => '40007', 'errmsg' => '订单取消失败'));
		}
	}
	
	/**
	 * 支付成功显示
	 * @author zhangchangchun@dodoca.com
	 * date 2017-09-06
	 */
	public function getOrderSuccess($id) {
		if(!$id || !is_numeric($id)) {
			return Response::json(array('errcode' => '40001', 'errmsg' => '缺少订单号参数'));
		}
		$data = $this->OrderInfoModel->select(['id','order_sn','amount','order_type','pay_type'])->where(['id'=>$id,'member_id'=>$this->member_id,'merchant_id'=>$this->merchant_id])->first();
		if(!$data) {
			return Response::json(array('errcode' => '40005', 'errmsg' => '订单不存在'));
		}
		if($data['order_type']==ORDER_FIGHTGROUP) {	//拼团订单
		    //查询拼团发起表id
		    $fightgroup = FightgroupJoin::select('launch_id','status')->where('order_id', $data['id'])->where('merchant_id', $this->merchant_id)->first();
			$data['fightgroup_id'] = isset($fightgroup['launch_id']) ? $fightgroup['launch_id'] : 0;
			$data['fightgroup_type'] = $fightgroup['status'] == 6 ? 1 : 0;//status=6参团成功，跳转到团结果页；否则跳转到团详情页
		}
		return Response::json(array('errcode' => '0', 'errmsg' => '返回成功', 'data' => $data));
	}
	
	/**
	 * 获取订单状态
	 * @author zhangchangchun@dodoca.com
	 * date 2017-09-06
	 * status 订单状态
	 * type 类型：1-返回前端状态文字，2-返回前端状态id
	 * data 订单数据
	 */
	private function orderStatus($status,$type=1,$data='') {
		$order_type = isset($data['order_type']) ? $data['order_type'] : 0;
		$order_goods_type = isset($data['order_goods_type']) ? $data['order_goods_type'] : 0;
		
		$str = '';
		if($type==1) {
			switch($status) {
				case ORDER_AUTO_CANCELED:
				case ORDER_BUYERS_CANCELED:
				case ORDER_MERCHANT_CANCEL:
				case ORDER_REFUND_CANCEL:
					$str = '已关闭';
					break;
				case ORDER_SUBMIT:
				case ORDER_TOPAY:
					$str = '待付款';
					break;
				case ORDER_TOSEND:
					$str = '待发货';
					break;
				case ORDER_SUBMITTED:
				case ORDER_SEND:
					if($order_type==ORDER_APPOINT) {	//预约订单
						$str = '待核销';
					} else {
						$str = '待收货';
					}
					
					if($order_goods_type == ORDER_GOODS_VIRTUAL){  //虚拟商品订单
					    $str = '待核销';
					}
					
					break;
				case ORDER_FORPICKUP:
					$str = '待自提';
					break;
				case ORDER_SUCCESS:
					$str = '已完成';
					break;
				default:
					break;
			}
		} else if($type==2) {
			switch($status) {
				case ORDER_SUBMIT:	//待下单
					$str = 0;
					break;
				case ORDER_TOPAY:	//待付款
					$str = 1;
					break;
				case ORDER_TOSEND:	//-待发货
					$str = 2;
					break;
				case ORDER_SUBMITTED:	//待收货
				case ORDER_FORPICKUP:
				case ORDER_SEND:
					$str = 3;
					break;
				case ORDER_SUCCESS:	//已完成
					$str = 5;
					break;
				case ORDER_AUTO_CANCELED:	//系统自动取消
					$str = 6;
					break;
				case ORDER_BUYERS_CANCELED:	//买家已取消
					$str = 7;
					break;
				case ORDER_MERCHANT_CANCEL:	//商家关闭订单
					$str = 8;
					break;
				case ORDER_REFUND_CANCEL:	//已关闭 ,所有维权申请处理完毕
					$str = 9;
					break;
				default:
					$str = 6;
					break;
			}
		}
		return $str;
	}
	
	/**
	 * 获取退款状态
	 * @author zhangchangchun@dodoca.com
	 * date 2017-09-06
	 */
	private function refundStatus($status) {
		$conf = config('config');
		return isset($conf['refund_status'][$status]) ? $conf['refund_status'][$status] : '';
	}
	
	/**
	 * 处理数据时间格式
	 * @author zhangchangchun@dodoca.com
	 * date 2017-09-06
	 */
	private function dateFormat($data) {
		foreach($data as $key => $v) {
			if($v=='0000-00-00 00:00:00') {
				$data[$key] = '';
			}
		}
		return $data;
	}
	
	/**
	 * 验证使用优惠权限
	 * @author zhangchangchun@dodoca.com
	 * date 2017-09-06
	 * order_type 订单类型
	 * mark_type  优惠类型
	 */
	private function get_reduction_power($order_type=0,$mark_type='') {
		if($order_type && $mark_type) {
			$config = config('config');
			if($config && isset($config['marketing_'.$order_type]) && in_array($mark_type,$config['marketing_'.$order_type])) {
				return true;
			}
		}
		return false;
	}
	
	/**
	 * 买家小程序中订单详情页生成二维码链接
	 */
	public function getUrlLink(Request $request) {
	    //获取open_id
	    $url_getOpenid = 'http://open.dodoca.com/wxauth/index?return_url=';
	    //生成H5核销页面需要的内容
	    $url_createH5link = ENV('APP_URL').'/weapp/order/h5chargeoff.json?';
	    $token = base64_encode(encrypt($request['order_id'],'E','H5ChargeOff20171110'));
	    $url_createH5param = 'order_id='.$request['order_id'].'&token='.$token;
	    $url_H5address = urlencode($url_createH5link.$url_createH5param);
	    //拼接成此url
	    $url_link = $url_getOpenid.$url_H5address;
	
	    dd($url_link);
	}
	/**
	 * 模拟获取微信open_id后的回调
	 */
	public function getH5UrlLink(Request $request) {
	    $url = 'http://applet.rrdoy.local:1111/weapp/order/h5chargeoff.json?';
	
	    $token = base64_encode(encrypt($request['order_id'],'E','H5ChargeOff20171110'));
	
	    $open_id = 'o30ZExOeVB_M-6NfP0vVeBcScj-4';
	    $wx_open_id = base64_encode(encrypt($open_id,'E','OPEN_WXWEB_AUTH'));
	
	    $url_H5ChargeOff = $url.'order_id='.$request['order_id'].'&token='.$token.'&wx_open_id='.$wx_open_id;
	
	    dd($url_H5ChargeOff);
	}
	
	/**
	 * 回调地址生成H5核销地址
	 * @author songyongshang@dodoca.com
	 * date 2017-09-06
	 */
	public function getH5ChargeOff(Request $request) {
	    //订单id
	    if(!isset($request['order_id']) || empty($request['order_id'])){
	        $rt['errcode']=100001;
	        $rt['errmsg']='订单id不能为空';
	        return Response::json($rt);
	    }
	    //dd('adf');
	    //订单详情
	    $rs_orderinfo = OrderInfo::where(['id'=>$request['order_id']])->first();
	    //dd($rs_orderinfo);
	    //open_id
	    if(!isset($request['wx_open_id']) || empty($request['wx_open_id'])){
	        $rt['errcode']=100001;
	        $rt['errmsg']='open_id不能为空';
	        return Response::json($rt);
	    }
	    $data['open_id']=encrypt(base64_decode($request['wx_open_id']),'D','OPEN_WXWEB_AUTH');
	    if(!isset($data['open_id']) || empty($data['open_id'])){
	        $rt['errcode']=100001;
	        $rt['errmsg']='没有获取到open_id';
	        return Response::json($rt);
	    }
	     
	    //验证token
	    if(!isset($request['token']) || empty($request['token'])){
	        $rt['errcode']=100001;
	        $rt['errmsg']='token不能为空2';
	        return Response::json($rt);
	    }

	    $url_h5 = env('APP_URL');
	    $url_h5 .= '/wap/destroy/'.$request['order_id'].'/'.$rs_orderinfo['merchant_id'].'?token='.$request['token'].'&wx_open_id='.$request['wx_open_id'];
        //dd($url_h5);
        return redirect($url_h5);
	}
	
	/**
	 * H5确认核销
	 * @author songyongshang@dodoca.com
	 * date 2017-09-06
	 */
	public function getH5ChargeOff_affirm(Request $request) {
	    //订单id
	    if(!isset($request['order_id']) || empty($request['order_id'])){
	        $rt['errcode']=100001;
	        $rt['errmsg']='订单id不能为空';
	        return Response::json($rt);
	    }
	    //订单详情
	    $rs_orderinfo = OrderInfo::where(['id'=>$request['order_id']])->first();
	    //dd($rs_orderinfo);
	    if(empty($rs_orderinfo)){
	        $rt['errcode']=100001;
	        $rt['errmsg']='没有此订单信息';
	        return Response::json($rt);
	    }else if( $rs_orderinfo['delivery_type']!=2 && $rs_orderinfo['order_type']!=4 && $rs_orderinfo['order_goods_type']!=1){
	        $rt['errcode']=100001;
	        $rt['errmsg']='此订单没有核销功能';
	        return Response::json($rt);
	    }else if( $rs_orderinfo['status']==ORDER_SUCCESS && $rs_orderinfo['order_goods_type']!=1){
	        $rt['errcode']=100001;
	        $rt['errmsg']='此订单已完成';
	        return Response::json($rt);
	    }
	    //open_id
	    if(!isset($request['wx_open_id']) || empty($request['wx_open_id'])){
	        $rt['errcode']=100001;
	        $rt['errmsg']='open_id不能为空';
	        return Response::json($rt);
	    }
	    $data['open_id']=encrypt(base64_decode($request['wx_open_id']),'D','OPEN_WXWEB_AUTH');
	    //dd($data['open_id']);
	    if(!isset($data['open_id']) || empty($data['open_id'])){
	        $rt['errcode']=100001;
	        $rt['errmsg']='没有获取到open_id';
	        return Response::json($rt);
	    }
	    //是否绑定了微信
	    //dd($data['open_id']);
	    $rs_user = User::where(['open_id'=>$data['open_id']])->first();
	    //dd($rs_user);
	    if( !isset($rs_user['open_id']) || empty($rs_user['open_id']) ){
	        $rt['errcode']=111301;
	        $rt['errmsg']='';
	        $rt['data']['bigChar'] = '您没有验证权限';
	        $rt['data']['smallChar'] = '请使用具有验证权限的微信扫描再试';
	        return Response::json($rt);
	    }
	    
	    //验证token
	    if(!isset($request['h5token']) || empty($request['h5token'])){
	        $rt['errcode']=100001;
	        $rt['errmsg']='token不能为空';
	        return Response::json($rt);
	    }
	    $token = $request['h5token'];
	    if( $request['h5token']!=base64_encode(encrypt($request['order_id'],'E','H5ChargeOff20171110')) ){
	        $rt['errcode']=100001;
	        $rt['errmsg']='token不正确!';
	        return Response::json($rt);
	    }
	    //上门自提H5核销确认
	    if($rs_orderinfo['delivery_type']==2){
	        $rs_orderselffetch = OrderSelffetch::where(['order_id'=>$request['order_id']])->first();
            if(empty($rs_orderselffetch)){
                $rt['errcode']=100001;
                $rt['errmsg']='没有获取到上门自提订单信息';
                return Response::json($rt);
            }
            //是否本门店
            if( $rs_user['store_id']!=$rs_orderinfo['store_id'] ){
                $rt['errcode']=111301;
                $rt['errmsg']='';
                $rt['data']['bigChar'] = '您没有该门店的核销权限';
			        $rt['data']['smallChar'] = '请让消费者到指定门店进行核销';
                return Response::json($rt);
            }
            
	        //验证核销权限位
	        $if_auth = UserPrivService::getHexiaoPriv($rs_user['id'],$rs_user['merchant_id'],$rs_user['is_admin'],'order_chargeoff_selflift');
	        if( !isset($if_auth['errcode']) && $if_auth['errcode']!='has_priv' ){
	            $rt['errcode']=111301;
	            $rt['errmsg']='';
    	        $rt['data']['bigChar'] = '您没有验证权限';
    	        $rt['data']['smallChar'] = '请使用具有验证权限的微信扫描再试';
	            return Response::json($rt);
	        }
	        //更新核销信息
	        $data_orderselffetch['hexiao_status'] = 1;
	        $data_orderselffetch['hexiao_source'] = 2;
	        $data_orderselffetch['user_id'] = $rs_user['id'];
	        $data_orderselffetch['hexiao_time'] = date('Y-m-d H:i:s');
	        $res_orderselffetch = OrderSelffetch::update_data($rs_orderselffetch['id'], $rs_user['merchant_id'], $data_orderselffetch);
	        if(!$res_orderselffetch) {
	            $rt['errcode']=100001;
	            $rt['errmsg']='核销失败';
	            return Response::json($rt);
	        }
	        //更新订单信息
	        $data_orderinfo['status'] = 11;
	        $data_orderinfo['finished_time'] = date('Y-m-d H:i:s');
	        $res_orderinf = OrderInfo::update_data($rs_orderselffetch['order_id'], $rs_user['merchant_id'], $data_orderinfo);
	        if(!$res_orderinf) {
	            $rt['errcode']=100001;
	            $rt['errmsg']='订单更新失败';
	            return Response::json($rt);
	        }
			//小票机打印
			if($rs_orderselffetch['order_id']){
				$orderPrint = new OrderPrintService();
				$orderPrint->printOrder($rs_orderselffetch['order_id'],$rs_user['merchant_id']);
			}
	        $rt['errcode']=0;
	        $rt['errmsg']='核销成功';
	        return Response::json($rt);
	    }
	    //预约服务H5核销确认
	    else if($rs_orderinfo['order_type']==4){
	        //预约服务订单
	        $rs_orderappt = OrderAppt::where(['order_id'=>$request['order_id']])->first();
	        if(empty($rs_orderappt)){
	            $rt['errcode']=100001;
	            $rt['errmsg']='没有获取到订单id．';
	            return Response::json($rt);
	        }
	        //是否本门店
	        if( $rs_user['store_id']!=$rs_orderappt['store_id'] ){
	            $rt['errcode']=111301;
	            $rt['errmsg']='';
	            $rt['data']['bigChar'] = '您没有该门店的核销权限';
			        $rt['data']['smallChar'] = '请让消费者到指定门店进行核销';
	            return Response::json($rt);
	        }
	        //预约服务H5核销权限位
	        $if_auth = UserPrivService::getHexiaoPriv($rs_user['id'],$rs_user['merchant_id'],$rs_user['is_admin'],'order_chargeoff_selflift');
	        if( !isset($if_auth['errcode']) && $if_auth['errcode']!='has_priv' ){
	            $rt['errcode']=111302;
	            $rt['errmsg']='';
    	        $rt['data']['bigChar'] = '您没有验证权限';
    	        $rt['data']['smallChar'] = '请使用具有验证权限的微信扫描再试';
	            return Response::json($rt);
	        }
	        
	        //更新核销信息
	        $data_appt['hexiao_status'] = 1;
	        $data_appt['hexiao_source'] = 2;
	        $data_appt['user_id'] = $rs_user['id'];
	        $data_appt['username'] = $rs_user['name'];
	        $data_appt['hexiao_time'] = date('Y-m-d H:i:s');
	        $res_orderappt = OrderAppt::update_data($request['order_id'], $rs_user['merchant_id'], $data_appt);
	        if(!$res_orderappt) {
	            $rt['errcode']=100001;
	            $rt['errmsg']='核销失败';
	            return Response::json($rt);
	        }
	        //更新订单信息
	        $data_orderinfo['status'] = 11;
	        $data_orderinfo['finished_time'] = date('Y-m-d H:i:s');
	        $res_orderinf = OrderInfo::update_data($rs_orderappt['order_id'], $rs_user['merchant_id'], $data_orderinfo);
	        if(!$res_orderinf) {
	            $rt['errcode']=100001;
	            $rt['errmsg']='订单更新失败';
	            return Response::json($rt);
	        }

			//小票机打印
			if($rs_orderappt['order_id']){
				$orderPrint = new OrderPrintService();
				$orderPrint->printOrder($rs_orderappt['order_id'],$rs_user['merchant_id']);
			}
	        $rt['errcode']=0;
	        $rt['errmsg']='核销成功';
	        $rt['data']['bigChar'] = '验证成功';
	        $rt['data']['smallChar'] = '订单已核销';
	        return Response::json($rt);
	    }
	    //虚拟商品
	    elseif($rs_orderinfo['order_goods_type']==1){
	        //H5核销权限位
	        $if_auth = UserPrivService::getHexiaoPriv($rs_user['id'],$rs_user['merchant_id'],$rs_user['is_admin'],'trade_orderhexiao_virtualgoods');
	        if( !isset($if_auth['errcode']) || $if_auth['errcode']!='has_priv' ){
	            $rt['errcode']=111302;
	            $rt['errmsg']='';
	            $rt['data']['bigChar'] = '您没有验证权限';
	            $rt['data']['smallChar'] = '请使用具有验证权限的微信扫描再试';
	            return Response::json($rt);
	        }
	        
	        $rs_isend = OrderVirtualgoods::where(['merchant_id'=>$rs_user['merchant_id'], 'order_id'=>$request['order_id'],'hexiao_status'=>0])->first();
	        if(empty($rs_isend)){
	            $rt['errcode']=0;
	            $rt['errmsg']='此订单已经全部核销完成';
	            return Response::json($rt);
	        }
	        //虚拟商品
            if( isset($request['hexiao_code']) && $request['hexiao_code']!='all' ){
                // 1 虚拟商品核销表(单条记录)
                $res = OrderVirtualgoods::where(['merchant_id'=>$rs_user['merchant_id'], 'order_id'=>$request['order_id'], 'hexiao_code'=>$request['hexiao_code']])->first();
                if (empty($res)) {
                    $data['errcode'] = 140002;
                    $data['errmsg'] = '此核销码无效';
                    return Response::json($data);
                }elseif ( isset($res['hexiao_status']) ) {
                    if($res['hexiao_status'] == 1){
                        $data['errcode'] = 140003;
                        $data['errmsg'] = '此核销码已核销';
                        return Response::json($data);
                    }elseif($res['hexiao_status'] == 2){
                        $data['errcode'] = 140003;
                        $data['errmsg'] = '此核销码对应的商品已退款';
                        return Response::json($data);
                    }
                }
            }
            // 2 商品-虚拟商品(有效期)
            //商品订单表
            $rs_order_goods = OrderGoods::where(['merchant_id'=>$rs_user['merchant_id'],'order_id'=>$request['order_id']])->first();
            //虚拟商品表(所有记录)
            $rs_goods_virtual = GoodsVirtual::where(['merchant_id'=>$rs_user['merchant_id'], 'goods_id'=>$rs_order_goods['goods_id']])->first();
            if(empty($rs_goods_virtual)){
                $data['errcode'] = 140012;
                $data['errmsg'] = '查不到此商品';
                return Response::json($data);
            }elseif( $rs_goods_virtual['time_type']==1 ){
                if( date('Y-m-d H:i:s')<$rs_goods_virtual['start_time'] ){
                    $data['errcode'] = 140013;
                    $data['errmsg'] = '不在此商品的有效期,不可核销';
                    return Response::json($data);
                }elseif( date('Y-m-d H:i:s')>$rs_goods_virtual['end_time'] ){
                    $data['errcode'] = 140013;
                    $data['errmsg'] = '超过此商品的有效期,已失效';
                    return Response::json($data);
                }
            }
            // 3 订单表
            $rs_order_info = OrderInfo::get_data_by_id($request['order_id'], $rs_user['merchant_id']) ;
            if(empty($rs_order_info)){
                $data['errcode'] = 140012;
                $data['errmsg'] = '查不到此订单';
                return Response::json($data);
            }
	        //4 退款表
            $rs_order_refund = OrderRefund::where(['merchant_id'=>$rs_user['merchant_id'],'order_id'=>$request['order_id']])->get();
            //退款有未完成的情况
            $refund_not_finish = 0;
            if(!empty($rs_order_refund)){
                foreach ($rs_order_refund as $key=>$val){
                    if(!in_array($val['status'], array(31,40,41))){
                        $refund_not_finish +=$val['refund_quantity'];
                        break;
                    }
                }
            }
	        //核销
            $update_data = array(
                'hexiao_status' => 1,
                'hexiao_source' => 2,
                'hexiao_time' => date("Y-m-d H:i:s"),
                'user_id' => $rs_user['id']
            );
            
            $result = 0;
            if( isset($request['hexiao_code'])&&$request['hexiao_code']=='all' ){
                $rs_order_virtualgoods = OrderVirtualgoods::where(['merchant_id'=>$rs_user['merchant_id'], 'order_id'=>$request['order_id']])->get();
                $freeze = 0;
                foreach ($rs_order_virtualgoods as $key=>$val){
                    if($val['hexiao_status']==0 && $refund_not_finish>0 && $freeze<$refund_not_finish){
                        $freeze++;
                        continue;
                    }
                    $result = OrderVirtualgoods::where(['id'=>$val['id'], 'hexiao_status'=>0])->update($update_data);
                }
            }else{
                //申请退款中的核销
                if( $refund_not_finish>0 ){
                    $rs_order_virtualgoods_count = OrderVirtualgoods::where(['merchant_id'=>$rs_user['merchant_id'], 'order_id'=>$request['order_id'],'hexiao_status'=>0])->count();
                    if( $rs_order_virtualgoods_count<=$refund_not_finish ){
                        $data['errcode'] = 140012;
                        $data['errmsg'] = '退款中的商品不可以核销';
                        return Response::json($data);
                    }
                }
                $result = OrderVirtualgoods::where(['hexiao_code'=>$request['hexiao_code'], 'merchant_id'=>$rs_user['merchant_id']])->update($update_data);
            }
            // 5 虚拟商品核销表(所有记录)
            $rs_order_virtualgoods = OrderVirtualgoods::where(['merchant_id'=>$rs_user['merchant_id'], 'order_id'=>$request['order_id']])->get();
            //未核销数量
            $hexiao_nodoing = 0;
            //已核销
            $hexiao_finish = 0;
            //已退款
            $hexiao_refund = 0;
            if( !$rs_order_virtualgoods->isEmpty() ){
                foreach ($rs_order_virtualgoods as $key=>$val){
                    if($val['hexiao_status']==0){
                        $hexiao_nodoing++;
                    }elseif($val['hexiao_status']==1){
                        $hexiao_finish++;
                    }elseif($val['hexiao_status']==2){
                        $hexiao_refund++;
                    }
                }
            }
            
            if ($result > 0) {
                $data['errcode'] = 0;
                $data['errmsg'] = '核销成功';
            } else {
                $data['errcode'] = 140008;
                $data['errmsg'] = '核销失败';
            }
            //退款是否全部完结
            if(empty($refund_not_finish)){
                if(
                    //商品有效期 过期->订单完成
                    $rs_goods_virtual['time_type']==1 && date('Y-m-d H:i:s')>$rs_goods_virtual['end_time']
                    //购买数量 = 已核销数量+已退款数量
                    || $rs_order_goods['quantity'] == $hexiao_finish+$hexiao_refund
                    ){
                        //dd('a');
                        $data_orderinfo['status'] = 11;
                        $data_orderinfo['finished_time'] = date('Y-m-d H:i:s');
                        $rs_orderinfo = OrderInfo::where(['merchant_id'=>$rs_user['merchant_id'],'id'=>$request['order_id']])->update($data_orderinfo);
                }
            }
	        $rt['errcode']=0;
	        $rt['errmsg']='核销成功';
	        $rt['data']['bigChar'] = '验证成功';
	        $rt['data']['smallChar'] = '订单已核销';
	        return Response::json($rt);
	    }
	}
	
	
}

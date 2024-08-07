<?php
/**
 * 订单队列类
 */
namespace App\Services;

use App\Jobs\DistribRefundComission;
use App\Jobs\WeixinMsgs;
use App\Models\OrderPackage;
use App\Models\Store;
use Illuminate\Foundation\Bus\DispatchesJobs;

use App\Models\OrderPackageItem;
use App\Models\WeixinLog;
use App\Utils\CommonApi;
use App\Models\OrderInfo;
use App\Models\OrderGoods;
use App\Models\OrderUmp;
use App\Models\OrderGoodsUmp;
use App\Models\OrderAppt;
use App\Models\OrderRefund;
use App\Models\OrderRefundLog;
use App\Models\CreditRule;
use App\Models\PaymentApply;
use App\Models\Member;
use App\Models\Trade;
use App\Models\OrderRefundApply;
use App\Models\FightgroupRefund;
use App\Models\Merchant;
use App\Models\OrderSelffetch;
use App\Models\Member as MemberModel;
use App\Services\CreditService;
use App\Services\GoodsService;
use App\Services\WeixinPayService;
use App\Services\FightgroupService;
use App\Services\ApptService;
use App\Services\SeckillService;
use App\Services\BuyService;
use App\Services\CouponService;
use App\Services\WeixinMsgService;
use App\Services\VirtualGoodsService;
use App\Services\DistribService;
use App\Services\OrderPrintService;
use App\Jobs\WeixinMsgJob;
use App\Jobs\DistribInitOrder;
use Illuminate\Support\Facades\DB;
use Log;

class OrderJobService
{
	use DispatchesJobs;
	
	/**
	 * 取消订单异步队列
	 * @author zhangchangchun@dodoca.com
	 * order 订单数据
	 */
	public function OrderCancelJob($order) {
		$CreditService = new CreditService;
		$GoodsService = new GoodsService;
		$WeixinPayService = new WeixinPayService;
		$SeckillService = new SeckillService;
		$FightgroupService = new FightgroupService;
		
		if(CommonApi::verifyJob(['activity_id'=>$order['id'],'data_type'=>'order_cancel_job','content'=>json_encode($order,JSON_UNESCAPED_UNICODE)])) {
			return 'Already execute';
		}
		
        if($order['status']==ORDER_AUTO_CANCELED || $order['status']==ORDER_BUYERS_CANCELED) {	//验证下是否已支付未推送
			$apply = PaymentApply::where(['order_id'=>$order['id'],'amount'=>$order['amount']])->orderBy('id','desc')->first();
			if($apply) {
				$result = $WeixinPayService->queryOrder(['merchant_id'=>$order['merchant_id'],'no'=>$apply['payment_sn'],'appid'=>$order['appid']]);
				if($result && isset($result['errcode']) && $result['errcode']==0) {	//支付成功(反转订单)
					$odata = [
						'pay_status'	=>	1,
						'status'		=>	ORDER_TOSEND,
						'explain'		=>	'取消已支付订单，订单反转',
					];
					OrderInfo::update_data($order['id'],$order['merchant_id'],$odata);
					
					//记录异常
					$except = [
						'activity_id'	=>	$order['id'],
						'data_type'		=>	'order_cancel_pay',
						'content'		=>	'已支付订单，被自动取消'.json_encode($order,JSON_UNESCAPED_UNICODE).'_'.json_encode($result,JSON_UNESCAPED_UNICODE),
					];
					CommonApi::errlog($except);
					return true;
				}
			}
		}
		
		//验证是否使用积分抵扣
		$ump = OrderUmp::select(['id','amount','credit'])->where(['order_id'=>$order['id'],'ump_type'=>3])->first();
		if($ump && isset($ump['credit']) && $ump['credit']>0) {
			$crtdata = [
				'give_credit'	=>	(int)$ump['credit'],
				'memo'			=>	'取消订单退还'.(int)$ump['credit'].'积分，订单号：'.$order['order_sn'],
			];
			$cresult = $CreditService->giveCredit($order['merchant_id'],$order['member_id'],6,$crtdata);
			if($cresult && isset($cresult['errcode']) && $cresult['errcode']==0) {
				
			} else {
				//记录异常
				$except = [
					'activity_id'	=>	$order['id'],
					'data_type'		=>	'order_cancel_return_credit',
					'content'		=>	'归还积分失败，'.json_encode($order,JSON_UNESCAPED_UNICODE).'_'.json_encode($cresult,JSON_UNESCAPED_UNICODE),
				];
				CommonApi::errlog($except);
			}
		}
		
		//退还优惠券（暂支持优惠买单退券）
		if($order['order_type']==ORDER_SALEPAY) {
			$ump_id = OrderGoodsUmp::where(['order_id'=>$order['id'],'ump_type'=>2])->pluck('ump_id');
			if($ump_id) {
				$CouponService = new CouponService();
				$return_coupon_result = $CouponService->returned(['merchant_id'=>$order['merchant_id'],'member_id'=>$order['member_id'],'coupon_code_id'=>$ump_id]);
				if($return_coupon_result && isset($return_coupon_result['errcode']) && $return_coupon_result['errcode']==0) {
					
				} else {
					//记录异常
					$except = [
						'activity_id'	=>	$order['id'],
						'data_type'		=>	'order_cancel_return_coupon',
						'content'		=>	'归还优惠券失败，'.json_encode($order,JSON_UNESCAPED_UNICODE).'_'.json_encode($return_coupon_result,JSON_UNESCAPED_UNICODE),
					];
					CommonApi::errlog($except);
				}
			}
		}
		
		//退还库存
		$ordergoods = OrderGoods::select(['id','quantity','stock_type','goods_id','spec_id'])->where(['order_id'=>$order['id']])->get();
		if($ordergoods) {
			foreach($ordergoods as $key => $ginfo) {
				if($ginfo['stock_type']==1) {	//拍下减库存
					$stkdata = [
						'merchant_id'	=>	$order['merchant_id'],
						'stock_num'		=>	$ginfo['quantity'],
						'goods_id'		=>	$ginfo['goods_id'],
						'goods_spec_id'	=>	$ginfo['spec_id'],
					];
					if($order['order_type']==ORDER_FIGHTGROUP) {	//拼团订单
						$stkdata['activity'] = 'tuan';
					}
					if($order['order_type']==ORDER_APPOINT) {	//预约订单
						$oppt = OrderAppt::select(['id','appt_date'])->where(['order_id'=>$order['id']])->first();
						if($oppt) {
							$stkdata['date'] = $oppt['appt_date'];
						}
					}
					$stkresult = $GoodsService->incStock($stkdata);
					if($stkresult && isset($stkresult['errcode']) && $stkresult['errcode']==0) {
						$GoodsService->desCsale($stkdata);
					} else {
						//记录异常
						$except = [
							'activity_id'	=>	$order['id'],
							'data_type'		=>	'order_cancel_return_stock',
							'content'		=>	'库存归还失败，goods_id->'.$ginfo['goods_id'].',spec_id->'.$ginfo['spec_id'].','.json_encode($order,JSON_UNESCAPED_UNICODE).'_'.json_encode($stkresult,JSON_UNESCAPED_UNICODE),
						];
						CommonApi::errlog($except);
					}
				}
			}
		}
		
		//取消订单后续处理其他业务
		if($order['order_type']==ORDER_SHOPPING) {	//商城订单
			
		} else if($order['order_type']==ORDER_FIGHTGROUP) {	//拼团订单
		    $FightgroupService->fightgroupJoinCancel($order);
        } else if ($order['order_type'] == ORDER_SECKILL) {//秒杀订单
            $SeckillService=new SeckillService();
            $SeckillService->orderCancel($order);
        } else if($order['order_type'] == ORDER_APPOINT){
            $apptService=new ApptService();
            $apptService->postOrderCancel($order);
        }
		
	}
	
	/**
	 * 确认收货异步队列
	 * @author zhangchangchun@dodoca.com
	 * order 订单数据
	 */
	public function OrderDeliveryJob($order) {
		if(CommonApi::verifyJob(['activity_id'=>$order['id'],'data_type'=>'order_delivery_job','content'=>json_encode($order,JSON_UNESCAPED_UNICODE)])) {
			return 'Already execute';
		}
		
		//送积分
		$CreditService = new CreditService;
		$creditruleinfo = CreditRule::get_data_by_merchantid($order['merchant_id'],3,1);
		if($creditruleinfo && isset($creditruleinfo['credit']) && $creditruleinfo['credit']>0) {
			$cresult = $CreditService->giveCredit($order['merchant_id'],$order['member_id'],3);
			if($cresult && isset($cresult['errcode']) && $cresult['errcode']==0) {
				
			} else {
				//记录异常
				$except = [
					'activity_id'	=>	$order['id'],
					'data_type'		=>	'order_delivery_return_credit',
					'content'		=>	'归还积分失败，'.json_encode($order,JSON_UNESCAPED_UNICODE).'_'.json_encode($cresult,JSON_UNESCAPED_UNICODE),
				];
				CommonApi::errlog($except);
			}
		}		
	}
	
	/**
	 * 订单支付成功回调队列
	 * @author zhangchangchun@dodoca.com
	 * order 订单数据
	 */
	public function OrderPaySuccessJob($order) {
		$GoodsService = new GoodsService;
		$FightgroupService = new FightgroupService;
		
		if(CommonApi::verifyJob(['activity_id'=>$order['id'],'data_type'=>'order_paysuccess_job','content'=>json_encode($order,JSON_UNESCAPED_UNICODE)])) {
			return 'Already execute';
		}
		$order['is_oversold'] = 0;	//是否超卖（1-超卖，0-正常）
		
		//更新会员最后一次购买时间
		Member::increment_data($order['member_id'],$order['merchant_id'],'purchased_count',1);
		Member::update_data($order['member_id'],$order['merchant_id'],['latest_buy_time'=>date("Y-m-d H:i:s")]);
		//更新商户收入
		Merchant::where(['id'=>$order['merchant_id']])->increment('income',$order['amount']);
		
		//扣库存、加销量
		$desStock = [];
		$isdesOk = 1;
        if (in_array($order['order_type'], [ORDER_SALEPAY, ORDER_KNOWLEDGE])) {    //优惠买单、知识付费不操作库存和销量

        } else {
			$ordergoods = OrderGoods::select(['id','quantity','stock_type','goods_id','spec_id'])->where(['order_id'=>$order['id']])->get();
			if($ordergoods) {
				foreach($ordergoods as $key => $ginfo) {
					if($ginfo['stock_type']==0) {	//付款减库存
						$stkdata = [
							'merchant_id'	=>	$order['merchant_id'],
							'stock_num'		=>	$ginfo['quantity'],
							'goods_id'		=>	$ginfo['goods_id'],
							'goods_spec_id'	=>	$ginfo['spec_id'],
						];
						if($order['order_type']==ORDER_FIGHTGROUP) {	//拼团订单
							$stkdata['activity'] = 'tuan';
						}
						if($order['order_type']==ORDER_APPOINT) {	//预约订单
							$oppt = OrderAppt::select(['id','appt_date'])->where(['order_id'=>$order['id']])->first();
							if($oppt) {
								$stkdata['date'] = $oppt['appt_date'];
							}
						}
						$stkresult = $GoodsService->desStock($stkdata);
						if($stkresult && isset($stkresult['errcode']) && $stkresult['errcode']==0) {
							$desStock[] = array_merge($stkdata,['order_id'=>$ginfo['id']]);
						} else {
							//记录异常
							$except = [
								'activity_id'	=>	$order['id'],
								'data_type'		=>	'order_pay_des_stock',
								'content'		=>	'库存扣除失败，goods_id->'.$ginfo['goods_id'].',spec_id->'.$ginfo['spec_id'].','.json_encode($order,JSON_UNESCAPED_UNICODE).'_'.json_encode($stkresult,JSON_UNESCAPED_UNICODE),
							];
							CommonApi::errlog($except);
							$isdesOk = 0;	//扣库存失败 = 超卖
							continue;
						}
					}
				}
			}
			
			if($isdesOk==1) {	//增加销量
				if($desStock) {
					foreach($desStock as $key => $stkinfo) {
						$GoodsService->incCsale($stkinfo);
					}
				}
			} else if($isdesOk==0) {
				$order['is_oversold'] = 1;
				
				//超卖 已扣除的库存加回去
				if($desStock) {
					foreach($desStock as $key => $stkinfo) {
						$stkresult = $GoodsService->incStock($stkinfo);
						if($stkresult && isset($stkresult['errcode']) && $stkresult['errcode']==0) {
							
						} else {
							//记录异常
							$except = [
								'activity_id'	=>	$stkinfo['order_id'],
								'data_type'		=>	'order_pay_inc_stock_err',
								'content'		=>	'订单超卖归还库存又失败，goods_id->'.$stkinfo['goods_id'].',spec_id->'.$stkinfo['goods_spec_id'],
							];
							CommonApi::errlog($except);
						}
					}
				}
				
				//超卖更新订单
				$odata = [
					'status'		=>	ORDER_MERCHANT_CANCEL,
					'explain'		=>	'库存超卖，自动取消',
				];
				OrderInfo::update_data($order['id'],$order['merchant_id'],$odata);
				
				//超卖退单
				$refunddata = array(
					'merchant_id'	=>	$order['merchant_id'],
					'order_id'		=>	$order['id'],
					'apply_type'	=>	3,
					'refund_id'		=>	0,
				);
				$BuyService = new BuyService;
				$orderrefund_rs = $BuyService->orderrefund($refunddata);
			}
		}
		
		//若正常订单，则调用发送佣金服务
		if($order['is_oversold']==0 && $order['order_type']!=ORDER_FIGHTGROUP) {
			$result = $this->dispatch(new DistribInitOrder($order['id'],$order['merchant_id']));		
			//记录日志
			CommonApi::wlog([
				'custom'    	=>    	'distrib_'.$order['id'],
				'merchant_id'   =>    	$order['merchant_id'],
				'member_id'     =>    	$order['member_id'],
				'content'		=>		'result->'.json_encode($result,JSON_UNESCAPED_UNICODE),
			]);
		}
		
        if($order['order_type'] == ORDER_APPOINT){//预约订单
            $apptService=new ApptService();
            $apptService->postOrderPaid($order);
        }elseif($order['order_type'] == ORDER_SECKILL){//秒杀订单
            $SeckillService=new SeckillService();
            $SeckillService->orderPaid($order);
        }elseif($order['order_type'] == ORDER_FIGHTGROUP){//拼团订单
            
            //超卖退单返回结果
            if(isset($orderrefund_rs) && !empty($orderrefund_rs)){
                $orderrefund_data = json_encode($orderrefund_rs);
            }else{
                $orderrefund_data = '';
            }
            
            $FightgroupService->fightgroupPayBack($order,$orderrefund_data);
        }
		
		//处理上门自提订单数据
		try{
			$this->OrderSelffetch($order);
		}catch (\Exception $e) {
			//记录异常
			$except = [
				'activity_id'	=>	$order['id'],
				'data_type'		=>	'order_pay_success_job_selffetch',
				'content'		=>	'上门自提处理失败，'.$e->getMessage().','.json_encode($order,JSON_UNESCAPED_UNICODE),
			];
			CommonApi::errlog($except);
		}
		
		//生成虚拟商品订单核销码数据
		if($order['is_oversold'] == 0 && $order['order_goods_type'] == ORDER_GOODS_VIRTUAL){
		    $virtualGoodsService = new VirtualGoodsService();
		    
		    try{
		        $virtualGoodsService->createVirtualHexiao($order);
		    }catch (\Exception $e) {
		        //记录异常
		        $except = [
		            'activity_id'	=>	$order['id'],
		            'data_type'		=>	'order_pay_success_job_virtual',
		            'content'		=>	'虚拟商品订单核销码生成失败，'.$e->getMessage().','.json_encode($order,JSON_UNESCAPED_UNICODE),
		        ];
		        CommonApi::errlog($except);
		    }
		}
		
		
		
		//发送消息模板 粉丝
        if(!($order['order_type'] == ORDER_KNOWLEDGE && $order['amount'] == 0.00)){//免费订阅的就不给用户发通知了
            $job = new WeixinMsgJob(['order_id'=>$order['id'],'merchant_id'=>$order['merchant_id'],'type'=>'paysuccess']);
            $this->dispatch($job);
        }

        //发送消息模板 商户 wangshiliang@dodoca.com
        $this->dispatch(new WeixinMsgs('order',$order));

		//小票机打印
		if($order['id']){
			$orderPrint = new OrderPrintService();
			$orderPrint->printOrder($order['id'],$order['merchant_id']);
		}

	}
		
	/**
	 * 处理上门自提订单数据
	 * @author zhangchangchun@dodoca.com
	 * order 订单数据
	 */
	public function OrderSelffetch($order) {
		if($order['is_oversold']==0 && $order['delivery_type']==2) {	//上门自提订单（非超卖订单）
			$selffetchInfo = OrderSelffetch::select(['id','order_id'])->where(['order_id'=>$order['id']])->first();
			if(!$selffetchInfo) {
				$BuyService = new BuyService;
				$hexiao_code = $BuyService->get_hexiao_sn('selffetch',10);
				$selffetch_data = [
					'merchant_id'	=>	$order['merchant_id'],
					'member_id'		=>	$order['member_id'],
					'order_id'		=>	$order['id'],
					'order_sn'		=>	$order['order_sn'],
					'store_id'		=>	$order['store_id'],
					'hexiao_code'	=>	$hexiao_code,
				];
				return OrderSelffetch::insert_data($selffetch_data);
			}
		}
	}
	
	/**
	 * 退款队列
	 * @author zhangchangchun@dodoca.com
	 * refund 退款数据
	 */
	public function OrderRefundJob($refund) {
		if(!$refund || !isset($refund['status']) || $refund['status']!=0) {
			return false;
		}
		if(CommonApi::verifyJob(['activity_id'=>$refund['id'],'data_type'=>'order_refund_job','content'=>json_encode($refund,JSON_UNESCAPED_UNICODE)])) {
			return 'Already execute';
		}
		$CreditService = new CreditService;
		
		//防止脚本执行过
		$real = OrderRefundApply::get_data_by_id($refund['id'],$refund['merchant_id']);
		if($real && $real['status']!=0) {
			return 'Command already execute';
		}
		
		$credit = 0;
		$amount = 0;
		
		$orderinfo = OrderInfo::get_data_by_id($refund['order_id'],$refund['merchant_id'],"id,amount,merchant_id,member_id,appid,pay_type");
		if(!$orderinfo) {
			\Log::info('OrderRefundJob->订单数据不存在，refund->'.json_encode($refund,JSON_UNESCAPED_UNICODE));
			return false;
		}
		if($refund['order_refund_id']>0) {	//用户申请退款
			$rinfo = OrderRefund::get_data_by_id($refund['order_refund_id'],$refund['merchant_id']);
			if(!$rinfo) {
				\Log::info('OrderRefundJob->退款数据不存在，refund->'.json_encode($refund,JSON_UNESCAPED_UNICODE));
				return false;
			}
			$credit = $rinfo['integral'];
			$amount = $rinfo['amount']+$rinfo['shipment_fee'];;
		} else {	//全单退款
			$ump_credit = OrderUmp::where(['order_id'=>$refund['order_id'],'ump_type'=>3])->pluck('credit');
			if($ump_credit) {
				$credit = $ump_credit;
			}
			$amount = $orderinfo['amount'];
		}
		
		$refund_status = 3;
		$query_data = '';
		$return_data = '';
		
		//退还积分
		if($credit>0) {
			$crtdata = [
				'give_credit'	=>	$credit,
				'memo'			=>	'订单退款退还'.$credit.'积分'
			];
			$cresult = $CreditService->giveCredit($orderinfo['merchant_id'],$orderinfo['member_id'],7,$crtdata);
			if($cresult && isset($cresult['errcode']) && $cresult['errcode']==0) {
				
			} else {
				//记录异常
				$except = [
					'activity_id'	=>	$refund['id'],
					'data_type'		=>	'order_refund_return_credit',
					'content'		=>	'归还积分失败，'.json_encode($refund,JSON_UNESCAPED_UNICODE).'_'.json_encode($cresult,JSON_UNESCAPED_UNICODE),
				];
				CommonApi::errlog($except);
			}
		}
		
		//退还余额
		if($amount>0 && $orderinfo['pay_type'] == ORDER_PAY_WEIXIN) {
			$WeixinPayService = new WeixinPayService;
			$tradeinfo = Trade::where(['order_id'=>$refund['order_id'],'trade_type'=>1,'pay_status'=>1])->first();
			if(!$tradeinfo) {
				\Log::info('OrderRefundJob->交易数据不存在，refund->'.json_encode($refund,JSON_UNESCAPED_UNICODE));
				return false;
			}
			$rdata = [
				'merchant_id'	=>	$refund['merchant_id'],
				'no'			=>	$tradeinfo['payment_sn'],
				'refund_no'		=>	$refund['feedback_sn'],
				'total_fee'		=>	$tradeinfo['amount'],
				'refund_fee'	=>	$amount,
				'appid'			=>	$orderinfo['appid'],
			];
			$result = $WeixinPayService->refundOrder($rdata);
			
			//记录日志
			CommonApi::wlog([
				'custom'    	=>    	'OrderRefundCommand_'.$orderinfo['id'],
				'merchant_id'   =>    	$orderinfo['merchant_id'],
				'member_id'     =>    	$orderinfo['member_id'],
				'content'		=>		'queue->refund_id->'.$refund['id'].',require->'.json_encode($rdata,JSON_UNESCAPED_UNICODE).',result->'.json_encode($result,JSON_UNESCAPED_UNICODE),
			]);
			
			if($result && isset($result['errcode']) && $result['errcode']==0) {	//退款申请成功
				//增加trade表退款
				$tradedata = [
					'merchant_id'	=>	$tradeinfo['merchant_id'],
					'member_id'		=>	$tradeinfo['member_id'],
					'order_id'		=>	$tradeinfo['order_id'],
					'order_sn'		=>	$tradeinfo['order_sn'],
					'pay_status'	=>	1,
					'pay_time'		=>	date("Y-m-d H:i:s"),
					'pay_type'		=>	$tradeinfo['pay_type'],
					'order_type'	=>	$tradeinfo['order_type'],
					'payment_sn'	=>	$refund['feedback_sn'],
					'amount'		=>	-$amount,
					'trade_type'	=>	2,
					'trade_sn'		=>	isset($result['transaction_refund_id']) ? $result['transaction_refund_id'] : '',
				];
				Trade::insert_data($tradedata);
			} else {
				$refund_status = 2;
			}
			$query_data = $rdata;
			$return_data = $result;
		}
		
		//更新退款信息
		$applydata = [
			'credit'	=>	$credit,
			'amount'	=>	$amount,
			'status'	=>	$refund_status,
			'query_data'=>	json_encode($query_data,JSON_UNESCAPED_UNICODE),
			'return_data'=>	json_encode($return_data,JSON_UNESCAPED_UNICODE),
		];
		OrderRefundApply::update_data($refund['id'],$refund['merchant_id'],$applydata);
		
		//更新商户在线退款
		if((float)$amount) {
			Merchant::where(['id'=>$refund['merchant_id']])->increment('payout',$amount);
		}
		
		//退款申请成功推送给各自功能模块
		if($refund_status==3) {
			$refund['status'] = 3;
			$refund['credit'] = $credit;
			$refund['amount'] = $amount;
			$this->OrderRefundStatus($refund);
		}
		
	}
	
	/**
	 * 退款脚本（查询退款状态、退款失败重新发起）
	 * @author zhangchangchun@dodoca.com
	 * refund 退款数据
	 */
	public function OrderRefundCommand($refund) {
		$refund = OrderRefundApply::where(['id'=>$refund['id']])->first();
		if(!$refund || !isset($refund['status'])) {
			return false;
		}
		$orderinfo = OrderInfo::get_data_by_id($refund['order_id'],$refund['merchant_id'],"id,appid,pay_type,merchant_id,member_id");
		if(!$orderinfo) {
			\Log::info('OrderRefundCommand->订单数据不存在，refund->'.json_encode($refund,JSON_UNESCAPED_UNICODE));
			return false;
		}
		
		$refund_status = 0;
		$query_data = '';
		$return_data = '';
		
		$refund['amount'] = (float)$refund['amount'];
		$WeixinPayService = new WeixinPayService;
		if($refund['status']==3) {	//退款中订单查询
			if($refund['amount'] && $orderinfo['pay_type'] == ORDER_PAY_WEIXIN) {
				$request_data = ['merchant_id'=>$refund['merchant_id'],'refund_no'=>$refund['feedback_sn'],'appid'=>$orderinfo['appid']];
				$result = $WeixinPayService->refundOrderQuery($request_data);
				
				//记录日志
				CommonApi::wlog([
					'custom'    	=>    	'OrderRefundCommand_'.$orderinfo['id'],
					'merchant_id'   =>    	$orderinfo['merchant_id'],
					'member_id'     =>    	$orderinfo['member_id'],
					'content'		=>		'refund_id->'.$refund['id'].',require->'.json_encode($request_data,JSON_UNESCAPED_UNICODE).',result->'.json_encode($result,JSON_UNESCAPED_UNICODE),
				]);
				
				if($result && isset($result['errcode']) && $result['errcode']==0) {	//退款申请成功
					$refund_status = 1;
				}
			} else if($refund['amount']==0 || $orderinfo['pay_type'] == ORDER_PAY_DELIVERY) {	//只退积分，直接标记为成功（货到付款，直接标记为成功）
				$refund_status = 1;
			}
		} else if($refund['status']==2) {	//退款失败处理
			$refundInfo = json_decode($refund['query_data'],true);
			if(!isset($refundInfo['appid'])) {
				$refundInfo['appid'] = $orderinfo['appid'];
			}
			$result = $WeixinPayService->refundOrder($refundInfo);
			
			//记录日志
			CommonApi::wlog([
				'custom'    	=>    	'OrderRefundCommand_'.$orderinfo['id'],
				'merchant_id'   =>    	$orderinfo['merchant_id'],
				'member_id'     =>    	$orderinfo['member_id'],
				'content'		=>		'refund_id->'.$refund['id'].',require->'.json_encode($refundInfo,JSON_UNESCAPED_UNICODE).',result->'.json_encode($result,JSON_UNESCAPED_UNICODE),
			]);
			
			if($result && isset($result['errcode']) && $result['errcode']==0) {	//退款申请成功
				$refund_status = 3;
				$return_data = $result;
			} else {
				$refund_status = 2;
				$return_data = $result;
				$refund_msg = isset($result['errmsg']) ? $result['errmsg'] : '';	//失败原因
			}
		} else if($refund['status']==10) {	//手工处理:有可能傻缺商家在微信支付后台退款了
			$refund_status = 1;
			$return_data = '手工处理:'.$refund['return_data'];
		} else if($refund['status']==0 && (strtotime($refund['created_time'])+1800)<time()) {	//退款未发起(且半小时前未发起的订单)
			/*$tradeinfo = Trade::select(['payment_sn','amount'])->where(['order_id'=>$refund['order_id'],'trade_type'=>1,'pay_status'=>1])->first();
			if(!$tradeinfo) {
				\Log::info('OrderRefundJob->交易数据不存在，refund->'.json_encode($refund,JSON_UNESCAPED_UNICODE));
				return false;
			}
			$rdata = [
				'merchant_id'	=>	$refund['merchant_id'],
				'no'			=>	$tradeinfo['payment_sn'],
				'refund_no'		=>	$refund['feedback_sn'],
				'total_fee'		=>	$tradeinfo['amount'],
				'refund_fee'	=>	$amount,
			];
			$result = $WeixinPayService->refundOrder($rdata);
			if($result && isset($result['errcode']) && $result['errcode']==0) {	//退款申请成功
				$refund_status = 3;
			} else {
				$refund_status = 2;
			}
			$query_data = $rdata;
			$return_data = $result;*/
		}
				
		//更新退款信息
		if($refund_status>0) {
			$applydata = [
				'status'	=>	$refund_status,
			];
			if($query_data) {
				$applydata['query_data'] = json_encode($query_data,JSON_UNESCAPED_UNICODE);
			}
			if($return_data) {
				$applydata['return_data'] = json_encode($return_data,JSON_UNESCAPED_UNICODE);
			}
			OrderRefundApply::update_data($refund['id'],$refund['merchant_id'],$applydata);
		}
		
		//退款申请成功推送给各自功能模块
		if($refund_status==1) {	//退款成功
			$refund['status'] = 1;
			$this->OrderRefundStatus($refund);
		} else if($refund_status==3) {	//退款申请中
			$refund['status'] = 3;
			$this->OrderRefundStatus($refund);
		} else if($refund_status==2) {	//退款失败
			$refund['status'] = 2;
			$refund['refund_msg'] = isset($refund_msg) ? $refund_msg : '';
			$this->OrderRefundStatus($refund);
		}
		
	}
	
	/**
	 * 退款状态推送
	 * @author zhangchangchun@dodoca.com
	 * refund 退款数据
	 * $refund = [
	 		'apply_type'		=>	1,		//类型：1用户申请退款处理记录，2拼团等自动退款记录 配置config/varconfig.php
			'merchant_id'		=>	1,		//商户id，外键关联merchant表id
			'order_id'			=>	1,		//订单id，外键关联order_info表id
			'order_refund_id'	=>	1,		//退款表ID 外键关联order_refund表id
			'feedback_sn'		=>	'',		//原路退款单号
			'status'			=>	1,		//退款状态：1-成功，2-失败，3-退款中，0-未发起
		];
	 */
	private function OrderRefundStatus($refund) {
		if(!$refund || !isset($refund['status'])) {
			return false;
		}
		if($refund['status']==1) {	//退款成功
            if($refund['apply_type'] == 1){
                $refundInfo = OrderRefund::get_data_by_id($refund['order_refund_id'],$refund['merchant_id']);
                if(isset($refundInfo['id']) && !empty($refundInfo['id'])){
                    $orderGoodsInfo = OrderGoods::query()->where(['order_id'=>$refundInfo['order_id'],'goods_id'=>$refundInfo['goods_id'],'spec_id'=>$refundInfo['spec_id']])->first();
                    if(isset($orderGoodsInfo['id']) && !empty($orderGoodsInfo['id'])){
                        $orderinfo = OrderInfo::get_data_by_id($refund['order_id'],$refund['merchant_id']);
                        DB::beginTransaction();
                        //退款记录状态
                        $result = OrderRefund::update_data($refund['order_refund_id'],$refund['merchant_id'],['status' => REFUND_FINISHED ,'finished_time'=>date('Y-m-d H:i:s')]);
                        if(!$result)  {
                            \Log::info('OrderRefund_1_['.$orderGoodsInfo['id'].']');
                            DB::rollback();
                            return false;
                        }
                        //退款数量更新
                        $result = OrderGoods::increment_data($orderGoodsInfo['id'],$refund['merchant_id'],'refund_quantity',$refundInfo['refund_quantity']);
                        if(!$result)  {
                            \Log::info('OrderRefund_2_['.$orderGoodsInfo['id'].']');
                            DB::rollback();
                            return false;
                        }
                        //订单状态
                        $list = OrderGoods::get_data_list(['order_id'=>$refund['order_id'],'merchant_id'=>$refund['merchant_id']]);
                        $quantity_sum = 0;
                        //退款已完成 && （全发货 至少有一个没退）
                        $shipped = [] ;//是否已发货
                        foreach ($list as $k => $v) {
                            $quantity_sum += $v['quantity'];
                            $shipped [$k] = false ;
                            if($v['quantity'] <=  ($v['shipped_quantity']+$v['refund_quantity'])  ){// 没有未发货  还有未退
                                $shipped [$k] = true ;
                            }
                        }

                        if(!in_array(false,$shipped) && $orderinfo['status'] == ORDER_TOSEND ){
                            $package = OrderPackage::end_one($refund['order_id']);
                            $shipments_time = isset($package['created_time'])?$package['created_time']:date('Y-m-d H:i:s');
                            $result = OrderInfo::update_data($refund['order_id'],$refund['merchant_id'],['status'=>ORDER_SEND,'shipments_time'=>$shipments_time]);
                            if(!$result)  {
                                \Log::info('OrderRefund_9_['.$orderGoodsInfo['id'].']');
                                DB::rollback();
                                return false;
                            }
                        }
                        //是否退款完毕
                        $refund_quantity_sum = OrderRefund::query()->where(['order_id'=>$refund['order_id'],'status' => REFUND_FINISHED])->sum('refund_quantity');
                        if($quantity_sum <= $refund_quantity_sum){
                            $orderinfo = OrderInfo::get_data_by_id($refund['order_id'],$refund['merchant_id']);
                            if(isset($orderinfo['order_type']) && $orderinfo['order_type'] == 4){
                                $OrderApptInfo = OrderAppt::query()->where(['order_id'=>$refund['order_id'],'merchant_id'=>$refund['merchant_id']])->first();
                                if(isset($OrderApptInfo['hexiao_status']) && $OrderApptInfo['hexiao_status'] == 0){
                                    $result = OrderAppt::update_data($refund['order_id'],$refund['merchant_id'],['hexiao_status'=>2]);
                                    if(!$result)  {
                                        \Log::info('OrderRefund_5_['.$orderGoodsInfo['id'].']');
                                        DB::rollback();
                                        return false;
                                    }
                                }
                            }
                            if(isset($orderinfo['delivery_type']) && $orderinfo['delivery_type'] == 2){
                                $OrderSelffetchInfo =  OrderSelffetch::query()->where(['order_id'=>$refund['order_id'],'merchant_id'=>$refund['merchant_id']])->first();
                                if(isset($OrderSelffetchInfo['hexiao_status']) && $OrderSelffetchInfo['hexiao_status'] == 0){
                                    $result = OrderSelffetch::update_orderid($refund['order_id'],$refund['merchant_id'],['hexiao_status'=>2]);
                                    if(!$result)  {
                                        \Log::info('OrderRefund_6_['.$orderGoodsInfo['id'].']');
                                        DB::rollback();
                                        return false;
                                    }
                                }
                            }
                            $result = OrderInfo::update_data($refund['order_id'],$refund['merchant_id'],['status'=>ORDER_REFUND_CANCEL]);
                            if(!$result)  {
                                \Log::info('OrderRefund_7_['.$orderGoodsInfo['id'].']');
                                DB::rollback();
                                return false;
                            }
                        }
                        //日志
                        $refund_str = '原路退款成功，请及时查收！';
                        if($orderinfo['pay_type'] == ORDER_PAY_DELIVERY){
                            $refund_str = '买卖双方已在线下退款成功';
                        }
                        $result = OrderRefundLog::insert_data([
                            'merchant_id' => $refund['merchant_id'],
                            'order_refund_id' => $refund['order_refund_id'],
                            'who' => '卖家',  'status_str' => '已成功退款',
                            'detaill'  => json_encode([ ['name'=> '退款成功' ,'value'=> $refund_str ] ],JSON_UNESCAPED_UNICODE)
                        ]);
                        if(!$result)  {
                            \Log::info('OrderRefund_8_['.$orderGoodsInfo['id'].']');
                            DB::rollback();
                            return false;
                        }
                        \Log::info('OrderRefund_0_['.$orderGoodsInfo['id'].']');
                        DB::commit();
                        //还销量
                        (new GoodsService())->desCsale(['goods_id'=>$refundInfo['goods_id'],'merchant_id'=>$refund['merchant_id'],'stock_num'=>$refundInfo['refund_quantity'],'goods_spec_id'=>$refundInfo['spec_id']]);
                        //还库存
                        $this->changeStock($refund['merchant_id'],$refundInfo['order_id'],$orderGoodsInfo['id'],$orderGoodsInfo['goods_id'],$refundInfo['refund_quantity'],$refundInfo['spec_id']);
                        //分销
                        $this->dispatch(new DistribRefundComission($refundInfo['order_id'], $refundInfo['id'], $refundInfo['merchant_id']));
                        //虚拟商品订单
                        if($orderinfo['order_goods_type'] == ORDER_GOODS_VIRTUAL){
                            $virtualGoodsService = new VirtualGoodsService();
                            //根据退款数量，把核销表对应数量的记录变为退款完成
                            $virtualGoodsService->changeHexiao($orderinfo, $refundInfo['refund_quantity']);
                            //订单状态非维权关闭，且未核销次数为0，订单完成
                            $virtualGoodsService->successOrder($orderinfo);
                        }
                    }
                }
            }
            //end author wangshiliang@dodoca.com
			if($refund['apply_type']==2 && $refund['order_id'] > 0){
                //退款成功更新拼团退款记录表fightgroup_refund
                $order_id = $refund['order_id'];
                $FightgroupRefund_info = FightgroupRefund::select('id','merchant_id')->where('order_id',$order_id)->first();
                if($FightgroupRefund_info['id'] && $FightgroupRefund_info['id']>0){
                    $fightgroupUpdata['finished_at'] = date('Y-m-d H:i:s') ;
                    $fightgroupUpdata['status'] = PIN_REFUND_SUCCESS ;
                    //更新退款记录
                    FightgroupRefund::update_data($FightgroupRefund_info['id'],$FightgroupRefund_info['merchant_id'],$fightgroupUpdata);
                }
            }
		} else if($refund['status']==3) {	//退款申请成功
            //statr author wangshiliang@dodoca.com
           OrderRefund::update_data($refund['order_refund_id'],$refund['merchant_id'],['status' => REFUND_TRADING ,'applyed_time'=>date('Y-m-d H:i:s'),'refund_msg'=>'']);
            //end author wangshiliang@dodoca.com
        } else if($refund['status']==2) {	//退款失败
			if(isset($refund['refund_msg']) && $refund['refund_msg'] && $refund['order_refund_id']) {
       			OrderRefund::update_data($refund['order_refund_id'],$refund['merchant_id'],['refund_msg' => $refund['refund_msg']]);
			}
        }
		
	}

	//还库存 wangshiliang@dodoca.com
    private function changeStock($merchant_id,$order_id,$order_goods_id,$goods_id,$quantity,$goods_spec_id){
	    $response = OrderPackageItem::query()->where(['order_id'=>$order_id,'order_goods_id'=>$order_goods_id])->first();
	    if(isset($response['id'])){
	        return false;
        }
        //是否拼团订单
        $orderinfo = OrderInfo::get_data_by_id($order_id,$merchant_id);
        $activity = isset($orderinfo['order_type']) && $orderinfo['order_type'] == 2 ? 'tuan':'';
        //是否预约订单
        if(isset($orderinfo['order_type']) && $orderinfo['order_type'] == 4){
            $orderapptinfo = OrderAppt::query()->where(['order_id'=>$order_id,'merchant_id'=>$merchant_id])->first();
            $appt_date = isset($orderapptinfo['appt_date']) && !empty($orderapptinfo['appt_date']) ?  $orderapptinfo['appt_date'] : null;
        }else{
            $appt_date = null;
        }
        //还库存
        return  ( new  GoodsService()) ->incStock([
            'merchant_id'  => $merchant_id,
            'stock_num'    => $quantity, // 加库存量
            'goods_id'     => $goods_id, // 商品id
            'goods_spec_id' => $goods_spec_id,// 规格id 没有传0
            'activity'     => $activity,// 商品所需操作库存类型  普通商品：可不传  拼团：tuan
            'date'         => $appt_date //预约商品-预约日期 2017-09-11
        ]);
    }




}

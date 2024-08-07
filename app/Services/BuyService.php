<?php
/**
 * 下单支付服务类
 * @author zhangchangchun@dodoca.com
 */
namespace App\Services;

use App\Models\AloneActivityRecode;
use App\Models\Seckill;
use App\Models\SeckillInitiate;
use App\Utils\CommonApi;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Bus\DispatchesJobs;
use App\Models\Cart;
use App\Models\DiscountGoods;
use App\Models\Goods;
use App\Models\GoodsSpec;
use App\Models\OrderInfo;
use App\Models\OrderGoods;
use App\Models\OrderUmp;
use App\Models\OrderGoodsUmp;
use App\Models\OrderAddr;
use App\Models\OrderRefundApply;
use App\Models\Member as MemberModel;
use App\Models\MemberCard;
use App\Models\MemberAddress;
use App\Models\PaymentApply;
use App\Models\MerchantSetting;
use App\Models\Shipment;
use App\Models\ShipmentArea;
use App\Models\ShipmentAreaRegion;
use App\Models\OrderSelffetch;
use App\Models\OrderVirtualgoods;
use App\Models\DistribBuyerRelation;
use App\Models\UserLog;
use App\Services\GoodsService;
use App\Services\DiscountService;
use App\Services\WeixinPayService;
use App\Services\ShipmentService;
use App\Services\CreditService;
use App\Services\ApptService;
use App\Services\MerchantService;
use App\Jobs\OrderRefund as OrderRefundJob;
use App\Jobs\WeixinMsgJob;
use App\Jobs\OrderPaySuccess;
use App\Facades\Member;

class BuyService {
	
	use DispatchesJobs;
    private static $order_name = [
        ORDER_SALEPAY => '优惠买单',
        ORDER_KNOWLEDGE => '知识付费',
    ];

	/**
	 * 下单公共调用
	 * @author zhangchangchun@dodoca.com
	 * data = array(
	 		'merchant_id'	=>	0,	//商户id
	 		'member_id'		=>	0,	//会员id
	 		'order_type'	=>	0,	//订单类型
			'goods'			=>	array(	//订单商品
				0	=>	array(
					'goods_id'	=>	0,	//商品id
					'spec_id'	=>	0,	//商品规格id
					'sum'		=>	0,	//购买数量
					
					'pay_price'	=>	0,	//购买价格(多个商品总价)
					'ump_type'=>	0,	//优惠类型（config/varconfig.php -> order_ump_ump_type）,没有为空
					'ump_id'	=>	0,	//优惠活动id
					
					'appoint_date'	=>	'2017-09-09',	//预约商品（预约日期）
				),
			),
			'amount'		=>	0,	//订单金额（适合无商品处理）
			'store_id'		=>	0,	//门店id（适合无商品处理）
			'coupon_id'		=>	0,	//券id（适合无商品处理）
			'is_credit'		=>	0,	//是否使用积分（1-使用，0-不使用）
	 	);
	 */
	public function createorder($data) {
		$GoodsService = new GoodsService;
		$DiscountService = new DiscountService;
		$CouponService = new CouponService;
		$ApptService = new ApptService;
		
		$merchant_id = isset($data['merchant_id']) ? (int)$data['merchant_id'] : 0;
		$member_id = isset($data['member_id']) ? (int)$data['member_id'] : 0;
		$order_type = isset($data['order_type']) ? (int)$data['order_type'] : 0;
		$is_credit = isset($data['is_credit']) ? (int)$data['is_credit'] : 0;
		$coupon_id = isset($data['coupon_id']) ? (int)$data['coupon_id'] : 0;
		$goods = isset($data['goods']) ? $data['goods'] : '';
		$config = config('config');
		$stock_type = isset($config['stock_type'][$order_type]) ? (int)$config['stock_type'][$order_type] : 0;
		$order_goods_ump_type = config('varconfig.order_goods_ump_ump_type');
		$source = isset($data['source']) ? (int)$data['source'] : 0;
		$source_id = isset($data['source_id']) ? (int)$data['source_id'] : 0;

		if(!$merchant_id) {
			return array('errcode'=>40059,'errmsg'=>'商户id不存在');
		}
		if(!$member_id) {
			return array('errcode'=>40059,'errmsg'=>'会员id不存在');
		}
		
		//验证版本权限是否过期
		$merchantInfo = MerchantService::getMerchantVersion($merchant_id);
		if(!$merchantInfo || !isset($merchantInfo['errcode'])) {
			return array('errcode'=>99901,'errmsg'=>'下单失败，获取商户数据失败');
		}
		if($merchantInfo['errcode']==0) {
			if($merchantInfo['data']['is_expired']==1) {
				return array('errcode'=>99901,'errmsg'=>'商户已关闭下单功能');
			}
		} else {
			return array('errcode'=>99901,'errmsg'=>'下单失败，'.$merchantInfo['errmsg']);
		}
		
		//是否有实质商品（伪造商品）
        if ($order_type == ORDER_KNOWLEDGE) {//知识付费订单名称、图
            self::$order_name[ORDER_KNOWLEDGE] = isset($data['name']) ? $data['name'] : '-';
            $data['img'] = (isset($data['img']) ? $data['img'] : '');
        }
        if(!$this->get_reduction_power($order_type,'goods',['merchant_id'=>$merchant_id,'member_id'=>$member_id])) {
			$goods[] = [
				'goods_id'	=>	0,
				'spec_id'	=>	0,
				'sum'		=>	1,	//购买数量
				'price'		=>	isset($data['amount']) ? $data['amount'] : 0,	//商品价格
				'pay_price'	=>	isset($data['amount']) ? $data['amount'] : 0,	//购买价格
				'goods'		=>	[
					'id'				=>	0,
					'merchant_id'		=>	$merchant_id,
					'goods_cat_id'		=>	0,
					'title'				=> isset(self::$order_name[$order_type]) ? self::$order_name[$order_type] : '普通商品',
					'goods_sn'			=>	'',
					'barcode'			=>	'',
					'original_price'	=>	'',
					'price'				=>	isset($data['amount']) ? $data['amount'] : 0,
					'max_price'			=>	0,
					'intro'				=>	isset(self::$order_name[$order_type]) ? self::$order_name[$order_type] : '普通商品',
					'weight'			=>	0,
					'volume'			=>	0,
					'stock_type'		=>	0,
					'shipment_id'		=>	0,
					'postage'			=>	0,
					'is_sku'			=>	1,
					'is_discount'		=>	1,
					'is_givecredit'		=>	1,
                    'img' => ($order_type == ORDER_KNOWLEDGE) ? $data['img'] : '',
				],
			];
		}
		if(!$goods) {
			return array('errcode'=>40059,'errmsg'=>'商品数据异常');
		}
		if(!isset($config['order_type'][$order_type])) {
			return array('errcode'=>40059,'errmsg'=>'订单类型异常');
		}

		$appid = Member::appid();	//获取小程序appid
        //$appid = 1;
		if(!$appid) {
			return array('errcode'=>40059,'errmsg'=>'小程序或公众号参数缺失');
		}

		//获取商户配置数据
		$mrSetInfo = MerchantSetting::get_data_by_id($merchant_id);
		if(!$mrSetInfo) {
			return array('errcode'=>40092,'errmsg'=>'商户尚未配置规则');
		}
		
		//获取会员信息
		$memberinfo = MemberModel::get_data_by_id($member_id,$merchant_id);
		if(!$memberinfo) {
			return array('errcode'=>40059,'errmsg'=>'会员不存在');
		}
		
		$amount = 0;				//订单总金额
		$goods_amount = 0;			//商品总金额
		//$ump_amount = 0;			//优惠总金额
		$shipment_fee = 0;			//运费总金额
		$shipment_original_fee = 0;	//原始运费金额
		$card_discount = 0;			//会员折扣
		$card_is_postage_free = 0;	//会员包邮
		$orderump = [];				//订单主表优惠数组
		$credit_dis_count = 0;		//积分抵扣数量
		$credit_dis_amount = 0;		//积分抵扣金额
		$delivery_type = 0;			//配送方式
		
		$order_goods_type = 0;      //订单商品类型
		
		//获取会员卡信息
		if($memberinfo['member_card_id']) {
			$cardinfo = MemberCard::get_data_by_id($memberinfo['member_card_id'],$merchant_id);
			if($cardinfo) {
				if($cardinfo['discount']>0 && $cardinfo['discount']<10) {
					$card_discount = $cardinfo['discount']/10;
				}
				$card_is_postage_free = $cardinfo['is_postage_free'];
			}
		}

		$shipment_goods = [];
		foreach($goods as $key => $v) {
			if($this->get_reduction_power($order_type,'goods',['merchant_id'=>$merchant_id,'member_id'=>$member_id])) {	//拥有真实的商品体系，计算优惠
				$goods_price = 0;
				if(!isset($v['goods_id']) || !isset($v['sum'])) {
					return array('errcode'=>40059,'errmsg'=>'商品参数有误');
				}
				if(!isset($v['spec_id'])) {
					$v['spec_id'] = 0;
					$goods[$key]['spec_id'] = 0;
				}
				
				$goodsinfo = Goods::get_data_by_id($v['goods_id'],$merchant_id);
				if($goodsinfo) {
					if($goodsinfo['shipment_id']>0 && (!$goodsinfo['weight'] || !$goodsinfo['volume'])) {
						return array('errcode'=>40059,'errmsg'=>'运费商品有误');
					}
					$goods[$key]['is_discount'] = $goodsinfo['goodsinfo'];
					if($goodsinfo['onsale']==0) {
						return array('errcode'=>40059,'errmsg'=>'商品'.$goodsinfo['title'].'已下架');
					}
					$v['price'] = $goodsinfo['price'];
					$goods[$key]['price'] = $goodsinfo['price'];
					if(($v['spec_id'] && $goodsinfo['is_sku']==0) || (!$v['spec_id'] && ($goodsinfo['is_sku']==1 || $goodsinfo['is_sku']==2))) {
						return array('errcode'=>40059,'errmsg'=>'商品规格不符');
					}
					if($v['spec_id'] && ($goodsinfo['is_sku']==1 || $goodsinfo['is_sku']==2)) {
						$specinfo = GoodsSpec::get_data_by_id($v['spec_id'],$merchant_id);
						if($specinfo) {
							$goods[$key]['specs'] = is_object($specinfo) ? $specinfo->toArray() : $specinfo;
							if($goodsinfo['is_sku']==2) {	//预约商品重新获取规格
								try {
									$appt = $ApptService->getApptPropsStr($specinfo['id'],$merchant_id,1);
									\Log::info('createorder-appt:specinfo->'.$specinfo.',result->'.json_encode($appt,JSON_UNESCAPED_UNICODE));
								} catch (\Exception $e) {
									return array('errcode'=>40059,'errmsg'=>'获取预约商品规格失败，请重试，'.$e->getMessage());
								}
								if(isset($appt['errcode']) && $appt['errcode']==0 && isset($appt['data']) && isset($appt['data'])) {
									$goods[$key]['specs']['props_str'] = $appt['data'];
								}
							}
							$goods_price = $specinfo['price'];
						} else {
							unset($goods[$key]);
							return array('errcode'=>40059,'errmsg'=>'商品规格id->'.$v['spec_id'].'已删除');
						}
						$v['price'] = $specinfo['price'];
						$goods[$key]['price'] = $specinfo['price'];
					} else {
						$goods_price = $goodsinfo['price'];
					}
					if($stock_type==1) {	//拍下减库存
						$goodsinfo['stock_type'] = 1;
					} else if($stock_type==2) {	//付款扣库存
						$goodsinfo['stock_type'] = 0;
					}
					$goods[$key]['goods'] = is_object($goodsinfo) ? $goodsinfo->toArray() : $goodsinfo;
					
					//记录下固定运费总数
					if($goodsinfo['postage']>0 && $goodsinfo['shipment_id']==0) {
						$shipment_original_fee += $goodsinfo['postage']*$v['sum'];
					}
					
					$shipment_goods['goods'][] = [
						'id'		=>	$v['goods_id'],
						'quantity'	=>	$v['sum'],
					];
					
					//商品类型为虚拟商品->订单商品类型为虚拟商品订单
					if($goodsinfo['goods_type'] == 1){
					    $order_goods_type = ORDER_GOODS_VIRTUAL;
					}
					
				} else {
					unset($goods[$key]);
					return array('errcode'=>40059,'errmsg'=>'商品id->'.$v['goods_id'].'已删除');
				}

				//计算库存			
				//if($goodsinfo['stock_type']==1) {	//拍下减库存
					$stockdata = [
						'merchant_id'	=>	$merchant_id,
						'goods_id'		=>	$goodsinfo['id'],
						'goods_spec_id'	=>	isset($specinfo) ? $specinfo['id'] : 0,
						'stock_num'		=>	$v['sum'],
					];
					if($order_type==ORDER_FIGHTGROUP) {	//拼团订单
						$stockdata['activity'] = 'tuan';
					}
					if($order_type==ORDER_APPOINT) {	//预约订单
						$stockdata['date'] = isset($v['appoint_date']) ? $v['appoint_date'] : '';
					}
					$stock = $GoodsService->getGoodsStock($stockdata);
					if($stock && isset($stock['errcode']) && isset($stock['data']) && $stock['errcode']==0) {
						if($stock['data']<$v['sum']) {
							return array('errcode'=>40059,'errmsg'=>$goodsinfo['title'].' 库存不足');
						}
					} else {
						return array('errcode'=>40059,'errmsg'=>(isset($stock['errmsg']) ? $stock['errmsg'] : '库存获取失败'));
					}
					$goods[$key]['stockdata'] = $stockdata;
				//}
				
				//验证限购
				$cquto_data = [
					'order_type'	=>	isset($data['order_type']) ? $data['order_type'] : 0,
					'ump_id'		=>	isset($v['ump_id']) ? $v['ump_id'] : 0,
				];
				$cquresult = $GoodsService->getCquota($v['sum'], $v['goods_id'], $member_id, $merchant_id,$cquto_data);
				if($cquresult && isset($cquresult['errcode']) && $cquresult['errcode']==0) {
					
				} else {
					return array('errcode'=>40059,'errmsg'=>$cquresult['errmsg']);
				}
				
				//计算优惠
				if(isset($v['ump_type']) && !isset($order_goods_ump_type[$v['ump_type']])) {
					return array('errcode'=>40059,'errmsg'=>'优惠方式不存在');
				}
				$goods_amount += $goods_price*$v['sum'];
				if(isset($v['ump_type'])) {
					$init_ump_amount = sprintf('%0.2f',($goods_price*$v['sum']-$v['pay_price']));
					if($init_ump_amount<0) {
						return array('errcode'=>40059,'errmsg'=>'活动价异常，请联系商家');
					}
					$amount += $v['pay_price'];
					$goods[$key]['pay_price'] = $v['pay_price'];				
					//$ump_amount += (float)$init_ump_amount;
					$goods[$key]['marking'][] = [
						'ump_type'	=>	$v['ump_type'],
						'ump_id'	=>	$v['ump_id'],
						'amount'	=>	$init_ump_amount,
						'memo'		=>	$order_goods_ump_type[$v['ump_type']].''.$init_ump_amount.'元',
					];
					
					//存储主优惠
					$is_ump = 1;
					foreach($orderump as $kk => $umpinfo) {
						if($umpinfo['ump_type']==$v['ump_type']) {
							$is_ump = 0;
							$orderump[$kk]['amount'] -= $init_ump_amount;
							$orderump[$kk]['memo'] = $order_goods_ump_type[$v['ump_type']].''.$orderump[$kk]['amount'].'元';
						}
					}
					if($is_ump==1) {
						$orderump[] = [
							'ump_type'	=>	$v['ump_type'],
							'amount'	=>	-$init_ump_amount,
							'memo'		=>	$order_goods_ump_type[$v['ump_type']].''.$init_ump_amount.'元',
						];
					}
				} else {
					$goods[$key]['pay_price'] = $goods_price*$v['sum'];
					$amount += $goods_price*$v['sum'];
				}
			} else {
				$amount = $goods_amount = $data['amount'];
			}

			//会员折扣
			if($this->get_reduction_power($order_type,'vip',['merchant_id'=>$merchant_id,'member_id'=>$member_id]) && $card_discount>0 && $goods[$key]['goods']['is_discount']==1) {
				$vip_price = (float)sprintf('%0.2f',$goods[$key]['pay_price']*$card_discount);
				$vip_discount = $goods[$key]['pay_price']-$vip_price;
				if($vip_discount>0) {
					$goods[$key]['marking'][] = [
						'ump_type'	=>	1,
						'ump_id'	=>	0,
						'amount'	=>	-$vip_discount,
						'memo'		=>	$order_goods_ump_type[1].''.$vip_discount.'元',
					];
					$goods[$key]['pay_price'] = $vip_price;
					$amount = (float)sprintf('%0.2f',($amount-$vip_discount));
					
					//存储主优惠
					$is_ump = 1;
					foreach($orderump as $kk => $umpinfo) {
						if($umpinfo['ump_type']==1) {
							$is_ump = 0;
							$orderump[$kk]['amount'] -= $vip_discount;
							$orderump[$kk]['memo'] = $order_goods_ump_type[1].''.$orderump[$kk]['amount'].'元';
						}
					}
					if($is_ump==1) {
						$orderump[] = [
							'ump_type'	=>	1,
							'amount'	=>	-$vip_discount,
							'memo'		=>	$order_goods_ump_type[1].''.$vip_discount.'元',
						];
					}
				}
			}
		}
		if(!$goods) {
			return array('errcode'=>40059,'errmsg'=>'订单中商品无效');
		}
		$is_baoyou_order = 0;//用于验证订单商品有满包邮时运费置为0
		//参加满减

		if($this->get_reduction_power($order_type,'discount',['merchant_id'=>$merchant_id,'member_id'=>$member_id])) {
			$disgoods = [];
			foreach($goods as $ginfo) {
				$disgoods[] = [
					'goods_id'	=>	$ginfo['goods_id'],
					'spec_id'	=>	$ginfo['spec_id'],
					'sum'		=>	$ginfo['sum'],
					'price'		=>	sprintf('%0.2f',$ginfo['pay_price']/$ginfo['sum']),
				];
			}
			$disdata = [
				'merchant_id'	=>	$merchant_id,
				'member_id'		=>	$member_id,
				'goods'			=>	$disgoods,
			];
			$discount = $DiscountService->getOrderGoodsDiscountMoney($disdata);

			//满减日志	
			CommonApi::wlog([
				'custom'    	=>    	'buyservice-discount_'.$member_id,
				'merchant_id'   =>    	$merchant_id,
				'member_id'     =>    	$member_id,
				'content'		=>		'require->'.json_encode($disdata,JSON_UNESCAPED_UNICODE).',result->'.json_encode($discount,JSON_UNESCAPED_UNICODE),
			]);
			
			if($discount) {
				$distdata = [];
				foreach($discount as $dtinfo) {
					if(isset($dtinfo['discount_id']) && isset($dtinfo['discounted_price']) && $dtinfo['discounted_price']>=0 && isset($dtinfo['reduction']) && isset($dtinfo['postage'])) {
						$distdata[$dtinfo['goods_id'].'_'.$dtinfo['spec_id']] = [
							'discount_id'		=>	$dtinfo['discount_id'],
							'discounted_price'	=>	$dtinfo['discounted_price'],
                            'reduction'	=>	$dtinfo['reduction'],
                            'postage'	=>	$dtinfo['postage'],
						];
					}
				}
				//dd($distdata);
				if($distdata) {
                    //存储主优惠验证
                    $is_ump_baoyou = 1;

					foreach($goods as $key => $ginfo) {
						//有满减数据
                        $dsdata = isset($distdata[$ginfo['goods_id'] . '_' . $ginfo['spec_id']]) ? $distdata[$ginfo['goods_id'] . '_' . $ginfo['spec_id']] : [];
                        if ($dsdata && ($distdata[$ginfo['goods_id'] . '_' . $ginfo['spec_id']]['reduction'] == 1)) {
                            $dis_ump_amount = sprintf('%0.2f', $dsdata['discounted_price']);
                            if ($dis_ump_amount < 0) {
                                return array('errcode' => 40059, 'errmsg' => '订单中满减优惠信息有误');
                            }
                            $amount = sprintf('%0.2f', $amount - (float)$dis_ump_amount);
                            $goods[$key]['pay_price'] = sprintf('%0.2f', ($ginfo['pay_price'] - $dis_ump_amount));
                            //$ump_amount += (float)$init_ump_amount;
                            $goods[$key]['marking'][] = [
                                'ump_type' => 7,
                                'ump_id' => $dsdata['discount_id'],
                                'amount' => -$dis_ump_amount,
                                'memo' => $order_goods_ump_type[7] . '' . $dis_ump_amount . '元',
                            ];

                            //存储主优惠
                            $is_ump = 1;
                            foreach ($orderump as $kk => $umpinfo) {
                                if ($umpinfo['ump_type'] == 7) {
                                    $is_ump = 0;
                                    $orderump[$kk]['amount'] -= $dis_ump_amount;
                                    $orderump[$kk]['memo'] = $order_goods_ump_type[7] . '' . $orderump[$kk]['amount'] . '元';
                                }
                            }
                            if ($is_ump == 1) {
                                $orderump[] = [
                                    'ump_type' => 7,
                                    'amount' => -$dis_ump_amount,
                                    'memo' => $order_goods_ump_type[7] . '' . $dis_ump_amount . '元',
                                ];
                            }
                        }
                        //dd($goods[$key]['is_discount']);
                        //满包邮
                        if ($dsdata && ($distdata[$ginfo['goods_id'] . '_' . $ginfo['spec_id']]['postage'] == 1) && $order_goods_type != ORDER_GOODS_VIRTUAL){

                            $goods[$key]['marking'][] = [
                                'ump_type' => 8,
                                'ump_id' => $dsdata['discount_id'],
                                'amount' => 0,
                                'memo' => $order_goods_ump_type[8],
                            ];


                            if ($is_ump_baoyou == 1) {
                                $is_ump_baoyou = 0;//一笔订单只存一条记录
                                $is_baoyou_order = 1;//等于1时，此订单为满包邮订单，运费不计算
                                $orderump[] = [
                                    'ump_type' => 8,
                                    'amount' => 0,
                                    'memo' => $order_goods_ump_type[8],
                                ];
                            }
                        }

                        //dd($orderump);
					}
				}
			}
		}
		//dd($is_baoyou_order);
		//优惠券抵扣
		if($coupon_id && $this->get_reduction_power($order_type,'coupon',['merchant_id'=>$merchant_id,'member_id'=>$member_id])) {
			if($amount<=0) {
				return array('errcode'=>40059,'errmsg'=>'优惠券无需使用');
			}
			$coupdata = [
				'merchant_id'	=>	$merchant_id,
				'member_id'		=>	$member_id,
				'coupon_code_id'=>	$coupon_id,
				'order_goods'	=>	$goods,
			];
			$couponinfo = $CouponService->getDiscount($coup
		if($is_credit==1 && $this->get_reduction_power($order_type,'credit',['merchant_id'=>$merchant_id,'member_id'=>$member_id]) && $amount>0) {
            $get_use_credit = self::get_credit(['merchant_id'=>$merchant_id,'member_id'=>$member_id,'amount'=>$amount]);
            if($get_use_credit && isset($get_use_credit['credit_amount']) && isset($get_use_credit['credit_ded_amount']) && $get_use_credit['credit_amount']>0 && $get_use_credit['credit_ded_amount']>0) {
                $credit_dis_count = $get_use_credit['credit_amount'];			//积分抵扣数量
                $credit_dis_amount = $get_use_credit['credit_ded_amount'];		//积分抵扣金额

                $amount -= $get_use_credit['credit_ded_amount'];
                $orderump[] = [
                    'ump_type'	=>	3,
                    'amount'	=>	-$get_use_credit['credit_ded_amount'],
                    'credit'	=>	$get_use_credit['credit_amount'],
                    'medata);
			if($couponinfo && isset($couponinfo['errcode']) && $couponinfo['errcode']==0) {
				$goods = self::split_coupon_price($goods,$couponinfo['data']);
				if($goods) {
					$coupon_dis_amount = 0;
					foreach($goods as $key => $v) {
						if(isset($v['marking_only'])) {
							$goods[$key]['marking'][] = $v['marking_only'];
							unset($v['marking_only']);
						}
						if(isset($goods[$key]['coupon_discount_price']) && $goods[$key]['coupon_discount_price']>0) {
							$coupon_dis_amount += $goods[$key]['coupon_discount_price'];
						}
					}
					if($coupon_dis_amount>0) {
						$amount = sprintf('%0.2f',$amount-(float)$coupon_dis_amount);
						$orderump[] = [
							'ump_type'	=>	2,
							'amount'	=>	-$coupon_dis_amount,
							'memo'		=>	$order_goods_ump_type[2].''.$coupon_dis_amount.'元',
						];
					}
				}
			} else {
				return ['errcode' => '110001', 'errmsg' => (isset($couponinfo['errmsg']) ? $couponinfo['errmsg'] : '获取优惠券失败，请重试')];
			}
		}
		
		//积分抵扣mo'		=>	$order_goods_ump_type[3].''.$get_use_credit['credit_ded_amount'].'元',
				];
				$goods = self::split_credit_price($goods,$get_use_credit);
				if($goods) {
					foreach($goods as $key => $v) {
						if(isset($v['marking_only'])) {
							$goods[$key]['marking'][] = $v['marking_only'];
							unset($v['marking_only']);
						}
					}
				}
				
			}
		}
		
		//计算运费
		$is_addr_ok = 0;
				
		if($this->get_reduction_power($order_type,'delivery',['merchant_id'=>$merchant_id,'member_id'=>$member_id]) && $mrSetInfo['delivery_enabled']==1 && $order_goods_type != ORDER_GOODS_VIRTUAL) {

			$delivery_type = 1;
			$memberaddr = MemberAddress::where(['member_id'=>$member_id,'is_default'=>1,'is_delete'=>1])->first();	//获取默认收货地址
			
			//验证商品是否支持会员卡优惠，如不支持
			$is_support_dis = 0;
			foreach($goods as $key => $info) {
				if(isset($info['goods']['is_discount']) && $info['goods']['is_discount']==1) {
					$is_support_dis = 1;
				}
			}
						
            if($is_baoyou_order==0) {//不是满包邮计算运费

                if ($memberaddr) {
                    if ($card_is_postage_free == 1 && $is_support_dis==1) {
                        $is_addr_ok = 1;
                    } else {
                        $shipment_goods['merchant_id'] = $merchant_id;
                        $shipment_goods['member_id'] = $member_id;
                        $shipment_goods['province'] = $memberaddr['province'];
                        $shipment_goods['city'] = $memberaddr['city'];
                        $sptresult = $this->getShipmentGoods($shipment_goods);                        
						if ($sptresult && isset($sptresult['errcode']) && $sptresult['errcode'] == 0) {
                            $shipment_original_fee = $shipment_fee = $sptresult['data'];
                            $is_addr_ok = 1;
                        }
                    }
                } else {
                    if ($card_is_postage_free != 1 && $memberaddr) {
                        $shipment_fee = $shipment_original_fee;
                    }
                }
            }
            if($is_baoyou_order==1) {//是满包邮
				
				//获取原始运费，不使用优惠还原用
				$shipment_goods['merchant_id'] = $merchant_id;
				$shipment_goods['member_id'] = $member_id;
				$shipment_goods['province'] = $memberaddr['province'];
				$shipment_goods['city'] = $memberaddr['city'];
				$sptresult = $this->getShipmentGoods($shipment_goods);
				if ($sptresult && isset($sptresult['errcode']) && $sptresult['errcode'] == 0) {
					foreach($orderump as $umpkey => $umpinfo) {
						if($umpinfo['ump_type']==8) {
							$orderump[$umpkey]['shipment_fee'] = $sptresult['data'];
						}
					}
				}
                $is_addr_ok = 1;
            }
		}

		$amount = $amount>0 ? $amount : 0;
		$order_sn = self::get_order_sn();
		$orderdata = array(
			'merchant_id'	=>	$merchant_id,
			'member_id'		=>	$member_id,
			'nickname'		=>	$memberinfo['name'],
			'order_sn'		=>	$order_sn,
			'order_title'	=>	$goods[0]['goods']['title'],
			'amount'		=>	$amount+$shipment_fee,
			'order_type'	=>	$order_type,
		    'order_goods_type'	=>	$order_goods_type,  //订单商品类型
			'goods_amount'	=>	$goods_amount,
			'shipment_fee'	=>	$shipment_fee,
			'shipment_original_fee'	=>	$shipment_original_fee,
			'status'		=>	5,
			'expire_at'		=>	date("Y-m-d H:i:s",(time()+$mrSetInfo['minutes']*60)),
			'appid'			=>	$appid,
			'store_id'		=>	isset($data['store_id']) ? (int)$data['store_id'] : 0,
			'delivery_type'	=>	$delivery_type,
			'source'	=>	$source,
			'source_id'		=>	$source_id,
		);
		if($orderdata['amount']==0 && in_array($order_type, [ORDER_SALEPAY, ORDER_KNOWLEDGE])) {	//优惠买单、知识付费特殊处理下
			$orderdata['status'] = ORDER_SUCCESS;
			$orderdata['pay_status'] = 1;
			$orderdata['pay_time'] = date("Y-m-d H:i:s");
			$orderdata['finished_time'] =  date("Y-m-d H:i:s",time()-7*86400);
		}
		//获取推客ID
		$distrib_buyer_relation = DistribBuyerRelation::get_data_by_memberid($member_id, $merchant_id);
		if($distrib_buyer_relation){
			$orderdata['distrib_member_id'] = $distrib_buyer_relation['distrib_member_id'];
		}

		$order_id = OrderInfo::insert_data($orderdata);
		if(!$order_id) {
			return array('errcode'=>40059,'errmsg'=>'订单生成失败，请重试');
		}
		
		//使用积分
		if($credit_dis_count && $credit_dis_amount) {			
			$credit_memo = ($order_type==ORDER_SALEPAY ? '买单' : '购物').'使用 '.$credit_dis_count.' 积分抵扣 '.$credit_dis_amount.' 元，订单号：'.$order_sn;
			$CreditService = new CreditService;
			$result = $CreditService->giveCredit($merchant_id,$member_id,4,['give_credit'=>-$credit_dis_count,'memo'=>$credit_memo]);
			if($result && isset($result['errcode']) && $result['errcode']==0) {
				
			} else {
				return array('errcode'=>40059,'errmsg'=>((isset($result['errmsg']) && $result['errmsg']) ? $result['errmsg'] : '积分使用失败'));
			}		
		}
		
		//使用优惠券
		if($coupon_id) {			
			$use_coupon_data = [
				'merchant_id'	=>	$merchant_id,
				'member_id'		=>	$member_id,
				'coupon_code_id'=>	$coupon_id,
				'use_member_id'	=>	$member_id,
			];
			$result = $CouponService->useCoupon($use_coupon_data);
			if($result && isset($result['errcode']) && $result['errcode']==0) {
				
			} else {
				return ['errcode' => '40090', 'errmsg' => ((isset($result['errmsg']) && $result['errmsg']) ? $result['errmsg'] : '优惠券使用失败')];
			}
		}
		
		foreach($goods as $key => $v) {
			$ordergoodsdata = array(
				'merchant_id'	=>	$merchant_id,
				'order_id'		=>	$order_id,
				'member_id'		=>	$member_id,
				'goods_id'		=>	$v['goods_id'],
				'spec_id'		=>	$v['spec_id'],
				'goods_name'	=>	$v['goods']['title'],
				'goods_img'		=>	$v['spec_id'] ? $v['specs']['img'] : $v['goods']['img'],
				'weight'		=>	$v['goods']['weight'],
				'volume'		=>	$v['goods']['volume'],
				'goods_sn'		=>	$v['spec_id'] ? $v['specs']['spec_sn'] : $v['goods']['goods_sn'],
				'barcode'		=>	$v['spec_id'] ? $v['specs']['barcode'] : $v['goods']['barcode'],
				'stock_type'	=>	$v['goods']['stock_type'],
				'quantity'		=>	$v['sum'],
				'price'			=>	$v['price'],
				'pay_price'		=>	$v['pay_price'],
				'props'			=>	$v['spec_id'] ? $v['specs']['props_str'] : '',

				'is_pinkage'	=>	(($card_is_postage_free==1 && $v['goods']['is_discount']==1) || $is_baoyou_order==1) ? 1 : 0,

				'postage'		=>	$v['goods']['postage'],
				'shipment_id'	=>	$v['goods']['shipment_id'],
				'valuation_type'=>	0,
				'start_standard'=>	0,
				'start_fee'		=>	0,
				'add_standard'	=>	0,
				'add_fee'		=>	0,
				'is_givecredit'	=>	$v['goods']['is_givecredit'],
			);
			if($v['goods']['shipment_id'] && isset($memberaddr) && isset($memberaddr['city'])) {
				$shipinfo = $this->getShipmentInfo(['merchant_id'=>$merchant_id,'shipment_id'=>$v['goods']['shipment_id'],'city'=>$memberaddr['city'],'province'=>$memberaddr['province']]);
				if($shipinfo && isset($shipinfo['errcode']) && $shipinfo['errcode']==0) {
					$ordergoodsdata['valuation_type'] = isset($shipinfo['valuation_type']) ? $shipinfo['valuation_type'] : 0;
					$ordergoodsdata['start_standard'] = isset($shipinfo['start_standard']) ? $shipinfo['start_standard'] : 0;
					$ordergoodsdata['start_fee'] = isset($shipinfo['start_fee']) ? $shipinfo['start_fee'] : 0;
					$ordergoodsdata['add_standard'] = isset($shipinfo['add_standard']) ? $shipinfo['add_standard'] : 0;
					$ordergoodsdata['add_fee'] = isset($shipinfo['add_fee']) ? $shipinfo['add_fee'] : 0;
				}
			}
			if($ordergoodsdata['shipment_id']>0 && (!$ordergoodsdata['weight'] || !$ordergoodsdata['volume'])) {
				return array('errcode'=>40059,'errmsg'=>'运费商品有误');
			}
			OrderGoods::insert_data($ordergoodsdata);
			
			if(isset($v['marking']) && $v['marking']) {
				foreach($v['marking'] as $kk => $mkinfo) {
					$goodsumpdata = array(
						'order_id'	=>	$order_id,
						'goods_id'	=>	$v['goods_id'],
						'spec_id'	=>	$v['spec_id'],
						'ump_id'	=>	$mkinfo['ump_id'],
						'ump_type'	=>	$mkinfo['ump_type'],
						'amount'	=>	$mkinfo['amount'],
						'credit'	=>	isset($mkinfo['credit']) ? (int)$mkinfo['credit'] : 0,
						'memo'		=>	$mkinfo['memo'],
					);
					OrderGoodsUmp::insert_data($goodsumpdata);
				}
			}
			
			//下单扣库存
			if($v['goods']['stock_type']==1) {	//拍下减库存
				$stock = $GoodsService->desStock($v['stockdata']);
				
				//记录扣库存日志
				CommonApi::wlog([
					'custom'    	=>    	'createorder-destock_'.$order_id,
					'merchant_id'   =>    	$merchant_id,
					'member_id'     =>    	$member_id,
					'content'		=>		'result->'.json_encode($stock,JSON_UNESCAPED_UNICODE),
				]);
				
				if($stock && isset($stock['errcode']) && isset($stock['data']) && $stock['errcode']==0) {
					//增加销量
					$resutl = $GoodsService->incCsale($v['stockdata']);

					//秒杀卖完处理
                    if ($order_type == ORDER_SECKILL && $stock['data'] == 0 && isset($v['ump_id'])) {
                        $goods_res=Goods::get_data_by_id($v['goods_id'],$merchant_id);
                        if($goods_res->is_sku == 0){
                            $this->stopSeckill($v['ump_id'], $merchant_id);
                        }elseif($goods_res->is_sku == 1){//多规格秒杀商品检查总库存
                            $res=GoodsSpec::get_data_by_goods_id($v['goods_id'],$merchant_id);
                            if(!empty($res)){
                                $stock_sum = 0;
                                $param_stock = [
                                    'merchant_id' => $merchant_id,
                                    'goods_id' => $v['goods_id'],
                                ];
                                foreach ($res as $goods_spec_id) {
                                    $param_stock['goods_spec_id'] = $goods_spec_id->id;
                                    $stock_res = $GoodsService->getGoodsStock($param_stock);
                                    if ($stock_res['errcode'] != 0) {
                                        //记录异常
                                        $except = [
                                            'activity_id' => $order_id,
                                            'data_type' => 'createorder-getstock',
                                            'content' => '秒杀下单获取多规格总库存异常：' . json_encode($stock_res, JSON_UNESCAPED_UNICODE),
                                        ];
                                        CommonApi::errlog($except);
                                    }
                                    $stock_sum += (!empty($stock_res['data']) ? intval($stock_res['data']) : 0);
                                }
                                if ($stock_sum < 1) {
                                    $this->stopSeckill($v['ump_id'], $merchant_id);
                                }
                            }
                        }
                    }
				} else {
					return array('errcode'=>40059,'errmsg'=>$goodsinfo['title'].'->商品库存不足');
				}
			}
		}
		if(isset($orderump) && $orderump) {
			foreach($orderump as $key => $umpinfo) {
				$goodsumpdata = array(
					'order_id'	=>	$order_id,
					'ump_type'	=>	$umpinfo['ump_type'],
					'amount'	=>	$umpinfo['amount'],
					'credit'	=>	isset($umpinfo['credit']) ? $umpinfo['credit'] : 0,
					'memo'		=>	$umpinfo['memo'],
				);
				if(isset($umpinfo['shipment_fee'])) {
					$goodsumpdata['shipment_fee'] = $umpinfo['shipment_fee'];
				}
				OrderUmp::insert_data($goodsumpdata);
			}
		}

		//添加收货地址
		if($is_addr_ok == 1 && $memberaddr) {
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
				
		$data = [
			'order_id'	=>	$order_id,
			'order_sn'	=>	$order_sn,
		];

        if (in_array($order_type, [ORDER_SALEPAY, ORDER_KNOWLEDGE])) {//优惠买单、知识付费直接发起支付
			$orderdata['id'] = $order_id;
			
			if($amount<=0) {
			    
			    $salepay_odata['pay_type'] = ORDER_PAY_WEIXIN;//支付方式（优惠买单默认微信支付）
			    $upid = OrderInfo::update_data($order_id,$merchant_id,$salepay_odata);
			    
				//发送到队列(抵扣完)
				$job = new OrderPaySuccess($orderdata['id'],$orderdata['merchant_id']);
				$this->dispatch($job);
				return ['errcode' => '0', 'errmsg' => '订单已支付', 'ispay' => 1, 'order_id' => $order_id, 'order_sn' => $order_sn];
			} else {
				$payinfo = self::topay($orderdata);
				if($payinfo && isset($payinfo['errcode']) && $payinfo['errcode']==0 && $payinfo['data']) {
					return ['errcode' => '0', 'errmsg' => '提交成功', 'data' => $payinfo['data'], 'order_id' => $order_id, 'order_sn' => $order_sn];
				} else {
					return ['errcode' => '40090', 'errmsg' => ((isset($payinfo['errmsg']) && $payinfo['errmsg']) ? $payinfo['errmsg'] : '订单生成失败，请重试'), 'order_id' => $order_id, 'order_sn' => $order_sn];
				}
			}
			
		}
		
		return ['errcode' => 0, 'errmsg' => '', 'data' => $data];
	}
	
	/**
	 * 订单支付公共调用
	 * @author zhangchangchun@dodoca.com
	 * order 订单数据
	 */
	public function topay($order) {
		$WeixinPayService = new WeixinPayService;
		$PaymentApply = new PaymentApply;
		
		$apply = $PaymentApply->where(['order_id'=>$order['id'],'amount'=>$order['amount']])->where("created_time",">",date('Y-m-d h:i:s',strtotime("-30 minute")))->orderBy('id','desc')->first();
		if(!$apply) {
			$payment_sn = self::get_payment_sn();
			$pdata = [
				'order_id'	=>	$order['id'],
				'payment_sn'=>	$payment_sn,
				'amount'	=>	$order['amount'],
				'pay_type'	=>	1,	//在线支付先默认为1-微信支付
			];
			$apply_id = $PaymentApply->insert($pdata);
			if(!$apply_id) {
				return array('errcode'=>40059,'errmsg'=>'订单生成失败，请重试');
			}
			$apply = [
				'id'		=>	$apply_id,
				'order_id'	=>	$order['id'],
				'payment_sn'=>	$payment_sn,
				'amount'	=>	$order['amount'],
			];
		}
		$pdata = [
			'merchant_id'	=>	$order['merchant_id'],
			'member_id'		=>	$order['member_id'],
			'no'			=>	$apply['payment_sn'],
			'total_fee'		=>	$apply['amount'],
			'notifyUrl'		=>	env('PAY_URL').'/com/cashier/paynotify/'.$apply['payment_sn'].'/'.$order['order_type'],
			'appid'			=>	$order['appid'],
		];
		return $WeixinPayService->payOrder($pdata);
	}
	
	/**
	 * 获取订单号
	 * @author zhangchangchun@dodoca.com
	 * prefix 前缀
	 */
	public static function get_order_sn($prefix='E') {
        $order_sn = $prefix.date('Ymdhis').str_pad(mt_rand(1,99999),5,'0',STR_PAD_LEFT);
        $order = OrderInfo::select('order_sn')->where(array('order_sn' => $order_sn))->first();
        if(!$order) {
            return $order_sn;
        }
		return self::get_order_sn($prefix);
	}
	
	/**
	 * 获取交易号
	 * @author zhangchangchun@dodoca.com
	 */
    private static function get_payment_sn() {        
		$payment_sn = date('ymdHis').str_pad(mt_rand(1,99999999),6,'0',STR_PAD_LEFT);
		$order = PaymentApply::select(['id','payment_sn'])->where('payment_sn',$payment_sn)->first();
		if(!$order) {
			return $payment_sn;
		}
        return self::get_payment_sn();
    }
	
	/**
	 * 获取退款单号
	 * @author zhangchangchun@dodoca.com
	 */
	public static function get_feedback_sn() {
        $feedback_sn = date('Ymdhis').str_pad(mt_rand(1,99999),5,'0',STR_PAD_LEFT);
        $apply = OrderRefundApply::select('feedback_sn')->where(array('feedback_sn' => $feedback_sn))->first();
        if(!$apply) {
            return $feedback_sn;
        }
		return self::get_feedback_sn();
	}
	
	/**
	 * 获取核销码
	 * @author zhangchangchun@dodoca.com
	 * type 类型
	 * length 核销码长度
	 * $randtype 随即数的类型  3位数字 1=>有 0=>无
	 * 第一位：数字
	 * 第二位：小写字母
	 * 第三位：大写字母
	 */
	public static function get_hexiao_sn($type='selffetch',$length=10,$randtype='100') {
		$arr1 = array('0','1','2','3','4','5','6','7','8','9');
		$arr2 = array('a','b','c','d','e','f','g','h','i','j','k','l','m','n','o','p','q','r','s','t','u','v','w','x','y','z');
		$arr3 = array('A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z');
		$tmp1 = substr($randtype,0,1)=='1' ? $arr1 : array();
		$tmp2 = substr($randtype,1,1)=='1' ? $arr2 : array();
		$tmp3 = substr($randtype,2,1)=='1' ? $arr3 : array();
		$tmp = array_merge($tmp1,$tmp2,$tmp3);
		$tmp = $tmp ? $tmp : $arr1;
		$hexiao_sn = '';
		for($i=0; $i<$length; $i++) {
			$hexiao_sn .= $tmp[rand(0,count($tmp)-1)];
		}
		$result = '';
		switch($type) {
			case 'selffetch':
				$result = OrderSelffetch::select('hexiao_code')->where(array('hexiao_code' => $hexiao_sn))->first();
				break;
			case 'virtual':
			    $result = OrderVirtualgoods::select('hexiao_code')->where('hexiao_code','like',$hexiao_sn.'%')->first();
			    break;
			default:
				break;
		}
        if(!$result) {
            return $hexiao_sn;
        }
		return self::get_hexiao_sn($type,$length,$randtype);
	}
	
	/**
	 * 统计会员分类订单数量
	 * @author zhangchangchun@dodoca.com
	 * data = array(
	 		'merchant_id'	=>	0,	//商户id
	 		'member_id'		=>	0,	//会员id
	 	);
	 */
	public static function ordersum($data) {
		$merchant_id = isset($data['merchant_id']) ? (int)$data['merchant_id'] : 0;
		$member_id = isset($data['member_id']) ? (int)$data['member_id'] : 0;		
		if(!$merchant_id) {
			return ['errcode' => 40003, 'errmsg' => '缺少商户id'];
		}
		if(!$member_id) {
			return ['errcode' => 40004, 'errmsg' => '缺少会员id'];
		}
		$data = array(
			1	=>	0,	//待付款
			2	=>	0,	//待发货
			3	=>	0,	//待收货
			4	=>	0,	//已完成
			5	=>	0,	//退款/售后
		);
		$info = OrderInfo::select(DB::raw('count(*) as count, status'))->where(['merchant_id'=>$merchant_id,'member_id'=>$member_id])
            ->where('order_type','<>',ORDER_SALEPAY)
            ->where('order_type','<>',ORDER_KNOWLEDGE)
            ->groupBy('status')->get()->toArray();
		$data[5] = OrderInfo::where(['merchant_id'=>$merchant_id,'member_id'=>$member_id,'refund_status'=>1])->where('status','<>',ORDER_REFUND_CANCEL)
            ->where('order_type','<>',ORDER_SALEPAY)
            ->where('order_type','<>',ORDER_KNOWLEDGE)
            ->count();
		if($info) {
			foreach($info as $v) {
				switch($v['status']) {
					case ORDER_SUBMIT:	//待付款
					case ORDER_TOPAY:
						$data[1] += (int)$v['count'];
						break;
					case ORDER_TOSEND:	//待发货
						$data[2] = (int)$v['count'];
						break;
					case ORDER_SEND:		//待收货
					case ORDER_FORPICKUP:	//上门自提，待取货
						$data[3] += (int)$v['count'];
						break;
					case ORDER_SUCCESS:	//已完成
						$data[4] = (int)$v['count'];
						break;
					default:
						break;
				}
			}
		}
		return ['errcode' => 0, 'errmsg' => '', 'data' => $data];
	}
	
	/**
	 * 退款接口（退积分，退金额）
	 * @author zhangchangchun@dodoca.com
	 * data = array(
	 		'merchant_id'	=>	0,	//商户id
	 		'order_id'		=>	0,	//订单id
			'apply_type'	=>	0,	//退款类型，备注config/varconfig.php
			'refund_id'		=>	0,	//退款表order_refund表id（全单退不传该参数）
			//'refund'		=>	array(	//不传该参数，订单全退
				//'amount'	=>	0,	//退款金额
				//'credit'	=>	0,	//退还
			//),
	 	);
	 */
	public function orderrefund($data) {
		$merchant_id = isset($data['merchant_id']) ? (int)$data['merchant_id'] : 0;
		$order_id = isset($data['order_id']) ? (int)$data['order_id'] : 0;
		$apply_type = isset($data['apply_type']) ? (int)$data['apply_type'] : 0;
		$refund_id = isset($data['refund_id']) ? (int)$data['refund_id'] : 0;
		if(!$merchant_id) {
			return ['errcode' => 40003, 'errmsg' => '缺少商户id'];
		}
		if(!$order_id) {
			return ['errcode' => 40001, 'errmsg' => '缺少订单号'];
		}
		$result = OrderRefundApply::where(['merchant_id'=>$merchant_id,'order_id'=>$order_id,'order_refund_id'=>$refund_id])->first();
		if(!$result) {
			$feedback_sn = self::get_feedback_sn();
			$result = [
				'apply_type'		=>	$apply_type,
				'merchant_id'		=>	$merchant_id,
				'order_id'			=>	$order_id,
				'order_refund_id'	=>	$refund_id,
				'feedback_sn'		=>	$feedback_sn,
				'query_data'		=>	json_encode($data,JSON_UNESCAPED_UNICODE),
				'status'			=>	0,
			];
			$result_id = OrderRefundApply::insert_data($result);
			if(!$result_id) {
				return ['errcode' => 41002, 'errmsg' => '退款订单生成失败'];
			}
			$result['id'] = $result_id;
		} else {
			if($result['status']==1) {
				return ['errcode' => 0, 'errmsg' => '已退款成功', 'data' => $result];
			} else if($result['status']==2) {
				return ['errcode' => 41003, 'errmsg' => '退款失败，等待重新发起'];
			} else if($result['status']==3) {
				return ['errcode' => 0, 'errmsg' => '退款中，请稍等'];
			}
		}
		
		//发送到队列
		$job = new OrderRefundJob($result);
		$this->dispatch($job);
		return ['errcode' => 0, 'errmsg' => '退款已提交，请等待结果', 'data' => $result];
	}
	
	/**
	 * 计算运费模板
	 * @author zhangchangchun@dodoca.com
	 * data = array(
	 		'merchant_id'	=>	0,	//商户id
	 		'member_id'		=>	0,	//会员id
			'order_id'	=>	0,	//订单号
	 		'goods'	=>	[	//商品数组
				0	=>	[
					'id'			=>	1,	//商品id
					'quantity'		=>	1,	//购买数量
				],
			],
			'province'	=>	0,	//收货地址-省份
			'city'		=>	0,	//收货地址-市区
	 	);
		type	=>	1,	//验证包邮：1-验证，2-不验证
	 */
	public function getShipmentGoods($data,$type=1) {
		$merchant_id = isset($data['merchant_id']) ? (int)$data['merchant_id'] : 0;
		$member_id = isset($data['member_id']) ? (int)$data['member_id'] : 0;
		$order_id = isset($data['order_id']) ? (int)$data['order_id'] : 0;
		if(!$merchant_id) {
			return ['errcode' => 40003, 'errmsg' => '缺少商户id'];
		}
		if(!$member_id) {
			return ['errcode' => 20013, 'errmsg' => '缺少会员id'];
		}
		if(!isset($data['province']) || !$data['province'] || !isset($data['city']) || !$data['city']) {
			return array('errcode'=>20014,'errmsg'=>'收货地址不存在');
		}
		$ShipmentGoods = [];
		if($order_id) {	//通过订单取数据
			$order_goods = OrderGoods::select(['goods_id','goods_name','weight','volume','quantity','is_pinkage','postage','shipment_id','valuation_type','start_standard','start_fee','add_standard','add_fee'])->where(['order_id'=>$order_id,'member_id'=>$member_id])->get();
			if(!$order_goods) {
				return ['errcode' => 20014, 'errmsg' => '订单商品不存在'];
			}
			foreach($order_goods as $key => $ginfo) {
				if($ginfo['is_pinkage']==1 && $type==1) {
					return ['errcode' => 0, 'errmsg' => '订单包邮', 'data' => 0];
				}
				if($ginfo['shipment_id']) {
					$shipinfo = $this->getShipmentInfo([
						'merchant_id'	=>	$merchant_id,
						'shipment_id'	=>	$ginfo['shipment_id'],
						'city'			=>	$data['city'],
						'province'		=>	$data['province'],
					]);
					if($shipinfo && isset($shipinfo['errcode']) && $shipinfo['errcode']==0) {
						$shipment_data = [
							'valuation_type'	=>	isset($shipinfo['data']['valuation_type']) ? $shipinfo['data']['valuation_type'] : 0,
							'start_standard'	=>	isset($shipinfo['data']['start_standard']) ? $shipinfo['data']['start_standard'] : 0,
							'start_fee'			=>	isset($shipinfo['data']['start_fee']) ? $shipinfo['data']['start_fee'] : 0,
							'add_standard'		=>	isset($shipinfo['data']['add_standard']) ? $shipinfo['data']['add_standard'] : 0,
							'add_fee'			=>	isset($shipinfo['data']['add_fee']) ? $shipinfo['data']['add_fee'] : 0,
						];
					} else {
						return array('errcode'=>20014,'errmsg'=>(isset($shipinfo['errcode']) ? $shipinfo['errcode'] : '运费模板无效'));
					}
				}
				$ShipmentGoods[] = [
					'id'			=>	$ginfo['goods_id'],
					'title'			=>	$ginfo['goods_name'],
					'shipment_id'	=>	$ginfo['shipment_id'],
					'postage'		=>	$ginfo['postage'],
					'quantity'		=>	$ginfo['quantity'],
					'weight'		=>	$ginfo['weight'],
					'volume'		=>	$ginfo['volume'],
					'shipment_data'	=>	isset($shipment_data) ? $shipment_data : [],
				];
			}
		} else if(isset($data['goods']) && $data['goods']){	//通过商品取数据
			$memberinfo = MemberModel::get_data_by_id($member_id,$merchant_id);
			if(!$memberinfo) {
				return array('errcode'=>20014,'errmsg'=>'会员不存在');
			}
			
			$is_support_dis = 0;	//商品中是否有支持会员优惠的商品
			foreach($data['goods'] as $key => $info) {
				$goods_res = Goods::get_data_by_id($info['id'],$merchant_id);
				if(isset($goods_res['is_discount']) && $goods_res['is_discount']==1) {
					$is_support_dis = 1;
				}
			}
			
			$cardinfo = MemberCard::get_data_by_id($memberinfo['member_card_id'],$merchant_id);
			if($cardinfo && $cardinfo['is_postage_free']==1 && $is_support_dis==1) {
				return ['errcode' => 0, 'errmsg' => '订单包邮', 'data' => 0];
			}
			foreach($data['goods'] as $key => $ginfos) {
				$ginfo = Goods::get_data_by_id($ginfos['id'],$merchant_id);
				if(!$ginfo) {
					return array('errcode'=>20014,'errmsg'=>'商品不存在');
				}
				if(!isset($ginfos['quantity']) || !$ginfos['quantity']) {
					return array('errcode'=>20014,'errmsg'=>'购买数量无效');
				}
				$ShipmentGoodsinfo = [
					'id'			=>	$ginfo['id'],
					'title'			=>	$ginfo['title'],
					'shipment_id'	=>	$ginfo['shipment_id'],
					'postage'		=>	$ginfo['postage'],
					'quantity'		=>	(int)$ginfos['quantity'],
					'weight'		=>	$ginfo['weight'],
					'volume'		=>	$ginfo['volume'],
					'shipment_data'	=>	[
						'valuation_type'	=>	0,
						'start_standard'	=>	0,
						'start_fee'			=>	0,
						'add_standard'		=>	0,
						'add_fee'			=>	0,
					],
				];
				if($ShipmentGoodsinfo['shipment_id']) {
					$shipinfo = $this->getShipmentInfo([
						'merchant_id'	=>	$merchant_id,
						'shipment_id'	=>	$ShipmentGoodsinfo['shipment_id'],
						'city'			=>	$data['city'],
						'province'		=>	$data['province'],
					]);
					if($shipinfo && isset($shipinfo['errcode']) && $shipinfo['errcode']==0) {
						$ShipmentGoodsinfo['shipment_data'] = [
							'valuation_type'	=>	isset($shipinfo['data']['valuation_type']) ? $shipinfo['data']['valuation_type'] : 0,
							'start_standard'	=>	isset($shipinfo['data']['start_standard']) ? $shipinfo['data']['start_standard'] : 0,
							'start_fee'			=>	isset($shipinfo['data']['start_fee']) ? $shipinfo['data']['start_fee'] : 0,
							'add_standard'		=>	isset($shipinfo['data']['add_standard']) ? $shipinfo['data']['add_standard'] : 0,
							'add_fee'			=>	isset($shipinfo['data']['add_fee']) ? $shipinfo['data']['add_fee'] : 0,
						];
					} else {
						return array('errcode'=>20014,'errmsg'=>(isset($shipinfo['errcode']) ? $shipinfo['errcode'] : '运费模板无效'));
					}
				}
				$ShipmentGoods[] = $ShipmentGoodsinfo;
			}
		}
		if(!$ShipmentGoods) {
			return array('errcode'=>20014,'errmsg'=>'运费商品不存在');
		}
		
		$ShipmentService = new ShipmentService;
		$sptresult = $ShipmentService->getOrderShipmentFee($ShipmentGoods,$data['province'],$data['city']);
		
		if($sptresult && isset($sptresult['status']) && $sptresult['status']) {
			return ['errcode' => 0, 'errmsg' => '获取成功', 'data' => $sptresult['shipment_fee']];
		} else {
			return array('errcode'=>20014,'errmsg'=>(isset($sptresult['error']) ? $sptresult['error'] : '收货地址无效'));
		}
	}
	
	/**
	 * 获取运费模板
	 * @author zhangchangchun@dodoca.com
	 * data = array(
	 		'merchant_id'	=>	0,	//商户id
	 		'shipment_id'	=>	0,	//运费模板id
			'province'		=>	0,	//收货地址-省份
			'city'			=>	0,	//收货地址-市区
	 	);
	 */
	public function getShipmentInfo($data) {
		$merchant_id = isset($data['merchant_id']) ? (int)$data['merchant_id'] : 0;
		$shipment_id = isset($data['shipment_id']) ? (int)$data['shipment_id'] : 0;
		$province = isset($data['province']) ? (int)$data['province'] : 0;
		$city = isset($data['city']) ? (int)$data['city'] : 0;
		if(!$merchant_id) {
			return ['errcode' => 40003, 'errmsg' => '缺少商户id'];
		}
		if(!$shipment_id) {
			return ['errcode' => 20014, 'errmsg' => '缺少运费模板id'];
		}
		if(!$province || !$city) {
			return ['errcode' => 20014, 'errmsg' => '缺少城市id'];
		}
		$shipinfo = Shipment::get_data_by_id($shipment_id);
		if(!$shipinfo) {
			return ['errcode' => 20014, 'errmsg' => '运费模板id不存在'];
		}
		$ShipmentAreaInfo = ShipmentArea::leftJoin('shipment_area_region', 'shipment_area.id', '=', 'shipment_area_region.shipment_area_id')->where(['shipment_area.shipment_id'=>$shipment_id,'shipment_area_region.merchant_id'=>$merchant_id,'shipment_area.is_delete'=>1,'shipment_area_region.is_delete'=>1])->whereIn('shipment_area_region.region_id',[$province,$city])->first();
		if($ShipmentAreaInfo) {
			$shipinfo['start_standard'] = $ShipmentAreaInfo['start_standard'];
			$shipinfo['start_fee'] = $ShipmentAreaInfo['start_fee'];
			$shipinfo['add_standard'] = $ShipmentAreaInfo['add_standard'];
			$shipinfo['add_fee'] = $ShipmentAreaInfo['add_fee'];
		}
		/*if(env('APP_URL','')=='https://applet.rrdoy.com') {
			
		}*/
		return ['errcode' => 0, 'errmsg' => '获取成功', 'data' => $shipinfo];
		
	}
	
	/**
	 * 验证使用优惠权限
	 * @author zhangchangchun@dodoca.com
	 * date 2017-09-06
	 * order_type 订单类型
	 * mark_type  优惠类型
	 * data = array(
	 	'merchant_id'	=>	1,	//商户id
		'member_id'		=>	1,	//会员id
	 );
	 */
	public function get_reduction_power($order_type=0,$mark_type='',$data=[]) {
		$merchant_id = isset($data['merchant_id']) ? $data['merchant_id'] : 0;
		$member_id = isset($data['member_id']) ? $data['member_id'] : 0;
		if($order_type && $mark_type) {
			$config = config('config');
			if($config && isset($config['marketing_'.$order_type]) && in_array($mark_type,$config['marketing_'.$order_type])) {
				if($order_type==5) {	//优惠买单
					$settingInfo = MerchantSetting::get_data_by_id($merchant_id);
					if($settingInfo && (($settingInfo['salepay_member']==1 && $mark_type=='vip') || ($settingInfo['salepay_coupon']==1 && $mark_type=='coupon') || ($settingInfo['salepay_credit']==1 && $mark_type=='credit'))) {
						return true;
					}
				} else {
					return true;
				}
			}
		}
		return false;
	}
	
	/**
	 * 获取订单积分数
	 * @author zhangchangchun@dodoca.com
	 * date 2017-09-06
	 * data = [
	 		'merchant_id'	=>	1,
			'member_id'		=>	1,
			'amount'		=>	1,
		];
	 * return [
	 	'credit_amount'		=>	1,		//积分抵扣数量
		'credit_ded_amount'	=>	10.20,	//积分抵扣金额
	 ];
	 */
	public function get_credit($data) {
		$creditdata = [
			'credit_amount'		=>	0,
			'credit_ded_amount'	=>	0,
		];
		if(isset($data['merchant_id']) && isset($data['member_id']) && isset($data['amount'])) {
			$mrtinfo = MerchantSetting::get_data_by_id($data['merchant_id']);
			$mrtinfo = isset($mrtinfo['credit_rule']) ? $mrtinfo['credit_rule'] : 0;
			if($mrtinfo && $mrtinfo>0) {
				$memberinfo = MemberModel::get_data_by_id($data['member_id'],$data['merchant_id']);
				$need_credit = ceil($data['amount']*$mrtinfo);
				if($need_credit>0 && $memberinfo['credit']>0) {
					if($memberinfo['credit']>=$need_credit) {
						if($memberinfo['credit']>=$need_credit) {
							$creditdata['credit_amount'] = $need_credit;
							$creditdata['credit_ded_amount'] = sprintf('%0.2f',$data['amount']);
						} else {
							$creditdata['credit_amount'] = $memberinfo['credit'];
							$creditdata['credit_ded_amount'] = sprintf('%0.2f',(sprintf('%0.2f',$need_credit/$mrtinfo)));
						}
					} else {
						$creditdata['credit_amount'] = $memberinfo['credit'];
						$creditdata['credit_ded_amount'] = sprintf('%0.2f',(sprintf('%0.2f',$memberinfo['credit']/$mrtinfo)));
					}
				}
			}
		}
		return $creditdata;
	}
	
	/**
	 * 拆分积分抵扣金额
	 * @author zhangchangchun@dodoca.com
	 * date 2017-09-06
	 * $order_goods  订单商品
	 * $creditdata   使用积分数据
	 */
	public function split_credit_price($order_goods,$creditdata) {
		if(!$order_goods || !isset($creditdata['credit_amount']) || !isset($creditdata['credit_ded_amount'])) {
			return $order_goods;
		}
		$order_goods_ump_type = config('varconfig.order_goods_ump_ump_type');
		
		$surplus_credit_dis_count = $creditdata['credit_amount'];
		$surplus_credit_dis_amount = $creditdata['credit_ded_amount'];
		
		$credit_lv = $surplus_credit_dis_amount/$surplus_credit_dis_count;
		foreach($order_goods as $kk => $ginfo) {
			if($order_goods[$kk]['pay_price']>=$surplus_credit_dis_amount) {	//积分只够该商品
				$s_amount = $surplus_credit_dis_amount;
				$s_credit = (int)($s_amount/$credit_lv);
				if($kk+1==count($order_goods)) {	//最后一个把积分分给它
					$s_credit = $surplus_credit_dis_count;
				}
				$order_goods[$kk]['marking_only'] = [
					'goods_id'	=>	$ginfo['goods_id'],
					'spec_id'	=>	$ginfo['spec_id'],
					'ump_id'	=>	0,
					'ump_type'	=>	3,
					'amount'	=>	-$s_amount,
					'credit'	=>	$s_credit,
					'memo'		=>	$order_goods_ump_type[3].''.$s_amount.'元',
				];
				$surplus_credit_dis_count -= $s_credit;
				$surplus_credit_dis_amount -= $s_amount;
				$order_goods[$kk]['pay_price'] -= $s_amount;
				break;
			} else {
				$order_goods[$kk]['pay_price'] = 0;
				$s_amount = $ginfo['pay_price'];
				$s_credit = (int)($s_amount/$credit_lv);
				if($kk+1==count($order_goods)) {	//最后一个把积分分给它
					$s_credit = $surplus_credit_dis_count;
				}
				$order_goods[$kk]['marking_only'] = [
					'goods_id'	=>	$ginfo['goods_id'],
					'spec_id'	=>	$ginfo['spec_id'],
					'ump_id'	=>	0,
					'ump_type'	=>	3,
					'amount'	=>	-$s_amount,
					'credit'	=>	$s_credit,
					'memo'		=>	$order_goods_ump_type[3].''.$s_amount.'元',
				];
				$surplus_credit_dis_count -= $s_credit;
				$surplus_credit_dis_amount -= $s_amount;
				$order_goods[$kk]['pay_price'] -= $s_amount;
			}
		}
		return $order_goods;
	}
	
	/**
	 * 拆分优惠券抵扣金额
	 * @author zhangchangchun@dodoca.com
	 * date 2017-09-06
	 * $order_goods  订单商品
	 * $couponinfo   api返回的券数据
	 */
	public function split_coupon_price($order_goods,$couponinfo) {
		if(!$couponinfo) {
			return $order_goods;
		}
		
		$order_goods_ump_type = config('varconfig.order_goods_ump_ump_type');
		$order_goods = $couponinfo['order_goods'];		//券商品
		$coupon_amount = $couponinfo['coupon_amount'];	//券优惠总金额
		$coupon_id = $couponinfo['coupon_code_id'];		//优惠券id
		if($coupon_amount>0) {
			$amount = 0; //能使用该优惠券的商品总金额
			$eff_order_goods_ids = [];	//可使用优惠券的商品数据
			foreach($order_goods as $key => $ginfo) {
				$order_goods[$key]['coupon_discount_price'] = 0;
				if(isset($ginfo['is_can_used']) && $ginfo['is_can_used']==1) {
					$amount += $ginfo['pay_price'];
					$eff_order_goods_ids[] = $ginfo['id'];
				}
			}
			if($amount>0) {
				$lv = $coupon_amount/$amount;
				foreach($order_goods as $key => $ginfo) {
					if(isset($ginfo['is_can_used']) && $ginfo['is_can_used']==1) {
						if($couponinfo['coupon_type']==1) {	//现金券
							if($coupon_amount>=$amount) {
								$order_goods[$key]['coupon_discount_price'] = $ginfo['pay_price'];
							} else {
								//最后一个商品，剩余的券抵扣金额都给它，兼容分配不均
								if(isset($eff_order_goods_ids[count($eff_order_goods_ids)-1]) && $eff_order_goods_ids[count($eff_order_goods_ids)-1]==$ginfo['id']) {
									$order_goods[$key]['coupon_discount_price'] = $coupon_amount;
								} else {
									$order_goods[$key]['coupon_discount_price'] = (float)sprintf('%0.2f',($lv*$ginfo['pay_price']));
								}
							}
							$coupon_amount -= $order_goods[$key]['coupon_discount_price'];
						} else if($couponinfo['coupon_type']==2) {	//折扣券
							$order_goods[$key]['coupon_discount_price'] = (float)sprintf('%0.2f',($ginfo['pay_price']*(1-$coupon_amount/10)));
						}
						$order_goods[$key]['pay_price'] -= $order_goods[$key]['coupon_discount_price'];
						if($order_goods[$key]['pay_price']<0) {
							$order_goods[$key]['pay_price'] = 0;
						}
						if($order_goods[$key]['coupon_discount_price']>0) {
							$order_goods[$key]['marking_only'] = [
								'goods_id'	=>	$ginfo['goods_id'],
								'spec_id'	=>	$ginfo['spec_id'],
								'ump_id'	=>	$coupon_id,
								'ump_type'	=>	2,
								'amount'	=>	-$order_goods[$key]['coupon_discount_price'],
								'memo'		=>	$order_goods_ump_type[2].''.$order_goods[$key]['coupon_discount_price'].'元',
							];
						}
					}
				}
			}
		}
		return $order_goods;
	}

    /**
     * 无库存-结束秒杀
     * @param $id
     * @param $merchant_id
     * @author: tangkang@dodoca.com
     */
    public function stopSeckill($id, $merchant_id)
    {
		if(!$id || !$merchant_id) {
			return false;
		}
        try{
            $date = date('Y-m-d H:i:s');
            Seckill::update_data($id, $merchant_id, ['end_time' => $date]);
            AloneActivityRecode::where('alone_id', $id)->where('merchant_id',$merchant_id)->update(['finish_time'=>$date]);
        }catch (\Exception $e){
            //记录异常
            $except = [
                'activity_id'	=>	$id,
                'data_type'		=>	'alone_activity_recode',
                'content'		=>	'秒杀售罄自动结束活动失败：'.$e->getMessage(),
            ];
            CommonApi::errlog($except);
        }

    }
}

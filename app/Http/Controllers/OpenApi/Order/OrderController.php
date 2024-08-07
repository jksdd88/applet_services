<?php
/**
 * 订单控制器
 * @author zhangchangchun@dodoca.com
 * date 2017-09-06
 */
 
namespace App\Http\Controllers\OpenApi\Order;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Support\Facades\DB;
use App\Utils\CommonApi;
use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Facades\Member;
use App\Services\FightgroupService;
use App\Services\OrderPrintService;
use App\Models\OrderInfo;
use App\Models\OrderGoods;
use App\Models\OrderPackage;
use App\Models\OrderPackageItem;
use App\Models\MerchantDelivery;
use App\Models\DeliveryCompany;
use App\Models\OrderRefund;
use App\Models\OrderAddr;
use App\Models\Goods;
use App\Models\GoodsSpec;
use App\Models\Member as MemberModel;
use App\Utils\Logistics;
use App\Jobs\WeixinMsgJob;

class OrderController extends Controller
{
	use DispatchesJobs;
		
    /**
     * 订单列表
	 * @author zhangchangchun@dodoca.com
	 * date 2018-07-16
     */
    public function getList(Request $request)
    {
		$this->merchant_id = $request->user()['merchant_id'];
		$pagesize = isset($request['pagesize']) && $request['pagesize'] ? (int)$request['pagesize'] : 10;
		$page = isset($request['page']) && $request['page'] ? (int)$request['page'] : 1;
		
		$order_type = isset($request['order_type']) ? (int)$request['order_type'] : '';	//订单类型
		$order_sn = isset($request['order_sn']) ? $request['order_sn'] : '';			//订单号
		$type = isset($request['type']) ? $request['type'] : '';						//订单状态
		$created_time_start = isset($request['created_time_start']) ? $request['created_time_start'] : '';	//创建时间-开始
		$created_time_end = isset($request['created_time_end']) ? $request['created_time_end'] : '';		//创建时间-结束
		$updated_time_start = isset($request['updated_time_start']) ? $request['updated_time_start'] : '';	//更新时间-开始
		$updated_time_end = isset($request['updated_time_end']) ? $request['updated_time_end'] : '';		//更新时间-结束
		
		$offset = ($page-1)*$pagesize;
		$data = array();
		$filed = 'id,member_id,order_sn,nickname,amount,amount,goods_amount,shipment_fee,order_type,pay_type,status,remark,memo,pay_status,pay_time,shipments_time,is_finish,finished_time,created_time,updated_time,delivery_type,order_goods_type,payment_sn,trade_sn,memo,remark';
		$query = OrderInfo::where(['merchant_id'=>$this->merchant_id]);
		if($created_time_start) {
			$query->where('created_time','>=',$created_time_start);
		}
		if($created_time_end) {
			$query->where('created_time','<=',$created_time_end);
		}
		if($updated_time_start) {
			$query->where('updated_time','>=',$updated_time_start);
		}
		if($updated_time_end) {
			$query->where('updated_time','<=',$updated_time_end);
		}
		if($order_sn) {
			$query->where('order_sn','=',$order_sn);
		}
		if($order_type) {
			$query->where('order_type','=',$order_type);
		}
		$query->where('is_valid','=',1);
		switch($type) {
			case 'topay':	//待付款
				$query->whereIn('status',[ORDER_SUBMIT,ORDER_TOPAY]);
				break;
			case 'tosend':	//待发货
				$query->whereIn('status',[ORDER_TOSEND]);
				break;
			case 'send':	//待收货
				$query->whereIn('status',[ORDER_SEND,ORDER_FORPICKUP]);
				break;
			case 'success':	//已完成
				$query->where(['status'=>ORDER_SUCCESS]);
				break;
			case 'cancel':	//已关闭
				$query->whereIn('status',[ORDER_AUTO_CANCELED,ORDER_BUYERS_CANCELED,ORDER_MERCHANT_CANCEL]);
				break;
			case 'refund':	//退款中
				$query->where(['refund_status'=>1])->where('status','<>',ORDER_REFUND_CANCEL);
				break;
		}
		$query->where('order_type','<>',ORDER_SALEPAY);		//去掉优惠买单
		$query->where('order_type','<>',ORDER_KNOWLEDGE);	//去掉知识付费
		$count = $query->count();
		$list = $query->select(\DB::raw($filed))->orderBy('id','desc')->skip($offset)->take($pagesize)->get()->toArray();
		
		if($list) {
			foreach($list as $key => $info) {
				$list[$key]['member_id'] = $info['member_id']+MEMBER_CONST;
				
				//收货地址
				$list[$key]['order_addr'] = OrderAddr::select(\DB::raw('consignee,mobile,country_name,province_name,city_name,district_name,address,zipcode'))->where(['order_id'=>$info['id']])->first();
				
				//待发货商品
				$list[$key]['order_shipping_goods'] = [];
				
				//购买商品
				$list[$key]['order_goods'] = OrderGoods::select(\DB::raw('id,goods_id,spec_id,goods_name,goods_img,quantity,shipped_quantity,refund_quantity,price,pay_price,props'))->where(['order_id'=>$info['id']])->get()->toArray();
				if($list[$key]['order_goods']) {
					foreach($list[$key]['order_goods'] as $keyitem => $infoitem) {
						$list[$key]['order_goods'][$keyitem]['goods_img'] = ENV('QINIU_STATIC_DOMAIN').'/'.$infoitem['goods_img'];
						$only_sum = (int)$infoitem['quantity']-(int)$infoitem['shipped_quantity']-(int)$infoitem['refund_quantity'];
						if($only_sum>0) {
							$list[$key]['order_shipping_goods'][] = [
								'id'			=>	$infoitem['id'],
								'goods_id'		=>	$infoitem['goods_id'],
								'goods_img'		=>	$list[$key]['order_goods'][$keyitem]['goods_img'],
								'spec_id'		=>	$infoitem['spec_id'],
								'goods_name'	=>	$infoitem['goods_name'],
								'props'			=>	$infoitem['props'],
								'quantity'		=>	$only_sum,
							];
						}
					}
				}
				
				//订单退款
				$list[$key]['order_refund'] = OrderRefund::select(\DB::raw('goods_id,spec_id,refund_type,refund_quantity,amount,shipment_fee,integral,reason,status,created_time,updated_time'))->where(['order_id'=>$info['id']])->get()->toArray();
				
				//订单包裹
				$list[$key]['order_package'] = OrderPackage::select(\DB::raw('id,logis_code,logis_name,logis_no,is_no_express'))->where(['order_id'=>$info['id']])->get()->toArray();
								
				unset($list[$key]['id']);
			}
		}
		
		return Response::json(array('errcode' => '0', 'errmsg' => '请求成功', 'count' => $count, 'data' => $list));		
    }
	
	/**
	 * 订单详情
	 * @author zhangchangchun@dodoca.com
	 * date 2017-09-06
	 */
	public function getInfo($order_sn,Request $request) {
		$this->merchant_id = $request->user()['merchant_id'];
		if(!$order_sn) {
			return Response::json(array('errcode' => '40001', 'errmsg' => '缺少订单号参数'));
		}
		$data = OrderInfo::get_data_by_order_sn($order_sn,$this->merchant_id,'id,member_id,nickname,order_sn,amount,goods_amount,shipment_fee,order_type,order_goods_type,pay_type,payment_sn,trade_sn,status,delivery_type,refund_status,remark,memo,pay_status,pay_time,shipments_time,is_finish,finished_time,created_time,updated_time,appoint_distribution_date,member_name,member_mobile');
		if(!$data) {
			return Response::json(array('errcode' => '40002', 'errmsg' => '订单不存在'));
		}
		$data = $data->toArray();
		
		$data['member_id'] = $data['member_id']+MEMBER_CONST;
		
		//收货地址
		$data['order_addr'] = OrderAddr::select(\DB::raw('consignee,mobile,country_name,province_name,city_name,district_name,address,zipcode'))->where(['order_id'=>$data['id']])->first();
		
		//待发货商品
		$data['order_shipping_goods'] = [];
		
		//购买商品
		$data['order_goods'] = OrderGoods::select(\DB::raw('id,goods_id,spec_id,goods_name,goods_img,quantity,shipped_quantity,refund_quantity,price,pay_price,props'))->where(['order_id'=>$data['id']])->get()->toArray();
		$order_goods = [];
		if($data['order_goods']) {
			foreach($data['order_goods'] as $key => $info) {
				$order_goods[$info['id']] = $info;
				$data['order_goods'][$key]['goods_img'] = ENV('QINIU_STATIC_DOMAIN').'/'.$info['goods_img'];
				
				$only_sum = (int)$info['quantity']-(int)$info['shipped_quantity']-(int)$info['refund_quantity'];
				if($only_sum>0) {
					$data['order_shipping_goods'][] = [
						'id'			=>	$info['id'],
						'goods_id'		=>	$info['goods_id'],
						'goods_img'		=>	$data['order_goods'][$key]['goods_img'],
						'spec_id'		=>	$info['spec_id'],
						'goods_name'	=>	$info['goods_name'],
						'props'			=>	$info['props'],
						'quantity'		=>	$only_sum,
					];
				}
			}
		}
			
		//订单包裹
		$order_package = OrderPackage::select(\DB::raw('id,logis_code,logis_name,logis_no,is_no_express'))->where(['order_id'=>$data['id']])->get()->toArray();
		if($order_package) {
			foreach($order_package as $key => $info) {
				$order_package[$key]['goods'] = [];
				$order_package_goods =  OrderPackageItem::get_data_by_package_id($info['id'],$data['id']);
				if($order_package_goods) {
					foreach($order_package_goods as $keyitem => $infoitem) {
						$order_package[$key]['goods'][] = [
							'goods_id'	=>	$order_goods[$infoitem['order_goods_id']]['goods_id'],
							'goods_name'=>	$order_goods[$infoitem['order_goods_id']]['goods_name'],
							'goods_img'	=>	ENV('QINIU_STATIC_DOMAIN').'/'.$order_goods[$infoitem['order_goods_id']]['goods_img'],
							'spec_id'	=>	$order_goods[$infoitem['order_goods_id']]['spec_id'],
							'quantity'	=>	$infoitem['quantity'],
						];
					}
				}
			}
		}
		$data['order_package'] = $order_package;
				
		//订单退款
		$data['order_refund'] = OrderRefund::select(\DB::raw('goods_id,spec_id,refund_type,refund_quantity,amount,shipment_fee,integral,reason,status,created_time,updated_time'))->where(['order_id'=>$data['id']])->get()->toArray();
		
		unset($data['id']);
		return Response::json(array('errcode' => '0', 'errmsg' => '返回成功', 'data' => $data));
	}
	
	/**
	 * 物流公司
	 * @author zhangchangchun@dodoca.com
	 * date 2017-09-06
	 */
	public function getDelivery(Request $request) {
		$this->merchant_id = $request->user()['merchant_id'];
		
		$list = [];
		$data = MerchantDelivery::select(\DB::raw('delivery_company_id'))->where(['merchant_id'=>$this->merchant_id,'is_delete'=>1])->get()->toArray();
		if($data) {
			foreach($data as $key => $info) {
				$dcinfo = DeliveryCompany::get_data_by_id($info['delivery_company_id']);
				$list[] = [
					'name'	=>	$dcinfo['name'],
					'code'	=>	$dcinfo['code'],
				];
			}
		}
		$list[] = [
			'name'	=>	'其他物流',
			'code'	=>	'other',
		];
		
		return Response::json(array('errcode' => '0', 'errmsg' => '返回成功', 'data' => $list));
	}
	
	/**
	 * 订单发货
	 * @author zhangchangchun@dodoca.com
	 * date 2017-09-06
	 */
	public function postShipping(Request $request) {
		$this->merchant_id = $request->user()['merchant_id'];
				
        $order_sn = isset($request["order_sn"]) ? trim($request["order_sn"]) : '';		//订单号
        $logis_type = isset($request["logis_type"]) ? trim($request["logis_type"]) : '';//发货类型：1-需要物流，2-无需物流
        $logis_code = isset($request["logis_code"]) ? trim($request["logis_code"]) : '';//物流公司编码
        $logis_name = isset($request["logis_name"]) ? trim($request["logis_name"]) : '';//物流公司名称
        $logis_no = isset($request["logis_no"]) ? trim($request["logis_no"]) : '';		//快递单号
        $goodsInfo = isset($request["goodsInfo"]) ? $request["goodsInfo"] : '';			//发货商品
		if(!$order_sn) {
			return Response::json(array('errcode' => '40001', 'errmsg' => '缺少订单号参数'));
		}
		if(!in_array($logis_type,[1,2])) {
			return Response::json(array('errcode' => '40003', 'errmsg' => '发货方式不正确'));
		}
		if($logis_type==1 && (!$logis_code || !$logis_name || !$logis_no)) {
			return Response::json(array('errcode' => '40004', 'errmsg' => '物流信息缺失'));
		}
		if(!$goodsInfo) {
			return Response::json(array('errcode' => '40005', 'errmsg' => '没有发货的商品'));
		}

		$orderInfo = OrderInfo::get_data_by_order_sn($order_sn,$this->merchant_id);		
		if(!$orderInfo) {
			return Response::json(array('errcode' => '40002', 'errmsg' => '订单不存在'));
		}
		
		if($orderInfo['status']==ORDER_SEND){
            return Response::json(array('errcode' => '40011', 'errmsg' => '订单已发货'));
        }
		
        if(!in_array($orderInfo['status'],[ORDER_TOSEND,ORDER_SUBMITTED])){
            return Response::json(array('errcode' => '40006', 'errmsg' => '订单不允许发货'));
        }
		
		if(!in_array($orderInfo['order_type'],[ORDER_SHOPPING,ORDER_FIGHTGROUP,ORDER_SECKILL,ORDER_BARGAIN])) {
			return Response::json(array('errcode' => '40006', 'errmsg' => '该类订单不允许发货'));
		}
		
		if(!in_array($orderInfo['delivery_type'],[1,3])) {
			return Response::json(array('errcode' => '40006', 'errmsg' => '该配送方式不允许发货'));
		}		
		
        if($orderInfo['order_type'] == 2){//验证团购订单是否可发货
            $FightgroupService = new FightgroupService();
			$check = $FightgroupService->fightgroupJoinOrder($orderInfo['id']);
            if($check['data']['type'] == 0){
                return Response::json(array('errcode' => '40006', 'errmsg' => '该拼团订单不可发货'));
            }
        }

        $temp = array();
        foreach($goodsInfo as $k=>$v){
            $orderGoods = OrderGoods::get_data_by_id($k,$this->merchant_id);
			if(!$orderGoods || $orderGoods['order_id']!=$orderInfo['id']) {
				return Response::json(array('errcode' => '40008', 'errmsg' => '订单商品不存在'));
			}
            $refundQuantity = OrderRefund::where(array('order_id' => $orderInfo['id'], 'goods_id' => $orderGoods['goods_id'], 'package_id' => 0))
                ->whereNotIn('status', [REFUND_CANCEL, REFUND_CLOSE,REFUND_MER_CANCEL])
                ->sum('refund_quantity');
            $delivery_num = $orderGoods['quantity'] - $orderGoods['shipped_quantity'] - $refundQuantity;
            if ($delivery_num > 0) {
                $orderGoods['edit_quantity'] = $delivery_num > $v ? $v : $delivery_num;
                $temp[] = $orderGoods;
            } else {
				return Response::json(array('errcode' => '40008', 'errmsg' => '该订单没有可发货商品'));
			}
        }

        if ($temp) {
            //查找物流是否系统自带，否则是自定义
            $orderPackageData = array(
                'order_id' 			=> 	$orderInfo['id'],
                'order_sn' 			=> 	$orderInfo['order_sn'],
                'logis_no' 			=> 	$logis_no,
                'logis_name' 		=> 	$logis_name,
                'logis_code' 		=> 	$logis_code,
                'is_no_express' 	=>	$logis_type==1 ? 0 : 1,
            );

            $shippment_id = 0;
            if($logis_code!='other' && $logis_type==1) {
				$delivery_company = DeliveryCompany::where('code', $logis_code)->first();
				if(!$delivery_company) {
					return Response::json(array('errcode' => '40007', 'errmsg' => '物流公司不存在'));
				}
				$orderPackageData['logis_name'] = $delivery_company['name'];
				
				$merchant_delivery = MerchantDelivery::where(array('merchant_id' => $this->merchant_id, 'delivery_company_id' => $delivery_company['id']))->first();
                if ($merchant_delivery) {
                    $shippment_id = $delivery_company['id'];
                }
			}

            //$order_type = $orderInfo['order_type'];
            try {
                DB::transaction(function () use ($temp, $orderPackageData, $shippment_id,$orderInfo) {
                    //先更新订单包裹表数据
                    $package_id = OrderPackage::insertGetId($orderPackageData);
                    if (!is_int($package_id)) {
                        return Response::json(array('errcode' => '40010', 'errmsg' => '发货失败，请重试！'));
                    }
                    //更新订单商品包裹表数据
                    foreach ($temp as $value) {
                        OrderGoods::where(['id' => $value['id']])->increment('shipped_quantity', $value['edit_quantity']);
                        //更新对应订单商品的状态
                        $orderGoodsInfo = OrderGoods::select('id', 'shipped_quantity', 'refund_quantity', 'quantity')->where(['id' => $value['id']])->first();
                        if ($orderGoodsInfo) {
                            if ($orderGoodsInfo['quantity'] == ($orderGoodsInfo['shipped_quantity'] + $orderGoodsInfo['refund_quantity'])) {
                                OrderGoods::where(['id' => $orderGoodsInfo['id']])->update(['status' => ORDER_SEND]);
                            }
                        }
                        //更新订单包裹子表,商品分化数量大于0才写发货子表
                        if($value['edit_quantity']>0){
                            $orderPackageChildData = array(
                                'package_id'     =>	$package_id,
                                'order_id'       =>	$orderInfo['id'],
                                'order_goods_id' =>	$value['id'],
                                'shipment_id'    =>	$shippment_id,
                                'quantity'       =>	$value['edit_quantity'],
                            );
                            $package_item_id = OrderPackageItem::insertGetId($orderPackageChildData);
                            if (!is_int($package_item_id)) {
                                return Response::json(array('errcode' => 40010, 'errmsg' => '发货失败，请重试。'));
                            }
                        }
                    }
                    //统计当前订单购买的总件数
                    $order_quantity = OrderGoods::where(['order_id' => $orderInfo['id']])->sum('quantity');
                    //统计订单已发货的件数
                    $shipped_quantity = OrderGoods::where(['order_id' => $orderInfo['id']])->sum('shipped_quantity');
                    //统计已成功退款的件数 去除包裹里退的件数
                    $refund_quantity = OrderRefund::where(['order_id' => $orderInfo['id'], 'status' => REFUND_FINISHED, 'package_id' => 0])->sum('refund_quantity');
                    if ($order_quantity == ($shipped_quantity + $refund_quantity)) {
                        //更新订单状态
                        OrderInfo::select()->where(['id' => $orderInfo['id']])->update(['status' => ORDER_SEND,'shipments_time'=>date("Y-m-d H:i:s")]);
                    }
					
					//发送发货消息模板
					$job = new WeixinMsgJob(['order_id'=>$orderInfo['id'],'merchant_id'=>$orderInfo['merchant_id'],'delivery_id'=>$package_id,'type'=>'delivery']);
					$this->dispatch($job);
					
                });
            } catch (\Exception $e) {
                $result = array('errcode' => 40010, 'errmsg' => '商品发货失败'.$e->getMessage());
                return Response::json($result, 200);
            }
			
            //小票机打印
            if($orderInfo['id']){
                $orderPrint = new OrderPrintService();
                $orderPrint->printOrder($orderInfo['id'],$orderInfo['merchant_id']);
            }
			
			return Response::json(array('errcode' => 0, 'errmsg' => '商品发货成功'));
        } else {
			return Response::json(array('errcode' => '40006', 'errmsg' => '该订单有待处理的退款申请,不能发货'));
        }
	}
	
}

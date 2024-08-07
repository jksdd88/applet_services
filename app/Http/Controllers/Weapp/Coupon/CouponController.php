<?php

namespace App\Http\Controllers\Weapp\Coupon;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use App\Services\CouponService;
use App\Models\Coupon;
use App\Models\CouponCode;
use App\Models\CouponGoods;
use App\Models\OrderInfo;
use App\Models\OrderGoods;
use App\Models\Member as MemberModel;
use App\Models\Shop;
use App\Models\OrderUmp;
use App\Facades\Member;
use App\Utils\CacheKey;
use Cache;
use DB;

class CouponController extends Controller
{
    /**
     * 优惠劵列表
     *
     * @param string $offset  偏移量
     * @param string $limit 每页数量
     * @param string $member_id  会员ID
     *
     * @return \Illuminate\Http\Response
     */
    public function getLists(Request $request)
    {
		$member_id   = Member::id();
		$merchant_id = Member::merchant_id();
		$pagesize    = $request->input('pagesize', 10);
		$page        = $request->input('page', 1);
		$offset      = ($page - 1) * $pagesize;

		if(!$merchant_id){
			return ['errcode' => 99001, 'errmsg' => '商户ID不存在'];
		}

		$query = Coupon::query();
		$query->select('id', 'card_color', 'name', 'coupon_sum', 'send_num', 'content_type', 'coupon_val', 'is_condition', 'condition_val', 'status', 'memo', 'time_type', 'effect_time', 'period_time', 'dt_validity_begin', 'dt_validity_end', 'get_type', 'get_num', 'rang_goods');
		$query->where('merchant_id', $merchant_id);
		$query->where('is_close', 1);
		$query->where('is_delete', 1);
		$query->where(function ($query){
			$query->where('time_type', 0)->orWhere(function ($query){
				$query->where('time_type', 1)->where('dt_validity_end', '>', Carbon::now());
			});
		});
		$query->whereRaw('coupon_sum > send_num');

		$total = $query->count();
		$query->skip($offset)->take($pagesize);
		$query->orderBy('time_type', 'desc')->orderBy('condition_val')->orderBy('coupon_val', 'desc')->orderBy('created_time', 'desc');
		$data = $query->get();

		if($data){
			foreach ($data as $key => $row) {
				$coupon_id = $row['id'];
				$data[$key]['show_status'] = 0;

				if($row['time_type'] == 1){
					$data[$key]['dt_validity_begin'] = date('Y.m.d', strtotime($row['dt_validity_begin']));
					$data[$key]['dt_validity_end']   = date('Y.m.d', strtotime($row['dt_validity_end']));
				}

				if($row['content_type'] == 2){
					$data[$key]['coupon_val'] = floatval($row['coupon_val']);
				}

				if($member_id){
					if($row['get_type'] == 2 && $row['get_num'] > 0){
						$member_code_wheres = [
							[
								'column'   => 'merchant_id',
								'operator' => '=',
								'value'    => $merchant_id
							],
							[
								'column'   => 'coupon_id',
								'operator' => '=',
								'value'    => $coupon_id
							],
							[
								'column'   => 'member_id',
								'operator' => '=',
								'value'    => $member_id
							],
							[
								'column'   => 'is_delete',
								'operator' => '=',
								'value'    => 1
							]
						];

						$member_code_quantity = CouponCode::get_data_count($member_code_wheres);

						if($member_code_quantity >= $row['get_num']){
							$data[$key]['show_status'] = 2;
						}
					}
				}
			}
		}

		return ['errcode' => 0, 'count' => $total, 'data' => $data];
    }

    /**
     * 买家已领取的优惠劵列表(个人中心)
     *
     * @param string $offset  偏移量
     * @param string $limit 每页数量
     * @param string $member_id  会员ID
     *
     * @return \Illuminate\Http\Response
     */
    public function forMember(Request $request)
    {
    	$member_id   = Member::id();
		$merchant_id = Member::merchant_id();
		$status      = $request->input('status', 0);
		$pagesize    = $request->input('pagesize', 10);
		$page        = $request->input('page', 1);
		$offset      = ($page - 1) * $pagesize;

		if(!$merchant_id){
			return ['errcode' => 99001, 'errmsg' => '商户ID不存在'];
		}

		if(!$member_id){
			return ['errcode' => 99001, 'errmsg' => '会员ID不存在'];
		}

		$query = DB::table('coupon_code');
		$query->leftJoin('coupon', 'coupon_code.coupon_id', '=', 'coupon.id');
		$query->select('coupon_code.id', 'coupon_code.coupon_id', 'coupon_code.start_time', 'coupon_code.end_time', 'coupon_code.status', 'coupon.card_color', 'coupon.name', 'coupon.memo', 'coupon.content_type', 'coupon.coupon_val', 'coupon.is_condition', 'coupon.condition_val', 'coupon.rang_goods');
		$query->where('coupon_code.merchant_id', $merchant_id);
		$query->where('coupon_code.member_id', $member_id);
		$query->where('coupon_code.is_delete', 1);

		if($status == 0){
			$query->where('coupon_code.status', 0);
			$query->where('coupon_code.end_time', '>', date('Y-m-d H:i:s'));
		}

		if($status == 1){
			$query->where(function ($query) {
				$query->where('coupon_code.status', 1)->orWhere('coupon_code.end_time', '<', date('Y-m-d H:i:s'));
			});
		}

		$total = $query->count();
		$query->skip($offset)->take($pagesize);
		$data = $query->orderBy('coupon.condition_val', 'asc')->orderBy('coupon.content_type', 'desc')->orderBy('coupon.coupon_val', 'desc')->orderBy('coupon_code.end_time', 'asc')->get();

		if($data){
			foreach($data as $key => &$row){
				$coupon_id = $row->coupon_id;

				//判断优惠劵是否过期
                if(strtotime($row->end_time) < time()){
                    $row->is_overtime = 1;
                }else{
                    $row->is_overtime = 0;
                }

				//立即使用跳转方向
				$row->location  = 0;
				if($row->rang_goods == 1){
					$coupon_goods = CouponGoods::select('goods_id')->where('coupon_id', $coupon_id)->where('is_delete', 1)->get();
					$goods_ids = [];
					foreach($coupon_goods as $coupon_good){
						$goods_ids[] = $coupon_good->goods_id;
					}
					if($goods_ids){
						if(count($goods_ids) > 1){
							//适用多个商品，连接到商品列表
							$row->location  = 1;
						}else{
							$row->location = 2;
							$row->goods_id = $goods_ids[0];
						}
					}
				}

				//即将过期判断
				if($row->start_time < Carbon::now() && $row->end_time > Carbon::now() && (strtotime($row->end_time) - strtotime(date('Y-m-d'). '23:59:59')) / 86400 <= 3){
					$row->soon_expire = 1;
				}

				//格式化时间
				$row->start_time = date('Y.m.d', strtotime($row->start_time));
				$row->end_time   = date('Y.m.d', strtotime($row->end_time));

				if($row->content_type == 2){
					$row->coupon_val = floatval($row->coupon_val);
				}
			}
		}

		$count = $this->count();

		return ['errcode' => 0, 'count' => $total, 'valid' => $count['valid'], 'invalid' => $count['invalid'], 'data' => $data];
    }

    /**
     * 获取优惠劵详情
     *
     * @param string $merchant_id  商户ID
     * @param string $coupon_code_id  优惠劵codeID
     *
     * @return \Illuminate\Http\Response
     */
	public function details(Request $request, $coupon_id)
	{	
		$merchant_id = Member::merchant_id();

		if(!$merchant_id){
			return ['errcode' => 99001, 'errmsg' => '商户ID不存在'];
		}

		if(!$coupon_id){
			return ['errcode' => 99001, 'errmsg' => '优惠劵ID不存在'];
		}

		$data = Coupon::get_data_by_id($coupon_id, $merchant_id);

		if(!$data){
			return ['errcode' => 50001, 'errmsg' => '优惠劵不存在'];
		}

		$coupon_status = 1;

		if($data['is_delete'] != 1 || $data['is_close'] != 1){
			$coupon_status = 2;
		}

		if($data['time_type'] == 1){
			if(strtotime($data['dt_validity_end']) < time()){
				$coupon_status = 2;
			}
		}

		//立即使用跳转方向
		$data['location']  = 0;
		if($data['rang_goods'] == 1){
			$coupon_goods = CouponGoods::select('goods_id')->where('coupon_id', $coupon_id)->where('is_delete', 1)->get();
			$goods_ids = [];
			foreach($coupon_goods as $coupon_good){
				$goods_ids[] = $coupon_good->goods_id;
			}
			if($goods_ids){
				if(count($goods_ids) > 1){
					//适用多个商品，连接到商品列表
					$data['location']  = 1;
				}else{
					$data['location'] = 2;
					$data['goods_id'] = $goods_ids[0];
				}
			}
		}

		unset($data['status'], $data['created_time'], $data['updated_time']);

		$data['coupon_status']     = $coupon_status;
		$data['coupon_val']        = $data['content_type'] == 2 ? floatval($data['coupon_val']) : $data['coupon_val'];
		$data['dt_validity_begin'] = date('Y.m.d', strtotime($data['dt_validity_begin']));
		$data['dt_validity_end']   = date('Y.m.d', strtotime($data['dt_validity_end']));

		return ['errcode' => 0, 'data' => $data];
	}

    /**
     * 获取优惠劵码详情
     *
     * @param string $merchant_id  商户ID
     * @param string $coupon_code_id  优惠劵codeID
     *
     * @return \Illuminate\Http\Response
     */
	public function codeDetails(Request $request, $coupon_code_id)
	{	
		$merchant_id = Member::merchant_id();

		if(!$merchant_id){
			return ['errcode' => 99001, 'errmsg' => '商户ID不存在'];
		}

		if(!$coupon_code_id){
			return ['errcode' => 99001, 'errmsg' => '优惠劵码ID不存在'];
		}

		\Log::info('劵码详情：id->'.$coupon_code_id.'商户ID->'.$merchant_id);
		$coupon_code = CouponCode::get_data_by_id($coupon_code_id, $merchant_id);

		if(!$coupon_code || $coupon_code['is_delete'] != 1){
			return ['errcode' => 50001, 'errmsg' => '优惠劵不存在'];
		}

		$coupon_id = $coupon_code['coupon_id'];
		$coupon = Coupon::get_data_by_id($coupon_id, $merchant_id);

		$data = [
			'coupon_id'     => $coupon_id,
			'card_color'    => $coupon['card_color'],
			'card_name'     => $coupon['name'],
			'content_type'  => $coupon['content_type'],
			'coupon_val'    => $coupon['content_type'] == 2 ? floatval($coupon['coupon_val']) : $coupon['coupon_val'],
			'is_condition'  => $coupon['is_condition'],
			'condition_val' => $coupon['condition_val'],
			'memo'          => $coupon['memo'],
			'rang_goods'    => $coupon['rang_goods'],
			'code'          => $coupon_code['code'],
			'start_time'    => date('Y.m.d', strtotime($coupon_code['start_time'])),
			'end_time'      => date('Y.m.d', strtotime($coupon_code['end_time'])),
			'status'        => $coupon_code['status']
		];

		//判断是否过期
		if(strtotime($coupon_code['end_time']) < time()){
            $data['is_overtime'] = 1;
        }else{
            $data['is_overtime'] = 0;
        }

		//立即使用跳转方向
		$data['location']  = 0;
		if($coupon['rang_goods'] == 1){
			$coupon_goods = CouponGoods::select('goods_id')->where('coupon_id', $coupon_id)->where('is_delete', 1)->get();
			$goods_ids = [];
			foreach($coupon_goods as $coupon_good){
				$goods_ids[] = $coupon_good->goods_id;
			}
			if($goods_ids){
				if(count($goods_ids) > 1){
					//适用多个商品，连接到商品列表
					$data['location']  = 1;
				}else{
					$data['location'] = 2;
					$data['goods_id'] = $goods_ids[0];
				}
			}
		}

		return ['errcode' => 0, 'data' => $data];
	}

	/**
     * 当前订单可用优惠劵列表
     *
     * @param string $member_id  会员ID
     * @param string $merchant_id  商户ID
     * @param string $order_id  订单ID
     *
     * @return \Illuminate\Http\Response
     */
	public function orderUsableList(CouponService $couponService, Request $request)
	{
		$member_id   = Member::id();
		$merchant_id = Member::merchant_id();
		$order_id    = $request->input('order_id', 0);

		if(!$merchant_id){
			return ['errcode' => 99001, 'errmsg' => '商户ID不存在'];
		}

		if(!$member_id){
			return ['errcode' => 99001, 'errmsg' => '会员ID不存在'];
		}

		if(!$order_id){
			return ['errcode' => 99001, 'errmsg' => '订单ID不存在'];
		}

		$order_info = OrderInfo::get_data_by_id($order_id, $merchant_id);

        if(!$order_info){
            return ['errcode' => 20014, 'errmsg' => '订单不存在'];
        }

        $CouponCode = CouponCode::query();
		$CouponCode->select('id', 'coupon_id', 'code', 'start_time', 'end_time', 'status');
		$CouponCode->where('merchant_id', $merchant_id);
		$CouponCode->where('member_id', $member_id);
		$CouponCode->where('is_delete', 1);
		$CouponCode->where('status', 0);
		$CouponCode->where('use_time', '0000-00-00 00:00:00');
		$CouponCode->where('start_time', '<', Carbon::now());
		$CouponCode->where('end_time', '>', Carbon::now());

		$data  = $CouponCode->get()->toArray();

    	$valid = [];

		//订单实付款
		$amount = $order_info['amount'] - $order_info['shipment_fee'];
		
		//若有满减
		$ump = OrderUmp::where(['order_id' => $order_id, 'ump_type' => 7])->first();
		if($ump) {
			$amount += abs($ump['amount']);
		}

		//判断当前订单可使用的劵
		if($data){
			foreach($data as $key => $row){
				//优惠劵ID
				$coupon_id = $row['coupon_id'];

				$coupon = Coupon::get_data_by_id($coupon_id, $merchant_id);

				//满减条件
				if($coupon['is_condition'] == 1){
					//低于满减条件不可使用
					if(bcsub($amount, $coupon['condition_val'], 2) < 0){
						continue;
					}
				}

				//支持部分商品
				if($coupon['rang_goods'] == 1){
					$coupon_goods = CouponGoods::select('goods_id')
						->where(['merchant_id' => $merchant_id, 'coupon_id' => $coupon_id, 'is_delete' => 1])
						->get()->toArray();
					
					if($coupon_goods){
						$is_ok = 0;
						foreach($coupon_goods as $goods){
							$goods_id = $goods['goods_id'];
							$order_goods = OrderGoods::where(['order_id' => $order_id, 'member_id' => $member_id, 'goods_id' => $goods_id])->first();
							
							if($order_goods){
								$goods_amount = OrderGoods::select(DB::raw('SUM(price * quantity) AS goods_amount'))
									->where(['order_id' => $order_id, 'member_id' => $member_id, 'goods_id' => $goods_id])
									->value('goods_amount');

								if(bcsub($goods_amount, $coupon['condition_val'], 2) >= 0){
									$is_ok = 1; 
									break;
								}
							}
						}
						//指定商品不存在订单中,跳出外层foreach
						if($is_ok == 0) {
							continue;
						}
					}else{
						continue;
					}
				}

				if($coupon['content_type'] == 1){
					$row['content_type_str'] = '抵用券';
					if($coupon['is_condition'] == 1){
						$row['coupon_val_str'] = '满'.$coupon['condition_val'].'元减'.$coupon['coupon_val'];
					}else{
						$row['coupon_val_str'] = '立减'.$coupon['coupon_val'].'元';
					}
				}

				if($coupon['content_type'] == 2){
					$row['content_type_str'] = '折扣券';
					if($coupon['is_condition'] == 1){
						$row['coupon_val_str'] = '满'.$coupon['condition_val'].'元打'.floatval($coupon['coupon_val']).'折';
					}else{
						$row['coupon_val_str'] = floatval($coupon['coupon_val']).'折';
					}
				}

				$row['start_time']   = date('Y-m-d', strtotime($row['start_time']));
				$row['end_time']     = date('Y-m-d', strtotime($row['end_time']));
				$row['content_type'] = $coupon['content_type'];
				$row['coupon_val']   = $coupon['coupon_val'];
				$row['card_name']    = $coupon['name'];
				
				$valid[] = $row;
			}
		}

		return ['errcode' => 0, 'count' => count($valid), 'data' => $valid];
	}

    /**
     * 发放优惠劵
     *
     * @param string $member_id  会员ID
     * @param string $merchant_id  会员ID
     * @param string $coupon_id  优惠劵ID
     *
     * @return \Illuminate\Http\Response
     */
    public function giveMember(CouponService $couponService, Request $request)
    {
    	$member_id   = Member::id();
		$merchant_id = Member::merchant_id();
		$coupon_id   = $request->input('coupon_id', 0);

		$param = [
			'member_id'   => $member_id,
			'merchant_id' => $merchant_id,
			'coupon_id'   => $coupon_id
		];

		return $couponService->giveMember($param);
    }

    /**
     * 已领优惠劵统计
     *
     * @param string $member_id  会员ID
     * @param string $merchant_id  商户ID
     *
     * @return \Illuminate\Http\Response
     */
    private function count()
    {
    	$member_id   = Member::id();
		$merchant_id = Member::merchant_id();

		$column = ['valid', 'invalid'];

		foreach($column as $k => $v){
			switch ($v) {
				case 'valid':
					$CouponCode = CouponCode::query();
					$CouponCode->select('id', 'coupon_id', 'code', 'start_time', 'end_time', 'status');
					$CouponCode->where('merchant_id', $merchant_id);
					$CouponCode->where('member_id', $member_id);
					$CouponCode->where('is_delete', 1);
					$CouponCode->where('status', 0);
					$CouponCode->where('end_time', '>', date('Y-m-d H:i:s'));
					$valid = $CouponCode->count();
					break;
				case 'invalid':
					$CouponCode = CouponCode::query();
					$CouponCode->select('id', 'coupon_id', 'code', 'start_time', 'end_time', 'status');
					$CouponCode->where('merchant_id', $merchant_id);
					$CouponCode->where('member_id', $member_id);
					$CouponCode->where('is_delete', 1);
					$CouponCode->where(function ($query) {
						$query->where('status', 1)->orWhere('end_time', '<', date('Y-m-d H:i:s'));
					});
					$invalid = $CouponCode->count();
					break;
			}
		}

		$data = [
			'valid'   => $valid,
			'invalid' => $invalid
		];

		return $data;
    }
}

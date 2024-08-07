<?php

namespace App\Services;

/**
 * 优惠劵服务类
 *
 * @package default
 * @author guoqikai
 **/
use App\Models\Coupon;
use App\Models\CouponCode;
use App\Models\CouponGoods;
use App\Models\OrderInfo;
use App\Models\OrderGoods;
use App\Models\OrderUmp;
use App\Models\Goods;
use App\Models\Member;
use App\Models\Merchant;
use Carbon\Carbon;
use DB;
use Cache;
use App\Utils\CacheKey;

class CouponService
{
	/**
     * 派发优惠劵
     *
     * @param int $member_id  会员ID
     * @param int $merchant_id  商户ID
     * @param int $coupon_id  优惠劵ID
     * @param int $channel 发送渠道 1、后台派发 2、领取 3、新用户有礼
     *
     * @return \Illuminate\Http\Response
     */
	public function giveMember($param)
    {
		$member_id   = isset($param['member_id']) ? intval($param['member_id']) : 0;
		$merchant_id = isset($param['merchant_id']) ? intval($param['merchant_id']) : 0;
		$coupon_id   = isset($param['coupon_id']) ? intval($param['coupon_id']) : 0;
		$channel     = isset($param['channel']) ? intval($param['channel']) : 2;

		if(!$merchant_id){
			return ['errcode' => 99001, 'errmsg' => '商户ID不存在'];
		}

		if(!$member_id){
			return ['errcode' => 99001, 'errmsg' => '会员ID不存在'];
		}

		if(!$coupon_id){
			return ['errcode' => 99001, 'errmsg' => '优惠劵ID不存在'];
		}

		$coupon = Coupon::get_data_by_id($coupon_id, $merchant_id);

		if(!$coupon || $coupon['is_delete'] != 1){
			return ['errcode' => 50001, 'errmsg' => '优惠劵不存在'];
		}

		if($coupon['merchant_id'] != $merchant_id){
			return ['errcode' => 50008, 'errmsg' => '优惠劵不属于当前商户'];
		}

		if($coupon['is_close'] != 1){
			return ['errcode' => 50009, 'errmsg' => '优惠劵不可领取'];
		}

		if($coupon['time_type'] == 1){
			if(strtotime($coupon['dt_validity_end']) < time()){
				return ['errcode' => 50004, 'errmsg' => '优惠券已过期'];
			}
		}

		if(!empty($coupon['vipcard'])){
			$vipcards = unserialize($coupon['vipcard']);

			$member = Member::get_data_by_id($member_id, $merchant_id);
			$member_card_id = $member['member_card_id'];

			if(!in_array($member_card_id, $vipcards)){
				return ['errcode' => 50012, 'errmsg' => '您的会员等级未达到领取门槛'];
			}
		}

		$key = CacheKey::get_coupon_stock_key($coupon_id, $merchant_id);

		if(!Cache::has($key)){
			$sent_quantity_wheres = [
				[
					'column'   => 'merchant_id',
					'operator' => '=',
					'value'    => $merchant_id,
				],
				[
					'column'   => 'coupon_id',
					'operator' => '=',
					'value'    => $coupon_id,
				],
				[
					'column'   => 'is_delete',
					'operator' => '=',
					'value'    => 1,
				],
			];
			//获取已派发数量
			$sent_quantity = CouponCode::get_data_count($sent_quantity_wheres);
			$stock = $coupon['coupon_sum'] - $sent_quantity;
			Cache::put($key, $stock, 60);
		}
		
		$coupon_stock = Cache::get($key);

		if($coupon_stock > 0){
			//先预减库存
			if(Cache::decrement($key) >= 0){
				$member_quantity = 0;
				//渠道为后台发放时不限制每人领取张数
				if($channel != 1){
					//每人领取张数限制
					if($coupon['get_type'] == 2){
						$member_quantity_wheres = [
							[
								'column'   => 'merchant_id',
								'operator' => '=',
								'value'    => $merchant_id,
							],
							[
								'column'   => 'member_id',
								'operator' => '=',
								'value'    => $member_id,
							],
							[
								'column'   => 'coupon_id',
								'operator' => '=',
								'value'    => $coupon_id,
							],
							[
								'column'   => 'is_delete',
								'operator' => '=',
								'value'    => 1,
							],
						];
						//买家已领取数量
						$member_quantity = CouponCode::get_data_count($member_quantity_wheres);
						if($coupon['get_num'] <= $member_quantity){
							//领取不成功退还库存
							Cache::increment($key);
							return ['errcode' => 50010, 'errmsg' => '领取数量已到上限'];
						}
					}
				}

				//优惠劵使用有效期 0->指定时间 1->固定范围
				if($coupon['time_type'] == 1){
					$start_time = $coupon['dt_validity_begin'];
					$end_time   = $coupon['dt_validity_end'];
				}else{
					//领取后几天生效
					$effect_time = $coupon['effect_time'];
					//有效天数
					$period_time = $coupon['period_time'];

					if($effect_time > 0){
						$start_time = Carbon::now()->addDays($effect_time)->toDateString();
					}else{
						$start_time = Carbon::now()->toDateString();
					}

					$valid_days = $effect_time + $period_time;
					if($valid_days > 0){
						$end_time   = Carbon::now()->addDays($valid_days)->toDateString().' 23:59:59';
					}else{
						$end_time   = Carbon::now()->toDateString().' 23:59:59';
					}
				}

				//写入一条领取记录
				$data = [
					'merchant_id' => $merchant_id,
					'member_id'   => $member_id,
					'coupon_id'   => $coupon_id,
					'code'        => str_random(),
					'get_time'    => Carbon::now(),
					'get_type'    => $channel,
					'start_time'  => $start_time,
					'end_time'    => $end_time,
					'is_delete'   => 1
				];
				
				if($coupon_code_id = CouponCode::insert_data($data)){
					//记录发放数量
					Coupon::where('id', $coupon_id)->increment('send_num');

					//判断用户本次领取后是否已达上线
					$is_continue = 0;
					if($coupon['get_type'] == 2){
						if($coupon['get_num'] <= ($member_quantity + 1)){
							$is_continue = 1;
						}
					}

					$result = ['code' => $data['code'], 'coupon_code_id' => $coupon_code_id, 'is_continue' => $is_continue];
					return ['errcode' => 0, 'errmsg' => '领取成功', 'data' => $result];
				}else{
					//领取不成功退还库存
					Cache::increment($key);
					return ['errcode' => 50011, 'errmsg' => '领取失败'];
				}
			}
			
		}

		return ['errcode' => 50007, 'errmsg' => '券已经被抢光了'];
    }
	/**
     * 会员优惠劵统计
     *
     * @param string $merchant_id  商户ID
     * @param string $member_id  会员ID
     * @param string $status  0、可用的 1、已使用的或已过期的
     *
     * @return \Illuminate\Http\Response
     */
    public function forMemberCount($param)
    {
		$merchant_id = isset($param['merchant_id']) ? intval($param['merchant_id']) : 0;
		$member_id   = isset($param['member_id']) ? intval($param['member_id']) : 0;
		$status      = isset($param['status']) ? intval($param['status']) : 0;

		if(!$merchant_id){
			return ['errcode' => 99001, 'errmsg' => '商户ID不存在'];
		}

		if(!$member_id){
			return ['errcode' => 99001, 'errmsg' => '会员ID不存在'];
		}

		$CouponCode = CouponCode::query();
		$CouponCode->select('id', 'coupon_id', 'code', 'start_time', 'end_time', 'status');
		$CouponCode->where('merchant_id', $merchant_id);
		$CouponCode->where('member_id', $member_id);
		$CouponCode->where('is_delete', 1);

		if($status == 0){
			$CouponCode->where('status', 0);
			$CouponCode->where('end_time', '>', date('Y-m-d H:i:s'));
		}

		if($status == 1){
			$CouponCode->where(function ($query) {
				$query->where('status', 1)->orWhere('end_time', '<', date('Y-m-d H:i:s'));
			});
		}

		return $CouponCode->count();
    }
	
	/**
     * 退还优惠劵
     *
     * @param string $merchant_id  商户ID
     * @param string $member_id 买家ID
     * @param string $coupon_code_id  优惠劵码ID
     *
     * @return \Illuminate\Http\Response
     */
    public function returned($param)
    {
    	$merchant_id    = isset($param['merchant_id']) ? intval($param['merchant_id']) : 0;
		$member_id      = isset($param['member_id']) ? intval($param['member_id']) : 0;
		$coupon_code_id = isset($param['coupon_code_id']) ? intval($param['coupon_code_id']) : 0;

		if(!$merchant_id){
			return ['errcode' => 99001, 'errmsg' => '商户ID不存在'];
		}

		if(!$member_id){
			return ['errcode' => 99001, 'errmsg' => '会员ID不存在'];
		}

		if(!$coupon_code_id){
			return ['errcode' => 99001, 'errmsg' => '优惠劵码ID不存在'];
		}

		$coupon_code = CouponCode::get_data_by_id($coupon_code_id, $merchant_id);

		if(!$coupon_code){
			return ['errcode' => 50001, 'errmsg' => '优惠劵不存在'];
		}

		$data = [
			'use_time' 		=> '0000-00-00 00:00:00',
			'status'   		=> 0,
			'use_member_id'	=> 0,
		];

		if(CouponCode::update_data($coupon_code_id, $merchant_id, $data)){
			return ['errcode' => 0, 'errmsg' => '成功退还优惠劵'];
		}

		return ['errcode' => 50014, 'errmsg' => '退还优惠劵失败'];
    }
	
	/**
     * 获取优惠劵折扣
     *
     * @param string $merchant_id  商户ID
     * @param string $member_id 买家ID
     * @param string $coupon_code_id  优惠劵码ID
     * @param array  $order_goods  商品数据
     * 
     * @return []
     */
    public function getDiscount($param)
    {
		$merchant_id    = isset($param['merchant_id']) ? intval($param['merchant_id']) : 0;
		$member_id      = isset($param['member_id']) ? intval($param['member_id']) : 0;
		$coupon_code_id = isset($param['coupon_code_id']) ? intval($param['coupon_code_id']) : 0;
		$order_goods    = isset($param['order_goods']) ? $param['order_goods'] : [];
		
		if(!$merchant_id){
			return ['errcode' => 99001, 'errmsg' => '商户ID不存在'];
		}
		
		if(!$member_id){
			return ['errcode' => 99001, 'errmsg' => '会员ID不存在'];
		}
		
		if(!$coupon_code_id){
			return ['errcode' => 99001, 'errmsg' => '优惠劵码ID不存在'];
		}
		
		if(!$order_goods){
			return ['errcode' => 99001, 'errmsg' => '商品数据不存在'];
		}
		
		//实付总金额
		$amount = 0;
		foreach($order_goods as $key => $goodinfo) {
			$amount += $goodinfo['pay_price'];
			$order_goods[$key]['is_can_used'] = 1;
			if(!isset($goodinfo['id'])) {
				$order_goods[$key]['id'] = $key+1;
			}
		}
		
		$coupon_code = CouponCode::get_data_by_id($coupon_code_id, $merchant_id);
		if(!$coupon_code || $coupon_code['is_delete'] != 1){
			return ['errcode' => 50001, 'errmsg' => '优惠劵不存在'];
		}
		
		if($coupon_code['status'] == 1 || $coupon_code['use_time'] != '0000-00-00 00:00:00'){
			return ['errcode' => 50002, 'errmsg' => '优惠劵已被使用'];
		}
		
		if(strtotime($coupon_code['start_time']) > strtotime(date('Y-m-d H:i:s'))){
			return ['errcode' => 50003, 'errmsg' => '优惠劵未生效'];
		}
		
		if(strtotime($coupon_code['end_time']) < strtotime(date('Y-m-d H:i:s'))){
			return ['errcode' => 50004, 'errmsg' => '优惠劵已过期'];
		}
		
		$coupon_id = $coupon_code['coupon_id'];
		$coupon = Coupon::get_data_by_id($coupon_id, $merchant_id);

		//劵类型 1->现金券  2->折扣券
		$content_type = $coupon['content_type'];
		//抵扣金额
		$coupon_val   = $coupon['coupon_val'];

		if($coupon['is_condition'] == 1){
			if(bcsub($amount, $coupon['condition_val'], 2) < 0){
				return ['errcode' => 50005, 'errmsg' => '订单金额未达到满减条件'];
			}
		}
		
		//指定商品才可使用
		if($coupon['rang_goods'] == 1){
			$valid_amount = 0;	//优惠券抵扣的有效金额
			foreach($order_goods as $key => $goodinfo) {
				$coupon_goods = CouponGoods::get_data($merchant_id, $coupon_id, $goodinfo['goods_id']);
				if($coupon_goods){
					$valid_amount += $goodinfo['pay_price'];
				} else {
					$order_goods[$key]['is_can_used'] = 0;
				}
			}
			if(bcsub($valid_amount, $coupon['condition_val'], 2) >= 0){
				$data = [
					'coupon_id'		=>	$coupon_id,
					'coupon_code_id'=>	$coupon_code_id,
					'coupon_type'   => 	$content_type,
					'coupon_amount' =>	$coupon_val,
					'rang_goods'    => 	1,	//指定商品
					'order_goods'   => 	$order_goods,
					'couponinfo'	=>	$coupon,
				];
				return ['errcode' => 0, 'data' => $data];				
			}else{
				return ['errcode' => 50006, 'errmsg' => '此订单无可用优惠劵'];
			}
		}

		$data = [
			'coupon_id'      =>	$coupon_id,
			'coupon_code_id' =>	$coupon_code_id,
			'coupon_type'    => $content_type,
			'coupon_amount'  => $coupon_val,
			'rang_goods'     => 0,	//任何商品
			'order_goods'    => $order_goods,
			'couponinfo'     =>	$coupon,
		];
		return ['errcode' => 0, 'data' => $data];
    }
	
	/**
     * 使用优惠劵
     *
     * @param string $merchant_id  		商户ID
     * @param string $member_id 		买家ID
     * @param string $coupon_code_id  	优惠劵码ID
     * @param string $use_member_id  	使用者ID
     *
     * @return \Illuminate\Http\Response
     */
    public function useCoupon($param)
    {
		$merchant_id    = isset($param['merchant_id']) ? intval($param['merchant_id']) : 0;
		$member_id      = isset($param['member_id']) ? intval($param['member_id']) : 0;
		$coupon_code_id = isset($param['coupon_code_id']) ? intval($param['coupon_code_id']) : 0;
		$use_member_id  = isset($param['use_member_id']) ? intval($param['use_member_id']) : 0;

		if(!$merchant_id){
			return ['errcode' => 99001, 'errmsg' => '商户ID不存在'];
		}

		if(!$member_id){
			return ['errcode' => 99001, 'errmsg' => '会员ID不存在'];
		}

		if(!$coupon_code_id){
			return ['errcode' => 99001, 'errmsg' => '优惠劵码ID不存在'];
		}
				
		$coupon_code = CouponCode::get_data_by_id($coupon_code_id, $merchant_id);

		if(!$coupon_code || $coupon_code['is_delete'] != 1){
			return ['errcode' => 50001, 'errmsg' => '优惠劵不存在'];
		}
		
		if($coupon_code['status'] == 1 || $coupon_code['use_time'] != '0000-00-00 00:00:00'){
			return ['errcode' => 50002, 'errmsg' => '优惠劵已被使用'];
		}
		
		if(strtotime($coupon_code['start_time']) > strtotime(date('Y-m-d H:i:s'))){
			return ['errcode' => 50003, 'errmsg' => '优惠劵未生效'];
		}
		
		if(strtotime($coupon_code['end_time']) < strtotime(date('Y-m-d H:i:s'))){
			return ['errcode' => 50004, 'errmsg' => '优惠劵已过期'];
		}
		
		//$coupon_id = $coupon_code['coupon_id'];
		//$coupon = Coupon::get_data_by_id($coupon_id, $merchant_id);
		
		$usedata = [
			'status'		=>	1,
			'use_time'		=>	date("Y-m-d H:i:s"),
			'use_member_id'	=>	$use_member_id,
		];
		$result = CouponCode::update_data($coupon_code_id, $merchant_id, $usedata);
		if($result) {
			return ['errcode' => 0, 'errmsg' => '使用成功'];
		} else {
			return ['errcode' => 50004, 'errmsg' => '使用失败'];
		}
    }

    public function checkCouponStatus($data)
    {
    	$status = '';

    	if($data['time_type'] == 0 && $data['coupon_sum'] > $data['send_num']){
            $status = 1;
        }
        if($data['time_type'] == 0 && $data['coupon_sum'] == $data['send_num']){
            $status = 2;
        }
        if($data['time_type'] == 1 && $data['dt_validity_begin'] > Carbon::now() && $data['coupon_sum'] > $data['send_num']){
            $status = 0;
        }
        if($data['time_type'] == 1 && $data['dt_validity_begin'] > Carbon::now() && $data['coupon_sum'] == $data['send_num']){
            $status = 2;
        }
        if($data['time_type'] == 1 && $data['dt_validity_begin'] < Carbon::now() && $data['dt_validity_end'] > Carbon::now() && $data['coupon_sum'] > $data['send_num']){
            $status = 1;
        }
        if($data['time_type'] == 1 && $data['dt_validity_begin'] < Carbon::now() && $data['dt_validity_end'] > Carbon::now() && $data['coupon_sum'] == $data['send_num']){
            $status = 2;
        }
        if($data['time_type'] == 1 && $data['dt_validity_end'] < Carbon::now()){
            $status = 2;
        }

        return $status;
    }
	
} // END class 

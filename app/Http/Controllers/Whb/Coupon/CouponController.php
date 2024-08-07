<?php

namespace App\Http\Controllers\Whb\Coupon;

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
use App\Models\Merchant;
use App\Models\OrderUmp;
use App\Facades\Member;
use App\Utils\CacheKey;
use Cache;
use DB;
use Illuminate\Support\Facades\Response;

class CouponController extends Controller
{
    protected $params;//参数

    public function __construct(Request $request){
        $this->params = $request->all();
    }

    /**
     * 优惠劵列表
     *
     * @param int $merchant_id  商户ID
     * @param string $keyword  优惠劵名称
     *
     * @return \Illuminate\Http\Response
     */
    public function getLists()
    {
		$merchant_id = isset($this->params["merchant_id"]) && !empty($this->params["merchant_id"]) ? $this->params["merchant_id"] : 0;
		$keyword     = isset($this->params["keyword"]) && !empty($this->params["keyword"]) ? $this->params["keyword"] : '';

		if(!$merchant_id){
			$rt['errcode'] = 99001;
			$rt['errmsg']  = '商户ID不存在';
            return Response::json($rt);
		}

		$query = Coupon::query();
		$query->select('id', 'card_color', 'name', 'coupon_sum', 'send_num', 'content_type', 'coupon_val', 'is_condition', 'condition_val', 'status', 'memo', 'time_type', 'effect_time', 'period_time', 'dt_validity_begin', 'dt_validity_end', 'get_type', 'get_num', 'rang_goods');
		$query->where('merchant_id', $merchant_id);
		$query->where('is_close', 1);
		$query->where('is_delete', 1);
		// 根据优惠券名称搜索
        if ($keyword) {
            $query->where('name', 'like', $keyword);
        }

		$query->where(function ($query){
			$query->where('time_type', 0)->orWhere(function ($query){
                $query->where('time_type', 1)->where('dt_validity_begin', '<', Carbon::now())->where('dt_validity_end', '>', Carbon::now());
			});
		});
		$query->whereRaw('coupon_sum > send_num');

		$total = $query->count();
		$query->orderBy('time_type', 'desc')->orderBy('condition_val')->orderBy('coupon_val', 'desc')->orderBy('created_time', 'desc');
		$data = $query->get();

		if($data){
			foreach ($data as $key => $row) {
				$data[$key]['dt_validity_begin'] = date('Y.m.d', strtotime($row['dt_validity_begin']));
				$data[$key]['dt_validity_end']   = date('Y.m.d', strtotime($row['dt_validity_end']));

				if($row['content_type'] == 2){
					$data[$key]['coupon_val'] = floatval($row['coupon_val']);
				}
			}
		}

		$rt['errcode'] = 0;
		$rt['errmsg']  = '优惠券获取成功';
		$rt['count']   = $total;
		$rt['data']    = $data;
        return Response::json($rt);
    }

    /**
     * 买家已领取的优惠劵列表(个人中心)
     *
     * @return \Illuminate\Http\Response
     */
    public function forMember()
    {
        $member_id   = isset($this->params["member_id"]) && !empty($this->params["member_id"]) ? $this->params["member_id"] : 0;
        $merchant_id = isset($this->params["merchant_id"]) && !empty($this->params["merchant_id"]) ? $this->params["merchant_id"] : 0;
        $status = isset($this->params["status"]) && !empty($this->params["status"]) ? $this->params["status"] : 0;

        if(!$merchant_id){
            $rt['errcode']=99001;
            $rt['errmsg']='商户ID不存在';
            return Response::json($rt);
        }

        if(!$member_id){
            $rt['errcode']=99001;
            $rt['errmsg']='会员ID不存在';
            return Response::json($rt);
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

		$total = $CouponCode->count();
		$data  = $CouponCode->get()->toArray();

		if($data){
			foreach($data as $key => $row){
				$coupon_id = $row['coupon_id'];

                if(strtotime($row['end_time']) < time()){
                    $row['is_overtime'] = 1;
                }else{
                    $row['is_overtime'] = 0;
                }

				$row['start_time'] = date('Y.m.d', strtotime($row['start_time']));
				$row['end_time']   = date('Y.m.d', strtotime($row['end_time']));

				$coupon = Coupon::get_data_by_id($coupon_id, $merchant_id);

                if($coupon['is_delete'] == -1 || $coupon['status'] == 2){
                    unset($data[$key]);
                    continue;
                }

                $shop_name = Shop::where('merchant_id', $coupon['merchant_id'])->first();

				$coupon_data = [
                    'shop_name'     => $shop_name['name'],
                    'card_color'    => $coupon['card_color'],
					'card_name'     => $coupon['name'],
					'card_memo'     => $coupon['memo'],
					'content_type'  => $coupon['content_type'],
					'coupon_val'    => $coupon['content_type'] == 2 ? floatval($coupon['coupon_val']) : $coupon['coupon_val'],
					'is_condition'  => $coupon['is_condition'],
					'condition_val' => $coupon['condition_val']
				];

				$data[$key] = array_merge($row, $coupon_data);
			}
		}

		$rt['errcode'] = 0;
		$rt['errmsg']  = '会员优惠券获取成功';
		$rt['count']   = $total;
		$rt['data']    = $data;
        return Response::json($rt);
    }

    /**
     * 获取优惠劵详情
     *
     * @param string $coupon_id  优惠劵ID
     *
     * @return \Illuminate\Http\Response
     */
	public function couponDetails()
	{
        $coupon_id   = isset($this->params["coupon_id"]) && !empty($this->params["coupon_id"]) ? $this->params["coupon_id"] : 0;

        if(!$coupon_id){
			$rt['errcode'] = 99001;
			$rt['errmsg']  = '优惠劵ID不存在';
            return Response::json($rt);
		}

        $coupon = Coupon::get_one_data($coupon_id);

        if($coupon['is_delete'] == -1 || $coupon['status'] == 2){
			$rt['errcode'] = 99001;
			$rt['errmsg']  = '优惠劵无效';
            return Response::json($rt);
        }

		$shop_name = Shop::where('merchant_id', $coupon['merchant_id'])->first();
        $merchant = Merchant::get_data_by_id($coupon['merchant_id']);

		$data = [
            'shop_name'         => $shop_name['name'],
            'merchant_name'     => $merchant['company'],
            'kefu_mobile'       => $shop_name['kefu_mobile'],
            'id'                => $coupon['id'],
            'merchant_id'       => $coupon['merchant_id'],
            'card_color'        => $coupon['card_color'],
            'wxcard_id'         => $coupon['wxcard_id'],
            'card_name'         => $coupon['name'],
            'coupon_sum'        => $coupon['coupon_sum'],
            'send_num'          => $coupon['send_num'], 
            'content_type'      => $coupon['content_type'],
            'coupon_val'        => $coupon['content_type'] == 2 ? floatval($coupon['coupon_val']) : $coupon['coupon_val'],
            'is_condition'      => $coupon['is_condition'],
            'condition_val'     => $coupon['condition_val'],
            'memo'              => $coupon['memo'],
            'status'            => $coupon['status'],
            'is_delete'         => $coupon['is_delete'],
            'time_type'         => $coupon['time_type'],
            'dt_validity_begin' => $coupon['dt_validity_begin'],
            'dt_validity_end'   => $coupon['dt_validity_end'],
            'effect_time'       => $coupon['effect_time'],
            'period_time'       => $coupon['period_time']
		];

        if(!empty($coupon['qrcode'])){
            $data['qrcode'] = env('QINIU_STATIC_DOMAIN').'/'.ltrim($coupon['qrcode'], '/');
        }

		$rt['errcode'] = 0;
		$rt['errmsg']  = '优惠券获取成功';
		$rt['data']    = $data;
        return Response::json($rt);
	}

    /**
     * 获取优惠劵码详情
     *
     * @param string $coupon_code_id  优惠劵码ID
     *
     * @return \Illuminate\Http\Response
     */
    public function couponCodeDetails()
    {
        $coupon_code_id = isset($this->params["coupon_code_id"]) && !empty($this->params["coupon_code_id"]) ? $this->params["coupon_code_id"] : 0;
        $merchant_id = isset($this->params["merchant_id"]) && !empty($this->params["merchant_id"]) ? $this->params["merchant_id"] : 0;

        if(!$coupon_code_id){
			$rt['errcode'] = 99001;
			$rt['errmsg']  = '优惠劵码ID不存在';
            return Response::json($rt);
        }

        if(!$merchant_id){
			$rt['errcode'] = 99001;
			$rt['errmsg']  = '商户ID不存在';
            return Response::json($rt);
        }

        $couponCode = CouponCode::get_data_by_id($coupon_code_id,$merchant_id)->toArray();
        $coupon = Coupon::get_one_data($couponCode['coupon_id']);

        if($coupon['is_delete'] == -1 || $coupon['status'] == 2){
			$rt['errcode'] = 99001;
			$rt['errmsg']  = '优惠劵无效';
            return Response::json($rt);
        }

        $shop_name = Shop::where('merchant_id', $coupon['merchant_id'])->first();

        $data = [
			'shop_name'  => $shop_name['name'],
			'card_color' => $coupon['card_color'],
			'card_name'  => $coupon['name'],
			'memo'       => $coupon['memo'],
        ];

        $data = array_merge($data,$couponCode);

		$rt['errcode'] = 0;
		$rt['errmsg']  = '优惠券码获取成功';
		$rt['data']    = $data;
        return Response::json($rt);
    }

    /**
     * 发放优惠劵
     * @return \Illuminate\Http\Response
     */
    public function giveMemberCoupon(CouponService $CouponService)
    {
        $member_id   = isset($this->params["member_id"]) && !empty($this->params["member_id"]) ? $this->params["member_id"] : 0;
		$merchant_id = isset($this->params["merchant_id"]) && !empty($this->params["merchant_id"]) ? $this->params["merchant_id"] : 0;
        $coupon_id   = isset($this->params["coupon_id"]) && !empty($this->params["coupon_id"]) ? $this->params["coupon_id"] : 0;

        $param = [
			'member_id'   => $member_id,
			'merchant_id' => $merchant_id,
			'coupon_id'   => $coupon_id
		];

		return $CouponService->giveMember($param);
    }

    /**
     * 优惠券库存统计
     *
     * @param string $coupon_id  优惠券ID
     * @param string $merchant_id  商户ID
     * @param string $coupon_sum  发放总数
     *
     * @return \Illuminate\Http\Response
     */
    private function couponStock($coupon_id,$merchant_id,$coupon_sum){
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
            $stock = $coupon_sum - $sent_quantity;
            Cache::put($key, $stock, 60);
        }

        $coupon_stock = Cache::get($key);
        return $coupon_stock;
    }
}

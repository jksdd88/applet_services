<?php

namespace App\Http\Controllers\Weapp\Seckill;

use App\Models\Goods;
use App\Models\GoodsSpec;
use App\Models\Seckill;
use App\Models\SeckillInitiate;
use App\Services\BuyService;
use App\Services\GoodsService;
use App\Utils\CommonApi;
use Carbon\Carbon;
use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Facades\Member;
use App\Models\Member as MemberModel;

class SeckillController extends Controller
{
    public function __construct()
    {
        $this->merchant_id = Member::merchant_id();
        $this->member_id = Member::id();
        $this->config_err = config('err');

    }

    /**
     * 秒杀订单提交
     * @param Request $request
     * @param BuyService $buyService
     * @return array
     * @author: tangkang@dodoca.com
     */
    public function postSeckillOrder(Request $request, BuyService $buyService)
    {
        $goods_spec_id = $request->get('goods_spec_id', 0);
        $goods_id = $request->get('goods_id', 0);
        $seckill_id = $request->get('seckill_id', 0);
        $quantity = $request->get('quantity', 1);
        $source = $request->get('source', 0);
        $source_id = $request->get('source_id', 1);
        $seckill_res = Seckill::get_data_by_id($seckill_id, $this->merchant_id);
        if (empty($seckill_res)) return $this->config_err['70001'];//秒杀活动不存在
        if ($seckill_res->end_time < date('Y-m-d H:i:s')) return $this->config_err['70003'];//秒杀已结束
        if ($seckill_res->start_time > date('Y-m-d H:i:s')) return $this->config_err['70002'];//秒杀未开始
        if (empty($goods_id)) return $this->config_err['80001'];//缺少商品id
        if (empty($seckill_id)) return $this->config_err['70004'];//缺少秒杀活动id
        if ($seckill_res->goods_id != $goods_id) return $this->config_err['70001'];//秒杀活动不存在

        $seckill_set_key = 'seckillOrderSet' . $seckill_id . $this->member_id;
        if (Cache::has($seckill_set_key)) {
            return ['errcode' => 1, 'errmsg' => '请勿重复参与'];
        }
        $carbon = new Carbon();
        Cache::put($seckill_set_key, '1', $carbon->second(-10));

        //处理订单
        $goods = Goods::get_data_by_id($goods_id, $this->merchant_id);
        if(empty($goods)) return $this->config_err['80004'];//商品不存在
        if ($goods->is_sku == 1) {//多规格商品
            if (empty($goods_spec_id)) return $this->config_err['80002'];//缺少商品规格id
            $goods_spec_res = GoodsSpec::get_data_by_id($goods_spec_id, $this->merchant_id);
            if (empty($goods_spec_res)) return ['errcode'=>1,'errmsg'=>'获取规格信息失败'];//商品不存在
        }
        $order_param = [
            'merchant_id' => $this->merchant_id,  //商户id
            'member_id' => $this->member_id,  //会员id
            'order_type' => ORDER_SECKILL,  //订单类型---预约下单
            'source' => $source,  //订单来源
            'source_id' => $source_id,  //订单来源id
            'goods' => [  //订单商品
                0 => [
                    'goods_id' => $goods_id,  //商品id
                    'spec_id' => $goods_spec_id,  //商品规格id
                    'sum' => $quantity,  //购买数量
                    'pay_price' => number_format($seckill_res->price * $quantity, 2, '.',''),  //购买价格(多个商品总价)
                    'ump_type' => 6,  //优惠类型（config/varconfig.php -> order_ump_ump_type）,没有为空
                    'ump_id' => $seckill_id,  //优惠活动id
                ],
            ],
        ];
        DB::beginTransaction();
        try {
            //调大拿接口
            $order_res = $buyService->createOrder($order_param);
            if ($order_res['errcode'] > 0) return $order_res;
            $member_model = MemberModel::get_data_by_id($this->member_id, $this->merchant_id);
            if (empty($member_model)) return $this->config_err['10004'];
            $seckill_initiate_data = [
                'merchant_id' => $this->merchant_id,
                'member_id' => $this->member_id,
                'seckill_id' => $seckill_id,
                'nickname' => $member_model->name,
                'avatar' => $member_model->avatar,
                'order_id' => $order_res['data']['order_id'],
                'order_sn' => $order_res['data']['order_sn'],
                'trade_id' => 0,
                'pay_state' => 0,
            ];
            SeckillInitiate::create($seckill_initiate_data);
//            Seckill::update_data($seckill_id, $this->merchant_id, ['cunpaid' => intval($seckill_res['cunpaid']) + 1]);//未付款订单数量 下单加付款减(暂时不要)
            DB::commit();
            $this->config_err['0']['data'] = ['order_id' => $order_res['data']['order_id'], 'order_sn' => $order_res['data']['order_sn']];
            return $this->config_err['0'];
        } catch (\Exception $e) {
            DB::rollBack();
            //记录异常
            $except = [
                'activity_id' => $seckill_id,
                'data_type' => 'seckill_initiate',
                'content' => '秒杀订单创建失败：' . $e->getMessage(),
            ];
            CommonApi::errlog($except);
//            if (app()->isLocal()) $this->config_err['40059']['errmsg'] .= $e->getMessage();
            return $this->config_err['40059'];
        }

    }

}

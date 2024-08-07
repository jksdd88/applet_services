<?php
/**
 * Created by PhpStorm.
 * User: tang
 * Date: 2017-09-05
 * Time: 14:02
 */
namespace App\Services;

use App\Utils\CommonApi;
use Illuminate\Http\Request;
use App\Models\Seckill;
use App\Models\Goods;
use App\Models\OrderGoods;
use App\Models\AloneActivityRecode;
use App\Models\SeckillInitiate;
use App\Models\GoodsSpec;


class SeckillService
{

    private $merchant_id;
    private $config_err;

    /**
     * 付款成功返回
     * @param $params
     * @return array seckill_ids 秒杀活动id，order_id 订单id
     * @author: tangkang@dodoca.com
     */
    public function orderPaid($order)
    {
        try {
            if (!empty($order) && $order['is_oversold'] == 1) {//超买订单取消
                return $this->orderCancel($order);
            }
            $this->config_err = config('err');
            $seckill_order = SeckillInitiate::where('order_id', $order['id'])->orderBy('id', 'desc')->first();

            if (empty($seckill_order['seckill_id']) || empty($seckill_order['merchant_id'])) {
                throw new \Exception(json_encode($seckill_order, JSON_UNESCAPED_UNICODE));
            }
            $seckill_data = Seckill::get_data_by_id($seckill_order['seckill_id'], $seckill_order['merchant_id']);
            if (empty($seckill_data)) {
                throw new \Exception(json_encode($seckill_data, JSON_UNESCAPED_UNICODE));
            }
			$ordergoodsInfo = OrderGoods::where(['order_id'=>$order['id']])->first();
			$sum = $ordergoodsInfo ? $ordergoodsInfo['quantity'] : 1;
            $update_data = ['cunpaid' => $seckill_data['cunpaid'] - 1, 'csale' => $seckill_data['csale'] + $sum];//未付款数量减去-1，//活动售出数量+1
            $seckill_res = Seckill::update_data($seckill_order['seckill_id'], $seckill_order['merchant_id'], $update_data);

            if (empty($seckill_res)) {
                throw new \Exception('秒杀活动表付款数量更新失败，' . json_encode($seckill_res, JSON_UNESCAPED_UNICODE));
            }
            // //队列处理----更新付款状态、、？处理秒杀发起记录 订单
            $seckill_order->pay_state = 1;
            $seckill_initiate_res = $seckill_order->save();
            if (empty($seckill_initiate_res)) {
                throw new \Exception('秒杀订单付款状态更新失败，' . json_encode($seckill_initiate_res, JSON_UNESCAPED_UNICODE));
            }
            return ['errcode' => 0, 'errmsg' => 'OK'];
        } catch (\Exception $e) {
            //记录异常
            $except = [
                'activity_id' => $order['id'],
                'data_type' => 'seckill_initiate',
                'content' => '秒杀订单信息异常（付款成功回调）：' . $e->getMessage(),
            ];
            CommonApi::errlog($except);
            return ['errcode' => 1, 'errmsg' => 'failed'];
        }

    }

    /**
     * 订单取消
     * @param $params
     * @return array
     * @author: tangkang@dodoca.com
     */
    public function orderCancel($order)
    {
        try {
            $this->config_err = config('err');
            $seckill_order_res = SeckillInitiate::where('order_id', $order['id'])->orderBy('id', 'desc')->first();
            if (empty($seckill_order_res) || empty($seckill_order_res['seckill_id']) || empty($seckill_order_res['order_id']) || empty($seckill_order_res['merchant_id'])) {
                throw new \Exception('秒杀订单信息异常，' . json_encode($seckill_order_res, JSON_UNESCAPED_UNICODE));
            }
            //队列处理、、、活动售出数量。。。mysql更新？
            $seckill_data = Seckill::get_data_by_id($seckill_order_res->seckill_id, $seckill_order_res->merchant_id);
            if (empty($seckill_data)) {
                throw new \Exception('秒杀活动不存在（订单取消），' . json_encode($seckill_data, JSON_UNESCAPED_UNICODE));
            }

//            $update_data = ['cunpaid' => $seckill_data['cunpaid'] - 1];//未付款数量减去  cancle肯定是还未付款的（暂时不要）
            if ($order['is_oversold'] == 1) {//超卖退款,活动售出数量-1
                $update_data['csale'] = $seckill_data['csale'] - 1;
                $res_seckill = Seckill::update_data($seckill_data['seckill_id'], $seckill_data['merchant_id'], $update_data);
                if (empty($res_seckill)) {
                    throw new \Exception('秒杀活动未付款数量更新失败，' . json_encode($res_seckill, JSON_UNESCAPED_UNICODE));
                }
            }

            //队列处理----更新付款状态、、处理秒杀发起记录 订单？
            $seckill_order_res->pay_state = 2;//已取消
            $res_order = $seckill_order_res->save();
            if (empty($res_order)) {
                throw new \Exception('秒杀活动付款状态更新失败，' . json_encode($res_order, JSON_UNESCAPED_UNICODE));
            }
            return ['errcode' => 0, 'errmsg' => 'OK'];
        } catch (\Exception $e) {
            //记录异常
            $except = [
                'activity_id' => $order['id'],
                'data_type' => 'seckill_initiate',
                'content' => '秒杀订单信息异常：' . $e->getMessage(),
            ];
            CommonApi::errlog($except);
            return ['errcode' => 1, 'errmsg' => 'failed'];
        }

    }

    /**
     * 活动详情
     * 如商品未参加活动返回空
     * @param $goods_id
     * @return mixed 空：商品未参加活动；其它：根据返回时间 预热、开始
     */
    public function getSeckill($merchant_id, $id)
    {
//        状态 0:未开始 1:预热中 2:活动中 3:已结束
        //新建时存redis？
        $seckill_res = $this->attachStatus($id, $merchant_id);
        if ($seckill_res['errcode'] != 0) return $seckill_res;//秒杀活动不存在
        return $seckill_res;
    }

    /**
     * 返回秒杀活动信息（含状态、当前服务器时间）
     * @param $id
     * @param $merchant_id
     * @return array
     * @author: tangkang@dodoca.com
     */
    public function attachStatus($id, $merchant_id)
    {
        $config_err = config('err');
        $seckill_res = Seckill::get_data_by_id($id, $merchant_id);
        if (empty($seckill_res)) return $config_err['70001'];//秒杀活动不存在
        $now = date('Y-m-d H:i:s');
        //状态 0:未开始 1:预热中 2:活动中 3:已结束
        $seckill_res->status = 0;
        if ($now < $seckill_res->presale_time) {
            if ($now < $seckill_res->end_time) $seckill_res->status = 0;//未开始
            if ($now < $seckill_res->start_time && $now <= $seckill_res->end_time) $seckill_res->status = 1;//预热
        }
        if ($now >= $seckill_res->start_time && $now < $seckill_res->end_time) $seckill_res->status = 2;//开始
        if ($now > $seckill_res->end_time) $seckill_res->status = 3;//结束
        $config_err['0']['data'] = $seckill_res;

        return $config_err['0'];
    }

    /**
     * 已创建未结束的秒杀商品最高价(未使用)
     * @param $id
     * @param $merchant_id
     * @author: tangkang@dodoca.com
     */
    public function getMaxPrice($id, $merchant_id)
    {
        if (empty($id)) return ['errcode' => 1, 'errmsg' => '缺少秒杀活动id参数'];//秒杀活动不存在
        if (empty($merchant_id)) return ['errcode' => 1, 'errmsg' => '缺少商户id参数'];//秒杀活动不存在
        $seckill_res = Seckill::get_data_by_id($id, $merchant_id);
        if (empty($seckill_res)) ['errcode' => 1, 'errmsg' => '获取秒杀活动信息失败'];

        $goods_res = Goods::get_data_by_id($seckill_res->goods_id, $merchant_id);

        if (empty($goods_res)) ['errcode' => 1, 'errmsg' => '获取商品信息失败'];

        if ($goods_res->is_sku == 1) {
            $goods_spec_res = GoodsSpec::get_data_by_goods_id($seckill_res->goods_id, $merchant_id);
            foreach ($goods_spec_res as $value) {
                $data[$value->id] = $seckill_res->price;
            }
        } elseif ($goods_res->is_sku == 0) {
            $data[0] = $seckill_res->price;
        }
        return ['errcode' => 0, 'errmsg' => '操作成功', 'data' => $data];
    }

    /**
     * 获取秒杀活动列表（预热、已开始未结束的秒杀活动）
     * @param $param
     * @return array
     * @author: tangkang@dodoca.com
     */
    public function getLists($param)
    {
        if(empty($param['merchant_id'])) return ['errcode' => 1, 'errmsg' => '缺失商户ID参数'];
        $date = date('Y-m-d H:i:s');
        $res['server_date']=$date;
        $res['lists'] = Seckill::where('merchant_id', $param['merchant_id'])
            ->where(function ($query) use ($date) {
                $query->where('presale_time', '<=', $date)
                    ->whereOr('start_time', '<=', $date);
            })
            ->where('end_time', '>', $date)
            ->get();
        return ['errcode' => 0, 'errmsg' => 'OK', 'data' => $res];
    }
}

<?php

/**
 * Created by PhpStorm.
 * User: tangkang@dodoca.com
 * Date: 2017-09-13
 * Time: 14:00
 */
use App\Models\Seckill;
use App\Models\SeckillInitiate;
use App\Utils\CommonApi;

class SeckillReturnService
{
    /**
     * 付款成功返回
     * @param $params
     * @return array seckill_ids 秒杀活动id，order_id 订单id
     * @author: tangkang@dodoca.com
     */
    public function postOrderPaid($params)
    {
        try{
            if (empty($params['id']) || empty($params['merchant_id'])) {
                //记录异常
                $except = [
                    'activity_id' => $params['id'],
                    'data_type' => 'seckill',
                    'content' => '订单-秒杀付款成功回调缺失参数：' . json_encode($params, JSON_UNESCAPED_UNICODE),
                ];
                CommonApi::errlog($except);
                return config('err')['99001'];
            }
            $order_id = $params['id'];
            $merchant_id = $params['merchant_id'];

            $seckill_data = Seckill::get_data_by_id($order_id, $merchant_id);

            if (empty($seckill_data)) {
                //记录异常
                $except = [
                    'activity_id' => $order_id,
                    'data_type' => 'seckill',
                    'content' => '订单-秒杀付款成功回调查询秒杀活动失败：' . json_encode($seckill_data, JSON_UNESCAPED_UNICODE),
                ];
                CommonApi::errlog($except);
                return ['errcode' => 1, 'errmsg' => '秒杀活动不存在'];
            }
            $seckill_id = $seckill_data->id;
            $seckill_data->cunpaid = $seckill_data['cunpaid'] - 1;//未付款数量减去
            $seckill_data->save();

            //处理秒杀发起记录 订单
            if ($order_id) {
                $seckill_initiate_data = SeckillInitiate::where(array('order_id' => $order_id, 'seckill_id' => $seckill_id))->first();
                if (empty($seckill_initiate_data)) {
                    //记录异常
                    $except = [
                        'activity_id' => $order_id,
                        'data_type' => 'seckill',
                        'content' => '订单-秒杀订单不存在：' . json_encode($seckill_initiate_data, JSON_UNESCAPED_UNICODE),
                    ];
                    CommonApi::errlog($except);
                    return ['errcode' => 1, 'errmsg' => '秒杀订单不存在'];
                }
                $seckill_initiate_data->pay_state = 1;
                $seckill_initiate_data->save();
            }
            return ['errcode' => 0, 'errmsg' => 'OK'];
        }catch (\Exception $e){
            //记录异常
            $except = [
                'activity_id' => $params['id'],
                'data_type' => 'seckill',
                'content' => '订单-更新秒杀订单信息失败：' . $e->getMessage(),
            ];
            CommonApi::errlog($except);
            return ['errcode' => 1, 'errmsg' => '更新秒杀订单信息失败'];
        }
    }

    /**
     * 订单取消
     * @param $params
     * @return array
     * @author: tangkang@dodoca.com
     */
    public function postOrderCancel($params)
    {
        try {
            if (empty($params['id']) || empty($params['merchant_id'])) {
                //记录异常
                $except = [
                    'activity_id' => $params['id'],
                    'data_type' => 'seckill',
                    'content' => '订单-秒杀取消回调缺失参数：' . json_encode($params, JSON_UNESCAPED_UNICODE),
                ];
                CommonApi::errlog($except);
                return config('err')['99001'];
            }
            $order_id = $params['id'];
            $merchant_id = $params['merchant_id'];
            $seckill_data = Seckill::get_data_by_id($order_id, $merchant_id);
            if (empty($seckill_data)) {
                //记录异常
                $except = [
                    'activity_id' => $order_id,
                    'data_type' => 'seckill',
                    'content' => '订单-秒杀付款成功回调查询秒杀活动失败：' . json_encode($seckill_data, JSON_UNESCAPED_UNICODE),
                ];
                CommonApi::errlog($except);
                return ['errcode' => 1, 'errmsg' => '秒杀活动不存在'];
            }
            $seckill_id = $seckill_data->id;

            $seckill_data->cunpaid = $seckill_data['cunpaid'] - 1;//未付款数量减去  cancle肯定是还未付款的
            $seckill_data->csale = $seckill_data['csale'] - 1;
            $seckill_data->save();

            //处理秒杀发起记录 订单
            if ($order_id) {
                $seckill_initiate_data = SeckillInitiate::where(array('order_id' => $order_id, 'seckill_id' => $seckill_id))->first();
                if (empty($seckill_initiate_data)) {
                    //记录异常
                    $except = [
                        'activity_id' => $order_id,
                        'data_type' => 'seckill',
                        'content' => '订单-秒杀订单不存在：' . json_encode($seckill_initiate_data, JSON_UNESCAPED_UNICODE),
                    ];
                    CommonApi::errlog($except);
                    return ['errcode' => 1, 'errmsg' => '秒杀订单不存在'];
                }
                $seckill_initiate_data->pay_state = 2;
                $seckill_initiate_data->save();
            }
            return ['errcode' => 0, 'errmsg' => 'OK'];
        } catch (\Exception $e) {
            //记录异常
            $except = [
                'activity_id' => $params['id'],
                'data_type' => 'seckill',
                'content' => '订单-更新秒杀订单信息失败：' . $e->getMessage(),
            ];
            CommonApi::errlog($except);
            return ['errcode' => 1, 'errmsg' => '更新秒杀订单信息失败'];
        }

    }
}
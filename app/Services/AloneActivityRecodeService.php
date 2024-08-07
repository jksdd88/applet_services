<?php
/**
 * Created by PhpStorm.
 * User: tang
 * Date: 2017-09-29
 * Time: 17:59
 */
namespace App\Services;

use  App\Models\AloneActivityRecode;

class AloneActivityRecodeService
{
    /**
     * 获取商品已创建的活动
     * @param $goods_id
     * @param $merchant_id
     * @return mixed seckill：秒杀商品，bargain：砍价商品， tuan：团购商品，为空则没有参加创建的活动（如返回seckill则为秒杀）
     * @author: tangkang@dodoca.com
     */
    public function getCreated(array $goods_ids, $merchant_id)
    {
        $date = date('Y-m-d H:i:s');
        $query = AloneActivityRecode::query();
        if (count($goods_ids) > 1) {
            $query->whereIn('goods_id', $goods_ids);
        } else {
            $query->where('goods_id', $goods_ids[0]);
        }
        $res = $query->where('merchant_id', $merchant_id)
            ->where(function ($query) use ($date) {
                $query->where(function ($q_type) use ($date) {
                    $q_type->where('act_type', 'seckill')->where('finish_time', '>', $date);
                })->orWhere(function ($q_type) {
                    $q_type->Where('act_type', 'tuan')->where('finish_time', '0000-00-00 00:00:00');
                })->orWhere(function ($q_type) use ($date) {
                    $q_type->Where('act_type', 'bargain')->where('finish_time', '>', $date);
                });
            })->orderBy('alone_id', 'desc')->get(['goods_id', 'act_type', 'alone_id']);
        return $res;
    }

    /**
     * 获取商品已创建 && 已开始进行的活动 (商品列表调用、装修模块调用)
     * @param $goods_id
     * @param $merchant_id
     * @return mixed ['act_type'=>'seckill'] ----- seckill：秒杀商品，tuan：团购商品，为空则没有参加创建的活动（如返回seckill则为秒杀）
     * @author: tangkang@dodoca.com
     */
    public function getStarting(array $goods_ids, $merchant_id)
    {
        $date = date('Y-m-d H:i:s');
        $query = AloneActivityRecode::query();
        if (count($goods_ids) > 1) {
            $query->whereIn('goods_id', $goods_ids);
        } else {
            $query->where('goods_id', $goods_ids[0]);
        }
        $query->where('merchant_id', $merchant_id)
            ->where(function ($query) use ($date) {
                $query->where(function ($q_type) use ($date) {
                    $q_type->where('act_type', 'seckill')->where('finish_time', '>', $date)->where('start_time', '<=', $date);
                })->orWhere(function ($q_type) use ($date) {
                    $q_type->Where('act_type', 'tuan')->where('finish_time', '0000-00-00 00:00:00')->where('start_time', '<=', $date);
                })->orWhere(function ($q_type) use ($date) {
                    $q_type->Where('act_type', 'bargain')->where('finish_time', '>', $date)->where('start_time', '<=', $date);
                });
            })->orderBy('alone_id', 'desc');
        $res = $query->get(['goods_id', 'act_type', 'alone_id']);
        return $res;
    }

    /**
     * 获取已创建未结束营销活动商品的最高价格
     * @param $goods_id
     * @param $merchant_id
     * @return array|void
     * errcode['data']返回数据格式
     *           (1) 空则未参加任何活动，
     *           (2) ['act_type'=>'seckill','0'=>100.23,'103'=>100.50];
     *              act_type：活动类型【seckill：秒杀，tuan：团购】
     *              0：非多规格商品
     *              103：规格id
     * @author: tangkang@dodoca.com
     */
//    public function getMaxPrice($goods_id, $merchant_id)
//    {
//        $act_type_res = $this->getCreated($goods_id, $merchant_id);
//        if (empty($act_type_res)) return ['errcode' => 0, 'errmsg' => '商品未参与营销活动', 'data' => []];
//        //参考结构--放各自活动中
//        $data = [];
//        if ($act_type_res->act_type == 'seckill') {//秒杀的价格
//            $SeckillService = new SeckillService();
//            $res = $SeckillService->getMaxPrice($act_type_res->alone_id, $merchant_id);
//            if ($res['errcode'] == 0) {
//                $data = $res['data'];
//                $data['act_type'] = 'seckill';
//            }
//        } elseif ($act_type_res->act_type == 'tuan') {//团购的价格
//            $FightgroupService = new FightgroupService();
//            $res = $FightgroupService->maxSpecLadderPrice($merchant_id, $goods_id, $act_type_res->alone_id);
//            if ($res['errcode'] == 0) {
//                $data = $res['data'];
//                $data['act_type'] = 'tuan';
//            }
//        }
//        if (isset($res) && $res['errcode'] != 0) return $res;
//        return ['errcode' => 0, 'errmsg' => '操作成功', 'data' => $data];
//    }
}

<?php
/**
 * Created by PhpStorm.
 * User: tang
 * Date: 2018-01-17
 * Time: 15:17
 */

namespace App\Services;

use App\Models\ToyGrabLog;
use App\Models\ToyMember;

class ToyService
{
    const MAX_MONEY = 5000;//当所有用户拥有的娃娃价值为5000元时，全平台的抓娃娃概率变为0%，但前三次必中不受影响。
    const MAX_TOY = 9;//可拥有最大娃娃数量
    const MAX_FREE_TIMES = 3;//每日免费次数
    const MAX_EXCHANGE_TIMES = 20;//每日助力最多兑换次数
    //1. 3个娃娃兑换一个10元娃娃
    //2. 6个娃娃兑换一个20元娃娃
    //3. 9个娃娃兑换一个30元娃娃
    static $exchange_rules = ['ten' => 3, 'twenty' => 6, 'thirty' => 9, 'coupon' => 1];

    /**
     * 每日可用最大次数(免费次数+可使用助力次数)
     * @return int
     * @author: tangkang@dodoca.com
     */
    public function getMaxTimes()
    {

        return self::MAX_FREE_TIMES + self::MAX_EXCHANGE_TIMES;
    }

    /**
     * 1%概率
     * @return bool
     * @author: tangkang@dodoca.com
     */
    public function onePercentPR()
    {
        return mt_rand(0, 99) === 1;
    }

    /**
     * 10%概率
     * @return bool
     * @author: tangkang@dodoca.com
     */
    public function tenPercentPR()
    {
        return mt_rand(0, 9) === 1;
    }

    /**
     * 5%概率
     * @return bool
     * @author: tangkang@dodoca.com
     */
    public function fivePercentPR()
    {
        return mt_rand(0, 19) === 1;
    }

    /**
     * 20%概率
     * @return bool
     * @author: tangkang@dodoca.com
     */
    public function twentyPercentPR()
    {
        return mt_rand(0, 4) === 1;
    }

    /**
     * 是否可兑换娃娃
     * @param $param
     * $param['member_id']:会员id，
     * $param['type']:兑换类型 1->三虚拟兑换一，2->6虚拟兑换一，3->9虚拟兑换一，4->1换一兑换券'
     * @param $merchant_id
     * @return bool
     * @author: tangkang@dodoca.com
     */
    public function ifExchange($param, $merchant_id)
    {
        $toy_member_res = ToyMember::get_data_by_id($param['member_id'], $merchant_id);
        if (empty($toy_member_res)) return false;
//        3个娃娃兑换一个10元娃娃
//        6个娃娃兑换一个20元娃娃
//        9个娃娃兑换一个30元娃娃
//        1个娃娃兑换一个兑换券
        if ($toy_member_res['toy_qty'] >= self::$exchange_rules['thirty']) {
            return true;
        } else {
            if (in_array($param['type'], [1, 2, 4])) {//兑换娃娃类型：1->三虚拟兑换一，2->六虚拟兑换一，3->九虚拟兑换一
                if ($param['type'] == 2 && $toy_member_res['toy_qty'] >= self::$exchange_rules['twenty']) {
                    return true;
                } elseif ($param['type'] == 1 && $toy_member_res['toy_qty'] >= self::$exchange_rules['ten']) {
                    return true;
                } elseif ($param['type'] == 4 && $toy_member_res['toy_qty'] >= self::$exchange_rules['coupon']) {
                    return true;
                } else {
                    return false;
                }
            } else {
                return false;
            }
        }
    }

    /**
     * 是否可抓取，返回今日可抓取次数
     * @param $param
     * @param $merchant_id
     * @return int
     * @author: tangkang@dodoca.com
     */
    public function ifGrab($param, $merchant_id)
    {
        if (empty($param['member_id'])) return 0;
//        $maxTimes = self::getMaxTimes();
        $count = ToyGrabLog::grab_times_today($param['member_id'], $merchant_id);//今天已抓取次数

        //已用完当天免费次数，用助力次数
        $toy_member_res = ToyMember::get_data_by_id($param['member_id'], $merchant_id);
//        if (empty($toy_member_res)) return 0;
        $can_qty = (int)$toy_member_res['assist_qty'] - (int)$toy_member_res['grab_qty'];//剩余可用助力次数
        if ($can_qty < 0) $can_qty = 0;
//        if ($count >= $maxTimes) {
//            return 0;//每天只能抓取23次（3次免费+20次助力）
//        }
        $rest_free_times = self::MAX_FREE_TIMES - $count;//免费次数-已使用次数（正整数：剩余免费次数，负数：使用了助力次数--免费次数用完了）
        if ($rest_free_times < 0) $rest_free_times = 0;

        //今天可用次数 = 20 - 今天已使用助力次数（$count - self::MAX_FREE_TIMES）
        //今天已用助力次数
//        $today_assist_grabbed = $count - self::MAX_FREE_TIMES;
//        $today_assist_grabbed = $today_assist_grabbed < 1 ? 0 : $today_assist_grabbed;
        $can_times = $rest_free_times + $can_qty;//可用次数 = 今天剩余免费次数+可用助力次数
        if ($can_times < 0) $can_times = 0;
//        if ($can_times > self::getMaxTimes()) $can_times = self::getMaxTimes();
        return $can_times;
    }

    /**
     * 获取抓取结果，是否抓到
     * @return bool true：抓到，false：未抓到
     * @author: tangkang@dodoca.com
     */
    public function getGrabResult($param, $merchant_id)
    {
        $toy_member_res = ToyMember::get_data_by_id($param['member_id'], $merchant_id);
        if ($toy_member_res['toy_qty_total'] >= self::MAX_TOY) {
            return false;
        } else {
            //前三次必中一次
            if ($toy_member_res['grab_qty_total'] <= 3) {
                $where = [
                    'merchant_id' => $merchant_id,
                    'member_id' => $param['member_id'],
                    'is_delete' => 1,
                    'result' => 1,
                ];
                $grab_result = ToyGrabLog::where($where)->limit(3)->get(['id']);
                if ($toy_member_res['grab_qty_total'] == 0) {
                    return 1 === mt_rand(1, 3);
                } elseif ($toy_member_res['grab_qty_total'] == 1) {
                    return 1 === mt_rand(1, 2) && (bool)($grab_result->isEmpty());
                } elseif ($toy_member_res['grab_qty_total'] == 2) {
                    return (bool)($grab_result->isEmpty());//一个未中就中一个吧
                } else {
                    return false;
                }
            } else {
                if ($toy_member_res['toy_qty_total'] == 1) {//抓到娃娃的概率为20%
                    $flag = self::twentyPercentPR();
                } elseif (in_array($toy_member_res['toy_qty_total'], [2, 3, 4, 6, 7])) {//抓到的娃娃概率为10%
                    $flag = self::tenPercentPR();
                } elseif ($toy_member_res['toy_qty_total'] == 5) {//抓到的娃娃概率为5%
                    $flag = self::fivePercentPR();
                } elseif ($toy_member_res['toy_qty_total'] == 8) {//抓到的娃娃概率为1%
                    $flag = self::onePercentPR();
                } else {
                    $flag = false;
                }
                //概率为10的
//                if (in_array($toy_member_res['toy_qty_total'], [1, 3, 4, 6, 7])) {//抓到娃娃的概率为10%
//                    $flag = self::tenPercentPR();
//                } elseif (in_array($toy_member_res['toy_qty_total'], [2, 5, 8])) {//抓到的娃娃概率为1%
//                    $flag = self::onePercentPR();
//                } else {
//                    $flag = false;
//                }
                if ($flag) {//中了再检测是否超出总金额了
                    //总金额大于5000谁都中不了了
                    $money = ToyMember::get_money($merchant_id);
                    if ($money >= self::MAX_MONEY) {
                        return false;
                    } else {
                        return true;
                    }
                } else {
                    return $flag;//false
                }
            }
        }
    }
}

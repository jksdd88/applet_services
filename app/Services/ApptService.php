<?php

/**
 * Created by PhpStorm.
 * User: tangkang@dodoca.com
 * Date: 2017-09-13
 * Time: 14:00
 */
namespace App\Services;

use App\Models\ApptStaff;
use App\Models\Goods;
use App\Models\GoodsAppt;
use App\Models\GoodsProp;
use App\Models\GoodsSpec;
use App\Models\OrderAppt;
use App\Models\Prop;
use App\Models\PropValue;
use App\Models\Store;
use App\Utils\Calendar;
use App\Utils\CommonApi;

class ApptService
{
    /**
     * 预约商品付款成功返回
     * @param $order
     * @return array
     * @author: tangkang@dodoca.com
     */
    public function postOrderPaid($order)
    {
        try {
            //        $order['is_oversold'] = 1;  //是否超卖（1-超卖，0-正常）
            if (!empty($order) && $order['is_oversold'] == 1) {//超买订单取消
                return $this->postOrderCancel($order);
            }
            $merchant_id = $order['merchant_id'];
            $hexiao_code_first = self::randTenLength();
            if (OrderAppt::where('merchant_id', $merchant_id)->where('hexiao_code', $hexiao_code_first)->count()) {
                $hexiao_code = self::randTenLength();
                if ($hexiao_code == $hexiao_code_first) $hexiao_code = self::randTenLength();
            } else {
                $hexiao_code = $hexiao_code_first;
            }
            $appt_data = [
                'pay_status' => 1,
                'paid_time' => date('Y-m-d H:i:s'),
                'hexiao_code' => $hexiao_code,
            ];
            $res = OrderAppt::update_data($order['id'], $merchant_id, $appt_data);
            if (empty($res)) throw new \Exception('预约商品付款成功状态更新失败，更新结果：' . $res);
            return ['errcode' => 0, 'errmsg' => 'OK'];
        } catch (\Exception $e) {
            //记录异常
            $except = [
                'activity_id' => $order['id'],
                'data_type' => 'order_appt',
                'content' => '预约商品付款信息保存异常。line：' . $e->getLine() . '，msg：' . $e->getMessage() . json_encode($order, JSON_UNESCAPED_UNICODE),
            ];
            CommonApi::errlog($except);
            return ['errcode' => 1, 'errmsg' => '预约商品付款信息保存异常：' . $e->getMessage()];
        }
    }

    /**
     * 预约商品订单取消
     * @param $params
     * @return array
     * @author: tangkang@dodoca.com
     */
    public function postOrderCancel($order)
    {
        try {
            $merchant_id = $order['merchant_id'];
            $appt_data = [
                'pay_status' => 2,//已取消
            ];
            OrderAppt::update_data($order['id'], $merchant_id, $appt_data);
            return ['errcode' => 0, 'errmsg' => 'OK'];
        } catch (\Exception $e) {
            //记录异常
            $except = [
                'activity_id' => $order['id'],
                'data_type' => 'order_appt',
                'content' => '预约商品订单取消异常：' . $e->getMessage() . json_encode($order, JSON_UNESCAPED_UNICODE),
            ];
            CommonApi::errlog($except);
            return ['errcode' => 1, 'errmsg' => '预约商品订单取消异常：' . $e->getMessage()];
        }
    }

    /**
     * 获取10为随机数字
     * @return string
     * @author: tangkang@dodoca.com
     */
    private function randTenLength()
    {
        $rand = str_shuffle("1234567890");
        $round = substr($rand, 0, 10);
        return $round;
    }

    /**
     * 预约商品是否可预约（errcode为-1：无法预约，前端显示msg。）
     *
     * @param $param ['date'=>'2017-10-30','goods_spec_id'=>1,'merchant_id'=>1]
     * @return array
     * @author: tangkang@dodoca.com
     */
    public function getIfAppt($param)
    {
        if (empty($param['date'])) return ['errcode' => 1, 'errmsg' => '缺少预约日期参数'];
        if (empty($param['goods_spec_id'])) return ['errcode' => 1, 'errmsg' => '缺少预约商品规格ID参数'];
        if (empty($param['merchant_id'])) return ['errcode' => 1, 'errmsg' => '缺少商户ID参数'];

        $goods_spec = GoodsSpec::get_data_by_id($param['goods_spec_id'], $param['merchant_id']);
        $goods_appt_res = GoodsAppt::get_data_by_goods_id($goods_spec->goods_id, $param['merchant_id']);
//        $goods_res = Goods::get_data_by_id($goods_spec->goods_id, $param['merchant_id']);
        $least_hour = $goods_appt_res->leadtime_hr;
        $most_day = $goods_appt_res->leadtime_day;
        $least = intval($least_hour) * 60 * 60;
        $most = intval($most_day);
        $current_day = date('Y-m-d');

        //获取库存信息
        $GoodsService = new GoodsService();
        $param_stock = [
            'merchant_id' => $param['merchant_id'],
            'goods_id' => $goods_spec->goods_id,
            'date' => $param['date'],
        ];
        $param_stock['goods_spec_id'] = $goods_spec->id;
        $stock_res = $GoodsService->getGoodsStock($param_stock);
        if ($stock_res['errcode'] != 0) return $stock_res;

        //无库存
        if ($stock_res['data'] < 1) return ['errcode' => -1, 'errmsg' => '已经约满'];

        //未开始出售
        if (date('Y-m-d', strtotime("-$most day")) > $current_day) {
            return ['errcode' => -1, 'errmsg' => '未开始售卖'];
        }
        //须提前N小时预约
//        $props_arr = explode(';', $goods_spec->props_str);
//        $props_key = array_search_like(':', $props_arr);

//        $prop_id = Prop::getPropIdByName('time', $goods_res->goods_cat_id);
//        $time = PropValue::where(['merchant_id' => $param['merchant_id'], 'prop_id' => $prop_id, 'prop_type' => 1])->value('title');
        $time_value_res = self::getApptTimeValue($param['goods_spec_id'], $param['merchant_id']);
        if (empty($time_value_res) || $time_value_res['errcode'] != 0) return $time_value_res;
        if ($time_value_res['data']['is_delete'] != 1) return ['errcode' => 1, 'errmsg' => '该时间段不可预约，请选择其它时间段'];
        $time = $time_value_res['data']['title'];

        if (!empty($time)) {
            $time_arr = explode('-', $time);
            $appt_time = $param['date'] . ' ' . $time_arr[0];//预约具体时间
            if ((strtotime($appt_time) - $least) <= time()) {
                if ($least_hour == 0) return ['errcode' => -1, 'errmsg' => '预约日期已过，请预约其它时间。'];
                return ['errcode' => -1, 'errmsg' => '须提前 ' . $least_hour . ' 小时预约'];
            }
        } else {
            return ['errcode' => 1, 'errmsg' => '获取商品预约时段规格值失败'];
        }
        return ['errcode' => 0, 'errmsg' => '可预约', 'data' => $time];
    }

    /**
     * 获取最近可预约日期列表范围
     * @param $param
     * @return array ['10-30'=>'今天','10-31'=>'周二','11-01'=>'周三,...]
     * @author: tangkang@dodoca.com
     */
    public function getApptDateRange($param)
    {
        if (empty($param['goods_id'])) return ['errcode' => 1, 'errmsg' => '缺少预约商品ID参数'];
        if (empty($param['merchant_id'])) return ['errcode' => 1, 'errmsg' => '缺少商户ID参数'];

        $goods_appt_res = GoodsAppt::get_data_by_goods_id($param['goods_id'], $param['merchant_id']);
        if (empty($goods_appt_res)) return ['errcode' => 1, 'errmsg' => '获取预约信息失败（日期范围列表）'];
        $date_range = $goods_appt_res['leadtime_day'];
        $date_range = intval($date_range);
        $week = ['日', '一', '二', '三', '四', '五', '六'];
        $stamp = time();
        $date_lists = [];
        for ($i = 0; $i <= $date_range; $i++) {//提前0天可预约今天的，显示今天时间。提前1天，说明我可以预约到明天的，显示今、明两天日期
            $thatTime = strtotime("+$i day", $stamp);
            if ($i === 0) {
                $date_lists[] = [
                    'week' => '今天',
                    'date' => date('m-d', $stamp),
                    'fulldate' => date('Y-m-d', $stamp),
                ];
            } elseif ($i === 1) {
                $date_lists[] = [
                    'week' => '明天',
                    'date' => date('m-d', $thatTime),
                    'fulldate' => date('Y-m-d', $thatTime),
                ];
            } else {
                $nunmber = date("w", $thatTime);
                $date_lists[] = [
                    'week' => '周' . $week[$nunmber],
                    'date' => date('m-d', $thatTime),
                    'fulldate' => date('Y-m-d', $thatTime),
                ];
            }
        }
        return ['errcode' => 0, 'errmsg' => 'OK', 'data' => $date_lists];
    }

    /**
     * 获取指定日期预约时间段列表（商品价格）
     *
     * @param $param ['date'=>'2017-10-30','goods_id'=>1,'merchant_id'=>1]
     * @return array|void
     * @author: tangkang@dodoca.com
     */
    public function getApptTime($param)
    {
        if (empty($param['date'])) return ['errcode' => 1, 'errmsg' => '缺少预约日期参数'];
        if (empty($param['goods_id'])) return ['errcode' => 1, 'errmsg' => '缺少预约商品ID参数'];
        if (empty($param['merchant_id'])) return ['errcode' => 1, 'errmsg' => '缺少商户ID参数'];
        if (empty($param['store_id'])) return ['errcode' => 1, 'errmsg' => '缺少商户ID参数'];

        $date = $param['date'];
        $goods_id = $param['goods_id'];
        $merchant_id = $param['merchant_id'];
        $store_id = $param['store_id'];

        $goods_res = Goods::get_data_by_id($goods_id, $merchant_id);
        if (empty($goods_res)) return ['errcode' => 1, 'errmsg' => '获取商品信息失败'];

        $goods_appt_res = GoodsAppt::get_data_by_goods_id($goods_id, $merchant_id);
        if (empty($goods_appt_res)) return ['errcode' => 1, 'errmsg' => '获取预约商品信息失败'];
//        $staff_id = null;
        if ($goods_appt_res->mode == 2) {//按人员预约
            if (empty($param['staff_id'])) return ['errcode' => 1, 'errmsg' => '请选择预约人员'];
            $staff_id = $param['staff_id'];
        }

        $prop_time_id = Prop::getPropIdByName('time', $goods_res->goods_cat_id);
        if (empty($prop_time_id)) return ['errcode' => 1, 'errmsg' => '获取预约时间段规格名ID失败'];


        $prop_time_value_list = GoodsProp::where('goods_prop.merchant_id', $merchant_id)
            ->where('goods_prop.goods_id', $goods_id)
            ->where('goods_prop.is_delete', 1)
            ->leftJoin('prop', 'goods_prop.prop_id', '=', 'prop.id')
            ->whereIn('prop.merchant_id', [$merchant_id, 0])
            ->where('prop.prop_type', 1)
            ->where('prop.id', $prop_time_id)
            ->where('prop.is_delete', 1)
            ->get(
                [
                    'goods_prop.prop_value as prop_value',
                    'goods_prop.prop_vid as prop_vid',
                ]
            );
        if (empty($prop_time_value_list)) return ['errcode' => 1, 'errmsg' => '获取预约时间段规格值列表失败'];
        $goods_spec_lists = GoodsSpec::get_data_by_goods_id($goods_id, $merchant_id);
        if (empty($goods_spec_lists)) return ['errcode' => 1, 'errmsg' => '获取商品规格列表失败'];
        $prop_store_id = Prop::getPropIdByName('store', $goods_res->goods_cat_id);
        $prop_date_id = Prop::getPropIdByName('date', $goods_res->goods_cat_id);
        $props_store_search = $prop_store_id . ':' . $store_id;
        if ($goods_appt_res->mode == 2 && !empty($staff_id)) {//按人员预约
            $prop_staff_id = Prop::getPropIdByName('staff', $goods_res->goods_cat_id);
            $props_staff_search = $prop_staff_id . ':' . $staff_id;
        }
//        $date_id = 0;//预约日期值id,周末或工作日或节假日

        $date_res = Calendar::get_date_type($date);//获取指定日期的类型

        if ($date_res['errcode'] != 0) {
            return ['errcode' => 1, 'errmsg' => '获取日历数据失败', 'data' => []];
            $prop_value = '';
        } else {
            $prop_value = $date_res['data'];
        }
        $date_id = PropValue::where('merchant_id', 0)
            ->where('prop_id', $prop_date_id)
            ->where('is_delete', 1)->where('title', $prop_value)->value('id');//预约日期规格值id

        $props_date_search = $prop_date_id . ':' . $date_id;//$date_id 预约日期值id
        $data = [];
        $goodsService = new GoodsService();
        $param_stock = [
            'merchant_id' => $merchant_id,
            'goods_id' => $goods_id,
            'date' => $date,
        ];
        foreach ($prop_time_value_list as $prop_time_value) {//组装商品规格下的时间段（下单可用该返回数据）
            $props_search = $prop_time_id . ':' . $prop_time_value->prop_vid;
            foreach ($goods_spec_lists as $goods_spec) {
//                $storeAndStaff = (mb_strpos($goods_spec->props, $props_store_search) !== false && mb_strpos($goods_spec->props, $props_staff_search) !== false);
                $props_store_search_pos = mb_strpos($goods_spec->props, $props_store_search);
                $props_date_search_pos = mb_strpos($goods_spec->props, $props_date_search);
                $props_search_pos = mb_strpos($goods_spec->props, $props_search);
                if ($goods_appt_res->mode == 2 && !empty($props_staff_search)) {
                    $props_staff_search_pos = mb_strpos($goods_spec->props, $props_staff_search);
                    $flag_inner = ($props_store_search_pos !== false && $props_staff_search_pos !== false && $props_search_pos !== false && $props_date_search_pos !== false);
                } elseif ($goods_appt_res->mode == 1) {
                    $flag_inner = ($props_store_search_pos !== false && $props_search_pos !== false && $props_date_search_pos !== false);
                }
                if (isset($flag_inner) && $flag_inner) {
                    $param = [
                        'date' => $date,
                        'goods_spec_id' => $goods_spec->id,
                        'merchant_id' => $merchant_id,
                    ];
                    $res = self::getIfAppt($param);
                    if (empty($res) || $res['errcode'] != 0) continue;//已删除时间段不可预约
//                    if ($res['errcode'] > 0) {//错误
//                        $errData = [
//                            'activity_id' => $goods_spec->id,
//                            'data_type' => 'goods_spec',
//                            'content' => '获取预约商品是否可预约状态失败->' . json_encode($res, JSON_UNESCAPED_UNICODE) . '；参数：' . json_encode($param, JSON_UNESCAPED_UNICODE),
//                        ];
//                        CommonApi::errlog($errData);
//                        continue;
//                    } elseif ($res['errcode'] < 0) {//排除日期已经过的时间段（比如现在6点，则6点前的时间段是不显示的）
//                        continue;
//                    }
                    $param_stock['goods_spec_id'] = $goods_spec->id;
                    $stock_res = $goodsService->getGoodsStock($param_stock);
                    if ($stock_res['errcode'] == 0) {
                        $stock = $stock_res['data'];//库存
                    } else {
                        $stock = 'Error';//库存
                    }
//                    $data['lists'][$prop_time_value->prop_vid]['msg'] = '¥' . $goods_spec->price;//价格
                    $data['lists'][] = [
                        'goods_spec_id' => $goods_spec->id,//规格id
                        'time' => $prop_time_value->prop_value,//时间段
                        'price' => '¥' . $goods_spec->price,//价格
                        'stock' => $stock,//价格
                    ];
                } else {//错误：商品规格属性关联表goods_props中数据未在goods_spec表中props发现组合
//                    $props_staff_search = empty($props_staff_search) ? null : $props_staff_search;
//                    $errData = [
//                        'activity_id' => $goods_spec->id,
//                        'data_type' => 'ApptService.php => getApptTime()',
//                        'content' => '规格id与规格值组合：time：' . $props_search .
//                            '，store:' . $props_store_search .
//                            '，staff:' . $props_staff_search .
//                            ',商品规格表props字段：' . json_encode($goods_spec->props, JSON_UNESCAPED_UNICODE),
//                    ];
//                    CommonApi::errlog($errData);
                    continue;
                }
            }
        }
        $data['count'] = (empty($data['lists']) ? 0 : count($data['lists']));
        return ['errcode' => 0, 'errmsg' => 'OK', 'data' => $data];
    }

    /**
     * 预约商品规格值组装（预约可修改规格值，不用goods_spec表props_str字段）
     * @param $goods_spec_id
     * @param $merchant_id
     * @param int $type 0:普通规格字符串。1：预约规格字符串 返回格式 ['服务门店：xxx','服务人员：xxx','服务日期：xxx',...]
     * @return array
     * @author: tangkang@dodoca.com
     */
    public function getApptPropsStr($goods_spec_id, $merchant_id, $type = 0)
    {
        if (empty($goods_spec_id)) return ['errcode' => 1, 'errmsg' => '获取规格值参数缺少：规格ID'];
        if (empty($merchant_id)) return ['errcode' => 1, 'errmsg' => '获取规格值参数缺少：商户ID'];

        //props 门店信息；技师信息；时段信息；日期信息
        $goods_spec_res = GoodsSpec::get_data_by_id($goods_spec_id, $merchant_id);
        if (empty($goods_spec_res)) return ['errcode' => 1, 'errmsg' => '获取规格值异常'];
        $props_arr = explode(';', $goods_spec_res->props);
//        array:4 [
//        0 => "661:3"
//  1 => "662:5"
//  2 => "659:3261"
//  3 => "660:3147"
//]
        $value_ids = [];
        $callback = function ($value) use ($merchant_id, &$value_ids) {
            $arr = explode(':', $value);
            $value_ids[] = $arr[1];
        };
        array_walk($props_arr, $callback);
//        dd($value_ids);
        $store_res = Store::get_data_by_id($value_ids[0], $merchant_id);
        if (empty($store_res)) return ['errcode' => 1, 'errmsg' => '获取预约门店规格值异常'];

        $data_type_zero[] = $store_res->name;//普通规格字符串
        $data_type_one[] = '服务门店：' . $store_res->name;//评论用格式
        $goods_appt_res = GoodsAppt::get_data_by_goods_id($goods_spec_res->goods_id, $merchant_id);
        if (empty($goods_appt_res)) return ['errcode' => 1, 'errmsg' => '获取预约模式失败'];

        if ($goods_appt_res->mode == 2) {//含技师
            $staff_res = ApptStaff::get_data_by_id($value_ids[1], $merchant_id);
            if (empty($staff_res)) return ['errcode' => 1, 'errmsg' => '获取预约人员规格值异常'];
            $data_type_zero[] = $staff_res->nickname;
            $data_type_one[] = '服务人员：' . $staff_res->nickname;
            $time = PropValue::where(['id' => $value_ids[2]])->value('title');
            $date = PropValue::where(['id' => $value_ids[3]])->value('title');
        } elseif ($goods_appt_res->mode == 1) {//不含技师
            $time = PropValue::where(['id' => $value_ids[1]])->value('title');
            $date = PropValue::where(['id' => $value_ids[2]])->value('title');
        }
        if (!empty($time) && !empty($date)) {
            $data_type_zero[] = $time . ';' . $date;
            $data_type_one[] = '服务日期：' . $date;
            $data_type_one[] = '服务时间段：' . $time;
            if ($type == 1) {//预约下单存取用
                $data = implode(';', $data_type_one);
            } else {
                $data = implode(';', $data_type_zero);
            }
        } else {
            if (empty($store_res)) return ['errcode' => 1, 'errmsg' => '获取预约时段/预约日期规格值异常'];
        }
        return ['errcode' => 0, 'errmsg' => 'OK', 'data' => $data];
    }

    /**
     * 根据规格取预约时间段起始时间
     * @param $goods_spec_id
     * @param $merchant_id
     * @return array 已删除状态时间段不可下单
     * @author: tangkang@dodoca.com
     */
    public function getApptTimeValue($goods_spec_id, $merchant_id)
    {
        if (empty($goods_spec_id)) return ['errcode' => 1, 'errmsg' => '获取规格值参数缺少：规格ID'];
        if (empty($merchant_id)) return ['errcode' => 1, 'errmsg' => '获取规格值参数缺少：商户ID'];
        $goods_spec_res = GoodsSpec::get_data_by_id($goods_spec_id, $merchant_id);
        if (empty($goods_spec_res)) return ['errcode' => 1, 'errmsg' => '获取规格值异常'];
        $goods_appt_res = GoodsAppt::get_data_by_goods_id($goods_spec_res->goods_id, $merchant_id);
        if (empty($goods_appt_res)) return ['errcode' => 1, 'errmsg' => '获取预约数据异常'];

        $props_arr = explode(';', $goods_spec_res->props);
        $props_tem = $goods_appt_res->mode == 1 ? $props_arr[1] : $props_arr[2];
        $props_tem_arr = explode(':', $props_tem);
        $time = PropValue::where(['id' => $props_tem_arr[1], 'prop_id' => $props_tem_arr[0]])->first(['title', 'is_delete']);
        return ['errcode' => 0, 'errmsg' => 'OK', 'data' => $time];
    }
}

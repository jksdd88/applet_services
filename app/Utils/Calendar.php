<?php

namespace App\Utils;

/**
 * 日历API
 * @author wangyu
 * @time 2017-09-11
 */

use App\Models\Calendar as CalendarMod;

class Calendar
{
    /**
     * @param $date 2017-10-1
     * @return array|mixed  成功返回{"status":0,"msg":"ok","result":array }
     */
    static function search_calendar($date)
    {
        $day = date('Y-m-d', strtotime($date));

        $day_date = CalendarMod::get_data_by_date($day);

        if ($day_date['status'] != 0 || $day_date['msg'] != 'ok') {
            $errData = [
                'activity_id' => 1,
                'data_type' => 'Calendar::search_calendar',
                'content' => '调用第三方日期接口返回失败：' . json_encode($day_date, JSON_UNESCAPED_UNICODE) . '，参数：' . $date
            ];
            CommonApi::errlog($errData);
            return array('status' => 5000, 'msg' => 'error');
        }

        return $day_date;
    }

    /**
     * @param $data array('weekdays'=>40,'weekends'=>41,'holiday'=>42);
     * @return array|mixed  成功返回
     */
    static function get_month_calendar($data)
    {
        $day_list = array();
        $weekdays_list = array('一', '二', '三', '四', '五');
        for ($i = 0; ($day = date('Y-m-d', strtotime(date('Y-m-d') . " +$i day"))) <= date("Y-m-d", strtotime(date('Y-m-d') . ' +1 month')); $i++) {
            $day_date = CalendarMod::get_data_by_date($day);

            if (!empty($day_date['result']['workholiday']) && $day_date['result']['workholiday'] == 1 && !empty($data['holiday'])) {
                $day_list[$day] = $data['holiday'];
            } elseif (!empty($day_date['result']['week']) && ($day_date['result']['week'] == '六' || $day_date['result']['week'] == '日') && !empty($data['weekends'])) {
                $day_list[$day] = $data['weekends'];
            } elseif (!empty($day_date['result']['week']) && in_array($day_date['result']['week'], $weekdays_list) && !empty($data['weekdays'])) {
                $day_list[$day] = $data['weekdays'];
            } else {
                $day_list[$day] = 0;
            }

        }

        return $day_list;
    }

    /**
     * 获取指定日期的类型（法定节假日、周六至周日、周一至周五）
     * @param $date
     * @return array
     * @author: tangkang@dodoca.com
     */
    static function get_date_type($date)
    {
        //非法字符串日期
        $data_temp = date('Y-m-d', strtotime($date));
        if ($data_temp == '1970-01-01') ['errcode' => 1, 'errmsg' => '日期非法'];//预约商品日期非法
        //获取日期规格值
        $config_appt = config('appt');
        $date_res = Calendar::search_calendar($date);
        if ($date_res['status'] != 0) {
            return ['errcode' => 1, 'errmsg' => '获取日期的日历数据失败'];
        }
        //workholiday 上班日不返回该字段。周六周日调休上班返回该字段值为0。法定假日值为1
        if (!empty($date_res['result']['workholiday']) && $date_res['result']['workholiday'] == 1) {
            $prop_value = $config_appt['date_name']['holiday'];//'法定节假日'
        } elseif (!empty($date_res['result']['week']) && empty($date_res['result']['workholiday']) && in_array($date_res['result']['week'], ['六', '日'])) {
            $prop_value = $config_appt['date_name']['weekend'];//'非调休上班的周六、周日';
        } elseif (!isset($date_res['result']['workholiday']) || $date_res['result']['workholiday'] == 0) {
            $prop_value = $config_appt['date_name']['weekday'];//'周一至周五、调休上班的周六或周日'
        } else {
            $errData = [
                'activity_id' => 1,
                'data_type' => 'get_date_type',
                'content' => '日期类型获取失败：Calendar::get_date_type，日期：' . $date . '，返回结果：' . json_encode($date_res, JSON_UNESCAPED_UNICODE),
            ];
            CommonApi::errlog($errData);
            return ['errcode' => 1, 'errmsg' => 'Failed', 'data' => 'Error'];
        }
        return ['errcode' => 0, 'errmsg' => 'OK', 'data' => $prop_value];
    }
}
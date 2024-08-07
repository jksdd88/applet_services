<?php

namespace App\Services;

use App\Models\OrderDaily;
use App\Models\OrderInfo;
use App\Models\XcxVisitDaily;
use Illuminate\Support\Facades\Auth;

/*
 * 订单统计信息service
 * shangyazhao@dodoca.com
 */
class OrderDailyService {

    protected $today = '';
    protected $model = '';

    function __construct(OrderDaily $orderDaily) {
        $this->model = $orderDaily;
        $this->today = date('Ymd');
    }

    //查询订单趋势（七天的，不包括今天）
    public function getOrderStatDays($merchant_id,$start_day,$end_day) {
        if($merchant_id==0){
            return;
        }
        $result = array();
        // $end_day = strtotime($this->today);
        // $start_day = $end_day - $limit_day * 3600 * 24;

        for ($day = $start_day; $day < $end_day; $day = $day + 3600 * 24) {
            $result[$day]['date'] = date('Y-m-d', $day);
            $result[$day]['c_send_num'] = 0;           
            $result[$day]['c_success_num'] = 0;
            $result[$day]['trade_count'] = 0;
        }
        $wheres = array(
            array('column' => 'merchant_id', 'value' => $merchant_id, 'operator' => '='),
            array('column' => 'day_time', 'value' => $this->today, 'operator' => '<')
        );
        $limit_day = ($end_day - $start_day)/(3600*24);
        if(!$limit_day){
            $limit_day = 7;
        }
        $data = $this->model->get_data_list($wheres,'day_time,total_day,success_num_day,send_num_day',0, $limit_day);        
        foreach ($result as $k => $_result) {
            if ($data) {
                foreach ($data as $_data) {                    
                    $strtotime = strtotime($_data['day_time']);
                    if ($k == $strtotime) {
                        $result[$k]['date'] = date('Y-m-d', $strtotime);
                        $result[$k]['c_send_num'] = $_data['send_num_day'];                        
                        $result[$k]['c_success_num'] = $_data['success_num_day'];
                        $result[$k]['trade_count'] = $_data['total_day'];
                    }
                }
            }
        }
        return array_values($result);
    }
    
    //商户分段统计
    public static function putOrderStatDay() {
        $i=0;
        $nums_merchant = 5000;
        $continue_calculate = 0;
        do {
            $rs_merchants = \DB::select("select id from merchant order by id asc limit ".$i*$nums_merchant.",".$nums_merchant);
            if (!empty($rs_merchants)) {
                $continue_calculate = 1;
                $i++;
                
                $arr_merchants = json_decode(json_encode($rs_merchants), TRUE);
                unset($rs_merchants);
                self::putOrderStatDay_byMerchant($arr_merchants);
            } else {
                $continue_calculate = 0;
            }
        } while( $continue_calculate );
    }
    
    //具体统计方法
    public static function putOrderStatDay_byMerchant($array_merchants) {
        $yestoday = date("Ymd", strtotime("-1 day"));
        $today = date("Ymd");
        
        $order_info=new OrderInfo();
        $model=new OrderDaily();

            $arr_data = array();
            //历史累计:订单总数
            $total = \DB::table('order_info')
                        ->select(\DB::raw('merchant_id,count(*) as num'))
                        ->whereIn('merchant_id',$array_merchants)
                        ->where('created_time', '<', date("Y-m-d"))
                        ->groupBy('merchant_id')
                        ->get();
            $total = json_decode(json_encode($total), TRUE);
            if(!empty($total)){
                foreach ($total as $key=>$val){
                    $arr_data[$val['merchant_id']]['total'] = isset($val['num'])?$val['num']:0;
                }
                unset($total);
            }
            //历史累计:已完成订单总数
            $success_num = \DB::table('order_info')
                        ->select(\DB::raw('merchant_id,count(*) as num'))
                        ->whereIn('merchant_id',$array_merchants)
                        ->where('created_time', '<', date("Y-m-d"))->where(['status'=>'11'])
                        ->groupBy('merchant_id')
                        ->get();
            $success_num = json_decode(json_encode($success_num), TRUE);
            if(!empty($success_num)){
                foreach ($success_num as $key=>$val){
                    $arr_data[$val['merchant_id']]['success_num'] = isset($val['num'])?$val['num']:0;
                }
                unset($success_num);
            }
            //历史累计:已发货订单总数
            $send_num = \DB::table('order_info')
                        ->select(\DB::raw('merchant_id,count(*) as num'))
                        ->whereIn('merchant_id',$array_merchants)
                        ->where('created_time', '<', date("Y-m-d"))->where(['status'=>'10'])
                        ->groupBy('merchant_id')
                        ->get();
            $send_num = json_decode(json_encode($send_num), TRUE);
            if(!empty($send_num)){
                foreach ($send_num as $key=>$val){
                    $arr_data[$val['merchant_id']]['send_num'] = isset($val['num'])?$val['num']:0;
                }
                unset($send_num);
            }
            //历史累计:已付款订单总数
            $total_pay = \DB::table('order_info')
                        ->select(\DB::raw('merchant_id,count(*) as num'))
                        ->whereIn('merchant_id',$array_merchants)
                        ->where('created_time', '<', date("Y-m-d"))->where(['pay_status'=>'1'])
                        ->groupBy('merchant_id')
                        ->get();
            $total_pay = json_decode(json_encode($total_pay), TRUE);
            if(!empty($total_pay)){
                foreach ($total_pay as $key=>$val){
                    $arr_data[$val['merchant_id']]['total_pay'] = isset($val['num'])?$val['num']:0;
                }
                unset($total_pay);
            }
            //历史累计:已支付金额
            $total_payment_amount = \DB::table('order_info')
                        ->select(\DB::raw('merchant_id,sum(amount) as amount_sum'))
                        ->whereIn('merchant_id',$array_merchants)
                        ->where('created_time', '<', date("Y-m-d"))->where(['pay_status'=>'1'])
                        ->groupBy('merchant_id')
                        ->get();
            $total_payment_amount = json_decode(json_encode($total_payment_amount), TRUE);
            if(!empty($total_payment_amount)){
                foreach ($total_payment_amount as $key=>$val){
                    $arr_data[$val['merchant_id']]['total_payment_amount'] = isset($val['amount_sum'])?$val['amount_sum']:0;
                }
                unset($total_payment_amount);
            }
            //历史累计:待发货订单总数
            $total_to_send = \DB::table('order_info')
                        ->select( \DB::raw('merchant_id,count(*) as num') )
                        ->whereIn('merchant_id',$array_merchants)
                        ->where('created_time', '<', date("Y-m-d"))->whereIn('status',array(7,8))
                        ->groupBy('merchant_id')
                        ->get();
            $total_to_send = json_decode(json_encode($total_to_send), TRUE);
            if(!empty($total_to_send)){
                foreach ($total_to_send as $key=>$val){
                    $arr_data[$val['merchant_id']]['total_to_send'] = isset($val['num'])?$val['num']:0;
                }
                unset($total_to_send);
            }
            //历史累计:维权订单总数
            $total_refund = \DB::table('order_info')
                        ->select( \DB::raw('merchant_id,count(*) as num') )
                        ->whereIn('merchant_id',$array_merchants)
                        ->where('created_time', '<', date("Y-m-d"))->where(['refund_status'=>1])
                        ->groupBy('merchant_id')
                        ->get();
            $total_refund = json_decode(json_encode($total_refund), TRUE);
            if(!empty($total_refund)){
                foreach ($total_refund as $key=>$val){
                    $arr_data[$val['merchant_id']]['total_refund'] = isset($val['num'])?$val['num']:0;
                }
                unset($total_refund);
            }
            //历史累计:浏览量总数
            $total_views = \DB::connection('applet_stats')->table('xcx_visit_daily')
                        ->select( \DB::raw('merchant_id,sum(visit_uv) as visit_uv_sum') )
                        ->whereIn('merchant_id',$array_merchants)
                        ->where('day_time', '<', date("Ymd"))
                        ->groupBy('merchant_id')
                        ->get();
            $total_views = json_decode(json_encode($total_views), TRUE);
            if(!empty($total_views)){
                foreach ($total_views as $key=>$val){
                    $arr_data[$val['merchant_id']]['total_views'] = isset($val['visit_uv_sum'])?$val['visit_uv_sum']:0;
                }
                unset($total_views);
            }
            
            //单日数据:昨日订单总数
            $total_day = \DB::table('order_info')
                        ->select( \DB::raw('merchant_id,count(*) as num') )
                        ->whereIn('merchant_id',$array_merchants)
                        ->where('created_time', '<', date("Y-m-d"))->where('created_time', '>=', date("Y-m-d", strtotime("-1 day")))
                        ->groupBy('merchant_id')
                        ->get();
            $total_day = json_decode(json_encode($total_day), TRUE);
            if(!empty($total_day)){
                foreach ($total_day as $key=>$val){
                    $arr_data[$val['merchant_id']]['total_day'] = isset($val['num'])?$val['num']:0;
                }
                unset($total_day);
            }
            //单日数据:昨日已完成订单总数
            $success_num_day = \DB::table('order_info')
                        ->select( \DB::raw('merchant_id,count(*) as num') )
                        ->whereIn('merchant_id',$array_merchants)
                        ->where('created_time', '<', date("Y-m-d"))->where('created_time', '>=', date("Y-m-d", strtotime("-1 day")))->where(['status'=>11])
                        ->groupBy('merchant_id')
                        ->get();
            $success_num_day = json_decode(json_encode($success_num_day), TRUE);
            if(!empty($success_num_day)){
                foreach ($success_num_day as $key=>$val){
                    $arr_data[$val['merchant_id']]['success_num_day'] = isset($val['num'])?$val['num']:0;
                }
                unset($success_num_day);
            }
            //单日数据:昨日已发货订单总数
            $send_num_day = \DB::table('order_info')
                        ->select( \DB::raw('merchant_id,count(*) as num') )
                        ->whereIn('merchant_id',$array_merchants)
                        ->where('created_time', '<', date("Y-m-d"))->where('created_time', '>=', date("Y-m-d", strtotime("-1 day")))->where(['status'=>10])
                        ->groupBy('merchant_id')
                        ->get();
            $send_num_day = json_decode(json_encode($send_num_day), TRUE);
            if(!empty($send_num_day)){
                foreach ($send_num_day as $key=>$val){
                    $arr_data[$val['merchant_id']]['send_num_day'] = isset($val['num'])?$val['num']:0;
                }
                unset($send_num_day);
            }
            //单日数据:昨日付款订单总数
            $total_pay_day = \DB::table('order_info')
                        ->select( \DB::raw('merchant_id,count(*) as num') )
                        ->whereIn('merchant_id',$array_merchants)
                        ->where('created_time', '<', date("Y-m-d"))->where('created_time', '>=', date("Y-m-d", strtotime("-1 day")))->where(['pay_status'=>1])
                        ->groupBy('merchant_id')
                        ->get();
            $total_pay_day = json_decode(json_encode($total_pay_day), TRUE);
            if(!empty($total_pay_day)){
                foreach ($total_pay_day as $key=>$val){
                    $arr_data[$val['merchant_id']]['total_pay_day'] = isset($val['num'])?$val['num']:0;
                }
                unset($total_pay_day);
            }
            //单日数据:单日支付金额
            $daily_payment_amount = \DB::table('order_info')
                        ->select( \DB::raw('merchant_id,sum(amount) as amount_sum') )
                        ->whereIn('merchant_id',$array_merchants)
                        ->where('created_time', '<', date("Y-m-d"))->where('created_time', '>=', date("Y-m-d", strtotime("-1 day")))->where(['pay_status'=>1])
                        ->groupBy('merchant_id')
                        ->get();
            $daily_payment_amount = json_decode(json_encode($daily_payment_amount), TRUE);
            if(!empty($daily_payment_amount)){
                foreach ($daily_payment_amount as $key=>$val){
                    $arr_data[$val['merchant_id']]['daily_payment_amount'] = isset($val['amount_sum'])?$val['amount_sum']:0;
                }
                unset($daily_payment_amount);
            }
            //单日数据:单日浏览量
            $daily_views = \DB::connection('applet_stats')->table('xcx_visit_daily')
                        ->select( \DB::raw('merchant_id,sum(visit_uv) as visit_uv_sum') )
                        ->whereIn('merchant_id',$array_merchants)
                        ->where('day_time', '<', date("Ymd"))->where('day_time', '>=', date("Ymd", strtotime("-1 day")))
                        ->groupBy('merchant_id')
                        ->get();
            $daily_views = json_decode(json_encode($daily_views), TRUE);
            if(!empty($daily_views)){
                foreach ($daily_views as $key=>$val){
                    $arr_data[$val['merchant_id']]['daily_views'] = isset($val['visit_uv_sum'])?$val['visit_uv_sum']:0;
                }
                unset($daily_views);
            }
            //单日数据:待发货订单总数
            $daily_to_send = \DB::table('order_info')
                        ->select( \DB::raw('merchant_id,count(*) as num') )
                        ->whereIn('merchant_id',$array_merchants)
                        ->where('created_time', '<', date("Y-m-d"))->where('created_time', '>=', date("Y-m-d", strtotime("-1 day")))->whereIn('status',array(7,8))
                        ->groupBy('merchant_id')
                        ->get();
            $daily_to_send = json_decode(json_encode($daily_to_send), TRUE);
            if(!empty($daily_to_send)){
                foreach ($daily_to_send as $key=>$val){
                    $arr_data[$val['merchant_id']]['daily_to_send'] = isset($val['num'])?$val['num']:0;
                }
                unset($daily_to_send);
            }
            //单日数据://维权订单总数
            $daily_refund = \DB::table('order_info')
                        ->select( \DB::raw('merchant_id,count(*) as num') )
                        ->whereIn('merchant_id',$array_merchants)
                        ->where('created_time', '<', date("Y-m-d"))->where('created_time', '>=', date("Y-m-d", strtotime("-1 day")))->where(['refund_status'=>1])
                        ->groupBy('merchant_id')
                        ->get();
            $daily_refund = json_decode(json_encode($daily_refund), TRUE);
            if(!empty($daily_refund)){
                foreach ($daily_refund as $key=>$val){
                    $arr_data[$val['merchant_id']]['daily_refund'] = isset($val['num'])?$val['num']:0;
                }
                unset($daily_refund);
            }
            unset($array_merchants);
            //dd($arr_data);
            if(!empty($arr_data)){
                foreach ($arr_data as $key=>$val){
                    $putdata = array();
                    $putdata = array(
                        'merchant_id' => $key,
                        'day_time'=>$yestoday,
                        'total_day' =>isset($val['total_day'])?$val['total_day']:0,
                        'total' => isset($val['total'])?$val['total']:0,
                        'success_num_day' => isset($val['success_num_day'])?$val['success_num_day']:0,
                        'success_num' => isset($val['success_num'])?$val['success_num']:0,
                        'send_num_day' => isset($val['send_num_day'])?$val['send_num_day']:0,
                        'send_num'=>isset($val['send_num'])?$val['send_num']:0,
                        'total_pay'=>isset($val['total_pay'])?$val['total_pay']:0,
                        'total_pay_day'=>isset($val['total_pay_day'])?$val['total_pay_day']:0,
                        'total_payment_amount'=>isset($val['total_payment_amount'])?$val['total_payment_amount']:0,
                        'daily_payment_amount'=>isset($val['daily_payment_amount'])?$val['daily_payment_amount']:0,
                        'total_to_send'=>isset($val['total_to_send'])?$val['total_to_send']:0,
                        'daily_to_send'=>isset($val['daily_to_send'])?$val['daily_to_send']:0,
                        'total_refund'=>isset($val['total_refund'])?$val['total_refund']:0,
                        'daily_refund'=>isset($val['daily_refund'])?$val['daily_refund']:0,
                        'total_views'=>isset($val['total_views'])?$val['total_views']:0,
                        'daily_views'=>isset($val['daily_views'])?$val['daily_views']:0
                    );
                    
                    $data = '';
                    $data = $model->where(array('merchant_id' => $key, 'day_time' => $yestoday))->first();
                    //print_r($data);
                    if ($data) {
                        //本地计算后的数据和环境中原有程序计算的数据对比
                        foreach ($putdata as $key1=>$val1){
                            if($val1!=$data[$key1]){
                                echo $key.' '.$key1.':'.$val1.'_'.$data[$key1].'<br />';
                            }
                        }
                        
                        $where = array();
                        $where[] = array('column' => 'day_time', 'value' => $yestoday, 'operator' => '=');
                        $where[] = array('column' => 'merchant_id', 'value' => $key, 'operator' => '=');
                        $result = $model->update_data_by_where($yestoday, $key, $where, $putdata);
                    } else {
                        $result = $model->insert_data($putdata);
                    }
                    unset($arr_data[$key]);
                }
            }
    }

}

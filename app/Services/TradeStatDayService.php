<?php
/**交易统计
 * Created by PhpStorm.
 * User: jiangnan
 * Date: 2017/9/13
 * Time: 15:14
 */

namespace App\Services;

use App\Models\TradeStatDay;
use App\Models\Trade;
use App\Models\Merchant;
use App\Models\OrderInfo;

class TradeStatDayService {

    //获取商户今日交易额
    static function getTrades($merchant_id){
        $time = date('Y-m-d 00:00:00');
        $now = date('Y-m-d H:i:s',time());
        $data = Trade::select('amount')
            ->where('merchant_id','=',$merchant_id)
            ->where('created_time','<=',$now)
            ->where('created_time','>=',$time)
            ->where('pay_status','=',1)
            ->where('trade_type','=',1)
            ->get();
        $income = 0;
        foreach($data as $val){
            $income += abs($val['amount']);
        }
        return $income;
    }

    /**
     * 获取商户每日交易金额
     */
    public function updateTrades()
    {

        $i = 0;
        $nums_merchant = 5000;
        $continue_calculate = 0;
        do {
            $rs_merchants = \DB::select("select id from merchant order by id asc limit " . $i * $nums_merchant . "," . $nums_merchant);
            if (!empty($rs_merchants)) {
                $continue_calculate = 1;
                $i++;

                $arr_merchants = json_decode(json_encode($rs_merchants), TRUE);
                unset($rs_merchants);
                self::putTradeStatDay_byMerchant($arr_merchants);
            } else {
                $continue_calculate = 0;
            }
        } while ($continue_calculate);
    }

    public function putTradeStatDay_byMerchant($array_merchants){

            $day = strtotime(date('Y-m-d 00:00:00')) - 24 * 3600;

            $model = new TradeStatDay();

            $todayStart = date("Y-m-d 00:00:00", strtotime("-1 day"));

            $todayEnd = date("Y-m-d 23:59:59", strtotime("-1 day"));

                //商户当日交易额
                $amount = \DB::table('trade')
                    ->select(\DB::raw('merchant_id,sum(amount) as num'))
                    ->where(array('pay_status'=>1,'trade_type'=>1))
                    ->where('created_time','>=',$todayStart)
                    ->where('created_time','<=',$todayEnd)
                    ->whereIn('merchant_id',$array_merchants)
                    ->groupBy('merchant_id')
                    ->get();

                $amount = json_decode(json_encode($amount), TRUE);

                if(!empty($amount)){

                    foreach ($amount as $key=>$val){

                        $arr_data[$val['merchant_id']]['total_day'] = isset($val['num'])?$val['num']:0;
                    }
                    unset($amount);
                }

                //商户总交易额
                $total = \DB::table('trade')
                    ->select(\DB::raw('merchant_id,sum(amount) as num'))
                    ->where(array('pay_status'=>1,'trade_type'=>1))
                    ->where('created_time','<=',$todayEnd)
                    ->whereIn('merchant_id',$array_merchants)
                    ->groupBy('merchant_id')
                    ->get();

                $total = json_decode(json_encode($total), TRUE);

                if(!empty($total)){

                    foreach ($total as $key=>$val){

                        $arr_data[$val['merchant_id']]['total'] = isset($val['num'])?$val['num']:0;
                    }
                    unset($total);
                }

                //商户当日收入
                $income = \DB::table('trade')
                    ->select(\DB::raw('merchant_id,sum(amount) as num'))
                    ->where('pay_status','=',1)
                    ->where('created_time','>=',$todayStart)
                    ->where('created_time','<=',$todayEnd)
                    ->whereIn('merchant_id',$array_merchants)
                    ->groupBy('merchant_id')
                    ->get();

                $income = json_decode(json_encode($income), TRUE);

                if(!empty($income)){

                    foreach ($income as $key=>$val){

                        $arr_data[$val['merchant_id']]['day_income'] = isset($val['num'])?$val['num']:0;
                    }
                    unset($income);
                }

                //商户总收入
                $total_income = \DB::table('trade')
                    ->select(\DB::raw('merchant_id,sum(amount) as num'))
                    ->where('pay_status','=',1)
                    ->where('created_time','<=',$todayEnd)
                    ->whereIn('merchant_id',$array_merchants)
                    ->groupBy('merchant_id')
                    ->get();

                $total_income = json_decode(json_encode($total_income), TRUE);

                if(!empty($total_income)){

                    foreach ($total_income as $key=>$val){

                        $arr_data[$val['merchant_id']]['total_income'] = isset($val['num'])?$val['num']:0;
                    }
                    unset($total_income);
                }

                //商户当日GMV_trade（表）
                $gmv = \DB::table('trade')
                    ->select(\DB::raw('merchant_id,sum(amount) as num'))
                    ->where('created_time','>=',$todayStart)
                    ->where('created_time','<=',$todayEnd)
                    ->whereIn('merchant_id',$array_merchants)
                    ->groupBy('merchant_id')
                    ->get();

                $gmv = json_decode(json_encode($gmv), TRUE);

                if(!empty($gmv)){

                    foreach ($gmv as $key=>$val){

                        $arr_data[$val['merchant_id']]['day_gmv'] = isset($val['num'])?$val['num']:0;
                    }
                    unset($gmv);
                }

                //商户总GMV_trade（表）
                $total_gmv = \DB::table('trade')
                    ->select(\DB::raw('merchant_id,sum(amount) as num'))
                    ->where('created_time','<=',$todayEnd)
                    ->whereIn('merchant_id',$array_merchants)
                    ->groupBy('merchant_id')
                    ->get();

                $total_gmv = json_decode(json_encode($total_gmv), TRUE);

                if(!empty($total_gmv)){

                    foreach ($total_gmv as $key=>$val){

                        $arr_data[$val['merchant_id']]['total_gmv'] = isset($val['num'])?$val['num']:0;
                    }
                    unset($total_gmv);
                }

                //商户当日GMV_normal（Order表）
                $gmv_normal = \DB::table('order_info')
                    ->select(\DB::raw('merchant_id,sum(goods_amount + shipment_fee ) as num'))
                    ->where(array('is_valid'=>1,'pay_status'=>1))
                    ->where('created_time','>=',$todayStart)
                    ->where('created_time','<=',$todayEnd)
                    ->whereIn('merchant_id',$array_merchants)
                    ->groupBy('merchant_id')
                    ->get();

                $gmv_normal = json_decode(json_encode($gmv_normal), TRUE);

                if(!empty($gmv_normal)){

                    foreach ($gmv_normal as $key=>$val){

                        $arr_data[$val['merchant_id']]['order_normal_day_gmv'] = isset($val['num'])?$val['num']:0;
                    }
                    unset($gmv_normal);
                }

                //商户总GMV_normal（Order表）
                $total_gmv_normal = \DB::table('order_info')
                    ->select(\DB::raw('merchant_id,sum(goods_amount + shipment_fee ) as num'))
                    ->where(array('is_valid'=>1,'pay_status'=>1))
                    ->where('created_time','<=',$todayEnd)
                    ->whereIn('merchant_id',$array_merchants)
                    ->groupBy('merchant_id')
                    ->get();

                $total_gmv_normal = json_decode(json_encode($total_gmv_normal), TRUE);

                if(!empty($total_gmv_normal)){

                    foreach ($total_gmv_normal as $key=>$val){

                        $arr_data[$val['merchant_id']]['total_order_normal_gmv'] = isset($val['num'])?$val['num']:0;
                    }
                    unset($total_gmv_normal);
                }

                //商户当日GMV_wan（Order表）
                $gmv_wan = \DB::table('order_info')
                    ->select(\DB::raw('merchant_id,sum(goods_amount + shipment_fee ) as num'))
                    ->where('is_valid','=',1)
                    ->where('goods_amount','<',50000)
                    ->where('shipment_fee','<',1000)
                    ->where('created_time','>=',$todayStart)
                    ->where('created_time','<=',$todayEnd)
                    ->whereIn('merchant_id',$array_merchants)
                    ->groupBy('merchant_id')
                    ->get();

                $gmv_wan = json_decode(json_encode($gmv_wan), TRUE);

                if(!empty($gmv_wan)){

                    foreach ($gmv_wan as $key=>$val){

                        $arr_data[$val['merchant_id']]['order_wan_day_gmv'] = isset($val['num'])?$val['num']:0;
                    }
                    unset($gmv_wan);
                }

                //商户当日GMV_wan（Order表）
                $total_gmv_wan = \DB::table('order_info')
                    ->select(\DB::raw('merchant_id,sum(goods_amount + shipment_fee ) as num'))
                    ->where('is_valid','=',1)
                    ->where('goods_amount','<',50000)
                    ->where('shipment_fee','<',1000)
                    ->where('created_time','<=',$todayEnd)
                    ->whereIn('merchant_id',$array_merchants)
                    ->groupBy('merchant_id')
                    ->get();

                $total_gmv_wan = json_decode(json_encode($total_gmv_wan), TRUE);

                if(!empty($total_gmv_wan)){

                    foreach ($total_gmv_wan as $key=>$val){

                        $arr_data[$val['merchant_id']]['total_order_wan_gmv'] = isset($val['num'])?$val['num']:0;
                    }
                    unset($total_gmv_wan);
                }

                if(!empty($arr_data)){

                    foreach ($arr_data as $key=>$val){

                        $putdata = array(
                            'merchant_id' => $key,
                            'day_time' => $day,
                            'day_income' => isset($val['day_income'])?$val['day_income']:0,
                            'total_income' => isset($val['total_income'])?$val['total_income']:0,
                            'total_day' => isset($val['total_day'])?$val['total_day']:0,
                            'total' => isset($val['total'])?$val['total']:0,
                            'day_gmv' => isset($val['day_gmv'])?$val['day_gmv']:0,
                            'total_gmv' => isset($val['total_gmv'])?$val['total_gmv']:0,
                            'order_normal_day_gmv' => isset($val['order_normal_day_gmv'])?$val['order_normal_day_gmv']:0,
                            'total_order_normal_gmv' => isset($val['total_order_normal_gmv'])?$val['total_order_normal_gmv']:0,
                            'order_wan_day_gmv' => isset($val['order_wan_day_gmv'])?$val['order_wan_day_gmv']:0,
                            'total_order_wan_gmv' =>isset($val['total_order_wan_gmv'])?$val['total_order_wan_gmv']:0,
                        );

                        $data = '';

                        $data = $model->where(array('merchant_id' => $key, 'day_time' => $day))->first();

                        if ($data) {
                            //本地计算后的数据和环境中原有程序计算的数据对比
                            foreach ($putdata as $key1=>$val1){

                                if($val1!=$data[$key1]){

                                    echo $key.' '.$key1.':'.$val1.'_'.$data[$key1].'<br />';
                                }
                            }

                            $where = array();

                            $where[] = array('column' => 'day_time', 'value' => $day, 'operator' => '=');

                            $where[] = array('column' => 'merchant_id', 'value' => $key, 'operator' => '=');

                            $result = $model->update_data_by_where($day, $key, $where, $putdata);

                        } else {

                            $result = $model->insert_data($putdata);
                        }
                    }
                }

        }


    //统计当日交易额
    public function get_daily_trades($merchant_id,$todayStart,$todayEnd){
        $amounts = Trade::where(array('merchant_id'=>$merchant_id,'pay_status'=>1,'trade_type'=>1))
            ->where('created_time','>=',$todayStart)
            ->where('created_time','<=',$todayEnd)
            ->sum('amount');
        return $amounts;
    }

    //统计当日收入
    public function get_daily_income($merchant_id,$todayStart,$todayEnd){
        $income = Trade::where(array('merchant_id'=>$merchant_id,'pay_status'=>1))
            ->where('created_time','>=',$todayStart)
            ->where('created_time','<=',$todayEnd)
            ->sum('amount');
        return $income;
    }

    //统计累计交易额
    public function get_total_trades($merchant_id,$todayEnd){
        $amounts = Trade::where(array('merchant_id'=>$merchant_id,'pay_status'=>1,'trade_type'=>1))
            ->where('created_time','<=',$todayEnd)
            ->sum('amount');
        return $amounts;
    }

    //统计累计收入
    public function get_total_income($merchant_id,$todayEnd){
        $amounts = Trade::where(array('merchant_id'=>$merchant_id,'pay_status'=>1))
            ->where('created_time','<=',$todayEnd)
            ->sum('amount');
        return $amounts;
    }

    //统计当日GMV_trade
    public function get_daily_gmv_trade($merchant_id,$todayStart,$todayEnd){
        $gmvs = Trade::where('merchant_id','=',$merchant_id)
            ->where('created_time','>=',$todayStart)
            ->where('created_time','<=',$todayEnd)
            ->sum('amount');
        return $gmvs;
    }

    //统计当日之前累计GMV_trade
    public function get_gmv_trade($merchant_id,$todayEnd){
        $gmvs = Trade::where('merchant_id','=',$merchant_id)
            ->where('created_time','<=',$todayEnd)
            ->sum('amount');
        return $gmvs;

    }

    //统计当日GMV_normal
    public function get_daily_gmv_normal($merchant_id,$todayStart,$todayEnd){

        $fields = "sum(goods_amount + shipment_fee ) as gmv";

        $sum = OrderInfo::select(\DB::raw($fields))->where(array('merchant_id'=>$merchant_id,'is_valid'=>1,'pay_status'=>1))
            ->where('created_time','>=',$todayStart)
            ->where('created_time','<=',$todayEnd)->get();
        $gmv = $sum[0]['gmv'];

        return $gmv;
    }

    //统计当日GMV_normal
    public function get_gmv_normal($merchant_id,$todayEnd){

        $fields = "sum(goods_amount + shipment_fee ) as gmv";

        $sum = OrderInfo::select(\DB::raw($fields))->where(array('merchant_id'=>$merchant_id,'is_valid'=>1,'pay_status'=>1))
            ->where('created_time','<=',$todayEnd)->get();

        $gmv = $sum[0]['gmv'];

        return $gmv;
    }

    //统计当日GMV_wan
    public function get_daily_gmv_wan($merchant_id,$todayStart,$todayEnd){

        $fields = "sum(goods_amount + shipment_fee ) as gmv";

        $sum = OrderInfo::select(\DB::raw($fields))->where(array('merchant_id'=>$merchant_id,'is_valid'=>1))
            ->where('goods_amount','<',50000)
            ->where('shipment_fee','<',1000)
            ->where('created_time','>=',$todayStart)
            ->where('created_time','<=',$todayEnd)->get();
        $gmv = $sum[0]['gmv'];

        return $gmv;
    }

    //统计当日之前累计GMV_wan
    public function get_gmv_wan($merchant_id,$todayEnd){

        $fields = "sum(goods_amount + shipment_fee ) as gmv";

        $sum = OrderInfo::select(\DB::raw($fields))->where(array('merchant_id'=>$merchant_id,'is_valid'=>1))
            ->where('goods_amount','<',50000)
            ->where('shipment_fee','<',1000)
            ->where('created_time','<=',$todayEnd)->get();

        $gmv = $sum[0]['gmv'];

        return $gmv;

    }
}
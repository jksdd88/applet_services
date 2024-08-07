<?php

/*
 * 交易统计
 * zhangyu1@dodoca.com
 *
 */

namespace App\Services;

use App\Models\Trade;
use App\Models\TradeStatsSum;

class TradeSumService {

    protected $tradestatssum;

    public function __construct(TradeStatsSum $tradestatssum) {

        $this->tradestatssum = $tradestatssum;
    }
    public function getTradeSum(){

        for($i=1;$i<=60;$i++){

            $day = strtotime(date('Y-m-d 00:00:00'))-($i*24)*3600;      //昨天

            $yesstart = date("Y-m-d 00:00:00",strtotime('-'.$i.'day'));

            $yesend = date("Y-m-d 23:59:59",strtotime('-'.$i.'day'));

            //昨日交易额
            $amount = $this->get_daily_trades($yesstart,$yesend);

            if(!$amount){

                $amount = 0;
            }
            //昨日退款金额
            $refund = $this->get_daily_refund($yesstart,$yesend);

            if(!$refund) {

                $refund = 0;
            }

            //昨日之前总交易额
            $all_amount = $this->get_trades($yesstart);

            //昨日之前总退款额
            $all_refund = $this->get_refund($yesend);

            if($all_amount){
                //累计总交易额
                $total = $all_amount + $amount;

            }else{

                $total = $amount;
            }

            //当日收入
            $income = $amount + $refund;

            //累计总收入
            $all_income = $all_amount + $all_refund;


            if($all_income){
                //累计总收入
                $total_income = $all_income + $income;

            }else{

                $total_income = $income;
            }

            $tradesumdata = array(                //插入交易总表数据
                'day_time'=>$day,
                'day_income'=>$income,
                'total_income'=>$total_income,
                'total_day'=>$amount,
                'total'=>$total,
            );

            $tradesumdaily = $this->tradestatssum->where("day_time",$day)->first();

            if(!$tradesumdaily){

                $tradesumdata['created_time'] = date("Y-m-d H:i:s",strtotime("-".($i-1)." day"));

                $sumres = $this->tradestatssum->insert($tradesumdata);
            }
        }
    }

    //统计当日交易额
    public function get_daily_trades($start,$end){
        $todayStart = $start;
        $todayEnd = $end;
        $amount = 0;
        $amounts = Trade::select('amount')
            ->where('pay_status','=',1)
            ->where('trade_type','=',1)
            ->where('created_time','>=',$todayStart)
            ->where('created_time','<=',$todayEnd)
            ->get();
        foreach($amounts as $val){
            $amount += abs($val['amount']);
        }
        return $amount;
    }

    //统计当日退款金额
    public function get_daily_refund($start,$end){
        $todayStart = $start;
        $todayEnd = $end;
        $refund = 0;
        $refunds = Trade::select('amount')
            ->where('trade_type','=',2)
            ->where('pay_status','=',1)
            ->where('created_time','>=',$todayStart)
            ->where('created_time','<=',$todayEnd)
            ->get();
        foreach($refunds as $val){
            $refund += $val['amount'];
        }
        return $refund;
    }

    //统计当日之前总交易额
    public function get_trades($start){
        $todayStart = $start;
        $amount = 0;
        $amounts = Trade::select('amount')
            ->where('pay_status','=',1)
            ->where('trade_type','=',1)
            ->where('created_time','<',$todayStart)
            ->get();
        foreach($amounts as $val){
            $amount += abs($val['amount']);
        }
        return $amount;
    }

    //统计当日之前总退款金额
    public function get_refund($start){
        $todayStart = $start;
        $refund = 0;
        $refunds = Trade::select('amount')
            ->where('trade_type','=',2)
            ->where('pay_status','=',1)
            ->where('created_time','<',$todayStart)
            ->get();
        foreach($refunds as $val){
            $refund += $val['amount'];
        }
        return $refund;
    }

}

<?php

/**
 * 统计当日交易数据
 *
 * @package default
 * @author zhangyu
 **/

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Utils\SendMessage;
use App\Models\TradeStatDay;
use App\Models\TradeStatsSum;

class TradeStatsSumDay extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:TradeStatsSumDay';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '统计当日交易总数据';


    /**
     * The model
     */
    protected $tradestatday;

    protected $tradestatssum;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(TradeStatDay $tradestatday,TradeStatsSum $tradestatssum)
    {
        parent::__construct();
        $this->tradestatday = $tradestatday;
        $this->tradestatssum = $tradestatssum;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle(){

        try{
            for($i=1;$i<=7;$i++) {

                $day = strtotime(date('Y-m-d 00:00:00')) - ($i * 24) * 3600;      //昨天

                $fields = "sum(day_income) as day_income,sum(total_income) as total_income,
                        sum(total_day) as total_day,sum(total) as total,
                        sum(day_gmv) as day_gmv,sum(total_gmv) as total_gmv,
                        sum(order_normal_day_gmv) as order_normal_day_gmv,sum(total_order_normal_gmv) as total_order_normal_gmv,
                        sum(order_wan_day_gmv) as order_wan_day_gmv,sum(total_order_wan_gmv) as total_order_wan_gmv";

                $trade_detail = TradeStatDay::select(\DB::raw($fields))->where(array('day_time'=>$day))->get();


                $income = $trade_detail[0]['day_income'];

                $total_income = $trade_detail[0]['total_income'];

                $amount = $trade_detail[0]['total_day'];

                $total = $trade_detail[0]['total'];

                $day_gmv = $trade_detail[0]['day_gmv'];

                $total_gmv = $trade_detail[0]['total_gmv'];

                $order_normal_day_gmv = $trade_detail[0]['order_normal_day_gmv'];

                $total_order_normal_gmv = $trade_detail[0]['total_order_normal_gmv'];

                $order_wan_day_gmv = $trade_detail[0]['order_wan_day_gmv'];

                $total_order_wan_gmv = $trade_detail[0]['total_order_wan_gmv'];

                $tradesumdata = array(                //插入交易总表数据
                    'day_time' => $day,
                    'day_income' => $income,
                    'total_income' => $total_income,
                    'total_day' => $amount,
                    'total' => $total,
                    'day_gmv' => $day_gmv,
                    'total_gmv' =>$total_gmv,
                    'order_normal_day_gmv'=>$order_normal_day_gmv,
                    'total_order_normal_gmv'=>$total_order_normal_gmv,
                    'order_wan_day_gmv'=>$order_wan_day_gmv,
                    'total_order_wan_gmv'=>$total_order_wan_gmv,
                );

                $tradesumdaily = $this->tradestatssum->where("day_time", $day)->first();

                if(!$tradesumdaily) {

                    $tradesumdata['created_time'] = date("Y-m-d H:i:s",strtotime("-".($i-1)." day"));

                    $sumres = $this->tradestatssum->insert($tradesumdata);
                }else{

                    $updatedata = array(
                        'day_income' => $income,
                        'total_income' => $total_income,
                        'total_day' => $amount,
                        'total' => $total,
                        'day_gmv' => $day_gmv,
                        'total_gmv' =>$total_gmv,
                        'order_normal_day_gmv'=>$order_normal_day_gmv,
                        'total_order_normal_gmv'=>$total_order_normal_gmv,
                        'order_wan_day_gmv'=>$order_wan_day_gmv,
                        'total_order_wan_gmv'=>$total_order_wan_gmv,
                    );

                    $sumres = $this->tradestatssum->where("day_time", $day)->update($updatedata);
                }
            }
        }
        catch (\Exception $e)
        {
            SendMessage::send_sms('15651778032', '统计当日交易总数据脚本异常，请及时查看！', '6', 50); //短信通知 -》zhangyu
        }

    }
}

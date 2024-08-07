<?php

/**
 * 统计当日订单数据
 *
 * @package default
 * @author zhangyu
 **/

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Utils\SendMessage;
use App\Models\OrderDaily;
use App\Models\OrderSumDaily;

class OrderSumTodayStatics extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:OrderSumTodayStatics';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '统计当日订单总数据';


    /**
     * The model
     */
    protected $ordersumdaily;

    protected $orderdaily;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct( OrderDaily $orderdaily, OrderSumDaily $ordersumdaily)
    {
        parent::__construct();
        $this->orderdaily = $orderdaily;
        $this->ordersumdaily = $ordersumdaily;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle(){

        try{
            for($i=1;$i<=7;$i++) {

                $yestoday = date("Ymd", strtotime("-" . $i . " day"));

                $fields = "sum(total_day) as total_day,sum(total) as total,
                        sum(success_num_day) as success_num_day,sum(success_num) as success_num,
                        sum(send_num_day) as send_num_day,sum(send_num) as send_num,
                        sum(total_pay_day) as total_pay_day,sum(total_pay) as total_pay";

                $order_detail = OrderDaily::select(\DB::raw($fields))->where(array('day_time'=>$yestoday))->get();

                $total_day = $order_detail[0]['total_day'];

                $total = $order_detail[0]['total'];

                $success_num_day = $order_detail[0]['success_num_day'];

                $success_num = $order_detail[0]['success_num'];

                $send_num_day = $order_detail[0]['send_num_day'];

                $send_num = $order_detail[0]['send_num'];

                $total_pay_day = $order_detail[0]['total_pay_day'];

                $total_pay = $order_detail[0]['total_pay'];

                $ordersumdata = array(                //插入订单总表数据
                    'total_day' => $total_day,
                    'total' => $total,
                    'success_num_day' => $success_num_day,
                    'success_num' => $success_num,
                    'send_num_day' => $send_num_day,
                    'send_num' => $send_num,
                    'total_pay_day'=>$total_pay_day,
                    'total_pay'=>$total_pay,
                    'day_time' => $yestoday,
                );

                $ordersumdaily = $this->ordersumdaily->where("day_time", $yestoday)->first();

                if (!$ordersumdaily) {

                    $ordersumdata['created_time'] = date("Y-m-d H:i:s",strtotime("-".($i-1)." day"));

                    $sumres = $this->ordersumdaily->insert($ordersumdata);
                } else {
                    if($ordersumdaily->total_day != $total_day)
                    {
                        $sumres = $this->ordersumdaily->where('day_time', $yestoday)->update($ordersumdata);
                    }
                }
            }
        }
        catch (\Exception $e)
        {
            SendMessage::send_sms('15651778032', '统计当日订单总数据脚本异常，请及时查看！', '6', 50); //短信通知 -》zhangyu
        }

    }
}

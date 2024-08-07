<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018-01-16
 * Time: 下午 02:59
 */
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Utils\SendMessage;
use App\Models\OrderHourDaily;
use App\Models\OrderInfo;

class OrderHourDailyStatistics extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:OrderHourDailyStatistics';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '统计昨日订单每小时统计';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        try {
            for ($i = 150; $i >= 1; $i--) {
                $daily = date("Ymd", strtotime("-" . $i . " day"));
                $search_daily_start = date("Y-m-d", strtotime("-" . $i . " day")) . " 00:00:00";
                $search_daily_end = date("Y-m-d", strtotime("-" . $i . " day")) . " 23:59:59";

                $order_query=OrderHourDaily::select("id");
                $order_query->where('day_time','=',$daily);
                $orders=$order_query->count();
                if($orders){
                    continue;
                }
                $fields = 'DISTINCT order_sn,merchant_id,created_time';
                $query = OrderInfo::select(\DB::raw($fields));
                $query->where('created_time','>=',$search_daily_start);
                $query->where('created_time','<=',$search_daily_end);
                $query->orderBy('created_time','asc');
                $list = $query->get()->toArray();

                if($list){
                    $create_data = array();
                    foreach ($list as $key=>$val){
                        for($hour=0;$hour<=23;$hour++){
                            $hour_start = date("Y-m-d H", strtotime("+" . $hour . " hour",strtotime($daily))).":00:00";
                            $hour_end = date("Y-m-d H", strtotime("+" . $hour . " hour",strtotime($daily))).":59:59";

                            if($val['created_time']>=$hour_start && $val['created_time']<=$hour_end){
                                if(!isset($create_data[$val['merchant_id']][$hour])){
                                    $create_data[$val['merchant_id']][$hour] = 1;
                                }else{
                                    $create_data[$val['merchant_id']][$hour] ++;
                                }
                            }
                        }

                    }
                    if($create_data){
                        foreach ($create_data as $key=>$val){
                            foreach ($val as $k=>$v){
                                $insert_data['merchant_id'] = $key;
                                $insert_data['day_time'] = $daily;
                                if($k<10){
                                    $insert_data['hour'] = "0".$k;
                                }else{
                                    $insert_data['hour'] = $k;
                                }
                                $insert_data['order_today_total'] = $v;
                                $insert_data['created_time'] = date("Y-m-d H:i:s");
                                OrderHourDaily::insert($insert_data);

                            }
                        }
                    }
                }
            }
        }catch (Exception $e) {
            SendMessage::send_sms('18300689297', '统计昨日订单每小时统计脚本异常，请及时查看！', '6', 50);
        }
    }
}
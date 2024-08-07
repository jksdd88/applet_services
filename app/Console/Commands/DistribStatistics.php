<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\DistribDaily;
use App\Models\DistribPartner;
use App\Models\DistribOrder;
use App\Models\Merchant;
use Illuminate\Support\Facades\DB;
use App\Utils\CommonApi;
class DistribStatistics extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:DistribStatistics';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '用于统计推客数据';

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
            //每天时间区间为00:00:00-23:59:59
            $yesterday_short_str = date('Y-m-d',strtotime('-1 day')); //昨日时间短格式
            $today_short_str = date('Y-m-d'); //今天日时间短格式
            //清除数据
            DistribDaily::where('day_time',$yesterday_short_str)->delete();
            DistribPartner::select('merchant_id')
                ->groupBy('merchant_id')
                 ->chunk(50,function($merchant_list) use($today_short_str,$yesterday_short_str){
                     //截止到昨日的 总订单额,总佣金,总订单数
                     $data = [];
                     foreach($merchant_list as $k=>$v){
                         $data[$k] = DistribOrder::select(DB::raw('sum(order_amount) as order_money_sum,sum(total_comission) as comission_sum, count(*) as order_sum' ))
                             ->whereDate('created_time','<',$today_short_str)
                             ->where('merchant_id',$v->merchant_id)->first()->toArray();
                         //截止到昨日总推客数
                         $data[$k]['distrib_sum'] = DistribPartner::where('merchant_id',$v->merchant_id)
                             ->whereIn('status',[1,2])
                             ->whereDate('check_time','<',$today_short_str)
                             ->count();
                         $data[$k]['merchant_id'] = $v->merchant_id;
                         $data[$k]['day_time'] = $yesterday_short_str;//统计哪天的数据
                     }
                     //插入统计表
                     DistribDaily::insert($data);
                 });

            //循环七日数据 订单额,佣金额,订单数,推客数
            for($i=1;$i<=7;$i++){
                $short_data = date('Y-m-d',strtotime('-'.$i.' day'));
                DistribOrder::select(DB::raw('sum(order_amount) as order_money_day,sum(total_comission) as comission_day, count(*) as order_day, merchant_id' ))
                    ->whereDate('created_time','=',$short_data)
                    ->groupBy('merchant_id')
                    ->chunk(50,function($today_list) use($short_data){
                        foreach ($today_list->toArray() as $v){
                            //插入(昨日订单额,佣金额,订单数);
                            DistribDaily::where(['merchant_id'=>$v['merchant_id'],'day_time'=>$short_data])->update($v);
                        }
                    });

                DistribPartner::selectRaw('count(1) as count ,merchant_id')
                ->wherein('status',[1,2])
                ->where(function($query)use($short_data){
                    $query->whereDate('created_time','=',$short_data)//该日申请
                          ->orWhere(function($qr)use($short_data){
                            $qr->WhereDate('check_time','=',$short_data);//该日审核
                          });
                })
                ->groupBy('merchant_id')
                ->chunk(50,function($distrib_list)use($short_data){
                    foreach($distrib_list->toArray() as $v){
                        DistribDaily::where(['merchant_id'=>$v['merchant_id'],'day_time'=>$short_data])->update(['distrib_day'=>$v['count']]);
                    }
                });
            }
        }
        catch (\Exception $e)
        {
            $errData = [
                'activity_id' => 0,
                'data_type' => 'distrib_command',
                'content' => '推客脚本异常,信息:'.$e->getMessage().', 行数:'.$e->getLine(),
            ];
            CommonApi::errlog($errData);
            SendMessage::send_sms('15333777932', '推客脚本异常，请及时查看！', '6', 50); //短信通知 ->任瑞琪
        }
    }
}

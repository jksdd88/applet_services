<?php

/**
 * 预约服务提醒
 *
 * @package default
 * @author songyongshang@dodoca.com
 **/

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Foundation\Bus\DispatchesJobs;
use App\Models\OrderAppt;
use App\Models\UserLog;
use App\Utils\CacheKey;
use Illuminate\Support\Facades\Cache;
use App\Jobs\WeixinMsgJob;


class ApptNotice extends Command
{
    use DispatchesJobs;
    
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:ApptNotice';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '亲，请提前安排好时间哦~';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct() {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        OrderAppt::where(['order_appt.hexiao_status'=> 0,'order_appt.pay_status'=> 1])
                ->where('order_appt.appt_date', '=', date('Y-m-d'))
                ->chunk(100, function($list){
                    if($list) {
                        $dump = [];
                        foreach($list as $row){
                            
                            $cachekey = CacheKey::wait_apptnotice_msg($row['id']);
                            //Cache::forget($cachekey);
                            $ccdata = Cache::get($cachekey);
                            
                            //----------日志 start----------
                            $data_UserLog['merchant_id']=$row['merchant_id'];
                            $data_UserLog['user_id']=0;
                            $data_UserLog['type']=47;
                            $data_UserLog['url']='merchant/merchant.json';
                            $data_UserLog['content']=json_encode(array(
                                'order_appt'=>$row['id'],
                            ));
                            $data_UserLog['ip']=get_client_ip();
                            $data_UserLog['created_time']=date('Y-m-d H:i:s');
                            $data_UserLog['updated_time']=date('Y-m-d H:i:s');
                            UserLog::insertGetId($data_UserLog);
                            //----------日志 end----------
                            
                            if(!$ccdata) {
                                //发送到队列
                                $job = new WeixinMsgJob([
                                    'merchant_id'=>$row['merchant_id'],
                                    'member_id'=>$row['member_id'],
                                    'order_id'=>$row['order_id'],
                                    'goods_title'=>$row['goods_title'],
                                    'appt_string'=>$row['appt_string'],
                                    'store_name'=>$row['store_name'],
                                    'store_id'=>$row['store_id'],
                                    'type'=>'appointment'
                                ]);
                                $this->dispatch($job);
                            
                                $dump[] = $row['id'];
                                Cache::put($cachekey, $row['id'], 60);
                            }
                        }
                        print_r($dump);
                    }
        });
    }
}

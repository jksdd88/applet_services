<?php

/**
 * 待付款消息模板通知（提交订单10分钟未支付，发送待付款提醒）
 *
 * @package default
 * @author zhangchangchun@dodoca.com
 * */

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Support\Facades\Cache;
use App\Utils\CacheKey;
use App\Models\OrderInfo;
use App\Jobs\WeixinMsgJob;

class OrderWaitPay extends Command {
	
	use DispatchesJobs;
	
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:OrderWaitPay';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '待付款消息模板通知';

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
    public function handle() {
		OrderInfo::select(['id','merchant_id'])->where('expire_at','>',date('Y-m-d H:i:s',time()))->where('created_time','>',date('Y-m-d H:i:s',time()-1200))->where('created_time','<',date('Y-m-d H:i:s',time()-600))->where(['pay_status'=>0])->whereIn('status',[ORDER_SUBMIT,ORDER_TOPAY])->orderBy('id','asc')->chunk(100, function ($list) {
			if($list) {
				$dump = [];
				foreach($list as $key => $oinfo) {
					$cachekey = CacheKey::wait_pay_msg($oinfo['id']);
					$ccdata = Cache::get($cachekey);
					if(!$ccdata) {
						
						//发送到队列
						$job = new WeixinMsgJob(['order_id'=>$oinfo['id'],'merchant_id'=>$oinfo['merchant_id'],'type'=>'topay']);
						$this->dispatch($job);
						
						$dump[] = $oinfo['id'];
						Cache::put($cachekey, $oinfo['id'], 60);
					}
				}
				print_r($dump);
			}
		});
    }

}

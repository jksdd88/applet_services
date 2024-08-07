<?php

/**
 * 超时未付款订单关闭
 *
 * @package default
 * @author zhangchangchun@dodoca.com
 * */

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Foundation\Bus\DispatchesJobs;
use App\Utils\SendMessage;
use App\Models\OrderInfo;
use App\Jobs\OrderCancel;

class OrderOvertimeClose extends Command {
	
	use DispatchesJobs;
	
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:OrderOvertimeClose';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '超时订单关闭';

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
		OrderInfo::select(['id','merchant_id'])->where('expire_at','<',date('Y-m-d H:i:s',time()))->where(['pay_status'=>0])->whereIn('status',[ORDER_SUBMIT,ORDER_TOPAY])->orderBy('id','asc')->chunk(100, function ($list) {
			if($list) {
				$dump = [];
				foreach($list as $key => $oinfo) {
					$data = [
						'status'	=>	ORDER_AUTO_CANCELED,
						'explain'	=>	'超时系统取消',
					];
					$result = OrderInfo::update_data($oinfo['id'],$oinfo['merchant_id'],$data);
					if($result) {
						//发送到队列
						$job = new OrderCancel($oinfo['id'],$oinfo['merchant_id']);
						$this->dispatch($job);
					}
					$dump[] = $oinfo['id'];
				}
				print_r($dump);
			}
		});
    }

}

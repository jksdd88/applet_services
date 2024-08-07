<?php
/**
 * @ 订单自动确认收货（7+n日后）
 * @ author zhangchangchun@dodoca.com
 * @ time 2017-09-04
 */

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Foundation\Bus\DispatchesJobs;
use App\Utils\SendMessage;
use App\Models\OrderInfo;
use App\Models\MerchantSetting;

class OrderSureDelivery extends Command {
	
	use DispatchesJobs;
	
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:OrderSureDelivery';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '订单自动确认收货';

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
		OrderInfo::select(['id','merchant_id','shipments_time'])->where('shipments_time','>','0000-00-00')->where('shipments_time','<',date('Y-m-d H:i:s',time()-604800))->where(['status'=>ORDER_SEND])->orderBy('id','asc')->chunk(300, function ($list) {
			if($list) {
				$dump = [];
				$mrt_list = [];
				foreach($list as $key => $oinfo) {
					if(isset($mrt_list[$oinfo['merchant_id']])) {
						$auto_finished_time = (int)$mrt_list[$oinfo['merchant_id']];
					} else {
						$auto_finished_time = (int)MerchantSetting::where(['merchant_id'=>$oinfo['merchant_id']])->pluck('auto_finished_time');
						$mrt_list[$oinfo['merchant_id']] = (int)$auto_finished_time;					
					}
					if($auto_finished_time && (strtotime($oinfo['shipments_time'])+$auto_finished_time*86400)<time()) {
						$data = [
							'status'		=>	ORDER_SUCCESS,
							'finished_time'	=>	date("Y-m-d H:i:s"),
						];
						$result = OrderInfo::update_data($oinfo['id'],$oinfo['merchant_id'],$data);
						if($result) {
							$dump[] = $oinfo['id'];
						}
					}
				}
				print_r($dump);
			}
		});
    }

}

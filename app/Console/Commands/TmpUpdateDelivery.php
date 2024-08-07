<?php
/**
 * @ 更新订单表历史数据（order_info表appid）
 * @ author zhangchangchun@dodoca.com
 * @ time 2017-10-25
 */

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Foundation\Bus\DispatchesJobs;
use App\Models\OrderInfo;
use App\Models\OrderPackage;

class TmpUpdateDelivery extends Command {
	
	use DispatchesJobs;
	
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:TmpUpdateDelivery';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '修复批量发货未更新发货时间';

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
		OrderInfo::select(['id','merchant_id'])->where(['status'=>10,'pay_status'=>1,'shipments_time'=>'0000-00-00 00:00:00'])->chunk(10000, function ($list) {
			if($list) {
				$dump1 = [];
				$dump2 = [];
				foreach($list as $key => $oinfo) {
					if(!in_array($oinfo['id'],[97,138,159])) {
						//continue;
					}
					
					//获取订单发货时间
					$package = OrderPackage::select(['created_time'])->where(['order_id'=>$oinfo['id']])->orderBy('id','desc')->first();
					if($package) {
						$order_data = [
							'shipments_time'	=>	$package['created_time'],
						];
						
						if(strtotime($package['created_time'])<time()-7*24*3600) {	//7天前的订单自动完成
							$order_data['finished_time'] = date("Y-m-d H:i:s",strtotime($package['created_time'])+7*24*3600);
							$order_data['status'] = ORDER_SUCCESS;
							$dump1[] = $oinfo['id'];
						} else {
							$dump2[] = $oinfo['id'];
						}
						
						OrderInfo::update_data($oinfo['id'],$oinfo['merchant_id'],$order_data);
					}
				}
				echo implode(',',$dump1);
				echo '<br>';
				echo implode(',',$dump2);
			}
		});
    }

}

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
use App\Models\WeixinInfo;

class TmpUpdateAppid extends Command {
	
	use DispatchesJobs;
	
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:TmpUpdateAppid';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '更新订单表历史数据';

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
		/*OrderInfo::select(['id','merchant_id'])->chunk(200, function ($list) {
			if($list) {
				$dump = [];
				$weixin = [];
				foreach($list as $key => $oinfo) {
					$appid = isset($weixin[$oinfo['merchant_id']]) ? $weixin[$oinfo['merchant_id']] : '';
					if(!$appid) {
						$wxinfo = WeixinInfo::select(['appid'])->where(['merchant_id'=>$oinfo['merchant_id'],'status'=>'1'])->orderBy('id','asc')->first();
						if($wxinfo) {
							$weixin[$oinfo['merchant_id']] = $appid = $wxinfo['appid'];						
						} else {	//若已删除，选一个最新的appid
							$wxinfo = WeixinInfo::select(['appid'])->where(['merchant_id'=>$oinfo['merchant_id'],'status'=>'-1'])->orderBy('id','desc')->first();
							if($wxinfo) {
								$weixin[$oinfo['merchant_id']] = $appid = $wxinfo['appid'];
							}
						}
					}
					if($appid) {
						OrderInfo::update_data($oinfo['id'],$oinfo['merchant_id'],['appid'=>$appid]);
						$dump[] = $oinfo['id'];
					}
				}
				print_r($dump);
			}
		});*/
    }

}

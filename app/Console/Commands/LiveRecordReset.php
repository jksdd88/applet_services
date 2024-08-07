<?php
/**
 * 录播人次月底清零
 * @author wangshen@dodoca.com
 * @cdate 2018-5-17
 * 
 */
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\MerchantSetting;
use App\Services\MerchantService;

class LiveRecordReset extends Command {
	
	
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:LiveRecordReset';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '录播人次月底清零';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(MerchantService $merchantService) {
        parent::__construct();
        //商户服务类
        $this->merchantService = $merchantService;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle() {
		MerchantSetting::select(['id','merchant_id','live_record'])->where('live_record','>',0)->orderBy('id','asc')->chunk(300, function ($list) {
			if($list) {
				$dump = [];
				foreach($list as $key => $oinfo) {
				    
				    //减去直播余额、记录直播余额变化信息
				    //调用商家直播余额变动api
				    $live_balance_data = [
				        'merchant_id' => $oinfo['merchant_id'],                         //商户id
				        'ctype'       => 2,                                             //余额类型：1->直播包，2->录播包，3->云存储
				        'type'	      => 5,	                                            //变动类型：配置config/varconfig.php
				        'sum'		  => -1 * $oinfo['live_record'],		            //变动金额
				        'memo'		  => '月底清空录播包 '.$oinfo['live_record'].' 次',	    //备注
				    ];
				    
				    $livebalance_rs = $this->merchantService->changeLiveMoney($live_balance_data);
				    if($livebalance_rs) {
						$dump[] = $oinfo['merchant_id'];
					}
				}
				print_r($dump);
			}
		});
    }

}

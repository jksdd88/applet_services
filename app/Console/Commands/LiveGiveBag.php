<?php
/**
 * 收费版商户送一个直播包
 * @author wangshen@dodoca.com
 * @cdate 2018-5-18
 * 
 */
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Merchant;
use App\Models\LiveBalance;
use App\Services\MerchantService;

class LiveGiveBag extends Command {
	
	
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:LiveGiveBag';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '收费版商户送一个直播包';

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
		Merchant::select(['id','version_id'])->whereIn('version_id', [2,3,4,6])->orderBy('id','asc')->chunk(300, function ($list) {
			if($list) {
				$dump = [];
				foreach($list as $key => $oinfo) {
				    
				    //查询是否已有赠送记录
				    $balance_info = LiveBalance::where('merchant_id','=',$oinfo['id'])
                                                    ->where('type','=',11)
                                                    ->count();
				    
                    //无赠送记录才赠送                                
                    if($balance_info == 0){
                        //增加直播余额、记录直播余额变化信息
                        //调用商家直播余额变动api
                        $live_balance_data = [
                            'merchant_id' => $oinfo['id'],                         //商户id
                            'ctype'       => 1,                                    //余额类型：1->直播包，2->录播包，3->云存储
                            'type'	      => 11,	                               //变动类型：配置config/varconfig.php
                            'sum'		  => 1,		                               //变动金额
                            'memo'		  => '收费商户系统赠送直播包 1 个',	               //备注
                        ];
                        
                        $livebalance_rs = $this->merchantService->changeLiveMoney($live_balance_data);
                        if($livebalance_rs) {
                            $dump[] = $oinfo['id'];
                        }
                    }
				    
				}
				print_r($dump);
			}
		});
    }

}

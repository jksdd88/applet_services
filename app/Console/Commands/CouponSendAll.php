<?php
/**
 * 群发优惠券
 * @author wangshen@dodoca.com
 * @cdate 2018-7-4
 * 
 */
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\CouponSend;
use App\Models\Member;
use App\Services\CouponService;

class CouponSendAll extends Command {
	
	
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:CouponSendAll';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '群发优惠券';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(CouponService $couponService) {
        parent::__construct();
        $this->couponService = $couponService;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle() {
        //查询未执行的记录
		CouponSend::select(['id','merchant_id','coupon_id','status'])->where('status','=',1)->orderBy('id','asc')->chunk(300, function ($list) {
		    
			if($list) {
			    //先将状态全部改为执行中
			    foreach($list as $kk => $vv) {
			        CouponSend::update_data($vv['id'], $vv['merchant_id'], ['status' => 2]);
			    }
			    
			    $dump = [];
				foreach($list as $key => $val) {
				    
				    //查出商家下所有的会员id
				    Member::select(['id'])->where('merchant_id','=',$val['merchant_id'])->orderBy('id','DESC')->chunk(300, function ($memberlist) use($val) {
				        foreach($memberlist as $k => $v) {
				            
				            $param = [
				                'member_id' => $v['id'],
				                'merchant_id' => $val['merchant_id'],
				                'coupon_id' => $val['coupon_id'],
				                'channel' => 1, //发送渠道 1、后台派发 2、领取 3、新用户有礼
				            ];
				            
				            $this->couponService->giveMember($param);
				        }
				    });
				    
				    //状态改为执行完毕
				    $rs = CouponSend::update_data($val['id'], $val['merchant_id'], ['status' => 3]);
				    if($rs){
				        $dump[] = $val['id'];
				    }
				}
				print_r($dump);
			}
			
		});
		
    }
    

}

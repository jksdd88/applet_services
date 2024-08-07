<?php
/**
 * @ 订单自动完成（完成时间后7天）
 * @ author zhangchangchun@dodoca.com
 * @ time 2017-09-04
 */

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Foundation\Bus\DispatchesJobs;
use App\Utils\SendMessage;
use App\Models\OrderInfo;
use App\Models\OrderGoods;
use App\Models\OrderRefund;
use App\Models\Member;
use App\Jobs\VipCardAutoUpgrade;
use App\Jobs\DistribSettledComission;
use App\Services\CreditService;
use App\Services\DistribService;

class OrderFinish extends Command {
	
	use DispatchesJobs;
	
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:OrderFinish';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '订单自动完成';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(CreditService $CreditService) {
        parent::__construct();
		$this->CreditService = $CreditService;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle() {
		OrderInfo::select(['id','merchant_id','member_id','amount','order_sn','finished_time','is_finish'])->where(['is_finish'=>0])->where('finished_time','>','0000-00-00')->where('finished_time','<',date('Y-m-d H:i:s',time()-604800))->where(['status'=>ORDER_SUCCESS])->orderBy('id','asc')->chunk(100, function ($list) {
			if($list) {
				$dump = [];
				foreach($list as $key => $oinfo) {
					if($oinfo['is_finish']==1) {
						continue;
					}
					
					//有未完结的退款
					$is_continue = 0;
					$refundList = OrderRefund::select(['status'])->where(['order_id'=>$oinfo['id']])->get();
					if($refundList) {
						foreach($refundList as $refundInfo) {
							if(!in_array($refundInfo['status'],[REFUND_FINISHED,REFUND_CLOSE,REFUND_CANCEL,REFUND_MER_CANCEL])) {
								$is_continue = 1;
								break;
							}
						}
					}
					if($is_continue==1) {
						continue;
					}
					
					$data = [
						'is_finish'	=>	1,
					];
					$result = OrderInfo::update_data($oinfo['id'],$oinfo['merchant_id'],$data);
					if($result) {
						if($oinfo['amount']>0) {
							$refundamount = OrderRefund::where(['order_id'=>$oinfo['id'],'status'=>REFUND_FINISHED])->sum('amount');
							$amount = $oinfo['amount']-$refundamount;
							if($amount) {
								//增加累计消费金额，消费次数（实际支付金额-退款金额）
								Member::where(['id'=>$oinfo['member_id']])->increment('total_amount',$amount);
								//Member::where(['id'=>$oinfo['member_id']])->increment('purchased_count');
								Member::forgetCache($oinfo['member_id'],$oinfo['merchant_id']);
								
								//赠送积分(参与积分抵扣的商品)
								$real_pay_money = 0;
								$ordergoods = OrderGoods::select(['quantity','refund_quantity','pay_price'])->where(['order_id'=>$oinfo['id'],'is_givecredit'=>1])->get();
								if($ordergoods) {
									foreach($ordergoods as $kk => $orderginfo) {
										$orinfo_price = ($orderginfo['quantity']-$orderginfo['refund_quantity'])*($orderginfo['pay_price']/$orderginfo['quantity']);
										if($orinfo_price>0) {
											$real_pay_money += $orinfo_price;
										}
									}
									if($real_pay_money>0) {
										$oinfo['amount'] = $real_pay_money;
										$this->CreditService->giveCredit($oinfo['merchant_id'],$oinfo['member_id'],2,$oinfo);
									}
								}
								
								//发送到队列
								$this->dispatch(new VipCardAutoUpgrade($oinfo['member_id']));	//订单完成算会员
							}
						}
														
						//发放佣金
						$result = $this->dispatch(new DistribSettledComission($oinfo['id'],$oinfo['merchant_id']));		
						\Log::info('orderFinish-distrib:order_id->'.$oinfo['id'].',result->'.json_encode($result,JSON_UNESCAPED_UNICODE));
					}
					$dump[] = $oinfo['id'];
				}
				print_r($dump);
			}
		});
    }

}

<?php
/**
 * 订单退款队列
 * @author zhangchangchun@dodoca.com
 * $order_id 订单id
 */
namespace App\Jobs;

use App\Jobs\Job;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Bus\SelfHandling;
use Illuminate\Contracts\Queue\ShouldQueue;

use App\Services\OrderJobService;

class OrderRefund extends Job implements SelfHandling, ShouldQueue
{
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($refund)
    {
        $this->refund = $refund;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(OrderJobService $OrderJobService)
    {
        //退还积分、退款金额
		if($this->refund) {
			$OrderJobService->OrderRefundJob($this->refund);
		}
    }
	
}

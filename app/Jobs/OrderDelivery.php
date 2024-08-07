<?php
/**
 * 订单主动确认收货
 * @author zhangchangchun@dodoca.com
 * $order_id 订单id
 */
namespace App\Jobs;

use App\Jobs\Job;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Bus\SelfHandling;
use Illuminate\Contracts\Queue\ShouldQueue;

use App\Models\OrderInfo;
use App\Services\OrderJobService;

class OrderDelivery extends Job implements SelfHandling, ShouldQueue
{
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($order_id = 0, $merchant_id = 0)
    {
        $this->order_id = $order_id;
		$this->merchant_id = $merchant_id;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(OrderJobService $OrderJobService)
    {
        //送积分
		$order = OrderInfo::get_data_by_id($this->order_id,$this->merchant_id,'id,status,order_type,merchant_id,member_id');
		if($order) {
			$OrderJobService->OrderDeliveryJob($order);
		}
    }
	
}

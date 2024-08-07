<?php
/**
 *
 * 初始化推客订单
 * @author 王禹
 */
namespace App\Jobs;

use Illuminate\Contracts\Bus\SelfHandling;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Services\DistribService;

class DistribInitOrder extends Job implements SelfHandling, ShouldQueue
{
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($order_id, $merchant_id)
    {
        $this->order_id = $order_id;
		$this->merchant_id = $merchant_id;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        DistribService::initDistribOrder($this->order_id ,$this->merchant_id);
    }
	
}

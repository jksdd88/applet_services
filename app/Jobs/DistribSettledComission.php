<?php
/**
 *
 * 结算佣金
 * @author 王禹
 */
namespace App\Jobs;

use Illuminate\Contracts\Bus\SelfHandling;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Services\DistribService;

class DistribSettledComission extends Job implements SelfHandling, ShouldQueue
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
        DistribService::settledComission($this->order_id ,$this->merchant_id);
    }
	
}

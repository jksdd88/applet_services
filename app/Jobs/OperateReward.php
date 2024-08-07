<?php
/**
 * 营销活动发放奖励队列（邀请好友开店（授权成功）得商品上架数量）
 * @author changzhixian@dodoca.com
 * $act_type 活动类型
 * $merchant_id 商户id
 */
namespace App\Jobs;
use Illuminate\Contracts\Bus\SelfHandling;
use Illuminate\Contracts\Queue\ShouldQueue;

use App\Services\OperateRewardService;

class OperateReward extends Job implements SelfHandling, ShouldQueue
{
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($act_type, $merchant_id)
    {
        $this->act_type = $act_type;
		$this->merchant_id = $merchant_id;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(OperateRewardService $OperateRewardService)
    {
        //发放奖励
        $OperateRewardService->giveOperateReward($this->act_type,$this->merchant_id);
    }
	
}

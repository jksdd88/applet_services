<?php
/**
 *
 * C端用户行为采集
 * @author 王禹
 */
namespace App\Jobs;

use Illuminate\Contracts\Bus\SelfHandling;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Services\MemberBehaviorService;

class MemberBehaviorCollection extends Job implements SelfHandling, ShouldQueue
{
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($member_id, $merchant_id, $weapp_id, $appid, $type, $type_id = 0, $chat_group_id = '',
                                $distrib_member_id = 0,$share_member_id = 0)
    {
        $this->member_id = (int)$member_id;
        $this->merchant_id = (int)$merchant_id;
        $this->weapp_id = (int)$weapp_id;
        $this->appid = $appid;
        $this->type = (int)$type;
        $this->type_id = (int)$type_id;
        $this->chat_group_id = $chat_group_id;
        $this->distrib_member_id = (int)$distrib_member_id;
        $this->share_member_id = (int)$share_member_id;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        MemberBehaviorService::collection($this->member_id, $this->merchant_id, $this->weapp_id, $this->appid,
            $this->type, $this->type_id, $this->chat_group_id, $this->distrib_member_id, $this->share_member_id);
    }
	
}

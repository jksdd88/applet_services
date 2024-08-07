<?php

namespace App\Jobs;

use App\Jobs\Job;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Bus\SelfHandling;
use Illuminate\Contracts\Queue\ShouldQueue;

use App\Models\Member;
use App\Models\MemberCard;
use App\Services\VipcardService;
use Log;

class VipCardAutoUpgrade extends Job implements SelfHandling, ShouldQueue
{
    use InteractsWithQueue, SerializesModels;

    protected $member_id;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($member_id)
    {
        $this->member_id = $member_id;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(VipcardService $vipcardService)
    {
        \Log::info('会员卡自动升级member_id：'.$this->member_id);
        $vipcardService->autoUpgrade( $this->member_id );
    }
}

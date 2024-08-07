<?php

/**
 * 手动升级的会员卡到期后运行此自动升级程序
 *
 * @package default
 * @author guoqikai
 **/

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Member;
use App\Services\VipcardService;
use App\Utils\SendMessage;

class VipCardUpgrade extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:vipCardUpgrade';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '手动升级会员卡到期后执行自动升级';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(VipcardService $vipcardService)
    {
        parent::__construct();
        $this->vipcardService = $vipcardService;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        try {
            Member::select('id', 'member_card_overtime')->where('member_card_id', '>', 0)->where('member_card_overtime', '<>', '0000-00-00')->where('member_card_overtime', '<', date('Y-m-d'))->orderBy('id', 'asc')->chunk(100, function($lists){
                if($lists){
                    foreach($lists as $member){
                        $member_id = $member['id'];
                        $this->vipcardService->autoUpgrade( $member_id );
                    }
                }
            });
        } catch (\Exception $e) {
            SendMessage::send_sms('18721687252', '手动会员卡自动升级错误，请及时查看！', '6', 50);
        }
    }
}

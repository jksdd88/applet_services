<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\CreditDayService;

class CreditStatsDay extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:CreditStatsDay';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '统计每天积分';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct() {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle(CreditDayService $CreditDayService) {

        //更新商家今日积分
        try {

            $CreditDayService->putCredirStatDay();
        } catch (Exception $e) {
            SendMessage::send_sms('15824193323', '积分统计脚本异常，请及时查看！', '6', 50);
        }
    }

}

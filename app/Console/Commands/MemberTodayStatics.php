<?php

/**
 * 统计当日新增会员数和成交会员数
 *
 * @package default
 * @author shangyazhao
 **/

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\MemberDailyService;
use App\Utils\SendMessage;

class MemberTodayStatics extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:MemberTodayStatics';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '统计当日新增会员数和成交会员数';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle(MemberDailyService $memberDailyService)
    {
        $memberDailyService->putMemberStatDay();
    }
}

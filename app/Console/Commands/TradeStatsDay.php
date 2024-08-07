<?php

/** 统计每天交易额
 * Created by PhpStorm.
 * User: jiangnan
 * Date: 2017/9/13
 * Time: 14:20
 */
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\TradeStatDayService;


class TradeStatsDay extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:TradeStatsDay';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '统计每天交易额';

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
    public function handle(TradeStatDayService $TradeStatDayService)
    {

        //更新商家今日交易金额
        try {

            $TradeStatDayService->updateTrades();

        } catch (Exception $e) {
            SendMessage::send_sms('13052268638', '交易统计脚本异常，请及时查看！', '6', 50);
        }
    }
}


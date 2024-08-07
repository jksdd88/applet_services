<?php

/**
 * 统计当日订单数据
 *
 * @package default
 * @author shangyazhao
 **/

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\OrderDailyService;

class OrderTodayStatics extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:OrderTodayStatics';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '统计当日订单数据';

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
    public function handle(OrderDailyService $OrderDailyService)
    {
        $OrderDailyService->putOrderStatDay();
    }
}

<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017-12-27
 * Time: 下午 04:36
 */
namespace App\Console\Commands;

use App\Services\WeixinDataService;
use Illuminate\Console\Command;
use App\Utils\SendMessage;
use App\Models\XcxVisitDaily;
use App\Models\WeixinTemplate;
use App\Utils\Weixin\Statics;
use App\Services\WeixinService;
use App\Services\OrderDailyService;

class XcxVisitDailyStatistics extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:XcxVisitDailyStatistics';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '统计当日小程序访问趋势';

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

		\Log::info('command:XcxVisitDailyStatistics->start,data->'.date("Y-m-d H:i:s"));
        try{
            $weixindata = new WeixinDataService();
            $weixindata->readyGo('visitTrend');
            //统计当日订单数据
            $OrderDailyService->putOrderStatDay();
        }catch (Exception $e) {
            SendMessage::send_sms('18300689297', '统计当日小程序访问趋势脚本异常，请及时查看！', '6', 50);
        }
		\Log::info('command:XcxVisitDailyStatistics->start,data->'.date("Y-m-d H:i:s"));
    }
}
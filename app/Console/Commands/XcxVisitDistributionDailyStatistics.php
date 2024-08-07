<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017-12-28
 * Time: 上午 10:46
 */
namespace App\Console\Commands;

use App\Services\WeixinDataService;
use Illuminate\Console\Command;
use App\Utils\SendMessage;
use App\Models\XcxVisitDistributionDaily;
use App\Models\WeixinTemplate;
use App\Utils\Weixin\Statics;
use App\Services\WeixinService;

class XcxVisitDistributionDailyStatistics extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:XcxVisitDistributionDailyStatistics';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '统计当日小程序访问分布';

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
    public function handle()
    {
		\Log::info('command:XcxVisitDistributionDailyStatistics->start,data->'.date("Y-m-d H:i:s"));
        try {
            $weixindata = new WeixinDataService();
            $weixindata->readyGo('visitDistribution');
        } catch (Exception $e) {
            SendMessage::send_sms('18300689297', '统计当日小程序访问分布脚本异常，请及时查看！', '6', 50);
        }
		\Log::info('command:XcxVisitDistributionDailyStatistics->start,data->'.date("Y-m-d H:i:s"));
    }
}
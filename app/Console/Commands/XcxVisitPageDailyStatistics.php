<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017-12-27
 * Time: 下午 05:04
 */
namespace App\Console\Commands;

use App\Services\WeixinDataService;
use Illuminate\Console\Command;
use App\Utils\SendMessage;
use App\Models\XcxVisitPageDaily;
use App\Models\WeixinTemplate;
use App\Utils\Weixin\Statics;
use App\Services\WeixinService;

class XcxVisitPageDailyStatistics extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:XcxVisitPageDailyStatistics';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '统计当日小程序访问页面';

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
		\Log::info('command:XcxVisitPageDailyStatistics->start,data->'.date("Y-m-d H:i:s"));
        try{
            $weixindata = new WeixinDataService();
            $weixindata->readyGo('visitPage');
        }catch (Exception $e) {
            SendMessage::send_sms('18300689297', '统计当日小程序访问页面脚本异常，请及时查看！', '6', 50);
        }
		\Log::info('command:XcxVisitPageDailyStatistics->start,data->'.date("Y-m-d H:i:s"));
    }
}
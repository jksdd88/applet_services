<?php
/**
 * Created by PhpStorm.
 * User: lizhenheng
 * Date: 2017-12-15
 * Time: 下午 03:56
 */
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Utils\SendMessage;
use App\Models\XcxVersionDaily;

class XcxStatics extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:XcxStatics';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '统计小程序版本对应的商户数';

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
		\Log::info('command:XcxStatics->start,data->'.date("Y-m-d H:i:s"));
        try{
            for($i=10;$i>=1;$i--) {
                $daily_start = date("Ymd", strtotime("-" . $i . " day"));
                $daily_end = date("Y-m-d", strtotime("-" . ($i - 1) . " day")) . " 00:00:00";
                $starttime = date("Y-m-d", strtotime("-" . $i . " day")) . " 00:00:00";
                $version = config('version');
                $xcx = XcxVersionDaily::get_data_by_day($daily_start);
                if (!$xcx) {
                    foreach ($version as $key => $value) {
                        $alls = \DB::select("select count(1) as num from merchant where version_id=" . $key . " and created_time<:createtime", [':createtime' => $daily_end]);
                        $today_alls = \DB::select("select count(1) as num from merchant where version_id=" . $key . " and created_time<:createtime and created_time>=:starttime", [':createtime' => $daily_end, ':starttime' => $starttime]);
                        $data = array();
                        $data["day_time"] = $daily_start;
                        $data["version_id"] = $key;
                        $data["merchant_total"] = $alls[0]->num;
                        $data["merchant_total_today"] = $today_alls[0]->num;
                        $data["created_time"] = date("Y-m-d H:i:s");
                        XcxVersionDaily::insert($data);
                    }
                }
            }
        }catch (Exception $e) {
            SendMessage::send_sms('18300689297', '统计小程序版本对应的商户数脚本异常，请及时查看！', '6', 50);
        }
		\Log::info('command:XcxStatics->end,data->'.date("Y-m-d H:i:s"));
    }
}
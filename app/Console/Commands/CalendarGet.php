<?php

/**
 * 拉取本月与之后两个月的日历信息 获取法定节假日
 * @package default
 * @author 王禹
 **/

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Calendar;
use GuzzleHttp\Client;
use App\Utils\SendMessage;

class CalendarGet extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'calendar:get';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '获取日历';

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
        try {
            for ($i = 1; ($day = date('Y-m-d', strtotime(date('Y-m-01', strtotime(date('Y-m'))) . " +3 month -$i day"))) >= date("Y-m-01", strtotime(date('Y-m'))); $i++) {

                $day_date = Calendar::get_data_by_date($day);

                if (!$day_date) {

                    $headers = array("Authorization" => 'APPCODE ' . env('CALENDAR_APP_CODE'));
                    $calendarClient = new Client(['base_uri' => env('CALENDAR_SERVER'), 'headers' => $headers]);

                    $value['date'] = $day;
                    $api_url = '/calendar/query' . '?' . http_build_query($value);
                    $result = json_decode($calendarClient->request('GET', $api_url)->getBody(), true);

                    if ($result['status'] == 0 && $result['msg'] == 'ok') {
                        $data['date'] = $day;
                        $data['data'] = json_encode($result);
                        Calendar::insert_data($data);
                    } else {
                        SendMessage::send_sms('13052268638', '日历脚本异常，请及时查看！', '6', 50); //短信通知 -》王禹
                    }

                }
            }
        }
        catch (\Exception $e)
        {
            SendMessage::send_sms('13052268638', '日历脚本异常，请及时查看！', '6', 50); //短信通知 -》王禹
        }
    }
}

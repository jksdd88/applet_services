<?php
/**
 * Created by PhpStorm.
 * User: zhangyu1@dodoca.com
 * Date: 2018/4/2
 * Time: 11:19
 */
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BargainService;


class BargainOvertimeClose extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:BargainOvertimeClose';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '关闭超时砍价活动';

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
    public function handle(BargainService $BargainService)
    {

        //更新已到期的砍价活动
        try {

            $data = array();

            $BargainService->ActionClose($data);

        } catch (Exception $e) {

            SendMessage::send_sms('15651778032', '砍价活动脚本异常，请及时查看！', '6', 50);
        }
    }
}
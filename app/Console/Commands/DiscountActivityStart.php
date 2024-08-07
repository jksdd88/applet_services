<?php

/**
 * 自动更新未开始的满减状态为开始
 * @author changzhixian
 * */

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Utils\SendMessage;
use App\Models\DiscountActivity;


class DiscountActivityStart extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:DiscountActivityStart';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '自动更新未开始的满减状态为开始';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(){
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
            //自动更新未开始的满减状态为开始
            $info = DiscountActivity::select('id', 'start_time', 'end_time', 'status', 'range')
                ->where('status', '！=', 2)
                ->where('is_delete', '=', 1)
                ->get()
                ->toArray();
            $nowtime=date('Y-m-d H:i:s',time());
            if(!empty($info)){
                foreach ($info as $value) {
                    if ($value['start_time'] < $nowtime && $nowtime < $value['end_time']) {
                        DiscountActivity::where('id', $value['id'])->update(array('status' => 1));
                    }
                }
            }
            //throw new \Exception("抛出错误");
        }
        catch (\Exception $e)
        {
            SendMessage::send_sms('18606518882', '手动更新未开始的满减状态为开始脚本异常，请及时查看！', '6', 50); //短信通知 -》changzhixian
        }
    }
}

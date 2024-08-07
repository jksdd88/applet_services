<?php

/**
 * 自动更新未开始的拼团状态为开始
 * @author changzhixian
 * */

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Utils\SendMessage;

use App\Models\Fightgroup;
use App\Models\FightgroupLadder;


class FightgroupStart extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:FightgroupStart';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '自动更新未开始的拼团状态为开始';

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
    public function handle() {

        try {
            //自动更新未开始的拼团状态为开始
            $Fightgroup = Fightgroup::select('id','merchant_id')->where('start_time','<=',date('Y-m-d H:i:s'))
                        ->where('status',PIN_SUBMIT)->get()->toArray();

            if($Fightgroup){

                foreach($Fightgroup as $k=>$v) {
                    //修改拼团状态
                    Fightgroup::update_data($v['id'],$v['merchant_id'],array('status'=>PIN_ACTIVE));

                    //修改拼团阶梯状态
                    $ladderlist = FightgroupLadder::select('id','merchant_id')->where('fightgroup_id','=',$v['id'])
                        ->get()->toArray();
                    if($ladderlist){
                        foreach($ladderlist as $lk=>$lv) {
                            FightgroupLadder::update_data($lv['id'],$lv['merchant_id'],array('status'=>PIN_LADDER_ACTIVE));
                        }
                    }

                }
            }

            //throw new \Exception("抛出错误");
        }
        catch (\Exception $e)
        {
            //FightgroupJoin::where('id',3112)->update(['nickname'=>"we"]);
            SendMessage::send_sms('15201867629', '自动更新未开始的拼团状态为开始脚本异常，请及时查看！', '6', 50); //短信通知 -》changzhixian
        }




    }

}

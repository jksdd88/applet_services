<?php

/**
 * 拼团到时间未成团退款
 *
 * @package default
 * @author changzhixian
 * */

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Utils\SendMessage;

use App\Services\FightgroupService;

use App\Models\FightgroupLaunch;
use App\Models\FightgroupJoin;


class FightgroupRefund extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:FightgroupRefund';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '拼团到时间未成团退款';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(FightgroupService $fightgroupService){
        parent::__construct();
        //拼团服务类
        $this->fightgroupService = $fightgroupService;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle() {

        try {
            //查询拼团时间到的未成团
            $launchs = FightgroupLaunch::select('id','merchant_id')->where('end_time','<=',date('Y-m-d H:i:s'))
                        ->whereIn('status',[PIN_INIT_ACTIVE])->get()->toArray();

            if($launchs){

                foreach($launchs as $k=>$v) {
                    //2、修改拼团发起表状态
                    FightgroupLaunch::update_data($v['id'],$v['merchant_id'],array('status'=>PIN_INIT_FAIL_END));
                    //3、修改拼团参与人表状态并执行退款操作
                    $this->fightgroupService->launchRefund($v['id'],"2",$v['merchant_id']);//跑脚本结束
                }
            }

            //throw new \Exception("抛出错误");
        }
        catch (\Exception $e)
        {
            //FightgroupJoin::where('id',3112)->update(['nickname'=>"we"]);
            SendMessage::send_sms('15201867629', '拼团到时间未成团退款脚本异常，请及时查看！', '6', 50); //短信通知 -》changzhixian
        }




    }

}

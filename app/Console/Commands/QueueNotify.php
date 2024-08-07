<?php

/**
 * REDIS实现消息队列
 * @package default
 * @author 王禹
 **/

namespace App\Console\Commands;

use Illuminate\Console\Command;
//use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

use App\Models\WeixinLog;
use App\Services\WeixinMsgService;

class QueueNotify extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:QueueNotify';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '小程序消息模板通知队列';

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

        while(True){

            try{

                $queueTask = Redis::lpop('dodocaQueue');

                if(!empty($queueTask))
                {
                    $queueTask = json_decode($queueTask,true);
                    $response = [];
                    switch ($queueTask['jobName'])
                    {
                        case 'activityAlert':
                             $response = (new WeixinMsgService())->activity($queueTask['data']);
                            break;
                        default:
                            break;
                    }
                    //WeixinLog::insert_data([  'merchant_id'=>0,'value'=>$queueTask['jobName'],'action'=>'QueueNotify','request'=>json_encode($queueTask['data']),'reponse'=>json_encode($response)  ]);
                }
                else
                {
                    sleep(rand()%3);
                }

            }catch(Exception $e){

                echo $e->getMessage()."\n";
                sleep(rand()%3);
            }

        }
    }
}

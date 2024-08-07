<?php
/**
 * @ redis queue
 * @author wangyu
 * @time 2018-07-17
 */
namespace App\Utils;

use Illuminate\Support\Facades\Redis;

class QueueRedis
{

    /**
     * @ 推送一个工作至队列 异步进行，只返回处理句柄。
     * @param $job_names    指定需调用的工作
     * @param array $data   传递到工作脚本内的数据
     * @return 返回处理句柄
     */
    public static function addJob($job_names ,$data = array()){
        Redis::lpush('dodocaQueue',json_encode(['jobName' => $job_names ,'data' => $data]));
    }
}
<?php
/**
 * @ gearman queue
 * @author wangyu
 * @time 2017-05-18
 */
namespace App\Utils;

include_once 'Queue/Gearman.php';
use Gearman;

class QueueGearman extends Gearman
{

    /**
     * @ 推送一个工作至队列 异步进行，只返回处理句柄。
     * @param $job_names    指定需调用的工作
     * @param array $data   传递到工作脚本内的数据
     * @return 返回处理句柄
     */
    public static function addjob($job_names ,$data = array()){

        $client = self::getClient();
        return $client -> doBackground($job_names , serialize($data));

    }
}
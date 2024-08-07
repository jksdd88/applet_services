<?php

/**
 * 数据库减拼团活动某个团的可用名额
 * @author wangshen@dodoca.com
 * @cdate 2017-9-19
 *
 * @param int $merchant_id  商户id
 * @param int $fightgroup_launch_id  拼团发起表主键id
 * @param int $num  数量
 */

namespace App\Jobs;

use App\Jobs\Job;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Bus\SelfHandling;
use Illuminate\Contracts\Queue\ShouldQueue;

use App\Models\FightgroupLaunch;

use App\Utils\CacheKey;
use Illuminate\Support\Facades\Cache;



class FightgroupNumsLessAlter extends Job implements SelfHandling, ShouldQueue
{
    use InteractsWithQueue, SerializesModels;
    
    protected $merchant_id;
    protected $fightgroup_launch_id;
    protected $num;
    
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($merchant_id, $fightgroup_launch_id, $num)
    {
        $this->merchant_id = $merchant_id;
        $this->fightgroup_launch_id = $fightgroup_launch_id;
        $this->num = $num;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        //减可用名额
        FightgroupLaunch::where('id','=',$this->fightgroup_launch_id)
                        ->where('merchant_id','=',$this->merchant_id)
                        ->decrement('nums_less',$this->num);

                        
        //清除单条记录缓存
        $key = CacheKey::get_fightgroup_launch_by_id_key($this->fightgroup_launch_id,$this->merchant_id);
        Cache::forget($key);
    }

}

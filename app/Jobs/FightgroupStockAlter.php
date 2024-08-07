<?php

/**
 * 数据库加减拼团活动库存
 * @author wangshen@dodoca.com
 * @cdate 2017-9-14
 *
 * @param int $merchant_id  商户id
 * @param int $fightgroup_stock_id  拼团库存表id
 * @param int $num  数量
 * @param int $type  1加库存，2减库存
 */

namespace App\Jobs;

use App\Jobs\Job;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Bus\SelfHandling;
use Illuminate\Contracts\Queue\ShouldQueue;

use App\Models\FightgroupStock;



class FightgroupStockAlter extends Job implements SelfHandling, ShouldQueue
{
    use InteractsWithQueue, SerializesModels;
    
    protected $merchant_id;
    protected $fightgroup_stock_id;
    protected $num;
    protected $type;
    
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($merchant_id, $fightgroup_stock_id, $num, $type)
    {
        $this->merchant_id = $merchant_id;
        $this->fightgroup_stock_id = $fightgroup_stock_id;
        $this->num = $num;
        $this->type = $type;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {

        if($this->type == 1){//数据库加库存
            
            FightgroupStock::where('id','=',$this->fightgroup_stock_id)
                            ->where('merchant_id','=',$this->merchant_id)
                            ->increment('stock',$this->num);
            
        }elseif($this->type == 2){//数据库减库存
            
            FightgroupStock::where('id','=',$this->fightgroup_stock_id)
                            ->where('merchant_id','=',$this->merchant_id)
                            ->decrement('stock',$this->num);
            
        }

    }

}

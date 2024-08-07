<?php
/**
 * Created by PhpStorm.
 * User: wangshiliang@dodoca.com
 * Date: 2018/6/25
 * Time: 10:43
 */

namespace App\Services;

use App\Models\MemberInfo;
use App\Models\Seckill;
use App\Models\Bargain;
use App\Models\Fightgroup;
use App\Models\WeixinMsgTemplate;
use App\Models\WeixinLog;
use App\Utils\QueueRedis;

class ActivityAlertService
{

    private $now;
    private $delay;
    private $startTime;
    private $endTime;

    private $type;
    private $runtype;

    private $total = 0;

    public function __construct()
    {

    }

    //配置信息
    public function setConfig($type, $pulseTime = 3600, $runtype = 'job', $delay = 1000){
        $this->total = 0;
        $this->type = $type;
        $this->runtype = $runtype;
        $this->now = time();
        $this->delay = $delay;
        $this->startTime = date('Y-m-d H:i:s',$this->now+$pulseTime);//3600秒后  $pulseTime
        $this->endTime   = date('Y-m-d H:i:s',$this->now+$pulseTime*2);//3600秒后的3600时间段
        return $this;
    }

    //砍价活动
    public function activity($page = 0){
        $limit = 1000;
        $list = [];
        if($this->type == 'bargain'){
            $list = Bargain::query()->select(['id','merchant_id','goods_id','title','start_time'])->where('start_time','>=',$this->startTime)->where('start_time','<=',$this->endTime)->where(['is_onoff'=>1,'is_delete'=>1])->skip($page*$limit)->take($limit)->get() ->toArray();
        }else if($this->type == 'seckill'){
            $list = Seckill::query()->select(['id','merchant_id','goods_id','goods_title','start_time'])->where('start_time','>=',$this->startTime)->where('start_time','<=',$this->endTime)->where(['is_remind'=>1])->skip($page*$limit)->take($limit)->get()->toArray();//['id','merchant_id','goods_id','title','start_time']
            foreach ($list as $k=>$v){
                $list[$k]['title'] = '秒杀活动 '.$v['goods_title'];
            }
        }else if($this->type == 'fightgroup'){
            $list = Fightgroup::query()->select(['id','merchant_id','goods_id','title','start_time'])->where('start_time','>=',$this->startTime)->where('start_time','<=',$this->endTime)->where(['is_remind'=>1])->skip($page*$limit)->take($limit)->get()->toArray();
        }else{
            return true;
        }

        $counter = 0;
        foreach ($list as $k => $v) {
            $counter ++ ;
            $appInfo = WeixinMsgTemplate::query()->where(['merchant_id' => $v['merchant_id'],'template_type'=>13,'status'=>1])->get()->toArray();
            foreach ($appInfo as $ks=>$vs) {
                $logstart = date('Y-m-d H:i:s');
                $count =  MemberInfo::query()->where(['merchant_id'=>$v['merchant_id'],'appid'=>$vs['appid'] ])->count();
                $this->addJob($v, $vs['appid'], 0);
                $v['member_count'] = $count;
                WeixinLog::insert_data([  'merchant_id'=>$v['merchant_id'],'value'=>$vs['appid'],'action'=>'ActivityAlert'.$this->type,'request'=>json_encode($v),'reponse'=>json_encode(['start'=>$logstart,'end'=>date('Y-m-d H:i:s')])  ]);

            }
        }
        if($counter < $limit){
            return true;
        }else{
            return $this->activity(++$page);
        }
    }

    //队列添加
    private function addJob($activityInfo, $appid, $delay, $page = 0){
        $limit = 1000;
        $counter = 0;
        $list = MemberInfo::query()->select(['id','member_id'])->where(['merchant_id'=>$activityInfo['merchant_id'],'appid'=>$appid ])->skip($page*$limit)->take($limit)->get()->toArray();
        foreach ($list as $key => $val) {
            $counter++;
            $data = ['merchant_id'=>$activityInfo['merchant_id'],'member_id'=>$val['member_id'],'appid'=>$appid,'id'=>$activityInfo['goods_id'],'name'=>$activityInfo['title'],'time'=>$activityInfo['start_time'],'remark'=> '活动即将开始，请及时参与'];
            QueueRedis::addJob('activityAlert',$data);
        }
        $this->total += $counter;
        if($counter < $limit){
            return true;
        }else{
            return $this->addJob($activityInfo, $appid, $delay, ++$page);
        }
    }

    //记录日志
    public function setlog(){
        //return WeixinLog::insert_data(['merchant_id'=>0,'value'=>date('Ymd').$this->type,'action'=>'ActivityAlertService','request'=>json_encode(['type'=>$this->type,'start'=>$this->now,'end'=>time(),'totle'=>$this->total,'start_time'=>$this->startTime,'end_time'=>$this->endTime]),'reponse'=>$this->total]);
    }

}
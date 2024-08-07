<?php
/**
 * Created by PhpStorm.
 * User: zhangyu1@dodoca.com
 * Date: 2018/3/30
 * Time: 16:58
 */

namespace App\Services;

use App\Facades\Member;
use App\Facades\Suppliers;
use App\Models\Bargain;
use App\Models\AloneActivityRecode;
use App\Models\BargainJoin;
use App\Models\BargainLaunch;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Request;
use Monolog\Handler\FingersCrossed\ActivationStrategyInterface;


class BargainService
{

    public function __construct() {

        $this->today = date('Y-m-d H:i:s');
    }

    /**
     * 結束砍價活動
     *
     * @param string $merchant_id  商户ID
     * @param string $id  活動ID
     * @param string $goods_id  商品ID
     *
     * @return \Illuminate\Http\Response
     */

    public function ActionClose($param){

        $merchant_id = isset($param['merchant_id']) ? intval($param['merchant_id']) : 0;

        $action_id   = isset($param['id']) ? intval($param['id']) : 0;

        $goods_id    = isset($param['goods_id']) ? intval($param['goods_id']) : 0;

        if($merchant_id && $merchant_id>0 && $action_id && $action_id>0 && $goods_id && $goods_id>0){      //商家手动结束

            $action = Bargain::get_data_by_id($action_id,$merchant_id);

            if(!$action){

                return ['errcode' => 10004, 'errmsg' => '活动不存在'];

            }
            if($action['status'] == -1){

                return ['errcode' => 10004, 'errmsg' => '活动不存在'];
            }

            $data['status'] = 3;

            $update_result = Bargain::update_data($action_id,$merchant_id,$data);

            $launch_data['status'] = 3;

            $all_launch_actions = BargainLaunch::where(array('bargain_id'=>$action_id,'status'=>1))->update($launch_data);    //结束所有当前的关联发起活动

            //更新私有活动记录表
            $activity_recode = [
                'finish_time' => date('Y-m-d H:i:s')
            ];

            $activity_update = AloneActivityRecode::where(array('merchant_id'=>$merchant_id,'goods_id'=>$goods_id,'alone_id'=>$action_id,'act_type'=>'bargain'))->update($activity_recode);

            if($update_result){

                return ['errcode' => 0, 'errmsg' => '活动已结束'];
            }

        }else{            //活动到期自动结束

            $fields = 'id,merchant_id,goods_id,status,is_delete,start_time,end_time,created_time,updated_time';

            $all_actions = Bargain::select(\DB::raw($fields))->where('is_delete','=',1)->where('end_time','<=',$this->today)->where('status','<',2)->get();   //查询所有正在活动中的砍价活动

            if(!empty($all_actions)){

                foreach($all_actions as $key=>$value){

                    $data['status'] = 2;

                    $auto_update_result = Bargain::update_data($value['id'],$value['merchant_id'],$data);    //结束当前砍价活动

                    $launch_data['status'] = 3;

                    $all_launch_actions = BargainLaunch::where(array('bargain_id'=>$value['id'],'status'=>1))->update($launch_data);    //结束所有当前的关联发起活动

                    /*//更新私有活动记录表
                    $activity_recode = [
                        'finish_time' => date('Y-m-d H:i:s')
                    ];

                    $activity_update = AloneActivityRecode::where(array('merchant_id'=>$value['merchant_id'],'goods_id'=>$value['goods_id'],'alone_id'=>$value['id'],'act_type'=>'bargain'))->update($activity_recode);*/


                }

            }

        }

    }


    /**
     * 开启砍價活動
     *
     * @return \Illuminate\Http\Response
     */

    /*public function AutoStartAction(){

        $fields = 'id,merchant_id,goods_id,status,is_delete,start_time,end_time';

        $all_actions = Bargain::select(\DB::raw($fields))->where(array('is_delete'=>1,'status'=>0))->get();   //查询所有有效的未开始的砍价活动

        foreach($all_actions as $key=>$value){

            if(strtotime($value['start_time']) <= strtotime($this->today)  && strtotime($this->today) < strtotime($value['end_time'])){   //当前时间大于等于开始时间

                $data['status'] = 1;

                $auto_update_result = Bargain::update_data($value['id'],$value['merchant_id'],$data);    //开启当前砍价活动

            }else{

                continue;
            }

        }

    }*/




    /**
     * 通过商品id获取砍价活动信息
     * chang
     * 20180401 12:00
     * $merchant_id  商户ID  必传参数
     * $goods_id     商品ID  必传参数
     */
    public function bargainInfo($merchant_id=0,$goods_id=0)
    {

        $merchant_id = isset($merchant_id) ? intval($merchant_id) : 0;
        $goods_id    = isset($goods_id) ? intval($goods_id) : 0;

        if($goods_id < 1 || $merchant_id<1){
            return ['errcode' => 10001, 'errmsg' => '获取砍价活动信息失败，参数有误！'];exit;
        }

        $Bargain_Info = Bargain::select()
            ->where(['merchant_id' => $merchant_id, 'goods_id' => $goods_id, 'is_delete' => 1,'status'=> 0])
            ->where('start_time', '<=', date('Y-m-d H:i:s'))
            ->where('end_time', '>=', date('Y-m-d H:i:s'))
            ->first();
        if(empty($Bargain_Info)) {
            return ['errcode' => 10002, 'errmsg' => '无效砍价活动！'];exit;
        }elseif(isset($Bargain_Info['status'])){
            $Bargain_Info['status'] = 1;
        }

        return ['errcode' => 0, 'errmsg' => "获取砍价活动信息成功", 'data' => $Bargain_Info];
    }


    /**
     * 通过活动id获取活动信息
     * chang
     * 20180401 12:00
     * $merchant_id  商户ID  必传参数
     * $goods_id     商品ID  必传参数
     */
    public function bargainIdInfo($merchant_id=0,$bargain_id=0)
    {

        $merchant_id = isset($merchant_id) ? intval($merchant_id) : 0;
        $bargain_id  = isset($bargain_id) ? intval($bargain_id) : 0;

        if($bargain_id < 1 || $merchant_id<1){
            return ['errcode' => 10001, 'errmsg' => '获取砍价活动信息失败，参数有误！'];exit;
        }

        $Bargain_Info = Bargain::select()
            ->where(['merchant_id' => $merchant_id, 'id' => $bargain_id, 'is_delete' => 1])
            ->first();
        if(empty($Bargain_Info)) {
            return ['errcode' => 10002, 'errmsg' => '无效砍价活动！'];exit;
        }else{
            if($Bargain_Info['status'] != 3){
                if(strtotime($Bargain_Info['start_time']) <= strtotime(date('Y-m-d H:i:s'))  && strtotime(date('Y-m-d H:i:s')) < strtotime($Bargain_Info['end_time'])){
                    $Bargain_Info['status'] = 1;
                }
            }
        }
        

        return ['errcode' => 0, 'errmsg' => "获取砍价活动信息成功", 'data' => $Bargain_Info];
    }

}
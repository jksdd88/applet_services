<?php
/**
 * 营销活动服务类
 * Date: 2018-03-12
 * Time: 15:20
 */
namespace App\Services;

use App\Models\OperateRewardDetail;
use App\Models\Merchant;
use App\Models\MerchantSetting;

use Illuminate\Foundation\Bus\DispatchesJobs;
use App\Utils\CommonApi;
use App\Jobs\OperateReward;
use DB;

class OperateRewardService
{

    use DispatchesJobs;

    function __construct() {
        $this->reward = 3;//邀请好友注册成功，授权成功奖励商品上架数量
    }
    /**
     * 营销活动(授权成功时调用)
     * @cdate 2018-03-12
     *
     * @param int $act_type 活动类型
     * @param int $merchant_id 商户id
     */
    public function operateReward($act_type = 0, $merchant_id = 0)
    {

        //调用此方法插入日志
        \Log::info('OperateRewardService-discount:act_type->'.$act_type.',merchant_id->'.$merchant_id);

        //活动类型
        $act_type = isset($act_type) ? (int)$act_type : 0;
        //商户id
        $merchant_id = isset($merchant_id) ? (int)$merchant_id : 0;

        if ($act_type < 1 || $merchant_id < 1) {
            return ['errcode' => 10001, 'errmsg' => '参数有误'];
        }

        //开始发放奖励
        $job = new OperateReward($act_type, $merchant_id);
        $this->dispatch($job);
    }

    /**
     * 发放奖励(脚本调用)
     * @cdate 2018-03-12
     *
     * @param int $act_type 活动类型
     * @param int $merchant_id 商户id
     */
    public function giveOperateReward($act_type = 0, $merchant_id = 0)
    {
        $merchant_info = Merchant::get_data_by_id($merchant_id);
        if(isset($merchant_info) && !empty($merchant_info)){
            $referee_merchant_id = $merchant_info->referee_merchant_id;
            if($referee_merchant_id > 0 ){//推荐人商户id存在
                $merchant_data = OperateRewardDetail::get_data_by_id($merchant_id);
                if($merchant_data){
                    //插入日志 return ['errcode' => 10005, 'errmsg' => '此商户已经是老用户（给推荐人发放过奖励）'];
                    \Log::info('OperateRewardService-errcode:act_type->'.$act_type.',merchant_id->'.$merchant_id.",errmsg(10005)->此商户已经是老用户（给推荐人发放过奖励）");
                return;
                }
                //事物控制  防止数据异常
                DB::beginTransaction();
                try{
                    $reward = $this->reward;
                    $remark = '邀请好友获得上架商品数量（授权成功得3个上架商品数量）';
                    $start_time = date("Y-m-d H:i:s");
                    $end_y = date('Y-', strtotime("+1 year",strtotime(date("Y"))));
                    $end_time = $end_y.date("m-d H:i:s");
                    $data_insert = array(
                        'merchant_id' => $merchant_id,
                        'referee_merchant_id' => $referee_merchant_id,
                        'act_type' => $act_type,
                        'reward' => $reward,
                        'remark' => $remark,
                        'start_time' => $start_time,
                        'end_time' => $end_time
                    );
                    $id = OperateRewardDetail::insert_data($data_insert);//奖励记录
                    if ($id && $id > 0) {
                        $merchant_info = MerchantSetting::get_data_by_id($referee_merchant_id);
                        $reward = isset($merchant_info ) ? $merchant_info->reward : 0;
                        $reward = (int)$reward + $this->reward;
                        $update = MerchantSetting::update_data($referee_merchant_id,['reward'=>$reward]);//推荐人发放奖励
                    }
                    DB::commit();
                    //发放奖励成功插入日志 return ['errcode' => 0, 'errmsg' => '发放奖励成功'];
                    \Log::info('OperateRewardService-errcode:act_type->'.$act_type.',merchant_id->'.$merchant_id.",errmsg(0)->发放奖励成功");
                    return;
                }catch (\Exception $e) {
                    DB::rollBack();
                    //记录发放奖励异常
                    $except = [
                        'activity_id'	=>	$merchant_id,
                        'data_type'		=>	'OperateRewardDetail',
                        'content'		=>	'邀请好友开店获得商品上架数量活动发放奖励失败，注册用户商户id：'.$merchant_id.'，推荐人商户id'.$referee_merchant_id,
                    ];
                    CommonApi::errlog($except);
                    //发放奖励失败插入日志 return ['errcode' => 1004, 'errmsg' => '发放奖励失败'];
                    \Log::info('OperateRewardService-errcode:act_type->'.$act_type.',merchant_id->'.$merchant_id.",errmsg(1004)->发放奖励失败");
                    return;
                }
            }else{
                //此商户来源非营销活动插入日志 return ['errcode' => 10003, 'errmsg' => '此商户来源非营销活动'];
                \Log::info('OperateRewardService-errcode:act_type->'.$act_type.',merchant_id->'.$merchant_id.",errmsg(10003)->此商户来源非营销活动");
            }
        }else{
            //此商户来源非营销活动插入日志 return ['errcode' => 10002, 'errmsg' => '商户信息不存在'];
            \Log::info('OperateRewardService-errcode:act_type->'.$act_type.',merchant_id->'.$merchant_id.",errmsg(10002)->商户信息不存在");
            return;
        }

    }


}
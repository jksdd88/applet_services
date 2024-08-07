<?php

namespace App\Services;

use App\Facades\Shop;
use App\Facades\Member;
use App\Models\CreditDetail;
use App\Models\CreditGoods;
use App\Models\CreditRule;
use App\Models\MerchantMember;
use App\Models\MerchantSetting;
use Illuminate\Support\Facades\DB;
use App\Models\Member as MemberModel;
use GuzzleHttp\Client;
use App\Models\MerchantDayCredit;
use App\Models\MerchantMonthCreditType;
use Carbon\Carbon;

/**
 * 积分服务类
 * @author gongruimin
 **/
class CreditService
{
       
    /**
     * 根据规则送积分
     * @param $merchant_id
     * @param $member_id 
     * @param $type 类型
     * @param array $conditions 传递规则
     *$conditions = [
     *                   'give_credit'=>10,
     *                   'memo'=>'退款退积分10'
     *               ];
     */
    public function giveCredit($merchant_id,$member_id,$type,$conditions=array()){
        if(!$member_id){
            return ['errcode' => 30001, 'errmsg' => '会员ID不存在'];
        }
        if(!$merchant_id){
            return ['errcode' => 99004, 'errmsg' => '商户ID不存在'];
        }
        $credit = 0;
        //区别类型
        switch($type){
            case 1://完善手机送积分
                $creditRules = CreditRule::get_data_by_merchantid($merchant_id, 1);
                if($creditRules && $creditRules->credit){
                    $credit = $creditRules->credit;
                    $memo = '完善手机送 '.$credit.' 积分';
                }
                break;
            case 2://消费送积分
                $memo = '';
                if(isset($conditions['give_credit']) && $conditions['give_credit'] > 0){
                    $credit = $conditions['give_credit'];
                    $memo = '消费 '.$conditions['amount'].' 元送 '.$credit.' 积分';
                }else{
                    if(isset($conditions['finished_time'])){
                        $result = $this->get_credit($merchant_id,$conditions);
                    }
                    if($result){
                        $credit = $result['credit'];
                        $memo = '消费 '.$conditions['amount'].' 元送 '.$result['credit'].' 积分';
                    }
                }
                if(isset($conditions['order_sn']) && $conditions['order_sn']){
                    $memo .= '(订单号:'.$conditions['order_sn'].')';
                }
                break;
            case 3://主动确认收货
                $creditRules = CreditRule::get_data_by_merchantid($merchant_id, 3);
                if($creditRules && $creditRules->credit){
                    $credit = $creditRules->credit;
                    $memo = '主动确认收货送 '.$credit.' 积分';
                }
                break;
            default:
                if(isset($conditions['give_credit']) && $conditions['give_credit']){
                    $credit = $conditions['give_credit'];
                    $memo = isset($conditions['memo']) ? $conditions['memo'] : '';
                }else{
                    return ['errcode' => 99001, 'errmsg' => '请求缺失参数'];
                }
        }

        if(!$credit)
            return ['errcode' => 30002, 'errmsg' => '积分不能为空'];

        $memo = isset($memo) ? $memo : '获得本店积分:'.$credit;
        $result = $this->giveToggleCredit($merchant_id,$member_id,$credit,$type,$memo);
        if($result){
            $this->putCreditStatistics($merchant_id,$credit,$type);
           return ['errcode' => 0, 'errmsg' => '积分写入成功'];
        }else{
           return ['errcode' => 30003, 'errmsg' => '积分写入失败'];
        }
    }

    /**
     * 会员积分变化
     * @param $merchant_id
     * @param $member_id 
     * @param $type 类型
     * @param $credit
     * @param $memo
     * @return bool|float
     */
    private function giveToggleCredit($merchant_id,$member_id,$credit,$type=0,$memo=''){
        $merchantMember = MemberModel::where(array('merchant_id' => $merchant_id, 'id' => $member_id))->first();
        if(!$merchantMember){
            return false;
        }
        //变动前积分
        $pre_credit = $merchantMember['credit'];

        //变动后积分
        $merchantMember->credit = $merchantMember['credit']+$credit;
        $merchantMember->credit = ($merchantMember->credit < 0) ? 0 : $merchantMember->credit;

        //增加累计积分
        if($credit > 0){
            $merchantMember->total_credit = $merchantMember['total_credit']+$credit;
            $merchantMember->total_credit = ($merchantMember->total_credit < 0) ? 0 : $merchantMember->total_credit;
        }

        $result = MemberModel::update_data($member_id,$merchant_id,['credit'=>$merchantMember->credit,'total_credit'=>$merchantMember->total_credit]);
        
        if(!$result){
            return false;
        }

        $nickname = $merchantMember ? $merchantMember['name'] :'';
        //积分变化记录
        $create = array(
            'merchant_id'   => $merchant_id,
            'member_id'     => $member_id,
            'nickname'      => $nickname,
            'pre_credit'      => $pre_credit,
            'credit'        => $credit,
            'created_time'    => Carbon::now(),
            'final_credit' => $merchantMember->credit
        );
        $create['type'] = $type;
        $create['memo'] = (isset($memo) && $memo) ? $memo : '获得本店积分:'.$credit;
        //积分增加日志
        $data = CreditDetail::insert_data($create);      
        return $data;
    }
    /**
     * 消费金额送积分 规则
     * @param $merchant_id
     * @param array $conditions 传递规则
     */
    private function get_credit($merchant_id,$condition){

        $rules = array();
        //传递了规则
        if(isset($condition['rules']) && $condition['rules']){
            $creditRules = $condition['rules'];
            ksort($creditRules);
            $rule = array();
            foreach($creditRules as $_amount => $_credit){
                if($condition['amount'] >= $_amount){
                    $rule['credit'] = $_credit;
                    $rule['amount'] = $_amount;
                    break;
                }
            }
            if($rule){
                $multiple = floor($condition['amount'] / $rule['amount']);
                $credit = $rule['credit']*$multiple;
                $rules = $condition['rules'];
            }else{
                return false;
            }

        }else{
            $creditRule = CreditRule::select('id','credit','condition')->where(array('merchant_id' => $merchant_id, 'type' => 2 ,'enabled' => 1))->where('condition','<=',$condition['amount'])->where('condition','!=',0)->where('credit','!=',0)->orderBy('condition','desc')->first();
            if(!$creditRule){
                return false;
            }

            //金额的倍数
            $multiple = floor($condition['amount'] / $creditRule['condition']);

            $credit = $creditRule['credit']*$multiple;
            
            $wheres = [
                [
                    'column'   => 'merchant_id',
                    'operator' => '=',
                    'value'    => $merchant_id
                ],
                [
                    'column'   => 'type',
                    'operator' => '=',
                    'value'    => 2
                ],
                [
                    'column'   => 'enabled',
                    'operator' => '=',
                    'value'    => 1
                ],
            ];
            $creditRules = CreditRule::get_data_list($wheres);
            foreach($creditRules as $rule){
                $rules[$rule['condition']] = $rule['credit'];
            }
        }

        return array('credit'=>$credit,'rules'=>json_encode($rules));
    }
    
     //写入统计库信息
    public static function putCreditStatistics($merchant_id,$credit,$type,$shop_id=1,$is_refund=0) {
        if ($credit > 0 && $is_refund == 0) {
            $monthStart = date("Y-m-d 00:00:00", mktime(0, 0, 0, date("m"), 1, date("Y"))); //本月开始时间
            //给商家发放积分渠道加月统计
            $monthCreditTypeInfo = MerchantMonthCreditType::where('merchant_id', $merchant_id)->where('type', $type)->where('created_time', $monthStart)->first();
            if ($monthCreditTypeInfo) {
                $monthCreditTypeInfo->credit = $monthCreditTypeInfo['credit'] + $credit;
                $monthCreditTypeInfo->save();
            } else {
                $monthCreditTypeData = [
                    'type' => $type,
                    'merchant_id' => $merchant_id,
                    'shop_id' => $shop_id,
                    'credit' => $credit,
                    'expend_credit' => 0,
                    'created_time' => $monthStart
                ];
                MerchantMonthCreditType::create($monthCreditTypeData);
            }
            //商家单日发放积分统计
            $merchantDayCreditInfo = MerchantDayCredit::where('merchant_id', $merchant_id)->where('created_time', Carbon::today())->first();
            if ($merchantDayCreditInfo) {
                $merchantDayCreditInfo->credit = $merchantDayCreditInfo['credit'] + $credit;
                $merchantDayCreditInfo->save();
            } else {
                $MerchantDayCreditData = [
                    'merchant_id' => $merchant_id,
                    'shop_id' => $shop_id,
                    'credit' => $credit,
                    'expend_credit' => 0,
                    'created_time' => Carbon::today()
                ];
                MerchantDayCredit::create($MerchantDayCreditData);
            }
        }
        //用户积分消耗商家统计
        if ($credit < 0 || $is_refund == 1) {
            $monthStart = date("Y-m-d 00:00:00", mktime(0, 0, 0, date("m"), 1, date("Y"))); //本月开始时间
            $monthCreditTypeInfo = MerchantMonthCreditType::where('merchant_id', $merchant_id)->where('type', $type)->where('created_time', $monthStart)->first();
            if ($monthCreditTypeInfo) {
                if ($is_refund == 1) {
                    $monthCreditTypeInfo->expend_credit = $monthCreditTypeInfo['expend_credit'] - abs($credit);
                } else {
                    $monthCreditTypeInfo->expend_credit = $monthCreditTypeInfo['expend_credit'] + abs($credit);
                }
                $monthCreditTypeInfo->save();
            } else {
                $monthCreditTypeData = [
                    'type' => $type,
                    'merchant_id' => $merchant_id,
                    'shop_id' => $shop_id,
                    'credit' => 0,
                    'expend_credit' => $is_refund == 1 ? -abs($credit) : abs($credit),
                    'created_time' => $monthStart
                ];
                MerchantMonthCreditType::create($monthCreditTypeData);
            }
            //用户单日消耗积分商家统计
            $merchantDayCreditInfo = MerchantDayCredit::where('merchant_id', $merchant_id)->where('created_time', Carbon::today())->first();
            if ($merchantDayCreditInfo) {
                if ($is_refund == 1) {
                    $merchantDayCreditInfo->expend_credit = $merchantDayCreditInfo['expend_credit'] - abs($credit);
                } else {
                    $merchantDayCreditInfo->expend_credit = $merchantDayCreditInfo['expend_credit'] + abs($credit);
                }
                $merchantDayCreditInfo->save();
            } else {
                $MerchantDayCreditData = [
                    'merchant_id' => $merchant_id,
                    'shop_id' => $shop_id,
                    'credit' => 0,
                    'expend_credit' => $is_refund == 1 ? -abs($credit) : abs($credit),
                    'created_time' => Carbon::today()
                ];
                MerchantDayCredit::create($MerchantDayCreditData);
            }
        }
    }

    
   
}

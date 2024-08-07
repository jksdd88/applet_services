<?php

namespace App\Http\Controllers\Weapp\Credit;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\CreditDetail;
use App\Models\CreditRule;
use App\Models\MerchantSetting;
use Illuminate\Support\Facades\DB;
use App\Models\Member as MemberModel;
use GuzzleHttp\Client;
use Carbon\Carbon;
use App\Facades\Member;

class CreditController extends Controller
{
    public function __construct()
    {
        $this->merchant_id = Member::merchant_id();
        $this->member_id = Member::id();
    }

   //积分规则
    public function getRegular(Request $request){
    	$param = $request->all();
        $merchant_id = $this->merchant_id;
        $data = array();
        //积分抵扣规则
        $data['credit_rule'] = MerchantSetting::where('merchant_id',$merchant_id)->pluck('credit_rule');
        //途径
        $rules = CreditRule::where(['merchant_id'=>$merchant_id,'enabled'=>1])->get();

        $data['rules'] = array();
        if($rules){
            foreach($rules as $k=>$tmp){
                if($tmp->type == 1 ){
                    $data['rules']['other'][] = '完善手机送 '.$tmp->credit.' 积分';
                }
                //主动确认收货
                if($tmp->type == 3 ){
                    $data['rules']['other'][] = '主动确认收货送 '.$tmp->credit.' 积分';
                }
                //晒单分享
                if($tmp->type == 4 ){
                    $data['rules']['other'][] = '晒单送 '.$tmp->credit.' 积分';
                }
                //满送积分
                if($tmp->type == 2){

                    $data['rules']['moneyCredit'][] = '订单完成满'.$tmp->condition.'元送'.$tmp->credit.'积分';
                }
            }
        }
        return ['errcode' => 0, 'data' => $data];
    }


    
    //个人中心积分明细
    public function getCreditDetail(Request $request)
    {
    	$param = $request->all();
        $merchant_id = $this->merchant_id;
        $member_id   = $this->member_id;
        $pagesize    = $request->input('pagesize', 10);
        $page        = $request->input('page', 1);
        $offset      = ($page - 1) * $pagesize;

        if(!$merchant_id){
            return ['errcode' => 30001, 'errmsg' => '商户不存在'];
        }

        if(!$member_id){
            return ['errcode' => 99004, 'errmsg' => '会员不存在'];
        }

        $wheres = [
            [
                'column'   => 'merchant_id',
                'operator' => '=',
                'value'    => $merchant_id
            ],
            [
                'column'   => 'member_id',
                'operator' => '=',
                'value'    => $member_id
            ],
        ];

        $total = CreditDetail::get_data_count($wheres);
        $data['creditlist']  = CreditDetail::get_data_list($wheres, '*', $offset, $pagesize);
        $member = MemberModel::get_data_by_id($member_id,$merchant_id);
        $data['credit'] = $member['credit'];
        return ['errcode' => 0, 'count' => $total, 'data' => $data];
    }


}

<?php

namespace App\Http\Controllers\Whb\Member;

use App\Http\Controllers\Controller;
use App\Models\CreditDetail;
use App\Services\CreditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Auth;
use App\Models\Member;
use App\Models\Merchant;
use App\Models\MemberInfo;
use App\Services\WeixinService;
use App\Utils\Encrypt;
use Cache;

class MemberController extends Controller
{
    protected $params;//参数

    public function __construct(Request $request){
        $this->params = $request->all();
    }

    /**
     * 根据粉丝id和商户id获取粉丝信息
     *    
     */
    public function getMemberInfo()
    {
        $result = MemberInfo::get_one_by_openid($this->params['openid'], $this->params['fxuserid']);
        if(empty($result)){
            return ['errcode' => 1];
        }
        $data = Member::get_data_by_id($result['member_id'], $this->params['fxuserid']);

        $data['member_info'] = $result;
        if(!empty($data)){
            return ['errcode' => 0,'data' => $data];
        }
        else{
            return ['errcode' =>1];
        }
    }

    /**
     * 根据小程序登录token获取粉丝openid
     *    
     */
    public function getMemberOpenid()
    {
        $token       = isset($this->params['token']) ? $this->params['token'] : '';
        $merchant_id = isset($this->params['merchant_id']) ? intval($this->params['merchant_id']) : 0;

        $data = Cache::get($token);

        if (!$data) {
            return ['errcode' => 1];
        }

        $member_id = $data['id'];
        $appid     = $data['appid'];

        $info = MemberInfo::select('open_id')->where('merchant_id', $merchant_id)->where('member_id', $member_id)->where('appid', $appid)->first();

        $open_id = $info->open_id;

        if($open_id){
            $Encrypt = new Encrypt;
            $open_id = $Encrypt->encode($open_id, 'dodoca_applet_webauth');

            return ['errcode' => 0, 'data' => $open_id];
        }
    }
    
    /**
     * 根据粉丝id和商户id获取粉丝信息
     *    
     */
    public function getAccessToken(WeixinService $weixinService)
    {
        
        $result=$weixinService->getAccessToken($this->params['appid']);
        return ['errcode' => 0,'data' => $result];
       
    }

    /**
     * 会员积分变动
     */
    public function giveCredit(){
        $member_id   = isset($this->params["member_id"]) && !empty($this->params["member_id"]) ? $this->params["member_id"] : 0;

        $merchant_id = isset($this->params["merchant_id"]) && !empty($this->params["merchant_id"]) ? $this->params["merchant_id"] : 0;

        $credit = isset($this->params["credit"]) && !empty($this->params["credit"]) ? $this->params["credit"] : 0;

        $memo = isset($this->params["memo"]) && !empty($this->params["memo"]) ? $this->params["memo"] : '';

        $type = isset($this->params["type"]) && !empty($this->params["type"]) ? $this->params["type"] : 0;

        if(!$member_id){
            $rt['errcode']=30001;
            $rt['errmsg']='会员ID不存在';
            return Response::json($rt);
        }

        if(!$merchant_id){
            $rt['errcode']=99004;
            $rt['errmsg']='商户ID不存在';
            return Response::json($rt);
        }

        if(!$credit){
            $rt['errcode']=30002;
            $rt['errmsg']='积分不能为空';
            return Response::json($rt);
        }

        $condition = array(
            'give_credit' => $credit,
            'memo' => $memo,
        );

        $result = (new CreditService())->giveCredit($merchant_id,$member_id,$type,$condition);

        $rt['errcode'] = $result['errcode'];
        $rt['errmsg'] = $result['errmsg'];
        return Response::json($rt);
    }

    /*
     * 获取会员积分
     */
    public function getCredit(){
        $member_id   = isset($this->params["member_id"]) && !empty($this->params["member_id"]) ? $this->params["member_id"] : 0;

        $merchant_id = isset($this->params["merchant_id"]) && !empty($this->params["merchant_id"]) ? $this->params["merchant_id"] : 0;

        if(!$member_id){
            $rt['errcode']=30001;
            $rt['errmsg']='会员ID不存在';
            return Response::json($rt);
        }

        if(!$merchant_id){
            $rt['errcode']=99004;
            $rt['errmsg']='商户ID不存在';
            return Response::json($rt);
        }

        $credit = CreditDetail::where(['merchant_id'=>$merchant_id,'member_id'=>$member_id])->orderBy('id','desc')->pluck('final_credit');


        $rt['errcode'] = 0;
        $rt['errmsg'] = '会员积分获取成功';
        $rt['data'] = $credit ? $credit : 0;
        return Response::json($rt);
    }
    
}

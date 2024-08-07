<?php

namespace App\Http\Controllers\Weapp\Weixin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\MemberInfo;
use App\Models\WeixinFormId;
use App\Utils\CacheKey;
use App\Facades\Member;
use Cache;


class AppController extends Controller
{

    private $request;
    private $member_id;
    private $merchant_id;
    private $appid;

    public function __construct(Request $request)
    {
        $this->request = $request;
        $this->member_id = Member::id();
        $this->merchant_id = Member::merchant_id();
        $this->appid = Member::appid();
    }

    public function formid(){
        $formid      = $this->request->input('formid','');
        $type        = $this->request->input('type',1);
        if(empty($formid)  || $formid == 'the formId is a mock one'){
            return ['errcode' => 1, 'errmsg' => 'param null' ];
        }
        $memberInfo = MemberInfo::get_one($this->member_id,$this->appid,$this->merchant_id);
        if(!isset($memberInfo['id'])){
            return ['errcode' => 1, 'errmsg' => 'param error','param'=>['member_id'=>$this->member_id,'appid'=>$this->appid,'merchant_id'=>$this->merchant_id] ];
        }
        $now = time();
        $response = WeixinFormId::insert_data(['merchant_id' => $memberInfo['merchant_id'], 'appid' => $memberInfo['appid'], 'member_id' => $memberInfo['member_id'], 'open_id' => $memberInfo['open_id'], 'formid' => $formid, 'number' => 1 , 'time' => $now + 601200,]);
        if($response){
            return ['errcode' => 0, 'errmsg' => 'ok' ];
        }else{
            return ['errcode' => 2, 'errmsg' => 'sys error' ];
        }
    }

}

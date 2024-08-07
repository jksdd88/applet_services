<?php

/**
 * 用户行为数据采集
 * @author 王禹
 * @cdate 2018-7-25
 * 
 */
namespace App\Http\Controllers\Weapp\Behavior;


use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use App\Facades\Member;
use App\Jobs\MemberBehaviorCollection;
use App\Utils\WxDataCrypt\WxBizDataCrypt;
use Cache;


class BehaviorController extends Controller {

    
    public function __construct(Request $request)
    {
        $this->member_id = Member::id();//会员id
        $this->merchant_id = Member::merchant_id();//商户id
        $this->weapp_id = Member::weapp_id();//小程序id对应weixin_info表主键
        $this->appid = Member::appid();//小程序appid
    }

    public function collection(Request $request)
    {
        $type              = empty($request->type) ? 0 : (int)$request->type;
        $type_id           = empty($request->type_id) ? 0 : (int)$request->type_id;
        $distrib_member_id = !empty($request->distrib_member_id) ? (int)encrypt($request->distrib_member_id, 'D') : 0;
        $share_member_id   = !empty($request->share_member_id) ? (int)encrypt($request->share_member_id, 'D') : 0;
        $session_key       = $request->input('session_key', ''); //登录态
        $encryptedData     = $request->input('encryptedData', ''); //包括敏感数据在内的完整用户信息的加密数据
        $iv                = $request->input('iv', ''); //加密算法的初始向量
        $share             = $request->input('share');

        $chat_group_id = '';

        //只有在群里进入小程序才记录
        if($share == 1 && !empty($session_key) && !empty($encryptedData) && !empty($iv)){
            $session_key = Cache::get($session_key);
            $sessionKey = $session_key && isset($session_key['session_key']) ? $session_key['session_key'] : '';
            if($sessionKey){
                $dataCrypt = new WxBizDataCrypt($this->appid, $sessionKey);
                $resultStatus = $dataCrypt->decryptData($encryptedData, $iv, $result);
                if($resultStatus == 0) {
                    $result = json_decode($result, true);
                    $chat_group_id = $result['openGId'];
                }
            }
        }

        $this->dispatch(new MemberBehaviorCollection($this->member_id, $this->merchant_id, $this->weapp_id, $this->appid, $type, $type_id, $chat_group_id, $distrib_member_id, $share_member_id));

        return Response::json(['errcode'=>0,'errmsg'=>'ok']);
    }
}
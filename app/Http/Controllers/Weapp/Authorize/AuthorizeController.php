<?php

namespace App\Http\Controllers\Weapp\Authorize;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use App\Models\Member as MemberModel;
use App\Models\MemberInfo;
use App\Models\Merchant;
use App\Models\WeixinInfo;
use App\Models\DistribPartner;
use App\Facades\Member;
use App\Services\WeixinService;
use App\Services\MemberService;
use App\Services\DistribService;
use App\Utils\WxDataCrypt\WxBizDataCrypt;
use App\Utils\Encrypt;
use App\Utils\CommonApi;
use Cache;
use Config;
use DB;

class AuthorizeController extends Controller
{

    /**
     * 创建新的控制器实例。
     *
     * @param  TaskRepository $tasks
     * @return void
     */
    public function __construct(WeixinService $weixinService,  MemberService $memberService, DistribService $distribService)
    {
        $this->weixinService  = $weixinService;
        $this->memberService  = $memberService;
        $this->distribService = $distribService;
    }
    
    /**
     * 检测token是否过期
     *
     * @return \Illuminate\Http\Response
     */
    public function checkToken(Request $request)
    {
        $token = $request->token;

        if(Cache::has($token)){
            return ['errcode' => 0];
        }else{
            return ['errcode' => 10001, 'errmsg' => '请重新登录'];
        }
    }

    /**
     * 通过code换取session_key
     *
     * @return \Illuminate\Http\Response
     */
    public function getToken(Request $request)
    {
        $merchant_id = Member::merchant_id();
        $weapp_id    = Member::weapp_id();
        $appid       = Member::appid();
        $code        = $request->input('code', '');

        if(!$code){
            return ['errcode' => 99001, 'errmsg' => '缺少必须参数code'];
        }

        //获取第三方平台 component_access_token
        $component_appid        = Config::get('weixin.component_appid');
        $component_access_token = $this->weixinService->getComponentToken();

        //设置代理
        $proxy = empty(env('PROXY_IP')) ? null : ['proxy' => env('PROXY_IP') . ':' . env('PROXY_PORT')];
        //接口地址
        $apiUrl = 'https://api.weixin.qq.com/sns/component/jscode2session';
        //请求参数
        $query = [
            'appid'                  => $appid,
            'js_code'                => $code,
            'grant_type'             => 'authorization_code',
            'component_appid'        => $component_appid,
            'component_access_token' => $component_access_token
        ];

        $response = mxCurl($apiUrl, $query, false, $proxy);
        $authInfo = json_decode($response, true);

        if (isset($authInfo['errcode'])) { //invalid code
            return ['errcode' => 10005, 'errmsg' => $authInfo['errcode'] . ':' . $authInfo['errmsg']];
        }

        $openid  = $authInfo['openid'];

        //默认数据
        $token = '';
        $encrypt_member_id = '';

        $member_info = MemberInfo::where('open_id', $openid)->where('merchant_id', $merchant_id)->first();
        if($member_info){
            $member_id = $member_info->member_id;
            $encrypt_member_id = encrypt($member_id, 'E');
            //用户认证信息
            $member = MemberModel::get_data_by_id($member_id, $merchant_id);
            if($member){
                MemberModel::update_data($member_id, $merchant_id, ['latest_access_time' => date('Y-m-d H:i:s')]);
                $token_data = [
                    'merchant_id' => $merchant_id,
                    'weapp_id'    => $weapp_id,
                    'appid'       => $appid,
                    'id'          => $member_id,
                    'name'        => $member['name'],
                    'avatar'      => $member['avatar']
                ];
                $token = 'token_' . md5($openid . $authInfo['session_key']);
                Cache::put($token, $token_data, Carbon::now()->addDays(1));
            }
        }
        
        $weapp_session_key = 'session_' . md5($openid . $authInfo['session_key']);
        Cache::put($weapp_session_key, $authInfo, Carbon::now()->addDays(1));

        $data = [
            'token'             => $token,
            'weapp_session_key' => $weapp_session_key,
            'member_id'         => $encrypt_member_id
        ];
        return ['errcode' => 0, 'errmsg' => '登录成功', 'data' => $data];
    }

    /**
     * 买家登录
     *
     * @return \Illuminate\Http\Response
     */
    public function onLogin(Request $request)
    {
        $merchant_id       = Member::merchant_id();
        $weapp_id          = Member::weapp_id();
        $appid             = Member::appid();
        $weapp_session_key = $request->input('weapp_session_key', ''); //登录态
        $rawData           = $request->input('rawData', ''); //不包括敏感信息的原始数据字符串
        $signature         = $request->input('signature', ''); //签名
        $encryptedData     = $request->input('encryptedData', ''); //包括敏感数据在内的完整用户信息的加密数据
        $iv                = $request->input('iv', ''); //加密算法的初始向量
        $distrib_member_id = $request->input('distrib_member_id', ''); //推客会员ID
        $share_member_id   = $request->input('share_member_id', ''); //分享者会员ID
        $client_ip         = get_client_ip(); //客户端IP

        if (!$merchant_id) {
            return ['errcode' => 99001, 'errmsg' => '商户ID是必须的'];
        }

        if (!$weapp_id) {
            return ['errcode' => 99001, 'errmsg' => '小程序ID是必须的'];
        }

        if (!$rawData || !$signature || !$encryptedData || !$iv) {
            return ['errcode' => 99001, 'errmsg' => '缺少必须参数'];
        }

        $weapp_session_key = Cache::get($weapp_session_key);
        if(!$weapp_session_key || !isset($weapp_session_key['session_key'])){
            return ['errcode' => 10009, 'errmsg' => '登录态已失效'];
        }

        $sessionKey = $weapp_session_key['session_key'];
        $signature2 = sha1($rawData . $sessionKey);
        if ($signature2 !== $signature) {
            $errlog = [
                'session_key' => $sessionKey,
                'rawData'     => $rawData,
                'signature'   => $signature,
                'signature2'  => $signature2
            ];
            $wlog = [
                'custom'      => 'signature_error',   //标识字段数据
                'merchant_id' => $merchant_id,        //商户id
                'content'     => json_encode($errlog) //日志内容
            ];
            CommonApi::wlog($wlog);
            return ['errcode' => 10006, 'errmsg' => '授权信息签名不匹配'];
        }

        $dataCrypt = new WxBizDataCrypt($appid, $sessionKey);
        $resultStatus = $dataCrypt->decryptData($encryptedData, $iv, $result);

        if ($resultStatus !== 0) {
            return ['errcode' => 10007, 'errmsg' => '解密授权信息错误'];
        }

        $result = json_decode($result, true);
        //收集unionId不存在的错误信息
        if (!isset($result['unionId'])) {
            $wlog = [
                'custom'      => 'unionid_notset',     //标识字段数据
                'merchant_id' => $merchant_id,        //商户id
                'content'     => json_encode($result) //日志内容
            ];
            CommonApi::wlog($wlog);
        }

        if (!isset($result['openId']) || !isset($result['unionId'])) {
            return ['errcode' => 10008, 'errmsg' => '授权失败，请联系商家'];
        }

        $open_id  = $result['openId'];
        $union_id = $result['unionId'];
        $is_new_user = 0;

        $member_id = MemberInfo::where(['open_id' => $open_id, 'merchant_id' => $merchant_id])->value('member_id');
        if (!$member_id) {
            $member_id = MemberInfo::where(['union_id' => $union_id, 'merchant_id' => $merchant_id])->value('member_id');
            //如果会员不存在，新增粉丝和会员信息
            if (!$member_id) {
                DB::beginTransaction();
                $member_data = [
                    'merchant_id' => $merchant_id,
                    'name'        => $result['nickName'],
                    'avatar'      => $result['avatarUrl'],
                    'gender'      => $result['gender'],
                    'country'     => $result['country'],
                    'province'    => $result['province'],
                    'city'        => $result['city'],
                    'login_ip'    => $client_ip,
                    'login_time'  => date('Y-m-d H:i:s')
                ];
                //新增会员
                $member_id = MemberModel::insert_data($member_data);

                $member_info_data = [
                    'merchant_id' => $merchant_id,
                    'member_id'   => $member_id,
                    'appid'       => $appid,
                    'open_id'     => $open_id,
                    'union_id'    => $union_id,
                    'source_type' => 1
                ];
                //新增粉丝
                if (MemberInfo::insert_data($member_info_data)) {
                    $is_new_user = 1;
                    DB::commit();
                } else {
                    DB::rollBack();
                }
            } else {
                //记录粉丝openid
                $member_info_data = [
                    'merchant_id' => $merchant_id,
                    'member_id'   => $member_id,
                    'appid'       => $appid,
                    'open_id'     => $open_id,
                    'union_id'    => $union_id,
                    'source_type' => 1
                ];

                MemberInfo::insert_data($member_info_data);
            }
        }

        if($member_id){
            //用户认证信息
            $member = MemberModel::get_data_by_id($member_id, $merchant_id);
            $token_data = [
                'merchant_id' => $merchant_id,
                'weapp_id'    => $weapp_id,
                'appid'       => $appid,
                'id'          => $member_id,
                'name'        => $member['name'],
                'avatar'      => $member['avatar']
            ];
            $token = 'token_' . md5($open_id . $sessionKey);
            Cache::put($token, $token_data, Carbon::now()->addDays(1));

            //加密member_id
            $encode_member_id = encrypt($member_id, 'E');

            $data = [
                'token'       => $token,
                'member_id'   => $encode_member_id,
                'is_new_user' => $is_new_user
            ];

            //新用户有礼
            if($is_new_user){
                $new_user_gift_response = $this->memberService->newUserGift([
                    'member_id'   => $member_id,
                    'merchant_id' => $merchant_id
                ]);
                if($new_user_gift_response && $new_user_gift_response['errcode'] == 0){
                    $new_user_gift_id = $new_user_gift_response['data']['new_user_gift_id']; //活动ID
                    $coupon_code_ids  = $new_user_gift_response['data']['coupon_code_ids']; //发送的劵码ID

                    $record_result = $this->memberService->getNewUserGiftRecord([
                        'merchant_id'      => $merchant_id,
                        'new_user_gift_id' => $new_user_gift_id,
                        'coupon_code_ids'  => $coupon_code_ids
                    ]);
                    if (isset($record_result['errcode']) && $record_result['errcode'] == 0) {
                        $data['gift_coupon_list'] = $record_result['data'];
                    }
                }
            }

            //绑定推客关系
            $distrib_member_id = !empty($distrib_member_id) ? encrypt($distrib_member_id, 'D') : 0;
            $share_member_id   = !empty($share_member_id) ? encrypt($share_member_id, 'D') : 0;
            $distrib_member_id = $this->distribService->distribBuyerRelation($member_id, $merchant_id, $distrib_member_id, $share_member_id);
            if($distrib_member_id){
                $encrypt_distrib_member_id = encrypt($distrib_member_id, 'E');
                $data['distrib_member_id'] = $encrypt_distrib_member_id;
            }
            
            return ['errcode' => 0, 'errmsg' => '登录成功', 'data' => $data];
        }

        return ['errcode' => 10002, 'errmsg' => '登录失败'];
    }
}

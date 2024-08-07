<?php

namespace App\Http\Controllers\Weapp\Member;

use App\Models\MemberBalanceDetail;
use App\Services\WeixinMsgService;
use App\Services\WeixinPayService;
use App\Utils\CommonApi;
use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use App\Models\Member as MemberModel;
use App\Models\MemberInfo;
use App\Models\MemberCard;
use App\Models\Merchant;
use App\Models\FormFeedback;
use App\Models\WeixinInfo;
use App\Models\DistribPartner;
use App\Models\DistribSetting;
use App\Models\MerchantSetting;
use App\Facades\Member;
use App\Services\WeixinService;
use App\Services\BuyService;
use App\Services\CouponService;
use App\Services\CreditService;
use App\Services\MemberService;
use App\Services\DistribService;
use App\Utils\WxDataCrypt\WxBizDataCrypt;
use App\Utils\Encrypt;
use App\Utils\SendMessage;
use App\Utils\CacheKey;
use Cache;
use Config;
use Session;
use DB;

class MemberController extends Controller
{
    /**
     * 创建新的控制器实例。
     *
     * @param  TaskRepository $tasks
     * @return void
     */
    public function __construct(WeixinService $weixinService, CouponService $couponService, CreditService $creditService, MemberService $memberService, DistribService $distribService)
    {
        $this->weixinService = $weixinService;
        $this->couponService = $couponService;
        $this->creditService = $creditService;
        $this->memberService = $memberService;
        $this->distribService = $distribService;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $merchant_id = $request->input('merchant_id', 0);
        $open_id = $request->input('open_id', '123456789');
        $weapp_id = $request->input('weapp_id', 0);
        $client_ip = $request->getClientIp();

        if (!$merchant_id) {
            return ['errcode' => -1, 'errmsg' => '商户ID是必须的'];
        }

        if (!$weapp_id) {
            return ['errcode' => -1, 'errmsg' => '小程序ID是必须的'];
        }

        //商户APPID
        $weixin_info = WeixinInfo::get_one('id', $weapp_id, 1);
        $appid = $weixin_info['appid'];

        $union_id = '987654321';

        $member_id = MemberInfo::where(['open_id' => $open_id, 'merchant_id' => $merchant_id])->value('member_id');

        if (!$member_id) {
            $member_id = MemberInfo::where(['union_id' => $open_id, 'merchant_id' => $merchant_id])->value('member_id');

            if (!$member_id) {
                $member_data = [
                    'merchant_id' => $merchant_id,
                    'name' => 'test',
                    'avatar' => 'test',
                    'gender' => 1,
                    'country' => 'china',
                    'province' => 'shanghai',
                    'city' => 'shanghai',
                    'login_ip' => $client_ip,
                    'login_time' => date('Y-m-d H:i:s')
                ];

                $member_id = MemberModel::insert_data($member_data);

                $member_info_data = [
                    'merchant_id' => $merchant_id,
                    'member_id' => $member_id,
                    'open_id' => $open_id,
                    'union_id' => $union_id,
                    'source_type' => 1
                ];

                MemberInfo::insert_data($member_info_data);
            }
        }

        if ($member_id) {

            $token = str_random(32);

            $member = MemberModel::get_data_by_id($member_id, $merchant_id);

            $member->weapp_id = $weapp_id;
            $member->appid = $appid;

            Cache::put($token, $member->toArray(), 60);

            //加密member_id
            $Encrypt = new Encrypt;
            $member_id = $Encrypt->encode($member_id);

            $data = [
                'token' => $token,
                'member_id' => $member_id,
                'userinfo' => [
                    'id' => $member['id'],
                    'merchant_id' => $member['merchant_id'],
                    'name' => $member['name'],
                    'avatar' => $member['avatar'],
                    'gender' => $member['gender'],
                    'mobile' => $member['mobile'],
                    'is_verify_mobile' => $member['is_verify_mobile'],
                    'country' => $member['country'],
                    'province' => $member['province'],
                    'city' => $member['city']
                ]
            ];

            return ['errcode' => 0, 'errmsg' => '登录成功', 'data' => $data];
        }

        return ['errcode' => -1, 'errmsg' => '登录失败'];
    }

    /**
     * 买家登录
     *
     * @return \Illuminate\Http\Response
     */
    public function login(Request $request)
    {
        $merchant_id = Member::merchant_id();
        //小程序ID
        $weapp_id = Member::weapp_id();
        //js_code 
        $code = $request->input('code', '');
        //不包括敏感信息的原始数据字符串
        $rawData = $request->input('rawData', '');
        //签名
        $signature = $request->input('signature', '');
        //包括敏感数据在内的完整用户信息的加密数据
        $encryptedData = $request->input('encryptedData', '');
        //加密算法的初始向量
        $iv = $request->input('iv', '');
        //客户端IP
        $client_ip = get_client_ip();
        //推客ID
        $distrib_member_id = $request->input('distrib_member_id', '');

        if (!$merchant_id) {
            return ['errcode' => 99001, 'errmsg' => '商户ID是必须的'];
        }

        if (!$weapp_id) {
            return ['errcode' => 99001, 'errmsg' => '小程序ID是必须的'];
        }

        if (!$code || !$rawData || !$signature || !$encryptedData || !$iv) {
            return ['errcode' => 99001, 'errmsg' => '缺少必须参数'];
        }

        //商户APPID
        $weixin_info = WeixinInfo::get_one('id', $weapp_id, 1);
        $appid = $weixin_info['appid'];

        //获取第三方平台 component_access_token
        $component_appid = Config::get('weixin.component_appid');
        $component_access_token = $this->weixinService->getComponentToken();

        //使用code换取session_key
        //设置代理
        $proxy = empty(env('PROXY_IP')) ? null : ['proxy' => env('PROXY_IP') . ':' . env('PROXY_PORT')];
        //接口地址
        $apiUrl = 'https://api.weixin.qq.com/sns/component/jscode2session';
        //请求参数
        $query = [
            'appid' => $appid,
            'js_code' => $code,
            'grant_type' => 'authorization_code',
            'component_appid' => $component_appid,
            'component_access_token' => $component_access_token
        ];

        $response = mxCurl($apiUrl, $query, false, $proxy);

        $authInfo = json_decode($response, true);

        if (isset($authInfo['errcode'])) {
            return ['errcode' => 10005, 'errmsg' => $authInfo['errcode'] . ':' . $authInfo['errmsg']];
        }
        if (!isset($authInfo['session_key'])) {
            return ['errcode' => 10005, 'errmsg' => '请求session_key失败'];
        }

        $sessionKey = $authInfo['session_key'];

        //记录session_key，获取微信手机号使用
        Cache::put('member_auth_session_key_' . $appid . '_' . $weapp_id, $sessionKey, Carbon::now()->addDays(3));

        $signature2 = sha1($rawData . $sessionKey);

        if ($signature2 !== $signature) {
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
            \Log::info('小程序授权失败，原因：unionId不存在，商户ID：' . $merchant_id . '，appid：' . $appid);
        }

        if (!isset($result['openId']) || !isset($result['unionId'])) {
            return ['errcode' => 10008, 'errmsg' => '授权失败，请联系商家'];
        }

        $open_id = $result['openId'];
        $union_id = $result['unionId'];

        $is_new_user = 0; //是否是新用户

        $member_info = MemberInfo::where(['open_id' => $open_id, 'merchant_id' => $merchant_id])->first();

        if (!$member_info) {
            $member_id = MemberInfo::where(['union_id' => $union_id, 'merchant_id' => $merchant_id])->value('member_id');
            //如果会员不存在，新增粉丝和会员信息
            if (!$member_id) {
                DB::beginTransaction();
                $member_data = [
                    'merchant_id' => $merchant_id,
                    'name' => $result['nickName'],
                    'avatar' => $result['avatarUrl'],
                    'gender' => $result['gender'],
                    'country' => $result['country'],
                    'province' => $result['province'],
                    'city' => $result['city'],
                    'login_ip' => $client_ip,
                    'login_time' => date('Y-m-d H:i:s')
                ];
                //新增会员
                $member_id = MemberModel::insert_data($member_data);

                $member_info_data = [
                    'merchant_id' => $merchant_id,
                    'member_id' => $member_id,
                    'appid' => $appid,
                    'open_id' => $open_id,
                    'union_id' => $union_id,
                    'source_type' => 1
                ];
                //新增粉丝
                if (MemberInfo::insert_data($member_info_data)) {
                    DB::commit();

                    $is_new_user = 1;
                    //新用户有礼
                    $new_user_gift_response = $this->memberService->newUserGift([
                        'member_id' => $member_id,
                        'merchant_id' => $merchant_id
                    ]);
                } else {
                    DB::rollBack();
                }
            } else {
                //记录粉丝openid
                $member_info_data = [
                    'merchant_id' => $merchant_id,
                    'member_id' => $member_id,
                    'appid' => $appid,
                    'open_id' => $open_id,
                    'union_id' => $union_id,
                    'source_type' => 1
                ];

                MemberInfo::insert_data($member_info_data);
            }
        } else {
            //处理第二期授权流程产生的union_id不一样的问题
            $member_id = $member_info->member_id;
            $member_info_union_id = $member_info->union_id;

            if ($member_info_union_id != $union_id) {
                MemberInfo::where('id', $member_info->id)->update(
                    [
                        'union_id' => $union_id,
                        'updated_time' => date('Y-m-d H:i:s')
                    ]
                );
            }
        }

        if ($member_id) {
            //更新登录时间
            $update_data['login_time'] = date('Y-m-d H:i:s');
            $update_data['login_ip'] = $client_ip;
            //更新昵称和头像
            if(isset($result['nickName']) && !empty($result['nickName']) && isset($result['avatarUrl']) && !empty($result['avatarUrl'])){
                $update_data['name']   = $result['nickName'];
                $update_data['avatar'] = $result['avatarUrl'];
            }
            MemberModel::update_data($member_id, $merchant_id, $update_data);

            //用户认证信息key
            $token = md5($appid . $sessionKey);

            //用户认证信息内容
            $member = MemberModel::get_data_by_id($member_id, $merchant_id);
            $member->weapp_id = $weapp_id;
            $member->appid = $appid;
            $member->open_id = $open_id;
            Cache::put($token, $member, Carbon::now()->addDays(3));

            //加密member_id，短加密串：生成推客二维码有长度限制，所以才使用短加密串
            $encode_member_id = encrypt($member_id, 'E');

            $data = [
                'token' => $token,
                'member_id' => $encode_member_id,
                'is_new_user' => $is_new_user
            ];

            //新用户有礼
            if ($is_new_user && isset($new_user_gift_response) && $new_user_gift_response['errcode'] == 0) {
                //活动ID
                $new_user_gift_id = $new_user_gift_response['data']['new_user_gift_id'];
                //发送的劵码ID
                $coupon_code_ids = $new_user_gift_response['data']['coupon_code_ids'];

                $record_result = $this->memberService->getNewUserGiftRecord([
                    'merchant_id' => $merchant_id,
                    'new_user_gift_id' => $new_user_gift_id,
                    'coupon_code_ids' => $coupon_code_ids
                ]);
                if (isset($record_result['errcode']) && $record_result['errcode'] == 0) {
                    $data['gift_coupon_list'] = $record_result['data'];
                }
            }

            //是否是推客
            $distrib_partner = DistribPartner::get_data_by_memberid($member_id, $merchant_id);
            if ($distrib_partner && in_array($distrib_partner['status'], [1, 2])) {
                $data['distrib_member_id'] = $encode_member_id; 
            }

            return ['errcode' => 0, 'errmsg' => '登录成功', 'data' => $data];
        }

        return ['errcode' => 10002, 'errmsg' => '登录失败'];
    }

    /**
     * 通过code换取session_key
     *
     * @return \Illuminate\Http\Response
     */
    public function getSessionKey(Request $request)
    {
        $merchant_id       = Member::merchant_id();
        $weapp_id          = Member::weapp_id();
        $weapp_session_key = $request->input('weapp_session_key', '');
        $code              = $request->input('code', '');
        $distrib_member_id = $request->input('distrib_member_id', ''); //推客会员ID
        $share_member_id   = $request->input('share_member_id', ''); //分享者会员ID

        $distrib_member_id = !empty($distrib_member_id) ? encrypt($distrib_member_id, 'D') : 0;
        $share_member_id   = !empty($share_member_id) ? encrypt($share_member_id, 'D') : 0;

        if (!$code) {
            return ['errcode' => 99001, 'errmsg' => '缺少必须参数code'];
        }

        $authInfo = '';
        if(!empty($weapp_session_key)){
            $weapp_session = Cache::get($weapp_session_key);
            if($weapp_session && $weapp_session['temp_expir_date'] > time()){
                $authInfo = $weapp_session;
            }else{
                $authInfo = $this->codetosession($weapp_id, $code);
            }
        }else{
            $authInfo = $this->codetosession($weapp_id, $code);
        }

        if (isset($authInfo['errcode'])) {
            return ['errcode' => 10005, 'errmsg' => $authInfo['errcode'] . ':' . $authInfo['errmsg']];
        }

        $openid    = $authInfo['openid'];
        $member_id = MemberInfo::where('merchant_id', $merchant_id)->where('open_id', $openid)->value('member_id');
        
        $encrypt_member_id         = '';
        $encrypt_distrib_member_id = '';
        if($member_id){
            $encrypt_member_id = encrypt($member_id, 'E');
            $distrib_member_id = $this->distribService->distribBuyerRelation($member_id, $merchant_id, $distrib_member_id, $share_member_id);
            if($distrib_member_id){
                $encrypt_distrib_member_id = encrypt($distrib_member_id, 'E');
            }
        }
        
        $weapp_session_key = md5($openid . $authInfo['session_key']);
        $authInfo['temp_expir_date'] = strtotime('+5 minute');
        Cache::put($weapp_session_key, $authInfo, Carbon::now()->addDays(3));

        $data = [
            'weapp_session_key' => $weapp_session_key,
            'member_id'         => $encrypt_member_id,
            'distrib_member_id' => $encrypt_distrib_member_id
        ];
        return ['errcode' => 0, 'errmsg' => '获取session_key成功', 'data' => $data];
    }

    private function codetosession($weapp_id, $code)
    {
        //商户APPID
        $weixin_info = WeixinInfo::get_one('id', $weapp_id, 1);
        $appid       = $weixin_info['appid'];

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

        return $authInfo;
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
        $weapp_session_key = $request->input('weapp_session_key', ''); //登录态
        $rawData           = $request->input('rawData', ''); //不包括敏感信息的原始数据字符串
        $signature         = $request->input('signature', ''); //签名
        $encryptedData     = $request->input('encryptedData', ''); //包括敏感数据在内的完整用户信息的加密数据
        $iv                = $request->input('iv', ''); //加密算法的初始向量
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

        //商户APPID
        $weixin_info = WeixinInfo::get_one('id', $weapp_id, 1);
        $appid       = $weixin_info['appid'];

        //记录session_key，获取微信手机号使用
        Cache::put('member_auth_session_key_' . $appid . '_' . $weapp_id, $sessionKey, Carbon::now()->addDays(3));

        $signature2 = sha1($rawData . $sessionKey);

        if ($signature2 !== $signature) {
            $errlog = [
                'session_key' => $sessionKey,
                'rawData'     => $rawData,
                'signature'   => $signature,
                'signature2'  => $signature2
            ];
            $wlog = [
                'custom'      => 'signature_error',     //标识字段数据
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
            \Log::info('小程序授权失败，原因：unionId不存在，商户ID：' . $merchant_id . '，appid：' . $appid);
        }

        if (!isset($result['openId']) || !isset($result['unionId'])) {
            return ['errcode' => 10008, 'errmsg' => '授权失败，请联系商家'];
        }

        $open_id  = $result['openId'];
        $union_id = $result['unionId'];

        $is_new_user = 0; //是否是新用户

        $member_info = MemberInfo::where(['open_id' => $open_id, 'merchant_id' => $merchant_id])->first();

        if (!$member_info) {
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
                    DB::commit();

                    $is_new_user = 1;
                    //新用户有礼
                    $new_user_gift_response = $this->memberService->newUserGift([
                        'member_id'   => $member_id,
                        'merchant_id' => $merchant_id
                    ]);
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
        } else {
            //处理第二期授权流程产生的union_id不一样的问题
            $member_id            = $member_info->member_id;
            $member_info_union_id = $member_info->union_id;

            if ($member_info_union_id != $union_id) {
                MemberInfo::where('id', $member_info->id)->update(
                    [
                        'union_id'     => $union_id,
                        'updated_time' => date('Y-m-d H:i:s')
                    ]
                );
            }
        }

        if ($member_id) {
            //更新登录时间
            $update_data['login_time'] = date('Y-m-d H:i:s');
            $update_data['login_ip']   = $client_ip;
            //更新昵称和头像
            if(isset($result['nickName']) && !empty($result['nickName']) && isset($result['avatarUrl']) && !empty($result['avatarUrl'])){
                $update_data['name']   = $result['nickName'];
                $update_data['avatar'] = $result['avatarUrl'];
            }
            MemberModel::update_data($member_id, $merchant_id, $update_data);

            //用户认证信息key
            $token = md5($appid . $sessionKey);

            //用户认证信息内容
            $member = MemberModel::get_data_by_id($member_id, $merchant_id);
            $member->weapp_id = $weapp_id;
            $member->appid    = $appid;
            $member->open_id  = $open_id;
            Cache::put($token, $member, Carbon::now()->addDays(3));

            //加密member_id，短加密串：生成推客二维码有长度限制，所以才使用短加密串
            $encode_member_id = encrypt($member_id, 'E');

            $data = [
                'token'       => $token,
                'member_id'   => $encode_member_id,
                'is_new_user' => $is_new_user
            ];

            //新用户有礼
            if ($is_new_user && isset($new_user_gift_response) && $new_user_gift_response['errcode'] == 0) {
                //活动ID
                $new_user_gift_id = $new_user_gift_response['data']['new_user_gift_id'];
                //发送的劵码ID
                $coupon_code_ids  = $new_user_gift_response['data']['coupon_code_ids'];

                $record_result = $this->memberService->getNewUserGiftRecord([
                    'merchant_id'      => $merchant_id,
                    'new_user_gift_id' => $new_user_gift_id,
                    'coupon_code_ids'  => $coupon_code_ids
                ]);
                if (isset($record_result['errcode']) && $record_result['errcode'] == 0) {
                    $data['gift_coupon_list'] = $record_result['data'];
                }
            }

            //是否是推客
            $distrib_partner = DistribPartner::get_data_by_memberid($member_id, $merchant_id);
            if ($distrib_partner && in_array($distrib_partner['status'], [1, 2])) {
                $data['distrib_member_id'] = $encode_member_id; 
            }

            return ['errcode' => 0, 'errmsg' => '登录成功', 'data' => $data];
        }

        return ['errcode' => 10002, 'errmsg' => '登录失败'];
    }

    /**
     * 授权登录
     *
     * @return \Illuminate\Http\Response
     */
    public function webAuth(Request $request)
    {
        $merchant_id = $request->merchant_id;
        $return_url = $request->return_url;

        if (!$merchant_id) {
            echo '商户ID是必须的';
            exit;
        }

        if (!$return_url) {
            echo '回调地址就必须的';
            exit;
        }

        //校验merchant_id是否有效
        $merchant = Merchant::get_data_by_id($merchant_id);
        if (!$merchant) {
            echo '商家不存在';
            exit;
        }

        $weixin_info = WeixinInfo::get_one('merchant_id', $merchant_id, 2);
        $appid = $weixin_info['appid'];

        //获取第三方平台 component_appid
        $component_appid = Config::get('weixin.component_appid');

        $redirect_uri = urlencode(env('APP_URL') . '/weapp/member/webauth_back.json?return_url=' . urlencode($return_url));

        $apiUrl = 'https://open.weixin.qq.com/connect/oauth2/authorize?appid=' . $appid . '&redirect_uri=' . $redirect_uri . '&response_type=code&scope=snsapi_userinfo&state=' . $merchant_id . '&component_appid=' . $component_appid . '#wechat_redirect';

        return redirect($apiUrl);
    }

    /**
     * 授权回调
     *
     * @return \Illuminate\Http\Response
     */
    public function webAuthBack(Request $request)
    {
        $return_url = $request->return_url;
        $code = $request->code;
        $appid = $request->appid;
        $merchant_id = $request->state;
        $client_ip = $request->getClientIp();

        //校验merchant_id是否有效
        $merchant = Merchant::get_data_by_id($merchant_id);
        if (!$merchant) {
            echo '商家不存在';
            exit;
        }

        //获取第三方平台 component_access_token
        $component_appid = Config::get('weixin.component_appid');
        $component_access_token = $this->weixinService->getComponentToken();

        //设置代理
        $proxy = empty(env('PROXY_IP')) ? null : ['proxy' => env('PROXY_IP') . ':' . env('PROXY_PORT')];
        //接口地址
        $apiUrl = 'https://api.weixin.qq.com/sns/oauth2/component/access_token';
        //请求参数
        $query = [
            'appid' => $appid,
            'code' => $code,
            'grant_type' => 'authorization_code',
            'component_appid' => $component_appid,
            'component_access_token' => $component_access_token
        ];
        $result = mxCurl($apiUrl, $query, false, $proxy);
        $result = json_decode($result, true);

        if(!isset($result['access_token']) || !isset($result['openid']) || !isset($result['unionid'])){
            echo '授权失败，请联系商家';
            exit;
        }
        
        $access_token = $result['access_token'];
        $open_id = $result['openid'];
        $union_id = $result['unionid'];
        $userinfo = $this->getUserInfo($access_token, $open_id);

        if ($userinfo === false) {
            echo '获取粉丝信息失败';
            exit;
        }

        $member_info = MemberInfo::where(['open_id' => $open_id, 'merchant_id' => $merchant_id])->first();

        if (!$member_info) {
            $member_id = MemberInfo::where(['union_id' => $union_id, 'merchant_id' => $merchant_id])->value('member_id');
            //如果会员不存在，新增粉丝和会员信息
            if (!$member_id) {
                DB::beginTransaction();
                $member_data = [
                    'merchant_id' => $merchant_id,
                    'name'        => $userinfo['nickname'],
                    'avatar'      => $userinfo['headimgurl'],
                    'gender'      => $userinfo['sex'],
                    'country'     => $userinfo['country'],
                    'province'    => $userinfo['province'],
                    'city'        => $userinfo['city'],
                    'login_ip'    => $client_ip,
                    'login_time'  => date('Y-m-d H:i:s')
                ];
                //新增会员
                $member_id = MemberModel::insert_data($member_data);

                $member_info_data = [
                    'merchant_id' => $merchant_id,
                    'member_id' => $member_id,
                    'appid' => $appid,
                    'open_id' => $open_id,
                    'union_id' => $union_id,
                    'source_type' => 2
                ];
                //新增粉丝
                if (MemberInfo::insert_data($member_info_data)) {
                    DB::commit();
                } else {
                    DB::rollBack();
                }
            } else {
                //新增粉丝openid
                $member_info_data = [
                    'merchant_id' => $merchant_id,
                    'member_id' => $member_id,
                    'appid' => $appid,
                    'open_id' => $open_id,
                    'union_id' => $union_id,
                    'source_type' => 2
                ];

                MemberInfo::insert_data($member_info_data);
            }
        } else {
            $member_id = $member_info->member_id;
            //如果是先关注的粉丝，是没会员信息的
            if (!$member_id) {

                $member_id = MemberInfo::where(['union_id' => $union_id, 'merchant_id' => $merchant_id])->value('member_id');

                DB::beginTransaction();
                if (!$member_id) {
                    $member_data = [
                        'merchant_id' => $merchant_id,
                        'name' => $userinfo['nickname'],
                        'avatar' => $userinfo['headimgurl'],
                        'gender' => $userinfo['sex'],
                        'country' => $userinfo['country'],
                        'province' => $userinfo['province'],
                        'city' => $userinfo['city'],
                        'login_ip' => $client_ip,
                        'login_time' => date('Y-m-d H:i:s')
                    ];
                    //新增会员
                    $member_id = MemberModel::insert_data($member_data);
                }

                if (MemberInfo::where('id', $member_info->id)->update(['member_id' => $member_id, 'union_id' => $union_id])) {
                    DB::commit();
                } else {
                    DB::rollBack();
                }
            }
        }

        if ($member_id) {
            //更新登录时间
            $update_data['login_time'] = date('Y-m-d H:i:s');
            //更新昵称和头像
            if(isset($userinfo['nickname']) && !empty($userinfo['nickname']) && isset($userinfo['headimgurl']) && !empty($userinfo['headimgurl'])){
                $update_data['name']   = $userinfo['nickname'];
                $update_data['avatar'] = $userinfo['headimgurl'];
            }
            MemberModel::update_data($member_id, $merchant_id, $update_data);
            //加密openid
            $Encrypt = new Encrypt;
            $open_id = $Encrypt->encode($open_id, 'dodoca_applet_webauth');
            $return_url = str_replace('OPEN_ID_E', $open_id, $return_url);
            return redirect($return_url);
        } else {
            echo '获取会员信息失败';
            exit;
        }
    }

    /**
     * 获取会员信息
     *
     * @return \Illuminate\Http\Response
     */
    private function getUserInfo($access_token, $openid)
    {
        //设置代理
        $proxy = empty(env('PROXY_IP')) ? null : ['proxy' => env('PROXY_IP') . ':' . env('PROXY_PORT')];
        //接口地址
        $apiUrl = 'https://api.weixin.qq.com/sns/userinfo';
        //请求参数
        $query = [
            'access_token' => $access_token,
            'openid' => $openid,
            'lang' => 'zh_CN'
        ];
        $result = mxCurl($apiUrl, $query, false, $proxy);
        $result = json_decode($result, true);

        return isset($result['openid']) ? $result : false;
    }

    /**
     * 获取微信绑定手机号
     *
     * @return \Illuminate\Http\Response
     */
    public function getPhoneNumber(Request $request)
    {
        $merchant_id = Member::merchant_id();
        //小程序ID
        $weapp_id = Member::weapp_id();

        $code = $request->input('code', '');
        //包括敏感数据在内的完整用户信息的加密数据
        $encryptedData = $request->encryptedData;
        //加密算法的初始向量
        $iv = $request->iv;

        if (!$merchant_id) {
            return ['errcode' => 99001, 'errmsg' => '商户ID是必须的'];
        }

        if (!$weapp_id) {
            return ['errcode' => 99001, 'errmsg' => '小程序ID是必须的'];
        }

        if (!$encryptedData || !$iv) {
            return ['errcode' => 99001, 'errmsg' => '缺少必须参数'];
        }

        //商户APPID
        $weixin_info = WeixinInfo::get_one('id', $weapp_id, 1);
        $appid = $weixin_info['appid'];

        if ($code) {
            //获取第三方平台 component_access_token
            $component_appid = Config::get('weixin.component_appid');
            $component_access_token = $this->weixinService->getComponentToken();

            //使用code换取session_key
            //设置代理
            $proxy = empty(env('PROXY_IP')) ? null : ['proxy' => env('PROXY_IP') . ':' . env('PROXY_PORT')];
            //接口地址
            $apiUrl = 'https://api.weixin.qq.com/sns/component/jscode2session';
            //请求参数
            $query = [
                'appid' => $appid,
                'js_code' => $code,
                'grant_type' => 'authorization_code',
                'component_appid' => $component_appid,
                'component_access_token' => $component_access_token
            ];

            $response = mxCurl($apiUrl, $query, false, $proxy);

            $authInfo = json_decode($response, true);

            if (isset($authInfo['errcode'])) {
                return ['errcode' => 10005, 'errmsg' => $authInfo['errcode'] . ':' . $authInfo['errmsg']];
            }
            if (!isset($authInfo['session_key'])) {
                return ['errcode' => 10005, 'errmsg' => '请求session_key失败'];
            }

            $sessionKey = $authInfo['session_key'];
        } else {
            $sessionKey = Cache::get('member_auth_session_key_' . $appid . '_' . $weapp_id);
        }

        $dataCrypt = new WxBizDataCrypt($appid, $sessionKey);
        $resultStatus = $dataCrypt->decryptData($encryptedData, $iv, $result);

        if ($resultStatus !== 0) {
            return ['errcode' => 10007, 'errmsg' => '解密授权信息错误'];
        }

        $result = json_decode($result, true);

        if ($result) {
            if (isset($result['watermark'])) unset($result['watermark']);
            return ['errcode' => 0, 'data' => $result];
        }
    }

    /**
     * 发送验证码
     *
     * @param int $merchant_id 商户id
     * @param int $type 类型
     * @param string $mobile 手机号
     * @author denghongmei@dodoca.com
     *
     * @return \Illuminate\Http\Response
     */
    public function sendMessage(Request $request)
    {
        $param = $request->all();
        $member_id = Member::id();
        $merchant_id = Member::merchant_id();
        $mobile = isset($param['mobile']) ? $param['mobile'] : '';
        $type = isset($param['type']) ? $param['type'] : 0;
        $captcha = isset($param['captcha']) ? $param['captcha'] : '';

        if (!$merchant_id) {
            return ['errcode' => 99001, 'errmsg' => '商户ID不存在'];
        }
        if (!$mobile) {
            return ['errcode' => 99001, 'errmsg' => '联系电话不存在'];
        }
        if (strlen($mobile) != 11) {
            return ['errcode' => 99001, 'errmsg' => '联系电话格式不正确'];
        }
        if (!$captcha) {
            return ['errcode' => 99001, 'errmsg' => '图片验证码不能为空'];
        }

        $captcha_key = CacheKey::get_member_captcha_key($merchant_id, $member_id);
        $captcha_code = Cache::get($captcha_key);

        if($captcha_code != strtolower($captcha)){
            $wlog = [
                'custom'      => 'bind_phone_captcha_error',     //标识字段数据
                'merchant_id' => $merchant_id,        //商户id
                'member_id'   => $member_id,
                'content'     => json_encode([
                    'cache_captcha' => $captcha_code,
                    'input_captcha' => $captcha
                ]) //日志内容
            ];
            CommonApi::wlog($wlog);
            return ['errcode' => 110006, 'errmsg' => '图片验证码不正确'];
        }
        $sms_str = rand(100000, 999999);
        $sms_content = '您的验证码是：'.$sms_str;

        //发送验证码
        $result = SendMessage::send_sms($mobile, $sms_content, $type);

        $key = CacheKey::get_member_sms_by_mobile_key($mobile, $merchant_id);
        $expiresAt = Carbon::now()->addMinutes(5);
        Cache::put($key, $sms_str, $expiresAt);   //有效期5分钟

        if ($result) {
            return ['errcode' => 0, 'errmsg' => '发送成功', 'data' => $result];
        } else {
            return ['errcode' => 110004, 'errmsg' => '发送失败！'];
        }
    }

    /**
     * 校验验证码
     *
     * @param int $merchant_id 商户id
     * @param string $mobile 手机号
     * @param string $sms_str 验证码
     * @author denghongmei@dodoca.com
     *
     * @return \Illuminate\Http\Response
     */
    public function verifyMessage(Request $request)
    {
        $param = $request->all();
        $merchant_id = Member::merchant_id();
        $mobile = isset($param['mobile']) ? $param['mobile'] : '';
        $sms_str = isset($param['sms_str']) ? $param['sms_str'] : '';

        if (!$merchant_id) {
            return ['errcode' => 99001, 'errmsg' => '商户ID不存在'];
        }
        if (!$mobile) {
            return ['errcode' => 99001, 'errmsg' => '联系电话不存在'];
        }
        if (strlen($mobile) != 11) {
            return ['errcode' => 99001, 'errmsg' => '联系电话格式不正确'];
        }
        if (!$sms_str) {
            return ['errcode' => 99001, 'errmsg' => '短信验证码不存在'];
        }

        $key = CacheKey::get_member_sms_by_mobile_key($mobile, $merchant_id);
        $data = Cache::get($key);
        if (!$data) {
            return ['errcode' => 110005, 'errmsg' => '短信验证码无效或已过期'];
        }
        if ($data != $sms_str) {
            return ['errcode' => 110006, 'errmsg' => '短信验证码不正确'];
        }

        return ['errcode' => 0, 'errmsg' => '验证成功'];
    }


    /**
     * 绑定/更改手机号
     *
     * @param int $merchant_id 商户id
     * @param string $mobile 手机号
     * @param string $sms_str 验证码
     * @author denghongmei@dodoca.com
     *
     * @return \Illuminate\Http\Response
     */
    public function postEditMobile(Request $request)
    {
        $param = $request->all();
        $member_id = Member::id();
        $merchant_id = Member::merchant_id();
        $mobile = isset($param['mobile']) ? $param['mobile'] : '';
        $sms_str = isset($param['sms_str']) ? $param['sms_str'] : '';

        if (!$merchant_id) {
            return ['errcode' => 99001, 'errmsg' => '商户ID不存在'];
        }
        if (!$member_id) {
            return ['errcode' => 99001, 'errmsg' => '会员ID不存在'];
        }
        if (!$mobile) {
            return ['errcode' => 99001, 'errmsg' => '联系电话不存在'];
        }
        if (strlen($mobile) != 11) {
            return ['errcode' => 99001, 'errmsg' => '联系电话格式不正确'];
        }
        if (!$sms_str) {
            return ['errcode' => 99001, 'errmsg' => '验证码不存在'];
        }

        $member = MemberModel::get_data_by_id($member_id, $merchant_id);
        if (!$member) {
            return ['errcode' => 110007, 'errmsg' => '该会员不存在'];
        }

        if ($member['mobile'] == $mobile) {
            return ['errcode' => 110008, 'errmsg' => '新旧手机号不能相同'];
        }

        $wheres = [
            [
                'column'   => 'merchant_id',
                'value'    => $merchant_id, 
                'operator' => '='
            ],
            [
                'column'   => 'mobile',
                'value'    => $mobile, 
                'operator' => '='
            ]
        ];
        $count = MemberModel::get_data_count($wheres);
        if ($count) {
            return ['errcode' => 110008, 'errmsg' => '该手机号码已被注册'];
        }

        $key = CacheKey::get_member_sms_by_mobile_key($mobile, $merchant_id);
        $data = Cache::get($key);
        if (!$data) {
            return ['errcode' => 110005, 'errmsg' => '验证码无效或已过期'];
        }
        if ($data != $sms_str) {
            return ['errcode' => 110006, 'errmsg' => '验证码不正确'];
        }

        if ($member['is_verify_mobile']) {
            $result = MemberModel::update_data($member_id, $merchant_id, ['mobile' => $mobile]);
        } else {
            $result = MemberModel::update_data($member_id, $merchant_id, ['mobile' => $mobile, 'is_verify_mobile' => 1]);
            //绑定手机送积分
            $this->creditService->giveCredit($merchant_id, $member_id, 1);
        }

        if ($result) {
            return ['errcode' => 0, 'errmsg' => '修改成功', 'data' => $result];
        } else {
            return ['errcode' => -1, 'errmsg' => '修改失败！'];
        }

    }

    /**
     * 我的-获取用户信息
     *
     * @param int $merchant_id 商户id
     * @author denghongmei@dodoca.com
     *
     * @return \Illuminate\Http\Response
     */
    public function getMemberInfo(Request $request)
    {
        $param = $request->all();
        $member_id = Member::id();
        $merchant_id = Member::merchant_id();
        //小程序ID
        $weapp_id = Member::weapp_id();

        if (!$merchant_id) {
            return ['errcode' => 99001, 'errmsg' => '商户ID不存在'];
        }

        if (!$member_id) {
            return ['errcode' => 99001, 'errmsg' => '会员ID不存在'];
        }

        if (!$weapp_id) {
            return ['errcode' => 99001, 'errmsg' => '小程序ID是必须的'];
        }

        $baseinfo = MemberModel::get_data_by_id($member_id, $merchant_id);
        if (!$baseinfo) {
            return ['errcode' => 110007, 'errmsg' => '用户不存在'];
        }

        //会员卡名称
        if ($baseinfo['member_card_id']) {
            $member_card = MemberCard::get_data_by_id($baseinfo['member_card_id'], $merchant_id);
            $baseinfo['member_card'] = $member_card['card_name'];
        } else {
            $baseinfo['member_card'] = '默认会员';
        }

        //可用优惠券数量
        $status = 0;
        $f_params = [
            'merchant_id' => $merchant_id,
            'member_id' => $member_id,
            'status' => $status
        ];
        $baseinfo['coupon_count'] = $this->couponService->forMemberCount($f_params);

        //各种状态下订单数量
        $p_params = [
            'merchant_id' => $merchant_id,  //商户id
            'member_id' => $member_id,  //会员id
        ];
        $ordersum = BuyService::ordersum($p_params);
        $baseinfo['order_count'] = $ordersum['data'];

        //计算表单反馈数量
        $form_count = FormFeedback::where('member_id', $member_id)->where('merchant_id', $merchant_id)->where('wxinfo_id', $weapp_id)->where('is_delete', 1)->count();
        $baseinfo['form_count'] = $form_count;

        //商户是否开启推客
        $DistribSetting = DistribSetting::get_data_by_merchant_id($merchant_id);
        if(!empty($DistribSetting) && $DistribSetting['status'] == 1){
            $baseinfo['on_distrib'] = 1;//开启
            //是否是推客
        }else{
            $baseinfo['on_distrib'] = 0;
        }

        $distrib_partner = DistribPartner::get_data_by_memberid($member_id, $merchant_id);
        $baseinfo['distrib_partner'] = $distrib_partner;
        if($distrib_partner){
            //推广活动未读
            $results = DB::select('SELECT COUNT(1) AS unread FROM distrib_activity AS a LEFT JOIN (SELECT * FROM distrib_activity_relation WHERE merchant_id = ? AND distrib_member_id = ?) temp ON a.id = temp.distrib_activity_id WHERE a.merchant_id = ? AND a.send_time >= ? AND a.send_time <= ? AND a.is_delete=1 AND temp.distrib_member_id IS NULL', [$merchant_id, $member_id, $merchant_id, $distrib_partner['check_time'], date('Y-m-d H:i:s')]);
            $baseinfo['distrib_activity_unread'] = isset($results[0]) ? $results[0]->unread : 0;
        }
        
        //是否开启购买记录
        $merchant_set = MerchantSetting::get_data_by_id($merchant_id);
        if(!empty($merchant_set)){
            $baseinfo['knowledge_record'] = $merchant_set['knowledge_record'];
        }else{
            $baseinfo['knowledge_record'] = 1;
        }
        return ['errcode' => 0, 'errmsg' => '获取数据成功', 'data' => $baseinfo];
    }

    /**
     * 更新会员信息
     *
     * @return \Illuminate\Http\Response
     */
    public function setMemberInfo(Request $request)
    {
        $member_id   = Member::id();
        $merchant_id = Member::merchant_id();
        $name        = $request->name;
        $avatar      = $request->avatar;
        $gender      = $request->gender;
        $country     = $request->country;
        $province    = $request->province;
        $city        = $request->city;

        $data = [
            'name'     => $name,
            'avatar'   => $avatar,
            'gender'   => $gender,
            'country'  => $country,
            'province' => $province,
            'city'     => $city
        ];

        if(MemberModel::update_data($member_id, $merchant_id, $data)){
            return ['errcode' => 0, 'errmsg' => '更新成功'];
        }
    }

    /**
     * 会员、推客余额变动记录
     * @return array
     * @author: tangkang@dodoca.com
     */
    public function banlanceLog()
    {
        $merchant_id = Member::merchant_id();
        $param = request()->all();
        $param['member_id'] = Member::id();
        $data = MemberBalanceDetail::get_lists($param, $merchant_id);
        return ['errcode' => 0, 'errmsg' => 'OK', 'data' => $data];
    }

    /**
     * 提现中金额
     * @return array
     * @author: tangkang@dodoca.com
     */
    public function withdrawing()
    {
        $member_id = Member::id();
        $merchant_id = Member::merchant_id();

        //提现中
        $data['amount'] = MemberBalanceDetail::where('merchant_id', $merchant_id)
            ->where('member_id', $member_id)
            ->where('is_delete', 1)
            ->whereIn('type', [MemberService::BALANCE_WEIXIN, MemberService::BALANCE_ALIPAY, MemberService::BALANCE_BANK])
            ->whereIn('status', [TAKECASH_AWAIT, TAKECASH_SUBMIT, TAKECASH_FAIL])->sum('amount');

        //余额
        $data['balance'] = MemberModel::get_data_by_id($member_id, $merchant_id);
        if (empty($data['balance'])) {
            return ['errcode' => 110007, 'errmsg' => '用户不存在'];
        }
        $data['balance'] = $data['balance']['balance'];

        $data['takecash_type'] = DistribSetting::get_data_by_merchant_id($merchant_id);
//        if (empty($data['takecash_type'])) {
//            return ['errcode' => 0, 'errmsg' => '未设置提现方式'];
//        }
        $data['takecash_type'] = $data['takecash_type']['takecash_type'];
        return ['errcode' => 0, 'errmsg' => 'OK', 'data' => $data];
    }

    /**
     * 提现到微信零钱
     * @return array
     * @author: tangkang@dodoca.com
     */
    public function withdrawWx(Requests\Weapp\Member\WithdrawWxRequest $request)
    {
        $request_param = $request->all();

        $setting = DistribSetting::get_data_by_merchant_id(Member::merchant_id());
        if ($setting['takecash_type'] != MemberService::BALANCE_WEIXIN) {
            return ['errcode' => 1, 'errmsg' => '商户未设置该提现方式'];
        }

        if ($today_amount = $request->todayAmount() > 1000000) {
            $today = $today_amount / 10000;
            $msg = '每日提现总额不可超过 100 万元，今日已提交申请 %.4f 万元，还可提交 %.4f 万元';
            $msg = sprintf($msg, $today, 100 - $today);
            return ['errcode' => 1, 'errmsg' => $msg];
        }
        $request_param['member_id'] = Member::id();
        $request_param['wxinfo_id'] = Member::weapp_id();
        $request_param['appid'] = Member::appid();
        $request_param['type'] = MemberService::BALANCE_WEIXIN;
        $res = MemberService::withdraw($request_param, Member::merchant_id());
        return $res;
    }

    /**
     * 提现到支付宝
     * @return array
     * @author: tangkang@dodoca.com
     */
    public function withdrawAlipay(Requests\Weapp\Member\WithdrawAlipayRequest $request)
    {
        if (app()->isLocal()) {
            $member_id = 2;
            $merchant_id = 2;
            $weapp_id = 5;
            $appid = 5;
        }else{
            $member_id = Member::id();
            $merchant_id = Member::merchant_id();
            $weapp_id = Member::weapp_id();
            $appid = Member::appid();
        }
        $request_param = $request->all();

        $setting = DistribSetting::get_data_by_merchant_id($merchant_id);
        if ($setting['takecash_type'] != MemberService::BALANCE_ALIPAY) {
            return ['errcode' => 1, 'errmsg' => '商户未设置该提现方式'];
        }

        $request_param['member_id'] = $member_id;
        $request_param['wxinfo_id'] = $weapp_id;
        $request_param['appid'] = $appid;
        $request_param['type'] = MemberService::BALANCE_ALIPAY;
        $res = MemberService::withdraw($request_param, $merchant_id);
        return $res;
    }

    /**
     * 提现到银行卡
     * @return array
     * @author: tangkang@dodoca.com
     */
    public function withdrawBank(Requests\Weapp\Member\WithdrawBankRequest $request)
    {
        $request_param = $request->all();

        $setting = DistribSetting::get_data_by_merchant_id(Member::merchant_id());
        if ($setting['takecash_type'] != 2) {//setting银行卡和支付宝都是2
            return ['errcode' => 1, 'errmsg' => '商户未设置该提现方式'];
        }

        $request_param['member_id'] = Member::id();
        $request_param['wxinfo_id'] = Member::weapp_id();
        $request_param['appid'] = Member::appid();
        $request_param['type'] = MemberService::BALANCE_BANK;
        $res = MemberService::withdraw($request_param, Member::merchant_id());
        return $res;
    }

}

<?php
/**
 * Created by PhpStorm.
 * User: wangshiliang@yunca.com
 * Desc: 授权和通信
 * link: https://open.weixin.qq.com/cgi-bin/showdocument?action=dir_list&t=resource/res_list&verify=1&id=open1453779503
 * Date: 15/7/21
 * Time: 下午8:08
 */

namespace App\Utils\Weixin;

use App\Utils\Weixin\Http;
use App\Utils\Weixin\MsgCrypt;
use Config;

class Component {

    const WEIXIN_API_BASE = 'https://api.weixin.qq.com/cgi-bin/';

    protected $appId;
    protected $appSecret;
    protected $token;
    protected $encodingAesKey;
    protected $componentToken;

    protected $http;

    public $http_response ;

    public $func_list = [
        1 => '消息管理权限',  2 => '用户管理权限',    3 => '帐号服务权限',  4 => '网页服务权限',  5 => '微信小店权限',
        6 => '微信多客服权限',7 => '群发与通知权限',  8 => '微信卡券权限',  9 => '微信扫一扫权限',10 => '微信连WIFI权限',
        11 => '素材管理权限', 12 => '微信摇周边权限', 13 => '微信门店权限', 14 => '微信支付权限', 15 => '自定义菜单权限',
        22 => '城市服务接口权限', 23 => '广告管理权限', 24 => '开放平台帐号管理权限', 26 => '微信电子发票权限',
        17 => '帐号管理权限', 18 => '开发管理权限', 19 => '客服消息管理权限',20 => '微信登录权限'/*废弃*/, 21 => '数据分析权限',25 => '第三方平台管理权限',30 => '小程序基本信息设置权限',31=>'小程序认证权限'
    ];
    public $option_list = [
        1 => 'location_report', //地理位置上报选项: 0 无上报 1 进入会话时上报 2 每5s上报
        2  => 'voice_recognize', //语音识别开关选项: 0 关 1 开
        3 => 'customer_service', //多客服开关选项:  0 关 1 开
    ];


    public function __construct($config = [])
    {
        if(empty($config)){
            $this->appId     = Config::get('weixin.component_appid');
            $this->appSecret = Config::get('weixin.component_appsecret');
            $this->token     = Config::get('weixin.component_token');
            $this->encodingAesKey = Config::get('weixin.component_key');
        }else{
            $this->appId     = $config['component_appid'];
            $this->appSecret = $config['component_appsecret']; //Config::get('weixin.component_appsecret');
            $this->token     = $config['component_token'];
            $this->encodingAesKey =$config['component_key'];
        }

        $this->http = new Http();
    }

    /**
     * @anme 获取推送内容
     * @return  xml
     */
    public function getReceiveXml($getData)
    {
        $crypter  = new MsgCrypt($this->token, $this->encodingAesKey, $this->appId);
        if(isset($getData['msg_signature'])){
            $result = $crypter->decryptMsg($getData['msg_signature'], $getData['timestamp'], $getData['nonce'], file_get_contents("php://input"));
        }else{
            $result[0] = true;
        }
         return $result;
    }
    /**
     * @anme 返回推送
     * @return encrypt string
     */
    public function responsReceiveXml($getData,$content,$repType)
    {
        $crypter = new MsgCrypt($this->token, $this->encodingAesKey, $this->appId);
        $input = $crypter->decryptMsg($getData['msg_signature'], $getData['timestamp'], $getData['nonce'], file_get_contents("php://input"));
        if ($input[0]) {
            return false;
        }
        $input = (array)simplexml_load_string($input[1], 'SimpleXMLElement', LIBXML_NOCDATA);
        $xml = "<xml><ToUserName><![CDATA[".$input['FromUserName']."]]></ToUserName><FromUserName><![CDATA[". $input['ToUserName']."]]></FromUserName> <CreateTime>".time()."</CreateTime> <MsgType><![CDATA[".$repType."]]></MsgType><Content><![CDATA[".$content."]]></Content></xml>";
        $output = $crypter ->encryptMsg($xml, $getData['timestamp'], $getData['nonce']);
        return $output[1];
    }

    /**
     * @anme 返回推送
     * @return encrypt string
     */

    public function responsReceive($getData,$xml){
        $crypter = new MsgCrypt($this->token, $this->encodingAesKey, $this->appId);
        $output = $crypter ->encryptMsg($xml, $getData['timestamp'], $getData['nonce']);
        return $output[1];
    }


    public function setComponentToken($componentToken)
    {
        $this->componentToken = $componentToken;
        return $this;
    }

    /**
     * @name 获取第三方平台component_access_token
     * @param string componentVerifyTicket
     * @return json {"component_appid":"appid_value" ,"component_appsecret": "appsecret_value","component_verify_ticket": "ticket_value"}
     */
    public function getComponentToken($componentVerifyTicket)
    {
        return $this->mxCurl('component/api_component_token',json_encode([
            'component_appid' => $this->appId,
            'component_appsecret' => $this->appSecret,
            'component_verify_ticket' => $componentVerifyTicket
        ]));
    }

    /**
     * @name 接口调用次数限制清理
     * @param string $offset
     * @param int $count
     * @return json {"errcode":0,"errmsg":"ok"}
     */
    public function clearQuota(){
        return $this->mxCurl('component/clear_quota?component_access_token='.$this->componentToken,json_encode([
            'component_appid' => $this->appId
        ]));
    }

    /**
     * @name 获取已授权的账号列表
     * @param string $offset
     * @param int $count
     * @return json { "total_count":33,list:[{"authorizer_appid": "authorizer_appid_1","refresh_token": "refresh_token_1","auth_time": auth_time_1}}
     */
    public function apiAuthorizerList($offset,$count = 500){
        return $this->mxCurl('component/api_get_authorizer_list?component_access_token='.$this->componentToken,json_encode([
            'component_appid' => $this->appId,
            'offset' => $offset,
            'count' => $count
        ]));
    }

    /**
     * @name 获取预授权码
     * @return json {"pre_auth_code":"C","expires_in":600}
     */
    public function createPreAuthCode()
    {
        return $this->mxCurl('component/api_create_preauthcode?component_access_token='.$this->componentToken,json_encode([
            'component_appid' => $this->appId,
        ]));
    }

    /**
     * @name 换取公众号的授权信息
     * @param  sting authorization_code
     * @return json  {"authorization_info": {"authorizer_appid": "","authorizer_access_token": "","expires_in": 7200,"authorizer_refresh_token": "","func_info": [{"funcscope_category": {"id": 3}}]}
     */
    public function queryAuth($authCode)
    {
        return $this->mxCurl('component/api_query_auth?component_access_token='.$this->componentToken,json_encode([
            'component_appid' => $this->appId,
            'authorization_code' => $authCode
        ]));
    }

    /**
     * @name 刷新授权公众号的令牌
     * @param string appid
     * @param string refresh_token
     * @return  json {"authorizer_access_token": "","expires_in": 7200,"authorizer_refresh_token": ""}
     */
    public function getAuthorizerToken($appid,$refresh_token)
    {
        return $this->mxCurl('component/api_authorizer_token?component_access_token='.$this->componentToken,json_encode([
            'component_appid' => $this->appId,
            'authorizer_appid' => $appid,
            'authorizer_refresh_token' => $refresh_token
        ]));
    }

    /**
     * 获取授权方的账户信息
     */
    public function getAuthorizerInfo($authorizerAppid)
    {
        return $this->mxCurl('component/api_get_authorizer_info?component_access_token='.$this->componentToken,json_encode([
            'component_appid' => $this->appId,
            'authorizer_appid' => $authorizerAppid
        ]));
    }

    /**
     * name 获取授权方的选项设置信息
     */
    public function getAuthorizerOption($authorizerAppid, $optionName)
    {
        return $this->mxCurl('component/api_get_authorizer_option?component_access_token='.$this->componentToken,json_encode([
            'component_appid' => $this->appId,
            'authorizer_appid' => $authorizerAppid,
            'option_name' => $optionName
        ]));
    }

    /**
     * 设置授权方的选项信息
     */
    public function setAuthorizerOption($authorizerAppid, $option)
    {
        return $this->mxCurl('component/api_set_authorizer_option?component_access_token='.$this->componentToken,json_encode([
            'component_appid' => $this->appId,
            'authorizer_appid' => $authorizerAppid,
            'option_name' => $option['name'],
            'option_value' => $option['value']
        ]));
    }

    public function mxCurl($url , $data , $is_post = true){
        $response = ( new Http())->mxCurl(static::WEIXIN_API_BASE.$url,$data,$is_post);
        $this->http_response = json_encode($response);
        if($response['errcode'] == 0 ){
            return json_decode($response['data'],true);
        }else{
            return $response;
        }
    }

} 

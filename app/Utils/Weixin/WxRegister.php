<?php
/**
 * Created by PhpStorm.
 * User: wangshiliang@dodoca.com
 * Date: 2017/12/28
 * Time: 15:03
 */

namespace App\Utils\Weixin;


class WxRegister
{
    const URL = 'https://api.weixin.qq.com/cgi-bin/';
    private $component_appid ;
    private $token;

    public function __construct()
    {
    }

    public function setConfig($component_appid,$token){
        $this->component_appid = $component_appid;
        $this->token = $token;
    }

    /**
     * @name 注册账号
     * @param string $principal_name 主体信息
     * @param int $account_type 帐号类型（1：订阅号，2：服务号，3：小程序）
     * @param int $register_method 注册方式（1：微信认证）
     * @param int $principal_type 主体类型（1：企业）
     * @return  array [ 'error_code' => 0 , 'error_message'=>'' , 'appid' => '' , 'authorization_code' => '授权码']
     */
    public function register($principal_name,$account_type = 3 , $register_method = 1 , $principal_type = 1){
        $data = [
            'component_appid' => $this->component_appid,
            'account_type' => $account_type,
            'register_method' => $register_method,
            'principal_type'  => $principal_type,
            'principal_name' => $principal_name
        ];
        return $this->mxCurl('component/registeraccount?component_access_token='.$this->token,json_encode($data,true));
    }

    /**
     * @name 查询注册列表
     * @param string $offset 起始位置
     * @param int $count 请求数据量（最大 100）
     * @return  array [ 'error_code' => 0 , 'error_message'=>'' , 'account_list' => [ ['appid'=>'','authorizer_refresh_token'=>'调用凭证'] ] ,'total'=>'数据量']
     */
    public function registerList($offset,$count){
        //
        $data = [
            'component_appid' => $this->component_appid,
            'offset' => $offset,
            'count' => $count
        ];
        return $this->mxCurl('component/getregisteraccountlist?component_access_token='.$this->token,json_encode($data,true));
    }

    private function errcode($errcode){
        return [
            61026 => '没有注册权限',
            61027 => '注册额度超过限制'
        ];
    }

    //发起请求
    private function mxCurl($url , $data , $is_post = true){
        $response = ( new Http())->mxCurl(static::URL.$url,$data,$is_post);
        $this->http_response = json_encode($response);
        if($response['errcode'] == 0 ){
            return json_decode($response['data'],true)  ;
        }else{
            return $response;
        }
    }

}
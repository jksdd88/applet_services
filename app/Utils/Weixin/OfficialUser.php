<?php
/**
 * Created by PhpStorm.
 * User: wangshiliang@dodoca.com
 * Desc: 账号清理
 * link: https://mp.weixin.qq.com/wiki?t=resource/res_main&id=mp1421140839
 * Date: 2017/11/7
 * Time: 15:33
 */

namespace App\Utils\Weixin;


class OfficialUser
{
    const WEIXIN_API_CGI = 'https://api.weixin.qq.com/cgi-bin/';

    private $access_token ;

    public $http_response;

    public function __construct()
    {

    }

    public function setAccessToken($access_token){
        $this->access_token = $access_token;
        return $this;
    }

    /**
     * @name 清理小程序/公众账号限制
     * @param $appid   string
     * @link  https://open.weixin.qq.com/cgi-bin/showdocument?action=dir_list&t=resource/res_list&id=open1419318587
     * @return array
     */
    public function clearQuota($appid){
        return $this->mxCurl('clear_quota?access_token='.$this->access_token,['appid'=>$appid]);
    }

    /**
     * @name 获取粉丝信息
     * @param $openid   string
     * @return array
     */
    public function getUserInfo($openid){
        return $this->mxCurl('user/info?access_token='.$this->access_token.'&openid='.$openid.'&lang=zh_CN',[],false);
    }

    /**
     * @name 批量获取粉丝信息
     * @return array
     */
    public function getUserList(){
        return $this->mxCurl('user/info/batchget?access_token='.$this->access_token,[]);
    }

    private function mxCurl($url , $data , $is_post = true){
        $response = ( new Http())->mxCurl(static::WEIXIN_API_CGI.$url,$data,$is_post);
        $this->http_response = json_encode($response);
        if($response['errcode'] == 0 ){

            return json_decode($response['data'],true) ;
        }else{
            return $response;
        }
    }
}
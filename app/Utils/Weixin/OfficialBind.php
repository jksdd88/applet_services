<?php
/**
 * Created by PhpStorm.
 * User: wangshiliang@dodoca.com
 * Eesc: 公众账号绑定小程序
 * link: https://open.weixin.qq.com/cgi-bin/showdocument?action=dir_list&t=resource/res_list&verify=1&id=open1513255108_gFkRI
 * Date: 2018/3/19
 * Time: 15:13
 */

namespace App\Utils\Weixin;


class OfficialBind
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
     * @name 获取已关联公众号的小程序
     * @return array ['errcode'=>'','errmsg'=>'','wxopens'=> [ 'items' =>[
     *          [
     *           'status' => '1：已关联；2：等待小程序管理员确认中；3：小程序管理员拒绝关联12：等到公众号管理员确认中；',
     *           'username' => '小程序gh_id',
     *           'nickname' => '小程序名称',
     *           'selected' => '是否在公众号管理页展示中',
     *           'nearby_display_status' => '是否展示在附近的小程序中',
     *           'released' => '是否已经发布',
     *           'headimg_url' => '头像url',
     *           'func_info' => '微信认证及支付信息，0表示未开通，1表示开通',
     *           'email' => '小程序邮箱'
     *          ]
     * ] ] ]
     */
    public function getWxampLink(){
        return $this->mxCurl('wxopen/wxamplinkget?access_token='.$this->access_token,[]);
    }

    /**
     * @name 公众账号关联小程序
     * @param $appid string 小程序appid
     * @param $notify_users int 是否发送模板消息通知公众号粉丝
     * @param $show_profile int 是否展示公众号主页中
     * @return array ['errcode'=>'','errmsg'=>'' ]
     */
    public function wxampLink($appid, $notify_users, $show_profile){
        return $this->mxCurl('wxopen/wxamplink?access_token='.$this->access_token,['appid'=>$appid,'notify_users'=>$notify_users,'show_profile'=>$show_profile]);
    }

    /**
     * @name 解除关联的小程序
     * @param $appid string 小程序appid
     * @return array ['errcode'=>'','errmsg'=>'' ]
     */
    public function wxampunLink($appid){
        return $this->mxCurl('wxopen/wxampunlink?access_token='.$this->access_token,['appid'=>$appid]);
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
<?php
/**
 * Created by PhpStorm.
 * User: wangshiliang@dodoca.com
 * link: https://mp.weixin.qq.com/debug/wxadoc/dev/api/custommsg/receive.html
 * Desc: 小程序客服消息
 * Date: 2017/12/12
 * Time: 13:53
 */

namespace App\Utils\Weixin;


class custom
{
    const WEIXIN_API_WXA = 'https://api.weixin.qq.com/cgi-bin/message/custom/';
    private $access_token;

    public $http_response ;

    public function __construct($access_token)
    {
        $this->access_token = $access_token;
    }

    /**
     * @name 发送客服消息
     * @param $open 粉丝 openid
     * @param $type  text | image | link | miniprogrampage
     * @param $data ["content"=>""] | ['media_id'=>""] | ["title"=>"","description"=>"", "url"=>"" ,"thumb_url"=>"" ] | ["title"=>"","pagepath"=>"","thumb_media_id"=>""]
     * @return array  ["errcode" =>"" ,"errmsg"=>'"]
     */
    public function sendmsg($open,$type,$data){
        return $this->mxCurl('send?access_token='. $this->access_token, json_encode(['touser'=>$open,'msgtype'=>$type,$type=>$data]));
    }

    /**
     * @name 发送客服状态
     * @param $open 粉丝 openid
     * @param $command  Typing | CancelTyping
     * @return array  ["errcode" =>"" ,"errmsg"=>'"]
     */
    public function typing($open,$command){
        return $this->mxCurl('typing?access_token='. $this->access_token, json_encode(['touser'=>$open,'command'=>$command]));
    }


    public function mxCurl($url , $data , $is_post = true){
        $response = ( new Http())->mxCurl(static::WEIXIN_API_WXA.$url,$data,$is_post);
        $this->http_response = json_encode($response);
        if($response['errcode'] == 0 ){
            return json_decode($response['data'],true);
        }else{
            return $response;
        }
    }
}
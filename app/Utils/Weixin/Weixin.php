<?php
/**
 * Created by PhpStorm.
 * User: wangshiliang@dodoca.com
 * Date: 2018/8/10
 * Time: 15:26
 */

namespace App\Utils\Weixin;

class Weixin
{
    const WEIXIN_API = 'https://api.weixin.qq.com';

    public $url;
    public $request;
    public $response;
    public $access_token = '';
    public $component_token = '';
    public $httpDetails = false;
    public $responsjson = true;
    public $errcode = true;

    public function __construct()
    {

    }

    public function setAccessToken($access_token){
        $this->access_token = $access_token;
        return $this;
    }

    public function setComponentToken($component_token){
        $this->component_token = $component_token;
        return $this;
    }

    public function mxCurl($url , $data , $is_post = true,$config = []){
        if(!empty($this->access_token)){
            $url .= '?access_token='.$this->access_token;
        }elseif(!empty($this->component_access_token)){
            $url .= '?component_access_token='.$this->access_token;
        }
        $response = ( new Http())->mxCurl(self::WEIXIN_API.$url,$data,$is_post,$config);
        if($this->httpDetails){
            $this->url      = $response['request']['url'];
            $this->request  = $response['request']['data'];
            $this->response = json_encode($response);
        }
        if($response['errcode'] != 0 ){
            return $response;
        }
        if(!$this->responsjson){
            return $response['data'];
        }
        $response = json_decode($response['data'],true);
        if($this->errcode && !isset($response['errcode'])){
            $response['errcode'] = -1000 ;
        }
        return $response ;
    }
}
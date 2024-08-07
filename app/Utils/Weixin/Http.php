<?php
/**
 * Created by PhpStorm.
 * User: wangshiliang@yuncam.com
 * Desc: curl
 * Date: 15/7/23
 * Time: 上午11:41
 */

namespace App\Utils\Weixin;

use GuzzleHttp\Client;
use Config;

class Http {

    const WEIXIN_API_BASE = 'https://api.weixin.qq.com/cgi-bin/';

    protected $client;
    protected $componentToken;
    protected $componentAppid;
    protected $authorizerToken;
    
    protected $uploadType;
    protected $proxy;
    protected $proxyis;

    public function __construct($baseUri='')
    {
        $this->proxy = Config::get('weixin.proxy');
        $this->proxyis = Config::get('weixin.proxy_is');

    }


    /***
     * @name curl
     * @param  $url string 地址
     * @param  $data string|array 数据
     * @param  $is_post bool 是否post请求
     * @param  $config array  [ 'proxy' => '127.0.0.1' , 'timeout' => 10 , 'header' => [] , 'ssl' => ['cert' , 'key' ]]
     * @param
     * @return array  [ errno  error  data ]   -(errno+1000)
     */
    public function mxCurl($url , $data , $is_post = true,$config = []){
        $ch = curl_init();
        if(!$is_post && !empty($data)){ //get 请求
            $url .=  (stripos($url, '?') ? '&' : '?' ).http_build_query($data);
        }
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, $is_post);
        //post
        if($is_post){
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }
        //proxy
        if(isset($config['proxy']) &&  $config['proxy'] == 'false' ){

        }else if(isset($config['proxy']) &&  !empty($config['proxy'])){
            curl_setopt($ch, CURLOPT_PROXY, $config['proxy']);
        }else if(!empty($this->proxy)){
            curl_setopt($ch, CURLOPT_PROXY, $this->proxy);
        }
        //timeout
        if(isset($config['timeout']) && !empty($config['timeout'])){
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $config['timeout'] );
        }else{
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10 );
        }
        //header
        if(isset($config['header']) && !empty($config['header'])){
            curl_setopt($ch, CURLOPT_HTTPHEADER, $config['header']);
        }
        //cookie
        if(isset($config['cookie']) && !empty($config['cookie'])){
            curl_setopt($ch, CURLOPT_COOKIE, $config['cookie']);
        }
        //ssl
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        if(isset($config['ssl']) && !empty($config['ssl']) ){
            curl_setopt($ch,CURLOPT_SSLCERTTYPE,'PEM');
            curl_setopt($ch,CURLOPT_SSLCERT,$config['ssl']['cert']);
            curl_setopt($ch,CURLOPT_SSLKEYTYPE,'PEM');
            curl_setopt($ch,CURLOPT_SSLKEY,$config['ssl']['key']);
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $info  = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        curl_close($ch);
        if($errno) {
            return ['errcode'=>-($errno+1000),'errmsg'=>$error,'data'=>null ,'request'=>['url'=>$url,'data'=>$data]];
        }else{
            return ['errcode'=>0,'errmsg'=>'error','data'=>$info ,'request'=>['url'=>$url,'data'=>$data]];
        }
    }


} 

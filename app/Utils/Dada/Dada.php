<?php
/**
 * Created by PhpStorm.
 * User: wangshiliang@dodoca.com
 * Date: 2018/7/24
 * Time: 15:32
 * Link: http://newopen.imdada.cn/#/development/file/index
 */

namespace App\Utils\Dada;

use  App\Utils\Weixin\Http;

class Dada
{
    const URLD = 'http://newopen.qa.imdada.cn';
    const URL = 'http://newopen.imdada.cn';

    protected $app_key;
    protected $app_secret;
    protected $host = '';
    protected $merchant = '';

    public function __construct()
    {

    }

    public function setConfig($app_key, $app_secret, $merchant, $develop = true){
        $this->app_key  = $app_key;
        $this->app_secret = $app_secret;
        $this->merchant = $merchant;
        $this->host = $develop ?  self::URLD : self::URL;
        return $this;
    }


    protected function mxCurl($url , $data){
        $datas['app_key']   = $this->app_key;
        $datas['body']      = empty($data)?'':json_encode($data);
        $datas['format']    = 'json';
        $datas['source_id'] = $this->merchant;
        $datas['timestamp'] = time();
        $datas['v']         = '1.0';
        $datas['signature'] = $this->signature($datas);
        $response = ( new Http())->mxCurl($this->host.$url,json_encode($datas),true,['timeout'=>15,'header'=>['Content-type: application/json;charset="utf-8"'] ]);
        if($response['errcode'] == 0 ){
            return json_decode($response['data'],true) ;
        }else{
            return $response;
        }
    }

    private function signature($data){
        ksort($data);
        $str = '';
        foreach ($data as $key => $v) {
            $str .= $key.$v;
        }
        $str = md5($this->app_secret.$str.$this->app_secret);
        return strtoupper($str);
    }

    public function signatureCallback($data){
        sort($data);
        $str = '';
        foreach ($data as $key => $v) {
            $str .= $v;
        }
        return md5($str);
    }
}
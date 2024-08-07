<?php
/**
 * Created by PhpStorm.
 * User: wangshiliang@dodoca.com
 * Date: 2018/4/14
 * Time: 13:31
 */

namespace App\Utils\Migu;

use App\Utils\Weixin\Http;

class MiguLogin
{

    const URL = 'http://api.migucloud.com';

    public function __construct()
    {
    }

    /**
     * @name 咪咕登录
     * @param $user 用户名
     * @param $pwd 密码
     * @return array  [ "ret"=>"0"  result => [ "atoken"=>"", "ftoken"=>"" ,"expired_time"=>0,"timestamp"=>0, "user_info" => [ "uid" => "" , "uname"=>"" , "utype" => 1 ] ] ]
     */
    public function login($user,$pwd){
        return $this->mxCurl(static::URL.'/a0/user/auth',json_encode([ 'uname' => $user , 'passwd' => $pwd ]));
    }

    /**
     * @name $token权限检查
     * @param $uid 用户id
     * @param $token 密码
     * @return array  [ "ret"=>"0"  result => [ ="expired_time"=>0,"timestamp"=>0, "utype" => 1] ]
     */
    public function loginCheck($uid,$token){
        return $this->mxCurl(static::URL.'/a0/user/atoken/verify',[ 'uid' => $uid , 'atoken' => $token ],false);
    }

    /**
     * @name $token刷新
     * @param $uid 用户id
     * @param $ftoken 刷新token
     * @return array  [ "ret"=>"0"  result => [ "expired_time"=>0,"timestamp"=>0, "utype" => 1] ]
     */
    public function loginRefresh($uid,$ftoken){
        return $this->mxCurl(static::URL.'/a0/user/atoken/flush',[ 'uid' => $uid , 'ftoken' => $ftoken ],false);
    }

    private function mxCurl($url , $data , $is_post = true){
        $response = ( new Http())->mxCurl($url,$data,$is_post,['timeout'=>20]);
        if($response['errcode'] == 0 ){
            return json_decode($response['data'],true) ;
        }else{
            $response['ret'] = '-1';
            return $response;
        }
    }

}
<?php
/**
 * Created by PhpStorm.
 * User: wangshiliang@dodoca.com
 * Desc: 公众账号二维码
 * link: https://mp.weixin.qq.com/wiki?t=resource/res_main&id=mp1443433542
 * Date: 2018/1/3
 * Time: 20:12
 */

namespace App\Utils\Weixin;


class QrcodeOfficial
{
    const WEIXIN_API_CGI = 'https://api.weixin.qq.com/cgi-bin';
    private $access_token ;

    public function __construct()
    {
    }

    /**
     * @name 配置 $access_token
     * @return void
     */
    public function setConfig($access_token){
        $this->access_token = $access_token;
    }

    /**
     * @name 创建二维码
     * @param $scene   string  参数  长度限制为1到64
     * @param $expire int 期限 0 永久  3600 - 2592000秒
     * @return ['ticket'=>'','expire_seconds'=>'','url'=>'']
     */
    public function create($scene,$expire=3600){
        $data = [] ;
        if($expire != 0 ){
            $data['expire_seconds'] = $expire;
            $data['action_name'] = 'QR_STR_SCENE';
            $data['action_info'] = [ 'scene' => ['scene_str'=>(string)$scene] ] ;
        }else{
            $data['action_name'] = 'QR_LIMIT_STR_SCENE';
            $data['action_info'] = [ 'scene' => ['scene_str'=>(string)$scene] ] ;
        }
        return $this->mxCurl('/qrcode/create', json_encode($data));
    }

    /**
     * @name 获取二维码
     * @return jpg
     */
    public function getImg($ticket){
        return 'https://mp.weixin.qq.com/cgi-bin/showqrcode?ticket='.$ticket;
    }




    private function mxCurl($url , $data , $is_post = true){
        $url = static::WEIXIN_API_CGI.$url.'?access_token='. $this->access_token;
        $response = ( new Http())->mxCurl($url,$data,$is_post);
        $this->http_response = json_encode($response);
        if($response['errcode'] == 0 ){
            return json_decode($response['data'],true);
        }else{
            return $response;
        }
    }
}
<?php
/**
 * Created by PhpStorm.
 * User: wangshiliang@dodoca.com
 * Desc: 小程序代码管理
 * link: https://open.weixin.qq.com/cgi-bin/showdocument?action=dir_list&t=resource/res_list&verify=1&id=open1489144594_DhNoV
 * Date: 2017/8/30
 * Time: 14:50
 */
namespace App\Utils\Weixin;

use App\Utils\Encrypt;
use Config;

class Applet
{

    const WEIXIN_API_WXA = 'https://api.weixin.qq.com/wxa/';
    const WEIXIN_API_CGI = 'https://api.weixin.qq.com/cgi-bin/';

    protected $wh_server;
    protected $qn_server;
    protected $map_server;
    protected $xcx_server;
    protected $static_server;
    protected $wss_server;
    protected $xiu_server;
    protected $phone_server;
    protected $socket_server;
    protected $access_token;

    private $responsjson = true;
    private $proxy_is;
    private $proxyIp;
    private $proxyPort;
    private $version = [];
    public $http_response ;

    public $request;


    public  function __construct()
    {
        $this->wh_server = Config::get('weixin.wh_host');
        $this->phone_server = Config::get('weixin.phone_host');
        $this->xiu_server = Config::get('weixin.xiu_host');
        $this->qn_server = Config::get('weixin.qn_host');
        $this->map_server = Config::get('weixin.map_host');
        $this->xcx_server = Config::get('weixin.base_host');
        $this->static_server = Config::get('weixin.static_host');
        $this->socket_server = Config::get('weixin.socket_host') ;

        $this->wss_server = str_replace("https://","wss://",$this->socket_server);
        $this->wss_server = str_replace("http://","wss://",$this->wss_server);

        $this->proxy_is   = Config::get('weixin.proxy_is');
        $this->proxyIp    = Config::get('weixin.proxy_ip');
        $this->proxyPort  = Config::get('weixin.proxy_port');

    }

    public function setAccessToken($access_token){
        $this->access_token = $access_token;
        return $this;
    }
    /**
     * @name 修改服务器地址
     * @param string $action default set  [set | get | add | delete] 操作动作
     * @return array
     */
    public function modifyDomain($action = 'set',$hosts = [])
    {
        if($action != 'get'){
            $data['requestdomain']   =  !empty($hosts)?$hosts:[$this->xcx_server,$this->qn_server,$this->map_server,$this->static_server];
            $data['wsrequestdomain'] =  [$this->wss_server];
            $data['uploaddomain']    =  [$this->xcx_server,$this->qn_server];
            $data['downloaddomain']  =  [$this->xcx_server,$this->qn_server];
        }
        $data['action'] =  $action;
        $response =  $this->mxCurl(static::WEIXIN_API_WXA.'modify_domain?access_token='.$this->access_token,json_encode($data));
        $response['data'] = $data;
        return $response;
    }
    /**
     * @name 业务域名
     * @param string $action default set  [set | get | add | delete] 操作动作
     * @return array
     */
    public function webviewDomain($action = 'set',$hosts = []){
        if($action != 'get'){
            $data['webviewdomain']   =  !empty($hosts)?$hosts:[$this->xcx_server,$this->wh_server,$this->phone_server,$this->xiu_server];
        }
        $data['action'] =  $action;
        $response =  $this->mxCurl(static::WEIXIN_API_WXA.'setwebviewdomain?access_token='.$this->access_token,json_encode($data));
        $response['data'] = $data;
        return $response;
    }


    /**
     * @name 绑定/解绑体验者
     * @param string $user 微信账号
     * @param string $type default bind [unbind | bind] 操作类型
     * @return array
     */
    public function bindTester($user,$type = 'bind')
    {
        return $this->mxCurl(static::WEIXIN_API_WXA.($type == 'unbind' ? 'unbind_tester' : 'bind_tester').'?access_token='.$this->access_token,json_encode(['wechatid'=>$user]));
    }
    /**
     * @name 上传小程序代码
     * @param string $appid 小程序appid
     * @param int $merchant_id 店铺id
     * @param string $infoid id
     * @return  array
     */
    public function commit($appid,$merchant_id,$infoid,$jumplist = [])
    {
        $verinfo = $this->version;
        $ext_json['extAppid'] = $appid;
        $ext_json['ext']      = [
            'host'        => $this->xcx_server ,
            'staticHost'  => $this->static_server ,
            'qnHost'      => $this->qn_server ,
            'appid'       => $appid ,
            'merchant_id' => $merchant_id ,
            'infoid'      => $infoid
        ];
        $ext_json['extPages'] = [];
        if(!empty($jumplist)){
            $ext_json['navigateToMiniProgramAppIdList'] = $jumplist;
        }
        $data['template_id']   = $verinfo['number'];
        $data['ext_json']      =  (string)json_encode($ext_json);
        $data['user_version']  = $verinfo['version'];
        $data['user_desc']     = $verinfo['desc'];
        $data = json_encode($data,JSON_UNESCAPED_UNICODE);
        $response =  $this->mxCurl(static::WEIXIN_API_WXA.'commit?access_token='.$this->access_token,$data);
        $response['data'] = $data;
        $response['is_sync'] = 1;
        return $response;
    }

    /**
     * @name 小程序当前版本
     * @return  array
     */
    public function setVersion($config){
        $this->version = $config;
    }

    public function getVersion(){
        return [
            'number' =>  Config::get('weixin.template_id'),
            'version' =>  Config::get('weixin.template_version'),
            'desc' =>  Config::get('weixin.template_desc'),
            'id' =>  (int)Config::get('weixin.template_date'),
            'index' => Config::get('weixin.template_index'),
        ];
    }

    /**
     * @name 小程序的体验二维码
     * @return image;
     */
    public function getQrcode($path = '')
    {
        $this->responsjson = false;
        $data = empty($path)?[]:['path'=>urlencode($path)];
        return $this->mxCurl(static::WEIXIN_API_WXA.'get_qrcode?access_token='.$this->access_token, $data,false);
    }


    /**
     * @name 获取授权小程序帐号的可选类目
     * @return array;
     */
    public function getCategory(){
        return $this->mxCurl(static::WEIXIN_API_WXA.'get_category?access_token='.$this->access_token,[],false);
    }

    /**
     * @name 获取小程序的第三方提交代码的页面配置
     * @return array
     */
    public function getPage(){
        return $this->mxCurl(static::WEIXIN_API_WXA.'get_page?access_token='.$this->access_token,[],false);
    }

    /**
     * @name 将第三方提交的代码包提交审核
     * @return array
     */
    public function submitAudit($title ,$tag ,$category){
        $verinfo = $this->version;
        $data['item_list'][] = [
            'address' => $verinfo['index'],
            'tag'     => $tag,
            'first_class'  => $category['first_class'],
            'second_class' => $category['second_class'],
            'first_id' => $category['first_id'],
            'second_id'=> $category['second_id'],
            'title' => $title
        ];
        if(!empty($category['third_class'])) $data['item_list'][0]['third_class'] = $category['third_class'];
        if(!empty($category['third_id'])) $data['item_list'][0]['third_id'] = $category['third_id'];
        $data = json_encode($data,JSON_UNESCAPED_UNICODE);
        $response =  $this->mxCurl(static::WEIXIN_API_WXA.'submit_audit?access_token='.$this->access_token,$data);
        $response['data'] =$data;
        return $response;
    }

    /**
     * @name 查询某个指定版本的审核状态
     * @return array
     */
    public function getAuditStatus($auditid){
        return $this->mxCurl(static::WEIXIN_API_WXA.'get_auditstatus?access_token='.$this->access_token,json_encode(['auditid' => $auditid]));
    }

    /**
     * @name 查询最新一次提交的审核状态
     * @return array
     */
    public function getLatestAuditStatus(){
        return $this->mxCurl(static::WEIXIN_API_WXA.'get_latest_auditstatus?access_token='.$this->access_token,[],false);
    }

    /**
     * @name 发布已通过审核的小程序
     * @return array
     */
    public function release(){
        return $this->mxCurl(static::WEIXIN_API_WXA.'release?access_token='.$this->access_token,'{}');
    }

    /**
     * @name 修改小程序线上代码的可见状态
     * @return array
     */
    public function change_visitstatus( $action = 'close' ){
        return $this->mxCurl(static::WEIXIN_API_WXA.'change_visitstatus?access_token='.$this->access_token,json_encode(['action' => ($action == 'open' ? 'open' : 'close') ]));
    }

    /**
     * @name 查询当前设置的最低基础库版本及各版本用户占比
     * @return array
     */
    public function getWeappSupportVersion(){
        return $this->mxCurl(static::WEIXIN_API_WXA.'getweappsupportversion?access_token='.$this->access_token,[],false);
    }

    /**
     * @name 设置最低基础库版本
     * @return array
     */
    public function setWeappSupportVersion(){
        return $this->mxCurl(static::WEIXIN_API_WXA.'setweappsupportversion?access_token='.$this->access_token,[],false);
    }

    /**
     * @name 小程序版本回退
     * @desc 87013   每天一次，每个月10次
     * @return array
     */
    public function revertCodeRelease(){
        return $this->mxCurl(static::WEIXIN_API_WXA.'revertcoderelease?access_token='.$this->access_token,[],false);
    }

    /**
     * @name 小程序审核撤回
     * @return array
     */
    public function undoCodeAudit(){
        return $this->mxCurl(static::WEIXIN_API_WXA.'undocodeaudit?access_token='.$this->access_token,[],false);
    }

    /**
     * @name 创建开放平台帐号并绑定公众号/小程序
     * @return array
     */
    public function openCreate($appid){
        return $this->mxCurl(static::WEIXIN_API_CGI.'open/create?access_token='.$this->access_token,json_encode([ 'appid' => $appid]));
    }

    /**
     * @name 将公众号/小程序绑定到开放平台帐号下
     * @return array
     */
    public function openBind($appid,$open_appid){
        return $this->mxCurl(static::WEIXIN_API_CGI.'open/bind?access_token='.$this->access_token,json_encode([ 'appid' => $appid , 'open_appid' => $open_appid]));
    }

    /**
     * @name 将公众号/小程序从开放平台帐号下解绑
     * @return array
     */
    public function openUnbind($appid,$open_appid){
        return $this->mxCurl(static::WEIXIN_API_CGI.'open/unbind?access_token='.$this->access_token,json_encode([ 'appid' => $appid , 'open_appid' => $open_appid]));
    }

    /**
     * @name 获取公众号/小程序所绑定的开放平台帐号
     * @return array
     */
    public function openGet($appid){
        return $this->mxCurl(static::WEIXIN_API_CGI.'open/get?access_token='.$this->access_token,json_encode([ 'appid' => $appid ]));
    }

    /**
     * @name 小程序码A
     * @param  $path string 小程序路径
     * @param  $config ['width'=>'二维码的宽度430 ','color'=>['r'=>'十进制表示','g'=>'十进制表示','b'=>'十进制表示'] ,'hyaline'=>'true透明']
     * @deprecated  适用于需要的码数量较少的业务场景
     * @return string
     */
    public function getCodeQrcode($path,$config = [])
    {
        $this->responsjson = false;
        $data = ['path'=>$path];
        if(isset($config['width'])){
            $data['width'] = $config['width'];
        }
        if(isset($config['color']['r']) && isset($config['color']['g']) && isset($config['color']['b'])){
            $data['auto_color'] = false;
            $data['line_color'] = ['r'=>$config['color']['r'],'g'=>$config['color']['g'],'b'=>$config['color']['g']];
        }else{
            $data['auto_color'] = false;
        }
        if(isset($config['hyaline']) && $config['hyaline'] === true){
            $data['is_hyaline'] = true;
        }
        return $this->mxCurl(static::WEIXIN_API_WXA.'getwxacode?access_token='.$this->access_token,json_encode($data));
    }

    /**
     * @name 小程序码 B
     * @param $path 小程序路径
     * @param  $scene='' 二维码参数 最大32个可见字符，只支持数字，大小写英文以及部分特殊字符：!#$&'()*+,/:;=?@-._~，
     * @param  $config ['width'=>'二维码的宽度430 ','color'=>['r'=>'十进制表示','g'=>'十进制表示','b'=>'十进制表示'] ,'hyaline'=>'true透明']
     * @deprecated  适用于需要的码数量较少的业务场景
     * @return string;
     */
    public function getCodeLimitQrcode($path, $scene = '',$config = [])
    {
        $this->responsjson = false;
        $data = ['scene'=>$scene,'path'=>$path] ;
        if(isset($config['width'])){
            $data['width'] = $config['width'];
        }
        if(isset($config['color']['r']) && isset($config['color']['g']) && isset($config['color']['b'])){
            $data['auto_color'] = false;
            $data['line_color'] = ['r'=>$config['color']['r'],'g'=>$config['color']['g'],'b'=>$config['color']['g']];
        }else{
            $data['auto_color'] = false;
        }
        if(isset($config['hyaline']) && $config['hyaline'] === true){
            $data['is_hyaline'] = true;
        }
        return $this->mxCurl(static::WEIXIN_API_WXA.'getwxacodeunlimit?access_token='.$this->access_token,json_encode($data));//,'width'=>$width,'auto_color'=>$auto_color,'line_color'=>$line_color
    }

    /**
     * @name 小程序的二维码
     * @param string $path 小程序路径
     * @param  $width string  宽度
     * @deprecated  适用于需要的码数量较少的业务场景
     * @return string;
     */
    public function getAppQrcode($path,$width = 430)
    {
        $this->responsjson = false;
        return $this->mxCurl(static::WEIXIN_API_CGI.'wxaapp/createwxaqrcode?access_token='.$this->access_token,json_encode(['path'=>$path,'width'=>$width]));
    }

    public function mxCurl($url , $data , $is_post = true){
        $response = ( new Http())->mxCurl($url,$data,$is_post);
        $this->request = $response['request'];
        $this->http_response = json_encode($response);
        if($response['errcode'] == 0 ){
            return $this->responsjson ? json_decode($response['data'],true) : $response['data'] ;
        }else{
            return $response;
        }
    }

}

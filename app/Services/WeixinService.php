<?php
/**
 * Created by PhpStorm.
 * User: wangshiliang@dodoca.com
 * Date: 2017/8/30
 * Time: 14:50
 */
namespace App\Services;

use App\Models\User;
use App\Models\WeixinComponent;
use App\Models\WeixinInfo;
use App\Models\WeixinLog;
use App\Models\WeixinLogAuthorize;
use App\Models\WeixinOpen;
use App\Models\WeixinQrcodeLog;
use App\Models\WeixinTemplate;
use App\Models\Merchant;
use App\Models\WeixinVersion;
use App\Models\WeixinQrcode;
use App\Utils\CacheKey;
use App\Utils\Weixin\AppletVersion;
use App\Utils\Weixin\Component;
use App\Utils\Weixin\Applet;
use App\Utils\Encrypt;
use Mockery\Exception;
use Qiniu\Auth as qAuth;
use Qiniu\Storage\BucketManager;
use Qiniu\Storage\UploadManager;
use Cache;
use Config;

class WeixinService
{
    protected $componentId;
    protected $appId;
    protected $app_env;
    protected $test_merchant;
    private  $config = [];

    public function __construct($config = [])
    {
        if(empty($config)){
            $this->componentId     = Config::get('weixin.component_id');
            $this->appId           = Config::get('weixin.component_appid');
        }else{
            $this->componentId     = $config['component_id'];
            $this->appId           = $config['component_appid'];
            $this->config     = $config;
        }
        $this->app_env = Config::get('weixin.app_env');
        $this->test_merchant = Config::get('weixin.test_merchant_id');
    }

    //========================================== token ==========================================
    /**
     * @name 获取第三方平台
     * @return string
     */
    public function getComponentToken()
    {
        $cachekey = CacheKey::get_weixin_cache('component_access_token',$this->appId);
        $component_access_token = Cache::get($cachekey);
        if($component_access_token)
        {
            return $component_access_token;
        }
        $componentInfo = WeixinComponent::get_data_by_id($this->componentId);
        $Component = new Component($this->config);
        $response = $Component->getComponentToken($componentInfo->verify_ticket); //{"component_access_token":"", "expires_in":7200}
        if(!empty($response['component_access_token']))
        {
            $expires_in = intval($response['expires_in']/120);
            $expires_in = $expires_in > 0 ? $expires_in : 1;
            Cache::put($cachekey,$response['component_access_token'],$expires_in);
            return $response['component_access_token'];
        }else{
            $this->setLog('weixinServiceComponentToken','',$response);
            return '';
        }

    }
    /**
     * @name 获取第三方平台access_token
     * @param $appid string
     * @param $cache int 是否取缓存
     * @return string
     */
    public function getAccessToken($appid,$cache = 1){
        $cachekey = CacheKey::get_weixin_cache('appid_access_token',$appid);
        $access_token = Cache::get($cachekey);
        if($access_token)
        {
            return $access_token;
        }
        if($cache == 1){
            $WeixinInfo = WeixinInfo::get_one('appid',$appid);
        }else{
            $WeixinInfo =  WeixinInfo::query()->where('appid','=',$appid)->orderBy('id', 'DESC')->first();
            if($WeixinInfo) $WeixinInfo = $WeixinInfo ->toArray();
        }
        if( !isset($WeixinInfo['appid']) || empty($WeixinInfo['appid'])){
            return '';
        }
        $Component = new Component($this->config);
        $Component ->setComponentToken($this->getComponentToken());
        $response = $Component->getAuthorizerToken($appid,$WeixinInfo['token']);//{"authorizer_access_token": "", "expires_in": 7200, "authorizer_refresh_token": ""}
        if(isset($response['authorizer_access_token']) && !empty($response['authorizer_access_token'])) {
            $expires_in = intval($response['expires_in']/120);
            $expires_in = $expires_in > 0 ? $expires_in : 1;
            Cache::put($cachekey,$response['authorizer_access_token'],$expires_in);
            return $response['authorizer_access_token'];
        }else if(isset($response['errcode']) && $response['errcode'] == 61023){
            WeixinInfo::update_data('id',$WeixinInfo['id'],['auth'=>-1]);
            return '';
        }else{  //
            $this->setLog('weixinServiceAccessToken',['appid'=>$WeixinInfo['appid'],'token'=>$WeixinInfo['token']],$response,$WeixinInfo['merchant_id'],$WeixinInfo['appid']);
            return '';
        }

    }
    /**
     * @name 获取第授权token
     * @param  $key merchant_id
     * @return  string
     */
    public function getPreAuthCode($key){
        $cachekey = CacheKey::get_weixin_cache('pre_auth_code',$key);
        $pre_auth_code = Cache::get($cachekey);
        if($pre_auth_code)  return $pre_auth_code;

        $Component = new Component($this->config);
        $response = $Component->setComponentToken($this->getComponentToken()) ->createPreAuthCode();
        if(isset($response['pre_auth_code']) && $response['pre_auth_code']){ //{"pre_auth_code":"", "expires_in":600}
            $expires_in = intval($response['expires_in']/120);
            $expires_in = $expires_in > 0 ? $expires_in : 1;
            Cache::put($cachekey,$response['pre_auth_code'],$expires_in);
            return $response['pre_auth_code'];
        }else{
            $this->setLog('weixinPreAuthCode','',$response);
            return false;
        }
    }
    /**
     * @name 清理缓存
     * @return boolean
     */
    public function clearCacheComponentToken(){
        $cachekey = CacheKey::get_weixin_cache('component_access_token',$this->appId);
        return Cache::forget($cachekey);
    }
    /**
     * @name 清理缓存
     * @param $appid string
     * @return  boolean
     */
    public function clearCacheAccessToken($appid){
        if(empty($appid)) return false;
        $cachekey = CacheKey::get_weixin_cache('appid_access_token',$appid);
        return  Cache::forget($cachekey);
    }
    /**
     * @name 清理缓存
     * @param $key merchant_id
     * @return  boolean
     */
    public function clearPreAuthCode($key){
        if(empty($key)) return false;
        $cachekey = CacheKey::get_weixin_cache('pre_auth_code',$key);
        return  Cache::forget($cachekey);
    }

    //========================================== 小程序版本 ==========================================
    /**
     * @name 版本升级
     * @param $page int 分页
     * @return boolean
     */
    public function versionUpdate($page = 0){
       $cacheKey = CacheKey::get_weixin_cache('weixinVersionUpdateNew','console');//阻塞
        if($page == 0){
            if(Cache::get($cacheKey)){
                return true;
            }else{
                Cache::put($cacheKey,1,1440);
            }
        }
        $this->setLog('WeixinService_commit',['step'=>'run shell page','time'=>$_SERVER['REQUEST_TIME'],'now'=>time()],[],0,$page);//@@@20180827
        try{
            $limit = 1000;
            $applet = new Applet();
            $applist = WeixinInfo:: query()->select(['id','merchant_id','appid','tpl_type','principal_name'])->where(['status'=>1, 'auth'=>1,'type'=>1 ])->where('appid','!=','')->skip($page*$limit)->take($limit)->orderBy('id', 'ASC')->get()->toArray();
            $counter = 0;
            foreach ($applist as $key => $val) {
                $counter ++ ;
                $tplInfo = WeixinTemplate::get_one_ver($val['merchant_id'],$val['appid']);
                //数据有误 ->   跳过
                if(!isset($tplInfo['id'])){
                    continue ;
                }
                $version = $this->getVersion($val['tpl_type']);
                //最新版本核 ->  跳过
                if($tplInfo['version_id'] >= $version['id'] ){
                    continue ;
                }
                //审核中   ->   跳过
                if($tplInfo['check'] > 0 && $tplInfo['pass_date'] == 0 ){
                    continue ;
                }
                $tplInfoId = $tplInfo['id'];
                // 上一次没提交/失败 获取最近一次成功 否则跳过  ->   跳过
                if($tplInfo['check'] == 0 || $tplInfo['verify'] == 2 ){
                    $tplInfo = WeixinTemplate::get_pass_ver($val['merchant_id'],$val['appid']);
                    if(!isset($tplInfo['id'])){
                        continue ;
                    }
                }
                //升级
                $accessToken = $this->getAccessToken($val['appid']);
                if(empty($accessToken)){
                    continue ;
                }
                $applet -> setAccessToken($accessToken);
                $encrypt = new  Encrypt();
                $applet->setVersion($version);
                //域名
                $infodata = [];
                $response = $applet->modifyDomain();
                if($response['errcode'] == 0){
                    $infodata['miniprograminfo'] = json_encode(['network'=>$response['data']]);
                }
                //业务域名
                if($val['principal_name'] != '个人'){
                    $responses = $applet->webviewDomain();
                    if($responses['errcode'] == 0){
                        $infodata['webview_domain'] = json_encode($responses['data']);
                    }
                }
                if(!empty($infodata)){
                    WeixinInfo::update_data('id',$val['id'],$infodata);
                }
                //版本 配置
                $response = $applet -> commit($val['appid'],$encrypt->encode($val['merchant_id']),$encrypt->encode($val['id']));
                $this->setLog('WeixinService_commit',['step'=>'run shell php','time'=>$_SERVER['REQUEST_TIME'],'now'=>time()],$response,$val['merchant_id'],$val['appid']);//@@@20180827
                if($response['errcode'] != 0 ){
                    $this->setLog('WeixinService_versionUpdate','commit',$response,$val['merchant_id'],$val['appid']);
                    continue;
                }
                $exitdata = $response['data'];
                //提交 审核
                $checkexit  = json_decode($tplInfo['check_exit'],true);
                $checkexit  = $checkexit['item_list'][0];
                $classdata = ['first_class'=>$checkexit['first_class'],'second_class'=>$checkexit['second_class'],'first_id'=>$checkexit['first_id'],'second_id'=>$checkexit['second_id']];
                if(!empty($checkexit['third_class']) && !empty($checkexit['third_id'])){
                    $classdata['third_class'] = $checkexit['third_class'];
                    $classdata['third_id'] = $checkexit['third_id'] ;
                }
                $response = $applet->submitAudit('首页',$checkexit['tag'],$classdata);
                if($response['errcode'] != 0){
                    switch ($response['errcode']){
                        case 85006 :  $msgwsl = '标签格式错误';break;
                        case 85008 :  $msgwsl = '类目填写错误';break;
                        case 85009 :  $msgwsl = '已经有正在审核的版本';break;
                        case 85077 :  $msgwsl = '小程序类目信息失效（类目中含有官方下架的类目，请重新选择类目）';break;
                        case 86002 :  $msgwsl = '小程序还未设置昵称、头像、简介。请先设置完后再重新提交';break;
                        default: $msgwsl = '升级有误';
                    }
                    WeixinTemplate::update_data($tplInfoId,['update_error' => $msgwsl]);
                    $this->setLog('WeixinService_versionUpdate','submitAudit',$response,$val['merchant_id'],$val['appid']);
                    continue;
                }
                WeixinTemplate::insert_data([
                    'merchant_id'=>$val['merchant_id'],
                    'appid'=>$val['appid'],
                    'version_id'=>$version['id'],
                    'version'=>$version['version'],
                    'tag'=>$tplInfo['tag'],
                    'category'=>$tplInfo['category'],
                    'host'=> $tplInfo['host'],
                    'host_date'=>$tplInfo['host_date'],
                    'exit'=>$exitdata,
                    'exit_date' =>$_SERVER['REQUEST_TIME'],
                    'check_exit'=>$response['data'],
                    'check_date'=>$_SERVER['REQUEST_TIME'],
                    'check'=>isset($response['auditid'])?$response['auditid']:0
                ]);
            }
        }catch (Exception $e){
            Cache::forget($cacheKey);
            return true;
        }
        if($counter < $limit){
            Cache::forget($cacheKey);
            return true;
        }else{
            return $this->versionUpdate(++$page);
        }
    }
    /**
     * @name 审核状态检查
     * @param $page int 分页
     * @return boolean
     */
    public function checkVerify($page = 0){
        $limit = 1000;
        $applist = WeixinInfo:: query()->select(['id','merchant_id','appid','qrcode'])->where(['auth'=>1,'status'=>1])->where('appid','!=','')->skip($page*$limit)->take($limit)->get()->toArray();
        $counter = 0;
        foreach ($applist as $key => $val) {
            $counter ++ ;
            $tplInfo = WeixinTemplate::get_one_ver($val['merchant_id'],$val['appid']);
            if(!isset($tplInfo['id']) || $tplInfo['verify'] != 0  || empty($tplInfo['check'])){
                continue;
            }
            $applet = new Applet();
            $applet -> setAccessToken($this->getAccessToken($val['appid']));
            $response =  $applet->getAuditStatus($tplInfo['check']);
            if(!isset($response['errcode']) || $response['errcode'] != 0  ){
                continue;
            }
            //审核中
            if($response['status'] != 1 && $response['status'] != 0){
                continue;
            }
            //审核失败
            if($response['status'] == 1){
                WeixinTemplate::update_data($tplInfo['id'],['verify'=>2, 'check_error'=> $response['reason'], 'pass_date'=>$_SERVER['REQUEST_TIME'] ]);
                continue;
            }
            //审核成功
            $this->releaseVerify($tplInfo,$val,$applet);
        }
        if($counter < $limit){
            return true;
        }else{
            return $this->checkVerify(++$page);
        }
    }
    /**
     * @name 审核成功
     * @param $tplInfo weixin_template data
     * @param $info  weixin_info data
     * @param $applet null obj
     * @return array
     */
    public function releaseVerify($tplInfo,$info,$applet = null){
        WeixinTemplate::update_data($tplInfo['id'],['pass_date'=>$_SERVER['REQUEST_TIME'] ]);
        if($applet === null){
            $applet = new Applet();
            $applet->setAccessToken($this->getAccessToken($info['appid']));
        }
        //发布
        $response = $applet->release();
        if($response['errcode'] != 0 && $response['errcode'] != 85052 ){
            $this->setLog('WeixinService_releaseVerify',['id'=>$tplInfo['id'],'step'=>'release'],$response,$info['merchant_id'],$info['appid']);//@@@20180827
            return ['errcode' => 1, 'errmsg' => 'release error'] ;
        }
        WeixinTemplate::update_data($tplInfo['id'],['verify'=>1,'release'=>1,'release_date'=>$_SERVER['REQUEST_TIME'] ]);
        //检查二维码
        $isqrocde = explode('?v=',$info['qrcode']);
        if(isset($isqrocde[1]) && $isqrocde[1] > 1){
            return ['errcode' => 0, 'errmsg' => 'ok'] ;
        }
        //生成二维码
        $check_exit =  json_decode($tplInfo['check_exit'],true);
        $response = $this->qrcode($info['appid'],$check_exit['item_list'][0]['address'],$applet);
        if($response['errcode'] != 0){
            $this->setLog('WeixinService_checkVerify',['id'=>$tplInfo['id'],'step'=>'qrcode'],$response,$info['merchant_id'],$info['appid']);
            return ['errcode' => 2, 'errmsg' => $response['errmsg']] ;
        }
        WeixinTemplate::update_data($tplInfo['id'],['qrcode'=>1 ]);
        WeixinInfo::update_data('id',$info['id'],['qrcode' =>$response['url'] ]);
        return ['errcode' => 0, 'errmsg' => 'ok'] ;
    }
    /**
     * @name 版本回滚
     * @param $verid int 版本id
     * @param $verbase int 最低版本id
     * @param $page int 分页
     * @return boolean
     */
    public function versionBack($verid, $verbase, $page = 0){
        $limit = 1000;
        $list   = DB::select('SELECT * FROM (SELECT id,appid,merchant_id,`check`,`release`,version_id,release_date FROM weixin_template WHERE `status` =1 AND `release`=1   ORDER BY id DESC ) c_tpl GROUP BY appid HAVING version_id = ? limit '.($page*$limit).','.$limit, [$verid]);
        $applet = new Applet();
        $counter = 0;
        foreach ($list as $k => $v){
            $v = (array)$v;
            $responseCheck = WeixinTemplate::query()->where(['release'=>1,'status'=>1])->where('version_id','<',$verid)->where('version_id','>=',$verbase)->first();
            if($responseCheck){
                $applet->setAccessToken($this->getAccessToken($v['appid']));
                $response  = $applet->revertCodeRelease();
                if($response['errcode'] == 0){
                    WeixinTemplate::update_data($v['id'],['status'=>-1]);
                }
                $v['request'] = $applet->request;
                $this->setLog('revertCodeRelease',$v,$response,$v['merchant_id'],$v['appid']);
            }
        }
        if($counter < $limit){
            return true;
        }else{
            return $this->versionBack($verid, $verbase, ++$page);
        }
    }
    /**
     * @name 版本回滚
     * @param $verid int 版本id
     * @param $page int 分页
     * @return boolean
     */
    public function verifyBack($verid, $page = 0){
        $limit = 1000;
        $list = WeixinTemplate::query()->select(['id','merchant_id','appid','check','pass_date'])->where(['version_id'=>$verid,'status'=>1])->skip($page*$limit)->take($limit)->get()->toArray();
        $applet = new Applet();
        $counter = 0;
        foreach ($list as $k => $v){
            $counter ++ ;
            if($v['check'] > 0 && $v['pass_date'] == 0){
                $applet->setAccessToken($this->getAccessToken($v['appid']));
                $response  = $applet->undoCodeAudit();
                if($response['errcode'] == 0){
                    WeixinTemplate::update_data($v['id'],['status'=>-1]);
                }
                $v['request'] = $applet->request;
                $this->setLog('undoCodeAudit',$v,$response,$v['merchant_id'],$v['appid']);
            }
        }
        if($counter < $limit){
            return true;
        }else{
            return $this->verifyBack($verid, ++$page);
        }
    }
    /**
     * @name 版本信息
     * @param $type string 版本类型
     * @param $merchant_id int 版本状态
     * @return array
     */
    public function getVersion( $type = 'V' ,$merchant_id = 0){ // config/weixin $appTplType
        if(empty($type)){
            return false;
        }
        $state = $this->app_env == 'production' && !in_array($merchant_id,$this->test_merchant) ? 1 : 0;
        $typeMap =  Config::get('weixin.template_type_map');
        $info = WeixinVersion::get_version($this->componentId,$typeMap[$type],$state);
        if(isset($info['id'])){
            return [
                'number' =>  $info['tmplate_id'],
                'version' =>  $info['version'],
                'desc' =>   $info['desc'],
                'id' =>  $info['time'],
                'index' => Config::get('weixin.template_index'),
            ];
        }
        return [
            'number' =>  Config::get('weixin.template_id'),
            'version' =>  Config::get('weixin.template_version'),
            'desc' =>  Config::get('weixin.template_desc'),
            'id' =>  (int)Config::get('weixin.template_date'),
            'index' => Config::get('weixin.template_index'),
        ];
    }

    //========================================== 小程序/公众账号信息 ==========================================
    /**
     * @name 检查小程序数量
     * @param $merchant_id int
     * @return array
     */
    public function checkAppMax($merchant_id){
        $merchantinfo = Merchant::get_data_by_id($merchant_id);
        $merchantVersion = config::get('version');
        $countlimit = isset($merchantVersion[$merchantinfo['version_id']]['template']) ? $merchantVersion[$merchantinfo['version_id']]['template'] : 1;
        $weixincount = WeixinInfo::count_merchant_applet($merchant_id);
        if( $weixincount >=  $countlimit){
            return [ 'errcode'=>1, 'errmsg'=>'模板数量已超出，请升级' ,'max'=>$countlimit];
        }else{
            return [ 'errcode'=>0, 'errmsg'=>'ok' ,'max'=>$countlimit];
        }
    }
    /**
     * @name 创建小程序/公众账号
     * @param $merchant_id int
     * @param $type int 类型 1 小程序2 公众账号
     * @return  array
     */
    public function createApp($merchant_id, $type = 1, $appid = ''){
        if($type == 1){
            $merchantinfo = Merchant::get_data_by_id($merchant_id);
            $merchantVersion = config::get('version');
            $weixincount = WeixinInfo::count_data('merchant_id',$merchant_id);
            $countlimit = isset($merchantVersion[$merchantinfo['version_id']]['template']) ? $merchantVersion[$merchantinfo['version_id']]['template'] : 1;
            if( $weixincount >=  $countlimit && $merchant_id != 14761 ){
                return [ 'errcode'=>2, 'errmsg'=>'模板数量已超出，请升级' ];
            }
            $id = WeixinInfo::insert_data(['merchant_id'=>$merchant_id,'ticket_id'=>config::get('weixin.component_id'),'type'=>$type,'is_sync'=>1]);
            return [ 'errcode'=>0, 'errmsg'=>'','id'=>$id ];
        }
        $checkinfo = WeixinInfo::get_one('merchant_id',$merchant_id,2);
        if(isset($checkinfo['id'])){
            return [ 'errcode'=>0, 'errmsg'=>'','id'=>$checkinfo['id'] ];
        }
        $id =  WeixinInfo::insert_data(['merchant_id'=>$merchant_id,'ticket_id'=>config::get('weixin.component_id'),'type'=>2]);
        return [ 'errcode'=>0, 'errmsg'=>'','id'=>$id ];
    }
    /**
     * @name 获取小程序
     * @param $id int weixin_info id
     * @param $merchant_id int
     * @return array
     */
    public function appinfo($id,$merchant_id){
        $info = WeixinInfo::get_one('id',$id,1);
        if(!isset($info['id']) || empty($info['id']) ||  $info['merchant_id'] != $merchant_id){
            return ['errcode' => 1, 'errmsg' => 'param'];
        }
        $qn_host = Config::get('weixin.qn_host');
        $tplinfo = WeixinTemplate::get_one_ver($info['merchant_id'],$info['appid']);
        if($tplinfo['verify'] != 1 ){
            $tplinfoverify =  WeixinTemplate::get_one_verify($info['merchant_id'],$info['appid']);
        }else{
            $tplinfoverify['id'] = 1;
        }
        $data = [
            'id' => $info['id'],
            'appid' =>$info['appid'],
            'name'  => $info['nick_name'],
            'head'  => $info['head_img'],
            'online' => isset($tplinfoverify['id'])?1:0,
            'qrcode'  => $qn_host.$info['qrcode'],
            'service'  => $info['service_type'],
            'verify'  => $info['verify_type'],
            'user_name'  => $info['user_name'],
            'desc'  => $info['signature'],
            'principal'  => $info['principal_name'],
        ];
        return ['errcode' => 0, 'errmsg' => 'ok','data'=>$data];
    }
    /**
     * @name 获取公众账号
     * @param $merchant_id int
     * @return array
     */
    public function officialAccount($merchant_id){
        $info = WeixinInfo::get_one('merchant_id',$merchant_id,2);
        if(!isset($info['id']) || empty($info['id'])){
            return ['errcode' => 1, 'errmsg' => 'param'];
        }
        $data = [
            'id' => $info['id'],
            'appid' =>$info['appid'],
            'token' => $this->getAccessToken($info['appid']),
            'name'  => $info['nick_name'],
            'head'  => $info['head_img'],
            'qrcode'  => $info['qrcode_url'],
            'service'  => $info['service_type'],
            'auth'    => $info['auth'],
            'verify'  => $info['verify_type'],
            'user_name'  => $info['user_name'],
            'desc'  => $info['signature'],
            'principal'  => $info['principal_name'],
        ];
        return ['errcode' => 0, 'errmsg' => 'ok','data'=>$data,'delete'=>0  ];

    }
    /**
     * @name 同步小程序账号信息
     * @param $merchant_id int
     * @param $appid string
     * @return array
     */
    public function updateAppInfo($merchant_id, $appid = ''){
        if($appid == ''){
            $info = WeixinInfo::get_one('merchant_id', $merchant_id, 2);
        }else{
            $info = WeixinInfo::get_one('appid', $appid);
        }
        if($info['id'] && $info['merchant_id'] != $merchant_id){
            return [ 'errcode'=>10, 'errmsg'=>'privilege grant failed ; key_value:'.$info['id'], 'msg_wsl'=>'通信错误,请稍后重试'  ];
        }
        $Component = new Component();
        $respons = $Component->setComponentToken($this->getComponentToken())->getAuthorizerInfo($info['appid']);
        if(!isset($respons['authorizer_info']) || !isset($respons['authorization_info'])){
            return [ 'errcode'=>11, 'errmsg'=>'token error ; key_value:'.$info['appid'] , 'msg_wsl'=>'通信有误,请稍后重试' ];
        }
        $authorizer    = $respons['authorizer_info'];
        $authorization = $respons['authorization_info'];
        //公众账号 服务号 验证
        if(empty($authorizer['MiniProgramInfo']) && $authorizer['service_type_info']['id'] != 2){
            return [ 'errcode'=>12, 'errmsg'=>'authorize type ; key_value:'.$info['appid'], 'msg_wsl'=>'请绑定服务号' ];
        }
        $datam['type']            =  !empty($authorizer['MiniProgramInfo']) ? 1 : 2;
        $datam['nick_name']       =  empty($authorizer['nick_name'])? $authorizer['user_name'] : $authorizer['nick_name'];
        $datam['head_img']        =  isset($authorizer['head_img'])?$authorizer['head_img']:'';
        $datam['service_type']    =  $authorizer['service_type_info']['id'];
        $datam['verify_type']     =  $authorizer['verify_type_info']['id'];
        $datam['user_name']       =  $authorizer['user_name'];
        $datam['signature']       =  $authorizer['signature'];
        $datam['principal_name']  =  $authorizer['principal_name'];
        $datam['alias']           =  $authorizer['alias'];
        $datam['business_info']   = json_encode($authorizer['business_info']);
        $datam['qrcode_url']      = $authorizer['qrcode_url'];
        $datam['miniprograminfo'] = !empty($authorizer['MiniProgramInfo']['network'])?json_encode($authorizer['MiniProgramInfo']['network']):'';
        $authlist = array_map(function ($v){ return $v['funcscope_category']['id'];},$authorization['func_info']);
        $datam['authlist']  = implode(',',$authlist);
        WeixinInfo::update_data('id',$info['id'], $datam) ;

        return [
            'errcode'=>0, 'errmsg'=>'ok',
            'authorizer_appid'=>$info['appid'],
            'type'=>$datam['type'],
            'data'=>[
                'id' => $info['id'],
                'appid' =>$info['appid'],
                'token' => $this->getAccessToken($info['appid']),
                'name'  => $datam['nick_name'],
                'head'  => $datam['head_img'],
                'qrcode'  => $datam['qrcode_url'],
                'auth'    => 1,
                'service'  => $datam['service_type'],
                'verify'  => $datam['verify_type'],
                'user_name'  => $datam['user_name'],
                'desc'  => $datam['signature'],
                'principal'  => $datam['principal_name'],
            ]
        ];

    }
    /**
     * 删除公众账号
     */
    public function officialDelete($merchant_id,$clear = 0){
        $info = WeixinInfo::get_one('merchant_id',$merchant_id,2);
        if(isset($info['appid']) && !empty($info['appid'])){
            $openc = WeixinOpen::list_count($merchant_id);
            $open = WeixinOpen::get_one('appid',$info['appid']);
            if(isset($open['merchant_id']) && $open['merchant_id'] == $merchant_id && ($openc > 1 || $clear == 1 ) ){
                if($open['bind'] == 1){
                    $applet = (new Applet())->setAccessToken($this->getAccessToken($info['appid'])) ;
                    $response =  $applet->openUnbind($open['appid'],$open['open_appid']);
                    WeixinOpen::update_data('appid',$info['appid'],['status'=>-1,'bind'=>$response['errcode'] == 0 ? 3 : 1]);
                }else{
                    WeixinOpen::update_data('appid',$info['appid'],['status'=>-1]);
                }
            }
            WeixinInfo::delete_data($info['id'],1);
        }
       return true;
    }
    /**
     * 获取小程序列表
     */
    public function appletlist($merchant_id,$name = '',$page = 1,$lenth = 10, $ver = 171101){
        $WeixinInfoQuery = WeixinInfo::where(['merchant_id'=>$merchant_id,'type'=>1, 'status'=>1]);//list_data('merchant_id',$merchant_id);
        if(!empty($name)){
            $WeixinInfoQuery ->where('nick_name','like','%'.$name.'%');
        }
        $WeixinInfo      = $WeixinInfoQuery->get()->toArray();
        $data = [];
        $nick_name  = [];
        $releases = [];
        foreach ($WeixinInfo as $k => $v) {
            if(!empty($v['appid'])){
                $tplinfo = WeixinTemplate::list_version_new($merchant_id,$v['appid'],$ver);
                $release = 0;
                foreach ($tplinfo as $ks => $vs){
                    if($vs['release'] == 1){
                        $release = 1;
                    }
                }
                $nick_name[] = $v['nick_name'];
                $releases[] = $release;
                $data[] = ['id' => $v['id'], 'appid' =>$v['appid'], 'name'  => $v['nick_name'], 'head'  => $v['head_img'], 'qrcode'  => $v['qrcode_url'], 'service'  => $v['service_type'], 'verify'  => $v['verify_type'], 'user_name'  => $v['user_name'], 'desc'  => $v['signature'], 'principal'  => $v['principal_name'], 'release' => $release];
            }
        }
        array_multisort($releases, SORT_DESC, $nick_name, SORT_ASC, $data);
        $data = array_chunk($data,$lenth,true);
        $page--;
        $count = WeixinInfo::count_merchant_applet($merchant_id);
        return ['errcode' => 0, 'errmsg' => 'ok','data'=>isset($data[$page])?$data[$page]:[],'count'=>$count];
    }
    /**
     * 清楚主体
     */
    public function delOpen($merchant_id){
        $list = WeixinOpen::list_data('merchant_id',$merchant_id);
        foreach ($list as $k => $v) {
            $check = WeixinInfo::get_one('appid',$v['appid']);
            if( isset($check['id']) ){ //&& $check['merchant_id'] == $v['merchant_id']
                continue;
            }
            if($v['bind'] == 1){
                $accesstoken = $this->getAccessToken($v['appid'],0) ;
                $applet = (new Applet())->setAccessToken($accesstoken) ;
                $response =  $applet->openUnbind($v['appid'],$v['open_appid']);
                WeixinOpen::update_data('appid',$v['appid'],['status'=>-1,'bind'=>($response['errcode'] == 0 ? 3 : 1)]);
            }else{
                WeixinOpen::update_data('appid',$v['appid'],['status'=>-1]);
            }
        }
        $weixincount = WeixinInfo::count_app_data($merchant_id);
        if($weixincount == 0){
            return ['errcode'=>0,'errmsg'=>'ok'];
        }
        return ['errcode'=>0,'errmsg'=>''];
    }
    /**
     * 清空账号
     */
    public function removeAll($merchantId){
        $applist = WeixinInfo:: query()->select(['id','appid','auth'])->where(['merchant_id'=>$merchantId,'status'=>1])->where('appid','!=','')->get()->toArray();
        foreach ($applist as $k => $v) {
            $accesstoken = $this->getAccessToken($v['appid'],0) ;
            if($accesstoken){
                $applet = (new Applet())->setAccessToken($accesstoken) ;
                $response =  $applet->openUnbind($v['appid'],$v['open_appid']);
            }
            WeixinOpen::update_data('appid',$v['appid'],['status'=>-1,'bind'=>($response['errcode'] == 0 ? 3 : 1)]);
            WeixinInfo::update_data('id',$v['id'],['status'=>-1]);
        }
        WeixinOpen::update_data('merchant_id',$merchantId,['status'=>-1]);
    }

    //========================================== commont ==========================================
    /**
     * @name 小程序码 生成
     * @param  $appid string 小程序路径
     * @param  $path string 不能为空，最大长度 128 字节
     * @param  $applet null obj
     * @return array
     */
    public function qrcode($appid,$path,$applet = null){
        return $this->qrcodeAll($appid,1,$path,$applet);
    }
    /**
     * @name 小程序码 综合
     * @param $appid
     * @param $type  类型 1 永久小程序码 10万个； 2 临时小程序码；  3 小程序二维码；  4 小程序体验二维码；
     * @param $path  小程序路径 例如 /page/index/index?id=0  ; $type = 2 ? 后自动为一个参数 最大32个可见字符，只支持数字，大小写英文以及部分特殊字符：!#$&'()*+,/:;=?@-._~，
     * @param $applet  = null
     * @param $config  = ['width'=>'二维码的宽度430 ','color'=>['r'=>'十进制表示','g'=>'十进制表示','b'=>'十进制表示'] ,'hyaline'=>'true透明']
     * @return  return [ 'errcode'=>1, 'errmsg'=>'','url'=>'','data'=>[] ];
     */
    public function qrcodeAll($appid, $type, $path, $applet = null,$config = []){
        //参数验证
        if(empty($appid) || empty($path) || !in_array($type,[1,2,3,4])){
            return [ 'errcode'=>1, 'errmsg'=>'意外出错请重试','data'=>'params null' ];
        }
        $key = md5($type == 1 ? $appid.'_'.$path : $appid.'_'.$type.'_'.$path);
        //是否已存在
        $check = WeixinQrcodeLog::get_one('key',$key);
        if(isset($check['id']) && !empty($check['url'])){
            return ['errcode' => 0, 'errmsg' => 'ok!','url'=>$check['url']];
        }
        //阻塞缓存
        $blocking = CacheKey::get_weixin_cache($key);
        if(Cache::get($blocking)){
            return [ 'errcode'=>1, 'errmsg'=>'请求超速,请一分钟再试' ];
        }
        Cache::put($blocking,1,1);
        //生成小程序码
        if($applet === null){
            $info = WeixinInfo::get_one('appid',$appid,1);
            if(!isset($info['id'])){
                return [ 'errcode'=>1, 'errmsg'=>'参数有误' ];
            }
            $applet = new Applet();
            $applet->setAccessToken($this->getAccessToken($appid));
        }
        if($type == 1){
            $response = $applet->getCodeQrcode($path,$config);
        }else if($type == 2){
            list($paths,$scene) = explode('?',$path);
            $response = $applet->getCodeLimitQrcode($paths,$scene,$config);
        }else if($type == 3){
            $response = $applet->getAppQrcode($path);
        }else if($type == 4){
            $response = $applet->getQrcode($path);
        }
        //$this->setLog('cache20180614',['key'=>$key,'appid'=>$appid, 'type'=>$type, 'path'=>$path],['errcode'=>0]);
        if(!is_string($response)){
            $this->setLog('qrcodeAll',['key'=>$key,'appid'=>$appid, 'type'=>$type, 'path'=>$path],['errcode'=>1,'response'=>$response]);
            return [ 'errcode'=>1, 'errmsg'=>'意外出错请重试','data'=>$response ];
        }
        $jfif = strpos($response,'JFIF');
        $ispng = strpos($response,'PNG');
        if($jfif != 6 && $ispng != 1){
            $this->setLog('qrcodeAll',['key'=>$key,'appid'=>$appid, 'type'=>$type, 'path'=>$path],['errcode'=>2,'response'=>$response]);
            return [ 'errcode'=>1, 'errmsg'=>'意外出错请重试','data'=>$ispng ];
        }

        $id = isset($check['id'])? $check['id'] : WeixinQrcodeLog::insert_data(['appid'=>$appid,'type'=>$type,'key'=>$key,'path'=>$path]);
        $response = $this->uploadQiniu($response,$appid,$_SERVER['REQUEST_TIME'],($ispng==1?'png':'jpg'));
        if($response['errcode'] != 0){
            $this->setLog('qrcodeAll',['key'=>$key,'appid'=>$appid, 'type'=>$type, 'path'=>$path],['errcode'=>3,'response'=>$response]);
            WeixinQrcodeLog::update_data([ 'id' => $id ], [ 'is_delete'=>-1 ]);
            return [ 'errcode'=>2, 'errmsg'=>$response['errmsg'] ];
        }
        WeixinQrcodeLog::update_data([ 'id' => $id ], [ 'url'=>$response['url'] , 'is_delete' => 1 ]);
        return ['errcode' => 0, 'errmsg' => 'ok','url'=>$response['url']];
    }
    /**
     * Qiniu 图片保存
     */
    public  function uploadQiniu($response,$appid,$version,$suffix = 'jpg'){
        $auth = new qAuth(env('QINIU_ACCESS_KEY'), env('QINIU_SECRET_KEY'));
        $token = $auth->uploadToken(env('QINIU_BUCKET'));//
        $uploadMgr = new UploadManager();
        $urlkey = date('Ymd').'/'.md5($appid.$_SERVER['REQUEST_TIME']).'/'.$appid.'/qrcode.'.$suffix;
        list($errcode, $errmsg) = $uploadMgr->put($token, $urlkey, $response);
        if ($errmsg !== null) {
            \Log::info('uploadQiniu:'.json_encode($errmsg));
            return ['errcode' => 4, 'errmsg' => 'Qiniu:error','err' => $errmsg];
        }
        return ['errcode' => 0, 'errmsg' => 'ok','url'=>'/'.$urlkey.'?v='.$version];
    }
    /**
     * 授权日志
     */
    public function logAuth($merchant_id,$appid,$type,$errcode,$request,$reponse){
        $request = is_array($request) ? json_encode($request) : $request;
        $reponse = is_array($reponse) ? json_encode($reponse) : $reponse;
        return WeixinLogAuthorize::insert_data(['merchant_id'=>$merchant_id,'appid'=>$appid,'type'=>$type,'errcode'=>$errcode,'request'=>$request,'reponse'=>$reponse]);
    }
    /**
     * 通信日志
     */
    public function setLog($action, $request, $response, $merchant_id = '', $key = ''){
        $request = is_array($request) ? json_encode($request) : $request;
        $response = is_array($response) ? json_encode($response) : $response;
        $merchant_id = empty($merchant_id) ?  0 : $merchant_id;
        $key         = empty($key)?'null':$key;
        return WeixinLog::insert_data(['merchant_id'=>$merchant_id,'value'=>$key,'action'=>$action,'request'=>$request,'reponse'=>$response]);
    }

    //========================================== 其他 ==========================================
    /**
     * 新版本是否发布
     */
    public function checkNewVersion($merchant_id,$appid, $ver = 171101){
        $info = WeixinTemplate::get_one_verify($merchant_id,$appid);
        if(isset($info['id']) &&  $info['version_id'] >= $ver){
            return true;
        }else{
            return false;
        }
    }
    /**
     * 小程序是否解绑 （基本废弃）
     */
    public function checkApp($merchant_id,$appid,$id = 0){
        if($id > 0){
            $info = WeixinInfo::check_one_id($id);
        }else{
            $info = WeixinInfo::check_one($merchant_id,$appid);
        }
        if(!isset($info['id'])){
            return ['errcode' => 1, 'errmsg' => '小程序不存在。'];
        }
        if($info['status'] == 1){
            return ['errcode' => 0, 'errmsg' => ''];
        }
        $info = User::query()->where(['merchant_id'=>$info['merchant_id'],'is_admin'=>1])->first();
        return ['errcode' => 1, 'errmsg' => '小程序已关闭，如有需要可以致电商家:'.(isset($info['mobile'])?$info['mobile']:'').'。'];
    }
    /**
     * 小程序码生成 qrcode
     */
    public function limitQrcode($appid,$key,$path = ''){
        $host = Config::get('weixin.qn_host');
        $path = !empty($path)? $path : Config::get('weixin.template_index');
        return $this->qrcodeAll($appid, 1,$path.'?'.$key);
    }

}
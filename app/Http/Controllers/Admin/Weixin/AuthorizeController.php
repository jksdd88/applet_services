<?php
/**
 * Created by PhpStorm.
 * User: wangshiliang@dodoca.com
 * Date: 2017/8/30
 * Time: 14:50
 * link  https://open.weixin.qq.com/cgi-bin/showdocument?action=dir_list&t=resource/res_list&verify=1&id=open1453779503&token=219ebc645a9dad4097ddad647106a68ecd23ec50&lang=zh_CN
 */
namespace App\Http\Controllers\Admin\Weixin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\Fightgroup;
use App\Models\Goods;
use App\Models\Seckill;
use App\Models\WeixinQrcode;
use App\Services\DesignService;
use App\Services\OperateRewardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;
use App\Models\WeixinMsgTemplate;
use App\Models\WeixinInfo;
use App\Models\WeixinOpen;
use App\Models\WeixinPay;
use App\Models\WeixinTemplate;
use App\Models\WeixinSetting;
use App\Models\User;
use App\Services\WeixinMsgService;
use App\Services\WeixinService;
use App\Utils\Weixin\MsgTemplate;
use App\Utils\Weixin\Component;
use App\Utils\Weixin\Applet;
use App\Utils\Encrypt;
use Config;
use Cache;

class AuthorizeController extends Controller
{
    private $request;
    private $merchant_id;
    private $is_admin ;

    private $component_appid;
    private $component_id;
    private $host;
    private $qn_host;


    private $func_count = [ 1 => 6 , 2 => 18 ];

    public function __construct(Request $request,DesignService $designService)
    {
        $this->request = $request;
        $this->merchant_id = Auth::user()->merchant_id ;
        $this->is_admin    =  Auth::user()->is_admin;

        $this->component_appid =  Config::get('weixin.component_appid');//app_env
        $this->component_id =  Config::get('weixin.component_id');
        $this->host = Config::get('weixin.base_host');
        $this->qn_host = Config::get('weixin.qn_host');
        $this->func_count[1] = Config::get('weixin.wechat_auth_count');
        $this->func_count[2] = Config::get('weixin.wechat_auths_count');

        $this->app_env = Config::get('weixin.app_env');
        $this->test_merchant = Config::get('weixin.test_merchant_id');
        $this->designService      = $designService;
    }

    public function index(){

    }

    // ====================   授权 推送配置  ====================
    //获取authorizer_refresh_token
    private function authorizationInfo($code , $id = 0, $type = 1, $tpl = 'V'){
        $WeixinService = new WeixinService();
        $WeixinService->clearPreAuthCode($this->merchant_id);
        $componentToken = $WeixinService->getComponentToken();
        $Component = new Component();
        $respons = $Component->setComponentToken($componentToken) ->queryAuth($code);
        $respons = isset($respons['authorization_info'])?$respons['authorization_info']:[];
        if(!isset($respons['authorizer_appid']) || empty($respons['authorizer_appid']) || !isset($respons['authorizer_refresh_token']) || empty($respons['authorizer_refresh_token'])){
            return [ 'errcode'=>1, 'errmsg'=>'privilege grant failed', 'msg_wsl'=>'授权失败' ];
        }
        //$WeixinService->setLog('cache20180526',$respons,['type'=>1],$this->merchant_id,$id);
        $authlist = array_map(function ($v){ return $v['funcscope_category']['id']; },$respons['func_info']);
        //检查是否授权
        $info = WeixinInfo::get_one('appid', $respons['authorizer_appid']);
        if(isset($info['id']) && $info['id'] ){
            //更新token
            WeixinInfo::update_data('id',$info['id'], ['token'=>$respons['authorizer_refresh_token']]) ;
            $WeixinService->clearCacheAccessToken($info['appid']);
            if($type == 1 && $info['type']==2){
                return [ 'errcode'=>6, 'errmsg'=>'type error', 'msg_wsl'=>'在小程序与公众账号列表中请选择小程序，不要选择公众账号', 'authorizer_appid'=>$respons['authorizer_appid'] ];
            }
            if($type == 2 && $info['type']==1){
                return [ 'errcode'=>5, 'errmsg'=>'type error', 'msg_wsl'=>'在小程序与公众账号列表中请选择公众账号，不要选择小程序', 'authorizer_appid'=>$respons['authorizer_appid'] ];
            }
            //检查
            if($info['merchant_id'] != $this->merchant_id){
                $userinfo = User::query()->where(['merchant_id'=>$info['merchant_id'],'is_admin'=>1])->first();
                $userinfo['mobile'] = empty($userinfo['username'])?$userinfo['mobile']:$userinfo['mobile'];
                return [ 'errcode'=>2, 'errmsg'=>'privilege grant failed ; key_value:'.$info['merchant_id'], 'msg_wsl'=>($info['type']==1?'小程序':'公众账号').'已绑定过本平台其他账号('.$userinfo['mobile'].')，请解绑后重试' , 'authorizer_appid'=>$respons['authorizer_appid'] ];
            }
            if($info['id'] != $id){
                return [ 'errcode'=>3, 'errmsg'=>'privilege grant failed ; key_value:'.$info['id'], 'msg_wsl'=>'您的'.($info['type']==1?'小程序':'公众账号').'已授权，请不要重复授权' , 'authorizer_appid'=>$respons['authorizer_appid'] ];
            }
        }
        //类型和权限集验证
        if($type != 1 && in_array(17,$authlist)){//小程序 17,18,19,25, 30,31
            return [ 'errcode'=>5, 'errmsg'=>'type error', 'msg_wsl'=>'在小程序与公众账号列表中请选择公众账号，不要选择小程序', 'authorizer_appid'=>$respons['authorizer_appid'] ];
        }
        if($type != 2 && in_array(1,$authlist)){ //公众账号 1,2,3,4,5,6,7,8,9,10,11,12,13,15,22,23,24,26
            return [ 'errcode'=>6, 'errmsg'=>'type error', 'msg_wsl'=>'在小程序与公众账号列表中请选择小程序，不要选择公众账号', 'authorizer_appid'=>$respons['authorizer_appid'] ];
        }
        if($this->func_count[$type]  > count($authlist)){
            return [ 'errcode'=>7, 'errmsg'=>'authorize list; key_value : '.implode(',',$authlist), 'msg_wsl'=>'请将'.($type == 1 ? '小程序' : '公众账号' ).'的所有权限授权给点点客', 'authorizer_appid'=>$respons['authorizer_appid'] ];
        }
        $tpl_update = true;
        //创建
        if($id == 0){
            $response = (new WeixinService())->createApp($this->merchant_id,$type,$respons['authorizer_appid']);
            if($response['errcode'] != 0){
                return [ 'errcode'=>8, 'errmsg'=>'createApp error ', 'msg_wsl'=>$response['errmsg'], 'authorizer_appid'=>$respons['authorizer_appid'] ];
            }
            $id = $response['id'];
        }else{
            $info = WeixinInfo::get_one('id', $id);
            if( !isset($info['id']) || $info['merchant_id']  != $this->merchant_id ){
                return [ 'errcode'=>4, 'errmsg'=>'id error  ; key_value:'.$id, 'msg_wsl'=>'您的授权id 有误' , 'authorizer_appid'=>$respons['authorizer_appid'] ];
            }
            $tpl_update = $info['tpl_type'] == $tpl ? false : true;
        }
        WeixinInfo::update_data('id',$id, [
            'appid'=>$respons['authorizer_appid'],
            'token'=>$respons['authorizer_refresh_token'],
            'authlist'=>implode(',',$authlist),
            'tpl_type'=>$tpl,
            'auth'=>1,
            'auth_time'=>date('Y-m-d H:i;s')
        ]) ;
        //errcode 9
        return [ 'errcode'=>0, 'errmsg'=>'ok', 'authorizer_appid'=>$respons['authorizer_appid'],'id'=> $id ,'tpl_update'=> $tpl_update];
    }
    //获取小程序、公众账号信息
    private function getAuthorizerInfo($appid){
        $info = WeixinInfo::get_one('appid', $appid);
        if($info['id'] && $info['merchant_id'] != $this->merchant_id){
            return [ 'errcode'=>10, 'errmsg'=>'privilege grant failed ; key_value:'.$info['id'], 'msg_wsl'=>'通信错误,请稍后重试'  ];
        }
        $WeixinService = new WeixinService();
        $Component = new Component();
        $respons = $Component->setComponentToken($WeixinService->getComponentToken())->getAuthorizerInfo($appid);
        if(!isset($respons['authorizer_info']) || !isset($respons['authorization_info'])){
            return [ 'errcode'=>11, 'errmsg'=>'token error ; key_value:'.$appid , 'msg_wsl'=>'通信有误,请稍后重试' ];
        }
        $authorizer    = $respons['authorizer_info'];
        $authorization = $respons['authorization_info'];
        //公众账号 服务号 验证
        if(empty($authorizer['MiniProgramInfo']) && $authorizer['service_type_info']['id'] != 2){
            return [ 'errcode'=>12, 'errmsg'=>'authorize type ; key_value:'.$appid, 'msg_wsl'=>'请绑定服务号' ];
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
        //errcode 13 14
        return [ 'errcode'=>0, 'errmsg'=>'ok','authorizer_appid'=>$appid,'type'=>$datam['type']  ];
    }
    //绑定第三方平台
    private function opeinAppid($appid, $type =1){
        $WeixinService = new WeixinService();
        $Applet = ( new Applet() ) ->setAccessToken($WeixinService->getAccessToken($appid));
        $check = WeixinOpen::get_one('appid',$appid);
        //检查冲突
        if(isset($check['id']) && !empty($check['id'])){
            if($check['bind'] == 1){
                if($check['merchant_id'] != $this->merchant_id){
                    $userinfo = User::query()->where(['merchant_id'=>$check['merchant_id'],'is_admin'=>1])->first();
                    $userinfo['mobile'] = empty($userinfo['username'])?$userinfo['mobile']:$userinfo['mobile'];
                    return  ['errcode'=>15,'errmsg'=>'merchant_id error; key_value:'.$check['id'] , 'msg_wsl'=>($check['type']==1?'小程序':'公众账号').'已绑定过本平台其他账号('.$userinfo['mobile'].')，请解绑后重试。'];
                }
            }else{
                WeixinOpen::update_data('id',$check['id'],['status'=>-1]);
            }
        }
        $list = WeixinOpen::list_data('merchant_id',$this->merchant_id);
        //未绑定过
        if(!isset($list[0]['open_appid'])){
            //创建
            $openCreate =  $Applet ->openCreate($appid);
            if($openCreate['errcode'] != 0 && $openCreate['errcode'] != 89000){
                return  ['errcode'=>16,'errmsg'=>'open Create error ; key_value:'.$openCreate['errcode'], 'msg_wsl'=>'开放平台通信错误,请稍后重试'];
            }
            //获取已有
            if($openCreate['errcode'] == 89000){
                $openGet =$Applet->openGet($appid);
                if(empty($openGet['open_appid'])){
                    return  ['errcode'=>17,'errmsg'=>'open Create error;1 key_value:'.$Applet->http_response,'msg_wsl'=>'开放方平台通信错误,请稍后重试'];
                }
                WeixinOpen::insert_data(['merchant_id'=>$this->merchant_id,'open_appid'=>$openGet['open_appid'],'appid'=>$appid,'type'=>$type,'bind'=>1]);
                return ['errcode'=>0,'errmsg'=>''];
            }
            if(empty($openCreate['open_appid'])){
                return  ['errcode'=>17,'errmsg'=>'open Create error;2 key_value:'.$Applet->http_response,'msg_wsl'=>'开放方平台通信错误,请稍后重试'];
            }
            WeixinOpen::insert_data(['merchant_id'=>$this->merchant_id,'open_appid'=> $openCreate['open_appid'],'appid'=>$appid,'type'=>$type,'bind'=>1]);
            return ['errcode'=>0,'errmsg'=>''];
        }else{
            //已存在  绑定
            $openAppid = $list[0]['open_appid'];
            $appidlist = [];
            foreach ($list as $k => $v) {
                $appidlist[$v['appid']] = ['bind' => $v['bind'] , 'id' => $v['id']];
            }
            if(!array_key_exists($appid,$appidlist)){
                $id = WeixinOpen::insert_data(['merchant_id'=>$this->merchant_id,'open_appid'=>$openAppid,'appid'=>$appid,'type'=>$type,'bind'=>1]);
            }else{
                $id = $appidlist[$appid]['id'];
            }
            $opencount = WeixinOpen::list_count($this->merchant_id);
            $status  = $opencount > 1 ? -1 : 1;
            //绑定
            $openBind = $Applet->openBind($appid,$openAppid);
            if($openBind['errcode'] != 0 && $openBind['errcode'] != 89000){
                if($openBind['errcode'] == 89001){
                    $msg_wsl = '请确保授权的（小程序/公众账号）和已授权账号是同一个主体。';
                    return  ['errcode'=>20,'errmsg'=>' open Create error;key_value:'.$openBind['errcode'],'msg_wsl'=>$msg_wsl];
                }
                if($openBind['errcode'] == 89003){
                    $msg_wsl = '已授权给其他开放方平台,请在微信开发平台管理中心（小程序/公众账号）解绑';
                    return  ['errcode'=>30,'errmsg'=>' open Create error;key_value:'.$openBind['errcode'],'msg_wsl'=>$msg_wsl];
                }
                if($openBind['errcode'] == 89004){
                    $msg_wsl = '该开放平台帐号所绑定的公众号/小程序已达上限（100个）';
                    return  ['errcode'=>31,'errmsg'=>' open Create error;key_value:'.$openBind['errcode'],'msg_wsl'=>$msg_wsl];
                }
                WeixinOpen::update_data('id',$id,['bind'=>2,'status'=>$status]);
                return  ['errcode'=>18,'errmsg'=>' open Create error;key_value:'.$openBind['errcode'],'msg_wsl'=>'第三方平台通信错误'];
            }
            //获取已有
            if($openBind['errcode'] == 89000){
                $openGet = $Applet->openGet($appid);
                if(!isset($openGet['open_appid'])){
                    return  ['errcode'=>18,'errmsg'=>' open Create error;key_value:'.json_encode($openGet),'msg_wsl'=>'第三方平台通信错误'];
                }
                if( $openAppid == $openGet['open_appid']){
                    WeixinOpen::update_data('id',$id,['bind'=>1]);
                }else{
                    WeixinOpen::update_data('id',$id,['bind'=>2,'status'=>$status]);
                }
                if($openAppid != $openGet['open_appid']){
                    return  ['errcode'=>19,'errmsg'=>' open Create error','msg_wsl'=>'已授权给其他开放方平台，请在微信公众平台中解绑后重试'];
                }
            }
            WeixinOpen::update_data('id',$id,['bind'=>1]);
            return ['errcode'=>0,'errmsg'=>''];
        }
        return ['errcode'=>0,'errmsg'=>''];
    }
    //授权后 配置域名
    private function modifyDomainCommit($appid){
        $info = WeixinInfo::get_one('appid', $appid);
        if($info['id'] && $info['merchant_id'] != $this->merchant_id){
            return [ 'errcode'=>24, 'errmsg'=>'privilege grant failed'  ];
        }
        $weixinService = new WeixinService();
        $applet = (new Applet())->setAccessToken($weixinService->getAccessToken($appid)) ;
        $verinfo = $weixinService->getVersion($info['tpl_type'],$this->merchant_id);
        //域名
        $response = $applet->modifyDomain();
        if($response['errcode'] != 0){
            return ['errcode'=>21,'errmsg'=>$response['errcode'].' '.$response['errmsg']];
        }
        $infodata['miniprograminfo'] = json_encode(['network'=>$response['data']]);
        //业务域名
        if($info['principal_name'] != '个人'){
            $responses = $applet->webviewDomain();
            if($responses['errcode'] == 0){
                $infodata['webview_domain'] = json_encode($responses['data']);
            }
        }
        WeixinInfo::update_data('id',$info['id'],$infodata);

        $tplinfo =  WeixinTemplate::get_one_ver($this->merchant_id,$appid);
        if(isset($tplinfo['id']) && $tplinfo['id']){
            $tplinfoid = $tplinfo['id'];
            WeixinTemplate::update_data($tplinfo['id'],['host'=>json_encode($response['data']),'host_date'=>$_SERVER['REQUEST_TIME']  ]);//$_SERVER['REQUEST_TIME']
            if($tplinfo['check'] > 0 && $tplinfo['pass_date'] == 0 ) {
                $responsex = $applet->getAuditStatus($tplinfo['check']);
                if ($responsex['errcode'] != 0 || ($responsex['status'] != 0 && $responsex['status'] != 1)) {
                    return ['errcode' => 0, 'errmsg' => 'ok', 'status'=>1];
                }
            }
        }
        $tplinfoid = WeixinTemplate::insert_data([
            'merchant_id'=>$this->merchant_id,
            'appid'=>$appid,
            'version_id'=>$verinfo['id'],
            'version'=>$verinfo['version'],
            'tag'=>(isset($tplinfo['tag'])?$tplinfo['tag']:''),
            'category'=>(isset($tplinfo['category'])?$tplinfo['category']:''),
            'host'=>json_encode($response['data']),
            'host_date'=>$_SERVER['REQUEST_TIME']
        ]);
        //模板 和 配置
        $applet->setVersion($verinfo);
        $encrypt = new  Encrypt();
        $commit = $applet-> commit($appid,$encrypt->encode($this->merchant_id),$encrypt->encode($info['id']));
        if($commit['errcode'] != 0){
            return ['errcode'=>23,'errmsg'=>$commit['errcode'].''.$commit['errmsg']];
        }
        WeixinTemplate::update_data($tplinfoid,['exit'=>$commit['data'],'exit_date'=>($commit['errcode'] == 0 ? $_SERVER['REQUEST_TIME'] : 0 )]);
        //体验二维码
        $response = $weixinService->qrcodeAll($appid,4, Config::get('weixin.template_index'),$applet);
        if($response['errcode'] == 0){
            WeixinInfo::update_data('id',$info['id'],['qrcode' =>$response['url'] ]);
        }
        //errcode 22
        return ['errcode'=>0,'errmsg'=>'ok', 'status'=>0];
    }
    // domain commit  授权失败 回滚
    private function authrollback($appid, $del = 1, $clear = 0){// $del 1 回滚 0 强制删除 $clear 1 清除主体
        $open = WeixinOpen::get_one('appid',$appid);
        $info = WeixinInfo::get_one('appid',$appid);
        $pay  = WeixinPay::get_one( 'appid',$appid);
        $tpl  = WeixinTemplate::get_one('appid',$appid);
        if(isset($open['id']) && isset($info['id']) && isset($tpl['id']) && $del == 1){  // 成功不回滚
            return true;
        }
        $openc = WeixinOpen::list_count($this->merchant_id);
        $WeixinService = new WeixinService();
        if(isset($open['merchant_id']) && $open['merchant_id'] == $this->merchant_id && ($openc > 1 || $clear == 1) ){
            if($open['bind'] == 1){
                $applet = (new Applet())->setAccessToken($WeixinService->getAccessToken($open['appid'])) ;
                $response =  $applet->openUnbind($open['appid'],$open['open_appid']);
                WeixinOpen::update_data('appid',$appid,['status'=>-1,'bind'=>$response['errcode'] == 0 ? 3 : 1]);
            }else{
                WeixinOpen::update_data('appid',$appid,['status'=>-1]);
            }
        }
        if(isset($tpl['merchant_id']) && $tpl['merchant_id'] == $this->merchant_id){
            WeixinTemplate::delete_data_app($appid,['status'=>-1]);
        }
        if(isset($pay['merchant_id']) && $pay['merchant_id'] == $this->merchant_id){
            WeixinPay::update_data('appid',$appid,['status'=>-1]);
        }
        if(isset($info['merchant_id']) && $info['merchant_id'] == $this->merchant_id){
            WeixinInfo::delete_data($info['id']);
            $WeixinService->logAuth($this->merchant_id,$appid,2,0,'','');
            $WeixinService->clearCacheAccessToken($appid);
        }
        $WeixinService->clearCacheAccessToken($appid);
        return true;
    }
    //获取授权链接
    public function authorizes(){
        $type   = $this->request->get('type',0);
        $id     = $this->request->get('id',0);
        $callback = $this->request->get('callback',2);
        $typeTpl  =  $this->request->get('tpl','V');
        if($type != 1 && $type != 2){
            return [ 'errcode'=>1, 'errmsg'=>'param error' ];
        }
        if($type == 1 && $callback != 1 && $callback != 2){ //公众账号唯一 直接生成id
            return [ 'errcode'=>1, 'errmsg'=>'param error' ];
        }
        $pre_auth_code = (new WeixinService())->getPreAuthCode($this->merchant_id);//缓存
        if(!$pre_auth_code){
            return [ 'errcode'=>1, 'errmsg'=>'通信有误' ];
        }
        $type = $type.'_'.$id.'_'.$callback.'_'.$typeTpl;
        $url = 'https://mp.weixin.qq.com/cgi-bin/componentloginpage?component_appid='.$this->component_appid.'&pre_auth_code='.$pre_auth_code.'&redirect_uri='.urlencode($this->host.'/weixin/authback?type='.$type);
        if(!empty($pre_auth_code)){
            return [ 'errcode'=>0, 'errmsg'=>'ok', 'url'=>$url ];
        }else{
            return [ 'errcode'=>1, 'errmsg'=>'get token error' ];
        }
    }
    //授权回调
    public function authorizeback(){
        $request = $this->request->all();
        //授权
        list($request['type'],$request['id'],$request['callback'],$request['tpl']) = explode('_',$request['type']);
        $request['tpl'] = !in_array($request['tpl'] ,Config::get('weixin.template_type'))?'V':$request['tpl'] ;
        $error = [ 'errcode' => 0 , 'msg' => '' ,'errmsg' => '','tpl'=>'' , 'status'=>0] ;
        $appid = '';
        $tpl_update = true;
        $request['tpl'] = empty($request['tpl'])?'V':$request['tpl'];
        $weixinService = new WeixinService();
        if($request['type'] == 1 || $request['type'] == 2){
            $resutl  = $this->authorizationInfo($request['auth_code'],$request['id'],$request['type'],$request['tpl']);
            $appid = isset($resutl['authorizer_appid']) ? $resutl['authorizer_appid'] : '';
            if($resutl['errcode'] != 0){
                $error = [ 'errcode' => $resutl['errcode'] , 'msg' => $resutl['errmsg'] ,'errmsg' => (isset($resutl['msg_wsl']) ? $resutl['msg_wsl'] : "授权失败"),'tpl'=>$request['tpl']] ;
            }else{
                $request['id'] = $resutl['id'];
                $tpl_update    = $resutl['tpl_update'];
                //基础信息
                //$autharray = $this->getAuthorizerInfo($resutl['authorizer_appid']);
                $autharray = $weixinService->updateAppInfo($this->merchant_id,$resutl['authorizer_appid']);
                if($autharray['errcode'] != 0){
                    $this->authrollback($resutl['authorizer_appid']);
                    $error = [ 'errcode' => $autharray['errcode'] , 'msg' => $autharray['errmsg'] ,'errmsg' => (isset($autharray['msg_wsl']) ? $autharray['msg_wsl'] : "授权失败"),'tpl'=>$request['tpl']] ;
                }else{
                    //公众平台
                    $request['type'] = $autharray['type'];
                    $openarray = $this->opeinAppid($resutl['authorizer_appid'],$request['type']);
                    if($openarray['errcode'] != 0){
                        $this->authrollback($resutl['authorizer_appid']);
                        $error = [ 'errcode' => $openarray['errcode'] , 'msg' => $openarray['errmsg'] ,'errmsg' => (isset($openarray['msg_wsl']) ? $openarray['msg_wsl'] : "授权失败"),'tpl'=>$request['tpl']] ;
                    }else if($autharray['type'] == 1){
                        //小程序代码推送
                        $comitarray = $this->modifyDomainCommit($resutl['authorizer_appid']);
                        if($comitarray['errcode'] != 0){
                            $this->authrollback($resutl['authorizer_appid']);
                            $error = [ 'errcode' => $comitarray['errcode'] , 'msg' => $comitarray['errmsg'] ,'errmsg' => (isset($comitarray['msg_wsl']) ? $comitarray['msg_wsl'] : "授权失败"),'tpl'=>$request['tpl']] ;
                        }else{
                            $error['status'] = $comitarray['status'];
                        }
                    }
                }
            }
        }
        //授权结果日志
        if($error['errcode'] != 0 ){
            $weixinService->logAuth($this->merchant_id,$appid,1,$error['errcode'],['id'=>$request['id'],'type'=>$request['type']],$error);
        }else{
            $weixinService->logAuth($this->merchant_id,$appid,0,0,['id'=>$request['id'],'type'=>$request['type']],$error);
            (new OperateRewardService())->operateReward(1,$this->merchant_id);
            if($tpl_update && ($request['tpl'] == 'V' || $request['tpl'] == 'W')){
                $template_type =  Config::get('config.template_type');
                $template_type = $request['tpl'] == 'V' ? $template_type['mall'] :  $template_type['website'];
                $this->designService->getTemplet($this->merchant_id, $request['id'],$template_type);
            }
        }
        $weixinService->clearPreAuthCode($this->merchant_id);
        //回调url
        $param = '';
        if($request['type'] == 1){
            if($error['errcode'] != 0 ){
                $url = Config::get('weixin.wechat_auth_callcack').http_build_query($error);
            }else{
                $url = Config::get('weixin.wechat_auth_callcack').'id='.$request['id'].'&status='.$error['status'];
            }
        }else{
            if($error['errcode'] != 0 ){
                $param = '?'.http_build_query($error);
            }
            $url = Config::get('weixin.wechat_auth_callcacks').$param;
        }
        header('Location: '.$this->host.$url);
        exit();
    }
    //删除授权
    public function authdel(){
        $id = $this->request->get('id',0);
        $appid = $this->request->get('appid','');
        $type  = $this->request->get('type',1);
        $clear = $this->request->get('clear',0);
        if(!empty($id)){
            $info = WeixinInfo::get_one('id',$id,$type);
        }else if(!empty($appid)){
            $info = WeixinInfo::get_one_appid($this->merchant_id,$appid,$type);
        }else{
            return [ 'errcode'=>1, 'errmsg'=>'param null' ];
        }
        if(empty($info['id']) || $info['merchant_id'] != $this->merchant_id){
            return [ 'errcode'=>1, 'errmsg'=>'unauthorized' ];
        }
        if($info['appid']){
            $this->authrollback($info['appid'],0,$clear);
        }else{
            WeixinInfo::delete_data($info['id']);
            WeixinSetting::delete_data($info['id'],$this->merchant_id);
        }
        if(!empty($info['appid'])){
            $weixincount = WeixinInfo::count_app_data($this->merchant_id);
            $last = ($weixincount == 0 ? 1 : 0);
        }else{
            $last = 0;
        }
        return [ 'errcode'=>0, 'errmsg'=>'ok','last'=>$last ];
    }
    //刷新账号信息
    public function refresh()
    {
        $id = $this->request->get('id',0);
        $appid = $this->request->get('appid','');
        if(!empty($id)){
            $info = WeixinInfo::get_one('id',$id,1);
        }else if(!empty($appid)){
            $info = WeixinInfo::get_one_appid($this->merchant_id,$appid);
        }else{
            return [ 'errcode'=>1, 'errmsg'=>'param null' ];
        }
        if(!isset($info['id']) || empty($info['appid']) || $info['type'] != 1 || $info['merchant_id'] != $this->merchant_id){
            return [ 'errcode'=>1, 'errmsg'=>'unauthorized' ];
        }
        $result = $this->getAuthorizerInfo($info['appid']);
        $info = WeixinInfo::get_one_appid($this->merchant_id,$info['appid']);
        return [ 'errcode'=>$result['errcode'], 'errmsg'=>$result['errmsg'] ,'data' => [ 'name'=>$info['nick_name'] ] ];
    }
    //============================== 提交审核  小程序  ==============================
    //切换
    public function changeTpl(){
        return [ 'errcode'=>0, 'errmsg'=>'请使用新接口' ];
    }
    //获取类目
    public function category(){
        $id = $this->request->get('id',0);
        $appid = $this->request->get('appid','');
        if(!empty($id)){
            $info = WeixinInfo::get_one('id',$id,1);
        }else if(!empty($appid)){
            $info = WeixinInfo::get_one_appid($this->merchant_id,$appid);
        }else{
            return [ 'errcode'=>1, 'errmsg'=>'param null' ];
        }
        if(!isset($info['id']) || empty($info['appid']) || $info['type'] != 1|| $info['merchant_id'] != $this->merchant_id){
            return [ 'errcode'=>1, 'errmsg'=>'unauthorized' ];
        }
        //类目
        $applet = (new Applet())->setAccessToken((new WeixinService())->getAccessToken($info['appid'])) ; // errcode   errmsg   category_list ：$v['first_class'] $v['second_class'] $v['first_id'] $v['second_id']  / $v['third_class']  $v['third_id']
        $response = $applet->getCategory();
        return ['errcode'=>$response['errcode'],'errmsg'=>$response['errmsg'],'data'=>isset($response['category_list'])?$response['category_list']:[],'response'=>$info['appid']];
    }
    //提交审核
    public function verify(){
        $id = $this->request->input('id',0);
        $appid = $this->request->input('appid','');
        $tplType =  $this->request->input('type','');
        $tag =  $string =  preg_replace ("/\s(?=\s)/","\\1", $this->request->input('tag') ); ;
        $data['first_class']= $this->request->input('first_class');
        $data['second_class']= $this->request->input('second_class');
        $data['first_id']= $this->request->input('first_id');
        $data['second_id']= $this->request->input('second_id');
        $data['third_id']= $this->request->input('third_id');
        $data['third_class']= $this->request->input('third_class');
        if(!empty($tplType) && !in_array($tplType,Config::get('weixin.template_type'))){
            return ['errcode'=>1,'errmsg'=>'模板类型有误'];
        }
        //参数检查
        if(empty($tag) || empty($data['first_class']) || empty($data['second_class']) || empty($data['first_id']) || empty($data['second_id'])){
            return ['errcode'=>1,'errmsg'=>'value null'];
        }
        if(empty($data['third_id']) || empty($data['third_class'])){
            unset($data['third_id']);
            unset($data['third_class']);
        }

        if(!empty($id)){
            $info = WeixinInfo::query()->where(['id'=>$id,'status'=>1,'type'=>1])->first();
        }else if(!empty($appid)){
            $info = WeixinInfo::query()->where(['merchant_id'=>$this->merchant_id,'appid'=>$appid,'status'=>1,'type'=>1])->first();
        }else{
            return [ 'errcode'=>1, 'errmsg'=>'param null' ];
        }
        if(!isset($info['id']) || empty($info['appid']) || $info['type'] != 1 || $info['merchant_id'] != $this->merchant_id){
            return [ 'errcode'=>1, 'errmsg'=>'unauthorized' ];
        }
        //检查
        $tplinfo = WeixinTemplate::get_one_ver($this->merchant_id,$info['appid']);
        if(!isset($tplinfo['id'])){
            return ['errcode'=>3,'errmsg'=>'tpl  null'];
        }
        if($tplinfo['check'] > 0 && $tplinfo['verify'] == 0){
            return ['errcode'=>10,'errmsg'=>'微信平台正在审核，请耐心等待'];
        }
        //发布信息版本
        $WeixinService = new WeixinService();
        $applet = (new Applet())->setAccessToken($WeixinService->getAccessToken($info['appid'])) ;
        $version = $WeixinService->getVersion(empty($tplType)?$info['tpl_type']:$tplType,$this->merchant_id);
        //模板改版 或者  有新版本
        if((!empty($tplType) && $tplType != $info['tpl_type']) || $version['id'] > $tplinfo['version_id']){
            $response = $applet->modifyDomain();
            if( $response['errcode'] == 1 ){
                $tplinfo['host'] = $response['data'];
                $tplinfo['host_date'] = $_SERVER['REQUEST_TIME'];
            }
            $encrypt = new  Encrypt();
            $applet->setVersion($version);
            $response = $applet -> commit($info['appid'],$encrypt->encode($this->merchant_id),$encrypt->encode($info['id']));
            if($response['errcode'] != 0 ){
                unset($response['data']);
                return [ 'errcode'=>2, 'errmsg'=>'commit error' ,'response'=>$response ];
            }
            $tplinfo['exit'] = $response['data'];
            $tplinfo['exit_date'] = $_SERVER['REQUEST_TIME'];
        }

        //提交审核
        $applet->setVersion($version);
        $response = $applet->submitAudit('首页',$tag,$data);
        if($response['errcode'] != 0  && $response['errcode'] != 85009){ //
            switch ($response['errcode']){
                case 85006 :  $msgwsl = '标签格式错误，请填写简单的关键词';break;
                case 85008 :  $msgwsl = '类目填写错误，请核对微信后台类目信息';break;
                case 85009 :  $msgwsl = '已经有正在审核的版本,请等待审核通过后再提交';break;
                case 85077 :  $msgwsl = '小程序类目信息失效（类目中含有官方下架的类目，请重新选择类目）';break;
                case 86002 :  $msgwsl = '小程序还未设置昵称、头像、简介。请在微信后台完善信息';break;
                default: $msgwsl = '意外错误，请联系客服';
            }
            return ['errcode'=>6,'errmsg'=>$msgwsl,'error'=>$response['errmsg'],'errorcode'=>$response['errcode']];
        }
        $tplinfo['check_exit'] = $response['data'];
        $tplinfo['check_date'] = $_SERVER['REQUEST_TIME'];
        if ($response['errcode'] == 85009){
            $state = $this->app_env == 'production' && !in_array($this->merchant_id,$this->test_merchant) ? 1 : 0;
            if($state == 1){
                return [ 'errcode'=>7, 'errmsg'=>'微信后台已有审核中版本，请等待审核通过后再发布。' ,'version' => '' ];
            }else{
                $response = $applet->getLatestAuditStatus();
                if($response['errcode'] != 0){
                    return [ 'errcode'=>$response['errcode'], 'errmsg'=>$response['errmsg'] ];
                }
            }
        }
        $category = $data['first_class'].'#'.$data['second_class'].(isset($data['third_class'])? '#'.$data['third_class']:'' );
        WeixinTemplate::insert_data([
            'merchant_id'=>$this->merchant_id,
            'appid'=>$info['appid'],
            'check' => $response['auditid'],
            'version_id'=>$version['id'],
            'version'=>$version['version'],
            'tag'=>$tag,
            'category'=>$category,
            'host'=> $tplinfo['host'],
            'host_date'=>$tplinfo['host_date'],
            'exit'=>$tplinfo['exit'],
            'exit_date' =>$tplinfo['exit_date'],
            'check_exit'=>$tplinfo['check_exit'],
            'check_date' =>$tplinfo['check_date'],
        ]);
        if(!empty($tplType) && $tplType != $info['tpl_type']){
            WeixinInfo::update_data('id',$info['id'],['tpl_type'=>$tplType]);
        }
        if(!empty($tplType) && $tplType != $info['tpl_type'] && ($tplType == 'V' || $tplType == 'W')){
            $template_type = Config::get('config.template_type');
            $template_type =  $tplType == 'V' ? $template_type['mall'] :  $template_type['website'];
            $this->designService->getTemplet($this->merchant_id, $info['id'],$template_type);
        }
        return ['errcode'=>0,'errmsg'=>'ok'];
    }
    //更新版本
    public function upgrade(){
        $id = $this->request->input('id',0);
        $appid = $this->request->input('appid','');
        if(!empty($id)){
            $info = WeixinInfo::query()->where(['id'=>$id,'status'=>1,'type'=>1])->first();
        }else if(!empty($appid)){
            $info = WeixinInfo::query()->where(['merchant_id'=>$this->merchant_id,'appid'=>$appid,'status'=>1,'type'=>1])->first();
        }else{
            return [ 'errcode'=>1, 'errmsg'=>'param null' ];
        }
        if(!isset($info['id']) || empty($info['appid']) || $info['type'] != 1 || $info['merchant_id'] != $this->merchant_id){
            return [ 'errcode'=>1, 'errmsg'=>'unauthorized' ];
        }
        $infoTpl = WeixinTemplate::get_one_ver($this->merchant_id,$info['appid']);
        $versionType = $this->app_env == 'production' && !in_array($this->merchant_id,$this->test_merchant) ? 1 : 0;
        $applet = new Applet();
        $WeixinService = new WeixinService();
        $version = $WeixinService->getVersion($info['tpl_type'],$this->merchant_id);
        $applet -> setAccessToken($WeixinService->getAccessToken($info['appid']));
        if($version['id'] <= $infoTpl['version_id']){
            return [ 'errcode'=>1, 'errmsg'=>'已是最新版本' ];
        }
        if(empty($infoTpl['check_exit'])){
            return [ 'errcode'=>2, 'errmsg'=>'请先提交审核' ];
        }
        //域名
        $response = $applet->modifyDomain();
        if($response['errcode'] == 0){
            $infodata['miniprograminfo'] = json_encode(['network'=>$response['data']]);
        }else{
            return [ 'errcode'=>1, 'errmsg'=>'域名更新有误','response'=>$response ];
        }
        //业务域名
        if($info['principal_name'] != '个人'){
            $responses = $applet->webviewDomain();
            if($responses['errcode'] == 0){
                $infodata['webview_domain'] = json_encode($responses['data']);
            }else{
                return [ 'errcode'=>1, 'errmsg'=>'业务域名更新有误','response'=>$responses ];
            }
        }
        if(!empty($infodata)){
            WeixinInfo::update_data('id',$info['id'],$infodata);
        }
        //推送配置信息
        $encrypt = new  Encrypt();
        $applet->setVersion($version);
        $response = $applet -> commit($info['appid'],$encrypt->encode($this->merchant_id),$encrypt->encode($info['id']));
        if($response['errcode'] != 0 ){
            unset($response['data']);
            return [ 'errcode'=>2, 'errmsg'=>'commit error test' ,'response'=>$response ];
        }
        $infoTpl['exit'] =  $response['data'];

        //提交审核
        $checkexit  = json_decode($infoTpl['check_exit'],true);
        $checkexit  = $checkexit['item_list'][0];
        $classdata = ['first_class'=>$checkexit['first_class'],'second_class'=>$checkexit['second_class'],'first_id'=>$checkexit['first_id'],'second_id'=>$checkexit['second_id']];
        if(!empty($checkexit['third_class']) && !empty($checkexit['third_id'])){
            $classdata['third_class'] = $checkexit['third_class'];
            $classdata['third_id'] = $checkexit['third_id'] ;
        }
        $response = $applet->submitAudit('首页',$checkexit['tag'],$classdata);
        if($response['errcode'] == 85009){
            return [ 'errcode'=>0, 'errmsg'=>'已更新体验二维码,请等待审核通过再更新线上版本' ,'version' => $infoTpl['version'] ];
        }
        if($response['errcode'] != 0){
            switch ($response['errcode']){
                case 85006 :  $msgwsl = '标签格式错误，请填写简单的关键词';break;
                case 85008 :  $msgwsl = '类目填写错误，请核对微信后台类目信息';break;
                case 85009 :  $msgwsl = '已经有正在审核的版本,请等待审核通过后再提交';break;
                case 85077 :  $msgwsl = '小程序类目信息失效（类目中含有官方下架的类目，请重新选择类目）';break;
                case 86002 :  $msgwsl = '小程序还未设置昵称、头像、简介。请在微信后台完善信息';break;
                default: $msgwsl = '意外错误，请联系客服';
            }
            return [ 'errcode'=>2, 'errmsg'=>$msgwsl,'response'=>$response ];
        }
        WeixinTemplate::insert_data([
            'merchant_id'=>$this->merchant_id,
            'appid'=>$info['appid'],
            'version_id'=>$version['id'],
            'version'=>$version['version'],
            'tag'=>$infoTpl['tag'],
            'category'=>$infoTpl['category'],
            'host'=> $infoTpl['host'],
            'host_date'=>$infoTpl['host_date'],
            'exit'=>$infoTpl['exit'],
            'exit_date' =>$_SERVER['REQUEST_TIME'],
            'check_exit'=>$response['data'],
            'check_date'=>$_SERVER['REQUEST_TIME'],
            'check'=>$response['auditid']
        ]);
        return [ 'errcode'=>0, 'errmsg'=>'更新成功'  ,'version' => $version['version']];
    }
    //刷新小程序码
    public function refreshQrcode(){
        $id = $this->request->input('id',0);
        $appid = $this->request->input('appid','');
        if(!empty($id)){
            $info = WeixinInfo::get_one('id',$id,1);
        }else if(!empty($appid)){
            $info = WeixinInfo::get_one_appid($this->merchant_id,$appid);
        }else{
            return [ 'errcode'=>1, 'errmsg'=>'param null' ];
        }
        if(!isset($info['id']) || empty($info['appid']) || $info['type'] != 1 || $info['merchant_id'] != $this->merchant_id){
            return [ 'errcode'=>1, 'errmsg'=>'unauthorized' ];
        }
        $infoTplx = WeixinTemplate::get_one_verify($this->merchant_id,$info['appid']);
        if(!isset($infoTplx['id'])){
            return [ 'errcode'=>2, 'errmsg'=>'体验二维码无需刷新' ];
        }
        $infoTplx['check_exit'] =  json_decode($infoTplx['check_exit'],true);
        $response = (new WeixinService()) ->qrcode($info['appid'],$infoTplx['check_exit']['item_list'][0]['address'].'?create='.date('YmdH'));
        if($response['errcode'] != 0){
            return [ 'errcode'=>4, 'errmsg'=>$response['errmsg'] ];
        }

        WeixinTemplate::update_data($infoTplx['id'],['qrcode'=>1 ]);
        WeixinInfo::update_data('id',$info['id'],['qrcode' =>$response['url'] ]);
        return ['errcode'=>0,'errmsg'=>$response['errmsg'], 'qrcode' =>$this->qn_host.$response['url'] ];
    }
    //体验二维码
    public function experience(){
        $id = $this->request->input('id',0);
        $appid = $this->request->get('appid','');
        if(!empty($id)){
            $info = WeixinInfo::get_one('id',$id,1);
        }else if(!empty($appid)){
            $info = WeixinInfo::get_one_appid($this->merchant_id,$appid);
        }else{
            return [ 'errcode'=>1, 'errmsg'=>'param null' ];
        }
        if(!isset($info['id']) || empty($info['appid']) || $info['type'] != 1 || $info['merchant_id'] != $this->merchant_id){
            return [ 'errcode'=>1, 'errmsg'=>'unauthorized' ];
        }
        $applet = (new Applet())->setAccessToken((new WeixinService())->getAccessToken($info['appid'])) ;
        $response = $applet->getQrcode();
        ob_clean();
        header('Content-Type: image/jpeg');
        echo $response;
        exit();
    }
    //动态参数小程序码
    public function dynamicQrcode(){
        $id = $this->request->get('id',0);
        $appid = $this->request->get('appid','');
        $key  = $this->request->get('key','');
        if(!empty($id)){
            $info = WeixinInfo::query()->where(['id'=>$id,'status'=>1,'type'=>1])->first();
        }else if(!empty($appid)){
            $info = WeixinInfo::query()->where(['merchant_id'=>$this->merchant_id,'appid'=>$appid,'status'=>1,'type'=>1])->first();
        }else{
            return [ 'errcode'=>1, 'errmsg'=>'param null' ];
        }
        if(!isset($info['id']) || empty($info['appid']) || $info['type'] != 1 || $info['merchant_id'] != $this->merchant_id){
            return [ 'errcode'=>1, 'errmsg'=>'unauthorized' ];
        }
        $WeixinService = new WeixinService();
        $version = $WeixinService->getVersion($info['tpl_type'],$this->merchant_id);
        $applet = new Applet();
        $applet -> setAccessToken($WeixinService->getAccessToken($info['appid']));
        $response = $applet->getCodeLimitQrcode($version['index'],$key);
        ob_clean();
        header('Content-Type: image/jpeg');
        echo $response;
        exit();
    }
    //推广小程序码
    public function spreadQrcode(){
        $id = $this->request->get('id',0);
        $appid = $this->request->get('appid','');
        $sid  = $this->request->get('sid',0);
        $type  = $this->request->get('type',1);// 1商品 2 优惠券 3 拼团 4秒杀
        if(!in_array($type,[1,2,3,4]) || $sid < 0 || ( $id < 0 && empty($appid)) ){
            return [ 'errcode'=>1, 'errmsg'=>'param null' ];
        }
        if(!empty($id)){
            $info = WeixinInfo::query()->where(['id'=>$id,'status'=>1,'type'=>1])->first();
        }else if(!empty($appid)){
            $info = WeixinInfo::query()->where(['merchant_id'=>$this->merchant_id,'appid'=>$appid,'status'=>1,'type'=>1])->first();
        }else{
            return [ 'errcode'=>1, 'errmsg'=>'param null' ];
        }
        if(!isset($info['id']) || empty($info['appid']) || $info['type'] != 1 || $info['merchant_id'] != $this->merchant_id){
            return [ 'errcode'=>1, 'errmsg'=>'unauthorized' ];
        }
        $check = WeixinQrcode::check($sid,$type,$info['appid']);
        if(isset($check['id'])){
            return [ 'errcode'=>0, 'errmsg'=>'小程序码生成成功','url'=>$this->qn_host.$check['url'] ];
        }

        if($type == 1){
            $source  = Goods::get_data_by_id($sid,$this->merchant_id);
            if(!isset($source['id'])){
                return [ 'errcode'=>1, 'errmsg'=>'param error' ];
            }
            $path = 'pages/goods/detail/detail?id='.$source['id'];
            $name =  $source['title'];
        }else if($type == 2){
            $source = Coupon::get_data_by_id($sid,$this->merchant_id);
            if(!isset($source['id'])){
                return [ 'errcode'=>1, 'errmsg'=>'param error' ];
            }
            $path = 'pages/card/cardinfo/cardinfo?id='.$source['id'].'&share_status=1';
            $name =  $source['name'];
        }else if($type == 3){
            $source = Fightgroup::get_data_by_id($sid,$this->merchant_id);
            if(!isset($source['id'])){
                return [ 'errcode'=>1, 'errmsg'=>'param error' ];
            }
            $path = 'pages/goods/detail/detail?id='.$source['goods_id'];
            $name =  $source['title'];
        } else if($type == 4){
            $source = Seckill::get_data_by_id($sid,$this->merchant_id);
            if(!isset($source['id'])){
                return [ 'errcode'=>1, 'errmsg'=>'param error' ];
            }
            $path = 'pages/goods/detail/detail?id='.$source['goods_id'];
            $name =  $source['price_title'];
        }

        $weixinService =  new WeixinService();
        $response = $weixinService ->qrcode($info['appid'],$path);
        if($response['errcode'] != 0){
            return [ 'errcode'=>4, 'errmsg'=>$response['errmsg'],'data'=>isset($response['data'])?$response['data']:'' ];
        }
        WeixinQrcode::insert_data(['merchant_id'=>$this->merchant_id,'appid'=>$info['appid'],'type'=>$type,'sid'=>$sid,'url'=>$response['url'],'path'=>$path,'name'=>$name]);
        return [ 'errcode'=>0, 'errmsg'=>'小程序码生成成功','url'=>$this->qn_host.$response['url'] ];
    }
    //消息模板id自动生成
    public function autoTplId(){
        $id = $this->request->input('id',0);
        $appid = $this->request->input('appid','');
        $type   = $this->request->input('type',0);
        $action = $this->request->input('action',2);
        if(!empty($id)){
            $info = WeixinInfo::query()->where(['id'=>$id,'status'=>1,'type'=>1])->first();
        }else if(!empty($appid)){
            $info = WeixinInfo::query()->where(['merchant_id'=>$this->merchant_id,'appid'=>$appid,'status'=>1,'type'=>1])->first();
        }else{
            return [ 'errcode'=>1, 'errmsg'=>'param null' ];
        }
        if(!isset($info['id']) || empty($info['appid']) || $info['type'] != 1 || $info['merchant_id'] != $this->merchant_id){
            return [ 'errcode'=>1, 'errmsg'=>'unauthorized' ];
        }
        $weixinMsgService = new WeixinMsgService();
        if(!isset($weixinMsgService->tpldata[$type]['type']) || $weixinMsgService->tpldata[$type]['type'] != 1) {
            return [ 'errcode'=>1, 'errmsg'=>'type error ' ];
        }
        $weixinService = new WeixinService();
        $msgTemplate = new MsgTemplate();
        $msgTemplate->setAccessToken($weixinService->getAccessToken($info['appid']));
        //删除
        if($action == 2){
            $msgTplInfo = WeixinMsgTemplate::get_one($this->merchant_id,$info['appid'],$type);
            if(!isset($msgTplInfo['template_id']) || empty($msgTplInfo['template_id'])){
                return [ 'errcode'=>0, 'errmsg'=>'ok','template_id'=>''];
            }
            $response = $msgTemplate->delTemplate($msgTplInfo['template_id']);
            WeixinMsgTemplate::update_data('id',$msgTplInfo['id'],['template_id'=>'']);
            if($response['errcode'] != 0){
                return [ 'errcode'=>2, 'errmsg'=>'删除异常' ];
            }
            return [ 'errcode'=>0, 'errmsg'=>'ok','template_id'=>''];
        }
        //添加 更新
        $tpldata = $weixinMsgService->tpldata[$type];
        $response = $msgTemplate->getTemplate($tpldata['id']);
        if($response['errcode'] != 0 || !isset($response['keyword_list'])){
            return [ 'errcode'=>4, 'errmsg'=>$response['errcode'].':'.$response['errmsg'] ];
        }
        foreach ($tpldata['data'] as $k => $v) {
            $keylistx = false;
            foreach ($response['keyword_list'] as $ks => $vs) {
                if($vs['name'] == $v){
                    $resultlist[] = $vs['keyword_id'];
                    $keylistx = true;
                }
            }
            if($keylistx == false){
                break;
            }
        }
        if(count($resultlist) < count($tpldata['data'])){
            return [ 'errcode'=>2, 'errmsg'=>'search error','data' =>$resultlist ];
        }
        $response = $msgTemplate->addTemplate($tpldata['id'],$resultlist);
        if($response['errcode'] != 0){
            if($response['errcode'] == 45026){
                return [ 'errcode'=>3, 'errmsg'=>'模板数量超出限制，请登录微信后台在消息模板中删除不使用消息模板' ];
            }
            return [ 'errcode'=>3, 'errmsg'=>'post error,'.$response['errcode'].':'.$response['errmsg'] ];
        }
        $msgTplInfo = WeixinMsgTemplate::get_one($this->merchant_id,$info['appid'],$type);
        if(isset($msgTplInfo['id']) && $msgTplInfo['id'] > 0){
            if(!empty($msgTplInfo['template_id'])){
                $msgTemplate->delTemplate($msgTplInfo['template_id']);
            }
            WeixinMsgTemplate::update_data('id',$msgTplInfo['id'],['template_id'=>$response['template_id']]);
        }else{
            WeixinMsgTemplate::insert_data(['merchant_id'=>$this->merchant_id,'appid'=>$info['appid'],'template_id'=>$response['template_id'],'template_type'=>$type]);
        }
        return [ 'errcode'=>0, 'errmsg'=>'ok','template_id'=>$response['template_id']];

    }
    
    //小程序 支付方式 开关设置
    public function paysettingSwitch(Request $request){
        //小程序id
        $info_id = $this->request->input('id',0);
        if(!empty($info_id)){
            $info = WeixinInfo::query()->where(['id'=>$info_id,'status'=>1,'type'=>1])->first();
        }else{
            return [ 'errcode'=>1, 'errmsg'=>'param null' ];
        }
        if(!isset($info['id']) || empty($info['appid']) || $info['type'] != 1 || $info['merchant_id'] != $this->merchant_id){
            return [ 'errcode'=>1, 'errmsg'=>'unauthorized' ];
        }
        //支付类型
        $pay_type   = $this->request->input('pay_type',0);
        if(empty($pay_type) || !in_array($pay_type, array(1,2))){
            return [ 'errcode'=>1, 'errmsg'=>'pay_type null' ];
        }
        //开关状态
        $action = $this->request->input('action',0);
        if(empty($action) || !in_array($action, array(1,2))){
            return [ 'errcode'=>1, 'errmsg'=>'action null' ];
        }
        
        $data = array();
        //查询是否已经有配置记录
        $slt_weixinsetting = WeixinSetting::get_data_by_id($info_id, $this->merchant_id);
        if(empty($slt_weixinsetting)){
            $data['merchant_id'] = $this->merchant_id;
            $data['info_id'] = $info_id;
            if($pay_type==1){
                $data['weixin_onoff'] = ($action==1)?1:0;
                $data['delivery_onoff'] = 0;
            }else{
                $data['weixin_onoff'] = 0;
                $data['delivery_onoff'] = ($action==1)?1:0;
            }
            $rs_weixinsetting = WeixinSetting::insert_data($data);
        }else{
            if($pay_type==1){
                $data['weixin_onoff'] = ($action==1)?1:0;
            }else{
                $data['delivery_onoff'] = ($action==1)?1:0;
            }
            $rs_weixinsetting = WeixinSetting::update_data($info_id, $this->merchant_id, $data);
        }
        //返回
        if($rs_weixinsetting){
            $rt = [ 'errcode'=>0, 'errmsg'=>'设置成功'];
        }else{
            $rt = [ 'errcode'=>2, 'errmsg'=>'update error'];
        }
        return Response::json($rt);
    }
    
    //小程序 支付方式 开关设置：初始化数据
    public function paysettingInit(){
        //小程序id
        $arr_info = WeixinInfo::where(['status'=>1,'type'=>1])->chunk(100, function($list){
                    if($list) {
                        $dump = [];
                        foreach($list as $row){
                            $slt_weixinsetting = WeixinSetting::get_data_by_id($row['id'], $row['merchant_id']);
                            if(empty($slt_weixinsetting)){
                                $data = array();
                                $data['merchant_id'] = $row['merchant_id'];
                                $data['info_id'] = $row['id'];
                                $data['weixin_onoff'] = 1;
                                $data['delivery_onoff'] = 0;
                            
                                WeixinSetting::insert_data($data);
                            }
                        }
                    }
        });
        
        $rt = [ 'errcode'=>0, 'errmsg'=>'ok'];
        return Response::json($rt);
    }
    
    //清除主体
    public function openDelete(){
        return ['errcode'=>1,'errmsg'=>'没有权限操作'];

        return (new WeixinService())->delOpen($this->merchant_id);
    }

}

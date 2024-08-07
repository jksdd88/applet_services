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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\WeixinExperiencer;
use App\Models\WeixinInfo;
use App\Models\WeixinTemplate;
use App\Services\WeixinService;
use App\Utils\Weixin\Applet;
use App\Utils\Encrypt;
use Config;

class AuthbackController extends Controller
{
    private $request;
    private $merchant_id;
    private $is_admin ;

    private $component_appid;
    private $component_id;
    private $host;
    private $qn_host;

    private $func_count = [ 1 => 6 , 2 => 18 ];

    public function __construct(Request $request)
    {
        $this->request = $request;
        $this->merchant_id = Auth::user()->merchant_id ;
        $this->is_admin    =  Auth::user()->is_admin;

        $this->component_appid =  Config::get('weixin.component_appid');
        $this->component_id =  Config::get('weixin.component_id');
        $this->host = Config::get('weixin.base_host');
        $this->qn_host = Config::get('weixin.qn_host');
        $this->func_count[1] = Config::get('weixin.wechat_auth_count');
        $this->func_count[2] = Config::get('weixin.wechat_auths_count');
    }

    public function index(){

    }

    //获取 authorizer_refresh_token x
    private function getRefreshToken($code){
        $token = $this->getToken();
        $curl = new Client($this->curlset);
        $respons = $curl->request('POST' , $this->gateway.'api_query_auth?component_access_token='.$token , ['json'=> [ 'component_appid' => $this->component_appid , 'authorization_code' => $code ] ]);
        $respons = (string)$respons->getBody();
        $respons = json_decode($respons,true);
        $respons = $respons['authorization_info'];
        if(empty($respons['authorizer_appid']) || empty($respons['authorizer_refresh_token'])){
            return [ 'errcode'=>1, 'errmsg'=>'privilege grant failed', 'msg_wsl'=>'授权失败' ];
        }
        $authlist = [];
        foreach ($respons['func_info'] as $k => $v) {
            $authlist[] = $v['funcscope_category']['id'];
        }
        $authlist = implode(',',$authlist);

        return [ 'errcode'=>0, 'errmsg'=>'ok', 'data'=>['appid'=>$respons['authorizer_appid'],'token'=>$respons['authorizer_refresh_token'] , 'authlist'=>$authlist] ];
    }
    //==============================推送魔板==============================
    //获取小程序页面列表
    public function getPage(){
        $id = $this->request->get('id',0);
        $appid = $this->request->get('appid','');
        if(!empty($id)){
            $info = WeixinInfo::get_one('id',$id,1);
        }else if(!empty($appid)){
            $info = WeixinInfo::get_one_appid($this->merchant_id,$appid);
        }else{
            return [ 'errcode'=>1, 'errmsg'=>'param null' ];
        }
        if(!isset($info['id']) || empty($info['appid']) || $info['type'] != 1 ||  $info['merchant_id'] != $this->merchant_id){
            return [ 'errcode'=>1, 'errmsg'=>'unauthorized' ];
        }
        $applet = (new Applet())->setAccessToken((new WeixinService())->getAccessToken($info['appid'])) ;
        return $applet -> getPage();// errcode   errmsg   page_list
    }
    //保存审核信息
    public function verifySave(){
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
        $tag =  $string =  preg_replace ("/\s(?=\s)/","\\1", $this->request->input('tag') ); ;
        $data['first_class']= $this->request->input('first_class');
        $data['second_class']= $this->request->input('second_class');
        $data['third_class']= $this->request->input('third_class');
        if(empty($tag) || empty($data['first_class']) || empty($data['second_class'])){
            return ['errcode'=>1,'errmsg'=>'value null'];
        }
        if(empty($data['third_class'])){  unset($data['third_class']);  }

        $tplinfo = WeixinTemplate::get_one_ver($this->merchant_id,$info['appid']);
        if(empty($tplinfo['id'])){
            return ['errcode'=>3,'errmsg'=>'tpl  null'];
        }
        $category = $data['first_class'].'#'.$data['second_class'].(isset($data['third_class'])? '#'.$data['third_class']:'' );
        if(empty($tplinfo['check'])){
            WeixinTemplate::update_data($tplinfo['id'],['tag'=>$tag,'category'=>$category]);
            return ['errcode'=>0,'errmsg'=>'提交成功'];
        }

        $WeixinService  = new WeixinService();
        $applet = (new Applet())->setAccessToken($WeixinService->getAccessToken($info['appid'])) ;
        $version = $WeixinService->getVersion();
        if($version['id'] > $tplinfo['version_id']){
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
        $tplid = WeixinTemplate::insert_data([
            'merchant_id'=>$this->merchant_id,
            'appid'=>$info['appid'],
            'version_id'=>$version['id'],
            'version'=>$version['version'],
            'tag'=>$tag,
            'category'=>$category,
            'host'=> $tplinfo['host'],
            'host_date'=>$tplinfo['host_date'],
            'exit'=>$tplinfo['exit'],
            'exit_date' =>$tplinfo['exit_date']
        ]);
        if($tplid){
            return ['errcode'=>0,'errmsg'=>'ok'];
        }else{
            return ['errcode'=>2,'errmsg'=>'sys error'];
        }
    }
    //提交保存审核信息
    public function verifySubmit(){
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
        $tplinfo = WeixinTemplate::get_one_ver($this->merchant_id,$info['appid']);
        if(empty($tplinfo['id'])){
            return ['errcode'=>3,'errmsg'=>'tpl  null'];
        }
        if(!empty($tplinfo['check'])){
            return ['errcode'=>0,'errmsg'=>'ok'];
        }
        $checkexit = [];
        $category = explode('#',$tplinfo['category']);
        $WeixinService = new WeixinService();
        $applet = (new Applet())->setAccessToken($WeixinService->getAccessToken($info['appid'])) ;
        $response = $applet->getCategory();
        foreach ($response['category_list'] as $k => $v) {
            if($v['first_class'] == $category[0] && $v['second_class'] == $category[1] && (!isset($category[2]) ||  (isset($category[2]) && $v['third_class'] == $category[2]) )){
                $checkexit = $v;
                break;
            }
        }
        if(empty($checkexit)){
            return ['errcode'=>4,'errmsg'=>'类目保存有误','category'=>$response['category_list']];
        }
        $classdata = ['first_class'=>$checkexit['first_class'],'second_class'=>$checkexit['second_class'],'first_id'=>$checkexit['first_id'],'second_id'=>$checkexit['second_id']];
        if(!empty($checkexit['third_class']) && !empty($checkexit['third_id'])){
            $classdata['third_class'] = $checkexit['third_class'];
            $classdata['third_id'] = $checkexit['third_id'] ;
        }
        $applet->setVersion($WeixinService->getVersion());
        $response = $applet->submitAudit('首页',$tplinfo['tag'],$classdata);
        if($response['errcode'] != 0 &&  $response['errcode'] != 85009){
            return [ 'errcode'=>2, 'errmsg'=>'submit_audit error','response'=>$response ];
        }
        if($response['errcode'] == 0){
            WeixinTemplate::update_data($tplinfo['id'],['check_exit'=>$response['data'],'check_date'=>$_SERVER['REQUEST_TIME'],'check'=>$response['auditid']]);
            return [ 'errcode'=>0, 'errmsg'=>'ok' ];
        }
        if ($response['errcode'] == 85009){
            $audit = $applet->getLatestAuditStatus();
            if(!isset($audit['auditid']) || empty($audit['auditid'])){
                return [ 'errcode'=>3, 'errmsg'=>'auditid error','response'=>$audit ];
            }
            WeixinTemplate::update_data($tplinfo['id'],['check_exit'=>$response['data'],'check_date'=>$_SERVER['REQUEST_TIME'],'check'=>$audit['auditid']]);
            return [ 'errcode'=>0, 'errmsg'=>'ok'];
        }
    }
    //获取审核结果
    public function getVerify(){
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
        $tplinfo = WeixinTemplate::get_one_ver($this->merchant_id,$info['appid']);
        if(empty($tplinfo['id'])){
            return [ 'errcode'=>1, 'errmsg'=>'Authorization failure [tpl]' ];
        }
        if(empty($tplinfo['check'])){
            return [ 'errcode'=>2, 'errmsg'=>'set null' ];
        }
        if($tplinfo['verify'] == 2){
            return [ 'errcode'=>0, 'errmsg'=>'ok' , 'error'=> $tplinfo['check_error'] , 'status' => 2  ];
        }
        if($tplinfo['verify'] == 1){
            return [ 'errcode'=>0, 'errmsg'=>'ok' , 'status' => 0 , 'qrcode' =>$this->qn_host.$info['qrcode'] ];
        }
        //检查审核情况
        $applet = (new Applet())->setAccessToken((new WeixinService())->getAccessToken($info['appid'])) ;
        if(empty($tplinfo['pass_date'])){
            $response =  $applet->getAuditStatus($tplinfo['check']);
            if($response['errcode'] != 0){
                return [ 'errcode'=>4, 'errmsg'=>'auditstatus null' ];
            }
            if($response['status'] == 0){
                WeixinTemplate::update_data($tplinfo['id'],['pass_date'=>$_SERVER['REQUEST_TIME'] ]);
            }else if($response['status'] == 1){
                WeixinTemplate::update_data($tplinfo['id'],['verify'=>2, 'check_error'=> $response['reason'], 'pass_date'=>$_SERVER['REQUEST_TIME'] ]);
                return [ 'errcode'=>0, 'errmsg'=>'audit error' , 'status' => 2 , 'error'=> $response['reason']  ];
            }else{
                return [ 'errcode'=>0, 'errmsg'=>'In audit' , 'status' => 1];
            }
        }
        //审核通过后发布
        $response = $applet->release();
        if($response['errcode'] == 85019 || $response['errcode'] == 85020){
            $response = $applet -> getLatestAuditStatus();
            if($response['errcode'] != 0){
                return [ 'errcode'=>6, 'errmsg'=>'release error'];
            }
            $verify = $response['status'] == 2 ? 0 : ($response['status']+1);
            $check_error = isset($response['reason']) && !empty($response['reason']) ? $response['reason'] : '';
            if($verify == 0 ){
                WeixinTemplate::update_data($tplinfo['id'],['verify'=>0,'check'=>$response['auditid'],'release'=>0,'pass_date'=>0 ]);
                return [ 'errcode'=>0, 'errmsg'=>'In audit' , 'status' => 1];
            }
            WeixinTemplate::update_data($tplinfo['id'],['verify'=>$verify,'check'=>$response['auditid'],'check_error'=>$check_error,'release'=>1,'pass_date'=>$_SERVER['REQUEST_TIME'] ]);
            if($verify == 1){
                return [ 'errcode'=>0, 'errmsg'=>'In audit' , 'status' => 1];
            }else{
                return [ 'errcode'=>0, 'errmsg'=>'audit error' , 'status' => 2 , 'error'=> $response['reason']  ];
            }
        }

        if($response['errcode'] != 0 && $response['errcode'] != 85052){
            return [ 'errcode'=>6, 'errmsg'=>'release error'];
        }
        WeixinTemplate::update_data($tplinfo['id'],['verify'=>1,'release'=>1,'is_sync'=>1,'release_date'=>$_SERVER['REQUEST_TIME'] ]);
        //发布后生成二维码
        $check_exit =  json_decode($tplinfo['check_exit'],true);
        $response = $applet-> getCodeQrcode($check_exit['item_list'][0]['address']);
        $cacheres = json_decode($response);
        if(!empty($cacheres) || empty($response)){
            return [ 'errcode'=>0, 'errmsg'=>'ok' , 'status' => 0, 'qrcode' => '' ];
        }
        $response = (new WeixinService())->uploadQiniu($response,$info['appid'],$tplinfo['version_id']);
        if($response['errcode'] !== 0){
            return [ 'errcode'=>0, 'errmsg'=>'ok' , 'status' => 0, 'qrcode' => '' ];
        }
        WeixinTemplate::update_data($tplinfo['id'],['qrcode'=>1 ]);
        WeixinInfo::update_data('id',$info['id'],['qrcode' => $response['url'] ]);

        return [ 'errcode'=>0, 'errmsg'=>'ok' , 'status' => 0, 'qrcode' =>$this->qn_host.$info['qrcode']];
    }
    //添加体验账号
    public function addExperiencer(){
        $id = $this->request->input('id',0);
        $appid = $this->request->input('appid','');
        $action =  $this->request->input('action',2);
        $account = $this->request->input('account','');

        $account = explode(',',$account);
        $action  =  $action == 2 ? 'bind' : 'unbind';

        if(empty($account)){
            return [ 'errcode'=>1, 'errmsg'=>'param null' ];
        }
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
        $applet = new Applet();
        $applet -> setAccessToken((new WeixinService())->getAccessToken($info['appid']));
        $return = [];
        foreach ($account as $k => $v) {
            $response = $applet -> bindTester($v,$action);
            if($response['errcode'] == 0 || $response['errcode'] == 85004){
                if($action == 'bind'){
                    WeixinExperiencer::insert_data($this->merchant_id,$info['appid'],$v);
                }else{
                    WeixinExperiencer::delete_data($this->merchant_id,$info['appid'],$v);
                }
                $return[$v] = 'ok';
            }else if($response['errcode'] == 85001){
                $return[$v] = '微信号不存在或微信号设置为不可搜索';
            }else if($response['errcode'] == 85002){
                $return[$v] = '小程序绑定的体验者数量达到上限';
            }else if($response['errcode'] == 85003){
                $return[$v] = '微信号绑定的小程序体验者达到上限';
            }else{
                $return[$v] = '意外错误：'.$response['errcode'];
            }
        }
        return [ 'errcode'=>0, 'errmsg'=>'ok' ,'data' => $return ];
    }
    //查看体验者
    public function getExperiencer(){
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
        $data = WeixinExperiencer::list_data($this->merchant_id,$info['appid']);
        return [ 'errcode'=>0, 'errmsg'=>'ok','data' => $data ];
    }
    //清除主体
    public function openDelete(){
        return ['errcode'=>1,'errmsg'=>'没有权限操作'];

        return (new WeixinService())->delOpen($this->merchant_id);
    }

    public function qrcode($appid,$path,$applet = null){
        /*
        //参数验证
        if(empty($appid) || empty($path)){
            return [ 'errcode'=>1, 'errmsg'=>'意外出错请重试','data'=>'params null' ];
        }
        $key = md5($appid.'_'.$path);
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

        $response = $applet->getCodeQrcode($path);
        if(!is_string($response)){
            return [ 'errcode'=>1, 'errmsg'=>'意外出错请重试','data'=>$response ];
        }
        $jfif = strpos($response,'JFIF');
        if($jfif != 6 ){
            return [ 'errcode'=>1, 'errmsg'=>'意外出错请重试','data'=>$response ];
        }

        if(isset($check['id'])){
            $id = $check['id'];
        }else{
            $id = WeixinQrcodeLog::insert_data(['appid'=>$appid,'type'=>1,'key'=>$key,'path'=>$path]);
        }
        $response = $this->uploadQiniu($response,$appid,$_SERVER['REQUEST_TIME']);
        if($response['errcode'] != 0){
            WeixinQrcodeLog::update_data([ 'id' => $id ], [ 'is_delete'=>-1 ]);
            return [ 'errcode'=>2, 'errmsg'=>$response['errmsg'] ];
        }
        WeixinQrcodeLog::update_data([ 'id' => $id ], [ 'url'=>$response['url'] , 'is_delete' => 1 ]);
        return ['errcode' => 0, 'errmsg' => 'ok','url'=>$response['url']];
        */
    }

}

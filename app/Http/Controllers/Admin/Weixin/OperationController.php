<?php

namespace App\Http\Controllers\Admin\Weixin;

use App\Http\Controllers\Controller;
use App\Models\OrderGoods;
use App\Models\OrderInfo;
use App\Models\OrderRefundApply;
use App\Models\User;
use App\Models\WeixinInfo;
use App\Models\WeixinLog;
use App\Models\WeixinOpen;
use App\Models\WeixinSetting;
use App\Models\OrderPackage;
use App\Services\LiveService;
use App\Utils\Weixin\AppletInfo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use App\Models\WeixinTemplate;
use App\Models\WeixinVersion;
use App\Utils\Weixin\AppletVersion;
use App\Utils\Weixin\Component;
use App\Utils\Weixin\OfficialUser;
use App\Utils\Weixin\Applet;
use App\Utils\Weixin\Statics;
use App\Services\WeixinMsgOfficialService;
use App\Services\WeixinMsgService;
use App\Services\WeixinService;
use App\Utils\CacheKey;
use Cache;
use Config;
use Illuminate\Foundation\Bus\DispatchesJobs;


class OperationController extends Controller
{
    use DispatchesJobs;

    private $request ;
    private $component_id;
    private $component_appid;

    public function __construct(Request $request)
    {
        $this->request = $request;
        if($this->request->get('user') != 'root'){
            return [ 'errcode'=>1, 'errmsg'=>'user error' ];
        }
        $this->component_id =  Config::get('weixin.component_id');
        $this->component_appid = Config::get('weixin.component_appid');
    }

    public function index(){
        $password = $this->request->get('password','');
        if($password != '6635e77dfdf93kdd98gbcb563cc6edhi'){
            return [ 'errcode'=>2, 'errmsg'=>'user error' ];
        }
        $action = $this->request->get('action','');

        if($action == 'WeixinSetting'){
            $id = $this->request->get('id',0);
            if($id){
                $response = WeixinSetting::where(['id'=>$id])->delete();
            }
            return ['response'=>$response];
        }elseif($action == 'Artisan'){

            $rnun = $this->request->get('run','');
            $response = Artisan::call('command:'.$rnun);
            return $response;
        }elseif($action == 'qrcode'){
            $appid = $this->request->get('appid','');
            if(!empty($appid)){
                $weixinService = new WeixinService();
                $response = $weixinService->qrcodeAll($appid,1,'pages/decorate/decorate?ver='.date('YmdHi'),null,[]);
                return $response ;
            }
        }elseif($action == 'ordererror'){
            $weixinService = new WeixinService();
            $staticHttp = new Statics();
            $staticHttp->setAccessToken($weixinService->getAccessToken('wx625ea949e42b4d52'));
            $response = $staticHttp->getweanalysisappiddailyvisittrend( ['begin_date' => '20180815', 'end_date' => '20180815']);
            return $response;
        }
        return 'hi';
    }
    //删除主体
    public function openDelete(){
        if($this->request->get('password') != '6635e77df36958a0d3abcb563cc6edc5'){
            return [ 'errcode'=>2, 'errmsg'=>'user error' ];
        }
        $merchant_id = (int)$this->request->get('merchant_id',0);
        if($merchant_id < 1){
            return [ 'errcode'=>3, 'errmsg'=>'merchant_id error' ];
        }
        return (new WeixinService())->delOpen($merchant_id);
    }
    //版本同步
    public function appletVersion(){
        if($this->request->get('password') != '492wdc07fa8a2d1a0a18a8a67c644322'){
            return [ 'errcode'=>2, 'errmsg'=>'user error' ];
        }
        $weixinService = new WeixinService();
        $componentToken = $weixinService->getComponentToken();
        $appletver = new AppletVersion($componentToken);
        //草稿箱列表
        $response = $appletver->getTemplateDraftList();
        if(!isset($response['errcode']) || $response['errcode'] != 0 || empty($response['draft_list'])){
            return $response;
        }
        $draft = ['create_time'=>0] ;
        foreach ($response['draft_list'] as $k => $v) {
            if($v['create_time'] > $draft['create_time']){
                $draft = $v;
            }
        }
        if(!isset($draft['user_version'])){
            return ['errcode' => 1 , 'errmsg'=>'getTemplateDraftList error'];
        }
        //最新已同步
        $checkinfo = WeixinVersion::check_version($draft['create_time'],$this->component_id);
        if(isset($checkinfo['id'])){
            $info = WeixinVersion::get_version($this->component_id,'',0);
            return ['errcode' => 0 , 'errmsg'=>'not new draft error','data'=>$info];
        }
        //版本类型验证
        $listtype = Config::get('weixin.template_type');
        $type  = substr($draft['user_version'],0,1);
        if(!in_array($type,$listtype)){
            return ['errcode' => 1 , 'errmsg'=>'type error'];
        }
        //草稿箱入库
        $id = WeixinVersion::insert_data(['ticket_id'=>$this->component_id,'version'=>$draft['user_version'],'type'=>$type,'desc'=>$draft['user_desc'],'time'=>$draft['create_time']]);
        $response = $appletver->addToTemplate($draft['draft_id']);
        if(!isset($response['errcode']) || $response['errcode'] != 0){
            WeixinVersion::update_data('id',$id,['status'=>-1]);
            return ['errcode' => 2 , 'errmsg'=>'addToTemplate error','response'=>$response];
        }
        //已入库列表
        $response = $appletver->getTemplateList();
        if(!isset($response['errcode']) || $response['errcode'] != 0){
            return ['errcode' => 3 , 'errmsg'=>'getTemplateList error'];
        }
        //草稿箱入库验证是否上线
        $template = ['template_id' => 0];
        foreach ($response['template_list'] as $k => $v) {
            if($v['template_id'] > $template['template_id']){
                $template = $v;
            }
        }
        if($template['template_id'] ==0 || $template['user_version'] != $draft['user_version']  || $template['user_desc'] != $draft['user_desc']){
            WeixinVersion::update_data('id',$id,['status'=>-1]);
            return ['errcode' => 4 , 'errmsg'=>'getTemplateList error'];
        }
        WeixinVersion::update_data('id',$id,['tmplate_id'=>$template['template_id']]);
        //删除最老 保证库存不溢出
        $endinfo = $weixinService->getVersion($type);
        if(isset($endinfo['number'])){
            foreach ($response['template_list'] as $k => $v ) {
                $cacheType = substr($v['user_version'],0,1);
                if($cacheType  == $type && $endinfo['number'] > $v['template_id']){
                    $responseDel = $appletver->deleteTemplate($v['template_id']);
                    if(isset($responseDel['errcode']) && $responseDel['errcode'] == 0){
                        WeixinVersion::update_data('tmplate_id',$v['template_id'],['state'=>2]);
                    }
                    break;
                }
            }
        }
        $info = WeixinVersion::get_version($this->component_id,'',0);
        return ['errcode' => 0 , 'errmsg'=>'ok','data'=>$info];
    }
    //版本上线
    public function onlineVersion(){
        if($this->request->get('password') != '492wdc07fa8a2d1a0a18a8a67c644322'){
            return [ 'errcode'=>2, 'errmsg'=>'user error' ];
        }
        $id = $this->request->get('id',0);
        $info = WeixinVersion::get_one('id',$id);
        if(!isset($info['id'])){
            return ['errcode' => 1 , 'errmsg'=>'id error'];
        }
        WeixinVersion::update_data('id',$info['id'],['state'=>1]);
        return ['errcode' => 0 , 'errmsg'=>'ok'];
    }
    //版本列表
    public function versionList(){
        if($this->request->get('password') != '492wdc07fa8a2d1a0a18a82wd8tf6aq2'){
            return [ 'errcode'=>2, 'errmsg'=>'user error' ];
        }
        $list = WeixinVersion::query()->where(['status'=>1])-> orderBy('id', 'DESC')->skip(0)->take(100)->get()->toArray();
        $data = [];
        foreach ($list as $k => $v) {
            $data[] = [
                'id'         => $v['id'],
                'tmplate_id' => $v['tmplate_id'],
                'version'    => $v['version'],
                'type'       => $v['type'],
                'state'      => $v['state'],
                'desc'       => $v['desc'],
                'time'       => $v['created_time']
            ];
        }
        return ['errcode' => 0 , 'errmsg'=>'ok','data'=>$data];
    }
    //版本回撤
    public function versionBack(){
        if($this->request->get('password') != '492wdc07fa8a2d1a0a18a82wd8tfback'){
            return [ 'errcode'=>2, 'errmsg'=>'user error' ];
        }
        $verid = $this->request->get('verid',0);
        $verbase = $this->request->get('verbase',0);
        if($verid == 0 || $verbase == 0){
            return [ 'errcode'=>2, 'errmsg'=>'param null' ];
        }
        $checkinfo = WeixinVersion::get_one('time',$verid);
        if(!isset($checkinfo['id'])){
            return [ 'errcode'=>3, 'errmsg'=>'param error' ];
        }
        $checkinfo = WeixinVersion::get_one('time',$verbase);
        if(!isset($checkinfo['id'])){
            return [ 'errcode'=>3, 'errmsg'=>'param error' ];
        }

        ( new WeixinService() ) -> versionBack($verid, $verbase);

        return [ 'errcode'=>0, 'errmsg'=>'ok'];

    }
    //审核回撤
    public function verifyBack(){
        if($this->request->get('password') != '492wdc07fa8a2d1a0a18a82wd8tfback'){
            return [ 'errcode'=>2, 'errmsg'=>'user error' ];
        }
        $verid = $this->request->get('verid',0);
        if($verid == 0){
            return [ 'errcode'=>2, 'errmsg'=>'param null' ];
        }
        $checkinfo = WeixinVersion::get_one('time',$verid);
        if(!isset($checkinfo['id'])){
            return [ 'errcode'=>2, 'errmsg'=>'param error' ];
        }
        ( new WeixinService() ) -> verifyBack($verid);
        return [ 'errcode'=>0, 'errmsg'=>'ok' ];
    }
    //升级
    public function upgrade(){
        if($this->request->get('password') != '493ceb07fa8a2d1a0a18a8a67c644311'){
            return [ 'errcode'=>2, 'errmsg'=>'user error' ];
        }
        $weixinService = new WeixinService();
        $weixinService->versionUpdate();
        return ['errcode'=>0,'errmsg'=>''];
    }
    //验证升级
    public function verify(){
        if($this->request->get('password') != '391fff20b7806d9828a7cbc145d2a5b7'){
            return [ 'errcode'=>2, 'errmsg'=>'user error' ];
        }
        $weixinService = new WeixinService();
        $weixinService->checkVerify();
        return ['errcode'=>0,'errmsg'=>''];
    }
    //删除 formid
    public function formidDelete(){
        if($this->request->get('password') != '8510d744628415270900c8b820d9d2a5'){
            return [ 'errcode'=>2, 'errmsg'=>'user error' ];
        }
        $WeixinMsgService = new WeixinMsgService();
        $WeixinMsgService -> delete();
        return ['errcode'=>0,'errmsg'=>''];
    }
    //清理平台请求限制
    public function clearQuota(){
        if($this->request->get('password') != '0ae1ec2c2fb7cd886148970cc78b40e6'){
            return [ 'errcode'=>2, 'errmsg'=>'user error' ];
        }
        $type = $this->request->get('type','');
        if($type == 'weixin'){
            $componentToken = (new WeixinService())->getComponentToken();
            $component = ( new Component()) ->setComponentToken($componentToken);
            $response = $component->clearQuota();
        }else if($type == 'app'){
            $appid = $this->request->get('appid','');
            if(empty($appid)){
                return [ 'errcode'=>1, 'errmsg'=>'appid error' ];
            }
            $accessToken = (new WeixinService())->getAccessToken($appid);
            $officialUser = ( new OfficialUser()) ->setAccessToken($accessToken);
            $response =  $officialUser->clearQuota();
        }
        return ['errcode'=>0,'errmsg'=>'' ,'response'=>$response];
    }
    //清理token
    public function clearToken(){
        if($this->request->get('password') != 'b9c8b41cff3f744bbb9810ae5a1a75fa'){
            return [ 'errcode'=>2, 'errmsg'=>'user error' ];
        }
        $type = $this->request->get('type','');
        if($type == 'weixin'){
            $weixinService = new WeixinService();
            $weixinService -> clearCacheComponentToken();
        }else if($type == 'live'){
            $miguService = new LiveService();
            $miguService ->clearToken();
        }else if($type == 'app'){
            $appid = $this->request->get('appid','');
            if(empty($appid)){
                return [ 'errcode'=>3, 'errmsg'=>'appid error' ];
            }
            $weixinService = new WeixinService();
            $weixinService -> clearCacheAccessToken($appid);
        }else if($type == 'auth'){
            $merchantId = $this->request->get('merchant_id','');
            if(empty($merchantId)){
                return [ 'errcode'=>3, 'errmsg'=>'merchant_id error' ];
            }
            $weixinService = new WeixinService();
            $weixinService -> clearPreAuthCode($merchantId);
        }else if($type == 'version'){
            Cache::forget(CacheKey::get_weixin_cache('weixinVersionUpdateNew','console'));
        }
        return ['errcode'=>0,'errmsg'=>'' ,'type'=>$type];
    }
    //搜索开关
    public function search(){
        if($this->request->get('password') != '0evj8c2c2fb74gk86148970cc78b40e6'){
            return [ 'errcode'=>2, 'errmsg'=>'user error' ];
        }
        $appid = $this->request->get('appid','');
        $type  = $this->request->get('type','');
        if(empty($appid) || !in_array($type,['get','open','close'])){
            return [ 'errcode'=>3, 'errmsg'=>'param error' ];
        }
        $accessToken = (new WeixinService())->getAccessToken($appid);
        if(empty($accessToken)){
            return [ 'errcode'=>4, 'errmsg'=>'appid error' ];
        }
        $appletInfo = (new AppletInfo())->setAccessToken($accessToken);
        if($type == 'get'){
            $response   = $appletInfo->getSearch();
        }else if($type == 'open'){
            $response   = $appletInfo->changeSearch(0);
        }else if($type == 'close'){
            $response   = $appletInfo->changeSearch(1);
        }
        return ['errcode'=>0,'errmsg'=>'' ,'response'=>$response];
    }
    //退款
    public function orderRefund(){
        if($this->request->get('password') != '054a465f3ceeffe46f7347cb6668cefc'){
            return [ 'errcode'=>2, 'errmsg'=>'user error' ];
        }
        $id = $this->request->get('id',0);
        $merchant_id = $this->request->get('merchant_id',0);
        if($id < 1 && $merchant_id < 1){
            return [ 'errcode'=>3, 'errmsg'=>'id error' ];
        }
        $response = OrderRefundApply::update_data($id, $merchant_id,['status'=>10]);
        return  ['errcode'=>0,'errmsg'=>'' ,'response'=>$response];
    }
    //订单
    public function order(){
        if($this->request->get('password') != '054a1f5f3cebhfe46f7347cb64g8cefc'){
            return [ 'errcode'=>2, 'errmsg'=>'user error' ];
        }
        $id = $this->request->get('id',0);
        $merchant_id = $this->request->get('merchant_id',0);
        if($id < 1 && $merchant_id < 1){
            return [ 'errcode'=>3, 'errmsg'=>'id error' ];
        }
        $package = OrderPackage::end_one($id);
        $shipments_time = isset($package['created_time'])?$package['created_time']:date('Y-m-d H:i:s');
        $response = OrderInfo::query()->where(['id' => $id, 'merchant_id' => $merchant_id,'status'=>ORDER_TOSEND])->update(['status'=>ORDER_SEND,'shipments_time'=>$shipments_time]);
        return  ['errcode'=>0,'errmsg'=>'' ,'response'=>$response];

    }
    //清理小程序
    public function clearApp(){
        if($this->request->get('password') != '054a1f5f3cebhfe46f7347cb64g8cefc'){
            return [ 'errcode'=>2, 'errmsg'=>'user error' ];
        }
        $username = $this->request->get('username','');
        if(empty($username)){
            return [ 'errcode'=>3, 'errmsg'=>'username null' ];
        }
        $userinfo = User::query()->where(['username'=>$username,'is_delete'=>-1])->first();
        if(!$userinfo){
            return [ 'errcode'=>3, 'errmsg'=>'username error' ];
        }
        if(empty($userinfo['merchant_id']) || !empty($userinfo['mobile'])){
            return [ 'errcode'=>3, 'errmsg'=>'username error' ];
        }
        $list = WeixinInfo::query()->where(['merchant_id'=>$userinfo['merchant_id'], 'status'=>1])->get(['id','appid','merchant_id'])->toArray();
        foreach ($list as $k => $v) {
            WeixinInfo::update_data('id',$v['id'],['status'=>-1]);
            WeixinOpen::update_data('appid',$v['appid'],['status'=>-1]);
        }
        return [ 'errcode'=>0, 'errmsg'=>'' ,'list' => $list];
    }

}

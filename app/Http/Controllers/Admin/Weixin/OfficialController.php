<?php

namespace App\Http\Controllers\Admin\Weixin;

use App\Http\Controllers\Controller;
use App\Models\WeixinExperiencer;
use App\Models\WeixinMsgTemplate;
use App\Models\WeixinQrcode;
use App\Services\WeixinMsgService;
use App\Services\WeixinService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Utils\Weixin\Applet;
use App\Models\WeixinPay;
use App\Models\WeixinOpen;
use App\Models\WeixinTemplate;
use App\Models\WeixinInfo;
use App\Models\WeixinSetting;
use Cache;
use Config;

class OfficialController extends Controller
{
    private $request ;
    private $merchant_id;
    private $qn_host;
    private $app_env;
    private $func_count = [ 1 => 4 , 2 => 18 ];

    public function __construct(Request $request)
    {
        $this->request = $request;
        $this->merchant_id = Auth::user()->merchant_id ;
        $this->is_admin    =  Auth::user()->is_admin;

        $this->component_id =  Config::get('weixin.component_id');
        $this->qn_host = Config::get('weixin.qn_host');
        $this->func_count[1] = Config::get('weixin.wechat_auth_count');
        $this->func_count[2] = Config::get('weixin.wechat_auths_count');
        $this->app_env = Config::get('weixin.app_env');
        $this->test_merchant = Config::get('weixin.test_merchant_id');
    }

    //公众账号信息
    public function appServer(){
        $info = WeixinInfo::get_one('merchant_id',$this->merchant_id,2);
        if(isset($info['id'])){
            $data = ['id'=>$info['id'],'appid'=>$info['appid'],'name'=>$info['nick_name'],'head'=>$info['head_img'],'desc'=>$info['signature']];
        }else{
            $data = ['id'=>0,'appid'=>'','name'=>'','head'=>'','desc'=>''];
        }
        return ['errcode' => 0, 'errmsg' => 'ok' ,'data' => $data ];
    }
    //小程序列表
    public function appList(){
        $nickname = $this->request->get('nickname','');
        $page = $this->request->get('page',1);
        $length = $this->request->get('length',10);
		$showdel = $this->request->get('showdel',0);
        $page = ($page -1)*$length;

        $listInfo = WeixinInfo::list_page($this->merchant_id,$nickname,$page,$length,1,$showdel);
        $WeixinService = new WeixinService();
        $data = [];
        $versionType = $this->app_env == 'production' && !in_array($this->merchant_id,$this->test_merchant) ? 1 : 0;
        foreach ($listInfo as $k => $v){
            if(!empty($v['appid'])){
                $version = $WeixinService ->getVersion($v['tpl_type'],$this->merchant_id);
                $tplinfo = WeixinTemplate::get_one_ver($this->merchant_id,$v['appid']);

                $tplinfoverify = $tplinfo['release'] == 1 ? ['id' => 1 ] : WeixinTemplate::get_one_verify($this->merchant_id,$v['appid']);
                $tplinfocheck  = $tplinfo['check'] > 0 ? ['check_date' => $tplinfo['check_date'] ] :  WeixinTemplate::get_one_check($this->merchant_id,$v['appid']);

                $data[] = [
                    'id'       => $v['id'],
                    'appid'    => $v['appid'],
                    'name'     => $v['status'] == -1 ? $v['nick_name'].'(已删除)' : $v['nick_name'],
                    'head'     => $v['head_img'],
                    'desc'     => $v['signature'],
                    'qrcode'   => empty($v['qrcode']) ? '-' : $this->qn_host.$v['qrcode'],
                    'category' => str_replace('#','->',$tplinfo['category']),
                    'tag'      => str_replace(' ',',',$tplinfo['tag'] ),
                    'online'   => isset($tplinfoverify['id']) ? 1 : 0 ,
                    'auth'     => $v['auth'],
                    'tpl'      => $v['tpl_type'],
                    'verify'   => $tplinfo['check'] == 0 ? -1 : $tplinfo['verify'],
                    'isupdate' => $version['id'] > $tplinfo['version_id'] ? 1 : 0 ,
                    'uperror'  => $tplinfo['update_error'],
                    'error'    => $tplinfo['check_error'],
                    'version'  => $tplinfo['version'],
                    'versions' => $version['version'],
                    'subtime'  => isset($tplinfocheck['check_date'])?date('Y-m-d H:i:s',$tplinfocheck['check_date']):'',
                ];
            }else{
                $data[] = [
                    'id' => $v['id'],
                    'appid'    => '' ,
                    'name'     => '未命名小程序',
                    'head'     => '',
                    'desc'     => '',
                    'qrcode'   => '',
                    'category' => '',
                    'tag'      => '',
                    'online'   => 0,
                    'auth'     => 0,
                    'tpl'      => 'S',
                    'verify'   => -1,
                    'isupdate' => 0,
                    'uperror'  => '',
                    'error'    => '',
                    'version'  => '',
                    'versions' => '',
                    'subtime'  => 0,
                    ];
            }
        }
        $check = $WeixinService->checkAppMax($this->merchant_id);
        $last   = 0;
        $count = WeixinInfo::list_count($this->merchant_id,$nickname);
        return [ 'errcode'=>0, 'errmsg'=>'ok' ,'data' => $data,'count'=>$count ,'max' => $check['max'],'delete'=>0, 'last'=>$last  ];
    }
    //小程序详细信息
    public function info(){
        $id = $this->request->get('id',0);
        $appid = $this->request->get('appid','');
        if(!empty($id)){
            $info = WeixinInfo::get_one('id',$id,1);
        }else if(!empty($appid)){
            $info = WeixinInfo::get_one_appid($this->merchant_id,$appid);
        }else{
            return [ 'errcode'=>1, 'errmsg'=>'param null' ];
        }
        if(!isset($info['id']) || empty($info['appid']) || $info['type'] != 1 || $info['auth'] != 1 || $info['merchant_id'] != $this->merchant_id || $info['ticket_id'] != $this->component_id){
            return [ 'errcode'=>1, 'errmsg'=>'授权状态失效，请重新授权' ];
        }
        $openinfo = WeixinOpen::get_one('appid',$info['appid']);
        if(empty($openinfo['id']) || $openinfo['bind'] != 1){
            return [ 'errcode'=>3, 'errmsg'=>'授权失败 [open]' ];
        }
        $tplInfo = WeixinTemplate::get_one_ver($this->merchant_id,$info['appid']);
        //支付配置信息
        $payinfo = WeixinPay::get_one_appid($this->merchant_id,$info['appid']);
        $config = json_decode($payinfo['config'],true);
        $pem    = json_decode($payinfo['pem'],true);

        $tplinfoverify = $tplInfo['release'] == 1 ? ['id' => 1 , 'version_id' => $tplInfo['version_id'] ] : WeixinTemplate::get_one_verify($this->merchant_id,$info['appid']);
        $tplinfocheck  = $tplInfo['check'] > 0 ? ['check_date' => $tplInfo['check_date'] ] :  WeixinTemplate::get_one_check($this->merchant_id,$info['appid']);
        $newversion = (new WeixinService())->getVersion($info['tpl_type'],$this->merchant_id);

        if($tplInfo['release'] == 1){
            $status = 0;
        } else if($tplInfo['verify'] == 2){
            $status = 2;
        }else if($tplInfo['verify'] == 0 and $tplInfo['check'] > 0){
            $status = 1;
        }else{
            $status = -1;
        }

        $data  = [
            'appid'    => $info['appid'],
            'name'    => $info['nick_name']   ,
            'head'    => $info['head_img'],
            'verify'  => $info['verify_type'] == -1 ? '未认证' : '已认证或认证中',
            'desc'    => $info['signature'],
            'qrcode'   => empty($info['qrcode']) ? '-' : $this->qn_host.$info['qrcode'],
            'auth'     => $info['auth'],
            'tpl'      => $info['tpl_type'],
            'online'   => isset($tplinfoverify['id']) ? 1 : 0  , //是否上线过
            'onlinev'  => $tplinfoverify['version_id'] > 181182 ? 1 : 0 ,
            'submit'   => $tplinfocheck['check_date'] > 0 ? 1 : 0, //是否提交过

            'pay'     => [
                'payid'  => (isset($config['mch_id']) && !empty($config['mch_id'])) ?  substr_replace($config['mch_id'],"****",3,4) : ''  ,
                'paykey' => (isset($config['key']) && !empty($config['key'])) ?  substr_replace($config['key'],"********",6,20)  : '' ,
                'paypem' => empty($pem) ? 0 : 1
            ],
            'set'     => [
                'tag'      => empty($tplInfo['tag'])?[]:explode(' ',$tplInfo['tag'] ) ,
                'category' => empty($tplInfo['category'])?[]:explode('#',$tplInfo['category'])
            ],
            'version' => [
                'current' => $tplInfo['version'] ,
                'new'     => $newversion['version'] ,
                'time'    => $tplInfo['pass_date'] > 0 ? date('Y.m.d',$tplInfo['pass_date']) : '审核中'  ,
                'status'  => $status , //【0:审核成功 1:审核中 2:审核失败 】
                'error'   => $tplInfo['verify'] == 2 ? $tplInfo['check_error']:'',
                'isupdate' => $newversion['id'] > $tplInfo['version_id'] ? 1 :0 ,//0 无更新  1 有更新,
                'release'  => !empty($tplInfo['check']) && $tplInfo['release'] == 0 ? 1  : 0,
                'qrcode'   => 0 ,
                'uperror'  => $tplInfo['update_error'],
            ]
        ];
        
        //小程序 支付方式 开关状态
        $rs_weixinsetting = WeixinSetting::where(['info_id'=>$id, 'merchant_id'=>$this->merchant_id])->first();
        if(empty($rs_weixinsetting)){
            $data_setting = array();
            $data_setting['merchant_id'] = $this->merchant_id;
            $data_setting['info_id'] = $id;
            $data_setting['weixin_onoff'] = 1;
            $data_setting['delivery_onoff'] = 0;
        
            WeixinSetting::insert_data($data_setting);
            
            $rs_weixinsetting = WeixinSetting::get_data_by_id($id, $this->merchant_id);
        }
        $data['setting'] = [
            'weixin_onoff' => !empty($rs_weixinsetting)?$rs_weixinsetting['weixin_onoff']:0,
            'delivery_onoff' => !empty($rs_weixinsetting)?$rs_weixinsetting['delivery_onoff']:0
        ];
        
        return [ 'errcode'=>0, 'errmsg'=>'ok' ,'data' => $data  ];
    }
    //推广二维码查看
    public function getSpreadQrcode(){
        $type = $this->request->get('type',0);// 0 全部 1商品 2 优惠券 3 拼团 4秒杀
        $page = $this->request->get('page',1);
        $leng = $this->request->get('length',10);
        $page = ($page-1)*$leng;
        if(!in_array($type,[0,1,2])){
            return [ 'errcode'=>1, 'errmsg'=>'param error' ];
        }
        $list = [];
        $result = WeixinQrcode::list_data($this->merchant_id,$type,$page,$leng);
        foreach ($result as $k => $v) {
            $cache = WeixinInfo::get_one('appid',$v['appid']);
            $list[] = [ 'id' =>$v['id'] , 'url' => $this->qn_host.$v['url'],'type' => $v['type'], 'name'=>$v['name'],'appidname'=>$cache['nick_name'],'time'=>$v['created_time'] ];

        }
        $count = WeixinQrcode::list_count($this->merchant_id,$type);
        return [ 'errcode'=>0, 'errmsg'=>'','data'=>$list ,'count'=>$count];
    }
    //删除推广小程序码
    public function delSpreadQrcode(){
        $id = $this->request->input('id',0);
        if($id < 0){
            return [ 'errcode'=>1, 'errmsg'=>'param error' ];
        }
        WeixinQrcode::update_data(['id'=>$id,'merchant_id'=>$this->merchant_id],['is_delete'=>-1]);
        return [ 'errcode'=>0, 'errmsg'=>'小程序码删除成功' ];
    }
    //下载下程序码
    public function downloadQrcode(){
        $id = $this->request->input('id',0);
        if($id < 0){
            return [ 'errcode'=>1, 'errmsg'=>'param null' ];
        }
        $info = WeixinQrcode::get_one('id',$id);
        if(!$info['id']){
            return [ 'errcode'=>1, 'errmsg'=>'param error' ];
        }
        $file = file_get_contents($this->qn_host.$info['url']);
        header('Content-Type: image/jpeg');
        header("Content-disposition:attachment;filename=qrcode.jpg;");
        echo $file;
        exit();
    }
    //设置消息模板id
    public function setMsgTemplate(){
        $id = $this->request->input('id',0);
        $appid = $this->request->input('appid','');
        $data     =  $this->request->input('data');
        if(!is_array($data)){
            return [ 'errcode'=>1, 'errmsg'=>'param error' ];
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
        $msg_template_type = array_keys((new WeixinMsgService())->tpldata);
        foreach ($data as $k => $v) {
            if(!isset($v['template_id']) || !in_array($v['template_type'],$msg_template_type) ){
              unset($data[$k]);
            }
        }
        if(empty($data)){
            return [ 'errcode'=>1, 'errmsg'=>'param error' ];
        }
        $info = WeixinInfo::get_one_appid($this->merchant_id,$info['appid']);
        if(empty($info['appid'])){
            return [ 'errcode'=>1, 'errmsg'=>'Authorization failure' ];
        }
        foreach ($data as $k => $v) {
            $v['template_id'] = trim($v['template_id']);
            $msgTplInfo = WeixinMsgTemplate::get_one($this->merchant_id,$info['appid'],$v['template_type']);
            if(isset($msgTplInfo['id']) && $msgTplInfo['id'] > 0){
                 WeixinMsgTemplate::update_data('id',$msgTplInfo['id'],['template_id'=>$v['template_id']]);
            }else{
                 WeixinMsgTemplate::insert_data(['merchant_id'=>$this->merchant_id,'appid'=>$info['appid'],'template_id'=>$v['template_id'],'template_type'=>$v['template_type']]);
            }
        }
        return [ 'errcode'=>0, 'errmsg'=>'ok' ];
    }
    //查看消息模板id
    public function getMsgTemlate(){
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
        $list = WeixinMsgTemplate::list_data($this->merchant_id,$info['appid']);
        $data = [];
        foreach ($list as $k => $v) {
            $data[] = ['template_type' => $v['template_type'], 'template_id' => $v['template_id'] ];
        }
        return [ 'errcode'=>0, 'errmsg'=>'ok' , 'data' => $data ];
    }
    //设置支付信息
    public function setPayInfo(){
        $id = $this->request->input('id',0);
        $appid = $this->request->input('appid','');
        $payid = $this->request->input('payid');
        $paykey = $this->request->input('paykey');
        if(empty($paykey) || empty($payid) ){
            return [ 'errcode'=>1, 'errmsg'=>'param error' ];
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
        $payinfo = WeixinPay::get_one_appid($this->merchant_id,$info['appid']);
        if(empty($payinfo['id'])){
            WeixinPay::insert_data(['merchant_id' => $this->merchant_id ,'appid' => $info['appid'], 'config' => json_encode([ 'mch_id'=>$payid, 'key'=>$paykey ]) ]);
        }else{
            WeixinPay::update_data('id',$payinfo['id'],[  'config' => json_encode([ 'mch_id'=>$payid, 'key'=>$paykey ])  ]);
        }
        return [ 'errcode'=>0,'msg'=>'ok' ];
    }
    //上传支付证书
    public function uplPayInfo(){
        if(!$this->request->hasFile('cert')){
            return ['errcode' => 1, 'msg' => '请上传文件'];
        }
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
        $appid = $info['appid'];
        $file    = $this->request->file('cert');
        //检验一下上传的文件是否有效.
        if (!$file->isValid() ) {
            return ['errcode' => 2, 'msg' => '上传无效'];
        }
        $Suffix = $file->getClientOriginalExtension(); //上传文件的后缀
        //只允许上传zip后缀压缩文件
        if ($Suffix != 'zip') {
            return ['errcode' => 3, 'msg' => '文件格式不正确,请上传zip压缩文件'];
        }
        $newName = $file->getClientOriginalName();
        //把以前上传的文件删除
        $path_file = PEM_PATH .$this->merchant_id . '/'.$appid . '/'. date('Ym', time()) . '/';
        $path = '/' . $path_file;
        $file->move($path, $newName);
        $file_path = $path . $newName;
        if(!is_file($file_path)){
            $this->delete_path($file_path);
            return [  'code' => 3,  'msg' => '上传的文件目录不存在'];
        }
        $zip = zip_open($file_path);
        if (!$zip || !is_resource($zip)){
            return [  'code' => 3,  'msg' => '上传的文件打开错误'];
        }
        $type_name = 'wxpay';
        $dir_arr = []; //压缩包里面文件名数组
        $config = Config::get('payment_certificate.'.$type_name ); //对应支付方式要检查的文件数组
        while ($zip_entry = zip_read($zip)) {
            $dir = zip_entry_name($zip_entry);
            if (in_array($dir, $config)) {
                $dir_arr[] = $dir;
            }
        }
        //如果检查压缩里没有所需要的文件 则再检查压缩文件里的文件
        if (!isset($dir_arr) || empty($dir_arr)) {
            $zip = zip_open($file_path);
            while ($zip_entry = zip_read($zip)) {
                $dir = zip_entry_name($zip_entry);
                if (isset($_dir)) {
                    $dir_r = str_replace($_dir, '', $dir);
                    if (in_array($dir_r, $config)) {
                        $dir_arr[] = $dir_r;
                    }
                } else {
                    $_dir = $dir;
                }
            }
        }
        zip_close($zip);
        //检测压缩的文件里是否有对应的文件存在
        //如果缺少对应的文件 则是文件上传失败  删除已经上传的文件夹
        $array = array_diff($config, $dir_arr);
        if (!empty($array)) {
            $this->delete_path($file_path);
            return [  'code' => 4,  'msg' => '压缩包中缺少必要的文件' ];
        }
        //解压zip压缩文件
        $zip_object = new \ZipArchive();  //实例化解压zip文件类
        $res = $zip_object->open($file_path);
        if ($res !== TRUE) {
            $this->delete_path($file_path);
            return ['code' => 5, 'msg' => '解压zip文件失败'];
        }
        if (isset($_dir)) {
            $zip_object->extractTo($path);
        } else {
            $zip_object->extractTo($path .$type_name);
        }
        $zip_object->close();
        $this->delete_path($file_path);
        //把上传的文件 以url的链接形式存储到对应商户支付方式的列中

        if (isset($_dir)) {
            $type_name = 'wxpay_small_routine/';
            $this->moveFile('/' . $path_file . $type_name . $_dir, $path . $type_name);
        }else{
            $type_name .= '/';
        }
        $path_file = str_replace(PEM_PATH, '', $path_file);
        $data = array(
            'apiclient_cert' => $path_file . $type_name . 'apiclient_cert.pem',
            'apiclient_key' => $path_file . $type_name . 'apiclient_key.pem',
            'rootca' => $path_file . $type_name . 'rootca.pem'
        );
        unset($config, $dir_arr);
        $info = WeixinPay::get_one_appid($this->merchant_id,$appid);
        if(empty($info['id'])){
            WeixinPay::insert_data(['merchant_id' => $this->merchant_id,'appid'=>$appid , 'pem' => json_encode($data) ]);
        }else{
            WeixinPay::update_data('id',$info['id'],[  'pem' => json_encode($data)  ]);
        }
        return ['errcode' => 0, 'msg' => 'ok'];
    }

    private function moveFile($oldFile,$newFile)
    {
        if($oldFile){
            $newFile = str_replace('\\', '/', $newFile);
            $oldFile = str_replace('\\', '/', $oldFile);
            @rename($oldFile,$newFile); //拷贝到新目录
        }

    }

    private function delete_path($file_path)
    {
        if (is_file($file_path)) {
            unlink($file_path);
        }
    }
}

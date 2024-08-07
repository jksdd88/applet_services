<?php
/*
 * @name  微信第三方全网发布接入
 * @auth  wangshiliang@dodoca.com
 * @link  https://open.weixin.qq.com/cgi-bin/showdocument?action=dir_list&t=resource/res_list&verify=1&id=open1419318611&token=&lang=zh_CN
 */

namespace App\Http\Controllers\Admin\Weixin;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use App\Models\WeixinMsgMerchant;
use App\Models\WeixinInfo;
use App\Models\WeixinMsgTemplate;
use App\Utils\Weixin\QrcodeOfficial;
use App\Utils\Weixin\OfficialMsgTemplate;
use App\Utils\CacheKey;
use App\Services\WeixinMsgService;
use App\Services\WeixinService;
use Config;
use Cache;


class WechatOfficialController extends Controller
{
    private $request;
    private $merchant_id;

    public function __construct(Request $request)
    {
        $this->request = $request;
        $this->merchant_id = $request->get('merchant_id',0);
    }

    //带参数二维码
    public function getQrcode(){
        $info = WeixinInfo::get_one('merchant_id',$this->merchant_id,2);
        if(!isset($info['id']) || empty($info['appid'])){
            return [ 'errcode'=>1, 'errmsg'=>'请授权公众账号' ];
        }
        $cachekey = CacheKey::get_weixin_cache('component_access_token',$this->merchant_id);
        $cachevalue = Cache::get($cachekey);

        $qrcodeOfficial = new QrcodeOfficial();
        if($cachevalue){
            return [ 'errcode'=>0, 'errmsg'=>'','ticket' => $qrcodeOfficial->getImg($cachevalue) ];
        }
        $scene =  Config::get('weixin.official_qrcode_notice');
        $weixinService = new WeixinService();
        $qrcodeOfficial->setConfig($weixinService->getAccessToken($info['appid']));
        $response = $qrcodeOfficial ->create($scene);
        if(!isset($response['ticket'])){
            return [ 'errcode'=>1, 'errmsg'=>'网络有误，请稍候重试' ];
        }
        $expire_seconds  = $response['expire_seconds']/60 - 5;
        Cache::put($cachekey,$response['ticket'],$expire_seconds);
        return [ 'errcode'=>0, 'errmsg'=>'','ticket' => $qrcodeOfficial->getImg($response['ticket']) ];
    }
    //通知列表
    public function listNotice(){
        $info = WeixinInfo::get_one('merchant_id',$this->merchant_id,2);
        if(!isset($info['id']) || empty($info['appid'])){
            return [ 'errcode'=>1, 'errmsg'=>'请授权公众账号' ];
        }
        $list = WeixinMsgMerchant::list_data($info['merchant_id'],$info['appid']);
        $data = [];
        if(!empty($list)){
            $weixinMsgService = new WeixinMsgService();
            foreach ($list as $k => $v) {
                $cacheValue = empty($v['notice'])? [] : explode(',',$v['notice']);
                $notice  = [];
                foreach ($cacheValue as $ks => $vs) {
                    $notice[$vs] = $weixinMsgService->tpldata[$vs]['title'];
                }
                $data[] = ['id'=>$v['id'],'headimg'=>$v['headimg'],'nickname'=>$v['nickname'],'notice'=>$notice];
            }
        }
        return [ 'errcode'=>0, 'errmsg'=>'ok','data'=>$data];
    }
    //通知删除
    public function deleteNotice(){
        $id   = $this->request->input('id',1);
        $noticeinfo = WeixinMsgMerchant::get_one('id',$id);
        if(!isset($noticeinfo['id'])){
            return [ 'errcode'=>1, 'errmsg'=>'参数有误' ];
        }
        if(isset($noticeinfo['id']) && $noticeinfo['merchant_id'] != $this->merchant_id){
            return [ 'errcode'=>1, 'errmsg'=>'权限不足' ];
        }
        WeixinMsgMerchant::update_data($id,['status'=>-1]);
        return [ 'errcode'=>0, 'errmsg'=>'' ];
    }
    //通知设置
    public function setNotice(){
        $id   = $this->request->input('id',1);
        $val  = $this->request->input('val','');
        $val  = explode(',',$val);
        $weixinMsgService = new WeixinMsgService();
        foreach ($val as $k => $v) {
            if(!isset($weixinMsgService->tpldata[$v])){
                unset($val[$k]);
            }
        }
        $noticeinfo = WeixinMsgMerchant::get_one('id',$id);
        if(!isset($noticeinfo['id'])){
            return [ 'errcode'=>1, 'errmsg'=>'参数有误' ];
        }
        if(isset($noticeinfo['id']) && $noticeinfo['merchant_id'] != $this->merchant_id){
            return [ 'errcode'=>1, 'errmsg'=>'权限不足' ];
        }
        $info = WeixinInfo::get_one('merchant_id',$this->merchant_id,2);
        if(!isset($info['id']) || empty($info['appid'])){
            return [ 'errcode'=>1, 'errmsg'=>'请授权公众账号' ];
        }
        WeixinMsgMerchant::update_data($id,['notice'=>empty($val)?'':implode(',',$val)]);
        return [ 'errcode'=>0, 'errmsg'=>'' ];
    }
    //通知模板列表
    public function getTplId(){
        $info = WeixinInfo::get_one('merchant_id',$this->merchant_id,2);
        if(!isset($info['id']) || empty($info['appid'])){
            return [ 'errcode'=>1, 'errmsg'=>'请授权公众账号' ];
        }
        $weixinMsgService = new WeixinMsgService();
        $list = WeixinMsgTemplate::list_data($this->merchant_id,$info['appid'],2);
        $data = [];
        foreach ($list as $k => $v) {
            $title  = isset($weixinMsgService->tpldata[$v['template_type']]['title'] )?$weixinMsgService->tpldata[$v['template_type']]['title']:'';
            $data[$v['template_type']] = ['template_type' => $v['template_type'], 'template_id' => $v['template_id'] ,'template_name'=> $title ];
        }
        return [ 'errcode'=>0, 'errmsg'=>'ok' , 'data' => $data ];
    }
    //通知模板打开
    public function autoTplId(){
        $action = $this->request->input('action',2);
        $type   = $this->request->input('type',61);//61  62

        $info = WeixinInfo::get_one('merchant_id',$this->merchant_id,2);
        if(!isset($info['id']) || empty($info['appid'])){
            return [ 'errcode'=>1, 'errmsg'=>'请授权公众账号' ];
        }
        $weixinMsgService = new WeixinMsgService();
        if(!isset($weixinMsgService->tpldata[$type]['type']) || $weixinMsgService->tpldata[$type]['type'] != 2) {
            return [ 'errcode'=>1, 'errmsg'=>'type error ' ];
        }

        $msgTemplate = new OfficialMsgTemplate();
        $weixinService = new WeixinService();
        $msgTemplate->setConfig($weixinService->getAccessToken($info['appid']));
        //删除
        if($action == 2){
            $msgTplInfo = WeixinMsgTemplate::get_one($this->merchant_id,$info['appid'],$type);
            if(!isset($msgTplInfo['template_id']) || empty($msgTplInfo['template_id'])){
                return [ 'errcode'=>0, 'errmsg'=>'ok!','template_id'=>''];
            }
            $response = $msgTemplate->delTemplate($msgTplInfo['template_id']);
            WeixinMsgTemplate::update_data('id',$msgTplInfo['id'],['template_id'=>'']);
            if($response['errcode'] != 0){
                return [ 'errcode'=>2, 'errmsg'=>'删除异常' ];
            }
            return [ 'errcode'=>0, 'errmsg'=>'ok!','template_id'=>''];
        }
        //添加 更新
        $msgTplInfo = WeixinMsgTemplate::get_one($this->merchant_id,$info['appid'],$type);
        //行业添加
        if(!isset($msgTplInfo['id'])){
            $response = $msgTemplate -> getIndustry();
            if(!isset($response['secondary_industry']['second_class']) || empty($response['secondary_industry']['second_class'])){
                $response = $msgTemplate->setIndustry();
                if($response['errcode'] != 0){
                    return [ 'errcode'=>3, 'errmsg'=>'industry error,'.$response['errcode'].':'.$response['errmsg'] ];
                }
            }
        }
        //生成
        $tpldata = $weixinMsgService->tpldata[$type];
        $response = $msgTemplate->addTemplate($tpldata['id']);
        if($response['errcode'] != 0){
            return [ 'errcode'=>4, 'errmsg'=>'post error,'.$response['errcode'].':'.$response['errmsg'] ];
        }
        if(isset($msgTplInfo['id']) && $msgTplInfo['id'] > 0){
            if(!empty($msgTplInfo['template_id'])){
                $msgTemplate->delTemplate($msgTplInfo['template_id']);
            }
            WeixinMsgTemplate::update_data('id',$msgTplInfo['id'],['template_id'=>$response['template_id']]);
        }else{
            WeixinMsgTemplate::insert_data(['merchant_id'=>$this->merchant_id,'appid'=>$info['appid'],'template_id'=>$response['template_id'],'template_type'=>$type,'app_type'=>$tpldata['type']]);
        }
        return [ 'errcode'=>0, 'errmsg'=>'ok.','template_id'=>$response['template_id']];

    }

    //综合兼容
    public function whtest(){
        $qrcode = $this->getQrcode();
        $tpl  = $this->getTplId();
        $list = $this->listNotice();
        return [ 'errcode'=>0, 'errmsg'=>'ok','ticket' => isset($qrcode['ticket'])?$qrcode['ticket']:'' , 'tpl' => isset($tpl['data'])?$tpl['data']:[], 'list' => isset($list['data']) ? $list['data'] : []  ];
    }



}

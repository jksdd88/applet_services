<?php
/*
 * @name  微信第三方全网发布接入
 * @auth  wangshiliang@dodoca.com
 * @link  https://open.weixin.qq.com/cgi-bin/showdocument?action=dir_list&t=resource/res_list&verify=1&id=open1419318611&token=&lang=zh_CN
 */

namespace App\Http\Controllers\Admin\Weixin;

use App\Http\Controllers\Controller;
use App\Models\MemberInfo;
use App\Models\WeixinMsgMerchant;
use App\Services\MemberService;
use App\Utils\Weixin\OfficialUser;
use Illuminate\Http\Request;
use App\Models\WeixinPay;
use App\Models\WeixinComponent;
use App\Models\WeixinInfo;
use App\Models\WeixinTemplate;
use App\Models\WeixinOpen;
use App\Models\WeixinSetting;
use App\Services\WeixinService;
use App\Utils\Weixin\Component;
use App\Utils\Weixin\Applet;
use App\Utils\Weixin\Http;
use Config;
use Log;


class ComponentController extends Controller
{

    private $gateway;
    private $component_appid;
    private $component_id;
    private $request;
    private $host;
    private $whHost;

    public function __construct(Request $request)
    {
        $this->request = $request;
        $this->component_id =  Config::get('weixin.component_id');
        $this->component_appid =  Config::get('weixin.component_appid');
        $this->gateway = Config::get('weixin.wechat_open_gateway');
        $this->whHost = Config::get('weixin.wh_host');
    }

    public function index(){
        return '';
    }

    public function componentToken(){
        return [ 'errcode'=>0, 'errmsg'=>'ok', 'token'=>(new WeixinService())->getComponentToken() ];
    }

    public function receive(){
        $WeixinService = new WeixinService();
        $Component =  new Component();
        $result = $Component->getReceiveXml( $this->request->input());
        if ($result[0]) {
            return 'error';
        }
        $array = (array)simplexml_load_string($result[1], 'SimpleXMLElement', LIBXML_NOCDATA);
        switch ($array['InfoType']) {
            case 'component_verify_ticket': //推送ticket
                WeixinComponent::update_data($this->component_id,['verify_ticket' => $array['ComponentVerifyTicket']]);
                break;
            case 'unauthorized': //取消授权 template  open
                $WeixinService->clearCacheAccessToken($array['AuthorizerAppid']);
                $info =  WeixinInfo::get_one('appid',$array['AuthorizerAppid']);
                if(isset($info['merchant_id']) && !empty($info['merchant_id'])  ){
                    WeixinInfo::update_data('id',$info['id'],['auth'=>0]);
                    $WeixinService->logAuth($info['merchant_id'],$array['AuthorizerAppid'],2,1,'','');
                    $WeixinService->clearPreAuthCode($info['merchant_id']);
                    $WeixinService->clearCacheAccessToken($info['appid']);
                }
                break;
            case 'authorized': //
                $WeixinService->setLog('component_authorized',$array,[]);
                break;
            case 'updateauthorized': //
                $WeixinService->setLog('component_updateauthorized',$array,[]);
                break;
        }
        return 'success';
    }

    public function message($app_id){
        $post = $this->request->input();
        $Component =  new Component();
        $result = $Component->getReceiveXml($post);
        if ($result[0]) {
            return 'error';
        }
        $WeixinService = new WeixinService();
        $array = (array)simplexml_load_string($result[1], 'SimpleXMLElement', LIBXML_NOCDATA);
        //======================= 第三方 发布 ===================
        if ($app_id == 'wx570bc396a51b8ff8' || $app_id == 'wxd101a85aa106f53e' ) {
            if ($array['MsgType'] == 'text') {//普通文本验证
                $repType = "text";
                if (isset($array['Content']) && $array['Content'] == 'TESTCOMPONENT_MSG_TYPE_TEXT') {
                    $repCont = $array['Content'].'_callback';
                } else {//API验证
                    $verify_ticket = WeixinComponent::get_data_by_id($this->component_id,'verify_ticket,updated_time');
                    $WeixinService->clearCacheComponentToken();
                    $component_access_token = $Component ->getComponentToken($verify_ticket->verify_ticket);
                    $repCont = explode(":", $array['Content'])[1];//接受微信放发来的query_auth_code
                    $msgcontent = ["content" => $repCont . '_from_api'];
                    //获取access_token
                    $applet = new Applet();
                    $client_result = $applet->mxCurl('https://api.weixin.qq.com/cgi-bin/component/api_query_auth?component_access_token='.$component_access_token['component_access_token'],json_encode(['component_appid' => $this->component_appid,  'authorization_code' => $repCont]));
                    $applet->mxCurl('https://api.weixin.qq.com/cgi-bin/message/custom/send?access_token='.$client_result['authorization_info']['authorizer_access_token'],json_encode(["touser"  => $array['FromUserName'], "msgtype" => 'text', "text"	=>	$msgcontent]));
                    $repCont = $repCont . '_from_api';
                }
            } elseif ( $array['MsgType'] == 'event') {//事件验证
                $repType = "text";
                $repCont = $array['Event'] . "from_callback";
            }
            $response = $Component->responsReceiveXml($post,$repCont,$repType);
            return $response;
        }
        //======================= 小程序 发布结果通知 ====================
        if(isset($array['MsgType']) && $array['MsgType']  == 'event' && isset($array['Event']) && ($array['Event'] == 'weapp_audit_success' || $array['Event'] == 'weapp_audit_fail')){
            $weixinInfo = WeixinInfo::get_one('user_name',$array['ToUserName'],1);
            if(isset($weixinInfo['id'])){
                $tplInfo = WeixinTemplate::get_one_ver($weixinInfo['merchant_id'],$weixinInfo['appid']);
                if($array['Event'] == 'weapp_audit_fail'){
                    WeixinTemplate::update_data($tplInfo['id'],['verify'=>2, 'check_error'=> $array['Reason'], 'pass_date'=>$_SERVER['REQUEST_TIME'] ]);
                }elseif($array['Event'] == 'weapp_audit_success'){
                    $WeixinService->releaseVerify($tplInfo,$weixinInfo);
                }
            }
            return 'success';
        }
        //======================= 小程序 客服事件 ====================
        if(in_array($array['MsgType'],['text','image','miniprogrampage'])  || ($array['MsgType'] == 'event'  && $array['Event'] == 'user_enter_tempsession') ){
            $weixinInfo = WeixinInfo::get_one('user_name',$array['ToUserName'],1);
            if(isset($weixinInfo['id'])){
                $xml = '<xml><ToUserName><![CDATA['.$array['FromUserName'].']]></ToUserName><FromUserName><![CDATA['.$array['ToUserName'].']]></FromUserName><CreateTime>'.$_SERVER['REQUEST_TIME'].'</CreateTime><MsgType><![CDATA[transfer_customer_service]]></MsgType></xml>';
                 return $Component->responsReceive($post,$xml);
            }else{
                //$WeixinService->setLog('user_enter_tempsession',$array,[]);
            }
        }

        //======================= 公众账号 (扫码)   ====================
        if(isset($array['MsgType'])  && isset($array['Event']) && $array['MsgType']  == 'event' && in_array($array['Event'],['subscribe','unsubscribe','SCAN'])){
            $weixinInfo = WeixinInfo::get_one('user_name',$array['ToUserName'],2);//公众账号信息

            if(isset($weixinInfo['appid']) && !empty($weixinInfo['appid'])){
                //带参数二维码 事件
                if(isset($array['EventKey']) &&  isset($array['Ticket'])){
                    $EventKey = str_replace('qrscene_','',$array['EventKey']);
                    $scene =  Config::get('weixin.official_qrcode_notice');
                    if($EventKey == $scene){
                        $check = WeixinMsgMerchant::check_open($array['FromUserName']);
                        if(isset($check['id'])){
                            return $Component->responsReceiveXml($post,'请勿重复绑定','text');
                        }
                        $userOfficial = new OfficialUser();
                        $userOfficial->setAccessToken($WeixinService->getAccessToken($weixinInfo['appid']));
                        $openinfo = $userOfficial -> getUserInfo($array['FromUserName']);
                        if(isset($openinfo['headimgurl'])){
                            WeixinMsgMerchant::insert_data(['merchant_id'=>$weixinInfo['merchant_id'],'appid'=>$weixinInfo['appid'],'openid'=>$array['FromUserName'],'headimg'=>$openinfo['headimgurl'],'nickname'=>$openinfo['nickname']]);
                            return $Component->responsReceiveXml($post,'绑定成功','text');
                        }else{
                            $WeixinService->setLog('Ticket',[],$openinfo);
                        }
                    }
                }
                //关注/取消关注事件
                if($array['Event'] == 'subscribe' || $array['Event'] == 'unsubscribe'){
                    $status = ($array['Event'] == 'subscribe') ? 1 : 0 ;
                    (new MemberService())->subscribe(['merchant_id'=>$weixinInfo['merchant_id'],'appid'=>$weixinInfo['appid'],'openid'=>$array['FromUserName'],'status'=>$status]);
                }
                //取消关注事件 解绑消息魔板
                if($array['Event'] == 'unsubscribe'){
                    $reuslt = WeixinMsgMerchant::check_open($array['FromUserName']);
                    if(isset($reuslt['id'])){
                        WeixinMsgMerchant::update_data($reuslt['id'],['status'=>-1]);
                    }
                }

                //其他
            }
        }
        //======================= 公众账号（武汉） =======================
        $http = new Http();
        $response =  $http->mxCurl($this->whHost.'/weixinapi/msgreply',$array,true,['proxy'=>'false']);
        if($response['errcode'] == 0){
            $response = json_decode( $response['data'],true);
            if($response['code'] == 0){
                return $Component->responsReceive($post,$response['data']);
            }
        }

    }

}

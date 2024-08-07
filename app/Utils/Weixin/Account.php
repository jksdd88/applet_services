<?php
/**
 * Created by PhpStorm.
 * User: wangshiliang@dodoca.com
 * Desc: 小程序代码管理
 * Date: 2017/8/30
 * Time: 14:50
 */
namespace App\Utils\Weixin;


use Illuminate\Support\Facades\Auth;
use App\Utils\Encrypt;
use Config;
use App\Services\WeixinService;
use App\Models\WeixinInfo;

class Account
{
	const WEIXIN_API_CGI = 'https://api.weixin.qq.com/cgi-bin/';
    const WEIXIN_MP_CGI = 'https://mp.weixin.qq.com/cgi-bin/';


    protected $componentId;
    protected $appId;
	protected $appSecret;
    protected $config;
	private $responsjson = true;

	private $merchant_id;
	private $host;


    public function __construct($config = [])
    {
		Config::set('weixin.proxy',false);
        if(empty($config)){
            $this->componentId     = Config::get('weixin.component_id');
            $this->appId           = Config::get('weixin.component_appid');
			$this->appSecret       = Config::get('weixin.component_appsecret');
        }else{
            $this->componentId     = $config['component_id'];
            $this->appId           = $config['component_appid'];
			$this->appSecret       = $config['component_appsecret'];
            $this->config          = $config;
        }

		$this->merchant_id = Auth::user()->merchant_id;
		//$this->merchant_id = 1;
		$this->host = Config::get('weixin.base_host');
    }

	public function createurl($id)
	{
		$info = WeixinInfo::query()->where(['merchant_id'=>$this->merchant_id,'type'=>2,'auth'=>1,'status'=>1])->first();
		if(!$info || !$info['appid']) {	
			return [ 'errcode'=>7001, 'errmsg'=>'请先授权公众号' ];
		} elseif(!in_array('33',explode(',',$info['authlist']))) {
			//没有授权重新公众号授权 测试用
			$pre_auth_code = (new WeixinService())->getPreAuthCode($this->merchant_id);//缓存
			if(!$pre_auth_code){
				return [ 'errcode'=>1, 'errmsg'=>'通信有误' ];
			}
			$type = '2_'.$info['id'].'_0';
			$url = static::WEIXIN_MP_CGI.'componentloginpage?component_appid='.$this->appId.'&pre_auth_code='.$pre_auth_code.'&redirect_uri='.urlencode($this->host.'/weixin/authback?type='.$type);
			echo '<a href="'.htmlspecialchars($url).'">请重新授权增加快速注册小程序权限</a>';exit;
			
			return [ 'errcode'=>7002, 'data'=>htmlspecialchars($res['url']), 'errmsg'=>'请重新授权增加快速注册小程序权限' ];
		} else {
			$appid = $info->appid;
			$callbackurl = urlencode(route('account_callback').'?id='.$id.'&appid='.$appid);
			$component_appid = $this->appId;
			$api = static::WEIXIN_MP_CGI.'fastregisterauth';
			$url = $api."?appid=".$appid."&component_appid=".$component_appid."&copy_wx_verify=1&redirect_uri=".$callbackurl;
			echo '<a href="'.htmlspecialchars($url).'">创建小程序账号</a>';exit;

			return [ 'errcode'=>0, 'data'=>htmlspecialchars($url), 'errmsg'=>'创建小程序账号' ];
		}
	}

	public function reg($id,$appid,$ticket)
	{
		$WeixinService = new WeixinService();
		$access_token = $WeixinService->getAccessToken($appid);
		if(!$access_token) {
			return [ 'errcode'=>7003, 'errmsg'=>'获取access_token失败' ];
		}

		$data = array(
			'ticket' => $ticket,
		);
		$data = json_encode($data,JSON_UNESCAPED_UNICODE);
        $response =  $this->mxCurl(static::WEIXIN_API_CGI.'account/fastregister?access_token='.$access_token,$data);
		$response['data'] = $data;
		print_r($response);
		$logtype = $response['errcode'] ? 1 : 0;
		(new WeixinService())->logAuth($this->merchant_id,$appid,$logtype,$response['errcode'],['id'=>$id,'ticket'=>$ticket],$response);

		if($response['errcode']==0) {
			WeixinInfo::update_data('id',$id,['appid'=>$response['appid']]);
			$res = $this->getAuthorizerInfo($id);
			if($res['errcode']!=0) {
				return $res;
			}
		}

        return [ 'errcode'=>$response['errcode'], 'errmsg'=>$response['errmsg'] ];
	}
	
	//Weixin\AuthorizeController.php getAuthorizerInfo
	public function getAuthorizerInfo($id)
	{
		$info = WeixinInfo::get_one('id', $id);
        if($info['id'] && $info['merchant_id'] != $this->merchant_id){
            return [ 'errcode'=>7004, 'errmsg'=>'privilege grant failed ; key_value:'.$info['id']  ];
        }
		$appid = $info['appid'];
        $componentToken = (new WeixinService())->getComponentToken();
        $Component = new Component();
        $respons = $Component->setComponentToken($componentToken)->getAuthorizerInfo($appid);
        if(!isset($respons['authorizer_info']) || !isset($respons['authorization_info'])){
            return [ 'errcode'=>7005, 'errmsg'=>'token error ; key_value:'.$appid , 'msg_wsl'=>'通信有误' ];
        }
        $authorizer    = $respons['authorizer_info'];
        $authorization = $respons['authorization_info'];
		
        if(empty($authorizer['MiniProgramInfo']) && $authorizer['service_type_info']['id'] != 2){
            return [ 'errcode'=>7006, 'errmsg'=>'authorize type ; key_value:'.$appid, 'msg_wsl'=>'请绑定服务号' ];
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
        $datam['miniprograminfo'] = !empty($authorizer['MiniProgramInfo'])?json_encode($authorizer['MiniProgramInfo']):'';
        $authlist = array_map(function ($v){ return $v['funcscope_category']['id'];},$authorization['func_info']);
        $datam['authlist']  = implode(',',$authlist);
		$datam['token'] = $authorization['authorizer_refresh_token'];
        WeixinInfo::update_data('id',$info['id'], $datam) ;
        return [ 'errcode'=>0, 'errmsg'=>'ok','authorizer_appid'=>$appid,'type'=>$datam['type']  ];

	}

	public function mxCurl($url , $data , $is_post = true){
        $response = ( new Http())->mxCurl($url,$data,$is_post);
        $this->http_response = json_encode($response);
        if($response['errcode'] == 0 ){
            return $this->responsjson ? json_decode($response['data'],true) : $response['data'] ;
        }else{
            return $response;
        }
    }
}
<?php
/**
 * Created by PhpStorm.
 * User: ailiya@dodoca.com
 * Date: 2018/8/30
 * Time: 17:50
 */
namespace App\Http\Controllers\Admin\WeixinAd;

use Illuminate\Http\Request;
use App\Http\Requests;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Config;

use App\Models\AdminLog;

class AuthorizeController extends Controller
{

    function __construct(Request $request)
    {
        $this->merchant_id = 2;//测试
        
        //$this->merchant_id = Auth::user()->merchant_id;

        $this->component_appid = '1107156055';//应用ID
        $this->component_id    = 'pMLpoWobtlTDO1kh';//应用secret
        $this->redirect_domain = 'https://applet.rrdoy.com';//回调域名
        
        //$this->component_appid = Config::get('weixinad.client_id');//应用ID
        //$this->component_id    = Config::get('weixinad.client_secret');//应用secret
        //$this->redirect_domain = Config::get('weixinad.redirect_domain');//回调域名
    }

    public function index(){

    }
    
    public function callback(Request $request)
    {
        $param = $request->all();
        if(!empty($param))
        {
            //记录日志
            $data['action'] = 'weixinad/authorize/callback.json';
            $data['url'] = 'weixinad/authorize/callback.json';
            $data['requests'] = json_encode($param);
            $data['ip']=get_client_ip();
            $rs = AdminLog::insert_data($data);
            s($data);
            var_dump($rs);
        }
    }
}

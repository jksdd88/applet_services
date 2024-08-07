<?php

namespace App\Http\Controllers\Admin\Auth;

use Validator;
use App\Events\UserLogin;
use App\Events\UserLogout;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Admin\Priv;

use App\Models\User;
use App\Models\UserLog;
use App\Utils\SendMessage;
//use App\Services\AdminService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Session;
use Illuminate\Foundation\Auth\ThrottlesLogins;
use Illuminate\Foundation\Auth\AuthenticatesAndRegistersUsers;
use Illuminate\Http\Request;
use Mews\Captcha\Facades\Captcha;
use Carbon\Carbon;
use function GuzzleHttp\json_encode;
use App\Models\Merchant;
use function Qiniu\json_decode;

class AuthController extends Controller {
    /*
    |--------------------------------------------------------------------------
    | Registration & Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles the registration of new users, as well as the
    | authentication of existing users. By default, this controller uses
    | a simple trait to add these behaviors. Why don't you explore it?
    |
    */

    use AuthenticatesAndRegistersUsers, ThrottlesLogins;

    /**
     * Create a new authentication controller instance.
     *
     * @return void
     */
    private $service;
    public function __construct() {
        //$this->middleware('guest', ['except' => 'getLogout']);
        //$this->service$this->service = $adminService;
    }
    
    public function getLogin(){
        //dd(Auth::user());
        $global = [];
        $global['url'] = [
            'base' => url(),
            'https' => url('', '', true),
            'ms'   => env('QINIU_DOMAIN'),
            'static' => env('STATIC_DOMAIN'),
            'static_https' => env('STATIC_HTTPS_DOMAIN'),
        ];
        $data['global'] = json_encode($global);
        return view('home', $data);
    }
    
    /**
     * 登录
     *
     *  @author：songyongshang@dodoca.com
     */
    public function postLogin(Request $request) {
        $input = array(
            'username' => Input::get('username'),
            'password' => Input::get('password'),
            'captcha'  => Input::get('captcha')
        );
        $rules = array (
            'username' => 'required',
            'password' => 'required',
            'captcha' => 'required|captcha'
        );
        $reminder = array(
            'username.required' => '用户名 不能为空！',
            'password.required' => '密码 不能为空！',
            'captcha.required' => '验证码 不能为空！',
            'captcha.captcha' => '验证码不正确,请重试！',
        );
		if(in_array($_SERVER['HTTP_HOST'],['qa-applet.dodoca.com','release-applet.dodoca.com']) && $input['captcha']=='dodoca2018') {
			unset($rules['captcha']);
			unset($reminder['captcha.captcha']);
		}
		
        //dd($input);
        $validator = Validator::make($input, $rules,$reminder);
        if ( $validator->fails() ) {
            $rt['errcode']=100001;
            $errmsg = $validator->getMessageBag()->toArray();
            $rt['errmsg']=$errmsg['captcha'][0];
            $rt['data']='';
            return Response::json($rt);
            //return Response::json(['error' => ['message'=>$validator->getMessageBag()->toArray(), 'type'=>'Auth', 'code'=>401]]);
        } else {
            // 认证凭证
            $credentials = [
                'username' => Input::get('username'),
                'password' => Input::get('password')
            ];
            if (!Auth::validate($credentials)) {
                $credentials = [
                    'mobile' => Input::get('username'),
                    'password' => Input::get('password')
                ];
            }
            //$rt='';
            if (Auth::validate($credentials)) {
                //dd('a');
                Auth::login(Auth::getLastAttempted());
                //event(new UserLogin(Auth::user()));
                $rs_merchant_id = Auth::user()->merchant_id;
                $rs_user_id = Auth::user()->id;
                if(Auth::user()->is_delete!=1){
                    Auth::logout();
                    
                    $rt['errcode']=10001;
                    $rt['errmsg']='此用户账号已失效';
                }
                
                $rs_merchant=Merchant::where('id',$rs_merchant_id)->first();
                if($rs_merchant['status']=='-1'){
                    Auth::logout();
                    
                    $rt['errcode']=10002;
                    $rt['errmsg']='此商户状态已失效';
                }
                if( !empty($rs_merchant) && !in_array($rs_merchant['version_id'], array(1,5)) &&date('Y-m-d H:i:s')>=$rs_merchant['expire_time'] ){
                    Auth::logout();
                    
                    $rt['errcode']=10002;
                    $rt['errmsg']='您的账号已过期，请联系代理商进行续费';
                    return $rt;
                }
                //dd(Auth::user());
                //登录失败的尝试登录到店
                //dd($rt);
                if(!empty($rt)){
                    //dd(is_numeric($request['username']));
                    if(is_numeric($request['username'])){
                        $fgg_jsondecode = $this->getLoginDD($request['username'],$request['password']);
                        if($fgg_jsondecode->code==1 && !empty($fgg_jsondecode->url)){
                            //return Redirect::to($fgg_jsondecode->url);
                            //return redirect(ENV('APP_URL').'/admin/redirecttodd/'.urlencode(base64_encode($fgg_jsondecode->url)));
                            //return redirect($fgg_jsondecode->url);
                            //header('Location:'.$fgg_jsondecode->url);die();
                            $rt['errcode']=20171115;
                            $rt['errmsg']='成功登录到店';
                            $rt['data']['Redirect_url']=base64_encode($fgg_jsondecode->url);
                        }
                    }
                    
                    return Response::json($rt);
                }
                Session::put('password_at',Auth::user()->password_at);
//                 else if(date('Y-m-d H:i:s')>$rs_merchant['expire_time']){
//                     Auth::logout();
                    
//                     $rt['errcode']=10002;
//                     $rt['errmsg']='此商户已过期';
//                     return Response::json($rt);
//                 }
                // 日志
                $request['password'] = md5($request['password']);
                $data = array(
                    'merchant_id'    => $rs_merchant_id,
                    'user_id'    => $rs_user_id,
                    'type' => 3,
                    'url' => json_encode(array('method'=>'POST','route'=>'auth/login.json')),
                    'content' => json_encode($request->all()),
                    'ip' => $request->ip(),
                    'created_time' => date('Y-m-d H:i:s')
                );
                //UserLog::create($data);
                
                $rt['errcode']=0;
                $rt['errmsg']='登录成功';
                $rt['data']=$this->getGlobal();
                
                //商家信息写到缓存里
                
                
                return Response::json($rt);
            } else {
                //尝试登录到店
                $fgg_jsondecode = $this->getLoginDD($request['username'],$request['password']);
                //dd($fgg_jsondecode);
                if($fgg_jsondecode->code==1 && !empty($fgg_jsondecode->url)){
                    //dd(ENV('APP_URL').'/admin/redirecttodd/'.urlencode(base64_encode($fgg_jsondecode->url)));
                    //return redirect(ENV('APP_URL').'/admin/redirecttodd/'.urlencode(base64_encode($fgg_jsondecode->url)));
                    //return Redirect::to($fgg_jsondecode->url);
                    //header('Location:'.$fgg_jsondecode->url);die();
                    //Redirect::to($fgg_jsondecode->url)->send();
                    $rt['errcode']=20171115;
                    $rt['errmsg']='成功登录到店';
                    $rt['data']['Redirect_url']=base64_encode($fgg_jsondecode->url);
                    return Response::json($rt);
                }
                $rt['errcode']=100001;
                $rt['errmsg']='“用户名”或“密码”错误，请重新登录！';
                $rt['data']='';
                return Response::json($rt);
            }
        }
    }

    /**
     * SSO:单点登录
     *  @author：songyongshang@dodoca.com
     */
    public function getSSOLogin(Request $request) {
//         //模拟老郭序列化
//         //$arr_user['username']='Xls001';
//         //$arr_user['mobile']='15221744186';
//         //模拟微伙伴单点登录
//         $arr_user['merchant_id']=1;
//         $arr_user['date_login']=date('Y-m-d H:i:s');
//         $var_serialize = serialize((object)$arr_user);
//         $var_encrypt = encrypt($var_serialize, 'E', 'dodoca_sso');
//         $var_base64encode = base64_encode($var_encrypt);
//         dd($var_base64encode);
        
        //接收数据
        $arr_base64decode = base64_decode($request['token']);
        $arr_decrypt = encrypt($arr_base64decode, 'D', 'dodoca_sso');
        $arr_unserialize = unserialize($arr_decrypt);
        $arr_unserialize = is_object($arr_unserialize)?(array)$arr_unserialize:$arr_unserialize;
        //dd($arr_unserialize);
        // 认证凭证
        // SSO单点登录
        if( !isset($arr_unserialize['merchant_id']) || empty($arr_unserialize['merchant_id']) ){
            $credentials = [
                'username' => $arr_unserialize['username'],
                'mobile' => $arr_unserialize['mobile']
            ];
        }
        // 微伙伴单点登录
        else{
            $credentials = [
                'merchant_id' => $arr_unserialize['merchant_id'],
                'is_admin' => 1
            ];
            if(!isset($arr_unserialize['date_login'])){
                $rt['errcode']=1000012;
                $rt['errmsg']='登录超时.';
                return redirect('manage/login');
            }
            if( (time()-strtotime($arr_unserialize['date_login']))>600 ){
                $rt['errcode']=1000013;
                $rt['errmsg']='登录超时!';
                return redirect('manage/login');
            }
        }
        $rs_user = User::where($credentials)->first();
        //dd($rs_user);
        if(empty($rs_user)){
            Auth::logout();
            
            $rt['errcode']=100015;
            $rt['errmsg']='无效的单点登录';
            return redirect('manage/login');
        }
        Auth::loginUsingId($rs_user['id']);
        
        $rs_merchant_id = Auth::user()->merchant_id;
        $rs_user_id = Auth::user()->id;
        if(Auth::user()->is_delete!=1){
            Auth::logout();

            $rt['errcode']=10001;
            $rt['errmsg']='此用户账号已失效';
        }

        $rs_merchant=Merchant::where('id',$rs_merchant_id)->first();
        if($rs_merchant['status']=='-1'){
            Auth::logout();

            $rt['errcode']=10002;
            $rt['errmsg']='此商户状态已失效';
        }
        if( !empty($rs_merchant) && !in_array($rs_merchant['version_id'], array(1,5)) &&date('Y-m-d H:i:s')>=$rs_merchant['expire_time'] ){
            //Auth::logout();
            
            $rt['errcode']=10002;
            $rt['errmsg']='您的账号已过期，请联系代理商进行续费';
            //return $rt;
        }
        Session::put('password_at',Auth::user()->password_at);
        // 日志
        $request['password'] = md5($request['password']);
        $data = array(
            'merchant_id'    => $rs_merchant_id,
            'user_id'    => $rs_user_id,
            'type' => 16,
            'url' => json_encode(array('method'=>'POST','route'=>'auth/login.json')),
            'content' => json_encode(
                    array(
                        'request'=>$request->all(),
                        'decrypt'=>$arr_unserialize
                    )
                ),
            'ip' => get_client_ip(),
            'created_time' => date('Y-m-d H:i:s')
        );
        UserLog::create($data);

        $rt['errcode']=0;
        $rt['errmsg']='登录成功';
        $rt['data']=$this->getGlobal();

        return redirect('manage/');
        
    }
    
    /**
     * SSO:查看账号是否过期
     *  @author：songyongshang@dodoca.com
     */
    public function getAccountEnable(Request $request) {
        //接收数据
        $arr_base64decode = base64_decode($request['token']);
        $arr_decrypt = encrypt($arr_base64decode, 'D', 'dodoca_sso');
        $arr_unserialize = unserialize($arr_decrypt);
        $arr_unserialize = is_object($arr_unserialize)?(array)$arr_unserialize:$arr_unserialize;
        
        // 认证凭证
        // SSO单点登录
        if( !isset($arr_unserialize['merchant_id']) || empty($arr_unserialize['merchant_id']) ){
            $credentials = [
                'username' => $arr_unserialize['username'],
                'mobile' => $arr_unserialize['mobile']
            ];
        }
        
        $rs_user = User::where($credentials)->first();
        if(empty($rs_user)){
            $rt['errcode']=100015;
            $rt['errmsg']='无效的单点登录';
            $rt['data']['enable']=0;
            return $rt;
        }
    
        $rs_merchant_id = Auth::user()->merchant_id;
        $rs_user_id = Auth::user()->id;
        if(Auth::user()->is_delete!=1){
            $rt['errcode']=10001;
            $rt['errmsg']='此用户账号已失效';
            $rt['data']['enable']=0;
            return $rt;
        }
    
        $rs_merchant=Merchant::where('id',$rs_merchant_id)->first();
        if($rs_merchant['status']=='-1'){
            $rt['errcode']=10002;
            $rt['errmsg']='此商户状态已失效';
            $rt['data']['enable']=0;
            return $rt;
        }
        if( !empty($rs_merchant) && !in_array($rs_merchant['version_id'], array(1,5)) &&date('Y-m-d H:i:s')>=$rs_merchant['expire_time'] ){
            $rt['errcode']=10002;
            $rt['errmsg']='您的账号已过期，请联系代理商进行续费';
            $rt['data']['enable']=0;
            return $rt;
        }
    
        $rt['errcode']=0;
        $rt['errmsg']='登录成功';
        $rt['data']['enable']=1;
        return $rt;
    
    }
    
    /**
     * 退出
     */
    public function getLogout(Request $request) {
        if(Auth::user()) {
            // 日志
            $data = array(
                    'user_id'    => Auth::user()->id,
                    'type' => 4,
                    'url' => 'auth/loginout.json',
                    'content' => json_encode($request),
                    'ip' => $request->ip(),
                    'created_time' => date('Y-m-d H:i:s')
                );
            UserLog::create($data);              
            //UserLogevent(new UserLogout(Auth::user()));
        }
        Auth::logout();
        
        $rt['errcode']=0;
        $rt['errmsg']='已退出登录';
        if( ENV('APP_ENV')=='production' ){
            $rt['data']['url'] = 'https://sso.dodoca.com/account/logout';
        }else{
            $rt['data']['url'] = 'https://tsso.dodoca.com/account/logout';
        }
        
        return Response::json($rt);
    }
    
    /**
     * 全局变量
     * @return string[][]|NULL[][]|NULL[]
     */
    function getGlobal(){
        $global = [];
        $global['url'] = [
            'base' => url(),
            'ms'   => env('QINIU_DOMAIN'),
            'static' => env('STATIC_DOMAIN'),
        ];
        $global['is_super'] = isset(Auth::user()->is_super)?Auth::user()->is_super:'';
        $priv = new Priv\PrivController();
        $global['privs'] = $priv->getAllPrivs();
        $global['target_url'] = env('APP_URL').'/manage';
        return $global;
    }
    
    //获取短信验证码
    public function SendsmsMessage(Request $request)
    {
        //dd('d');
        if(empty($request['bind_mobile'])){
            $rt['errcode']=100001;
            $rt['errmsg']='手机号 不能为空';
            return Response::json($rt);
        }
        //dd('c');
        if(empty($request['captcha'])){
            $rt['errcode']=100002;
            $rt['errmsg']='验证码 不能为空';
            return Response::json($rt);
        }else if(!Captcha::check($request['captcha'])){
            $rt['errcode']=100003;
            $rt['errmsg']='验证码 不正确';
            return Response::json($rt);
        }

        $send_sms = new SendMessage();
        if(isset($request['sms_type']) && $request['sms_type']=='sms_withdraw_notice'){
            $sms_content = '您正在进行提现操作，验证码是'.$this->createSmsStr().'，有效期为1小时，请尽快验证。';
        }else if(!isset($request['sms_type']) || $request['sms_type']=='sms_reset_pwd'){
            $sms_content = '您正在重置密码，验证码是'.$this->createSmsStr().'，有效期为1小时，请尽快验证。';
        }
        Session::put('reset_password_mobile',$request['bind_mobile']);
        $send_sms->send_sms($request['bind_mobile'], $sms_content, 1);
        
        $rt['errcode']=0;
        $rt['errmsg']='短信验证码 已发送';
        return Response::json($rt);
    }
    
    //重置密码
    public function resetPwd(Request $request)
    {
        //Session::set('reset_password_validation','2160');
        if(empty($request['smscode'])){
            $rt['errcode']=100001;
            $rt['errmsg']='手机验证码 不能为空';
            return Response::json($rt);
        }else if($request['smscode']!=Session::get('reset_password_validation')){
            $rt['errcode']=100002;
            $rt['errmsg']='手机验证码 不正确';
            return Response::json($rt);
        }else if(date('Y-m-d H:i:s')>Session::get('reset_password_expire')){
            $rt['errcode']=100003;
            $rt['errmsg']='手机验证码 已过期';
            return Response::json($rt);
        }
    
        if(empty($request['password'])){
            $rt['errcode']=100004;
            $rt['errmsg']='设定的新的密码 不能为空';
            return Response::json($rt);
        }else if(empty($request['confirmpassword'])){
            $rt['errcode']=100005;
            $rt['errmsg']='确认新的密码 不能为空';
            return Response::json($rt);
        }else if ($request['password']!=$request['confirmpassword']){
            $rt['errcode']=100006;
            $rt['errmsg']='设定的新的密码和确认新的密码 不相同';
            return Response::json($rt);
        }else{
            $data_User['password']=bcrypt($request['password']);
        }
        
        $slt_User = User::where(['mobile'=>$request['bind_mobile']])->first();
        //echo User;dd();
        //dd($slt_User);
        if(empty($slt_User)){
            $rt['errcode']=100007;
            $rt['errmsg']='手机号码无效';
            return Response::json($rt);
        }
        //dd(Auth::user()->id);
        if(Session::get('reset_password_mobile')!=$request['bind_mobile']){
            $rt['errcode']=100007;
            $rt['errmsg']='请输入正确的手机号码';
            return Response::json($rt);
        }
        $data_User['password_at'] = date('Y-m-d H:i:s');
        $up_User = User::where(['mobile'=>$request['bind_mobile'],'is_delete'=>1])->update($data_User);
        if($up_User){
            Session::put('reset_password_expire',date('Y-m-d H:i:s'));
            Session::put('reset_password_mobile','');
            
            $rt['errcode']=0;
            $rt['errmsg']='密码重置成功';
        }else{
            $rt['errcode']=100009;
            $rt['errmsg']='密码重置失败';
        }
        
        
        
        return Response::json($rt);
    }

    //发送短信
    private function createSmsStr()
    {
        /* 选择一个随机的方案 */
        mt_srand((double) microtime() * 1000000);
        $str = str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
        Session::put('reset_password_expire',date('Y-m-d H:i:s',strtotime('+60 minute')));
        Session::put('reset_password_validation',$str);
        
        return $str;
    }
    
    //日志
    public function getCaptchalog(){
        //dd(file_get_contents(__DIR__.'/../../../../config/captcha.log'));
    }

    //登录到店
    private function getLoginDD($username,$password,$acount='dodoca_pm_cy'){
        //登录失败的尝试登录到店
        if(ENV('APP_ENV')=='production'){
            $url_dd = 'https://xcx.dodoca.com/sso/login';
        }else{
            $url_dd = 'https://txcx.dodoca.com/sso/login';
        }
        $data['phone'] = $username;
        $data['password'] = $password;
        
        $fgc = mxCurl($url_dd,$data);
        //dd($fgc);
        $fgg_jsondecode = json_decode($fgc);
        //dd($fgg_jsondecode);
        return $fgg_jsondecode;
    }
}

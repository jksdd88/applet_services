<?php

namespace App\Http\Controllers\Admin\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserRequest;

use App\Models\User;
use App\Models\Merchant;
use App\Models\Role;
use App\Models\SmsHistory;
use App\Models\VersionPriv;
use App\Models\Priv;
use App\Models\UserPriv;
use App\Models\UserRole;
use App\Models\RolePriv;
use App\Models\Store;
use App\Models\UserLog;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Cache;

use Hash;
use Mews\Captcha\Facades\Captcha;
use \Milon\Barcode\DNS2D;

use App\Utils\SendMessage;
use App\Utils\CacheKey;

use App\Services\AuthService;
use App\Utils\SSOFun;
use function GuzzleHttp\json_encode;

class UserController extends Controller
{
    /**
     * 获取登陆用户信息
     *
     * @return Response
     */
    public function getMe()
    {
        $data = [
            'errcode'=>0,
            'errmsg'=>'获取成功',
            'account' => Auth::user(),
            'merchant' => Merchant::get_data_by_id(Auth::user()->merchant_id)
        ];
        if( isset($data['account']['open_id'])&& !empty($data['account']['open_id']) ){
            $data['account']['open_id'] = 1;
        }else{
            $data['account']['open_id'] = 0;
        }
        $store = Store::get_data_by_id(Auth::user()->store_id, Auth::user()->merchant_id);
        $data['account']['store_name'] = $store['name'];
        $data['account']['whb_token'] = AuthService::createApiToken(Auth::user()->merchant_id,Auth::user()->is_admin);
        
        return Response::json($data);
    }

    /**
     *
     * 获取初始管理员
     */
    public function getInitUser()
    {
        $merchant_id = Auth::user()->merchant_id;
        $user = User::where(array('is_admin'=>1,'merchant_id'=>$merchant_id))->first();
        $user['errcode'] = 0;
        return Response::json($user, 200);
    }

    /**
     * 添加管理员
     */
    public function postUser(UserRequest $request)
    {
        $param = $request->all();
        $initUser = User::where(array('is_admin'=>1,'merchant_id'=>Auth::user()->merchant_id))->first();
        $username = (isset($param['is_admin']) && $param['is_admin'] == 1) ? $param['username'] : $initUser['username'].':'.$param['username'];
        $cuser = User::where('username',$username)->count();
        if($cuser > 0)
        {
            return array('errcode'=>10001,'errmsg'=>'子账号已被占用');
        }
        $mobile = User::where('mobile',$param['mobile'])->count();
        if($mobile > 0){
            return array('errcode'=>10001,'errmsg'=>'手机号已被占用，请更换手机号。');
        }
        
        //SSO:检测用户名
        $request['username'] = $username;
        $rt_ssocheck = json_decode(SSOFun::checkaccount($request,Auth::user()->merchant_id,Auth::user()->id));
        if(empty($rt_ssocheck) || !isset($rt_ssocheck->errcode) || $rt_ssocheck->errcode!=0){
            $rt['errcode']=100027;
            $rt['errmsg']=is_object($rt_ssocheck)?$rt_ssocheck->errmsg:'SSO接口异常';
            return Response::json($rt);
        }
        
        $pass_nocrypt = $request['password'];
        //添加用户
        $data = array(
            'merchant_id'=>Auth::user()->merchant_id,
            'username'=>$initUser['username'].':'.$param['username'],
            'password'=>bcrypt($param['password']),
            'weixin'=>isset($param['weixin']) ? $param['weixin'] : '',
            'mobile'=>$param['mobile'],
            'realname'=>isset($param['realname']) ? $param['realname'] : '',
            'is_admin'=>isset($param['is_admin']) ? $param['is_admin'] : 0,
            'email'=>isset($param['email']) ? $param['email'] : '',
            'gender'=>$param['gender'],
            'store_id'=>isset($param['store_id']) ? $param['store_id'] : '',
            'is_delete'=>1
        );
        DB::beginTransaction();
        try{
            $user = User::create($data);
            //添加用户角色
            if($user && isset($param['role_id']) && $param['role_id'])
            {
                $role_id = explode(',',$param['role_id']);
                $role_id = $this->array_filter_empty($role_id);
                $roleResult = $user->roles()->sync($role_id);
            }

            //添加用户权限
            if(isset($roleResult) && $roleResult)
            {
                $rolePrivs = $this->getMultiRolePrivs($role_id);
                $privs = array_fetch($rolePrivs,'id');
                //$user->privs()->sync($privs);
            }
            //-------------日志 start-----------------
            $data_UserLog['merchant_id']=Auth::user()->merchant_id;
            $data_UserLog['user_id']=Auth::user()->id;
            $data_UserLog['type']=11;
            $data_UserLog['url']='merchant/merchant.json';
            $data_UserLog['content']=json_encode($request->all());
            $data_UserLog['ip']=get_client_ip();
            $data_UserLog['created_time']=date('Y-m-d H:i:s');
            $data_UserLog['updated_time']=date('Y-m-d H:i:s');
            UserLog::insertGetId($data_UserLog);
            //-------------日志 end-----------------
            
            //SSO:注册
            $request['password'] = $pass_nocrypt;
            $request['data_level'] = 2;
            $request['data_id'] = $user['id'];
            $rt_register = json_decode(SSOFun::register($request,Auth::user()->merchant_id,Auth::user()->id));
            if(empty($rt_register) || !isset($rt_register->errcode) || $rt_register->errcode!=0){
                $rt['errcode']=100028;
                $rt['errmsg']=is_object($rt_register)?$rt_register->errmsg:'SSO接口异常';
                return Response::json($rt);
            }
            DB::commit();
        }catch (\Exception $e) {
            $rt['errcode']=100027;
            $rt['errmsg']='开账号 失败';
            return Response::json($rt);
        }
        
        $user['errcode'] = 0;
        $user['errmsg'] = '添加成功';
        return Response::json($user, 200);
    }

    /**
     * 获取当前商户下的所有管理员
     *
     */
    public function getUsers(Request $request)
    {
        $merchant_id = Auth::user()->merchant_id;
        $params = $request->all();
        $offset = isset($params['offset']) ? $params['offset'] : 0;
        $limit = isset($params['limit']) ? $params['limit'] : 20;

        $wheres = array(
            array('column'=>'merchant_id', 'value'=>$merchant_id, 'operator'=>'='),
            array('column'=>'is_delete', 'value'=>1, 'operator'=>'=')
        );
        //按门店搜索
        if(isset($params['store_id']) && !empty($params['store_id'])){
            $where = array(
                array('column'=>'store_id', 'value'=>$params['store_id'], 'operator'=>'='),
            );
            $wheres = array_merge($wheres,$where);
        }
        
        if(isset($params['search']) && $params['search']){
            $where = array(
                array('column'=>'username', 'value'=>"%".$params['search']."%", 'operator'=>'like'),
            );
            $wheres = array_merge($wheres,$where);
        }
        $query = User::query();
        foreach($wheres as $where) {
            $query->where($where['column'], $where['operator'], $where['value']);
        }
        $count = $query->count();
        $query->skip($offset);
        $query->take($limit);
        $users = $query->get();

        if($users)
        {
            foreach($users as $user_key=>$user)
            {
                $user['roles'] = $this->getRoles($user['id']);
                $rs_store = Store::get_data_by_id($user['store_id'], Auth::user()->merchant_id);
                $users[$user_key]['store_name'] = $rs_store['name'];
            }
        }
        $data['errcode'] = 0;
        $data['errmsg'] = '获取成功';
        $data['data'] = $users;
        $data['_count'] = $count;
        return Response::json($data, 200);
    }

    /**
     * 根据id获取单一管理员
     *
     */
    public function getUser(Request $request)
    {
        $userid = $request->id;
        
        $user = $this->fetchUser($userid);
        if(!$user){
            return Response::json(['errcode'=>100002,'errmsg'=>'获取失败1']);
        }
        $user['errcode'] = 0;
        $user['errmsg'] = '获取成功';
        return Response::json($user, 200);
    }

    /**
     * 删除管理员子账号
     * User: lufee(ldw1007@sina.cn)
     */
    public function deleteUser(Request $request)
    {
        $userid = $request->id;
        $merchant_id = Auth::user()->merchant_id;
        $user = $this->fetchUser($userid,false);
        if(empty($user)){
            return ['errcode'=>100003,'errmsg'=>'子账号不存在'];
        }else if($request->id==Auth::user()->id){
            return ['errcode'=>100001,'errmsg'=>'不能删除当前登录账号'];
        }else if($user->is_admin==1){
            return ['errcode'=>100002,'errmsg'=>'超级管理员账号不允许删除'];
        }
        
        $result = User::update_data($userid,array('is_delete'=>-1,'mobile'=>null));
        UserRole::delete_data($userid);
        UserPriv::delete_data($userid);
        if(!empty($result)){
            //SSO:删除帐号
            $arr_user['data_id'] = $merchant_id;
            $arr_user['username'] = $user['username'];
            $arr_user['mobile'] = $user['mobile'];
            $rt_ssocheck = json_decode(SSOFun::deleteaccount($arr_user,Auth::user()->merchant_id,Auth::user()->id));
            if(empty($rt_ssocheck) || !isset($rt_ssocheck->errcode) || $rt_ssocheck->errcode!=0){
                $rt['errcode']=100027;
                $rt['errmsg']=is_object($rt_ssocheck)?$rt_ssocheck->errmsg:'SSO接口异常';
                return Response::json($rt);
            }
        }
        return $result ? Response::json(['errcode'=>0,'errmsg'=>'删除成功']) : ['errcode'=>100001,'errmsg'=>'删除失败'];
    }

    /**
     * 修改管理员
     */
    public function putUser(UserRequest $userRequest,$id)
    {
        $data = $userRequest->all();
        if(!$id){
            $id = Auth::user()->id;
        }
        $cuser = User::where('username',$data['username'])->where('id','!=',$id)->count();
        if($cuser > 0){
            return Response::json(['errcode'=>1000002,'errmsg'=>'参数错误']);
        }
        $merchant_id = Auth::user()->merchant_id;
        $user = User::get_data_by_id($id);
        //删除关联角色表数据，然后更新
        if($user && isset($data['role_id']) && $data['role_id']){
            UserRole::delete_data($id);
            $arr_role = !empty($data['role_id'])?explode(',', $data['role_id']):array();
            foreach ($arr_role as $key=>$val){
                $data_userrole = array();
                $data_userrole['user_id'] = $id;
                $data_userrole['role_id'] = $val;
                UserRole::insert_data($data_userrole);
            }
        }
        
        //初始管理员不允许修改手机
        if(isset($user['is_admin']) && $user['is_admin']==0) {
            $duplication = User::where(function ($query) use ($data) {
                $query->where(['username'=>$data['mobile']])->orWhere(['mobile'=>$data['mobile']]);
            })->where('id','!=',$id)->get();
            if( !$duplication->isEmpty() ){
                $rt['errcode']=100027;
                $rt['errmsg']='此手机号已被注册';
                return Response::json($rt);
            }
            $user->mobile=$data['mobile'];
        }
        
        $user->realname=isset($data['realname']) ? $data['realname'] : '';
        $user->avatar = $data['avatar'];
        $user->email=$data['email'];
        $user->gender=$data['gender'];
        $user->notice_refund=$data['notice_refund'];
        $user->notice_awaiting=$data['notice_awaiting'];
        $user->store_id=isset($data['store_id']) ? $data['store_id'] : '';
        $user->is_check_auth = isset($data['is_check_auth']) ? intval($data['is_check_auth']) : $user['is_check_auth'];
        if(isset($data['password']) && $data['password']){
            if($data['password']!=$data['password_confirmation']){
                $rt['errcode']=100027;
                $rt['errmsg']='两次输入的密码不一致';
                return Response::json($rt);
            }
            
            if(!Hash::check($data['password'],$user->password)){
                //$user->password_at = date('Y-m-d H:i:s');
            }
            $pass_nocrypt = $data['password'];
            $user->password=bcrypt($data['password']);
            $user->password_at=date('Y-m-d H:i:s');
        }
        $res = $user->save();
        if($res) {
            if(isset($data['password']) && $data['password']){
                //SSO:修改账号密码
                $userRequest['data_id'] = Auth::user()->merchant_id;
                $userRequest['password'] = $pass_nocrypt;
                $userRequest['username'] = $user['username'];
                $rt_ssocheck = json_decode(SSOFun::changepasswd($userRequest,Auth::user()->merchant_id,Auth::user()->id));
                if(empty($rt_ssocheck) || !isset($rt_ssocheck->errcode) || $rt_ssocheck->errcode!=0){
                    $rt['errcode']=100027;
                    $rt['errmsg']=is_object($rt_ssocheck)?$rt_ssocheck->errmsg:'SSO接口异常';
                    return Response::json($rt);
                }
                unset($userRequest['username']);
            }
            return Response::json(['errcode'=>0,'errmsg'=>'更新成功']);
        } else {
            return Response::json(['errcode'=>1000003,'errmsg'=>'更新失败']);
        }
    }
    
    /**
     * 核销验证微信:绑定微信
     */
    public function getBindwechatBindURL()
    {
        $rs_user = User::get_data_by_id(Auth::user()->id);
        if( !empty($rs_user['open_id']) ){
            $rt['errcode'] = 100002;
            $rt['errmsg'] = '此账号核销微信已经绑定';
            
            return Response::json($rt);
        }
        
        $token = encrypt(Auth::user()->mobile,'E','BindOpenId');
        $token = str_replace('+', '加', $token);
        $token = str_replace('-', '减', $token);
        $token = str_replace('=', '等', $token);
        $token = str_replace('/', '杠', $token);
        //dd($token);
        $callback_url = ENV('APP_URL').'/admin/user/bindwechatcallbackopenid.json?';//扫描成功后回调小程序的地址
        $callback_url .= 'mobile='.Auth::user()->mobile.'&token='.$token; //回调的参数
        
        $url = 'http://open.dodoca.com/wxauth/index?';//老郭绑定微信的接口
        $url .= 'return_url='.urlencode($callback_url); 
        //dd($url);
        //-------------日志 start-----------------
        $data = array(
            'user_id'    => Auth::user()->id,
            'type' => 52,
            'url' => '',
            'content' => $url,
            'ip' => '',
            'created_time' => date('Y-m-d H:i:s')
        );
        UserLog::create($data);
        //-------------日志 end-----------------
        $rt['errcode'] = 0;
        $rt['errcode'] = '显示绑定二维码';
        $rt['data']['qrcode'] = 'data:image/png;base64,'.DNS2D::getBarcodePNG($url, "QRCODE","10","10");
        
        return Response::json($rt);
    }
    
    /**
     * 核销验证微信:回调接收open_id并保存
     */
    public function getBindwechatCallbackOpenid(Request $request)
    {
        
        //失败地址
        $url_h5 = env('APP_URL').'/wap/destroy/fail?msg=';
        
        //验证mobile
        if(!isset($request['mobile']) || empty($request['mobile'])){
            $rt['errcode']=100001;
            $rt['errmsg']='回调mobile不能为空';
            //return Response::json($rt);
            return redirect($url_h5.urlencode(urlencode($rt['errmsg'])));
        }
        //-------------日志 start-----------------
        $data_userlog = array(
            'user_id'    => $request['mobile'],
            'type' => 53,
            'url' => '',
            'content' => json_encode($request->all()),
            'ip' => '',
            'created_time' => date('Y-m-d H:i:s')
        );
        UserLog::create($data_userlog);
        //-------------日志 end-----------------
        $rs_user = User::where(['mobile'=>$request['mobile']])->first();
        if(empty($rs_user)){
            $rt['errcode']=100002;
            $rt['errmsg']='查不到对应的用户';
            return redirect($url_h5.urlencode(urlencode($rt['errmsg'])));
        }else if( !empty($rs_user['open_id']) ){
            $rt['errcode']=100002;
            $rt['errmsg']='此账号核销微信已绑定';
            return redirect($url_h5.urlencode(urlencode($rt['errmsg'])));
        }
        $user_id = $rs_user['id'];
        //验证token
        if(!isset($request['token']) || empty($request['token'])){
            $rt['errcode']=100003;
            $rt['errmsg']='回调token不能为空';
            return redirect($url_h5.urlencode(urlencode($rt['errmsg'])));
        }
        //dd($request['token']);
        //dd(encrypt($request['token'],'D','BindOpenId'));
        $token = $request['token'];
        $token = str_replace('加','+',  $token);
        $token = str_replace('减','-',  $token);
        $token = str_replace('等','=',  $token);
        $token = str_replace('杠','/',  $token);
        if( $request['mobile']!=encrypt($token,'D','BindOpenId') ){
            $rt['errcode']=100004;
            $rt['errmsg']='回调token不正确';
            return redirect($url_h5.urlencode(urlencode($rt['errmsg'])));
        }
        
        //open_id
        if(!isset($request['wx_open_id']) || empty($request['wx_open_id'])){
            $rt['errcode']=100005;
            $rt['errmsg']='open_id不能为空';
            return redirect($url_h5.urlencode(urlencode($rt['errmsg'])));
        }
        $data['open_id']=encrypt(base64_decode($request['wx_open_id']),'D','OPEN_WXWEB_AUTH');
        //dd($data['open_id']);
        if(!isset($data['open_id']) || empty($data['open_id'])){
            $rt['errcode']=100006;
            $rt['errmsg']='没有获取到open_id';
            return redirect($url_h5.urlencode(urlencode($rt['errmsg'])));
        }
        $rs_User = User::where(['open_id'=>$data['open_id']])->first();
        if(!empty($rs_User)){
            $rt['errcode']=100007;
            $rt['errmsg']='该微信号已绑定过，请更换后重试。';
            return redirect($url_h5.urlencode(urlencode($rt['errmsg'])));
        }
        $res = User::update_data($user_id,$data);
    
        //清User缓存
        $cacheKey = CacheKey::get_user_privs_key($rs_user['merchant_id'],$rs_user['id']);
        Cache::forget($cacheKey);
        
        if($res) {
            //成功地址
            $url_h5 = env('APP_URL').'/wap/destroy/success?';
            
            $rt['errcode']=0;
            $rt['errmsg']='绑定成功';
            return redirect($url_h5.urlencode(urlencode($rt['errmsg'])));
        } else {
            $rt['errcode']=1000008;
            $rt['errmsg']='绑定失败';
            return redirect($url_h5.urlencode(urlencode($rt['errmsg'])));
        }
    }
    
    /**
     * 核销验证微信:解除绑定
     */
    public function putUnbindWechat()
    {
        $data['open_id']='';
        $res = User::update_data(Auth::user()->id,$data);
    
        if($res) {
            return Response::json(['errcode'=>0,'errmsg'=>'更新成功']);
        } else {
            return Response::json(['errcode'=>1000003,'errmsg'=>'更新失败']);
        }
    }
    
    /**
     * 获取用户的权限(权限+角色权限)
     */
    public function getUserPrivs($uid)
    {
        $merchant_id = Auth::user()->merchant_id;
        $userid = $uid ? $uid : Auth::user()->id;
        $privs = array();
        $cacheKey = CacheKey::get_user_privs_key($merchant_id,$userid);
        Cache::forget($cacheKey);
        if(Cache::get($cacheKey))
        {
            $privs = Cache::get($cacheKey);
        }else {
            //用户的权限
            $user = User::where(array('id' => $userid, 'merchant_id' => $merchant_id))->first();
            if ($user) {
                $privs = $this->getPrivCodes($user['id'])->toArray();
            }
            //用户对应的角色
            $user_role = UserRole::get_data_by_id($userid);
            //dd($user_role);
            if(!empty($user_role)){
                foreach ($user_role as $key=>$val){
                    $role_priv = RolePriv::get_data_by_id($val);
                }
            }
            //dd($role_priv);
            if(!empty($role_priv)){
                foreach ($role_priv as $key1=>$val1){
                    $priv_code = Priv::get_PrivCode_by_PrivId($val1);
                    $priv[] = $priv_code['code'];
                }
            }
            if(!empty($priv_code)){
                $privs = array_merge($priv,$privs);
                $privs = array_unique($privs);
            }
            //dd($privs);
            
        }
        $result['privs'] = $privs;
        $result['errcode'] = empty($privs) ? 1000004 : 0;
        $result['errmsg'] = $result['errcode'] == 0 ? '获取成功' : '获取失败';
        return $result;
    }
    
    /**
     * 获取用户的权限(权限)
     */
    public function getUserPriv($uid)
    {
        $merchant_id = Auth::user()->merchant_id;
        $userid = $uid ? $uid : Auth::user()->id;
        $privs = array();
        $cacheKey = CacheKey::get_user_privs_key($merchant_id,$userid);
        Cache::forget($cacheKey);
        if(Cache::get($cacheKey))
        {
            $privs = Cache::get($cacheKey);
        }else {
            //用户的权限
            $user = User::where(array('id' => $userid, 'merchant_id' => $merchant_id))->first();
            if ($user) {
                $privs = $this->getPrivCodes($user['id'])->toArray();
            }
        }
        $result['privs'] = $privs;
        $result['errcode'] = 0;
        $result['errmsg'] = $result['errcode'] == 0 ? '获取成功' : '获取失败';
        return $result;
    }

    /**
     * 获取管理员的权限
     */
    public function getUserLoginPrivs()
    {
        $i=0;
        $log_time = array();
        $log_time[] = $i++." start ".date('Y-m-d H:i:s')."<br >\r\n";
        $merchant_id = Auth::user()->merchant_id;
        //dd($merchant_id);
        $userid = Auth::user()->id;
        $privs = array();
        
        $merchant_info = Merchant::get_data_by_id(Auth::user()->merchant_id);
        if($merchant_info['expire_time']<date('Y-m-d H:i:s')){
            $redis_uprpkey = CacheKey::get_UserprivRolePriv_by_Id(Auth::user()->id,1);
        }else{
            $redis_uprpkey = CacheKey::get_UserprivRolePriv_by_Id(Auth::user()->id,$merchant_info['version_id']);
        }
        Cache::forget($redis_uprpkey);
        $privs = Cache::get($redis_uprpkey);
        
        $arr_privs = array();
        //dd(Auth::user()->is_admin);
        if(Auth::user()->is_admin==1){
            if($merchant_info['expire_time']<date('Y-m-d H:i:s')){
                //dd('a');
                $redis_vpkey = CacheKey::get_VersionPriv_by_VersionId(1);
            }else{
                //dd($merchant_info['version_id']);
                $redis_vpkey = CacheKey::get_VersionPriv_by_VersionId($merchant_info['version_id']);
                //dd($redis_vpkey);
            }
            $data = Cache::get($redis_vpkey);
        }
        //dd($data);
        if( (Auth::user()->is_admin==1&&empty($data)) || !$privs){
            if(Auth::user()->is_admin==1){
                if($merchant_info['expire_time']<date('Y-m-d H:i:s')){
                    $arr_priv_ids = VersionPriv::get_data_by_id(1);
                }else{
                    $arr_priv_ids = VersionPriv::get_data_by_id($merchant_info['version_id']);
                }
                $arr_privs = !empty($arr_priv_ids)?array_merge($arr_privs,$arr_priv_ids):$arr_privs;
                $log_time[] = $i++." admin ".date('Y-m-d H:i:s')."<br >\r\n";
            }else{
                //账号已过期
                if($merchant_info['expire_time']<date('Y-m-d H:i:s')){
                    $arr_priv_ids = array();
                    //用户的权限
                    $arr_priv_ids_obj = VersionPriv::where(['version_priv.version_id'=>5])
                                    ->join('user_priv','user_priv.priv_id','=','version_priv.priv_id')
                                    ->where(['user_priv.user_id'=>$userid])
                                    ->select('version_priv.priv_id')->get();
                    if(!empty($arr_priv_ids_obj)){
                        foreach ($arr_priv_ids_obj as $key=>$val){
                            $arr_priv_ids[]= $val;
                        }
                    }
                    $arr_privs = !empty($arr_priv_ids)?array_merge($arr_privs,$arr_priv_ids):$arr_privs;
                    $log_time[] = $i++." nonad ".date('Y-m-d H:i:s')."<br >\r\n";
                    //用户拥有的角色所对应的权限
                    $user_role = UserRole::get_data_by_id(Auth::user()->id);
                    if(!empty($user_role)){
                        foreach ($user_role as $key=>$val){
                            $arr_rolepriv_ids = array();
                            $arr_rolepriv_ids_obj = VersionPriv::where(['version_priv.version_id'=>5])
                                                ->join('role_priv','role_priv.priv_id','=','version_priv.priv_id')
                                                ->where(['role_priv.role_id'=>$val])
                                                ->select('version_priv.priv_id')->get();
                            if(!empty($arr_rolepriv_ids_obj)){
                                foreach ($arr_rolepriv_ids_obj as $key=>$val){
                                    $arr_rolepriv_ids[]= $val;
                                }
                            }
                            $arr_privs = !empty($arr_rolepriv_ids)?array_merge($arr_privs,$arr_rolepriv_ids):$arr_privs;
                        }
                    }
                    $log_time[] = $i++." userp ".date('Y-m-d H:i:s')."<br >\r\n";
                }
                //账号未过期
                else{
                    //用户的权限
                    $arr_priv_ids = UserPriv::get_data_by_id($userid,Auth::user()->merchant_id);
                    $arr_privs = !empty($arr_priv_ids)?array_merge($arr_privs,$arr_priv_ids):$arr_privs;
                    //dd($arr_priv_ids);
                    //用户拥有的角色所对应的权限
                    $user_role = UserRole::get_data_by_id($userid);
                    if(!empty($user_role)){
                        foreach ($user_role as $key=>$val){
                            $arr_rolepriv_ids = RolePriv::get_data_by_id($val);
                            $arr_privs = !empty($arr_rolepriv_ids)?array_merge($arr_privs,$arr_rolepriv_ids):$arr_privs;
                        }
                    }
                    
                    $log_time[] = $i++." hahaa ".date('Y-m-d H:i:s')."<br >\r\n";
                }
            }
            array_filter($arr_privs);
            //dd($arr_privs);
            $arr_privs = array_unique($arr_privs);
            //dd($arr_priv_ids);
            $privs_all = array();
            if(!empty($arr_privs)){
                foreach ($arr_privs as $key=>$val){
                    $privs_code= Priv::get_PrivCode_by_PrivId($val);
                    $privs_all[] = $privs_code['code'];
                }
            }
            $privs_unique  = array_values(array_unique($privs_all));
            
            foreach ($privs_unique as $key=>$val){
                $privs[] = $val;
            }
            //dd($redis_uprpkey);
            Cache::forget($redis_uprpkey);
            Cache::forever($redis_uprpkey, $privs);
        }
        $log_time[] = $i++." !!!!! ".date('Y-m-d H:i:s')."<br >\r\n";
        $result['privs'] = $privs;
        $result['log_time'] = $log_time;
        $result['errcode'] = 0;
        $result['errmsg'] = $result['errcode'] == 0 ? '获取成功' : '获取失败';
        return $result;
    }
    
    /**
     * 设置管理员权限
     */
    public function putUserPrivs(Request $request,$uid)
    {
        $data = $request->all();
        $merchant_id = Auth::user()->merchant_id;
        $user = User::where(array('merchant_id'=>$merchant_id,'id'=>$uid))->first();
        $privs = explode(',',$data['priv_id']);
        //删除关联权限表数据，然后更新
        if($user)
        {
            $user->privs()->detach();
            $user->privs()->sync($privs);
        }
        $cacheKey = CacheKey::get_user_privs_key($merchant_id,$uid);
        Cache::forget($cacheKey);
        return Response::json(['errcode'=>0,'errmsg'=>'设置成功'], 200);
    }

    /*
     * 修改手机号下一步
     */
    public function postVerifyMobileMessage(Request $request){
        $merchant_id = Auth::user()->merchant_id;
        $params = $request->all();
        $user_id = Auth::user()->id;
        if(!$user_id){
            return array('errcode'=>10000,'errmsg'=>'请登录后修改');
        }
        //旧手机
        $old_mobile = isset($params['old_mobile']) && $params['old_mobile'] ? $params['old_mobile'] : '';
        if($old_mobile==''){
            return Response::json(array('errcode'=>10001,'errmsg'=>'旧手机号 不能为空'));
        }
        //验证码
        $old_code = isset($params['old_code']) && $params['old_code'] ? $params['old_code'] : '';
        if($old_code==''){
            return Response::json(array('errcode'=>10001,'errmsg'=>'验证码 不能为空'));
        }
        $oldMobileVerify = $this->verifyMessage(array('mobile'=>$old_mobile,'check_code'=>$old_code,'type'=>5));
        if(isset($oldMobileVerify['error']['status']) && $oldMobileVerify['error']['status']){
            
            $cache_key = CacheKey::get_verify_mobile_message_key($user_id);
            Cache::put($cache_key, "array('is_next_verify'=>1)", 60); //是否经过第一步验证
            return Response::json(array('errcode'=>0,'errmsg'=>'操作成功'));
        }else{
            return Response::json(['errcode'=>10003,'errmsg'=>$oldMobileVerify['error']['message']]);
        }
    }

    /*
     * 修改手机号
     */
    public function putAdminMobile(Request $request){
        $merchant_id = Auth::user()->merchant_id;
        $user_id = Auth::user()->id;
        $params = $request->all();

        if(!$user_id){
            return Response::json(array('errcode'=>10003,'errmsg'=>'请登录后修改'));
        }
        //旧手机
        $old_mobile = isset($params['old_mobile']) && $params['old_mobile'] ? $params['old_mobile'] : '';
        if($old_mobile==''){
            return Response::json(array('errcode'=>10004,'errmsg'=>'请输入旧手机号'));
        }
        //新手机
        $mobile = isset($params['mobile']) && $params['mobile'] ? $params['mobile'] : '';
        if($mobile==''){
            return Response::json(array('errcode'=>10004,'errmsg'=>'请输入新手机号'));
        }
        $duplication = User::where(function ($query) use ($params) {
            $query->where(['username'=>$params['mobile']])->orWhere(['mobile'=>$params['mobile']]);
        })->where('id','!=',Auth::user()->id)->get();
        if( !$duplication->isEmpty() ){
            $rt['errcode']=100027;
            $rt['errmsg']='此手机号已被注册';
            return Response::json($rt);
        }
        //短信验证码
        $code = isset($params['code']) && $params['code'] ? $params['code'] : '';
        if($code=='' ){
            return Response::json(array('errcode'=>10004,'errmsg'=>'请输入短信验证码'));
        }
        if($old_mobile==$mobile){
            return Response::json(array('errcode'=>10005,'errmsg'=>'旧手机号/新手机号不能相同'));
        }
        //获取用户信息
        $userInfo = User::where('id',$user_id)->where('merchant_id',$merchant_id)->first();
        if(!$userInfo){
            return Response::json(array('errcode'=>10006,'errmsg'=>'用户信息不存在'));
        }
        
        //SSO:换绑手机检测
        $request['data_id'] = Auth::user()->merchant_id;
        $request['username'] = Auth::user()->username;
        $rt_ssocheck = json_decode(SSOFun::checkmobile($request,Auth::user()->merchant_id,Auth::user()->id));
        if(empty($rt_ssocheck) || !isset($rt_ssocheck->errcode) || $rt_ssocheck->errcode!=0){
            $rt['errcode']=100027;
            $rt['errmsg']=is_object($rt_ssocheck)?$rt_ssocheck->errmsg:'SSO接口异常';
            return Response::json($rt);
        }
        unset($request['username']);
        
        //验证是否通过上一步
        $up_cache_key = CacheKey::get_verify_mobile_message_key($user_id);
        $is_up_auth = Cache::get($up_cache_key);

        if(!$is_up_auth){
            return Response::json(array('errcode'=>10007,'errmsg'=>'请输入正确的短信验证码'));
        }
        //验证新手机号
        $mobileVerify = $this->verifyMessage(array('mobile'=>$mobile,'check_code'=>$code,'type'=>2));
        if(isset($mobileVerify['error']['status']) && $mobileVerify['error']['status']){
            //手机号唯一性检测
            $slt_User_mobile=User::where(['mobile'=>$mobile])->first();
            if(!empty($slt_User_mobile)){
                $rt['errcode']=100021;
                $rt['errmsg']='此手机号码已经绑定了账号';
                return Response::json($rt);
            }
            
            //验证通过,修改号码
            $userInfo->is_verification_mobile = 1;
            $userInfo->mobile = $mobile;
            if($userInfo->save()){
                //删除上一步缓存
                Cache::forget($up_cache_key);
                
                //SSO:换绑手机
                $request['data_id'] = Auth::user()->merchant_id;
                $request['username'] = Auth::user()->username;
                $rt_ssobind = json_decode(SSOFun::bindmobile($request,Auth::user()->merchant_id,Auth::user()->id));
                if(empty($rt_ssobind) || !isset($rt_ssobind->errcode) || $rt_ssobind->errcode!=0){
                    $rt['errcode']=100027;
                    $rt['errmsg']=$rt_ssobind->errmsg;
                    return Response::json($rt);
                }
                unset($request['username']);
                
                return Response::json(array('errcode'=>0,'errmsg'=>'操作成功'));
            }else{
                return Response::json(array('errcode'=>10008,'errmsg'=>'操作失败'));
            }
        }else{
            return Response::json(['errcode'=>10009,'errmsg'=>$mobileVerify['error']['message']]);
        }
    }

    /**
     * 发送验证码
     */
    public function sendSmsMessage(Request $request){
        if(empty($request['mobile'])){
            return Response::json(['errcode'=>100001,'errmsg'=>'手机号不能为空']);
        }
        if(empty($request['captcha'])){
            return Response::json(['errcode'=>100002,'errmsg'=>'验证码不能为空']);
        }
        if(!Captcha::check($request['captcha'])){
            return Response::json(['errcode'=>100003,'errmsg'=>'验证码不正确']);
        }
        $send_sms = new SendMessage();
        $sms_content = '您的验证码是'.$this->createSmsStr().'，有效期为1小时，请尽快验证。';

        $send_sms->send_sms($request['mobile'], $sms_content, $request['type']);
        return Response::json(['errcode'=>0,'errmsg'=>'短信验证码已发送']);
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

    //获取单个管理员
    private function fetchUser($userid,$role=true)
    {
        $merchant_id = Auth::user()->merchant_id;
        if(!$userid)
        {
            $userid = Auth::user()->id;
        }
        $user = User::where(array('id'=>$userid,'merchant_id'=>$merchant_id,'is_delete'=>1))->first();
        if($role && $user){
            $user['role'] = $this->getRoles($user['id']);
        }
        return $user;
    }

    //获取当前角色所拥有的权限
    public function getRolePrivs($id)
    {
        return Role::find($id)->privs;
    }

    //获取多个角色的所有权限（不重复）
    public function getMultiRolePrivs($ids)
    {
        $privs = array();
        foreach($ids as $v)
        {
            $temp = $this->getRolePrivs($v)->toArray();
            $privs = array_merge($privs,$temp);
        }
        if($privs)
        {
            $privs = $this->_handleArray2d($privs);
        }
        return $privs;
    }

    /**
     * 获取用户版本信息
     * @return Response
     */
    public function getUserVersion(Request $request)
    {
        if(empty($request['username'])){
            $rt['errcode']=100001;
            $rt['errmsg']='账户 不能为空';
            return Response::json($rt);
        }else if(empty($request['mobile'])){
            $rt['errcode']=100001;
            $rt['errmsg']='手机 不能为空';
            return Response::json($rt);
        }
        $rs_user = User::where(['user.username'=>$request['username'],'user.mobile'=>$request['mobile']])
            ->leftjoin('merchant','merchant.id','=','user.merchant_id')
            ->select('merchant.version_id','merchant.industry_sign')->first();
        if(empty($rs_user)){
            $rt['errcode']=100001;
            $rt['errmsg']='查不到相关信息';
            return Response::json($rt);
        }
        $rt['errcode']=0;
        $rt['errmsg']='获取成功';
        //$rt['data']['industry_sign']=$rs_user['industry_sign'];
        $rt['data']['industry_sign_name']=isset(config('industrysign')[$rs_user['industry_sign']]['name'])?config('industrysign')[$rs_user['industry_sign']]['name']:'';
        //$rt['data']['version_id']=$rs_user['version_id'];
        $rt['data']['version_name']=isset(config('version')[$rs_user['version_id']]['name'])?config('version')[$rs_user['version_id']]['name']:'';
        return Response::json($rt);
    }
    
    /**
     * SSO修改密码
     * Author: songyongshang@dodoca.com
     */
    public function getSSOModifypass(Request $request){
        //接收数据
        if( !isset($request['param']) || empty($request['param']) ){
            $rt['errcode']=100001;
            $rt['errmsg']='修改密码 参数不能为空';
            return Response::json($rt);
        }
    
        $arr_base64decode = base64_decode($request['param']);
        $arr_decrypt = encrypt($arr_base64decode, 'D', 'dodoca_sso');
        $arr_unserialize = unserialize($arr_decrypt);
        //dd($arr_unserialize);
        // 认证凭证
        $credentials = array(
            'username' => $arr_unserialize['username'],
            'mobile' => $arr_unserialize['mobile'],
            'password' => $arr_unserialize['password'],
        );
        if( !isset($credentials['username']) || empty($credentials['username']) ){
            $rt['errcode']=100002;
            $rt['errmsg']='用户名 不能为空';
            return Response::json($rt);
        }else if( !isset($credentials['mobile']) || empty($credentials['mobile']) ){
            $rt['errcode']=100003;
            $rt['errmsg']='手机 不能为空';
            return Response::json($rt);
        }else if( !isset($credentials['password']) || empty($credentials['password']) ){
            $rt['errcode']=100004;
            $rt['errmsg']='新密码 不能为空';
            return Response::json($rt);
        }else if( strlen($credentials['password'])<6 ){
            $rt['errcode']=100004;
            $rt['errmsg']='新密码至少6位';
            return Response::json($rt);
        }
        $data_User['password'] = bcrypt($credentials['password']);
        $data_User['password_at'] = date('Y-m-d H:i:s');
        $rs_User = User::where(['username' => $arr_unserialize['username'],'mobile' => $arr_unserialize['mobile'],'is_delete'=>1])->first();
        if(empty($rs_User)){
            $rt['errcode']=100005;
            $rt['errmsg']='没有查找到此用户';
            return Response::json($rt);
        }
        $rsl_User = User::where(['id'=>$rs_User['id']])->update($data_User);
        //dd($rs_User);
        //-------------日志 start-----------------
        $data = array(
            'user_id'    => $rs_User['id'],
            'type' => 23,
            'url' => 'merchant/merchant.json',
            'content' => json_encode(array(
                'data_original' =>$request['param'],
                'data_decode' =>$credentials,
                'data_rs' =>$rsl_User,
            )),
            'ip' => $request->ip(),
            'created_time' => date('Y-m-d H:i:s')
        );
        UserLog::create($data);
        //-------------日志 end-----------------
    
        if(!$rs_User){
            $rt['errcode']=100006;
            $rt['errmsg']='修改密码 失败';
            return Response::json($rt);
        }
    
        Auth::logout();
    
        $rt['errcode']=0;
        $rt['errmsg']='修改密码 成功';
        return Response::json($rt);
    }
    
    /**
     * SSO登录:兼容旧账号
     * Author: songyongshang@dodoca.com
     */
    public function getSSOOldLogin(Request $request){
        //接收数据
        if( !isset($request['param']) || empty($request['param']) ){
            $rt['errcode']=100001;
            $rt['errmsg']='修改密码 参数不能为空';
            return Response::json($rt);
        }
    
        $arr_base64decode = base64_decode($request['param']);
        $arr_decrypt = encrypt($arr_base64decode, 'D', 'dodoca_sso');
        $arr_unserialize = unserialize($arr_decrypt);
        //dd($arr_unserialize);
        // 认证凭证
        $credentials = array(
            'username' => $arr_unserialize['username'],
            'password' => $arr_unserialize['password'],
        );
        if( !isset($credentials['username']) || empty($credentials['username']) ){
            $rt['errcode']=100002;
            $rt['errmsg']='用户名 不能为空';
            return Response::json($rt);
        }else if( !isset($credentials['password']) || empty($credentials['password']) ){
            $rt['errcode']=100004;
            $rt['errmsg']='密码 不能为空';
            return Response::json($rt);
        }
        //-------------日志 start-----------------
        $data = array(
            'user_id'    => '',
            'type' => 41,
            'url' => 'merchant/merchant.json',
            'content' => json_encode(array(
                'data_original' =>$request['param'],
                'data_decode' =>$credentials,
            )),
            'ip' => $request->ip(),
            'created_time' => date('Y-m-d H:i:s')
        );
        UserLog::create($data);
        //-------------日志 end-----------------
        // 认证凭证
        if (!Auth::validate($credentials)) {
            // 认证凭证
            $credentials = array(
                'mobile' => $arr_unserialize['username'],
                'password' => $arr_unserialize['password'],
            );
        }
        if (Auth::validate($credentials)) {
            Auth::login(Auth::getLastAttempted());
            $rs_merchant_id = Auth::user()->merchant_id;
            $rs_user_id = Auth::user()->id;
            if(Auth::user()->is_delete!=1){
                Auth::logout();
                
                $rt['errcode']=10001;
                $rt['errmsg']='此用户账号已失效';
                return Response::json($rt);
            }
            
            $rs_merchant=Merchant::where('id',$rs_merchant_id)->first();
            if($rs_merchant['status']=='-1'){
                Auth::logout();
                
                $rt['errcode']=10002;
                $rt['errmsg']='此商户状态已失效';
                return Response::json($rt);
            }
            if( !empty($rs_merchant) && !in_array($rs_merchant['version_id'], array(1,5)) &&date('Y-m-d H:i:s')>=$rs_merchant['expire_time'] ){
                Auth::logout();
                
                $rt['errcode']=10002;
                $rt['errmsg']='您的账号已过期，请联系代理商进行续费';
                return $rt;
            }
            $rt['errcode']=0;
            $rt['errmsg']='登录成功';
            $rt['data']['user_id']=$rs_user_id;
            return Response::json($rt);
        } else {
            $rt['errcode']=100001;
            $rt['errmsg']='“用户名”或“密码”错误，请重新登录！';
            $rt['data']='';
            return Response::json($rt);
        }
    }
    
    //手机验证码校验
    public function verifyMessage($data)
    {
        //判断验证码是否正确
        $mobileSms = SmsHistory::where(array('sms_recipient'=>$data['mobile'],'type'=>$data['type']))->orderBy('id','desc')->first();
        if(!$mobileSms)
        {
            return array('error'=>array('status'=>false,'message'=>'验证码无效','code'=>100001));
        }
        if(!preg_match("/".$data['check_code']."/i",$mobileSms['sms_content']))
        {
            return array('error'=>array('status'=>false,'message'=>'验证码不正确','code'=>100003));
        }
        //删除验证码
        SmsHistory::where(array('sms_recipient'=>$data['mobile']))->delete();
        return array('error'=>array('status'=>true));
    }

    private function getRoles($id)
    {
        return User::find($id)->roles;
    }

    private function getStores($id)
    {
        return User::find($id)->store;
    }

    private function getPrivs($id)
    {
        return User::find($id)->privs;
    }

    private function getPrivCodes($id)
    {
        return User::find($id)->privs()->lists('code');
    }

    //去掉重复项
    private function _handleArray2d($array)
    {
        if(!is_array($array))
        {
            return array();
        }

        $tempid = array();
        $result = array();
        foreach($array as $key=>$val)
        {
            if(!in_array($val['id'],$tempid))
            {
                $tempid[] = $val['id'];
                $result[] = $val;
            }
        }
        return $result;
    }

    //去掉数组中值为空的项
    private function array_filter_empty($arr)
    {
        $res = array();
        if(!$arr)
        {
            return $res;
        }
        foreach($arr as $v)
        {
            if($v)
            {
                $res[] = $v;
            }
        }
        return $res;
    }
}

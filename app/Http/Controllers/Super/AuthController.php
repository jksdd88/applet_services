<?php

namespace App\Http\Controllers\Super;

use App\Models\SuperPriv;
use App\Models\SuperRole;
use Validator;
use App\Events\UserLogin;
use App\Events\UserLogout;
use App\Http\Controllers\Controller;

use App\Models\UserLog;
use App\Models\SuperUser;
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
use Hash;
use App\Models\SuperRolePriv;

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
        //设置后台登陆配置
        config(['auth.model' => 'App\SuperUser','auth.table'=>'super_user']);
    }

    public function getLogin(){
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
     *  登录
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

        $validator = Validator::make($input, $rules,$reminder);
        if ( $validator->fails() ) {
            $rt['errcode']=100001;
            $errmsg = $validator->getMessageBag()->toArray();
            $rt['errmsg']=$errmsg['captcha'][0];
            $rt['data']='';
            return Response::json($rt);
        } else {
            // 认证凭证
            $credentials = [
                'username' => Input::get('username'),
                'password' => Input::get('password')
            ];
            $rs_superuser = SuperUser::where(['username'=>Input::get('username')])->first();

            if (Hash::check(Input::get('password'), $rs_superuser['password'])) {
                if($rs_superuser['is_delete']!=1){
                    \Session::flush();
                    $rt['errcode']=10001;
                    $rt['errmsg']='此用户账号已失效';
                    return Response::json($rt);
                }

                Session::put('super_user.id', $rs_superuser['id']);
                Session::put('super_user.username', $rs_superuser['username']);
                Session::put('super_user.mobile', $rs_superuser['mobile']);
                Session::put('super_user.email', $rs_superuser['email']);
                Session::put('super_user.realname', $rs_superuser['realname']);
                Session::put('super_user.avatar', $rs_superuser['avatar']);
                Session::put('super_user.gender', $rs_superuser['gender']);
                Session::put('super_user.is_admin', $rs_superuser['is_admin']);
                Session::put('super_user.super_role_id', $rs_superuser['super_role_id']);

                //Session::put('super_user', 1);


                //记录日志
                $data = array(
                    'user_id'    => Session::get('super_user.id'),
                    'type' => 39,
                    'url' => 'super/auth/login.json',
                    'ip' => $request->ip(),
                    'created_time' => date('Y-m-d H:i:s')
                );
                UserLog::create($data);

                $rt['errcode']=0;
                $rt['errmsg']='登录成功';
                $rt['data']=$this->getGlobal();
                return Response::json($rt);
            } else {
                $rt['errcode']=100001;
                $rt['errmsg']='“用户名”或“密码”错误，请重新登录！';
                $rt['data']='';
                return Response::json($rt);
            }
        }
    }

    public function getLogout(Request $request) {
        if(Session::get('super_user.id')) {
            //日志
            $data = array(
                'user_id'    => Session::get('super_user.id'),
                'type' => 40,
                'url' => 'super/auth/loginout.json',
                'content' => json_encode($request),
                'ip' => $request->ip(),
                'created_time' => date('Y-m-d H:i:s')
            );
            UserLog::create($data);
        }
        Session::forget('super_user.id');
        Session::forget('super_user.username');
        Session::forget('super_user.mobile');
        Session::forget('super_user.email');
        Session::forget('super_user.realname');
        Session::forget('super_user.avatar');
        Session::forget('super_user.gender');
        Session::forget('super_user.is_admin');
        Session::forget('super_user.super_role_id');
        //Auth::logout();
        $rt['errcode']=0;
        $rt['errmsg']='已退出登录';
        return Response::json($rt);
    }

    //获取全局变量
    private function getGlobal(){
        $global = [];
        $global['url'] = [
            'base' => url(),
            'ms'   => env('QINIU_DOMAIN'),
            'static' => env('STATIC_DOMAIN'),
        ];
        return $global;
    }

    //重置密码
    public function resetPwd(Request $request)
    {
        $uid = Session::get('super_user.id');
        if(!$uid){
            $rt['errcode']=100003;
            $rt['errmsg']='登录失效,不能重置操作';
            return Response::json($rt);
        }
        if(empty($request['password'])){
            $rt['errcode']=100004;
            $rt['errmsg']='新密码不能为空';
            return Response::json($rt);
        }else if(empty($request['confirmpassword'])){
            $rt['errcode']=100005;
            $rt['errmsg']='确认新密码不能为空';
            return Response::json($rt);
        }else if ($request['password']!=$request['confirmpassword']){
            $rt['errcode']=100006;
            $rt['errmsg']='设定的新密码和确认新密码不相同';
            return Response::json($rt);
        }else{
            $data_User['password']=bcrypt($request['password']);
        }
        $result = SuperUser::where(['id'=>$uid,'is_delete'=>1])->update($data_User);
        if($result){
            $rt['errcode']=0;
            $rt['errmsg']='密码重置成功';
        }else{
            $rt['errcode']=100009;
            $rt['errmsg']='密码重置失败';
        }
        return Response::json($rt);
    }

    public function getUserinfo(Request $request)
    {
        $rt['data']=SuperUser::get_data_by_id(Session::get('super_user.id'));

        $rt['errcode']=0;
        $rt['errmsg']='获取商户信息 成功';
        return Response::json($rt);
    }

    //获取当前用户角色权限
    public function getSuperUserprivs(Request $request){
        //查询角色ID
        $role_id = SuperRole::select('is_delete')->where('id','=',Session::get('super_user.super_role_id'))->get()->toArray();

        if(!empty($role_id)){

            if($role_id[0]['is_delete'] == 1){

                $Roles = SuperRolePriv::where(['super_role_priv.super_role_id'=>Session::get('super_user.super_role_id')])
                    ->leftjoin('super_role','super_role.id','=','super_role_priv.super_role_id')
                    ->leftjoin('super_priv','super_priv.id','=','super_role_priv.priv_id')
                    ->select('super_priv.code')->get()->toArray();
                $rt['errcode']=0;
                $rt['errmsg']='获取当前用户权限 成功';
                if($Roles){
                    foreach ($Roles as $key=>$val){

                        $code[] = $val['code'];
                    }
                    $rt['data'] = $code;
                }else{

                    $parent_codes = SuperPriv::select('*')->where("code",'=','statistics')->get()->toArray();

                    $parentdata['super_role_id'] = Session::get('super_user.super_role_id');

                    $parentdata['priv_id'] = $parent_codes[0]['id'];

                    SuperRolePriv::insert($parentdata);

                    $code[] = $parent_codes[0]['code'];

                    $child_codes = SuperPriv::select('*')->where("parent_id",'=',$parent_codes[0]['id'])->get()->toArray();

                    foreach ($child_codes as $key=>$val){

                        $childdata['super_role_id'] = Session::get('super_user.super_role_id');

                        $childdata['priv_id'] = $val['id'];

                        SuperRolePriv::insert($childdata);

                        $code[]  = $val['code'];
                    }

                    $rt['data'] = $code;
                }
            }else{

                SuperRolePriv::where('super_role_id','=',Session::get('super_user.super_role_id'))->delete();

                $rt['errcode']=0;

                $rt['errmsg']='获取当前用户权限 成功';

                $parent_codes = SuperPriv::select('*')->where("code",'=','statistics')->get()->toArray();

                $parentdata['super_role_id'] = Session::get('super_user.super_role_id');

                $parentdata['priv_id'] = $parent_codes[0]['id'];

                SuperRolePriv::insert($parentdata);

                $code[] = $parent_codes[0]['code'];

                $child_codes = SuperPriv::select('*')->where("parent_id",'=',$parent_codes[0]['id'])->get()->toArray();

                foreach ($child_codes as $key=>$val){

                    $childdata['super_role_id'] = Session::get('super_user.super_role_id');

                    $childdata['priv_id'] = $val['id'];

                    SuperRolePriv::insert($childdata);

                    $code[]  = $val['code'];
                }

                $rt['data'] = $code;
            }
        }else{

            $rt['errcode'] = 100016;

            $rt['errmsg'] = '请重新登录！';

        }



        return Response::json($rt);
    }

    public function getCodePriv(Request $request){

        $super_role_id = Session::get('super_user.super_role_id');

        $code = $request->code;

        $res = SuperPriv::where('code','=',$code)->get()->toArray();

        if($res[0]['parent_id'] == 0){   //一级权限

            $second_privs = SuperPriv::where('parent_id','=',$res[0]['id'])->orderby('id','asc')->get();   //查询其所有二级权限

            $second_count = SuperPriv::where('parent_id','=',$res[0]['id'])->count();

            $count = 0;
            foreach ($second_privs as $key=>$val){

                $role_sec = SuperRolePriv::where(array('super_role_id'=>$super_role_id,'priv_id'=>$val->id))->get()->toArray();   //查询是否存在所有二级权限

                if(!empty($role_sec)){          //如果存在，则返回

                    $rt['errcode']=0;
                    $rt['errmsg']='获取权限 成功';
                    $rt['data'] = $val->code;

                    return Response::json($rt);

                    break;
                }else{

                    $count ++;
                }
            }

            if($count == $second_count){

                $rt['errcode']=10001;
                $rt['errmsg']='暂无任何二级目录';

                return Response::json($rt);
            }

        }else{

            $rt['errcode']=0;
            $rt['errmsg']='获取权限 成功';
            $rt['data'] = $res[0]['code'];

            return Response::json($rt);

        }
    }
}

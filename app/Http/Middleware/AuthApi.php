<?php namespace App\Http\Middleware;

//use App\Services\AdminService;
use Closure;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\UserPriv;
use App\Models\UserRole;
use App\Models\Merchant;
use App\Models\VersionPriv;
use App\Models\Priv;
use App\Models\RolePriv;

class AuthApi {

    /**
     * The Guard implementation.
     *
     * @var Guard
     */
    protected $auth;

    /**
     * Create a new filter instance.
     *
     * @param  Guard  $auth
     * @return void
     */
    public function __construct(Guard $auth)
    {
        //dd('a');
        $this->auth = $auth;
        //$this->AdminService = $adminService;
    }
	/**
	 * Handle an incoming request.
	 *
	 * @param  \Illuminate\Http\Request  $request
	 * @param  \Closure  $next
	 * @return mixed
	 */
    public function handle($request, Closure $next,$priv_code='') {
        //dd($priv_code);
        if ($this->auth->guest()) {
            $rt['errcode'] = '111111';
            $rt['errmsg'] = 'RedirctLogin';
            $rt['data'] = '';
            return Response::json($rt,401);
        }
        if( Session::get('password_at')!=Auth::user()->password_at ){
            $rt['errcode'] = '111111';
            $rt['errmsg'] = 'RedirctLogin';
            $rt['data'] = '';
            return Response::json($rt,401);
        }
        if(!empty($priv_code)){
            $userPrivs = $this->getPrivs($priv_code);
            //dd($userPrivs);
            if ($userPrivs['errcode']=='no_priv' ) {
                return $userPrivs;
            }
        }
        
        return $next($request);
    }

    function getPrivs($priv_code='') {
        //校验权限
        if(empty($priv_code)){
            return true;
        }
        
        //查询priv.code对应的priv.id
        $priv_id = Priv::get_id_by_code($priv_code);
        //dd($priv_id);
        if(empty($priv_id)){
            $rt['errcode'] = 'no_priv';
            $rt['errmsg'] = '查询不到此权限';
            $rt['data'] = '';
            return $rt;
        }
        
        // 1 商户使用的版本所拥有的权限
        // 1.1 商户的版本
        $merchant_info = Merchant::get_data_by_id(Auth::user()->merchant_id);
        if(empty($merchant_info['version_id'])){
            $rt['errcode'] = 'no_priv';
            $rt['errmsg'] = '此商户的版本有问题';
            $rt['data'] = '';
            return $rt;
        }
        // 1.2 版本对应的权限列表
        $version_priv = VersionPriv::get_data_by_id($merchant_info['version_id']);
        // 1.3 商户版本是否包含此权限
        if(!in_array($priv_id,$version_priv)){
            $rt['errcode'] = 'no_priv';
            $rt['errmsg'] = '您正在使用的版本尚未开通此功能，如需开通请联系您的销售顾问';
            $rt['data'] = '';
            return $rt;
        }else if(Auth::user()->is_admin && in_array($priv_id,$version_priv)){
            $rt['errcode'] = 'has_priv';
            $rt['errmsg'] = '';
            $rt['data'] = '';
            return $rt;
        }
        // 1.4 免费版对应的权限列表
        $free_version_priv = VersionPriv::get_data_by_id(5);
        // 1.5 商户账号过期,只能使用免费版的有限权限,免费版是否包含此权限
        if($merchant_info['expire_time'] < date('Y-m-d H:i:s')){
            if(!in_array($priv_id,$free_version_priv)){
                $rt['errcode'] = 'no_priv';
                $rt['errmsg'] = '此账号已过期,只有免费版权限,高级功能请联系您的销售顾问';
                $rt['data'] = '';
                return $rt;
            }
        }
        
        // 2 用户是否有此权限
        $user_priv = UserPriv::get_data_by_id(Auth::user()->id,Auth::user()->merchant_id);
        if(!empty($user_priv) && in_array($priv_id,$user_priv)){
            $rt['errcode'] = 'has_priv';
            $rt['errmsg'] = '';
            $rt['data'] = '';
            return $rt;
        }
        
        // 3 用户角色是否有此权限
        // 3.1 用户角色
        $user_role = UserRole::get_data_by_id(Auth::user()->id);
        //dd($user_role);
        if(!empty($user_role)){
            foreach ($user_role as $key=>$val){
                // 3.2 角色权限
                $role_priv=array();
                $role_priv = RolePriv::get_data_by_id($val);
                if(!empty($role_priv) && in_array($priv_id,$role_priv)){
                    $rt['errcode'] = 'has_priv';
                    $rt['errmsg'] = '';
                    $rt['data'] = '';
                    return $rt;
                }
            }
            $rt['errcode'] = 'no_priv';
            $rt['errmsg'] = '您没有权限访问,请联系管理员开通后再使用该功能.';
            $rt['data'] = '';
            return $rt;
        }
        
        $rt['errcode'] = 'no_priv';
        $rt['errmsg'] = '您没有权限访问,请联系管理员开通后再使用该功能!';
        $rt['data'] = '';
        return $rt;
    }

}


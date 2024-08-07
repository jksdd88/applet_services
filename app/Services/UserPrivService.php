<?php
/**
 * 用户权限
 * Date: 2017-08-31
 * Time: 15:20
 */
namespace App\Services;

use App\Models\User;
use App\Models\Merchant;
use App\Models\Role;
use App\Models\VersionPriv;
use App\Models\Priv;
use App\Models\UserPriv;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Cache;
use App\Utils\CacheKey;
use App\Models\UserRole;
use App\Models\RolePriv;

class UserPrivService {

/**
     * 获取管理员的权限
     */
    static function getUserPrivs($uid=0)
    {
        $merchant_id = Auth::user()->merchant_id;
        //dd($merchant_id);
        if(empty($uid)){
            $userid = Auth::user()->id;
        }else{
            $userid = $uid;
        }
        
        $privs = array();
        $cacheKey = CacheKey::get_user_privs_key($merchant_id,$userid);
        Cache::forget($cacheKey);
        if(Cache::get($cacheKey))
        {
            $privs = Cache::get($cacheKey);
        }else {
            if(Auth::user()->is_admin==1){
                //dd('a');
                $merchant_info = Merchant::get_data_by_id(Auth::user()->merchant_id);
                $arr_privs = VersionPriv::get_data_by_id($merchant_info['version_id']);
                if(!empty($arr_privs)){
                    foreach ($arr_privs as $key=>$val){
                        $privs_code= Priv::get_PrivCode_by_PrivId($val);
                        $privs[] = $privs_code['code'];
                    }
                }
            }else{
                //用户的权限
                $user = User::where(array('id' => $userid, 'merchant_id' => $merchant_id))->first();
                if ($user) {
                    $privs = User::find($user['id'])->privs()->lists('code')->toArray();
                }
                //用户拥有的角色所对应的角色
                $user_role = UserRole::get_data_by_id(Auth::user()->id);
                if(!empty($user_role)){
                    foreach ($user_role as $key=>$val){
                        $role_priv = RolePriv::get_data_by_id($val);
                    }
                }
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
            Cache::forever($cacheKey, $privs);
        }

        return $privs;
    }
    
    /**
     * 获取管理员的权限
     */
    static function getHexiaoPriv($user_id,$merchant_id,$is_admin,$priv_code='') {
        //校验权限
        if(empty($priv_code)){
            return false;
        }
        if(empty($user_id)){
            return false;
        }
        if(empty($merchant_id)){
            return false;
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
        $merchant_info = Merchant::get_data_by_id($merchant_id);
        //dd($merchant_info);
        if(empty($merchant_info['version_id'])){
            $rt['errcode'] = 'no_priv';
            $rt['errmsg'] = '此商户的版本有问题';
            $rt['data'] = '';
            return $rt;
        }
        // 1.2 版本对应的权限列表
        $version_priv = VersionPriv::get_data_by_id($merchant_info['version_id']);
        //dd($is_admin);
        // 1.3 商户版本是否包含此权限
        if(!in_array($priv_id,$version_priv)){
            $rt['errcode'] = 'no_priv';
            $rt['errmsg'] = '您正在使用的版本尚未开通此功能，如需开通请联系您的销售顾问';
            $rt['data'] = '';
            return $rt;
        }else if($is_admin && in_array($priv_id,$version_priv)){
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
        $user_priv = UserPriv::get_data_by_id($user_id,$merchant_id);
        if(!empty($user_priv) && in_array($priv_id,$user_priv)){
            $rt['errcode'] = 'has_priv';
            $rt['errmsg'] = '';
            $rt['data'] = '';
            return $rt;
        }
    
        // 3 用户角色是否有此权限
        // 3.1 用户角色
        $user_role = UserRole::get_data_by_id($user_id);
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
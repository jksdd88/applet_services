<?php namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Utils\CacheKey;
use Cache;


class UserRole extends Model {

    protected $table = 'user_role';
    protected $fillable = ['user_id','role_id'];
    
    protected $guarded = ['id'];

    
    public $timestamps = false;

    /**
     * 插入一条记录
     * @return id
     */
    static function insert_data($data){
        return self::insertGetId($data);
    }
    /**
     * demo 角色对应的权限列表
     * @return array
     */
    static function get_data_by_id($user_id)
    {
        if(!$user_id || !is_numeric($user_id))return;
    
        $key = CacheKey::get_UserRole_by_UserId($user_id);
        $data = Cache::get($key);
        //Cache::forget($key);
        if(!$data)
        {
            $data_cache = self::query()
                    ->leftjoin('role','role.id','=','user_role.role_id')
                    ->where(['user_role.user_id'=>$user_id,'role.is_delete'=>1])
                    ->where(['user_role.user_id'=>$user_id])->select('user_role.role_id')->get();
            if(!empty($data_cache)){
                foreach ($data_cache as $key=>$val){
                    $data[] = $val['role_id'];
                }
            }
            if($data)
            {
                Cache::put($key, $data, 180);
            }
        }
        return $data;
    }
    
    /**
     * 删除用户角色
     * @return int| 删除条数
     */
    static function delete_data($user_id)
    {
        if(!$user_id || !is_numeric($user_id))return;
    
        //用户角色
        $key = CacheKey::get_UserRole_by_UserId($user_id);
        Cache::forget($key);
        
        $rs_user = User::get_data_by_id($user_id);
        $merchant_info = Merchant::get_data_by_id($rs_user['merchant_id']);
        //商户过期 用户权限+商户过期 角色权限
        if($merchant_info['expire_time']<date('Y-m-d H:i:s')){
            $key = CacheKey::get_UserprivRolePriv_by_Id($user_id,1);
        }else{
            //商户未过期 用户权限+商户未过期 角色权限
            $key = CacheKey::get_UserprivRolePriv_by_Id($user_id,$merchant_info['version_id']);
        }
        Cache::forget($key);
        
        return self::query()->where('user_id','=',$user_id)->delete();
    }
}
<?php

namespace App\Models;

use Illuminate\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use App\Utils\CacheKey;
use Illuminate\Support\Facades\Cache;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Foundation\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;

class User extends Model implements AuthenticatableContract,
                                    AuthorizableContract,
                                    CanResetPasswordContract
{
    use Authenticatable, Authorizable, CanResetPassword;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'user';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['merchant_id','username','password','weixin','mobile','email','is_admin','realname','is_delete','store_id'];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = ['password', 'remember_token'];
    const CREATED_AT = 'created_time';
    const UPDATED_AT = 'updated_time';

    /**
     * 插入一条记录
     * @return id
     */
    static function insert_data($data){
        $data['created_time'] = date('Y-m-d H:i:s');
        $data['updated_time'] = date('Y-m-d H:i:s');
        return self::insertGetId($data);
    }

    /**
     * demo 查询一条记录
     * @return array
     */

    static function get_data_by_id($id, $fields = '*')
    {
        if(!$id || !is_numeric($id))return;

        $key = CacheKey::get_user_by_id($id);
        $data = Cache::get($key);
        //Cache::forget($key);
        if(!$data){
            $data = self::query()->select(\DB::raw($fields))->where('id','=',$id)->first();
            if($data){
                Cache::put($key, $data,180);
            }
        }
        
        return $data;
    }
    
    /**
     * demo 查询一条记录
     * @return array
     */
    
    static function get_user_by_merchantid($merchant_id, $fields = '*')
    {
        if(!$merchant_id || !is_numeric($merchant_id))return;
    
        $key = CacheKey::get_merchantuser_by_merchantid($merchant_id);
        $data = Cache::get($key);
        //Cache::forget($key);
        if(!$data){
            $data = self::query()->select(\DB::raw($fields))->where('merchant_id','=',$merchant_id)->get();
            if($data){
                Cache::put($key, $data);
            }
        }
    
        return $data;
    }

    /**
     * demo 修改一条记录
     * @return int|修改成功条数
     */

    static function update_data($id ,$data)
    {
        if(!$id || !is_numeric($id))return;
        
        $key = CacheKey::get_user_by_id($id);
        Cache::forget($key);
        
        $data['updated_time'] = date('Y-m-d H:i:s');
        return self::query()->where('id','=',$id)->update($data);

    }

    /**
     * demo 删除一条记录
     * @return int|删除条数
     */

    static function delete_data($id)
    {
        if(!$id || !is_numeric($id))return;

        $key = CacheKey::get_user_by_id($id);
        Cache::forget($key);
        
        $data['is_delete'] = -1;
        $data['updated_time'] = date('Y-m-d H:i:s');

        return self::query()->where('id','=',$id)->update($data);
    }

    /**
     * count记录条数
     * @return int|count
     */
    static function get_data_count($wheres=array())
    {
        $query = self::query();
        foreach($wheres as $where) {
            $query->where($where['column'], $where['operator'], $where['value']);
        }
        return $query->count();
    }

    /**
     * 查询多条记录
     * @return array
     */
    static function get_data_list($wheres=array(), $fields = '*', $offset = 0, $limit = 10)
    {
        $query = self::query();
        foreach($wheres as $where) {
            $query->where($where['column'], $where['operator'], $where['value']);
        }
        $data = $query->select(\DB::raw($fields))->skip($offset)->take($limit)->get();
        return json_decode($data,true);
    }

    //获取关联角色
    public function roles()
    {
        return $this->belongsToMany('App\Models\Role', 'user_role', 'user_id', 'role_id');
    }

    //获取关联权限
    public function privs()
    {
        return $this->belongsToMany('App\Models\Priv', 'user_priv', 'user_id', 'priv_id');
    }
}

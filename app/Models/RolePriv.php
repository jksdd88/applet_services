<?php namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use App\Utils\CacheKey;
use Cache;

class RolePriv extends Model {

    protected $table = 'role_priv';
    protected $fillable = ['role_id', 'priv_id'];
    const CREATED_AT = 'created_time';
    const UPDATED_AT = 'updated_time';

    /**
     * 插入一条记录
     * @return id
     */
    static function insert_data($data)
    {
        $data['created_time'] = date('Y-m-d H:i:s');
        $data['updated_time'] = date('Y-m-d H:i:s');
        return self::insertGetId($data);
    }

    /**
     * demo 查询一条记录
     * @return array
     */
    static function get_data_by_id($role_id)
    {
        if(!$role_id || !is_numeric($role_id))return;
    
        $key = CacheKey::get_RolePriv_by_RoleId($role_id);
        $data = Cache::get($key);
        Cache::forget($key);
        if(!$data)
        {
            $data_cache = self::query()->where(['role_id'=>$role_id])->select('priv_id')->get();
            if(!empty($data_cache)){
                foreach ($data_cache as $key=>$val){
                    $data[] = $val['priv_id'];
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
     * demo 查询一条记录
     * @return array
     */
    static function get_data_by_user_role($arr_user_roles)
    {
        if(!$arr_user_roles || empty($arr_user_roles))return;
    
        $key = CacheKey::get_RolePriv_by_RoleId(Auth::user()->id);
        $data = Cache::get($key);
        Cache::forget($key);
        if(!$data)
        {
            $data_cache = self::query()->whereIn('role_id',$arr_user_roles)->select('priv_id')->get();
            if(!empty($data_cache)){
                foreach ($data_cache as $key=>$val){
                    $data[] = $val['priv_id'];
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
     * demo 修改一条记录
     * @return int|修改成功条数
     */

    static function update_data($id ,$data)
    {
        if(!$id || !is_numeric($id))return;

        $this->delete_priv($id);
        
        $data['updated_time'] = date('Y-m-d H:i:s');
        return self::query()->where(['id'=>$id])->update($data);

    }

    /**
     * demo 删除一条记录
     * @return int|删除条数
     */

    static function delete_data($id)
    {
        if(!$id || !is_numeric($id))return;

        $this->delete_priv($id);
        
        $data['is_delete'] = -1;
        $data['updated_time'] = date('Y-m-d H:i:s');

        return self::query()->where(['id'=>$id])->update($data);
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

    //删除相应的商户下的用户权限
    static function delete_priv($role_id){
        $key = CacheKey::get_RolePriv_by_RoleId($role_id);
        Cache::forget($key);

        $rs_role = Role::get_data_by_id($role_id);
        $rs_merchant = Merchant::get_data_by_id($rs_role['merchant_id']);
        $rs_user = User::get_user_by_merchantid($rs_role['merchant_id']);
        if(!empty($rs_user)){
            foreach ($rs_user as $key=>$val){
                //商户过期 用户权限+商户过期 角色权限
                $key = CacheKey::get_UserprivRolePriv_by_Id($val['id'],1);
                Cache::forget($key);
                //商户未过期 用户权限+商户未过期 角色权限
                $key = CacheKey::get_UserprivRolePriv_by_Id($val['id'],$rs_merchant['version_id']);
                Cache::forget($key);
            }
        }
    }
}

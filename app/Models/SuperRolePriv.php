<?php namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Utils\CacheKey;
use Illuminate\Support\Facades\Cache;

class SuperRolePriv extends Model {

    protected $table = 'super_role_priv';
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
     * demo 删除一条记录
     * @return int|删除条数
     */

    static function delete_data($id)
    {
        if(!$id || !is_numeric($id))return;

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

    /**
     * 修改版本权限
     * @return array
     */

    static function update_data($super_role_id,$priv_id,$op)
    {
        if(!$super_role_id || !is_numeric($super_role_id))return;
        if(!$priv_id || !is_numeric($priv_id))return;

        $key = CacheKey::get_SuperRolePriv_by_RoleId($super_role_id);
        Cache::forget($key);
        if($op=='strike'){
            return self::query()->where(['super_role_id'=>$super_role_id,'priv_id'=>$priv_id])->delete();
        }else if($op=='add'){
            $data['super_role_id'] = $super_role_id;
            $data['priv_id'] = $priv_id;
            return self::insertGetId($data);
        }

    }

    /**
     * 查询一条记录
     * @return array
     */

    static function get_data_by_id($super_role_id)
    {
        if(!$super_role_id || !is_numeric($super_role_id))return;

        $key = CacheKey::get_SuperRolePriv_by_RoleId($super_role_id);
        $data = Cache::get($key);
        //dd($data);
        //Cache::forget($key);
        if(!$data)
        {
            $data_cache = self::query()->where(['super_role_id'=>$super_role_id])->get();
            //dd($data);
            if(!empty($data_cache)){
                foreach ($data_cache as $key=>$val){
                    $data[] = $val['priv_id'];
                }
            }

            if(!empty($data))
            {
                Cache::put($key, $data, 60);
            }
        }
        return $data;
    }


}
<?php 

/**
 * 门店表MOD
 * @author wangshen@dodoca.com
 * @cdate 2017-10-23
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Utils\CacheKey;
use Illuminate\Support\Facades\Cache;

class Store extends Model {

    protected $table = 'store';
    protected $guarded = ['id'];
    
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
     * 查询一条记录
     * @return array
     */
    static function get_data_by_id($id,$merchant_id)
    {
        if(!$id || !is_numeric($id))return;
        if(!$merchant_id || !is_numeric($merchant_id))return;
    
        $key = CacheKey::get_store_by_id_key($id,$merchant_id);
        $data = Cache::get($key);
        Cache::forget($key);
        if(!$data)
        {
            $data = self::query()->where('id','=',$id)
                                ->where('merchant_id','=',$merchant_id)
                                ->first();
    
            if($data)
            {
                $key = CacheKey::get_store_by_id_key($id,$merchant_id);
                Cache::put($key, $data, 60);
            }
    
        }
    
        return $data;
    
    }
    
    /**
     * 修改一条记录
     * @return int|修改成功条数
     */
    static function update_data($id,$merchant_id,$data)
    {
        if(!$id || !is_numeric($id))return;
        if(!$merchant_id || !is_numeric($merchant_id))return;
    
        $key = CacheKey::get_store_by_id_key($id,$merchant_id);
        Cache::forget($key);
    
        $data['updated_time'] = date('Y-m-d H:i:s');
        return self::query()->where('id','=',$id)->where('merchant_id','=',$merchant_id)->update($data);
    
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
    static function get_data_list($wheres=array(), $fields = '*', $offset = 0, $limit = 10, $orderby_field = 'id', $orderby_type = 'ASC')
    {
        $query = self::query();
        foreach($wheres as $where) {
            $query->where($where['column'], $where['operator'], $where['value']);
        }
        $data = $query->select(\DB::raw($fields))->skip($offset)->take($limit)->orderBy($orderby_field, $orderby_type)->get();
        return json_decode($data,true);
    }
    
    
    /**
     * 查询多条记录全部
     * @return array
     */
    static function get_data_list_all($wheres=array(), $fields = '*', $orderby_field = 'id', $orderby_type = 'ASC')
    {
        $query = self::query();
        foreach($wheres as $where) {
            $query->where($where['column'], $where['operator'], $where['value']);
        }
        $data = $query->select(\DB::raw($fields))->orderBy(\DB::raw($orderby_field),$orderby_type)->get();
        return json_decode($data,true);
    }
    
    
}
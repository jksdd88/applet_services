<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Utils\CacheKey;
use Cache;

class Member extends Model
{
    /**
     * 与模型关联的数据表。
     *
     * @var string
     */
    protected $table = 'member';

    /**
     * 指定是否模型应该被戳记时间。
     *
     * @var bool
     */
    public $timestamps = false;


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

    static function get_data_by_id($id, $merchant_id)
    {
        if(!$id || !is_numeric($id))return;
        if(!$merchant_id || !is_numeric($merchant_id))return;

        $key = CacheKey::get_member_by_id_key($id, $merchant_id);
        $data = Cache::get($key);
        if(!$data)
        {
            $data = self::query()->where('id','=',$id)->first();

            if($data)
            {
                Cache::add($key, $data, 120);
            }
        }
        return $data;

    }
    
    /**
     * 查询一条记录
     * @return array
     */

    static function get_data_by_openid($public_open_id, $merchant_id)
    {
        if(!$public_open_id)return;
        if(!$merchant_id || !is_numeric($merchant_id))return;       
        $data = self::query()->where('public_open_id','=',$public_open_id)->where('merchant_id','=',$merchant_id)->first();    
        return $data;

    }

    /**
     * demo 修改一条记录
     * @return int|修改成功条数
     */

    static function update_data($id ,$merchant_id, $data)
    {
        if(!$id || !is_numeric($id))return;
        if(!$merchant_id || !is_numeric($merchant_id))return;

        $key = CacheKey::get_member_by_id_key($id, $merchant_id);
        Cache::forget($key);

        $data['updated_time'] = date('Y-m-d H:i:s');
        return self::query()->where('id','=',$id)->update($data);
    }

    /**
     * 根据条件更新一条数据
     * @return int|修改成功条数
     */

    static function update_data_by_where($id ,$merchant_id, $wheres = [], $data)
    {
        if(!$merchant_id || !is_numeric($merchant_id))return;       
        if(is_array($id)){
            foreach ($id as $ke=> $va) {
                $key = CacheKey::get_member_by_id_key($va, $merchant_id);
                Cache::forget($key);
            }
            
        }
        else{
            $key = CacheKey::get_member_by_id_key($id, $merchant_id);
            Cache::forget($key);
        }

        $query = self::query();
        foreach($wheres as $where) {
            if($where['operator'] == 'in'){
                $query->whereIn($where['column'],$where['value']);
            }else{
                $query->where($where['column'], $where['operator'], $where['value']);
            }
            
        }

        $data['updated_time'] = date('Y-m-d H:i:s');
        return $query->update($data);
    }

    /**
     * demo 删除一条记录
     * @return int|删除条数
     */

    static function delete_data($id)
    {
        if(!$id || !is_numeric($id))return;
        if(!$merchant_id || !is_numeric($merchant_id))return;

        $key = CacheKey::get_member_by_id_key($id, $merchant_id);
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
        $data = $query->select(\DB::raw($fields))->skip($offset)->take($limit)->orderBy('id', 'desc')->get();
        return json_decode($data,true);
    }
	
	/**
     * 清除缓存
	 * @author zhangchangchun@dodoca.com
     */
    static function forgetCache($id, $merchant_id)
    {
        if (!$id || !is_numeric($id)) return;
        if (!$merchant_id || !is_numeric($merchant_id)) return;
		
        $key = CacheKey::get_member_by_id_key($id, $merchant_id);
        Cache::forget($key);
		return true;
    }

    /**
     * 递增
     * @author 王禹
     * @return int|成功条数
     */

    static function increment_data($id ,$merchant_id ,$field ,$val)
    {
        if(!$id || !is_numeric($id))return;
        if(!$merchant_id || !is_numeric($merchant_id))return;

        self::forgetCache($id ,$merchant_id);

        //->where(\DB::raw("$field + $val")  ,'=',0)
        return self::query()->where('id','=',$id)
            ->where('merchant_id','=',$merchant_id)
            ->increment($field, $val);

    }


    /**
     * 递减
     * @author 王禹
     * @return int|成功条数
     */

    static function decrement_data($id ,$merchant_id ,$field ,$val)
    {
        if(!$id || !is_numeric($id))return;
        if(!$merchant_id || !is_numeric($merchant_id))return;

        self::forgetCache($id ,$merchant_id);

        return self::query()->where('id','=',$id)
            ->where('merchant_id','=',$merchant_id)
            ->where($field, '>=', abs($val))
            ->where(\DB::raw("$field - ".abs($val))  ,'>=',0)
            ->decrement($field, abs($val));
    }
	
}

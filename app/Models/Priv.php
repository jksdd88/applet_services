<?php namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Utils\CacheKey;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;

class Priv extends Model {

    protected $table = 'priv';
    const CREATED_AT = 'created_time';
    const UPDATED_AT = 'updated_time';
    
    /**
     * 插入一条记录
     * @return id
     */
    static function insert_data($data) {
        //清除,所有权限列表的缓存
        $key = CacheKey::get_all_priv();
        Cache::forget($key);
        
        $data['created_time'] = date('Y-m-d H:i:s');
        $data['updated_time'] = date('Y-m-d H:i:s');
        return self::insertGetId($data);
    }

    /**
     * demo 查询一条记录
     * @return array
     */
    static function get_data_by_id($id, $fields = '*') {
        if(!$id || !is_numeric($id))
            return;

        $key = CacheKey::get_priv_by_id($id);
        $data = Cache::get($key);
        //Cache::forget($key);
        if(!$data) {
            $data = self::query()->where(['merchant_id'=>Auth::user()->merchant_id,'id'=>$id])->first();
            if($data) {
                Cache::put($key, $data, 180);//第三个参数为缓存生命周期 单位：分钟
            }
        }
        
        return $data;
    }
    
    /**
     * 通过priv.code查priv.id
     * @return array
     */
    static function get_id_by_code($priv_code) {
        if(!$priv_code )
            return;
    
        $key = CacheKey::get_PrivId_by_PrivCode($priv_code);
        $data = Cache::get($key);
        //Cache::forget($key);
        if(!$data) {
            $rs_data = self::query()->where(['code'=>$priv_code,'is_delete'=>1])->first();
            //dd($rs_data);
            $data = $rs_data['id'];
            if($data) {
                Cache::put($key, $data, 180);//第三个参数为缓存生命周期 单位：分钟
            }
        }
    
        return $data;
    }
    
    /**
     * 通过priv.code查priv.id
     * @return array
     */
    static function get_PrivCode_by_PrivId($priv_id) {
        if(!$priv_id )
            return;
    
        $key = CacheKey::get_PrivCode_by_PrivId($priv_id);
        $data = Cache::get($key);
        //Cache::forget($key);
        if(!$data) {
            $data = self::query()->select('code')->where(['id'=>$priv_id,'is_delete'=>1])->first();
            if($data) {
                Cache::put($key, $data, 180);//第三个参数为缓存生命周期 单位：分钟
            }
        }
    
        return $data;
    }

    /**
     * demo 修改一条记录
     * @return int|修改成功条数
     */
    static function update_data($id ,$data) {
        if(!$id || !is_numeric($id))
            return;
        
        //清除缓存:所有权限
        $key_all_priv = CacheKey::get_all_priv();
        Cache::forget($key_all_priv);
        //清除缓存:以id为键的记录
        $key_priv_id = CacheKey::get_priv_by_id($id);
        Cache::forget($key_priv_id);
        //清除缓存:以code为键的记录
        if( isset($data['code']) && !empty($data['code']) ){
            $key_priv_code = CacheKey::get_PrivId_by_PrivCode($data['code']);
            Cache::forget($key_priv_code);
        }
        
        $data['updated_time'] = date('Y-m-d H:i:s');
        return self::query()->where('id','=',$id)->update($data);
    }

    /**
     * demo 删除一条记录
     * @return int|删除条数
     */
    static function delete_data($id) {
        if(!$id || !is_numeric($id))
            return;

        //清除缓存:所有权限
        $key_all_priv = CacheKey::get_all_priv();
        Cache::forget($key_all_priv);
        //清除缓存:以id为键的记录
        $key_priv_id = CacheKey::get_priv_by_id($id);
        Cache::forget($key_priv_id);
        
        $data['is_delete'] = -1;
        $data['updated_time'] = date('Y-m-d H:i:s');

        return self::query()->where('id','=',$id)->update($data);
    }

    /**
     * count记录条数
     * @return int|count
     */
    static function get_data_count($wheres=array()) {
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
    static function get_data_list($wheres=array(), $fields = '*', $offset = 0, $limit = 10) {
        $query = self::query();
        foreach($wheres as $where) {
            $query->where($where['column'], $where['operator'], $where['value']);
        }
        $data = $query->select(\DB::raw($fields))->skip($offset)->take($limit)->get();
        return json_decode($data,true);
    }
    
    /**
     * demo 查询一条记录
     * @return array
     */
    static function get_all_priv_data($fields = '*') {
        $key = CacheKey::get_all_priv();
        $data = Cache::get($key);
        Cache::forget($key);
        if(!$data) {
            $data = self::query()->where(['is_delete'=>1])->orderBy('parent_id')->orderBy('sort')->get();
            if($data) {
                Cache::put($key, $data, 600);//第三个参数为缓存生命周期 单位：分钟
            }
        }
    
        return $data;
    }


}
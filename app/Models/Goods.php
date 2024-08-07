<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Utils\CacheKey;
use Illuminate\Support\Facades\Cache;

class Goods extends Model
{
    protected $table = 'goods';

    protected $guarded = ['id'];

    /**
     * The name of the "created at" column.
     *
     * @var string
     */
    const CREATED_AT = 'created_time';

    /**
     * The name of the "updated at" column.
     *
     * @var string
     */
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

    static function get_data_by_id($id, $merchant_id)
    {
        if (!$id || !is_numeric($id)) return;
        if (!$merchant_id || !is_numeric($merchant_id)) return;

        $key = CacheKey::get_good_by_id_key($id, $merchant_id);
        $data = Cache::get($key);
        if (!$data) {
            $data = self::query()->where('id', '=', $id)
                ->where('merchant_id', '=', $merchant_id)
                ->first();

            if ($data) {
                $key = CacheKey::get_good_by_id_key($id, $merchant_id);
                Cache::put($key, $data, 60);
            }
        }

        return $data;

    }

    /**
     * 删除一条记录
     * @return int| 删除条数
     */
    static function delete_data($id, $merchant_id)
    {
        if (!$id || !is_numeric($id)) return;
        if (!$merchant_id || !is_numeric($merchant_id)) return;

        $key = CacheKey::get_good_by_id_key($id, $merchant_id);
        Cache::forget($key);

        Cache::tags('goods_sharecard_'.$id.'_'.$merchant_id)->flush();

        $data['is_delete'] = -1;
        $data['updated_time'] = date('Y-m-d H:i:s');
        $tags_key = CacheKey::get_tags_goods_stock($id, 0);  //清除标签组
        Cache::tags($tags_key)->flush();
        return self::query()->where(['id'=>$id,'merchant_id'=>$merchant_id])->update($data);
    }

    /**
     * demo 修改一条记录(仅后台发布修改商品调用，会清redis)
     * @return int|修改成功条数
     */
    static function update_data($id, $merchant_id, $data)
    {
        if (!$id || !is_numeric($id)) return;
        if (!$merchant_id || !is_numeric($merchant_id)) return;

        $key = CacheKey::get_good_by_id_key($id, $merchant_id);
        Cache::forget($key);
        
        Cache::tags('goods_sharecard_'.$id.'_'.$merchant_id)->flush();

        if (isset($data['stock'])) {//库存
            $stock = self::query()->where('id', '=', $id)->where('merchant_id', '=', $merchant_id)->value('stock');
            if ($stock != $data['stock']) {//修改库存了
                $tags_key = CacheKey::get_tags_goods_stock($id, 0); //清除标签组
                Cache::tags($tags_key)->flush();
            }
        }
        $data['updated_time'] = date('Y-m-d H:i:s');
        return self::query()->where('id', '=', $id)->where('merchant_id', '=', $merchant_id)->update($data);
    }


    /**
     * count记录条数
     * @return int|count
     */
    static function get_data_count($wheres = array())
    {
        $query = self::query();
        foreach ($wheres as $where) {
            if($where['operator'] == 'in'){
                $query->whereIn($where['column'],$where['value']);
            }else{
                $query->where($where['column'], $where['operator'], $where['value']);
            }
        }
        return $query->count();
    }

    /**
     * 查询多条记录
     * @return array
     */
    static function get_data_list($wheres = array(), $fields = '*', $offset = 0, $limit = 10, $order = array())
    {
        $query = self::query();
        foreach ($wheres as $where) {
            if ($where['operator'] == 'in') {
                $query->whereIn($where['column'], $where['value']);
            } else {
                $query->where($where['column'], $where['operator'], $where['value']);
            }
        }
        if (is_array($fields)) $fields = implode(',', $fields);
        $query = $query->select(\DB::raw($fields));
        if (!empty($order)) {
            if(isset($order[0])&&is_array($order[0])){
                foreach ($order as $sort){
                    $query = $query->orderBy($sort['column'], $sort['direct']);
                }
            }else{
                $query = $query->orderBy($order['column'], $order['direct']);
            }
        }
        if(!empty($offset)){
            $query = $query->skip($offset);
        }
        if(!empty($limit)){
            $query = $query->take($limit);
        }
        $data = $query->get();
        return json_decode($data, true);
    }

    /***
     * 根据条件更新数据
     * @param $wheres
     * @param $update_data
     * @Author  DuMing
     */
    static function updateByWhere($wheres, $update_data)
    {
        $query = self::query();
        foreach ($wheres as $where) {
            if ($where['operator'] == 'in') {
                $query->whereIn($where['column'], $where['value']);
            } else {
                $query->where($where['column'], $where['operator'], $where['value']);
            }
        }
        $update_data['updated_time'] = date('Y-m-d H:i:s', time());
        $res = $query->update($update_data);
        return $res;
    }

    /***
     * @param $where
     * @param string $fields
     * @Author  DuMing
     * 根据条件获取数据
     */
    static function getDataByWhere($wheres, $fields = "*")
    {
        $query = self::query();
        foreach ($wheres as $where) {
            if ($where['operator'] == 'in') {
                $query->whereIn($where['column'], $where['value']);
            } else {
                $query->where($where['column'], $where['operator'], $where['value']);
            }
        }
        $data = $query->select($fields)->get();
        return $data;
    }
	
	/**
     * 清除缓存
	 * @author zhangchangchun@dodoca.com
     */
    static function forgetCache($id, $merchant_id)
    {
        if (!$id || !is_numeric($id)) return;
        if (!$merchant_id || !is_numeric($merchant_id)) return;
		
        $key = CacheKey::get_good_by_id_key($id, $merchant_id);
        Cache::forget($key);
        Cache::tags('goods_sharecard_'.$id.'_'.$merchant_id)->flush();
		return true;
    }
        /**
     * 查询多条记录
     * @return array
     */
    static function get_data_list_new($wheres = array(), $fields = '*', $offset = 0, $limit = 10, $orders = array())
    {
        $query = self::query();
        foreach ($wheres as $where) {
            if ($where['operator'] == 'in') {
                $query->whereIn($where['column'], $where['value']);
            } else {
                $query->where($where['column'], $where['operator'], $where['value']);
            }
        }
        if (is_array($fields)) $fields = implode(',', $fields);
        $query = $query->select(\DB::raw($fields));
        if (!empty($orders)) {
            foreach($orders as $order){
                $query = $query->orderBy($order['column'], $order['direct']);
            }
        }
        if(!empty($offset)){
            $query = $query->skip($offset);
        }
        if(!empty($limit)){
            $query = $query->take($limit);
        }
        $data = $query->get();
        return json_decode($data, true);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Utils\CacheKey;
use Illuminate\Support\Facades\Cache;

class GoodsSpec extends Model
{
    protected $table = 'goods_spec';

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
     * demo 查询商品规格
     * @return array
     */

    static function get_data_by_goods_id($goods_id, $merchant_id)
    {
        if (!$goods_id || !is_numeric($goods_id)) return;
        if (!$merchant_id || !is_numeric($merchant_id)) return;

        $key = CacheKey::get_goodspec_by_goodsid_key($goods_id, $merchant_id);
        $data = Cache::get($key);
        if (!$data) {
            $data = self::query()->where('goods_id', '=', $goods_id)
                ->where('merchant_id', '=', $merchant_id)
                ->where('is_delete', '=', 1)
                ->get();

            if ($data) {
                $key = CacheKey::get_goodspec_by_goodsid_key($goods_id, $merchant_id);
                Cache::put($key, $data, 60);
            }

        }

        return $data;

    }

    /**
 * 清除缓存
 * @author zhangchangchun@dodoca.com
 */
    static function forgetCacheByGoods($goods_id, $merchant_id)
    {
        if (!$goods_id || !is_numeric($goods_id)) return;
        if (!$merchant_id || !is_numeric($merchant_id)) return;

        $key = CacheKey::get_goodspec_by_goodsid_key($goods_id, $merchant_id);
        Cache::forget($key);
        return true;
    }

    /**
     * demo 查询一条记录
     * @return array
     */
    static function get_data_by_id($id, $merchant_id)
    {
        if (!$id || !is_numeric($id)) return;
        if (!$merchant_id || !is_numeric($merchant_id)) return;

        $key = CacheKey::get_goodspec_by_id_key($id, $merchant_id);
        $data = Cache::get($key);
        //Cache::forget($key);
        if (!$data) {
            $data = self::query()->where('id', '=', $id)
                ->where('merchant_id', '=', $merchant_id)
                ->where('is_delete', '=', 1)
                ->first();

            if ($data) {
                $key = CacheKey::get_goodspec_by_id_key($id, $merchant_id);
                Cache::put($key, $data, 60);
            }

        }
        return $data;
    }

    /**
     * demo 查询一条记录
     * @return array
     */
    static function get_alldata_by_id($id, $merchant_id)
    {
        if (!$id || !is_numeric($id)) return;
        if (!$merchant_id || !is_numeric($merchant_id)) return;
    
        $key = CacheKey::get_goodspec_by_id_key($id, $merchant_id);
        $data = Cache::get($key);
        //Cache::forget($key);
        if (!$data) {
            $data = self::query()->where('id', '=', $id)
            ->where('merchant_id', '=', $merchant_id)
            ->first();
    
            if ($data) {
                $key = CacheKey::get_goodspec_by_id_key($id, $merchant_id);
                Cache::put($key, $data, 60);
            }
    
        }
        return $data;
    }
    

    /**
     * 清除缓存
     * @author zhangchangchun@dodoca.com
     */
    static function forgetCacheById($id, $merchant_id)
    {
        if (!$id || !is_numeric($id)) return;
        if (!$merchant_id || !is_numeric($merchant_id)) return;

        $key = CacheKey::get_goodspec_by_id_key($id, $merchant_id);
        Cache::forget($key);
        return true;
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

        $data['is_delete'] = -1;
        $data['updated_time'] = date('Y-m-d H:i:s');
        $goods_spec_res = self::get_data_by_id($id, $merchant_id);
        if (empty($goods_spec_res)) return;

        $tags_key = CacheKey::get_tags_goods_stock($goods_spec_res->goods_id, $id); //清除标签组
        Cache::tags($tags_key)->flush();

        return self::query()->where('id', '=', $id)->update($data);
    }

    /**
     * demo 修改一条记录
     * @return int|修改成功条数
     */
    static function update_data($id, $merchant_id, $data)
    {
        if (!$id || !is_numeric($id)) return;
        if (!$merchant_id || !is_numeric($merchant_id)) return;

        $key = CacheKey::get_goodspec_by_id_key($id, $merchant_id);
        Cache::forget($key);
        if (isset($data['stock']) && !empty($data['stock'])) {//库存
            $stock = self::query()->where('id', '=', $id)->where('merchant_id', '=', $merchant_id)->value('stock');
            if ($data['stock'] != $stock) {//修改库存了
                $goods_spec_res = self::get_data_by_id($id, $merchant_id);
                if (empty($goods_spec_res)) return;

                $tags_key = CacheKey::get_tags_goods_stock($goods_spec_res->goods_id, $id); //清除标签组
                Cache::tags($tags_key)->flush();
            }
        }
        $data['updated_time'] = date('Y-m-d H:i:s');
        return self::query()->where('id', '=', $id)->where('merchant_id', '=', $merchant_id)->update($data);

    }

    static function getDataByWhere($wheres, $fields)
    {
        $query = self::query();
        foreach ($wheres as $where) {
            $query->where($where['column'], $where['operator'], $where['value']);
        }
        $res = $query->select($fields)->get();
        return $res;

    }

    static function updateByWhere($wheres, $update_data)
    {
        $query = self::query();
        foreach ($wheres as $where) {
            if($where['operator'] == 'in'){
                $query->whereIn($where['column'], $where['value']);
            }else{
                $query->where($where['column'], $where['operator'], $where['value']);
            }
        }
        $res = $query->update($update_data);
        return $res;

    }
	
	/**
     * 清除缓存
	 * @author zhangchangchun@dodoca.com
     */
    static function forgetCache($id, $merchant_id)
    {
        if (!$id || !is_numeric($id)) return;
        if (!$merchant_id || !is_numeric($merchant_id)) return;
		
        $key = CacheKey::get_goodspec_by_id_key($id, $merchant_id);
        Cache::forget($key);
		return true;
    }
	
}

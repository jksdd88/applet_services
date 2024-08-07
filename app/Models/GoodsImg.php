<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Utils\CacheKey;
use Illuminate\Support\Facades\Cache;

class GoodsImg extends Model
{
    protected $table = 'goods_img';

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
     * demo 查询商品图片
     * @return array
     */
    static function get_data_by_goods_id($goods_id, $merchant_id)
    {
        if (!$goods_id || !is_numeric($goods_id)) return;
        if (!$merchant_id || !is_numeric($merchant_id)) return;

        $key = CacheKey::get_goodimg_by_id_key($goods_id, $merchant_id);
        $data = Cache::get($key);
        if (!$data) {
            $data = self::query()->where('goods_id', '=', $goods_id)
                ->where('merchant_id', '=', $merchant_id)
                ->get();

            if ($data) {
                $key = CacheKey::get_goodimg_by_id_key($goods_id, $merchant_id);
                Cache::put($key, $data, 60);
            }
        }
        return $data;
    }

    /**
     * 清除缓存
     * @author zhangchangchun@dodoca.com
     */
    static function forgetCache($goods_id, $merchant_id)
    {
        if (!$goods_id || !is_numeric($goods_id)) return;
        if (!$merchant_id || !is_numeric($merchant_id)) return;

        $key = CacheKey::get_goodimg_by_id_key($goods_id, $merchant_id);
        Cache::forget($key);
        return true;
    }

    static function getDataByWhere($wheres, $fields = '*', $order = 'listorder')
    {
        $query = self::query();
        foreach ($wheres as $v) {
            $query = $query->where($v['column'], $v['operator'], $v['value']);
        }
        return $query->orderBy($order)->select($fields)->get();
    }

    /***
     * @Author  DuMing
     */
    static function deleteDataByWhere($wheres)
    {
        $query = self::query();
        foreach ($wheres as $v) {
            if ($v['operator'] == 'in') {
                $query = $query->whereIn($v['column'], $v['value']);
            } else {
                $query = $query->where($v['column'], $v['operator'], $v['value']);
            }
        }
        return $query->delete();
    }
}

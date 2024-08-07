<?php

/**
 * 订单商品分佣明细表Model
 * @author 王禹
 * @package App\Models
 */
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use App\Utils\CacheKey;
use Illuminate\Support\Facades\Cache;

class DistribOrderGoods extends Model
{

    protected $table = 'distrib_order_goods';
    protected $guarded = ['id'];
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
     * 修改一条记录
     * @return int|修改成功条数
     */
    static function update_data($order_id, $merchant_id, $goods_id, $spec_id, $data)
    {

        if(!$order_id || !is_numeric($order_id))return;
        if(!$merchant_id || !is_numeric($merchant_id))return;
        if(!$goods_id || !is_numeric($goods_id))return;

        Cache::forget(CacheKey::get_distrib_order_goods_data_key($order_id, $merchant_id, $goods_id, $spec_id));
        Cache::forget(CacheKey::get_distrib_order_goods_list_key($order_id,$merchant_id));

        $data['updated_time'] = date('Y-m-d H:i:s');
        return self::query()->where('order_id','=',$order_id)
            ->where('merchant_id','=',$merchant_id)
            ->where('goods_id','=',$goods_id)
            ->where('spec_id','=',$spec_id)
            ->update($data);

    }

    /**
     * 通过order_id查询一条记录
     * @return array
     */
    static function get_data($order_id, $merchant_id, $goods_id, $spec_id = 0)
    {
        if(!$order_id || !is_numeric($order_id))return;
        if(!$merchant_id || !is_numeric($merchant_id))return;
        if(!$goods_id || !is_numeric($goods_id))return;

        $key = CacheKey::get_distrib_order_goods_data_key($order_id, $merchant_id, $goods_id, $spec_id);
        $data = Cache::get($key);
        if(!$data)
        {
            $data = self::query()->where('order_id','=',$order_id)
                ->where('merchant_id','=',$merchant_id)
                ->where('goods_id','=',$goods_id)
                ->where('spec_id','=',$spec_id)
                ->first();

            if($data)
            {
                Cache::put($key, $data, 120);
            }

        }

        return $data;

    }

    /**
     * 通过order_id查询多条记录
     * @return array
     */

    static function get_list_by_orderid($order_id , $merchant_id)
    {
        if(!$order_id || !is_numeric($order_id))return;
        if(!$merchant_id || !is_numeric($merchant_id))return;

        $key = CacheKey::get_distrib_order_goods_list_key($order_id,$merchant_id);
        $data = Cache::get($key);
        if(!$data)
        {
            $data = self::query()->where('order_id','=',$order_id)->where('merchant_id','=',$merchant_id)->get();

            if($data)
            {
                Cache::put($key, $data, 120);
            }

        }

        return $data;

    }
}
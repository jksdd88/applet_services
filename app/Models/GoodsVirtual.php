<?php

/**
 * 商品（虚拟商品扩展表）MOD
 *
 */
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Utils\CacheKey;
use Illuminate\Support\Facades\Cache;

class GoodsVirtual extends Model
{

    protected $table = 'goods_virtual';
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
    static function get_data_by_id($goods_id,$merchant_id)
    {
        if(!$goods_id || !is_numeric($goods_id))return;
        if(!$merchant_id || !is_numeric($merchant_id))return;
    
        $key = CacheKey::get_goods_virtual_by_goodsid_key($goods_id,$merchant_id);
        $data = Cache::get($key);
        if(!$data)
        {
            $data = self::query()->where('goods_id','=',$goods_id)
                                ->where('merchant_id','=',$merchant_id)
                                ->first();
    
            if($data)
            {
                $key = CacheKey::get_goods_virtual_by_goodsid_key($goods_id,$merchant_id);
                Cache::put($key, $data, 60);
            }
    
        }
    
        return $data;
    
    }
    
    /**
     * 修改一条记录
     * @return int|修改成功条数
     */
    static function update_data($goods_id,$merchant_id,$data)
    {
        if(!$goods_id || !is_numeric($goods_id))return;
        if(!$merchant_id || !is_numeric($merchant_id))return;
    
        $key = CacheKey::get_goods_virtual_by_goodsid_key($goods_id,$merchant_id);
        Cache::forget($key);
    
        $data['updated_time'] = date('Y-m-d H:i:s');
        return self::query()->where('goods_id','=',$goods_id)->where('merchant_id','=',$merchant_id)->update($data);
    
    }
    
    
    
}
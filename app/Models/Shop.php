<?php

namespace App\Models;
use App\Utils\CacheKey;
use Illuminate\Support\Facades\Cache;

use Illuminate\Database\Eloquent\Model;

class Shop extends Model
{
    protected $table = 'shop';
    protected $guarded = ['id'];
    const CREATED_AT = 'created_time';
    const UPDATED_AT = 'updated_time';
    
    /**
     * 查询一条记录
     * @return array
     */
    
    public static function get_data_by_merchant_id($merchant_id)
    {
        if(!$merchant_id || !is_numeric($merchant_id))return;
    
        $key = CacheKey::get_shop_by_merchant_id_key($merchant_id);
        $data = Cache::get($key);
        //Cache::forget($key);
        if(!$data)
        {
            $data = self::query()->where('merchant_id', $merchant_id)->first();
    
            if($data)
            {
                Cache::put($key, $data, 60);
            }
        }
        return $data;
    }
    
    /**
     * 修改一条记录
     * @return int|修改成功条数
     */
    
    public static function update_data($merchant_id, $data)
    {
        if(!$merchant_id || !is_numeric($merchant_id))return;
    
        $key = CacheKey::get_shop_by_merchant_id_key($merchant_id);
        Cache::forget($key);
    
        $data['updated_time'] = date('Y-m-d H:i:s');
        return self::query()->where('merchant_id', $merchant_id)->update($data);
    }
    
    /**
     * 删除一条记录
     * @return int|删除条数
     */
    
    static function delete_data($merchant_id)
    {
        if(!$merchant_id || !is_numeric($merchant_id))return;
    
        $key = CacheKey::get_shop_by_merchant_id_key($merchant_id);
        Cache::forget($key);
    
        $data['updated_time'] = date('Y-m-d H:i:s');
    
        return self::query()->where('merchant_id', $merchant_id)->update($data);
    }
}

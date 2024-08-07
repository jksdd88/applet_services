<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Utils\CacheKey;
use Cache;

class ShareCard extends Model
{

    protected $table = 'share_card';

    const CREATED_AT = 'created_time';
    const UPDATED_AT = 'updated_time';

    /**
     * 插入数据
     */
    static function insert_data($data)
    {
        $data['created_time'] = date('Y-m-d H:i:s');
        $data['updated_time'] = date('Y-m-d H:i:s');
        return self::insertGetId($data);
    }

    /**
     * 获取单条数据
     */
    static function get_data($merchant_id, $wxinfo_id)
    {
        if(!$merchant_id || !is_numeric($merchant_id))return;
        if(!$wxinfo_id || !is_numeric($wxinfo_id))return;

        $key  = CacheKey::get_sharedata_by_wxinfoid($merchant_id, $wxinfo_id);
        $data = Cache::get($key);
        if(!$data)
        {
            $data = self::query()->where(['merchant_id' => $merchant_id, 'wxinfo_id' => $wxinfo_id])->first();

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
    static function update_data($merchant_id, $wxinfo_id, $data)
    {
        if(!$merchant_id || !is_numeric($merchant_id))return;
        if(!$wxinfo_id || !is_numeric($wxinfo_id))return;

        $key = CacheKey::get_sharedata_by_wxinfoid($merchant_id, $wxinfo_id);
        Cache::forget($key);
        $cacheKey = CacheKey::share_card_custom_key($merchant_id, $wxinfo_id);
        Cache::forget($cacheKey);

        $data['updated_time'] = date('Y-m-d H:i:s');
        return self::query()->where('merchant_id',$merchant_id)->where('wxinfo_id',$wxinfo_id)->update($data);

    }
}

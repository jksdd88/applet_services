<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Utils\CacheKey;
use Illuminate\Support\Facades\Cache;
class DistribCodeSetting extends Model
{
    protected $table = 'distrib_code_setting';
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
     * 根据商品id获取单条数据
     * @author renruiqi@dodoca.com
     */
    static function get_data_by_merchantid($merchant_id) {
        if (!$merchant_id || !is_numeric($merchant_id))return;
        $key = CacheKey::get_distrib_code_by_merchantid_key($merchant_id);
        $data = Cache::get($key);
        if (!$data) {
            $data = self::where('merchant_id', $merchant_id)->first();
            if ($data) {
                Cache::put($key, $data, 60);
            }
        }

        return $data;
    }


    /**
     * 修改数据
     * @author renruiqi@dodoca.com
     */
    static function update_data($merchant_id, $data) {
        if (!$merchant_id || !is_numeric($merchant_id)) return;
        $data['updated_time'] = date('Y-m-d H:i:s');
        $key = CacheKey::get_distrib_code_by_merchantid_key($merchant_id);
        Cache::forget($key);

        Cache::tags('distrib_merchant_'.$merchant_id)->flush();

        return self::where('merchant_id', $merchant_id)->update($data);

    }

}

<?php

/**
 * 分销设置表Model
 * @author 王禹
 * @package App\Models
 */
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use App\Utils\CacheKey;
use Illuminate\Support\Facades\Cache;

class DistribSetting extends Model
{

    protected $table = 'distrib_setting';
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
     * 通过id查询一条记录
     * @return array
     */

    static function get_data_by_merchant_id($merchant_id)
    {
        if(!$merchant_id || !is_numeric($merchant_id))return;

        $key = CacheKey::get_distrib_setting_by_merchant_id_key($merchant_id);
        $data = Cache::get($key);
        if(!$data)
        {
            $data = self::query()->where('merchant_id','=',$merchant_id)->first();

            if($data)
            {
                Cache::put($key, $data, 120);
            }

        }

        return $data;

    }


    /**
     * 修改一条记录
     * @return int|修改成功条数
     */

    static function update_data($merchant_id ,$data)
    {

        if(!$merchant_id || !is_numeric($merchant_id))return;

        $key = CacheKey::get_distrib_setting_by_merchant_id_key($merchant_id);
        Cache::forget($key);

        $data['updated_time'] = date('Y-m-d H:i:s');
        return self::query()->where('merchant_id','=',$merchant_id)->update($data);

    }

}
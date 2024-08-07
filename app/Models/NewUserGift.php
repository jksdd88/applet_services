<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Utils\CacheKey;
use Cache;

class NewUserGift extends Model
{
    const CREATED_AT = 'created_time';
    const UPDATED_AT = 'updated_time';
    /**
     * 与模型关联的数据表。
     *
     * @var string
     */
    protected $table = 'new_user_gift';

    /**
     * 不可被批量赋值的属性。
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * 查询一条记录
     * @return array
     */
    static function get_data_by_id($id, $merchant_id)
    {
        if(!$id || !is_numeric($id))return;
        if(!$merchant_id || !is_numeric($merchant_id))return;

        $key = CacheKey::get_newusergift_by_id_key($id, $merchant_id);
        $data = Cache::get($key);
        if(!$data)
        {
            $data = self::query()->where(['id' => $id, 'merchant_id' => $merchant_id])->first();

            if($data)
            {
                Cache::put($key, $data, 60);
            }
        }
        return $data;

    }

    /**
     * demo 修改一条记录
     * @return int|修改成功条数
     */
    static function update_data($id, $merchant_id, $data)
    {
        if(!$id || !is_numeric($id))return;
        if(!$merchant_id || !is_numeric($merchant_id))return;

        $key = CacheKey::get_newusergift_by_id_key($id, $merchant_id);
        Cache::forget($key);

        return self::query()->where(['id' => $id, 'merchant_id' => $merchant_id])->update($data);
    }

    /**
     * demo 删除一条记录
     * @return int|删除条数
     */
    static function delete_data($id, $merchant_id)
    {
        if(!$id || !is_numeric($id))return;

        $key = CacheKey::get_newusergift_by_id_key($id, $merchant_id);
        Cache::forget($key);

        $data['is_delete'] = -1;

        return self::query()->where(['id' => $id, 'merchant_id' => $merchant_id])->update($data);
    }
}

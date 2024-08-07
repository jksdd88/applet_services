<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Utils\CacheKey;
use Illuminate\Support\Facades\Cache;

class FormFeedback extends Model
{
    protected $table = 'form_feedback';

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
     * demo 查询一条记录
     * @return array
     */
    static function get_data_by_id($id, $merchant_id)
    {
        if (!$id || !is_numeric($id)) return;
        if (!$merchant_id || !is_numeric($merchant_id)) return;
        $key = CacheKey::get_formfeedback_by_id_key($id, $merchant_id);
        $data = Cache::get($key);
        if (!$data) {
            $data = self::query()->where('id', '=', $id)
                ->where('merchant_id', '=', $merchant_id)
                ->where('is_delete', '=', 1)
                ->first();

            if ($data) {
                $key = CacheKey::get_formfeedback_by_id_key($id, $merchant_id);
                Cache::put($key, $data, 60);
            }
        }

        return $data;

    }

    /**
     * demo 修改一条记录(仅后台发布修改商品调用，会清redis)
     * @return int|修改成功条数
     */
    static function update_data($id, $merchant_id, $data)
    {
        if (!$id || !is_numeric($id)) return;
        if (!$merchant_id || !is_numeric($merchant_id)) return;

        $key = CacheKey::get_formfeedback_by_id_key($id, $merchant_id);
        Cache::forget($key);
        $data['updated_time'] = date('Y-m-d H:i:s');
        return self::query()->where('id', '=', $id)->where('merchant_id', '=', $merchant_id)->update($data);
    }
}

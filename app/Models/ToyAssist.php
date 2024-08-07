<?php

namespace App\Models;

use App\Utils\CacheKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class ToyAssist extends Model
{
    protected $connection = 'applet_cust';

    protected $table = 'toy_assist';
    const CREATED_AT = 'created_time';
    const UPDATED_AT = 'updated_time';

    /**
     * 助力列表
     * @param $member_id
     * @param $merchant_id
     * @author: tangkang@dodoca.com
     */
    static public function get_data_list($member_id, $merchant_id)
    {
        if (!$member_id || !is_numeric($member_id)) return;
        if (!$merchant_id || !is_numeric($merchant_id)) return;
        $tag_key = CacheKey::get_toy_tag_by_merchant_id_id_key($merchant_id);
        $key = CacheKey::get_toy_assist_list_by_member_id_key($member_id, $merchant_id);
        $data = Cache::tags($tag_key)->get($key);
        if (!$data) {
            $where = [
                'merchant_id' => $merchant_id,
                'member_id' => $member_id,
                'is_delete' => 1,
            ];
            $query = self::query()->where($where);
            $data['_count'] = $query->count();
            $data['lists'] = $query->orderBy('created_time', 'desc')->limit(100)->get();
            if ($data) {
                $key = CacheKey::get_toy_assist_list_by_member_id_key($member_id, $merchant_id);
                Cache::tags($tag_key)->put($key, $data, 10080);
            }
        }
        return $data;
    }

    /**
     * 新增助力
     * @param $data
     * @return mixed
     * @author: tangkang@dodoca.com
     */
    static public function insert_data($data)
    {
        $data['created_time'] = date('Y-m-d H:i:s');
        $data['updated_time'] = date('Y-m-d H:i:s');
        $data['is_delete'] = 1;
        $tag_key = CacheKey::get_toy_tag_by_merchant_id_id_key($data['merchant_id']);
        $key = CacheKey::get_toy_assist_list_by_member_id_key($data['member_id'], $data['merchant_id']);
        Cache::tags($tag_key)->forget($key);
        return self::insertGetId($data);
    }
}

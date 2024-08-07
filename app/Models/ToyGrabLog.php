<?php

namespace App\Models;

use App\Utils\CacheKey;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class ToyGrabLog extends Model
{
    protected $connection = 'applet_cust';

    protected $table = 'toy_grab_log';
    const CREATED_AT = 'created_time';
    const UPDATED_AT = 'updated_time';

    /**
     * 记录抓取
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
        $key = CacheKey::get_toy_grab_today_times_by_id_key($data['member_id'], $data['merchant_id']);
        Cache::tags($tag_key)->forget($key);
        return self::insertGetId($data);
    }

    /**
     * 获取今日已抓取次数
     * @param $member_id
     * @param $merchant_id
     * @author: tangkang@dodoca.com
     */
    static public function grab_times_today($member_id, $merchant_id)
    {
        $tag_key = CacheKey::get_toy_tag_by_merchant_id_id_key($merchant_id);
        $key = CacheKey::get_toy_grab_today_times_by_id_key($member_id, $merchant_id);
        $count = Cache::tags($tag_key)->get($key);
        if (is_null($count)) {
            $date_time = Carbon::today()->addHours(23)->addMinutes(59)->addSeconds(59);
            $member_data = [
                'merchant_id' => $merchant_id,
                'member_id' => $member_id,
                'is_delete' => 1,
            ];
            $count = ToyGrabLog::where($member_data)->whereDate('created_time', '=', date('Y-m-d'))->count();
            Cache::tags($tag_key)->put($key, $count, $date_time);
        }
        return $count;
    }
}

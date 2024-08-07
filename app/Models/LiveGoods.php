<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Utils\CacheKey;
use Cache;

class LiveGoods extends Model
{
    /**
     * 与模型关联的数据表。
     *
     * @var string
     */
    protected $table = 'live_goods';

    const CREATED_AT = 'created_time';
    const UPDATED_AT = 'updated_time';

	static function insert_data($data)
    {
        return self::insertGetId($data);
    }
	
	//查询直播间关联商品
	public static function get_data_by_liveid($live_id,$merchant_id) {
		if (!$live_id || !is_numeric($live_id)) return;
        if (!$merchant_id || !is_numeric($merchant_id)) return;

		$key = CacheKey::get_live_goods_by_id_key($live_id, $merchant_id);
		$data = Cache::get($key);
        $data = false;
		if (!$data) {
			$data = LiveGoods::where('merchant_id', $merchant_id)
                ->where('live_id', $live_id)
                ->where('is_delete', 1)
                ->get()->toArray();

			$key = CacheKey::get_live_goods_by_id_key($live_id, $merchant_id);
			Cache::put($key, $data, 60);
		}

		return $data;
	}


    /**
     * demo 删除一条记录
     * @return int|修改成功条数
     */
    static function delete_data($live_id, $goods_id, $merchant_id)
    {
        if(!$live_id || !is_numeric($live_id))return;
		if(!$goods_id || !is_numeric($goods_id))return;
        if(!$merchant_id || !is_numeric($merchant_id))return;

        $key = CacheKey::get_live_goods_by_id_key($live_id, $merchant_id);
        Cache::forget($key);

		$data = ['is_delete'=>-1];

        return self::query()->where(['live_id' => $live_id, 'goods_id' => $goods_id, 'merchant_id' => $merchant_id])->update($data);
    }
}

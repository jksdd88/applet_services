<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Utils\CacheKey;
use Cache;

class CouponGoods extends Model
{
    const CREATED_AT = 'created_time';
    const UPDATED_AT = 'updated_time';
    
    /**
     * 与模型关联的数据表。
     *
     * @var string
     */
    protected $table = 'coupon_goods';


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
     * 获取一条数据
     * @param int $merchant_id  商户ID
     * @param int $member_id  买家ID
     * @param int $coupon_id  优惠劵ID
     * @return id
     */
    static function get_data($merchant_id, $coupon_id, $goods_id)
    {
        if(!$merchant_id || !is_numeric($merchant_id)) return;
        if(!$coupon_id || !is_numeric($coupon_id)) return;
        if(!$goods_id || !is_numeric($goods_id)) return;

        $key = CacheKey::get_coupon_goods($merchant_id, $coupon_id, $goods_id);
        $data = Cache::get($key);
        if(!$data)
        {
            $data = self::query()->where(['merchant_id' => $merchant_id, 'coupon_id' => $coupon_id, 'goods_id' => $goods_id])->first();

            if($data)
            {
                Cache::put($key, $data, 60);
            }
        }
        return $data;
    }

    
}

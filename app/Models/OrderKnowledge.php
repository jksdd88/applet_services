<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Utils\CacheKey;
use Illuminate\Support\Facades\Cache;

class OrderKnowledge extends Model
{
    protected $table = 'order_knowledge';
    protected $guarded = ['id'];
    const CREATED_AT = 'created_time';
    const UPDATED_AT = 'updated_time';
    const K_TYPE_COLUMN = 1;//专栏
    const K_TYPE_CONTENT = 2;//内容
    const PAY_STATUS_UNPAID = 0;//未付款
    const PAY_STATUS_SUCCESS = 1;//付款成功
    const PAY_STATUS_FAILURE = 2;//付款取消
    static $type_msg = [
        self::K_TYPE_COLUMN => '专栏',
        self::K_TYPE_CONTENT => '内容',
    ];

    /**
     * 新增
     * @param $data
     * @return mixed
     * @author: tangkang@dodoca.com
     */
    static function insert_data($data)
    {
        return self::insertGetId($data);
    }

    /**
     * 更新知识付费订单
     * @param $order_id
     * @param $merchant_id
     * @param $data
     * @return int|void
     * @author: tangkang@dodoca.com
     */
    static function update_data($order_id, $merchant_id, $data)
    {
        if (!$order_id || !is_numeric($order_id))
            return;
        if (!$merchant_id || !is_numeric($merchant_id))
            return;
        return self::query()->where(['order_id' => $order_id, 'merchant_id' => $merchant_id])->update($data);
    }

    /**
     * 查询会员知识付费单条订单支付成功记录
     * @param $knowledge_id
     * @param $k_type
     * @param $merchant_id
     * @return mixed
     * @author: tangkang@dodoca.com
     */
    static function get_data_by_k_member_mercahnt_id($knowledge_id, $k_type, $member_id, $merchant_id)
    {
        if (!$knowledge_id || !is_numeric($knowledge_id))
            return;
        if (!$k_type || !is_numeric($k_type))
            return;
        if (!$member_id || !is_numeric($member_id))
            return;
        if (!$merchant_id || !is_numeric($merchant_id))
            return;
        return self::query()
            ->whereKnowledgeId($knowledge_id)
            ->whereKType($k_type)
            ->whereMerchantId($merchant_id)
            ->whereMemberId($member_id)
            ->wherePayStatus(OrderKnowledge::PAY_STATUS_SUCCESS)
            ->first();
    }

    /**
     * 根据订单id查询单条记录
     * @param $order_id
     * @param $merchant_id
     * @author: tangkang@dodoca.com
     */
    static function get_data_by_order_id($order_id, $merchant_id)
    {
        if (!$order_id || !is_numeric($order_id))
            return;
        if (!$merchant_id || !is_numeric($merchant_id))
            return;
        return self::query()
            ->whereOrderId($order_id)
            ->whereMerchantId($merchant_id)
            ->first();
    }
}

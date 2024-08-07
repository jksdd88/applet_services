<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Utils\CacheKey;
use Illuminate\Support\Facades\Cache;

class KnowledgeColumn extends Model
{
    protected $table = 'knowledge_column';
    protected $guarded = ['id'];
    const CREATED_AT = 'created_time';
    const UPDATED_AT = 'updated_time';
    const STATUS_ONSHELVE = 1;//上架
    const STATUS_UNSHELVE = 2;//下架

    /**
     * 新增
     * @param $data
     * @return int
     * @author tangkang@dodoca.com
     */
    static function insert_data($data)
    {
        $data['is_delete'] = 1;
        $data['status'] = self::STATUS_ONSHELVE;
        return self::insertGetId($data);
    }

    /**
     * 获取一条数据
     * @param $id
     * @param $merchant_id
     * @return int
     * @author tangkang@dodoca.com
     */
    static function get_data_by_id($id, $merchant_id, $fields = ['*'])
    {
        if (!$id || !is_numeric($id)) return 0;
        if (!$merchant_id || !is_numeric($merchant_id)) return 0;
        if ($fields === ['*']) {
            $key = CacheKey::get_k_column_by_id($id, $merchant_id);
            $data = Cache::get($key);
            if (!$data) {
                $data = self::query()->whereId($id)->whereMerchantId($merchant_id)->first();

                if ($data) {
                    Cache::put($key, $data, 60);//第三个参数为缓存生命周期 单位：分钟
                }
            }
        } else {
            $data = self::query()->whereId($id)->whereMerchantId($merchant_id)->first($fields);
        }
        return $data;
    }

    /**
     * 更新
     * @param $id
     * @param $merchant_id
     * @param $data
     * @return int|void
     * @author tangkang@dodoca.com
     */
    static function updata_data($id, $merchant_id, $data)
    {
        if (!$id || !is_numeric($id)) return 0;
        if (!$merchant_id || !is_numeric($merchant_id)) return 0;
        if (empty($data)) return 0;
        $key = CacheKey::get_k_column_by_id($id, $merchant_id);
        Cache::forget($key);
        return self::query()->whereId($id)->whereMerchantId($merchant_id)->update($data);
    }

    /**
     * 自增订阅量
     * @param $id
     * @param $merchant_id
     * @author: tangkang@dodoca.com
     */
    static function incCsale($id, $merchant_id)
    {
        if (!$id || !is_numeric($id)) return;
        if (!$merchant_id || !is_numeric($merchant_id)) return;
        $key = CacheKey::get_k_column_by_id($id, $merchant_id);
        Cache::forget($key);
        return self::query()->whereId($id)->whereMerchantId($merchant_id)->increment('csale');
    }

    /**
     * 自增期数
     * @param $id
     * @param $merchant_id
     * @author: tangkang@dodoca.com
     */
    static function incPeriodNumber($id, $merchant_id, $num_success = 1)
    {
        if (!$id || !is_numeric($id)) return;
        if (!$merchant_id || !is_numeric($merchant_id)) return;
        $key = CacheKey::get_k_column_by_id($id, $merchant_id);
        Cache::forget($key);
        return self::query()->whereId($id)->whereMerchantId($merchant_id)->increment('period_number', $num_success);
    }

    /**
     * 自减期数
     * @param $id
     * @param $merchant_id
     * @author: tangkang@dodoca.com
     */
    static function decPeriodNumber($id, $merchant_id, $num_success = 1)
    {
        if (!$id || !is_numeric($id)) return;
        if (!$merchant_id || !is_numeric($merchant_id)) return;
        $key = CacheKey::get_k_column_by_id($id, $merchant_id);
        Cache::forget($key);
        return self::query()->whereId($id)->whereMerchantId($merchant_id)->decrement('period_number', $num_success);
    }

    /**
     * 删除
     * @param $id
     * @param $merchant_id
     * @return int
     * @author tangkang@dodoca.com
     */
    static function delete_data($id, $merchant_id)
    {
        if (!$id || !is_numeric($id)) return 0;
        if (!$merchant_id || !is_numeric($merchant_id)) return 0;
        $data = [
            'is_delete' => -1,
        ];
        $key = CacheKey::get_k_column_by_id($id, $merchant_id);
        Cache::forget($key);
        return self::query()->whereId($id)->whereMerchantId($merchant_id)->update($data);

    }
}

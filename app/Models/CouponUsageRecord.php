<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Utils\CacheKey;
use Cache;

class CouponUsageRecord extends Model
{

    /**
     * 与模型关联的数据表。
     *
     * @var string
     */
    protected $table = 'coupon_usage_record';

    /**
     * 指定是否模型应该被戳记时间。
     *
     * @var bool
     */
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
     * 获取单条数据
     * @author zhangchangchun@dodoca.com
     */
    static function get_data_by_id($id)
    {
        if(!$id || !is_numeric($id))return;

        return self::query()->where(['id' => $id, 'merchant_id' => $merchant_id])->first();
    }

    /**
     * 更新一条数据
     * @param int $member_id  买家ID
     * @param int $coupon_id  优惠劵ID
     * @return id
     */
    static function update_data($id, $data = array())
    {   
        if(!$id || !is_numeric($id))return;

        $data['updated_time'] = date('Y-m-d H:i:s');
        return self::query()->where(['id' => $id])->update($data);
    }

    /**
     * count记录条数
     * @return int|count
     */
    static function get_data_count($wheres=array())
    {
        $query = self::query();
        foreach($wheres as $where) {
            $query->where($where['column'], $where['operator'], $where['value']);
        }
        return $query->count();
    }

    /**
     * 查询多条记录
     * @return array
     */
    static function get_data_list($wheres=array(), $fields = '*')
    {
        $query = self::query();
        foreach($wheres as $where) {
            $query->where($where['column'], $where['operator'], $where['value']);
        }
        $data = $query->select(\DB::raw($fields))->orderBy('created_time', 'desc')->get();
        return json_decode($data,true);
    }
}

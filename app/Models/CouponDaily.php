<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CouponDaily extends Model
{
    /**
     * 与模型关联的数据表。
     *
     * @var string
     */
    protected $table = 'coupon_daily';//推客数据每日统计

    /**
     * 指定是否模型应该被戳记时间。
     *
     * @var bool
     */
    public $timestamps = false;

    protected $connection = 'applet_stats';


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
}

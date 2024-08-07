<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FormSum extends Model
{
    /**
     * 与模型关联的数据表。
     *
     * @var string
     */
    protected $table = 'form_sum';//订单每天统计

    /**
     * 指定是否模型应该被戳记时间。
     *
     * @var bool
     */
    public $timestamps = false;

    protected $connection = 'applet_stats';

     static function insert_data($data)
    {

        $data['created_time'] = date('Y-m-d H:i:s');
        return self::insertGetId($data);
    }
}

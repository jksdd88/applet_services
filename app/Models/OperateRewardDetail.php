<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OperateRewardDetail extends Model
{
    protected $table='operate_reward_detail';
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
     * 获取单条数据
     * @author zhangchangchun@dodoca.com
     */
    static function get_data_by_id($merchant_id)
    {
        if(!$merchant_id || !is_numeric($merchant_id))return;

        $data = self::query()->where(['merchant_id'=>$merchant_id])->first();
        return $data;
    }


    /**
     * 插入数据
     * @author zhangchangchun@dodoca.com
     */
    static function insert_data($data)
    {
        $data['created_time'] = date('Y-m-d H:i:s');
        $data['updated_time'] = date('Y-m-d H:i:s');
        return self::insertGetId($data);
    }
   
}

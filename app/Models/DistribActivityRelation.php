<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DistribActivityRelation extends Model
{
    protected $table = 'distrib_activity_relation';
    public $timestamps = false;

    /**
     * 插入一条记录
     * @return id
     */
    static function insert_data($data)
    {
        $data['created_time'] = date('Y-m-d H:i:s');
        return self::insertGetId($data);
    }
    
    
    /**
     * 批量删除数据
     * @param activity_id
     */
    static function delete_datas($activity_id, $merchant_id)
    {
        if (!$activity_id || !is_numeric($activity_id))return;
        if (!$merchant_id || !is_numeric($merchant_id))return;
        
        return self::query()->where('distrib_activity_id','=',$activity_id)
            ->where('merchant_id','=',$merchant_id)->delete();
    }
}

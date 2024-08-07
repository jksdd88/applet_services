<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DistribCheckLog extends Model
{
    protected $table = 'distrib_check_log';
    protected $guarded = ['id'];
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
}

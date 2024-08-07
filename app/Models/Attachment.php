<?php namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Attachment extends Model {
    //模型
    //protected $connection='retail_hq';

    protected $table = 'attachment';
    protected $guarded = ['id'];
    const CREATED_AT = 'created_time';
    const UPDATED_AT = 'updated_time';
    
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
<?php
/**
 * 队列数据表（防止队列重复执行）
 * @author zhangchangchun@dodoca.com
 */
 
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QueueData extends Model
{
   
    protected $table = 'queue_data';	
    const CREATED_AT = 'created_time';
    const UPDATED_AT = 'updated_time';

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
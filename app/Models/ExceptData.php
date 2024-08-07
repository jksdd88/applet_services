<?php
/**
 * 异常数据表
 * @author zhangchangchun@dodoca.com
 */
 
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExceptData extends Model
{
   
    protected $table = 'except_data';	
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
<?php
/**
 * 异步数据导出任务表
 * @author guoqikai@dodoca.com
 */
 
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DataExportTask extends Model
{
   
    protected $table = 'data_export_task';	
    const CREATED_AT = 'created_time';
    const UPDATED_AT = 'updated_time';

    static function insert_data($data)
    {
        $data['created_time'] = date('Y-m-d H:i:s');
        $data['updated_time'] = date('Y-m-d H:i:s');
        return self::insertGetId($data);
    }
	
}
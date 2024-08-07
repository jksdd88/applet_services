<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Wlog extends Model
{
    const CREATED_AT = 'created_time';
    const UPDATED_AT = 'updated_time';
	
    /**
     * 与模型关联的数据表。
     *
     * @var string
     */
    protected $table = 'log';
    protected $connection = 'applet_log';
	
	public function __construct() 
	{
		$this->table = 'log_'.date("ym");
	}
	
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

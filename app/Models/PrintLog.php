<?php namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Utils\CacheKey;
use Illuminate\Support\Facades\Cache;

class PrintLog extends Model {

    protected $table = 'print_log';

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
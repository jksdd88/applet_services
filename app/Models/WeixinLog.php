<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use App\Utils\CacheKey;

class WeixinLog extends Model
{

    //protected $connection = '';//数据库链接
    protected $table = 'weixin_log';//表明
    //protected $primaryKey = ''; //主键自定义
    //protected $fillable = '';// 可以被批量赋值的属性
    protected $guarded = ['id'];//不可被批量赋值的属性。
    public $timestamps = false;//时间戳定义 created_at   updated_at

    static function insert_data($data)
    {
        $data['created_time'] = date('Y-m-d H:i:s');
        return self::insertGetId($data);
    }


}
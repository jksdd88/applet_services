<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WeixinLogAuthorize extends Model
{

    protected $table = 'weixin_log_authorize';
    protected $guarded = ['id'];
    public $timestamps = false;

    static function insert_data($data)
    {
        $data['created_time'] = date('Y-m-d H:i:s');
        return self::insertGetId($data);
    }


}
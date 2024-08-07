<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NewUserGiftRecord extends Model
{
    const CREATED_AT = 'created_time';
    const UPDATED_AT = 'updated_time';
    /**
     * 与模型关联的数据表。
     *
     * @var string
     */
    protected $table = 'new_user_gift_record';
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Utils\CacheKey;
use Cache;

class VirtualMember extends Model
{
    /**
     * 与模型关联的数据表。
     *
     * @var string
     */
    protected $table = 'virtual_member';

    /**
     * 指定是否模型应该被戳记时间。
     *
     * @var bool
     */
    const CREATED_AT = 'created_time';

    const UPDATED_AT = 'updated_time';

    protected $fillable = ['merchant_id','nickname','avatar','level','is_delete'];
}

<?php

/**
 * 分销买家与推客关系表Model
 * @author 郭其凯
 * @package App\Models
 */
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class DistribMemberTrack extends Model
{
    const CREATED_AT = 'created_time';
    const UPDATED_AT = 'updated_time';

    /**
     * 与模型关联的数据表。
     *
     * @var string
     */
    protected $table = 'distrib_member_track';

    /**
     * 不可被批量赋值的属性。
     *
     * @var array
     */
    protected $guarded = [];
}
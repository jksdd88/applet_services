<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeckillInitiate extends Model {

    protected $table = 'seckill_initiate';
    protected $guarded = ['id'];
    public $timestamps = true;
    const CREATED_AT = 'created_time';

    /**
     * The name of the "updated at" column.
     *
     * @var string
     */
    const UPDATED_AT = 'updated_time';

}

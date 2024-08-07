<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiscountItem extends Model
{
    protected $table = 'discount_item';
    protected $guarded = ['id'];
    const CREATED_AT = 'created_time';

    /**
     * The name of the "updated at" column.
     *
     * @var string
     */
    const UPDATED_AT = 'updated_time';
}

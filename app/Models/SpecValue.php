<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SpecValue extends Model
{
    protected $table='spec_value';

    protected $guarded = ['id'];

    /**
     * The name of the "created at" column.
     *
     * @var string
     */
    const CREATED_AT = 'created_time';

    /**
     * The name of the "updated at" column.
     *
     * @var string
     */
    const UPDATED_AT = 'updated_time';
}

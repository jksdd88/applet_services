<?php

namespace App\Models;

use App\Scopes\MemberMerchantScope;
use Illuminate\Database\Eloquent\Model;

class Cart extends Model
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'cart';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    const CREATED_AT = 'created_time';
    const UPDATED_AT = 'updated_time';

}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MobileSms extends Model
{
    protected $table = 'mobile_sms';

    protected $guarded = ['id'];

    public $timestamps = false;
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MobileSmsLog extends Model {

    protected $table = 'mobile_sms_log';
    protected $guarded = ['id'];
    public $timestamps = false;
}

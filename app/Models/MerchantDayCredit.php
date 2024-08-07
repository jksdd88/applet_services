<?php namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Utils\CacheKey;
use Illuminate\Support\Facades\Cache;

class MerchantDayCredit extends Model {

    protected $connection = 'applet_stats';
    protected $table = 'merchant_day_credit';
    protected $guarded = ['id'];
    public $timestamps = false;
}
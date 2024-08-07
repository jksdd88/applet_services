<?php namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Utils\CacheKey;
use Illuminate\Support\Facades\Cache;

class MerchantMonthCreditType extends Model {

    protected $connection = 'applet_stats';
    protected $table = 'merchant_month_credit_type';
    protected $guarded = ['id'];
    public $timestamps = false;
}
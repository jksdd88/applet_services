<?php namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MerchantDelivery extends Model {

    protected $table = 'merchant_delivery';
    protected $guarded = ['id'];
    public $timestamps = false;
}
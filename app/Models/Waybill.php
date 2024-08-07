<?php namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Waybill extends Model {

    protected $table = 'waybill';
    protected $guarded = ['id'];
    public $timestamps = false;
}
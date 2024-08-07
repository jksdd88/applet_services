<?php namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Utils\CacheKey;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;

class StoreUser extends Model {

    protected $table = 'store_user';
    const CREATED_AT = 'created_time';
    const UPDATED_AT = 'updated_time';
}
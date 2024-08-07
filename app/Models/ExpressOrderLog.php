<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use App\Utils\CacheKey;
use Illuminate\Support\Facades\DB;

class ExpressOrderLog extends Model
{

    protected $table = 'express_order_log';
    protected $guarded = ['id'];
    public $timestamps = false;

    static function cacheKey($key){
        return CacheKey::get_dada_express($key,'express_order_log');
    }
    static function clearCache($info){
        Cache::forget(self::cacheKey('id_'.$info['id']));
        Cache::forget(self::cacheKey('merchant_id_'.$info['merchant_id']));
    }

    static function insert_data($data)
    {
        $data['created_time'] = date('Y-m-d H:i:s');
        $data['updated_time'] = date('Y-m-d H:i:s');
        return self::insertGetId($data);
    }

    static function get_one($key, $value){
        $cachekey = self::cacheKey($key.'_'.$value);
        $data = Cache::get($cachekey);
        if(!$data){
            $data = self::query()->where($key,'=',$value)->where(['is_delete'=>1])  -> orderBy('id', 'ASC') ->first();
            if($data) {
                $data =  $data ->toArray();
                Cache::put($cachekey, $data, 10);
            }
        }
        return $data;
    }

    static function update_data($key, $value ,$data)
    {
        $info = self::query()->where($key,'=',$value)->where('is_delete','=',1)->first();
        if($info) {
            static ::clearCache($info->toArray());
            $data['updated_time'] = date('Y-m-d H:i:s');
            return self::query()->where('id','=',$info->id)->update($data);
        }else{
            return false;
        }
    }


}
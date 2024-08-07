<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use App\Utils\CacheKey;

class WeixinPay extends Model
{

    protected $table = 'weixin_pay';
    protected $guarded = ['id'];
    public $timestamps = false;

    static function cacheKey($key){
        return CacheKey::get_weixin_models($key,'weixin_pay');
    }

    static function insert_data($data)
    {
        $data['created_time'] = date('Y-m-d H:i:s');
        return self::insertGetId($data);
    }

    static function get_one( $key, $value){
        $cachekey = self::cacheKey($key.'_'.$value);
        $data = Cache::get($cachekey);
        if(!$data){
            $data = self::query()->where($key,'=',$value)->where('status','=',1)->first();
            if($data) {
                $data =  $data ->toArray();
                Cache::put($cachekey, $data, 1);
            }
        }
        return $data;
    }
    static function get_one_appid($merchant_id, $appid ){
        $key = self::cacheKey('merchant_appid_'.$merchant_id.(empty($appid)?'':$appid));
        $data = Cache::get($key);
        if(!$data){
            $data = self::query()->where('merchant_id','=',$merchant_id)->where('appid','=',$appid) ->where('status','=',1) -> orderBy('id', 'DESC') ->first();
            if($data) {
                $data =  $data ->toArray();
                Cache::put($key, $data, 1);
            }
        }
        return $data;
    }

    static function update_data($key, $value ,$data)
    {
        $info = self::query()->where($key,'=',$value)->where('status','=',1)->first();
        if($info) {
            Cache::forget(self::cacheKey('id_'.$info->id));
            Cache::forget(self::cacheKey('appid_'.$info->appid));
            Cache::forget(self::cacheKey('merchant_id_'.$info->merchant_id));
            Cache::forget(self::cacheKey('merchant_appid_'.$info->merchant_id));
            Cache::forget(self::cacheKey('merchant_appid_'.$info->merchant_id.$info->appid));
            $data['updated_time'] = date('Y-m-d H:i:s');
            return self::query()->where('id','=',$info->id)->update($data);
        }else{
            return false;
        }
    }


}
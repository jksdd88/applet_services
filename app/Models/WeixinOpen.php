<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use App\Utils\CacheKey;

class WeixinOpen extends Model
{

    protected $table = 'weixin_open';
    protected $guarded = ['id'];
    public $timestamps = false;

    static function cacheKey($key){
        return CacheKey::get_weixin_models($key,'weixin_open');
    }

    static function insert_data($data)
    {
        if(isset($data['merchant_id'])){
            Cache::forget(self::cacheKey('list_count_'.$data['merchant_id']));
        }
        $data['created_time'] = date('Y-m-d H:i:s');
        $data['updated_time'] = date('Y-m-d H:i:s');
        return self::insertGetId($data);
    }

    static function get_one($key, $value, $fields = '*'){
        $cachekey = self::cacheKey($key.'_'.$value);
        $data = Cache::get($cachekey);
        if(!$data){
            $data = self::query()->where($key,'=',$value)->where('status','=',1)->first();
            if($data) {
                $data =  $data ->toArray();
                Cache::put($cachekey, $data, 10);
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
            if(isset($data['status']) && $data['status'] == -1){
                Cache::forget(self::cacheKey('list_count_'.$info->merchant_id));
            }
            $data['updated_time'] = date('Y-m-d H:i:s');
            return self::query()->where('id','=',$info->id)->update($data);
        }else{
            return false;
        }
    }

    static function list_count($merchant_id){
        $cachekey = self::cacheKey('list_count_'.$merchant_id);
        $count = Cache::get($cachekey);
        if(!$count){
            $count = self::query()->where(['merchant_id'=>$merchant_id,'status'=>1,'bind'=>1])->count();
            if($count){
                Cache::put($cachekey, $count, 10);
            }
        }
        return $count;
    }

    static function list_data($key, $value){
        return self::query()->where($key,'=',$value)->where('status','=',1)->get()->toArray();
    }

}
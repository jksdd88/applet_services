<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use App\Utils\CacheKey;

class WeixinTransfers extends Model
{

    protected $table = 'weixin_transfers';
    protected $guarded = ['id'];
    public $timestamps = false;

    static function cacheKey($key){
        return CacheKey::get_weixin_models($key,'weixin_transfers');
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
            $data = self::query()->where($key,'=',$value)->where('is_delete','=',1)->first();
            if($data) {
                $data =  $data ->toArray();
                Cache::put($cachekey, $data, 1);
            }
        }
        return $data;
    }

    static function script_list($status){
        return self::query()->where('status','=',$status)->where('is_delete','=',1)->get()->toArray();
    }

    static function increment_data($id,$sum=1){
        if(!$id || !is_numeric($id))
            return false;
        return self::query()->where(['id'=>$id])->increment('reason_sum',$sum);
    }

    static function update_data($key, $value ,$data)
    {
        $info = self::query()->where($key,'=',$value)->where('is_delete','=',1)->first();
        if($info) {
            Cache::forget(self::cacheKey('id_'.$info->id));
            Cache::forget(self::cacheKey('appid_'.$info->appid));
            Cache::forget(self::cacheKey('order_no'.$info->order_no));
            Cache::forget(self::cacheKey('merchant_id_'.$info->merchant_id));
            $data['updated_time'] = date('Y-m-d H:i:s');
            return self::query()->where('id','=',$info->id)->update($data);
        }else{
            return false;
        }
    }

}






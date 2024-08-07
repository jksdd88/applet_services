<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WeixinExperiencer extends Model
{

    protected $table = 'weixin_experiencer';
    protected $guarded = ['id'];
    public $timestamps = false;

    static function cacheKey($key){
        return CacheKey::get_weixin_models($key,'weixin_experiencer');
    }
    static function clearCache($info){

    }


    static function insert_data($merchant_id, $appid, $name)
    {
        $info = static ::get_one($merchant_id, $appid, $name);
        if(isset($info['id']) && $info['id'] > 0) {
            return $info['id'];
        }else{
            $data['created_time'] = date('Y-m-d H:i:s');
            return self::insertGetId(['merchant_id'=>$merchant_id,'appid'=>$appid,'name'=>$name]);
        }
    }

    static function get_one($merchant_id, $appid, $name){
        $cachekey = self::cacheKey($merchant_id.'_'.$appid.'_'.$name);
        $data = Cache::get($cachekey);
        if(!$data){
            $data = self::where(['merchant_id'=>$merchant_id,'appid'=>$appid,'name'=>$name,'status'=>1]) ->first();
            if($data) {
                $data =  $data ->toArray();
                Cache::put($cachekey, $data, 10);
            }
        }
        return $data;

    }

    static function delete_data($merchant_id, $appid, $name){
        $info = static ::get_one($merchant_id, $appid, $name);
        if(isset($info['id'])) {
            Cache::forget(self::cacheKey($merchant_id.'_'.$appid.'_'.$name));
            $data['updated_time'] = date('Y-m-d H:i:s');
            return self::query()->where('id','=',$info['id'])->update(['status'=>-1]);
        }else{
            return false;
        }

    }

    static function list_data($merchant_id, $appid){
        return self::where(['merchant_id'=>$merchant_id,'appid'=>$appid,'status'=>1])->lists('name');
    }


}
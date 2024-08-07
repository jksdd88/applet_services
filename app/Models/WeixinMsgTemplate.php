<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use App\Utils\CacheKey;

class WeixinMsgTemplate extends Model
{

    protected $table = 'weixin_msg_template';
    protected $guarded = ['id'];
    public $timestamps = false;

    static function cacheKey($key){
        return CacheKey::get_weixin_models($key,'weixin_msg_template');
    }

    static function insert_data($data)
    {
        $data['created_time'] = date('Y-m-d H:i:s');
        $data['updated_time'] = date('Y-m-d H:i:s');
        return self::insertGetId($data);
    }

    static function get_one($merchant_id, $appid, $type){
        $cachekey = self::cacheKey($merchant_id.'_'.$appid.'_'.$type);
        $data = Cache::get($cachekey);
        if(!$data){
            $data = self::query()->where(['merchant_id' => $merchant_id , 'appid' => $appid, 'template_type'=>$type , 'status' => 1])->first();
            if($data) {
                $data =  $data ->toArray();
                Cache::put($cachekey, $data, 1);
            }
        }
        return $data;
    }

    static function get_one_type($merchant_id, $type){
        $cachekey = self::cacheKey('get_one_type_'.$merchant_id.'_'.$type);
        $data = Cache::get($cachekey);
        if(!$data){
            $data = self::query()->where(['merchant_id' => $merchant_id , 'template_type'=>$type , 'status' => 1])->first();
            if($data) {
                $data =  $data ->toArray();
                Cache::put($cachekey, $data, 1);
            }
        }
        return $data;
    }

    static function update_data($key, $value ,$data)
    {
        $info = self::query()->where($key,'=',$value)->where('status','=',1)->first();
        if($info) {
            Cache::forget(self::cacheKey($info->merchant_id.'_'.$info->appid.'_'.$info->template_type));
            Cache::forget(self::cacheKey('get_one_type_'.$info->merchant_id.'_'.$info->template_type));
            $data['updated_time'] = date('Y-m-d H:i:s');
            return self::query()->where('id','=',$info->id)->update($data);
        }else{
            return false;
        }
    }

    static function list_data($merchant_id, $appid,$type=1){
        return self::query()->where(['merchant_id' => $merchant_id , 'appid' => $appid,'app_type'=>$type,'status'=>1])->get()->toArray();
    }

}
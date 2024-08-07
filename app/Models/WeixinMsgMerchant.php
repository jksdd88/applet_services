<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Utils\CacheKey;
use Cache;

class WeixinMsgMerchant extends Model
{

    protected $table = 'weixin_msg_merchant';
    protected $guarded = ['id'];
    public $timestamps = false;

    static function cacheKey($key){
        return CacheKey::get_weixin_models($key,'weixin_msg_merchant');
    }

    static function insert_data($data)
    {
        $data['created_time'] = date('Y-m-d H:i:s');
        $data['updated_time'] = date('Y-m-d H:i:s');
        return self::insertGetId($data);
    }

    static function get_one($key,$val){
        $cachekey = self::cacheKey($key.'_'.$val);
        $data = Cache::get($cachekey);
        if(!$data){
            $data = self::query()->where($key,'=',$val) ->first();
            if($data) {
                $data =  $data ->toArray();
                Cache::put($cachekey, $data, 10);
            }
        }
        return $data;
    }

    static function check_open($open){
        $cachekey = self::cacheKey('check_open_'.$open);
        $data = Cache::get($cachekey);
        if(!$data){
            $data = self::query()->where(['openid'=>$open,'status'=>1]) ->first();
            if($data) {
                $data =  $data ->toArray();
                Cache::put($cachekey, $data, 10);
            }
        }
        return $data;
    }

    static function update_data($id,$data){
        $info = self::query()->where(['id'=>$id]) ->first();
        if($info){
            $info = $info->toArray();
            Cache::forget(self::cacheKey('id_'.$info['id']));
            Cache::forget(self::cacheKey('merchant_id_'.$info['merchant_id']));
            Cache::forget(self::cacheKey('appid_'.$info['appid']));
            Cache::forget(self::cacheKey('openid_'.$info['openid']));
            Cache::forget(self::cacheKey('check_open_'.$info['openid']));
            return self::query()->where('id','=',$id)->update($data);
        }
    }

    static function list_data($merchant_id, $appid){
        return self::where(['merchant_id'=>$merchant_id,'appid'=>$appid,'status'=> 1])->get()->toArray();
    }


}
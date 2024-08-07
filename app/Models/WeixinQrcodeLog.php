<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use App\Utils\CacheKey;

class WeixinQrcodeLog extends Model
{
    protected $table = 'weixin_qrcode_log';
    protected $guarded = ['id'];
    public $timestamps = false;

    static function cacheKey($key){
        return CacheKey::get_weixin_models($key,'weixin_qrcode_log');
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
            $data = self::query()->where($key,'=',$value)->first();
            if($data) {
                $data =  $data ->toArray();
                Cache::put($cachekey, $data, 10);
            }
        }
        return $data;
    }

    static function update_data($where,$data){
        $info = self::query()->where($where)->first();
        if($info) {
            Cache::forget(self::cacheKey('id_'.$info['id']));
            Cache::forget(self::cacheKey('appid_'.$info['appid']));
            Cache::forget(self::cacheKey('key_'.$info['sid']));
            $data['updated_time'] = date('Y-m-d H:i:s');
            return self::query()->where('id','=',$info->id)->update($data);
        }else{
            return false;
        }
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use App\Utils\CacheKey;

class WeixinQrcode extends Model
{

    protected $table = 'weixin_qrcode';
    protected $guarded = ['id'];
    public $timestamps = false;

    static function cacheKey($key){
        return CacheKey::get_weixin_models($key,'weixin_qrcode');
    }

    static function insert_data($data)
    {
        $data['created_time'] = date('Y-m-d H:i:s');
        return self::insertGetId($data);
    }
    static function check($sid, $type, $appid ){
        $cachekey = self::cacheKey($sid.'_'.$type.'_'.$appid);
        $data = Cache::get($cachekey);
        if(!$data){
            $data = self::query()->where(['sid'=>$sid,'appid'=>$appid,'type'=>$type,'is_delete'=>1])->first();
            if($data) {
                $data = $data ->toArray();
                Cache::put($cachekey, $data, 10);
            }
        }
        return $data;
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

    static function update_data($where,$data){
        $info = self::query()->where($where)->where('is_delete','=',1)->first();
        if($info) {
            Cache::forget(self::cacheKey('id_'.$info['id']));
            Cache::forget(self::cacheKey('merchant_id_'.$info['merchant_id']));
            Cache::forget(self::cacheKey('appid_'.$info['appid']));
            Cache::forget(self::cacheKey('sid_'.$info['sid']));
            Cache::forget(self::cacheKey($info['sid'].'_'.$info['type'].'_'.$info['appid']));
            $data['updated_time'] = date('Y-m-d H:i:s');
            return self::query()->where('id','=',$info->id)->update($data);
        }else{
            return false;
        }
    }

    static function list_data($merchant_id, $type = 0, $offset = 0, $limit = 0 ){
        $query = self::query()->where(['merchant_id'=>$merchant_id,'is_delete'=>1]);
        if($type==0){
            $query -> wherein('type',[1,2,3,4]);
        }else if($type==1){
            $query -> where('type','=',1);
        }else if($type==2){
            $query -> wherein('type',[2,3,4]);
        }
        return $query->skip($offset)->take($limit)->get()->toArray();
    }
    static function list_count($merchant_id, $type = 0){
        $query = self::query()->where(['merchant_id'=>$merchant_id,'is_delete'=>1]);
        if($type==0){
            $query -> wherein('type',[1,2,3,4]);
        }else if($type==1){
            $query -> where('type','=',1);
        }else if($type==2){
            $query -> wherein('type',[2,3,4]);
        }
        return $query->count();
    }

}
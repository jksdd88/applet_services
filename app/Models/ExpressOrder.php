<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use App\Utils\CacheKey;
use Illuminate\Support\Facades\DB;

class ExpressOrder extends Model
{

    protected $table = 'express_order';
    protected $guarded = ['id'];
    public $timestamps = false;

    static function cacheKey($key){
        return CacheKey::get_dada_express($key,'express_order');
    }
    static function clearCache($info){
        Cache::forget(self::cacheKey('id_'.$info['id']));
        Cache::forget(self::cacheKey('merchant_id_'.$info['merchant_id']));
        Cache::forget(self::cacheKey('shop_id_'.$info['shop_id']));
        Cache::forget(self::cacheKey('order_id_'.$info['order_id']));
        Cache::forget(self::cacheKey('dada_sn_'.$info['dada_sn']));
        Cache::forget(self::cacheKey('dada_waybill_'.$info['dada_sn'].$info['waybill_sn']));
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
            $data = self::query()->where($key,'=',$value)->where(['is_delete'=>1])  -> orderBy('id', 'DESC') ->first();
            if($data) {
                $data =  $data ->toArray();
                Cache::put($cachekey, $data, 10);
            }
        }
        return $data;
    }

    static function get_dada_waybill($dada_sn,$waybill_sn){
        $cachekey = self::cacheKey('dada_waybill_'.$dada_sn.$waybill_sn);
        $data = Cache::get($cachekey);
        if(!$data){
            $data = self::query()->where(['dada_sn'=>$dada_sn,'is_delete'=>1])->whereIn('waybill_sn',['',$waybill_sn]) -> orderBy('id', 'DESC') ->first();
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

    static function update_by_time($id, $statusTime ,$data)
    {
        $info = self::query()->where('id','=',$id)->where('is_delete','=',1)->where('time','<',$statusTime)->first();
        if($info) {
            static ::clearCache($info->toArray());
            $data['updated_time'] = date('Y-m-d H:i:s');
            return self::query()->where('id','=',$info->id)->update($data);
        }else{
            return false;
        }
    }


}
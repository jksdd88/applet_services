<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use App\Utils\CacheKey;

class LiveChannel extends Model
{
    //
    protected $table = 'live_channel';
    protected $guarded = ['id'];
    public $timestamps = false;

    static function cacheKey($key)
    {
        return CacheKey::get_live_cache($key,'live_channel');
    }

    static function insert_data($data)
    {
        return self::insertGetId($data);
    }

    static function get_one($key, $value ){
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

    static function update_data($key, $value ,$data)
    {
        $info = self::query()->where($key,'=',$value)->where('is_delete','=',1)->first();
        if($info) {
            Cache::forget(self::cacheKey('id_'.$info->id));
            Cache::forget(self::cacheKey('merchant_id_'.$info->merchant_id));
            Cache::forget(self::cacheKey('channel_id_'.$info->channel_id));
            $data['updated_time'] = date('Y-m-d H:i:s');
            return self::query()->where('id','=',$info->id)->update($data);
        }else{
            return false;
        }
    }
	
	//递增
	static function increment_data($id ,$merchant_id ,$field ,$val)
    {
        if(!$id || !is_numeric($id))return;
        if(!$merchant_id || !is_numeric($merchant_id))return;
		
		$info = self::query()->where(['id'=>$id,'merchant_id'=>$merchant_id])->first();
        if($info) {
            Cache::forget(self::cacheKey('id_'.$info->id));
            Cache::forget(self::cacheKey('merchant_id_'.$info->merchant_id));
            Cache::forget(self::cacheKey('channel_id_'.$info->channel_id));
            $data['updated_time'] = date('Y-m-d H:i:s');
            return self::query()->where(['id'=>$id,'merchant_id'=>$merchant_id])->increment($field, $val);
        }else{
            return false;
        }
    }

    static function selectFinishChannel($delay = 0){
        return self::query()->select(\DB::raw('id,channel_id,merchant_id,end_time,status,play_status'))->where('end_time','<',$_SERVER['REQUEST_TIME'] - $delay)->where('is_delete','=',1)->get()->toArray();
    }

    static function selectStartChannel($advance){
        return self::query()->select(\DB::raw('id,channel_id,merchant_id,end_time,status,play_status'))->where('start_time','<',$_SERVER['REQUEST_TIME']+$advance)->where('end_time','>', $_SERVER['REQUEST_TIME'])->where(['status'=>-1,'is_delete'=>1])->get()->toArray();
    }

    static function selectMaxChannel($delay = 0 ){
        return self::query()->select(\DB::raw('id,channel_id,merchant_id,view_max'))->where('start_time','<',$_SERVER['REQUEST_TIME'])->where('end_time','<', $_SERVER['REQUEST_TIME'] + $delay )->where(['is_delete'=>1])->get()->toArray();
    }

}

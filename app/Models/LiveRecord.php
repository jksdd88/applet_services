<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use App\Utils\CacheKey;

class LiveRecord extends Model
{
    //
    protected $table = 'live_record';
    protected $guarded = ['id'];
    public $timestamps = false;

    static function cacheKey($key)
    {
        return CacheKey::get_live_cache($key,'live_record');
    }

    static function insert_data($data)
    {
        return self::insertGetId($data);
    }

    static function get_one($key, $value ){
        $cachekey = self::cacheKey($key.'_'.$value);
        $data = Cache::get($cachekey);
        if(!$data){
            $data = self::query()->where($key,'=',$value)->where(['is_delete'=>1]) ->where('lid','>',0) -> orderBy('id', 'DESC') ->first();
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
            Cache::forget(self::cacheKey('lid_'.$info->lid));
            Cache::forget(self::cacheKey('vid_'.$info->vid));
            $data['updated_time'] = date('Y-m-d H:i:s');
            return self::query()->where('id','=',$info->id)->update($data);
        }else{
            return false;
        }
    }

    static function update_data_lid($lid ,$data)
    {
        $info = self::query()->where($lid,'=',$lid)->where('is_delete','=',1)->get()->toArray();
        foreach ($info as $k => $v) {
            Cache::forget(self::cacheKey('id_'.$info['id']));
            Cache::forget(self::cacheKey('merchant_id_'.$info['merchant_id']));
            Cache::forget(self::cacheKey('channel_id_'.$info['channel_id']));
            Cache::forget(self::cacheKey('lid_'.$info['lid']));
            Cache::forget(self::cacheKey('vid_'.$info['channel_id']));
            $data['updated_time'] = date('Y-m-d H:i:s');
            return self::query()->where('id','=',$info->id)->update($data);
        }
    }

    static function select_data($merchantId, $page=1, $length=15){
        return  self::query()->select(\DB::raw('id,merchant_id,lid,publish_status,length,play,download,status,start_time,end_time'))->where(['merchant_id'=>$merchantId,'is_delete'=>1,'status'=>2])-> orderBy('id', 'DESC')->skip(($page-1)*$length)->take($length)->get()->toArray();
    }

    static function select_count($merchantId){
        return  self::query()->where(['merchant_id'=>$merchantId,'is_delete'=>1,'status'=>2]) -> count();
    }

    static function selectVod($lid){
        //return  self::query()->select(\DB::raw('id,channel_id,merchant_id,vid'))->where(['lid'=>$lid,'is_delete'=>1,'status'=>2]) -> orderBy('id', 'DESC')->get()->toArray();
    }

    static function selectVodCount($lid){
        return  self::query()->where(['lid'=>$lid,'is_delete'=>1,'status'=>2]) ->count();
    }

    static function selectFinishVod(){
        return  self::query()->select(\DB::raw('id,vid'))->where(['is_delete'=>1,'status'=>2]) ->where('expire_time','<',$_SERVER['REQUEST_TIME'])->get()->toArray();
    }

    static function selectClosePublish(){
        return  self::query()->select(\DB::raw('id,vid,merchant_id'))->where(['is_delete'=>1,'status'=>2,'publish_status'=>1])->get()->toArray();
    }

    static function selectOnline(){
        return  self::query()->select(\DB::raw('id,vid,channel_id,merchant_id')) -> where('expire_time','>',$_SERVER['REQUEST_TIME'])->where(['is_delete'=>1,'status'=>2])->get()->toArray();
    }

	//递增
	static function increment_data($id ,$merchant_id ,$field ,$val)
    {
        if(!$id || !is_numeric($id))return;
        if(!$merchant_id || !is_numeric($merchant_id))return;
		
		$cachekey = self::cacheKey('id_'.$id);
        Cache::forget($cachekey);

        //->where(\DB::raw("$field + $val")  ,'=',0)
        return self::query()->where('id','=',$id)
            ->where('merchant_id','=',$merchant_id)
            ->increment($field, $val);

    }
	
	//递减
	static function decrement_data($id ,$merchant_id ,$field ,$val)
    {
        if(!$id || !is_numeric($id))return;
        if(!$merchant_id || !is_numeric($merchant_id))return;

        $cachekey = self::cacheKey('id_'.$id);
        Cache::forget($cachekey);

        return self::query()->where('id','=',$id)
            ->where('merchant_id','=',$merchant_id)
            ->where($field, '>=', abs($val))
            ->where(\DB::raw("$field - ".abs($val))  ,'>=',0)
            ->decrement($field, abs($val));
    }
}

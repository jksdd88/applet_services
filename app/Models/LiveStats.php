<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Support\Facades\Cache;
use App\Utils\CacheKey;

class LiveStats extends Model
{
    protected $table = 'live_stats';
    protected $guarded = ['id'];
    public $timestamps = false;

    static function cacheKey($key)
    {
        return CacheKey::get_live_cache($key,'live_stats');
    }

    static function insert_data($data)
    {
        return self::insertGetId($data);
    }

    static function check_one($channelId,$time,$valueType,$vid = ''){
        $cachekey = self::cacheKey('check_'.$channelId.$time.$valueType.$vid);
        $data = Cache::get($cachekey);
        if(!$data){
            $query = self::query()->where(['channel_id'=>$channelId, 'value_time'=>$time, 'value_type'=>$valueType ,'is_delete'=>1]) ;
            if(!empty($vid)){
                $query -> where('vid',$vid);
            }
            $data = $query -> orderBy('id', 'ASC') ->first();
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
            Cache::forget(self::cacheKey('merchant_id_'.$info->merchant_id));
            Cache::forget(self::cacheKey('channel_id_'.$info->channel_id));
            Cache::forget(self::cacheKey('value_time_'.$info->value_time));
            Cache::forget(self::cacheKey('check_'.$info->channel_id.$info->value_time.$info->value_type.$info->vid));
            $data['updated_time'] = date('Y-m-d H:i:s');
            return self::query()->where('id','=',$info->id)->update($data);
        }else{
            return false;
        }
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Support\Facades\Cache;
use App\Utils\CacheKey;

class LiveEvent extends Model
{
    //

    protected $table = 'live_event';
    protected $guarded = ['id'];
    public $timestamps = false;

    static function cacheKey($key)
    {
        return CacheKey::get_live_cache($key,'live_event');
    }

    static function insert_data($data)
    {
        return self::insertGetId($data);
    }

    static function get_one($channelId, $type){
        $data = self::query()->where([ 'channel_id' => $channelId ,'type' => $type , 'is_delete' => 1 ]) -> orderBy('id', 'DESC') ->first();
        if($data) {
            $data =  $data ->toArray();
        }
        return $data;
    }

    static function update_data($id ,$data)
    {
        return self::query()->where('id','=',$id)->update($data);
    }
}

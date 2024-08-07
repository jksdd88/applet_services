<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use App\Utils\CacheKey;

class ApiDoc extends Model
{
    protected $table = 'api_doc';//表明
    protected $guarded = ['id'];//不可被批量赋值的属性。
    public $timestamps = false;//时间戳定义 created_at   updated_at

    static function cacheKey($key){
        return CacheKey::get_open_api($key,'api_doc');
    }

    static function insertData($data)
    {
        $data['created_time'] = date('Y-m-d H:i:s');
        return self::insertGetId($data);
    }

    static function getDataList(){
        return self::query()->where(['is_delete'=>1])->get()->toArray();
    }


    static function updateData($key,$val,$data){
        $result =  self::query()->where([$key => $val,'is_delete'=>1])->first(['id','mid']);
        if($result && isset($result -> id)){
            $result = $result ->toArray();
            Cache::forget(self::cacheKey('one_id_'.$result['id']));
            Cache::forget(self::cacheKey('one_mid_'.$result['mid']));
            $data['updated_time'] = date('Y-m-d H:i:s');
            return self::query()->where(['id' => $result['id']])->update($data);
        }
        return false;

    }

    static function getDataOne($key,$val){
        $cachekey = self::cacheKey('one_'.$key.'_'.$val);
        $data = Cache::get($cachekey);
        if(!$data){
            $data =  self::query()->where([$key => $val,'is_delete'=>1])->first(['id','mid','title','text']);
            if($data){
                $data = $data->toArray();
                Cache::put($cachekey, $data, 10);
            }
        }
        return $data;
    }

}
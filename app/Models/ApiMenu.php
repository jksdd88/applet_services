<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use App\Utils\CacheKey;

class ApiMenu extends Model
{
    protected $table = 'api_menu';//表明
    protected $guarded = ['id'];//不可被批量赋值的属性。
    public $timestamps = false;//时间戳定义 created_at   updated_at


    static function cacheKey($key){
        return CacheKey::get_open_api($key,'api_menu');
    }

    static function insertData($data)
    {
        if(isset($data['pid'])){
            Cache::forget(self::cacheKey('pid_'.$data['pid'].'_0'));
            Cache::forget(self::cacheKey('pid_'.$data['pid'].'_1'));
        }
        $data['created_time'] = date('Y-m-d H:i:s');
        return self::insertGetId($data);
    }

    static function getDataList($did = 0,$pid = 0){
        $cachekey = self::cacheKey('pid_'.$pid.'_'.$did);
        $data = Cache::get($cachekey);
        if(!$data){
            $data = self::query()->where(['pid'=>$pid,'is_delete'=>1])->where('did','>=',$did)-> orderBy('sort', 'ASC')->get(['id','sort','did','title'])->toArray();
            if($data){
                Cache::put($cachekey, $data, 10);
            }
        }
        return $data;
    }

    static function updateData($key,$val,$data){
        $result =  self::query()->where([$key => $val,'is_delete'=>1])->first(['id','pid']);
        if($result && isset($result -> id)){
            $result = $result ->toArray();
            Cache::forget(self::cacheKey('pid_'.$result['pid'].'_0'));
            Cache::forget(self::cacheKey('pid_'.$result['pid'].'_1'));
            Cache::forget(self::cacheKey('id_'.$result['id']));
            $data['updated_time'] = date('Y-m-d H:i:s');
            return self::query()->where(['id' => $result['id']])->update($data);
        }
        return false;
    }

    static function getDataOneId($id){
        $cachekey = self::cacheKey('id_'.$id);
        $data = Cache::get($cachekey);
        if(!$data){
            $data =  self::query()->where(['id' => $id,'is_delete'=>1])->first(['id','pid','sort','title']);
            if($data){
                $data =  $data->toArray();
                Cache::put($cachekey, $data, 10);
            }
        }
        return $data;
    }


}
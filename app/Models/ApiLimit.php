<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use App\Utils\CacheKey;

class ApiLimit extends Model
{
    protected $table = 'api_limit';//表明
    protected $guarded = ['id'];//不可被批量赋值的属性。
    public $timestamps = false;//时间戳定义 created_at   updated_at

    static function cacheKey($key){
        return CacheKey::get_open_api($key,'api_limit');
    }

    static function insertData($data)
    {
        $data['created_time'] = date('Y-m-d H:i:s');
        return self::insertGetId($data);
    }

    static function incrementData($id){
        return self::query()->where('id','=',$id)->increment('count');
    }

    static function isOne($merchantId,$date,$api){
        $cachekey = self::cacheKey('one_'.$merchantId.$date.'_'.$api);
        $data = Cache::get($cachekey);
        if(!$data){
            $data =  self::query()->where(['merchant_id' => $merchantId,'date'=>$date,'api'=>$api])->first(['id','count']);
            if($data){
                $data = $data->toArray();
                Cache::put($cachekey, $data, 10);
            }
        }
        return $data;
    }
    /**
     * @name 接口限制
     * @param $merchantId 商户id
     * @param $apiType 接口
     * @param $limit 请求数量
     * @return bool
     */
    static function apiLimit($merchantId, $apiType, $limit){
        if($merchantId == 6) return true; //测试
        $limitCheck = self::isOne($merchantId,date('Ymd'),$apiType);
        if(!isset($limitCheck['id'])){
            self::insertData(['merchant_id'=>$merchantId,'date'=>date('Ymd'),'api'=>$apiType,'count'=>1]);
        }else{
            self::incrementData($limitCheck['id']);
        }
        if($limit > 0  && $limitCheck['count'] > $limit){
            return false;
        }else{
            return true;
        }
    }

}
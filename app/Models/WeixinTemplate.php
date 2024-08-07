<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use App\Utils\CacheKey;

class WeixinTemplate extends Model
{

    protected $table = 'weixin_template';
    protected $guarded = ['id'];
    public $timestamps = false;

    static function cacheKey($key){
        return CacheKey::get_weixin_models($key,'weixin_template');
    }

    static function clearCache($data){
        Cache::forget(self::cacheKey('id_'.$data['id']));
        Cache::forget(self::cacheKey('appid_'.$data['appid']));
        Cache::forget(self::cacheKey('merchant_id_'.$data['merchant_id']));
        Cache::forget(self::cacheKey('verify'.$data['merchant_id'].$data['appid']));
        Cache::forget(self::cacheKey('check'.$data['merchant_id'].$data['appid']));
        Cache::forget(self::cacheKey('pass'.$data['merchant_id'].$data['appid']));
        Cache::forget(self::cacheKey($data['merchant_id'].$data['appid']));
        Cache::forget(self::cacheKey($data['merchant_id'].$data['appid'].(string)$data['version_id']));

    }

    static function insert_data($data)
    {
        if(isset($data['merchant_id']) && isset($data['appid'])){
            Cache::forget(self::cacheKey($data['merchant_id'].$data['appid']));
        }
        $data['created_time'] = date('Y-m-d H:i:s');
        $data['updated_time'] = date('Y-m-d H:i:s');
        return self::insertGetId($data);
    }



    static function get_one_ver($merchantId, $appid, $version = 0 ){
        $key = self::cacheKey($merchantId.$appid.($version > 0 ? (string)$version : ''));
        $data =  Cache::get($key);
        if(!$data){
            $query = self::query()->where('merchant_id','=',$merchantId)->where('appid','=',$appid);
            if($version > 0 ){
                $query ->where('version_id','=',$version);
            }else{
                $query -> orderBy('id', 'DESC');
            }
            $data = $query->where('status','=',1)->first();
            if($data) {
                $data =  $data ->toArray();
                Cache::put($key, $data, 1);
            }
        }
        return $data;
    }

    static function get_pass_ver($merchantId, $appid){
        $key = self::cacheKey('pass'.$merchantId.$appid);
        $data =  Cache::get($key);
        if(!$data){
            $data = self::query()->where(['merchant_id'=>$merchantId,'appid'=>$appid,'status'=>1])->where('pass_date','>',0)  -> orderBy('id', 'DESC') ->first();
            if($data) {
                $data =  $data ->toArray();
                Cache::put($key, $data, 1);
            }
        }
        return $data;
    }

    static function get_one_verify($merchantId, $appid){
        $key = self::cacheKey('verify'.$merchantId.$appid);
        $data = Cache::get($key);
        if(!$data){
            $data = self::query()->where(['merchant_id'=>$merchantId,'appid'=>$appid,'verify'=>1,'release'=>1,'status'=>1])-> orderBy('id', 'DESC')->first();
            if($data) {
                $data =  $data ->toArray();
                Cache::put($key, $data, 1);
            }
        }
        return $data;
    }

    static function get_one_check($merchantId, $appid){
        $key = self::cacheKey('check'.$merchantId.$appid);
        $data = Cache::get($key);
        if(!$data){
            $data = self::query()->where(['merchant_id'=>$merchantId,'appid'=>$appid,'status'=>1])->where('check','>',0)-> orderBy('id', 'DESC')->first();
            if($data) {
                $data =  $data ->toArray();
                Cache::put($key, $data, 1);
            }
        }
        return $data;
    }

    static function get_one($key, $value, $fields = '*'){
        $cachekey = self::cacheKey($key.'_'.$value);
        $data = Cache::get($cachekey);
        if(!$data){
            $data = self::query()->where($key,'=',$value)->where('status','=',1)->first();
            if($data) {
                $data =  $data ->toArray();
                Cache::put($cachekey, $data, 1);
            }
        }
        return $data;
    }

    static function update_data($id ,$data)
    {
        $info = self::query()->where('id','=',$id)->where('status','=',1)->first();
        if($info) {
            static :: clearCache($info->toArray());
            $data['updated_time'] = date('Y-m-d H:i:s');
            return self::query()->where('id','=',$info->id)->update($data);
        }else{
            return false;
        }
    }

    static function delete_data_app($appid ,$data)
    {
        $info = self::query()->where('appid','=',$appid)->where('status','=',1)->get();
        foreach ($info as $key => $val) {
            static :: clearCache($val->toArray());
            $data['updated_time'] = date('Y-m-d H:i:s');
            self::query()->where('id','=',$val->id)->update($data);
        }
        return true;
    }

    static function check_audit_id($check){
        return self::query()->where('check','=',$check)->first();
    }

    static function list_data($merchant_id, $appid ){
        return self::query()->where('merchant_id','=',$merchant_id)->where('appid','=',$appid)->where('status','=',1)-> orderBy('id', 'DESC')->get()->toArray();
    }

    static function list_version_new($merchant_id, $appid , $version  ){
        return self::query()->where(['merchant_id'=>$merchant_id , 'appid' => $appid ,'status' => 1  ])->where('version_id','>',$version)-> orderBy('id', 'DESC')->get()->toArray();
    }

}
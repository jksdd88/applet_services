<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use App\Utils\CacheKey;
use Illuminate\Support\Facades\DB;

class WeixinInfo extends Model
{

    protected $table = 'weixin_info';
    protected $guarded = ['id'];
    public $timestamps = false;

    static function cacheKey($key){
        return CacheKey::get_weixin_models($key,'weixin_info');
    }
    static function clearCache($info){
        Cache::forget(self::cacheKey('id_'.$info['id']));
        Cache::forget(self::cacheKey('appid_'.$info['appid']));
        Cache::forget(self::cacheKey('user_name_'.$info['user_name']));//
        Cache::forget(self::cacheKey('merchant_id_'.$info['merchant_id']));

        Cache::forget(self::cacheKey('id_'.$info['id'].$info['type']));
        Cache::forget(self::cacheKey('appid_'.$info['appid'].$info['type']));
        Cache::forget(self::cacheKey('user_name_'.$info['user_name'].$info['type']));//
        Cache::forget(self::cacheKey('merchant_id_'.$info['merchant_id'].$info['type']));

        Cache::forget(self::cacheKey('merchant_appid_'.$info['merchant_id']));
        Cache::forget(self::cacheKey('merchant_appid_'.$info['merchant_id'].$info['appid']));

        Cache::forget(self::cacheKey('check_appid_'.$info['appid']));//user_name
        Cache::forget(self::cacheKey('check_id_'.$info['id']));
    }

    static function insert_data($data)
    {
        if($data['merchant_id']){
            Cache::forget(self::cacheKey('count_merchant_applet'.$data['merchant_id']));
        }
        $data['created_time'] = date('Y-m-d H:i:s');
        $data['updated_time'] = date('Y-m-d H:i:s');
        return self::insertGetId($data);
    }

    static function get_one($key, $value, $type = 0 ){
        $cachekey = self::cacheKey($key.'_'.$value.($type > 0 ? $type : ''));
        $data = Cache::get($cachekey);
        if(!$data){
            $query = self::query()->where($key,'=',$value)->where(['status'=>1]);
            if($type != 0){
                $query -> where('type','=',$type);
            }
            $data = $query -> orderBy('id', 'ASC') ->first();
            if($data) {
                $data =  $data ->toArray();
                Cache::put($cachekey, $data, 10);
            }
        }
        return $data;
    }
	
	//获取包括已删除的记录
	static function get_one_all($key, $value, $type = 0 ){
        $cachekey = self::cacheKey($key.'_all_'.$value.($type > 0 ? $type : ''));
        $data = Cache::get($cachekey);
        if(!$data){
            $query = self::query()->where($key,'=',$value);
            if($type != 0){
                $query -> where('type','=',$type);
            }
            $data = $query -> orderBy('id', 'ASC') ->first();
            if($data) {
                $data =  $data ->toArray();
                Cache::put($cachekey, $data, 10);
            }
        }
        return $data;
    }

    static function check_one($merchant_id,$appid){
        $cachekey = self::cacheKey('check_appid_'.$appid);
        $data = Cache::get($cachekey);
        if(!$data){
            $data = self::query()->where(['merchant_id'=>$merchant_id,'appid'=>$appid,'type'=>1]) -> orderBy('status', 'DESC')->first();
            if($data['id']){
                $data =  $data ->toArray();
                Cache::put($cachekey, $data, 10);
            }
        }
        return $data;
    }

    static function check_one_id($id){
        $cachekey = self::cacheKey('check_id_'.$id);
        $data = Cache::get($cachekey);
        if(!$data){
            $data = self::query()->where(['id'=>$id,'type'=>1]) ->first();
            if($data['id']){
                $data =  $data ->toArray();
                Cache::put($cachekey, $data, 10);
            }
        }
        return $data;
    }

    static function get_one_appid($merchant_id, $appid = ''){
        $key = self::cacheKey('merchant_appid_'.$merchant_id.(empty($appid)?'':$appid));
        $data = Cache::get($key);
        if(!$data){
            $query = self::query()->where(['merchant_id'=>$merchant_id,'type'=>1, 'status'=>1]);
            if(!empty($appid)){
                $query ->where('appid','=',$appid) ;
            }
            $data = $query -> orderBy('id', 'DESC') ->first();
            if($data) {
                $data =  $data ->toArray();
                Cache::put($key, $data, 10);
            }
        }
        return $data;
    }

    static function update_data($key, $value ,$data)
    {
        $info = self::query()->where($key,'=',$value)->where('status','=',1)->first();
        if($info) {
            static ::clearCache($info->toArray());
            $data['updated_time'] = date('Y-m-d H:i:s');
            return self::query()->where('id','=',$info->id)->update($data);
        }else{
            return false;
        }
    }

    static function delete_data($id, $null = 0){
        $info = self::query()->where('id','=',$id)->where('status','=',1)->first();
        if($info) {
            static ::clearCache($info->toArray());
            $data['updated_time'] = date('Y-m-d H:i:s');
            if($null == 0){
                return self::query()->where('id','=',$info->id)->update([ 'status'=>-1]);
            }else{
                return self::query()->where('id','=',$info->id)->update([ 'appid'=>'','token'=>'','authlist'=>'','status'=>1,'nick_name'=>'','head_img'=>'','user_name'=>'','signature'=>'','principal_name'=>'','business_info'=>'','qrcode_url'=>'','miniprograminfo'=>'']);
            }
        }else{
            return false;
        }
    }

    static function replace_data($info,$merchant_id){
        if($info) {
            return self::query()->where('id','=',$info['id'])->update( [
                'merchant_id'=>$merchant_id,
                'appid'=>'',
                'token'=>'',
                'authlist'=>'',
                'status'=>1,
                'nick_name'=>'',
                'head_img'=>'',
                'qrcode'=>'',
                'user_name'=>'',
                'signature'=>'',
                'principal_name'=>'',
                'alias'=>'',
                'qrcode_url'=>'',
                'business_info'=>'',
                'webview_domain'=>'',
                'miniprograminfo'=>'',
                'index_id'=>0,
                'updated_time' => date('Y-m-d H:i:s')
            ]);
        }else{
            return false;
        }
    }

    static function list_data($key, $value, $type = 1,$ticket_id = 0){
        $query =  self::query()->where([$key=>$value,'status'=>1]);
        if($type != 0){
            $query ->where('type','=',$type);
        }
        if($ticket_id != 0 ){
            $query ->where('ticket_id','=',$ticket_id);
        }
        return  $query -> orderBy('id', 'DESC')->get()->toArray();
    }

	static function list_page($merchant_id,$nickname, $offset = 0 ,$limit = 10 ,$type = 1, $showdel = 0){
		if($showdel) {
			$query = self::query()->where(['merchant_id'=>$merchant_id,'type'=>$type]);
		} else {
			$query = self::query()->where(['merchant_id'=>$merchant_id,'type'=>$type,'status'=>1]);
		}
        if(!empty($nickname)){
            $query ->where('nick_name','like','%'.$nickname.'%');
        }
        return $query->skip($offset)->take($limit)-> orderBy('id', 'DESC')->get()->toArray();
    }

    static function list_count($merchant_id,$nickname,$type = 1){
        $query =  self::query()->where(['merchant_id'=>$merchant_id,'type'=>$type,'status'=>1]);
        if(!empty($nickname)){
            $query ->where('nick_name','like',$nickname);
        }
        return $query ->count();
    }

    static function count_data($key, $value, $type = 1){
        $where = $type == 0 ? [ 'status'=>1] : ['type'=>$type, 'status'=>1];
        return self::query()->where($key,'=',$value)->where($where)->count();
    }

    static function count_merchant_applet($merchant_id){
        $cachekey = self::cacheKey('count_merchant_applet'.$merchant_id);
        $count = Cache::get($cachekey);
        if(!$count){
            $count =  self::query()->where(['merchant_id'=>$merchant_id,'type'=>1, 'status'=>1])->count();
            if($count) {
                Cache::put($cachekey, $count, 10);
            }
        }
        return $count;
    }

    static function count_app_data($merchant_id){
        return self::where(['merchant_id'=>$merchant_id, 'status'=>1])->where('appid','!=','')->count();
    }

    static function linshi_update($id){
        return DB::update('UPDATE `weixin_info` set `auth_time` = `created_time` WHERE id <= ?',[$id]);
    }

}
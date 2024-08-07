<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WeixinVersion extends Model
{

    protected $table = 'weixin_version';
    protected $guarded = ['id'];
    public $timestamps = false;

    static function cacheKey($key){
        return CacheKey::get_weixin_models($key,'weixin_version');
    }
    static function clearCache($info){

    }

    static function insert_data($data)
    {
        $data['created_time'] = date('Y-m-d H:i:s');
        return self::insertGetId($data);
    }

    static function get_one($key,$val){
        $info =  self::where([$key=>$val,'status'=>1]) ->first();
        return $info?$info->toArray():[];
    }

    static function get_version($ticket_id, $type = '' , $state = 1){
        $query =  self::where(['ticket_id'=>$ticket_id,'status'=>1]) ;
        if($state != 0){
            $query -> where('state','=',$state);
        }else{
            $query -> where('state','!=',2);
        }
        if(!empty($type)){
            $query -> where('type','=',$type);
        }
        $info  = $query -> orderBy('id', 'DESC') ->first();
        return isset($info['id']) ? $info->toArray() : [];
    }

    static function check_version($time,$ticket_id){
        return self::where('time','>=',$time) -> where(['ticket_id'=>$ticket_id,'status'=>1]) -> orderBy('id', 'ASC') ->first();
    }

    static function update_data($key,$val,$data){
        return  self::where([$key=>$val]) ->update($data);
    }

    static function select_end($type, $state = 1){
        return  self::query()->where(['type'=>$type,'status'=>1])  -> where('state','=',$state) ->orderBy('id', 'DESC') ->first();
    }

    static function list_data(){
        return self::query()->where(['status'=>1])-> orderBy('id', 'DESC')->get()->toArray();
    }

}
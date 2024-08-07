<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use App\Utils\CacheKey;

class WeixinFormId extends Model
{

    protected $table = 'weixin_formid';
    protected $guarded = ['id'];
    public $timestamps = false;

    static function insert_data($data)
    {
        $data['created_time'] = date('Y-m-d H:i:s');
        $data['updated_time'] = date('Y-m-d H:i:s');
        return self::insertGetId($data);
    }

    static function get_one($merchant_id,$member_id,$appid = '',$order = ''){
        $where = !empty($appid) ?  ['member_id'=>$member_id,'appid'=>$appid,'merchant_id'=>$merchant_id] : ['member_id'=>$member_id,'merchant_id'=>$merchant_id];
        $query  =  self::query()->where($where)->where('number','>',0)->where('time','>',time())->where('no','=','');
        if(empty($order)){
            return $query->first();
        }else{
            return $query-> orderBy('id', 'DESC')->first();
        }
    }

    static function get_count($member_id,$appid,$merchant_id){
        return self::query()->where(['member_id'=>$member_id,'appid'=>$appid,'merchant_id'=>$merchant_id])->count();
    }

    static function get_list($merchant_id,$member_id){
        return self::query()->where(['member_id'=>$member_id,'merchant_id'=>$merchant_id])->where('number','>',0)->where('time','>',time())->where('no','=','')-> orderBy('id', 'DESC')->get();
    }

    static function update_decrement($id){
        return  self::query()->where(['id'=>$id])-> decrement('number');
    }

    static function update_data($id,$data){
        return  self::query()->where(['id'=>$id])-> update($data);
    }

    static function delete_data($limit = 10000 ){
        return  self::query()->where('number','<',1)->orWhere('time','<',time())->delete();
    }


}
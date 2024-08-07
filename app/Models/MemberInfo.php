<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Utils\CacheKey;
use Cache;

class MemberInfo extends Model
{
    /**
     * 与模型关联的数据表。
     *
     * @var string
     */
    protected $table = 'member_info';

    /**
     * 指定是否模型应该被戳记时间。
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * 插入一条记录
     * @return id
     */
    static function insert_data($data)
    {
        $data['created_time'] = date('Y-m-d H:i:s');
        $data['updated_time'] = date('Y-m-d H:i:s');
        return self::insertGetId($data);
    }

    /**
     * count记录条数
     * @return int|count
     */
    static function get_data_count($wheres=array())
    {
        $query = self::query();
        foreach($wheres as $where) {
            $query->where($where['column'], $where['operator'], $where['value']);
        }
        return $query->count();
    }

    /**
     * 查询多条记录
     * @return array
     */
    static function get_data_list($wheres=array(), $fields = '*', $offset = 0, $limit = 10)
    {
        $query = self::query();
        foreach($wheres as $where) {
            $query->where($where['column'], $where['operator'], $where['value']);
        }
        $data = $query->select(\DB::raw($fields))->skip($offset)->take($limit)->orderBy('created_time', 'desc')->get();
        return json_decode($data,true);
    }
    
    /**
     * 查询
     * @auth wangshiliang@dodoca.com
     * @return array
     */
     static public function get_one_by_openid($openid,$merchant_id){        
        $data = self::query()->where(['open_id'=>$openid,'merchant_id'=>$merchant_id])->first();
        if($data) {
            $data =  $data ->toArray();           
        }        
        return $data;
     }

    /**
     * 查询
     * @auth wangshiliang@dodoca.com
     * @return array
     */

    static public function get_one($member_id,$appid,$merchant_id){
        $key = CacheKey::get_weixin_models($member_id.$appid.$merchant_id,'member_info');
        $data = Cache::get($key);
        if(!$data){
            $data = self::query()->where(['member_id'=>$member_id,'appid'=>$appid,'merchant_id'=>$merchant_id])->first();
            if($data) {
                $data =  $data ->toArray();
                Cache::put($key, $data, 10);
            }
        }
        return $data;
    }

    static public function clear_one($member_id,$appid,$merchant_id){
        $key = CacheKey::get_weixin_models($member_id.$appid.$merchant_id,'member_info');
        return Cache::forget($key);
    }


}

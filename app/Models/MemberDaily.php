<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Utils\CacheKey;
use Cache;
/*
 * 会员统计信息model
 * shangyazhao@dodoca.com
 */
class MemberDaily extends Model
{
    /**
     * 与模型关联的数据表。
     *
     * @var string
     */
    protected $table = 'member_daily';//会员每天统计

    /**
     * 指定是否模型应该被戳记时间。
     *
     * @var bool
     */
    public $timestamps = false;
    
    protected $connection = 'applet_stats';


    /**
     * 插入一条记录
     * @return id
     */
    static function insert_data($data)
    {
        $data['created_time'] = date('Y-m-d H:i:s');      
        return self::insertGetId($data);
    }

    /**
     * 查询一条记录
     * @return array
     */

    static function get_data_by_id($day, $merchant_id)
    {
        if(!$day)return;
        if(!$merchant_id || !is_numeric($merchant_id))return;

        $key = CacheKey::get_member_daily_by_id_key($day, $merchant_id);
        $data = Cache::get($key);
        if(!$data)
        {
            $data = self::query()->where('day_time','=',$day)->where('merchant_id','=',$merchant_id)->first();

            if($data)
            {
                Cache::add($key, $data, 120);
            }
        }
        return $data;

    }
    
    /**
     * 根据条件更新一条数据
     * @return int|修改成功条数
     */

    static function update_data_by_where($day ,$merchant_id, $wheres = [], $data)
    {
        if(!$day)return;
        if(!$merchant_id || !is_numeric($merchant_id))return;

        $key = CacheKey::get_member_daily_by_id_key($day, $merchant_id);
        Cache::forget($key);

        $query = self::query();
        foreach($wheres as $where) {
            $query->where($where['column'], $where['operator'], $where['value']);
        }
        return $query->update($data);
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
        $data = $query->select(\DB::raw($fields))->skip($offset)->take($limit)->orderBy('day_time', 'desc')->get();
        return json_decode($data,true);
    }
}

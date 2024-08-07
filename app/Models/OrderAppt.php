<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Utils\CacheKey;
use Illuminate\Support\Facades\Cache;

class OrderAppt extends Model
{
    protected $table='order_appt';

    protected $guarded = ['id'];
    /**
     * The name of the "created at" column.
     *
     * @var string
     */
    const CREATED_AT = 'created_time';

    /**
     * The name of the "updated at" column.
     *
     * @var string
     */
    const UPDATED_AT = 'updated_time';

    /**
     * 获取单条数据
     */
    static function get_data_by_id($id, $merchant_id, $fields = '*')
    {
        if(!$id || !is_numeric($id))return;
        if(!$merchant_id || !is_numeric($merchant_id))return;
        $data = self::query()->select(\DB::raw($fields))->where(['id'=>$id,'merchant_id'=>$merchant_id])->first();
        return $data;
    }

    /**
     * 获取单条数据
     */
    static function get_data_by_order($order_id, $merchant_id)
    {
        if(!$order_id || !is_numeric($order_id))return;
        if(!$merchant_id || !is_numeric($merchant_id))return;
        $data = self::query()->where(['order_id'=>$order_id,'merchant_id'=>$merchant_id])->first();
        return $data;
    }
    
    /**
     * 获取单条数据
     */
    static function get_data_by_code($code, $merchant_id, $fields = '*')
    {
        if(!$code)return;
        if(!$merchant_id || !is_numeric($merchant_id))return;
        $data = self::query()->select(\DB::raw($fields))->where(['hexiao_code'=>$code,'merchant_id'=>$merchant_id])->first();
        return $data;
    }
    
    static function update_data_by_code($code, $merchant_id, $data)
    {
        if(!$code)return;
        if(!$merchant_id || !is_numeric($merchant_id))return;
        $data['updated_time'] = date('Y-m-d H:i:s');
        return self::query()->where(['hexiao_code'=>$code,'merchant_id'=>$merchant_id])->update($data);
    }


    static function update_data($order_id, $merchant_id, $data)
    {
        if(!$order_id || !is_numeric($order_id))return;
        if(!$merchant_id || !is_numeric($merchant_id))return;
        $data['updated_time'] = date('Y-m-d H:i:s');
        return self::query()->where(['order_id'=>$order_id,'merchant_id'=>$merchant_id])->update($data);
    }
    
     /**
     * 查询多条记录
     * @return array
     */
    static function get_data_list($wheres=array(), $fields = '*', $sort='desc',$offset = 0, $limit = 10)
    {
        $query = self::query();
        foreach($wheres as $where) {
            $query->where($where['column'], $where['operator'], $where['value']);
        }
        $data = $query->select(\DB::raw($fields))->skip($offset)->take($limit)->orderBy('id', $sort)->get();
        return json_decode($data,true);
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
}

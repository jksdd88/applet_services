<?php

/**
 * 拼团库存表MOD
 *
 */
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Utils\CacheKey;
use Illuminate\Support\Facades\Cache;

class FightgroupStock extends Model
{
    protected $table = 'fightgroup_stock';
    protected $guarded = ['id'];
    
    const CREATED_AT = 'created_time';
    const UPDATED_AT = 'updated_time';

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
     * 获取库存表主键id
     * @author wangshen@dodoca.com
     * @cdate 2017-9-15
     * 
     * @param int $merchant_id  商户id
     * @param int $fightgroup_id  活动id
     * @param int $goods_id  商品id
     * @param int $spec_id  规格id（单规格为0）
     */
    static function get_id_by_ids($merchant_id,$fightgroup_id,$goods_id,$spec_id)
    {
        if(!$merchant_id || !is_numeric($merchant_id))return;
        if(!$fightgroup_id || !is_numeric($fightgroup_id))return;
        if(!$goods_id || !is_numeric($goods_id))return;


        $key = CacheKey::get_fightgroup_stock_id_by_ids_key($merchant_id,$fightgroup_id,$goods_id,$spec_id);
        $data = Cache::get($key);
        if(!$data)
        {
            $data = self::query()->where('merchant_id','=',$merchant_id)
                                ->where('fightgroup_id','=',$fightgroup_id)
                                ->where('goods_id','=',$goods_id)
                                ->where('spec_id','=',$spec_id)
                                ->value('id');
    
            if($data)
            {
                $key = CacheKey::get_fightgroup_stock_id_by_ids_key($merchant_id,$fightgroup_id,$goods_id,$spec_id);
                Cache::put($key, $data, 60);
            }
    
        }

        return $data;
    
    }
    
    
    
    
    /**
     * 修改数据
     */
    static function update_data($id, $merchant_id, $data)
    {
        if(!$id || !is_numeric($id))return;
        if(!$merchant_id || !is_numeric($merchant_id))return;
        
        //库存若有修改，清除缓存
        if(isset($data['stock'])){
            $stock = self::query()->where('id','=',$id)
                                  ->where('merchant_id','=',$merchant_id)
                                  ->value('stock');
            
            if($data['stock'] != $stock){
                $stock_key = CacheKey::get_fightgroup_stock_key($id, $merchant_id);
                Cache::forget($stock_key);
            }
            
        }
        
        $data['updated_time'] = date('Y-m-d H:i:s');
        return self::query()->where(['id'=>$id,'merchant_id'=>$merchant_id])->update($data);
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
        $data = $query->select(\DB::raw($fields))->skip($offset)->take($limit)->get();
        return json_decode($data,true);
    }
    
}

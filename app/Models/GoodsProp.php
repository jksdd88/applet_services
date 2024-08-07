<?php

namespace App\Models;

use App\Utils\CacheKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class GoodsProp extends Model
{
    protected $table='goods_prop';

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
     * 根据商品id获取商品属性规格
     * @param $goods_id
     * @param $merchant_id
     * @author: tangkang@dodoca.com
     */
    public static function get_data_by_goods($goods_id,$merchant_id){
        if (!$goods_id || !is_numeric($goods_id)) return;
        if (!$merchant_id || !is_numeric($merchant_id)) return;
//        $key = CacheKey::get_data_by_goods_key($goods_id, $merchant_id);
//        $data = Cache::get($key);
//        if (!$data) {
            $data = GoodsProp::where('merchant_id', $merchant_id)
                ->where('goods_id', $goods_id)
                ->where('is_delete', 1)
                ->get();
//                ->distinct()->lists('prop_id');//规格、属性名称 && 规格值、属性值
//            if ($data) {
//                $key = CacheKey::get_data_by_goods_key($goods_id, $merchant_id);
//                Cache::put($key, $data, 60);
//            }

//        }
        return $data;
    }

    /**
     * 清除缓存
     * @author zhangchangchun@dodoca.com
     */
    static function forgetCache($goods_id, $merchant_id)
    {
        if (!$goods_id || !is_numeric($goods_id)) return;
        if (!$merchant_id || !is_numeric($merchant_id)) return;

        $key = CacheKey::get_goodspec_by_goodsid_key($goods_id, $merchant_id);
        Cache::forget($key);
        return true;
    }
    
    /**
     * 查询多条记录
     *@param  $wheres['where'] 查询条件 二维数组
     *@param  $wheres['select'] 保留字段  v,v,v,v
     *@param  $wheres['wherein'] 查询条件 二维数组  k=>[][,k=>[]]
     *@param  $wheres['orderBy'] 排序条件 一维数组 k=>v,k=>v
     *@param  $wheres['offset'] 起始位置    v
     *@param  $wheres['limit'] 查询几条     v
     *@param  $wheres['lists'] lists    v[,v]
     *@param  $wheres['get'] get()
     *@param  $wheres['toArray'] toArray()
     *@author  renruiqi
     * @return array
     */
    static function get_data_list_new($wheres=array())
    {
        if(!$wheres) return;
        $query = self::query();
        if(isset($wheres['select'])){
            $query = $query->select(\DB::raw($wheres['select']));
        }
        if(isset($wheres['where'])){
            foreach($wheres['where'] as $v){
                $query = $query->where($v['column'], $v['operator'], $v['value']);
            }
        }
        if(isset($wheres['whereIn'])){

            foreach($wheres['whereIn'] as $k=>$v){
                $query = $query->whereIn($k,$v);
            }
        }
        if(isset($wherse['orderBy'])){
            foreach($wheres['orderBy'] as $k=>$v){
                $query = $query->orderBy($k,$v);
            }
        }
        if(isset($wheres['offset'])&&isset($wheres['limit'])){
            $query = $query->offset($wheres['offset'])->limit($wheres['limit']);
        }
        if(isset($wheres['lists'])){
            if(count($wheres['lists'])==2){
                $data = $query->lists($wheres['lists'][0],$wheres['lists'][1]);
            }else{
                $data = $query->lists($wheres['lists'][0]);
            }
        }
        if(isset($wheres['get'])){
            $data = $query->get();
        }
        if(isset($wheres['toArray'])){
            if(empty($data)||count($data)<1) return [];
            $data = $data->toArray();
        }
        return $data;
    }

    static function getDataByWhere($wheres,$fields='*'){
        $query = self::query();
        foreach($wheres as $v){
            $query = $query->where($v['column'], $v['operator'], $v['value']);
        }
        return $query->select($fields)->get();
    }

    static function updateByWhere($wheres, $update_data)
    {
        $query = self::query();
        foreach ($wheres as $where) {
            if($where['operator'] == 'in'){
                $query->whereIn($where['column'], $where['value']);
            }else{
                $query->where($where['column'], $where['operator'], $where['value']);
            }
        }
        $res = $query->update($update_data);
        return $res;

    }
}

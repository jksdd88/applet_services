<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiscountActivity extends Model {

    protected $table = 'discount_activity';
    protected $guarded = ['id'];
    const CREATED_AT = 'created_time';
    const UPDATED_AT = 'updated_time';

    public function discountItem(){
        return $this->hasOne(DiscountItem::class,'discount_id','id');
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
}

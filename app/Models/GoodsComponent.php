<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoodsComponent extends Model
{
    protected $table='goods_component';

    protected $guarded = ['id'];

    static function getDataByWhere($wheres,$fields='*',$order='listorder'){
        $query = self::query();
        foreach($wheres as $v){
            $query = $query->where($v['column'], $v['operator'], $v['value']);
        }
        return $query->orderBy($order)->select($fields)->get();
    }

    static function insert_data($data)
    {

        $data['created_time'] = date('Y-m-d H:i:s');
        $data['updated_time'] = date('Y-m-d H:i:s');
        return self::insertGetId($data);

    }

    static function updateByWhere($wheres,$data)
    {
        $query = self::query();
        foreach($wheres as $where) {
            $query->where($where['column'], $where['operator'], $where['value']);
        }
        $res = $query->update($data);
        return $res;

    }

    static function deleteDataByWhere($wheres){
        $query = self::query();
        foreach($wheres as $v){
            if($v['operator'] == 'in'){
                $query = $query->whereIn($v['column'], $v['value']);
            }else{
                $query = $query->where($v['column'], $v['operator'], $v['value']);
            }
        }
        return $query->delete();
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PropValue extends Model
{
    protected $table='prop_value';

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
     * 插入一条记录
     * @return id
     */
    static function insert_data($data)
    {

        $data['created_time'] = date('Y-m-d H:i:s');
        $data['updated_time'] = date('Y-m-d H:i:s');
        $data['is_delete'] = 1;
        return self::insertGetId($data);

    }

    static function updateByWhere($wheres,$data)
    {
        $query = self::query();
        foreach($wheres as $where) {
            if($where['operator'] == 'in'){
                $query->whereIn($where['column'], $where['value']);
            }else{
                $query->where($where['column'], $where['operator'], $where['value']);
            }
        }
        $res = $query->update($data);
        return $res;

    }

    static function updateOpenApi($wheres,$data)
    {
        return  self::query()->where($wheres)->update($data);
    }

    /***
     * @param $wheres
     * @Author  DuMing
     */
    static function getDataByWhere($wheres,$fields="*"){
        $query = self::query();
        foreach($wheres as $where) {
            if($where['operator'] == 'in'){
                $query->whereIn($where['column'], $where['value']);
            }else{
                $query->where($where['column'], $where['operator'], $where['value']);
            }
        }
        $data = $query->select($fields)->get();
        return $data;

    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServeLabel extends Model
{
    protected $table='serve_label';

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

    static function getDataByWhere($wheres,$fields='*',$order='listorder'){
        $query = self::query();
        foreach($wheres as $v){
            $query = $query->where($v['column'], $v['operator'], $v['value']);
        }
        if(!empty($order)){
            $query = $query->orderBy($order);
        }
        return $query->select($fields)->get();
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

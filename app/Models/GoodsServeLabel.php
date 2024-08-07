<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoodsServeLabel extends Model
{
    protected $table='goods_serve_label';

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

    static function getDataByWhere($wheres=array(),$fields='*'){
        $query = self::query();
        foreach ($wheres as $where) {
            $query->where($where['column'], $where['operator'], $where['value']);
        }
        $data = $query->select(\DB::raw($fields))->get();
        return $data;
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

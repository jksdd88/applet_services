<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopDesignComponent extends Model
{
    const CREATED_AT = 'created_time';
    const UPDATED_AT = 'updated_time';

    protected $table = 'shop_design_component';
    
	/**
	 * 插入数据
	 */
    static function insert_data($data)
    {
        $data['created_time'] = date('Y-m-d H:i:s');
        $data['updated_time'] = date('Y-m-d H:i:s');
        return self::insertGetId($data);
    }

    /**
     * 查询多条记录
     * @author denghongmei@dodoca.com
     * @return array
     */
    static function get_data_list($wheres=array(), $fields = '*')
    {
        $query = self::query();
        foreach($wheres as $where) {
            $query->where($where['column'], $where['operator'], $where['value']);
        }
        $data = $query->select(\DB::raw($fields))->orderBy('listorder', 'asc')->get();
        return json_decode($data,true);
    }

    /**
     * count记录条数
     * @author denghongmei@dodoca.com
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


    static public function update_data($id, $data)
    {
        if (!$id || !is_numeric($id)) return;
        $data['updated_time'] = date('Y-m-d H:i:s');
        return self::query()->where('id', '=', $id)->update($data);
    }
}

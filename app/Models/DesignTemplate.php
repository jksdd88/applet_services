<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DesignTemplate extends Model
{
   
    protected $table = 'design_template';
	
    const CREATED_AT = 'created_time';
    const UPDATED_AT = 'updated_time';

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
     * demo 修改一条记录
     * @return int|修改成功条数
     */

    static function update_data($id,  $data)
    {
        if(!$id || !is_numeric($id))return;

        $data['updated_time'] = date('Y-m-d H:i:s');
        return self::query()->where('id' , $id)->update($data);
    }

    /**
     * 查询一条记录
     * @return array
     */

    static function get_data_by_id($id)
    {
        if(!$id || !is_numeric($id))return;
        $data = self::query()->where(['id' => $id])->first();
        return $data;
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
        $data = $query->select(\DB::raw($fields))->skip($offset)->take($limit)->orderBy('created_time', 'desc')->get();
        return json_decode($data,true);
    }


}

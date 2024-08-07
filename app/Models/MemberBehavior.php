<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class MemberBehavior extends Model
{

    protected $table = 'member_behavior';
    protected $guarded = ['id'];
    public $timestamps = false;


    static function insert_data($data)
    {
        $data['created_time'] = date('Y-m-d H:i:s');
        $data['updated_time'] = date('Y-m-d H:i:s');
        return self::insertGetId($data);
    }

    /**
     * count
     * @return array
     */
    static function get_count($wheres=array())
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
    static function get_data_list($wheres=array(), $fields = '*', $offset = 0, $limit = 100)
    {
        $query = self::query();
        if(isset($wheres[0]['column'])) {
            foreach($wheres as $where) {
                $query->where($where['column'], $where['operator'], $where['value']);
            }
        } else {
            $query->where($wheres);
        }
        $data = $query->select(\DB::raw($fields))->skip($offset)->take($limit)->orderBy('created_time', 'desc')->get();
        return json_decode($data,true);
    }
    /**
     * demo 修改一条记录
     * @return int|修改成功条数
     */

    static function update_data($id ,$clerk_id, $data)
    {
        if(!$id || !is_numeric($id))return;
        if(!$clerk_id || !is_numeric($clerk_id))return;

        $data['updated_time'] = date('Y-m-d H:i:s');
        return self::query()->where('id','=',$id)->where('clerk_id','=',$clerk_id)->update($data);
    }

}
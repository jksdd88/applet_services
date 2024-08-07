<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018-02-22
 * Time: 上午 10:36
 */
namespace App\Models;

use Illuminate\Database\Eloquent\Model;


class Printer extends Model
{
    /**
     * 与模型关联的数据表。
     *
     * @var string
     */
    protected $table = 'print';

    /**
     * 指定是否模型应该被戳记时间。
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * 插入一条记录
     * @return id
     */
    static function insert_data($data)
    {
        $data['created_time'] = date('Y-m-d H:i:s');
//        $data['updated_time'] = date('Y-m-d H:i:s');
        return self::insertGetId($data);
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

    static function get_data_count($wheres=array())
    {
        $query = self::query();
        foreach($wheres as $where) {
            $query->where($where['column'], $where['operator'], $where['value']);
        }
        $data = $query->select(\DB::raw('count(1) as num'))->orderBy('created_time', 'desc')->first();
        return json_decode($data,true);
    }

    /**
     * 根据条件更新一条数据
     * @return int|修改成功条数
     */

    static function update_data_by_where( $wheres = [], $data)
    {
        $query = self::query();
        foreach($wheres as $where) {
            $query->where($where['column'], $where['operator'], $where['value']);
        }
        $data["updated_time"]=date("Y-m-d H:i:s");
        return $query->update($data);
    }

    static function get_one_data($wheres, $fields = '*')
    {
        $query = self::query();
        foreach($wheres as $where) {
            $query->where($where['column'], $where['operator'], $where['value']);
        }
        $data = $query->select(\DB::raw($fields))->first();
        return $data;

    }

}
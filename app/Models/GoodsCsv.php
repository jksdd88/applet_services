<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Utils\CacheKey;
use Illuminate\Support\Facades\Cache;

class GoodsCsv extends Model
{
    protected $table = 'goods_csv';

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
        return self::insertGetId($data);

    }

    /**
     * demo 查询一条记录
     * @return array
     */

    static function get_data_by_id($id, $merchant_id)
    {
        if (!$id || !is_numeric($id)) return;
        if (!$merchant_id || !is_numeric($merchant_id)) return;

        $data = self::query()->where('id', '=', $id)
            ->where('merchant_id', '=', $merchant_id)
            ->first();

        return $data;

    }

    /**
     * 删除一条记录
     * @return int| 删除条数
     */
    static function delete_data($id, $merchant_id)
    {
        if (!$id || !is_numeric($id)) return;
        if (!$merchant_id || !is_numeric($merchant_id)) return;

        $data['is_delete'] = -1;
        $data['updated_time'] = date('Y-m-d H:i:s');
        return self::query()->where(['id'=>$id,'merchant_id'=>$merchant_id])->update($data);
    }




    /**
     * count记录条数
     * @return int|count
     */
    static function get_data_count($wheres = array())
    {
        $query = self::query();
        foreach ($wheres as $where) {
            if($where['operator'] == 'in'){
                $query->whereIn($where['column'],$where['value']);
            }else{
                $query->where($where['column'], $where['operator'], $where['value']);
            }
        }
        return $query->count();
    }



    /***
     * 根据条件更新数据
     * @param $wheres
     * @param $update_data
     * @Author  DuMing
     */
    static function updateByWhere($wheres, $update_data)
    {
        $query = self::query();
        foreach ($wheres as $where) {
            $query->where($where['column'], $where['operator'], $where['value']);
        }
        $update_data['updated_time'] = date('Y-m-d H:i:s', time());
        $res = $query->update($update_data);
        return $res;
    }

    /***
     * @param $where
     * @param string $fields
     * @Author  DuMing
     * 根据条件获取数据
     */
    static function getDataByWhere($wheres, $fields = "*")
    {
        $query = self::query();
        foreach ($wheres as $where) {
            if ($where['operator'] == 'in') {
                $query->whereIn($where['column'], $where['value']);
            } else {
                $query->where($where['column'], $where['operator'], $where['value']);
            }
        }
        $data = $query->select($fields)->get();
        return $data;
    }

}

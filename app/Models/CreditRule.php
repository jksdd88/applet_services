<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CreditRule extends Model {

    protected $table = 'credit_rule';

    const CREATED_AT = 'created_time';
    const UPDATED_AT = 'updated_time';

    /**
     * 插入数据
     * @author gongruimin@dodoca.com
     */
    static function insert_data($data) {
        $data['created_time'] = date('Y-m-d H:i:s');
        $data['updated_time'] = date('Y-m-d H:i:s');
        return self::insertGetId($data);
    }

    /**
     * 获取各规则下的单条数据
     * @author gongruimin@dodoca.com
     */
    static function get_data_by_merchantid($merchant_id, $type, $enabled = 1, $fields = '*') {
        if (!$type || !is_numeric($type))
            return;
        if (!$merchant_id || !is_numeric($merchant_id))
            return;
        $data = self::query()->select(\DB::raw($fields))->where(['merchant_id' => $merchant_id, 'type' => $type, 'enabled' => $enabled])->first();
        return $data;
    }

    /**
     * 修改数据
     * @author gongruimin@dodoca.com
     */
    static function update_data($id, $merchant_id, $data) {
        if (!$id || !is_numeric($id))
            return;
        if (!$merchant_id || !is_numeric($merchant_id))
            return;
        $data['updated_time'] = date('Y-m-d H:i:s');
        return self::query()->where(['id' => $id, 'merchant_id' => $merchant_id])->update($data);
    }

    /**
     * count记录条数
     * @return int|count
     */
    static function get_data_count($wheres = array()) {
        $query = self::query();
        foreach ($wheres as $where) {
            $query->where($where['column'], $where['operator'], $where['value']);
        }
        return $query->count();
    }

    /**
     * 查询多条记录
     * @return array
     */
    static function get_data_list($wheres = array(), $fields = '*', $offset = 0, $limit = 10) {
        $query = self::query();
        foreach ($wheres as $where) {
            $query->where($where['column'], $where['operator'], $where['value']);
        }
        $data = $query->select(\DB::raw($fields))->skip($offset)->take($limit)->orderBy('created_time', 'desc')->get();
        return json_decode($data, true);
    }
    
        /**
     * 删除一条记录
     * @return int| 删除条数
     */
    static function delete_data($id)
    {
        if(!$id || !is_numeric($id))return;

        $key = CacheKey::get_demo_by_id_key($id);
        Cache::forget($key);

        $data['is_delete'] = -1;
        $data['updated_time'] = date('Y-m-d H:i:s');

        return self::query()->where('id','=',$id)->update($data);
    }


}

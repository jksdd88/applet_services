<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Utils\CacheKey;
use Cache;

class MemberAddress extends Model
{
    /**
     * 与模型关联的数据表。
     *
     * @var string
     */
    protected $table = 'member_address';
    public $timestamps = false;

    
    /**
     * 插入数据
     * @author denghongmei@dodoca.com
     */
    static function insert_data($data)
    {
        $data['created_time'] = date('Y-m-d H:i:s');
        $data['updated_time'] = date('Y-m-d H:i:s');
        return self::insertGetId($data);
    }

    /**
     * 获取单条数据
     * @author denghongmei@dodoca.com
     */
    static function get_data_by_id($id, $member_id, $fields = '*')
    {
        if(!$id || !is_numeric($id))return;
        if(!$member_id || !is_numeric($member_id))return;
        $data = self::query()->select(\DB::raw($fields))->where(['id'=>$id,'member_id'=>$member_id,'is_delete'=>1])->first();
        return json_decode($data,true);
    }

    /**
     * 修改数据
     * @author denghongmei@dodoca.com
     */
    static function update_data($id, $member_id, $data)
    {
        if(!$id || !is_numeric($id))return;
        if(!$member_id || !is_numeric($member_id))return;
        $data['updated_time'] = date('Y-m-d H:i:s');
        return self::query()->where(['id'=>$id,'member_id'=>$member_id,'is_delete'=>1])->update($data);
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

    /**
     * 查询多条记录
     * @author denghongmei@dodoca.com
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

    /**
     * 删除一条记录
     * @return int|删除条数
     */

    static function delete_data($id,$member_id)
    {
        if(!$id || !is_numeric($id))return;
        if(!$member_id || !is_numeric($member_id))return;

        $data['is_delete'] = -1;
        $data['updated_time'] = date('Y-m-d H:i:s');

        return self::query()->where(['id'=>$id,'member_id'=>$member_id,'is_delete'=>1])->update($data);
    }
}

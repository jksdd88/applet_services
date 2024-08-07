<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Utils\CacheKey;
use Cache;

class MemberCard extends Model
{
    /**
     * 与模型关联的数据表。
     *
     * @var string
     */
    protected $table = 'member_card';
    protected $guarded = ['id'];
    public $timestamps = false;


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
     * 查询一条记录
     * @return array
     */

    static function get_data_by_id($id, $merchant_id)
    {
        if(!$id || !is_numeric($id))return;
        if(!$merchant_id || !is_numeric($merchant_id))return;

        $key = CacheKey::get_member_card_by_id_key($id, $merchant_id);
        $data = Cache::get($key);
        if(!$data)
        {
            $data = self::query()->where('id','=',$id)->first();

            if($data)
            {
                Cache::add($key, $data, 120);
            }
        }
        return $data;

    }

    /**
     * demo 修改一条记录
     * @return int|修改成功条数
     */

    static function update_data($id ,$merchant_id,$data)
    {
        if(!$id || !is_numeric($id))return;
        if(!$merchant_id || !is_numeric($merchant_id))return;

        $key = CacheKey::get_member_card_by_id_key($id, $merchant_id);
        Cache::forget($key);

        $data['updated_time'] = date('Y-m-d H:i:s');
        return self::query()->where('id','=',$id)->update($data);
    }

    /**
     * demo 删除一条记录
     * @return int|删除条数
     */

    static function delete_data($id,$merchant_id)
    {
        if(!$id || !is_numeric($id))return;
        if(!$merchant_id || !is_numeric($merchant_id))return;

        $key = CacheKey::get_member_card_by_id_key($id, $merchant_id);
        Cache::forget($key);

        $data['is_delete'] = -1;
        $data['updated_time'] = date('Y-m-d H:i:s');

        return self::query()->where('id','=',$id)->update($data);
    }

    /**
     * count记录条数
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
     * @return array
     */
    static function get_data_list($wheres=array(), $fields = '*', $sort='desc',$offset = 0, $limit = 10)
    {
        $query = self::query();
        foreach($wheres as $where) {
            $query->where($where['column'], $where['operator'], $where['value']);
        }
        $data = $query->select(\DB::raw($fields))->skip($offset)->take($limit)->orderBy('id', $sort)->get();
        return json_decode($data,true);
    }
}

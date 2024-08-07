<?php

namespace App\Models;

use App\Utils\CacheKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Prop extends Model
{
    protected $table = 'prop';

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
        $data['is_delete'] = 1;
        return self::insertGetId($data);

    }

    static function getDataByCat($cat_id, $merchant_id,$file = [])
    {
        if (!$cat_id || !is_numeric($cat_id)) return;
        if (!$merchant_id || !is_numeric($merchant_id)) return;
        if(empty($file)){
            $data = self::query()->where('goods_cat_id', $cat_id)    ->whereIn('merchant_id', [$merchant_id, 0]) ->where('is_delete', '=', 1)  ->get();
        }else{
            $data = self::query()->select($file)->where('goods_cat_id', $cat_id) ->whereIn('merchant_id', [$merchant_id, 0])  ->where('is_delete', '=', 1)   ->get() ->toArray();
        }
        return $data;
    }

    static function updateByWhere($wheres, $data)
    {
        $query = self::query();
        foreach ($wheres as $where) {
            $query->where($where['column'], $where['operator'], $where['value']);
        }
        $res = $query->update($data);
        return $res;

    }

    static function updateOpenApi($wheres, $data)
    {
        return self::query()->where($wheres)->update($data);
    }

    /***
     * @param $wheres
     * @Author  DuMing
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

    /**
     * 获取预约名对应的主键id
     * @param $prop_key
     * @param $goods_cat_id 分类id，为null为获取列表id（每个分类下都有一套预约规格名）
     * @return \Illuminate\Database\Eloquent\Collection|void|static[]
     * @author: tangkang@dodoca.com
     */
    static function getPropIdByName($prop_key, $goods_cat_id = null)
    {
        if (!$prop_key || !is_string($prop_key)) return;
        $appt_name = config('appt.spec_name');
//        $appt_name = [
//            'store' => '预约门店',
//            'staff' => '服务人员',
//            'date' => '预约日期',
//            'time' => '预约时段',
//        ];
        $prop_name = $appt_name[$prop_key];
        $merchant_id = 0;
        $key = CacheKey::getPropIdByName($prop_key, $goods_cat_id, $merchant_id);
        $data = Cache::get($key);
        if (!$data) {
            $query = self::query()->where('title', $prop_name)
                ->where('merchant_id', $merchant_id);
            if ($goods_cat_id !== null) {
                $data = $query->where('goods_cat_id', $goods_cat_id)
                    ->where('prop_type', '=', 1)
                    ->where('is_delete', '=', 1)
                    ->value('id');
            } else {
                $data = $query->where('prop_type', '=', 1)
                    ->where('is_delete', '=', 1)
                    ->lists('id');
            }

            if ($data) {
                $key = CacheKey::getPropIdByName($prop_key, $goods_cat_id, $merchant_id);
                Cache::put($key, $data, 1800);
            }
        }
        return $data;
    }
}

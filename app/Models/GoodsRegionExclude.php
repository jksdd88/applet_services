<?php

/**
 * 商品配送区域排除表
 * @author 王禹
 * @package App\Models
 */
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Utils\CacheKey;
use Illuminate\Support\Facades\Cache;

class GoodsRegionExclude extends Model
{

    protected $table = 'goods_region_exclude';
    protected $guarded = ['id'];
    public $timestamps = false;

    //插入一条记录
    static function insert_data($data)
    {

        $data['created_time'] = date('Y-m-d H:i:s');
        $data['updated_time'] = date('Y-m-d H:i:s');
        return self::insertGetId($data);

    }

    //通过商品id 查询记录
    static function get_data_by_goodsid($goods_id ,$fields = '*')
    {
        if(!$goods_id || !is_numeric($goods_id))return;

        $key = CacheKey::get_goods_region_exclude_bygoodsid($goods_id);
        $data = Cache::get($key);
        if(!$data)
        {
            $data = self::query()->select(\DB::raw($fields))->where('goods_id','=',$goods_id)->first();

            if($data)
            {
                $key = CacheKey::get_goods_region_exclude_bygoodsid($goods_id);
                Cache::put($key, $data, 1);
            }

        }

        return $data;

    }

    /**
     * 清除缓存
     * @author zhangchangchun@dodoca.com
     */
    static function forgetCache($goods_id)
    {
        if (!$goods_id || !is_numeric($goods_id)) return;

        $key = CacheKey::get_goods_region_exclude_bygoodsid($goods_id);
        Cache::forget($key);
        return true;
    }

    static function getDataByWhere($wheres=array(),$fields='*'){
        $query = self::query();
        foreach ($wheres as $where) {
            $query->where($where['column'], $where['operator'], $where['value']);
        }
        $data = $query->select(\DB::raw($fields))->get();
        return $data;
    }

    static function updateByWhere($wheres, $update_data)
    {
        $query = self::query();
        foreach ($wheres as $where) {
            $query->where($where['column'], $where['operator'], $where['value']);
        }
        $res = $query->update($update_data);
        return $res;

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

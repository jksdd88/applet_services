<?php

/**
 * 节日营销--商家活动管理 Model demo
 * @author songyongshang@dodoca.com
 * @package App\Models
 */
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Utils\CacheKey;
use Illuminate\Support\Facades\Cache;

class HolidaymarketingMerchant extends Model
{

    protected $table = 'holiday_marketing_merchant';
    protected $guarded = ['id'];
    public $timestamps = false;

    //插入一条记录
    static function insert_data($data) {
        $data['created_time'] = date('Y-m-d H:i:s');
        $data['updated_time'] = date('Y-m-d H:i:s');
        return self::insertGetId($data);
    }

    //查询一条记录
    static function get_data_by_id($id, $fields = '*') {
        if(!$id || !is_numeric($id))return;

        $key = CacheKey::get_holiday_marketing_merchant_by_id($id);
        $data = Cache::get($key);
        if(!$data) {
            $data = self::query()->select(\DB::raw($fields))->where('id','=',$id)->first();

            if($data) {
                Cache::forever($key, $data);//第三个参数为缓存生命周期 单位：分钟
            }
        }

        return $data;
    }

    /**
     * 修改一条记录
     * @return int| 修改成功条数
     */
    static function update_data($id ,$data) {
        if(!$id || !is_numeric($id))return;

        $key = CacheKey::get_holiday_marketing_merchant_by_id($id);
        Cache::forget($key);

        $data['updated_time'] = date('Y-m-d H:i:s');
        return self::query()->where('id','=',$id)->update($data);
    }

    /**
     * 删除一条记录
     * @return int| 删除条数
     */
    static function delete_data($id) {
        if(!$id || !is_numeric($id))return;

        $key = CacheKey::get_holiday_marketing_merchant_by_id($id);
        Cache::forget($key);

        $data['is_delete'] = -1;
        $data['updated_time'] = date('Y-m-d H:i:s');

        return self::query()->where('id','=',$id)->update($data);
    }

    /**
     * count记录条数
     * @return int| count
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
    static function get_data_list($wheres=array(), $fields = '*', $offset = 0, $limit = 10)
    {
        $query = self::query();
        foreach($wheres as $where) {
            $query->where($where['column'], $where['operator'], $where['value']);
        }
        $data = $query->select(\DB::raw($fields))->skip($offset)->take($limit)->get();
        return json_decode($data,true);
    }
}

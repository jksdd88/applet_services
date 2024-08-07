<?php 

/**
 * 底部导航设置
 * @cdate 2017-11-21
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Utils\CacheKey;
use Illuminate\Support\Facades\Cache;

class ShopDesignNav extends Model {

    protected $table = 'shop_design_nav';
    protected $guarded = ['id'];
    
    const CREATED_AT = 'created_time';
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
     * 修改一条记录
     * @return int|修改成功条数
     */
    static function update_data($id,$merchant_id,$wxinfo_id,$data)
    {
        if(!$id || !is_numeric($id))return;
        if(!$merchant_id || !is_numeric($merchant_id))return;
        if(!$wxinfo_id || !is_numeric($wxinfo_id))return;
    
        $data['updated_time'] = date('Y-m-d H:i:s');
        return self::query()->where('id','=',$id)->where('merchant_id','=',$merchant_id)->where('wxinfo_id','=',$wxinfo_id)->update($data);
    
    }
    

    /**
     * 批量修改小程序id
     * @return int|修改成功条数
     */
    static function update_wxinfo_id($merchant_id,$old_wxinfo_id,$new_wxinfo_id)
    {
        if(!$merchant_id || !is_numeric($merchant_id))return;
        if(!$old_wxinfo_id || !is_numeric($old_wxinfo_id))return;
        if(!$new_wxinfo_id || !is_numeric($new_wxinfo_id))return;
        $data['updated_time'] = date('Y-m-d H:i:s');
        return self::query()->where('merchant_id','=',$merchant_id)->where('wxinfo_id','=',$old_wxinfo_id)->update(['wxinfo_id'=>$new_wxinfo_id]);
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
    static function get_data_list($wheres=array(), $fields = '*', $offset = 0, $limit = 10, $orderby_field = 'id', $orderby_type = 'ASC')
    {
        $query = self::query();
        foreach($wheres as $where) {
            $query->where($where['column'], $where['operator'], $where['value']);
        }
        $data = $query->select(\DB::raw($fields))->skip($offset)->take($limit)->orderBy($orderby_field, $orderby_type)->get();
        return json_decode($data,true);
    }
    
    
    
    
}
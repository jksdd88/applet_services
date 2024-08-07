<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopDesign extends Model
{
   
    protected $table = 'shop_design';
	
    const CREATED_AT = 'created_time';
    const UPDATED_AT = 'updated_time';

	/**
	 * 插入数据
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
    static function update_data($id,$merchant_id,$data)
    {
        if(!$id || !is_numeric($id))return;
        if(!$merchant_id || !is_numeric($merchant_id))return;     
        $data['updated_time'] = date('Y-m-d H:i:s');
        return self::query()->where('id',$id)->where('merchant_id',$merchant_id)->update($data);
    
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
	 * 获取单条数据
	 */
    static function get_data_by_shop_id($id,$wxinfo_id,$merchant_id, $fields = '*')
    {
        if(!$merchant_id || !is_numeric($merchant_id))return;
		$data = self::query()->select(\DB::raw($fields))->where(['merchant_id'=>$merchant_id])->where(['id'=>$id])->where(['wxinfo_id'=>$wxinfo_id])->first();
        return $data;
    }

    public function shopComponents() {
        return $this->hasMany('App\Models\ShopDesignComponent');
    }

    /**
     * 查询多条记录
     * @return array
     */
    static function get_data_list_first($wheres=array(), $fields = '*')
    {
        $query = self::query();
        if(isset($wheres[0]['column'])) {
            foreach($wheres as $where) {
                $query->where($where['column'], $where['operator'], $where['value']);
            }
        } else {
            $query->where($wheres);
        }
        $data = $query->select(\DB::raw($fields))->orderBy('listorder', 'asc')->first();
        return json_decode($data,true);
    }
}

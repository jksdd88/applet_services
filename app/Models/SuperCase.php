<?php
/**
 * 小程序案例
 * @author gongruimin@dodoca.com
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SuperCase extends Model
{
   
    protected $table = 'super_case';
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
	 * 获取手机端单条数据
	 */
    static function get_data_by_id($id,$fields = '*')
    {
        if(!$id || !is_numeric($id))return;
		$data = self::query()->select(\DB::raw($fields))->where(['id'=>$id,'onshow'=>1,'is_delete'=>1])->first();
        return $data;
    }

    /**
     * 获取超级后台单条数据
     */
    static function super_get_data_by_id($id, $fields = '*')
    {
        if(!$id || !is_numeric($id))return;
        $data = self::query()->select(\DB::raw($fields))->where(['id'=>$id,'is_delete'=>1])->first();
        return $data;
    }
    

    /**
	 * 用户修改数据
	 */
    static function update_data($id, $merchant_id,$member_id,$data)
    {
        if(!$id || !is_numeric($id))return;
        if(!$merchant_id || !is_numeric($merchant_id))return;
        if(!$member_id || !is_numeric($member_id))return;
		$data['updated_time'] = date('Y-m-d H:i:s');
        return self::query()->where(['id'=>$id,'merchant_id'=>$merchant_id,'member_id'=>$member_id])->update($data);
    }

    /**
     * 超级后台修改数据
     */
    static function super_update_data($id, $data)
    {
        if(!$id || !is_numeric($id))return;
        $data['updated_time'] = date('Y-m-d H:i:s');
        return self::query()->where(['id'=>$id])->update($data);
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
    static function get_data_list($wheres=array(), $fields = '*', $offset = 0, $limit = 100)
    {
        $query = self::query();
        if(isset($wheres[0]['column'])) {
			foreach($wheres as $where) {
				$query->where($where['column'], $where['operator'], $where['value']);
			}
		} else {
			$query->where($wheres);
		}
        $data = $query->select(\DB::raw($fields))->skip($offset)->take($limit)->orderBy('created_time', 'desc')->get();
        return json_decode($data,true);
    }
}

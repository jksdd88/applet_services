<?php namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Utils\CacheKey;
use Illuminate\Support\Facades\Cache;

class MerchantSetting extends Model {
    //模型

    protected $table = 'merchant_setting';

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
	 * 获取单条数据
	 * @author zhangchangchun@dodoca.com
	 */
    static function get_data_by_id($merchant_id)
    {
        if(!$merchant_id || !is_numeric($merchant_id))return;
		
		$key = CacheKey::merchant_setting($merchant_id);
        $data = Cache::get($key);
		
		if(!$data) {
			$data = self::query()->where(['merchant_id'=>$merchant_id])->first();		
			if($data)
			{
				Cache::put($key, $data, 60);
			}
		}
        return $data;
    }
	
	/**
     * 修改一条记录
     * @return int|修改成功条数
     */
    static function update_data($merchant_id,$data)
    {
        if(!$merchant_id || !is_numeric($merchant_id))return;
    
		$key = CacheKey::merchant_setting($merchant_id);
        Cache::forget($key);
    	
        $data['updated_time'] = date('Y-m-d H:i:s');
        return self::query()->where('merchant_id','=',$merchant_id)->update($data);
    
    }
	
	//干掉没用的
    /*static function updateByWhere($wheres,$data)
    {
        $query = self::query();
        foreach($wheres as $where) {
            $query->where($where['column'], $where['operator'], $where['value']);
        }
        $res = $query->update($data);
        return $res;

    }*/

    static function getDataByWhere($wheres, $fields)
    {
        $query = self::query();
        foreach ($wheres as $where) {
            $query->where($where['column'], $where['operator'], $where['value']);
        }
        $res = $query->select($fields)->get();
        return $res;

    }
	
	//递增余额
	static function increment_data($id ,$merchant_id ,$field ,$val)
    {
        if(!$id || !is_numeric($id))return;
        if(!$merchant_id || !is_numeric($merchant_id))return;
		
		$key = CacheKey::merchant_setting($merchant_id);
        Cache::forget($key);

        //->where(\DB::raw("$field + $val")  ,'=',0)
        return self::query()->where('id','=',$id)
            ->where('merchant_id','=',$merchant_id)
            ->increment($field, $val);

    }
	
	//递减余额
	static function decrement_data($id ,$merchant_id ,$field ,$val)
    {
        if(!$id || !is_numeric($id))return;
        if(!$merchant_id || !is_numeric($merchant_id))return;

        $key = CacheKey::merchant_setting($merchant_id);
        Cache::forget($key);

        return self::query()->where('id','=',$id)
            ->where('merchant_id','=',$merchant_id)
            ->where($field, '>=', abs($val))
            ->where(\DB::raw("$field - ".abs($val))  ,'>=',0)
            ->decrement($field, abs($val));
    }
}
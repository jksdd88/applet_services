<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Utils\CacheKey;
use Cache;

class LiveInfo extends Model
{
    /**
     * 与模型关联的数据表。
     *
     * @var string
     */
    protected $table = 'live_info';

    const CREATED_AT = 'created_time';
    const UPDATED_AT = 'updated_time';

	static function insert_data($data)
    {
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

        $key = CacheKey::get_live_by_id_key($id, $merchant_id);
        $data = Cache::get($key);
        $data = false;
        if(!$data)
        {
            $data = self::query()->where(['id' => $id, 'merchant_id' => $merchant_id, 'is_delete'=>1])->first();

            if($data)
            {
                Cache::put($key, $data, 60);
            }
        }
		
		//获取点赞数（不清理缓存）
		if($data) {
			$praise_key = CacheKey::live_praise($id);
			$praise = Cache::get($praise_key);
			if($praise) {
				$data['praise'] = $praise;
			}
		}
		
        return $data;
    }


    /**
     * demo 修改一条记录
     * @return int|修改成功条数
     */

    static function update_data($id, $merchant_id, $data)
    {
        if(!$id || !is_numeric($id))return;
        if(!$merchant_id || !is_numeric($merchant_id))return;

        $key = CacheKey::get_live_by_id_key($id, $merchant_id);
        Cache::forget($key);

        return self::query()->where(['id' => $id, 'merchant_id' => $merchant_id])->update($data);
    }

    /**
     * demo 删除一条记录
     * @return int|删除条数
     */

    static function delete_data($id, $merchant_id)
    {
        if(!$id || !is_numeric($id))return;

        $key = CacheKey::get_live_by_id_key($id, $merchant_id);
        Cache::forget($key);

        $data['is_delete'] = -1;

        return self::query()->where(['id' => $id, 'merchant_id' => $merchant_id])->update($data);
    }

	//递增
	static function increment_data($id, $merchant_id, $field, $val)
    {
        if(!$id || !is_numeric($id))return;
        if(!$merchant_id || !is_numeric($merchant_id))return;
		
		$key = CacheKey::get_live_by_id_key($id,$merchant_id);
        Cache::forget($key);

        //->where(\DB::raw("$field + $val")  ,'=',0)
        return self::query()->where('id','=',$id)
            ->where('merchant_id','=',$merchant_id)
            ->increment($field, $val);

    }
	
	//递减
	static function decrement_data($id ,$merchant_id ,$field ,$val)
    {
        if(!$id || !is_numeric($id))return;
        if(!$merchant_id || !is_numeric($merchant_id))return;

        $key = CacheKey::get_live_by_id_key($id,$merchant_id);
        Cache::forget($key);

        return self::query()->where('id','=',$id)
            ->where('merchant_id','=',$merchant_id)
            ->where($field, '>=', abs($val))
            ->where(\DB::raw("$field - ".abs($val))  ,'>=',0)
            ->decrement($field, abs($val));
    }
}

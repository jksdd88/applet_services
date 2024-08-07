<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Utils\CacheKey;
use Illuminate\Support\Facades\Cache;
class WeixinComponent extends Model
{

    protected $table = 'weixin_component';
    protected $guarded = ['id'];
    public $timestamps = false;

    static function cacheKey($key){
        return CacheKey::get_weixin_models($key,'weixin_component');
    }

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
     * demo 查询一条记录
     * @return array
     */

    static function get_data_by_id($id, $fields = '*')
    {
        if(!$id || !is_numeric($id))return;
        $key = self::cacheKey($id);
        $data = Cache::get($key);
        if(!$data)
        {
            $data = self::query()->where('id','=',$id)->first();
            if($data)
            {
                $time = strtotime($data->updated_time)+600 - $_SERVER['REQUEST_TIME'];
                $time = floor($time/60);
                if($time > 2){
                    Cache::put($key, $data, $time);
                }
            }
        }
        return $data;
    }

    /**
     * demo 修改一条记录
     * @return int|修改成功条数
     */

    static function update_data($id ,$data)
    {
        if(!$id || !is_numeric($id))return;

        Cache::forget(self::cacheKey($id));

        $data['updated_time'] = date('Y-m-d H:i:s');
        return self::query()->where('id','=',$id)->update($data);

    }



}
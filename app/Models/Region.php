<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Utils\CacheKey;
use Illuminate\Support\Facades\Cache;

class Region extends Model
{
    /**
     * 与模型关联的数据表。
     *
     * @var string
     */
    protected $table = 'region';

    
    /**
     * 插入数据
     * @author denghongmei@dodoca.com
     */
    static function insert_data($data)
    {
        $data['created_time'] = date('Y-m-d H:i:s');
        $data['updated_time'] = date('Y-m-d H:i:s');
        return self::insertGetId($data);
    }

    /**
     * 获取单条数据
     * @author denghongmei@dodoca.com
     */
    static function get_data_by_id($id, $fields = '*')
    {
        if(!$id || !is_numeric($id))return;
        $data = self::query()->select(\DB::raw($fields))->where(['id'=>$id])->first();
        return $data;
    }

    /**
     * 获取地址名称
     * @author denghongmei@dodoca.com
     */
    static function get_title_by_id($id, $fields = '*')
    {
        if(!$id || !is_numeric($id))return;
        $data = self::query()->select(\DB::raw($fields))->where(['id'=>$id])->value('title');
        return $data;
    }

    /**
     * 根据省市区模糊匹配
     * @author denghongmei@dodoca.com
     */
    static function get_data_by_title($title, $parent_id, $fields = '*')
    {
        if(!$title)return;
        if(!$parent_id || !is_numeric($parent_id))return;
        $data = self::query()->select(\DB::raw($fields))->where('title','like','%'.$title.'%')->where('parent_id','=',$parent_id)->first();
        return $data;
    }

    /**
     * 根据PID获取所有数据
     * @author 
     */
    static function get_data_by_parentid($parent_id)
    {
        if(!$parent_id) return false;

        $cache_key = CacheKey::get_region_list_by_parentid_key($parent_id);
        $data = Cache::get($cache_key);

        if(!$data){
            $data = self::query()->select('id', 'title')->where('parent_id', $parent_id)->where('id', '<>', 900000)->get();

            if($data){
                Cache::put($cache_key, $data, 1440);
            }
        }

        return $data;
        
    }
    
    /**
     * 获取区域名称
     * @param int $id  区域表主键id
     */
    static function get_title_by_id_cache($id)
    {
        if(!$id || !is_numeric($id))return;
    
    
        $key = CacheKey::get_region_title_by_id_key($id);
        $data = Cache::get($key);
        if(!$data)
        {
            $data = self::query()->where('id','=',$id)->value('title');
    
            if($data)
            {
                $key = CacheKey::get_region_title_by_id_key($id);
                Cache::put($key, $data, 60);
            }
    
        }
    
        return $data;
    
    }
    
}

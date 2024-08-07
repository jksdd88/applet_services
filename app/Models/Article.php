<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Utils\CacheKey;
use Illuminate\Support\Facades\Cache;
use App\Models\ArticleGroup;





class Article extends Model
{
    protected $table = 'article';
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
     * demo 查询文章
     * @return array
     */
    static function get_data_by_id($id, $merchant_id)
    {
        if (!$id || !is_numeric($id)) return;
        if (!$merchant_id || !is_numeric($merchant_id)) return;
        $key = CacheKey::get_article_by_id_key($id, $merchant_id);
        $data = Cache::get($key);
        // $data =null;
        if (!$data) {
            $data = self::query()->select('id','title','intro','group_ids','content','created_time','updated_time','read_num','img_json')
                ->where('merchant_id', '=', $merchant_id)
                ->find($id);
            if(count($data) < 1) return null;
            //分组列表
            $group_list = ArticleGroup::where('merchant_id',(int)$merchant_id)
                        ->where('is_delete',1)
                        ->lists('name','id');
            if( count($group_list )>0){
                $group_list = $group_list->toArray();
                //将group_ids 转化为名称
                preg_match_all('/\d+/',$data->group_ids,$preg_group_list);
                if(count($preg_group_list[0])>0){
                    $preg_group_list[0] = array_unique($preg_group_list[0]);   //去重
                    foreach($preg_group_list[0] as $vv){
                        if(array_key_exists(intval($vv),$group_list)){
                            $group_name_array[] = $group_list[intval($vv)];        //组装数组
                        }
                    }
                    // $data['group_name_array'] = $group_name_array;
                    $data['group_name_str'] = join($group_name_array,',');
                }else{
                    // $data['group_name_array'] = ['--'];               //无分组文章
                    $data['group_name_str'] = '--'; 
                }
            }/*else{
                return null;//若无分组信息说明数据非法
            } */
            if ($data) {
                $key = CacheKey::get_article_by_id_key($id, $merchant_id);
                Cache::put($key, $data, 60);
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
        if (!$id || !is_numeric($id)) return;
        if (!$merchant_id || !is_numeric($merchant_id)) return;
        $key = CacheKey::get_article_by_id_key($id, $merchant_id);
        Cache::forget($key);
        $data['updated_time'] = date('Y-m-d H:i:s');
        return self::query()->where('id', '=', $id)->where('merchant_id', '=', $merchant_id)->update($data);
    }

}

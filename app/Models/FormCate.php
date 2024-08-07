<?php
/**
   *超级表单分类表
*/
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\FormCateRel;
use App\Utils\CacheKey;
use Illuminate\Support\Facades\Cache;

class FormCate extends Model
{
    protected $table = 'form_cate';
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
     * 修改数据
     */
    static function update_data($id, $merchant_id, $data)
    {
        if(!$id || !is_numeric($id))return;
        if(!$merchant_id || !is_numeric($merchant_id))return;
        $key = CacheKey::get_formcate_by_formid_key($id, $merchant_id);     //获取key 清除缓存
        Cache::forget($key);
        $data['updated_time'] = date('Y-m-d H:i:s');
        return self::query()->where(['id'=>$id,'merchant_id'=>$merchant_id,'is_edit'=>2])->update($data);
    }


     /**
     * demo 查询一条记录
     * @return array
     */

    static function get_data_by_id($id,$merchant_id)
    {
        if(!$id || !is_numeric($id))return;
        if(!$merchant_id || !is_numeric($merchant_id))return;

        $key = CacheKey::get_formcate_by_formid_key($id,$merchant_id);
        $data = Cache::get($key);
        if(!$data)
        {
            $data = self::query()->where('id','=',$id)
                ->where('is_delete','=',1)
                ->whereIn('merchant_id',[0,$merchant_id])
                ->first();
            if($data)
            {
                $key = CacheKey::get_formcate_by_formid_key($id,$merchant_id);
                Cache::put($key, $data, 60);
            }
        }
        return $data;
    }
    
}

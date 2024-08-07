<?php

/**
 * 意见反馈
 * @author songyongshang@dodoca.com
 * @package App\Models
 */
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Utils\CacheKey;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;

class Feedback extends Model
{

    protected $table = 'feedback';
    protected $guarded = ['id'];
    public $timestamps = false;

    /**
     * 插入一条记录
     */
    static function insert_data($data) {
        $data['created_time'] = date('Y-m-d H:i:s');
        $data['updated_time'] = date('Y-m-d H:i:s');
        return self::insertGetId($data);
    }

    /**
     * 查询一条记录
     */
    static function get_data_by_id($id, $fields = '*') {
        if(!$id || !is_numeric($id))return;

        $key = CacheKey::get_feedback_by_id($id);
        $data = Cache::get($key);
        //$data = Cache::forget($key);
        if(!$data) {
            $data = self::query()->select(\DB::raw($fields))->where(['id'=>$id,'merchant_id'=>Auth::user()->merchant_id])->first();

            if($data) {
                Cache::forever($key, $data);//第三个参数为缓存生命周期 单位：分钟
            }
        }

        return $data;
    }

    /**
     * 修改一条记录
     */
    static function update_data($id ,$data) {
        if(!$id || !is_numeric($id))return;

        $key = CacheKey::get_feedback_by_id($id);
        Cache::forget($key);

        $data['updated_time'] = date('Y-m-d H:i:s');
        return self::query()->where('id','=',$id)->update($data);
    }

    /**
     * 删除一条记录
     */
    static function delete_data($id) {
        if(!$id || !is_numeric($id))return;

        $key = CacheKey::get_feedback_by_id($id);
        Cache::forget($key);

        $data['is_delete'] = -1;
        $data['updated_time'] = date('Y-m-d H:i:s');

        return self::query()->where('id','=',$id)->update($data);
    }

    /**
     * count记录条数
     */
    static function get_data_count($wheres=array()) {
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
    static function get_data_list($wheres=array(), $fields = '*', $offset = 0, $limit = 10) {
        $query = self::query();
        foreach($wheres as $where) {
            $query->where($where['column'], $where['operator'], $where['value']);
        }
        $data = $query->select(\DB::raw($fields))->orderby('created_time','desc')->skip($offset)->take($limit)->get();
        return json_decode($data,true);
    }
}

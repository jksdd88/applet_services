<?php

/**
 * 投票主题表 Model demo
 * @author songyongshang@dodoca.com
 * @package App\Models
 */
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Utils\CacheKey;
use Illuminate\Support\Facades\Cache;

class VoteDetail extends Model
{

    protected $table = 'vote_details';
    protected $guarded = ['id'];
    public $timestamps = false;
    protected $connection = 'applet_cust';

    //插入一条记录
    static function insert_data($data) {
        //清除缓存 所有投票选项
        $key_all_votedetail = CacheKey::get_all_vote_details_by_vote_id($data['vote_id']);
        Cache::forget($key_all_votedetail);
        
        $data['created_time'] = date('Y-m-d H:i:s');
        $data['updated_time'] = date('Y-m-d H:i:s');
        return self::insertGetId($data);
    }

    /**
     * 查询一条记录
     */
    static function get_data_by_id($vote_id, $vote_detail_id, $fields = '*') {
        if(!$vote_detail_id || !is_numeric($vote_detail_id))return;
        //设置缓存 单个投票选项
        $key = CacheKey::get_vote_detail_by_vote_detail_id($vote_id,$vote_detail_id);
        $data = Cache::get($key);
        if(!$data) {
            $data = self::query()->select(\DB::raw($fields))->where(['vote_id'=>$vote_id,'id'=>$vote_detail_id,'is_delete'=>1])->first();

            if($data) {
                Cache::forever($key, $data);//第三个参数为缓存生命周期 单位：分钟
            }
        }

        return $data;
    }

    /**
     * 查询商户下的所有投票选项记录
     */
    static function get_alldata_by_vote_id($vote_id, $fields = '*') {
        if(!$vote_id || !is_numeric($vote_id))return;
        //设置缓存 所有投票选项
        $key_all_votedetail = CacheKey::get_all_vote_details_by_vote_id($vote_id);
        //Cache::forget($key_all_votedetail);
        $data = Cache::get($key_all_votedetail);
        //
        if(!$data) {
            $data = self::query()->select(\DB::raw($fields))->where(['vote_id'=>$vote_id,'is_delete'=>1])->orderBy('order_num')->get();
            if(!$data->isEmpty()) {
                Cache::forever($key_all_votedetail, $data);//第三个参数为缓存生命周期 单位：分钟
            }
        }
    
        return $data;
    }
    
    /**
     * 修改一条记录
     */
    static function update_data($vote_id, $vote_detail_id ,$data) {
        if(!$vote_id || !is_numeric($vote_id))return;
        if(!$vote_detail_id || !is_numeric($vote_detail_id))return;
        
        //清除缓存 单个投票选项
        $key = CacheKey::get_vote_detail_by_vote_detail_id($vote_id, $vote_detail_id);
        Cache::forget($key);
        //清除缓存 所有投票选项
        $key_all_votedetail = CacheKey::get_all_vote_details_by_vote_id($vote_id);
        Cache::forget($key_all_votedetail);
        
        $data['updated_time'] = date('Y-m-d H:i:s');
        return self::query()->where('id','=',$vote_detail_id)->update($data);
    }

    /**
     * 删除一条记录
     */
    static function delete_data($vote_id, $vote_detail_id) {
        if(!$vote_id || !is_numeric($vote_id))return;
        if(!$vote_detail_id || !is_numeric($vote_detail_id))return;

        //清除缓存 单个投票选项
        $key = CacheKey::get_vote_detail_by_vote_detail_id($vote_detail_id);
        Cache::forget($key);
        //清除缓存 所有投票选项
        $key_all_votedetail = CacheKey::get_all_vote_details_by_vote_id($vote_id);
        Cache::forget($key_all_votedetail);
        
        $data['is_delete'] = -1;
        $data['updated_time'] = date('Y-m-d H:i:s');

        return self::query()->where('id','=',$vote_detail_id)->update($data);
    }

    /**
     * count记录条数
     * @return int| count
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
        $data = $query->select(\DB::raw($fields))->skip($offset)->take($limit)->get();
        return json_decode($data,true);
    }
}

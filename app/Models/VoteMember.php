<?php

/**
 * 会员投票表 Model demo
 * @author songyongshang@dodoca.com
 * @package App\Models
 */
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Utils\CacheKey;
use Illuminate\Support\Facades\Cache;

class VoteMember extends Model
{

    protected $table = 'vote_member';
    protected $guarded = ['id'];
    public $timestamps = false;
    protected $connection = 'applet_cust';

    //插入一条记录
    static function insert_data($data) {
        //清除缓存 所有投票选项
        $key = CacheKey::get_vote_member_by_vote_id($data['vote_id'], $data['member_id']);
        Cache::forget($key);
        
        $data['created_time'] = date('Y-m-d H:i:s');
        $data['updated_time'] = date('Y-m-d H:i:s');
        return self::insertGetId($data);
    }

    /**
     * 查询会员的所有投票记录
     */
    static function get_data_by_vote_id($vote_id, $member_id, $fields = '*') {
        if(!$vote_id || !is_numeric($vote_id))return;
        if(!$member_id || !is_numeric($member_id))return;

        //设置缓存 我的投票详情
        $key = CacheKey::get_vote_member_by_vote_id($vote_id, $member_id);
        //Cache::forget($key);
        $data = Cache::get($key);
        
        //dd($data);
        if(!$data) {
            $data = self::query()->select(\DB::raw($fields))->where(['vote_id'=>$vote_id,'member_id'=>$member_id,'is_delete'=>1])->get();
            
            if(!$data->isEmpty()) {
                Cache::forever($key, $data);//第三个参数为缓存生命周期 单位：分钟
            }else{
                $data = array();
            }
        }

        return $data;
    }

}

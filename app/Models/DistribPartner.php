<?php

/**
 * 推客表Model
 * @author 王禹
 * @package App\Models
 */
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use App\Utils\CacheKey;
use Illuminate\Support\Facades\Cache;

class DistribPartner extends Model
{

    protected $table = 'distrib_partner';
    protected $guarded = ['id'];
    public $timestamps = false;



    /**
     *  插入一条记录
     *  备注: 若无上级推客 则parent_member_id = 0
     *        若未审核  则check_time = '0000-00-00 00:00:00'
     *        小程序端当商城设置自动审核时 请调用DistribServer->addDistribCheckLog();
     * @return id
     */
    static function insert_data($data)
    {

        $data['created_time'] = date('Y-m-d H:i:s');
        $data['updated_time'] = date('Y-m-d H:i:s');
        return self::insertGetId($data);

    }

    /**
     * 通过id查询一条记录
     * @return array
     */

    static function get_data_by_memberid($member_id , $merchant_id)
    {
        if(!$member_id || !is_numeric($member_id))return;
        if(!$merchant_id || !is_numeric($merchant_id))return;

        $key = CacheKey::get_distrib_partner_byid_key($member_id,$merchant_id);
        $data = Cache::get($key);
        if(!$data)
        {
            $data = self::query()->where('member_id','=',$member_id)->where('merchant_id','=',$merchant_id)->first();

            if($data)
            {
                Cache::put($key, $data, 120);
            }

        }

        return $data;

    }


    /**
     * 修改一条记录
     * @return int|修改成功条数
     */

    static function update_data($member_id ,$merchant_id ,$data)
    {
        if(!$member_id || !is_numeric($member_id))return;
        if(!$merchant_id || !is_numeric($merchant_id))return;

        $key = CacheKey::get_distrib_partner_byid_key($member_id ,$merchant_id);
        Cache::forget($key);

        $data['updated_time'] = date('Y-m-d H:i:s');
        return self::query()->where('member_id','=',$member_id)->where('merchant_id','=',$merchant_id)->update($data);

    }

    /**
     * 递增
     * @return int|成功条数
     */

    static function increment_data($member_id ,$merchant_id ,$field ,$val)
    {
        if(!$member_id || !is_numeric($member_id))return;
        if(!$merchant_id || !is_numeric($merchant_id))return;

        $key = CacheKey::get_distrib_partner_byid_key($member_id ,$merchant_id);
        Cache::forget($key);

        //->where(\DB::raw("$field + $val")  ,'=',0)
        return self::query()->where('member_id','=',$member_id)
            ->where('merchant_id','=',$merchant_id)
            ->increment($field, $val);

    }


    /**
     * 递减
     * @return int|成功条数
     */

    static function decrement_data($member_id ,$merchant_id ,$field ,$val)
    {
        if(!$member_id || !is_numeric($member_id))return;
        if(!$merchant_id || !is_numeric($merchant_id))return;

        $key = CacheKey::get_distrib_partner_byid_key($member_id ,$merchant_id);
        Cache::forget($key);

        return self::query()->where('member_id','=',$member_id)
            ->where('merchant_id','=',$merchant_id)
            ->where($field, '>=', abs($val))
            ->where(\DB::raw("$field - ".abs($val))  ,'>=',0)
            ->decrement($field, abs($val));
    }
}
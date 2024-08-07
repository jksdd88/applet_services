<?php

/**
 * 优惠券群发记录表
 * @author wangshen@dodoca.com
 * @cdate 2018-7-3
 */
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CouponSend extends Model
{

    protected $table = 'coupon_send';
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
     * 修改一条记录
     * @return int|修改成功条数
     */
    static function update_data($id,$merchant_id,$data)
    {
        if(!$id || !is_numeric($id))return;
        if(!$merchant_id || !is_numeric($merchant_id))return;
        
        $data['updated_time'] = date('Y-m-d H:i:s');
        return self::query()->where('id','=',$id)->where('merchant_id','=',$merchant_id)->update($data);
    
    }

    
    
}
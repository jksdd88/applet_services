<?php
/**
 * 小程序加盟线索表
 * @author wangshen@dodoca.com
 * @cdate 2018-4-9
 * 
 */
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class XcxJoinclue extends Model
{

    protected $connection = 'applet_cust';
    protected $table = 'xcx_joinclue';
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
    
    
}
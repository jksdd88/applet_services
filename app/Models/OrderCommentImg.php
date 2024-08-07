<?php
/**
 * 订单评论图片表
 * @author zhangchangchun@dodoca.com
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderCommentImg extends Model
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'order_comment_img';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    const CREATED_AT = 'created_time';
    const UPDATED_AT = 'updated_time';
	
	/**
	 * 插入数据
	 * @author zhangchangchun@dodoca.com
	 */
    static function insert_data($data)
    {
        $data['created_time'] = date('Y-m-d H:i:s');
        $data['updated_time'] = date('Y-m-d H:i:s');
        return self::insertGetId($data);
    }
	
}

<?php
/**
 * Created by PhpStorm.
 * User: jiangnan
 * Date: 2017/9/7
 * Time: 15:13
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Utils\CacheKey;
use Cache;

class Comment extends Model {

    protected $table = 'order_comment';

    protected $guarded = ['id'];

    public $timestamps = false;

    /**
     * demo 修改一条记录
     * @return int|修改成功条数
     */

    static function update_data($id ,$merchant_id,$data)
    {
        if(!$id || !is_numeric($id))return;
        if(!$merchant_id || !is_numeric($merchant_id))return;

        $key = CacheKey::get_order_comment_by_id_key($id, $merchant_id);
        Cache::forget($key);

        $data['updated_time'] = date('Y-m-d H:i:s');
        return self::query()->where('id','=',$id)->update($data);
    }

    /**
     * 插入数据
     * @author zhangchangchun@dodoca.com
     */
    static function insert_data($data)
    {
        $data['created_time'] = date('Y-m-d H:i:s');
        //$data['updated_time'] = date('Y-m-d H:i:s');
        return self::insertGetId($data);
    }
}
<?php
/**
 * Created by PhpStorm.
 * User: lizhenheng
 * Date: 2017-12-26
 * Time: 下午 03:15
 */
 namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SuperAttachment extends Model {
    //模型
    //protected $connection='retail_hq';

    protected $table = 'super_attachment';
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
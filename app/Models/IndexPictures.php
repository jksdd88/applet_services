<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Utils\CacheKey;
use Illuminate\Support\Facades\Cache;

class IndexPictures extends Model
{
    /**
     * 与模型关联的数据表。
     *
     * @var string
     */
    protected $table = 'index_pictures';
    const CREATED_AT = 'created_time';
    const UPDATED_AT = 'updated_time';


    /**
     * 插入数据
     * @author zhangyu1@dodoca.com
     */
    static function insert_data($data)
    {
        $data['created_time'] = date('Y-m-d H:i:s');
        $data['updated_time'] = date('Y-m-d H:i:s');
        return self::insertGetId($data);
    }

    /**
     * 获取单条数据
     * @author zhangyu1@dodoca.com
     */
    static function get_data_by_id($id, $fields = '*')
    {
        if(!$id || !is_numeric($id))return;
        $data = self::query()->select(\DB::raw($fields))->where(['id'=>$id])->first();
        return $data;
    }


    /**
     * demo 修改一条记录
     * @return int|修改成功条数
     */

    static function update_data($id , $data)
    {
        $data['updated_time'] = date('Y-m-d H:i:s');
        return self::query()->where('id','=',$id)->update($data);
    }

}

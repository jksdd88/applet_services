<?php

namespace App\Models;

use App\Utils\CacheKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class ArticleGroup extends Model
{
    protected $table = 'article_group';
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
     * demo 修改一条记录
     * @return int|修改成功条数
     */
    static function update_data($id, $merchant_id, $data)
    {
        if (!$id || !is_numeric($id)) return;
        if (!$merchant_id || !is_numeric($merchant_id)) return;
        $data['updated_time'] = date('Y-m-d H:i:s');
        return self::query()->where('id', '=', $id)->where('merchant_id', '=', $merchant_id)->update($data);
    }
}

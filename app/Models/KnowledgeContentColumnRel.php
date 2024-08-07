<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KnowledgeContentColumnRel extends Model
{
    protected $table = 'knowledge_content_column_rel';
    protected $guarded = ['id'];
    const CREATED_AT = 'created_time';
    const UPDATED_AT = 'updated_time';

    /**
     * 新增
     * @param $data
     * @return int
     * @author tangkang@dodoca.com
     */
    static function insert_data($data)
    {
        $data['is_delete'] = 1;
        return self::insertGetId($data);
    }

    /**
     * 查询单条记录
     * @param $content_id
     * @param $column_id
     * @param $merchant_id
     * @return mixed
     * @author: tangkang@dodoca.com
     */
    static function get_one_by_content_column_id($content_id, $column_id, $merchant_id)
    {
        return self::query()->whereColumnId($column_id)->whereContentId($content_id)->whereMerchantId($merchant_id)->whereIsDelete(1)->first(['id']);
    }

    /**
     * 根据内容id获取所属栏目id列表内容
     * @param $content_id
     * @param $merchant_id
     * @return mixed
     * @author: tangkang@dodoca.com
     */
    static function get_lists_by_content_id($content_id, $merchant_id)
    {
        $column_ids = self::query()->whereMerchantId($merchant_id)->whereContentId($content_id)->whereIsDelete(1)->lists('column_id');
        $data['count'] = count($column_ids);
        //专栏信息
        foreach ($column_ids as $column_id) {
            $column_data = KnowledgeColumn::get_data_by_id($column_id, $merchant_id,['id','name','img','period_number'])->toArray();
            unset($column_data['details']);
            $data['lists'][] = $column_data;
        }
        return $data;
    }

    /**
     * 根据内容/栏目id删除多条
     * @param $id
     * @param $merchant_id
     * @return int
     * @author tangkang@dodoca.com
     */
    static function delete_data($id, $field, $merchant_id)
    {
        if (!$id || !is_numeric($id)) return 0;
        if (!in_array($field, ['content_id', 'column_id'])) return 0;
        if (!$merchant_id || !is_numeric($merchant_id)) return 0;
        $data = [
            'is_delete' => -1,
        ];
        return self::query()->where($field, $id)->whereMerchantId($merchant_id)->update($data);

    }

    /**
     * 移除单条关联关系
     * @param $id
     * @param $field
     * @param $merchant_id
     * @return int
     * @author: tangkang@dodoca.com
     */
    static function destroy_data($column_id, $content_id, $merchant_id)
    {
        if (!$column_id || !is_numeric($column_id)) return 0;
        if (!$content_id || !is_numeric($content_id)) return 0;
        if (!$merchant_id || !is_numeric($merchant_id)) return 0;
        $data = [
            'is_delete' => -1,
        ];
        return self::query()->whereColumnId($column_id)->whereContentId($content_id)->whereMerchantId($merchant_id)->update($data);

    }
}

<?php

namespace App\Http\Requests\Weapp;

use App\Http\Requests\Request;
use App\Models\KnowledgeColumn;
use App\Models\KnowledgeContent;
use App\Models\OrderKnowledge;

class OrderKnowledgeRequest extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'k_type' => 'required | in:' . OrderKnowledge::K_TYPE_CONTENT . ',' . OrderKnowledge::K_TYPE_COLUMN,
            'object_id' => 'required | integer',
            'price' => 'required',
        ];
    }

    public function messages()
    {
        return [
            'k_type.required' => '知识类型不能为空',
            'k_type.in' => '知识类型参数错误',
            'object_id.required' => '请选择要购买的专栏/内容',
            'object_id.integer' => '参数非法：专栏/内容参数错误',
            'price.required' => '要购买的专栏/内容金额参数错误',
        ];
    }

    /**
     * 参数效验，返回数据
     * @param $param
     * @return array
     * @author: tangkang@dodoca.com
     */
    public function getData($param, $merchant_id)
    {
        if ($param['k_type'] == OrderKnowledge::K_TYPE_COLUMN) {
            $k_res = KnowledgeColumn::get_data_by_id($param['object_id'], $merchant_id);
            $k_res['type'] = 0;
        } elseif ($param['k_type'] == OrderKnowledge::K_TYPE_CONTENT) {
            $k_res = KnowledgeContent::get_data_by_id($param['object_id'], $merchant_id);
        } else {
            return ['errcode' => 1, 'errmsg' => '参数错误'];
        }
        if (empty($k_res) || empty($k_res['id'])) {
            return ['errcode' => 1, 'errmsg' => '获取' . OrderKnowledge::$type_msg[$param['k_type']] . '信息失败'];
        } else {
            if ($k_res['is_delete'] !== 1 || $k_res['status'] !== KnowledgeColumn::STATUS_ONSHELVE) {
                return ['errcode' => 1, 'errmsg' => OrderKnowledge::$type_msg[$param['k_type']] . '已下架'];
            }
            if ($k_res['price'] !== $param['price'] || $param['price'] < 0) {
                return ['errcode' => 1, 'errmsg' => OrderKnowledge::$type_msg[$param['k_type']] . '价格错误，请刷新页面重新购买。'];
            }
        }
        $img_arr = json_decode($k_res['img'], true);
        $img = !empty($img_arr[0]) ? $img_arr[0] : '-';
        $data = [
            'price' => $k_res['price'],
            'k_type' => $param['k_type'],
            'type' => $k_res['type'],
            'knowledge_id' => $k_res['id'],
            'name' => $k_res['name'],
            'img' => $img['url'],
        ];
        return ['errcode' => 0, 'errmsg' => '', 'data' => $data];
    }
}

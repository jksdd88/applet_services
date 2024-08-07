<?php
/**
 * Created by PhpStorm.
 * User: tang
 * Date: 2018-04-09
 * Time: 11:25
 */

namespace App\Http\Requests\Admin;

use App\Http\Requests\Request;
use App\Models\KnowledgeColumn;

class KnowledgeColumnRequest extends Request
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
            'name' => 'required | max:50',
            'summary' => 'max:100',
            'img' => 'required',
            'details' => 'required',
            'price' => 'required',
            'status' => 'required | in:1,2',
            'base_csale' => 'integer|min:0',
        ];
    }

    public function messages()
    {
        return [
            'name.required' => '请输入专栏名称',
            'name.max' => '专栏名称最多50个字符',
            'summary.max' => '专栏简介最多100个字符',
            'img.required' => '请上传专栏封面',
            'details.required' => '请输入专栏详情',
            'price.required' => '请输入售卖价格',
            'status.required' => '请选择知识专栏上下架状态',
            'status.in' => '知识专栏上下架状态错误',
            'base_csale.integer' => '基础销量必须为整数',
            'base_csale.min' => '基础销量不能小于0',
        ];
    }

    public function validate_param($param)
    {
        $img_arr = $param['img'];
        if (count($img_arr) > 3) {
            return ['errcode' => 1, 'errmsg' => '最多可上传3张封面'];
        }
        if ($param['price'] < 0) {
            return ['errcode' => 1, 'errmsg' => '价格不能小于0'];
        }
        if (!preg_match('/^[0-9]+(.[0-9]{1,2})?$/', $param['price'])) {
            return ['errcode' => 1, 'errmsg' => '价格格式错误，最多两位小数'];
        }
        $param['price'] = number_format($param['price'], 2, '.', '');
        $param['img'] = json_encode($param['img']);
        return ['errcode' => 0, 'errmsg' => '', 'data' => $param];

    }
}

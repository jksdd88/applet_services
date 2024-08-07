<?php

namespace App\Http\Requests\Weapp;

use App\Http\Requests\Request;

class ApptTimeListRequest extends Request
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
            'goods_id' => 'required | integer|min:1',
            'store_id' => 'required | integer|min:1',
            'date' => 'required',
        ];
    }

    public function messages()
    {
        return [
            'goods_id.required' => '缺失参数：商品参数ID',
            'goods_id.integer' => '参数非法：商品ID必须为数字',
            'goods_id.min' => '参数非法：商品ID必须大于0的整数',
            'store_id.required' => '缺失参数：门店ID',
            'store_id.integer' => '参数非法：门店ID必须为数字',
            'store_id.min' => '参数非法：门店ID必须大于0的整数',
            'date.required' => '缺失参数：请选择预约日期',
        ];
    }
}

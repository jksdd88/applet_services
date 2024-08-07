<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Request;

class BoardRequest extends Request
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
            'store_id'=>'required|integer|min:1',
            'type'=>'required|in:1,2',
        ];
    }
    public function messages()
    {
        return [
            'store_id.required' => '缺失参数：门店ID',
            'store_id.integer' => '门店ID参数非法：门店ID必须为数字',
            'store_id.min' => '门店ID参数非法：门店ID必须大于0的整数',
            'type.required' => '请选择展示维度（服务或技师）',
            'type.in' => '展示维度必须是1或者2（1：服务，2：技师）',
        ];
    }
}

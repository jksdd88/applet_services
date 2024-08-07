<?php

namespace App\Http\Requests;

use App\Http\Requests\Request;

class ApptRequest extends Request
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
            'goods_spec_id' => 'required | integer|min:1',
//            'customer' => 'required',
//            'customer_mobile' => 'required',
            'store_id' => 'required',
//            'appt_staff_id' => 'required',
            'quality' => 'required|integer|min:1',
            'date' => 'required',
        ];
    }

    public function messages()
    {
        return [
            'goods_id.required' => '缺失参数：商品参数ID',
            'goods_id.integer' => '商品ID参数非法：商品ID必须为数字',
            'goods_id.min' => '商品ID参数非法：商品ID必须大于0的整数',
            'goods_spec_id.required' => '缺失参数：商品参数规格ID',
            'goods_spec_id.integer' => '商品ID参数非法：规格ID必须为数字',
            'goods_spec_id.min' => '商品ID参数非法：规格ID必须大于0的整数',
//            'customer.required' => '请填写预约人姓名',
//            'customer_mobile.required' => '请填写预约人手机号',
            'store_id.required' => '请选择预约门店',
//            'appt_staff_id.required' => '请选择预约技师',
            'quality.required' => '请填写商品数量',
            'quality.integer' => '商品数量必须为数字',
            'date.required' => '请选择预约日期',
        ];
    }
}

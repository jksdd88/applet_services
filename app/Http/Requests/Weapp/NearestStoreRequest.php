<?php

namespace App\Http\Requests\Weapp;

use App\Http\Requests\Request;

class NearestStoreRequest extends Request
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
//            'lng' => 'required',
//            'lat' => 'required',
        ];
    }

    public function messages()
    {
        return [
//            'lng.required' => '缺失参数：经度',
//            'lat.required' => '缺失参数：纬度',
            'goods_id.required' => '缺失参数：商品参数ID',
            'goods_id.integer' => '商品ID参数非法：商品ID必须为数字',
            'goods_id.min' => '商品ID参数非法：商品ID必须大于0的整数',
        ];
    }
}

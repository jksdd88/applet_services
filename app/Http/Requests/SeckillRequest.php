<?php

namespace App\Http\Requests;

use App\Http\Requests\Request;

class SeckillRequest extends Request
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
               'goods_id'      => 'required',
               'goods_title'   => 'required',
               'goods_img'     => 'required',
               'price'         => ['required', 'regex:/^\d*(\.\d{1,2})?$/'],
               'created_at'    => 'required',
            ];
    }

    public function messages ()
    {
        return [
            'goods_id.required'    => json_encode(['message'=>'请选择商品', 'type' => 'Params', 'code'=>1000001]),
            'goods_title.required' => json_encode(['message'=>'缺少商品标题', 'type' => 'Params', 'code'=>1000002]),
            'goods_img.required'   => json_encode(['message'=>'缺少商品图片', 'type' => 'Params', 'code'=>1000003]),
            'price.required'       => json_encode(['message'=>'请输入秒杀价', 'type' => 'Params', 'code'=>1000004]),
            'created_at.required'  => json_encode(['message'=>'请输入开始时间', 'type' => 'Params', 'code'=>100005]),
        ];
    }
}

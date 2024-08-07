<?php

namespace App\Http\Requests;

use App\Http\Requests\Request;

class CartRequest extends Request
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
        $reules_data=[];
        $method=strtolower($this->method());
        if($method == 'post'){//添加到购物车
            $reules_data=[
                'goods_id'=>'required|integer|min:1',
                'goods_spec_id'=>'integer',
                'quantity'=>'integer|min:1',
            ];
        }elseif($method == 'put'){
            $reules_data=[
//                'id'=>'required|integer|min:1',
//                'goods_id'=>'required|integer|min:1',
                'quantity'=>'integer|min:1',
                'status'=>'integer',
            ];
        }elseif($method == 'delete'){
            $reules_data=[
                'ids'=>'required',
            ];
        }

        return $reules_data;
    }
    public function messages()
    {
        return [
            'ids.required' => '请选择要删除的商品',
//            'id.required' => '购物车参数获取失败',
//            'id.integer' => '购物车参数非法',
//            'id.min' => '购物车参数非法',
            'goods_id.required' => '请添加要加入购物车的商品',
            'goods_id.integer' => '商品参数非法',
            'goods_spec_id.integer' => '商品规格参数非法',
            'quantity.integer' => '商品数量非法',
            'quantity.min' => '商品数量非法',
            'status.integer' => '参数非法',
        ];
    }
}

<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Request;

class GoodsRequest extends Request
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
        if($this->get('is_sku',null) == 2){
            return [
                'goods_cat_id' => 'required | integer|min:0',
                'is_sku' => 'required | in:0,1,2',
                'leadtime_hr' => 'required | integer|min:0',
                'leadtime_day' => 'required | integer|min:0',
                'mode' => 'required|in:1,2',
            ];
        }else{
            return [
                'is_sku' => 'required | in:0,1,2',
            ];
        }
    }

    public function messages()
    {
        return [
            'goods_cat_id.required' => '缺失商品分类参数',
            'goods_cat_id.integer' => '商品分类参数必须为整数',
            'goods_cat_id.min' => '商品分类参数不能小于0',
            'is_sku.required' => '最少需提前限制不能为空',
            'is_sku.in' => '是否多规格参数必须是0、1或者2【0：非多规格，1：普通商品多规格，2：预约商品】',
            'leadtime_hr.required' => '预约时间限制：最少需提前的时间不能为空',
            'leadtime_hr.integer' => '预约时间限制：最少需提前的时间必须为整数',
            'leadtime_hr.min' => '预约时间限制：最少需提前的时间不能小于0',
            'leadtime_day.required' => '预约时间限制：最多可提前的时间不能为空',
            'leadtime_day.integer' => '预约时间限制：最多可提前的时间必须为整数',
            'leadtime_day.min' => '预约时间限制：最多可提前的时间不能小于0',
            'mode.required' => '请选择预约模式',
            'mode.in' => '预约模式参数错误【必须是1（按时段预约）或者2（按人员预约）】',
        ];
    }
}

<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Request;
use Illuminate\Contracts\Validation\ValidationException;

class FormRequest extends Request
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
        $rules = [
            'name' => 'required',
//            'cate_id' => 'required|integer|min:1',
            'period_type' => 'required | in:1,2',
            'limit_type' => 'required|in:1,2,3,4',
            'submit_btn' => 'required',
            'components' => 'required',
        ];
        if ($this->get('period_type', 0) == 2) {
            $rules['start_time'] = 'required|dateformat:Y-m-d H:i:s';
            $rules['end_time'] = 'required|dateformat:Y-m-d H:i:s';
        }
        $limit_type = $this->get('limit_type', 0);
        if ($limit_type > 1) {
            if ($limit_type == 2) {//累计、每天都限制
                $rules['limit_maximum'] = 'required';
                $rules['limit_maximum_day'] = 'required | min:0';
            } elseif ($limit_type == 3) {
                $rules['limit_maximum'] = 'required | min:1';
            } elseif ($limit_type == 4) {
                $rules['limit_maximum_day'] = 'required | min:1';
            }
        }
        return $rules;
    }

    public function messages()
    {
        return [
            'name.required' => '表单名称不能为空',
            'period_type.required' => '请选择有效期类型',
            'limit_type.required' => '请选择限提交次数类型',
            'start_time.required' => '开始时间不能为空',
            'end_time.required' => '结束时间不能为空',
            'start_time.dateformat' => '开始时间格式错误',
            'end_time.dateformat' => '结束时间格式错误',
            'submit_btn.required' => '表单提交按钮不能为空',
            'submit_color.required' => '表单提交按钮颜色不能为空',
            'components.required' => '表单组件参数不能为空',
//            'cate_id.required' => '表单分类参数不能为空',
//            'cate_id.integer' => '表单分类参数必须为整数',
//            'cate_id.min' => '表单分类参数必须为大于0的整数',
            'period_type.in' => '有效期参数错误（1：永久有效，2：期限内）',
            'limit_type.in' => '限提交次数类型参数错误（1：每用户不限制，2：限制累计和每天，3：限制累计，4：限制每天）',
//            'limit_maximum.min' => '累计限制次数不能小于每天限制次数',
            'limit_maximum.required' => '累计限制次数不能为空',
            'limit_maximum_day.min' => '每天限制次数不能小于0',
            'limit_maximum_day.required' => '每天限制次数不能为空',
        ];
    }

    public function validateForm()
    {
        $param = $this->all();
        if ($this->get('period_type', 0) == 2 && $this->get('start_time', 0) >= $this->get('end_time', 0)) {
            return ['errcode' => 1, 'errmsg' => '开始时间不能大于、等于结束时间'];
        }
        if ($this->get('limit_type', 0) == 2 && $this->get('limit_maximum', 0) == 0 && $this->get('limit_maximum_day', 0) == 0) {
            return ['errcode' => 1, 'errmsg' => '每个用户累计提交次数与每天提交次数必须有一个大于0'];
        }
        if ($param['limit_type'] == 2) {
            if (!empty($param['limit_maximum']) && $param['limit_maximum_day'] > $param['limit_maximum']) return ['errcode' => 1, 'errmsg' => '累计限制次数不能小于每天限制次数'];
        }
        //反馈上限必须大于用户限制次数,0为不限制
        //2：限制累计和每天，3：限制累计，4：限制每天
        if ($param['feedback_maximum'] != 0 && in_array($param['limit_type'], [2, 3, 4])) {
            if ($param['feedback_maximum'] < $param['limit_maximum'] || $param['feedback_maximum'] < $param['limit_maximum_day']) {
                return ['errcode' => 1, 'errmsg' => '反馈上限不能小于每个用户的限制次数'];
            }
        }
        if (count($param['components']) > 100) return ['errcode' => 1, 'errmsg' => '表单不可超过100个组件'];

        return ['errcode' => 0, 'errmsg' => 'Ok'];
    }

}

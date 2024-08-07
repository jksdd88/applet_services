<?php

namespace App\Http\Requests;

use App\Http\Requests\Request;

class TestRequest extends Request
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
//            'test'=>'required | in:1,2'
        ];
    }
    public function messages(){
        return [
//            'test.required' => '必填',
//            'test.in' => '参数错误（1：xx；2：yy）',
        ];
    }
}

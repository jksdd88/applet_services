<?php

namespace App\Http\Requests;

use App\Http\Requests\Request;

class GoodsTestRequest extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        if ($this->method() == 'PUT') {
            return [
                'stock_num' => 'required|integer|min:1',
            ];
        }
        return [
            //
        ];
    }
}

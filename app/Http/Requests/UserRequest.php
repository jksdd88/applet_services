<?php

namespace App\Http\Requests;

use App\Http\Requests\Request;
use Illuminate\Support\Facades\Auth;
use Route;

class UserRequest extends Request
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
        switch($this->method())
        {
            case 'GET':
                break;
            case 'DELETE':
                return [];
                break;
            case 'POST':
                return [
                    'username'=>'required|alpha_dash|between:4,20|unique:user,username',
                    'password'=>'required|confirmed|min:6',
                    'password_confirmation'=>'required|min:6',
                    'mobile'=>'required|digits_between:10,20|unique:user,mobile,null,id,is_delete,1',
                    'email'=>'email'
                ];
                break;
            case 'PUT':
                if(Route::currentRouteName() == 'userPass')
                {
                    return [
                        'password'=>'required|min:6',
                        'old_password'=>'required|min:1'
                    ];
                }elseif(Route::currentRouteName() == 'forgetUserPass'){
                    return [
                        'check_code'=>'required',
                        'password'=>'required|confirmed|min:6',
                        'password_confirmation'=>'required|min:6',
                    ];
                }elseif(Route::currentRouteName() == 'verifiyUserPhone'){
                    return [
                        'check_code'=>'required',
                        'mobile'=>'required|digits_between:10,20',
                    ];
                }else{
                    return [
                        //'weixin'=>'required|unique:user,weixin,'.$this->id,
                        //'username'=>'required|alpha_dash|between:4,20|unique:user,'.$this->id,
                        'password'=>'min:6',
                        'mobile'=>'required|digits_between:10,20|unique:user,mobile,'.Auth::user()->merchant_id.',merchant_id',
                        'email'=>'email'
                    ];
                }
                break;
            case 'PATCH':
                return [];
                break;
            default:
                break;
        }

    }

    public function messages()
    {
        switch($this->method())
        {
            case 'POST':
                return [
                    'username.unique'=>'用户名已被占用',
                    'username.between'=>'用户名必须在4-20位字符之间',
                    'password.regex'=>'密码至少6位',
                    'password.confirmed'=>'密码与确认密码不一致',
                    'mobile.unique'=>'电话号码已被占用',
                ];
                break;
            case 'PUT':
                if(Route::currentRouteName() == 'userPass')
                {
                    return [
                        'password.regex'=>'密码至少6位',
                    ];
                }elseif(Route::currentRouteName() == 'forgetUserPass'){
                    return [
                        'password.regex'=>'密码至少6位',
                        'password_confirmation'=>'请再次输入你的密码',
                        'check_code.required' =>'请输入手机验证码',
                    ];
                }elseif(Route::currentRouteName() == 'verifiyUserPhone'){
                    return [
                        'mobile.digits_between'=>'手机号必须在10-20位字符之间',
                        'check_code.required' =>'请输入手机验证码',
                    ];
                }else{
                    return [
                        'password.regex'=>'密码至少6位',
                        'mobile.unique'=>'手机号已被占用',
                        'mobile.digits_between'=>'手机号必须在10-20位字符之间',
                        'email.email'=>'Email格式不正确'
                    ];
                }
                break;
            default:
                return '';
                break;
        }
    }
}

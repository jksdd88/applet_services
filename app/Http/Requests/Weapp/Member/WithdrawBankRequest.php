<?php

namespace App\Http\Requests\Weapp\Member;

use App\Facades\Member;
use App\Http\Requests\Request;
use App\Models\DistribPartner;
use App\Models\MemberBalanceDetail;

class WithdrawBankRequest extends Request
{
    private $member_id;
    private $merchant_id;
    private $amount;

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
        $this->member_id = Member::id();
        $this->merchant_id = Member::merchant_id();
        $this->weapp_id = Member::weapp_id();
        $DistribPartner_res = \App\Models\Member::get_data_by_id($this->member_id, $this->merchant_id);
        $this->amount = empty($DistribPartner_res['balance']) ? 0 : $DistribPartner_res['balance'];
        $rules = [
            'name' => 'required',
            'mobile' => 'required | regex:/^1[345678][0-9]{9}$/',
            'account_number' => 'required',
            'branch_bank_name' => 'required',
        ];
        return array_merge($rules, Withdraw::rules($this->amount));

    }

    public function messages()
    {
        $msg = [
            'name.required' => '清输入您的姓名',
            'mobile.required' => '清输入您的手机号',
            'mobile.regex' => '清输入正确的手机号码',
            'account_number.required' => '清输入您的银行帐号',
            'branch_bank_name.required' => '清输入您银行卡的开户行（具体到支行）',
        ];
        return array_merge($msg,Withdraw::messages($this->amount));
    }
}

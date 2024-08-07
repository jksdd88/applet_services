<?php

namespace App\Http\Requests\Weapp\Member;

use App\Facades\Member;
use App\Http\Requests\Request;
use App\Models\DistribPartner;
use App\Models\MemberBalanceDetail;
use App\Services\MemberService;

class WithdrawWxRequest extends Request
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
        if(app()->isLocal()){
            $this->member_id =2;
            $this->merchant_id = 2;
            $this->weapp_id = 5;
        }
        $DistribPartner_res = \App\Models\Member::get_data_by_id($this->member_id, $this->merchant_id);
        $this->amount = empty($DistribPartner_res['balance']) ? 0 : $DistribPartner_res['balance'];
        return  Withdraw::rules($this->amount);
    }

    public function messages()
    {
        $msg = Withdraw::messages($this->amount);
        if ($this->get('amount', 0) > 20000) $msg['amount.max'] = '提现金额需小于单笔日限额 2 万元';
        return $msg;
    }

    /**
     * 单个用户每日提现总额 100万
     * @author: tangkang@dodoca.com
     */
    public function todayAmount()
    {
//如果当日累计申请提现金额超出单个用户每日提现总额 100万，则提示：每日提现总额不可超过 100 万元，今日已提交申请 x 万元，还可提交 y 万元。
//（x 为该用户当日提交申请提现总金额，y 为当日剩余可申请金额即 100 万-当日累计申请提现金额）
        $amount = MemberBalanceDetail::where('merchant_id', $this->merchant_id)
            ->where('member_id', $this->member_id)
            ->where('type', MemberService::BALANCE_WEIXIN)
            ->whereDate('created_time', '=', date('Y-m-d'))
            ->where('is_delete', 1)
            ->sum('amount');
        return $amount;
    }
}

<?php
/**
 * Created by PhpStorm.
 * User: tang
 * Date: 2018-03-09
 * Time: 13:58
 */

namespace App\Http\Requests\Weapp\Member;

class Withdraw
{
    /**
     *
     * @param $amount
     * @return array
     * @author: tangkang@dodoca.com
     */
    public static function rules($amount)
    {
        return [
            'amount' => 'required | numeric | min:0 | max:' . $amount,
        ];
    }

    /**
     * 提现msg
     * @param $amount
     * @return array
     * @author: tangkang@dodoca.com
     */
    public static function messages($amount)
    {

        $msg = [
            'amount.required' => '请输入提现金额',
            'amount.min' => '提现金额不可小于 0 元',
            'amount.max' => '最大可提现金额为 ' . $amount . ' 元',
        ];
//        if ($param_amount < 1) $msg['amount.min'] = '提现金额不可小于 1 元';
        return $msg;
    }
}

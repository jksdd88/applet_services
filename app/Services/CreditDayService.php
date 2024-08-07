<?php

namespace App\Services;

use App\Models\MerchantDayCredit;
use App\Models\MerchantMonthCreditType;
use Carbon\Carbon;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;


class CreditDayService {

    use InteractsWithQueue,
        SerializesModels;

    protected $type = '';
    protected $shop_id = '';
    protected $member_id = '';
    protected $credit = 0;
    protected $is_refund = 0;

    public function __construct($type, $member_id, $credit, $is_refund = 0) {
        //parent::__construct();
        $this->type = $type;
        $this->member_id = $member_id;
        $this->credit = $credit;
        $this->is_refund = $is_refund;
        $this->shop_id = 1;
    }

    //写入统计库信息
    public static function putCredirStatDay() {
        $merchant_id = $this->member_id;
        if ($this->credit > 0 && $this->is_refund == 0) {
            $monthStart = date("Y-m-d 00:00:00", mktime(0, 0, 0, date("m"), 1, date("Y"))); //本月开始时间
            //给商家发放积分渠道加月统计
            $monthCreditTypeInfo = MerchantMonthCreditType::where('merchant_id', $merchant_id)->where('type', $this->type)->where('created_time', $monthStart)->first();
            if ($monthCreditTypeInfo) {
                $monthCreditTypeInfo->credit = $monthCreditTypeInfo['credit'] + $this->credit;
                $monthCreditTypeInfo->save();
            } else {
                $monthCreditTypeData = [
                    'type' => $this->type,
                    'merchant_id' => $merchant_id,
                    'shop_id' => $this->shop_id,
                    'credit' => $this->credit,
                    'expend_credit' => 0,
                    'created_time' => $monthStart
                ];
                MerchantMonthCreditType::create($monthCreditTypeData);
            }
            //商家单日发放积分统计
            $merchantDayCreditInfo = MerchantDayCredit::where('merchant_id', $merchant_id)->where('created_time', Carbon::today())->first();
            if ($merchantDayCreditInfo) {
                $merchantDayCreditInfo->credit = $merchantDayCreditInfo['credit'] + $this->credit;
                $merchantDayCreditInfo->save();
            } else {
                $MerchantDayCreditData = [
                    'merchant_id' => $merchant_id,
                    'shop_id' => $this->shop_id,
                    'credit' => $this->credit,
                    'expend_credit' => 0,
                    'created_time' => Carbon::today()
                ];
                MerchantDayCredit::create($MerchantDayCreditData);
            }
        }
        //用户积分消耗商家统计
        if ($this->credit < 0 || $this->is_refund == 1) {
            $monthStart = date("Y-m-d 00:00:00", mktime(0, 0, 0, date("m"), 1, date("Y"))); //本月开始时间
            $monthCreditTypeInfo = MerchantMonthCreditType::where('merchant_id', $merchant_id)->where('type', $this->type)->where('created_time', $monthStart)->first();
            if ($monthCreditTypeInfo) {
                if ($this->is_refund == 1) {
                    $monthCreditTypeInfo->expend_credit = $monthCreditTypeInfo['expend_credit'] - abs($this->credit);
                } else {
                    $monthCreditTypeInfo->expend_credit = $monthCreditTypeInfo['expend_credit'] + abs($this->credit);
                }
                $monthCreditTypeInfo->save();
            } else {
                $monthCreditTypeData = [
                    'type' => $this->type,
                    'merchant_id' => $merchant_id,
                    'shop_id' => $this->shop_id,
                    'credit' => 0,
                    'expend_credit' => $this->is_refund == 1 ? -abs($this->credit) : abs($this->credit),
                    'created_time' => $monthStart
                ];
                MerchantMonthCreditType::create($monthCreditTypeData);
            }
            //用户单日消耗积分商家统计
            $merchantDayCreditInfo = MerchantDayCredit::where('merchant_id', $merchant_id)->where('created_time', Carbon::today())->first();
            if ($merchantDayCreditInfo) {
                if ($this->is_refund == 1) {
                    $merchantDayCreditInfo->expend_credit = $merchantDayCreditInfo['expend_credit'] - abs($this->credit);
                } else {
                    $merchantDayCreditInfo->expend_credit = $merchantDayCreditInfo['expend_credit'] + abs($this->credit);
                }
                $merchantDayCreditInfo->save();
            } else {
                $MerchantDayCreditData = [
                    'merchant_id' => $merchant_id,
                    'shop_id' => $this->shop_id,
                    'credit' => 0,
                    'expend_credit' => $this->is_refund == 1 ? -abs($this->credit) : abs($this->credit),
                    'created_time' => Carbon::today()
                ];
                MerchantDayCredit::create($MerchantDayCreditData);
            }
        }
    }

}

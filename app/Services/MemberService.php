<?php

namespace App\Services;

/**
 * 会员服务类
 *
 * @package default
 * @author guoqikai
 **/
use App\Models\Member as MemberModel;
use App\Models\MemberBalanceDetail;
use App\Models\MemberInfo;
use App\Models\NewUserGift;
use App\Models\NewUserGiftRecord;
use App\Models\Coupon;
use App\Models\CouponCode;
use App\Services\CouponService;
use App\Utils\CacheKey;
use App\Utils\CommonApi;
use Carbon\Carbon;
use DB;
use Illuminate\Support\Facades\Redis;

class MemberService
{
    const BALANCE_WEIXIN = 1;
    const BALANCE_ALIPAY = 2;
    const BALANCE_BANK = 3;
    const BALANCE_COMMISSION = 4;

    /**
     * 处理粉丝关注公众号事件
     *
     * @param  商户ID $merchant_id
     * @param  公众号APPID $appid
     * @param  粉丝OPENID $openid
     * @param  关注状态 $status 0、取消关注 1、关注
     * @return void
     */
    public function subscribe($param)
    {
        $merchant_id = isset($param['merchant_id']) ? intval($param['merchant_id']) : 0;
        $appid = isset($param['appid']) ? trim($param['appid']) : '';
        $openid = isset($param['openid']) ? trim($param['openid']) : '';
        $status = $param['status'];

        if (!$merchant_id) {
            return ['errcode' => 99001, 'errmsg' => '商户ID不存在'];
        }

        if (!$appid) {
            return ['errcode' => 99001, 'errmsg' => 'APPID不存在'];
        }

        if (!$openid) {
            return ['errcode' => 99001, 'errmsg' => 'OPENID不存在'];
        }

        if (!isset($status) || !is_numeric($status)) {
            return ['errcode' => 99001, 'errmsg' => '关注状态未提供'];
        }

        $exist = MemberInfo::where('open_id', $openid)->where('merchant_id', $merchant_id)->first();

        if ($exist) {
            MemberInfo::where('id', $exist->id)->update(['is_subscribe' => $status, 'updated_time' => date('Y-m-d H:i:s')]);
            if ($exist->member_id) {
                //清缓存
                MemberInfo::clear_one($exist->member_id, $exist->appid, $exist->merchant_id);
            }
            return ['errcode' => 0, 'errmsg' => '设置成功', 'data' => ['member_info_id' => $exist->id]];
        } else {
            $data = [
                'merchant_id' => $merchant_id,
                'appid' => $appid,
                'open_id' => $openid,
                'source_type' => 3,
                'is_subscribe' => $status
            ];

            if ($member_info_id = MemberInfo::insert_data($data)) {
                return ['errcode' => 0, 'errmsg' => '设置成功', 'data' => ['member_info_id' => $member_info_id]];
            }
        }
    }

    /**
     * 新用户有礼
     *
     * @param  商户ID $merchant_id
     * @param  公众号APPID $appid
     * @param  粉丝OPENID $openid
     * @param  关注状态 $status 0、取消关注 1、关注
     * @return void
     */
    public function newUserGift($param)
    {
        $CouponService = new CouponService;

        $member_id = isset($param['member_id']) ? intval($param['member_id']) : 0;
        $merchant_id = isset($param['merchant_id']) ? intval($param['merchant_id']) : 0;

        if (!$merchant_id) {
            return ['errcode' => 99001, 'errmsg' => '商户ID不存在'];
        }

        if (!$member_id) {
            return ['errcode' => 99001, 'errmsg' => '会员ID不存在'];
        }

        $record_id = NewUserGiftRecord::where('member_id', $member_id)->value('id');

        if (!$record_id) {
            $info = NewUserGift::where('merchant_id', $merchant_id)
                ->where('begin_time', '<=', Carbon::now())
                ->where('end_time', '>=', Carbon::now())
                ->where('status', '!=', 2)
                ->where('is_delete', 1)
                ->first();

            if ($info) {
                $coupon_code_ids = [];
                $coupon_ids = json_decode($info->coupon_ids, true);
                if ($coupon_ids) {
                    foreach ($coupon_ids as $coupon_id) {
                        $coupon = Coupon::get_data_by_id($coupon_id, $merchant_id);
                        if ($coupon && $coupon->is_delete == 1 && $coupon->coupon_sum > $coupon->send_num) {
                            $data = [
                                'member_id'   => $member_id,
                                'merchant_id' => $merchant_id,
                                'coupon_id'   => $coupon_id,
                                'channel'     => 3
                            ];
                            $response = $CouponService->giveMember($data);
                            if ($response['errcode'] == 0) {
                                $coupon_code_id = $response['data']['coupon_code_id'];
                                $coupon_code_ids[] = $coupon_code_id;
                            }
                        }
                    }
                }
                if (count($coupon_code_ids) > 0) {
                    $record_id = NewUserGiftRecord::insertGetId([
                        'new_user_gift_id' => $info->id,
                        'member_id'        => $member_id,
                        'coupon_code_ids'  => json_encode($coupon_code_ids),
                        'created_time'     => Carbon::now(),
                        'updated_time'     => Carbon::now()
                    ]);

                    $response = [
                        'new_user_gift_id' => $info->id,
                        'record_id'        => $record_id,
                        'coupon_code_ids'  => $coupon_code_ids
                    ];
                    return ['errcode' => 0, 'data' => $response];
                } else {
                    return ['errcode' => -1, 'errmsg' => '发放失败'];
                }
            } else {
                return ['errcode' => -1, 'errmsg' => '无有效活动'];
            }

        } else {
            return ['errcode' => -1, 'errmsg' => '已发放'];
        }
    }

    /**
     * 赠送给用户的劵码列表
     *
     * @param string $merchant_id 商户ID
     * @param string $member_id 会员ID
     *
     * @return \Illuminate\Http\Response
     */
    public function getNewUserGiftRecord($param)
    {
        $merchant_id = isset($param['merchant_id']) ? intval($param['merchant_id']) : 0;
        $new_user_gift_id = isset($param['new_user_gift_id']) ? intval($param['new_user_gift_id']) : 0;
        $coupon_code_ids = isset($param['coupon_code_ids']) ? $param['coupon_code_ids'] : '';

        if ($coupon_code_ids) {

            $data = [];
            $new_user_gift = NewUserGift::get_data_by_id($new_user_gift_id, $merchant_id);
            $data['banner'] = $new_user_gift['banner'];

            $coupon_data = [];
            $condition_val = [];

            if ($coupon_code_ids) {
                foreach ($coupon_code_ids as $coupon_code_id) {
                    $coupon_code = CouponCode::select('coupon_id', 'start_time', 'end_time')->where('id', $coupon_code_id)->first();
                    $coupon_id = $coupon_code->coupon_id;

                    $coupon = Coupon::get_data_by_id($coupon_id, $merchant_id);

                    $coupon_data[] = [
                        'start_time' => date('Y-m-d', strtotime($coupon_code->start_time)),
                        'end_time' => date('Y-m-d', strtotime($coupon_code->end_time)),
                        'card_color' => $coupon['card_color'],
                        'content_type' => $coupon['content_type'],
                        'coupon_val' => $coupon['coupon_val'],
                        'is_condition' => $coupon['is_condition'],
                        'condition_val' => $coupon['condition_val'],
                        'rang_goods' => $coupon['rang_goods'],
                    ];

                    $condition_val[] = $coupon['condition_val'];
                }
            }

            array_multisort($condition_val, SORT_ASC, $coupon_data);

            $data['coupon_data'] = $coupon_data;

            return ['errcode' => 0, 'data' => $data];
        } else {
            return ['errcode' => -1];
        }
    }

    /**
     * 提现
     * @param $param
     * @return array
     * @author: tangkang@dodoca.com
     * @throws \Exception
     */
    static public function withdraw($param, $merchant_id)
    {
        $key = CacheKey::get_withdraw_limits_by_member_id_key($param['member_id'], $merchant_id);
        if (!Redis::set($key, '1', 'EX', 1, 'NX')) {//限制每个会员每1秒访问一次
            return ['errcode' => 1, 'errmsg' => '提现过于频繁，请稍后重试。'];
        }
        $amount_arr = explode('.', $param['amount']);
        if (isset($amount_arr[1]) && mb_strlen($amount_arr[1]) > 2) {
            return ['errcode' => 1, 'errmsg' => '最多输入到小数点后两位'];
        }
        $member_res = MemberModel::get_data_by_id($param['member_id'], $merchant_id);
        $amount = abs($param['amount']);
        if ($amount < 1) return ['errcode' => 1, 'errmsg' => '提现金额不可小于 1 元'];
        $final_amount = $member_res['balance'] - $amount;
        if ($final_amount < 0) return ['errcode' => 1, 'errmsg' => '余额不足，可提现金额：' . $member_res['balance']] . '元';
        $orderNO_res = CommonApi::getOrderNO($param['type']);
        if ($orderNO_res['errcode'] != 0) return $orderNO_res;
        //微信零钱
        $data = [
            'merchant_id' => $merchant_id,
            'member_id' => $param['member_id'],
            'wxinfo_id' => $param['wxinfo_id'],
            'appid' => $param['appid'],
            'amount' => $amount,
            'pre_amount' => $member_res['balance'],
            'final_amount' => $final_amount,
            'type' => $param['type'],
            'order_no' => $orderNO_res['data'],
            'status' => TAKECASH_AWAIT,
        ];
        if (MemberBalanceDetail::where('order_no', $data['order_no'])->first()) {
            $orderNO_res = CommonApi::getOrderNO($param['type']);
            if ($orderNO_res['errcode'] != 0) return $orderNO_res;
            $data['order_no'] = $orderNO_res['data'];
        }
        if ($param['type'] == MemberService::BALANCE_WEIXIN) {
            $type_msg = '微信零钱';
        } elseif ($param['type'] == MemberService::BALANCE_ALIPAY) {
            $type_msg = '支付宝';
            $data['name'] = empty($param['name']) ? '-' : $param['name'];
            $data['mobile'] = empty($param['mobile']) ? '-' : $param['mobile'];
            $data['alipay'] = empty($param['alipay']) ? '-' : $param['alipay'];
        } elseif ($param['type'] == MemberService::BALANCE_BANK) {
            $type_msg = '银行卡';
            $data['name'] = empty($param['name']) ? '-' : $param['name'];
            $data['mobile'] = empty($param['mobile']) ? '-' : $param['mobile'];
            $data['account_number'] = empty($param['account_number']) ? '-' : $param['account_number'];
            $data['branch_bank_name'] = empty($param['branch_bank_name']) ? '-' : $param['branch_bank_name'];
        } else {
            $type_msg = '';
            //记录异常
            $except = [
                'activity_id' => $param['type'],
                'data_type' => 'member_withdraw',
                'content' => '获取提现类型失败。请求参数：' . json_encode($param, JSON_UNESCAPED_UNICODE) . '，$data：' . json_encode($data, JSON_UNESCAPED_UNICODE),
            ];
            CommonApi::errlog($except);
            return ['errcode' => 1, 'errmsg' => '获取提现类型失败'];
        }
        DB::beginTransaction();
        try {
            //扣余额
            $member_balance_res = MemberModel::decrement_data($param['member_id'], $merchant_id, 'balance', $param['amount']);
            if (empty($member_balance_res)) throw new \Exception('member表余额更新失败');

            $res = MemberBalanceDetail::insert_data($data);
            if (empty($res)) throw new \Exception('MemberBalanceDetail表插入失败');
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            //记录异常
            $except = [
                'activity_id' => $param['type'],
                'data_type' => 'member_withdraw',
                'content' => '会员申请提现异常。msg:' .
                    $e->getMessage() .
                    '，请求参数：' . json_encode($param, JSON_UNESCAPED_UNICODE) .
                    '，$data：' . json_encode($data, JSON_UNESCAPED_UNICODE),
            ];
            $errlog_id = CommonApi::errlog($except);
            if (empty($errlog_id)) $errlog_id = 0;
            return ['errcode' => 1, 'errmsg' => '提现申请提交失败'];
        }
        $WeixinMsgService = new WeixinMsgService();

        $msg_data = [
            'merchant_id' => $merchant_id,
            'name' => $member_res['name'],
            'time' => date('Y-m-d H:i:s'),
            'price' => $amount,
            'type' => $type_msg,
        ];
        $WeixinMsgService->withdrawals($msg_data);
        return ['errcode' => 0, 'errmsg' => '提现申请提交成功'];
    }
}

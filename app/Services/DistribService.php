<?php
/**
 * 分销
 * @author 王禹
 * @package  App\Services;
 */
namespace App\Services;

use App\Models\DistribLog;
use App\Models\DistribPartner;
use App\Models\DistribSetting;
use App\Models\DistribOrder;
use App\Models\DistribOrderGoods;
use App\Models\DistribOrderDetail;
use App\Models\DistribSettledLog;
use App\Models\OrderInfo;
use App\Models\OrderGoods;
use App\Models\OrderRefund;
use App\Models\DistribCheckLog;
use App\Models\DistribBuyerRelation;
use App\Models\DistribMemberFirstRecord;
use App\Models\DistribGoodsSetting;
use App\Models\Member;
use App\Models\MemberBalanceDetail;
use Illuminate\Support\Facades\DB;
use App\Utils\CommonApi;
use App\Services\WeixinMsgService;

class DistribService
{
    /**
     * 初始化推客订单
     * @author 王禹
     * @param $order_id 订单id
     * @param $merchant_id
     */
    static function initDistribOrder($order_id, $merchant_id)
    {
        try
        {
            $distrib_setting = DistribSetting::get_data_by_merchant_id($merchant_id);   //获取分销设置
            $order_status_distrib = DISTRIB_NOT;  //不参与分佣

            //LOG
            self::distribLog(array( 'merchant_id' => $merchant_id,
                'order_id' => $order_id,
                'data_type' => 'initDistribOrder(distrib_setting)',
                'data' => json_encode($distrib_setting))
            );

            //同意分销协议并商家在正常开启状态下生成分销订单
            if(!empty($distrib_setting['status']) && $distrib_setting['status'] === 1 && in_array($distrib_setting['distrib_level'],array(1,2,3)))
            {
                //获取订单所属推客
                $order_data = OrderInfo::get_data_by_id($order_id, $merchant_id);

                //LOG
                self::distribLog(array( 'merchant_id' => $merchant_id,
                    'order_id' => $order_id,
                    'data_type' => 'initDistribOrder(order_data)',
                    'data' => json_encode($order_data))
                );

                //部分类型订单不参与分佣
                if($order_data['order_type'] == ORDER_SALEPAY || $order_data['pay_type'] == ORDER_PAY_DELIVERY)
                {
                    goto end_init;
                }

                if($order_data['status_distrib'] !== DISTRIB_AWAIT)
                {
                    //记录异常
                    $except = [
                        'activity_id' => $order_id,
                        'data_type' => 'distrib_service_init',
                        'content' => '初始化推客订单(订单异常)：'.json_encode($order_data),
                    ];
                    CommonApi::errlog($except);
                    return;
                }


                if(!empty($order_data['distrib_member_id']))
                {
                    $comission_percent = json_decode($distrib_setting['comission_percent'], true);  //佣金比例
                    $order_goods_list =  OrderGoods::get_data_list(['merchant_id' => $merchant_id,'order_id' => $order_id], 'goods_id,spec_id,goods_name,goods_img,quantity,pay_price');   //获取订单商品
                    $distrib_detail['distrib_partner_1'] = $order_data['distrib_member_id'];    //一级推客id
                    $distrib_order = array();   //佣金订单
                    $distrib_order['total_comission'] = 0;  //订单总佣金

                    DB::beginTransaction(); //开启事物

                    //计算佣金
                    for($i = 1; $i <= $distrib_setting['distrib_level']; $i++)
                    {
                        //获取当前级别推客
                        $distrib_partner = empty($distrib_detail['distrib_partner_'.$i]) ? null : DistribPartner::get_data_by_memberid($distrib_detail['distrib_partner_'.$i], $merchant_id);

                        if(!empty($distrib_partner['id'])){

                            //获取上级推客
                            $distrib_detail['distrib_partner_'.($i+1)] = empty($distrib_partner['parent_member_id']) ? 0 : $distrib_partner['parent_member_id'];
                            $distrib_order_detail = array();    //当前推客订单（各级分佣明细）

                            $distrib_order_detail['merchant_id'] = $merchant_id;
                            $distrib_order_detail['member_id'] = $distrib_partner['member_id'];
                            $distrib_order_detail['order_id'] = $order_id;
                            $distrib_order_detail['order_sn'] = $order_data['order_sn'];
                            $distrib_order_detail['level'] = $i;
                            $distrib_order_detail['comission'] = 0;
                            $distrib_order_detail['status'] = DISTRIB_AWAIT_SETTLED;

                            //计算订单中各商品佣金
                            foreach($order_goods_list as &$order_goods)
                            {

                                //如果推客被冻结 佣金为0 订单正常生成
                                if(!empty($distrib_partner['status']) && $distrib_partner['status'] === 1)
                                {
                                    $distrib_goods_setting = DistribGoodsSetting::get_data_by_goods_id($order_goods['goods_id'],$merchant_id);

                                    //LOG
                                    self::distribLog(array( 'merchant_id' => $merchant_id,
                                            'order_id' => $order_id,
                                            'data_type' => 'initDistribOrder(distrib_goods_setting)',
                                            'data' => json_encode($distrib_goods_setting))
                                    );

                                    if($distrib_goods_setting && !empty($distrib_goods_setting['comission_percent']))
                                    {
                                        $goods_comission_percent = json_decode($distrib_goods_setting['comission_percent'], true);
                                        $percent = empty($goods_comission_percent['comission_percent_'.$i]) ? 0 : $goods_comission_percent['comission_percent_'.$i];   //各级分佣比例
                                    }
                                    else
                                    {
                                        $percent = empty($comission_percent['comission_percent_'.$i]) ? 0 : $comission_percent['comission_percent_'.$i];   //各级分佣比例
                                    }
                                }
                                else
                                {
                                    $percent = 0;
                                }

                                $order_goods['comission_info']['comission_'.$i]['comission_percent'] = $percent; //分佣比例
                                $order_goods['comission_info']['comission_'.$i]['comission'] = round($percent * $order_goods['pay_price'] / 100, 2); //当前商品佣金
                                $distrib_order_detail['comission'] += $order_goods['comission_info']['comission_'.$i]['comission'];  //计算当前推客订单佣金

                                //计算商品总佣金
                                $order_goods['comission'] = empty($order_goods['comission']) ?
                                    $order_goods['comission_info']['comission_'.$i]['comission'] :
                                    $order_goods['comission'] + $order_goods['comission_info']['comission_'.$i]['comission'];


                                unset($distrib_goods_setting);
                                unset($goods_comission_percent);
                                unset($percent);
                            }


                            $distrib_order['total_comission'] += $distrib_order_detail['comission'];
                            //生成各级推客订单
                            $distrib_order_detail_id = DistribOrderDetail::insert_data($distrib_order_detail);

                            if(empty($distrib_order_detail_id) || !is_int($distrib_order_detail_id))
                            {
                                DB::rollBack();

                                //记录异常
                                $except = [
                                    'activity_id' => $order_id,
                                    'data_type' => 'distrib_service_init',
                                    'content' => '初始化推客订单(订单生成失败)：'.json_encode($distrib_order_detail),
                                ];
                                CommonApi::errlog($except);

                                return;
                            }

                            if($distrib_order_detail['comission'] > 0)
                            {
                                //当前推客追加佣金
                                $expect_comission_flag = DistribPartner::increment_data($distrib_order_detail['member_id'],
                                    $merchant_id ,'expect_comission',$distrib_order_detail['comission']);

                                if(empty($expect_comission_flag))
                                {
                                    DB::rollBack();

                                    //记录异常
                                    $except = [
                                        'activity_id' => $order_id,
                                        'data_type' => 'distrib_service_init',
                                        'content' => '初始化推客订单(递增佣金失败)：'.json_encode($distrib_order_detail).
                                            ';expect_comission_flag:'.$expect_comission_flag,
                                    ];
                                    CommonApi::errlog($except);

                                    return;
                                }
                            }

                            unset($distrib_order_detail);
                            unset($distrib_order_detail_id);
                            unset($expect_comission_flag);

                            $flag = 1;  //标记至少进入一次佣金计算
                        }

                        unset($distrib_partner);

                    }

                    if(empty($flag))
                    {
                        DB::rollBack();

                        //记录异常
                        $except = [
                            'activity_id' => $order_id,
                            'data_type' => 'distrib_service_init',
                            'content' => '初始化推客订单(获取推客异常)：'.json_encode($distrib_detail),
                        ];
                        CommonApi::errlog($except);

                        return;
                    }

                    //记录当前订单每件商品的分佣明细
                    foreach($order_goods_list as $order_goods_info)
                    {
                        $distrib_order_goods['merchant_id'] = $merchant_id;
                        $distrib_order_goods['order_id'] = $order_id;
                        $distrib_order_goods['goods_id'] = $order_goods_info['goods_id'];
                        $distrib_order_goods['spec_id'] = $order_goods_info['spec_id'];
                        $distrib_order_goods['goods_name'] = $order_goods_info['goods_name'];
                        $distrib_order_goods['goods_img'] = $order_goods_info['goods_img'];
                        $distrib_order_goods['goods_quantity'] = $order_goods_info['quantity'];
                        $distrib_order_goods['goods_amount'] = $order_goods_info['pay_price'];
                        $distrib_order_goods['comission'] = $order_goods_info['comission'];
                        $distrib_order_goods['comission_info'] = json_encode($order_goods_info['comission_info']);

                        $distrib_order_goods_id = DistribOrderGoods::insert_data($distrib_order_goods);

                        if(empty($distrib_order_goods_id) || !is_int($distrib_order_goods_id))
                        {
                            DB::rollBack();

                            //记录异常
                            $except = [
                                'activity_id' => $order_id,
                                'data_type' => 'distrib_service_init',
                                'content' => '初始化推客订单(单商品生成失败)：'.json_encode($distrib_order_goods),
                            ];
                            CommonApi::errlog($except);

                            return;
                        }

                        unset($distrib_order_goods);
                        unset($distrib_order_goods_id);
                    }

                    $distrib_order['merchant_id'] = $merchant_id;
                    $distrib_order['member_id'] = $order_data['distrib_member_id'];
                    $distrib_order['order_member_id'] = $order_data['member_id'];
                    $distrib_order['appid'] = $order_data['appid'];
                    $distrib_order['order_id'] = $order_id;
                    $distrib_order['order_sn'] = $order_data['order_sn'];
                    $distrib_order['order_amount'] = $order_data['amount'] - $order_data['shipment_fee'];
                    $distrib_order['status'] = DISTRIB_AWAIT_SETTLED;

                    //生成佣金主订单
                    $distrib_order_goods_id = DistribOrder::insert_data($distrib_order);

                    if(empty($distrib_order_goods_id) || !is_int($distrib_order_goods_id))
                    {
                        DB::rollBack();

                        //记录异常
                        $except = [
                            'activity_id' => $order_id,
                            'data_type' => 'distrib_service_init',
                            'content' => '初始化推客订单(主订单生成失败)：'.json_encode($distrib_order),
                        ];
                        CommonApi::errlog($except);

                        return;
                    }

                    $order_status_distrib = DISTRIB_AWAIT_SETTLED;  //已处理，待结算
                }
            }

            end_init:

            //更新订单状态
            $order_info_update_flag = OrderInfo::update_data($order_id ,$merchant_id ,array('status_distrib' => $order_status_distrib));

            if($order_info_update_flag)
            {
                DB::commit();
            }
            else
            {
                DB::rollBack();

                //记录异常
                $except = [
                    'activity_id' => $order_id,
                    'data_type' => 'distrib_service_init',
                    'content' => '初始化推客订单(订单状态更新失败)：'.json_encode(array('order_id' => $order_id, 'merchant_id' => $merchant_id, 'status_distrib' => $order_status_distrib)),
                ];
                CommonApi::errlog($except);

                return;
            }

        }
        catch(\Exception $e)
        {

            DB::rollBack();

            //记录异常
            $except = [
                'activity_id' => $order_id,
                'data_type' => 'distrib_service_init',
                'content' => '初始化推客订单(ERROR)：'.$order_id . '_' . $merchant_id . '_' . $e->getMessage() . ' ' . $e->getFile() . ' ' . $e->getLine(),
            ];
            CommonApi::errlog($except);
        }
    }




    /**
     * 退款变更佣金
     * @author 王禹
     * @param $order_id
     * @param $merchant_id
     * @param $order_refund_id
     */
    static function refundComission($order_id, $order_refund_id, $merchant_id)
    {
        try
        {
            $order_status = OrderInfo::get_data_by_id($order_id, $merchant_id, 'status,status_distrib');  //获取订单状态
            //LOG
            self::distribLog(array( 'merchant_id' => $merchant_id,
                    'order_id' => $order_id,
                    'data_type' => 'refundComission(order_status)',
                    'data' => json_encode($order_status))
            );

            //判断订单是否参与分销并且在待结算状态
            if($order_status['status_distrib'] !== DISTRIB_AWAIT_SETTLED)
            {
                return;
            }

            //获取退款子订单
            $order_refund_data = OrderRefund::get_data_by_id($order_refund_id, $merchant_id);
            //LOG
            self::distribLog(array( 'merchant_id' => $merchant_id,
                    'order_id' => $order_id,
                    'data_type' => 'refundComission(order_refund_data)',
                    'data' => json_encode($order_refund_data).'_'.$order_refund_id)
            );

            //验证退款子订单状态
            if($order_refund_data['status'] != REFUND_FINISHED || $order_refund_data['status_distrib'] == 1)
            {
                //记录异常
                $except = [
                    'activity_id' => $order_id,
                    'data_type' => 'distrib_refund_comission',
                    'content' => '退款变更推客佣金(订单未完成退款或已经处理)：'.json_encode($order_refund_data),
                ];
                CommonApi::errlog($except);

                return;
            }

            //获取主推客订单
            $distrib_order_data = DistribOrder::get_data_by_orderid($order_id, $merchant_id);

            //判断佣金是否为待结算状态
            if($distrib_order_data['status'] !== DISTRIB_AWAIT_SETTLED && $distrib_order_data['status'] !== DISTRIB_REFUND)
            {
                //记录异常
                $except = [
                    'activity_id' => $order_id,
                    'data_type' => 'distrib_refund_comission',
                    'content' => '退款变更推客佣金(佣金订单已结算，不可退款)：'.json_encode($distrib_order_data),
                ];
                CommonApi::errlog($except);

                return;
            }

            //获取当前退款商品
            $distrib_order_goods_data = DistribOrderGoods::get_data($order_id, $merchant_id, $order_refund_data['goods_id'], $order_refund_data['spec_id']);
            //LOG
            self::distribLog(array( 'merchant_id' => $merchant_id,
                    'order_id' => $order_id,
                    'data_type' => 'refundComission(distrib_order_goods)',
                    'data' => json_encode($distrib_order_goods_data).'_'.$order_refund_data['goods_id'].'_'.$order_refund_data['spec_id'])
            );

            $comission_info = json_decode($distrib_order_goods_data['comission_info'],true);    //获取当前商品各级佣金比例

            if(empty($comission_info['comission_1']))
            {
                //记录异常
                $except = [
                    'activity_id' => $order_id,
                    'data_type' => 'distrib_refund_comission',
                    'content' => '退款变更推客佣金(获取各级佣金比例失败)：'.json_encode($distrib_order_goods_data),
                ];
                CommonApi::errlog($except);

                return;
            }

            //sum订单退款总金额
            $refund_amount = OrderRefund::get_amount_by_orderid($order_id, $merchant_id);
            if(empty($refund_amount) && $refund_amount !== 0)
            {
                //记录异常
                $except = [
                    'activity_id' => $order_id,
                    'data_type' => 'distrib_refund_comission',
                    'content' => '退款变更推客佣金(商品退款总金额异常)：'.json_encode($refund_amount),
                ];
                CommonApi::errlog($except);

                return;
            }


            $distrib_order_detail_list = DistribOrderDetail::get_list_by_orderid($order_id, $merchant_id);  //查出所有涉及到的推客子订单
            if(empty(count($distrib_order_detail_list)))
            {
                //记录异常
                $except = [
                    'activity_id' => $order_id,
                    'data_type' => 'distrib_refund_comission',
                    'content' => '退款变更推客佣金(佣金子订单异常)：'.json_encode($distrib_order_detail_list),
                ];
                CommonApi::errlog($except);

                return;
            }


            $refund_comission_info = array(); //退款佣金
            $total_refund_comission = 0; //退款总金额

            DB::beginTransaction(); //开启事物

            //计算退款佣金
            foreach($distrib_order_detail_list as $distrib_order_detail)
            {

                //判断当前子订单是否为待结算状态（异常可能性极低）
                if($distrib_order_detail['status'] !== DISTRIB_AWAIT_SETTLED)
                {
                    DB::rollBack();

                    //记录异常
                    $except = [
                        'activity_id' => $order_id,
                        'data_type' => 'distrib_refund_comission',
                        'content' => '退款变更推客佣金(佣金子订单已结算，不可退款)：'.json_encode($distrib_order_detail),
                    ];
                    CommonApi::errlog($except);

                    return;
                }

                //当前退款子订单退款佣金
                $refund_comission_detail = round(abs($order_refund_data['amount']) * $comission_info['comission_'.$distrib_order_detail['level']]['comission_percent'] / 100, 2);

                if(($distrib_order_detail['refund_comission'] + $refund_comission_detail) > $distrib_order_detail['comission'])
                {
                    $refund_comission_detail = $distrib_order_detail['comission'] - $distrib_order_detail['refund_comission'];
                }

                //当前订单退款总佣金
                $refund_comission = round(abs($refund_amount) * $comission_info['comission_'.$distrib_order_detail['level']]['comission_percent'] / 100, 2);

                //如果退款佣金大于结算佣金或商品全部退完 退还全部佣金
                if($refund_comission > $distrib_order_detail['comission'] || $order_status['status'] == ORDER_REFUND_CANCEL)
                {
                    $refund_comission = $distrib_order_detail['comission'];
                    $refund_comission_detail = $distrib_order_detail['comission'] - $distrib_order_detail['refund_comission'];
                }



                //如果订单全部退款 更改分销子订单状态
                if($order_status['status'] == ORDER_REFUND_CANCEL)
                {
                    $distrib_order_detail_data['status'] = DISTRIB_REFUND;   //推客子订单标记为已退单
                    $distrib_order_detail_flag = DistribOrderDetail::update_data($distrib_order_detail['id'], $order_id, $merchant_id, $distrib_order_detail_data);

                    if(empty($distrib_order_detail_flag))
                    {
                        DB::rollBack();

                        //记录异常
                        $except = [
                            'activity_id' => $order_id,
                            'data_type' => 'distrib_refund_comission',
                            'content' => '退款变更推客佣金(更新推客订单子表失败)：'.json_encode($distrib_order_detail_data).'_'.$distrib_order_detail_flag,
                        ];
                        CommonApi::errlog($except);

                        return;
                    }
                }


                //扣除当前子订单对应推客的退款佣金
                if($refund_comission_detail > 0)
                {

                    //递增退款佣金
                    $distrib_comission_flag = DistribOrderDetail::increment_data($distrib_order_detail['id'], $order_id, $merchant_id,'refund_comission',$refund_comission_detail,'comission');
                    if(empty($distrib_comission_flag))
                    {
                        DB::rollBack();

                        //记录异常
                        $except = [
                            'activity_id' => $order_id,
                            'data_type' => 'distrib_refund_comission',
                            'content' => '退款变更推客佣金(递增退款佣金失败)：'.$refund_comission_detail.'_'.$distrib_comission_flag,
                        ];
                        CommonApi::errlog($except);

                        return;
                    }

                    //扣除未结算佣金
                    $expect_comission_flag = DistribPartner::decrement_data($distrib_order_detail['member_id'],
                        $merchant_id, 'expect_comission', $refund_comission_detail);

                    if(empty($expect_comission_flag))
                    {
                        DB::rollBack();

                        //记录异常
                        $except = [
                            'activity_id' => $order_id,
                            'data_type' => 'distrib_refund_comission',
                            'content' => '退款变更推客佣金(更新推客失败)：'.json_encode($distrib_order_detail).
                                ';expect_comission_flag:'.$expect_comission_flag.
                                ';refund_comission_detail:'.$refund_comission_detail,
                        ];
                        CommonApi::errlog($except);

                        return;
                    }
                }
                else
                {
                    $distrib_comission_flag = -1;
                    $expect_comission_flag = -1;
                }

                $refund_comission_info['refund_comission_'.$distrib_order_detail['level']] = $refund_comission_detail; //记录各级当前退款子订单退款金额
                $total_refund_comission += $refund_comission;   //记录该订单退款总金额

                //LOG
                self::distribLog(array( 'merchant_id' => $merchant_id,
                        'order_id' => $order_id,
                        'data_type' => 'refundComission(distrib_order_detail)',
                        'data' => json_encode(array(

                            'distrib_order_detail' => $distrib_order_detail,
                            'refund_comission' => $refund_comission,
                            'refund_comission_detail' => $refund_comission_detail,
                            'distrib_order_detail_flag' => $distrib_comission_flag,
                            'expect_comission_flag' => $expect_comission_flag

                        )))
                );

                unset($distrib_order_detail_flag);
                unset($distrib_comission_flag);
                unset($distrib_order_detail_data);
                unset($refund_comission_detail);
                unset($expect_comission_flag);
                unset($refund_comission);
            }

            //推客订单商品表 记录退款佣金信息
            $refund_comission_list =array();
            if(!empty(json_decode($distrib_order_goods_data['refund_info'],true)))
            {
                $refund_comission_list = json_decode($distrib_order_goods_data['refund_info'],true);
            }
            $refund_comission_list[] = $refund_comission_info;

            $distrib_order_goods_flag = DistribOrderGoods::update_data($order_id, $merchant_id, $order_refund_data['goods_id'], $order_refund_data['spec_id'], array('refund_info' => json_encode($refund_comission_list)));

            if(empty($distrib_order_goods_flag))
            {
                DB::rollBack();

                //记录异常
                $except = [
                    'activity_id' => $order_id,
                    'data_type' => 'distrib_refund_comission',
                    'content' => '退款变更推客佣金(更新推客订单商品失败)：'.json_encode($refund_comission_list).'_'.$distrib_order_goods_flag,
                ];
                CommonApi::errlog($except);

                return;
            }

            //更新退款子订单 推客处理状态为：已处理
            $order_refund_flag = OrderRefund::update_data_status_distrib($order_refund_data['id'], $merchant_id, array('status_distrib' => 1));

            if(empty($order_refund_flag))
            {
                DB::rollBack();

                //记录异常
                $except = [
                    'activity_id' => $order_id,
                    'data_type' => 'distrib_refund_comission',
                    'content' => '退款变更推客佣金(更新退款子订单失败)：'.$order_refund_flag,
                ];
                CommonApi::errlog($except);

                return;
            }

            //如果订单为退款已关闭 同步推客订单状态
            if($order_status['status'] == ORDER_REFUND_CANCEL)
            {
                $distrib_order['status'] = DISTRIB_REFUND;   //推客主订单状态更改为已退单
            }

            //更新推客订单主表 总退款佣金
            $distrib_order['total_refund_comission'] = $total_refund_comission; //推客总退款佣金
            $distrib_order_flag = DistribOrder::update_data($order_id, $merchant_id, $distrib_order);

            if(empty($distrib_order_flag))
            {
//                DB::rollBack();
//
//                //记录异常
//                $except = [
//                    'activity_id' => $order_id,
//                    'data_type' => 'distrib_refund_comission',
//                    'content' => '退款变更推客佣金(更新推客订单主表失败)：'.json_encode($distrib_order).'_'.$distrib_order_flag,
//                ];
//                CommonApi::errlog($except);
//
//                return;
                self::distribLog(array( 'merchant_id' => $merchant_id,
                        'order_id' => $order_id,
                        'data_type' => 'refundComission(total_refund_comission)',
                        'data' => json_encode($distrib_order).'_'.$distrib_order_flag)
                );
            }

            DB::commit();
        }
        catch(\Exception $e)
        {
            DB::rollBack();

            //记录异常
            $except = [
                'activity_id' => $order_id,
                'data_type' => 'distrib_refund_comission',
                'content' => '退款变更推客佣金(ERROR)：'.$order_id . '_' . $merchant_id . '_' . $e->getMessage() . ' ' . $e->getFile() . ' ' . $e->getLine(),
            ];
            CommonApi::errlog($except);
        }
    }





    /**
     * 结算佣金
     * @param $order_id
     * @param $merchant_id
     */
    static function settledComission($order_id, $merchant_id)
    {
        try {

            $order_status = OrderInfo::get_data_by_id($order_id, $merchant_id, 'status,status_distrib,appid');  //获取订单状态

            //LOG
            self::distribLog(array('merchant_id' => $merchant_id,
                    'order_id' => $order_id,
                    'data_type' => 'settledComission(order_status)',
                    'data' => json_encode($order_status))
            );

            //判断订单是否参与分销并且在待结算状态
            if($order_status['status_distrib'] !== DISTRIB_AWAIT_SETTLED)
            {
                return;
            }

            //判断订单是否为完成状态
            if ($order_status['status'] !== ORDER_SUCCESS) {
                //记录异常
                $except = [
                    'activity_id' => $order_id,
                    'data_type' => 'distrib_settled_comission',
                    'content' => '结算佣金(订单未完成，不可结算)：' . json_encode($order_status),
                ];
                CommonApi::errlog($except);

                return;
            }

            //获取主推客订单
            $distrib_order_data = DistribOrder::get_data_by_orderid($order_id, $merchant_id);

            //判断佣金是否为待结算状态
            if ($distrib_order_data['status'] !== DISTRIB_AWAIT_SETTLED) {
                //记录异常
                $except = [
                    'activity_id' => $order_id,
                    'data_type' => 'distrib_settled_comission',
                    'content' => '结算佣金(订单佣金已结算，不可重复结算)：' . json_encode($distrib_order_data),
                ];
                CommonApi::errlog($except);

                return;
            }



            //查看是否有未处理的退款订单

            $where_order_refund[] = array('column' => 'merchant_id', 'value' => $merchant_id, 'operator' => '=');
            $where_order_refund[] = array('column' => 'order_id', 'value' => $order_id, 'operator' => '=');
            $where_order_refund[] = array('column' => 'status_distrib', 'value' => 0, 'operator' => '=');

            //筛选掉直接关闭的订单
            $where_notins[] = array('column' => 'status', 'value' => [REFUND_CLOSE,REFUND_CANCEL,REFUND_MER_CANCEL]);

            $order_refund_flag = OrderRefund::get_data_count($where_order_refund, $where_notins);

            //验证退款子订单状态
            if($order_refund_flag > 0)
            {
                //记录异常
                $except = [
                    'activity_id' => $order_id,
                    'data_type' => 'distrib_settled_comission',
                    'content' => '结算佣金(有未处理的退款订单，结算失败)：'.$order_refund_flag,
                ];
                CommonApi::errlog($except);

                return;
            }



            //查出所有涉及到的推客子订单
            $distrib_order_detail_list = DistribOrderDetail::get_list_by_orderid($order_id, $merchant_id);
            if(empty(count($distrib_order_detail_list)))
            {
                //记录异常
                $except = [
                    'activity_id' => $order_id,
                    'data_type' => 'distrib_settled_comission',
                    'content' => '结算佣金(佣金子订单异常)：'.json_encode($distrib_order_detail_list),
                ];
                CommonApi::errlog($except);

                return;
            }

            DB::beginTransaction(); //开启事物

            //结算各级推客佣金
            foreach($distrib_order_detail_list as $distrib_order_detail)
            {

                //判断当前子订单是否为待结算状态（异常可能性极低）
                if($distrib_order_detail['status'] !== DISTRIB_AWAIT_SETTLED)
                {
                    DB::rollBack();

                    //记录异常
                    $except = [
                        'activity_id' => $order_id,
                        'data_type' => 'distrib_settled_comission',
                        'content' => '结算佣金(子订单已结算，不可重复结算)：'.json_encode($distrib_order_detail),
                    ];
                    CommonApi::errlog($except);

                    return;
                }



                //更新各级推客订单信息
                $distrib_order_detail_data['status'] = DISTRIB_FINISH;
                $distrib_order_detail_data['settled_time'] = date("Y-m-d H:i:s");


                $distrib_order_detail_flag = DistribOrderDetail::update_data($distrib_order_detail['id'], $order_id, $merchant_id, $distrib_order_detail_data);

                if(empty($distrib_order_detail_flag))
                {
                    DB::rollBack();

                    //记录异常
                    $except = [
                        'activity_id' => $order_id,
                        'data_type' => 'distrib_settled_comission',
                        'content' => '结算佣金(更新推客订单子表失败)：'.json_encode($distrib_order_detail_data).'_'.$distrib_order_detail_flag,
                    ];
                    CommonApi::errlog($except);

                    return;
                }


                $comission = $distrib_order_detail['comission'] - $distrib_order_detail['refund_comission']; //结算佣金

                if($comission > 0)
                {
                    //扣除未结算佣金
                    $expect_comission_flag = DistribPartner::decrement_data($distrib_order_detail['member_id'],
                        $merchant_id, 'expect_comission', $comission);

                    if(empty($expect_comission_flag))
                    {
                        DB::rollBack();

                        //记录异常
                        $except = [
                            'activity_id' => $order_id,
                            'data_type' => 'distrib_settled_comission',
                            'content' => '结算佣金(扣除未结算佣金失败)：'.json_encode($distrib_order_detail).
                                ';expect_comission_flag:'.$expect_comission_flag.
                                ';comission:'.$comission,
                        ];
                        CommonApi::errlog($except);

                        return;
                    }


                    //递增累计结算佣金
                    $total_comission_flag = DistribPartner::increment_data($distrib_order_detail['member_id'],
                        $merchant_id, 'total_comission', $comission);

                    if(empty($total_comission_flag))
                    {
                        DB::rollBack();

                        //记录异常
                        $except = [
                            'activity_id' => $order_id,
                            'data_type' => 'distrib_settled_comission',
                            'content' => '结算佣金(递增累计结算佣金失败)：'.json_encode($distrib_order_detail).
                                ';total_comission_flag:'.$total_comission_flag.
                                ';comission:'.$comission,
                        ];
                        CommonApi::errlog($except);

                        return;
                    }

                    $memberinfo  = Member::get_data_by_id($distrib_order_detail['member_id'] ,$merchant_id);

                    //增加推客（会员）余额
                    $balance_flag = Member::increment_data($distrib_order_detail['member_id'],
                        $merchant_id, 'balance', $comission);

                    if(empty($balance_flag))
                    {
                        DB::rollBack();

                        //记录异常
                        $except = [
                            'activity_id' => $order_id,
                            'data_type' => 'distrib_settled_comission',
                            'content' => '结算佣金(增加推客余额失败)：'.json_encode($distrib_order_detail).
                                ';total_comission_flag:'.$balance_flag.
                                ';comission:'.$comission,
                        ];
                        CommonApi::errlog($except);

                        return;
                    }



                    //会员余额变动记录
                    $member_balance_detail['merchant_id'] = $merchant_id;
                    $member_balance_detail['member_id'] = $distrib_order_detail['member_id'];
                    $member_balance_detail['amount'] = $comission;
                    $member_balance_detail['pre_amount'] = $memberinfo['balance'];
                    $member_balance_detail['final_amount'] = $memberinfo['balance'] + $comission;
                    $member_balance_detail['type'] = 4;
                    $member_balance_detail['is_delete'] = 1;

                    $member_balance_detail_id = MemberBalanceDetail::insert_data($member_balance_detail);

                    if(empty($member_balance_detail_id))
                    {
                        DB::rollBack();

                        //记录异常
                        $except = [
                            'activity_id' => $order_id,
                            'data_type' => 'distrib_settled_comission',
                            'content' => '结算佣金(会员余额变动记录失败)：'.json_encode($member_balance_detail).
                                ';member_balance_detail_id:'.$member_balance_detail_id
                        ];
                        CommonApi::errlog($except);

                        return;
                    }

                    //记录日志
                    $settled_comission_data['member_balance_detail'] = $member_balance_detail;
                    $settled_comission_data['expect_comission_flag'] = $expect_comission_flag;
                    $settled_comission_data['total_comission_flag'] = $total_comission_flag;
                    $settled_comission_data['balance_flag'] = $balance_flag;
                    $settled_comission_data['member_balance_detail_id'] = $member_balance_detail_id;

                    //发送消息模板
                    (new WeixinMsgService())->balance(array(
                        'merchant_id' => $merchant_id,
                        'member_id' => $distrib_order_detail['member_id'],
                        'appid' => $order_status['appid'],
                        'change' => $comission.'元',
                        'time' => $distrib_order_detail_data['settled_time'],
                        'type' => '佣金结算'));
                }
                else
                {
                    $comission = 0;
                }

                //推客结算记录
                $distrib_settled_log['merchant_id'] = $merchant_id;
                $distrib_settled_log['member_id'] = $distrib_order_detail['member_id'];
                $distrib_settled_log['order_id'] = $order_id;
                $distrib_settled_log['order_sn'] = $distrib_order_detail['order_sn'];
                $distrib_settled_log['order_amount'] = $distrib_order_data['order_amount'];
                $distrib_settled_log['comission'] = $comission;
                $distrib_settled_log['settled_time'] = date("Y-m-d H:i:s");;

                $distrib_settled_log_id = DistribSettledLog::insert_data($distrib_settled_log);

                //记录异常但不做回滚与停止操作
                if(empty($distrib_settled_log_id))
                {
                    //记录异常
                    $except = [
                        'activity_id' => $order_id,
                        'data_type' => 'distrib_settled_comission',
                        'content' => '结算佣金(插入推客结算记录失败)：'.json_encode($distrib_settled_log).
                            ';distrib_settled_log_id:'.$distrib_settled_log_id
                    ];
                    CommonApi::errlog($except);
                    unset($except);
                }

                //记录日志
                $settled_comission_data['distrib_order_detail'] = $distrib_order_detail;
                $settled_comission_data['comission'] = $comission;
                $settled_comission_data['distrib_order_detail_data'] = $distrib_order_detail_data;
                $settled_comission_data['distrib_order_detail_flag'] = $distrib_order_detail_flag;
                $settled_comission_data['distrib_settled_log_id'] = $distrib_settled_log_id;


                //LOG
                self::distribLog(array( 'merchant_id' => $merchant_id,
                        'order_id' => $order_id,
                        'data_type' => 'settledComission(settled_comission_data)',
                        'data' => json_encode($settled_comission_data))
                );

                unset($comission);
                unset($distrib_order_detail_flag);
                unset($distrib_order_detail_data);
                unset($expect_comission_flag);
                unset($total_comission_flag);
                unset($distrib_settled_log);
                unset($distrib_settled_log_id);
                unset($memberinfo);
                unset($balance_flag);
                unset($member_balance_detail);
                unset($member_balance_detail_id);
            }


            //变更推客订单主表状态为已结算
            $distrib_order['status'] = DISTRIB_FINISH;
            $distrib_order['settled_time'] = date("Y-m-d H:i:s");

            $distrib_order_flag = DistribOrder::update_data($order_id, $merchant_id, $distrib_order);

            if(empty($distrib_order_flag))
            {
                DB::rollBack();

                //记录异常
                $except = [
                    'activity_id' => $order_id,
                    'data_type' => 'distrib_settled_comission',
                    'content' => '退款变更推客佣金(变更推客订单主表状态失败)：'.json_encode($distrib_order).
                        '_'.$distrib_order_flag,
                ];
                CommonApi::errlog($except);

                return;
            }


            //变更推客订单主表状态为已结算
            $order_info['status_distrib'] = DISTRIB_FINISH;

            $order_info_flag = OrderInfo::update_data($order_id, $merchant_id, $order_info);

            if(empty($order_info_flag))
            {
                DB::rollBack();

                //记录异常
                $except = [
                    'activity_id' => $order_id,
                    'data_type' => 'distrib_settled_comission',
                    'content' => '退款变更推客佣金(更新订单主表结算失败)：'.json_encode($order_info).
                        '_'.$distrib_order_flag,
                ];
                CommonApi::errlog($except);

                return;
            }

            DB::commit();

        }
        catch(\Exception $e)
        {
            DB::rollBack();

            //记录异常
            $except = [
                'activity_id' => $order_id,
                'data_type' => 'distrib_settled_comission',
                'content' => '结算佣金(ERROR)：'.$order_id . '_' . $merchant_id . '_' . $e->getMessage() . ' ' . $e->getFile() . ' ' . $e->getLine(),
            ];
            CommonApi::errlog($except);
        }
    }

    static function distribLog($data = array())
    {
        DistribLog::insert_data($data);
    }








    /**
    *推客验证log
    *适用情况 (用户手动申请成为推客&&商城设置推客审核为自动审核) 
    *@param $merchant_id int 必选 商城id
    *@param $member_id   int 必选 会员id
    *@author renruiqi@dodoca.com
    */
    public function addDistribCheckLog($member_id, $merchant_id)
    {   
        //查询推客信息
        $distrib_info = DistribPartner::get_data_by_memberid($member_id , $merchant_id);
        if(!$distrib_info) return ['errcode'=>'1','errmsg'=>'该用户并非推客'];//用户并非推客
        $data = [
            'member_id'         => $member_id,
            'merchant_id'       => $merchant_id,
            'mobile'            => $distrib_info['mobile'],
            'collect_fields'    => $distrib_info['collect_fields'],
            'apply_time'        => $distrib_info['created_time'],
            'check_time'        => date('Y-m-d H:i:s'),
            'check_uid'         => 0,//自动审核
            'check_type'        => 1,//自动审核
            'check_result'      => 1,//1通过2拒绝
        ];
        DistribCheckLog::insert_data($data);
        return ['errcode'=>'0','errmsg'=>'操作完成'];
    }


    /**
     * 绑定买家与推客佣金关系
     * @author 郭其凯
     * @param $member_id 会员id
     * @param $merchant_id 商户ID
     * @param $distrib_member_id 推客ID
     */
    static function distribBuyerRelation($member_id, $merchant_id, $distrib_member_id, $share_member_id)
    {
        //查询分享者是否是推客
        if($share_member_id){
            $share_is_partner = DistribPartner::get_data_by_memberid($share_member_id, $merchant_id);
            if ($share_is_partner && in_array($share_is_partner['status'], [1, 2])) {
                $distrib_member_id = $share_member_id;
            }
        }

        if($distrib_member_id){
            try {
                //推客状态0:未审核, 1:正常, 2:禁用, 3:审核失败
                $is_partner = DistribPartner::get_data_by_memberid($distrib_member_id, $merchant_id);
                if($is_partner && in_array($is_partner['status'], [1, 2])){  //正常或被禁用时可以绑定关系
                    //记录会员第一次进入推客店铺记录，用与推客上下级设置 2018-7-12新增
                    if($distrib_member_id != $member_id){
                        $first_record = DistribMemberFirstRecord::get_data_by_memberid($member_id, $merchant_id);
                        if(!$first_record){
                            $first_data = [
                                'merchant_id'       => $merchant_id,
                                'member_id'         => $member_id,
                                'distrib_member_id' => $distrib_member_id
                            ];
                            DistribMemberFirstRecord::insert_data($first_data);
                        }
                    }
                    //查看佣金关系
                    $relation = DistribBuyerRelation::get_data_by_memberid($member_id, $merchant_id);
                    if(!$relation){
                        $relation_data = [
                            'merchant_id'       => $merchant_id,
                            'member_id'         => $member_id,
                            'distrib_member_id' => $distrib_member_id
                        ];

                        if(DistribBuyerRelation::insert_data($relation_data)){
                            return $distrib_member_id;
                        }
                    }else{
                        /*
                            绑定过关系 字段buyer_period为0：一次 1：永久
                            永久：之前已绑定过关系，不做任何操作
                            一次：重新绑定关系，如果自已已经是推客，相当于进入自已的店铺，与自已绑定
                        */
                        $distrib_setting = DistribSetting::get_data_by_merchant_id($merchant_id);
                        if($distrib_setting && $distrib_setting['buyer_period'] == 0){
                            //如果是一次性关系，查看自已是否为推客，是推客则佣金关系为自已
                            $my_is_partner = DistribPartner::get_data_by_memberid($member_id, $merchant_id);
                            if($my_is_partner && in_array($my_is_partner['status'], [1, 2])){
                                $distrib_member_id = $member_id;  
                            }

                            if($relation['distrib_member_id'] != $distrib_member_id){
                                DistribBuyerRelation::update_data($member_id, $merchant_id, ['distrib_member_id' => $distrib_member_id]);
                            }
                        }
                        return $distrib_member_id;
                    }
                }
            } catch (\Exception $e) {
                //记录异常
                $except = [
                    'activity_id' => $merchant_id,
                    'data_type' => 'distrib_buyer_relation',
                    'content' => '绑定买家与推客佣金关系(ERROR)：'.$merchant_id . '_' . $member_id . '_' . $distrib_member_id . '_' . $e->getMessage() . ' ' . $e->getFile() . ' ' . $e->getLine()
                ];
                CommonApi::errlog($except);
            }
        }
    }


    /**
     * 退款修复佣金
     * @author 王禹
     * @param $order_id
     * @param $merchant_id
     * @param $order_refund_id
     */
    static function fixComission($order_id, $merchant_id)
    {
        try
        {
            $order_status = OrderInfo::get_data_by_id($order_id, $merchant_id, 'status,status_distrib');  //获取订单状态
            //LOG
            self::distribLog(array( 'merchant_id' => $merchant_id,
                    'order_id' => $order_id,
                    'data_type' => 'fixComission(order_status)',
                    'data' => json_encode($order_status))
            );

            //判断订单是否参与分销并且在待结算状态
            if($order_status['status_distrib'] !== DISTRIB_AWAIT_SETTLED || $order_status['status'] !== ORDER_MERCHANT_CANCEL)
            {
                return;
            }


            //获取主推客订单
            $distrib_order_data = DistribOrder::get_data_by_orderid($order_id, $merchant_id);

            //判断佣金是否为待结算状态
            if($distrib_order_data['status'] !== DISTRIB_AWAIT_SETTLED)
            {
                //记录异常
                $except = [
                    'activity_id' => $order_id,
                    'data_type' => 'distrib_fix_refund_comission',
                    'content' => '退款修复变更推客佣金(佣金订单已结算，不可退款)：'.json_encode($distrib_order_data),
                ];
                CommonApi::errlog($except);

                return;
            }

            $distrib_order_detail_list = DistribOrderDetail::get_list_by_orderid($order_id, $merchant_id);  //查出所有涉及到的推客子订单
            if(empty(count($distrib_order_detail_list)))
            {
                //记录异常
                $except = [
                    'activity_id' => $order_id,
                    'data_type' => 'distrib_fix_refund_comission',
                    'content' => '退款修复变更推客佣金(佣金子订单异常)：'.json_encode($distrib_order_detail_list),
                ];
                CommonApi::errlog($except);

                return;
            }


            $refund_comission_info = array(); //退款佣金
            $total_refund_comission = 0; //退款总金额

            DB::beginTransaction(); //开启事物

            //计算退款佣金
            foreach($distrib_order_detail_list as $distrib_order_detail)
            {

                //判断当前子订单是否为待结算状态（异常可能性极低）
                if($distrib_order_detail['status'] !== DISTRIB_AWAIT_SETTLED)
                {
                    DB::rollBack();

                    //记录异常
                    $except = [
                        'activity_id' => $order_id,
                        'data_type' => 'distrib_fix_refund_comission',
                        'content' => '退款修复变更推客佣金(佣金子订单已结算，不可退款)：'.json_encode($distrib_order_detail),
                    ];
                    CommonApi::errlog($except);

                    return;
                }

                //当前订单退款总佣金
                $refund_comission_detail = $distrib_order_detail['comission'] - $distrib_order_detail['refund_comission'];
                $refund_comission = $distrib_order_detail['comission'];


                //如果订单全部退款 更改分销子订单状态
                $distrib_order_detail_data['status'] = DISTRIB_REFUND;   //推客子订单标记为已退单
                $distrib_order_detail_flag = DistribOrderDetail::update_data($distrib_order_detail['id'], $order_id, $merchant_id, $distrib_order_detail_data);

                if(empty($distrib_order_detail_flag))
                {
                    DB::rollBack();

                    //记录异常
                    $except = [
                        'activity_id' => $order_id,
                        'data_type' => 'distrib_fix_refund_comission',
                        'content' => '退款修复变更推客佣金(更新推客订单子表失败)：'.json_encode($distrib_order_detail_data).'_'.$distrib_order_detail_flag,
                    ];
                    CommonApi::errlog($except);

                    return;
                }



                //扣除当前子订单对应推客的退款佣金
                if($refund_comission_detail > 0)
                {

                    //递增退款佣金
                    $distrib_comission_flag = DistribOrderDetail::increment_data($distrib_order_detail['id'], $order_id, $merchant_id,'refund_comission',$refund_comission_detail,'comission');
                    if(empty($distrib_comission_flag))
                    {
                        DB::rollBack();

                        //记录异常
                        $except = [
                            'activity_id' => $order_id,
                            'data_type' => 'distrib_fix_refund_comission',
                            'content' => '退款修复变更推客佣金(递增退款佣金失败)：'.$refund_comission_detail.'_'.$distrib_comission_flag,
                        ];
                        CommonApi::errlog($except);

                        return;
                    }

                    //扣除未结算佣金
                    $expect_comission_flag = DistribPartner::decrement_data($distrib_order_detail['member_id'],
                        $merchant_id, 'expect_comission', $refund_comission_detail);

                    if(empty($expect_comission_flag))
                    {
                        DB::rollBack();

                        //记录异常
                        $except = [
                            'activity_id' => $order_id,
                            'data_type' => 'distrib_fix_refund_comission',
                            'content' => '退款修复变更推客佣金(更新推客失败)：'.json_encode($distrib_order_detail).
                                ';expect_comission_flag:'.$expect_comission_flag.
                                ';refund_comission_detail:'.$refund_comission_detail,
                        ];
                        CommonApi::errlog($except);

                        return;
                    }
                }
                else
                {
                    $distrib_comission_flag = -1;
                    $expect_comission_flag = -1;
                }

                $refund_comission_info['refund_comission_'.$distrib_order_detail['level']] = $refund_comission_detail; //记录各级当前退款子订单退款金额
                $total_refund_comission += $refund_comission;   //记录该订单退款总金额

                //LOG
                self::distribLog(array( 'merchant_id' => $merchant_id,
                        'order_id' => $order_id,
                        'data_type' => 'fixComission(distrib_order_detail)',
                        'data' => json_encode(array(

                            'distrib_order_detail' => $distrib_order_detail,
                            'refund_comission' => $refund_comission,
                            'refund_comission_detail' => $refund_comission_detail,
                            'distrib_order_detail_flag' => $distrib_comission_flag,
                            'expect_comission_flag' => $expect_comission_flag

                        )))
                );

                unset($distrib_order_detail_flag);
                unset($distrib_comission_flag);
                unset($distrib_order_detail_data);
                unset($refund_comission_detail);
                unset($expect_comission_flag);
                unset($refund_comission);
            }

            $distrib_order['status'] = DISTRIB_REFUND;   //推客主订单状态更改为已退单


            //更新推客订单主表 总退款佣金
            $distrib_order['total_refund_comission'] = $total_refund_comission; //推客总退款佣金
            $distrib_order_flag = DistribOrder::update_data($order_id, $merchant_id, $distrib_order);

            if(empty($distrib_order_flag))
            {
                self::distribLog(array( 'merchant_id' => $merchant_id,
                        'order_id' => $order_id,
                        'data_type' => 'fixComission(total_refund_comission)',
                        'data' => json_encode($distrib_order).'_'.$distrib_order_flag)
                );
            }

            DB::commit();
        }
        catch(\Exception $e)
        {
            DB::rollBack();

            //记录异常
            $except = [
                'activity_id' => $order_id,
                'data_type' => 'distrib_fix_refund_comission',
                'content' => '退款修复变更推客佣金(ERROR)：'.$order_id . '_' . $merchant_id . '_' . $e->getMessage() . ' ' . $e->getFile() . ' ' . $e->getLine(),
            ];
            CommonApi::errlog($except);
        }
    }
}

<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017/12/14
 * Time: 17:45
 */
namespace  App\Services;

use App\Models\OrderInfo;
use App\Models\OrderSumDaily;

class OrderSumService{

    protected $ordersumdaily;

    public function __construct(OrderSumDaily $ordersumdaily) {

        $this->ordersumdaily = $ordersumdaily;

    }
    public function getOrderSum(){

        $order_info=new OrderInfo();

        for($i=1;$i<=60;$i++) {

            $yestoday = date("Ymd", strtotime("-" . $i . " day"));
            $date = date("Y-m-d");

            $wheres=array();
            $wheres[] = array('column' => 'created_time', 'value' => $date, 'operator' => '<');
            $total = $order_info->get_data_count($wheres); //订单总数

            $wheres[] = array('column' => 'status', 'value' => 11, 'operator' => '=');
            $wheres[] = array('column' => 'created_time', 'value' => $date, 'operator' => '<');
            $success_num = $order_info->get_data_count($wheres); //已完成订单总数

            $wheres = array();
            $wheres[] = array('column' => 'status', 'value' => 10, 'operator' => '=');
            $wheres[] = array('column' => 'created_time', 'value' => $date, 'operator' => '<=');
            $send_num = $order_info->get_data_count($wheres); //已发货订单总数

            $wheres = array();
            $wheres[] = array('column' => 'created_time', 'value' => date("Y-m-d", strtotime("-".($i-1)." day")), 'operator' => '<');
            $wheres[] = array('column' => 'created_time', 'value' => date("Y-m-d", strtotime("-".$i." day")), 'operator' => '>=');
            $total_day = $order_info->get_data_count($wheres); //昨日订单总数

            $wheres = array();
            $wheres[] = array('column' => 'status', 'value' => 11, 'operator' => '=');
            $wheres[] = array('column' => 'created_time', 'value' => date("Y-m-d", strtotime("-".($i-1)." day")), 'operator' => '<');
            $wheres[] = array('column' => 'created_time', 'value' => date("Y-m-d", strtotime("-".$i." day")), 'operator' => '>=');
            $success_num_day = $order_info->get_data_count($wheres); //昨日已完成订单总数

            $wheres = array();
            $wheres[] = array('column' => 'status', 'value' => 10, 'operator' => '=');
            $wheres[] = array('column' => 'created_time', 'value' => date("Y-m-d", strtotime("-".($i-1)." day")), 'operator' => '<');
            $wheres[] = array('column' => 'created_time', 'value' => date("Y-m-d", strtotime("-".$i." day")), 'operator' => '>=');
            $send_num_day = $order_info->get_data_count($wheres); //昨日已发货订单总数


            $ordersumdata = array(                //插入订单总表数据
                'total_day' => $total_day,
                'total' => $total,
                'success_num_day' => $success_num_day,
                'success_num' => $success_num,
                'send_num_day' => $send_num_day,
                'send_num' => $send_num,
                'day_time' => $yestoday,
            );

            $ordersumdaily = $this->ordersumdaily->where("day_time", $yestoday)->first();

            if (!$ordersumdaily) {

                $ordersumdata['created_time'] = date("Y-m-d H:i:s",strtotime("-".($i-1)." day"));

                $sumres = $this->ordersumdaily->insert($ordersumdata);
            }
        }
    }
}
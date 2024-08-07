<?php

/*
 * 首页后台
 * shangyazhao@dodoca.com
 *
 */

namespace App\Http\Controllers\Admin\Index;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Response;
use App\Models\WeixinInfo;
use Illuminate\Support\Facades\Auth;
use App\Services\OrderDailyService;
use App\Services\TradeStatDayService;
use App\Models\OrderInfo;
use App\Models\Goods;
use App\Models\OrderDaily;
use App\Models\Member;
use App\Models\IndexPictures;
use Illuminate\Http\Request;
use App\Http\Requests;

class IndexController extends Controller {

    protected $order_daily_service;
    protected $trade_stat_day_service;
    protected $order_info;
    protected $goods;
    protected $merchant_id;
    protected $member;

    public function __construct(OrderDailyService $order_daily_service,TradeStatDayService $trade_stat_day_service,OrderInfo $order_info,Goods $goods,Member $member,Request $request) {
        $this->order_daily_service = $order_daily_service;
        $this->trade_stat_day_service=$trade_stat_day_service;
        $this->order_info=$order_info;
        $this->goods=$goods;
        $this->member=$member;
        $this->merchant_id=Auth::user()->merchant_id; 
        $this->request = $request;
        $this->params = $request->all();
        $this->today = date('Ymd');
    }

    /*
     * 订单趋势统计
     */

    public function daysTrend() {
        if (!empty($this->params['startdate'])) {  //创建时间

            $start_day = strtotime($this->params['startdate']);

        }else{

            $start_day = strtotime($this->today) - 7*3600*24;
        }

        if (!empty($this->params['enddate'])) {  //结束时间

            $end_day =  strtotime($this->params['enddate']);

        }else{

            $end_day = strtotime($this->today);
        }
        $result = $this->order_daily_service->getOrderStatDays($this->merchant_id,$start_day,$end_day);
        $data['errcode'] = 0;
        $data['data'] = $result;        
        return Response::json($data);
    }

    /*
     * 订单数量统计
     */

    public function orderStatistics() {       
        $data['errcode'] = 0;       
        $data['data']['trade_amount']=$this->trade_stat_day_service->getTrades($this->merchant_id);  //今日交易额
         //今日订单总数
        $wheres = array();
        $wheres[] = array('column' => 'merchant_id', 'value' => $this->merchant_id, 'operator' => '=');
        $wheres[] = array('column' => 'created_time', 'value' => date("Y-m-d"), 'operator' => '>=');
        $wheres[] = array('column' => 'created_time', 'value' => date("Y-m-d", strtotime("+1 day")), 'operator' => '<');        
        $data['data']['trade_count']= $this->order_info->get_data_count($wheres); //今日订单总数
        
        //待付款订单数
        $wheres = array();
        $wheres[] = array('column' => 'merchant_id', 'value' => $this->merchant_id, 'operator' => '=');
        $wheres[] = array('column' => 'status', 'value' => [5,6], 'operator' => 'in');    
        $wheres[] = array('column' => 'pay_status', 'value' => 0, 'operator' => '=');
        $data['data']['topay_count']= $this->order_info->get_data_count($wheres); //待支付订单数
        
         //待发货订单数
        $wheres = array();
        $wheres[] = array('column' => 'merchant_id', 'value' => $this->merchant_id, 'operator' => '=');
        $wheres[] = array('column' => 'status', 'value' => 7, 'operator' => '=');  
        $wheres[] = array('column' => 'pay_status', 'value' => 1, 'operator' => '=');
        $data['data']['tosend_count']= $this->order_info->get_data_count($wheres); //待发货订单数
        
        //维权中订单数
        $query_refund = OrderInfo::where(['order_info.merchant_id'=>$this->merchant_id,'order_info.refund_status'=>1])
                                   ->leftJoin('order_refund','order_refund.order_id','=','order_info.id')
                                   ->where(function ($query)  {
                                        $query->whereIn('order_info.order_type',array(1, 2, 3, 4))
                                              ->orwhere(function ($query){
                                                            $query->where('order_info.order_type', 5)
                                                                  ->where('order_info.pay_status', 1);
                                                        });
                                    });
        $data['data']['feedback_count'] = $query_refund->count();
        return Response::json($data);
    }

    /*
     * 销量排行
     */

    public function salesSort() {
        $where = array(
            array('column' => 'merchant_id', 'value' => $this->merchant_id, 'operator' => '='),
            array('column' => 'is_delete', 'value' => 1, 'operator' => '='),
            array('column' => 'onsale', 'value' => 1, 'operator' => '=')
        );
        $result = $this->goods->get_data_list($where, 'title,csale,shelve_at', 0, 3,array(array('column'=>'csale','direct'=>'desc'),array('column'=>'shelve_at','direct'=>'desc')));
        $data['errcode'] = 0;
        $data['data'] = $result;
        return Response::json($data);
    }

    /*
     * 二维码
     */

    public function qrcode() {        
        $result = WeixinInfo::get_one_appid($this->merchant_id);
        if (empty($result) || empty($result['qrcode'])) {
            $data['errcode'] = 120001;
            $data['errmsg'] = '二维码未创建';
        }
        $data['errcode'] = 0;
        $data['data'] = array(
            "url" => $result['qrcode']
        );
        return Response::json($data);
    }

    //首页数据统计
    public function getIndexData(){
        // $yesterday = date("Ymd",strtotime("-1 day"));
        // $before_yesterday = date("Ymd",strtotime("-2 day"));
         if (!empty($this->params['startdate'])) {  //创建时间
            $before_yesterday = date("Ymd",strtotime($this->params['startdate']));
        }else{
            $before_yesterday = date("Ymd",strtotime("-2 day"));
        }

        if (!empty($this->params['enddate'])) {  //结束时间
            $yesterday = date("Ymd",strtotime($this->params['enddate'])) ;
        }else{
            $yesterday = date("Ymd",strtotime("-1 day"));
        }

        $yesterday_data=OrderDaily::get_data_by_id($yesterday,$this->merchant_id);
        $before_yesterday_data=OrderDaily::get_data_by_id($before_yesterday,$this->merchant_id);
        $result = array();
        if($yesterday_data && $before_yesterday_data){
            $views = $yesterday_data["total_views"]-$before_yesterday_data['total_views'];
            $views = $views?$views:0;
            $result['views']=$views?$views:'--'; //浏览量
            if($before_yesterday_data['total_views']!=0){
                $result['views_percentage'] = round(($views/$before_yesterday_data['total_views'])*100,2);
            }elseif($views){
                $result['views_percentage'] = 100;
            }else{
                $result['views_percentage'] = 0.00;
            }
            $orders = $yesterday_data["total"]-$before_yesterday_data['total'];
            $result['orders']=$orders?$orders:'--'; //下单数
            if($before_yesterday_data['total']!=0){
                $result['orders_percentage'] = round(($orders/$before_yesterday_data['total'])*100,2);
            }elseif($orders){
                $result['orders_percentage'] = 100;
            }else{
                $result['orders_percentage'] = 0.00;
            }        
            $order_pay = $yesterday_data["total_pay"]-$before_yesterday_data['total_pay'];
            $result['order_pay']=$order_pay?$order_pay:'--'; //今日订单付款数
            if($before_yesterday_data['total_pay']!=0){
                $result['order_pay_percentage'] = round(($order_pay/$before_yesterday_data['total_pay'])*100,2);
            }elseif($order_pay){
                $result['order_pay_percentage'] = 100;
            }else{
                $result['order_pay_percentage'] = 0.00;
            }
            $order_payment = $yesterday_data["total_payment_amount"]-$before_yesterday_data['total_payment_amount'];
            $result['order_payment']=$order_payment?round($order_payment,2):'--'; //支付金额
            if($before_yesterday_data['total_payment_amount']!=0){
                $result['order_payment_percentage'] = round(($order_payment/$before_yesterday_data['total_payment_amount'])*100,2);
            }elseif($order_payment){
                $result['order_payment_percentage'] = 100;
            }else{
                $result['order_payment_percentage'] = 0.00;
            }

            // if($yesterday_data['daily_views']!=0){
            //     //访问支付转化率
            //     $result['pay_conversion_rate'] = round($yesterday_data['total_pay_day']/$yesterday_data['daily_views'],2)*100;
            //     //访问下单转化率
            //     $result['order_conversion_rate'] = round($yesterday_data['total_day']/$yesterday_data['daily_views'],2)*100;
            // }else{
            //     $result['pay_conversion_rate'] = 0.00;
            //     $result['order_conversion_rate'] = 0.00;
            // }
            // //下单支付转化率
            // if($yesterday_data['total_day']!=0){
            //     $result['pay_order_conversion_rate'] = round($yesterday_data['total_pay_day']/$yesterday_data['total_day'],2)*100;
            // }else{
            //     $result['pay_order_conversion_rate'] = 0.00;
            // }

            if($views!=0){
                //访问支付转化率
                $result['pay_conversion_rate'] = round(($order_pay/$views)*100,2);
                //访问下单转化率
                $result['order_conversion_rate'] = round(($orders/$views)*100,2);
            }else{
                $result['pay_conversion_rate'] = 0.00;
                $result['order_conversion_rate'] = 0.00;
            }
            //下单支付转化率
            if($orders!=0){
                $result['pay_order_conversion_rate'] = round(($order_pay/$orders)*100,2);
            }else{
                $result['pay_order_conversion_rate'] = 0.00;
            }

        }else{
            $result['views'] = '--';
            $result['views_percentage'] = 0;
            $result['orders'] = '--';
            $result['orders_percentage'] = 0;
            $result['order_pay'] = '--';
            $result['order_pay_percentage'] = 0;
            $result['order_payment'] = '--';
            $result['order_payment_percentage'] = 0;
            $result['pay_conversion_rate'] = 0.00;
            $result['order_conversion_rate'] = 0.00;
            $result['pay_order_conversion_rate'] = 0.00;
        }
        $data['errcode'] = 0;
        $data['data'] = $result;
        return Response::json($data);
    }
    //获取订单总数和交易总数
    public function getTrade(){
        $data['errcode'] = 0;
        //交易总额
        $trade_amount=$this->trade_stat_day_service->get_total_trades($this->merchant_id,date("Y-m-d H:i:s"));
        $data["data"]['trade_amount']=$trade_amount?$trade_amount:0;
        //订单总数
        $wheres = array();
        $wheres[] = array('column' => 'merchant_id', 'value' => $this->merchant_id, 'operator' => '=');
        $wheres[] = array('column' => 'created_time', 'value' => date("Y-m-d H:i:s"), 'operator' => '<=');
        $data["data"]['order_count']= $this->order_info->get_data_count($wheres);
        //用户数
        $wheres = array();
        $wheres[] = array('column' => 'merchant_id', 'value' => $this->merchant_id, 'operator' => '=');
        $wheres[] = array('column' => 'created_time', 'value' => date("Y-m-d H:i:s"), 'operator' => '<=');
        $data["data"]['member_count']= $this->member->get_data_count($wheres);
        return Response::json($data);
    }

    /**
     * 获取当前所有有效轮播图
     *
     * Author:zhangyu1@dodoca.com
     *
     */
    public function getAllPictures(){

        $query=IndexPictures::select('*');

        $query->where('is_delete','=',1);

        $count = $query->count();

        $query->orderby('sort','asc');

        $list = $query->get();

        return Response::json(['errcode'=>0,'_count'=>$count,'errmsg'=>'获取数据 成功','data'=>$list]);

    }

}

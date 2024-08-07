<?php

namespace App\Http\Controllers\Super;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Auth;
use App\Models\OrderSumDaily;
use App\Models\OrderInfo;
use App\Models\TradeStatsSum;
use App\Models\MerchantStaticsDaily;
use App\Models\DesignTemplate;
use App\Models\IndustryDaily;
use App\Models\MerchantTotalDaily;
use App\Models\XcxVersionDaily;
use App\Models\Merchant;
use App\Models\TradeStatDay;
use App\Services\TradeStatDayService;

class CountController extends Controller {
    protected $request;
    protected $params;
    protected $merchantdaily;
    protected $ordersumdaily;
    protected $templatedaily;
    protected $industrydaily;
    protected $tradestatssum;
    protected $tradedaily;
    protected $today = '';

    public function __construct(Request $request, MerchantStaticsDaily $merchantdaily,
                                DesignTemplate $templatedaily, IndustryDaily $industrydaily,
                                OrderSumDaily $ordersumdaily, TradeStatsSum $tradestatssum,
                                TradeStatDay $tradedaily,TradeStatDayService $tradeStatDayService) {
        $this->request = $request;
        $this->params = $request->all();
        $this->merchantdaily = $merchantdaily;
        $this->templatedaily = $templatedaily;
        $this->industrydaily = $industrydaily;
        $this->ordersumdaily = $ordersumdaily;
        $this->tradestatssum = $tradestatssum;
        $this->tradedaily = $tradedaily;
        $this->tradeStatDayService = $tradeStatDayService;
        $this->today = date('Ymd');
    }


    /**
     * 查询行业商户趋势
     */

    public function getIndustryDays(Request $request) {

        $industry_sign = config("industrysign");

        $limit_day = 20;

        $result = array();

        $total['total'] = 0;

        $total['total_today'] = 0;

        foreach ($industry_sign as $key => $val){

            $result[$key]['industry_name'] = $val['name'];

            if(!empty($this->params['startdate'])){

                $result[$key]['date'] = date("Y-m-d",strtotime($this->params['startdate']));

                $day_time = date("Ymd",strtotime($this->params['startdate']));

            }else{

                $result[$key]['date'] = date('Y-m-d', strtotime("-1 day"));

                $day_time = date('Ymd', strtotime("-1 day"));
            }

            $result[$key]['merchant_total'] = 0;

            $result[$key]['merchant_total_today'] = 0;

            $wheres = array(

                array('column' => 'industry_id', 'value' => $key, 'operator' => '='),

                array('column' => 'day_time', 'value' => $day_time, 'operator' => '=')
            );

            $industry = $this->industrydaily->get_data_list($wheres,'*',0, $limit_day);

            if($industry){

                $result[$key]['date'] = date('Y-m-d', strtotime($industry[0]['day_time']));

                $result[$key]['industry_name'] = $val['name'];

                $result[$key]['merchant_total'] = $industry[0]['merchant_total'];

                $result[$key]['merchant_total_today'] = $industry[0]['merchant_total_today'];

                $total['total'] =  $total['total'] + $result[$key]['merchant_total'];

                $total['total_today'] = $total['total_today'] + $result[$key]['merchant_total_today'];

            }

        }

        $data['errcode'] = 0;

        $data['data'] = array_values($result);

        $data['total'] = $total;

        return Response::json($data);

    }


    /**
     *  订单统计趋势(七天的，不包括今天)
     */

    public function getOrderCount(){

        $offset = isset($this->params['offset']) ? $this->params['offset'] : 0;

        $limit_day = isset($this->params['limit']) ? $this->params['limit'] : 10;

        if (!empty($this->params['startdate'])) {  //创建时间

            $start_day = strtotime($this->params['startdate']);

        }else{

            $start_day = strtotime($this->today) - 7*3600*24;
        }

        if (!empty($this->params['enddate'])) {  //结束时间

            $end_day =  strtotime($this->params['enddate']);

        }else{

            $end_day = strtotime($this->today) - 3600*24;
        }
        $data['count'] = ($end_day-$start_day)/(3600*24) + 1;
        $result = array();
        $num = $offset/10;
        $end = $end_day-($num + 1)*10*3600*24;
        if($end<$start_day){
            $end = $start_day-3600*24;
        }
        $start = $end_day-$num*10*3600*24;
        for ($day = $start; $day > $end; $day = $day - 3600 * 24) {

            $result[$day]['date'] = date('Y-m-d', $day);

            $result[$day]['total_day'] = 0;

            $result[$day]['success_num_day'] = 0;

            $result[$day]['send_num_day'] = 0;

            $result[$day]['total_pay_day'] = 0;

            $result[$day]['day_income'] = 0;     //收入

            $result[$day]['total_day_income'] = 0;      //交易

            $result[$day]['day_gmv'] = 0;      //trade_gmv

            $result[$day]['order_normal_day_gmv'] = 0;      //normal_gmv

            $result[$day]['order_wan_day_gmv'] = 0;      //wan_gmv

            $result[$day]['merchant_trade_num'] = 0;    //每日交易商家数


        }
        $wheres = array(

            array('column' => 'day_time', 'value' => date("Ymd",$end_day), 'operator' => '<=')
        );

        $order_data = $this->ordersumdaily->get_data_list($wheres,'day_time,total_day,total,success_num_day,success_num,send_num_day,send_num,total_pay_day,total_pay',$offset, $limit_day);

        foreach ($result as $k => $_result) {

            if ($order_data) {

                foreach ($order_data as $key => $_data) {

                    $strtotime = strtotime($_data['day_time']);

                    if ($k == $strtotime) {

                        $result[$k]['date'] = date('Y-m-d', $strtotime);

                        $result[$k]['total_day'] = $_data['total_day'] ? $_data['total_day'] : 0;

                        $result[$k]['success_num_day'] = $_data['success_num_day'] ? $_data['success_num_day'] : 0;

                        $result[$k]['send_num_day'] = $_data['send_num_day'] ? $_data['send_num_day'] : 0;

                        $result[$k]['total_pay_day'] = $_data['total_pay_day'] ? $_data['total_pay_day'] :0;
                    }
                }
            }
        }
        $wheres_order = array(
            array('column' => 'day_time', 'value' => date("Ymd",$end_day), 'operator' => '<='),
            array('column' => 'day_time', 'value' => date("Ymd",$start_day), 'operator' => '>=')
        );
        $order_sum = $this->ordersumdaily->get_data_list($wheres_order,'sum(total_day) as total_day,sum(success_num_day) as success_num_day,sum(send_num_day) as send_num_day,sum(total_pay_day) as total_pay_day');

        if($order_sum){
            $search['search_total_day'] = $order_sum[0]['total_day'];
            $search['search_success_num_day'] = $order_sum[0]['success_num_day'];
            $search['search_send_num_day'] = $order_sum[0]['send_num_day'];
            $search['search_total_pay_day'] = $order_sum[0]['total_pay_day'];
        }

        $all_data = $this->ordersumdaily->where('day_time',date("Ymd",strtotime("-1 day")))->get()->toArray();

        if(!empty($all_data[0])){

            $total['total'] = $all_data[0]['total'];

            $total['success_num'] = $all_data[0]['success_num'];

            $total['send_num'] = $all_data[0]['send_num'];

            $total['total_pay'] = $all_data[0]['total_pay'];
        }else{

            $total['total'] = 0;

            $total['success_num'] = 0;

            $total['send_num'] = 0;

            $total['total_pay'] = 0;
        }




        $wheres_trade = array(

            array('column' => 'day_time', 'value' => $end_day, 'operator' => '<=')
        );

        $trade_data = $this->tradestatssum->get_data_list($wheres_trade,'*',$offset, $limit_day);

        foreach ($result as $k => $_result) {

            if ($trade_data) {

                foreach ($trade_data as $key => $_data) {

                    $strtotime = $_data['day_time'];

                    if ($k == $strtotime) {

                        $result[$k]['date'] = date('Y-m-d', $strtotime);

                        $result[$k]['day_income'] = $_data['day_income'] ? $_data['day_income'] : 0;

                        $result[$k]['total_day_income'] = $_data['total_day'] ? $_data['total_day'] : 0;

                        $result[$k]['day_gmv'] = $_data['day_gmv'] ? $_data['day_gmv'] : 0;

                        $result[$k]['order_normal_day_gmv'] = $_data['order_normal_day_gmv'] ? $_data['order_normal_day_gmv'] : 0;

                        $result[$k]['order_wan_day_gmv'] = $_data['order_wan_day_gmv'] ? $_data['order_wan_day_gmv'] : 0;
                    }
                }
            }

            //每日交易商家数
            $merchant_trade_num = \DB::select("select count(DISTINCT merchant_id) as num from trade where created_time >= :starttime and created_time <= :endtime",[':endtime'=>$_result['date'].' 23:59:59',':starttime'=>$_result['date'].' 00:00:00']);

            $result[$k]['merchant_trade_num'] = $merchant_trade_num[0]->num;
        }

        $wheres_trade_sum = array(
            array('column' => 'day_time', 'value' => $end_day, 'operator' => '<='),
            array('column' => 'day_time', 'value' => $start_day, 'operator' => '>=')
        );
        $trade_sum = $this->tradestatssum->get_data_list($wheres_trade_sum,'sum(day_income) as day_income,sum(total_day) as total_day,sum(day_gmv) as day_gmv,sum(order_normal_day_gmv) as order_normal_day_gmv,sum(order_wan_day_gmv) as order_wan_day_gmv');

        if($trade_sum){
            $search['search_day_income'] = $trade_sum[0]['day_income'];
            $search['search_total_day_income'] = $trade_sum[0]['total_day'];
            $search['search_day_gmv'] = $trade_sum[0]['day_gmv'];
            $search['search_normal_day_gmv'] = $trade_sum[0]['order_normal_day_gmv'];
            $search['search_wan_day_gmv'] = $trade_sum[0]['order_wan_day_gmv'];

            //搜索日期内交易商家数
            $search_merchant_trade_num = \DB::select("select count(DISTINCT merchant_id) as num from trade where created_time >= :starttime and created_time <= :endtime",[':endtime'=>date('Y-m-d'." 23:59:59",$end_day),':starttime'=>date('Y-m-d',$start_day)]);

            $search['merchant_trade_num'] = $search_merchant_trade_num[0]->num;
        }

        $result  = array_values($result);

        $all_data = $this->tradestatssum->where('day_time',(strtotime($this->today)-3600 * 24))->get()->toArray();

        $total_merchant_trade_num = \DB::select("select count(DISTINCT merchant_id) as num from trade where created_time <= :endtime",[':endtime'=>date("Y-m-d 23:59:59", strtotime("-1 day"))]);

        $total['merchant_trade_num'] = $total_merchant_trade_num[0]->num;

        if(!empty($all_data)){

            $total['total_income'] = $all_data[0]['total_income'];

            $total['total_trans'] = $all_data[0]['total'];

            $total['total_gmv'] = $all_data[0]['total_gmv'];

            $total['total_normal_gmv'] = $all_data[0]['total_order_normal_gmv'];

            $total['total_wan_gmv'] = $all_data[0]['total_order_wan_gmv'];
        }else{

            $total['total_income'] = 0;

            $total['total_trans'] =0;

            $total['total_gmv'] =0;

            $total['total_normal_gmv'] =0;

            $total['total_wan_gmv'] =0;
        }


        $data['errcode'] = 0;

        $data['data'] = $result;

        $data['search_data'] = $search;

        $data['total_data'] = $total;

        return Response::json($data);
    }


    /**
     * 交易统计趋势
     */

    public function getTradeCount(){

        /*$type = $this->params['type'];

        if($type==2){

            $limit_day = 30;

        }elseif($type==3){

            $limit_day = 7;

        }else{

            $limit_day = 10;
        }*/

        if (!empty($this->params['startdate'])) {  //创建时间

            $start_day = strtotime($this->params['startdate']);

        }else{

            $start_day = strtotime($this->today) - 7*3600*24;
        }

        if (!empty($this->params['enddate'])) {  //结束时间

            $end_day =  strtotime($this->params['enddate']);

        }else{

            $end_day = strtotime($this->today) - 3600*24;
        }

        $limit_day = ($end_day-$start_day)/(3600*24);

        $result = array();

        /*$end_day = strtotime($this->today);

        $start_day = $end_day - $limit_day * 3600 * 24;*/

        for ($day = $start_day; $day <= $end_day; $day = $day + 3600 * 24) {

            $result[$day]['date'] = date('Y-m-d', $day);

            $result[$day]['day_income'] = 0;

            $result[$day]['total_day'] = 0;
        }
        $search['search_day_income'] = 0;

        $search['search_total_day'] = 0;

        $wheres = array(

            array('column' => 'day_time', 'value' => $end_day, 'operator' => '<=')
        );

        $trade_data = $this->tradestatssum->get_data_list($wheres,'*',0, $limit_day);

        foreach ($result as $k => $_result) {

            if ($trade_data) {

                foreach ($trade_data as $key => $_data) {

                    $strtotime = $_data['day_time'];

                    if ($k == $strtotime) {

                        $result[$k]['date'] = date('Y-m-d', $strtotime);

                        $result[$k]['day_income'] = $_data['day_income'] ? $_data['day_income'] : 0;

                        $search['search_day_income'] = $search['search_day_income'] + $result[$k]['day_income'];

                        $result[$k]['total_day'] = $_data['total_day'] ? $_data['total_day'] : 0;

                        $search['search_total_day'] = $search['search_total_day'] + $result[$k]['total_day'];

                    }
                }
            }
        }

        $result  = array_values($result);

        $all_data = $this->tradestatssum->where('day_time',(strtotime($this->today)-3600 * 24))->get()->toArray();

        if(!empty($all_data)){

            $total['total_income'] = $all_data[0]['total_income'];

            $total['total'] = $all_data[0]['total'];
        }else{

            $total['total_income'] = 0;

            $total['total'] =0;
        }


        $data['errcode'] = 0;

        $data['data'] = $result;

        $data['search_data'] = $search;

        $data['total_data'] = $total;

        return Response::json($data);
    }


    /**
     * 商家注册统计
     */

    public function getMerchantCount(){
        //创建时间
        if (!empty($this->params['startdate'])) {
            $start_day = strtotime($this->params['startdate']);
        }else{
            $start_day = strtotime($this->today) - 7*3600*24;
        }
        //结束时间
        if (!empty($this->params['enddate'])) {
            $end_day =  strtotime($this->params['enddate']);
        }else{
            $end_day = strtotime($this->today) - 3600*24;
        }

        $limit_day = !empty($this->params['limit'])?$this->params['limit']:10;
        $offset = !empty($this->params['offset'])?$this->params['offset']:0;

        $wheres = array(
            array('column' => 'day_time', 'value' => date("Ymd",$end_day), 'operator' => '<='),
            array('column' => 'day_time', 'value' => date("Ymd",$start_day), 'operator' => '>=')
        );
        //按日期区间统计数据
        $fields="day_time
        ,(today_register_pc+today_register_mobile+today_register_xcx+today_register_whb) as register_total
        ,today_register_pc
        ,today_register_mobile
        ,today_register_xcx
        ,today_register_whb
        ,(today_pc_register_login+today_mobile_register_login+today_xcx_register_login+today_whb_register_login) as register_login_total
        ,today_pc_register_login
        ,today_mobile_register_login
        ,today_xcx_register_login
        ,today_whb_register_login
        ,today_empower
        ,today_weapp_release_fail
        ,today_weapp_release_success
        ,today_empower_success
        ,today_weapp_release_lost
        ,today_weapp_emp_lost
        ,today_empower_success_free
        ,today_empower_success_charge
        ,today_release_success
        ,today_release_success_free
        ,today_release_success_charge
        ,seven_login_total
        ,month_login_total
        ,today_weapp_release_net
        ,today_weapp_release_net as net_increase_release_success";
        $merchant_data = $this->merchantdaily->get_data_list($wheres,$fields,$offset, $limit_day);
        $data = array();
        $data_search = array(
            'register_login_total'=>0,
            'today_pc_register_login'=>0,
            'today_mobile_register_login'=>0,
            'today_xcx_register_login'=>0,
            'today_whb_register_login'=>0
        );
        if($merchant_data){
            foreach ($merchant_data as $key=>$val){
                $merchant_data[$key]["day_time"]=date("Y-m-d",strtotime($val['day_time']));
                /**搜索日期内累计**/
                $data_search['register_login_total'] += $val['register_login_total'];
                $data_search['today_pc_register_login'] += $val['today_pc_register_login'];
                $data_search['today_mobile_register_login'] += $val['today_mobile_register_login'];
                $data_search['today_xcx_register_login'] += $val['today_xcx_register_login'];
                $data_search['today_whb_register_login'] += $val['today_whb_register_login'];
            }
        }
        //历史累计数据
        $fields="sum(today_register_pc+today_register_mobile+today_register_xcx+today_register_whb) as register_total
        ,sum(today_register_pc) as today_register_pc
        ,sum(today_register_mobile) as today_register_mobile
        ,sum(today_register_xcx) as today_register_xcx
        ,sum(today_register_whb) as today_register_whb
        ,sum(today_empower) as today_empower
        ,sum(today_weapp_release_fail) as today_weapp_release_fail
        ,sum(today_weapp_release_success) as today_weapp_release_success
        ,sum(today_empower_success) as today_empower_success
        ,sum(seven_login_total) as seven_login_total
        ,sum(today_weapp_emp_lost) as today_weapp_emp_lost
        ,sum(today_weapp_release_lost) as today_weapp_release_lost
        ,sum(month_login_total) as month_login_total";
        $search = $this->merchantdaily->get_data_list($wheres,$fields,0);
        $data['search_data'] = $search;
        $data['search_data'][0]['register_login_total'] = $data_search['register_login_total'];
        $data['search_data'][0]['today_pc_register_login'] = $data_search['today_pc_register_login'];
        $data['search_data'][0]['today_mobile_register_login'] = $data_search['today_mobile_register_login'];
        $data['search_data'][0]['today_xcx_register_login'] = $data_search['today_xcx_register_login'];
        $data['search_data'][0]['today_whb_register_login'] = $data_search['today_whb_register_login'];
        
        $where_alls=array(
            array('column' => 'day_time', 'value' => date("Ymd",strtotime("-1 day")), 'operator' => '<='),
        );
        $total = $this->merchantdaily->get_data_list($where_alls,$fields,0);
        $fields="total_register_login,total_pc_register_login,total_mobile_register_login,total_xcx_register_login,total_whb_register_login";
        $total_row = $this->merchantdaily->get_data_by_id(date("Ymd",strtotime("-1 day")));
        //
        $query=MerchantStaticsDaily::select('*');
        $query->where('day_time','>=',date("Ymd",$start_day));
        $query->where('day_time','<=',date("Ymd",$end_day));
        $count = $query->count();
        $data['errcode'] = 0;
        $data['_count'] = $count;
        $data['data'] = $merchant_data;
        $data['total_data'] = $total;
        $data['total_data'][0]['register_login_total'] =$total_row['total_register_login'];
        $data['total_data'][0]['today_pc_register_login'] =$total_row['total_pc_register_login'];
        $data['total_data'][0]['today_mobile_register_login'] =$total_row['total_mobile_register_login'];
        $data['total_data'][0]['today_xcx_register_login'] =$total_row['total_xcx_register_login'];
        $data['total_data'][0]['today_whb_register_login'] =$total_row['total_whb_register_login'];
        return Response::json($data);
    }

    //商家操作统计
    public function getMerchantTotal(){

        if (!empty($this->params['startdate'])) {  //创建时间

            $start_day = strtotime($this->params['startdate']);

        }else{

            $start_day = strtotime($this->today) - 7*3600*24;
        }

        if (!empty($this->params['enddate'])) {  //结束时间

            $end_day =  strtotime($this->params['enddate']);

        }else{

            $end_day = strtotime($this->today) - 3600*24;
        }

        $limit_day = !empty($this->params['limit'])?$this->params['limit']:10;
        $offset = !empty($this->params['offset'])?$this->params['offset']:0;

        $wheres = array(

            array('column' => 'day_time', 'value' => date("Ymd",$end_day), 'operator' => '<='),
            array('column' => 'day_time', 'value' => date("Ymd",$start_day), 'operator' => '>=')
        );
        $fields="day_time,editgoods,redecorated,`release`,release_success,empower_wechat,replenish_pc_total,replenish_mobile_total,replenish_xcx_total,replenish_whb_total,empower,weapp_release_fail,weapp_release_success,empower_success,weapp_emp_lost,weapp_release_lost,weapp_check,release_done,release_done_free,release_done_charge,empower_success_free,empower_success_charge";
        $merchant_data = MerchantTotalDaily::get_data_list($wheres,$fields,$offset, $limit_day);
        if($merchant_data){
            foreach ($merchant_data as $key=>$val){
                $merchant_data[$key]["day_time"]=date("Y-m-d",strtotime($val['day_time']));
                $merchant_data[$key]["empower_success"] = $merchant_data[$key]["empower_success_charge"] + $merchant_data[$key]["empower_success_free"];
                $merchant_data[$key]["release_done"] = $merchant_data[$key]["release_done_charge"] + $merchant_data[$key]["release_done_free"];

            }
        }

        $query=MerchantTotalDaily::select('*');
        $query->where('day_time','>=',date("Ymd",$start_day));
        $query->where('day_time','<=',date("Ymd",$end_day));
        $count = $query->count();
        $data['errcode'] = 0;
        $data['_count'] = $count;
        $data['data'] = $merchant_data;

        return Response::json($data);
    }

    //小程序版本统计
    public function getXcxVersionCount(){

        if (!empty($this->params['startdate'])) {  //创建时间

            $start_day = strtotime($this->params['startdate']);

        }else{

            $start_day = strtotime($this->today) - 7*3600*24;
        }

        if (!empty($this->params['enddate'])) {  //结束时间

            $end_day =  strtotime($this->params['enddate']);

        }else{

            $end_day = strtotime($this->today) - 3600*24;
        }

        $limit_day = !empty($this->params['limit'])?$this->params['limit']:10;
        $offset = !empty($this->params['offset'])?$this->params['offset']:0;

        $wheres = array(

            array('column' => 'day_time', 'value' => date("Ymd",$end_day), 'operator' => '<='),
            array('column' => 'day_time', 'value' => date("Ymd",$start_day), 'operator' => '>=')
        );
        $result = array();
        $total=array();
        $res=array();
        $merchant_data = XcxVersionDaily::get_data_list($wheres,'*',$offset*6,$limit_day*6);

        if($merchant_data)
        {
            foreach ($merchant_data as $key=>$val){
                $result[$val["day_time"]]['day_time'] = date("Y-m-d",strtotime($val["day_time"]));
                switch ($val['version_id']){
                    case 1:$result[$val["day_time"]]['version_id1']=$val['merchant_total_today'];break;
                    case 2:$result[$val["day_time"]]['version_id2']=$val['merchant_total_today'];break;
                    case 3:$result[$val["day_time"]]['version_id3']=$val['merchant_total_today'];break;
                    case 4:$result[$val["day_time"]]['version_id4']=$val['merchant_total_today'];break;
                    //case 5:$result[$val["day_time"]]['version_id5']=$val['merchant_total_today'];break;
                    //case 6:$result[$val["day_time"]]['version_id6']=$val['merchant_total_today'];break;
                }
                $ver5 = XcxVersionDaily::select('merchant_total_today')->where('day_time','=',date("Ymd",strtotime($val['day_time'])))->where('version_id',5)->get();
                $ver6 = XcxVersionDaily::select('merchant_total_today')->where('day_time','=',date("Ymd",strtotime($val['day_time'])))->where('version_id',6)->get();
                if(!empty($ver5[0])){
                    $result[$val["day_time"]]['version_id5'] = $ver5[0]['merchant_total_today'];
                }else{
                    $result[$val["day_time"]]['version_id5'] = 0;
                }

                if(!empty($ver6[0])){
                    $result[$val["day_time"]]['version_id6'] = $ver6[0]['merchant_total_today'];
                }else{
                    $result[$val["day_time"]]['version_id6'] = 0;
                }


            }
        }

        $query=XcxVersionDaily::query();
        foreach($wheres as $where) {
            $query->where($where['column'], $where['operator'], $where['value']);
        }
        $fields="version_id,sum(merchant_total_today) as merchant_total_today";
        $search=$query->select(\DB::raw($fields))->groupBy('version_id')->orderBy('day_time', 'desc')->get();
        if($search){
            foreach ($search as $key=>$val){
                switch ($val['version_id']){
                    case 1:$res['version_id1']=intval($val['merchant_total_today']);break;
                    case 2:$res['version_id2']=intval($val['merchant_total_today']);break;
                    case 3:$res['version_id3']=intval($val['merchant_total_today']);break;
                    case 4:$res['version_id4']=intval($val['merchant_total_today']);break;
                    case 5:$res['version_id5']=intval($val['merchant_total_today']);break;
                    case 6:$res['version_id6']=intval($val['merchant_total_today']);break;
                }
            }
            $res['version_id_total']=$res['version_id1']+$res['version_id2']+$res['version_id3']+$res['version_id4']+$res['version_id5']+$res['version_id6'];
        }
        $query=XcxVersionDaily::query();
        $query->where('day_time', '=', date("Ymd",strtotime("-1 day")));
        $fields="version_id,merchant_total";
        $alls=$query->select(\DB::raw($fields))->orderBy('day_time', 'desc')->get();
        if($alls){
            foreach ($alls as $key=>$val){
                switch ($val['version_id']){
                    case 1:$total['version_id1']=intval($val['merchant_total']);break;
                    case 2:$total['version_id2']=intval($val['merchant_total']);break;
                    case 3:$total['version_id3']=intval($val['merchant_total']);break;
                    case 4:$total['version_id4']=intval($val['merchant_total']);break;
                    case 5:$total['version_id5']=intval($val['merchant_total']);break;
                    case 6:$total['version_id6']=intval($val['merchant_total']);break;
                }
            }
            if($total){
                $total['version_id_total']=$total['version_id1']+$total['version_id2']+$total['version_id3']+$total['version_id4']+$total['version_id6'] + $total['version_id5'];
            }
        }

        $data['errcode'] = 0;

        if($result){
            foreach ($result as $key=>$val){
                $edition[] = $val['day_time'];
                $current_day_sum = XcxVersionDaily::select(\DB::raw('sum(merchant_total_today) as merchant_total_today'))->where('day_time','=',date("Ymd",strtotime($val['day_time'])))->get();
                $result[$key]['version_id_total']=$current_day_sum[0]['merchant_total_today'];
            }
            array_multisort($edition, SORT_DESC, $result);
        }

        $query=XcxVersionDaily::select('*');
        $query->where('day_time','>=',date("Ymd",$start_day));
        $query->where('day_time','<=',date("Ymd",$end_day));
        $count = $query->count();
        $data['_count'] = ceil($count/6);
        $data['data'] = $result;
        $data['search_data'] = $res;
        $data['total_data'] = $total;

        return Response::json($data);
    }

    /**
     * 模板使用统计(最近七天数据，不包含今天)
     */
    public function getTemplateDay(Request $request){

        $offset = isset($this->params['offset']) ? $this->params['offset'] : 0;

        $limit = isset($this->params['limit']) ? $this->params['limit'] : 10;

        $group_id = isset($this->params['group_id']) ? $this->params['group_id'] : '';

        $query = DesignTemplate::select('*');

        if(empty($group_id)){

            $data['count'] = $query->count();

            $design_temps = $query->skip($offset)->take($limit)->get()->toArray();

            $designs = $this->templatedaily->get()->toArray();

        }else{

            $query->whereRaw("FIND_IN_SET(".$group_id.",group_id)");

            $data['count'] = $query->count();

            $design_temps = $query->skip($offset)->take($limit)->get()->toArray();

            $designs = $this->templatedaily->whereRaw("FIND_IN_SET(".$group_id.",group_id)")->get()->toArray();

        }

        $sum = 0;

        if($design_temps){

            foreach($design_temps as $key=>$value){

                $template_data[$key]['use_count'] = 0;

                $wheres = array(

                    array('column' => 'id', 'value' => $value['id'], 'operator' => '='),

                );

                $industry = $this->templatedaily->get_data_list($wheres,'*',0, 10);

                if($industry){

                    $result[$key]['template_name'] = $industry[0]['name'];

                    $result[$key]['use_count'] = $industry[0]['use_count'];

                    $sum = $sum + $industry[0]['use_count'];

                }
            }
        }else{

            $result = '';
        }

        $total_sum = 0;

        if($designs){

            foreach($designs as $key=>$value){

                $template_data[$key]['use_count'] = 0;

                $wheres = array(

                    array('column' => 'id', 'value' => $value['id'], 'operator' => '='),

                );

                $industry = $this->templatedaily->get_data_list($wheres,'*',0, 10);

                if($industry){

                    $total_sum = $total_sum + $industry[0]['use_count'];

                }
            }
        }

        $data['errcode'] = 0;

        //$data['page_sum'] = $sum;

        $data['total_sum'] = $total_sum;

        $data['data'] = $result;

        return Response::json($data);
    }


    /**
     * 模板分类
     */

    public function getTemplateType(){

        $industry_sign = config("industrycat");

        $data['errcode'] ='';

        $data['data'] = $industry_sign;
        return Response::json($data);
    }

    /**
     *  Top100商家
     *
     */
    public function getTopMerchant(){

        $offset = isset($this->params['offset']) ? $this->params['offset'] : 0;

        $limit = isset($this->params['limit']) ? $this->params['limit'] : 10;

        $company = isset($this->params['company']) ? trim($this->params['company']) : '';

        if(!empty($this->params['startdate'])){

            $start_day = strtotime($this->params['startdate']);

        }
        if(!empty($this->params['enddate'])){

            $end_day = strtotime($this->params['enddate']);

        }
        $today = strtotime(date("Y-m-d 00:00:00")) - 3600*24;

        if(!empty($start_day) && !empty($end_day)){
            if($company){
                $list=\DB::select("SELECT sum(trade_daily.order_wan_day_gmv) as total_order_wan_gmv,sum(trade_daily.total_day) as total,trade_daily.merchant_id FROM applet_stats.trade_daily left join applet.merchant on merchant.id = trade_daily.merchant_id WHERE  trade_daily.day_time >= :starttime AND trade_daily.day_time <= :endtime AND merchant.company = :company GROUP BY trade_daily.merchant_id ORDER BY SUM(trade_daily.total_day) DESC LIMIT :limit OFFSET :offset",[':endtime'=>$end_day,':starttime'=>$start_day,':limit'=>$limit,':offset'=>$offset,':company' =>$company]);
                $count = \DB::select("SELECT count(*) as count from (SELECT trade_daily.id FROM applet_stats.trade_daily left join applet.merchant on merchant.id = trade_daily.merchant_id WHERE  trade_daily.day_time >= :starttime AND trade_daily.day_time <= :endtime AND merchant.company = :company GROUP BY trade_daily.merchant_id)  as t",[':endtime'=>$end_day,':starttime'=>$start_day,':company' =>$company]);
                $count = $count[0]->count;
            }else{
                $list=\DB::select("SELECT sum(order_wan_day_gmv) as total_order_wan_gmv,sum(total_day) as total,merchant_id FROM applet_stats.trade_daily WHERE  day_time >= :starttime AND day_time <= :endtime GROUP BY merchant_id ORDER BY SUM(total_day) DESC LIMIT :limit OFFSET :offset",[':endtime'=>$end_day,':starttime'=>$start_day,':limit'=>$limit,':offset'=>$offset]);
                $count = \DB::select("SELECT count(*) as count from (SELECT trade_daily.id FROM applet_stats.trade_daily WHERE  day_time >= :starttime AND day_time <= :endtime GROUP BY merchant_id)  as t",[':endtime'=>$end_day,':starttime'=>$start_day]);
                $count = $count[0]->count;
            }
        }else{
            if($company){
                $list=\DB::select("SELECT trade_daily.total_order_wan_gmv,trade_daily.total,trade_daily.merchant_id FROM applet_stats.trade_daily left join applet.merchant on merchant.id = trade_daily.merchant_id WHERE  trade_daily.day_time = :starttime  and merchant.company = :company ORDER BY total DESC LIMIT :limit OFFSET :offset",[':starttime'=>$today,':limit'=>$limit,':offset'=>$offset,':company' =>$company]);
                $count = \DB::select("SELECT count(*) as count FROM applet_stats.trade_daily left join applet.merchant on merchant.id = trade_daily.merchant_id WHERE  trade_daily.day_time = :starttime  and merchant.company = :company",[':starttime'=>$today,':company' =>$company]);
                $count = $count[0]->count;
            }else{
                // $list=\DB::select("SELECT total_order_wan_gmv,total,merchant_id FROM applet_stats.trade_daily WHERE  day_time = :starttime  ORDER BY total DESC LIMIT :limit OFFSET :offset",[':starttime'=>$today,':limit'=>$limit,':offset'=>$offset]);
                // $count = \DB::select("SELECT count(*) as count FROM applet_stats.trade_daily WHERE  day_time = :starttime",[':starttime'=>$today]);
                $query = TradeStatDay::select('total_order_wan_gmv','total','merchant_id')->where('day_time',$today); 
                $count= $query->count();
                $list = $query->orderBy('total','DESC')->skip($offset)->take($limit)->get();
            }

        }

        foreach ($list as $key=>$val){

            $merchant_name = Merchant::select('company')->where('id','=',$val->merchant_id)->get()->toArray();

            $all_orders = OrderInfo::where('merchant_id',$val->merchant_id)->where('is_valid',1)->count();  //所有订单数

            $all_orders_pay = OrderInfo::where('merchant_id',$val->merchant_id)->where('is_valid',1)->where('pay_status',1)->count();  //所有支付订单数订单数

            $list[$key]->all_orders = $all_orders;

            $list[$key]->all_orders_pay = $all_orders_pay;

            if($all_orders > 0){

                $ratio = $all_orders_pay * 100 / $all_orders;

                $list[$key]->radio = round($ratio,2);
            }else{

                $list[$key]->radio = 0;
            }

            $list[$key]->merchant_name = $merchant_name[0]['company'];

        }

        if(!empty($list)){

            $data['_count'] = $count;
        }else{

            $data['_count'] = 0;
        }


        $data['errcode'] = 0;

        $data['errmsg'] = '获取交易数据 成功';

        $data['data'] = $list;

        return Response::json($data);


    }


    /**
     * 商家每日详情
     *
     */

    public function getMerchantDetail(Request $request){

        $offset = isset($this->params['offset']) ? $this->params['offset'] : 0;

        $limit = isset($this->params['limit']) ? $this->params['limit'] : 10;

        if(!empty($this->params['startdate'])){

            $start_day = strtotime($this->params['startdate']);

        }else{

            $start_day = strtotime(date('Y-m-d 00:00:00'))-60*3600*24;
        }

        if(!empty($this->params['enddate'])){

            $end_day = strtotime($this->params['enddate'])-3600*24+1;

        }else{

            $end_day = strtotime(date('Y-m-d 00:00:00'))-3600*24;
        }

        $merchant_id = $this->params['merchant_id'];

        if(empty($merchant_id)){

            return Response::json(['errcode'=>10001,'errmsg'=>'参数错误']);
        }

        $merchant_name = Merchant::select('company')->where('id','=',$merchant_id)->get()->toArray();

        $result = array();

        $data['_count'] = ($end_day-$start_day)/(3600*24) + 1;

        $num = $offset/10;

        $end = $end_day-($num + 1)*10*3600*24;

        if($end<$start_day){

            $end = $start_day-3600*24;
        }

        $start = $end_day-$num*10*3600*24;

        for ($day = $start; $day > $end; $day = $day - 3600 * 24) {

            $result[$day]['date'] = date('Y-m-d', $day);

            $result[$day]['total_day'] = 0;

            $result[$day]['order_wan_day_gmv'] = 0;

            $result[$day]['merchant_name'] = $merchant_name[0]['company'];

        }
        $wheres = array(

            array('column' => 'day_time', 'value' => $end_day, 'operator' => '<='),

            array('column' => 'merchant_id', 'value' => $merchant_id, 'operator' => '='),
        );

        $trade_data = $this->tradedaily->get_data_list($wheres,'day_time,total_day,order_wan_day_gmv',$offset, $limit);

        foreach ($result as $k => $_result) {

            if ($trade_data) {

                foreach ($trade_data as $key => $_data) {

                    if ($_data['day_time'] == $k) {

                        $result[$k]['date'] = date('Y-m-d', $k);

                        $result[$k]['total_day'] = $_data['total_day'] ? $_data['total_day'] : 0;

                        $result[$k]['order_wan_day_gmv'] = $_data['order_wan_day_gmv'] ? $_data['order_wan_day_gmv'] : 0;
                    }
                }
            }
        }

        $result  = array_values($result);

        $data['errcode'] = 0;

        $data['errmsg'] = '每日数据查询 成功';

        $data['data'] = $result;

        return Response::json($data);

    }





}
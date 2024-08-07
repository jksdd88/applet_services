<?php

/**
 * 直播账户管理
 * @author wangshen@dodoca.com
 * @cdate 2018-4-27
 * 
 */
namespace App\Http\Controllers\Admin\Live;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Http\Requests;
use App\Http\Controllers\Controller;

use App\Models\Merchant;
use App\Models\MerchantSetting;
use App\Models\MerchantBalance;
use App\Models\LiveBalance;
use App\Models\MerchantOrder;

use App\Services\MerchantService;
use App\Services\AliPayPcService;

use App\Utils\CommonApi;

class LiveAccountController extends Controller {

    
    public function __construct(MerchantService $merchantService,AliPayPcService $aliPayPcService){
		$this->merchant_id = isset(Auth::user()->merchant_id) ? Auth::user()->merchant_id : 0;
        //$this->merchant_id = 2;
        
        //商户服务类
        $this->merchantService = $merchantService;
        
        //支付宝电脑网站支付服务类
        $this->aliPayPcService = $aliPayPcService;
    }
        
    
    
    /**
     * 获取直播账户余额信息
     * @author wangshen@dodoca.com
     * @cdate 2018-4-27
     *
     */
    public function getAccountInfo(Request $request){
        
        //参数
        $params = $request->all();
        
        $merchant_id = $this->merchant_id;//商户id
        
        if(!$merchant_id){
            return Response::json(['errcode' => 99001,'errmsg' => '商户ID不存在']);
        }
        
        //商户表信息
        $merchant_info = Merchant::get_data_by_id($merchant_id);
        
        if(!$merchant_info){
            return Response::json(['errcode' => 99001,'errmsg' => '商户信息不存在']);
        }
        
        //点点币余额
        $balance = isset($merchant_info['balance']) ? $merchant_info['balance'] : 0;
        $balance = (int)$balance == $balance ? (int)$balance : $balance;
        
        //商户设置表信息
        $merchant_setting_info = MerchantSetting::get_data_by_id($merchant_id);
        
        if(!$merchant_setting_info){
            return Response::json(['errcode' => 99001,'errmsg' => '商户设置信息不存在']);
        }
        
        //直播包余额
        $live_balance = isset($merchant_setting_info['live_balance']) ? $merchant_setting_info['live_balance'] : 0;
        
        //录播包余额
        $live_record = isset($merchant_setting_info['live_record']) ? $merchant_setting_info['live_record'] : 0;
        
        //云存储余额
        $live_store = isset($merchant_setting_info['live_store']) ? $merchant_setting_info['live_store'] : 0;
        $live_store = (int)$live_store == $live_store ? (int)$live_store : $live_store;
        
        
        //录播有效期，到月底时间
        $next_month = date("Y-m-01", strtotime("+1 Months"));
        $now = date("Y-m-d");
        $surplus_day = self::getChaBetweenTwoDate($next_month, $now);
        
        
        
        //资源使用情况
        
        //已购买直播包（包括购买的和赠送的）
        $buy_live_balance = LiveBalance::get_sum_by_type(1, $merchant_id);
        $buy_live_balance = $buy_live_balance ? $buy_live_balance : 0;
        //已赠送直播包
        $give_live_balance = LiveBalance::get_sum_by_type(11, $merchant_id);
        $give_live_balance = $give_live_balance ? $give_live_balance : 0;
        //实际已购买直播包
        $buy_live_balance = $buy_live_balance + $give_live_balance;
        
        
        
        //已购买录播包
        $buy_live_record = LiveBalance::get_sum_by_type(3, $merchant_id);
        $buy_live_record = $buy_live_record ? $buy_live_record : 0;
        
        
        
        //已购买云存储（包括购买的和赠送的）
        $buy_live_store = LiveBalance::get_sum_by_type(4, $merchant_id);
        $buy_live_store = $buy_live_store ? $buy_live_store : 0;
        //已赠送云存储
        $give_live_store = LiveBalance::get_sum_by_type(12, $merchant_id);
        $give_live_store = $give_live_store ? $give_live_store : 0;
        //实际已购买云存储
        $buy_live_store = $buy_live_store + $give_live_store;
        
        
        
        
        //直播资源数据
        $can_live_balance = $live_balance;//可使用直播包
        $use_live_balance = $buy_live_balance - $can_live_balance;//已使用直播包
        
        
        //录播资源数据
        $can_live_record = $live_record;//可使用录播包
        $lose_live_record = LiveBalance::get_sum_by_type(5, $merchant_id);//已失效录播包
        $lose_live_record = $lose_live_record ? abs($lose_live_record) : 0;
        $use_live_record = $buy_live_record - $can_live_record - $lose_live_record;//已使用录播包
        
        
        //云存储资源数据
        $can_live_store = $live_store;//可使用云存储
        $use_live_store = $buy_live_store - $can_live_store;//已使用云存储
        
        $use_live_store = substr(sprintf("%.3f",$use_live_store),0,-1);
        $use_live_store = (float)$use_live_store;
        
        
        $data = [
            'balance' => $balance,                      //点点币余额
            'live_balance' => $live_balance,            //直播包余额
            'live_record' => $live_record,              //录播包余额
            'live_store' => $live_store,                //云存储余额
            'surplus_day' => $surplus_day,              //录播人次剩余天数
            
            'buy_live_balance' => (int)$buy_live_balance,    //已购买直播包（包括购买的和赠送的）
            'buy_live_record' => (int)$buy_live_record,      //已购买录播包
            'buy_live_store' => $buy_live_store,        //已购买云存储（包括购买的和赠送的）
            
            'can_live_balance' => (int)$can_live_balance,    //可使用直播包
            'use_live_balance' => (int)$use_live_balance,    //已使用直播包
            
            'can_live_record' => (int)$can_live_record,      //可使用录播包
            'lose_live_record' => (int)$lose_live_record,    //已失效录播包
            'use_live_record' => (int)$use_live_record,      //已使用录播包
            
            'can_live_store' => $can_live_store,        //可使用云存储
            'use_live_store' => $use_live_store,        //已使用云存储
        ];
        
        return Response::json(['errcode' => 0,'errmsg' => '获取信息成功','data' => $data]);
    }
    
    /**
     * 收支明细
     * @author wangshen@dodoca.com
     * @cdate 2018-4-27
     *
     */
    public function getMerchantBalanceList(Request $request){
    
        //参数
        $params = $request->all();
    
        $merchant_id = $this->merchant_id;//商户id
    
        if(!$merchant_id){
            return Response::json(['errcode' => 99001,'errmsg' => '商户ID不存在']);
        }
    
        
        //点点币变化类型配置
        $merchant_balance_type = config('varconfig.merchant_balance_type');
        
        
        //分页参数
        $page = isset($params['page']) ? (int)$params['page'] : 1;//页码
        $pagesize = isset($params['pagesize']) ? (int)$params['pagesize'] : 10;//每页条数
        
        //偏移量/每页数量
        $offset = ($page - 1) * $pagesize;
        $limit = $pagesize;
        
        
        //条件
        $wheres = [];
        $wheres[] = ['column' => 'merchant_id','operator' => '=','value' => $merchant_id];
        
        
        //开始时间
        if(isset($params['start_time']) && !empty($params['start_time'])) {
            $wheres[] = ['column' => 'created_time','operator' => '>=','value' => date('Y-m-d 00:00:00', strtotime($params['start_time']))];
        }
        //结束时间
        if(isset($params['end_time']) && !empty($params['end_time'])) {
            $wheres[] = ['column' => 'created_time','operator' => '<=','value' => date('Y-m-d 23:59:59', strtotime($params['end_time']))];
        }
        
        
        //排序
        $sort_type = isset($params['sort_type']) ? (int)$params['sort_type'] : 2;//排序类型：1->ASC，2->DESC
        $sort = $sort_type == 1 ? 'ASC' : 'DESC';
        
        
        //查询字段
        $fields = 'merchant_id,sum,balance,type,memo,created_time,balance_sn';
        
        //数量
        $_count = MerchantBalance::get_data_count($wheres);
        
        //列表数据
        $merchant_balance_list = MerchantBalance::get_data_list($wheres,$fields,$offset,$limit,'created_time',$sort);
        
        
        if($merchant_balance_list){
            foreach($merchant_balance_list as $key => $val){
                
                //类型文案
                $merchant_balance_list[$key]['type_name'] = $merchant_balance_type[$val['type']];
                
                //存入、支出
                if($val['sum'] >= 0){
                    $merchant_balance_list[$key]['balance_type'] = '存入';
                }else{
                    $merchant_balance_list[$key]['balance_type'] = '支出';
                }
                
                //点点币数量
                $merchant_balance_list[$key]['balance_sum'] = abs($val['sum']);
                
                //备注
                $merchant_balance_list[$key]['memo'] = $merchant_balance_list[$key]['memo'] ? $merchant_balance_list[$key]['memo'] : '--';
                
                //交易方式
                $merchant_balance_list[$key]['trade_type'] = '点点币';
        
            }
        }
        
        
        $data['_count'] = $_count;
        $data['data'] = $merchant_balance_list;
        
        return ['errcode' => 0,'errmsg' => '获取信息成功','data' => $data];
    }
    
    
    /**
     * 购买直播余额
     * @author wangshen@dodoca.com
     * @cdate 2018-4-28
     *
     */
    public function buyLive(Request $request){
    
        //参数
        $params = $request->all();
    
        $merchant_id = $this->merchant_id;//商户id
    
        if(!$merchant_id){
            return Response::json(['errcode' => 99001,'errmsg' => '商户ID不存在']);
        }
        
        //购买直播余额配置
        $live_buy_config = config('config.live_buy');
        
        //购买内容：1->直播包，2->录播包，3->云存储
        $buy_type = isset($params['buy_type']) ? (int)$params['buy_type'] : 0;
        
        if(!in_array($buy_type,[1,2,3])){
            return Response::json(['errcode' => 310002,'errmsg' => '购买参数不正确']);
        }
        
        //购买数量
        $buy_num = isset($params['buy_num']) ? (int)$params['buy_num'] : 0;
        
        //购买数量判断
        if($buy_type == 1){    //直播包
            if(!array_key_exists($buy_num, $live_buy_config['live_bag'])){
                return Response::json(['errcode' => 310002,'errmsg' => '购买参数不正确']);
            }
            
            //计算需要消耗的点点币
            $cost_balance = $live_buy_config['live_bag'][$buy_num];
            
            //类型区分
            $live_balance_data_ctype = 1;
            $live_balance_data_type = 1;
            
            $merchant_balance_data_type = 2;
            
            //$memo = '花费 '.$cost_balance.' 点点币购买直播包 '.$buy_num.' 个';
            
            
            
            
            //购买直播包，额外赠送云存储处理
            
            //赠送类型区分
            $give_live_balance_data_ctype = 3;
            $give_live_balance_data_type = 12;
            
            //赠送的云存储数量
            $give_live_store = $live_buy_config['live_store'][$buy_num];
            
            $give_memo = '花费 '.$cost_balance.' 点点币购买直播包 '.$buy_num.' 个（额外赠送云存储'.$give_live_store.'G）';
            
            
            $memo = $give_memo;
            
            
            
        }elseif($buy_type == 2){    //录播包
            if(!array_key_exists($buy_num, $live_buy_config['record_bag'])){
                return Response::json(['errcode' => 310002,'errmsg' => '购买参数不正确']);
            }
            
            //计算需要消耗的点点币
            $cost_balance = $live_buy_config['record_bag'][$buy_num];
            
            //类型区分
            $live_balance_data_ctype = 2;
            $live_balance_data_type = 3;
            
            $merchant_balance_data_type = 3;
            
            $memo = '花费 '.$cost_balance.' 点点币购买录播包 '.$buy_num.' 次';
            
            
        }elseif($buy_type == 3){    //云存储
            if($buy_num <= 0){
                return Response::json(['errcode' => 310002,'errmsg' => '购买参数不正确']);
            }
            
            //计算需要消耗的点点币
            $cost_balance = $buy_num;
            
            //类型区分
            $live_balance_data_ctype = 3;
            $live_balance_data_type = 4;
            
            $merchant_balance_data_type = 4;
            
            $memo = '花费 '.$cost_balance.' 点点币购买云存储 '.$buy_num.' G';
            
            
        }
        
        //商户表信息
        $merchant_info = Merchant::get_data_by_id($merchant_id);
        
        if(!$merchant_info){
            return Response::json(['errcode' => 99001,'errmsg' => '商户信息不存在']);
        }
        
        //点点币余额
        $balance = isset($merchant_info['balance']) ? $merchant_info['balance'] : 0;
        
        //点点币判断
        if($cost_balance < 0){
            return Response::json(['errcode' => 310002,'errmsg' => '购买参数不正确']);
        }
        
        if($cost_balance > $balance){
            return Response::json(['errcode' => 310003,'errmsg' => '点点币余额不足']);
        }
        
        
        //事务
        DB::beginTransaction();
        try{
            
            //扣除点点币、记录点点币余额变化信息
            //调用商家余额变动api
            $merchant_balance_data = [
                'merchant_id' => $merchant_id,                   //商户id
                'type'	      => $merchant_balance_data_type,    //变动类型：配置config/varconfig.php
                'sum'		  => -1 * $cost_balance,		     //变动金额
                'memo'		  => $memo,	                         //备注
            ];
            
            
            $merchantbalance_rs = $this->merchantService->changeMerchantBalance($merchant_balance_data);
            
            if(!$merchantbalance_rs){
                
                throw new \Exception('修改商家余额失败');
            }else{
                
                //增加直播余额、记录直播余额变化信息
                //调用商家直播余额变动api
                $live_balance_data = [
                    'merchant_id' => $merchant_id,                //商户id
                    'ctype'       => $live_balance_data_ctype,    //余额类型：1->直播包，2->录播包，3->云存储
                    'type'	      => $live_balance_data_type,	  //变动类型：配置config/varconfig.php
                    'sum'		  => $buy_num,		              //变动金额
                    'memo'		  => $memo,	                      //备注
                ];
                
                
                $livebalance_rs = $this->merchantService->changeLiveMoney($live_balance_data);
                
                if(!$livebalance_rs){
                
                    throw new \Exception('修改直播余额失败');
                }else{
                    
                    //购买直播包赠送云存储
                    if($buy_type == 1){
                        //增加直播余额、记录直播余额变化信息
                        //调用商家直播余额变动api
                        $give_live_balance_data = [
                            'merchant_id' => $merchant_id,                     //商户id
                            'ctype'       => $give_live_balance_data_ctype,    //余额类型：1->直播包，2->录播包，3->云存储
                            'type'	      => $give_live_balance_data_type,	   //变动类型：配置config/varconfig.php
                            'sum'		  => $give_live_store,		           //变动金额
                            'memo'		  => $give_memo,	                   //备注
                        ];
                        
                        
                        $give_livebalance_rs = $this->merchantService->changeLiveMoney($give_live_balance_data);
                        
                        if(!$give_livebalance_rs){
                        
                            throw new \Exception('修改直播余额失败');
                        }
                        
                    }
                    
                }
                
            }
            
            DB::commit();
            return Response::json(['errcode' => 0,'errmsg' => '购买成功']);
            
        }catch (\Exception $e){
            
            DB::rollBack();
            return Response::json(['errcode' => 310004,'errmsg' => $e->getMessage()]);
        }
        
        
    }
    
    
    
    
    public static function getChaBetweenTwoDate($date1,$date2){
        $Date_List_a1=explode("-",$date1);
        $Date_List_a2=explode("-",$date2);
        $d1=mktime(0,0,0,$Date_List_a1[1],$Date_List_a1[2],$Date_List_a1[0]);
        $d2=mktime(0,0,0,$Date_List_a2[1],$Date_List_a2[2],$Date_List_a2[0]);
        $Days=round(($d1-$d2)/3600/24);
        if($Days<0){
            $Days=1;
        }
        return $Days;
    }
    
    
    
    
    /**
     * 充值点点币
     * @author wangshen@dodoca.com
     * @cdate 2018-5-7
     *
     */
    public function rechargeAmount(Request $request){
    
        //参数
        $params = $request->all();
        
        //充值数量
        if(ENV('APP_ENV') == 'production'){ //线上（只能整数）
            $recharge_num = isset($params['recharge_num']) ? $params['recharge_num'] : 0;
            
            if((int)$params['recharge_num'] != $params['recharge_num']){
                return Response::json(['errcode' => 310005,'errmsg' => '购买数量不正确']);
            }
            
            if($recharge_num <= 0 || $recharge_num > 10000){
                return Response::json(['errcode' => 310005,'errmsg' => '购买数量不正确']);
            }
        }else{  //测试（可以小数）
            $recharge_num = isset($params['recharge_num']) ? $params['recharge_num'] : 0;
            
            if($recharge_num <= 0){
                return Response::json(['errcode' => 310005,'errmsg' => '购买数量不正确']);
            }
            
            if (!preg_match('/^[0-9]+(.[0-9]{1,2})?$/', $recharge_num)) {
                return ['errcode' => 310005,'errmsg' => '购买数量不正确'];
            }
             
            $recharge_num = (float)sprintf('%0.2f',$recharge_num);
        }
        
        
        
        //支付金额
        $amount = $recharge_num;
        
        $merchant_id = $this->merchant_id;//商户id
    
        if(!$merchant_id){
            return Response::json(['errcode' => 99001,'errmsg' => '商户ID不存在']);
        }
        
        
        //生成订单
        $order_sn = $this->aliPayPcService->get_order_sn(); //订单号
        $order_title = $recharge_num.'点点币'; //订单名称
        $remark = '花费'.$amount.'元购买'.$recharge_num.'点点币';   //订单说明
        
        $merchant_order_data = [
            'merchant_id' => $merchant_id,
            'order_sn' => $order_sn,
            'order_title' => $order_title,
            'amount' => $amount,
            'remark' => $remark,
        ];
        
        $order_rs = MerchantOrder::insert_data($merchant_order_data);
        if(!$order_rs){
            return Response::json(['errcode' => 310006,'errmsg' => '订单创建失败']);
        }
        
        //订单id
        $merchant_order_data['order_id'] = $order_rs;
        
        //支付接口
        $rs = $this->aliPayPcService->doPay($merchant_order_data);
        
        
        if(isset($rs['errcode']) && $rs['errcode'] == 310006){
            return Response::json(['errcode' => $rs['errcode'],'errmsg' => $rs['errmsg']]);
        }
        
        
        return Response::json(['errcode' => 0,'errmsg' => '获取支付信息成功','data' => $rs]);
    }
    
    
    
    /**
     * 代理商给用户充值点点币
     * @author wangshen@dodoca.com
     * @cdate 2018-5-15
     *
     */
    public function agentAddBalance(Request $request){
    
        //参数
        $params = $request->all();
        
        
        //记录日志log-----
        $wlog_data = [
            'custom'      => 'liveaccount_agentaddbalance',        //标识字段数据
            'content'     => '记录原力系统代理商给用户充值数据:data->'.json_encode($params,JSON_UNESCAPED_UNICODE), //日志内容
        ];
        $r = CommonApi::wlog($wlog_data);
        //记录日志log-----
        
        
        //解密、验证签名
        $verify_sign = $this->agentsign($params);
        if($verify_sign['errcode'] != 0){
            return Response::json(['errcode' => $verify_sign['errcode'],'errmsg' => $verify_sign['errmsg']]);
        }
        $params = $verify_sign['data'];
        
        
        //商户id
        $merchant_id = isset($params['merchant_id']) ? (int)$params['merchant_id'] : 0;
        
        if(!$merchant_id){
            return Response::json(['errcode' => 99001,'errmsg' => '商户ID不存在']);
        }
        
        //商户表信息
        $merchant_info = Merchant::get_data_by_id($merchant_id);
        
        if(!$merchant_info){
            return Response::json(['errcode' => 99001,'errmsg' => '商户信息不存在']);
        }
        
        //点点币数量
        $recharge_num = isset($params['recharge_num']) ? $params['recharge_num'] : 0;
        
        if((int)$params['recharge_num'] != $params['recharge_num']){
            return Response::json(['errcode' => 310005,'errmsg' => '购买数量不正确']);
        }
        
        if($recharge_num <= 0 || $recharge_num > 9999999){
            return Response::json(['errcode' => 310005,'errmsg' => '购买数量不正确']);
        }
        
        //代理商id
        $agent_id = isset($params['agent_id']) ? (int)$params['agent_id'] : 0;
        //代理商公司名
        $agent_company_name = isset($params['agent_company_name']) ? $params['agent_company_name'] : '';
        
        
        //增加点点币、记录点点币余额变化信息
        //调用商家余额变动api
        $merchant_balance_data_sum = $recharge_num;//点点币变化值
        $memo = '代理商 '.$agent_company_name.' 给商家 '.$merchant_info['company'].' 充值点点币'.$recharge_num.'个';
        
        $merchant_balance_data = [
            'merchant_id' => $merchant_id,                   //商户id
            'type'	      => 5,                              //变动类型：配置config/varconfig.php
            'sum'		  => $merchant_balance_data_sum,	 //变动金额
            'memo'		  => $memo,	                         //备注
            'type_id'     => $agent_id,                      //原力系统的代理商id
        ];
        
        $MerchantService = new MerchantService;
        $merchantbalance_rs = $MerchantService->changeMerchantBalance($merchant_balance_data);
        
    
        if($merchantbalance_rs){
            return Response::json(['errcode' => 0,'errmsg' => '成功']);
        }else{
            return Response::json(['errcode' => 99001,'errmsg' => '失败']);
        }
    }
    
    
    /**
     * 代理商获取用户的点点币数量
     * @author wangshen@dodoca.com
     * @cdate 2018-5-16
     *
     */
    public function agentGetBalance(Request $request){
    
        //参数
        $params = $request->all();
        
        //解密、验证签名
        $verify_sign = $this->agentsign($params);
        if($verify_sign['errcode'] != 0){
            return Response::json(['errcode' => $verify_sign['errcode'],'errmsg' => $verify_sign['errmsg']]);
        }
        $params = $verify_sign['data'];
    
        //商户id
        $merchant_id = isset($params['merchant_id']) ? (int)$params['merchant_id'] : 0;
    
        if(!$merchant_id){
            return Response::json(['errcode' => 99001,'errmsg' => '商户ID不存在']);
        }
    
        //商户表信息
        $merchant_info = Merchant::get_data_by_id($merchant_id);
    
        if(!$merchant_info){
            return Response::json(['errcode' => 99001,'errmsg' => '商户信息不存在']);
        }
    
        if(isset($merchant_info['balance'])){
            return Response::json(['errcode' => 0,'errmsg' => '获取用户的点点币数量成功','data'=>$merchant_info['balance']]);
        }else{
            return Response::json(['errcode' => 99001,'errmsg' => '获取用户的点点币数量失败']);
        }
    }
    
    
    
    
    
    /**
     * 解密、验证签名
     * @author wangshen@dodoca.com
     * @cdate 2018-5-17
     *
     */
    public function agentsign($data){
        
        $key = '81c0350e8118b01a02a283129c79419f';
        
        if(!isset($data['data']) || empty($data['data'])){
            return array('errcode' => 2,'errmsg' => '缺少加密数据');
        }
        
        //解密
        $data = (array)json_decode(encrypt(base64_decode($data['data']),'D',$key));
        if(empty($data)){
            return array('errcode' => 2,'errmsg' => '加密数据错误');
        }
        
        
        if(!isset($data['nonce_str']) || !$data['nonce_str']) {
            return array('errcode' => 2,'errmsg' => '缺失nonce_str参数');
        }
        if(!isset($data['timestamp']) || !$data['timestamp']) {
            return array('errcode' => 2,'errmsg' => '缺失timestamp参数');
        }
        if(!isset($data['sign']) || !$data['sign']) {
            return array('errcode' => 2,'errmsg' => '缺失sign参数');
        }
        if(strlen($data['nonce_str'])>32){
            return array('errcode' => 2,'errmsg' => 'nonce_str参数长度错误');
        }
        if($data['timestamp']>2147483648 || !is_numeric($data['timestamp'])){
            return array('errcode' => 2,'errmsg' => 'timestamp参数错误');
        }
        
        
        //验证签名
        $singarr = $data;
        unset($singarr['sign']);
        $sign = $this->appletsign($singarr);
        if($sign != $data['sign']) {
            return array('errcode' => 2,'errmsg' => '签名错误');
        }
        
        //判断时间戳
        if((time() - 60) > $data['timestamp']){
            return array('errcode' => 2,'errmsg' => '签名过期');
        }
        
        return array('errcode' => 0,'errmsg' => '验证成功','data' => $data);
    }
    
    //签名
    public function appletsign($data) {
        $signarr = array();
        ksort($data);
        foreach($data as $key => $v) {
            $signarr[] = $key.'='.$v;
        }
        return strtoupper(md5(implode('&',$signarr)));
    }
}

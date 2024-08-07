<?php
/**
 * 支付宝电脑网站支付服务类
 * @author wangshen@dodoca.com
 * @cdate 2018-5-7
 * 
 */
namespace App\Services;

use App\Utils\Alipaypc\AlipayTradePagePayContentBuilder;
use App\Utils\Alipaypc\AlipayTradeQueryContentBuilder;
use App\Utils\Alipaypc\AlipayTradeService;

use App\Models\MerchantOrder;
use App\Models\MerchantPaymentApply;

use App\Utils\CommonApi;

class AliPayPcService {
    
    /**
     * 支付
     * @author wangshen@dodoca.com
	 * @cdate 2018-5-7
     */
    public function doPay($data){
        
        //生成支付申请数据
        $payment_sn = self::get_payment_sn();
        $pdata = [
            'order_id'	=>	$data['order_id'],
            'payment_sn'=>	$payment_sn,
            'amount'	=>	$data['amount'],
            'pay_type'	=>	1,	//先默认为1-支付宝
        ];
        $apply_id = MerchantPaymentApply::insert_data($pdata);
        if(!$apply_id) {
            return array('errcode'=>310006,'errmsg'=>'订单创建失败');
        }
        
        
        
        //请求支付，推送数据
        
        //配置
        $alipaypc_config = config('alipaypc');
        
        //商户订单号，商户网站订单系统中唯一订单号，必填
        $out_trade_no = trim($payment_sn);
        
        //订单名称，必填
        $subject = trim($data['order_title']);
        
        //付款金额，必填
        $total_amount = trim($data['amount']);
        
        //商品描述，可空
        //$body = trim($data['remark']);
        $body = '';
        
        //构造参数
        $payRequestBuilder = new AlipayTradePagePayContentBuilder();
        $payRequestBuilder->setBody($body);
        $payRequestBuilder->setSubject($subject);
        $payRequestBuilder->setTotalAmount($total_amount);
        $payRequestBuilder->setOutTradeNo($out_trade_no);
        
        $aop = new AlipayTradeService($alipaypc_config);
        
        /**
         * pagePay 电脑网站支付请求
         * @param $builder 业务参数，使用buildmodel中的对象生成。
         * @param $return_url 同步跳转地址，公网可以访问
         * @param $notify_url 异步通知地址，公网可以访问
         * @return $response 支付宝返回的信息
         */
        $response = $aop->pagePay($payRequestBuilder,$alipaypc_config['return_url'],$alipaypc_config['notify_url']);
        
        //记录日志log-----
        //请求支付宝业务参数
        $alipaypc_pagepay_request_data = [
            'out_trade_no' => $out_trade_no,
            'subject' => $subject,
            'total_amount' => $total_amount,
            'body' => $body,
        ];
        $wlog_data = [
            'custom'      => 'alipaypc_pagepay',        //标识字段数据
            'merchant_id' => $data['merchant_id'],      //商户id
            'content'     => 'request->'.json_encode($alipaypc_pagepay_request_data,JSON_UNESCAPED_UNICODE).',response->'.$response, //日志内容
        ];
        $r = CommonApi::wlog($wlog_data);
        //记录日志log-----
        
        
        //输出表单
        //var_dump($response);
        
        return $response;
        
    }
    
    /**
     * 订单查询
     * @author wangshen@dodoca.com
     * @cdate 2018-5-7
     */
    public function orderQuery($data){
    
        //配置
        $alipaypc_config = config('alipaypc');
        
        //商户订单号，商户网站订单系统中唯一订单号
        $out_trade_no = trim($data['payment_sn']);
    
        //支付宝交易号
        //$trade_no = trim($data['WIDTQtrade_no']);
        //请二选一设置
        //构造参数
    	$RequestBuilder = new AlipayTradeQueryContentBuilder();
    	$RequestBuilder->setOutTradeNo($out_trade_no);
    	//$RequestBuilder->setTradeNo($trade_no);
    
    	$aop = new AlipayTradeService($alipaypc_config);
    	
    	/**
    	 * alipay.trade.query (统一收单线下交易查询)
    	 * @param $builder 业务参数，使用buildmodel中的对象生成。
    	 * @return $response 支付宝返回的信息
     	 */
    	$response = $aop->Query($RequestBuilder);
    	$response = json_decode(json_encode($response),true);//对象转数组
    	
    	//记录日志log-----
    	//请求支付宝业务参数
    	$alipaypc_pagepay_request_data = [
    	    'out_trade_no' => $out_trade_no,
    	];
    	$wlog_data = [
    	    'custom'      => 'alipaypc_orderquery',        //标识字段数据
    	    'merchant_id' => $data['merchant_id'],         //商户id
    	    'content'     => 'request->'.json_encode($alipaypc_pagepay_request_data,JSON_UNESCAPED_UNICODE).',response->'.json_encode($response,JSON_UNESCAPED_UNICODE), //日志内容
    	];
    	$r = CommonApi::wlog($wlog_data);
    	//记录日志log-----
    	
    	
    	if(isset($response) && ($response['trade_status'] == 'TRADE_SUCCESS' || $response['trade_status'] == 'TRADE_FINISHED')){
    	    return array('errcode'=>0,'errmsg'=>'交易支付成功');
    	}else{
    	    return array('errcode'=>2,'errmsg'=>'交易不成功');
    	}
    
    }
    
    
    
    /**
     * 获取订单号
     * prefix 前缀
     */
    public static function get_order_sn($prefix='E') {
        $order_sn = $prefix.date('Ymdhis').str_pad(mt_rand(1,99999),5,'0',STR_PAD_LEFT);
        $order = MerchantOrder::select('order_sn')->where(array('order_sn' => $order_sn))->first();
        if(!$order) {
            return $order_sn;
        }
        return self::get_order_sn($prefix);
    }
    
    /**
     * 获取交易号
     */
    private static function get_payment_sn() {
        $payment_sn = date('ymdHis').str_pad(mt_rand(1,99999999),6,'0',STR_PAD_LEFT);
        $order = MerchantPaymentApply::select(['id','payment_sn'])->where('payment_sn',$payment_sn)->first();
        if(!$order) {
            return $payment_sn;
        }
        return self::get_payment_sn();
    }

}
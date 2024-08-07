<?php
/**
 * Created by PhpStorm.
 * User: wangshiliang@dodoca.com
 * Desc: 微信支付
 * link: https://pay.weixin.qq.com/wiki/doc/api/index.html
 * Date: 2017/9/7
 * Time: 11:07
 */

namespace App\Utils\Weixin;

use GuzzleHttp\Client;
use Config;

class Pay
{

    const WEIXIN_API_PAY = 'https://api.mch.weixin.qq.com/';

    private $appid;
    private $mch_id;
    private $key;
    private $apiList = [
        'ordersUnified' => 'pay/unifiedorder',
        'ordersQuery' => 'pay/orderquery',
        'ordersClose' => '/pay/closeorder',
        'ordersRefund' => 'secapi/pay/refund',
        'ordersRefundQuery' =>'pay/refundquery',
        'ordersDownloadbill' =>'pay/downloadbill',
        'getComment'=>'billcommentsp/batchquerycomment',
        'transfers'=>'mmpaymkttransfers/promotion/transfers',
        'gettransferinfo'=>'mmpaymkttransfers/gettransferinfo '
    ];

    public $http_response;

    public function __construct()
    {

    }

    /**
     * @name 下单接口
     * @param string $appid 商户小程序 appid
     * @param string $mch_id 商户支付 id
     * @param string $key 商户支付 key
     * @return  object; this
     */
    public function setPay($appid, $mch_id, $key){
        $this->appid = $appid;
        $this->mch_id = $mch_id;
        $this->key = $key;
        return $this;
    }

    /**
     * @name 统一下单接口
     * @param string $openid  用户身份
     * @param string $body  类目
     * @param string $no 订单号
     * @param string $total_fee 支付价格
     * @param string $notifyUrl 通知地址
     * @param string $trade_type 支付类型 //JSAPI--公众号支付/小程序、NATIVE--原生扫码支付、APP--app支付、MWEB--H5支付的交易类型为
     * @return array
     */

    public function ordersUnified( $openid, $body, $no, $total_fee, $notifyUrl, $trade_type = 'JSAPI' ){
        $param['appid']         = $this->appid;
        $param['mch_id']        = $this->mch_id;
        $param['nonce_str']     = $this->nonce_str(30);
        $param['openid']        = $openid;
        $param['body']          = $body;//商家名称-销售商品类目 128
        $param['out_trade_no']  = $no;//32
        $param['total_fee']     = $total_fee;
        $param['notify_url']    = $notifyUrl;
        $param['trade_type']    = $trade_type; //JSAPI--公众号支付/小程序、NATIVE--原生扫码支付、APP--app支付、MWEB--H5支付的交易类型为
        $param['attach']        = '';//127
        $param['spbill_create_ip'] = $this->get_client_ip();
        $param['sign']          = $this->sign($param,$this->key);//

        $response =  $this->xmlCurl('pay/unifiedorder',$this->toXML($param)) ;
        $response = $this->toArray($response);
        return $response;
    }

    /**
     * @name 订单查询
     * @param string $no 微信订单号
     * @return array
     */
    public function ordersQuery( $no){
        $param['appid']         = $this->appid;
        $param['mch_id']        = $this->mch_id;
        $param['nonce_str']     = $this->nonce_str(30);
        $param['out_trade_no']  = $no; //$param['transaction_id']  = $transaction_id;
        $param['sign']          = $this->sign($param,$this->key);//
        $response =  $this->xmlCurl('pay/orderquery',$this->toXML($param)) ;
        return $this->toArray($response);
    }

    /**
     * @name 关闭订单
     * @param string $no 订单号
     * @return array
     */
    public function ordersClose($no){
        $param['appid']         = $this->appid;
        $param['mch_id']        = $this->mch_id;
        $param['nonce_str']     = $this->nonce_str(30);
        $param['out_trade_no']  = $no;
        $param['sign']          = $this->sign($param,$this->key);//
        $response =  $this->xmlCurl('pay/closeorder',$this->toXML($param)) ;
        return $this->toArray($response);
    }

    /**
     * @name 申请退款
     * @param string $no 微信订单号
     * @param string $refund_no 订单号
     * @param string $total_fee 订单价格
     * @param string $refund_fee 支付价格
     * @param array $ssl [cert,key]
     * @return array
     */
    public function ordersRefund($no, $refund_no, $total_fee, $refund_fee, $ssl){
        $param['appid']         = $this->appid;
        $param['mch_id']        = $this->mch_id;
        $param['nonce_str']     = $this->nonce_str(30);
        $param['out_trade_no']  = $no; //$param['transaction_id']  = $transaction_id;
        $param['out_refund_no']  = $refund_no;
        $param['total_fee']  = $total_fee;
        $param['refund_fee']  = $refund_fee;
        $param['sign']          = $this->sign($param,$this->key);//
        $response =  $this->xmlCurl('secapi/pay/refund',$this->toXML($param),$ssl) ;
        $response = $this->toArray($response);
        return $response;
    }
    /**
     * @name 查询退款
     * @param string $refund_no 退款单号
     * @return array
     */
    public function ordersRefundQuery($refund_no){
        $param['appid']        = $this->appid;
        $param['mch_id']        = $this->mch_id;
        $param['nonce_str']     = $this->nonce_str(30);
        $param['refund_id']     = '';
        $param['out_refund_no']   = $refund_no;
        $param['transaction_id']  = '';
        $param['out_trade_no']    = '';
        $param['sign']           = $this->sign($param,$this->key);//
        $response =  $this->xmlCurl('pay/refundquery',$this->toXML($param)) ;
        return $this->toArray($response);
    }

    /**
     * @name 对账下载
     * @param string $bill_date 对账日期 格式：20140603
     * @param string $bill_type 订单类型：SUCCESS，返回当日成功支付的订单 REFUND，返回当日退款订单
     * @return array
     */
    public function ordersDownloadbill($bill_date, $bill_type){
        $param['appid']         = $this->appid;
        $param['mch_id']        = $this->mch_id;
        $param['nonce_str']     = $this->nonce_str(30);
        $param['bill_date']     = $bill_date;
        $param['bill_type']     = $bill_type;
        $param['sign']          = $this->sign($param,$this->key);//
        return  $this->xmlCurl('secapi/pay/refund',$this->toXML($param)) ;
    }

    /**
     * @name 通知url必须为直接可访问的url
     * @return array
    */

    public function notify_url($xml){
        return $this->toArray($xml);
    }

    /**
     * @name 交易保障
     * @param string $urlkey to $this->apiList
     * @param int $time 单位为毫秒
     * @param string  $return SUCCESS/FAIL
     * @param string  $result SUCCESS/FAIL
     * @return array
     */
    public function orderReport($urlkey, $time, $return, $result){
        $param['appid']         = $this->appid;
        $param['mch_id']        = $this->mch_id;
        $param['nonce_str']     = $this->nonce_str(30);
        $param['interface_url'] = static::WEIXIN_API_PAY.$this->apiList[$urlkey];
        $param['execute_time']  = $time;  //单位为毫秒
        $param['return_code']   = $return;  //SUCCESS/FAIL
        $param['result_code']   = $result; //SUCCESS/FAIL
        $param['user_ip']       = $this->get_server_ip();
        $param['sign']          = $this->sign($param,$this->key);//
        $response =  $this->xmlCurl('payitil/report',$this->toXML($param)) ;
        return $this->toArray($response);
    }


    /**
     * @name 小程序调起支付API
     * @param string $prepayId 统一下单接口返回的 prepay_id
     * @return array
     */
    public function payment($prepayId ){
        $param['timeStamp'] = (string)$_SERVER['REQUEST_TIME'];
        $param['nonceStr']  = $this->nonce_str(30);
        $param['package']   = $prepayId;
        $param['signType']  = 'MD5';
        $param['paySign']   = $this->sign(['appId'=>$this->appid,'nonceStr'=>$param['nonceStr'],'package'=>'prepay_id='.$prepayId,'signType'=>'MD5','timeStamp'=>$param['timeStamp']],$this->key);//  MD5('&package=prepay_id=&signType=MD5&timeStamp=&key=');
        return $param;
    }

    //=======================================企业付款=======================================
    /**
     * @name 企业付款到零钱
     * @param string $openid
     * @param string(28) $no
     * @param string $amount
     * @param string $ssl
     * @return array
     */
    public function transfers($openid,$no,$amount,$ssl,$desc='佣金'){
        $param['mch_appid']          = $this->appid;//微信分配的账号ID（企业号corpid即为此appId）
        $param['mchid']              = $this->mch_id; //微信支付分配的商户号
        $param['nonce_str']          = $this->nonce_str(30);//随机字符串，不长于32位
        $param['partner_trade_no']   = $no;//商户订单号，需保持唯一性 字符数字
        $param['openid']             = $openid;
        $param['check_name']         = 'NO_CHECK';
        $param['amount']             = $amount*100;//转 分 单位
        $param['desc']               = $desc;//
        $param['spbill_create_ip']   = $this->get_client_ip();
        $param['sign']               = $this->sign($param,$this->key);//签名
        $response =  $this->xmlCurl('mmpaymkttransfers/promotion/transfers',$this->toXML($param),$ssl) ;
        return $this->toArray($response);
    }


    /**
     * @name 企业付款到零钱查询
     * @param string $no
     * @param string $ssl
     * @return array
     */
    public function gettransferinfo($no,$ssl){
        $param['appid']          = $this->appid;//微信分配的账号ID（企业号corpid即为此appId）
        $param['mch_id']              = $this->mch_id; //微信支付分配的商户号
        $param['nonce_str']          = $this->nonce_str(30);//随机字符串，不长于32位
        $param['partner_trade_no']   = $no;//商户订单号，需保持唯一性 字符数字
        $param['sign']               = $this->sign($param,$this->key);//签名
        $response =  $this->xmlCurl('mmpaymkttransfers/gettransferinfo',$this->toXML($param),$ssl) ;
        return $this->toArray($response);
    }

    //=======================================公共=======================================

    private function toArray($msg){
        $msg = str_replace(array('<!--[',']-->'),array('<![',']>'),$msg);
        $msg = simplexml_load_string($msg, 'SimpleXMLElement', LIBXML_NOCDATA);
        $msg = json_encode($msg);
        return json_decode($msg,true);
    }

    private function toXML($data){
        $xml = '';
        foreach ($data as $k => $v) {
            $xml .= '<'.$k.'>'.$v.'</'.$k.'>';
        }
        return '<xml>'.$xml.'</xml>';
    }

    private function sign($data, $key){
        ksort($data);
        $sign = $this->ToUrlParams($data);
        $sign = md5($sign.'&key='.$key);
        return strtoupper($sign);
    }

    private function ToUrlParams($data)
    {
        $buff = "";
        foreach ($data as $k => $v)
        {
            if($k != "sign" && $v != "" && !is_array($v)){
                $buff .= $k . "=" . $v . "&";
            }
        }
        $buff = trim($buff, "&");
        return $buff;
    }

    private function nonce_str($length){
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $password = '';
        $charslen = strlen($chars) - 1;
        for ( $i = 0; $i < $length; $i++ ) {
            $key = mt_rand(0, $charslen);
            $password .= $chars[ $key ];
        }
        return $password;
    }

    private function get_server_ip(){
        return gethostbyname($_SERVER['SERVER_NAME']);
    }

    private function get_client_ip(){
        if(isset($_SERVER['REMOTE_ADDR'])){
            return $_SERVER['REMOTE_ADDR'];
        }
        if(getenv('REMOTE_ADDR')){
            return getenv('REMOTE_ADDR');
        }
        return "unknown";
    }


    private function xmlCurl($url , $data , $ssl = []){
        $config['header'] = ['Content-Type: text/xml; charset=UTF8'];
        if(count($ssl) > 0)  $config['ssl'] = $ssl;
        $config['proxy'] = 'false';
        $response = ( new Http())->mxCurl(static::WEIXIN_API_PAY.$url,$data,true,$config );
        $this->http_response = json_encode($response);
        if($response['errcode'] == 0 ){
            return  $response['data'] ;
        }else{
            return '<xml><curl_errno><![CDATA['.$response['errcode'].']]></curl_errno><curl_error><![CDATA['.$response['errmsg'].']]></curl_error></xml>';
        }
    }

}
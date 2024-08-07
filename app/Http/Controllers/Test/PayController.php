<?php

namespace App\Http\Controllers\Test;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;

use App\Http\Requests;
use App\Http\Controllers\Controller;

use App\Utils\Weixin\Pay;
use App\Utils\Printer;

class PayController extends Controller
{

	protected $params;//参数
	protected $account;
	protected $merchant_id;
	protected $appid;
	protected $mch_id;
	protected $key;

    function __construct(Request $request){
        $this->params = $request->all();
		//$this->merchant_id = Auth::user()->merchant_id;
		$this->merchant_id = 1;
		//$this->appid = 'wx8a892ff87aae1a3c';
		//$this->mch_id = '1313378001';
		//$this->key = '098jojojofjslflsl9jop2ipo3447poi';
		
		//$this->appid = 'wx8640e0313cc95d8d';
		$this->appid = 'wx15087d2470181355';
		$this->mch_id = '1429143502';
		$this->key = 'Dodocacanyindodocacanyindodoca03';

		//$this->appid = 'wx0d91962695ca28e7';
		//$this->mch_id = '1498073912';
		//$this->key = 'dalianyunxiakejiyouxiangongsiliu';
    }

	function step3(Request $request) {
		$pay = new Pay();

		$pay->setPay($this->appid, $this->mch_id, $this->key, 1);

		$pay->setSignKey();
		
		
		//Array ( [return_code] => SUCCESS [return_msg] => ok [sandbox_signkey] => f6924efe14f5fb3aec2777e1a7b47924 )
		//print_r($response);exit;
		
		$notifyUrl = 'https://applet.rrdoy.com/pay/callback';
		$orderno = rand(100000,999999);
		$response = $pay->ordersUnified('o2Z_qwsacBaz_44LylCNTA1Oe1-c', 128, $orderno, 551, $notifyUrl, 'JSAPI');
		//Array ( [return_code] => SUCCESS [return_msg] => OK [appid] => wx8a892ff87aae1a3c [mch_id] => 1313378001 [nonce_str] => CaTVXxlOC0NATQgC [sign] => 3EC1A902A89DD6C08C8ACC5B69DCFAE6 [result_code] => SUCCESS [prepay_id] => wx20180131160019d2678308e30238715153 [trade_type] => NATIVE [code_url] => weixin://wxpay/bizpayurl?pr=c63dynd )
		echo '下单';
		print_r($response);

		//查单
		$response = $pay->ordersQuery($orderno);
		//Array ( [openid] => wxd930ea5d5a258f4f [sub_mch_id] => Array ( ) [coupon_fee] => 1 [cash_fee_type] => CNY [settlement_total_fee] => 550 [nonce_str] => TLdWFKXmBlkdNmtsnC5cRA3k08tpg2 [return_code] => SUCCESS [err_code_des] => SUCCESS [time_end] => 20180131175615 [mch_id] => 1313378001 [trade_type] => APP [trade_state_desc] => ok [trade_state] => SUCCESS [coupon_batch_id_0] => 12345 [sign] => FB60F74533EEFA1F95D2826E42BDA731 [cash_fee] => 550 [coupon_id_0] => 10000 [coupon_fee_0] => 1 [coupon_type_0] => NO_CASH [is_subscribe] => Y [return_msg] => OK [fee_type] => CNY [bank_type] => CMC [attach] => sandbox_attach [device_info] => sandbox [out_trade_no] => 285806 [transaction_id] => 20180131175615852531784 [total_fee] => 551 [appid] => wx8a892ff87aae1a3c [coupon_count] => 1 [result_code] => SUCCESS [err_code] => SUCCESS )
		echo '查单';
		print_r($response);
		//echo 1;exit;
	}

	function step4(Request $request) {
		$pay = new Pay();

		$pay->setPay($this->appid, $this->mch_id, $this->key, 1);

		$pay->setSignKey();
		
		$notifyUrl = 'https://applet.rrdoy.com/pay/callback';
		$orderno = rand(100000,999999);
		$response = $pay->ordersUnified('o2Z_qwsacBaz_44LylCNTA1Oe1-c', 128, $orderno, 552, $notifyUrl, 'JSAPI');
		echo '下单';
		print_r($response);

		//查单
		$response = $pay->ordersQuery($orderno);
		echo '查单';
		print_r($response);

		//退款
		$ssl = ['cert'=>'E:\cert\apiclient_cert.pem','key'=>'E:\cert\apiclient_key.pem'];
		$response = $pay->ordersRefund($response['transaction_id'],$orderno,552,552,$ssl);
		echo '退款';
		print_r($response);

		$response = $pay->ordersRefundQuery($orderno);
		echo '退款查询';
		print_r($response);
	}

	function step5(Request $request) {
		$pay = new Pay();

		$pay->setPay($this->appid, $this->mch_id, $this->key, 1);

		$pay->setSignKey();

		$response = $pay->ordersDownloadbill('20180131','REFUND');
		echo '对账单';
		print_r($response);
	}

	function printer(Request $request) {
		//echo 1;exit;
		$Printer = new Printer();
		//$res = $Printer->init();
		$res = $Printer->do_print('123','123');
		//$res = $Printer->get_result('123');
		//$res = $Printer->get_list('2018-01-05','2018-01-05');
		//$res = $Printer->print_data('123','123');

		print_r($res);exit;
	}

}

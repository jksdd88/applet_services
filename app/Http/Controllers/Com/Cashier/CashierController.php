<?php
/**
 * 支付操作类
 * @author zhangchangchun@dodoca.com
 * date 2017-09-06
 */

namespace App\Http\Controllers\Com\Cashier;

use App\Http\Controllers\Controller;
use App\Services\KnowledgeService;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use App\Models\OrderInfo;
use App\Models\PaymentApply;
use App\Models\Trade;
use App\Services\WeixinPayService;
use App\Services\OrderJobService;
use App\Jobs\OrderPaySuccess;
use App\Utils\CommonApi;

use App\Jobs\OrderCancel;

use App\Utils\Alipaypc\AlipayTradeService;
use App\Models\MerchantTrade;
use App\Models\MerchantOrder;
use App\Models\MerchantPaymentApply;
use App\Services\AliPayPcService;
use App\Services\MerchantService;

class CashierController extends Controller
{
    use DispatchesJobs;
	
    public function __construct(WeixinPayService $WeixinPayService,OrderJobService $OrderJobService) {
       $this->WeixinPayService = $WeixinPayService;
	   $this->OrderJobService = $OrderJobService;
    }
	
    //支付结果通知
    public function paynotify($payment_sn,$type=1,Request $request)
	{
		$postStr = file_get_contents("php://input");
		\Log::info('paynotify:url->'.$request->url().',payment_sn->'.$payment_sn.','.json_encode($postStr,JSON_UNESCAPED_UNICODE));
		
		//记录日志
		CommonApi::wlog([
			'custom'    	=>    	'wx_paynotify_'.$payment_sn,
			'merchant_id'   =>    	0,
			'member_id'     =>    	0,
			'content'		=>		'payment_sn->'.$payment_sn.',url->'.$request->url().',result->'.json_encode($postStr,JSON_UNESCAPED_UNICODE),
		]);
		
		if(Trade::where(['payment_sn'=>$payment_sn])->first()) {
			echo "<xml><return_code>SUCCESS</return_code><return_msg>Ok</return_msg></xml>"; exit;
			//return ['errcode'=>40059,'errmsg'=>'订单已支付'.$payment_sn];
		}
		$payment = PaymentApply::select(['order_id','amount','payment_sn','pay_type'])->where(['payment_sn'=>$payment_sn])->first();
		if($payment) {
			$order = OrderInfo::select(['id','merchant_id','member_id','order_sn','pay_type','order_type','pay_status','amount','appid','delivery_type','order_goods_type'])->where(['id'=>$payment['order_id']])->first();
			if($order) {
				if($order['amount']==$payment['amount']) {
					$payinfo = $this->WeixinPayService->queryOrder(['merchant_id'=>$order['merchant_id'],'no'=>$payment['payment_sn'],'appid'=>$order['appid']]);
					if($payinfo && isset($payinfo['errcode']) && $payinfo['errcode']==0) {
						$tradedata = [
							'merchant_id'	=>	$order['merchant_id'],
							'member_id'		=>	$order['member_id'],
							'order_id'		=>	$order['id'],
							'order_sn'		=>	$order['order_sn'],
							'pay_type'		=>	$payment['pay_type'],
							'payment_sn'	=>	$payment['payment_sn'],
							'amount'		=>	$payment['amount'],
							'pay_status'	=>	1,
							'pay_time'		=>	date("Y-m-d H:i:s"),
							'trade_type'	=>	1,
							'order_type'	=>	$order['order_type'],
							'trade_sn'		=>	isset($payinfo['info']['order_no']) ? $payinfo['info']['order_no'] : '',
						];
						Trade::insert_data($tradedata);
						$orderdata = [
							'status'		=>	ORDER_TOSEND,
							'pay_status'	=>	1,
							'pay_time'		=>	date("Y-m-d H:i:s"),
							'payment_sn'	=>	$tradedata['payment_sn'],
							'pay_type'		=>	$payment['pay_type'],
							'trade_sn'		=>	$tradedata['trade_sn'],
						];
						if($order['order_type']==ORDER_APPOINT) {	//预约订单 待收货
							$orderdata['status'] = ORDER_SEND;
						}
						if($order['delivery_type']==2) {	//上门自提
							$orderdata['status'] = ORDER_FORPICKUP;
						}
						
						if($order['order_goods_type'] == ORDER_GOODS_VIRTUAL) {	//虚拟商品订单
						    $orderdata['status'] = ORDER_SEND;
						}

                        if (in_array($order['order_type'], [ORDER_SALEPAY, ORDER_KNOWLEDGE])) {    //优惠买单、知识付费
							$orderdata['status'] = ORDER_SUCCESS;
							$orderdata['finished_time'] = date("Y-m-d H:i:s",time()-7*86400);
						}
						OrderInfo::update_data($order['id'],$order['merchant_id'],$orderdata);
                        if ($order['order_type'] == ORDER_KNOWLEDGE) {
                            KnowledgeService::postOrderPaid($order);
                        }
						//发送到队列
						$job = new OrderPaySuccess($order['id'],$order['merchant_id']);
						$this->dispatch($job);
						
    					echo "<xml><return_code>SUCCESS</return_code><return_msg>Ok</return_msg></xml>"; exit;
					} else {
						$err = [
							'activity_id'	=>	$order['id'],
							'data_type'		=>	'pay_paynotify_wx',
							'content'		=>	'订单未支付异常->'.json_encode($payinfo,JSON_UNESCAPED_UNICODE),
						];
					}
				} else {
					$err = [
						'activity_id'	=>	$order['id'],
						'data_type'		=>	'pay_paynotify_wx',
						'content'		=>	'订单金额异常->'.$order['amount'].',payment->'.$payment['amount'],
					];
				}
			} else {
				$err = [
					'activity_id'	=>	$payment['order_id'],
					'data_type'		=>	'pay_paynotify_wx',
					'content'		=>	'订单数据不存在->'.json_encode($payment,JSON_UNESCAPED_UNICODE),
				];
			}
		} else {
			$err = [
				'activity_id'	=>	$payment_sn,
				'data_type'		=>	'pay_paynotify_wx',
				'content'		=>	'交易数据不存在->'.json_encode($postStr,JSON_UNESCAPED_UNICODE),
			];
		}
		if(isset($err) && $err) {
			CommonApi::errlog($err);
		}
		echo "<xml><return_code>FAIL</return_code><return_msg></return_msg></xml>"; exit;
    }
	
	//模拟支付仅测试环境有效
    public function notifytest($order_sn,Request $request)
	{
		if(!strstr($request->url(),'release-applet.dodoca.com') && !strstr($request->url(),'qa-applet.dodoca.com')) {
			if(strstr($request->url(),'.dodoca.com')) {
				echo 'forbid'; exit;
			}
		}
		
		$order = OrderInfo::select(['id','merchant_id','member_id','order_sn','pay_type','order_type','pay_status','amount','appid','delivery_type'])->where(['order_sn'=>$order_sn])->first();
		if(!$order) {
			die('no order');
		}
		
		if(Trade::where(['order_sn'=>$order_sn])->first()) {
			echo "SUCCESS"; exit;
		}
		
		$payment = PaymentApply::select(['order_id','amount','payment_sn','pay_type'])->where(['order_id'=>$order['id']])->first();
		if($payment) {
			if($order) {
				if($order['amount']==$payment['amount']) {
					$payinfo = array(
						'errcode'	=>	0,
						'info'		=>	array(
							'order_no'	=>	'zcc'.date("YmdHis").rand(1000000,9999999),
						),
					);
					if($payinfo && isset($payinfo['errcode']) && $payinfo['errcode']==0) {
						$tradedata = [
							'merchant_id'	=>	$order['merchant_id'],
							'member_id'		=>	$order['member_id'],
							'order_id'		=>	$order['id'],
							'order_sn'		=>	$order['order_sn'],
							'pay_type'		=>	$payment['pay_type'],
							'payment_sn'	=>	$payment['payment_sn'],
							'amount'		=>	$payment['amount'],
							'pay_status'	=>	1,
							'pay_time'		=>	date("Y-m-d H:i:s"),
							'trade_type'	=>	1,
							'order_type'	=>	$order['order_type'],
							'trade_sn'		=>	isset($payinfo['info']['order_no']) ? $payinfo['info']['order_no'] : '',
						];
						Trade::insert_data($tradedata);
						$orderdata = [
							'status'		=>	ORDER_TOSEND,
							'pay_status'	=>	1,
							'pay_time'		=>	date("Y-m-d H:i:s"),
							'payment_sn'	=>	$tradedata['payment_sn'],
							'pay_type'		=>	$payment['pay_type'],
							'trade_sn'		=>	$tradedata['trade_sn'],
						];
						if($order['order_type']==ORDER_APPOINT) {	//预约订单 待收货
							$orderdata['status'] = ORDER_SEND;
						}
						if($order['delivery_type']==2) {	//上门自提
							$orderdata['status'] = ORDER_FORPICKUP;
						}
						if($order['order_type']==ORDER_SALEPAY) {	//优惠买单
							$orderdata['status'] = ORDER_SUCCESS;
							$orderdata['finished_time'] = date("Y-m-d H:i:s",time()-7*86400);
						}
						OrderInfo::update_data($order['id'],$order['merchant_id'],$orderdata);
						
						//发送到队列
						$job = new OrderPaySuccess($order['id'],$order['merchant_id']);
						$this->dispatch($job);
						
    					echo "SUCCESS"; exit;
					} else {
						$err = [
							'activity_id'	=>	$order['id'],
							'data_type'		=>	'pay_paynotify_wx',
							'content'		=>	'订单未支付异常->'.json_encode($payinfo,JSON_UNESCAPED_UNICODE),
						];
					}
				} else {
					$err = [
						'activity_id'	=>	$order['id'],
						'data_type'		=>	'pay_paynotify_wx',
						'content'		=>	'订单金额异常->'.$order['amount'].',payment->'.$payment['amount'],
					];
				}
			} else {
				$err = [
					'activity_id'	=>	$payment['order_id'],
					'data_type'		=>	'pay_paynotify_wx',
					'content'		=>	'订单数据不存在->'.json_encode($payment,JSON_UNESCAPED_UNICODE),
				];
			}
		} else {
			$err = [
				'activity_id'	=>	$payment_sn,
				'data_type'		=>	'pay_paynotify_wx',
				'content'		=>	'交易数据不存在->'.json_encode($postStr,JSON_UNESCAPED_UNICODE),
			];
		}
		if(isset($err) && $err) {
			CommonApi::errlog($err);
		}
		echo "SUCCESS"; exit;
    }
    
    
    /**
     * 支付宝pc网页支付异步通知
     * @author wangshen@dodoca.com
     * @cdate 2018-5-7
     * 
     * 支付宝通知数据：
     * $_POST['out_trade_no']->商户订单号
     * $_POST['trade_no']->支付宝交易号
     * $_POST['trade_status']->交易状态
     * $_POST['total_amount']->交易金额
     */
    public function alipcnotify(Request $request){
        
        //参数
        $params = $request->all();
        
        $payment_sn = $params['out_trade_no'];
        
        //记录日志log-----
        $wlog_data = [
            'custom'      => 'alipcnotify_alidata',        //标识字段数据
            'content'     => '记录支付宝异步通知数据:payment_sn->'.$payment_sn.',alidata->'.json_encode($params,JSON_UNESCAPED_UNICODE), //日志内容
        ];
        $r = CommonApi::wlog($wlog_data);
        //记录日志log-----
        
        
        //配置
        $alipaypc_config = config('alipaypc');
        
        $aop = new AlipayTradeService($alipaypc_config);
        
        //验签
        $result = $aop->check($params);
        
        if($result) {
            //验证成功
            
            if(MerchantTrade::where(['payment_sn'=>$payment_sn])->first()) {
                
                //记录日志log-----
                $err = [
                    'activity_id'	=>	0,
                    'data_type'		=>	'alipcnotify_err',
                    'content'		=>	'订单已支付:payment_sn->'.$payment_sn.',alidata->'.json_encode($params,JSON_UNESCAPED_UNICODE),
                ];
                CommonApi::errlog($err);
                //记录日志log-----
                
                echo "success";exit;//订单已支付
            }
            
            if($params['trade_status'] == 'TRADE_SUCCESS' || $params['trade_status'] == 'TRADE_FINISHED'){
                
                //支付申请数据
                $payment = MerchantPaymentApply::select(['order_id','amount','payment_sn','pay_type'])->where(['payment_sn'=>$payment_sn])->first();
                
                if(!$payment){
                    //支付申请数据不存在
                    
                    //记录日志log-----
                    $err = [
                        'activity_id'	=>	0,
                        'data_type'		=>	'alipcnotify_err',
                        'content'		=>	'支付申请数据不存在:payment_sn->'.$payment_sn.',alidata->'.json_encode($params,JSON_UNESCAPED_UNICODE),
                    ];
                    CommonApi::errlog($err);
                    //记录日志log-----
                    
                    echo "fail";exit;
                }
                
                if($params['total_amount'] != $payment['amount']){
                    //支付宝返回金额与商家支付请求时的金额不一致
                    
                    //记录日志log-----
                    $err = [
                        'activity_id'	=>	$payment['order_id'],
                        'data_type'		=>	'alipcnotify_err',
                        'content'		=>	'支付宝返回金额与商家支付请求时的金额不一致:payment_sn->'.$payment_sn.',payment->'.json_encode($payment,JSON_UNESCAPED_UNICODE),
                    ];
                    CommonApi::errlog($err);
                    //记录日志log-----
                    
                    echo "fail";exit;
                }
                
                $order = MerchantOrder::select(['id','merchant_id','order_sn','pay_status','amount','remark'])->where(['id'=>$payment['order_id']])->first();
                
                if(!$order){
                    //订单数据不存在
                    
                    //记录日志log-----
                    $err = [
                        'activity_id'	=>	$payment['order_id'],
                        'data_type'		=>	'alipcnotify_err',
                        'content'		=>	'订单数据不存在:payment_sn->'.$payment_sn.',payment->'.json_encode($payment,JSON_UNESCAPED_UNICODE),
                    ];
                    CommonApi::errlog($err);
                    //记录日志log-----
                    
                    echo "fail";exit;
                }
                
                if($order['amount'] != $payment['amount']){
                    //订单金额异常
                    
                    //记录日志log-----
                    $err = [
                        'activity_id'	=>	$payment['order_id'],
                        'data_type'		=>	'alipcnotify_err',
                        'content'		=>	'订单金额异常:payment_sn->'.$payment_sn.',payment->'.json_encode($payment,JSON_UNESCAPED_UNICODE),
                    ];
                    CommonApi::errlog($err);
                    //记录日志log-----
                    
                    
                    echo "fail";exit;
                }
                
                //订单查询
                $AliPayPcService = new AliPayPcService;
                $orderQueryRs = $AliPayPcService->orderQuery(['payment_sn' => $payment_sn,'merchant_id' => $order['merchant_id']]);
                
                if(!isset($orderQueryRs) || !isset($orderQueryRs['errcode']) || $orderQueryRs['errcode'] != 0){
                    //支付宝查询交易不成功
                    
                    //记录日志log-----
                    $err = [
                        'activity_id'	=>	$payment['order_id'],
                        'data_type'		=>	'alipcnotify_err',
                        'content'		=>	'支付宝查询交易不成功:payment_sn->'.$payment_sn.',payment->'.json_encode($payment,JSON_UNESCAPED_UNICODE),
                    ];
                    CommonApi::errlog($err);
                    //记录日志log-----
                    
                    
                    echo "fail";exit;
                }
                
                
                //交易成功处理
                $tradedata = [
                    'merchant_id'	=>	$order['merchant_id'],
                    'order_id'		=>	$order['id'],
                    'order_sn'		=>	$order['order_sn'],
                    'pay_type'		=>	$payment['pay_type'],
                    'payment_sn'	=>	$payment['payment_sn'],
                    'amount'		=>	$payment['amount'],
                    'pay_status'	=>	1,
                    'pay_time'		=>	date("Y-m-d H:i:s"),
                    'trade_type'	=>	1,
                    'trade_sn'		=>	isset($params['trade_no']) ? $params['trade_no'] : '',
                ];
                MerchantTrade::insert_data($tradedata);
                
                $orderdata = [
                    'pay_status'	=>	1,
                    'pay_time'		=>	date("Y-m-d H:i:s"),
                    'payment_sn'	=>	$tradedata['payment_sn'],
                    'pay_type'		=>	$payment['pay_type'],
                    'trade_sn'		=>	$tradedata['trade_sn'],
                ];
                MerchantOrder::update_data($order['id'],$order['merchant_id'],$orderdata);
                
                
                //增加点点币、记录点点币余额变化信息
                //调用商家余额变动api
                $merchant_balance_data_sum = $payment['amount'];//点点币变化值
                
                $merchant_balance_data = [
                    'merchant_id' => $order['merchant_id'],          //商户id
                    'type'	      => 1,                              //变动类型：配置config/varconfig.php
                    'sum'		  => $merchant_balance_data_sum,	 //变动金额
                    'memo'		  => $order['remark'],	             //备注
                    'type_id'     => $order['id'],	                 //订单id
                ];
                
                $MerchantService = new MerchantService;
                $merchantbalance_rs = $MerchantService->changeMerchantBalance($merchant_balance_data);
                
                
                echo "success";	//请不要修改或删除
                
            }else{
                //支付宝交易状态不成功
                
                //记录日志log-----
                $err = [
                    'activity_id'	=>	0,
                    'data_type'		=>	'alipcnotify_err',
                    'content'		=>	'支付宝交易状态不成功:payment_sn->'.$payment_sn.',alidata->'.json_encode($params,JSON_UNESCAPED_UNICODE),
                ];
                CommonApi::errlog($err);
                //记录日志log-----
                
                echo "fail";
            }
            
        }else {
            
            //记录日志log-----
            $err = [
                'activity_id'	=>	0,
                'data_type'		=>	'alipcnotify_err',
                'content'		=>	'验签失败:payment_sn->'.$payment_sn.',alidata->'.json_encode($params,JSON_UNESCAPED_UNICODE),
            ];
            CommonApi::errlog($err);
            //记录日志log-----
            
            
            //验证失败
            echo "fail";
        }
    }
    
    
    
    /**
     * 支付宝pc网页支付同步通知
     * @author wangshen@dodoca.com
     * @cdate 2018-5-7
     */
    public function alipcreturn(Request $request){
        //参数
        $params = $request->all();
    
        //配置
        $alipaypc_config = config('alipaypc');
    
        $aop = new AlipayTradeService($alipaypc_config);
    
        //验签
        $result = $aop->check($params);
    
        if($result) {
            //验证成功
            $url = env('APP_URL').'/manage/main/marketing/live/account?type=1';
    
            //跳转地址：/manage/main/marketing/live/account?type=1
            echo "<meta http-equiv='Content-Type'' content='text/html; charset=utf-8'>";
            echo "<script>window.location='".$url."'</script>";
            exit;
            
        }else {
            //验证失败
            $url = env('APP_URL').'/manage/main/marketing/live/account?type=2';
            
            //跳转地址：/manage/main/marketing/live/account?type=2
            echo "<meta http-equiv='Content-Type'' content='text/html; charset=utf-8'>";
            echo "<script>window.location='".$url."'</script>";
            exit;
        }
    }
	
}

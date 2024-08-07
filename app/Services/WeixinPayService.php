<?php
/**
 * Created by PhpStorm.
 * User: wangshiliang@dodoca.com
 * Date: 2017/8/30
 * Time: 14:50
 */
namespace App\Services;

use App\Models\MemberBalanceDetail;
use App\Models\MemberInfo;
use App\Models\WeixinFormId;
use App\Models\WeixinInfo;
use App\Models\WeixinPay;
use App\Models\WeixinTemplate;
use App\Models\WeixinTransfers;
use App\Utils\Weixin\Pay;
use App\Utils\CacheKey;
use Illuminate\Support\Facades\DB;
use Cache;
use PHPUnit\Runner\Exception;

class WeixinPayService
{

    private $payType;

    private $logkey = 'WeixinPayService';

    public function __construct()
    {
        $this->payType = '';
    }

    /**
     * @name 设置支付方式
     * @param string $type 支付类型：WXPAY ->微信支付; ALIPAY->支付宝
     * @return void
     */
    public function setPay($type){
        $this->payType = $type;
    }

    /**
     * @name 小程序调起支付API
     * @param int $merchant_id 商户id
     * @param string $appid  小程序
     * @param string $prepayId 统一下单接口返回的 prepay_id
     * @return array
     */
    public function payment($merchant_id,$appid,$prepayId){
        if(empty($merchant_id) || empty($prepayId)){
            return ['errcode'=>1,'errmsg'=>'param null'] ;
        }
        $info =  WeixinInfo::get_one_appid($merchant_id,$appid) ;
        if(empty( $info['id'])){
            return ['errcode'=>1,'errmsg'=>'merchant or appid null'] ;
        }
        $infoPaySet = WeixinPay::get_one_appid($merchant_id,$info['appid']) ;
        $infoPaySet = json_decode($infoPaySet['config'],true);
        if(empty( $infoPaySet['mch_id']) ||  empty( $infoPaySet['key'])){
            return ['errcode'=>1,'errmsg'=>'merchant pay set null'] ;
        }
        $utilsPay = new Pay();
        $utilsPay->setPay($info['appid'], $infoPaySet['mch_id'], $infoPaySet['key']);
        return $utilsPay->payment($prepayId);
    }


    /**
     * @name 统一下单接口
     * @param array data[ merchant_id => 商户id,'appid'=>小程序, member_id => 用户身份 , no => 订单号 , total_fee => 支付价格, notifyUrl => 通知地址 ];
     * @return array
     */
    public function payOrder($data){
        $merchant_id = $data['merchant_id'];
        $appid       = $data['appid'];
        $member_id   = $data['member_id'];
        $no          = $data['no'];
        $total_fee   = intval(strval($data['total_fee']*100));

        $notifyUrl   = $data['notifyUrl'];
        if(empty($merchant_id)  || empty($member_id) || empty($total_fee)  || empty($no) || empty($notifyUrl)){
            return ['errcode'=>1,'errmsg'=>'param null'] ;
        }
        $info =  WeixinInfo::get_one_appid($merchant_id,$appid) ;
        if(empty( $info['id'])){
            return ['errcode'=>1,'errmsg'=>'merchant or appid null'] ;
        }
        $infoPaySet = WeixinPay::get_one_appid($merchant_id,$info['appid']) ;
        $infoPaySet = json_decode($infoPaySet['config'],true);
        if(empty( $infoPaySet['mch_id']) ||  empty( $infoPaySet['key'])){
            return ['errcode'=>1,'errmsg'=>'请联系商家配置支付信息'] ;
        }
        $infoTpl =   WeixinTemplate::get_one_ver($merchant_id,$info['appid']); //(new WeixinTemplate())->where('merchant_id',$merchant_id)->where('appid',$info['appid'])->where('status','>',-1)->orderBy('id', 'desc')->first();//
        if(empty( $infoTpl['category'])){
            return ['errcode'=>1,'errmsg'=>'请联系商家配置审核类目'] ;
        }
        $categroy = explode('#',$infoTpl['category']);
        $memberInfo = MemberInfo::query()->where(['member_id'=>$member_id,'appid'=>$info['appid']])->first();
        if(!isset($memberInfo->open_id)  || empty($memberInfo->open_id)){
            return ['errcode'=>1,'errmsg'=>'member_info null'] ;
        }
        $utilsPay = new Pay();
        $utilsPay->setPay($info['appid'], $infoPaySet['mch_id'], $infoPaySet['key']);//
        $response = $utilsPay -> ordersUnified( $memberInfo->open_id, $info['principal_name'].'-'.$categroy[0], $no, $total_fee, $notifyUrl);
        if(!isset($response['return_code']) || $response['return_code'] != 'SUCCESS'){
            $return_msg = isset($response['return_msg']) ? $response['return_msg'] : 'return error';
            (new WeixinService())->setLog($this->logkey.'_'.__FUNCTION__,$data,$response,$merchant_id,$no);//日志
            return ['errcode'=>2,'errmsg'=>$return_msg ,'response'=>$response] ;
        }
        if(!isset($response['result_code']) || $response['result_code'] != 'SUCCESS'){
            (new WeixinService())->setLog($this->logkey.'_'.__FUNCTION__,$data,$response,$merchant_id,$no);//日志
            return ['errcode'=>2,'errmsg'=>$response['err_code_des'].'['.$response['err_code'].']' ,'response'=>$response ] ;
        }
        if(!isset($response['prepay_id']) || empty($response['prepay_id'])){
            (new WeixinService())->setLog($this->logkey.'_'.__FUNCTION__,$data,$response,$merchant_id,$no);//日志
            return ['errcode'=>2,'errmsg'=>'prepay_id error' ];
        }
        //消息模板 form_id 收集
        $this->formid_insert([
            'merchant_id'=>$memberInfo['merchant_id'],
            'appid'=>$memberInfo['appid'],
            'member_id'=>$memberInfo['member_id'],
            'open_id'=>$memberInfo['open_id'],
            'prepay_id'=>$response['prepay_id']
            ,'no'=>$no
        ]);
        return ['errcode'=>0,'errmsg'=>'ok','data'=> $utilsPay->payment($response['prepay_id']) ];
    }
    /**
     * @name 订单查询
     * @param array $data [merchant_id => 商户id ,'appid'=>小程序, no => 订单号 ]
     * @return  array
     */
    public function queryOrder($data){
        $merchant_id = $data['merchant_id'];
        $appid       = $data['appid'];
        $no          = $data['no'];
        if(empty($merchant_id) || empty($no) ){
            return ['errcode'=>1,'errmsg'=>'param null'] ;
        }
        $info =  WeixinInfo::get_one_appid($merchant_id,$appid) ;
        if(empty( $info['id'])){
            return ['errcode'=>1,'errmsg'=>'merchant or appid null'] ;
        }
        $infoPaySet = WeixinPay::get_one_appid($merchant_id,$info['appid']) ;
        $infoPaySet = json_decode($infoPaySet['config'],true);
        if(empty( $infoPaySet['mch_id']) ||  empty( $infoPaySet['key'])){
            return ['errcode'=>1,'errmsg'=>'请联系商家配置支付信息'] ;
        }
        $utilsPay = new Pay();
        $utilsPay->setPay($info['appid'], $infoPaySet['mch_id'], $infoPaySet['key']);
        $response = $utilsPay->ordersQuery($no);
        if(!isset($response['return_code']) || $response['return_code'] != 'SUCCESS'){
            $return_msg = isset($response['return_msg']) ? $response['return_msg'] : 'return error';
            (new WeixinService())->setLog($this->logkey.'_'.__FUNCTION__,$data,$response,$merchant_id,$no);//日志
            return ['errcode'=>2,'errmsg'=>$return_msg ,'response'=>$response] ;
        }
        if(!isset($response['result_code']) || $response['result_code'] != 'SUCCESS'){
            (new WeixinService())->setLog($this->logkey.'_'.__FUNCTION__,$data,$response,$merchant_id,$no);//日志
            return ['errcode'=>2,'errmsg'=>$response['err_code_des'].'['.$response['err_code'].']' ,'response'=>$response] ;
        }
        if(!isset($response['trade_state']) || ($response['trade_state'] != 'SUCCESS' &&  $response['trade_state'] !='REFUND') ){ //支付成功  或者 已退款
            (new WeixinService())->setLog($this->logkey.'_'.__FUNCTION__,$data,$response,$merchant_id,$no);//日志
            return ['errcode'=>3,'errmsg'=>$response['trade_state_desc'].'['.$response['trade_state'].']' ,'response'=>$response] ;
        }
        //消息模板 form_id 收集
        $this->formid_update($no);
        //时间格式转换
        $time_end = substr($response['time_end'],0,4).'-'.substr($response['time_end'],4,2).'-'.substr($response['time_end'],6,2).' '.substr($response['time_end'],8,2).':'.substr($response['time_end'],10,2).':'.substr($response['time_end'],12,2);
        return ['errcode'=>0,'errmsg'=>'ok','data'=> $response,'info' => ['order_no'=>$response['transaction_id'],'order_time'=>$time_end] ];
    }
    /**
     * @name 退款订单
     * @param array $data [merchant_id => 商户id ,'appid'=>小程序, no => 订单号 , refund_no => 退款订单号 , total_fee => 订单价格 , refund_fee => 退款价格 ]
     * @return  array
     */
    public function refundOrder($data){
        $merchant_id = $data['merchant_id'];
        $appid       = $data['appid'];
        $no          = $data['no'];
        $refund_no   = $data['refund_no'];
        $total_fee   = intval(strval($data['total_fee']*100  )) ;
        $refund_fee  = intval(strval($data['refund_fee']*100 )) ;
        if(empty($merchant_id)  || empty($no) || empty($refund_no) || empty($total_fee)   || empty($refund_fee)){
            return ['errcode'=>1,'errmsg'=>'param null'] ;
        }
        if($refund_fee > $total_fee){
            return ['errcode'=>1,'errmsg'=>'refund_fee gt total_fee'] ;
        }
        $info =  WeixinInfo::get_one_appid($merchant_id,$appid) ;
        if(empty( $info['id'])){
            return ['errcode'=>1,'errmsg'=>'merchant or appid  null'] ;
        }
        $infoPaySet = WeixinPay::get_one_appid($merchant_id,$info['appid']) ;
        $config = json_decode($infoPaySet['config'],true);
        $ssl  = json_decode($infoPaySet['pem'],true);
        if(empty( $config['mch_id']) ||  empty( $config['key']) || empty($ssl['apiclient_cert']) || empty($ssl['apiclient_key'])){
            return ['errcode'=>1,'errmsg'=>'请联系商家配置支付信息'] ;
        }
        $utilsPay = new Pay();
        $utilsPay->setPay($info['appid'], $config['mch_id'], $config['key']);
        $response = $utilsPay -> ordersRefund( $no, $refund_no, $total_fee, $refund_fee, ['cert'=>'/'.PEM_PATH.$ssl['apiclient_cert'],'key'=>'/'.PEM_PATH.$ssl['apiclient_key']]);
        if(!isset($response['return_code']) || $response['return_code'] != 'SUCCESS'){
            $return_msg = isset($response['return_msg']) ? $response['return_msg'] : 'return error';
            return ['errcode'=>2,'errmsg'=>$return_msg ,'response'=>$response] ;
        }
        if(!isset($response['result_code']) || $response['result_code'] != 'SUCCESS'){
            return ['errcode'=>2,'errmsg'=>$response['err_code_des'].'['.$response['err_code'].']' ,'response'=>$response] ;
        }
		$transaction_refund_id = isset($response['refund_id']) ? $response['refund_id'] : '';
        return ['errcode'=>0,'errmsg'=>'ok','data'=> $response,'transaction_refund_id'=>$transaction_refund_id];
    }
    /**
     * @name 退款查询
     * @param array $data [merchant_id => 商户id ,'appid'=>小程序, refund_no => 退款订单号 ]
     * @return  array
     */
    public function refundOrderQuery($data){
        $merchant_id = $data['merchant_id'];
        $appid       = $data['appid'];
        $refund_no   = $data['refund_no'];
        if(empty($merchant_id) ||  empty($refund_no) ){
            return ['errcode'=>1,'errmsg'=>'param null'] ;
        }
        $info =  WeixinInfo::get_one_appid($merchant_id,$appid) ;
        if(empty( $info['id'])){
            return ['errcode'=>1,'errmsg'=>'merchant or appid null'] ;
        }
        $infoPaySet = WeixinPay::get_one_appid($merchant_id,$info['appid']) ;
        $infoPaySet = json_decode($infoPaySet['config'],true);
        if(empty( $infoPaySet['mch_id']) ||  empty( $infoPaySet['key'])){
            return ['errcode'=>1,'errmsg'=>'请联系商家配置支付信息'] ;
        }
        $utilsPay = new Pay();
        $utilsPay->setPay($info['appid'], $infoPaySet['mch_id'], $infoPaySet['key']);
        $response = $utilsPay->ordersRefundQuery($refund_no);
        if(!isset($response['return_code']) || $response['return_code'] != 'SUCCESS'){
            $return_msg = isset($response['return_msg']) ? $response['return_msg'] : 'return error';
            return ['errcode'=>2,'errmsg'=>$return_msg,'response'=>$response] ;
        }
        if(!isset($response['result_code']) || $response['result_code'] != 'SUCCESS'){
            return ['errcode'=>2,'errmsg'=>$response['err_code_des'].'['.$response['err_code'].']' ,'response'=>$response] ;
        }
        if(!isset($response['refund_status_0']) || $response['refund_status_0'] != 'SUCCESS'){
            return [ 'errcode'=>2,'errmsg'=>$response['refund_status_0'] ,'response'=>$response ] ;
        }
        return ['errcode'=>0,'errmsg'=>'打款提交成功','data'=> $response ];
    }

    /**
     * @name 企业付款到零钱
     * @param array $data [merchant_id => 商户id ,'appid'=>小程序 ,'member_id'=>'会员id','amount'=>'金额 ,大于1元','cid'=>'回调数据id','no'=>打款单号,type=>'打款类型：1 分销打款…… 默认1 ']
     * @deprecated   每个推客每日10次; 单笔单日限额2万；不支持给非实名用户打款；一个商户同一日付款总额限额100W；单笔最小金额默认为1元
     * @param array $data [merchant_id => 商户id ,'appid'=>小程序 ]
     * @return  array
     */
    public function transfersSubmit($data){
        $merchant_id = (int)$data['merchant_id'];
        $appid       = $data['appid'];
        $member_id   = (int)$data['member_id'];
        $amount      = (float)$data['amount'];
        $cid         = (int)$data['cid'];
        $no          = $data['no'];//isset($data['no'])?$data['no']:'T'.date('YmdHis'). rand(10000,99999);
        $type        = isset($data['type'])?$data['type']:1;
        if(empty($merchant_id) ||  empty($appid) || empty($member_id) ||  $amount < 1 || $cid <= 0 || empty($no)){
            return ['errcode'=>1,'errmsg'=>'参数缺失'] ;
        }
        $info =  WeixinInfo::get_one_appid($merchant_id,$appid) ;
        if(empty( $info['id'])){
            return ['errcode'=>1,'errmsg'=>'商户小程序账号有误'] ;
        }
        $memberInfo = MemberInfo::get_one($member_id,$info['appid'],$info['merchant_id']);
        if(empty( $memberInfo['open_id'])){
            return ['errcode'=>1,'errmsg'=>'接收打款会员有误'] ;
        }
        $infoPaySet = WeixinPay::get_one_appid($merchant_id,$info['appid']) ;
        $config = json_decode($infoPaySet['config'],true);
        $ssl  = json_decode($infoPaySet['pem'],true);
        if(empty( $config['mch_id']) ||  empty( $config['key']) || empty($ssl['apiclient_cert']) || empty($ssl['apiclient_key'])){
            return ['errcode'=>1,'errmsg'=>'请联系商家配置支付信息'] ;
        }
        $transfersInfo  = WeixinTransfers::get_one('order_no',$no);
        if(isset($transfersInfo['id'])){
            if($transfersInfo['merchant_id'] != $merchant_id || $transfersInfo['appid'] != $appid ){
                return ['errcode'=>2,'errmsg'=>'打款单号重复'] ;
            }
            if($transfersInfo['status'] != 3){
                return ['errcode'=>2,'errmsg'=>'无效操作'] ;
            }
            WeixinTransfers::update_data('id',$transfersInfo['id'],['status'=>1,'reason'=>'' ]);
            return ['errcode'=>0,'errmsg'=>'ok','id'=>$transfersInfo['id']] ;
        }

        try{
            $id = WeixinTransfers::insert_data([
                'merchant_id'=>$info['merchant_id'],
                'appid'=>$info['appid'],
                'member_id'=>$memberInfo['member_id'],
                'open_id'=>$memberInfo['open_id'],
                'order_no'=>$no,
                'amount'=>$amount,
                'type'=>$type,
                'cid'=>$cid
            ]);
            if(!$id){
                return ['errcode'=>2,'errmsg'=>'打款单号重复'] ;
            }
            return ['errcode'=>0,'errmsg'=>'ok','id'=>$id] ;
        }catch (\Exception $e){
            return ['errcode'=>2,'errmsg'=>'打款单号重复'] ;
        }

    }
    /**
     * @name 企业付款到零钱 撤回
     * @param array $id
     * @return  array
     */
    public function transfersRollBack($id){
        if(!$id || is_numeric($id)){
            return ['errcode'=>1,'errmsg'=>'id error'] ;
        }
        $info = WeixinTransfers::get_one('id',$id);
        if(!isset($info['id'])){
            return ['errcode'=>1,'errmsg'=>'id error'] ;
        }
        if($info['status'] == 0){
            return ['errcode'=>2,'errmsg'=>'打款已完成，无法撤销'] ;
        }
        if($info['status'] == 2 && empty($info['reason'])){
            return ['errcode'=>3,'errmsg'=>'打款已完成，无法撤销'] ;
        }
        if($info['reason'] == 'SYSTEMERROR'){
            return ['errcode'=>4,'errmsg'=>'打款中，无法撤销'] ;
        }
        WeixinTransfers::update_data('id',$info['id'],[ 'status'=>3]);
        return ['errcode'=>0,'errmsg'=>'撤销成功'] ;
    }
    /**
     * @name 企业付款到零钱 脚本
     * @return  array
     */
    public function transfers(){
        $list = WeixinTransfers::script_list(1);
        $utilsPay = new Pay();
        foreach ($list as $k => $v) {
            $infoPaySet = WeixinPay::get_one_appid($v['merchant_id'],$v['appid']) ;
            $config = json_decode($infoPaySet['config'],true);
            $ssl  = json_decode($infoPaySet['pem'],true);
            if(empty( $config['mch_id']) ||  empty( $config['key']) || empty($ssl['apiclient_cert']) || empty($ssl['apiclient_key'])){
                WeixinTransfers::update_data('id',$v['id'],[ 'reason'=>'请商家配置支付信息['.$v['appid'].']' ]);
                WeixinTransfers::increment_data($v['id'],5);
                $this->transfersCallback($v['cid'],$v['merchant_id'],'请商家配置支付信息['.$v['appid'].']',$v['type']);
                continue;
            }
            $utilsPay->setPay($v['appid'], $config['mch_id'], $config['key']);
            $ssl = ['cert'=>'/'.PEM_PATH.$ssl['apiclient_cert'],'key'=>'/'.PEM_PATH.$ssl['apiclient_key']];
            //打款
            DB::beginTransaction();

            WeixinTransfers::update_data('id',$v['id'],[ 'status'=>2,'reason_sum'=>0,'reason'=>'']);
            $response = $utilsPay->transfers($v['open_id'],$v['order_no'],$v['amount'],$ssl);
            //打款是否成功
            if(!isset($response['return_code'])  || $response['return_code'] != 'SUCCESS'){
                DB::rollBack();
                WeixinTransfers::update_data('id',$v['id'],['status'=>3,'reason'=>'return error; '.json_decode($response) ]);
                $this->transfersCallback($v['cid'],$v['merchant_id'],isset($response['return_msg'])?$response['return_msg']:'打款失败【return_msg】',$v['type']);
                (new WeixinService())->setLog($this->logkey.'_'.__FUNCTION__, $v, $response, $v['merchant_id'],$v['id']);//日志
                continue;
            }
            //不成功 且 不为 SYSTEMERROR
            if($response['result_code'] != 'SUCCESS' && isset($response['err_code']) && $response['err_code'] != 'SYSTEMERROR'){
                DB::rollBack();
                WeixinTransfers::update_data('id',$v['id'],['status'=>3,'reason'=>$response['err_code_des'].'['.$response['err_code'].']' ]);
                $this->transfersCallback($v['cid'],$v['merchant_id'],$response['err_code_des'].'【'.$response['err_code'].'】',$v['type']);
                (new WeixinService())->setLog($this->logkey.'_'.__FUNCTION__, $v, $response, $v['merchant_id'],$v['id']);//日志
                continue;
            }
            //不成功 SYSTEMERROR
            if(isset($response['err_code']) &&  $response['err_code'] == 'SYSTEMERROR'){
                DB::rollBack();
                WeixinTransfers::update_data('id',$v['id'],['reason'=>$response['result_code'] ]);
                \Log::info('transfers['.$v['id'].']:'.json_encode($response));
                continue;
            }
            //成功
            DB::commit();
        }
    }
    public function transfersCheck(){
        $list = WeixinTransfers::script_list(2);
        $utilsPay = new Pay();
        foreach ($list as $k => $v) {
            $infoPaySet = WeixinPay::get_one_appid($v['merchant_id'],$v['appid']) ;
            $config = json_decode($infoPaySet['config'],true);
            $ssl  = json_decode($infoPaySet['pem'],true);
            if(empty( $config['mch_id']) ||  empty( $config['key']) || empty($ssl['apiclient_cert']) || empty($ssl['apiclient_key'])){
                WeixinTransfers::update_data('id',$v['id'],[ 'reason'=>'请商家配置支付信息['.$v['appid'].']' ]);
                WeixinTransfers::increment_data($v['id'],5);
                $this->transfersCallback($v['cid'],$v['merchant_id'],'请商家配置支付信息['.$v['appid'].']',$v['type']);
                continue;
            }
            $utilsPay->setPay($v['appid'], $config['mch_id'], $config['key']);
            $ssl = ['cert'=>'/'.PEM_PATH.$ssl['apiclient_cert'],'key'=>'/'.PEM_PATH.$ssl['apiclient_key']];
            //验证打款
            $response = $utilsPay->gettransferinfo($v['order_no'],$ssl);
            if(!isset($response['return_code']) || $response['return_code'] != 'SUCCESS'){
                WeixinTransfers::update_data('id',$v['id'],['reason'=>'return error; '.json_decode($response) ]);
                $this->transfersCallback($v['cid'],$v['merchant_id'],isset($response['return_msg'])?$response['return_msg']:'打款失败【return_msg】',$v['type']);
                (new WeixinService())->setLog($this->logkey.'_'.__FUNCTION__, $v, $response, $v['merchant_id'],$v['id']);//日志
                continue;
            }
            if(isset($response['err_code']) && $response['err_code'] != 'SUCCESS'){
                WeixinTransfers::update_data('id',$v['id'],['reason'=>$response['err_code_des'].'['.$response['err_code'].']' ]);
                $this->transfersCallback($v['cid'],$v['merchant_id'],$response['err_code_des'].'【'.$response['err_code'].'】',$v['type']);
                (new WeixinService())->setLog($this->logkey.'_'.__FUNCTION__, $v, $response, $v['merchant_id'],$v['id']);//日志
                continue;
            }
            if($response['status']=='SUCCESS'){//:转账成功
                WeixinTransfers::update_data('id',$v['id'],['status'=>0,'trade_sn'=> $response['detail_id'] ,'payment_no'=>$response['mch_id'],'payment_time'=>$response['transfer_time']]);
                $this->transfersCallback($v['cid'],$v['merchant_id'],'',$v['type']);
            }else if($response['status']=='FAILED'){//转账失败
                WeixinTransfers::update_data('id',$v['id'],['status'=>3,'reason'=>$response['reason']]);
                $this->transfersCallback($v['cid'],$v['merchant_id'],$response['reason'].'【FAILED】',$v['type']);
                (new WeixinService())->setLog($this->logkey.'_'.__FUNCTION__, $v, $response, $v['merchant_id'],$v['id']);//日志
            }else if($response['status']=='PROCESSING'){//处理中

            }
        }

    }
    /**
     * @name 企业付款到零钱 回调
     * @param string  $reason
     * @param int     $type
     * @param array  $response
     * @return  array
     */
    private function transfersCallback($id, $merchant_id, $reason = '', $type = 1){
        if($type == 1){
            if(!empty($reason)){
                MemberBalanceDetail::update_data($id,$merchant_id,['status'=>TAKECASH_FAIL,'fail_reason'=>$reason]);
            }else{
                MemberBalanceDetail::update_data($id,$merchant_id,['status'=>TAKECASH_SUCCESS]);
            }
        }
    }

    /**
     * 小程序 消息模板 form_id
     */
    private function formid_insert($data){
        $id = WeixinFormId::insert_data(['merchant_id' => $data['merchant_id'], 'appid' => $data['appid'], 'member_id' => $data['member_id'], 'open_id' => $data['open_id'], 'formid' => $data['prepay_id'], 'number' => 3 , 'no' => $data['no'] , 'time' => time() + 7200,]);
        if($id){
            Cache::put( CacheKey::get_weixin_cache('formid_prepay_id'.$data['no']),$id, 120);
        }
    }
    private function formid_update($no){
        $key = CacheKey::get_weixin_cache('formid_prepay_id'.$no);
        $formid_id = Cache::get($key);
        if($formid_id){
            WeixinFormId::update_data($formid_id,['no'=>'','time'=>time()+59400]);
            Cache::forget($key);
        }
    }

}
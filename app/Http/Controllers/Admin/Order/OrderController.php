<?php
/**
 * Date: 2017/9/5
 * Time: 11:01
 * Author: Lujingjing
 * 后台商品
 */
namespace App\Http\Controllers\Admin\Order;

use App\Models\OrderKnowledge;
use Illuminate\Foundation\Bus\DispatchesJobs;
use App\Models\DeliveryCompany;
use App\Models\Goods;
use App\Models\MerchantDelivery;
use App\Models\OrderPackageItem;
use App\Models\Region;
use App\Models\Shop;
use App\Models\Store;
use App\Models\Waybill;
use App\Services\FightgroupService;
use App\Services\OrderService;
use App\Utils\CacheKey;
use App\Utils\Logistics;
use App\Utils\Dada\DadaOrder;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\OrderInfo;
use App\Models\OrderGoods;
use App\Models\OrderComment;
use App\Models\OrderCommentImg;
use App\Models\OrderGoodsUmp;
use App\Models\OrderUmp;
use App\Models\OrderPackage;
use App\Models\OrderRefund;
use App\Models\OrderRefundLog;
use App\Models\OrderAddr;
use App\Models\OrderAppt;
use App\Models\OrderVirtualgoods;
use App\Models\GoodsVirtual;
use App\Models\MerchantSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Auth;
use Validator;
use Maatwebsite\Excel\Excel;
use App\Jobs\OrderCancel;
use App\Models\OrderSelffetch;
use App\Models\Member;
use App\Models\Priv;
use App\Models\Merchant;
use App\Models\VersionPriv;
use App\Models\UserPriv;
use App\Models\UserRole;
use App\Models\RolePriv;
use App\Models\WeixinInfo;
use App\Jobs\WeixinMsgJob;
use App\Services\OrderPrintService;
use App\Services\ExpressService;
use App\Models\ExpressOrder;
use Config;

use \Milon\Barcode\DNS1D;
use \Milon\Barcode\DNS2D;
use App\Http\Middleware\AuthApi;

class OrderController extends Controller{
	
	use DispatchesJobs;

    protected $params;//参数
    protected $merchant_id;//参数
    private $excel;
    private $service;
    private $fightgroupService;

    public function __construct(Request $request,OrderService $orderService,Excel $excel,FightgroupService $fightgroupService){
        $this->params = $request->all();
        $this->merchant_id = Auth::user()->merchant_id;
        //$this->merchant_id = 6;
        $this->service = $orderService;
        $this->fightgroupService = $fightgroupService;
        $this->excel = $excel;
    }

    /**
     * 订单列表  && 维权订单列表
     * @Author  Lujingjing
     * @Modify qinyuan
     * 	$status 0.全部,1待付款,2.待发货,3.已发货,4.已完成,5.已关闭,6.退款中
     */
    public function index(){
        //导出订单
        $export = isset($this->params['export']) ? trim($this->params['export']) : 0;
        
        set_time_limit(0); //防止导出数据量大时程序超时
        //$data2 = '{"page":1,"total_page":10,"list":[{"id":5,"merchant_id":2,"member_i d":3,"nickname":"昵称","order_sn":"E2017090504321164749","amount":"1.00","order_type":1,"pay_type":0,"payment_sn":"","trade_sn":"","star":0,"order_status":1,"comment_status":1,"pay_status":0,"goods_info":[{"id":2,"order_id":3,"member_id":6,"goods_id":1,"goods_name":"测试商品","goods_img":"0","quantity":5},{"id":3,"order_id":3,"member_id":6,"goods_id":2,"goods_name":"j阿斯蒂芬","goods_img":"0","quantity":5}],"remark":"","created_time":"2017-09-05 16:32:11"},{"id":5,"merchant_id":2,"member_id":3,"nickname":"昵称","order_sn":"E2017090504321164749","amount":"1.00","order_type":1,"pay_type":0,"payment_sn":"","trade_sn":"","star":0,"order_status":1,"comment_status":1,"pay_status":0,"goods_info":[{"id":2,"order_id":3,"member_id":6,"goods_id":1,"goods_name":"测试商品","goods_img":"0","quantity":5},{"id":3,"order_id":3,"member_id":6,"goods_id":2,"goods_name":"j阿斯蒂芬","goods_img":"0","quantity":5}],"remark":"","created_time":"2017-09-05 16:32:11"}]}';
        //return $data2;
        //物流名称别名
        $shopData = MerchantSetting::select("delivery_alias",'selffetch_alias')->where("merchant_id",$this->merchant_id)->first();
        //dd($shopData);
        $shipping_type = array(
            1 => isset($shopData["delivery_alias"])&&!empty($shopData["delivery_alias"])?$shopData["delivery_alias"]:'物流配送',
            2 => isset($shopData["selffetch_alias"])&&!empty($shopData["selffetch_alias"])?$shopData["selffetch_alias"]:'上门自提',
            3 => '同城配送',
        );
        
        //订单状态 && 维权状态
        $status = isset($this->params["status"]) && !empty($this->params["status"])?$this->params["status"]:0;
        //维权状态
        if(isset($this->params["refund_status"]) && !empty($this->params["refund_status"])){
            $where["refund_status"] = trim($this->params["refund_status"]);
            if($where["refund_status"] == 'all'){
                $query = OrderInfo::select("order_info.*",'order_info.id as order_id','order_info.status as order_status'
                    ,'order_addr.mobile');
            }else{
                $query = OrderInfo::select("order_info.*",'order_info.id as order_id','order_info.status as order_status'
                    ,"order_refund.*"
                    ,'order_addr.mobile');
            }
        }
        //订单状态
        else{
            if($status == 6){
                $query = OrderInfo::select("order_info.*",'order_info.id as order_id','order_info.status as order_status'
                    ,"order_refund.*"
                    ,'order_addr.mobile');
            }else{
                $query = OrderInfo::select("order_info.*",'order_info.id as order_id','order_info.status as order_status'
                    ,'order_addr.mobile');
            }
        }
        
        //免费版只能显示2个月内的订单，现已取消，同免费版（新）策略
        $rs_merchant = Merchant::where('id',$this->merchant_id)->first();
        if(empty($rs_merchant)){
            $result["errcode"] = 400001;
            $result["errmsg"] = "商户信息出错";
            
            return Response::json($result, 200);
        }
        
        //登录商家
        $query = $query->where("order_info.merchant_id",$this->merchant_id);

        // 维权订单 && 维权状态
        $feedback = isset($this->params['feedback']) && !empty($this->params["feedback"]) ? trim($this->params['feedback']) : '';
        if($feedback){
            $query = $query->where('order_info.refund_status','=','1');
        }

        // 付款方式
        $paymentCode = isset($this->params['buy_way']) && !empty($this->params["buy_way"]) ? trim($this->params['buy_way']) : "";
        if(isset($paymentCode) && $paymentCode) {
            if(in_array($paymentCode, array('wxpay'))) {
                $query = $query->where('order_info.pay_type', '=', 1);
            }
        }

        // 搜索 - 物流方式
        $expressType = isset($this->params['express_type']) && !empty($this->params["express_type"]) ? trim($this->params['express_type']) : "";
        if($expressType) {
            $query = $query->where(['order_info.delivery_type'=>$expressType]);
        }

        // 订单类型 读取varconfig配置
        $orderType = isset($this->params['order_type']) && !empty($this->params["order_type"]) ? trim($this->params['order_type']) : 0;
        if($orderType){
            if($orderType=='goods') {
                $query = $query->whereIn('order_info.order_type',array(1,2,3));
            }
            //关联预约服务订单表
            else if($orderType=='service') {
                $query = $query->where('order_info.order_type','=',4)
                ->leftJoin("order_appt","order_appt.order_id","=","order_info.id");
            }
            //订单类型:优惠买单 不显示失败的订单
            else if($orderType=='discount') {
                $query = $query->where(['order_info.order_type'=>5,'order_info.pay_status'=>1]);
            } else if ($orderType == 'knowledge') {//知识付费订单
                $query = $query->where(['order_info.order_type' => ORDER_KNOWLEDGE, 'order_info.pay_status' => 1]);
            }
        }else{
            //订单类型:所有,排除优惠买单不显示失败的订单 
            //大客户筛选订单超时,临时注释掉
//             if($export != 1){
//                 $query->where(function ($query) {
//                     $query->whereIn('order_info.order_type', array(1, 2, 3, 4,6))
//                     ->orwhere(['order_info.order_type' => 5, 'order_info.pay_status' => 1])
//                     ->orwhere(['order_info.order_type' => ORDER_KNOWLEDGE, 'order_info.pay_status' => 1]);
//                 });
//             }
        }

        // 订单来源:小程序appid
        $appid = isset($this->params['appid']) && !empty($this->params["appid"]) ? trim($this->params['appid']) : "";
        if(isset($appid) && $appid) {
            $query = $query->where('order_info.appid', '=', $appid);
        }
        
        if(isset($this->params["order_sn"]) && !empty($this->params["order_sn"])){
            $where["order_sn"] = trim($this->params["order_sn"]);
            $query = $query->where("order_info.order_sn","like",$where["order_sn"].'%');
        }

        if(isset($this->params["payment_sn"]) && !empty($this->params["payment_sn"])){
            $where["payment_sn"] = trim($this->params["payment_sn"]);
            $query = $query->where("order_info.payment_sn","like",'%'.$where["payment_sn"].'%');
        }

        if(isset($this->params["created_time"]) && !empty($this->params["created_time"])){
            $tempCreatedTime = trim($this->params["created_time"]);
            $where["created_time"] = explode("/",$tempCreatedTime);

            $query = $query->where("order_info.created_time",">",$where["created_time"][0]);
            $query = $query->where("order_info.created_time","<",$where["created_time"][1]);
        }

        if(isset($this->params["finished_time"]) && !empty($this->params["finished_time"])){
            $tempFinishedTime = trim($this->params["finished_time"]);
            $where["finished_time"] = explode("/",$tempFinishedTime);

            $query = $query->where("order_info.finished_time",">",$where["finished_time"][0]);
            $query = $query->where("order_info.finished_time","<",$where["finished_time"][1]);
        }

        if(isset($this->params["member_sn"]) && !empty($this->params["member_sn"])){
            $where["member_sn"] = trim($this->params["member_sn"]);
            $where["member_sn"] = $where["member_sn"] - MEMBER_CONST;
            $query = $query->where("order_info.member_id","=",$where["member_sn"]);
            //if(strpos($where["member_sn"],MEMBER_CONST) !== false){
            //    $where["member_sn"] = str_replace(MEMBER_CONST,"",$where["member_sn"]);
            //    $query = $query->where("order_info.member_id",$where["member_sn"]);
            //}
        }

        $query = $query->leftJoin("order_addr","order_addr.order_id","=","order_info.id");
        if(isset($this->params["consignee"]) && !empty($this->params["consignee"])){
            $where["consignee"] = trim($this->params["consignee"]);
            $query = $query->where("order_addr.consignee","like",'%'.$where["consignee"].'%');
        }

        if(isset($this->params["mobile"]) && !empty($this->params["mobile"])){
            $where["mobile"] = trim($this->params["mobile"]);
            $query = $query->where("order_addr.mobile","like",$where["mobile"].'%');
        }

        if(isset($this->params["province"]) && !empty($this->params["province"])){
            $where["province"] = trim($this->params["province"]);
            //$query = $query->leftJoin("order_addr","order_addr.order_id","=","order_info.id");
            $query = $query->where("order_addr.province","=",$where["province"]);
        }

        if(isset($this->params["city"]) && !empty($this->params["city"])){
            $where["city"] = trim($this->params["city"]);
            $query = $query->where("order_addr.city","=",$where["city"]);
        }

        if(isset($this->params["district"]) && !empty($this->params["district"])){
            $where["district"] = trim($this->params["district"]);
            $query = $query->where("order_addr.district","=",$where["district"]);
        }

        if(isset($this->params["goods_name"]) && !empty($this->params["goods_name"])){
            $where["goods_name"] = trim($this->params["goods_name"]);
            $query = $query->leftJoin("order_goods","order_goods.order_id","=","order_info.id");
            $query = $query->where("order_goods.goods_name","like",'%'.$where["goods_name"].'%');
        }

        //维权状态
        //all.全部,sellertodo:等待卖家处理,buyertodo:等待买家处理,accept:同意退款,feedback_closed:维权撤销
        if(isset($this->params["refund_status"]) && !empty($this->params["refund_status"])){
           	$query = $query->leftJoin("order_refund","order_refund.order_id","=","order_info.id");
			
            $where["refund_status"] = trim($this->params["refund_status"]);
            if($where["refund_status"] == 'all'){
                $query = $query->where("order_info.refund_status",1);
            }elseif($where["refund_status"] == 'sellertodo'){
                $query = $query->whereIn("order_refund.status",[10,11]);
            }elseif($where["refund_status"] == 'buyertodo'){
                $query = $query->where("order_refund.status","=",21);
            }elseif($where["refund_status"] == 'accept'){
                $query = $query->where("order_refund.status","=",20);
            }elseif($where["refund_status"] == 'feedback_closed'){
                $query = $query->where("order_refund.status","=",41);
            }
        }

        //支付方式
        if( isset($this->params["pay_type"]) && !empty($this->params["pay_type"]) ){
            $query = $query->where("order_info.pay_type","=",$this->params["pay_type"]);
		}
        
        $limit  =  10;
        if(isset($this->params["pagesize"])){
            $limit  = !empty($this->params["pagesize"]) ? $this->params["pagesize"] : 10;
        }

        $offset = 0;
        if(isset($this->params["page"])){
            $offset = !empty($this->params["page"]) ? $this->params["page"] : 0;
            $offset = ($offset - 1) * $limit;
        }
        		
        //订单状态
        switch ($status)
        {
            case 0:
                //全部
                $result["_count"] = $query->count();
				
                break;
            case 1:
                //待付款
                $result["_count"] = $query->where("order_info.pay_status",0)->whereIn("order_info.status",[5,6])->distinct()->count(["order_info.id"]);
                $query = $query->where("order_info.pay_status",0)->whereIn("order_info.status",[5,6]);
                break;
            case 2:
                //待发货
                $result["_count"] = $query->where("order_info.pay_status",1)->where("order_info.status",7)->distinct()->count(["order_info.id"]);
                $query = $query->where("order_info.pay_status",1)->where("order_info.status",7);
                break;
            case 3:
                //已发货
                $result["_count"] = $query->where("order_info.pay_status",1)->where("order_info.status",10)->distinct()->count(["order_info.id"]);
                $query = $query->where("order_info.pay_status",1)->where("order_info.status",10);
                break;
            case 4:
                //已完成
                $result["_count"] = $query->where("order_info.pay_status",1)->where("order_info.status",11)->distinct()->count(["order_info.id"]);
                $query = $query->where("order_info.pay_status",1)->where("order_info.status",11);
                break;
            case 5:
                //已关闭
                $result["_count"] = $query->whereIn("order_info.status",array(1,2,3,4))->distinct()->count(["order_info.id"]);
                $query = $query->whereIn("order_info.status",array(1,2,3,4));
                break;
            case 6:
                //退款中
                if(!isset($this->params["refund_status"]) || empty($this->params["refund_status"])){
                    $query = $query->leftJoin("order_refund","order_refund.order_id","=","order_info.id");
                }
                $query = $query->whereIn("order_refund.status",[10,11,12]);
                $result["_count"] = $query->where("order_info.pay_status",1)->where("order_info.refund_status",1)->distinct()->count(["order_info.id"]);
                $query = $query->where("order_info.pay_status",1)->where("refund_status",1);
                break;
            case 9:
                //上门自提,待提货
                $result["_count"] = $query->whereIn("order_info.status",array(9))->distinct()->count(["order_info.id"]);
                $query = $query->whereIn("order_info.status",array(9));
                break;
        }
        if(isset($this->params['order_type']) && !empty($this->params["order_type"]) && $orderType=='service'){
            $query = $query->groupBy("order_info.id");
        }
        if(isset($this->params["goods_name"]) && !empty($this->params["goods_name"])){
            $query = $query->groupBy("order_info.id");
        }
        if(isset($this->params["refund_status"]) && !empty($this->params["refund_status"])){
            $query = $query->groupBy("order_info.id");
        }
        
        //小程序列表
        $arr_weixininfo = array();
        $rs_weixininfo = WeixinInfo::list_data('merchant_id',Auth::user()->merchant_id,1,0);
        if(!empty($rs_weixininfo)){
            foreach ($rs_weixininfo as $key=>$val){
                $arr_weixininfo[$val['appid']] = $val['nick_name'];
            }
        }
        //导出订单
        if($export == 1){
            //导出模式 1:单一SKU 2:多个SKU
            $export_mode = isset($this->params['export_mode']) ? trim($this->params['export_mode']) : 0;
            if( !in_array($export_mode,array(1,2)) ){
                $result["errcode"] = 400001;
                $result["errmsg"] = "导出模式不正确";
            
                return Response::json($result, 200);
            }
            //需要导出订单的id
            $ids = isset($this->params['ids']) ? trim($this->params['ids']) : '';
            if(isset($ids) && $ids){
                $orderids = explode(',', $ids);
                $query = $query -> whereIn('order_info.id',$orderids);
            }


            $query->orderBy("order_info.created_time", "ASC");

            $filename = '订单列表'.date('Ymd',time()).'.csv';
            header('Content-Type: application/vnd.ms-excel');
            header('Content-Disposition: attachment;filename="'.$filename.'"');
            header('Cache-Control: max-age=0');
            //打开PHP文件句柄，php://output表示直接输出到浏览器
            $fp = fopen('php://output', 'a');
            //表格列表

            if ( $export_mode==1 ){
                $head = ['订单编号', '下单时间', '订单金额', '支付方式', '付款时间', '系统单号', '支付单号', '订单类型', '订单状态', '配送方式', '运费', '买家', '来源小程序', '会员账号', '收货人/提货人', '联系电话', '国家', '省', '市', '区', '地址', '门店名称','门店地址', '商品名称 | 商品货号 | 商品规格 | 商品数量',  '订单商品总数量', '订单实付总价', '备注', '完成时间'];
            } else if ( $export_mode==2 ) {
                $head = ['订单编号', '下单时间', '订单金额', '支付方式', '付款时间', '系统单号', '支付单号', '订单类型', '订单状态', '配送方式', '运费', '买家', '来源小程序', '会员账号', '收货人/提货人', '联系电话', '国家', '省', '市', '区', '地址', '门店名称','门店地址','商品名称', '商品货号', '商品规格', '商品数量', '订单实付价', '备注', '完成时间'];
            }
			
            foreach($head as $key => $val){
                $head[$key] = @iconv('UTF-8', 'GBK//IGNORE', $val);
            }
            fputcsv($fp, $head);

            $order_type_name=[
                1 => '商品订单',
                2 => '商品订单',
                3 => '商品订单',
                4 => '预约服务订单',
                5 => '优惠买单订单',
                6 => '砍价订单',
                7 => '知识付费订单'
            ];
            
            $order_had_export = array();
            $query->chunk(100, function($list) use ($fp,$order_type_name, $shipping_type, $arr_weixininfo, $export_mode) {
                foreach($list as $value){
                    if( in_array($value['order_type'], array(5,7)) && $value['pay_status']!=1 ){
                        continue;
                    }
                    if(empty($value)){
                        continue;
                    }
                    $payAt         = $value['pay_time'] == '0000-00-00 00:00:00' ? '' : $value['pay_time'];
                    $createdAt     = $value['created_time'] == '0000-00-00 00:00:00' ? '' : $value['created_time'];
                    $finishedAt    = $value['finished_time'] == '0000-00-00 00:00:00' ? '' : $value['finished_time'];
                    $order_type    = isset($order_type_name[$value['order_type']]) ? $order_type_name[$value['order_type']] : '';
                    //订单状态 
                    if($value['status'] == 9 && $value['order_goods_type'] == 1){
                        $order_status  = '买家付款,待核销';
                    }else if($value["order_type"] == ORDER_FIGHTGROUP){
                        $check = $this->fightgroupService->fightgroupJoinOrder($value['order_id']);
                        if(!empty($check['data']) && $check['data']['type'] == 0){
                            $order_status  = '未成团订单';
                        }else {
                            $order_status  = OrderService::convertOrderStatus($value['order_status']);
                        }
                    }else{
                        $order_status  = OrderService::convertOrderStatus($value['order_status']);
                    }
                    //dd($order_status);
                    $delivery_type = isset($shipping_type[$value['delivery_type']]) && !empty($shipping_type[$value['delivery_type']]) ? $shipping_type[$value['delivery_type']] : '';
                    //获取收货信息
                    $orderAddr = OrderAddr::select('consignee', 'mobile', 'country_name', 'province_name', 'city_name', 'district_name', 'address')
                        ->where('order_id', $value['order_id'])
                        ->first();
                    //订单商品
                    $exportOrderGoods = OrderGoods::select('goods_name','goods_sn','props','quantity','pay_price','price','postage','spec_id')
                        ->where('order_id', $value['order_id'])
                        ->get();
                    $goodsName = '';
                    $goodsSn   = '';
                    $props     = '';
                    $quantity  = 0;
                    
                    $store_name = $store_addr = '';
                    if( isset($value['store_id'])&&!empty($value['store_id']) ){
                        $rs_store = '';
                        $rs_store = Store::get_data_by_id($value['store_id'], $this->merchant_id);
                        $store_name = isset($rs_store['name'])?$rs_store['name']:'';
                        $store_addr = isset($rs_store['address'])?$rs_store['address']:'';
                    }
                    if ( $export_mode==1 ){
                        foreach ($exportOrderGoods as $order_goods) {
                            $goodsName .= !empty($goodsName)?"\n":'';
                            $goodsName .= (!empty($order_goods->goods_name)?$order_goods->goods_name:' - '). '|';
                            $goodsName .= (!empty($order_goods->goods_sn)?$order_goods->goods_sn:' - '). '|';
                            $goodsName .= (!empty($order_goods->props)?$order_goods->props:' - '). '|';
                            $goodsName .= (!empty($order_goods->quantity)?$order_goods->quantity:' - ');
                            $quantity  += (!empty($order_goods->quantity)?$order_goods->quantity:' - ');
                        }
                        
                        $row = [
                            'order_sn'      => $value['order_sn'],
                            'created_time'  => $createdAt,
                            'amount'        => (string)number_format(($value['goods_amount']+$value['shipment_fee']),2,'.',''),
                            'pay_type'      => $value['pay_type'] == 1 ? "微信支付" : ($value['pay_type'] == 2 ? '货到付款' : '其他付款方式'),
                            'pay_time'      => $payAt,
                            'payment_sn'    => !empty($value['payment_sn']) ? "\t".$value['payment_sn']."\t" : '',
                            'trade_sn'      => !empty($value['trade_sn']) ? "\t".$value['trade_sn']."\t" : '',
                            'order_type'    => $order_type,
                            'status'        => $order_status,
                            'delivery_type' => $delivery_type,
                            'shipment_fee'  => $value['shipment_fee'],
                            'nickname'      => filter_emoji($value['nickname']),
                            'weapp'         => isset($arr_weixininfo[$value['appid']]) ? $arr_weixininfo[$value['appid']] : '',
                            'account'       => $value['member_id'] + MEMBER_CONST,
                            'consignee'     => $value['delivery_type']==2?$value['member_name']:filter_emoji($orderAddr['consignee']),
                            'mobile'        => $value['delivery_type']==2?$value['member_mobile']:($orderAddr['mobile'] && $orderAddr['mobile'] !=0 ? $orderAddr['mobile'] : ''),
                            'country_name'  => $orderAddr['country_name'],
                            'province_name' => $orderAddr['province_name'],
                            'city_name'     => $orderAddr['city_name'],
                            'district_name' => $orderAddr['district_name'],
                            'address'       => filter_emoji($orderAddr['address']),
                            'store_name'     => (in_array($value['delivery_type'], array(2,3))||$value['order_type']==5)?$store_name:'--',
                            'store_addr'     => (in_array($value['delivery_type'], array(2,3))||$value['order_type']==5)?$store_addr:'--',
                            'goods_name'    => $goodsName ? rtrim($goodsName,'|') : '',
                            //'goods_sn'      => $goodsSn ? rtrim($goodsSn,'|') : '',
                            //'props'         => $props ? rtrim($props,'|') : '',
                            'quantity'      => $quantity,
                            'goods_amount'  => (string)number_format($value['amount'],2,'.',''),
                            'memo'          => filter_emoji($value['memo']),
                            'finished_time' => $finishedAt
                        ];
                        $column = array();
                        if(!empty($row)){
                            foreach($row as $k => $v){
                                $column[$k] = @iconv('UTF-8', 'GBK//IGNORE', $v);
                            }
                            fputcsv($fp, $column);
                        }
                        unset($row,$column);
                    } else if ( $export_mode==2 ) {
                        foreach ($exportOrderGoods as $order_goods) {
                            $goodsName = '';
                            $goodsSn   = '';
                            $props     = '';
                            $quantity  = 0;

                            $goodsName = $order_goods->goods_name ;
                            $goodsSn   = $order_goods->goods_sn ;
                            $props     = $order_goods->props ;
                            $quantity  = $order_goods->quantity;
                        
                            $row = [
                                'order_sn'      => $value['order_sn'],
                                'created_time'  => $createdAt,
                                'amount'        => (string)number_format(($value['goods_amount']+$value['shipment_fee']),2,'.',''),
                                'pay_type'      => $value['pay_type'] == 1 ? "微信支付" : ($value['pay_type'] == 2 ? '货到付款' : '其他付款方式'),
                                'pay_time'      => $payAt,
                                'payment_sn'    => !empty($value['payment_sn']) ? "\t".$value['payment_sn']."\t" : '',
                                'trade_sn'      => !empty($value['trade_sn']) ? "\t".$value['trade_sn']."\t" : '',
                                'order_type'    => $order_type,
                                'status'        => $order_status,
                                'delivery_type' => $delivery_type,
                                'shipment_fee'  => $order_goods['postage'],
                                'nickname'      => filter_emoji($value['nickname']),
                                'weapp'         => isset($arr_weixininfo[$value['appid']]) ? $arr_weixininfo[$value['appid']] : '',
                                'account'       => $value['member_id'] + MEMBER_CONST,
                                'consignee'     => $value['delivery_type']==2?$value['member_name']:filter_emoji($orderAddr['consignee']),
                                'mobile'        => $value['delivery_type']==2?$value['member_mobile']:($orderAddr['mobile'] && $orderAddr['mobile'] !=0 ? $orderAddr['mobile'] : ''),
                                'country_name'  => $orderAddr['country_name'],
                                'province_name' => $orderAddr['province_name'],
                                'city_name'     => $orderAddr['city_name'],
                                'district_name' => $orderAddr['district_name'],
                                'address'       => filter_emoji($orderAddr['address']),
                                'store_name'     => (in_array($value['delivery_type'], array(2,3))||$value['order_type']==5)?$store_name:'--',
                                'store_addr'     => (in_array($value['delivery_type'], array(2,3))||$value['order_type']==5)?$store_addr:'--',
                                'goods_name'    => $goodsName ? trim($goodsName) : '',
                                'goods_sn'      => $goodsSn ? trim($goodsSn) : '',
                                'props'         => $props ? trim($props) : '',
                                'quantity'      => $quantity,
                                'goods_amount'  => (string)number_format($value['amount'],2,'.',''),
                                'memo'          => filter_emoji($value['memo']),
                                'finished_time' => $finishedAt
                            ];
                            $column = array();
                            if(!empty($row)){
                                foreach($row as $k => $v){
                                    $column[$k] = @iconv('UTF-8', 'GBK//IGNORE', $v);
                                }
                                fputcsv($fp, $column);
                            }
                            
                            unset($order_goods,$row,$column);
                        }
                    }
                    unset($value);
                }
            });
            exit;
        }

        //获取列表
        $data = $query->orderBy("order_info.created_time", "DESC")->skip($offset)->take($limit)->get()->toArray();

        if(!empty($data)){
            foreach($data as $key => $val){
                $data[$key]['pay_type_msg'] = $val['pay_type']==1?"微信支付":($val['pay_type']==2?'货到付款':'其他付款方式');
                $tempOrderIds[] = $val["order_id"];
                $data[$key]['id'] = $val['order_id'];
                $data[$key]['store_name'] = '';
                if( isset($val['store_id'])&&!empty($val['store_id']) ){
                    $rs_store = '';
                    $rs_store = Store::get_data_by_id($val['store_id'], $this->merchant_id);
                    $data[$key]['store_name'] = isset($rs_store['name'])?$rs_store['name']:'';
                }
                $data[$key]['applet_name'] = isset($arr_weixininfo[$val['appid']])?$arr_weixininfo[$val['appid']]:'';

            }

            //订单商品
            $orderGoodsDataQuery = OrderGoods::select()->whereIn("order_id",$tempOrderIds)->get()->toArray();
            //退款
            $tempOrderResultDataQuery = OrderRefund::select()->whereIn("order_id",$tempOrderIds)->get()->toArray();

            if(!empty($tempOrderResultDataQuery)){
                foreach($tempOrderResultDataQuery as $key9 => $val9){
                    $orderResultDataQuery[$val9["order_id"]][$val9["goods_id"]][$val9["spec_id"]][] = $val9;//  wangshiliang@dodoca.com   -> [$val9["spec_id"]]
                }
            }

            foreach($orderGoodsDataQuery as $key2=>$val2){
                $orderGoodsData[$val2["order_id"]][] = $val2;
            }

            if(!empty($orderGoodsData)){
                foreach($orderGoodsData as $key22=>$val22){
                    foreach($val22 as $key222=>$val222){
                        if(isset($orderResultDataQuery[$key22][$val222["goods_id"]][$val222['spec_id']])){//  wangshiliang@dodoca.com   -> [$val9["spec_id"]]
                            $orderGoodsData[$key22][$key222]["refund"] = $orderResultDataQuery[$key22][$val222["goods_id"]][$val222['spec_id']] ;
                        }else{
                            $orderGoodsData[$key22][$key222]["refund"] = [];
                        }
                
                    }
                }
            }

            foreach($data as $key => $val){
                if(isset($orderGoodsData[$val["order_id"]])){
                    $data[$key]["goods_info"] = $orderGoodsData[$val["order_id"]];
                }

                $data[$key]["member_sn"] = MEMBER_CONST + $data[$key]["member_id"];
                $data[$key]["shipping_type"] = isset($shipping_type[$val['delivery_type']])&&!empty($shipping_type[$val['delivery_type']])?$shipping_type[$val['delivery_type']]:'';
                $data[$key]["order_status"] = $data[$key]["order_status"];
                $data[$key]["status"] = $status;

                if(($data[$key]["order_status"] == 6 || $data[$key]["order_status"] == 5)  && $data[$key]["pay_status"] == 0){
                    $data[$key]["status"] = 1;
                }elseif($data[$key]["order_status"] == 7 && $data[$key]["pay_status"] == 1){
                    $data[$key]["status"] = 2;
                }elseif($data[$key]["order_status"] == 10 && $data[$key]["pay_status"] == 1){
                    $data[$key]["status"] = 3;
                }elseif($data[$key]["order_status"] == 11 && $data[$key]["pay_status"] == 1){
                    $data[$key]["status"] = 4;
                }elseif((in_array($data[$key]["order_status"], [1,2,3,4])) && $data[$key]["pay_status"] == 1){
                    $data[$key]["status"] = 5;
                }elseif($data[$key]["refund_status"] == 1){
                    //退款
                    $tempRefundData =  OrderRefund::select()->where("order_id",$data[$key]["id"])->get()->toArray();
                    if(!empty($tempRefundData)){
                        foreach($tempRefundData as $k => $v){
                            if(in_array($v["status"], [10,11,12])){
                                $data[$key]["status"] = 6;
                            }
                        }
                    }
                }
                //订单类型:服务订单 
                if($data[$key]["order_type"] == 4){
                    $tempOrderAppt = OrderAppt::select()->where("order_id",$val["order_id"])->get()->toArray();
                    if(!empty($tempOrderAppt)){
                        $orderApptData = $tempOrderAppt[0];
                        $data[$key]["hexiao"] = $orderApptData;
                        //核销人员
                        if($orderApptData["user_id"]){
                            $tempUserInfo = User::select("username")->where("id",$orderApptData["user_id"])->get()->toArray();
                            $data[$key]["hexiao"]["hexiao_user"] = $tempUserInfo[0]["username"];
                        }else{
                            $data[$key]["hexiao"]["hexiao_user"] = '';
                        }
                    }else{
                        $data[$key]["hexiao"] = [];
                    }
                    if($orderApptData["pay_status"]==0){
                        $data[$key]["order_status_msg"] = '待支付';
                    }else if($orderApptData["pay_status"]==1 && $orderApptData["hexiao_status"]==0){ //
                        $data[$key]["order_status_msg"] = '买家支付，待核销';
                    }else if($orderApptData["pay_status"]==1 && $orderApptData["hexiao_status"]==1){ //
                        $data[$key]["order_status_msg"] = '已完成';
                    }
                }
                //订单类型:非服务订单
                else{
                    $data[$key]["hexiao"] = [];
                }
                //知识付费订单
                if ($data[$key]["order_type"] == ORDER_KNOWLEDGE) {
                    $OrderKnowledgeRes = OrderKnowledge::get_data_by_order_id($val["order_id"], $val["merchant_id"]);
                    if (!empty($OrderKnowledgeRes) && !empty($OrderKnowledgeRes['col_content_type'])) {
                        $data[$key]["knowledge_type"] = $OrderKnowledgeRes['col_content_type'];
                    } else {
                        $data[$key]["knowledge_type"] = '-';
                    }
                }
                
                //拼团订单
                if ($data[$key]["order_type"] == ORDER_FIGHTGROUP) {
                    
                    //判断拼团订单是否未成团
                    $fight_check = $this->fightgroupService->fightgroupJoinOrder($val["order_id"]);
                    if(isset($fight_check['data']['type']) && $fight_check['data']['type'] == 0){
                        $data[$key]["fight_flag"] = 0;
                    }else{
                        $data[$key]["fight_flag"] = 1;
                    }
                    
                }
                
                
                
            }
        }

        $result["errcode"] = 0;
        $result["data"] = $data;
        if($shipping_type){
            foreach ($shipping_type as $key=>$val){
                $arr_shipping_type[]= array(
                    'key' => $key,
                    'val' => $val
                );
            }
        }
        $result['express_type'] = $arr_shipping_type;
        $result["errmsg"] = "订单列表获取成功。";

        return Response::json($result, 200);
    }

    /**
     * 获取订单详情
     * @Author  Lujingjing
     */
    public function getOrderInfo(){
        //$data = '[{"id":5,"merchant_id":2,"member_id":3,"order_status":2,"nickname":"昵称","order_sn":"E2017090504321164749","delivery_alias":"物流配送","consignee":"小鸿","address":"湖北省","fields":"测试赛是水水水水","refund_status":1,"explain":"系统关闭","refund_quantity":2,"refund_type":1,"reason":"发反反复复","refund_amount":"1.25","shipment_fee":0.25,"amount":"1.00","goods_amount":100,"order_type":1,"pay_type":0,"status":10,"images":"","pay_status":0,"goods_info":[{"id":2,"order_id":3,"member_id":6,"goods_id":1,"goods_name":"测试商品","goods_img":"0","refund_quantity":5,"amount":1,"shipment_fee":"退运费","reason":"太小","refund_type":1},{"id":2,"order_id":3,"member_id":6,"goods_id":1,"goods_name":"测试商品","goods_img":"0","refund_quantity":5,"amount":1,"shipment_fee":"退运费","reason":"太小","refund_type":1}],"remark":"","created_time":"2017-09-05 16:32:11"}]';
        if(!isset($this->params["order_id"]) || empty($this->params["order_id"])){
            $result["errcode"] = 40001;
            $result["errmsg"] = "订单Id不能为空。";

            return Response::json($result, 200);
        }
        //订单表
        $orderId = trim($this->params["order_id"]);
        $tempData =  OrderInfo::select()->where("id",$orderId)->where("merchant_id",$this->merchant_id)->get()->toArray();
        if(empty($tempData)){
            $result["errcode"] = 40031;
            $result["errmsg"] = "找不到对应的订单。";

            return Response::json($result, 200);
        }
        $data = $tempData[0];
        $data["order_status"] = $data['status'];
        $data["order_pay_price"] = $data['amount'];
        $pay_type_msg = config("varconfig.order_info_pay_type");
        $data["pay_type_msg"] = isset($pay_type_msg[$data['pay_type']])&&!empty($pay_type_msg[$data['pay_type']])?$pay_type_msg[$data['pay_type']]:'';
        //来源小程序
        if( isset($data['appid'])&&!empty($data['appid'])){
            $rs_weixininfo = WeixinInfo::get_one_appid(Auth::user()->merchant_id,$data['appid']);
            if(!empty($rs_weixininfo)){
                $data['applet_name'] = $rs_weixininfo['nick_name'];
            }
        }
        //门店表
        if(isset($data['store_id'])&&!empty($data['store_id'])){
            $rs_store = Store::get_data_by_id($data['store_id'], Auth::user()->merchant_id);
            $data["store_name"] = isset($rs_store['name'])&&!empty($rs_store['name'])?$rs_store['name']:'';
            $data["store_address"] = $rs_store['province_name'].' '.$rs_store['city_name'].' '.$rs_store['district_name'].' '.$rs_store['address'];
        }
        //会员表 买家信息
        if(!empty($data['member_id'])){
            $rs_member = Member::get_data_by_id($data['member_id'], Auth::user()->merchant_id);
            if(!empty($rs_member)){
                $data['customer'] = $rs_member['name'];
                $data['customer_mobile'] = $rs_member['mobile'];
            }
        }
        
        
        //订单收货信息表
        $tempOrderAddrData =  OrderAddr::select()->where("order_id",$orderId)->get()->toArray();
        if(!empty($tempOrderAddrData)){
            $data["mobile"] = $tempOrderAddrData[0]["mobile"];
            $data["consignee"] = $tempOrderAddrData[0]["consignee"];
            $data["address"] = $tempOrderAddrData[0]["province_name"].$tempOrderAddrData[0]["city_name"].$tempOrderAddrData[0]["district_name"].$tempOrderAddrData[0]["address"];
        }else{
            $data["mobile"] = "";
            $data["consignee"] = "";
            $data["address"] = "";
        }
        
        //订单商品表
        $tempGoodsData =  OrderGoods::select()->where("order_id",$orderId)->get()->toArray();
        foreach($tempGoodsData as $key => $val){
            $tempGoodsArray[] = $val["goods_id"];
            $tempGoodsData[$key]["pice_diff"] = 0;
            $tempGoodsData[$key]["amount_ump"] = 0;
            
        }
        //订单商品与营销关联表
        $tempGoodsUmpData =  OrderGoodsUmp::select()->where("order_id",$orderId)->get()->toArray();
        if(!empty($tempGoodsUmpData)){
            foreach($tempGoodsUmpData as $key1 => $val1){
                if($val1["ump_type"] != 4){
                    if(isset($amountUmp[$val1["goods_id"]])){
                        $amountUmp[$val1["goods_id"]] = abs($amountUmp[$val1["goods_id"]]) + abs($val1["amount"]);
                    }else{
                        $amountUmp[$val1["goods_id"]] = abs($val1["amount"]);
                    }
                }
            }
        }
        //订单维权信息表
        $tempRefundData =  OrderRefund::select()->where("order_id",$orderId)->whereIn("goods_id",$tempGoodsArray)->get()->toArray();
        foreach($tempRefundData as $key3 => $val3){ // wangshiliang@dodoca.com
            $refundData[$val3["goods_id"]][$val3['spec_id']][] = $val3;
            /*
            if(isset($val3['spec_id'])){
                $refundData[$val3["goods_id"]][$val3['spec_id']][] = $val3;
            }else{
                $refundData[$val3["goods_id"]][] = $val3;
            }
            */
        }
        //订单包裹子表
        $tempPackageItemData = OrderPackageItem::select('order_goods.*','order_package_item.package_id','order_package_item.order_goods_id','order_package_item.quantity as shippedQuantity')
                                ->leftJoin('order_goods','order_goods.id','=','order_package_item.order_goods_id')
                                ->where("order_goods.order_id",$orderId)->get()->toArray();

        if(!empty($tempPackageItemData)){
            foreach($tempPackageItemData as $key2 => $val2){
                $goodsPackageData[$val2["order_goods_id"]] = $val2["package_id"];
            }
            //订单包裹表
            $tempPackageData =  OrderPackage::select()->where("order_id",$orderId)->get()->toArray();
            foreach($tempPackageData as $key22 => $val22){
                $package[$val22["id"]]["package_id"] = $val22["id"];
                $package[$val22["id"]]["logis_no"] = $val22["logis_no"];
                $package[$val22["id"]]["logis_code"] = $val22["logis_code"];
                $package[$val22["id"]]["logis_name"] = $val22["logis_name"];

                foreach($tempPackageItemData as $key2 => $val2){
                    if($val2['package_id']==$val22['id']){

                        if(isset($amountUmp[$val2["goods_id"]])) {
                            $val2["amount_ump"] = sprintf("%.2f", $amountUmp[$val2["goods_id"]]/$val2['quantity']*$val2['shippedQuantity']);
                        }else{
                            $val2["amount_ump"] = 0;
                        }

                        $val2['status'] = ORDER_SEND;
                        //订单商品与营销关联表
                        $tempAmount =  OrderGoodsUmp::where(["order_id"=>$orderId,'goods_id'=>$val2["goods_id"],'ump_type'=>4])->pluck('amount');
                        $tempAmount = $tempAmount ? $tempAmount : 0.00;
                        $val2["pice_diff"] = sprintf("%.2f",$tempAmount/$val2['quantity']*$val2['shippedQuantity']);//改价价格
                        $val2['quantity'] = $val2['shipped_quantity'] = $val2['shippedQuantity'];

                        if(isset($refundData[$val2["goods_id"]][$val2["spec_id"]])) {
                            $val2["refund"] = $refundData[$val2["goods_id"]][$val2["spec_id"]];
                        }else{
                            $val2["refund"] = [];
                        }
                        /*
                        elseif(isset($refundData[$val2["goods_id"]])) {
                            $val2["refund"] = $refundData[$val2["goods_id"]];
                        }
                        */
                        $package[$val22["id"]]['goods'][] = $val2;
                    }
                }
            }
        }else{
            $package = null;
        }
        //订单与营销关联表
        $tempOrderUmpData = OrderUmp::select()->where("order_id",$orderId)->get()->toArray();
        $orderPriceDiff = 0;
        if(!empty($tempOrderUmpData)){
            foreach($tempOrderUmpData as $key33 => $val33){
                if($val33['ump_type'] == 4){
                    $orderPriceDiff = $val33['amount'];
                }
                $data["order_ump"][] = $val33;
            }
        }else{
            $data["order_ump"] = [];
        }        
        foreach($tempGoodsData as $key4 => $val4){
            if(isset($refundData[$val4["goods_id"]][$val4["spec_id"]])){
                $tempGoodsData[$key4]["refund"] = $refundData[$val4["goods_id"]][$val4["spec_id"]];
            }else{
                $tempGoodsData[$key4]["refund"] = [];
            }

            // wangshiliang@dodoca.com
            /*
             if(isset($refundData[$val4["goods_id"]][$val4["spec_id"]])){
                //$tempGoodsData[$key4]["refund_quantity"] = $refundData[$val4["goods_id"]]["refund_quantity"];
                //$tempGoodsData[$key4]["amount"] = $refundData[$val4["goods_id"]]["amount"];
                //$tempGoodsData[$key4]["shipment_fee"] = $refundData[$val4["goods_id"]]["shipment_fee"];
                //$tempGoodsData[$key4]["reason"] = $refundData[$val4["goods_id"]]["reason"];
                //$tempGoodsData[$key4]["refund_type"] = $refundData[$val4["goods_id"]]["refund_type"];
                $tempGoodsData[$key4]["refund"] = $refundData[$val4["goods_id"]][$val4["spec_id"]];
            }elseif(isset($refundData[$val4["goods_id"]])){
                //$tempGoodsData[$key4]["refund_quantity"] = $refundData[$val4["goods_id"]]["refund_quantity"];
                //$tempGoodsData[$key4]["amount"] = $refundData[$val4["goods_id"]]["amount"];
                //$tempGoodsData[$key4]["shipment_fee"] = $refundData[$val4["goods_id"]]["shipment_fee"];
                //$tempGoodsData[$key4]["reason"] = $refundData[$val4["goods_id"]]["reason"];
                //$tempGoodsData[$key4]["refund_type"] = $refundData[$val4["goods_id"]]["refund_type"];
                $tempGoodsData[$key4]["refund"] = $refundData[$val4["goods_id"]];
            }else{
                //$tempGoodsData[$key4]["refund_quantity"] = '';
                //$tempGoodsData[$key4]["amount"] = '';
                //$tempGoodsData[$key4]["shipment_fee"] = '';
                //$tempGoodsData[$key4]["reason"] = '';
                //$tempGoodsData[$key4]["refund_type"] = '';
                $tempGoodsData[$key4]["refund"] = [];
            }
            */

            $surplusNum = $val4['quantity']-$val4['shipped_quantity']-$val4['refund_quantity'];

            if(isset($amountUmp[$val4["goods_id"]])) {
                if(isset($goodsPackageData[$val4["id"]])){
                    $tempGoodsData[$key4]["amount_ump"] = sprintf("%.2f",$amountUmp[$val4["goods_id"]]/$val4['quantity']*$surplusNum);
                }else{
                    $tempGoodsData[$key4]["amount_ump"] = sprintf("%.2f",$amountUmp[$val4["goods_id"]]);
                }
            }else{
                $tempGoodsData[$key4]["amount_ump"] = 0;
            }
            //订单商品与营销关联表
            $tempAmount =  OrderGoodsUmp::where(["order_id"=>$orderId,'goods_id'=>$val4["goods_id"],'spec_id'=>$val4["spec_id"],'ump_type'=>4])->pluck('amount');
            $tempAmount = $tempAmount ? $tempAmount : 0.00;

			$tempGoodsData[$key4]["pice_diff"] = sprintf("%.2f",$tempAmount);//改价价格
            /*if(isset($goodsPackageData[$val4["id"]])){
                $tempGoodsData[$key4]["pice_diff"] = sprintf("%.2f",$tempAmount/$val4['quantity']*$surplusNum);//改价价格
            }else{
                $tempGoodsData[$key4]["pice_diff"] = sprintf("%.2f",$tempAmount);//改价价格
            }*/

            if(!isset($tempGoodsData[$key4]["amount_ump"])){
                $tempGoodsData[$key4]["amount_ump"] = 0;
            }

            if(isset($goodsPackageData[$val4["id"]])) {
                if($tempGoodsData[$key4]['quantity'] <= $tempGoodsData[$key4]['shipped_quantity'] + $tempGoodsData[$key4]['refund_quantity']){
                    unset($tempGoodsData[$key4]);
                }else{
                    $tempGoodsData[$key4]['quantity'] = $surplusNum;
                }
            }
        }        
        //dd($data["order_goods_type"]);
        //预约服务表
        if( $data["order_type"]==4 ){
            $orderApptData = OrderAppt::select()->where(["order_id"=>$orderId,'merchant_id'=>$this->merchant_id])->first();
            if(!empty($orderApptData)){
                $data["hexiao"] = $orderApptData;
                $data['customer_appt'] = $orderApptData['customer'];
                $data['customer_mobile_appt'] = $orderApptData['customer_mobile'];
                
                $hexiao_status_msg = config('varconfig.order_appt_hexiao_status');
                $data["hexiao"]["hexiao_status_msg"] = isset($hexiao_status_msg)&&isset($hexiao_status_msg[$data["hexiao"]["hexiao_status"]])?$hexiao_status_msg[$data["hexiao"]["hexiao_status"]]:'';
                
                if($orderApptData["user_id"]){
                    $tempUserInfo = User::select("username")->where("id",$orderApptData["user_id"])->get()->toArray();
                    $data["hexiao"]["hexiao_user"] = $tempUserInfo[0]["username"];
                }else{
                    $data["hexiao"]["hexiao_user"] = '';
                }
                if( isset($orderApptData['store_id']) && !empty($orderApptData['store_id']) ){
                    $data["hexiao"]["store_address"] = Store::get_data_by_id($orderApptData['store_id'], Auth::user()->merchant_id);
                }
            }else{
                $data["hexiao"] = [];
            }
        }
        //知识付费
        else if( $data["order_type"]==ORDER_KNOWLEDGE ){
            $orderKnowledge = orderKnowledge::get_data_by_order_id($orderId, $this->merchant_id);
            $data["knowledge_type"] = '';//内容类型
            $data["payk_type"] = '';//知识付费类型
            if(!empty($orderKnowledge)){
                $data["knowledge_type"] = $orderKnowledge['col_content_type'];
                $data["payk_type"] = $orderKnowledge['k_type'];
            }
        }
        //dd($data["delivery_type"]==2);
        //上门自提表
        else if( $data["delivery_type"]==2 ){
			$OrderSelffetchData = OrderSelffetch::select()->where(["order_id"=>$orderId,'merchant_id'=>$this->merchant_id])->first();
		    if($data['order_type']==ORDER_FIGHTGROUP) {	//拼团订单验证是否完成
				$check = $this->fightgroupService->fightgroupJoinOrder($data['id']);
				if(!empty($check['data']) && $check['data']['type'] == 0){
					$OrderSelffetchData = '';
				}
			}
			
            //dd($OrderSelffetchData);
            if(!empty($OrderSelffetchData)){
                $data["hexiao"] = $OrderSelffetchData;
                
                $hexiao_status_msg = config('varconfig.order_appt_hexiao_status');
                $data["hexiao"]["hexiao_status_msg"] = isset($hexiao_status_msg)&&isset($hexiao_status_msg[$data["hexiao"]["hexiao_status"]])?$hexiao_status_msg[$data["hexiao"]["hexiao_status"]]:'';
                
                if($OrderSelffetchData["user_id"]){
                    $tempUserInfo = User::select("username")->where("id",$OrderSelffetchData["user_id"])->get()->toArray();
                    $data["hexiao"]["hexiao_user"] = $tempUserInfo[0]["username"];
                }else{
                    $data["hexiao"]["hexiao_user"] = '';
                }
                if( isset($OrderSelffetchData['store_id']) && !empty($OrderSelffetchData['store_id']) ){
                     $store_address = Store::get_data_by_id($OrderSelffetchData['store_id'], Auth::user()->merchant_id);
                     $data["hexiao"]["store_address"] = $store_address['province_name'].' '.$store_address['city_name'].' '.$store_address['district_name'].' '.$store_address['address'];
                     $data["hexiao"]["store_name"] = $store_address['name'];
                     $data["hexiao"]["store_mobile"] = $store_address['mobile'];
                }
                if( isset($OrderSelffetchData['hexiao_code']) && !empty($OrderSelffetchData['hexiao_code']) ){
                    //大拿已经做过了
                    //$data["hexiao"]["bar_code"] = 'data:image/png;base64,' . DNS1D::getBarcodePNG($OrderSelffetchData['hexiao_code'], "CODABAR", "2", "80");
                    //$data["hexiao"]["qrcode"] = $this->creadtQRCODE($orderId,$OrderSelffetchData['hexiao_code']);
                }
            }else{
                $data["hexiao"] = [];
            }
        }
        //dd($data["order_goods_type"]);
        //虚拟商品
        else if( $data["order_goods_type"]==1 ){
            //dd($data["order_goods_type"]);
            //虚拟商品扩展表
            $rs_goods_virtual = GoodsVirtual::where(['merchant_id'=>$this->merchant_id,'goods_id'=>$tempGoodsData[0]['goods_id']])->first();
            //dd($rs_goods_virtual);
            //虚拟商品核销表
            $OrderVirtualgoods = OrderVirtualgoods::select()->where(["order_id"=>$orderId,'merchant_id'=>$this->merchant_id])->get();
            //dd($OrderVirtualgoods);
            $hexiao_status_msg = config('varconfig.order_virtualgoods_hexiao_status');
            //dd($OrderSelffetchData);
            if(!empty($OrderVirtualgoods)){
                foreach ($OrderVirtualgoods as $key5=>$val5){
                    //不可核销
                    //是否可以进行核销操作
                    $OrderVirtualgoods[$key5]["is_hexiao"] = 0;
                    if( $rs_goods_virtual['time_type']==1 && date('Y-m-d H:i:s')>=$rs_goods_virtual['start_time'] && date('Y-m-d H:i:s')<=$rs_goods_virtual['end_time'] && $val5['hexiao_status']==0){
                        $OrderVirtualgoods[$key5]["is_hexiao"] = 1;
                    }elseif( $rs_goods_virtual['time_type']==0 && $val5['hexiao_status']==0){
                        $OrderVirtualgoods[$key5]["is_hexiao"] = 1;
                    }
                    //核销状态
                    if( $rs_goods_virtual['time_type']==1 && date('Y-m-d H:i:s')>$rs_goods_virtual['end_time'] && $val5['hexiao_status']==0){
                        $OrderVirtualgoods[$key5]["hexiao_status_msg"] = '已失效';
                    }else{
                        $OrderVirtualgoods[$key5]["hexiao_status_msg"] = isset($hexiao_status_msg)&&isset($hexiao_status_msg[$val5["hexiao_status"]])?$hexiao_status_msg[$val5["hexiao_status"]]:'';
                    }
                    //验证人
                    if($val5["user_id"]){
                        $tempUserInfo = User::select("username")->where("id",$val5["user_id"])->get()->toArray();
                        $OrderVirtualgoods[$key5]["hexiao_user"] = $tempUserInfo[0]["username"];
                    }
                }
                //整理虚拟商品核销数组
                //申请退款中
                $order_refund_sum = OrderRefund::where(['order_id'=>$orderId])
                    ->whereNotIn('status',array(REFUND_FINISHED,REFUND_CLOSE,REFUND_CANCEL))
                    ->sum('refund_quantity');
                $arr = array();
                $freeze = 0;
                foreach ($OrderVirtualgoods as $key5=>$val5){
                    if( $order_refund_sum>0 && $freeze<$order_refund_sum && $val5['hexiao_status']==0 ){
                        $freeze++;
                        $val5['is_hexiao'] = 0;
                        $val5['hexiao_status'] = 4;
                        $val5['hexiao_status_msg'] = ($val5['hexiao_status_msg']=='未使用'||$val5['hexiao_status_msg']=='已失效')?($val5['hexiao_status_msg'].'(维权中)'):'维权中';
                        $data["order_virtualgoods"][] = $val5;
                        continue;
                    }else{
                        $data["order_virtualgoods"][] = $val5;
                    }
                }
                
            }else{
                $data["order_virtualgoods"] = [];
            }
        }else{
            $data["hexiao"] = [];
        }

        $data["goods_info"] = array_values($tempGoodsData);        
        //$data["amount"] = $data["amount"]+$orderPriceDiff;
        if(!empty($package)){
            $data["package"] = array_values($package);
        }else{
            $data["package"] = [];
        }
        //商户配置表
        $setting = MerchantSetting::where(['merchant_id'=>$this->merchant_id])->first();
        if(!empty($setting)){
            $daysNum = $setting["auto_finished_time"]+$data['extend_days'];
            if($data["finished_time"] == "0000-00-00 00:00:00" && $data["shipments_time"] != "0000-00-00 00:00:00"){
                $data["finished_time"] = date("Y-m-d H:i:s",strtotime($data["shipments_time"]."+".$daysNum." day"));
            }
        }
        $shipping_type = array(
            1 => isset($setting["delivery_alias"])&&!empty($setting["delivery_alias"])?$setting["delivery_alias"]:'物流配送',
            2 => isset($setting["selffetch_alias"])&&!empty($setting["selffetch_alias"])?$setting["selffetch_alias"]:'上门自提',
            3 => '同城配送',
        );

        $data['delivery_alias'] = isset($shipping_type[$data['delivery_type']])&&!empty($shipping_type[$data['delivery_type']])?$shipping_type[$data['delivery_type']]:'';
        $data['extend_info'] = json_decode($data['extend_info'],true);
        $result["errcode"] = 0;
        $result["data"] = $data;
        $result["errmsg"] = "订单详情获取成功。";

        return Response::json($result, 200);
    }

    /**
     * 获取退款订单商品详情
     * @Author  Lujingjing
     */
    public function getOrderRefundGoodsInfo(){
        if(!isset($this->params["refund_id"]) || empty($this->params["refund_id"])){
            $result["errcode"] = 40001;
            $result["errmsg"] = "维权Id不能为空。";

            return Response::json($result, 200);
        }

        $refundId = trim($this->params["refund_id"]);
        $refundData = OrderRefund::select()->where("id",$refundId)->where("merchant_id",$this->merchant_id)->get()->toArray();

        if(!empty($refundData)){
            $refundData[0]['total_amount'] = sprintf("%.2f",round(( $refundData[0]['amount']+$refundData[0]['shipment_fee']),2));
            $refundData = $refundData[0];
            $orderInfo = OrderInfo::select()->where("id",$refundData["order_id"])->get()->toArray();
            $orderUmpData = OrderUmp::select()->where("order_id",$refundData["order_id"])->get()->toArray();
            if(!empty($orderUmpData)){
                $tempOrderUmp = 0;
                foreach($orderUmpData as $key => $val){
                    $tempOrderUmp = $tempOrderUmp + $val["amount"];
                }
                $orderInfo[0]["order_ump"] = $tempOrderUmp;
            }else{
                $orderInfo[0]["order_ump"] = 0;
            }

            $goodsInfo = OrderGoods::select()->where("order_id",$refundData["order_id"])->where("goods_id",$refundData["goods_id"])->get()->toArray();
            $consultList = OrderRefundLog::select()->where("order_refund_id",$refundData["id"])->get()->toArray();
            foreach($consultList as $key => $val){
                if(!empty($val["detaill"])){
                    $tempDetaill = json_decode($val["detaill"],true);
                    foreach($tempDetaill as $key1 => $val1){
                        if(isset($val1["type"]) &&  $val1["type"] == "image"){
                            $tempDetaill[$key1]["value"] = json_decode($val1["value"],true);
                        }
                    }
                    $consultList[$key]["detaill"] = $tempDetaill;
                }else{
                    $consultList[$key]["detaill"] = '';
                }
            }
			
			//结束维权按钮是否显示（商家拒绝，且用户7天无操作）
			$refundData['is_refund_over'] = 0;
			if($refundData['status']==REFUND_REFUSE && strtotime($refundData['refuse_time'])+7*24*3600<time()) {
				$refundData['is_refund_over'] = 1;
			}
        }else{
            $refundData = [];
            $orderInfo = [];
            $goodsInfo = [];
            $consultList = [];
        }

        $data['refund_info'] = $refundData;
        $data['order_info'] = $orderInfo;
        $data['goods_info'] = $goodsInfo;
        $data['consult_list'] = $consultList;
        $result["errcode"] = 0;
        $result["data"] = $data;
        $result["errmsg"] = "退款订单商品详情获取成功。";
        //$data = '[{"id":5,"merchant_id":2,"member_id":3,"goods_id":2,"spec_id":5,"member_id":"2017090504321164749","refund_type":"物流配送","order_sn":"小鸿","package_id":"湖北省","refund_quantity":"测试赛是水水水水","amount":1,"shipment_fee":"系统关闭","apply_status":2,"status":1,"images":"发反反复复","operated_time":"1.25","finished_time":0.25,"shipmented_time":"1.00","applyed_time":100,"created_time":1,"updated_time":0},{"id":5,"merchant_id":2,"member_id":3,"goods_id":2,"spec_id":5,"member_id":"2017090504321164749","refund_type":"物流配送","order_sn":"小鸿","package_id":"湖北省","refund_quantity":"测试赛是水水水水","amount":1,"shipment_fee":"系统关闭","apply_status":2,"status":1,"images":"发反反复复","operated_time":"1.25","finished_time":0.25,"shipmented_time":"1.00","applyed_time":100,"created_time":1,"updated_time":0}]';
        return Response::json($result, 200);
    }

    /**
     * 订单改价
     * @Author  qinyuan
     */
    public function updateOrderGoodsPrice(){
        $messages = array(
            'order_id.required' => json_encode(['errcode'=>40001,'errmsg'=>'订单Id不能为空！']),
            'order_id.numeric' => json_encode( ['errcode'=>40047,'errmsg'=>'订单数据id不合法！']),
            'goods.required' => json_encode(['errcode'=>80001,'errmsg'=>'商品Id不能为空！']),
            'goods.array' => json_encode( ['errcode'=>40048,'errmsg'=>'商品数据id不合法！']),
            'postage.required' => json_encode(['errcode'=>40071,'errmsg'=>'运费价格不能为空！']),
        );
        $rules = [
            'order_id' => 'required|numeric',
            'goods' => 'required|array',
            'postage' => 'required'
        ];
        $validator = Validator::make($this->params,$rules,$messages);

        if ( $validator->fails() ) {
            $error = $validator->errors()->first();
            return $error;
        }

        $orderId = trim($this->params["order_id"]);
        $goods = $this->params["goods"];
        $postage = (float)trim($this->params["postage"]);
		
		if(!$goods) {
			$result["errcode"] = 40073;
            $result["errmsg"] = "缺失商品参数";
            return Response::json($result, 200);
		}
		
        $orderGoods = OrderGoods::select('id', 'goods_id', 'spec_id', 'price', 'quantity', 'pay_price')
            ->where('order_id', $orderId)
            ->get()->toArray();
        if (empty($orderGoods)) {
            $result["errcode"] = 40073;
            $result["errmsg"] = "异常订单,请联系客服。";
            return Response::json($result, 200);
        }

        $OrderInfo = OrderInfo::select('id','order_sn', 'status', 'amount', 'goods_amount', 'shipment_fee','shipment_original_fee')->where(['id' => $orderId])->first();
        if (!$OrderInfo) {
            $result["errcode"] = 20014;
            $result["errmsg"] = "订单不存在!";
            return Response::json($result, 200);
        }

        if (!in_array($OrderInfo['status'], [ORDER_TOPAY])) {
            $result["errcode"] = 40078;
            $result["errmsg"] = "当前订单状态不能操作改价!";
            return Response::json($result, 200);
        }
		
		//转换格式
		$order_goods_ids = [];
		foreach($goods as $key => $ginfo) {
			$order_goods_ids[$ginfo['id']] = $ginfo['price'];
		}
		
		//还原order_goods价格
		$order_goods_ump_arr = [];
		$order_amount = $OrderInfo['amount']-$OrderInfo['shipment_fee'];
		$change_money = 0;
		foreach($orderGoods as $key => $ginfo) {
			$g_amount = OrderGoodsUmp::where(['order_id' => $orderId, 'ump_type' => 4,'goods_id' => $ginfo['goods_id'],'spec_id' => $ginfo['spec_id']])->pluck('amount');
			if($g_amount) {
				$orderGoods[$key]['pay_price'] = $ginfo['pay_price'] = $orderGoods[$key]['pay_price']-$g_amount;
			}
			if(isset($order_goods_ids[$ginfo['id']])) {
				$dis_amount = (float)$order_goods_ids[$ginfo['id']];
				if($dis_amount!=0) {
					if((float)sprintf('%0.2f',((float)$ginfo['pay_price']+$dis_amount))<0) {
						$result["errcode"] = 40075;
						$result["errmsg"] = "您修改的商品价格不合法，请重新修改。";
						return Response::json($result, 200);
					}
					$orderGoods[$key]['pay_price'] += $dis_amount;
					$change_money += $dis_amount;
					
					if($dis_amount<0) {
						$memo = "商品共优惠".abs($dis_amount)."元";
					}else{
						$memo = "商品共涨价".abs($dis_amount)."元";
					}
					$order_goods_ump_arr[] = [
						'order_id' 	=> $orderId,
						'goods_id'	=> $ginfo['goods_id'],
						'spec_id'	=>	$ginfo['spec_id'],
						'ump_id' 	=> 0,
						'ump_type' 	=> 4,
						'amount' 	=> $dis_amount,
						'memo' 		=> $memo,
					];
				}
			}
		}
		$u_amount = (float)OrderUmp::where(['order_id'=>$orderId,'ump_type'=>4])->pluck('amount');
		if($u_amount) {
			$order_amount = $order_amount-$u_amount;
		}
		$order_amount = $order_amount+$postage;
		
		$memo = '商品共优惠0元';
		if($change_money!=0) {
			$order_amount = $order_amount+$change_money;
			if($change_money<0) {
				$memo = "商品共优惠".abs($change_money)."元";
			}else{
				$memo = "商品共涨价".abs($change_money)."元";
			}
		}
		
		if($postage != $OrderInfo['shipment_fee']) {
			if($postage == 0) {
				$memo .= "(订单包邮)";
			}else{
				$memo .= "(运费{$postage}元)";
			}
		}
		
		$orderUmpData = [
			'order_id'		=> $orderId,
			'ump_type'		=> 4,
			'amount'		=> $change_money,
            'shipment_fee'	=> $postage,
			'memo'			=> $memo,
		];
        
		DB::beginTransaction();
		try{
			$orderData = [
				'amount'		=>	$order_amount,
				'shipment_fee'	=>	$postage,
			];
			$orderUpRes = OrderInfo::where(['id' => $orderId, 'merchant_id' => $this->merchant_id])->update($orderData);
			if(!$orderUpRes) {
				$result["errcode"] = 0;
				$result["errmsg"] = "操作异常,请稍后尝试。";
				return Response::json($result, 200);
			}

			OrderUmp::where(['order_id' => $orderId, 'ump_type' => 4])->update(['order_id'=>-$orderId]);
			OrderGoodsUmp::where(['order_id' => $orderId, 'ump_type' => 4])->update(['order_id'=>-$orderId]);
			
			$orderUmpId = OrderUmp::insertGetId($orderUmpData);
			if($orderUmpId) {
				if($order_goods_ump_arr) {
					foreach($order_goods_ump_arr as $key => $umpData) {
						OrderGoodsUmp::insert_data($umpData);
					}
				}
				foreach($orderGoods as $key => $ginfo) {
					OrderGoods::where(['id' => $ginfo['id'],'order_id' => $orderId])->update(['pay_price' => $ginfo['pay_price']]);
				}
			}
			DB::commit();
        }catch (\Exception $e) {
			DB::rollBack();
            $result["errcode"] = 1;
            $result["errmsg"] = "修改商品价格失败。".$e->getMessage();
            return Response::json($result, 200);
        }
		
        $result["errcode"] = 0;
        $result["errmsg"] = "修改商品价格成功。";
        return Response::json($result, 200);
    }

    /**
     * 增加,修改订单商家备注
     * @Author  qinyuan
     */
    public function editOrderRemarks(){
        $messages = array(
            'order_id.required' => json_encode( ['errcode'=>40001,'errmsg'=>'订单id不能为空']),
            'order_id.numeric' => json_encode( ['errcode'=>40047,'errmsg'=>'订单数据id不合法！']),
            'remark.required' => json_encode( ['errcode'=>40013,'errmsg'=>'备注信息不能为空！']),
        );
        $rules = [
            'order_id' => 'required|numeric',
            'remark' => 'required',
        ];
        $validator = Validator::make($this->params,$rules,$messages);

        if ( $validator->fails() ) {
            $error = $validator->errors()->first();
            return $error;
        }

        $orderId = trim($this->params["order_id"]);
        $remark = trim($this->params["remark"]);

        $res = OrderInfo::select()->where(['id'=>$orderId])->update(['remark'=>$remark]);

        if($res){
            $result["errcode"] = 0;
            $result["errmsg"] = "修改备注成功。";

        }else{
            $result["errcode"] = 1;
            $result["errmsg"] = "修改备注失败。";
        }

        return Response::json($result, 200);
    }

    /**
     * 修改订单地址
     * @Author  qinyuan
     */
    public function editOrderAddress(){
        $messages = array(
            'order_id.required' => json_encode( ['errcode'=>40001,'errmsg'=>'订单id不能为空!']),
            'order_id.numeric' => json_encode( ['errcode'=>40047,'errmsg'=>'订单数据id不合法！']),
            'province.required' => json_encode( ['errcode'=>40015,'errmsg'=>'省份id不能为空!']),
            'city.required' => json_encode( ['errcode'=>40016,'errmsg'=>'市区id不能为空!']),
            'district.required' => json_encode( ['errcode'=>40017,'errmsg'=>'地区id不能为空!']),
            'address.required' => json_encode( ['errcode'=>40018,'errmsg'=>'街道地址不能为空!']),
            'province_name.required' => json_encode( ['errcode'=>40019,'errmsg'=>'联系地址省份名称不能为空!']),
            'city_name.required' => json_encode( ['errcode'=>40020,'errmsg'=>'联系地址市区名称不能为空!']),
            'district_name.required' => json_encode( ['errcode'=>40021,'errmsg'=>'联系地址地区不能为空!']),
            'zipcode.required' => json_encode( ['errcode'=>40022,'errmsg'=>'邮编不能为空!']),
            'consignee.required' => json_encode( ['errcode'=>40023,'errmsg'=>'收件人姓名不能为空!']),
            'mobile.required' => json_encode( ['errcode'=>40024,'errmsg'=>'手机号码不能为空!']),
            'mobile.regex' => json_encode( ['errcode'=>40025,'errmsg'=>'手机号码不合法!']),
        );

        $rules = [
            'order_id' => 'required|numeric',
            'province' => 'required|numeric',
            'city' => 'required|numeric',
            'district' => 'required|numeric',
            'address' => 'required',
            'province_name' => 'required',
            'city_name' => 'required',
            'district_name' => 'required',
            'zipcode' => 'required',
            'consignee' => 'required',
            'mobile' => "required|numeric|regex:/^1[34578][0-9]{9}$/",
        ];
        $validator = Validator::make($this->params,$rules,$messages);

        if ( $validator->fails() ) {
            $error = $validator->errors()->first();
            return $error;
        }
        $orderId = trim($this->params["order_id"]);
        $province = trim($this->params["province"]);
        $city = trim($this->params["city"]);
        $district = trim($this->params["district"]);
        $address = trim($this->params["address"]);
        $province_name = trim($this->params["province_name"]);
        $city_name = trim($this->params["city_name"]);
        $district_name = trim($this->params["district_name"]);
        $zipcode = trim($this->params["zipcode"]);
        $consignee = trim($this->params["consignee"]);
        $mobile = trim($this->params["mobile"]);

        $countryInfo = Region::select()->where(['id'=>$province])->first();

        $updateData = array(
            'country'=>$countryInfo['parent_id'],
            'province'=>$province,
            'city'=>$city,
            'district'=>$district,
            'country_name'=>$countryInfo['title'],
            'province_name'=>$province_name,
            'city_name'=>$city_name,
            'district_name'=>$district_name,
            'address'=>$address,
            'zipcode'=>$zipcode,
            'consignee'=>$consignee,
            'mobile'=>$mobile,
        );
        $res = OrderAddr::select()->where(['order_id'=>$orderId])->update($updateData);

        if($res){
            $result["errcode"] = 0;
            $result["errmsg"] = "修改收货地址成功。";

        }else{
            $result["errcode"] = 1;
            $result["errmsg"] = "修改收货地址失败。";
        }

        return Response::json($result, 200);
    }

    /**
     * 取消订单
     * @Author  Lujingjing
     */
    public function canceOrder(){
        if(!isset($this->params["order_id"]) || empty($this->params["order_id"])){
            $result["errcode"] = 40001;
            $result["errmsg"] = "订单Id不能为空。";

            return Response::json($result, 200);
        }

        if(!isset($this->params["explain"]) || empty($this->params["explain"])){
            $result["errcode"] = 40020;
            $result["errmsg"] = "订单取消原因不能为空。";

            return Response::json($result, 200);
        }

        $orderId = trim($this->params["order_id"]);
        $explain = trim($this->params["explain"]);

        $rs = OrderInfo::select()->where("id",$orderId)->update(["status"=>3,"explain"=>$explain]);
        if($rs){
            $result["errcode"] = 0;
            $result["errmsg"] = "订单关闭成功。";

            //订单缓存
            $order_data = OrderInfo::get_data_by_id($orderId,$this->merchant_id,'id,order_type');
            //拼团订单
            if($order_data['order_type']==ORDER_FIGHTGROUP) {	
                $this->fightgroupService->fightgroupJoinCancel($order_data);
            }
            //发送到队列
            $job = new OrderCancel($orderId,Auth::user()->merchant_id);
            $this->dispatch($job);
            
            
        }else{
            $result["errcode"] = 40007;
            $result["errmsg"] = "订单关闭失败。";
        }

        return Response::json($result, 200);
    }

    /**
     * 选择运单模板
     * @Author  qinyuan
     */
    public function getWayBill(){
        $data = $this->service->getWaybills();
        return Response::json($data, 200);
    }

    /**
     * 发货
     * @Author  qinyuan
     */
    public function shippingGoods(){
        $messages = array(
            'order_id.required' => json_encode( ['errcode'=>40001,'errmsg'=>'订单id不能为空!']),
            'order_id.numeric' => json_encode( ['errcode'=>40047,'errmsg'=>'订单数据id不合法！']),
            'goods_info.required' => json_encode( ['errcode'=>40026,'errmsg'=>'商品信息不能为空!']),
            'goods_info.array' => json_encode( ['errcode'=>40027,'errmsg'=>'商品信息数据格式不合法!']),
            'is_no_express.required' => json_encode( ['errcode'=>40028,'errmsg'=>'发货方式不能为空!']),
            'logis_code.required_if' => json_encode( ['errcode'=>40040,'errmsg'=>'物流公司代码不能为空!']),
            'logis_no.required_if' => json_encode( ['errcode'=>40041,'errmsg'=>'运单号不能为空!']),
            'logis_name.required_if' => json_encode( ['errcode'=>40042,'errmsg'=>'物流公司名称不能为空!']),
            'is_no_express.numeric' => json_encode( ['errcode'=>40029,'errmsg'=>'发货方式数据格式不合法!']),
            'subscribe_id.required_if' => json_encode( ['errcode'=>40042,'errmsg'=>'预约配送id不能为空!']),
        );
        $rules = [
            'goods_info' => 'required|array',
            'order_id' => 'required|numeric',
            'is_no_express' => 'required|numeric',
            'logis_code' => 'required_if:is_no_express,0',
            'logis_name' => 'required_if:is_no_express,0',
            'logis_no' => 'required_if:is_no_express,0',
            'subscribe_id' => 'required_if:is_no_express,2',
        ];
        $validator = Validator::make($this->params,$rules,$messages);

        if ( $validator->fails() ) {
            $error = $validator->errors()->first();
            return $error;
        }

        $orderId = trim($this->params["order_id"]);
        $goodsInfo = $this->params["goods_info"];
        $isNoExpress = trim($this->params["is_no_express"]);
        $logisCode = isset($this->params["logis_code"]) && !empty($this->params["logis_code"]) ? trim($this->params["logis_code"]) : '';
        $logisName = isset($this->params["logis_name"]) && !empty($this->params["logis_name"]) ? trim($this->params["logis_name"]) : '';
        $logisNo = isset($this->params["logis_no"]) && !empty($this->params["logis_no"]) ? trim($this->params["logis_no"]) : '';
        $subscribe_id = isset($this->params["subscribe_id"]) && !empty($this->params["subscribe_id"]) ? trim($this->params["subscribe_id"]) : 0;
        if($isNoExpress == 2){ //骑手专送（dada）
            $logisCode = '';
            $logisName =  '骑手专送';
            $logisNo =  '';
        }
        $orderInfo = OrderInfo::select()->where(['id'=>$orderId])->first();
        if(empty($orderInfo)){
            $result["errcode"] = 20014;
            $result["errmsg"] = "订单不存在!";

            return Response::json($result, 200);
        }

        if(!in_array($orderInfo['status'],[ORDER_TOSEND,ORDER_SUBMITTED])){
            $msg = '订单状态:'.OrderService::convertOrderStatus($orderInfo['status']);
            if($orderInfo['refund_status'] == 1){
                $msg = "订单维权中";
            }

            $msg .= '无法发货';
            $result["errcode"] = 40068;
            $result["errmsg"] = $msg;

            return Response::json($result, 200);
        }

        if($orderInfo['merchant_id'] != $this->merchant_id){
            $result["errcode"] = 40069;
            $result["errmsg"] = "非本店订单!";

            return Response::json($result, 200);
        }

        if($orderInfo['order_type'] == 2){//验证团购订单是否可发货
            $check = $this->fightgroupService->fightgroupJoinOrder($orderId);

            if(!empty($check['data']) && $check['data']['type'] == 0){
                $result["errcode"] = 41004;
                $result["errmsg"] = "该拼团订单不可发货！";

                return Response::json($result, 200);
            }
        }

        $temp = array();
        foreach($goodsInfo as $k=>$v){

            $orderGoods = OrderGoods::select()->where('id',$k)->first();

            $refundQuantity = OrderRefund::where(array('order_id' => $orderId, 'goods_id' => $orderGoods['goods_id'],'spec_id'=>$orderGoods['spec_id'], 'package_id' => 0))
                ->whereNotIn('status', [REFUND_CANCEL, REFUND_CLOSE,REFUND_MER_CANCEL])//wangshiliang@dodoca.com -> 'spec_id'=>$orderGoods['spec_id']
                ->sum('refund_quantity');

            $delivery_num = $orderGoods['quantity'] - $orderGoods['shipped_quantity'] - $refundQuantity;
            if ($delivery_num > 0) {
                $orderGoods['edit_quantity'] = $delivery_num > $v ? $v : $delivery_num;
                $temp[] = $orderGoods;
            }
        }

        if ($temp) {
            //查找物流是否系统自带，否则是自定义
            $orderPackageData = array(
                'order_id' => $orderInfo['id'],
                'order_sn' => $orderInfo['order_sn'],
                'logis_no' => $logisNo,
                'logis_name' => $logisName,
                'logis_code' => $logisCode,
                'is_no_express' => $isNoExpress,
            );

            $shippment_id = 0;
            $delivery_company = DeliveryCompany::where('name', $logisName)->first();
            if ($delivery_company) {
                //商家是否有权选择这个物流
                $merchant_delivery = MerchantDelivery::where(array('merchant_id' => $this->merchant_id, 'delivery_company_id' => $delivery_company['id']))->first();
                if ($merchant_delivery) {
                    $shippment_id = $delivery_company['id'];
                }
            }

            $order_type = $orderInfo['order_type'];
            try {
                DB::transaction(function () use ($orderId, $temp, $orderPackageData, $order_type, $shippment_id,$orderInfo,$subscribe_id) {
                    //先更新订单包裹表数据
                    $package_id = OrderPackage::insertGetId($orderPackageData);
                    if (!is_int($package_id)) {
                        $result["errcode"] = 40070;
                        $result["errmsg"] = "发货数据入库失败!";
                        return Response::json($result, 200);
                    }
                    //更新订单商品包裹表数据
                    foreach ($temp as $value) {
                        OrderGoods::where(['id' => $value['id']])->increment('shipped_quantity', $value['edit_quantity']);
                        //更新对应订单商品的状态
                        $orderGoodsInfo = OrderGoods::select('id', 'shipped_quantity', 'refund_quantity', 'quantity')->where(['id' => $value['id']])->first();
                        if ($orderGoodsInfo) {
                            if ($orderGoodsInfo['quantity'] == ($orderGoodsInfo['shipped_quantity'] + $orderGoodsInfo['refund_quantity'])) {
                                OrderGoods::where(['id' => $orderGoodsInfo['id']])->update(['status' => ORDER_SEND]);
                            }
                        }
                        //更新订单包裹子表,商品分化数量大于0才写发货子表
                        if($value['edit_quantity']>0){
                            $orderPackageChildData = array(
                                'package_id'     =>$package_id,
                                'order_id'       =>$orderId,
                                'order_goods_id'       =>$value['id'],
                                'shipment_id'    =>$shippment_id,
                                'quantity'       =>$value['edit_quantity'],
                            );
                            $package_item_id = OrderPackageItem::insertGetId($orderPackageChildData);
                            if (!is_int($package_item_id)) {
                                $result["errcode"] = 40089;
                                $result["errmsg"] = "订单包裹子表数据入库失败!";
                            
                                return Response::json($result, 200);
                            }
                        }
                    }
                    //统计当前订单购买的总件数
                    $order_quantity = OrderGoods::where(['order_id' => $orderId])->sum('quantity');
                    //统计订单已发货的件数
                    $shipped_quantity = OrderGoods::where(['order_id' => $orderId])->sum('shipped_quantity');
                    //统计已成功退款的件数 去除包裹里退的件数
                    $refund_quantity = OrderRefund::where(['order_id' => $orderId, 'status' => REFUND_FINISHED, 'package_id' => 0])->sum('refund_quantity');
                    if ($order_quantity == ($shipped_quantity + $refund_quantity)) {
                        //更新订单状态
                        OrderInfo::select()->where(['id' => $orderId])->update(['status' => ORDER_SEND,'shipments_time'=>date("Y-m-d H:i:s")]);

                    }
                    if($orderPackageData['is_no_express'] == 2){//达达发送订单
                        $info = ExpressOrder::get_one('id',$subscribe_id);
                        if(!isset($info['id']) || $info['order_id'] != $orderId){
                            throw  new \Exception('subscribe_id 参数有误');
                        }
                        $expressService = new ExpressService();
                        $dada = (new DadaOrder())->setConfig(Config::get('express.app_key'), Config::get('express.app_secret'), $expressService->getDadaMerchant($this->merchant_id),$expressService->getEnv($this->merchant_id));
                        $response = $dada -> releaseOrder($info['delivery_sn']);
                        if(!isset($response['code']) ||  !in_array($response['code'],[0,2062,2063,2064])){
                            \Log::info('admin_order_shippingGoods:[1] '.json_encode($response));
                            throw  new \Exception(isset($response['msg'])?$response['msg']:'网络通信有误');
                        }
                        if($response['code'] == 2062 || $response['code'] == 2064){
                            //停留时间过长 已过期  重新来
                            $order_sn = explode('_',$info['dada_sn']);
                            $response = $dada ->order($info['shop_id'],$info['dada_sn'],$info['city'],$info['price'],$info['is_prepay'],$info['receiver'],$info['address'],$info['lat'],$info['lng'],$info['mobile'],$expressService->getCallbackUrl(),[
                                'mark'      => $info['remark'],
                                'mark_no'   => $order_sn[0],
                                'insurance' => $info['insurance'],
                                'delivery'  => $info['delivery']
                            ],'get');
                            if(!isset($response['code']) || !isset($response['result']['fee']) ||   $response['code'] != 0){
                                \Log::info('admin_order_shippingGoods:[2] '.json_encode($response));
                                throw  new \Exception(isset($response['msg'])?$response['msg']:'网络通信有误');
                            }
                            $update = [
                                'total'=> $response['result']['fee'] ,
                                'distance' => $response['result']['distance'] ,
                                'freight' =>  $response['result']['deliverFee'],
                                'delivery_sn' =>  $response['result']['deliveryNo']
                            ];
                            if(isset($response['result']['insuranceFee'])) $update['insurance_price'] =  $response['result']['insuranceFee'];
                            if(isset($response['result']['tips']))         $update['price_tip']       =  $response['result']['tips'];
                            if(isset($response['result']['couponFee']))    $update['coupon']          =  $response['result']['couponFee'];

                        }else{
                            $update = [];
                        }
                        $update['package_id'] = $package_id;
                        $update['delivery_time'] = date('Y-m-d H:i:s');
                        ExpressOrder::update_data('id',$subscribe_id,$update);
                    }
					//发送发货消息模板
					$job = new WeixinMsgJob(['order_id'=>$orderInfo['id'],'merchant_id'=>$orderInfo['merchant_id'],'delivery_id'=>$package_id,'type'=>'delivery']);
					$this->dispatch($job);
					
                });
            } catch (\Exception $e) {
//                var_dump($e);
                $result = array('errcode' => 1, 'errmsg' => '商品发货失败'.$e->getMessage());
                return Response::json($result, 200);
            }
            //小票机打印
            if($orderId){
                $orderPrint = new OrderPrintService();
                $orderPrint->printOrder($orderId,$orderInfo['merchant_id']);
            }
            $result = array('errcode' => 0, 'errmsg'=>'商品发货成功');
            return Response::json($result, 200);
        } else {
            $result = array('errcode' => 1, 'errmsg' => '该订单有待处理的退款申请');//无发货商品');
            return Response::json($result, 200);
        }
    }

    /**
     * 发货记录修改
     * @Author  qinyuan
     */
    public function editOrderShipping(){
        $messages = array(
            'package_id.required' => json_encode( ['errcode'=>40043,'errmsg'=>'物流包裹id不能为空!']),
            'package_id.numeric' => json_encode( ['errcode'=>40044,'errmsg'=>'物流包裹id数据类型不合法!']),
            'logis_code.required' => json_encode( ['errcode'=>40040,'errmsg'=>'物流公司代码不能为空!']),
            'logis_no.required' => json_encode( ['errcode'=>40041,'errmsg'=>'运单号不能为空!']),
            'logis_name.required' => json_encode( ['errcode'=>40042,'errmsg'=>'物流公司名称不能为空!']),
        );
        $rules = [
            'package_id' => 'required|numeric',
            'logis_code' => 'required',
            'logis_name' => 'required',
            'logis_no' => 'required',
        ];
        $validator = Validator::make($this->params,$rules,$messages);

        if ( $validator->fails() ) {
            $error = $validator->errors()->first();
            return $error;
        }

        $packageId = trim($this->params["package_id"]);
        $logisCode = trim($this->params["logis_code"]);
        $logisName = trim($this->params["logis_name"]);
        $logisNo = trim($this->params["logis_no"]);

        $res = OrderPackage::select()->where(['id'=>$packageId])->update(['logis_code'=>$logisCode,'logis_name'=>$logisName,'logis_no'=>$logisNo]);

        if($res){
            $result["errcode"] = 0;
            $result["errmsg"] = "物流信息修改成功。";

        }else{
            $result["errcode"] = 1;
            $result["errmsg"] = "物流信息修改失败。";
        }

        return Response::json($result, 200);
    }

    /**
     * 批量发货记录
     * @Author  qinyuan
     */
    public function shippingGoodsList(){
//        $data = '{"page":1,"total_page":10,"list":[{"id":5,"order_id":25564,"order_sn":"E2017090504321164749","logis_name":"1.00","logis_no":1,"created_time":"2017/9/7 17:9:4","remark":0}]}';
//        return $data;
        //$data = OrderPackage::select()->where()->get()->toArray();
        $params['order_sn'] = isset($this->params["order_sn"]) && !empty($this->params["order_sn"]) ? trim($this->params["order_sn"]) : '';
        $params['logis_no'] = isset($this->params["logis_no"]) && !empty($this->params["logis_no"]) ? trim($this->params["logis_no"]) : '';
        $params['status'] = isset($this->params["status"]) && $this->params["status"] != '' ? trim($this->params["status"]) : '';
        $params['created_time'] = isset($this->params["created_time"]) && !empty($this->params["created_time"]) ? trim($this->params["created_time"]) : '';
        $params['page'] = isset($this->params["page"]) && !empty($this->params["page"]) ? intval($this->params["page"]) : 1;
        $params['pagesize'] = isset($this->params["pagesize"]) && !empty($this->params["pagesize"]) ? intval($this->params["pagesize"]) : 10;

        $list = $this->service->getShippingGoodsList($params);

        return Response::json($list, 200);
    }

    /**
     * 批量发货记录
     * @Author  qinyuan
     */
    public function shippingGoodsExcel(){
        $csv = $_FILES['file'];

        $data = $this->service->importShippingCsv($csv);

        return Response::json($data, 200);
    }

    /**
     * 批量发货
     * @Author  qinyuan
     */
    public function postBatchShipments(){
        $result = $this->service->batchShipments($this->params);

        return Response::json(array('errcode'=>$result['errcode'], 'errmsg'=>$result['errmsg']));
    }

    /**
     * 退货处理
     * @Author  qinyuan
     */
    public function updateOrderRefund(){
        $messages = array(
            'order_id.required' => json_encode( ['errcode'=>40001,'errmsg'=>'订单id不能为空']),
            'order_id.numeric' => json_encode( ['errcode'=>40047,'errmsg'=>'订单数据id不合法！']),
            'refund_id.required' => json_encode( ['errcode'=>40060,'errmsg'=>'退款id不能为空']),
            'refund_id.numeric' => json_encode( ['errcode'=>40061,'errmsg'=>'退款id不合法！']),
            'apply_type.required' => json_encode( ['errcode'=>40062,'errmsg'=>'申请方式不能为空！']),
            'apply_type.numeric' => json_encode( ['errcode'=>40063,'errmsg'=>'申请方式数据不合法！']),
            'refund_type.required' => json_encode( ['errcode'=>40064,'errmsg'=>'退款类型不能为空！']),
            'refund_type.numeric' => json_encode( ['errcode'=>40065,'errmsg'=>'退款类型数据不合法！']),
            'reason.required_if' => json_encode( ['errcode'=>40066,'errmsg'=>'拒绝理由不能为空！']),
        );
        $rules = [
            'order_id' => 'required|numeric',
            'refund_id' => 'required|numeric',
            'apply_type' => 'required|numeric',
            'refund_type' => 'required|numeric',
            'reason' => 'required_if:apply_type,2',
        ];
        $validator = Validator::make($this->params,$rules,$messages);

        if ( $validator->fails() ) {
            $error = $validator->errors()->first();
            return $error;
        }

        $orderId = trim($this->params["order_id"]);
        $refundId = trim($this->params["refund_id"]);
        $applyType = trim($this->params["apply_type"]);
        $refundType = trim($this->params["refund_type"]);
        $reason = isset($this->params["reason"]) && !empty($this->params["reason"]) ? trim($this->params["reason"]) : '';
        $address = isset($this->params["address"]) && !empty($this->params["address"]) ? trim($this->params["address"]) : '';

        if($applyType == 1 && $refundType == 1){
            $result["errcode"] = 1;
            $result["errmsg"] = "收货地址不能为空!";
            return Response::json($result, 200);
        }

        try{
            $data = array(
                'refund_type'=>$refundType,
                'operated_time'=>date('Y-m-d H:i:s')
            );
            if($applyType == 1){//同意退款
                $data['apply_status'] = REFUND_AGREE;
                $data['status'] = REFUND_AGREE;
                if($refundType == 1){//退款退货
                    $data['address'] = $address;
                }
            }else{//拒绝退款
                $data['apply_status'] = REFUND_REFUSE;
                $data['status'] = REFUND_REFUSE;
                $data['reason'] = $reason;
            }

            DB::transaction(function () use($data,$orderId,$refundId){
                OrderRefund::select()->where(['id'=>$refundId])->whereIn('apply_status',[REFUND,REFUND_AGAIN])->update($data);

                //修改订单状态
                OrderInfo::where(['id'=>$orderId])->update(['refund_status' => 1]);
            });

            $result["errcode"] = 0;
            $result["errmsg"] = "退款操作成功。";
            return Response::json($result, 200);

        }catch (\Exception $e) {
//            var_dump($e);
            $result["errcode"] = 1;
            $result["errmsg"] = "退款操作失败。";
            return Response::json($result, 200);
        }
    }

    /**
     * 延期收货
     * @Author  qinyuan
     */
    public function updateOrderExtendDays(){
        $messages = array(
            'order_id.required' => json_encode( ['errcode'=>40001,'errmsg'=>'订单id不能为空']),
            'order_id.numeric' => json_encode( ['errcode'=>40047,'errmsg'=>'订单数据id不合法！']),
            'days.required' => json_encode( ['errcode'=>40045,'errmsg'=>'延长收货天数不能为空！']),
            'days.numeric' => json_encode( ['errcode'=>40046,'errmsg'=>'延长收货天数数据不合法！']),
        );
        $rules = [
            'order_id' => 'required|numeric',
            'days' => 'required|numeric',
        ];
        $validator = Validator::make($this->params,$rules,$messages);

        if ( $validator->fails() ) {
            $error = $validator->errors()->first();
            return $error;
        }

        $orderId = trim($this->params["order_id"]);
        $days = trim($this->params["days"]);


        $res = OrderInfo::where(['id'=>$orderId])->update(['extend_days'=>$days]);

        if($res){
            $result["errcode"] = 0;
            $result["errmsg"] = "延长收货天数修改成功。";

        }else{
            $result["errcode"] = 1;
            $result["errmsg"] = "延长收货天数修改失败。";
        }

        return Response::json($result, 200);
    }

    /**
     * 订单设置
     * @Author  qinyuan
     */
    public function orderSet(){
        $messages = array(
            'minutes.required' => json_encode( ['errcode'=>40001,'errmsg'=>'订单过期时间不能为空']),
            'minutes.numeric' => json_encode( ['errcode'=>40047,'errmsg'=>'订单过期时间数据不合法！']),
            'minutes.min' => json_encode( ['errcode'=>40047,'errmsg'=>'订单过期时间不得小于20分钟']),
            'auto_finished_time.required' => json_encode( ['errcode'=>40045,'errmsg'=>'自动收货时间不能为空！']),
            'auto_finished_time.numeric' => json_encode( ['errcode'=>40046,'errmsg'=>'自动收货时间数据不合法！']),
            'auto_finished_time.min' => json_encode( ['errcode'=>40046,'errmsg'=>'自动收货时间不得低于7天！']),
        );
        $rules = [
            'minutes' => 'required|numeric|min:20',
            'auto_finished_time' => 'required|numeric|min:7',
        ];
        $validator = Validator::make($this->params,$rules,$messages);

        if ( $validator->fails() ) {
            $error = $validator->errors()->first();
            return $error;
        }

        $minutes = intval($this->params["minutes"]);
        $autoFinishedTime = intval($this->params["auto_finished_time"]);
        $fields = isset($this->params["fields"]) && !empty($this->params["fields"]) ? $this->params["fields"] : array();

        $setting = MerchantSetting::get_data_by_id($this->merchant_id);
		
        $data = array(
            'minutes'=> $minutes,
            'auto_finished_time'=> $autoFinishedTime,
            'fields'=> json_encode($fields),
        );

        if(!empty($setting)){//更新
            $res = MerchantSetting::update_data($this->merchant_id,$data);
        }else{//新增
			$data['merchant_id'] = $this->merchant_id;
			$data['warning_stock'] = 10;
            $res = MerchantSetting::insert_data($data);
        }

        $result["errcode"] = 0;
        $result["errmsg"] = "订单设置操作成功。";

        return Response::json($result, 200);
    }

    /**
     * 獲取訂單設置信息
     * @author qinyuan
     */
    public function getOrderSet(){
        $setting = MerchantSetting::select()->where(['merchant_id'=>$this->merchant_id])->first();
        $setting['fields'] = json_decode($setting['fields'],true);

        $result["errcode"] = 0;
        $result["errmsg"] = "订单设置操作成功。";
        $result['data'] = $setting;
        return Response::json($result, 200);
    }

    /**
     * 订单评价加星
     * @Author  qinyuan
     */
    public function updateOrderCommentScore(){
        $messages = array(
            'order_id.required' => json_encode( ['errcode'=>40001,'errmsg'=>'订单id不能为空']),
            'order_goods_id.required' => json_encode(['errcode'=>80001,'errmsg'=>'商品Id不能为空！']),
            'score.required' => json_encode( ['errcode'=>40048,'errmsg'=>'评分值不能为空！']),
            'order_id.numeric' => json_encode( ['errcode'=>40047,'errmsg'=>'订单数据id不合法！']),
            'order_goods_id.numeric' => json_encode( ['errcode'=>40048,'errmsg'=>'商品数据id不合法！']),
            'score.numeric' => json_encode( ['errcode'=>40048,'errmsg'=>'评分值数据不合法！']),
        );
        $rules = [
            'order_id' => 'required|numeric',
            'order_goods_id' => 'required|numeric',
            'score' => 'required|numeric',
        ];
        $validator = Validator::make($this->params,$rules,$messages);

        if ( $validator->fails() ) {
            $error = $validator->errors()->first();
            return $error;
        }

        $orderId = trim($this->params["order_id"]);
        $orderGoodsId = trim($this->params["order_goods_id"]);
        $score = trim($this->params["score"]);

        $res = OrderComment::select()->where(['order_id'=>$orderId,'order_goods_id'=>$orderGoodsId])->update(['score'=>$score]);

        if($res){
            $result["errcode"] = 0;
            $result["errmsg"] = "订单评分操作成功。";

        }else{
            $result["errcode"] = 1;
            $result["errmsg"] = "订单评分操作失败。";
        }

        return Response::json($result, 200);
    }

    /**
     * 获取订单物流相关信息
     * @author qinyuan
     */
    public function orderTradeInfo(){
        $messages = array(
            'order_id.required' => json_encode( ['errcode'=>40001,'errmsg'=>'订单id不能为空']),
            'order_id.numeric' => json_encode( ['errcode'=>40047,'errmsg'=>'订单数据id不合法！']),
        );
        $rules = [
            'order_id' => 'required|numeric',
        ];
        $validator = Validator::make($this->params,$rules,$messages);

        if ( $validator->fails() ) {
            $error = $validator->errors()->first();
            return $error;
        }

        $orderId = trim($this->params["order_id"]);
        //订单表
        $OrderInfo = OrderInfo::where(['id'=>intval($orderId),'merchant_id'=>$this->merchant_id])->first();
        if(!$OrderInfo) {
            $result["errcode"] = 20014;
            $result["errmsg"] = "订单不存在!";

            return Response::json($result, 200);
        }
        //商户物流公司关联表
        $deliveryInfo = MerchantDelivery::select('delivery_company.*')->where('merchant_id',$this->merchant_id)
            ->join('delivery_company','delivery_company.id','=','merchant_delivery.delivery_company_id')
            ->get()->toArray();
        foreach($deliveryInfo as $k=>$v){
            $deliveryInfo[$k]['print_default'] = json_decode($deliveryInfo[$k]['print_default'],true);
        }
        //订单收货地址表
        $addressInfo = OrderAddr::select('*')->where('order_id', $orderId)->first();

        //订单商品信息
        $goodsInfo = OrderGoods::select('*')->where(array('order_id'=>$orderId))->get()->toArray();

        if(!empty($goodsInfo)){//获取商品的物流信息
            $temp = array();
            foreach($goodsInfo as $k=>$v){
                //订单包裹表
                $packageInfo = OrderPackage::select('order_package.id as package_id','order_package.logis_code','order_package.logis_name','order_package.logis_no','order_package.is_no_express','order_package_item.quantity')
                        ->leftJoin("order_package_item","order_package_item.package_id","=","order_package.id")
                        ->where(array('order_package.order_id'=>$orderId,'order_package_item.order_goods_id'=>$v['id']))
                        ->get()->toArray();
                $canShipNum = $v['quantity']-$v['shipped_quantity']-$v['refund_quantity'];
                $goodsInfo[$k]['can_ship_quantity'] = $canShipNum;
                $goodsInfo[$k]['package_arr'] = array();
                //订单商品营销表
                $discount = OrderGoodsUmp::where(['order_id'=>$orderId,'goods_id'=>$v['goods_id'],'ump_type'=>4])->first(['amount']);
                $goodsInfo[$k]['discount_amount'] = !empty($discount['amount']) ? $discount['amount'] : 0;
                $goodsInfo[$k]['package_arr'] = $packageInfo;
            }
        }

        $data = [
            'deliveryInfo' => $deliveryInfo,
            'addressInfo' => $addressInfo,
            'goodsInfo' => $goodsInfo,
            'memo' => $OrderInfo['memo'],
            'remark' => json_decode($OrderInfo['extend_info'],true),
        ];

        $result["errcode"] = 0;
        $result["errmsg"] = "订单评分操作成功。";
        $result["data"] = $data;

        return Response::json($result, 200);
    }

    /**
     * 获取订单设置里面自定义留言的文本格式
     * @author qinyuan
     */
    public function getOrderSetMsgFormat(){
        $data = config("config.order_custom_message_format");

        return Response::json($data, 200);
    }

    /**
     * 打印发货单
     * @author qinyuan
     */
    public function printDelivery() {
        $messages = array(
            'order_ids.required' => json_encode( ['errcode'=>40001,'errmsg'=>'订单id不能为空']),
        );
        $rules = [
            'order_ids' => 'required',
        ];
        $validator = Validator::make($this->params,$rules,$messages);

        if ( $validator->fails() ) {
            $error = $validator->errors()->first();
            return $error;
        }

        $order_ids  = isset($this->params['order_ids']) && $this->params['order_ids'] ? explode(',', $this->params['order_ids']) : '';

        $shopInfo = Shop::where(array('merchant_id'=>$this->merchant_id))->firstOrFail();

        $orderList = OrderInfo::select('id', 'nickname', 'order_sn','amount', 'remark', 'created_time', 'goods_amount', 'shipment_fee', 'memo','refund_status')
            ->where(array('merchant_id' => $this->merchant_id))
            ->whereIn('id', $order_ids)
            ->whereIn('status', [ORDER_TOSEND, ORDER_SUBMITTED, ORDER_FORPICKUP,ORDER_SEND])
            ->orderBy('id','DESC')
            ->get();

        if(empty($orderList)){
            $result["errcode"] = 20014;
            $result["errmsg"] = "订单不存在!";

            return Response::json($result, 200);
        }

        $orders = array();
        foreach($orderList as &$val){

            $consignee = OrderAddr::select('consignee', 'mobile', 'country_name', 'province_name', 'city_name', 'district_name', 'address')->where('order_id',$val['id'])->first();

            $val['consignee'] = $consignee['consignee'];
            $val['mobile'] = $consignee['mobile'];
            $val['country_name'] = $consignee['country_name'];
            $val['province_name'] = $consignee['province_name'];
            $val['city_name'] = $consignee['city_name'];
            $val['district_name'] = $consignee['district_name'];
            $val['address'] = $consignee['address'];

            // 商品信息
            $orderGoods = OrderGoods::select('goods_id','goods_name','goods_sn','quantity','props','price','pay_price','refund_status')
                ->where('order_id',$val['id'])
                ->get()
                ->toArray();
            $val['goods'] = array();
            foreach($orderGoods as &$goods){
                // 货号（商家编码）
                if(!$goods['goods_sn']){
                    $tmpgoods = Goods::select('goods_sn')->where('id',$goods['goods_id'])->first();
                    $goods['goods_sn'] = $tmpgoods['goods_sn'];
                }

                if($val['refund_status']==1 && $goods['refund_status']>0){
                    $orderRefund = OrderRefund::select('refund_quantity','refund_type','status')
                        ->where(['goods_id'=>$goods['goods_id'],'order_id'=>$val['id']])
                        ->first();
                    if($orderRefund) {
                        $goods['refund_info'] = $orderRefund->toArray();
                    }
                }
            }
            $val['goods'] = $orderGoods;

            // 订单优惠信息
            $ordersUmp = OrderUmp::select('ump_type', 'amount')->where('order_id', $val['id'])->where('amount', '!=', '0.00')->get()->toArray();
            $ump_detail = '';
            if($ordersUmp){
                foreach($ordersUmp as $ump){
                    switch($ump['ump_type']){
                        case 1:
                            $ump_detail = "{$ump['amount']}（会员卡优惠） + ";
                            break;
                        case 2:
                            $ump_detail .= "{$ump['amount']}（优惠券） + ";
                            break;
                        case 3:
                            $ump_detail .= "{$ump['amount']}（积分抵扣） + ";
                            break;
                        case 4:
                            $ump_detail .= "{$ump['amount']}（商家改价） + ";
                            break;
                        case 5:
                            $ump_detail .= "{$ump['amount']}（拼团） + ";
                            break;
                        case 6:
                            $ump_detail .= "{$ump['amount']}（秒杀） + ";
                            break;
                        default:
                            break;
                    }
                }
                if($ump_detail) {
                    $length = strlen($ump_detail);
                    $ump_detail = substr_replace($ump_detail,'',$length - 3,$length);
                }
            }

            $val['ump_detail'] = $ump_detail;
            $val['created_at'] = date("Y-m-d H:i",time());
            $val['payment_name'] = '微信支付';

            $orders[$val['order_sn']] = $val;
        }

        return view('print.print_delivery')->with('orders',$orders)->with('shop',$shopInfo);
    }

    /**
     * 打印快递单接口
     * @author qinyuan
     */
    public function printExpress(){
        $data = array();
        $messages = array(
            'order_ids.required' => json_encode( ['errcode'=>40001,'errmsg'=>'订单id不能为空']),
            'waybill_tpl_id.required' => json_encode( ['errcode'=>40085,'errmsg'=>'运单模板id不能为空']),
            'logis_begin_no.required' => json_encode( ['errcode'=>40086,'errmsg'=>'起始运单号不能为空']),
        );
        $rules = [
            'order_ids' => 'required',
            'waybill_tpl_id' => 'required',
            'logis_begin_no' => 'required',
        ];
        $validator = Validator::make($this->params,$rules,$messages);

        if ( $validator->fails() ) {
            $error = $validator->errors()->first();
            return $error;
        }

        $order_ids  = isset($this->params['order_ids']) && $this->params['order_ids'] ? $this->params['order_ids'] : '';
        //运单模板
        $waybill_id = isset($this->params['waybill_tpl_id']) ? $this->params['waybill_tpl_id'] : '';
        $waybill = Waybill::where('id',$waybill_id)->first()->toArray();
        if(!$waybill){
            $result["errcode"] = 40087;
            $result["errmsg"] = "运单模板不存在。";
            return Response::json($result, 200);
        }

        if($waybill['merchant_id'] != $this->merchant_id){
            $result["errcode"] = 40088;
            $result["errmsg"] = "运单模板错误。";
            return Response::json($result, 200);
        }

        $pixels = isset($this->params['pixels']) ? json_decode($this->params['pixels'],true) : array();

        if(!$pixels){
            $result["errcode"] = 1;
            $result["errmsg"] = "pixels参数错误。";
            return Response::json($result, 200);
        }

        $waybill['screen'] = '';
        $waybill['print'] = '';
        $waybill['size'] = $waybill['size'] ? $waybill['size'] : 14;

        $waybill['imgWidth'] = $waybill['width'] / $pixels[0];
        $waybill['imgHeight'] = $waybill['height'] / $pixels[1];

        $show_goods_props = 0;//是否要显示商户的属性,数量

        //发货备注
        $sendDefine = '';

        $waybill['print_item_arr'] = array();
        if($waybill['print_items']){
            $print_item_arr = json_decode($waybill['print_items'], true);
            foreach($print_item_arr as $value){
                if($value['show'] == 1){

                    $cssName = $value['id'];

                    $waybill['screen'] .= '.css-'.$cssName.' {left:'.$value['left'].'px;top:'.$value['top'].'px;width:'.$value['width'].'px;height:'.$value['height'].'px}';
                    $waybill['print'] .= '.css-'.$cssName.' {left:'.($value['left']*$pixels[0]).'mm;top:'.($value['top']*$pixels[1]).'mm;width:'.($value['width'])*$pixels[0].'mm;height:'.($value['height']*$pixels[1]).'mm}';

                }
                //显示属性
                if($value['id'] == 'orderProps' && $value['show'] == 1){
                    $show_goods_props = 1;
                }
                //发货备注
                if($value['id'] == 'sendDefine' && $value['show'] == 1){
                    $sendDefine = $value['text'];
                    $value['text'] = $value['id'];
                }
                $waybill['print_item_arr'][] = $value;
            }
        }

        //物流公司名
        $delivery = DeliveryCompany::where('id',$waybill['delivery_company_id'])->first();
        if($delivery) {
            $waybill['express_company'] = $delivery['name'];//物流公司名
            $waybill['logis_code'] = $delivery['code'];//物流公司代码
        }else{
            $waybill['express_company'] = $waybill['name'];//物流公司名
            $waybill['logis_code'] = '';
        }

        //快递单起始卡号
        $logis_begin_no = isset($this->params['logis_begin_no']) ? $this->params['logis_begin_no'] : '';

        $waybill['logis_no'] = $logis_begin_no;

        $data['waybill_tpl'] = $waybill;

        preg_match('/([a-zA-Z]*)(\d*)([a-zA-Z]*)/', $logis_begin_no, $match);
        $logis_pre = $match[1];
        $logis_mid = '1' . $match[2];
        $logis_end = $match[3];

        $order_ids = explode(',',$order_ids);
        $orders = OrderInfo::select('id','nickname','order_sn','remark','memo')
            ->whereIn('id',$order_ids)
            ->whereIn('status',[ORDER_TOSEND,ORDER_SUBMITTED])
            ->get();

        if($orders){

            //发货门店
            $store = MerchantSetting::where('merchant_id',$waybill['merchant_id'])->first();
            foreach ($orders as $k => &$order){

                //门店信息
                $consignee = OrderAddr::select('consignee', 'mobile', 'country_name', 'province_name', 'city_name', 'district_name', 'address','zipcode')->where('order_id',$order['id'])->first();
                $order['consignee'] = $consignee['consignee'];
                $order['mobile'] = $consignee['mobile'];
                $order['country_name'] = $consignee['country_name'];
                $order['province_name'] = $consignee['province_name'];
                $order['city_name'] = $consignee['city_name'];
                $order['district_name'] = $consignee['district_name'];
                $order['address'] = $consignee['address'];
                $order['zipcode'] = $consignee['zipcode'];

                //订单编号
                $orders[$k]['orderId'] = $order['order_sn'];
                //发件人姓名
                $orders[$k]['senderName'] = $store['addresser_name'];
                //发件人地址
                $orders[$k]['senderAddress'] = $store['addresser_address'];
                //发件人邮编
                $orders[$k]['senderTel'] = $store['addresser_tel'];
                //发件人公司
                $orders[$k]['senderCompanyName'] = $store['addresser_company'];

                //收件人姓名
                $orders[$k]['receiverName'] = $order['consignee'] ? $order['consignee'] : $order['nickname'];
                //目的地
                $orders[$k]['receiverCity'] = $order['city_name'];

                //收件人地址
                $orders[$k]['receiverAddress'] = $order['country_name'].$order['province_name'].$order['city_name'].$order['district_name'].$order['address'];
                //收件人邮编
                $orders[$k]['receiverPostCode'] = $order['zipcode'];
                //收件人电话
                $orders[$k]['receiverTel'] = $order['mobile'];
                // 打印時間
                $orders[$k]['printTime'] = date("Y-m-d H:i:s",time());
                //货号+属性+数量
                if($show_goods_props == 1){
                    $order_goods = OrderGoods::select('goods_sn','props','quantity','shipped_quantity','refund_quantity')->where('order_id',$order['id'])->get();
                    //print_r($order_goods);
                    $br = $orderProps = '';
                    foreach($order_goods as $goods){
                        //总数量-已发货数量-退款数量
                        $quantity = $goods['quantity']-$goods['shipped_quantity']-$goods['refund_quantity'];
                        $goods_sn = $goods['goods_sn'] ? $goods['goods_sn'] : '';
                        $orderProps .= $br.$goods_sn.' '.$goods['props'].' X '.$quantity;
                        $br = '<br/>';
                    }
                    $orders[$k]['orderProps'] = $orderProps;
                }
                //买家备注
                $orders[$k]['userDefine'] = $order['memo'];
                //商家备注
                $orders[$k]['merchantDefine'] = $order['remark'];
                //发货备注
                $orders[$k]['sendDefine'] = $sendDefine;

                # 快递单号自增
                $orders[$k]['shipCode'] = $logis_pre . substr($logis_mid, 1) . $logis_end;
                $logis_mid++;
            }
        }

        return view('print.print_express')->with('waybill_tpl', $data['waybill_tpl'])->with('orders',$orders);
    }

    /**
     * 物流跟踪接口
     * @author qinyuan
     */
    public function logisticsTracking(){
        $messages = array(
            'logis_code.required' => json_encode( ['errcode'=>40040,'errmsg'=>'物流公司代码不能为空!']),
            'logis_no.required' => json_encode( ['errcode'=>40041,'errmsg'=>'运单号不能为空!']),
        );
        $rules = [
            'logis_code' => 'required',
            'logis_no' => 'required',
        ];
        $validator = Validator::make($this->params,$rules,$messages);

        if ( $validator->fails() ) {
            $error = $validator->errors()->first();
            return $error;
        }

        $param['logis_code'] = trim($this->params["logis_code"]);
        $param['logis_no'] = trim($this->params["logis_no"]);

        $data = Logistics::search_logistic($param);

        $logis_name = DeliveryCompany::where(['code'=>$param['logis_code']])->pluck('name');
        $data['data']['logis_name'] = $logis_name;
        $data['data']['logis_code'] = $param['logis_code'];
        $data['data']['logis_no'] = $param['logis_no'];
        $data['data']['data'] = isset($data['data']['data']) && !empty($data['data']['data']) ? $data['data']['data'] : array();

        $result["errcode"] = 0;
        $result["errmsg"] = "物流跟踪操作成功";
        $result["data"] = $data;

        return Response::json($result, 200);
    }

    
    /**
     * 获取订单的收货地址
     * @author qinyuan
     */
    public function getOrderAddress(){
        $messages = array(
            'order_id.required' => json_encode( ['errcode'=>40001,'errmsg'=>'订单id不能为空']),
            'order_id.numeric' => json_encode( ['errcode'=>40047,'errmsg'=>'订单数据id不合法！']),
        );
        $rules = [
            'order_id' => 'required|numeric',
        ];
        $validator = Validator::make($this->params,$rules,$messages);

        if ( $validator->fails() ) {
            $error = $validator->errors()->first();
            return $error;
        }

        $orderId = trim($this->params["order_id"]);

        $addressInfo = OrderAddr::select('*')->where('order_id', $orderId)->first();

        $data = [
            'addressInfo' => $addressInfo,
        ];
        return Response::json($data, 200);
    }
    
    /**
     * 买家已提货
     * @Author  qinyuan
     */
    public function putHadPickup(){
        $messages = array(
            'order_id.required' => json_encode( ['errcode'=>40001,'errmsg'=>'订单id不能为空']),
        );
        $rules = [
            'order_id' => 'required|numeric',
        ];
        $validator = Validator::make($this->params,$rules,$messages);
    
        if ( $validator->fails() ) {
            $error = $validator->errors()->first();
            return $error;
        }
    
        $orderId = trim($this->params["order_id"]);
        //是否上门自提订单
        $rs_Order = OrderInfo::where(['merchant_id'=>Auth::user()->merchant_id,'id'=>$orderId])->first();
        if(empty($rs_Order)){
            $result["errcode"] = 1;
            $result["errmsg"] = "没有查到此订单";
            return Response::json($result, 200);
        }else if($rs_Order['delivery_type']!=2){
            $result["errcode"] = 1;
            $result["errmsg"] = "此订单的物流方式不是上门自提";
            return Response::json($result, 200);
        }else if( !in_array($rs_Order['status'], array(9))){
            $result["errcode"] = 1;
            $result["errmsg"] = "此订单状态不可操作 '买家已提货'";
            return Response::json($result, 200);
        }
        $if_auth = $this->getPickupPriv(Auth::user()->id,Auth::user()->merchant_id,Auth::user()->is_admin,'order_hadpickup');
        if( !isset($if_auth['errcode']) && $if_auth['errcode']!='has_priv' ){
            $rt['errcode']=100001;
            $rt['errmsg']='您没有确认提货权限';
            return Response::json($rt);
        }
        //更新订单状态
        $data_OrderSelffetch['status'] = 11;
		$data_OrderSelffetch['finished_time'] = date('Y-m-d H:i:s');
        $res = OrderInfo::where(['merchant_id'=>Auth::user()->merchant_id,'id'=>$orderId])->update($data_OrderSelffetch);
        //更新核销状态
        if($rs_Order['delivery_type']==2){
            $rs_orderselffetch = OrderSelffetch::where(['order_id'=>$orderId,'merchant_id'=>Auth::user()->merchant_id])->first();
            if(!empty($rs_orderselffetch)){
                $update_data = array(
                    'hexiao_status' => 1,
                    'hexiao_source' => 1,
                    'hexiao_time' => date("Y-m-d H:i:s"),
                    'user_id' => Auth::user()->id,
                );
                $result_orderselffetch = OrderSelffetch::update_data($rs_orderselffetch['id'], Auth::user()->merchant_id, $update_data);
            }
        }
        
        
        if($res){
            $result["errcode"] = 0;
            $result["errmsg"] = "操作成功。";
        }else{
            $result["errcode"] = 1;
            $result["errmsg"] = "操作失败。";
        }
    
        return Response::json($result, 200);
    }
    
    /**
     * 修改门店
     * @Author  qinyuan
     */
    public function putModifyStore(){
        $messages = array(
            'order_id.required' => json_encode( ['errcode'=>40001,'errmsg'=>'订单id不能为空']),
            'store_id.required' => json_encode( ['errcode'=>40001,'errmsg'=>'门店不能为空']),
        );
        $rules = [
            'order_id' => 'required|numeric',
            'store_id' => 'required|numeric',
        ];
        $validator = Validator::make($this->params,$rules,$messages);
    
        if ( $validator->fails() ) {
            $error = $validator->errors()->first();
            return $error;
        }
    
        $orderId = trim($this->params["order_id"]);
        //是否上门自提订单
        $rs_Order = OrderInfo::where(['merchant_id'=>Auth::user()->merchant_id,'id'=>$orderId])->first();
        if(empty($rs_Order)){
            $result["errcode"] = 1;
            $result["errmsg"] = "没有查到此订单";
            return Response::json($result, 200);
        }else if($rs_Order['delivery_type']!=2){
            $result["errcode"] = 1;
            $result["errmsg"] = "此订单的物流方式不是上门自提";
            return Response::json($result, 200);
        }else if( !in_array($rs_Order['status'], array(5,6))){
            $result["errcode"] = 1;
            $result["errmsg"] = "此订单状态不可以修改门店";
            return Response::json($result, 200);
        }
        //门店id是否合法
        $data_OrderSelffetch['store_id'] = trim($this->params["store_id"]);
        $rs_store = Store::where(['merchant_id'=>Auth::user()->merchant_id,'id'=>$this->params["store_id"]])->first();
        if(empty($rs_store)){
            $result["errcode"] = 1;
            $result["errmsg"] = "请选择正确的门店";
            return Response::json($result, 200);
        }
        $res = OrderInfo::where(['merchant_id'=>Auth::user()->merchant_id,'id'=>$orderId])->update($data_OrderSelffetch);
    
        if($res){
            $result["errcode"] = 0;
            $result["errmsg"] = "修改门店 操作成功。";
        }else{
            $result["errcode"] = 1;
            $result["errmsg"] = "修改门店 操作失败。";
        }
    
        return Response::json($result, 200);
    }
    
    function getPickupPriv($user_id,$merchant_id,$is_admin,$priv_code='') {
        //校验权限
        if(empty($priv_code)){
            return false;
        }
        if(empty($user_id)){
            return false;
        }
        if(empty($merchant_id)){
            return false;
        }
        //查询priv.code对应的priv.id
        $priv_id = Priv::get_id_by_code($priv_code);
        //dd($priv_id);
        if(empty($priv_id)){
            $rt['errcode'] = 'no_priv';
            $rt['errmsg'] = '查询不到此权限';
            $rt['data'] = '';
            return $rt;
        }
    
        // 1 商户使用的版本所拥有的权限
        // 1.1 商户的版本
        $merchant_info = Merchant::get_data_by_id($merchant_id);
        if(empty($merchant_info['version_id'])){
            $rt['errcode'] = 'no_priv';
            $rt['errmsg'] = '此商户的版本有问题';
            $rt['data'] = '';
            return $rt;
        }
        // 1.2 版本对应的权限列表
        $version_priv = VersionPriv::get_data_by_id($merchant_info['version_id']);
        // 1.3 商户版本是否包含此权限
        if(!in_array($priv_id,$version_priv)){
            $rt['errcode'] = 'no_priv';
            $rt['errmsg'] = '您正在使用的版本尚未开通此功能，如需开通请联系您的销售顾问';
            $rt['data'] = '';
            return $rt;
        }else if($is_admin && in_array($priv_id,$version_priv)){
            $rt['errcode'] = 'has_priv';
            $rt['errmsg'] = '';
            $rt['data'] = '';
            return $rt;
        }
        // 1.4 免费版对应的权限列表
        $free_version_priv = VersionPriv::get_data_by_id(1);
        // 1.5 商户账号过期,只能使用免费版的有限权限,免费版是否包含此权限
        if($merchant_info['expire_time'] < date('Y-m-d H:i:s')){
            if(!in_array($priv_id,$free_version_priv)){
                $rt['errcode'] = 'no_priv';
                $rt['errmsg'] = '此账号已过期,只有免费版权限,高级功能请联系您的销售顾问';
                $rt['data'] = '';
                return $rt;
            }
        }
    
        // 2 用户是否有此权限
        $user_priv = UserPriv::get_data_by_id($user_id,$merchant_id);
        if(!empty($user_priv) && in_array($priv_id,$user_priv)){
            $rt['errcode'] = 'has_priv';
            $rt['errmsg'] = '';
            $rt['data'] = '';
            return $rt;
        }
    
        // 3 用户角色是否有此权限
        // 3.1 用户角色
        $user_role = UserRole::get_data_by_id($user_id);
        if(!empty($user_role)){
            foreach ($user_role as $key=>$val){
                // 3.2 角色权限
                $role_priv=array();
                $role_priv = RolePriv::get_data_by_id($val);
                if(!empty($role_priv) && in_array($priv_id,$role_priv)){
                    $rt['errcode'] = 'has_priv';
                    $rt['errmsg'] = '';
                    $rt['data'] = '';
                    return $rt;
                }
            }
            $rt['errcode'] = 'no_priv';
            $rt['errmsg'] = '您没有权限访问,请联系管理员开通后再使用该功能.';
            $rt['data'] = '';
            return $rt;
        }
    
        $rt['errcode'] = 'no_priv';
        $rt['errmsg'] = '您没有权限访问,请联系管理员开通后再使用该功能!';
        $rt['data'] = '';
        return $rt;
    }

}
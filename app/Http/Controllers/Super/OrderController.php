<?php
/**
 * 订单管理
 */
namespace App\Http\Controllers\Super;
use App\Models\OrderPackageItem;
use App\Services\OrderService;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\OrderInfo;
use App\Models\OrderGoods;
use App\Models\OrderGoodsUmp;
use App\Models\OrderUmp;
use App\Models\OrderPackage;
use App\Models\OrderRefund;
use App\Models\OrderAddr;
use App\Models\OrderAppt;
use App\Models\MerchantSetting;
use Illuminate\Support\Facades\Response;
use Maatwebsite\Excel\Excel;

class OrderController extends Controller{

    protected $params;//参数
    private $excel;

    public function __construct(Request $request,Excel $excel){
        $this->params = $request->all();
        $this->excel = $excel;
    }

    /**
     * 订单列表  && 维权订单列表
     * 	$status 0.全部,1待付款,2.待发货,3.已发货,4.已完成,5.已关闭,6.退款中
     */
    public function getOrderList(){
        $status = isset($this->params["status"]) && !empty($this->params["status"])?$this->params["status"]:0;//订单状态
        $export = isset($this->params['export']) ? trim($this->params['export']) : 0;//导出订单
        $ids = isset($this->params['ids']) ? trim($this->params['ids']) : '';//需要导出订单的id
        $feedback = isset($this->params['feedback']) && !empty($this->params["feedback"]) ? trim($this->params['feedback']) : '';         // 维权订单 && 维权状态
        $paymentCode = isset($this->params['buy_way']) && !empty($this->params["buy_way"]) ? trim($this->params['buy_way']) : "";      // 付款方式
        $expressType = isset($this->params['express_type']) && !empty($this->params["express_type"]) ? trim($this->params['express_type']) : "";    // 物流方式
        $orderType = isset($this->params['order_type']) && !empty($this->params["order_type"]) ? trim($this->params['order_type']) : 0;   // 订单类型 读取varconfig配置

        if(isset($this->params["refund_status"]) && !empty($this->params["refund_status"])){
            $where["refund_status"] = trim($this->params["refund_status"]);
            if($where["refund_status"] == 'all'){
                $query = OrderInfo::select("order_info.*",'order_info.id as order_id','order_info.status as order_status','order_addr.mobile');
            }else{
                $query = OrderInfo::select("order_info.*","order_refund.*",'order_info.id as order_id','order_info.status as order_status','order_addr.mobile');
            }
        }else{
            if($status == 6){
                $query = OrderInfo::select("order_info.*","order_refund.*",'order_info.id as order_id','order_info.status as order_status','order_addr.mobile');
            }else{
                $query = OrderInfo::select("order_info.*",'order_info.id as order_id','order_info.status as order_status','order_addr.mobile');
            }
        }

        //维权订单
        if($feedback){
            $query = $query->where('order_info.refund_status','=','1');
        }

        // 付款方式
        if(isset($paymentCode) && $paymentCode) {
            if(in_array($paymentCode, array('wxpay'))) {
                $query = $query->where('order_info.pay_type', '=', 1);
            }
        }

        // 搜索 - 物流方式
        if($expressType) {
            $query = $query->where(['order_info.delivery_type'=>$expressType]);
        }

        //搜索 订单类型
        if($orderType){
            if(in_array($orderType, array(1,2,3,4))) {
                $query = $query->where(['order_info.order_type'=>$orderType]);
            }
        }

        if(isset($this->params["order_sn"]) && !empty($this->params["order_sn"])){
            $where["order_sn"] = trim($this->params["order_sn"]);
            $query = $query->where("order_info.order_sn","like",'%'.$where["order_sn"].'%');
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
        }

        $query = $query->leftJoin("order_addr","order_addr.order_id","=","order_info.id");
        if(isset($this->params["consignee"]) && !empty($this->params["consignee"])){
            $where["consignee"] = trim($this->params["consignee"]);
            $query = $query->where("order_addr.consignee","like",'%'.$where["consignee"].'%');
        }

        if(isset($this->params["mobile"]) && !empty($this->params["mobile"])){
            $where["mobile"] = trim($this->params["mobile"]);
            $query = $query->where("order_addr.mobile","like",'%'.$where["mobile"].'%');
        }

        if(isset($this->params["province"]) && !empty($this->params["province"])){
            $where["province"] = trim($this->params["province"]);
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

        //all.全部,sellertodo:等待卖家处理,buyertodo:等待买家处理,accept:同意退款,feedback_closed:维权撤销
        if(isset($this->params["refund_status"]) && !empty($this->params["refund_status"])){
            $where["refund_status"] = trim($this->params["refund_status"]);
            if($where["refund_status"] == 'all'){
				$query = $query->leftJoin("order_refund","order_refund.order_id","=","order_info.id");
                $query = $query->where("order_info.refund_status",1);
            }elseif($where["refund_status"] == 'sellertodo'){
                $query = $query->leftJoin("order_refund","order_refund.order_id","=","order_info.id");
                $query = $query->whereIn("order_refund.status",[10,11]);
            }elseif($where["refund_status"] == 'buyertodo'){
                $query = $query->leftJoin("order_refund","order_refund.order_id","=","order_info.id");
                $query = $query->where("order_refund.status","=",21);
            }elseif($where["refund_status"] == 'accept'){
                $query = $query->leftJoin("order_refund","order_refund.order_id","=","order_info.id");
                $query = $query->where("order_refund.status","=",20);
            }elseif($where["refund_status"] == 'feedback_closed'){
                $query = $query->leftJoin("order_refund","order_refund.order_id","=","order_info.id");
                $query = $query->where("order_refund.status","=",41);
            }
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
        
        switch ($status)
        {
            case 0:
                $result["_count"] = $query->count();
                break;
            case 1:
                $result["_count"] = $query->where("pay_status",0)->whereIn("order_info.status",[5,6])->count();
                $query = $query->where("pay_status",0)->whereIn("order_info.status",[5,6]);
                break;
            case 2:
                $result["_count"] = $query->where("pay_status",1)->where("order_info.status",7)->count();
                $query = $query->where("pay_status",1)->where("order_info.status",7);
                break;
            case 3:
                $result["_count"] = $query->where("pay_status",1)->where("order_info.status",10)->count();
                $query = $query->where("pay_status",1)->where("order_info.status",10);
                break;
            case 4:
                $result["_count"] = $query->where("pay_status",1)->where("order_info.status",11)->count();
                $query = $query->where("pay_status",1)->where("order_info.status",11);
                break;
            case 5:
                $result["_count"] = $query->whereIn("order_info.status",array(1,2,3,4))->count();
                $query = $query->whereIn("order_info.status",array(1,2,3,4));
                break;
            case 6:
                if(!isset($this->params["refund_status"]) || empty($this->params["refund_status"])){
                    $query = $query->leftJoin("order_refund","order_refund.order_id","=","order_info.id");
                }
                $query = $query->whereIn("order_refund.status",[10,11,12]);
                $result["_count"] = $query->where("pay_status",1)->where("refund_status",1)->count();
                $query = $query->where("pay_status",1)->where("refund_status",1);
                break;
        }

        if($export == 1){

            if(isset($ids) && $ids){
                $orderids = explode(',', $ids);
                $query = $query -> whereIn('order_info.id',$orderids);
            }

            $orderList = $query->get()->toArray();
            //编码
            header('Expires: 0');
            header('Cache-control: private');
            header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
            header('Content-Description: File Transfer');
            header('Content-Encoding: UTF-8');
            header('Content-type: text/csv; charset=UTF-8');
//            header("Content-type:application/csv;charset=UTF-8");

            $exportData[] = array(
                '订单编号'     => '',
                '下单时间'   => '',
                '订单金额'      => '',
                '支付方式' => '',
                '付款时间'       => '',
                '系统单号'   => '',
                '支付单号'     => '',
                '订单状态'      => '',
                '配送方式' => '',
                '运费' => '',
                '买家'       => '',
                '会员账号' =>'',
                '收货人'   => '',
                '联系电话' => '',
                '国家'     => '',
                '省'    => '',
                '市'        => '',
                '区'    => '',
                '地址'     => '',
                '商品名称汇总'   => '',
                '商品货号汇总'   => '',
                '商品属性汇总'  => '',
                '商品总数量' => '',
                '商品实付总价'  => '',
                '备注'        => '',
//                '优惠券'  => '',
//                '积分'  => '',
//                '余额'  => '',
                '完成时间'   => '',
            );

            if(!empty($orderList)){
                foreach ($orderList as $value) {
                    $shopData = MerchantSetting::select("delivery_alias")->where("merchant_id",$value['merchant_id'])->get()->toArray();
                    if(!empty($shopData)){
                        $deliveryAlias = $shopData[0]["delivery_alias"];
                    }else{
                        $deliveryAlias = '物流配送';
                    }
                    $payAt = date('Y-m-d', strtotime($value['pay_time'])) == '0000-00-00 00:00:00' ? '' : date('Y-m-d H:i:s', strtotime($value['pay_time']));
                    $createdAt = date('Y-m-d', strtotime($value['created_time'])) == '0000-00-00 00:00:00' ? '' : date('Y-m-d H:i:s', strtotime($value['created_time']));
                    $finishedAt = date('Y-m-d', strtotime($value['finished_time'])) == '0000-00-00 00:00:00' ? '' : date('Y-m-d H:i:s', strtotime($value['finished_time']));

                    // 订单完成时间容错
                    if(date('Y-m-d H:i:s', strtotime($value['finished_time'])) == '0000-00-00 00:00:00') {
                        $value['finished_time'] = '';
                    }
                    //获取收货信息
                    $orderAddr = OrderAddr::select()->where(['order_id'=>$value['id']])->first();
                    $exportOrderGoods = OrderGoods::select('goods_name','goods_sn','props','quantity','status','price','goods_id')
                        ->where('order_id', $value['id'])
                        ->get()
                        ->toArray();
                    $goodsName = '';
                    $goodsSn = '';
                    $props = '';
                    $quantity = 0;
                    if (!empty($exportOrderGoods)) {

                        foreach ($exportOrderGoods as $list) {
                            $goodsName .= $list['goods_name'] . '|';
                            $goodsSn .= $list['goods_sn'] . '|';
                            $props .= $list['props'] . '|';
                            $quantity += $list['quantity'];
                        }
                    }

                    $exportData[] = array(
                        '订单编号'    => $value['order_sn'],
                        '下单时间'  => $createdAt,
                        '订单金额'     => (string)number_format($value['amount'],2,'.',''),
                        '支付方式'=> "微信支付",
                        '付款时间'      => $payAt,
                        '系统单号'  => $value['payment_sn'],
                        '支付单号'  => $value['trade_sn'],
                        '订单状态'     => OrderService::convertOrderStatus($value['status']),
                        '配送方式' => $deliveryAlias,
                        '运费'=> $value['shipment_fee'],
                        '买家'      => mb_convert_encoding( $value['nickname'],'UTF-8',"auto" ),
                        '会员账号'   => $value['member_id']+MEMBER_CONST,
                        '收货人'  => $orderAddr['consignee'],
                        '联系电话'      => $orderAddr['mobile'] && $orderAddr['mobile'] !=0 ? $orderAddr['mobile'] : '',
                        '国家'    => $orderAddr['country_name'],
                        '省'   => $orderAddr['province_name'],
                        '市'       => $orderAddr['city_name'],
                        '区'    => $orderAddr['district_name'],
                        '地址'    => $orderAddr['address'],
                        '商品名称汇总'  => $goodsName ? rtrim($goodsName,'|') : '',
                        '商品货号汇总'  => $goodsSn ? rtrim($goodsSn,'|') : '',
                        '商品属性汇总' => $props ? rtrim($props,'|') : '',
                        '商品总数量'  => $quantity,
                        '商品实付总价' => (string)number_format($value['amount'],2,'.',''),
                        '备注'       => $value['memo'],
//                        '优惠券'  => $orderCoupon,
//                        '积分'  => $orderNumerical,
//                        '余额'  => $orderBalance,
                        '完成时间'   =>  $finishedAt,
                    );
                }
            }
            $filename = '订单列表'.date('Ymd',time());
            $this->excel->create($filename, function($excel) use ($exportData) {
                $excel->sheet('export', function($sheet) use ($exportData) {
                    $sheet->fromArray($exportData);
                });
            })->export('xls');
        }

        //获取列表
        $data = $query->orderBy("order_info.created_time", "DESC")->skip($offset)->take($limit)->get()->toArray();

        if(!empty($data)){
            foreach($data as $key => $val){
                $tempOrderIds[] = $val["order_id"];
            }

            $orderGoodsDataQuery = OrderGoods::select()->whereIn("order_id",$tempOrderIds)->get()->toArray();
            $tempOrderResultDataQuery = OrderRefund::select()->whereIn("order_id",$tempOrderIds)->get()->toArray();

            if(!empty($tempOrderResultDataQuery)){
                foreach($tempOrderResultDataQuery as $key9 => $val9){
                    $orderResultDataQuery[$val9["order_id"]][$val9["goods_id"]][$val9["spec_id"]][] = $val9;//  wangshiliang@dodoca.com   -> [$val9["spec_id"]]
                }
            }

            foreach($orderGoodsDataQuery as $key2=>$val2){
                $orderGoodsData[$val2["order_id"]][] = $val2;
            }

            foreach($orderGoodsData as $key22=>$val22){
                foreach($val22 as $key222=>$val222){
                    if(isset($orderResultDataQuery[$key22][$val222["goods_id"]][$val222['spec_id']])){//  wangshiliang@dodoca.com   -> [$val9["spec_id"]]
                        $orderGoodsData[$key22][$key222]["refund"] = $orderResultDataQuery[$key22][$val222["goods_id"]][$val222['spec_id']] ;
                    }else{
                        $orderGoodsData[$key22][$key222]["refund"] = [];
                    }

                }
            }

            foreach($data as $key => $val){
                if(isset($orderGoodsData[$val["order_id"]])){
                    $data[$key]["goods_info"] = $orderGoodsData[$val["order_id"]];
                }

                $shopData = MerchantSetting::select("delivery_alias")->where("merchant_id",$val['merchant_id'])->get()->toArray();
                if(!empty($shopData)){
                    $deliveryAlias = $shopData[0]["delivery_alias"];
                }else{
                    $deliveryAlias = '物流配送';
                }

                $data[$key]["member_sn"] = MEMBER_CONST + $data[$key]["member_id"];
                $data[$key]["shipping_type"] = $deliveryAlias;
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
                    $tempRefundData =  OrderRefund::select()->where("order_id",$data[$key]["id"])->get()->toArray();
                    if(!empty($tempRefundData)){
                        foreach($tempRefundData as $k => $v){
                            if(in_array($v["status"], [10,11,12])){
                                $data[$key]["status"] = 6;
                            }
                        }
                    }
                }

                if($data[$key]["order_type"] == 4){
                    $tempOrderAppt = OrderAppt::select()->where("order_id",$val["order_id"])->get()->toArray();
                    if(!empty($tempOrderAppt)){
                        $orderApptData = $tempOrderAppt[0];
                        $data[$key]["hexiao"] = $orderApptData;
                        if($orderApptData["user_id"]){
                            $tempUserInfo = User::select("username")->where("id",$orderApptData["user_id"])->get()->toArray();
                            $data[$key]["hexiao"]["hexiao_user"] = $tempUserInfo[0]["username"];
                        }else{
                            $data[$key]["hexiao"]["hexiao_user"] = '';
                        }

                    }else{
                        $data[$key]["hexiao"] = [];
                    }
                }else{
                    $data[$key]["hexiao"] = [];
                }
            }
        }

        $result["errcode"] = 0;
        $result["data"] = $data;
        $result["errmsg"] = "订单列表获取成功。";

        return Response::json($result, 200);
    }

    /**
     * 获取订单详情
     */
    public function getOrderInfo($orderId){

        $tempData =  OrderInfo::select()->where("id",$orderId)->get()->toArray();
        if(empty($tempData)){
            $result["errcode"] = 40031;
            $result["errmsg"] = "找不到对应的订单。";

            return Response::json($result, 200);
        }
        $data = $tempData[0];
        $data["order_status"] = $data['status'];

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

        $tempGoodsData =  OrderGoods::select()->where("order_id",$orderId)->get()->toArray();
        foreach($tempGoodsData as $key => $val){
            $tempGoodsArray[] = $val["goods_id"];
            $tempGoodsData[$key]["pice_diff"] = 0;
            $tempGoodsData[$key]["amount_ump"] = 0;
        }       
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

        $tempRefundData =  OrderRefund::select()->where("order_id",$orderId)->whereIn("goods_id",$tempGoodsArray)->get()->toArray();
        foreach($tempRefundData as $key3 => $val3){
            $refundData[$val3["goods_id"]][] = $val3;
        }

        $tempPackageItemData = OrderPackageItem::select('order_goods.*','order_package_item.package_id','order_package_item.order_goods_id','order_package_item.quantity as shippedQuantity')->leftJoin('order_goods','order_goods.id','=','order_package_item.order_goods_id')->where("order_goods.order_id",$orderId)->get()->toArray();

        if(!empty($tempPackageItemData)){
            foreach($tempPackageItemData as $key2 => $val2){
                $goodsPackageData[$val2["order_goods_id"]] = $val2["package_id"];
            }

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

                        $tempAmount =  OrderGoodsUmp::where(["order_id"=>$orderId,'goods_id'=>$val2["goods_id"],'ump_type'=>4])->pluck('amount');
                        $tempAmount = $tempAmount ? $tempAmount : 0.00;
                        $val2["pice_diff"] = sprintf("%.2f",$tempAmount/$val2['quantity']*$val2['shippedQuantity']);//改价价格
                        $val2['quantity'] = $val2['shipped_quantity'] = $val2['shippedQuantity'];

                        if(isset($refundData[$val2["goods_id"]])) {
                            $val2["refund"] = $refundData[$val2["goods_id"]];
                        }
                        $package[$val22["id"]]['goods'][] = $val2;
                    }
                }
            }
        }else{
            $package = null;
        }

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
            if(isset($refundData[$val4["goods_id"]])){
                $tempGoodsData[$key4]["refund"] = $refundData[$val4["goods_id"]];
            }else{
                $tempGoodsData[$key4]["refund"] = [];
            }

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

            $tempAmount =  OrderGoodsUmp::where(["order_id"=>$orderId,'goods_id'=>$val4["goods_id"],'ump_type'=>4])->pluck('amount');
            $tempAmount = $tempAmount ? $tempAmount : 0.00;

            if(isset($goodsPackageData[$val4["id"]])){
                $tempGoodsData[$key4]["pice_diff"] = sprintf("%.2f",$tempAmount/$val4['quantity']*$surplusNum);//改价价格
            }else{
                $tempGoodsData[$key4]["pice_diff"] = sprintf("%.2f",$tempAmount);//改价价格
            }

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

        if($data["order_type"] == 4){
            $tempOrderAppt = OrderAppt::select()->where("order_id",$orderId)->get()->toArray();
            if(!empty($tempOrderAppt)){
                $orderApptData = $tempOrderAppt[0];
                $data["hexiao"] = $orderApptData;
                if($orderApptData["user_id"]){
                    $tempUserInfo = User::select("username")->where("id",$orderApptData["user_id"])->get()->toArray();
                    $data["hexiao"]["hexiao_user"] = $tempUserInfo[0]["username"];
                }else{
                    $data["hexiao"]["hexiao_user"] = '';
                }

            }else{
                $data["hexiao"] = [];
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
        $setting = MerchantSetting::select()->where(['merchant_id'=>$data['merchant_id']])->first();
        if(!empty($setting)){
            $daysNum = $setting["auto_finished_time"]+$data['extend_days'];
            if($data["finished_time"] == "0000-00-00 00:00:00" && $data["shipments_time"] != "0000-00-00 00:00:00"){
                $data["finished_time"] = date("Y-m-d H:i:s",strtotime($data["shipments_time"]."+".$daysNum." day"));
            }
        }

        $shopData = MerchantSetting::select("delivery_alias")->where("merchant_id",$data['merchant_id'])->get()->toArray();
        if(!empty($shopData)){
            $deliveryAlias = $shopData[0]["delivery_alias"];
        }else{
            $deliveryAlias = '物流配送';
        }

        $data['delivery_alias'] = $deliveryAlias;
        $data['extend_info'] = json_decode($data['extend_info'],true);
        $result["errcode"] = 0;
        $result["data"] = $data;
        $result["errmsg"] = "订单详情获取成功。";

        return Response::json($result, 200);
    }



}
<?php

namespace App\Services;

/**
 * 订单服务类
 *
 * @package default
 * @author qinyuan
 **/
use App\Models\DeliveryCompany;
use App\Models\Goods;
use App\Models\MerchantDelivery;
use App\Models\OrderInfo;
use App\Models\OrderGoods;
use App\Models\OrderPackage;
use App\Models\OrderPackageImport;
use App\Models\OrderPackageItem;
use App\Models\OrderRefund;
use App\Models\Waybill;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

use App\Services\FightgroupService;


class OrderService
{

    protected $merchant_id;

    protected $orderStatus = array(
        ORDER_AUTO_CANCELED => '已关闭',
        ORDER_BUYERS_CANCELED => '用户取消',
        ORDER_MERCHANT_CANCEL => '商家取消',
        ORDER_REFUND_CANCEL => '已关闭',
        ORDER_SUBMIT => '待付款',
        ORDER_TOPAY => '待付款',
        ORDER_SEND => '待收货',
        ORDER_SUCCESS => '已完成',
    );

    public function __construct()
    {
        $this->merchant_id = Auth::user()->merchant_id;
//        $this->merchant_id = 6;
    }
    /**
     * 获取订单批量发货列表
     *
     * @param string order_sn  订单号
     * @param string logis_no  物流单号
     * @param string created_time  发货时间
     * @param string page 页数
     * @param string pagesize 每页数量
     *
     * @return array()
     */

    public function getShippingGoodsList($param = array()){
        $orderSn = $param['order_sn'];
        $logisNo = $param['logis_no'];
        $status = $param['status'];
        $createdTime = $param['created_time'];
        $page = $param['page'];
        $pageSize = $param['pagesize'];

        $offset = ($page-1) * $pageSize;

        $query = OrderPackageImport::select()->where(['merchant_id'=>$this->merchant_id]);

        if(isset($orderSn) && !empty($orderSn)){
            $query = $query->where("order_sn",$orderSn);
        }

        if(isset($logisNo) && !empty($logisNo)){
            $query = $query->where("logis_no",$logisNo);
        }

        if(isset($status) && $status != ''){
            if($status == 0){//发货成功
                $query = $query->where('status',1);
            }elseif($status == 1){
                $query = $query->where('status',0);
            }
        }

        if(isset($createdTime) && !empty($createdTime)){
            $createdTime = explode("/",$createdTime);

            $query = $query->where("created_time",">=",$createdTime[0]);
            $query = $query->where("created_time","<",$createdTime[1]);
        }

        $total = $query->count();

        $list = $query->orderBy("created_time","DESC")->skip($offset)->take($pageSize)->get()->toArray();

        return array(
            '_count'=>$total,
            'data'=>$list,
            'errcode'=>0,
            'errmsg'=>'发货列表获取成功',
        );

    }

    /**
     * @param $file
     * @return array
     * @author qinyuan
     * 批量发货导入csv
     */
    public function importShippingCsv($file){

        $valid = $this->checkCsvValid($file);
        if($valid !== true) {
            return $valid;
        }
        $s = file_get_contents($file['tmp_name']);//读取文件到变量

        $s = iconv('GBK', 'UTF-8', $s);

        $s = str_replace('	', '', $s);//去空格
        $results = $this->strGetcsv($s, "\t");
        $n = $f = 0;

        if(is_array($results)){
            array_shift($results);

            //去除数组中值为空的键
            while (list($k,$v)=each($results)) {
                if( $v[0] == ',,' ) unset($results[$k]);
            }

            if(count($results) == 1){
                foreach($results[0] as $_row) {
                    $row = str_replace('"','',$_row);
                    $row = explode(',',$row);
                    foreach($row as $k => &$tmp){
                        $tmp = trim($tmp);
                        if($k == 2){
                            $tmp = $this->NumToStr($tmp);
                        }
                    }
                    if($row[0]){
                        $shipment = $this->shipments($row);
                        $logData = array(
                            'merchant_id'=>$this->merchant_id,
                            'order_sn'=>str_replace('?','',$row[0]),
                            'logis_name'=>$row[1],
                            'logis_no'=>trim($row[2]),
                            'memo'=>'发货成功'
                        );

                        if($shipment['errcode'] == 0){
                            $logData['status'] = 1;
                            $n++;
                        }else{
                            $logData['status'] = 0;
                            $logData['memo'] = $shipment['errmsg'];
                            $f++;
                        }
                        OrderPackageImport::insertGetId($logData);
                    }
                }
            }else{
                if(count($results) > 500){
                    return ['errcode'=>1, 'errmsg'=>'一次最多可批量发货500个订单'];
                }
                foreach($results as $value){
                    if($value[0]){
                        $row = str_replace('"','',$value[0]);
                        $row = explode(',',$row);
                        foreach($row as $k => &$tmp){
                            $tmp = trim($tmp);
                            if($k == 2){
                                $tmp = $this->NumToStr($tmp);
                            }
                        }
                        $shipment = $this->shipments($row);

                        $logData = array(
                            'merchant_id'=>$this->merchant_id,
                            'order_sn'=>str_replace('?','',$row[0]),
                            'logis_name'=>$row[1],
                            'logis_no'=>trim($row[2]),
                            'memo'=>'发货成功'
                        );

                        if($shipment['errcode'] == 0){
                            $logData['status'] = 1;
                            $n++;
                        }else{
                            $logData['status'] = 0;
                            $logData['memo'] = $shipment['errmsg'];
                            $f++;
                        }

                        OrderPackageImport::insertGetId($logData);
                    }
                }
            }

        }else{
            return ['errcode'=>1, 'errmsg'=>'批量发货失败'];
        }
        return ['errcode'=>0, 'errmsg'=>'成功发货'.$n.'个订单，失败'.$f.'个订单'];
    }


    private function NumToStr($num){
        if (stripos($num,'e+')===false) return $num;
        $num = trim(preg_replace('/[=\'"]/','',$num,1),'"');//出现科学计数法，还原成字符串
        $result = "";
        while ($num > 0){
            $v = $num - floor($num / 10)*10;
            $num = floor($num / 10);
            $result   =   $v . $result;
        }
        return $result;
    }

    private function checkCsvValid($file)
    {
        $mimes = array('application/vnd.ms-excel','application/octet-stream','text/plain','text/csv','text/tsv');

        if(isset($file['error']) && $file['error'] > 0) {
            return ['errcode'=>1, 'errmsg'=>$file['error']];
        }else if(!in_array($file['type'],$mimes)){
            return ['errcode'=>1, 'errmsg'=>'请上传csv文件'];
        } else if($file['size'] > 1024*1024) {
            return ['errcode'=>1, 'errmsg'=>'文件大小不能超过1M'];
        }
        return true;
    }


    private function strGetcsv($string, $delimiter=',', $enclosure='"') {
        $fp = fopen('php://temp/', 'r+');
        fputs($fp, $string);
        rewind($fp);
        $r = [];
        while ($t = fgetcsv($fp, strlen($string), $delimiter, $enclosure)) {
            $r[] = $t;
        }
        if (count($r) == 1) {
            return current($r);
        }
        return $r;
    }

    private function shipments($row)
    {
        $remark = '';//失败原因
        //订单号
        $order_sn = $row[0];

        //快递公司
        $delivery = $row[1];

        if(!preg_match('/^[a-zA-Z0-9]*$/', $row[2])){
            return array('errcode'=>40070,'errmsg'=>'运单号格式不正确');
        }

        //查询当前订单是否可以发货

        $orderInfo = OrderInfo::select('id','status','merchant_id','order_sn','member_id','order_type')->where(['order_sn'=>$order_sn,'is_valid'=>1])->first();
        if(!$orderInfo){
            return array('errcode' =>20014,'errmsg' =>'订单不存在');
        }
        if($orderInfo['merchant_id'] != $this->merchant_id){
            return array('errcode'=>40069,'errmsg'=>'非本店订单');
        }
        
        //验证拼团订单是否能发货
        if($orderInfo['order_type'] == 2){//验证团购订单是否可发货
            $FightGroupService = new FightgroupService();
            
            $check = $FightGroupService->fightgroupJoinOrder($orderInfo['id']);
        
            if($check['data']['type'] == 0){
                return array('errcode'=>41004,'errmsg'=>'该拼团订单不可发货');
            }
        }
        
        
        if(!in_array($orderInfo['status'],[ORDER_TOSEND,ORDER_SUBMITTED])){
            $msg = '订单状态:'.$this->orderStatus[$orderInfo['status']];
            if($orderInfo['refund_status'] == 1){
                $msg = "订单维权中";
            }

            $msg .= '无法发货';
            return array('errcode'=>40068,'errmsg'=>$msg);
        }

        //该订单下可发货商品
        $goodsList = OrderGoods::select('id as order_goods_id','goods_id','goods_name','status','quantity','shipped_quantity','refund_quantity','order_id','refund_status')->where('order_id',$orderInfo['id'])->whereNotIn('status', [ORDER_MERCHANT_CANCEL, ORDER_AUTO_CANCELED, ORDER_BUYERS_CANCELED,ORDER_REFUND_CANCEL])->get()->toArray();

        $tmpGoods = array();
        if($goodsList) {
            foreach ($goodsList as $orderGoods) {
                $refundQuantity = OrderRefund::where(array('order_id' => $orderInfo['id'], 'goods_id' => $orderGoods['goods_id'], 'package_id' => 0))
                    ->whereNotIn('status', [REFUND_CANCEL, REFUND_CLOSE])
                    ->sum('refund_quantity');

                $delivery_num = $orderGoods['quantity'] - $orderGoods['shipped_quantity'] - $refundQuantity;
                if ($delivery_num > 0) {
                    $orderGoods['delivery_num'] = $delivery_num;
                    $tmpGoods[] = $orderGoods;
                }
            }

            //-------发货开始------
            if ($tmpGoods) {
                //查找物流是否系统自带，否则是自定义
                $ordrePackageData = array(
                    'order_id' => $orderInfo['id'],
                    'order_sn' => $orderInfo['order_sn'],
                    'logis_no' => $row[2],
                    'logis_name' => $delivery,
                    'logis_code' => '',
                    'is_no_express' => 0,
                );

                $delivery_company = DeliveryCompany::where('name', $delivery)->first();
                $shippment_id = '';
                if ($delivery_company) {
                    //商家是否有选择这个物流
                    $merchant_delivery = MerchantDelivery::where(array('merchant_id' => $this->merchant_id, 'delivery_company_id' => $delivery_company['id']))->first();
                    if ($merchant_delivery) {
                        $shippment_id = $delivery_company['id'];
                        $ordrePackageData['logis_name'] = $delivery_company['name'];
                        $ordrePackageData['logis_code'] = $delivery_company['code'];
                    }
                }

                $order_id = $orderInfo['id'];
                $order_type = $orderInfo['order_type'];
                try {
                    DB::transaction(function () use ($order_id, $tmpGoods, $ordrePackageData, $order_type, $shippment_id) {
                        //先更新订单包裹表数据
                        $package_id = OrderPackage::insertGetId($ordrePackageData);
                        if (!is_int($package_id)) {
                            throw new \Exception('发货数据入库失败！');
                        }
                        //更新订单商品表数据
                        foreach ($tmpGoods as $value) {
                            OrderGoods::where(['id' => $value['order_goods_id']])->increment('shipped_quantity', $value['delivery_num']);
                            $packageItemData = array(
                                'package_id' => $package_id,
                                'order_id' => $order_id,
                                'shipment_id' => $shippment_id,
                                'order_goods_id' => $value['order_goods_id'],
                                'quantity' => $value['delivery_num'],
                            );

                            $package_item_id = OrderPackageItem::insertGetId($packageItemData);
                            if (!is_int($package_item_id)) {
                                throw new \Exception('发货数据子表入库失败！');
                            }

                            //更新对应订单商品的状态
                            $orderGoodsInfo = OrderGoods::select('id', 'shipped_quantity', 'refund_quantity', 'quantity')->where(['id' => $value['order_goods_id']])->first();
                            if ($orderGoodsInfo) {
                                if ($orderGoodsInfo['quantity'] == ($orderGoodsInfo['shipped_quantity'] + $orderGoodsInfo['refund_quantity'])) {
                                    OrderGoods::where(['id' => $orderGoodsInfo['id']])->update(['status' => ORDER_SEND]);
                                }
                            }
                        }
                        //统计当前订单购买的总件数
                        $order_quantity = OrderGoods::where(['order_id' => $order_id])->sum('quantity');
                        //统计订单已发货的件数
                        $shipped_quantity = OrderGoods::where(['order_id' => $order_id])->sum('shipped_quantity');
                        //统计已成功退款的件数 去除包裹里退的件数
                        $refund_quantity = OrderRefund::where(['order_id' => $order_id, 'status' => REFUND_FINISHED, 'package_id' => 0])->sum('refund_quantity');
                        if ($order_quantity == ($shipped_quantity + $refund_quantity)) {
                            //更新订单状态
                            OrderInfo::select()->where(['id' => $order_id])->update(['status' => ORDER_SEND,'shipments_time'=>date("Y-m-d H:i:s")]);

                        }
                    });
                } catch (\Exception $e) {
                    print_r($e,true);
                    return array('errcode' => 1, 'errmsg' => '系统异常,发货失败');
                }

                return array('errcode' => 0, 'errmsg'=>'发货成功');

            } else {
                return array('errcode' => 1, 'errmsg' => '无发货商品');
            }
        }

    }



    /**
     * @param $orderIds
     * 生成订单报表
     * @author qinyuan
     */
    public static function exportOrder($orderIds){

    }

    /**
     *  订单状态转换
     *
     *  author: qinyuan
     */
    public static function convertOrderStatus($status)
    {
        //dd($status);
        switch ($status) {
            case ORDER_AUTO_CANCELED:
                $statusDesc = '自动取消';
                break;
            case ORDER_BUYERS_CANCELED:
                $statusDesc = '买家取消';
                break;
            case ORDER_MERCHANT_CANCEL:
                $statusDesc = '商家取消';
                break;
            case ORDER_REFUND_CANCEL:
                $statusDesc = '维权完成/已关闭';
                break;
            case ORDER_SUBMIT:
                $statusDesc = '待付款';
                break;
            case ORDER_TOPAY:
                $statusDesc = '待付款';
                break;
            case ORDER_TOSEND:
                $statusDesc = '待发货';
                break;
            case ORDER_SUBMITTED:
                $statusDesc = '货到付款/待发货';
                break;
            case ORDER_FORPICKUP:
                $statusDesc = '上门自提/待提货';
                break;
            case ORDER_SEND:
                $statusDesc = '商家发货/买家待收货';
                break;
            case ORDER_SUCCESS:
                $statusDesc = '已完成';
                break;
            default:
                $statusDesc = '';
                break;
        }
        return $statusDesc;
    }


    /**
     *  付款名称转换
     *
     *  author: qinyuan
     */
    public static function convertPayName(){
        $data = config("varconfig.order_info_pay_type");
        return $data[1];
    }

    /**
     * 获取运单模板列表
     * @author qinyuan
     */
    public function getWaybills(){
        $where = array(
            'merchant_id' => $this->merchant_id
        );
        $result['_count'] = Waybill::where($where)->count();
        $result['data'] = array();

        if ($result['_count'] > 0) {
            $result['data'] = Waybill::where($where)->get();
            foreach ($result['data'] as &$v) {
                $v['print_items'] = json_decode($v['print_items']);
                $logistics = DeliveryCompany::where('id', '=', $v['delivery_company_id'])
                    ->first();
                $v['delivery_company_name'] = $logistics['name'];
            }
        }
        return $result;
    }

    /**
     * 批量发货
     * @author qinyuan
     */
    public function batchShipments($params='') {
        if(!isset($params['data']) || empty($params['data'])) {
            return array('errcode'=>1,'errmsg'=>'参数错误');
        }
        // 開始驗證
        foreach($params['data'] as $order) {
            $postData = array();
            $arr = explode(',',$order);
            foreach($arr as $field){
                $f_v = explode(':',$field);
                $postData[$f_v[0]] = $f_v[1];
            }
            $orderId = isset($postData['order_id']) && $postData['order_id'] ? intval($postData['order_id']) : '';
            $deliveryNumber = isset($postData['logis_no']) && $postData['logis_no'] ? trim($postData['logis_no']) : '';
            $deliveryId = isset($postData['waybill_tpl_id']) && $postData['waybill_tpl_id'] ? intval($postData['waybill_tpl_id']) : '';
            $deliveryName = isset($postData['logis_name']) && $postData['logis_name'] ? trim($postData['logis_name']) : '';
            if(!$orderId || !$deliveryNumber || !$deliveryId || !$deliveryName) {
                return array('errcode'=>1,'errmsg'=>'参数错误');
            }
            // 校驗运单模板信息
            $checkDeliveryId = Waybill::where(array('merchant_id' => $this->merchant_id, 'id' => $deliveryId))->pluck('id');
            if(!$checkDeliveryId) {
                return array('errcode' => 40088, 'errmsg' => '运单模板错误');
            }
            // 訂單是否存在
            $orderInfo = OrderInfo::select('id', 'order_sn', 'status','order_type')->where(array('id' => $orderId, 'merchant_id' => $this->merchant_id))->first();
            if(!$orderInfo) {
                return array('errcode' => 1, 'errmsg' => "订单编号：{$orderInfo['order_sn']}不存在");
            }

            if(!in_array($orderInfo['status'], [ORDER_TOSEND, ORDER_SUBMITTED])) {
                return array('errcode' => 40068, 'errmsg' => '该订单状态不能操作发货');
            }

            // 校驗運單號
            if(!preg_match("/^[0-9a-zA-Z]+$/", $deliveryNumber)) {
                return array('errcode'=>40070,'errmsg'=>'运单号格式不正确');
            }

            //该订单下可发货商品
            $goodsList = OrderGoods::select('id as order_goods_id','goods_id','goods_name','status','quantity','shipped_quantity','refund_quantity','order_id','refund_status')->where('order_id',$orderInfo['id'])->whereNotIn('status', [ORDER_MERCHANT_CANCEL, ORDER_AUTO_CANCELED, ORDER_BUYERS_CANCELED,ORDER_REFUND_CANCEL])->get()->toArray();

            $tmpGoods = array();
            if($goodsList) {
                foreach ($goodsList as $orderGoods) {
                    $refundQuantity = OrderRefund::where(array('order_id' => $orderInfo['id'], 'goods_id' => $orderGoods['goods_id'], 'package_id' => 0))
                        ->whereNotIn('status', [REFUND_CANCEL, REFUND_CLOSE])
                        ->sum('refund_quantity');

                    $delivery_num = $orderGoods['quantity'] - $orderGoods['shipped_quantity'] - $refundQuantity;
                    if ($delivery_num > 0) {
                        $orderGoods['delivery_num'] = $delivery_num;
                        $tmpGoods[] = $orderGoods;
                    }
                }

                //-------发货开始------
                if ($tmpGoods) {
                    //查找物流是否系统自带，否则是自定义
                    $ordrePackageData = array(
                        'order_id' => $orderInfo['id'],
                        'order_sn' => $orderInfo['order_sn'],
                        'logis_no' => $deliveryNumber,
                        'logis_name' => $deliveryName,
                        'logis_code' => '',
                        'is_no_express' => 0,
                    );

                    $delivery_company = DeliveryCompany::where('name', $deliveryName)->first();
                    if ($delivery_company) {
                        //商家是否有选择这个物流
                        $merchant_delivery = MerchantDelivery::where(array('merchant_id' => $this->merchant_id, 'delivery_company_id' => $delivery_company['id']))->first();
                        if ($merchant_delivery) {
                            $ordrePackageData['logis_name'] = $delivery_company['name'];
                            $ordrePackageData['logis_code'] = $delivery_company['code'];
                        }
                    }

                    $order_id = $orderInfo['id'];
                    $order_type = $orderInfo['order_type'];
                    try {
                        DB::transaction(function () use ($order_id, $tmpGoods, $ordrePackageData, $order_type, $deliveryId) {
                            //先更新订单包裹表数据
                            $package_id = OrderPackage::insertGetId($ordrePackageData);
                            if (!is_int($package_id)) {
                                throw new \Exception('发货数据入库失败！');
                            }
                            //更新订单商品表数据
                            foreach ($tmpGoods as $value) {
                                OrderGoods::where(['id' => $value['order_goods_id']])->increment('shipped_quantity', $value['delivery_num']);
                                $packageItemData = array(
                                    'package_id' => $package_id,
                                    'order_id' => $order_id,
                                    'shipment_id' => $deliveryId,
                                    'order_goods_id' => $value['order_goods_id'],
                                    'quantity' => $value['delivery_num'],
                                );

                                $package_item_id = OrderPackageItem::insertGetId($packageItemData);
                                if (!is_int($package_item_id)) {
                                    throw new \Exception('发货数据子表入库失败！');
                                }

                                //更新对应订单商品的状态
                                $orderGoodsInfo = OrderGoods::select('id', 'shipped_quantity', 'refund_quantity', 'quantity')->where(['id' => $value['order_goods_id']])->first();
                                if ($orderGoodsInfo) {
                                    if ($orderGoodsInfo['quantity'] == ($orderGoodsInfo['shipped_quantity'] + $orderGoodsInfo['refund_quantity'])) {
                                        OrderGoods::where(['id' => $orderGoodsInfo['id']])->update(['status' => ORDER_SEND]);
                                    }
                                }
                            }
                            //统计当前订单购买的总件数
                            $order_quantity = OrderGoods::where(['order_id' => $order_id])->sum('quantity');
                            //统计订单已发货的件数
                            $shipped_quantity = OrderGoods::where(['order_id' => $order_id])->sum('shipped_quantity');
                            //统计已成功退款的件数 去除包裹里退的件数
                            $refund_quantity = OrderRefund::where(['order_id' => $order_id, 'status' => REFUND_FINISHED, 'package_id' => 0])->sum('refund_quantity');
                            if ($order_quantity == ($shipped_quantity + $refund_quantity)) {
                                //更新订单状态
                                OrderInfo::select()->where(['id' => $order_id])->update(['status' => ORDER_SEND,'shipments_time'=>date("Y-m-d H:i:s")]);

                            }
                        });
                    } catch (\Exception $e) {
                        print_r($e,true);
                        return array('errcode' => 1, 'errmsg' => '系统异常,发货失败');
                    }

                    return array('errcode' => 0, 'errmsg'=>'发货成功');

                } else {
                    return array('errcode' => 1, 'errmsg' => '无发货商品');
                }
            }
        }
    }

} // END class

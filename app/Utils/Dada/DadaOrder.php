<?php
/**
 * Created by PhpStorm.
 * User: wangshiliang@dodoca.com
 * Date: 2018/7/31
 * Time: 14:44
 */

namespace App\Utils\Dada;


class DadaOrder extends Dada
{
    protected $shop = 0;

    public function setConfig($app_key, $app_secret, $merchant, $develop = true){
        if($develop){
            $merchant = 73753;
            $this->shop = 11047059;
        }
        return parent::setConfig($app_key, $app_secret, $merchant, $develop);
    }

    /**
     * @name 新增订单 /  重发订单
     * @param $shop 门店编号
     * @param $order 订单号
     * @param $cityCode 城市编号
     * @param $price 订单价格
     * @param $prepay 是否需要垫付 1:是 0:否 (垫付订单金额，非运费)
     * @param $name 收货人姓名
     * @param $address 收货人地址
     * @param $lat 收货人地址维度
     * @param $lng 收货人地址经度
     * @param $phone 收货人手机号
     * @param $callback 回调地址
     * @param $data['tel'] 收货人座机号（手机号和座机号必填一项）
     * @param $data['tip'] 小费 单位：元，精确小数点后一位）
     * @param $data['info']  订单备注
     * @param $data['type']  订单商品类型：食品小吃-1,饮料-2,鲜花-3,文印票务-8,便利店-9,水果生鲜-13,同城电商-19, 医药-20,蛋糕-21,酒品-24,小商品市场-25,服装-26,汽修零配-27,数码-28,小龙虾-29, 其他-5
     * @param $data['weight'] 订单重量（单位：Kg）
     * @param $data['num'] 订单商品数量
     * @param $data['title'] 发票抬头
     * @param $data['mark']  订单来源标示（该字段可以显示在达达app订单详情页面，只支持字母，最大长度为10）
     * @param $data['mark_no']  订单来源编号（该字段可以显示在达达app订单详情页面，支持字母和数字，最大长度为30）
     * @param $data['insurance'] 是否使用保价费（0：不使用保价，1：使用保价； 同时，请确保填写了订单金额（cargo_price））
     * @param $data['code']  收货码（0：不需要；1：需要。收货码的作用是：骑手必须输入收货码才能完成订单妥投）
     * @param $data['time'] 预约发单时间（预约时间unix时间戳(10位),精确到分;整10分钟为间隔，并且需要至少提前20分钟预约。）
     * @param $data['delivery'] 是否选择直拿直送（0：不需要；1：需要。选择直拿只送后，同一时间骑士只能配送此订单至完成，同时，也会相应的增加配送费用）
     * @param $action = 'add' add 添加 update 更新 get 订单预发布查询运费
     * @return array ["code"=>0 ,"msg"=>""  "result"=>{
     *             "distance"=>"配送距离",
     *             "fee"=>"实际运费，运费减去优惠券费用",
     *             "deliverFee"=>"运费",
     *             "couponFee"=>"优惠券费用",
     *             "tips"=>"小费",
     *             "insuranceFee"=>"保价费",
     *             "deliveryNo"=>"平台订单号($action = get)"
     * } ]
     */
    public function order($shop, $order, $cityCode, $price, $prepay, $name, $address, $lat, $lng, $phone, $callback, $data, $action = 'add'){
        $datas = [
            'shop_no'           => $this->shop != 0 ? $this->shop : $shop,
            'origin_id'         => $order,
            'city_code'         => $cityCode,
            'cargo_price'       => $price,
            'is_prepay'         => $prepay,
            'receiver_name'     => $name,
            'receiver_address'  => $address,
            'receiver_lat'      => $lat,
            'receiver_lng'      => $lng,
            'receiver_phone'    => $phone,
            'callback'          => $callback
        ];
        if(isset($data['tel']))         $datas['receiver_tel']          = $data['tel'];
        if(isset($data['tip']))         $datas['tips']                  = $data['tip'];
        if(isset($data['info']))        $datas['info']                  = $data['info'];
        if(isset($data['type']))        $datas['cargo_type']            = $data['type'];
        if(isset($data['weight']))      $datas['cargo_weight']          = $data['weight'];
        if(isset($data['num']))         $datas['cargo_num']             = $data['num'];
        if(isset($data['title']))       $datas['invoice_title']         = $data['title'];
        if(isset($data['mark']))        $datas['origin_mark']           = $data['mark'];
        if(isset($data['mark_no']))     $datas['origin_mark_no']        = $data['mark_no'];
        if(isset($data['insurance']))   $datas['is_use_insurance']      = $data['insurance'];
        if(isset($data['code']))        $datas['is_finish_code_needed'] = $data['code'];
        if(isset($data['time']))        $datas['delay_publish_time']    = $data['time'];
        if(isset($data['delivery']))    $datas['is_direct_delivery']    = $data['delivery'];
        if($action == 'add'){
            return $this->mxCurl('/api/order/addOrder',$datas);
        }elseif($action == 'update'){
            return $this->mxCurl('/api/order/reAddOrder',$datas);
        }elseif($action == 'get'){
            return $this->mxCurl('/api/order/queryDeliverFee',$datas);
        }
    }

    /**
     * @name 订单发布
     * @param $no 平台订单号
     * @return array ["code"=>0 ,"msg"=>"" ]
     */
    public function releaseOrder($no){
        return $this->mxCurl('/api/order/addAfterQuery',[ 'deliveryNo' => $no ]);
    }

    /**
     * @name 订单详情
     * @param $order 订单号
     * @return array ["code"=>0 ,"msg"=>""  "result"=> [
     *             "orderId" => '第三方订单编号',
     *             "statusCode" => "订单状态(待接单＝1 待取货＝2 配送中＝3 已完成＝4 已取消＝5 已过期＝7 指派单=8 妥投异常之物品返回中=9 妥投异常之物品返回完成=10 系统故障订单发布失败=1000 可参考文末的状态说明）"
     *             "statusMsg"=>"订单状态",
     *             "transporterName"=>"	骑手姓名",
     *             "transporterPhone"=>"骑手电话",
     *             "transporterLng"=>"骑手经度",
     *             "transporterLat"=>"骑手纬度",
     *             "deliveryFee"=>"配送费",
     *             "tips"=>"小费",
     *             "couponFee"=>"优惠券费用",
     *             "insuranceFee"=>"保价费",
     *             "actualFee" => "实际支付费用",
     *             "distance"=>"配送距离",
     *             "createTime" =>"发单时间",
     *             "acceptTime"=>"接单时间",
     *             "fetchTime"=>"取货时间",
     *             "finishTime"=>"送达时间",
     *             "cancelTime"=>"取消时间",
     *             "orderFinishCode"=>"收货码"
     * ] ]
     */
    public function getOrder($order){
        return $this->mxCurl('/api/order/status/query',[ 'order_id' => $order ]);
    }

    /**
     * @name 订单取消
     * @param $order 订单号
     * @param $cancel 取消原因id
     * @param $reason = '' 取消原因(当取消原因ID为其他时，此字段必填)
     * @return array ["code"=>0 ,"msg"=>""  "result"=> "" ]
     */
    public function cancelOrder($order, $cancel, $reason = ''){
        return $this->mxCurl('/api/order/formalCancel',[ "order_id" => $order , "cancel_reason_id"=>$cancel ,"cancel_reason"=> $reason]);
    }

    /**
     * @name 添加小费
     * @param $order 第三方订单编号
     * @param $tip 小费
     * @param $cityCode 订单城市区号
     * @param $info = "" 备注
     * @return array ["code"=>0 ,"msg"=>""  ]
     */
    public function tipOrder($order, $tip, $cityCode, $info = ""){
        return $this->mxCurl('/api/order/addTip',[ "order_id"=>$order, "tips"=>$tip, "city_code"=>$cityCode, "info"=>$info ]);
    }

    /**
     * @name 订单追加骑手
     * @param $order 追加的第三方订单ID
     * @param $transporter 追加的配送员ID
     * @param $shop 追加订单的门店编码
     * @return array ["code"=>0 ,"msg"=>""  "result"=> "" ]
     */
    public function addToOrder($order, $transporter, $shop){
        return $this->mxCurl('/api/order/appoint/exist',[ "order_id" => $order , "transporter_id" => $transporter , "shop_no" => $shop ]);
    }

    /**
     * @name 取消追加骑手
     * @param $order  第三方订单号
     * @return array ["code"=>0 ,"msg"=>""  "result"=> "" ]
     */
    public function cancelToOrder($order){
        return $this->mxCurl('/api/order/appoint/cancel',[ "order_id" => $order ]);
    }


    /**
     * @name 订单回调
     * @return array ["code"=>0 ,"msg"=>""  "result"=> "" ]
     */
    public function orderCallback(){
        return $this->mxCurl('',[ ]);
    }
    // ====================    临时    ====================
    public function cacheAccept($order){
        return $this->mxCurl('/api/order/accept',[ "order_id" => $order ]);
    }

    public function cacheFetch($order){
        return $this->mxCurl('/api/order/fetch',[ "order_id" => $order ]);
    }

    public function cacheFinish($order){
        return $this->mxCurl('/api/order/finish',[ "order_id" => $order ]);
    }

    public function cacheCancel($order,$reason=''){
        return $this->mxCurl('/api/order/cancel',[ "order_id" => $order ,'reason'=>$reason ]);
    }

    public function cacheExpire($order){
        return $this->mxCurl('/api/order/expire',[ "order_id" => $order ]);
    }


}
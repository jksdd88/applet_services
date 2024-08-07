<?php
/**
 * Created by PhpStorm.
 * User: wangshiliang@dodoca.com
 * Date: 2018/6/25
 * Time: 10:43
 */

namespace App\Jobs;

use App\Jobs\Job;
use App\Services\WeixinService;
use Illuminate\Contracts\Bus\SelfHandling;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Services\WeixinMsgService;
use App\Models\OrderAppt;
use App\Models\Store;
use App\Models\OrderGoods;
use App\Models\Member as MemberModel;

class WeixinMsgs extends Job implements SelfHandling, ShouldQueue
{

    private $data;
    private $type;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($type,$data)
    {
        //
        $this->data = $data;
        $this->type = $type;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        //
        switch ($this->type){
            case 'order' : $this->order();break;
            default: ;
        }

    }


    private function order(){
        $weixinMsgService = new WeixinMsgService();
        //预约成功通知
        if($this->data['order_type'] == 4){
            $apptInfo = OrderAppt::get_data_by_order($this->data['id'],$this->data['merchant_id']);
            if(isset($apptInfo['id'])){
                $weixinMsgService->subscribe([
                    'merchant_id'=>$this->data['merchant_id'],
                    'name'=>$apptInfo['customer'],
                    'phone'=>$apptInfo['customer_mobile'],
                    'time'=>$apptInfo['appt_string'],
                    'details'=>'客户预定了'.$apptInfo['goods_title'].'，将于'.$apptInfo['appt_string'].'到'.$apptInfo['store_name'].'使用'
                ]);
            }
        }
        //订单待发货提醒
        else if($this->data['delivery_type'] == 1){
            $memberInfo = MemberModel::get_data_by_id($this->data['member_id'],$this->data['merchant_id']);
            if(isset($memberInfo['id'])){
                $weixinMsgService->pendingDelivery([
                    'merchant_id'=>$this->data['merchant_id'],
                    'no'=>$this->data['order_sn'],
                    'price' => '￥'.$this->data['amount'],
                    'buyer' => $memberInfo['name'] ,
                    'status' => '待发货'
                ]);
            }
        }
        //订单自提通知
        else if($this->data['delivery_type'] == 2){
            $storeInfo = Store::get_data_by_id($this->data['store_id'],$this->data['merchant_id']);
            $goodsInfo = OrderGoods::get_data_list(['order_id'=>$this->data['id'],'merchant_id'=>$this->data['merchant_id']],'goods_name,props');
            $order_title = '';
            foreach ($goodsInfo as $k=>$v) {
                $order_title .= $v['goods_name'].(!empty($v['props'])? '('.$v['props'].')' : '').',';
            }
            $order_title = trim($order_title,',');
            if( mb_strlen($order_title,'utf-8')  > 16  ){
                $order_title = mb_substr($order_title,0,16,'utf-8').'…';
            }
            if(isset($storeInfo['id'])){
                $weixinMsgService->extractionSelf([
                    'merchant_id'=>$this->data['merchant_id'],
                    'no'=>$this->data['order_sn'],
                    'price' => '￥'.$this->data['amount'],
                    'details'=>$order_title,
                    'stores'=>$storeInfo['name'],
                    'address' => $storeInfo['province_name'].$storeInfo['city_name'].$storeInfo['district_name'].$storeInfo['address']
                ]);
            }
        }
        //虚拟商品订单支付通知
        else if($this->data['order_goods_type'] == 1){
            $quantity = 0;
            $goodsInfo = OrderGoods::get_data_list(['order_id'=>$this->data['id'],'merchant_id'=>$this->data['merchant_id']],'id,quantity');
            foreach ($goodsInfo as $k => $v) {
                $quantity += $v['quantity'];
            }
            $weixinMsgService->virtualGood([
                'merchant_id'=>$this->data['merchant_id'],
                'no'=>$this->data['order_sn'],
                'name'=>$this->data['order_title'],
                'count'=>$quantity,
                'price'=>'￥'.$this->data['amount']
            ]);
        }
    }
}

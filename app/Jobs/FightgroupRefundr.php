<?php

/**
 * 单个拼团退款（手动结束成团/跑脚本拼团活动到时间的未成团）
 * @author changzhixian
 * @cdate 2017-9-14
 *
 * @param int $launchId   拼团发起表id
 * @param int $type_call  调用方式 1 商户后台手动结束拼团阶梯； 2 跑脚本调用（到时间未成团退款）
 */

namespace App\Jobs;

use App\Jobs\Job;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Bus\SelfHandling;
use Illuminate\Contracts\Queue\ShouldQueue;

use App\Models\FightgroupItem;
use App\Models\FightgroupJoin;
use App\Models\FightgroupRefund;
use App\Models\OrderInfo;
use App\Models\OrderUmp;

use App\Services\GoodsService;
use App\Services\BuyService;


class FightgroupRefundr extends Job implements SelfHandling, ShouldQueue
{
    public function __construct($launchId=0,$type_call=0)
    {
        $this->launchId = $launchId;
        $this->type_call = $type_call;
    }


    public function handle(GoodsService $GoodsService,BuyService $BuyService)
    {

        //查询所有订单
        $joins = FightgroupJoin::select('id','merchant_id','member_id','fightgroup_id','launch_id','order_id','order_sn','nickname','item_id','status','num')
                ->where('launch_id',$this->launchId)->whereIn('status',[PIN_JOIN_PAID])->get()->toArray();
        if($joins) {
                foreach($joins as $k=>$v){
                    //修改状态为手动关闭或改为状态跑脚本未成团
                    $status = ["1"=>PIN_JOIN_FAIL_MERCHANT,"2"=>PIN_JOIN_FAIL_END];
                    $updata = array('status'=>$status[$this->type_call]);
                    FightgroupJoin::update_data($v['id'],$v['merchant_id'],$updata);

                    //参团支付成功
                    if($v['status'] == PIN_JOIN_PAID){
                        //还库存接口
                        $itemInfo = FightgroupItem::select('id', 'goods_id', 'spec_id')->where('id',$v['item_id'])->first();
                        $param['merchant_id']   = $v['merchant_id'];
                        $param['goods_id']      = $itemInfo['goods_id'];
                        $param['goods_spec_id'] = $itemInfo['spec_id'] > 0 ? $itemInfo['spec_id'] : "0";//规格id 没有传0
                        $param['activity']      = 'tuan';//$activity 商品所需操作库存类型  普通商品：可不传  拼团：tuan
                        $param['stock_num']     = $v['num'];
                        $GoodsService->incStock($param);
                        
                        
                        //减销量（调用减销量方法）
                        //商品规格id
                        $goods_spec_id = isset($itemInfo['spec_id']) ? $itemInfo['spec_id'] : 0;
                        
                        $goods_des_csale_data = [
                            'merchant_id' => $v['merchant_id'],  //商户id
                            'stock_num' => $v['num'],    //减销量数量
                            'goods_id' => $itemInfo['goods_id'],    //商品id
                            'goods_spec_id' => $goods_spec_id  //规格id 没有传0
                        ];
                        $GoodsService->desCsale($goods_des_csale_data);
                        

                        //退款
                        $total_amount = OrderInfo::where('id', $v['order_id'])->value('amount');
                        if($this->type_call == 1){
                            $reason = "商家后台手动结束";
                        }elseif($this->type_call == 2){
                            $reason = "跑脚本未成团";
                        }else{
                            $reason = "未知";
                        }
                        $credit = OrderUmp::where('order_id', $v['order_id'])->where('ump_type',3)->value('credit');

                        $insertdata['merchant_id'] = $v['merchant_id'];
                        $insertdata['fightgroup_id'] = $v['fightgroup_id'];
                        $insertdata['launch_id'] = $v['launch_id'];
                        $insertdata['order_id'] = $v['order_id'];
                        $insertdata['member_id'] = $v['member_id'];
                        $insertdata['order_sn'] = $v['order_sn'];
                        $insertdata['nickname'] = $v['nickname'];
                        $insertdata['pay_type'] = 1;//微信支付
                        $insertdata['total_amount'] = $total_amount > 0 ? $total_amount : 0;
                        $insertdata['reason'] = $reason ;
                        $insertdata['credit'] = $credit > 0 ? $credit : 0 ;
                        $insertdata['status'] = PIN_REFUND_SUBMIT ;
                        $FightgroupRefund_id = FightgroupRefund::insertGetId($insertdata); //插入一条退款记录

                        //更新订单状态
                        $explain = ["1"=>"拼团，手动结束拼团阶梯未成团退款，订单取消","2"=>"拼团，团时间结束未成团退款，订单取消"];
                        $Orderdata = [
                            'status'		=>	ORDER_MERCHANT_CANCEL,
                            'explain'		=>	$explain[$this->type_call]
                        ];
                        OrderInfo::update_data($v['order_id'],$v['merchant_id'],$Orderdata);



                        //调大拿退款接口
                        $data_orderrefund['merchant_id'] = $v['merchant_id'];
                        $data_orderrefund['order_id']    = $v['order_id'];
                        $data_orderrefund['apply_type']  = 2;
                        $return_data = $BuyService->orderrefund($data_orderrefund);

                        if($FightgroupRefund_id>0){
                            $fightgroupUpdata['memo'] = json_encode($return_data);
                            //更新退款记录
                            FightgroupRefund::update_data($FightgroupRefund_id,$v['merchant_id'],$fightgroupUpdata);
                        }

                    }
                }
        }
    }

}

<?php
/**
 * 虚拟商品服务类
 * @author wangshen@dodoca.com
 * @cdate 2018-3-6
 * 
 */
namespace App\Services;

use App\Models\GoodsVirtual;
use App\Models\OrderVirtualgoods;
use App\Models\OrderGoods;
use App\Models\OrderInfo;
use App\Models\OrderRefund;

use App\Services\BuyService;

class VirtualGoodsService {
    
    /**
     * 获取虚拟商品信息
     * @author wangshen@dodoca.com
	 * @cdate 2018-3-6
     */
    public function getVirtualGoodsInfo($goods_id,$merchant_id){
        
        $goods_virtual_info = GoodsVirtual::get_data_by_id($goods_id, $merchant_id);
        if($goods_virtual_info){
            $info = [
                'time_type' => $goods_virtual_info['time_type'],
                'start_time' => $goods_virtual_info['start_time'],
                'end_time' => $goods_virtual_info['end_time']
            ];
            
            $info['start_time'] = date('Y年m月d日', strtotime($info['start_time']));
            $info['end_time'] = date('Y年m月d日', strtotime($info['end_time']));
            
            
            
            return ['errcode' => 0,'errmsg' => '获取虚拟商品信息成功','data' => ['goods_virtual_info' => $info]];
        }else{
            return ['errcode' => 210001,'errmsg' => '未获取到虚拟商品信息'];
        }
    }
    
    
    /**
     * 虚拟商品订单生成核销码数据
     * @author wangshen@dodoca.com
     * @cdate 2018-3-7
     */
    public function createVirtualHexiao($order){
        if($order['is_oversold'] == 0 && $order['order_goods_type'] == ORDER_GOODS_VIRTUAL){
    		
            $ordergoods = OrderGoods::get_data_by_order($order['id'], $order['merchant_id'], 'id,quantity');
            
            if($ordergoods){
                
                //核销码数量为商品购买数量
                $num = $ordergoods['quantity'];
                
                //商品数量位数长度
                $num_length = strlen($ordergoods['quantity']);
                
                //核销码
                $BuyService = new BuyService();
                $hexiao_code = $BuyService->get_hexiao_sn('virtual',10);
                
                
                for($i=0;$i<$num;$i++){
                    
                    //核销码后面拼接相同长度数字
                    $x = $i+1;
                    $hexiao_code_next = sprintf("%0".$num_length."d", $x);
                    $hexiao_code_true = $hexiao_code.$hexiao_code_next;
                    
                    
                    //核销记录表插入数据
                    $order_virtualgoods_data = [
                        'merchant_id' => $order['merchant_id'],
                        'member_id' => $order['member_id'],
                        'order_id' => $order['id'],
                        'order_sn' => $order['order_sn'],
                        'hexiao_code' => $hexiao_code_true,
                        'hexiao_status' => 0
                    ];
                    
                    OrderVirtualgoods::insert_data($order_virtualgoods_data);
                }
            
                return ['errcode' => 0,'errmsg' => '生成虚拟商品核销码成功'];
            }
        }
    }
    
    /**
     * 虚拟商品订单剩余核销次数
     * @author wangshen@dodoca.com
     * @cdate 2018-3-7
     */
    public function residueHexiao($order){
        
        $num = 0;
        
        $ordergoods = OrderGoods::get_data_by_order($order['id'], $order['merchant_id'], 'id,quantity,goods_id');
        
        if($ordergoods){
            //申请退款中
            $order_refund_sum = 0;
            $order_refund_sum = OrderRefund::where(['order_id'=>$order['id']])
                ->whereNotIn('status',array(REFUND_FINISHED,REFUND_CLOSE,REFUND_CANCEL))
                ->sum('refund_quantity');
            //虚拟商品信息
            $goods_virtual_info = GoodsVirtual::get_data_by_id($ordergoods['goods_id'], $order['merchant_id']);
            
            if($goods_virtual_info){
                
                //未核销数量
                $num = OrderVirtualgoods::where('merchant_id','=',$order['merchant_id'])
                                        ->where('order_id','=',$order['id'])
                                        ->where('hexiao_status','=',0)
                                        ->count();
                
                //指定有效期
                if($goods_virtual_info['time_type'] == 1){
                    if($goods_virtual_info['end_time'] < date('Y-m-d H:i:s')){
                        //当前时间过了有效期，则无剩余次数
                        $num = 0;
                    } else if($goods_virtual_info['start_time'] > date('Y-m-d H:i:s')){
                        //当前时间未到有效期，则无剩余次数
                        $num = 0;
                    }else{
                        $num = $num-$order_refund_sum;
                    }
                }else{
                    $num = $num-$order_refund_sum;
                }
            }
        }
        
        return $num;
    }
    
    /**
     * 虚拟商品订单，已核销次数
     * @author wangshen@dodoca.com
     * @cdate 2018-3-9
     */
    public function finishHexiaoNum($order){
    
        $num = 0;

        //已核销数量
        $num = OrderVirtualgoods::where('merchant_id','=',$order['merchant_id'])
                                ->where('order_id','=',$order['id'])
                                ->where('hexiao_status','=',1)
                                ->count();

        return $num;
    }

    /**
     * 虚拟商品订单，未核销次数（不管是否失效）
     * @author wangshen@dodoca.com
     * @cdate 2018-3-9
     */
    public function notHexiaoNum($order){
    
        $num = 0;
    
        //未核销数量
        $num = OrderVirtualgoods::where('merchant_id','=',$order['merchant_id'])
                                ->where('order_id','=',$order['id'])
                                ->where('hexiao_status','=',0)
                                ->count();
    
        return $num;
    }
    
    
    /**
     * 虚拟商品订单，根据退款数量，把核销表对应数量的记录变为退款完成
     * @author wangshen@dodoca.com
     * @cdate 2018-3-13
     */
    public function changeHexiao($order,$refund_num){
    
        //查询未核销记录
        $wheres = [];
        $wheres[] = ['column' => 'merchant_id','operator' => '=','value' => $order['merchant_id']];
        $wheres[] = ['column' => 'order_id','operator' => '=','value' => $order['id']];
        $wheres[] = ['column' => 'hexiao_status','operator' => '=','value' => 0];
        
        //查询字段
        $fields = 'id,hexiao_status';
        
        $offset = 0;
        $limit = $refund_num;
        
        //列表数据
        $order_virtualgoods_list = OrderVirtualgoods::get_data_list($wheres,$fields,$offset,$limit);
        
        if($order_virtualgoods_list){
            foreach($order_virtualgoods_list as $key=>$val){
                OrderVirtualgoods::update_data($val['id'], $order['merchant_id'], ['hexiao_status' => 2]);
            }
        }
        
        return ['errcode' => 0,'errmsg' => '成功'];
    }
    
    
    /**
     * 虚拟商品订单，完成订单
     * @author wangshen@dodoca.com
     * @cdate 2018-3-13
     */
    public function successOrder($order){
        
        $order = OrderInfo::get_data_by_id($order['id'],$order['merchant_id']);
        
        if($order['order_goods_type'] == ORDER_GOODS_VIRTUAL){
        
            $virtual_not_num = 0;//虚拟商品订单，未核销次数
            $virtual_not_num = $this->notHexiaoNum($order);//未核销次数
            
            //订单状态非维权关闭，且未核销次数为0，订单完成
            if($virtual_not_num == 0 && $order['status'] != ORDER_REFUND_CANCEL){
                //订单完成
                $data = [
                    'status'		=>	ORDER_SUCCESS,
                    'finished_time'	=>	date("Y-m-d H:i:s"),
                ];
                $result = OrderInfo::update_data($order['id'],$order['merchant_id'],$data);
            }
        
        }
        
        return ['errcode' => 0,'errmsg' => '成功'];
    }
    

}
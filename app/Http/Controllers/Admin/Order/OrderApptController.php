<?php

/**
 * Date: 2017/9/25
 * Time: 11:01
 * Author:shangyazhao
 * Modify:songyongshang@dodoca.com
 * 后台核销记录
 */

namespace App\Http\Controllers\Admin\Order;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Auth;
use App\Models\OrderAppt;
use App\Models\OrderGoods;
use App\Models\OrderInfo;
use App\Models\User;
use App\Models\UserLog;
use App\Models\OrderRefund;
use App\Models\OrderSelffetch;
use App\Models\OrderRefund as OrderRefund_OBJ;
use App\Models\OrderVirtualgoods;
use App\Models\Member;
use App\Models\WeixinInfo;
use App\Models\GoodsVirtual;
use App\Services\UserPrivService;
use App\Services\OrderPrintService;

class OrderApptController extends Controller {

    protected $params; //参数
    protected $merchant_id; //参数
    private $user_id;
    private $order_selffetch;
    private $order_goods; 
    private $order_info; 
    private $order_Selffetch;

    public function __construct(Request $request, OrderAppt $order_appt, OrderGoods $order_goods, OrderInfo $order_info,OrderSelffetch $order_Selffetch ) {
        $this->params = $request->all();
        $this->merchant_id = Auth::user()->merchant_id;
        $this->user_id = Auth::user()->id;
        $this->order_appt = $order_appt;
        $this->order_goods = $order_goods;      
        $this->order_info=$order_info;
        $this->order_Selffetch=$order_Selffetch;
    }

    /* 获取核销码信息
     * shangyazhao@dodoca.com
     */
    public function getHexiaoCode() {
        //核销类型
        if( !isset($this->params['hexiao_type']) || empty($this->params['hexiao_type']) ){
            $data['errcode'] = 140005;
            $data['errmsg'] = '请选择核销类型';
            $data['data'] = [];
            return Response::json($data);
        }else if(!in_array($this->params['hexiao_type'], array('appt','selffetch','virtualgoods'))){
            $data['errcode'] = 140006;
            $data['errmsg'] = '核销类型 异常';
            $data['data'] = [];
            return Response::json($data);
        }

        //核销码
        $code = isset($this->params['code']) ? $this->params['code'] : '';
        if (empty($code)) {
            $data['errcode'] = 140001;
            $data['errmsg'] = '请输入核销码';
            $data['data'] = [];
            return Response::json($data);
        }
        
        if($this->params['hexiao_type']=='appt'){
            //预约服务核销 权限
            $if_auth = UserPrivService::getHexiaoPriv(Auth::user()->id,Auth::user()->merchant_id,Auth::user()->is_admin,'trade_orderhexiao_appt');
            if( !isset($if_auth['errcode']) || $if_auth['errcode']!='has_priv' ){
                $rt['errcode']=111302;
                $rt['errmsg']='您没有预约服务核销权限';
                return Response::json($rt);
            }
            //预约服务
            $query = OrderAppt::where(['order_info.order_type'=>4])
                    ->leftjoin('order_info','order_info.id','=','order_appt.order_id')
                    ->where(['order_appt.hexiao_code'=>$code, 'order_appt.merchant_id'=>$this->merchant_id]);
            $res = $query->first();
            if (empty($res)) {
                $data['errcode'] = 140002;
                $data['errmsg'] = '查不到此核销码对应的订单';
                return Response::json($data);
            }
            //是否本门店
            $res['thisstore'] = 1;
            if($res['store_id']!=Auth::user()->store_id){
                $res['thisstore'] = 0;
            }
            //维权信息
            $res['refund_status_msg'] = $res['status']==4?'维权完成':($res['refund_status']==1?'维权中':'');
            //服务时间:提示
            if(date('Y-m-d'>$res['appt_date'])){
                $Days = round((time()-strtotime($res['appt_date']))/3600/24);
                $res['appt_string_notice'] = '提示:与预约服务时间延迟了'.$Days.'天';
            }else if(date('Y-m-d'<$res['appt_date'])){
                $Days = round((strtotime($res['appt_date'])-time())/3600/24);
                $res['appt_string_notice'] = '提示:与预约服务时间提前了'.$Days.'天';
            }
            
            $res['hexiao_status_msg'] = config('varconfig.order_appt_hexiao_status')[$res['hexiao_status']];
        }else if($this->params['hexiao_type']=='selffetch'){
            //上门自提核销 权限
            $if_auth = UserPrivService::getHexiaoPriv(Auth::user()->id,Auth::user()->merchant_id,Auth::user()->is_admin,'trade_orderhexiao_selffetch');
            if( !isset($if_auth['errcode']) || $if_auth['errcode']!='has_priv' ){
                $rt['errcode']=111302;
                $rt['errmsg']='您没有上门自提核销权限';
                return Response::json($rt);
            }
            //上门自提
            $query = OrderSelffetch::where(['order_info.delivery_type'=>2])
                    ->leftjoin('order_info','order_info.id','=','order_selffetch.order_id')
                    ->where(['order_selffetch.hexiao_code'=>$code, 'order_selffetch.merchant_id'=>$this->merchant_id]);            
            $res = $query->first();
            if (empty($res)) {
                $data['errcode'] = 140002;
                $data['errmsg'] = '查不到此核销码对应的订单';
                return Response::json($data);
            }
            
            //上门自提商品信息
            $query_order_selffetch = OrderGoods::where(['order_goods.order_id'=>$res['order_id'],'order_goods.merchant_id'=>$this->merchant_id])
                    ->leftjoin('order_info','order_info.id','=','order_goods.order_id')
                    ->select('order_goods.goods_img','order_goods.goods_name','order_goods.props','order_goods.quantity','order_goods.order_id','order_goods.goods_id','order_goods.spec_id',
                        'order_info.status')
                    ->get();
            if(!empty($query_order_selffetch)){
                //是否本门店
                $res['thisstore'] = 1;
                foreach ($query_order_selffetch as $key=>$val){
                    //是否本门店
                    if($res['store_id']!=Auth::user()->store_id){
                        $res['thisstore'] = 0;
                    }
                    //维权信息
                    if ($res['refund_status']==0) {
                        $query_order_selffetch[$key]['refund_status_msg'] = '';
                    }else{
                        //上门自提的维权说明
                        //单规格
                        if(empty($val['spec_id'])){
                            $order_refund_finish_sum = OrderRefund::where(['order_refund.order_id'=>$val['order_id'],'order_refund.status'=>REFUND_FINISHED,'goods_id'=>$val['goods_id']])->sum('refund_quantity');
                            $order_refund_doing_sum = OrderRefund::where(['order_refund.order_id'=>$val['order_id'],'goods_id'=>$val['goods_id']])
                                ->whereNotIn('order_refund.status',array(REFUND_FINISHED,REFUND_CLOSE,REFUND_CANCEL))
                                ->sum('refund_quantity');
                        }
                        //多规格
                        else{
                            $order_refund_finish_sum = OrderRefund::where(['order_refund.order_id'=>$val['order_id'],'order_refund.status'=>REFUND_FINISHED,'goods_id'=>$val['goods_id'],'spec_id'=>$val['spec_id']])->sum('refund_quantity');
                            $order_refund_doing_sum = OrderRefund::where(['order_refund.order_id'=>$val['order_id'],'goods_id'=>$val['goods_id'],'spec_id'=>$val['spec_id']])
                                ->whereNotIn('order_refund.status',array(REFUND_FINISHED,REFUND_CLOSE,REFUND_CANCEL))
                                ->sum('refund_quantity');
                        }
                        //dd($order_refund_finish_sum);
                        if( !empty($order_refund_finish_sum) && $val['quantity']==$order_refund_finish_sum ){
                            $query_order_selffetch[$key]['refund_status_msg'] = '维权完成';
                        }else if($order_refund_doing_sum>0){
                            $query_order_selffetch[$key]['refund_status_msg'] = '维权中';
                        }
                    }
                }
                $res['order_selffetch'] = $query_order_selffetch;
            }
        }else if($this->params['hexiao_type']=='virtualgoods'){
            //虚拟商品
            //虚拟商品 核销权限
            $if_auth = UserPrivService::getHexiaoPriv(Auth::user()->id,Auth::user()->merchant_id,Auth::user()->is_admin,'trade_orderhexiao_virtualgoods');
            if( !isset($if_auth['errcode']) || $if_auth['errcode']!='has_priv' ){
                $rt['errcode']=111302;
                $rt['errmsg']='您没有虚拟商品核销权限';
                return Response::json($rt);
            }
            //订单表扩展_虚拟商品核销
            $query_virtualgoods = OrderVirtualgoods::where('hexiao_code','like',substr($code, 0, 10).'%')
                    ->where(['merchant_id'=>$this->merchant_id])
                    ->get();
            //dd($query_virtualgoods);
            if ( $query_virtualgoods->isEmpty() ) {
                $data['errcode'] = 140009;
                $data['errmsg'] = '查不到此核销码对应的订单.';
                return Response::json($data);
            }
            
            //订单表
            $query_order_info = OrderInfo::where(['merchant_id'=>$this->merchant_id,'order_goods_type'=>1,'id'=>$query_virtualgoods[0]['order_id']])->first();
            if ( empty($query_order_info) ) {
                $data['errcode'] = 140010;
                $data['errmsg'] = '查不到此核销码对应的订单;';
                return Response::json($data);
            }
            //dd($query_order_info);
            //订单商品表
            $query_order_goods = OrderGoods::where(['order_id'=>$query_order_info['id'],'merchant_id'=>$this->merchant_id])->first();
            if( empty($query_order_goods) ){
                $data['errcode'] = 140011;
                $data['errmsg'] = '查不到此核销码对应的商品';
                return Response::json($data);
            }
            $data['data'] = $query_order_goods;
            
            //虚拟商品扩展表
            $rs_goods_virtual = GoodsVirtual::where(['merchant_id'=>$this->merchant_id,'goods_id'=>$query_order_goods['goods_id']])->first();
            if(empty($rs_goods_virtual)){
                $data['errcode'] = 140004;
                $data['errmsg'] = '虚拟商品出错';
                return Response::json($data);
            }
            $arr = array();
            foreach ($query_virtualgoods as $key=>$val) {
                $var_hexiao = config('varconfig.order_virtualgoods_hexiao_status');
                $val['hexiao_msg'] = isset($var_hexiao[$val['hexiao_status']])?$var_hexiao[$val['hexiao_status']]:'';
                
                $val['is_hexiao'] = 0;
                if( $rs_goods_virtual['time_type']==1 && date('Y-m-d H:i:s')>=$rs_goods_virtual['start_time'] && date('Y-m-d H:i:s')<=$rs_goods_virtual['end_time'] && $val['hexiao_status']==0){
                    $val['is_hexiao'] = 1;
                }elseif( $rs_goods_virtual['time_type']==0 && $val['hexiao_status']==0){
                    $val['is_hexiao'] = 1;
                }
                //dd($val);
                if( $rs_goods_virtual['time_type']==1 && date('Y-m-d H:i:s')>$rs_goods_virtual['end_time'] && $val['hexiao_status']==0){
                    $val['hexiao_msg'] = '已失效';
                }
                $arr[] = $val;
            }
            //dd($arr);
            $order_virtualgoods['order_info'] = $query_order_info;
            $data['data']['order_sn'] = $query_order_info['order_sn'];
            
            
            //维权信息
            if ($query_order_info['refund_status']==0) {
                $data['data']['refund_status_msg'] = '';
            }else{
                //维权说明
                //单规格
                if(empty($query_order_goods['spec_id'])){
                    //已退款
                    $order_refund_finish_sum = OrderRefund::where(['order_id'=>$query_order_info['id'],'status'=>REFUND_FINISHED,'goods_id'=>$query_order_goods['goods_id']])->sum('refund_quantity');
                    //dd($order_refund_finish_sum);
                    //申请退款中
                    $order_refund_doing_sum = OrderRefund::where(['order_id'=>$query_order_info['id'],'goods_id'=>$query_order_goods['goods_id']])
                            ->whereNotIn('status',array(REFUND_FINISHED,REFUND_CLOSE,REFUND_CANCEL))
                            ->sum('refund_quantity');
                }
                //多规格
                else{
                    //已退款
                    $order_refund_finish_sum = OrderRefund::where(['order_id'=>$query_order_info['id'],'order_refund.status'=>REFUND_FINISHED,'goods_id'=>$query_order_goods['goods_id'],'spec_id'=>$query_order_goods['spec_id']])->sum('refund_quantity');
                    //申请退款中
                    $order_refund_doing_sum = OrderRefund::where(['order_id'=>$query_order_info['id'],'goods_id'=>$query_order_goods['goods_id'],'spec_id'=>$query_order_goods['spec_id']])
                            ->whereNotIn('status',array(REFUND_FINISHED,REFUND_CLOSE,REFUND_CANCEL))
                            ->sum('refund_quantity');
                }
                //dd($order_refund_doing_sum);
                if( !empty($order_refund_finish_sum) && $query_order_goods['quantity']==$order_refund_finish_sum){
                    $data['data']['refund_status_msg'] = '维权完成';
                } else {
                    $data['data']['refund_status_msg'] = '该订单有退款申请';
                    if($order_refund_finish_sum>0){
                        $data['data']['refund_status_msg'] .= '，其中'.$order_refund_finish_sum.'件退款完成';
                    }
                    if($order_refund_doing_sum>0){
                        $data['data']['refund_status_msg'] .= '，'.$order_refund_doing_sum.'件退款中';
                        if( !empty($arr) ){
                            $freeze = 0;
                            foreach ($arr as $key=>$val){
                                if( $freeze<$order_refund_doing_sum && $val['hexiao_status']==0 ){
                                    $val['is_hexiao'] = 0;
                                    $val['hexiao_msg'] = ($val['hexiao_msg']=='未使用'||$val['hexiao_msg']=='已失效')?($val['hexiao_msg'].'(维权中)'):'维权中';
                                    $val['hexiao_status'] = 4;
                                    $arr[$key]=$val;
                                    $freeze++;
                                }
                            }
                        }
                    }
                }
            }
            $order_virtualgoods['hexiao_info'] = $arr;
            $data['data']['order_virtualgoods'] = $order_virtualgoods;
            
            $data['data']['time_type'] = $rs_goods_virtual['time_type'];
            $data['data']['start_time'] = $rs_goods_virtual['start_time'];
            $data['data']['end_time'] = $rs_goods_virtual['end_time'];
            
            $data['errcode'] = 0;
            return Response::json($data);
        }
        $var_hexiao = config('varconfig.order_appt_hexiao_status');
        $res['hexiao_status_msg'] = isset($var_hexiao[$res['hexiao_status']])?$var_hexiao[$res['hexiao_status']]:'';
        
        $result = $this->order_goods->get_data_by_order($res['order_id'], $this->merchant_id, 'goods_name,goods_img');
        if (empty($result)) {
            $data['errcode'] = 140004;
            $data['errmsg'] = '非法订单';
            return Response::json($data);
        }
        
        $res['good_img'] = $result['goods_img'];
        $res['goods_name'] = $result['goods_name'];
        $data['errcode'] = 0;
        $data['data'] = $res;
        return Response::json($data);
        
    }

    /* 核销码核销
     * shangyazhao@dodoca.com
     */
    public function dealCode() {
        //核销类型
        if( !isset($this->params['hexiao_type']) || empty($this->params['hexiao_type']) ){
            $data['errcode'] = 140005;
            $data['errmsg'] = '请选择核销类型';
            $data['data'] = [];
            return Response::json($data);
        }else if(!in_array($this->params['hexiao_type'], array('appt','selffetch','virtualgoods'))){
            $data['errcode'] = 140006;
            $data['errmsg'] = '核销类型 异常';
            $data['data'] = [];
            return Response::json($data);
        }
        if( !isset($this->params['order_id']) || empty($this->params['order_id']) ){
            $data['errcode'] = 140007;
            $data['errmsg'] = '订单id不能为空';
            $data['data'] = [];
            return Response::json($data);
        }
        $rs_orderinfos = $this->order_info->get_data_by_id($this->params['order_id'], $this->merchant_id);
        if(empty($rs_orderinfos)){
            $data['errcode'] = 140008;
            $data['errmsg'] = '查不到此订单信息';
            $data['data'] = [];
            return Response::json($data);
        }else if($this->params['hexiao_type']!='virtualgoods' && !in_array($rs_orderinfos['status'],array(9,10))){
            $data['errcode'] = 140009;
            $data['errmsg'] = '此订单状态下不可以进行核销操作';
            $data['data'] = [];
            return Response::json($data);
        }
        //核销码
        $code = isset($this->params['code']) ? $this->params['code'] : '';
        //dd($this->params);
        if( $this->params['hexiao_type']=='virtualgoods' && empty($this->params['hexiao_all']) && empty($this->params['code']) ){
            $data['errcode'] = 140001;
            $data['errmsg'] = '请输入核销码';
            $data['data'] = [];
            return Response::json($data);
        }elseif ( $this->params['hexiao_type']!='virtualgoods' && empty($code)) {
            $data['errcode'] = 140001;
            $data['errmsg'] = '请输入核销码';
            $data['data'] = [];
            return Response::json($data);
        }
        
        if($this->params['hexiao_type']=='appt'){
            //预约服务核销 权限
            $if_auth = UserPrivService::getHexiaoPriv(Auth::user()->id,Auth::user()->merchant_id,Auth::user()->is_admin,'trade_orderhexiao_appt');
            if( !isset($if_auth['errcode']) || $if_auth['errcode']!='has_priv' ){
                $rt['errcode']=111302;
                $rt['errmsg']='您没有预约服务核销权限';
                return Response::json($rt);
            }
            //预约服务核销
            $res = $this->order_appt->get_data_by_code($code, $this->merchant_id);
            if (empty($res)) {
                $data['errcode'] = 140002;
                $data['errmsg'] = '非法核销码';
                return Response::json($data);
            }
            //是否已核销
            if (isset($res['hexiao_status']) && $res['hexiao_status'] != 0) {
                $data['errcode'] = 140003;
                $data['errmsg'] = '该核销码已经核销过';
                return Response::json($data);
            }
            //是否本门店
            if ( $res['store_id']!= Auth::user()->store_id) {
                $data['errcode'] = 140004;
                $data['errmsg'] = '该笔订单不是本门店订单,不能操作.';
                return Response::json($data);
            }
            $update_data = array(
                'hexiao_status' => 1, 
                'hexiao_source' => 1,
                'hexiao_time' => date("Y-m-d H:i:s"), 
                'user_id' => $this->user_id,
                'username'=>Auth::user()->username                
            );
            $result = $this->order_appt->update_data_by_code($code, $this->merchant_id, $update_data);
            if ($result > 0) {
                $res = $this->order_appt->get_data_by_code($code, $this->merchant_id,'order_id');
                $data_order=array('status'=>11,'finished_time'=>date("Y-m-d H:i:s"));
                $this->order_info->update_data($res['order_id'], $this->merchant_id, $data_order);
                $data['errcode'] = 0;
                $data['errmsg'] = '核销成功';
            } else {
                $data['errcode'] = 1;
                $data['errmsg'] = '核销失败';
            }
            //更新订单状态
            $data_orderinfo['status'] = 11;
            $data_orderinfo['finished_time'] = date('Y-m-d H:i:s');
            $rs_orderinfo = OrderInfo::where(['merchant_id'=>Auth::user()->merchant_id,'id'=>$res['order_id']])->update($data_orderinfo);
            //小票机打印
            if($res['order_id']){
                $orderPrint = new OrderPrintService();
                $orderPrint->printOrder($res['order_id'],Auth::user()->merchant_id);
            }
        }else if($this->params['hexiao_type']=='selffetch'){
            //上门自提核销 权限
            $if_auth = UserPrivService::getHexiaoPriv(Auth::user()->id,Auth::user()->merchant_id,Auth::user()->is_admin,'trade_orderhexiao_selffetch');
            if( !isset($if_auth['errcode']) || $if_auth['errcode']!='has_priv' ){
                $rt['errcode']=111302;
                $rt['errmsg']='您没有上门自提核销权限';
                return Response::json($rt);
            }
            //上门自提核销
            $res = OrderSelffetch::where(['hexiao_code'=>$code, 'merchant_id'=>$this->merchant_id])->first();
            if (empty($res)) {
                $data['errcode'] = 140002;
                $data['errmsg'] = '非法核销码';
                return Response::json($data);
            }
            //是否已核销
            if (isset($res['hexiao_status']) && $res['hexiao_status'] != 0) {
                $data['errcode'] = 140003;
                $data['errmsg'] = '该核销码已经核销过';
                return Response::json($data);
            }
            //是否本门店
            if ( $rs_orderinfos['store_id']!= Auth::user()->store_id) {
                $data['errcode'] = 140004;
                $data['errmsg'] = '该笔订单不是本门店订单,不能操作!';
                return Response::json($data);
            }
            $update_data = array(
                'hexiao_status' => 1, 
                'hexiao_source' => 1,
                'hexiao_time' => date("Y-m-d H:i:s"), 
                'user_id' => $this->user_id
            );
            $result = OrderSelffetch::update_data($res['id'], $this->merchant_id, $update_data);
            if ($result > 0) {
                $data['errcode'] = 0;
                $data['errmsg'] = '核销成功';
            } else {
                $data['errcode'] = 140008;
                $data['errmsg'] = '核销失败';
            }
            //更新订单状态
            $data_orderinfo['status'] = 11;
            $data_orderinfo['finished_time'] = date('Y-m-d H:i:s');
            $rs_orderinfo = OrderInfo::where(['merchant_id'=>Auth::user()->merchant_id,'id'=>$res['order_id']])->update($data_orderinfo);
            //小票机打印
            if($res['order_id']){
                $orderPrint = new OrderPrintService();
                $orderPrint->printOrder($res['order_id'],Auth::user()->merchant_id);
            }

        }else if($this->params['hexiao_type']=='virtualgoods'){
            //虚拟商品 核销权限
            $if_auth = UserPrivService::getHexiaoPriv(Auth::user()->id,Auth::user()->merchant_id,Auth::user()->is_admin,'trade_orderhexiao_virtualgoods');
            if( !isset($if_auth['errcode']) || $if_auth['errcode']!='has_priv' ){
                $rt['errcode']=111302;
                $rt['errmsg']='您没有虚拟商品核销权限';
                return Response::json($rt);
            }
            
            $rs_isend = OrderVirtualgoods::where(['merchant_id'=>$this->merchant_id, 'order_id'=>$this->params['order_id'],'hexiao_status'=>0])->first();
            if(empty($rs_isend)){
                $rt['errcode']=0;
                $rt['errmsg']='此订单已经全部核销完成';
                return Response::json($rt);
            }
            
            //虚拟商品
            if( !isset($this->params['hexiao_all']) || $this->params['hexiao_all']!=1 ){
                // 1 虚拟商品核销表(单条记录)
                $res = OrderVirtualgoods::where(['merchant_id'=>$this->merchant_id, 'order_id'=>$this->params['order_id'], 'hexiao_code'=>$code])->first();
                if (empty($res)) {
                    $data['errcode'] = 140002;
                    $data['errmsg'] = '此核销码无效';
                    return Response::json($data);
                }elseif ( isset($res['hexiao_status']) ) {
                    if($res['hexiao_status'] == 1){
                        $data['errcode'] = 140003;
                        $data['errmsg'] = '此核销码已核销';
                        return Response::json($data);
                    }elseif($res['hexiao_status'] == 2){
                        $data['errcode'] = 140003;
                        $data['errmsg'] = '此核销码对应的商品已退款';
                        return Response::json($data);
                    }
                }
            }
            // 2 商品-虚拟商品(有效期)
            //商品订单表
            $rs_order_goods = OrderGoods::where(['merchant_id'=>$this->merchant_id,'order_id'=>$this->params['order_id']])->first();
            //虚拟商品表(所有记录)
            $rs_goods_virtual = GoodsVirtual::where(['merchant_id'=>$this->merchant_id, 'goods_id'=>$rs_order_goods['goods_id']])->first();
            if(empty($rs_goods_virtual)){
                $data['errcode'] = 140012;
                $data['errmsg'] = '查不到此商品';
                return Response::json($data);
            }elseif( $rs_goods_virtual['time_type']==1 ){
                if( date('Y-m-d H:i:s')<$rs_goods_virtual['start_time'] ){
                    $data['errcode'] = 140013;
                    $data['errmsg'] = '不在此商品的有效期,不可核销';
                    return Response::json($data);
                }elseif( date('Y-m-d H:i:s')>$rs_goods_virtual['end_time'] ){
                    $data['errcode'] = 140013;
                    $data['errmsg'] = '超过此商品的有效期,已失效';
                    return Response::json($data);
                }
            }
            // 3 订单表
            $rs_order_info = OrderInfo::get_data_by_id($this->params['order_id'], $this->merchant_id) ;
            if(empty($rs_order_info)){
                $data['errcode'] = 140012;
                $data['errmsg'] = '查不到此订单';
                return Response::json($data);
            }
            //4 退款表
            $rs_order_refund = OrderRefund::where(['merchant_id'=>$this->merchant_id,'order_id'=>$this->params['order_id']])->get();
            //退款有未完成的情况
            $refund_not_finish = 0;
            if(!empty($rs_order_refund)){
                foreach ($rs_order_refund as $key=>$val){
                    if(!in_array($val['status'], array(31,40,41))){
                        $refund_not_finish += $val['refund_quantity'];
                        break;
                    }
                }
            }
            //核销
            $update_data = array(
                'hexiao_status' => 1,
                'hexiao_source' => 1,
                'hexiao_time' => date("Y-m-d H:i:s"),
                'user_id' => $this->user_id
            );
            
            $result = 0;
            if( isset($this->params['hexiao_all'])&&$this->params['hexiao_all']==1 ){
                $rs_order_virtualgoods = OrderVirtualgoods::where(['merchant_id'=>$this->merchant_id, 'order_id'=>$this->params['order_id']])->get();
                $freeze = 0;
                foreach ($rs_order_virtualgoods as $key=>$val){
                    if($val['hexiao_status']==0){
                        if( $refund_not_finish>0 && $freeze<$refund_not_finish ){
                            $freeze++;
                            continue;
                        }
                        $result = OrderVirtualgoods::where(['id'=>$val['id'], 'hexiao_status'=>0])->update($update_data);
                    }
                }
            }else{
                //未核销数量
                $rs_num = OrderVirtualgoods::where(['merchant_id'=>$this->merchant_id,'order_id'=>$this->params['order_id'],'hexiao_status'=>0])->count();
                if( $refund_not_finish>0 && $rs_num<=$refund_not_finish){
                    $data['errcode'] = 140008;
                    $data['errmsg'] = '维权中的订单不可核销';
                    return Response::json($data);
                }
                $result = OrderVirtualgoods::update_data($res['id'], $this->merchant_id, $update_data);
            }
            // 5 虚拟商品核销表(所有记录)
            $rs_order_virtualgoods = OrderVirtualgoods::where(['merchant_id'=>$this->merchant_id, 'order_id'=>$this->params['order_id']])->get();
            //未核销数量
            $hexiao_nodoing = 0;
            //已核销
            $hexiao_finish = 0;
            //已退款
            $hexiao_refund = 0;
            if( !$rs_order_virtualgoods->isEmpty() ){
                foreach ($rs_order_virtualgoods as $key=>$val){
                    if($val['hexiao_status']==0){
                        $hexiao_nodoing++;
                    }elseif($val['hexiao_status']==1){
                        $hexiao_finish++;
                    }elseif($val['hexiao_status']==2){
                        $hexiao_refund++;
                    }
                }
            }
            
            if ($result > 0) {
                $data['errcode'] = 0;
                $data['errmsg'] = '核销成功';
            } else {
                $data['errcode'] = 140008;
                $data['errmsg'] = '核销失败';
            }
             
            //-------------日志 start-----------------
            $data_UserLog['merchant_id']=Auth::user()->merchant_id;
            $data_UserLog['user_id']=Auth::user()->id;
            $data_UserLog['type']=51;
            $data_UserLog['url']='merchant/merchant.json';
            $data_UserLog['content']=json_encode(
                array(
                    '$rs_goods_virtual["time_type"]'=>$rs_goods_virtual['time_type'],
                    '$rs_goods_virtual["end_time"]'=>$rs_goods_virtual['end_time'],
                    '$rs_order_goods["quantity"]'=>$rs_order_goods['quantity'],
                    '$hexiao_finish'=>$hexiao_finish,
                    '$hexiao_refund'=>$hexiao_refund,
                    '$refund_not_finish'=>$refund_not_finish
                ));
            $data_UserLog['ip']=get_client_ip();
            $data_UserLog['created_time']=date('Y-m-d H:i:s');
            $data_UserLog['updated_time']=date('Y-m-d H:i:s');
            UserLog::insertGetId($data_UserLog);
            //-------------日志 end-----------------
            
            //退款是否全部完结
            if(empty($refund_not_finish)){
                if(
                    //商品有效期 过期->订单完成
                    $rs_goods_virtual['time_type']==1 && date('Y-m-d H:i:s')>$rs_goods_virtual['end_time']
                    //购买数量 = 已核销数量+已退款数量
                    || $rs_order_goods['quantity'] == $hexiao_finish+$hexiao_refund
                    ){
                        //dd('a');
                        $data_orderinfo['status'] = 11;
                        $data_orderinfo['finished_time'] = date('Y-m-d H:i:s');
                        $rs_orderinfo = OrderInfo::where(['merchant_id'=>Auth::user()->merchant_id,'id'=>$this->params['order_id']])->update($data_orderinfo);
                }
            }
        }
        
        return Response::json($data);
    }

    /* 订单->核销记录:预约服务日志
     * 服务管理->服务订单
     * shangyazhao@dodoca.com
     * status=0全部  status=1(未核销)  status=2(已核销)
     */
    public function getList() {
        $query = OrderAppt::where(['order_appt.merchant_id'=>$this->merchant_id])
                ->leftjoin('order_info','order_info.id','=','order_appt.order_id')
                ->leftjoin('user','user.id','=','order_appt.user_id')
                ->whereNotNull('order_appt.hexiao_code')
                ->where('order_appt.hexiao_code','!=','');
        
        //订单号
        $order = isset($this->params['order'])&&!empty($this->params['order']) ? trim($this->params['order']) : '';
        if (!empty($order)) {  
            $query = $query->where(['order_appt.order_sn'=>$order]);
        }
        //核销码
        $code = isset($this->params['code'])&&!empty($this->params['code']) ? trim($this->params['code']) : '';
        if (!empty($code)) {    
            $query = $query->where(['order_appt.hexiao_code'=>$code]);
        }
        //用户名
        $username = isset($this->params['username'])&&!empty($this->params['username']) ? trim($this->params['username']) : '';
        if (!empty($username)) {
            $query = $query->where(['user.username'=>$username]);
        }
        //核销状态
        $status = isset($this->params['status'])&&!empty($this->params['status']) ? intval($this->params['status']) : '';
        if ($status > 0) {
            $query = $query->where(['order_appt.hexiao_status'=>($status - 1)]);
        }
        //支付状态:成功支付
        //$wheres[] = array('column' => 'pay_status', 'value' => 1 , 'operator' => '=');
        //预约日期:起始
        $appt_date_start = isset($this->params['appt_date_start'])&&!empty($this->params['appt_date_start']) ? $this->params['appt_date_start'] : '';
        if ($appt_date_start > 0) {
            $query = $query->where('order_appt.appt_date','>=',$appt_date_start);
        }
        //预约日期:截止
        $appt_date_end = isset($this->params['appt_date_end'])&&!empty($this->params['appt_date_end']) ? date('Y-m-d',strtotime('+1 day',strtotime($this->params['appt_date_start']))) : '';
        if ($appt_date_end > 0) {
            $query = $query->where('order_appt.appt_date','<',$appt_date_end);
        }
        //dd($query);
        //多条件搜索
        $search = isset($this->params['search'])&&!empty($this->params['search']) ? $this->params['search'] : '';
        //dd($search);
        if (!empty($search) ) {
            $query->where(function ($query) use ($search) {
                $query->where('order_appt.customer'  , 'like', '%'.$search.'%')
                    ->orwhere('order_appt.customer_mobile', 'like', '%'.$search.'%')
                    ->orwhere('order_appt.appt_staff_nickname', 'like', '%'.$search.'%')
                    ->orwhere('order_appt.store_name', 'like', '%'.$search.'%');
            });
        }
        //服务状态
        $appt_status = isset($this->params['appt_status'])&&!empty($this->params['appt_status']) ? $this->params['appt_status'] : '';
        //dd($appt_status == 'await');
        
        // 订单来源:小程序appid
        $appid = isset($this->params['appid']) && !empty($this->params["appid"]) ? trim($this->params['appid']) : "";
        if(isset($appid) && $appid) {
            $query = $query->where('order_info.appid', '=', $appid);
        }
        //小程序列表
        $arr_weixininfo = array();
        $rs_weixininfo = WeixinInfo::list_data('merchant_id',Auth::user()->merchant_id,1,0);
        if(!empty($rs_weixininfo)){
            foreach ($rs_weixininfo as $key=>$val){
                $arr_weixininfo[$val['appid']] = $val['nick_name'];
            }
        }
        
        if ($appt_status == 'await') {
            //dd('a');
            $query->where('order_appt.pay_status'  , '=', 1)
                ->where('order_appt.hexiao_status'  , '=', 0)
                ->where('order_appt.appt_date'  , '>=', date('Y-m-d'));
        }else if ($appt_status == 'overtime') {
            //dd('b');
            $query->where('order_appt.pay_status'  , '=', 1)
                ->where('order_appt.hexiao_status'  , '=', 0)
                ->whereNotIn('order_info.status'  , array(1,2,3,4,11))
                ->where('order_appt.appt_date'  , '<', date('Y-m-d'));
        }
        
        $data['errcode'] = 0;
        $data['_count'] = $query->count();
        //dd( OrderAppt);
        $page = isset($this->params['page']) ? intval($this->params['page']) : 1;
        $limit = isset($this->params['pagesize']) ? intval($this->params['pagesize']) : 10;
        $offset = ($page - 1) * $limit;
        $result = $query->select('order_appt.id','order_appt.order_id','order_appt.merchant_id','order_appt.order_sn','order_appt.hexiao_code','order_appt.hexiao_status'
            ,'order_appt.user_id','order_appt.paid_time','order_appt.pay_status','order_appt.appt_date','order_appt.appt_string','order_appt.member_id'
            ,'order_appt.hexiao_time','order_appt.created_time','order_appt.updated_time','order_appt.store_name','order_appt.appt_staff_nickname'
            ,'order_appt.customer','order_appt.customer_mobile','order_appt.goods_img','order_appt.goods_title','order_appt.quantity'
            ,'user.username'
            ,'order_info.status','order_info.refund_status','order_info.appid')
            ->skip($offset)->take($limit)->orderBy('id', 'desc')->get();
        if(!empty($result)){
            //兼容旧订单
            $arr_buyer = array();
            foreach ($result as $key=>$val){
                //来源小程序
                $result[$key]['applet_name'] = isset($arr_weixininfo[$val['appid']])?$arr_weixininfo[$val['appid']]:'';
                //买家
                if(empty($val['pay_status'])){
                    $result[$key]['customer']='';
                    $result[$key]['customer_mobile']='';
                }
                //技师
                if(empty($val['appt_staff_nickname'])){
                    $result[$key]['appt_staff_nickname']='-';
                }
                //核销时间
                if($val['hexiao_status']!=1 ){
                    $result[$key]['hexiao_time']='-';
                }
                //状态
                if(in_array($val['status'], array(ORDER_SUBMIT,ORDER_TOPAY))){
                    $result[$key]['appt_status_msg']='待付款';
                }else if(in_array($val['status'], array(ORDER_TOSEND,ORDER_SUBMITTED,ORDER_FORPICKUP,ORDER_SEND))){
                    $result[$key]['appt_status_msg']='已支付，待核销';
                }else if($val['status']==ORDER_SUCCESS){
                    $result[$key]['appt_status_msg']='已完成';
                }else if(in_array($val['status'], array(ORDER_AUTO_CANCELED,ORDER_BUYERS_CANCELED,ORDER_MERCHANT_CANCEL,ORDER_REFUND_CANCEL))){
                    $result[$key]['appt_status_msg']='已关闭';
                }
                //核销来源
                if($val['hexiao_source']==1){
                    $result[$key]['hexiao_source_msg']='后台核销';
                }else if($val['hexiao_status']!=1 ){
                    $result[$key]['hexiao_source_msg']='-';
                }
                //核销状态
                if($val['status']==4 && $val['hexiao_status']==0){
                    $result[$key]['username'] = '-';
                    $result[$key]['hexiao_time'] = '-';
                }
                $result[$key]['hexiao_status_msg']=config('varconfig.order_appt_hexiao_status')[$val['hexiao_status']];
                //维权信息
                if($result[$key]['refund_status'] == 1){
                    //从维权表中查询数据
                    // order_id
                    $refund_res = OrderRefund_OBJ::select('id','status')->where('order_id',$val['order_id'])->orderBy('id','desc')->limit(1)->get();
                    // if()
                    if(count($refund_res)>0){
                        $result[$key]['refund'] = $refund_res->toArray();
                    }else{
                        //非法数据 容错
                        $result[$key]['refund_status'] = 0;
                        // unset($result[$key]);
                    }
                }else{
                    $result[$key]['refund'] = [];
                }
                //兼容旧订单
                if( empty($val['customer']) && empty($val['customer_mobile']) && $val['created_time']<'2017-11-30 21:00:00' ){
                    $arr_buyer[] = $val['member_id'];
                }
            }
            
            if(!empty($arr_buyer)){
                $rs_member = Member::whereIn('id',$arr_buyer)->get();
                if(!empty($rs_member)){
                    foreach ($rs_member as $key=>$val){
                        $arr_buyer_rs[$val['id']]=$val;
                    }
                }
                foreach ($result as $key=>$val){
                    $result[$key]['customer']=isset($arr_buyer_rs[$val['member_id']])?$arr_buyer_rs[$val['member_id']]['name']:'';
                    $result[$key]['customer_mobile']=isset($arr_buyer_rs[$val['member_id']])?$arr_buyer_rs[$val['member_id']]['mobile']:'';
                    $result[$key]['goods_img']='2017/09/29/Fl_m-VXSPfhmxTGR-CShxr7S-qZK.png';
                    $result[$key]['quantity']=1;
                }
            }
        }
        
        $data['data'] = $result;
        return Response :: json($data);
    }

    /* 订单->核销记录:上门自提日志
     * shangyazhao@dodoca.com
     * status=0全部  status=1(未核销)  status=2(已核销)
     */    
    public function getSelfFetchList() {
        $query = OrderSelffetch::where(['order_selffetch.merchant_id'=>$this->merchant_id])
            ->leftjoin('order_info','order_info.id','=','order_selffetch.order_id')
            ->leftjoin('store','store.id','=','order_selffetch.store_id')
            ->leftjoin('user','user.id','=','order_selffetch.user_id');
    
        //订单号
        $order = isset($this->params['order'])&&!empty($this->params['order']) ? trim($this->params['order']) : '';
        if (!empty($order)) {
            $query = $query->where(['order_info.order_sn'=>$order]);
        }
        //核销码
        $code = isset($this->params['code'])&&!empty($this->params['code']) ? trim($this->params['code']) : '';
        if (!empty($code)) {
            $query = $query->where(['order_selffetch.hexiao_code'=>$code]);
        }
        //用户名
        $username = isset($this->params['username'])&&!empty($this->params['username']) ? trim($this->params['username']) : '';
        if (!empty($username)) {
            $query = $query->where(['user.username'=>$username]);
        }
        //核销状态
        $status = isset($this->params['status'])&&!empty($this->params['status']) ? intval($this->params['status']) : '';
        if ($status > 0) {
            $query = $query->where(['order_selffetch.hexiao_status'=>($status - 1)]);
        }
        
        $data['errcode'] = 0;
        $data['_count'] = $query->count();
        //dd( OrderAppt);
        $page = isset($this->params['page']) ? intval($this->params['page']) : 1;
        $limit = isset($this->params['pagesize']) ? intval($this->params['pagesize']) : 10;
        $offset = ($page - 1) * $limit;
        $result = $query->select('order_selffetch.id','order_selffetch.order_id','order_selffetch.hexiao_code','order_selffetch.hexiao_status'
            ,'order_selffetch.hexiao_time','order_selffetch.created_time','order_selffetch.updated_time','order_selffetch.hexiao_source'
            ,'store.name as store_name'
            ,'order_info.status','order_info.refund_status','order_info.order_sn','order_info.pay_status'
            ,'user.username'
            )->skip($offset)->take($limit)->orderBy('id', 'desc')->get();
        //dd($result);
            if(!empty($result)){
                foreach ($result as $key=>$val){
                    //核销人
                    if($val['hexiao_status']!=1 || empty($val['appt_staff_nickname'])){
                        $result[$key]['appt_staff_nickname']='-';
                    }
                    //核销时间
                    if($val['hexiao_status']!=1 ){
                        $result[$key]['hexiao_time']='-';
                    }
                    //核销来源
                    if($val['hexiao_source']==0){
                        $result[$key]['hexiao_source_msg']='';
                    }else if($val['hexiao_source']==1){
                        $result[$key]['hexiao_source_msg']='后台核销';
                    }else if($val['hexiao_source']==2){
                        $result[$key]['hexiao_source_msg']='手机端核销';
                    }else {
                        $result[$key]['hexiao_source_msg']='其他核销';
                    }
                    //核销状态
                    //核销状态
                    if($val['status']==4 && $val['hexiao_status']==0){
                        $val['username'] = '-';
                        $val['hexiao_time'] = '-';
                    }
                    $result[$key]['hexiao_status_msg']=config('varconfig.order_appt_hexiao_status')[$val['hexiao_status']];
                    //dd($result);
                }
            }
    
            $data['data'] = $result;
            return Response :: json($data);
    }
    
    /* 订单->核销记录:虚拟商品日志
     * songyongshang@dodoca.com
     * status 0:未核销  1:未核销  2:已退款
     */
    public function getVirtualgoodsList() {
        $query = OrderVirtualgoods::where(['order_virtualgoods.merchant_id'=>$this->merchant_id])
                ->leftjoin('order_info','order_info.id','=','order_virtualgoods.order_id')
                ->leftjoin('user','user.id','=','order_virtualgoods.user_id')
                ->leftjoin('order_goods','order_goods.order_id','=','order_info.id')
                ->leftjoin('goods_virtual','goods_virtual.goods_id','=','order_goods.goods_id');
        //订单号
        $order_sn = isset($this->params['order_sn'])&&!empty($this->params['order_sn']) ? trim($this->params['order_sn']) : '';
        if (!empty($order_sn)) {
            $query = $query->where(['order_info.order_sn'=>$order_sn]);
        }
        //核销码
        $hexiao_code = isset($this->params['hexiao_code'])&&!empty($this->params['hexiao_code']) ? trim($this->params['hexiao_code']) : '';
        if (!empty($hexiao_code)) {
            $query = $query->where('order_virtualgoods.hexiao_code','like',substr($this->params['hexiao_code'], 0, 10).'%');
            
        }
        //核销人
        $username = isset($this->params['username'])&&!empty($this->params['username']) ? trim($this->params['username']) : '';
        if (!empty($username)) {
            $query = $query->where(['user.username'=>$username]);
        }
        //核销状态
        $hexiao_status = isset($this->params['hexiao_status']) ? $this->params['hexiao_status'] : 'all';
        //dd($hexiao_status);
        if ($hexiao_status=='0') {
            $query = $query->where(['order_virtualgoods.hexiao_status'=>($hexiao_status)])
                    ->where('goods_virtual.end_time','>',date('Y-m-d H:i:s'));
        }elseif (in_array($hexiao_status, array(1,2))) {
            $query = $query->where(['order_virtualgoods.hexiao_status'=>($hexiao_status)]);
        }elseif ($hexiao_status=='invalid') {
            $query = $query->where(['order_virtualgoods.hexiao_status'=>0])
                    ->where('goods_virtual.end_time','<',date('Y-m-d H:i:s'))
                    ->where('goods_virtual.time_type',1);
        }
    
        $data['errcode'] = 0;
        $data['_count'] = $query->count();
        //dd( OrderAppt);
        $page = isset($this->params['page']) ? intval($this->params['page']) : 1;
        $limit = isset($this->params['pagesize']) ? intval($this->params['pagesize']) : 10;
        $offset = ($page - 1) * $limit;
        $result = $query->select('order_virtualgoods.id','order_virtualgoods.order_id','order_virtualgoods.hexiao_code','order_virtualgoods.hexiao_status'
            ,'order_virtualgoods.hexiao_time','order_virtualgoods.created_time','order_virtualgoods.updated_time','order_virtualgoods.hexiao_source'
            ,'order_info.order_sn','order_info.status','order_info.refund_status','order_info.pay_status'
            ,'user.username'
            ,'goods_virtual.end_time','goods_virtual.time_type'
            )->skip($offset)->take($limit)->orderBy('id', 'desc')->get();
            //dd($result);
            if(!empty($result)){
                foreach ($result as $key=>$val){
                    //核销人
                    if($val['hexiao_status']!=1 || empty($val['appt_staff_nickname'])){
                        $result[$key]['appt_staff_nickname']='--';
                    }
                    //核销时间
                    if($val['hexiao_status']!=1 ){
                        $result[$key]['hexiao_time']='--';
                    }
                    //核销来源
                    if($val['hexiao_source']==0){
                        $result[$key]['hexiao_source_msg']='';
                    }else if($val['hexiao_source']==1){
                        $result[$key]['hexiao_source_msg']='后台核销';
                    }else if($val['hexiao_source']==2){
                        $result[$key]['hexiao_source_msg']='手机端核销';
                    }else {
                        $result[$key]['hexiao_source_msg']='其他核销';
                    }
                    //核销状态
                    //核销状态
                    if($val['hexiao_status']!=1){
                        $val['username'] = '--';
                        $val['hexiao_time'] = '--';
                    }
                    $result[$key]['hexiao_status_msg']=config('varconfig.order_virtualgoods_hexiao_status')[$val['hexiao_status']];
                    if( $val['time_type']==1 && $val['hexiao_status']==0 && date('Y-m-d H:i:s')>$val['end_time'] ){
                        $result[$key]['hexiao_status_msg'] = '已失效';
                    }
                    //dd($result);
                }
            }
    
            $data['data'] = $result;
            return Response :: json($data);
    }
}

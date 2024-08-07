<?php
/**
 * 满减服务类
 * Date: 2017-08-31
 * Time: 15:20
 */
namespace App\Services;

use App\Models\DiscountActivity;
use App\Models\DiscountGoods;
use App\Models\DiscountItem;
use App\Models\Goods;
use App\Models\GoodsSpec;
use App\Models\Cart;
use App\Models\Shop;
use App\Models\UserLog;
//use App\Models\DiscountLadder;
//use App\Models\DiscountLaunch;
//use App\Models\DiscountRefund;
//use App\Models\DiscountStock;
use App\Services\VipcardService;

use Illuminate\Support\Facades\Cache;
use App\Utils\CacheKey;

class DiscountService {

    /**
     * systybj 满减：活动中某件商品详情的满减活动信息
     * $merchant_id  商户ID  必传参数
     * $goods_id     商品ID  必传参数
     */
    public function getgoodsDiscountInfo($merchant_id=0,$goods_id=0)
    {

        $merchant_id = isset($merchant_id) ? intval($merchant_id) : 0;
        if($merchant_id<1){
            $rt['errcode']=100001;
            $rt['errmsg']='商户id 不能为空';
            $rt['data']='';
            return $rt;
            exit;
        }
        $goods_id      = isset($goods_id) ? intval($goods_id) : 0;
        if($goods_id < 1 ){
            $rt['errcode']=100002;
            $rt['errmsg']='商品id 不能为空';
            $rt['data']='';
            return $rt;
            exit;
        }

        $date = date('Y-m-d H:i:s');
        $discount_id_res = DiscountActivity::where('is_delete', 1)
            ->where('merchant_id', $merchant_id)
            ->where('status', 1)
            ->where('range', 2)
            ->where('start_time', '<=', $date)
            ->where('end_time', '>', $date)
            ->orderBy('id', 'desc')->value('id');
        if(empty($discount_id_res)){
            //满减活动：商品列表
            $Discount_Goods = DiscountGoods::select('discount_goods.discount_id','discount_activity.title')
                ->leftjoin('discount_activity','discount_activity.id','=','discount_goods.discount_id')
                ->where(['discount_goods.merchant_id' => $merchant_id, 'discount_goods.goods_id' => $goods_id, 'discount_goods.status' => 1])
                ->where('discount_activity.start_time','<=',$date)
                ->where('discount_activity.end_time','>',$date)
                ->where(['discount_activity.is_delete'=>1])
//                ->whereIn('discount_activity.status',['0','1'])
                ->where('discount_activity.status',1)
                ->first();
        } else {//全店参与
            $Discount_Goods['discount_id'] = $discount_id_res;
        }

        if(empty($Discount_Goods)) {
            return ['errcode' => 10002, 'errmsg' => '无效满减1！'];exit;
        }

        //此满减的层级
        $rs_discountItem = DiscountItem::select('discount_id', 'condition', 'cash_val','reduction','postage')
            ->where(['merchant_id' =>$merchant_id, 'discount_id' => $Discount_Goods['discount_id']])
            ->orderBy('condition','asc')
            ->get();

        if(empty($rs_discountItem->toArray())) {
            return ['errcode' => 10003, 'errmsg' => '无效满减2！'];exit;
        }
        foreach ($rs_discountItem as $k1=>$v1){
            $rs_discountItem[$k1]['discountItem_msg'] = '满'.$v1['condition'].'减'.$v1['cash_val'];
        }

        return ['errcode' => 0, 'errmsg' => '获取满减活动层级列表成功1', 'data' => $rs_discountItem];
    }

    /**
     * systybj 满减：购物车详情
     * songyongshang@dodoca.com
     * 20170901 12:00
     * $merchant_id  商户ID  必传参数
     * $goods_id     商品ID  必传参数
     
     * $arr1_Goods                    //商品信息 商品id=>商品信息
     * $arr2_Goods_Vipdiscount        //商品折扣信息 商品id=>折扣信息

    
     * $arr5_DiscountActivity         //活动id
     * $arr3_DiscountActivity_Goods   //满减活动商品信息 活动id=>[商品id=>活动信息
     * $arr7_DiscountItemNum          //满减活动层级信息 活动id=>层级信息
     * $arr6_DiscountItem             //满减活动层级详情 活动id=>[层级id=>层级信息]
    
     * $arr8_MoneyOff   //每个活动对应的总金额 活动id=>总金额
    
     * $arr9_total_price      //购物车总金额
     * $none_discount_goods   //没有参加满减活动的总金额
    */
    public function getCartGoodsDiscountInfo($merchant_id=0,$member_id=0)
    {
        if($member_id < 1 ){
            $rt['errcode']=100001;
            $rt['errmsg']='会员id 不能为空';
            $rt['data']=[];
            return $rt;
            exit;
        }
        if($merchant_id < 1 ){
            $rt['errcode']=100001;
            $rt['errmsg']='商户id 不能为空';
            $rt['data']=[];
            return $rt;
            exit;
        }
        
        // 1.1 购物车列表
        $param = array('merchant_id'=>$merchant_id, 'member_id'=>$member_id);
        $rs1_CartGoods_array = CartService::getLists($param);
        $rs1_CartGoods = $rs1_CartGoods_array['data'];
        //dd($rs1_CartGoods);
        //return ['errcode' => 0, 'errmsg' => '购物车中无商品！','data'=>$rs1_CartGoods];exit;

        if(empty($rs1_CartGoods)) {
            return ['errcode' => 0, 'errmsg' => '购物车中无商品！','data'=>[]];exit;
        }
        //dd($rs1_CartGoods);
        // 1.2 购物车列表:多规格商品的价格/属性
        if(!empty($rs1_CartGoods)){
            foreach ($rs1_CartGoods as $key_Cart=>$val_Cart){
                //删除购物车中的无效商品
                if(empty($val_Cart['goods_id'])){
                    $delete_cart = array();
                    $delete_cart['ids'] = $key_Cart;
                    $delete_cart['member_id'] = $member_id;
                    $delete_cart['merchant_id'] = $merchant_id;
                    CartService::delete($delete_cart);
                    unset($rs1_CartGoods[$key_Cart]);
                    continue;
                }
                //单规格商品的规格id设为0
                if(empty($val_Cart['goods_spec_id'])){
                    $rs1_CartGoods[$key_Cart]['goods_spec_id'] = 0;
                }
                $rs1_CartGoods[$key_Cart]['cart_id'] = $key_Cart;
                $rs1_CartGoods[$key_Cart]['goods_pur_num'] = $val_Cart['quantity'];
                //商品属性
                if(!empty($val_Cart['goods_id'])){
                    $GoodsInfo = Goods::get_data_by_id($val_Cart['goods_id'], $merchant_id);
                    $rs1_CartGoods[$key_Cart]['goods_name'] = $GoodsInfo['title'];
                    $rs1_CartGoods[$key_Cart]['goods_onsale'] = $GoodsInfo['onsale'];
                    $rs1_CartGoods[$key_Cart]['goods_img'] = $GoodsInfo['img'];
                    $rs1_CartGoods[$key_Cart]['goods_price'] = $GoodsInfo['price'];//商品原价,不参与计算
                    $rs1_CartGoods[$key_Cart]['goods_vip_discount_price'] = $GoodsInfo['price'];//商品折扣后的价格,参与计算
                    $rs1_CartGoods[$key_Cart]['is_sku'] = $GoodsInfo['is_sku']; //是否单规格/多规格
                    $rs1_CartGoods[$key_Cart]['is_delete'] = $GoodsInfo['is_delete']; //商品是否删除状态
                    //商品规格变化,把购物车中的商品置为无效商品
                    $rs1_CartGoods[$key_Cart]['valid'] = 1;
                    if(
                        ( empty($val_Cart['goods_spec_id']) && !empty($GoodsInfo['is_sku']) ) //单规格的修改成多规格的
                        || ( !empty($val_Cart['goods_spec_id']) && empty($GoodsInfo['is_sku']) ) //多规格的修改成单规格的
                        ){
                            $rs1_CartGoods[$key_Cart]['valid'] = 0;
                    }
                }
                //多规格商品价格/属性
                if(!empty($val_Cart['goods_spec_id'])){
                    $GoodsSpec = GoodsSpec::get_alldata_by_id($val_Cart['goods_spec_id'], $merchant_id);
                    
                    $rs1_CartGoods[$key_Cart]['goods_props'] = $GoodsSpec['props_str'];
                    $rs1_CartGoods[$key_Cart]['goods_price'] = $GoodsSpec['price'];
                    $rs1_CartGoods[$key_Cart]['goods_vip_discount_price'] = $GoodsSpec['price'];
                    $rs1_CartGoods[$key_Cart]['goods_img'] = $GoodsSpec['img'];
                    $rs1_CartGoods[$key_Cart]['is_delete'] = $GoodsSpec['is_delete']; //商品是否删除状态
                }
            }
        }
        //dd($rs1_CartGoods);
        // 1.3 购物车的商品：是否有对应的满减活动
        //商品的属性
        $arr1_Goods = array();
        // 1.4 查询:购物车的商品：商品列表及商品信息
        //商品的会员价
        $arr2_Goods_Vipdiscount=array();
        //商品参加满减活动
        $arr3_DiscountActivity=array();
        //dd($rs1_CartGoods);
        foreach ($rs1_CartGoods as $key1_CartGoods_id=>$val1_CartGoods) {
            //商品数组
            if(!isset($val1_CartGoods['goods_id'])){
                continue;
            }
            //商品数组:多规格商品的属性和价格
            $arr1_Goods[$val1_CartGoods['goods_id']][$val1_CartGoods['goods_spec_id']]=$val1_CartGoods;
            if(!empty($val1_CartGoods['goods_spec_id'])){
                $arr1_Goods[$val1_CartGoods['goods_id']][$val1_CartGoods['goods_spec_id']]['goods_price']=$val1_CartGoods['goods_price'];
                $arr1_Goods[$val1_CartGoods['goods_id']][$val1_CartGoods['goods_spec_id']]['goods_pur_num']=$val1_CartGoods['quantity'];
            }
            
            //2 会员的折扣价格
            $param['merchant_id']=$merchant_id;
            $param['member_id']=$member_id;
            $param['goods_id']=$val1_CartGoods['goods_id'];
            $vip_price=array();
            $member_goods_discount = 10;
            
            $VipCardService = new VipcardService();
            $vip_price = $VipCardService->goodsDiscount($param);
            if(!empty($vip_price['data']['discount'])){
                $member_goods_discount = $vip_price['data']['discount'];
                $arr2_Goods_Vipdiscount[$val1_CartGoods['goods_id']]=$member_goods_discount;
            }
            
            //会员折扣价/会员折扣率
            if(!empty($member_goods_discount) && $member_goods_discount>0 && $member_goods_discount<10){
                $arr1_Goods[$val1_CartGoods['goods_id']][$val1_CartGoods['goods_spec_id']]['goods_vip_discount_rate'] = $member_goods_discount;
                //单件商品的会员价
                $member_price = 0;
                $member_price = ($val1_CartGoods['goods_price']*$member_goods_discount)/10;
                //echo $member_price.'<br>';
                $arr1_Goods[$val1_CartGoods['goods_id']][$val1_CartGoods['goods_spec_id']]['goods_vip_discount_price'] = $member_price;
                //折扣率
                $rs1_CartGoods[$key1_CartGoods_id]['goods_vip_discount_rate']=$member_goods_discount;
                //会员价
                $rs1_CartGoods[$key1_CartGoods_id]['goods_vip_discount_price']=$member_price;
                
                //折扣率
                $val1_CartGoods['goods_vip_discount_rate']=$member_goods_discount;
                //会员价
                $val1_CartGoods['goods_vip_discount_price']=round($member_price,2);
                
                //$rs1_CartGoods[$key1_CartGoods_id]['goods_price']=$member_price;
                //$val1_CartGoods['goods_price']=$member_price;
            }
            // 购物车的商品：是否有对应的满减活动
            // 排除掉 1.已经下架的商品 2.单规格/多规格变化的商品
            if( empty($val1_CartGoods['valid']) || $val1_CartGoods['goods_onsale']!=1 || $val1_CartGoods['is_delete']!=1){
                //已下架的商品
                $arr['data']['sold_out_goods'][]=$val1_CartGoods;
            }else if( $val1_CartGoods['goods_onsale']==1 ){
                //满减活动--自选商品
                $rs2_DiscountGoodsActivity = DiscountGoods::select('discount_goods.goods_id','discount_goods.discount_id','discount_activity.title')
                    ->leftjoin('discount_activity','discount_activity.id','=','discount_goods.discount_id')
                    ->where(['discount_goods.merchant_id' => $merchant_id, 'discount_goods.goods_id' => $val1_CartGoods['goods_id'], 'discount_goods.status' => 1])
                    ->where('discount_activity.start_time','<=',date('Y-m-d H:i:s'))
                    ->where('discount_activity.end_time','>=',date('Y-m-d H:i:s'))
                    ->where(['discount_activity.is_delete'=>1])
                    ->first();
                //满减活动--全店参与
                if(empty($rs2_DiscountGoodsActivity)){
                    $rs2_DiscountGoodsActivity = DiscountActivity::select('discount_activity.id','discount_activity.title')
                        ->where(['discount_activity.merchant_id' => $merchant_id])
                        ->where('discount_activity.start_time','<=',date('Y-m-d H:i:s'))
                        ->where('discount_activity.end_time','>=',date('Y-m-d H:i:s'))
                        ->where(['discount_activity.is_delete'=>1,'range'=>2])
                        ->first();
                    if(!empty($rs2_DiscountGoodsActivity)){
                        $rs2_DiscountGoodsActivity['discount_id'] = $rs2_DiscountGoodsActivity['id'];
                        $rs2_DiscountGoodsActivity['goods_id'] = $val1_CartGoods['goods_id'];
                    }
                }
                
                //dd($rs2_DiscountGoodsActivity);
                if(empty($rs2_DiscountGoodsActivity) ) {
                    //购物车的商品：没有参与满减活动的商品
                    $arr['data']['none_discount_goods'][]=$val1_CartGoods;
                    //continue;
                }else{
                    $rs2_DiscountGoodsActivity['goods_spec_id'] = $val1_CartGoods['goods_spec_id'];
                    // 3.1 按满减活动组织购物车中的商品
                    $arr3_DiscountActivity_Goods[$rs2_DiscountGoodsActivity['discount_id']][$key1_CartGoods_id]=$rs2_DiscountGoodsActivity;

                    // 4 满减活动数组
                    $arr5_DiscountActivity[]=$rs2_DiscountGoodsActivity['discount_id'];
                    //unset($rs1_CartGoods[$key1_CartGoods_id]);
                }
            }
        }
        //dd($arr5_DiscountActivity);
        // 4 满减活动的层级数组
        $arr6_DiscountItem=array();
        $arr7_DiscountItemNum=array();
        if(!empty($arr5_DiscountActivity)){
            $rs3_DiscountItem = DiscountItem::select('id','discount_id', 'condition', 'cash_val')
                ->whereIn('discount_id' , $arr5_DiscountActivity)
                ->orderBy('condition','asc')
                ->get();
            if(!empty($rs3_DiscountItem) ) {
                foreach ($rs3_DiscountItem as $rs3_key1 => $rs3_val1){
                    // 5 每个满减活动中有几个层级
                    if(empty($arr7_DiscountItemNum[$rs3_val1['discount_id']]['item_nums'])){
                        $arr7_DiscountItemNum[$rs3_val1['discount_id']]['item_nums']=1;
                    }else{
                        $arr7_DiscountItemNum[$rs3_val1['discount_id']]['item_nums']+=1;
                    }
                    // 6 每个满减活动中有几个层级的详细内容
                    $arr6_DiscountItem[$rs3_val1['discount_id']][$arr7_DiscountItemNum[$rs3_val1['discount_id']]['item_nums']]=array(
                        'id'=>$rs3_val1['id'],
                        'discount_id'=>$rs3_val1['discount_id'],
                        'condition'=>$rs3_val1['condition'],
                        'cash_val'=>$rs3_val1['cash_val'],
                    );
                }
            }
        }
        //dd($arr6_DiscountItem);
        // 7 计算过程
        //购物车总金额
        $arr9_total_price=0;
        $arr8_MoneyOff = array();
        //dd($arr3_DiscountActivity_Goods);
        if(!empty($arr3_DiscountActivity_Goods)){
            //按满减活动来计算金额
            foreach ($arr3_DiscountActivity_Goods as $arr3_key1=>$arr3_val1){
                $arr7=array(
                    'discount_id'=>$arr3_key1,
                    'discount_msg'=>'',
                    'discount_price'=>0,
                    'addon_item'=>'',
                    'discount_item'=>array(),
                    'discount_goods'=>array(),
                );

                //每个活动对应的总金额
                $arr8_MoneyOff[$arr3_key1]=0;
                if(!empty($arr3_val1)){
                    //循环活动中的每一个商品
                    //dd($rs1_CartGoods);
                    foreach ($arr3_val1 as $arr3_key2=>$arr3_val2){
                        $goods_price=0;
                        if(empty($rs1_CartGoods[$arr3_key2]['valid'])){
                            continue;
                        }
                        if(isset($rs1_CartGoods[$arr3_key2]['goods_pur_num']) && isset($rs1_CartGoods[$arr3_key2]['goods_vip_discount_price'])){
                            $goods_price = $rs1_CartGoods[$arr3_key2]['goods_pur_num']*$rs1_CartGoods[$arr3_key2]['goods_vip_discount_price'];
                            $arr8_MoneyOff[$arr3_key1] += $goods_price;
                            $arr9_total_price += $goods_price;
                            
                            $arr1_Goods[$arr3_key2]['vip_price'] = $rs1_CartGoods[$arr3_key2]['goods_pur_num']*$rs1_CartGoods[$arr3_key2]['goods_vip_discount_price'];
                            
                            //echo $arr9_total_price.'<br>';
                            $arr7['discount_goods'][] = $rs1_CartGoods[$arr3_key2];
                        }
                    }
                    $arr7['discount_price'] = $arr8_MoneyOff[$arr3_key1];
                }
                
                //有满减活动:检查是否满足满减条件
                if(!empty($arr6_DiscountItem[$arr3_key1])) {
                    //满减节省的金额
                    $arr10_discount_price=0;
                    for($i=1;$i<=$arr7_DiscountItemNum[$arr3_key1]['item_nums'];$i++){
                        if ($arr8_MoneyOff[$arr3_key1] >= $arr6_DiscountItem[$arr3_key1][$i]['condition']) {
                            $arr10_discount_price=$arr6_DiscountItem[$arr3_key1][$i]['cash_val'];

                            $arr7['addon_item'] ='查看活动';
                            $arr7['discount_msg'] = '已购满' . $arr6_DiscountItem[$arr3_key1][$i]['condition'] . '元，已减' . $arr6_DiscountItem[$arr3_key1][$i]['cash_val'] . '元' ;
                            
                        }
                        //下个阶梯 满减条件 1.已满足某个满减条件 2.还有下一个满减条件 3.只提示下一个
                        else if( !empty($arr7['discount_msg']) && $arr8_MoneyOff[$arr3_key1] < $arr6_DiscountItem[$arr3_key1][$i]['condition'] && !isset($arr7['discount_next_msg'])){
                            $arr7['discount_next_msg'] = 1;
                            $arr7['discount_msg'] .='。购满'.$arr6_DiscountItem[$arr3_key1][$i]['condition'] . '元，可减' . $arr6_DiscountItem[$arr3_key1][$i]['cash_val'] . '元，还差' . ($arr6_DiscountItem[$arr3_key1][$i]['condition']-$arr8_MoneyOff[$arr3_key1]) .'元' ;
                        }
                        $arr7['discount_item'][] = array(
                            'discount_item_id'=>$arr6_DiscountItem[$arr3_key1][$i]['id'],
                            'discount_item_condition'=>$arr6_DiscountItem[$arr3_key1][$i]['condition'],
                            'discount_item_cash_val'=>$arr6_DiscountItem[$arr3_key1][$i]['cash_val'],
                        );
                        //dd($arr7);
                    }
                    //总价-满减额
                    $arr9_total_price = $arr9_total_price-$arr10_discount_price;
                    //echo $arr10_discount_price.'<br>';
                    //每个满减-瓶减额
                    $arr8_MoneyOff[$arr3_key1] = $arr8_MoneyOff[$arr3_key1]-$arr10_discount_price;
                    $arr7['discount_price'] = $arr7['discount_price']-$arr10_discount_price;
                    //dd($arr8_MoneyOff);
                    if (empty($arr7['discount_msg'])) {
                        $arr7['discount_msg'] = '购满' . $arr6_DiscountItem[$arr3_key1][1]['condition'] . '元，可减' . $arr6_DiscountItem[$arr3_key1][1]['cash_val'] . '元，还差'.($arr6_DiscountItem[$arr3_key1][1]['condition']-$arr8_MoneyOff[$arr3_key1]).'元' ;
                        $arr7['addon_item'] ='去凑单';
                    }

                    $arr['data']['all_discount_data'][]=$arr7;
                }
            }
        }
        //dd($arr8_MoneyOff);
        $none_discount_goods=0; 
        //没有参加满减活动的总金额
        if(!empty($arr['data']['none_discount_goods'])){
            foreach ($arr['data']['none_discount_goods'] as $k7=>$v7){
                $none_discount_goods = $none_discount_goods+$v7['goods_pur_num']*$v7['goods_vip_discount_price'];
            }
        }

        $arr['data']['amount'] = round(( $arr9_total_price + $none_discount_goods),2);

        return ['errcode' => 0, 'errmsg' => '获取满减活动层级列表成功2', 'data' => $arr['data']];
    }

    /**
     * systybj 满减：活动列表
     * chang
     * 20170901 12:00
     * $merchant_id  商户ID  必传参数
     * $goods_id     商品ID  必传参数
     */
    public function getDiscountActivityList($request_data)
    {

        $merchant_id = isset($request_data['merchant_id']) ? intval($request_data['merchant_id']) : 0;
        $discount_activity_id = isset($request_data['discount_activity_id']) ? intval($request_data['discount_activity_id']) : 0;

        if($discount_activity_id < 1 || $merchant_id<1){
            return ['errcode' => 10001, 'errmsg' => '获取满减活动层级列表失败，商户id或商品id参数有误！'];exit;
        }

        $pagesize    = isset($request_data['pagesize']) ? $request_data['pagesize'] : 10;
        $page   = isset($request_data['page']) ? ($request_data['page']-1)*$pagesize : 0;
        
        //满减活动列表
        $rs1_DiscountActivity = DiscountActivity::select('discount_activity.id','discount_activity.title')
            ->where('discount_activity.start_time','<=',date('Y-m-d H:i:s'))
            ->where('discount_activity.end_time','>=',date('Y-m-d H:i:s'))
            ->where(['discount_activity.is_delete'=>1,'discount_activity.merchant_id'=>$merchant_id]);
        
        $rt['_count'] = $rs1_DiscountActivity->count();
        if(empty($rt['_count'])) {
            $rt['errcode'] = 10002;
            $rt['errmsg'] = '无效满减3！';
            $rt['data'] = [];
            return $rt;exit;
        }
        $rs1_DiscountActivity = $rs1_DiscountActivity->orderBy('id', 'desc')->skip($page)->take($pagesize)->get();
        
        //此满减的层级
//         $rs_discount_item = DiscountItem::select('discount_id', 'condition', 'cash_val')
//             ->where(['merchant_id' =>$merchant_id, 'discount_id' => $discount_activity_id])
//             ->orderBy('condition','asc')
//             ->get();
//         if(empty($rs_discount_item->toArray())) {
//             return ['errcode' => 10003, 'errmsg' => '无效满减！'];exit;
//         }

        $rt['errcode'] = 0;
        $rt['errmsg'] = '获取满减活动层级列表成功3！';
        $rt['data'] = $rs1_DiscountActivity;
        return $rt;
    }

    /**
     * systybj 满减：某个活动详情
     * chang
     * 20170901 12:00
     * $merchant_id  商户ID  必传参数
     * $goods_id     商品ID  必传参数
     */
    public function getDiscountActivity($request_data)
    {
        //dd(config('version'));
        //dd($request_data);
        $merchant_id = isset($request_data['merchant_id']) ? intval($request_data['merchant_id']) : 0;
        $discount_activity_id      = isset($request_data['discount_activity_id']) ? intval($request_data['discount_activity_id']) : 0;
    
        $pagesize    = isset($request_data['pagesize']) ? $request_data['pagesize'] : 10;
        $page   = isset($request_data['page']) ? ($request_data['page']-1)*$pagesize : 0;
        $column    = 'goods.'.(isset($request_data['column']) ? $request_data['column'] : 'id');
        $direct   = isset($request_data['direct']) ? $request_data['direct'] : 'desc';
        $search = isset($request_data['search']) ? $request_data['search'] : '';
        //dd($discount_activity_id);
        if($discount_activity_id < 1){
            $rt['errcode'] = 10001;
            $rt['errmsg'] = '满减活动id参数有误！';
            $rt['data'] = [];
            return $rt;exit;
        }else if($merchant_id<1){
            $rt['errcode'] = 10001;
            $rt['errmsg'] = '商户id有误！';
            $rt['data'] = [];
            return $rt;exit;
        }
    
        //商铺表
        $shop_info= Shop::where(['merchant_id'=>$merchant_id])->first();
        
        //满减活动列表
        $rs1_DiscountActivity = DiscountActivity::select('discount_activity.id','discount_activity.title','discount_activity.range')
                    ->where('discount_activity.start_time','<=',date('Y-m-d H:i:s'))
                    ->where('discount_activity.end_time','>=',date('Y-m-d H:i:s'))
                    ->where(['discount_activity.is_delete'=>1,'discount_activity.id'=>$discount_activity_id,'discount_activity.merchant_id'=>$merchant_id])
                    ->first();
        if(empty($rs1_DiscountActivity)) {
            $rt['errcode'] = 10002;
            $rt['errmsg'] = '无效满减4！';
            $rt['data'] = [];
            return $rt;exit;
        }
        //满减活动：商品列表
        if($rs1_DiscountActivity['range']==2){
            $rs2_Goods = Goods::select('goods.id','goods.merchant_id','goods.title','goods.price','goods.original_price','goods.img','goods.base_csale','goods.is_sku','goods.csale','goods.shelve_at')
            ->where(['goods.merchant_id' => $merchant_id,'goods.is_delete'=>1,'goods.onsale'=>1]);
        }else{
            //自定义商品
            if(empty($search)){
                $rs2_Goods = DiscountGoods::select('goods.id','goods.merchant_id','goods.title','goods.price','goods.original_price','goods.img','goods.base_csale','goods.is_sku','goods.csale','goods.shelve_at')
                    ->leftjoin('goods','goods.id','=','discount_goods.goods_id')
                    ->where(['discount_goods.merchant_id' => $merchant_id, 'discount_goods.discount_id' => $discount_activity_id, 'discount_goods.status' => 1,'goods.is_delete'=>1,'goods.onsale'=>1]);
            }else{
                $rs2_Goods = DiscountGoods::select('goods.id','goods.merchant_id','goods.title','goods.price','goods.original_price','goods.img','goods.base_csale','goods.is_sku','goods.csale','goods.shelve_at')
                    ->leftjoin('goods','goods.id','=','discount_goods.goods_id')
                    ->where(['discount_goods.merchant_id' => $merchant_id, 'discount_goods.discount_id' => $discount_activity_id, 'discount_goods.status' => 1,'goods.is_delete'=>1,'goods.onsale'=>1])
                    ->where('goods.title','like','%'.$search.'%');
            }
        }
        
        $rt['_count'] = $rs2_Goods->count();
        if(empty($rt['_count'])) {
            $rt['errcode'] = 0;
            $rt['errmsg'] = '无此商品！';
            $rt['data'] = [];
            return $rt;exit;
        }
        $rs2_Goods = $rs2_Goods->orderBy($column, $direct)->skip($page)->take($pagesize)->get();
        if(!empty($rs2_Goods)){
            foreach ($rs2_Goods as $key=>$val){
                $rs2_Goods[$key]['csale'] = intval($val['csale'])+intval($val['base_csale']);
                $rs2_Goods[$key]['csale_show'] = $shop_info['csale_show'];
                $rs2_Goods[$key]['price_field_alias'] = $shop_info['price_field_alias'];
            }
        }
        //此满减的层级
        $rs_discount_item = DiscountItem::select('id as discount_item_id', 'condition', 'cash_val', 'reduction', 'postage')
                ->where(['merchant_id' =>$merchant_id, 'discount_id' => $discount_activity_id])
                ->orderBy('condition','asc')
                //->offset(0)->limit(1)
                ->get();
        if(empty($rs_discount_item->toArray())) {
            $rt['errcode'] = 10003;
            $rt['errmsg'] = '无效层级！';
            $rt['data'] = [];
            return $rt;exit;
        }
    
        $rt['errcode'] = 0;
        $rt['errmsg'] = '获取满减活动层级列表成功4！';
        $rt['data'] = array(
            'discount_goods'=>$rs2_Goods,
            'discount_item'=>$rs_discount_item,
            'discount_activity'=>$rs1_DiscountActivity,
        );
        return $rt;
    }
    
    /**
     * systybj 满减：订单中商品的金额
     * chang
     * 20170901 12:00
     * $merchant_id  商户ID  必传参数
     * $goods_id     商品ID  必传参数
     */
    public function getOrderGoodsDiscountMoney($request_data)
    {
        //dd($request_data);
        $member_id = isset($request_data['member_id'])?$request_data['member_id']:'';
        if($member_id < 1 ){
            return ['errcode' => 10001, 'errmsg' => '会员id 有误！'];exit;
        }
        $merchant_id = isset($request_data['merchant_id'])?$request_data['merchant_id']:'';
        if($merchant_id < 1 ){
            return ['errcode' => 10001, 'errmsg' => '商户id 有误！'];exit;
        }
        if( !isset($request_data['goods']) || empty($request_data['goods']) ){
            return ['errcode' => 10001, 'errmsg' => '商品id 有误！'];exit;
        }
        
        // 1 排除无效商品
        $arr1_goods_id = array();
        foreach ($request_data['goods'] as $key1=>$val1){
            $rs1_goods = Goods::where(['goods.merchant_id'=>$merchant_id,'goods.is_delete'=>1,'goods.onsale'=>1,'id'=>$val1['goods_id']])->get();
            if(!empty($rs1_goods)){
                $arr1_goods_id[] = $val1['goods_id'];
            }
        }
        
        //2 是否参加满减活动
        $arr2_GoodsId_DiscountId = array();//商品->满减活动
        $arr3_DiscountId = array();//满减活动
        $arr6_DiscountItem=array();//满减活动层级
        //满减活动--自选商品
        $rs2_DiscountGoodsActivity = DiscountGoods::select('discount_goods.goods_id','discount_goods.discount_id','discount_activity.title')
                ->leftjoin('discount_activity','discount_activity.id','=','discount_goods.discount_id')
                ->where(['discount_goods.merchant_id' => $merchant_id,  'discount_goods.status' => 1])
                ->whereIn('discount_goods.goods_id',$arr1_goods_id)
                ->where('discount_activity.start_time','<=',date('Y-m-d H:i:s'))
                ->where('discount_activity.end_time','>=',date('Y-m-d H:i:s'))
                ->where(['discount_activity.is_delete'=>1])
                ->get();
        //满减活动--全店参与
        if($rs2_DiscountGoodsActivity->isEmpty()){
            $rs2_DiscountActivity = DiscountActivity::select('discount_activity.id','discount_activity.title')
            ->where(['discount_activity.merchant_id' => $merchant_id])
            ->where('discount_activity.start_time','<=',date('Y-m-d H:i:s'))
            ->where('discount_activity.end_time','>=',date('Y-m-d H:i:s'))
            ->where(['discount_activity.is_delete'=>1,'range'=>2])
            ->first();
            if(!empty($rs2_DiscountActivity) && !empty($arr1_goods_id)){
                $arr_qdcy = array();
                foreach ($arr1_goods_id as $key=>$val){
                    $arr_qdcy[$key]['discount_id'] = $rs2_DiscountActivity->id;
                    $arr_qdcy[$key]['title'] = $rs2_DiscountActivity->title;
                    $arr_qdcy[$key]['goods_id'] = $val;
                }
                $rs2_DiscountGoodsActivity = $arr_qdcy;
            }
        }
        if( empty($rs2_DiscountGoodsActivity) ) {
            return $request_data['goods'];
        }
        
        //-------------日志 start-----------------
        $data_UserLog['merchant_id']=$merchant_id;
        $data_UserLog['user_id']='';
        $data_UserLog['type']=52;
        $data_UserLog['url']='merchant/improveinfo.json';
        $data_UserLog['content']=json_encode(array(
            '$arr1_goods_id'=>$arr1_goods_id,
            '$rs2_DiscountGoodsActivity'=>$rs2_DiscountGoodsActivity,
        ));
        ;
        $data_UserLog['ip']=get_client_ip();
        $data_UserLog['created_time']=date('Y-m-d H:i:s');
        $data_UserLog['updated_time']=date('Y-m-d H:i:s');
        //UserLog::insertGetId($data_UserLog);
        //-------------日志 end-----------------
        
        foreach ($rs2_DiscountGoodsActivity as $rs2_key1=>$rs2_val1){
            $arr2_GoodsId_DiscountId[$rs2_val1['goods_id']] = $rs2_val1['discount_id'];
            $arr3_DiscountId[] = $rs2_val1['discount_id'];
        }
        //dd($arr3_DiscountId);
        // 2.1  购物车的商品：对应的满减活动
        foreach ($request_data['goods'] as $key1=>$val1){
            $request_data['goods'][$key1]['discount_id'] = 0;
            $request_data['goods'][$key1]['discounted_price'] = 0;
            if( isset($arr2_GoodsId_DiscountId[$val1['goods_id']]) ){
                $request_data['goods'][$key1]['discount_id'] = $arr2_GoodsId_DiscountId[$val1['goods_id']];
            }
        }
        // 2.2 满减活动的层级数组
        if(!empty($arr3_DiscountId)){
            $rs3_DiscountItem = DiscountItem::select('id','discount_id', 'condition', 'cash_val','reduction','postage')
                    ->whereIn('discount_id' , $arr3_DiscountId)
                    ->orderBy('condition','asc')
                    ->get();
            //dd($rs3_DiscountItem);
            if( !empty($rs3_DiscountItem) ) {
                // 2.3 每个满减活动中有几个层级的详细内容
                foreach ($rs3_DiscountItem as $rs3_key1 => $rs3_val1){
                    // 2.4 每个满减活动中有几个层级
                    if(empty($arr7_DiscountItemNum[$rs3_val1['discount_id']])){
                        $arr7_DiscountItemNum[$rs3_val1['discount_id']]=1;
                    }else{
                        $arr7_DiscountItemNum[$rs3_val1['discount_id']]+=1;
                    }
        
                    $arr6_DiscountItem[$rs3_val1['discount_id']][$arr7_DiscountItemNum[$rs3_val1['discount_id']]]=array(
                        'id'=>$rs3_val1['id'],
                        'discount_id'=>$rs3_val1['discount_id'],
                        'condition'=>$rs3_val1['condition'],
                        'cash_val'=>$rs3_val1['cash_val'],
                        'reduction'=>$rs3_val1['reduction'],
                        'postage'=>$rs3_val1['postage'],
                    );
                }
            }
        }
        //dd($arr6_DiscountItem);
        // 3 计算金额过程
        // 3.1 参与满减,未减总金额
        $arr4_PreDiscountTotalMoney = array();
        foreach ($request_data['goods'] as $arr3_key2=>$arr3_val2){
            if(isset($arr3_val2['sum']) && isset($arr3_val2['price']) && !empty($arr3_val2['discount_id'])){
                if(!isset($arr4_PreDiscountTotalMoney[$arr3_val2['discount_id']])){
                    $arr4_PreDiscountTotalMoney[$arr3_val2['discount_id']] = $arr3_val2['sum']*$arr3_val2['price'];
                }else{
                    $arr4_PreDiscountTotalMoney[$arr3_val2['discount_id']] += $arr3_val2['sum']*$arr3_val2['price'];
                }
            }
        }
        //dd($arr4_PreDiscountTotalMoney);
        // 3.2 是否满足满减
        $arr5_Discount_MoneyOff = array();
        $arr5_Discount_baoyou = array();
        if(!empty($arr4_PreDiscountTotalMoney)){
            foreach ($arr4_PreDiscountTotalMoney as $key=>$val){//$key为满减活动的id
                for ($i=1;$i<=$arr7_DiscountItemNum[$key];$i++){
                    if($val>=$arr6_DiscountItem[$key][$i]['condition']){
                        $arr5_Discount_MoneyOff[$key] = $arr6_DiscountItem[$key][$i]['cash_val'];

                        //满包邮
                        $arr5_Discount_baoyou[$key]['reduction'] = $arr6_DiscountItem[$key][$i]['reduction'];//是否开启满减
                        $arr5_Discount_baoyou[$key]['postage'] = $arr6_DiscountItem[$key][$i]['postage'];//是否开启满包邮
                    }
                }
            }
        }
        //dd($arr5_Discount_baoyou);
        // 3.3 应减
        foreach ($request_data['goods'] as $arr3_key2=>$arr3_val2){
            if(!empty($arr3_val2['discount_id']) && !empty($arr5_Discount_MoneyOff[$arr3_val2['discount_id']])){
                $request_data['goods'][$arr3_key2]['discounted_price_float'] = ($arr3_val2['sum']*$arr3_val2['price']/$arr4_PreDiscountTotalMoney[$arr3_val2['discount_id']])*$arr5_Discount_MoneyOff[$arr3_val2['discount_id']];
                $request_data['goods'][$arr3_key2]['discounted_price'] = substr(sprintf("%.3f",$request_data['goods'][$arr3_key2]['discounted_price_float']),0,-1);

                //满包邮
                if(isset($arr5_Discount_baoyou[$arr3_val2['discount_id']]['reduction']) && isset($arr5_Discount_baoyou[$arr3_val2['discount_id']]['postage'])){
                    $request_data['goods'][$arr3_key2]['reduction'] = $arr5_Discount_baoyou[$arr3_val2['discount_id']]['reduction'];
                    $request_data['goods'][$arr3_key2]['postage'] = $arr5_Discount_baoyou[$arr3_val2['discount_id']]['postage'];
                }


            }
        }
        //dd($request_data['goods']);
        // 3.4 实减
        $arr5_Discount_MoneyOff_Acturl = array();
        foreach ($request_data['goods'] as $arr3_key2=>$arr3_val2){
            if( !empty($arr3_val2['discounted_price']) ){
                if(!isset($arr5_Discount_MoneyOff_Acturl[$arr3_val2['discount_id']])){
                    $arr5_Discount_MoneyOff_Acturl[$arr3_val2['discount_id']] = $arr3_val2['discounted_price'];
                }else{
                    $arr5_Discount_MoneyOff_Acturl[$arr3_val2['discount_id']] += $arr3_val2['discounted_price'];
                }
                
            }
        }
        //dd($arr5_Discount_MoneyOff_Acturl);
        // 3.5 应减和实减有落差,程序校正
        foreach ($request_data['goods'] as $arr3_key2=>$arr3_val2){
            if( isset($arr5_Discount_MoneyOff[$arr3_val2['discount_id']]) && isset($arr5_Discount_MoneyOff_Acturl[$arr3_val2['discount_id']]) && $arr5_Discount_MoneyOff[$arr3_val2['discount_id']]>$arr5_Discount_MoneyOff_Acturl[$arr3_val2['discount_id']] ){
                $request_data['goods'][$arr3_key2]['discounted_price'] += ($arr5_Discount_MoneyOff[$arr3_val2['discount_id']]-$arr5_Discount_MoneyOff_Acturl[$arr3_val2['discount_id']]);
                $arr5_Discount_MoneyOff_Acturl[$arr3_val2['discount_id']] = $arr5_Discount_MoneyOff[$arr3_val2['discount_id']];
            }
        }
        //dd($request_data['goods']);
        return $request_data['goods'];
    }

}
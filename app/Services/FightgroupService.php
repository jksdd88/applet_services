<?php
/**
 * 拼团服务类
 * Date: 2017-08-31
 * Time: 15:20
 */
namespace App\Services;

use App\Models\Fightgroup;
use App\Models\FightgroupItem;
use App\Models\FightgroupJoin;
use App\Models\FightgroupLadder;
use App\Models\FightgroupLaunch;
use App\Models\FightgroupRefund;
use App\Models\FightgroupStock;
use App\Models\OrderInfo;
use App\Models\OrderUmp;
use App\Models\Goods;

use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Support\Facades\Cache;
use App\Utils\CacheKey;

use App\Jobs\FightgroupStockAlter;
use App\Jobs\FightgroupNumsLessAlter;
use App\Jobs\FightgroupRefundr;
use App\Jobs\OrderCancel;
use App\Jobs\WeixinMsgJob;
use App\Jobs\DistribInitOrder;

use Log;


class FightgroupService {
    
    use DispatchesJobs;
    
    /**
     * 获取拼团活动   普通/规格商品库存信息
     * @author wangshen@dodoca.com
	 * @cdate 2017-9-5
     * 
     * @param int $fightgroup_id  活动id
     * @param int $merchant_id  商户id
     */
    public function getSkuInfo($fightgroup_id,$merchant_id){
        
        //拼团活动id
        $fightgroup_id = isset($fightgroup_id) ? (int)$fightgroup_id : 0;
        
        //商户id
        $merchant_id = isset($merchant_id) ? (int)$merchant_id : 0;
        
        //查询拼团活动信息
        $fightgroup_info = Fightgroup::get_data_by_id($fightgroup_id, $merchant_id);
        
        if(!$fightgroup_info){
            return ['errcode' => 60001,'errmsg' => '未查询到拼团活动信息'];
        }
        
        if($fightgroup_info['status'] == 0 || $fightgroup_info['status'] == 1){
            //未开始或进行中的拼团活动才占用商品库存
            
            if($fightgroup_info['is_sku'] == 1){
                //多规格
                
                $fightgroup_stock_list_all = [];
                
                //获取拼团活动库存表信息
                $fightgroup_stock_list = FightgroupStock::select('*')
                                                        ->where('merchant_id','=',$merchant_id)
                                                        ->where('fightgroup_id','=',$fightgroup_id)
                                                        ->get();
                $fightgroup_stock_list = json_decode($fightgroup_stock_list,true);
                                                        
                if($fightgroup_stock_list){
                    foreach($fightgroup_stock_list as $key=>$val){
                        //通过拼团库存表主键    获取库存
                        $fightgroup_stock_list[$key]['stock'] = $this->getStock($val['id'], $merchant_id);
                        $fightgroup_stock_list_all[$val['id']] = $fightgroup_stock_list[$key];
                    }
                }                    
                
            }else{
                //单规格
                
                //获取拼团活动库存表信息
                $fightgroup_stock_info = FightgroupStock::where('merchant_id','=',$merchant_id)
                                                        ->where('fightgroup_id','=',$fightgroup_id)
                                                        ->first();
                //通过拼团库存表主键    获取库存
                $fightgroup_stock_info['stock'] = $this->getStock($fightgroup_stock_info['id'], $merchant_id);
            }
            
            
            
            //拼团活动占用剩余的总库存
            $data['fightgroup_stock'] = $this->getActivityStock($fightgroup_id, $merchant_id);
            
            //单/多规格
            $data['is_sku'] = $fightgroup_info['is_sku'];
            
            
            //拼团活动库存表数据
            if($fightgroup_info['is_sku'] == 1){
                $data['fightgroup_stock_list_more'] = $fightgroup_stock_list_all;   //多规格
                $data['fightgroup_stock_info_single'] = '';                         //单规格
            }else{
                $data['fightgroup_stock_list_more'] = '';                           //多规格
                $data['fightgroup_stock_info_single'] = $fightgroup_stock_info;     //单规格
            }
            
            
            
            return ['errcode' => 0,'errmsg' => 'ok','data' => $data];
            
        }else{
            //已结束的拼团活动无活动库存
            return ['errcode' => 60002,'errmsg' => '已结束的拼团活动无活动库存'];
        }
        
        
    }

    
    /**
     * 获取拼团活动 普通/规格库存
     * @author wangshen@dodoca.com
     * @cdate 2017-9-7
     *
     * @param int $fightgroup_stock_id  拼团库存表主键id
     * @param int $merchant_id  商户id
     */
    public function getStock($fightgroup_stock_id,$merchant_id){
    
        //键名
        $key = CacheKey::get_fightgroup_stock_key($fightgroup_stock_id, $merchant_id);
        
        if(Cache::has($key)){
            //根据键名获取键值
            $stock = Cache::get($key);
            
        }else{
            //取库存
            $stock = FightgroupStock::where('id','=',$fightgroup_stock_id)
                                    ->where('merchant_id','=',$merchant_id)
                                    ->value('stock');
            
            //取库存是否正确                                    
            if(isset($stock)){
                Cache::forever($key, $stock);
            }else{
                $stock = '';
            }     
            
        }
        
        return $stock;
    }
    
    /**
     * 获取整个拼团活动库存
     * @author wangshen@dodoca.com
     * @cdate 2017-9-7
     *
     * @param int $fightgroup_id  拼团活动id
     * @param int $merchant_id  商户id
     */
    public function getActivityStock($fightgroup_id,$merchant_id){
    
        $fightgroup_stock_list = FightgroupStock::select('id')
                                                ->where('merchant_id','=',$merchant_id)
                                                ->where('fightgroup_id','=',$fightgroup_id)
                                                ->get();
        $fightgroup_stock_list = json_decode($fightgroup_stock_list,true);
    
        //整个拼团活动库存
        $activity_stock = 0;
    
        if($fightgroup_stock_list){
            foreach($fightgroup_stock_list as $key=>$val){
    
                $stock = $this->getStock($val['id'], $merchant_id);
    
                //累加
                $activity_stock = $activity_stock + $stock;
    
                unset($stock);
            }
        }
    
        return $activity_stock;
    }
    
    
    /**
     * 减库存
     * @author wangshen@dodoca.com
     * @cdate 2017-9-7
     *
     * @param int $merchant_id  商户id
     * @param int $fightgroup_stock_id  拼团库存表主键id
     * @param int $num  数量
     */
    public function decStock($merchant_id,$fightgroup_stock_id,$num){
    
        //键名
        $key = CacheKey::get_fightgroup_stock_key($fightgroup_stock_id, $merchant_id);
    
        //获取库存
        $stock = $this->getStock($fightgroup_stock_id, $merchant_id);
        
        
        if($num > $stock){
            
            //减库存失败
            return ['errcode' => 60022,'errmsg' => '拼团活动减库存失败，库存不足'];
        }
    
        //缓存数自减
        $cache_rs = Cache::decrement($key,$num);
        
        //自减为负，减库存失败
        if($cache_rs < 0){
            
            //自减的库存，加回去
            Cache::increment($key,$num);
            
            //减库存失败
            return ['errcode' => 60022,'errmsg' => '拼团活动减库存失败，库存不足'];
        }
        
        
        //队列改数据库
        $type = 2;//1加库存，2减库存
        
        $job = new FightgroupStockAlter($merchant_id, $fightgroup_stock_id, $num, $type);
        $this->dispatch($job);
        
        //减库存成功（data为减成功后的库存）
        return ['errcode' => 0,'errmsg' => 'ok','data' => $cache_rs];
    }
    
    /**
     * 加库存
     * @author wangshen@dodoca.com
     * @cdate 2017-9-7
     *
     * @param int $merchant_id  商户id
     * @param int $fightgroup_stock_id  拼团库存表主键id
     * @param int $num  数量
     */
    public function incStock($merchant_id,$fightgroup_stock_id,$num){
    
        //键名
        $key = CacheKey::get_fightgroup_stock_key($fightgroup_stock_id, $merchant_id);
    
        //获取库存
        $stock = $this->getStock($fightgroup_stock_id, $merchant_id);
    
        //缓存数自增
        $cache_rs = Cache::increment($key,$num);
        
        
        //队列改数据库
        $type = 1;//1加库存，2减库存
        
        $job = new FightgroupStockAlter($merchant_id, $fightgroup_stock_id, $num, $type);
        $this->dispatch($job);
        
    
        //加库存成功（data为加成功后的库存）
        return ['errcode' => 0,'errmsg' => 'ok','data' => $cache_rs];
    }
    
    
    /**
     * 获取某个发起的团可用名额
     * @author wangshen@dodoca.com
     * @cdate 2017-9-19
     *
     * @param int $fightgroup_launch_id  拼团发起表主键id
     * @param int $merchant_id  商户id
     */
    public function getLaunchNumsLess($fightgroup_launch_id,$merchant_id){
    
        //键名
        $key = CacheKey::get_fightgroup_launch_numsless_key($fightgroup_launch_id, $merchant_id);
    
        if(Cache::has($key)){
            //根据键名获取键值
            $nums_less = Cache::get($key);
    
        }else{
            //取可用名额
            $nums_less = FightgroupLaunch::where('id','=',$fightgroup_launch_id)
                                            ->where('merchant_id','=',$merchant_id)
                                            ->value('nums_less');
    
            //取可用名额是否正确
            if(isset($nums_less)){
                Cache::forever($key, $nums_less);
            }else{
                $nums_less = '';
            }
    
        }
    
        return $nums_less;
    }

    /**
     * 某个发起的团减可用名额（每次固定减1）
     * @author wangshen@dodoca.com
     * @cdate 2017-9-19
     *
     * @param int $fightgroup_launch_id  拼团发起表主键id
     * @param int $merchant_id  商户id
     */
    public function decLaunchNumsLess($fightgroup_launch_id,$merchant_id){
    
        //数量
        $num = 1;//（每次固定减1）
        
        //键名
        $key = CacheKey::get_fightgroup_launch_numsless_key($fightgroup_launch_id, $merchant_id);
    
        //获取可用名额
        $nums_less = $this->getLaunchNumsLess($fightgroup_launch_id, $merchant_id);
    
    
        if($num > $nums_less){
    
            //减可用名额失败
            return ['errcode' => 60025,'errmsg' => '扣除团可用名额失败'];
        }
    
        //缓存数自减
        $cache_rs = Cache::decrement($key,$num);
    
        //自减为负， 减可用名额失败
        if($cache_rs < 0){
    
            //自减的库存，加回去
            Cache::increment($key,$num);
    
            //减可用名额失败
            return ['errcode' => 60025,'errmsg' => '扣除团可用名额失败'];
        }
    
    
        //队列改数据库
    
        $job = new FightgroupNumsLessAlter($merchant_id, $fightgroup_launch_id, $num);
        $this->dispatch($job);
    
        //减可用名额成功（data为减成功后的可用名额）
        return ['errcode' => 0,'errmsg' => 'ok','data' => $cache_rs];
    }
    
    
    
    /**
     * 获取会员整个拼团活动实际参与次数
     * @author wangshen@dodoca.com
     * @cdate 2017-9-12
     *
     * @param int $merchant_id  商户id
     * @param int $fightgroup_id  拼团活动id
     * @param int $member_id  会员id
     */
    public function memberJoinNums($merchant_id,$fightgroup_id,$member_id){
    
        $join_nums = FightgroupJoin::where('merchant_id','=',$merchant_id)
                                    ->where('fightgroup_id','=',$fightgroup_id)
                                    ->where('member_id','=',$member_id)
                                    ->whereIn('status', [1,4,6,7])
                                    ->count();
        return $join_nums;
    }
    
    /**
     * 获取会员某个单/多规格实际参与的购买数量
     * @author wangshen@dodoca.com
     * @cdate 2017-9-15
     *
     * @param int $merchant_id  商户id
     * @param int $fightgroup_id  拼团活动id
     * @param int $member_id  会员id
     * @param int $fightgroup_stock_id  拼团库存表id
     */
    public function memberStockBuyNums($merchant_id,$fightgroup_id,$member_id,$fightgroup_stock_id){
    
        //通过库存id，查询库存对应的子表id
        $item_ids = FightgroupItem::select('id')
                                    ->where('merchant_id','=',$merchant_id)
                                    ->where('fightgroup_id','=',$fightgroup_id)
                                    ->where('stock_id','=',$fightgroup_stock_id)
                                    ->get();
        $item_ids = json_decode($item_ids,true);
                                    
        $item_ids_arr = array();
        
        if (count($item_ids) > 0) {
            foreach ($item_ids as $key => $val) {
                $item_ids_arr[] = $val['id'];
            }
        }
        
        $buy_nums = FightgroupJoin::where('merchant_id','=',$merchant_id)
                                    ->where('fightgroup_id','=',$fightgroup_id)
                                    ->where('member_id','=',$member_id)
                                    ->whereIn('item_id', $item_ids_arr)
                                    ->whereIn('status', [1,4,6,7])
                                    ->sum('num');
        return $buy_nums;
    }
    
    
    
    /**
     * 验证会员参与拼团活动有效性
     * @author wangshen@dodoca.com
     * @cdate 2017-9-14
     * 
     * @param int $merchant_id  商户id
     * @param int $member_id  会员id
     * @param int $fightgroup_id  活动id
     * @param int $fightgroup_item_id  拼团子表id
     * @param int $quantity  购买数量
     * @param int $fightgroup_launch_id  拼团发起表id（开团为0）
     * @param int $type  类型：1开团，2参团
     * @param int $if_pay  是否去支付时验证：0否，1是
     * 
     */
    public function verifyDoFightgroup($merchant_id,$member_id,$fightgroup_id,$fightgroup_item_id,$quantity,$fightgroup_launch_id,$type,$if_pay=0){
        
        //查询拼团活动信息
        $fightgroup_info = Fightgroup::get_data_by_id($fightgroup_id, $merchant_id);
        
        //验证无效：未查询到拼团活动信息
        if(!$fightgroup_info){
            return ['errcode' => 60001,'errmsg' => '未查询到拼团活动信息'];
        }
        
        //查询商品信息
        $goods_info = Goods::get_data_by_id($fightgroup_info['goods_id'], $merchant_id);
        
        //验证无效：未查询到拼团商品信息
        if(!$goods_info){
            return ['errcode' => 60027,'errmsg' => '未查询到拼团商品信息'];
        }
        
        //验证无效：拼团商品已删除
        if($goods_info['is_delete'] == -1){
            return ['errcode' => 60028,'errmsg' => '拼团商品已删除'];
        }
        
        //验证无效：不是进行中的拼团活动
        if($fightgroup_info['status'] != 1){
            return ['errcode' => 60006,'errmsg' => '不是进行中的拼团活动'];
        }
        
        if(date('Y-m-d H:i:s') < $fightgroup_info['start_time']){
            return ['errcode' => 60006,'errmsg' => '不是进行中的拼团活动'];
        }
        
        if($fightgroup_info['end_time'] != '0000-00-00 00:00:00'){
            if(date('Y-m-d H:i:s') > $fightgroup_info['end_time']){
                return ['errcode' => 60006,'errmsg' => '不是进行中的拼团活动'];
            }
        }
        
        
        //获取会员整个拼团活动实际参与次数
        $member_true_join_nums = $this->memberJoinNums($merchant_id, $fightgroup_id, $member_id);
        
        //验证无效：会员整个拼团活动实际参与次数达到上限
        if($fightgroup_info['join_limit'] != 0){
            if($member_true_join_nums >= $fightgroup_info['join_limit']){
                return ['errcode' => 60007,'errmsg' => '您参加的团已到上限'];
            }
        }
        
        
        
        
        //查询拼团子表信息
        $fightgroup_item_info = FightgroupItem::get_data_by_id($fightgroup_item_id, $merchant_id);
        
        //验证无效：拼团子表信息不存在
        if(!$fightgroup_item_info){
            return ['errcode' => 60005,'errmsg' => '拼团规格信息不存在'];
        }
        
        
        //查询拼团阶梯表信息
        $fightgroup_ladder_info = FightgroupLadder::get_data_by_id($fightgroup_item_info['ladder_id'], $merchant_id);
        
        
        //验证无效：拼团阶梯信息不存在
        if(!$fightgroup_ladder_info){
            return ['errcode' => 60003,'errmsg' => '拼团阶梯信息不存在'];
        }
        
        
        //验证无效：不是进行中的拼团阶梯
        if($fightgroup_ladder_info['status'] != 1){
            return ['errcode' => 60008,'errmsg' => '不是进行中的拼团阶梯'];
        }
        
        
        
        //购买的的单/多规格活动库存
        $stock = $this->getStock($fightgroup_item_info['stock_id'], $merchant_id);
        
        //验证无效：购买的规格库存不足
        if($quantity > $stock){
            return ['errcode' => 60009,'errmsg' => '库存不足'];
        }
        
        
        //会员某个单/多规格实际参与的购买数量
        $member_stock_buy_nums = $this->memberStockBuyNums($merchant_id, $fightgroup_id, $member_id, $fightgroup_item_info['stock_id']);
        
        //验证无效：超出限购件数
        if($fightgroup_item_info['limit_num'] != 0){
            
            if($quantity + $member_stock_buy_nums > $fightgroup_item_info['limit_num']){
                return ['errcode' => 60010,'errmsg' => '超出限购件数'];
            }
        }
        
        
        //开团或参团还未支付时验证
        if($if_pay != 1){
            //判断当前拼团活动是否有未支付的参与记录
            $has_not_pay_c = FightgroupJoin::where('merchant_id','=',$merchant_id)
                                            ->where('fightgroup_id','=',$fightgroup_id)
                                            ->where('member_id','=',$member_id)
                                            ->where('status','=',0)
                                            ->count();
            
            //验证无效：该拼团活动有未支付订单
            if($has_not_pay_c > 0){
                return ['errcode' => 60011,'errmsg' => '该拼团活动有未支付订单'];
            }
        }
        
        
        
        //判断开团或参团
        if($type == 1){//开团
            
            //判断开团人是否有一个正在进行中的已开团
            $has_doing_launch_c = FightgroupLaunch::where('merchant_id','=',$merchant_id)
                                                    ->where('fightgroup_id','=',$fightgroup_id)
                                                    ->where('member_id','=',$member_id)
                                                    ->where('status','=',1)
                                                    ->count();
            
            //验证无效：您有一个已开团正在进行中
            if($has_doing_launch_c > 0){
                return ['errcode' => 60012,'errmsg' => '您有一个已开团正在进行中'];
            }
            
            //开团无拼团发起表信息
            $fightgroup_launch_info = '';
            
        }elseif($type == 2){//参团
            
            //查询拼团发起表信息
            $fightgroup_launch_info = FightgroupLaunch::get_data_by_id($fightgroup_launch_id, $merchant_id);
            
            //验证无效：团信息不存在
            if(!$fightgroup_launch_info){
                return ['errcode' => 60004,'errmsg' => '团信息不存在'];
            }
            
            //验证无效：不是进行中的团
            if($fightgroup_launch_info['status'] != 1){
                return ['errcode' => 60013,'errmsg' => '不是进行中的团'];
            }
            
            //验证无效：该团可用名额不足
            if($fightgroup_launch_info['nums_less'] <= 0){
                return ['errcode' => 60014,'errmsg' => '该团可用名额不足'];
            }
            
            //验证无效：已过成团时间
            if($fightgroup_launch_info['end_time'] <= date('Y-m-d H:i:s')){
                return ['errcode' => 60015,'errmsg' => '已过成团时间'];
            }
            
            //判断是否已经是团长
            $if_lunch_captain_c = FightgroupJoin::where('merchant_id','=',$merchant_id)
                                                ->where('fightgroup_id','=',$fightgroup_id)
                                                ->where('launch_id','=',$fightgroup_launch_id)
                                                ->where('member_id','=',$member_id)
                                                ->where('is_captain','=',1)
                                                ->count();
            
            //验证无效：您已是该团的团长
            if($if_lunch_captain_c > 0){
                return ['errcode' => 60016,'errmsg' => '您已是该团的团长'];
            }
            
            
            //判断是否实际参加过该团
            $if_true_join_c = FightgroupJoin::where('merchant_id','=',$merchant_id)
                                            ->where('fightgroup_id','=',$fightgroup_id)
                                            ->where('launch_id','=',$fightgroup_launch_id)
                                            ->where('member_id','=',$member_id)
                                            ->whereIn('status', [1,4,6,7])
                                            ->count();
            
            //验证无效：您已参加过该团
            if($if_true_join_c > 0){
                return ['errcode' => 60017,'errmsg' => '您已参加过该团'];
            }
            
        }
        
        
        //返回数据
        $data['fightgroup_info'] = $fightgroup_info;//活动信息
        $data['fightgroup_item_info'] = $fightgroup_item_info;//拼团子表信息
        $data['fightgroup_ladder_info'] = $fightgroup_ladder_info;//拼团阶梯表信息
        $data['fightgroup_launch_info'] = $fightgroup_launch_info;//拼团发起表信息（开团为空）
        
        
        return ['errcode' => 0,'errmsg' => 'ok','data' => $data];
    }
    
    
    /**
     * 开团或参团去支付 验证会员参与拼团活动有效性
     * @author wangshen@dodoca.com
     * @cdate 2017-9-18
     *
     * @param array $order_data  订单数据
     *
     */
    public function verifyPayFightgroup($order_data){
        
        //缺少订单数据
        if(empty($order_data)){
            return ['errcode' => 60019,'errmsg' => '缺少订单数据'];
        }
        
        
        //查询拼团参与表信息
        $fightgroup_join_info = FightgroupJoin::select('id',
                                                'merchant_id',
                                                'fightgroup_id',
                                                'launch_id',
                                                'item_id',
                                                'member_id',
                                                'num',
                                                'is_captain'
                                                )
                                                ->where('order_id','=',$order_data['id'])
                                                ->first();
        
        if(!$fightgroup_join_info){
            return ['errcode' => 60020,'errmsg' => '未查询到拼团参与信息'];
        }
        
        
        $merchant_id          = $fightgroup_join_info['merchant_id'];//商户id
        $member_id            = $fightgroup_join_info['member_id'];//会员id
        $fightgroup_id        = $fightgroup_join_info['fightgroup_id'];//活动id
        $fightgroup_item_id   = $fightgroup_join_info['item_id'];//拼团子表id
        $quantity             = $fightgroup_join_info['num'];//购买数量
        $fightgroup_launch_id = $fightgroup_join_info['is_captain'] == 1 ? 0 : $fightgroup_join_info['launch_id'];//拼团发起表（团员传拼团发起表id，团长传0）
        $type                 = $fightgroup_join_info['is_captain'] == 1 ? 1 : 2;//is_captain：0团员，1团长；$type：1开团，2参团
        $if_pay               = 1;//去支付时验证会员参与拼团活动有效性
         
        
        //验证会员参与拼团活动有效性（去支付时验证）
        $verifyDoFightgroup = $this->verifyDoFightgroup($merchant_id,$member_id,$fightgroup_id,$fightgroup_item_id,$quantity,$fightgroup_launch_id,$type,$if_pay);
        
        if($verifyDoFightgroup['errcode'] != 0){
        
            //验证有效性失败返回错误信息
            return ['errcode' => $verifyDoFightgroup['errcode'],'errmsg' => $verifyDoFightgroup['errmsg']];
        }
        
        
        
        //验证成功
        
        return ['errcode' => 0,'errmsg' => 'ok'];
    }
    
    
    
    
    
    
    
    /**
     * 订单取消相关处理（超时未支付自动取消或会员主动取消）
     * @author wangshen@dodoca.com
     * @cdate 2017-9-18
     *
     * @param array $order_data  订单数据
     *
     */
    public function fightgroupJoinCancel($order_data){
        
        //缺少订单数据
        if(empty($order_data)){
            return ['errcode' => 60019,'errmsg' => '缺少订单数据'];
        }
        
        
        //查询拼团参与表信息
        $fightgroup_join_info = FightgroupJoin::select('id',
                                                'merchant_id',
                                                'launch_id',
                                                'status',
                                                'is_captain'
                                                )
                                                ->where('order_id','=',$order_data['id'])
                                                ->first();
        
        if(!$fightgroup_join_info){
            return ['errcode' => 60020,'errmsg' => '未查询到拼团参与信息'];
        }
        
        if($fightgroup_join_info['status'] != 0){
            return ['errcode' => 60021,'errmsg' => '非待支付拼团订单'];
        }
        
        
        
        $merchant_id = $fightgroup_join_info['merchant_id'];//商户id
        
        $fightgroup_launch_id = $fightgroup_join_info['launch_id'];//拼团发起表id
        
        
        
        //判断开团或参团
        if($fightgroup_join_info['is_captain'] == 1){//开团
            
            //更新拼团发起表状态为：3团长开团超时未支付
            FightgroupLaunch::update_data($fightgroup_launch_id, $merchant_id, ['status' => 3]);
            
            //更新拼团参与表状态为：2失败（超时未支付）
            FightgroupJoin::update_data($fightgroup_join_info['id'], $merchant_id, ['status' => 2,'fail_status' => 2]);
            
        }elseif($fightgroup_join_info['is_captain'] == 0){//参团
            
            //更新拼团参与表状态为：2失败（超时未支付）
            FightgroupJoin::update_data($fightgroup_join_info['id'], $merchant_id, ['status' => 2,'fail_status' => 2]);
            
        }
        
        
        
        return ['errcode' => 0,'errmsg' => 'ok'];
    }
    
    
    
    /**
     * 开团或参团支付成功相关处理
     * @author wangshen@dodoca.com
     * @cdate 2017-9-18
     *
     * @param array  $order_data  订单数据
     * @param string $order_data['pay_time']  支付时间
     * @param int    $order_data['is_oversold']  是否超卖（1-超卖，0-正常）
     * @param string $orderrefund_data  若超卖，则有超卖退单返回数据，非超卖则为空
     *
     */
    public function fightgroupPayBack($order_data,$orderrefund_data){
        
        //缺少订单数据
        if(empty($order_data)){
            return ['errcode' => 60019,'errmsg' => '缺少订单数据'];
        }
        
        //查询拼团参与表信息
        $fightgroup_join_info = FightgroupJoin::where('order_id','=',$order_data['id'])->first();
        
        if(!$fightgroup_join_info){
            return ['errcode' => 60020,'errmsg' => '未查询到拼团参与信息'];
        }
        
//         if($fightgroup_join_info['status'] != 0){
//             return ['errcode' => 60021,'errmsg' => '非待支付拼团订单'];
//         }
        
        //参数
        
        //商户id
        $merchant_id = $fightgroup_join_info['merchant_id'];
        
        //拼团活动id
        $fightgroup_id = $fightgroup_join_info['fightgroup_id'];
        
        //拼团子表id
        $fightgroup_item_id = $fightgroup_join_info['item_id'];
        
        //拼团发起表id
        $fightgroup_launch_id = $fightgroup_join_info['launch_id'];
        
        //拼团参与表id
        $fightgroup_join_id = $fightgroup_join_info['id'];
        
        
        //类型：1开团，2参团
        $type = $fightgroup_join_info['is_captain'] == 1 ? 1 : 2;//is_captain：0团员，1团长；$type：1开团，2参团
        
        
        //活动数据
        
        //查询拼团活动信息
        $fightgroup_info = Fightgroup::get_data_by_id($fightgroup_id, $merchant_id);
        
        //查询拼团子表信息
        $fightgroup_item_info = FightgroupItem::get_data_by_id($fightgroup_item_id, $merchant_id);
        
        //拼团阶梯表id
        $fightgroup_ladder_id = $fightgroup_item_info['ladder_id'];
        
        //查询拼团阶梯表信息
        $fightgroup_ladder_info = FightgroupLadder::get_data_by_id($fightgroup_ladder_id, $merchant_id);
        
        //查询拼团发起表信息
        $fightgroup_launch_info = FightgroupLaunch::get_data_by_id($fightgroup_launch_id, $merchant_id);
        
        
        
        
        //库存超卖（订单支付成功统一回调已做退款处理）
        if($order_data['is_oversold'] == 1){
            
            if($type == 1){//开团
            
                //更新拼团发起表状态为：4开团失败（库存不足超卖）
                FightgroupLaunch::update_data($fightgroup_launch_id, $merchant_id, ['status' => 4]);
                
                //更新拼团参与表状态为：3失败（库存不足超卖）
                FightgroupJoin::update_data($fightgroup_join_id, $merchant_id, ['status' => 3,'fail_status' => 3]);
                
            }elseif($type == 2){//参团
            
                //更新拼团参与表状态为：3失败（库存不足超卖）
                FightgroupJoin::update_data($fightgroup_join_id, $merchant_id, ['status' => 3,'fail_status' => 3]);
                
            }
            
            
            //退款处理
            $this->joinRefund($fightgroup_join_info, $fightgroup_item_info, 3, $orderrefund_data);
            
            return ['errcode' => 60023,'errmsg' => '拼团活动库存不足'];
        }
        
        
        //库存足够
        
        //判断是否有效拼团活动，拼团是否进行中（无效则做超卖处理）
        $if_valid = 1;//0无效，1有效
        
        if($fightgroup_info['status'] != 1){//拼团大活动非进行中
            $if_valid = 0;
        }
        
        if(date('Y-m-d H:i:s') < $fightgroup_info['start_time']){//拼团大活动非进行中
            $if_valid = 0;
        }
        
        if($fightgroup_info['end_time'] != '0000-00-00 00:00:00'){//拼团大活动非进行中
            if(date('Y-m-d H:i:s') > $fightgroup_info['end_time']){
                $if_valid = 0;
            }
        }
        
        if($fightgroup_ladder_info['status'] != 1){//拼团阶梯非进行中
            $if_valid = 0;
        }
        
        if($type == 2 && $fightgroup_launch_info['status'] != 1){//参团，团非进行中
            $if_valid = 0;
        }
        
        
        if($if_valid == 0){
            //非进行中（活动、阶梯、团）超卖
            
            if($type == 1){//开团
            
                //更新拼团发起表状态为：7开团失败（非进行中（活动、阶梯、团）超卖）
                FightgroupLaunch::update_data($fightgroup_launch_id, $merchant_id, ['status' => 7]);
            
                //更新拼团参与表状态为：8失败（非进行中（活动、阶梯、团）超卖）
                FightgroupJoin::update_data($fightgroup_join_id, $merchant_id, ['status' => 8,'fail_status' => 8]);
            
            }elseif($type == 2){//参团
            
                //更新拼团参与表状态为：8失败（非进行中（活动、阶梯、团）超卖）
                FightgroupJoin::update_data($fightgroup_join_id, $merchant_id, ['status' => 8,'fail_status' => 8]);
            
            }
            
            //退款、还库存处理
            $this->joinRefund($fightgroup_join_info, $fightgroup_item_info, 1, $orderrefund_data);
            
            
            
            return ['errcode' => 60024,'errmsg' => '非进行中的拼团活动、阶梯或团'];
        }
        
        
        //参团，团人员超额处理
        if($type == 2){//参团
            
            //扣除团可用名额（扣除失败，可用名额不减）
            $join_dec_nums_less_rs = $this->decLaunchNumsLess($fightgroup_launch_id, $merchant_id);
            
            if($join_dec_nums_less_rs['errcode'] != 0){
                //扣除失败，团人员超额（已成团超卖）
                
                //更新拼团参与表状态为：5已成团超卖
                FightgroupJoin::update_data($fightgroup_join_id, $merchant_id, ['status' => 5,'fail_status' => 5]);
                
                
                
                //退款、还库存处理
                $this->joinRefund($fightgroup_join_info, $fightgroup_item_info, 2, $orderrefund_data);
                
                
                return ['errcode' => 60026,'errmsg' => '该团可用名额不足，已成团'];
            }
            
        }
        
        
        
        
        //有效开团或参团处理
        if($type == 1){//开团
            
            //开团成功扣可用名额
            $this->decLaunchNumsLess($fightgroup_launch_id, $merchant_id);
            
            
            //开团成功处理数据
            
            //更新拼团阶梯表
            $fightgroup_ladder_up_data = [
                'created_num' => $fightgroup_ladder_info['created_num'] + 1 //开团数量+1
            ];
            
            FightgroupLadder::update_data($fightgroup_ladder_id, $merchant_id, $fightgroup_ladder_up_data);
            
            
            //更新拼团子表
            $fightgroup_item_up_data = [
                'sell_num' => $fightgroup_item_info['sell_num'] + $fightgroup_join_info['num'], //增加已售件数
                'created_num' => $fightgroup_item_info['created_num'] + 1 //开团数量+1
            ];
            
            FightgroupItem::update_data($fightgroup_item_id, $merchant_id, $fightgroup_item_up_data);
            
            
            //更新拼团发起表
            //团结束时间
            $launch_end_time = date('Y-m-d H:i:s', strtotime("+".$fightgroup_ladder_info['expire_in']." hour"));
            
            $fightgroup_launch_up_data = [
                'nums_join' => 1,   //开团成功已用名额为1
                'status' => 1,  //状态改为：1拼团中
                'end_time' => $launch_end_time  //结束时间更改为：付款成功后加上拼团所需时间
            ];
            
            FightgroupLaunch::update_data($fightgroup_launch_id, $merchant_id, $fightgroup_launch_up_data);
            
            
            //更新拼团参与表
            $fightgroup_join_up_data = [
                'status' => 1,  //状态改为：1已支付
                'pay_time' => $order_data['pay_time']  //支付时间更新
            ];
            
            FightgroupJoin::update_data($fightgroup_join_id, $merchant_id, $fightgroup_join_up_data);
            
            
            
        }elseif($type == 2){//参团
            
            //扣除团可用名额未失败，参团成功（团可用名额已扣）
            
            
            //可用名额扣为0，则为成团
            if($join_dec_nums_less_rs['data'] != 0){
                //参团处理
                
                //更新拼团子表
                $fightgroup_item_up_data = [
                    'sell_num' => $fightgroup_item_info['sell_num'] + $fightgroup_join_info['num'] //增加已售件数
                ];
                
                FightgroupItem::update_data($fightgroup_item_id, $merchant_id, $fightgroup_item_up_data);
                
                
                //更新拼团发起表
                $fightgroup_launch_up_data = [
                    'nums_join' => $fightgroup_launch_info['nums'] - $join_dec_nums_less_rs['data']   //参团成功已用名额为所需人数减去可用名额
                ];
                
                FightgroupLaunch::update_data($fightgroup_launch_id, $merchant_id, $fightgroup_launch_up_data);
                
                
                //更新拼团参与表
                $fightgroup_join_up_data = [
                    'status' => 1,  //状态改为：1已支付
                    'pay_time' => $order_data['pay_time']  //支付时间更新
                ];
                
                FightgroupJoin::update_data($fightgroup_join_id, $merchant_id, $fightgroup_join_up_data);
                
                
            }elseif($join_dec_nums_less_rs['data'] == 0){
                
                //成团处理（可用名额为0）
                
                
                //更新拼团阶梯表
                $fightgroup_ladder_up_data = [
                    'finished_num' => $fightgroup_ladder_info['finished_num'] + 1 //成团数量+1
                ];
                
                FightgroupLadder::update_data($fightgroup_ladder_id, $merchant_id, $fightgroup_ladder_up_data);
                
                
                //更新拼团子表
                //更新团员拼团子表
                $fightgroup_item_up_data_ty = [
                    'sell_num' => $fightgroup_item_info['sell_num'] + $fightgroup_join_info['num'] //增加已售件数
                ];
                
                FightgroupItem::update_data($fightgroup_item_id, $merchant_id, $fightgroup_item_up_data_ty);
                
                //更新团长拼团子表
                //团长拼团子表信息
                $fightgroup_item_info_tz = FightgroupItem::get_data_by_id($fightgroup_launch_info['item_id'], $merchant_id);
                
                $fightgroup_item_up_data_tz = [
                    'finished_num' => $fightgroup_item_info_tz['finished_num'] + 1 //成团数量+1
                ];
                
                FightgroupItem::update_data($fightgroup_launch_info['item_id'], $merchant_id, $fightgroup_item_up_data_tz);
                
                
                //更新拼团发起表
                $fightgroup_launch_up_data = [
                    'nums_join' => $fightgroup_launch_info['nums'],   //拼团成功已用名额为所需人数
                    'status' => 2,  //状态改为：2成功
                    'success_at' => date('Y-m-d H:i:s') //成团时间改为当前时间
                ];
                
                FightgroupLaunch::update_data($fightgroup_launch_id, $merchant_id, $fightgroup_launch_up_data);
                
                
                //更新拼团参与表
                //更新自己的拼团参与表
                $fightgroup_join_up_data = [
                    'status' => 6,  //状态改为：6参团成功
                    'pay_time' => $order_data['pay_time']  //支付时间更新
                ];
                
                FightgroupJoin::update_data($fightgroup_join_id, $merchant_id, $fightgroup_join_up_data);
                
                
                //更新当前团其他已支付团员的拼团参与表
                FightgroupJoin::where('merchant_id','=',$merchant_id)
                                ->where('launch_id','=',$fightgroup_launch_id)
                                ->where('status','=',1) //1已支付
                                ->update(['status' => 6]);  //状态改为：6参团成功
                
                                
                //成团给所有参团成功的人发送消息模版
                $fightgroup_join_success_list = FightgroupJoin::select('id','member_id','order_id')
                                                                ->where('merchant_id','=',$merchant_id)
                                                                ->where('launch_id','=',$fightgroup_launch_id)
                                                                ->where('status','=',6) //6参团成功
                                                                ->get();
                $fightgroup_join_success_list = json_decode($fightgroup_join_success_list,true);
                         
                if($fightgroup_join_success_list){
                    foreach($fightgroup_join_success_list as $key=>$val){
                        
                        //成团产生佣金
                        \Log::info('fightgroup:distrib:order_id->'.$val['order_id'].',result->start');
                        $result = $this->dispatch(new DistribInitOrder($val['order_id'],$merchant_id));
                        \Log::info('fightgroup:distrib:order_id->'.$val['order_id'].',result->'.json_encode($result,JSON_UNESCAPED_UNICODE));
                        
                        //发送消息模板
                        $job = new WeixinMsgJob(['order_id'=>$val['order_id'],'merchant_id'=>$merchant_id,'type'=>'fightgroup']);
                        $this->dispatch($job);
                    }
                }
                
                
                
                                
                
                //当前团其他待支付的人做订单取消操作
                $this->launchJoinCancel($merchant_id, $fightgroup_launch_id, 3);
                                
                
                                
                //更新拼团库存表（成团售出库存字段更新）
                $fightgroup_stock_list = FightgroupStock::select('id','sell_stock')
                                                        ->where('merchant_id','=',$merchant_id)
                                                        ->where('fightgroup_id','=',$fightgroup_id)
                                                        ->get();
                $fightgroup_stock_list = json_decode($fightgroup_stock_list,true);
                
                if($fightgroup_stock_list){
                    foreach($fightgroup_stock_list as $key=>$val){
                        
                        //通过库存id，查询库存对应的子表id
                        $item_ids = FightgroupItem::select('id')
                                                    ->where('merchant_id','=',$merchant_id)
                                                    ->where('fightgroup_id','=',$fightgroup_id)
                                                    ->where('stock_id','=',$val['id'])
                                                    ->get();
                        $item_ids = json_decode($item_ids,true);
                        
                        $item_ids_arr = array();
                        
                        if (count($item_ids) > 0) {
                            foreach ($item_ids as $k => $v) {
                                $item_ids_arr[] = $v['id'];
                            }
                        }
                        
                        //查询该团参团成功的团员购买该库存的总数
                        $join_all_buy_nums = FightgroupJoin::where('merchant_id','=',$merchant_id)
                                                            ->where('fightgroup_id','=',$fightgroup_id)
                                                            ->where('launch_id','=',$fightgroup_launch_id)
                                                            ->whereIn('item_id', $item_ids_arr)
                                                            ->where('status','=',6) //6参团成功
                                                            ->sum('num');
                        
                        //更新成团售出库存字段
                        $fightgroup_stock_up_data = [
                            'sell_stock' => $val['sell_stock'] + $join_all_buy_nums //增加成团售出库存
                        ];
                                                            
                        FightgroupStock::update_data($val['id'], $merchant_id, $fightgroup_stock_up_data);
                        
                    }
                }        
                                
            }
            
            
            
            
        }
        
        
        
        
        return ['errcode' => 0,'errmsg' => 'ok'];
    }
    
    
    /**
     * 参与拼团活动的会员，退款、还库存处理（开团或参团支付成功相关处理调用）
     * @author wangshen@dodoca.com
     * @cdate 2017-9-20
     * 
     * @param array  $fightgroup_join_info  拼团参与表信息
     * @param array  $fightgroup_item_info  拼团子表信息
     * @param int    $type  1：非进行中（活动、阶梯、团）超卖，2：已成团超卖，3：库存超卖
     * @param string $orderrefund_data  若超卖，则有超卖退单返回数据，非超卖则为空
     * 
     */
    public function joinRefund($fightgroup_join_info,$fightgroup_item_info,$type,$orderrefund_data){
        
        $merchant_id = $fightgroup_join_info['merchant_id'];
        
        //类型1、2执行
        if($type == 1 || $type == 2){
            //归还库存（调用统一还库存方法）
            //商品规格id
            $goods_spec_id = isset($fightgroup_item_info['spec_id']) ? $fightgroup_item_info['spec_id'] : 0;
            
            $goods_inc_stock_data = [
                'merchant_id' => $merchant_id,  //商户id
                'stock_num' => $fightgroup_join_info['num'],    //加库存量
                'goods_id' => $fightgroup_item_info['goods_id'],    //商品id
                'goods_spec_id' => $goods_spec_id,  //规格id 没有传0
                'activity' => 'tuan'    //商品所需操作库存类型  普通商品：可不传  拼团：tuan
            ];
            
            //商品服务类
            $goods_service_obj = new GoodsService();
            $goods_service_obj->incStock($goods_inc_stock_data);
            
            
            //减销量（调用减销量方法）
            $goods_des_csale_data = [
                'merchant_id' => $merchant_id,  //商户id
                'stock_num' => $fightgroup_join_info['num'],    //减销量数量
                'goods_id' => $fightgroup_item_info['goods_id'],    //商品id
                'goods_spec_id' => $goods_spec_id  //规格id 没有传0
            ];
            $goods_service_obj->desCsale($goods_des_csale_data);
        }
        
        //插拼团退款申请表
        //退款总额
        $total_amount = OrderInfo::where('id', $fightgroup_join_info['order_id'])->value('amount');
        
        //退款原因
        if($type == 1){
            
            $reason = '拼团，非进行中（活动、阶梯、团）超卖，自动取消';
        }elseif($type == 2){
            
            $reason = '拼团，已成团超卖，自动取消';
        }elseif($type == 3){
            
            $reason = '库存超卖，自动取消';
        }
        
        //备注
        if($type == 1 || $type == 2){
            
            $memo = '提交中';
        }elseif($type == 3){
            
            //超卖退单返回数据json格式
            $memo = $orderrefund_data;
        }
        
        
        //抵扣积分数
        $credit = OrderUmp::where('order_id', $fightgroup_join_info['order_id'])
                            ->where('ump_type', 3)  //3:积分抵扣
                            ->value('credit');
        
        $credit = isset($credit) ? $credit : 0;
        
        $fightgroup_refund_data = [
            'merchant_id'   => $merchant_id,                            //商户id
            'fightgroup_id' => $fightgroup_join_info['fightgroup_id'],  //拼团活动id
            'launch_id'     => $fightgroup_join_info['launch_id'],      //拼团发起表id
            'order_id'      => $fightgroup_join_info['order_id'],       //订单id
            'member_id'     => $fightgroup_join_info['member_id'],      //会员id
            'order_sn'      => $fightgroup_join_info['order_sn'],       //订单号
            'nickname'      => $fightgroup_join_info['nickname'],       //昵称
            'pay_type'      => 1,                                       //1微信支付
            'total_amount'  => $total_amount,                           //退款总额
            'reason'        => $reason,                                 //退款原因
            'memo'          => $memo,                                   //备注
            'credit'        => $credit,                                 //积分抵扣
            'status'        => 0                                        //0申请退款中
        ];
        
        $fightgroup_refund_rs = FightgroupRefund::insert_data($fightgroup_refund_data);
        
        //类型1、2执行
        if($type == 1 || $type == 2){
            if($fightgroup_refund_rs){
                
                //更新order表状态为：已关闭 ,商家关闭
                $odata = [
                    'status' =>	ORDER_MERCHANT_CANCEL,
                    'explain' => $reason
                ];
                OrderInfo::update_data($fightgroup_join_info['order_id'],$merchant_id,$odata);
                
                //调用退款
                //下单支付服务类
                $buy_service_obj = new BuyService();
                $buy_service_obj_rs = $buy_service_obj->orderrefund(['merchant_id' => $merchant_id,'order_id' => $fightgroup_join_info['order_id'],'apply_type' => 2]);
                
                //记录返回值
                FightgroupRefund::update_data($fightgroup_refund_rs, $merchant_id, ['memo' => json_encode($buy_service_obj_rs)]);
            }
        }
        
        
    }
    
    
    /**
     * 查询拼团订单能否退款或发货
     * @author wangshen@dodoca.com
     * @cdate 2017-9-28
     *
     * @param int $order_id 订单id
     *
     */
    public function fightgroupJoinOrder($order_id){
        
        //缺少订单数据
        if(empty($order_id)){
            return ['errcode' => 60019,'errmsg' => '缺少订单数据'];
        }
        
        
        //查询拼团参与表信息
        $fightgroup_join_info = FightgroupJoin::select('id','status')
                                                ->where('order_id','=',$order_id)
                                                ->first();
        
        if(!$fightgroup_join_info){
            return ['errcode' => 60020,'errmsg' => '未查询到拼团参与信息'];
        }
        
        
        if($fightgroup_join_info['status'] == 6){
            $type = 1;//参团成功可退款或发货
        }else{
            $type = 0;//未参团成功不可退款或发货
        }
        
        return ['errcode' => 0,'errmsg' => 'ok', 'data' => ['type' => $type]];
    }
    
    
    
    /**
     * 某个团中待付款的人，订单取消操作
     * @author wangshen@dodoca.com
     * @cdate 2017-9-30
     * 
     * @param int $merchant_id  商户id
     * @param int $fightgroup_launch_id  拼团发起表id
     * @param int $type  类型：1商户后台手动结束拼团阶梯，2跑脚本调用（到时间未成团退款），3参团成功后成团处理调用
     * 
     */
    public function launchJoinCancel($merchant_id,$fightgroup_launch_id,$type){
        
        if($type == 1){
            
            $explain = '拼团，手动结束拼团阶梯，待付款订单取消';
        }elseif($type == 2){
            
            $explain = '拼团，团时间结束，待付款订单取消';
        }elseif($type == 3){
            
            $explain = '拼团，团满员成团，待付款订单取消';
        }
        
        
        //查询该团的待付款的人
        $fightgroup_join_list = FightgroupJoin::select('id','order_id')
                                                ->where('merchant_id','=',$merchant_id)
                                                ->where('launch_id','=',$fightgroup_launch_id)
                                                ->where('status','=',0) //0待支付
                                                ->get();
        $fightgroup_join_list = json_decode($fightgroup_join_list,true);
        
        if(count($fightgroup_join_list) > 0){
            
            //取消订单操作
            foreach($fightgroup_join_list as $key=>$val) {
                $data = [
                    'status'	=>	ORDER_MERCHANT_CANCEL,  // 已关闭 ,商家关闭
                    'explain'	=>	$explain
                ];
                $result = OrderInfo::update_data($val['order_id'],$merchant_id,$data);
                if($result) {
                    //发送到队列
                    $job = new OrderCancel($val['order_id'],$merchant_id);
                    $this->dispatch($job);
                }
            }
            
            
        }
        
    }
    
    
    
    
    

    /**
     * 通过商品id获取拼团活动阶梯列表
     * chang
     * 20170901 12:00
     * $merchant_id  商户ID  必传参数
     * $goods_id     商品ID  必传参数
     */
    public function fightgroupLadderList($merchant_id=0,$goods_id=0)
    {

        $merchant_id = isset($merchant_id) ? intval($merchant_id) : 0;
        $goods_id    = isset($goods_id) ? intval($goods_id) : 0;

        if($goods_id < 1 || $merchant_id<1){
            return ['errcode' => 10001, 'errmsg' => '获取拼团活动阶梯列表失败，参数有误！'];exit;
        }

        $Fightgroup_Info = Fightgroup::select('id')
            ->where(['merchant_id' => $merchant_id, 'goods_id' => $goods_id, 'status' => PIN_ACTIVE])
            ->first();
        if(empty($Fightgroup_Info)) {
            return ['errcode' => 10002, 'errmsg' => '无效拼团！'];exit;
        }

        $ladderInfo = FightgroupLadder::select('id', 'status', 'cpeoples')
            ->where(['merchant_id' =>$merchant_id, 'fightgroup_id' => $Fightgroup_Info['id'], 'status' => 1])
            ->get();

        if(empty($ladderInfo->toArray())) {
            return ['errcode' => 10003, 'errmsg' => '无效拼团！'];exit;
        }

        foreach($ladderInfo as $k=>$v){
            $data[$k]['fightgroup_id'] = $Fightgroup_Info['id'];
            $data[$k]['ladder_id'] = $v['id'];
            $data[$k]['cpeoples'] = $v['cpeoples'];
            $itemInfo = FightgroupItem::select('id', 'ladder_id', 'ladder_price')
                ->where(['merchant_id' => $merchant_id, 'fightgroup_id' => $Fightgroup_Info['id'], 'ladder_id' => $v['id']])
                ->orderBy('ladder_price', 'ASC')
                ->first();
            if(empty($itemInfo->toArray())) {
                return ['errcode' => 10004, 'errmsg' => '无效拼团！'];exit;
            }
            $data[$k]['ladder_price'] =$itemInfo['ladder_price'];

        }

        return ['errcode' => 0, 'errmsg' => '获取拼团活动阶梯列表成功', 'data' => $data];
    }

    /**
     * 单个拼团退款（手动结束成团/拼团活动到时间的未成团）
     * changzhixian
     * 201709010
     * $launchId     拼团发起表id
     * $type_call    类型 1 商户后台手动结束拼团阶梯； 2 跑脚本调用（到时间未成团退款）
     * $merchant_id  商户id
     */
    public function launchRefund($launchId=0,$type_call=0,$merchant_id){

        $launchId = $launchId > 0 ? $launchId : 0;
        $type_call = $type_call > 0 ? $type_call : 0;
        $merchant_id = $merchant_id > 0 ? $merchant_id : 0;
        if ($launchId < 1 || $type_call< 1 || $merchant_id < 1) {
            return ['errcode' => 10001, 'errmsg' => "缺少必传参数"];
            exit;
        }

        //单个拼团退款
        $refundJob = new FightgroupRefundr($launchId,$type_call);
        $this->dispatch($refundJob);
        
        
        //当前团其他待支付的人做订单取消操作
        $this->launchJoinCancel($merchant_id, $launchId, $type_call);
        


        return ['errcode' => 0, 'errmsg' => '单个拼团退款成功，还库存成功'];
    }

    /**
     * 验证当前商品价格是否小于拼团活动所设置的各规格最高价
     * changzhixian
     * 201709010
     * $merchant_id       商户id
     * $goods_id          商品id
     * $fightgroup_id     拼团活动id
     */
    public function maxSpecLadderPrice($merchant_id=0,$goods_id=0,$fightgroup_id=0){

        $merchant_id   = $merchant_id > 0 ? $merchant_id : 0;
        $goods_id      = $goods_id > 0 ? $goods_id : 0;
        $fightgroup_id = $fightgroup_id > 0 ? $fightgroup_id : 0;
        if ($merchant_id < 1 || $goods_id< 1 || $fightgroup_id< 1 ) {
            return ['errcode' => 10001, 'errmsg' => "缺少必传参数"];
            exit;
        }

        //拼团活动所设置的各规格最高价
        $spec_id_list = FightgroupItem::select('spec_id')
            ->where(['merchant_id' => $merchant_id, 'fightgroup_id' => $fightgroup_id, 'goods_id' => $goods_id])
            ->groupBy('spec_id')->get()->toArray();
        foreach ($spec_id_list as $v){
            $data[$v['spec_id']] = FightgroupItem::where(['merchant_id' => $merchant_id, 'fightgroup_id' => $fightgroup_id, 'goods_id' => $goods_id,'spec_id'=>$v['spec_id']])->max('ladder_price');
        }

        $data = $data ? $data : [];
        return ['errcode' => 0, 'errmsg' => '获取拼团活动各规格最高价成功','goods_sepec'=>$data];
    }

    /**
     * 接收一组拼团活动id返回拼团活动相关信息
     * 通过拼团活动ID返回拼团活动相关信息和已团商品数量、最近参团头像、参团人数
     * chang
     * 20171208 12:00
     * $merchant_id     商户ID  必传参数
     * $fightgroup_id   活动ID  必传参数，如果是多个ID，需要用英文逗号隔开，如：2,3,5,6
     */
    public function fightgroupInfo($merchant_id=0,$fightgroup_id=0)
    {

        $merchant_id = isset($merchant_id) ? intval($merchant_id) : 0;
        $fightgroup_id = isset($fightgroup_id) ? $fightgroup_id : 0;
        if($fightgroup_id == 0 || $merchant_id < 1){
            return ['errcode' => 10001, 'errmsg' => '获取拼团活动相关信息失败，参数有误！'];exit;
        }
        $Fightgroup_list = Fightgroup::select('id','goods_id','goods_img','goods_title','start_time','status')
            ->where('merchant_id',"=", $merchant_id)
            ->whereIn('id', $fightgroup_id)
            ->orderByRaw(\DB::raw("FIND_IN_SET(id, '" . implode(',', $fightgroup_id) . "'" . ')'))
            ->get()->toArray();
        if(empty($Fightgroup_list)) {
            return ['errcode' => 10002, 'errmsg' => '无相关数据！'];exit;
        }

        foreach($Fightgroup_list as $k=>$v){
            $data[$k] = $v;

            $ladder_price = FightgroupItem::select('ladder_price')
                ->where(['merchant_id' => $merchant_id, 'fightgroup_id' => $v['id']])
                ->min('ladder_price');
            $data[$k]['ladder_price'] = $ladder_price > 0 ? $ladder_price : 0 ;//拼团规格最低价

            $sell_num = FightgroupItem::select('ladder_price')
                ->where(['merchant_id' => $merchant_id, 'fightgroup_id' => $v['id']])
                ->sum('sell_num');
            $data[$k]['sell_num'] = $sell_num > 0 ? $sell_num : 0 ;//已售件数

            $join_count = FightgroupJoin::select('id')
                ->where(['merchant_id' => $merchant_id, 'fightgroup_id' => $v['id']])
                ->where('pay_time', '<>', '0000-00-00 00:00:00')
                ->count();
            $data[$k]['join_count'] = $join_count > 0 ? $join_count : 0 ;//已售件数

            $avatar = FightgroupJoin::select('id','avatar')
                ->where(['merchant_id' => $merchant_id, 'fightgroup_id' => $v['id']])
                ->where('pay_time', '<>', '0000-00-00 00:00:00')
                ->orderBy('id', 'desc')
                ->limit(6)->lists('avatar');
            $data[$k]['avatar'] = $avatar  ? $avatar : "" ;//已售头像
        }

        return ['errcode' => 0, 'errmsg' => '获取拼团活动相关信息成功', 'data' => $data];
    }
    

}
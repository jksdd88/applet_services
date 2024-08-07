<?php
/**
 * 拼团控制器测试
 * 
 */
namespace App\Http\Controllers\Weapp\Fightgroup;

use Illuminate\Http\Request;
use App\Http\Requests;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Facades\Member;

use App\Models\Fightgroup;
use App\Models\FightgroupItem;
use App\Models\FightgroupJoin;
use App\Models\FightgroupLadder;
use App\Models\FightgroupLaunch;
use App\Models\FightgroupRefund;
use App\Models\FightgroupStock;

use App\Models\GoodsComponent;
use App\Models\Goods;
use App\Models\OrderInfo;
use App\Models\Member as MemberModel;

use App\Services\FightgroupService;
use App\Services\BuyService;
use App\Services\OperateRewardService;
use App\Services\BargainService;

use Illuminate\Support\Facades\Cache;
use App\Utils\CacheKey;
use App\Services\MerchantService;
use App\Services\VirtualGoodsService;
use App\Utils\CommonApi;


class FightgrouptestController extends Controller {

    public function __construct(FightgroupService $fightgroupService,BuyService $buyService,OperateRewardService $OperateRewardService){
        
        //拼团服务类
        $this->fightgroupService = $fightgroupService;
        
        //下单支付服务类
        $this->buyService = $buyService;

        //营销活动服务类
        $this->OperateRewardService = $OperateRewardService;
        
        $this->member_id = Member::id();//会员id
        $this->merchant_id = Member::merchant_id();//商户id
        
    }


    /**
     * 测试service用
     * chang
     */
    public function getfightgrouptest(Request $request)
    {

        //$member_id = Member::id;
        //$merchant_id = Member::merchant_id;
        $goods_id = 2228;
        $member_id = 1;
        $merchant_id = 1;
        $ladderId = 121212;
        $Fightgroup_id = [2,3,4,5,6];

      $BargainService = new BargainService;
        $data = $BargainService->bargainInfo(1,$goods_id);

        return $data;

        dd($data);die;
        $launchs = FightgroupLaunch::where('ladder_id',$ladderId)->whereIn('status',[PIN_INIT_ACTIVE])->get()->toArray();
        if($launchs){
            //2、修改拼团发起表状态
            //FightgroupLaunch::where('ladder_id',$ladderId)->whereIn('status',[PIN_INIT_SUBMIT,PIN_INIT_ACTIVE])->update(array('status'=>PIN_INIT_FAIL_MERCHANT));
            //3、修改拼团参与人表状态并执行退款操作
            foreach($launchs as $k=>$v) {
                $data = $this->fightgroupService->launchRefund($v['id'],"1");//商户后台手动结束
            }
        }


        return isset($data) ? $data : "1";
    }
    
    
    /**
     * 测试
     * 开团或参团支付成功相关处理（测试）
     */
    public function fightgroupbacktest(Request $request){
        
        //参数
        $params = $request->all();
        
        $type = $params['type'];
        
        if($type == 1){
            //1：开团或参团支付成功相关处理
            
            $order_data['id'] = $params['id'];
            $order_data['pay_time'] = date('Y-m-d H:i:s');
            $order_data['is_oversold'] = 0; //是否超卖（1-超卖，0-正常）
            
            $rs = $this->fightgroupService->fightgroupPayBack($order_data);
            
            s($rs);
            
        }elseif($type == 2){
            //2：订单取消相关处理（超时未支付自动取消或会员主动取消）
            
            $order_data['id'] = $params['id'];
            
            
            $rs = $this->fightgroupService->fightgroupJoinCancel($order_data);
            
            s($rs);
            
        }elseif($type == 3){
            //3：查询缓存里拼团活动库存
            
            //根据（$fightgroup_stock_id  拼团库存表主键id）和（$merchant_id  商户id）获取缓存库存
            
            $merchant_id = $params['merchant_id'];
            
            $fightgroup_stock_id = $params['fightgroup_stock_id'];
            
            //键名
            $key = CacheKey::get_fightgroup_stock_key($fightgroup_stock_id, $merchant_id);
            
            if(Cache::has($key)){
                //根据键名获取键值
                $stock = Cache::get($key);
                
                s('商家id：'.$merchant_id.'，拼团库存表id：'.$fightgroup_stock_id.'，缓存中的库存为：'.$stock);
            }else{
                
                s('商家id：'.$merchant_id.'，拼团库存表id：'.$fightgroup_stock_id.'，缓存中的库存为：无缓存');
            }
            
            //清除缓存
            if(isset($params['clear_type'])){
                if($params['clear_type'] == 1){
                    Cache::forget($key);
                }
            }
            
            
        }elseif($type == 4){
            //改fightgroup_stock表数据（后台数据有问题，临时改数据）
            
            $merchant_id = $params['merchant_id'];
            
            $fightgroup_stock_id = $params['fightgroup_stock_id'];
            
            $rs = FightgroupStock::where('id','=',$fightgroup_stock_id)->update(['merchant_id' => $merchant_id]);
            
            if($rs){
                s('ok');
            }else{
                s('no');
            }
            
        }elseif($type == 5){
            //5：查询缓存里某个团可用名额数量
            
            //根据（$fightgroup_launch_id  拼团发起表主键id）和（$merchant_id  商户id）获取缓存里某个团可用名额数量
            
            $merchant_id = $params['merchant_id'];
            
            $fightgroup_launch_id = $params['fightgroup_launch_id'];
            
            //键名
            $key = CacheKey::get_fightgroup_launch_numsless_key($fightgroup_launch_id, $merchant_id);
            
            if(Cache::has($key)){
                //根据键名获取键值
                $nums_less = Cache::get($key);
                
                s('商家id：'.$merchant_id.'，拼团发起表id：'.$fightgroup_launch_id.'，缓存中的可用名额为：'.$nums_less);
            }else{
                
                s('商家id：'.$merchant_id.'，拼团发起表id：'.$fightgroup_launch_id.'，缓存中的可用名额为：无缓存');
            }
            
            //清除缓存
            if(isset($params['clear_type'])){
                if($params['clear_type'] == 1){
                    Cache::forget($key);
                }
            }
            
            
        }elseif($type == 6){
            //改fightgroup_launch表数据
            
            $merchant_id = $params['merchant_id'];
            
            $fightgroup_launch_id = $params['fightgroup_launch_id'];
            
            $end_time = $params['end_time'];
            
            $rs = FightgroupLaunch::update_data($fightgroup_launch_id, $merchant_id, ['end_time' => $end_time]);
            
            if($rs){
                s('ok');
            }else{
                s('no');
            }
            
        }elseif($type == 7){
            
            //获取商户版本信息
            $merchant_id = $params['merchant_id'];
            $info = MerchantService::getMerchantVersion($merchant_id);
            
            
            s($info);
            
        }elseif($type == 8){
            
            $merchant_id = $params['merchant_id'];
            
            $member_id = $params['member_id'];
            
            $mobile = $params['mobile'];
            
            $captcha_key = CacheKey::get_register_member_captcha_key($merchant_id, $member_id);
            $captcha_code = Cache::get($captcha_key);
            
            
            $key = CacheKey::get_register_member_sms_by_mobile_key($mobile, $merchant_id);
            $sms_data = Cache::get($key);
            
            s($captcha_code);
            s($sms_data);
            
        }elseif($type==9){
            
            //虚拟商品生成核销码
            
            $merchant_id = $params['merchant_id'];
            
            $order_id = $params['order_id'];
            
            
            $order = OrderInfo::get_data_by_id($order_id,$merchant_id,'*');
            
            $order['is_oversold'] = 0;
            
            $virtualGoodsService = new VirtualGoodsService();
            
            try{
                $virtualGoodsService->createVirtualHexiao($order);
            }catch (\Exception $e) {
                //记录异常
                $except = [
                    'activity_id'	=>	$order['id'],
                    'data_type'		=>	'order_pay_success_job_virtual',
                    'content'		=>	'虚拟商品订单核销码生成失败，'.$e->getMessage().','.json_encode($order,JSON_UNESCAPED_UNICODE),
                ];
                CommonApi::errlog($except);
            }
            
            
        }elseif($type==10){
            
            //虚拟商品核销码标记退款完成
            
            $merchant_id = $params['merchant_id'];
            
            $order_id = $params['order_id'];
            
            $refund_quantity = $params['refund_quantity'];
            
            $order = OrderInfo::get_data_by_id($order_id,$merchant_id,'*');
            
            
            $virtualGoodsService = new VirtualGoodsService();
            
            $rs = $virtualGoodsService->changeHexiao($order, $refund_quantity);
            
            
            s($rs);
        }
        
        
        
        
    }
    
    
    
}

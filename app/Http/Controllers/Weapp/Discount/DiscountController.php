<?php
/**
 * 满减控制器
 * 
 */
namespace App\Http\Controllers\Weapp\Discount;

use Illuminate\Http\Request;
use App\Http\Requests;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use App\Facades\Member;

use App\Models\Discount;
use App\Models\DiscountItem;
use App\Models\DiscountJoin;
use App\Models\DiscountLadder;
use App\Models\DiscountActivity;
use App\Models\DiscountRefund;
use App\Models\DiscountStock;

use App\Models\GoodsComponent;
use App\Models\Goods;

use App\Services\DiscountService;



class DiscountController extends Controller {

    public function __construct(DiscountService $discountService){
        //满减服务类
        $this->discountService = $discountService;
        
    }

    /**
     * systybj 满减：活动中某件商品详情的满减活动信息
     * songyongshang
     * 20170901 12:00
     * $merchant_id  商户Id
     * $status  活动状态
     * @return \Illuminate\Http\Response
     */
    public function getgoodsDiscountInfo(Request $request)
    {
        $request['member_id']   = Member::id();
        $request['merchant_id'] = Member::merchant_id();
        //dd($request);
        return $this->discountService->getgoodsDiscountInfo($request['merchant_id'],$request['goods_id']);
    }

    /**
     * systybj 满减：购物车详情
     * songyongshang
     * 20170901 12:00
     * $merchant_id  商户Id
     * $status  活动状态
     * @return \Illuminate\Http\Response
     */
    public function getCartGoodsDiscountInfo(Request $request)
    {
        $request['member_id']   = Member::id();
        $request['merchant_id'] = Member::merchant_id();
        //dd($request);
        return $this->discountService->getCartGoodsDiscountInfo($request['merchant_id'],$request['member_id'],$request['discount_activity_id']);
    }

    /**
     * systybj 满减：活动列表
     * songyongshang
     * 20170901 12:00
     * $merchant_id  商户Id
     * $status  活动状态
     * @return \Illuminate\Http\Response
     */
    public function getDiscountActivityList(Request $request)
    {
        //dd($request);
        $request['member_id']   = Member::id();
        $request['merchant_id'] = Member::merchant_id();
        //dd($request);
        return $this->discountService->getDiscountActivityList($request);
    }

    /**
     * systybj 满减：某个活动详情
     * songyongshang
     * 20170901 12:00
     * $merchant_id  商户Id
     * $status  活动状态
     * @return \Illuminate\Http\Response
     */
    public function getDiscountActivity(Request $request)
    {
        //dd($request);
        $request['member_id']   = Member::id();
        $request['merchant_id'] = Member::merchant_id();
        
        return $this->discountService->getDiscountActivity($request);
    }
    
    /**
     * systybj 满减：订单中商品的金额
     * songyongshang
     * 20170901 12:00
     * $merchant_id  商户Id
     * $status  活动状态
     * @return \Illuminate\Http\Response
     */
    public function getOrderGoodsDiscountMoney(Request $request)
    {
        
        $request = [
            'member_id'=>Member::id(),
            'merchant_id'=>Member::merchant_id(),
            'goods'	 =>	array(	//订单商品
                0	=>	array(
                    'goods_id'	=>	283,	//商品id
                    'spec_id'	=>	0,	//商品规格id
                    'sum'	 =>	1,	//购买数量
                    'price'	 =>	20,	//价格（会员折扣后）
                ),
//                 1	=>	array(
//                     'goods_id'	=>	1589,	//商品id
//                     'spec_id'	=>	243,	//商品规格id
//                     'sum'	 =>	1,	//购买数量
//                     'price'	 =>	9.99,	//价格（会员折扣后）
//                 ),
            )
        ];
        return $this->discountService->getOrderGoodsDiscountMoney($request);
    }

    
}

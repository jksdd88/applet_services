<?php
namespace App\Http\Controllers\Weapp\Bargain;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Bargain;
use App\Models\BargainJoin;
use App\Models\BargainLaunch;
use App\Models\Goods;
use App\Models\GoodsSpec;
use App\Models\Member as MemberModel;
use App\Models\OrderInfo;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Services\BuyService;
use App\Facades\Member;

class BargainController extends Controller
{
    public function __construct()
    {

        if (app()->isLocal()) {
            $this->merchant_id =1; //Member::merchant_id();
            $this->member_id =1; // Member::id();
        } else {
            $this->merchant_id =Member::merchant_id();
            $this->member_id =Member::id();
        }
    }


    /**
     * 我的砍价列表
     *
     * Author:zhangyu1@dodoca.com
     * date:2018-03-31
     */
    public function getBargainList(Request $request){
        $query = BargainLaunch::select('bargain_launch.id','bargain_launch.merchant_id','bargain_launch.member_id','bargain_launch.bargain_id','bargain_launch.is_sku',
            'bargain_launch.original_price','bargain_launch.bargain_price','bargain_launch.status','bargain_launch.order_id','bargain_launch.order_sn','bargain_launch.success_at',
            'bargain.goods_title','bargain.goods_img','bargain.goods_id','bargain.minimum')->leftJoin('bargain','bargain.id','=','bargain_launch.bargain_id');

        $query->where('bargain.is_delete','=',1);

        $result = $query->where(array('bargain_launch.merchant_id'=>$this->merchant_id,'bargain_launch.member_id'=>$this->member_id))->orderBy('bargain_launch.created_time','desc')->get();

        if(!empty($result)){

            foreach ($result as $key=>$value){

                $action_status = '';

                switch ($value['status'])      //砍价发起活动状态
                {
                    case 1:$action_status='砍价中';break;

                    case 2:$action_status='已完成';break;

                    case 3:$action_status='已结束';break;
                }

                $done_bargain = BargainJoin::where(array('bargain_id'=>$value['bargain_id'],'launch_id'=>$value['id']))
                    ->where('status','=',1)
                    ->sum('bargain_price');            //已砍掉的价格

                if(!$done_bargain){   //若砍掉价格为空

                    $done_bargain = 0;
                }

                $result[$key]['done_bargain'] = $done_bargain;

                if($value['status'] ==1 || $value['status'] ==3 || $value['status'] ==2){     //砍价中或砍价已结束(超时)或者砍价成功未下单

                    $undone_bargain = $value['original_price'] - $done_bargain - $value['minimum'];    //未砍掉的价格

                    $result[$key]['undone_bargain'] = round($undone_bargain,2);

                    $total_money = $value['original_price'] - $done_bargain;             //当前商品价格

                    $result[$key]['total_money'] = round($total_money,2);

                }

                if($value['status'] == 2 && !empty($value['order_sn'])){    //砍价后下单

                    $action_status = '已下单';

                    $orderInfo = OrderInfo::select('shipment_fee','pay_status','is_finish')->where(array('merchant_id'=>$value['merchant_id'],'member_id'=>$value['member_id'],'id'=>$value['order_id']))->first();

                    if(!empty($orderInfo)){

                        $total_money = $value['original_price'] - $done_bargain + $orderInfo['shipment_fee'];

                        $result[$key]['shipment_fee'] = $orderInfo['shipment_fee'];

                        $result[$key]['order_status'] = $orderInfo['pay_status'];

                    }else{

                        $total_money = $value['original_price'] - $done_bargain;
                    }

                    $result[$key]['total_money'] = round($total_money,2);

                }

                $result[$key]['action_status'] = $action_status;

                //商品规格详情
                $goods_spec = GoodsSpec::select('*')->where(array('merchant_id'=>$value['merchant_id'],'goods_id'=>$value['goods_id']))->first();

                $result[$key]['props_str'] = isset($goods_spec['props_str']) ? $goods_spec['props_str'] : "";//规格信息

            }

            return Response::json(['errcode' => 0, 'errmsg' => '查询成功', 'data' => $result]);

        }else{

            return Response::json(['errcode' => 0, 'errmsg' => '暂无数据']);
        }

    }






    /***
     * 获取砍价是否发起

     * @return \Illuminate\Http\Response*/

    public function getBargainJoinStatus(Request $request)
    {
        //参数
        $params['merchant_id'] = $request['merchant_id'];
        $params['bargain_id'] = $request['bargain_id'];
        $rules = [
            'bargain_id' => 'required|integer|min:1',
            'merchant_id' => 'required'
        ];
        $messages = [
            'bargain_id.required' => '活动ID是必传的',
            'bargain_id.integer' => '非法的活动ID',
            'bargain_id.min' => '非法的活动ID',
            'merchant_id.required' => '商户ID是必传的'
        ];
        $validator = Validator::make($params, $rules, $messages);
        if ($validator->fails()) {
            $error = $validator->errors()->first();
            return ['errcode' => 10001, 'errmsg' => $error];
            exit;
        }

        $bargain_id = isset($params['bargain_id']) ? $params['bargain_id'] : 0;//活动id
        $merchant_id = $this->merchant_id;
        $member_id = $this->member_id;
        $Bargain = Bargain::get_data_by_id($bargain_id,$merchant_id);
        if(empty($Bargain)) {
            return ['errcode' => 10004, 'errmsg' => '无效砍价！'];exit;
        }
        $bargain_launch_status =1;
        $data['bargain_launch_id']   = 0;
        // $bargain_launch = BargainLaunch::where(['merchant_id' => $merchant_id, 'member_id' => $member_id,'bargain_id'=>$bargain_id])
        //     ->first();
        // if (!empty($bargain_launch)){
        //     if(empty($bargain_launch['order_id']) && empty($bargain_launch['order_sn'])){
        //         $bargain_launch_status = 2;//此活动已经发起砍价 有未完成的砍价
        //         $data['bargain_launch_id']   = $bargain_launch['id'];
        //     }else{
        //         //查询用户砍了几次
        //         $count = BargainLaunch::where(['merchant_id' => $merchant_id, 'member_id' => $member_id,'bargain_id'=>$bargain_id])
        //             ->count();
        //         if($Bargain['limit_number']!=0 && $Bargain['limit_number']<= $count){
        //             $bargain_launch_status = 3;//此活动已经发起砍价上限
        //         }
        //     }
        //     //$bargain_launch_status = 2;//此活动已经发起砍价 有未完成的砍价     
        // }
        $bargain_launch = BargainLaunch::where(['merchant_id' => $merchant_id, 'member_id' => $member_id,'bargain_id'=>$bargain_id,'order_id'=>0,'order_sn'=>''])->first();
        if (!empty($bargain_launch)){
            $bargain_launch_status = 2;//此活动已经发起砍价 有未完成的砍价
            $data['bargain_launch_id']   = $bargain_launch['id'];
        }
        //查询用户砍了几次
        $count = BargainLaunch::where(['merchant_id' => $merchant_id, 'member_id' => $member_id,'bargain_id'=>$bargain_id])
            ->count();
        if($Bargain['limit_number']!=0 && $Bargain['limit_number']<= $count){
            $bargain_launch_status = 3;//此活动已经发起砍价上限
        }
        //$bargain_launch_status = 2;//此活动已经发起砍价 有未完成的砍价     
        $data['bargain_launch_status']   = $bargain_launch_status;//是否发起砍价 1：没有发起砍价；2：已发起

        return ['errcode' => 0, 'errmsg' => "获取成功", 'data' => $data];
    }

    /***
     * 获取砍价活动详情

     * @return \Illuminate\Http\Response*/

    public function getBargainInfo(Request $request)
    {
        //参数
        $params['merchant_id'] = $request['merchant_id'];
        $params['bargain_id'] = $request['bargain_id'];
        $params['bargain_launch_id'] = $request['bargain_launch_id'];
        $params['goods_spec_id'] = $request['goods_spec_id'];
        $rules = [
            'bargain_id' => 'required|integer|min:1',
            'merchant_id' => 'required',
            'goods_spec_id' => 'required|integer|min:0'
        ];
        $messages = [
            'bargain_id.required' => '活动ID是必传的',
            'bargain_id.integer' => '非法的活动ID',
            'bargain_id.min' => '非法的活动ID',
            'merchant_id.required' => '商户ID是必传的',
            'bargain_launch_id.required' => '活动发起表ID是必传的',
            'bargain_launch_id.integer' => '非法的发起表ID',
            'bargain_launch_id.min' => '非法的发起表ID',
            'goods_spec_id.required' => '商品规格ID是必传的',
            'goods_spec_id.integer' => '非法的商品规格ID',
            'goods_spec_id.min' => '非法的商品规格ID'
        ];
        $validator = Validator::make($params, $rules, $messages);
        if ($validator->fails()) {
            $error = $validator->errors()->first();
            return ['errcode' => 10001, 'errmsg' => $error];
            exit;
        }

        $bargain_id = isset($params['bargain_id']) ? $params['bargain_id'] : 0;//活动id

        $launch_id = isset($params['bargain_launch_id']) ? $params['bargain_launch_id'] : 0;//发起表id
        $goods_spec_id = isset($params['goods_spec_id']) ? $params['goods_spec_id'] : 0;//规格id
        $merchant_id = $this->merchant_id;
        $member_id = $this->member_id;
        $Bargain = Bargain::get_data_by_id($bargain_id,$merchant_id);
        if(empty($Bargain)) {
            return ['errcode' => 10004, 'errmsg' => '无效砍价！'];exit;
        }else{
            $bargain_info = $Bargain->toArray();
            $return = $this->doBargainTime($bargain_info['start_time'], $bargain_info['end_time']);
            if(($return == 1) && ($bargain_info['status']==0)){
                $bargain_info['status'] = 1;//重置活动状态为进行中
            }
        }
        if(!empty($bargain_info) && isset($bargain_info['goods_id'])){
            $Goods = Goods::get_data_by_id($bargain_info['goods_id'],$merchant_id);
            if(empty($Goods) || $Goods['is_delete'] == "-1"){
                $bargain_info['is_delete'] = "-1";//商品已删除
            }
        }

        $data['bargain_info']['props_str']   = "";//规格信息
        $bargain_info['props_str'] = "";




        $bargain_join = BargainJoin::where(['merchant_id' => $merchant_id, 'launch_id' => $launch_id,'bargain_id'=>$bargain_id])
            ->orderBy('id', 'DESC')
            ->take(15)
            ->get();
        $bargain_price_join = "0.00";
        $bargain_join_bargain_price = BargainJoin::where(['merchant_id' => $merchant_id, 'launch_id' => $launch_id,'bargain_id'=>$bargain_id])
                        ->sum('bargain_price');
        if($bargain_join_bargain_price > 0 ){
            $bargain_price_join = $bargain_join_bargain_price;
        }
        $bargain_launch = [];
        $bargain_launch_join = 1;
        $bargain_launch_status = 1;
        $bargain_launch_status_id = 0;
        $bargain_launch_status_member = 0;
        if($launch_id > 0){//通过分享链接进入

            $bargain_launch = BargainLaunch::where(['merchant_id' => $merchant_id, 'id' => $launch_id,'bargain_id'=>$bargain_id])
                ->first();
            $bargain_launch_status_member = $bargain_launch['member_id'] == $member_id ? 1 : 2;

            $bargain_launch_join_info = BargainJoin::select()
                ->where(['merchant_id' => $merchant_id, 'member_id' => $member_id,'launch_id'=>$launch_id])
                ->first();
            if (!empty($bargain_launch_join_info) ){
                $bargain_launch_join = 2;//此活动已经帮好友砍价过
            }
        }else{
            $bargain_launch = BargainLaunch::where(['merchant_id' => $merchant_id, 'member_id' => $member_id,'bargain_id'=>$bargain_id,'order_id'=>0,'order_sn'=>''])->first();
        }

        //砍价发起之后，若是多规格商品，重置显示商品原价
        if(isset($bargain_launch['goods_spec_id']) && ($bargain_launch['goods_spec_id'] >0)){
            $goods_spec_id = $bargain_launch['goods_spec_id'];
        }
        if($goods_spec_id > 0){
            $Goods_spec = GoodsSpec::get_data_by_id($goods_spec_id,$merchant_id);
            $bargain_info['props_str'] = isset($Goods_spec['props_str']) ? $Goods_spec['props_str'] : "";//规格信息
            $bargain_info['goods_price'] = $Goods_spec['price'];//重置活动商品原价
        }

        //分享数据活动标题开始
        $share_detail_info = $bargain_info['share_activity'];
        $share_detail = $share_detail_info ? json_decode($share_detail_info, true) : [];
        if(isset($share_detail['name'])) {
            $shareDetailArr = array(
                "{{砍价活动标题}}"=>$bargain_info['title'],
                "{{商品名称}}"=>$bargain_info['goods_title'],
                "{{商品最低价}}"=>$bargain_info['minimum']
            );
            $bargain_info['share_activity'] = strtr($share_detail['name'],$shareDetailArr);
        }else{
            $bargain_info['share_activity'] = "就差你一刀了！快来帮我一起砍价";
        }
        //分享活动标题 结束

        $bargain_launch_member = BargainLaunch::where(['merchant_id' => $merchant_id, 'member_id' => $member_id,'bargain_id'=>$bargain_id,'order_id'=>0,'order_sn'=>''])->first();
        if (!empty($bargain_launch_member) && isset($bargain_launch_member['id'])){
            $bargain_launch_status = 2;//此活动自己是否已经发起砍价
            $bargain_launch_status_id = $bargain_launch_member['id'];
        }
        //商品是否上下架
        if($bargain_info['goods_id'] && $bargain_info['goods_id']>0){
            $Goods = Goods::get_data_by_id($bargain_info['goods_id'],$merchant_id);
            $bargain_info['goods_onsale'] = $Goods['onsale'];
        }
        $data['bargain_info']   = $bargain_info;//活动信息
        $data['bargain_launch'] = $bargain_launch;//砍价发起表数据
        $data['bargain_join']   = $bargain_join ? $bargain_join : "";//砍价参与表数据
        $data['bargain_price_join']   = $bargain_price_join;//已砍价格
        $data['bargain_launch_join']   = $bargain_launch_join;//是否帮好友砍价过 1：没有砍价；2：已砍价
        $data['bargain_launch_status']   = $bargain_launch_status;//此活动自己是否已经发起砍价 1：没有发起砍价；2：已发起
        $data['bargain_launch_status_id']   = $bargain_launch_status_id;//此砍价活动自己发起的活动id
        $data['bargain_launch_status_member']   = $bargain_launch_status_member;//此活动自己是否是自己发起的 0->发起表id为0；1->是；2->否
        return ['errcode' => 0, 'errmsg' => "获取砍价活动信息成功", 'data' => $data];
    }

    /***
     * 砍价
     * 帮好友砍价或者自己先砍一刀

     * @return \Illuminate\Http\Response*/

    public function bargainJoin(Request $request)
    {
        //参数
        $params['merchant_id'] = $request['merchant_id'];
        $params['bargain_id'] = $request['bargain_id'];
        $params['bargain_launch_id'] = $request['bargain_launch_id'];
        $params['goods_spec_id'] = $request['goods_spec_id'];
        $rules = [
            'bargain_id' => 'required|integer|min:1',
            'merchant_id' => 'required',
            'bargain_launch_id' => 'required|integer|min:0',
            'goods_spec_id' => 'required|integer|min:0'
        ];
        $messages = [
            'bargain_id.required' => '活动ID是必传的',
            'bargain_id.integer' => '非法的活动ID',
            'bargain_id.min' => '非法的活动ID',
            'merchant_id.required' => '商户ID是必传的',
            'bargain_launch_id.required' => '活动发起表ID是必传的',
            'bargain_launch_id.integer' => '非法的发起表ID',
            'bargain_launch_id.min' => '非法的发起表ID',
            'goods_spec_id.required' => '规格ID是必传的',
            'goods_spec_id.integer' => '非法的规格ID',
            'goods_spec_id.min' => '非法的规格ID'
        ];
        $validator = Validator::make($params, $rules, $messages);
        if ($validator->fails()) {
            $error = $validator->errors()->first();
            return ['errcode' => 10001, 'errmsg' => $error];
            exit;
        }

        $bargain_id = isset($params['bargain_id']) ? $params['bargain_id'] : 0;//活动id
        $launch_id = isset($params['bargain_launch_id']) ? $params['bargain_launch_id'] : 0;//发起表id
        $goods_spec_id = isset($params['goods_spec_id']) ? $params['goods_spec_id'] : 0;//规格id
        //return("规格id：".$goods_spec_id);die;
        $merchant_id = $this->merchant_id;
        $member_id = $this->member_id;

        if($launch_id == 0){
            $Bargain = Bargain::get_data_by_id($bargain_id,$merchant_id);
            $count = BargainLaunch::where(['merchant_id' => $merchant_id, 'member_id' => $member_id,'bargain_id'=>$bargain_id])->count();
            if($Bargain['limit_number']!=0 && $Bargain['limit_number']<= $count){
                return ['errcode' => 1002, 'errmsg' => "此砍价已达到砍价次数上限", 'data' => ""];
            }
            $bargain_launch = BargainLaunch::where(['merchant_id' => $merchant_id, 'member_id' => $member_id,'bargain_id'=>$bargain_id,'order_id'=>0,'order_sn'=>''])->first();
            if (!empty($bargain_launch) ){
                return ['errcode' => 1001, 'errmsg' => "已在参与此砍价", 'data' => $bargain_launch['id']];
            }
        }

        //调用统一砍价入口
        $do = $this->doBargain($bargain_id, $launch_id, $goods_spec_id);
        if($do['errcode'] != 0){
            return ['errcode' => $do['errcode'],'errmsg' => $do['errmsg']];
        }

        return ['errcode' => 0, 'errmsg' => "砍价成功", 'data' => $do['data']];
    }


    /**
     * 砍价操作
     *
     */
    public function doBargain($bargain_id=0,$launch_id=0,$goods_spec_id=0){

        if($bargain_id<1 || $launch_id<0 || $goods_spec_id<0){
            return ['errcode' => 1000,'errmsg' => '参数有误','data' => ""];
        }
        //商户id
        $merchant_id = $this->merchant_id;//商户id
        //会员id
        $member_id = $this->member_id;//会员id
        //会员信息
        $member_info = MemberModel::get_data_by_id($member_id, $merchant_id);
        //活动信息
        $bargain_info = Bargain::get_data_by_id($bargain_id, $merchant_id);

        if(empty($bargain_info)){
            return ['errcode' => 60020,'errmsg' => "活动不存在"];
        }
        $return = $this->doBargainTime($bargain_info['start_time'], $bargain_info['end_time']);
        if($return != 1 || ($bargain_info['status'] != 0)){
            return ['errcode' => 60019,'errmsg' => "活动状态不在进行中"];
        }

        $original_price = $bargain_info['goods_price'];
        $bargain_price_insert = $bargain_info['goods_price'];
        if($bargain_info['is_sku']==1){//多规格
            if($goods_spec_id==0){
                return ['errcode' => 60020,'errmsg' => "规格id有误"];
            }
            $Goods_spec = GoodsSpec::get_data_by_id($goods_spec_id,$merchant_id);
            if(!isset($Goods_spec['price'])){
                return ['errcode' => 60021,'errmsg' => "规格id价格有误"];
            }
            $original_price = $Goods_spec['price'];
            $bargain_price_insert = $Goods_spec['price'];
        }
        if($bargain_price_insert<$bargain_info['minimum']){//活动最低价高于商品原价
            return ['errcode' => 60022,'errmsg' => "活动最低价有误，请联系商家"];
        }

        //事务
        DB::beginTransaction();
        try{
            //砍价参与表数据--发起砍价
            if($launch_id == 0) {

                $bargain_launch_data = [
                    'merchant_id' => $merchant_id,                           //商户id
                    'member_id' => $member_id,                             //会员id
                    'bargain_id' => $bargain_id,                         //活动id
                    'nickname' => $member_info['name'],                   //昵称
                    'avatar' => $member_info['avatar'],                 //头像
                    'is_sku' => $bargain_info['is_sku'],
                    'goods_id' => $bargain_info['goods_id'],
                    'goods_spec_id' => $goods_spec_id,
                    'original_price' => $original_price,//商品原价
                    'bargain_price' => $bargain_price_insert, //砍价价格（砍价后的当前价格）
                    'status' => 1                                  //'0默认 1砍价中 2成功 3失败（超时）',
                ];

                //插入发起表数据
                $launch_id = BargainLaunch::insert_data($bargain_launch_data);//成功返回主键id

                if ($launch_id<1) {
                    throw new \Exception('砍价发起失败');
                }
            }

            //砍价
            if($launch_id > 0) {

                $bargain_launch_join = BargainJoin::where(['merchant_id' => $merchant_id, 'member_id' => $member_id,'launch_id'=>$launch_id])
                    ->first();
                if (!empty($bargain_launch_join) ){
                    throw new \Exception('只能帮好友或自己砍价一次');
                }

                //活动发起表信息
                // $bargain_launch_info = BargainLaunch::get_data_by_id($launch_id, $merchant_id,$bargain_id);
                // if ($bargain_launch_info['status'] == 2) {
                //     throw new \Exception('此砍价已至最低价');
                // }
                // $bargain_price = 0;//要砍掉的价格
                // if($bargain_info['type'] == 1){
                //     $bargain_price = $bargain_info['fixed'];
                // }elseif($bargain_info['type'] == 2 ){
                //     $bargain_price = rand($bargain_info['prine_small']*100,$bargain_info['prine_big']*100)/100;
                // }

                //     $bargain_pricebig = ($bargain_launch_info['bargain_price']*100-$bargain_info['minimum']*100)/100;//能砍的最大价格
                // if($bargain_price >= $bargain_pricebig){
                //     $bargain_price = $bargain_pricebig;
                //     $bargain_launch_updata['status'] = 2;//砍价成功，已砍至最低价
                //     $bargain_launch_updata['bargain_price'] = $bargain_info['minimum'];//砍至最低价
                // }else{
                //     $bargain_launch_updata['bargain_price'] = $bargain_launch_info['bargain_price'] - $bargain_price;//砍价后的当前价格
                // }
                //已砍金额
                $chop_amount = BargainJoin:: where(['merchant_id' => $merchant_id, 'bargain_id' => $bargain_id,'launch_id'=>$launch_id])->sum('bargain_price');
                //当前剩余金额
                $remain_price = $original_price - $chop_amount;
                if($remain_price <= $bargain_info['minimum']){
                    throw new \Exception('此砍价已至最低价');
                }
                $bargain_price = 0;//要砍掉的价格
                if($bargain_info['type'] == 1){
                    $bargain_price = $bargain_info['fixed'];
                }elseif($bargain_info['type'] == 2 ){
                    $bargain_price = rand($bargain_info['prine_small']*100,$bargain_info['prine_big']*100)/100;
                    $bargain_price = round($bargain_price,2);
                }

                //$bargain_pricebig = ($remain_price*100-$bargain_info['minimum']*100)/100;//能砍的最大价格
                $bargain_pricebig = round(($remain_price-$bargain_info['minimum']),2);
                if($bargain_price >= $bargain_pricebig){
                    $bargain_price = $bargain_pricebig;
                    $bargain_launch_updata['status'] = 2;//砍价成功，已砍至最低价
                   
                }

                $minimum = $bargain_info['minimum'];
                //更新发起表数据
                $bargain_launch_data_count = \DB::update("update bargain_launch set bargain_price = (bargain_price-$bargain_price) where id=:launch_id and merchant_id=:merchant_id and  bargain_id = :bargain_id and (bargain_price-$bargain_price)>= $minimum",[':launch_id'=>$launch_id,':merchant_id'=>$merchant_id,':bargain_id'=>$bargain_id]);
                //$bargain_launch_data_count = BargainLaunch::update_data($launch_id,$merchant_id,$bargain_id,$bargain_launch_updata);//成功返回主键id
                if ($bargain_launch_data_count<1) {
                    throw new \Exception('砍价失败');
                }
                if( isset($bargain_launch_updata['status']) && $bargain_launch_updata['status'] == 2){
                    $status_count = BargainLaunch::update_data($launch_id,$merchant_id,$bargain_id,$bargain_launch_updata);
                    if($status_count<1){
                        throw new \Exception('砍价失败');
                    }
                }

                $bargain_join_data = [
                    'merchant_id' => $merchant_id,                           //商户id
                    'member_id' => $member_id,                             //会员id
                    'bargain_id' => $bargain_id,                         //活动id 
                    'launch_id' => $launch_id,                         //活动发起表id
                    'nickname' => $member_info['name'],                   //昵称
                    'avatar' => $member_info['avatar'],                 //头像
                    'bargain_price' => $bargain_price,
                    'status' => 1,//'0:默认 1:已砍价 2:失败(已到最低价或者活动结束) ',
                    'is_captain' =>  $member_id ? 1 : 0//'0:帮好友砍价 1:砍价发起人'
                ];
                //插入参与表数据
                $bargain_join_data_id = BargainJoin::insert_data($bargain_join_data);//成功返回主键id
                if (!$bargain_join_data_id) {
                    throw new \Exception('砍价失败');
                }
                

            }
            //返回信息
            $data['bargain_price'] = (string)$bargain_price;//砍价金额
            $data['bargain_launch_id'] = $launch_id;//砍价发起表id
            DB::commit();
            return ['errcode' => 0,'errmsg' => 'ok','data' => $data];
        }catch (\Exception $e){
            DB::rollBack();
            return ['errcode' => 60018,'errmsg' => $e->getMessage()];
        }



    }

    /**
     * 验证砍价当前状态
     */
    public function doBargainTime($start_time=0,$end_time=0){

        if(strtotime($start_time) <= strtotime(date('Y-m-d H:i:s'))  && strtotime(date('Y-m-d H:i:s')) < strtotime($end_time)){
            return 1;//活动可参与，正在进行中
        }else{
            return 2;//其他活动状态
        }
    }
    /*下单*/
    public function bargainBuy(Request $request){

        //参数
        $params['merchant_id'] = $request['merchant_id'];
        $params['bargain_id'] = $request['bargain_id'];
        $params['bargain_launch_id'] = $request['bargain_launch_id'];

        $source = isset($request['source']) ? (int)$request['source'] : 0;//订单来源

        $source_id = isset($request['source_id']) ? (int)$request['source_id'] : 0;//订单来源id
        
        $rules = [
            'bargain_id' => 'required|integer|min:1',
            'merchant_id' => 'required',
            'bargain_launch_id' => 'required|integer|min:1'
        ];
        $messages = [
            'bargain_id.required' => '活动ID是必传的',
            'bargain_id.integer' => '非法的活动ID',
            'bargain_id.min' => '非法的活动ID',
            'merchant_id.required' => '商户ID是必传的',
            'bargain_launch_id.required' => '活动发起表ID是必传的',
            'bargain_launch_id.integer' => '非法的发起表ID',
            'bargain_launch_id.min' => '非法的发起表ID'
        ];
        $validator = Validator::make($params, $rules, $messages);
        if ($validator->fails()) {
            $error = $validator->errors()->first();
            return ['errcode' => 10001, 'errmsg' => $error];
            exit;
        }

        $bargain_id = isset($params['bargain_id']) ? $params['bargain_id'] : 0;//活动id
        $launch_id = isset($params['bargain_launch_id']) ? $params['bargain_launch_id'] : 0;//发起表id
        $merchant_id = $this->merchant_id;
        $member_id = $this->member_id;
        //活动信息
        $bargain_info = Bargain::get_data_by_id($bargain_id, $merchant_id);
        if(empty($bargain_info)){
            return ['errcode' => 60020,'errmsg' => "活动不存在"];
        }
        $return = $this->doBargainTime($bargain_info['start_time'], $bargain_info['end_time']);
        if($return != 1 || ($bargain_info['status'] != 0)){
            return ['errcode' => 60019,'errmsg' => "活动状态不在进行中"];
        }


        //活动发起表信息
        $bargain_launch_info = BargainLaunch::get_data_by_id($launch_id, $merchant_id,$bargain_id);

        if($bargain_launch_info['member_id'] != $member_id){
            return ['errcode' => 1001,'errmsg' => '自己发起的砍价才能下单','data' => ""];
        }


        //事务
        DB::beginTransaction();
        try{

            //调用创建订单api
            $create_order_data = [
                'merchant_id' => $merchant_id,//商户id
                'member_id' => $member_id,//会员id
                'order_type' => ORDER_BARGAIN,//订单类型，砍价订单
                'source' => $source,//订单来源
                'source_id' => $source_id,//订单来源id
                'goods' => [
                    0 => [
                        'goods_id'	=>	$bargain_info['goods_id'],	             //商品id
                        'spec_id'	=>	$bargain_launch_info['goods_spec_id'],	 //商品规格id
                        'sum'		=>	1,	                                     //购买数量
                        'pay_price'	=>	$bargain_launch_info['bargain_price'],	 //购买价格
                        'ump_type'	=>	9,	                                     //优惠类型（config/varconfig.php -> order_ump_ump_type）,没有为空
                        'ump_id'	=>	$bargain_id,	                         //优惠活动id
                    ]
                ]
            ];

            $buyService = new buyService;
            $createorder_rs = $buyService->createorder($create_order_data);

            if($createorder_rs['errcode'] != 0){
                throw new \Exception($createorder_rs['errmsg']);
            }else{
                //下单成功

                //更新发起表数据
                $bargain_launch_updata['status'] = 2;//砍价成功
                $bargain_launch_updata['success_at'] = date('Y-m-d H:i:s');//下单时间
                $bargain_launch_updata['order_id'] = $createorder_rs['data']['order_id'];
                $bargain_launch_updata['order_sn'] = $createorder_rs['data']['order_sn'];
                $bargain_launch_data_count = BargainLaunch::update_data($launch_id,$merchant_id,$bargain_id,$bargain_launch_updata);//成功返回主键id
                //更新活动数据
                Bargain::where('id','=',$bargain_id)
                    ->where('merchant_id','=',$merchant_id)
                    ->increment('sales',1);

                if ($bargain_launch_data_count<1) {
                    throw new \Exception('下单失败');
                }
            }

            //订单信息
            $data['order_id'] = $createorder_rs['data']['order_id'];
            $data['order_sn'] = $createorder_rs['data']['order_sn'];


            DB::commit();
            return ['errcode' => 0,'errmsg' => '下单成功','data' => $data];

        }catch (\Exception $e){

            DB::rollBack();
            return ['errcode' => 60018,'errmsg' => $e->getMessage()];
        }



    }

}
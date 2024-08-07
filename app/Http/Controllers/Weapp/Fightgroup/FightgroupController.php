<?php
/**
 * 拼团控制器
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
use App\Models\Shipment;

use App\Services\FightgroupService;
use App\Services\BuyService;


class FightgroupController extends Controller {

    public function __construct(FightgroupService $fightgroupService,BuyService $buyService){
        
        //拼团服务类
        $this->fightgroupService = $fightgroupService;
        
        //下单支付服务类
        $this->buyService = $buyService;
        
        
        $this->member_id = Member::id();//会员id
        $this->merchant_id = Member::merchant_id();//商户id
        
    }

    /**
     * 获取拼团活动列表（拼团活动列表页面已经被干掉）
     * chang
     * 20170901 12:00
     * $merchant_id  商户Id
     * $status  活动状态
     * @return \Illuminate\Http\Response
     */
    /*public function getfightgroupIist(Request $request)
    {
        //参数
        $params['status'] = $request['status'];
        $params['offset'] = $request['page'];
        $params['limit'] = $request['pagesize'];

        $rules = [
            'merchant_id'   => 'required|integer|min:1',
            'status'        => 'required|in:1,2',
            'offset'        => 'integer|min:0',
            'limit'         => 'integer|min:1'
        ];
        $messages = [
            'status.required'           => 'status是必传的',
            'status.in'                 => 'status不在允许区间内',
            'offset.integer'            => '非法的offset',
            'offset.min'                => '非法的offset',
            'limit.integer'             => '非法的limit',
            'limit.min'                 => '非法的limit'
        ];
        $validator = Validator::make( $params ,$rules ,$messages );
        if($validator->fails()){
            $error = $validator->errors()->first();
            return ['errcode' => 10001, 'errmsg' => $error];
            exit;
        }

        $merchant_id = $this->merchant_id;//商户id
        $status = isset($params['status']) ? $params['status'] : 1;//活动状态
        $offset = isset($params['offset']) ? $params['offset'] : 0;
        $limit = isset($params['limit']) ? $params['limit'] : 10;

        $wheres = [
                        [
                            'column'   => 'merchant_id',
                            'operator' => '=',
                            'value'    => $merchant_id,
                        ],
                        [
                            'column'   => 'status',
                            'operator' => '=',
                            'value'    => $status,
                        ]
                    ];

        $fields=" id,merchant_id,goods_id,goods_price,goods_title,goods_img,title,is_sku,status,start_time,end_time ";
        $count = Fightgroup::get_data_count($wheres);
        $data = Fightgroup::get_data_list($wheres, $fields, $offset, $limit);
        foreach($data as $key=>$val){
             $itemInfo = FightgroupItem::select('id', 'ladder_id', 'product_price', 'ladder_price')
                ->where(['merchant_id' => $merchant_id, 'fightgroup_id' => $val['id']])
                ->orderBy('ladder_price', 'ASC')
                ->first();
            if($itemInfo) {
                $itemInfo['cpeoples'] = FightgroupLadder::where('id', $itemInfo['ladder_id'])->value('cpeoples');
                $itemInfo['ladder_price'] = $itemInfo['ladder_price'];
                $data[$key]['item'] = $itemInfo;
                unset($data['ladder_id']);
            }else{
                unset($data[$key]);
            }
        }
        return ['errcode' => 0, 'errmsg' => '获取拼团列表成功','_count'=>$count,'data' => $data];
    }*/
    /**
     * 获取拼团活动详情
     * 通过活动列表入口获取拼团活动详情
     * chang
     * 20170901 12:00
     * @param string $merchant_id  商户ID
     * @param string $fightgroup_id  活动ID
     * @return \Illuminate\Http\Response
     */
    public function getfightgroupInfo($fightgroup_id)
    {
        //参数
        $params['fightgroup_id'] = $fightgroup_id;
        $rules = [
            'fightgroup_id' => 'required|integer|min:1'
        ];
        $messages = [
            'fightgroup_id.required' => '拼团活动ID是必传的',
            'fightgroup_id.integer' => '非法的拼团活动ID',
            'fightgroup_id.min' => '非法的拼团活动ID'
        ];
        $validator = Validator::make($params, $rules, $messages);
        if ($validator->fails()) {
            $error = $validator->errors()->first();
            return ['errcode' => 10001, 'errmsg' => $error];
            exit;
        }

         $fightgroup_id = isset($params['fightgroup_id']) ? $params['fightgroup_id'] : 0;//活动id
         $member_id = Member::id();
         $merchant_id = Member::merchant_id();
         //$member_id =1;
         //$merchant_id = 10000000;

        $Fightgroup_Info = Fightgroup::get_data_by_id($fightgroup_id,$merchant_id);//活动详情
        if(empty($Fightgroup_Info)) {
            return ['errcode' => 10002, 'errmsg' => '无效拼团！'];exit;
        }
        $ladderInfo = FightgroupLadder::select('id', 'status', 'cpeoples')
            ->where(['merchant_id' =>$merchant_id, 'fightgroup_id' => $Fightgroup_Info['id'], 'status' => PIN_LADDER_ACTIVE])
            ->get();//阶梯列表
        if(empty($ladderInfo->toArray())) {
            return ['errcode' => 10003, 'errmsg' => '无效拼团！'];exit;
        }
        foreach($ladderInfo as $k=>$v){
            $ladderList[$k]['ladder_id'] = $v['id'];
            $ladderList[$k]['cpeoples'] = $v['cpeoples'];
            $itemInfo = FightgroupItem::select('id', 'ladder_id','product_price', 'ladder_price')
                ->where(['merchant_id' => $merchant_id, 'fightgroup_id' => $Fightgroup_Info['id'], 'ladder_id' => $v['id']])
                ->orderBy('ladder_price', 'ASC')
                ->first();//当前阶梯最低价
            if(empty($itemInfo->toArray())) {
                return ['errcode' => 10004, 'errmsg' => '无效拼团！'];exit;
            }
            $ladderList[$k]['ladder_price'] =$itemInfo['ladder_price'];
            $ladderList[$k]['product_price'] =$itemInfo['product_price'];
            //各阶梯是否有团长已开团
            $Launch = FightgroupLaunch::select('id', 'ladder_id', 'nums_less')
                ->where(['merchant_id' => $merchant_id, 'fightgroup_id' => $Fightgroup_Info['id'], 'ladder_id' => $v['id'],'status' => PIN_INIT_ACTIVE])
                ->orderBy('nums_less', 'ASC')
                ->first();//当前阶梯最低价
            $ladderList[$k]['launch_start'] = $Launch ? 1 : 0;//有团长已开团/无团长开团
            $ladderList[$k]['launch_id'] = $Launch['id'] ? $Launch['id'] : "";//fightgroup_launch 拼团发起表主键id 开团id
            $ladderList[$k]['launch_nums_less'] = $Launch['nums_less'] ? $Launch['nums_less'] : "";//可用名额
        }
        //$stocks = FightgroupStock::where('fightgroup_id', $Fightgroup_Info['id'])->sum('stock');//库存
        $stocks = $this->fightgroupService->getActivityStock($fightgroup_id,$merchant_id);
        $tuanJoins = FightgroupLaunch::where('fightgroup_id', $Fightgroup_Info['id'])->sum('nums_join');//参加人数
        $goods_content_json = GoodsComponent::where('goods_id', $Fightgroup_Info['goods_id'])->value('content');
        $goods_content = $goods_content_json ? json_decode($goods_content_json)->text : "";//商品详情

        //拼团参与列表
        $data_join_list = FightgroupJoin::select('id', 'status','launch_id','is_captain')
            ->where(['merchant_id' =>$merchant_id, 'fightgroup_id' => $Fightgroup_Info['id'], 'member_id' => $member_id])
            ->get();
        if($data_join_list){
            $join_list = array();
            foreach($data_join_list as $k=>$v){
                $join_list[$k]['join_id'] = $v['id'];
                $join_list[$k]['is_captain'] = $v['is_captain'];//'0:团员 1:团长'

                $join_status_name = self::get_my_join_list_status($v['is_captain'],$v['status']);
                $join_list[$k]['join_status'] = $join_status_name['join_status'];//拼团状态
                $join_list[$k]['join_status_name'] = $join_status_name['join_status_name'];//拼团状态--文案
                $join_list[$k]['join_status_button'] = $join_status_name['join_status_button'];//拼团状态--按钮文案

                $join_list[$k]['join_launch_id'] = $v['launch_id'];//拼团发起表id:开团id
                //参与团的团长信息
                $join_launch_info = FightgroupLaunch::select('avatar','nums','nums_less')->where(['id' => $v['launch_id']])->first();
                $join_list[$k]['join_launch_avatar'] = $join_launch_info['avatar'];//团长头像
                $join_list[$k]['join_launch_nums'] = $join_launch_info['nums'];//拼团所需人数
                $join_list[$k]['join_launch_nums_less'] = $join_launch_info['nums_less'];//差几人
            }
        }

        if($Fightgroup_Info['join_limit'] > 0){
            //join_limit_status 参团次数是否到上线（1 已到上限，不可以参团； 0 未到上限可以参团 ）
            $join_number = FightgroupJoin::select('id')
                ->where(['merchant_id' => $merchant_id, 'fightgroup_id' => $Fightgroup_Info['id'], 'member_id' => $member_id])
                ->whereIn('status', [1, 4, 6, 7])
                ->count();
            $join_limit_status = $Fightgroup_Info['join_limit'] - $join_number > 0 ? 0 : 1;
        }else{
            $join_limit_status = 0;//数据为0是不限制次数的
        }


        //fightgroup_join_status 是否有待支付拼团（1 有，不可以参团； 0 无，可以参团 ）
        $fightgroup_join_info = FightgroupJoin::select('id')
            ->where(['merchant_id'=>$merchant_id,'fightgroup_id'=>$Fightgroup_Info['id'],'member_id'=>$member_id,'status'=>0])
            ->count();
        $fightgroup_join_status = $fightgroup_join_info > 0 ? 1 : 0;

        //分享数据活动标题开始
        $share_detail_info = $Fightgroup_Info['share_activity'];
        $max_price = FightgroupItem::where(['merchant_id' => $merchant_id, 'fightgroup_id' => $Fightgroup_Info['id']])->max('product_price');
        $min_price = FightgroupItem::where(['merchant_id' => $merchant_id, 'fightgroup_id' => $Fightgroup_Info['id']])->min('ladder_price');
        $share_detail = $share_detail_info ? json_decode($share_detail_info, true) : [];
        if(isset($share_detail['name'])) {
            $shareDetailArr = array(
                "{{拼团活动名称}}"=>$Fightgroup_Info['title'],
                "{{商品最高价}}"=>$max_price,
                "{{商品最低价}}"=>$min_price
            );
            $share_activity = strtr($share_detail['name'],$shareDetailArr);
        }else{
            $share_activity = "原价￥".$max_price."，现价￥".$min_price."起，参加“".$Fightgroup_Info['title']."”即可享受";
        }
        //分享活动标题 结束

        $data['id']             = $Fightgroup_Info['id'];
        $data['goods_id']       = $Fightgroup_Info['goods_id'];
        $data['goods_img']      = $Fightgroup_Info['goods_img'];
        $data['goods_content']  = $goods_content ? $goods_content : "";//商品详情
        $data['is_sku']         = $Fightgroup_Info['is_sku'];
        $data['title']          = $Fightgroup_Info['title'];
        $data['remark']         = $Fightgroup_Info['remark'];
        $data['share_activity'] = $share_activity;
        $data['start_time']     = $Fightgroup_Info['start_time'];
        $data['end_time']       = $Fightgroup_Info['end_time'];
        $data['stocks']         = $stocks > 0 ? $stocks : 0;//库存
        $data['tuanJoins']      = $tuanJoins >0 ? $tuanJoins : 0;//参与人数
        $data['join_limit_status']      = $join_limit_status;
        $data['fightgroup_join_status'] = $fightgroup_join_status;
        $data['ladderList']     = $ladderList ? $ladderList : array();//阶梯价列表
        $data['joinList']       = $join_list ? $join_list : array();//参与的团列表

        return ['errcode' => 0, 'errmsg' => "获取拼团首页详情成功", 'data' => $data];
    }

    /**
     * 获取拼团活动某阶梯   普通/规格商品库存信息
     * @author wangshen@dodoca.com
	 * @cdate 2017-9-5
     * 
     * @param int $merchant_id  商户id
     * @param int $ladder_id  拼团活动阶梯id
     */
    public function getLadderSkuInfo($ladder_id){
        
        //参数
        $merchant_id = $this->merchant_id;//商户id
        
        
        $ladder_id = isset($ladder_id) ? (int)$ladder_id : 0;//拼团活动阶梯id
        
        
        
        //查询拼团阶梯表信息
        $fightgroup_ladder_info = FightgroupLadder::get_data_by_id($ladder_id, $merchant_id);
        
        if(!$fightgroup_ladder_info){
            return ['errcode' => 60003,'errmsg' => '拼团阶梯信息不存在'];
        }
        
        
        //拼团活动id
        $fightgroup_id = $fightgroup_ladder_info['fightgroup_id'];
        
        
        //查询拼团活动信息
        $fightgroup_info = Fightgroup::get_data_by_id($fightgroup_id, $merchant_id);
        
        if(!$fightgroup_info){
            return ['errcode' => 60001,'errmsg' => '未查询到拼团活动信息'];
        }
        
        
        //获取拼团活动   普通/规格商品库存信息
        $sku_info = $this->fightgroupService->getSkuInfo($fightgroup_id,$merchant_id);
        
        if($sku_info['errcode'] != 0){
            return ['errcode' => $sku_info['errcode'],'errmsg' => $sku_info['errmsg']];
        }
        
        
        //该阶梯团最低价
        $min_price = FightgroupItem::where('merchant_id','=',$merchant_id)
                                    ->where('ladder_id','=',$ladder_id)
                                    ->min('ladder_price');
        //该阶梯团最高价
        $max_price = FightgroupItem::where('merchant_id','=',$merchant_id)
                                    ->where('ladder_id','=',$ladder_id)
                                    ->max('ladder_price');
        
        //拼团活动商品规格数据
        $goods_props = $sku_info['data']['is_sku'] == 1 ? json_decode($fightgroup_info['goods_props'], true) : '';
                                    
                                    
        
        //普通/规格商品库存信息
        $fightgroup_item_list_all = [];
        
        $fightgroup_item_list = FightgroupItem::select('id',
                                                'stock_id',
                                                'goods_id', 
                                                'is_sku',
                                                'props', 
                                                'props_str', 
                                                'img', 
                                                'ladder_price as price'
                                                )
                                                ->where('merchant_id','=',$merchant_id)
                                                ->where('ladder_id','=',$ladder_id)
                                                ->get();
        $fightgroup_item_list = json_decode($fightgroup_item_list,true);
        
        if($fightgroup_item_list){
            
            foreach($fightgroup_item_list as $key=>$val) {
                if($sku_info['data']['is_sku'] == 1){
                    //多规格库存
                    $fightgroup_item_list[$key]['stock'] = $sku_info['data']['fightgroup_stock_list_more'][$val['stock_id']]['stock'];
                    unset($fightgroup_item_list[$key]['stock_id']);
                    $fightgroup_item_list_all[$val['props']] = $fightgroup_item_list[$key];//多规格键名定义为props
                }else{
                    //单规格库存
                    unset($fightgroup_item_list[$key]['stock_id']);
                    $fightgroup_item_list[$key]['stock'] = $sku_info['data']['fightgroup_stock_info_single']['stock'];
                }
            }
            
        }                                     
                            
        //普通/规格商品库存信息
        $products = $sku_info['data']['is_sku'] == 1 ? $fightgroup_item_list_all : $fightgroup_item_list;
        
                        
        //接口返回信息
        $data = [
            'type' => 'tuan',                                       //获取规格类型，拼团：tuan
            'is_sku' => $sku_info['data']['is_sku'],                //单/多规格，0->单规格  1->多规格
            'stock' => $sku_info['data']['fightgroup_stock'],       //拼团活动占用剩余总库存
            'price' => $min_price,                                  //该阶梯团最低价（单规格时最低最高价一样）
            'max_price' => $max_price,                              //该阶梯团最高价（单规格时最低最高价一样）
            'title' => $fightgroup_info['goods_title'],             //商品名称
            'img' => $fightgroup_info['goods_img'],                 //商品图片
            'goods_props' => $goods_props,                          //拼团活动商品规格数据
            'goods_spec' => $products                               //普通/规格商品库存信息
        ];
        
        
        
        return ['errcode' => 0,'errmsg' => 'ok','data' => $data];
    }
    

    /**
     * 我的拼团列表
     * @author wangshen@dodoca.com
	 * @cdate 2017-9-5
     * 
     * @param int $merchant_id  商户id
     */
    public function getMyFightgroupList(Request $request){
        
        //参数
        $params = $request->all();
        
        $merchant_id = $this->merchant_id;//商户id
        
        $member_id = $this->member_id;//会员id

        
        
        //分页参数
        $page = isset($params['page']) ? (int)$params['page'] : 1;//页码
        $pagesize = isset($params['pagesize']) ? (int)$params['pagesize'] : 10;//每页条数
        
        //偏移量/每页数量
        $offset = ($page - 1) * $pagesize;
        $limit = $pagesize;
        
        
        
        //条件
        $wheres = [];
        $wheres[] = ['column' => 'merchant_id','operator' => '=','value' => $merchant_id];
        $wheres[] = ['column' => 'member_id','operator' => '=','value' => $member_id];
        
        //查询字段
        $fields = 'fightgroup_id,launch_id,order_id,is_captain,status,num,item_id,tuan_price';
        
        //数量
        $_count = FightgroupJoin::get_data_count($wheres);
        
        //列表数据
        $fightgroup_join_list = FightgroupJoin::get_data_list($wheres,$fields,$offset,$limit,'id','DESC');
        
        if($fightgroup_join_list){
            foreach($fightgroup_join_list as $key => $val){
                
                //查询拼团发起表
                $fightgroup_launch_info = FightgroupLaunch::get_data_by_id($val['launch_id'], $merchant_id);
                
                //xx人团
                $fightgroup_join_list[$key]['cpeoples'] = $fightgroup_launch_info['nums'];
                
                //还差人数
                $fightgroup_join_list[$key]['nums_less'] = $fightgroup_launch_info['nums_less'];
                
                
                //获取我的拼团列表，状态和按钮状态（前端显示）
                $list_status_info = self::get_my_join_list_status($val['is_captain'], $val['status']);
                $fightgroup_join_list[$key]['join_status'] = $list_status_info['join_status'];//状态值
                $fightgroup_join_list[$key]['join_status_name'] = $list_status_info['join_status_name'];//状态文案
                $fightgroup_join_list[$key]['join_status_button'] = $list_status_info['join_status_button'];//按钮文案
                
                
                //查询拼团活动表
                $fightgroup_info = Fightgroup::get_data_by_id($val['fightgroup_id'], $merchant_id);
                
                //商品名称
                $fightgroup_join_list[$key]['goods_title'] = $fightgroup_info['goods_title'];
                
                //商品图片
                $fightgroup_join_list[$key]['goods_img'] = $fightgroup_info['goods_img'];
                
                //商品id
                $fightgroup_join_list[$key]['goods_id'] = $fightgroup_info['goods_id'];
                
                //拼团活动是否已过期
                if($fightgroup_info['status'] == 0 || $fightgroup_info['status'] == 1){
                    $fightgroup_join_list[$key]['if_over'] = 0;//未过期
                }else{
                    $fightgroup_join_list[$key]['if_over'] = 1;//已过期
                }
                
                
                //获取拼团子表数据
                $fightgroup_item_id = FightgroupItem::get_data_by_id($val['item_id'], $merchant_id);
                
                //原价
                $fightgroup_join_list[$key]['product_price'] = $fightgroup_item_id['product_price'];
                
                //规格数据
                $fightgroup_join_list[$key]['props_str'] = $fightgroup_item_id['props_str'];
                
                
                
                //订单运费
                $shipment_fee = OrderInfo::get_data_by_id($val['order_id'], $merchant_id,'shipment_fee');
                if($shipment_fee){
                    $shipment_fee = isset($shipment_fee['shipment_fee']) ? $shipment_fee['shipment_fee'] : 0;
                }else{
                    $shipment_fee = 0;
                }
                
                $fightgroup_join_list[$key]['shipment_fee'] = $shipment_fee;
                
                
                //总金额，拼团价*数量+运费
                $fightgroup_join_list[$key]['all_price'] = $val['tuan_price'] * $val['num'] + $shipment_fee;
                
                
                //去除不必要字段
                unset($fightgroup_join_list[$key]['fightgroup_id']);
                unset($fightgroup_join_list[$key]['order_id']);
                unset($fightgroup_join_list[$key]['is_captain']);
                unset($fightgroup_join_list[$key]['status']);
                unset($fightgroup_join_list[$key]['item_id']);
                
            }
        }
        
        
        $data['_count'] = $_count;
        $data['data'] = $fightgroup_join_list;
        
        return ['errcode' => 0,'errmsg' => 'ok','data' => $data];
    }
    
    
    /**
     * 获取我的拼团列表，状态和按钮状态（前端显示）
     * @author wangshen@dodoca.com
     * @cdate 2017-9-5
     * 
     * @param int $is_captain  0:团员 1:团长
     * @param int $status  拼团参与表状态id（0:待支付 1:已支付 2:失败(超时未支付) 3失败(库存不足超卖) 4未成团 5已成团超卖 6:参团成功 7未成团（手动结束）8失败（非进行中（活动、阶梯、团）超卖））
     * 
     * @return int $join_status 状态值
     * @return string $join_status_name 状态文案
     * @return string $join_status_button 按钮文案
     */
    public static function get_my_join_list_status($is_captain,$status){
        
        $join_status = 0;               //状态值
        $join_status_name = '未知';      //状态文案
        $join_status_button = '未知';    //按钮文案
        
        
        if($status == 0){//0待支付
            
            if($is_captain == 1){
                $join_status = 1;
                $join_status_name = '开团待支付';
                $join_status_button = '去支付';
            }else{
                $join_status = 2;
                $join_status_name = '参团待支付'; 
                $join_status_button = '去支付';
            }
            
        }elseif($status == 2 || $status == 3 || $status == 8){//2失败(超时未支付) 3失败(库存不足超卖)  8失败（非进行中（活动、阶梯、团）超卖）
            
            if($is_captain == 1){
                $join_status = 3;
                $join_status_name = '开团失败';
                $join_status_button = '查看详情';
            }else{
                $join_status = 4;
                $join_status_name = '参团失败';
                $join_status_button = '查看详情';
            }
            
        }elseif($status == 1){//1已支付
            
            $join_status = 5;
            $join_status_name = '拼团中';
            $join_status_button = '邀请好友';
            
        }elseif($status == 6){//6参团成功
            
            $join_status = 6;
            $join_status_name = '拼团成功';
            $join_status_button = '查看详情';
            
        }elseif($status == 4){//4未成团
            
            $join_status = 7;
            $join_status_name = '拼团失败';
            $join_status_button = '查看详情';
            
        }elseif($status == 7){//7未成团（手动结束）
            
            $join_status = 8;
            $join_status_name = '拼团结束';
            $join_status_button = '查看详情';
            
        }elseif($status == 5){//5已成团超卖
            
            $join_status = 9;
            $join_status_name = '拼团超卖';
            $join_status_button = '查看详情';
            
        }
        
        return [
            'join_status' => $join_status,
            'join_status_name' => $join_status_name,
            'join_status_button'=>$join_status_button
        ];
    }
    
    
    /**
     * 获取某个团的详情
     * @author wangshen@dodoca.com
     * @cdate 2017-9-8
     */
    public function getFightgroupDetail($fightgroup_launch_id){
        
        //参数
        $fightgroup_launch_id = isset($fightgroup_launch_id) ? (int)$fightgroup_launch_id : 0;//拼团发起表id
        
        
        $merchant_id = $this->merchant_id;//商户id
        
        
        $member_id = $this->member_id;//会员id
        
        
        //查询拼团发起表数据
        $fightgroup_launch_info = FightgroupLaunch::get_data_by_id($fightgroup_launch_id, $merchant_id);
        
        if(!$fightgroup_launch_info){
            return ['errcode' => 60004,'errmsg' => '团信息不存在'];
        }
        
        //查询拼团活动信息
        $fightgroup_info = Fightgroup::get_data_by_id($fightgroup_launch_info['fightgroup_id'], $merchant_id);
        
        if(!$fightgroup_info){
            return ['errcode' => 60001,'errmsg' => '未查询到拼团活动信息'];
        }
        
        
        
        
        //拼团活动  信息-----
        
        //商品图片
        $goods_img = $fightgroup_info['goods_img'];
        
        //活动标题
        $title = $fightgroup_info['title'];
        
        //运费（商品运费）（有运费模版则显示首费金额）
        $goods_info = Goods::get_data_by_id($fightgroup_info['goods_id'], $merchant_id);
        
        if(!empty($goods_info['shipment_id'])){
            $shipment_info = Shipment::get_data_by_id($goods_info['shipment_id']);
            
            $shipment_fee = isset($shipment_info['start_fee']) ? $shipment_info['start_fee'] : 0;
        }else{
            $shipment_fee = $goods_info['postage'] ? $goods_info['postage'] : 0;
        }
        
        //活动库存
        $stock = $this->fightgroupService->getActivityStock($fightgroup_launch_info['fightgroup_id'], $merchant_id);
        
        //商家设置的活动说明
        $activity_remark = $fightgroup_info['remark'];
        
        //商品详情
        $goods_content = GoodsComponent::where('goods_id', $fightgroup_info['goods_id'])->value('content');
        if($goods_content){
            $goods_content = json_decode($goods_content,true);
            $goods_content = isset($goods_content['text']) ? $goods_content['text'] : '';
        }else{
            $goods_content = '';
        }
        
        //商品id
        $goods_id = $fightgroup_info['goods_id'];
        
        //拼团大活动是否结束（0拼团大活动未结束，1拼团大活动已结束）
        $activity_if_end = 0;
        
        if($fightgroup_info['status'] != 1){
            $activity_if_end = 1;
        }
        
        if(date('Y-m-d H:i:s') < $fightgroup_info['start_time']){
            $activity_if_end = 1;
        }
        
        if($fightgroup_info['end_time'] != '0000-00-00 00:00:00'){
            if(date('Y-m-d H:i:s') > $fightgroup_info['end_time']){
                $activity_if_end = 1;
            }
        }
        
        
        //拼团活动  信息-----
        
        
        
        
        
        //团长发起的某个阶梯的团    信息-----
        
        //阶梯id
        $ladder_id = $fightgroup_launch_info['ladder_id'];
        
        //最低团价（该阶梯团最低团价）
        $tuan_price = FightgroupItem::where('merchant_id','=',$merchant_id)
                                    ->where('ladder_id','=',$fightgroup_launch_info['ladder_id'])
                                    ->min('ladder_price');
        
        
        //最高原价（该阶梯团最高原价）
        $original_price = FightgroupItem::where('merchant_id','=',$merchant_id)
                                        ->where('ladder_id','=',$fightgroup_launch_info['ladder_id'])
                                        ->max('product_price');
        
        //最高省xx元计算
        $fightgroup_item_list = FightgroupItem::select('product_price','ladder_price')
                                                ->where('merchant_id','=',$merchant_id)
                                                ->where('ladder_id','=',$fightgroup_launch_info['ladder_id'])
                                                ->get();
        $fightgroup_item_list = json_decode($fightgroup_item_list,true);
                                        
        //取出所有规格差价
        $c_price = [];
        
        if($fightgroup_item_list){
            foreach($fightgroup_item_list as $key=>$val){
                $c_price[]['ump_price'] = $val['product_price'] - $val['ladder_price'];
            }
        }
        
        $c_price = self::arraysort($c_price, 'ump_price', 'desc');
        
        //最高省xx元
        $ump_price = $c_price[0]['ump_price'];
        
        
                                        
        //xx人团
        $cpeoples = $fightgroup_launch_info['nums'];
        
        //已购人数
        $nums_join = $fightgroup_launch_info['nums_join'];
        
        //还差人数
        $nums_less = $fightgroup_launch_info['nums_less'];
        
        //团结束时间
        $end_time = 0;
        
        //团的状态
        if($fightgroup_launch_info['status'] == 0){
            //团长未支付 
            $launch_status = 0;//团状态：0开团人还未支付，1拼团中，2拼团成功，3拼团失败
            
        }elseif($fightgroup_launch_info['status'] == 1){
            //拼团中
            $launch_status = 1;//团状态：0开团人还未支付，1拼团中，2拼团成功，3拼团失败
            
            //团结束时间（显示倒计时）
            $end_time = $fightgroup_launch_info['end_time'];
            
        }elseif($fightgroup_launch_info['status'] == 2){
            //拼团成功
            $launch_status = 2;//团状态：0开团人还未支付，1拼团中，2拼团成功，3拼团失败
            
        }else{
            //其余都为拼团失败
            $launch_status = 3;//团状态：0开团人还未支付，1拼团中，2拼团成功，3拼团失败
            
        }
        
        //团是否有结果标识
        if($launch_status == 2 || $launch_status == 3){
            $if_launch_result = 1;//是否有结果，1有结果，0无结果
            
        }else{
            $if_launch_result = 0;//是否有结果，1有结果，0无结果
            
        }
        
        
        
        //团长发起的某个阶梯的团     信息-----
        
        
        
        
        
        //会员对于团的身份  信息-----
        
        
        //判断整个拼团活动实际参与次数
        $fightgroup_join_time = $this->fightgroupService->memberJoinNums($merchant_id, $fightgroup_info['id'], $member_id);
        
        if($fightgroup_info['join_limit'] != 0){
            if($fightgroup_join_time >= $fightgroup_info['join_limit']){
                $if_join_limit = 1;//整个拼团活动实际参与次数已到上限
            }else{
                $if_join_limit = 0;//整个拼团活动实际参与次数未到上限
            }
        }else{
            $if_join_limit = 0;//整个拼团活动实际参与次数未到上限
        }
        
        
        
        
        //判断是否有未支付的参与记录
        $has_not_pay_c = FightgroupJoin::where('merchant_id','=',$merchant_id)
                                        ->where('fightgroup_id','=',$fightgroup_info['id'])
                                        ->where('member_id','=',$member_id)
                                        ->where('status','=',0)
                                        ->count();
        if($has_not_pay_c > 0){
            $has_not_pay = 1;//该拼团活动有未支付订单
        }else{
            $has_not_pay = 0;//该拼团活动无未支付订单
        }
        
        
        //（0:待支付 1:已支付 2:失败(超时未支付) 3失败(库存不足超卖) 4未成团 5已成团超卖 6:参团成功 7未成团（手动结束）8失败（非进行中（活动、阶梯、团）超卖））
        //查询拼团参与表（未付款，或者实际参与了的会员）
        $fightgroup_join_info = FightgroupJoin::select('id','item_id','num','status','is_captain','order_time','order_id','order_sn')
                                                ->where('merchant_id','=',$merchant_id)
                                                ->where('launch_id','=',$fightgroup_launch_id)
                                                ->where('member_id','=',$member_id)
                                                ->whereIn('status', [0,1,4,6,7])
                                                ->first();                                            
        
        //是否显示去支付按钮：0不显示，1显示
        $if_pay = 0;
        
        //规格数据
        $goods_props = '';
        
        //参团编号
        $join_tuan_id = '';
        
        //参团时间
        $join_time = '';
        
        //订单id
        $order_id = 0;
        
        //订单号
        $order_sn = '';
        
                                                
        //参团人信息
        if($fightgroup_join_info){
            
            //参团人拼团子表信息
            $fightgroup_item_info = FightgroupItem::get_data_by_id($fightgroup_join_info['item_id'], $merchant_id);
            
            
            if($fightgroup_join_info['status'] == 0){
                
                $member_type = 4;//会员对于团的身份：1团长，2团员，3未参团，4待付款的人（还未参团）
                
            }else{
                if($fightgroup_join_info['is_captain'] == 1){
                    //团长
                    $member_type = 1;//会员对于团的身份：1团长，2团员，3未参团，4待付款的人（还未参团）
                
                }else{
                    //团员
                    $member_type = 2;//会员对于团的身份：1团长，2团员，3未参团，4待付款的人（还未参团）
                
                }
            }
            
            //团员支付状态（0:待支付 1:已支付 2:失败(超时未支付) 3失败(库存不足超卖) 4未成团 5已成团超卖 6:参团成功 7未成团（手动结束）8失败（非进行中（活动、阶梯、团）超卖））
            
            //是否显示去支付按钮：0不显示，1显示
            if($fightgroup_join_info['status'] == 0){
                $if_pay = 1;
            }else{
                $if_pay = 0;
            }
            
            //规格数据（字符串）
            $goods_props = self::convertGoodsProp($fightgroup_info['goods_props'], $fightgroup_item_info['props']) . " x" . $fightgroup_join_info['num'];
            
            //参团编号
            $join_tuan_id = $fightgroup_info['id'] . '/' . $fightgroup_launch_info['id'];
            
            //参团时间
            $join_time = $fightgroup_join_info['order_time'];
            
            //订单id
            $order_id = $fightgroup_join_info['order_id'];
            
            //订单号
            $order_sn = $fightgroup_join_info['order_sn'];
            
        }else{
            
            $member_type = 3;//会员对于团的身份：1团长，2团员，3未参团，4待付款的人（还未参团）
            
        }
        
        //会员对于团的身份  信息-----
        
        
        
        
        
        
        //实际参团人员列表  信息-----
        
        //实际参与的会员列表
        $fightgroup_join_list_all = [];
        
        //实际参与了的会员（0:待支付 1:已支付 2:失败(超时未支付) 3失败(库存不足超卖) 4未成团 5已成团超卖 6:参团成功 7未成团（手动结束）8失败（非进行中（活动、阶梯、团）超卖））
        //团长
        $fightgroup_join_tz['avatar'] = $fightgroup_launch_info['avatar'];
        $fightgroup_join_tz['is_captain'] = 1;
        
        $fightgroup_join_list_all[] = $fightgroup_join_tz;
        
        //团员
        $fightgroup_join_list = FightgroupJoin::select('avatar', 'is_captain')
                                                ->where('merchant_id','=',$merchant_id)
                                                ->where('launch_id','=',$fightgroup_launch_id)
                                                ->where('is_captain','=',0)
                                                ->whereIn('status', [1,4,6,7])
                                                ->orderBy('created_time','DESC')
                                                ->skip(0)
                                                ->take(20)//展示数量
                                                ->get();
        $fightgroup_join_list = json_decode($fightgroup_join_list,true);
        
        if($fightgroup_join_list){
            foreach($fightgroup_join_list as $key=>$val){
                $fightgroup_join_list_all[] = $val;
            }
        }
        
        //实际参团人员列表  信息-----
        
        
        
        
        
        
        //分享数据  信息-----
        
        $share_detail = $fightgroup_info['share_detail'];
        
        //团倒计时剩余秒数
        $left_time = strtotime($fightgroup_launch_info['end_time']) - time() < 0 ? 0 : strtotime($fightgroup_launch_info['end_time']) - time();
        
        if($share_detail) {
            $endTime = self::secsToStr($left_time);
            $share_detail = json_decode($share_detail, true);
            
            $shareDetailArr = array(
                "{{团长名称}}" => $fightgroup_launch_info['nickname'],
                "{{拼团时间倒计时}}"=>$endTime,
                "{{拼团活动名称}}"=>$fightgroup_info['title'],
                "{{商品最高价}}"=>$original_price,
                "{{商品最低价}}"=>$tuan_price,
                "{{拼团人数}}"=>$cpeoples,
                
                "{团长名称}" => $fightgroup_launch_info['nickname'],
                "{拼团时间倒计时}"=>$endTime,
                "{拼团活动名称}"=>$fightgroup_info['title'],
                "{商品最高价}"=>$original_price,
                "{商品最低价}"=>$tuan_price,
                "{拼团人数}"=>$cpeoples
            );
            
            if(isset($share_detail['name'])){
                $share_detail['name'] = strtr($share_detail['name'],$shareDetailArr);
                $share_detail = $share_detail['name'];
            }else{
                $share_detail = "我已经参加了“" . $fightgroup_launch_info['nickname'] . "”发起的拼团。";
            }
            
        }else{
            $share_detail = "我已经参加了“" . $fightgroup_launch_info['nickname'] . "”发起的拼团。";
        }
        
        //分享数据  信息-----
        
        
        
        
        
        
        
        //推荐其他的团（拼团中）    信息-----
        
        $fightgroup_launch_list = FightgroupLaunch::select('id','nickname','avatar','nums','nums_less')
                                                    ->where('merchant_id','=',$merchant_id)
                                                    ->where('fightgroup_id','=',$fightgroup_info['id'])
                                                    ->where('status','=',1)
                                                    ->where('id','!=',$fightgroup_launch_id)
                                                    ->get();
        $fightgroup_launch_list = json_decode($fightgroup_launch_list,true);
        
        //推荐其他的团（拼团中）    信息-----
        
        
        //接口返回数据
		$data = [
		    
		    //拼团活动  信息-----
		    'fightgroup_id' =>$fightgroup_info['id'],//活动id
		    'goods_img' => $goods_img,//商品图片
		    'title' => $title,//活动标题
		    'shipment_fee' => $shipment_fee,//运费
		    'stock' => $stock,//活动库存
		    'activity_remark' => $activity_remark,//商家设置的活动说明
		    'goods_content' => $goods_content,//商品详情
		    'goods_id' => $goods_id,//商品id
		    'activity_if_end' => $activity_if_end,//0拼团大活动未结束，1拼团大活动已结束
		    
		    //团长发起的某个阶梯的团    信息-----
		    'fightgroup_launch_id' =>$fightgroup_launch_id,//拼团发起表id
		    'ladder_id' => $ladder_id,//阶梯id
		    'tuan_price' => $tuan_price,//最低团价（该阶梯团最低团价）
		    'original_price' => $original_price,//最高原价（该阶梯团最高原价）
		    'ump_price' => $ump_price,//最高省xx元
		    'cpeoples' => $cpeoples,//xx人团
		    'nums_join' => $nums_join,//已购人数
		    'nums_less' => $nums_less,//还差人数
		    'end_time' => $end_time,//团结束时间
		    'launch_status' => $launch_status,//团状态：0开团人还未支付，1拼团中，2拼团成功，3拼团失败
		    'if_launch_result' => $if_launch_result,//团是否有结果：1有结果，0无结果
		    
		    //会员对于团的身份  信息-----
		    'if_join_limit' => $if_join_limit,//整个拼团活动实际参与次数是否已到上限，0否 1是
		    'has_not_pay' => $has_not_pay,//该拼团总活动中是否有未支付订单，0否 1是
		    'member_type' => $member_type,//会员对于团的身份：1团长，2团员，3未参团，4待付款的人（还未参团）
		    'if_pay' => $if_pay,//是否显示去支付按钮：0不显示，1显示
		    'goods_props' => $goods_props,//规格数据（字符串）
		    'join_tuan_id' => $join_tuan_id,//参团编号
		    'join_time' => $join_time,//参团时间
		    'order_id' => $order_id,//订单id
		    'order_sn' => $order_sn,//订单号
		    
		    //实际参团人员列表  信息-----
		    'fightgroup_join_list_all' => $fightgroup_join_list_all,//实际参与的会员列表
		    
		    //分享数据  信息-----
		    'share_detail' => $share_detail,//分享文案
		    
		    //推荐其他的团（拼团中）    信息-----
		    'fightgroup_launch_list' => $fightgroup_launch_list//推荐其他的团（拼团中
		    
		];
        
        return ['errcode' => 0,'errmsg' => 'ok','data' => $data];
    }
    
    /**
     * 冒泡排序，获取某个团的详情用
     */
    public static function arraysort($arr,$key,$sort='asc'){
        $count=count($arr);
        $sort=strtolower($sort);
        for($i=0;$i<$count;$i++){
            for($j=$count-1; $j>$i; $j--){
                if($sort=='asc'){
                    if($arr[$j][$key]<$arr[$j-1][$key]){
                        $temp=$arr[$j];
                        $arr[$j]=$arr[$j-1];
                        $arr[$j-1]=$temp;
                    }
                }elseif($sort=='desc'){
                    if($arr[$j][$key]>$arr[$j-1][$key]){
                        $temp=$arr[$j];
                        $arr[$j]=$arr[$j-1];
                        $arr[$j-1]=$temp;
                    }
                }else{
                    return 'wrong sort';
                }
            }
        }
        return $arr;
    }
    
    /**
     * 规格数据转换（字符串），获取某个团的详情用
     */
    public static function convertGoodsProp($goodsProps = '', $props = '') {
        $goodsPropStr = '';
        if($goodsProps && $props) {
            $goodsPropsArr = json_decode($goodsProps, true);
            $propsArr = explode(';', $props);
            if(!empty($goodsPropsArr) && !empty($propsArr)) {
                foreach($propsArr as $val) {
                    $propsItem =  explode(':', $val);
                    if(!empty($propsItem)) {
                        if(isset($goodsPropsArr[$propsItem[0]]['name'])) {
                            $goodsPropStr .= $goodsPropsArr[$propsItem[0]]['name']. ':';
                            foreach($goodsPropsArr[$propsItem[0]]['gps'] as $list) {
                                if($list['prop_vid'] == $propsItem[1]) {
                                    $goodsPropStr .= $list['prop_value']. '/';
                                }
                            }
                        }
                    }
                }
                $goodsPropStr = substr($goodsPropStr, 0, -1);
            }
        }
    
        return $goodsPropStr;
    
    }
    
    /**
     * 分享文案倒计时   秒转时间
     */
    public static function secsToStr($secs) {
        $r = '';
        if($secs>=86400){$days=floor($secs/86400);
            $secs=$secs%86400;
            $r=$days.'天';
        }
        if($secs>=3600){$hours=floor($secs/3600);
            $secs=$secs%3600;
            $r.=$hours.'时';
        }
        if($secs>=60){$minutes=floor($secs/60);
            $secs=$secs%60;
            $r.=$minutes.'分';
        }
        $r.=$secs.'秒';
        return $r;
    }
    
    
    
    
    /**
     * 开团
     * @author wangshen@dodoca.com
     * @cdate 2017-9-8
     */
    public function openFightgroup(Request $request){
        
        //参数
        $params = $request->all();
        
        $fightgroup_id = isset($params['fightgroup_id']) ? (int)$params['fightgroup_id'] : 0;//拼团活动id
        
        $fightgroup_item_id = isset($params['fightgroup_item_id']) ? (int)$params['fightgroup_item_id'] : 0;//拼团子表id
        
        $quantity = isset($params['quantity']) ? (int)$params['quantity'] : 0;//购买数量

        $source = isset($params['source']) ? (int)$params['source'] : 0;//订单来源

        $source_id = isset($params['source_id']) ? (int)$params['source_id'] : 0;//订单来源id
        
        $fightgroup_launch_id = 0;//拼团发起表id（开团为0）
        
        
        $type = 1;//1开团，2参团
        
        //调用统一入口
        $do = $this->doFightgroup($fightgroup_id, $fightgroup_item_id, $quantity, $fightgroup_launch_id, $type,$source,$source_id);
        
        if($do['errcode'] != 0){
            return ['errcode' => $do['errcode'],'errmsg' => $do['errmsg']];
        }
        
        
        //返回订单数据
        return ['errcode' => 0,'errmsg' => 'ok','data' => $do['data']];
    }
    
    
    
    /**
     * 参团
     * @author wangshen@dodoca.com
     * @cdate 2017-9-8
     */
    public function joinFightgroup(Request $request){
    
        //参数
        $params = $request->all();
        
        $fightgroup_id = isset($params['fightgroup_id']) ? (int)$params['fightgroup_id'] : 0;//拼团活动id
    
        $fightgroup_item_id = isset($params['fightgroup_item_id']) ? (int)$params['fightgroup_item_id'] : 0;//拼团子表id
        
        $fightgroup_launch_id = isset($params['fightgroup_launch_id']) ? (int)$params['fightgroup_launch_id'] : 0;//拼团发起表id
    
        $quantity = isset($params['quantity']) ? (int)$params['quantity'] : 0;//购买数量

        $source = isset($params['source']) ? (int)$params['source'] : 0;//订单来源

        $source_id = isset($params['source_id']) ? (int)$params['source_id'] : 0;//订单来源id
        
        $type = 2;//1开团，2参团
        
        //调用统一入口
        $do = $this->doFightgroup($fightgroup_id, $fightgroup_item_id, $quantity, $fightgroup_launch_id, $type,$source,$source_id);
        
        if($do['errcode'] != 0){
            return ['errcode' => $do['errcode'],'errmsg' => $do['errmsg']];
        }
        
    
        //返回订单数据
        return ['errcode' => 0,'errmsg' => 'ok','data' => $do['data']];
    }
    
    
    
    /**
     * 开团或参团统一入口
     * @author wangshen@dodoca.com
     * @cdate 2017-9-14
     * 
     * @param int $fightgroup_id  活动id
     * @param int $fightgroup_item_id  拼团子表id
     * @param int $quantity  购买数量
     * @param int $fightgroup_launch_id  拼团发起表id（开团为0）
     * @param int $type  类型：1开团，2参团
     * 
     */
    public function doFightgroup($fightgroup_id,$fightgroup_item_id,$quantity,$fightgroup_launch_id,$type,$source,$source_id){
        
        //参数
        if(!$fightgroup_id || !$fightgroup_item_id || !$quantity || !$type){
            
            return ['errcode' => 60005,'errmsg' => '开团参团参数缺失'];
        }
        
        //商户id
        $merchant_id = $this->merchant_id;//商户id
        
        //会员id
        $member_id = $this->member_id;//会员id
        
        $if_pay = 0;//未支付时验证会员参与拼团活动有效性
        
        
        //验证会员参与拼团活动有效性
        $verifyDoFightgroup = $this->fightgroupService->verifyDoFightgroup($merchant_id,$member_id,$fightgroup_id,$fightgroup_item_id,$quantity,$fightgroup_launch_id,$type,$if_pay);
        
        if($verifyDoFightgroup['errcode'] != 0){
            
            //验证有效性失败返回错误信息
            return ['errcode' => $verifyDoFightgroup['errcode'],'errmsg' => $verifyDoFightgroup['errmsg']];
        }
        
        
        //验证成功，允许开团/参团
        $fightgroup_info = $verifyDoFightgroup['data']['fightgroup_info'];//拼团活动信息
        $fightgroup_item_info = $verifyDoFightgroup['data']['fightgroup_item_info'];//拼团子表信息
        $fightgroup_ladder_info = $verifyDoFightgroup['data']['fightgroup_ladder_info'];//拼团阶梯表信息
        $fightgroup_launch_info = $verifyDoFightgroup['data']['fightgroup_launch_info'];//拼团发起表信息（开团为空）
        
        
        //会员信息
        $member_info = MemberModel::get_data_by_id($member_id, $merchant_id);
        
        //多个商品总价（数量乘以拼团阶梯价格）
        $fightgroup_all_pay_price = $quantity * $fightgroup_item_info['ladder_price'];
        
        
        //事务
        DB::beginTransaction();
        try{
            
            //调用创建订单api
            $create_order_data = [
                'merchant_id' => $merchant_id,//商户id
                'member_id' => $member_id,//会员id
                'order_type' => ORDER_FIGHTGROUP,//订单类型，拼团订单
                'source' => $source,//订单来源
                'source_id' => $source_id,//订单来源id
                'goods' => [
                    0 => [
                        'goods_id'	=>	$fightgroup_info['goods_id'],	         //商品id
                        'spec_id'	=>	$fightgroup_item_info['spec_id'],	     //商品规格id
                        'sum'		=>	$quantity,	                             //购买数量
                        'pay_price'	=>	$fightgroup_all_pay_price,	             //购买价格(多个商品总价)
                        'ump_type'	=>	5,	                                     //优惠类型（config/varconfig.php -> order_ump_ump_type）,没有为空
                        'ump_id'	=>	$fightgroup_id,	                         //优惠活动id
                    ]
                ]
            ];
            
            
            $createorder_rs = $this->buyService->createorder($create_order_data);
            
        
            if($createorder_rs['errcode'] != 0){
                throw new \Exception($createorder_rs['errmsg']);
            }else{
                
                //处理开团/参团
                if($type == 1){//开团
                    
                    //团结束时间
                    $launch_end_time = date('Y-m-d H:i:s', strtotime("+".$fightgroup_ladder_info['expire_in']." hour"));
                    
                    //拼团发起表数据
                    $fightgroup_launch_data = [
                        'merchant_id'    => $merchant_id,                           //商户id
                        'member_id'      => $member_id,                             //会员id
                        'fightgroup_id'  => $fightgroup_id,                         //拼团活动id
                        'nickname'       => $member_info['name'],                   //团长昵称
                        'avatar'         => $member_info['avatar'],                 //团长头像
                        'item_id'        => $fightgroup_item_id,                    //拼团子表id
                        'ladder_id'      => $fightgroup_ladder_info['id'],          //拼团阶梯表id
                        'is_sku'         => $fightgroup_info['is_sku'],             //是否是多规格的团，0单规格，1多规格
                        'nums'           => $fightgroup_ladder_info['cpeoples'],    //拼团所需人数
                        'nums_join'      => 0,                                      //已用名额
                        'nums_less'      => $fightgroup_ladder_info['cpeoples'],    //可用名额
                        'original_price' => $fightgroup_item_info['product_price'], //商品原价
                        'tuan_price'     => $fightgroup_item_info['ladder_price'],  //拼团价格
                        'status'         => 0,                                      //0提交 1拼团中 2成功 3团长开团超时未支付 4开团失败（库存不足超卖）5拼团失败（参团人数不足） 6手动结束 7开团失败（非进行中（活动、阶梯、团）超卖）
                        'extend_in'      => 0,                                      //延长时间（小时为单位），默认0
                        'start_time'     => date('Y-m-d H:i:s'),                    //开团时间
                        'end_time'       => '0000-00-00 00:00:00',                  //结束时间
                        'success_at'     => '0000-00-00 00:00:00'                   //成团时间
                    ];
                    
                    //插入拼团发起表数据
                    $fightgroup_launch_rs = FightgroupLaunch::insert_data($fightgroup_launch_data);//成功返回主键id
                    
                    
                    if(!$fightgroup_launch_rs){
                        
                        throw new \Exception('开团失败');
                    }else{
                        
                        //拼团参与表数据
                        $fightgroup_join_data = [
                            'merchant_id'   => $merchant_id,                            //商户id
                            'fightgroup_id' => $fightgroup_id,                          //拼团活动id
                            'launch_id'     => $fightgroup_launch_rs,                   //拼团发起表id
                            'item_id'       => $fightgroup_item_id,                     //拼团子表id
                            'member_id'     => $member_id,                              //会员id 
                            'nickname'      => $member_info['name'],                    //会员昵称
                            'avatar'        => $member_info['avatar'],                  //会员头像
                            'tuan_price'    => $fightgroup_item_info['ladder_price'],   //拼团价格
                            'num'           => $quantity,                               //购买件数
                            'order_id'      => $createorder_rs['data']['order_id'],     //订单id
                            'order_sn'      => $createorder_rs['data']['order_sn'],     //订单号
                            'status'        => 0,                                       //0:待支付 1:已支付 2:失败(超时未支付) 3失败(库存不足超卖) 4未成团 5已成团超卖 6:参团成功 7未成团（手动结束）8失败（非进行中（活动、阶梯、团）超卖）
                            'is_captain'    => 1,                                       //0:团员 1:团长
                            'is_refund'     => 0,                                       //是否已调用退款队列，默认0未调用
                            'order_time'    => date('Y-m-d H:i:s'),                     //下单时间
                            'pay_time'      => '0000-00-00 00:00:00',                   //付款时间
                            'fail_status'   => 0                                        //只记录参团失败状态
                        ];
                        
                        //插入拼团参与表数据
                        $fightgroup_join_rs = FightgroupJoin::insert_data($fightgroup_join_data);
                        
                        if(!$fightgroup_join_rs){
                            
                            throw new \Exception('开团失败');
                        } 
                        
                    }
                        
                    
                    
                }elseif($type == 2){//参团
                    
                    //拼团参与表数据
                    $fightgroup_join_data = [
                        'merchant_id'   => $merchant_id,                            //商户id
                        'fightgroup_id' => $fightgroup_id,                          //拼团活动id
                        'launch_id'     => $fightgroup_launch_info['id'],           //拼团发起表id
                        'item_id'       => $fightgroup_item_id,                     //拼团子表id
                        'member_id'     => $member_id,                              //会员id 
                        'nickname'      => $member_info['name'],                    //会员昵称
                        'avatar'        => $member_info['avatar'],                  //会员头像
                        'tuan_price'    => $fightgroup_item_info['ladder_price'],   //拼团价格
                        'num'           => $quantity,                               //购买件数
                        'order_id'      => $createorder_rs['data']['order_id'],     //订单id
                        'order_sn'      => $createorder_rs['data']['order_sn'],     //订单号
                        'status'        => 0,                                       //0:待支付 1:已支付 2:失败(超时未支付) 3失败(库存不足超卖) 4未成团 5已成团超卖 6:参团成功 7未成团（手动结束）8失败（非进行中（活动、阶梯、团）超卖）
                        'is_captain'    => 0,                                       //0:团员 1:团长
                        'is_refund'     => 0,                                       //是否已调用退款队列，默认0未调用
                        'order_time'    => date('Y-m-d H:i:s'),                     //下单时间
                        'pay_time'      => '0000-00-00 00:00:00',                   //付款时间
                        'fail_status'   => 0                                        //只记录参团失败状态
                    ];
                    
                    //插入拼团参与表数据
                    $fightgroup_join_rs = FightgroupJoin::insert_data($fightgroup_join_data);
                    
                    if(!$fightgroup_join_rs){
                        
                        throw new \Exception('参团失败');
                    } 
                    
                    
                }
                
                
            }
            
            
            //订单信息
            $data['order_id'] = $createorder_rs['data']['order_id'];
            $data['order_sn'] = $createorder_rs['data']['order_sn'];
            
            
            DB::commit();
            return ['errcode' => 0,'errmsg' => 'ok','data' => $data];
            
        }catch (\Exception $e){
            
            DB::rollBack();
            return ['errcode' => 60018,'errmsg' => $e->getMessage()];
        }
        
        
        
    }
    
    /**
     * 测试
     * 开团或参团支付成功相关处理（测试）
     */
    public function fightgroupbacktest(Request $request){
        
        //参数
        $params = $request->all();
        
        //测试类型，1：开团或参团支付成功相关处理，2：订单取消相关处理（超时未支付自动取消或会员主动取消）
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
            
            
        }
        
        
        
    }
    
    
    
}

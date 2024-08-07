<?php
namespace App\Http\Controllers\Admin\Fightgroup;


use App\Http\Controllers\Controller;
use App\Models\AloneActivityRecode;
use App\Models\Fightgroup;
use App\Models\FightgroupItem;
use App\Models\FightgroupJoin;
use App\Models\FightgroupLadder;
use App\Models\FightgroupLaunch;
use App\Models\FightgroupRefund;
use App\Models\FightgroupStock;
use App\Models\Goods;
use App\Models\GoodsProp;
use App\Models\GoodsSpec;
use App\Models\OrderRefund;
use App\Models\Prop;
use App\Services\FightgroupService;
use App\Services\GoodsService;
use App\Services\AloneActivityRecodeService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\WeixinQrcode;

class TuanController extends Controller
{

    private $merchant_id;

    protected $initiate_status = array(
        PIN_INIT_SUBMIT   => '开团未支付',
        PIN_INIT_ACTIVE  => '拼团中',
        PIN_INIT_SUCCESS  => '成功',
        PIN_INIT_FAIL  => '失败(团长未支付)',
        PIN_INIT_FAIL_STOCK  => '失败(库存不足)',
        PIN_INIT_FAIL_END  => '失败',
        PIN_INIT_FAIL_MERCHANT=>'失败(商家终止)',
        PIN_INIT_FAIL_NOTWORKING=>'开团失败(超卖)'
    );

    protected $initiate_join_status = array(
        PIN_JOIN_SUBMIT => '待支付',
        PIN_JOIN_PAID => '支付成功',
        PIN_JOIN_FAIL => '失败(超时未支付)',
        PIN_JOIN_FAIL_STOCK => '失败(库存不足)',
        PIN_JOIN_FAIL_END => '未成团',
        PIN_JOIN_FAIL_EXCEED => '参团失败(名额已满)',
        PIN_JOIN_SUCCESS => '参团成功',
        PIN_JOIN_FAIL_MERCHANT => '失败(手动)',
        PIN_JOIN_FAIL_NOTWORKING => '失败(超卖)'
    );

    protected $ladder_status = array(
        PIN_LADDER_SUBMIT => '未开始',
        PIN_LADDER_ACTIVE => '拼团中',
        PIN_LADDER_END => '已结束',
        PIN_LADDER_STOCK_END => '已结束(库存不足)',
        PIN_LADDER_MERCHANT_END => '已结束(手动)'
    );

    //分享活动文案
    protected $shareActivity = ['name'=>'原价￥{{最高价}}，现价￥{{最低价}}起，参加“{{拼团活动名称}}”即可享受'];

    //分享详情文案
    protected $shareDetail = ['name'=>'我已经参加了“{{团长名称}}”发起的拼团。'];

    public function __construct(FightgroupService $fightgroupService,GoodsService $goodsService)
    {
        $this->merchant_id = Auth::user()->merchant_id;
        $this->fightgroupService = $fightgroupService;
        $this->goodsService = $goodsService;
    }

    /**
     * 创建拼团
     */
    public function postTuan(Request $request){
        $params = $request->all();
        $goodsId = isset($params['goods_id']) ? intval($params['goods_id']) : 0;
        $title = isset($params['title']) ? htmlspecialchars($params['title']) : '';        // 活动标题
        $startTime = isset($params['created_time']) && $params['created_time'] != '' ? $params['created_time'] : Carbon::now();  // 开始时间
        $remark = isset($params['note']) ? htmlspecialchars($params['note']) : '';         // 商家说明
        $pinItem = isset($params['items']) && is_array($params['items']) ? $params['items'] : '';                 // 活动阶梯
        $goodsSetting = isset($params['goods_setting']) && is_array($params['goods_setting']) ? $params['goods_setting'] : '';       // 拼团价格设置
        $joinLimit = isset($params['setting']) ? intval($params['setting']) : 0;
        $share_activity = isset($params['share_activity']) && $params['share_activity'] ? $params['share_activity'] : $this->shareActivity;//活动分享  json {"name":"标题"}
        $share_detail = isset($params['share_detail']) && $params['share_detail'] ? $params['share_detail'] : $this->shareDetail;//活动详情分享  json {"name":"标题"}

        
        //活动提醒开关
        $is_remind = isset($params['is_onoff']) ? intval($params['is_onoff']) : 0;
        
        
        $startTime = date('Y-m-d H:i:s',strtotime($startTime));//防止出现24点问题
        $year = date('Y',strtotime($startTime));
        if($year>2039){
            return Response::json(['errcode'=>1,'errmsg'=>'开始时间不能超过2039年']);
        }
        if($year==1970){
            return Response::json(['errcode'=>2,'errmsg'=>'开始时间有误']);
        }

        $goodsInfo = $this->checkGoods($goodsId);
        if(!$goodsInfo) {
            return Response::json(['errcode'=>3,'errmsg'=>'请添加有效的活动商品']);
        }

        //验证正在参加秒杀或者拼团的商品不能再次参加（含未开始的活动）
        $goods_info = Goods::get_data_by_id($goodsId, $this->merchant_id);
        if (!empty($goods_info) && $goods_info->is_delete == 1 && $goods_info->onsale == 1) {
            $AloneActivityRecodeService = new AloneActivityRecodeService();
            $alone_res = $AloneActivityRecodeService->getCreated([$goodsId], $this->merchant_id);
            if (!empty($alone_res) && !empty($alone_res[0])) {
                if ($alone_res[0]['act_type'] == 'seckill') {
                    return Response::json(['errcode' => 3, 'errmsg' => '已参加秒杀活动，请重新选择其它商品创建。']);
                } elseif ($alone_res[0]['act_type'] == 'tuan') {
                    return Response::json(['errcode' => 3, 'errmsg' => '已参加团购活动，请重新选择其它商品创建。']);
                } else {
                    return Response::json(['errcode' => 3, 'errmsg' => '已参加营销活动，请重新选择其它商品创建。']);
                }
            }
        }

        $goodsProps = '';
        if($goodsInfo['is_sku'] == 1) {
            $propsInfo = $this->getGoodsProps($goodsId);
            if(isset($propsInfo['goods_props']) && !empty($propsInfo['goods_props'])) {
                $goodsProps = json_encode($propsInfo['goods_props']);
            }
        }

        if(!$title || $startTime == '0000-00-00 00:00:00' || empty($pinItem) || empty($goodsSetting)) {
            return Response::json(['errcode'=>4,'errmsg'=>'参数错误']);
        }

        $pnums = [];
        foreach($pinItem as $key => $_pItem) {
            if (!preg_match("/^[0-9]+$/", $_pItem['pin_num'])) {
                return Response::json(['errcode'=>5,'errmsg'=>'拼团人数设置错误，请返回修改']);
            }
            if (!preg_match('/^([+]?[0-9][\d]{0,15}|0)?$/', $_pItem['expire_in'])) {
                return Response::json(['errcode'=>6,'errmsg'=>'拼团时间格式错误或是设置过长，请返回修改']);
            }
            if($_pItem['expire_in'] > 168) {
                return Response::json(['errcode'=>7,'errmsg'=>'拼团时间至多设置168个小时']);
            }
            array_push($pnums, $_pItem['pin_num']);
        }

        foreach(array_count_values($pnums) as $_pnums) {
            if($_pnums > 1) {
                return Response::json(['errcode'=>8,'errmsg'=>'拼团阶梯人数设置重复，请返回修改']);
            }
        }

        foreach($goodsSetting as $setItem) {
            $isLimit = isset($setItem['is_limit']) ? intval($setItem['is_limit']) : 0;
            $activityStock = isset($setItem['activity_stock']) ? intval($setItem['activity_stock']) : 0;
            if($isLimit > $activityStock) {
                return Response::json(['errcode'=>9,'errmsg'=>'限购件数不能大于当前库存']);
            }
            if($goodsInfo['cquota'] > 0 && $isLimit > $goodsInfo['cquota']) {
                return Response::json(['errcode'=>10,'errmsg'=>'设置项不能超过当前商品限购件数']);
            }

            $pinPrice = isset($setItem['pin_price']) && is_array($setItem['pin_price']) ? $setItem['pin_price'] : '';
            if(!$pinPrice || empty($pinPrice)) {
                return Response::json(['errcode'=>11,'errmsg'=>'参数错误']);
            }
            foreach($pinPrice as $pinPriceItem) {
                if (!preg_match('/^([+]?[0-9][\d]{0,15}|0)?$/', $pinPriceItem['expire_in'])) {
                    return Response::json(['errcode'=>12,'errmsg'=>'Hacking Attempt']);
                }
            }

            //规格库存限制
            if($goodsInfo['is_sku'] == 0) {//单规格
                $goodsStock = Goods::where('id',$goodsId)->value('stock');
                if($setItem['activity_stock'] > $goodsStock){
                    return Response::json(['errcode' => 13, 'errmsg' => '不能超过商品总库存']);
                }
            }else{
                $product = GoodsSpec::where('id',$setItem['id'])->first();
                if($setItem['activity_stock'] > $product['stock']){
                    return Response::json(['errcode' => 14, 'errmsg' => '规格【'.$product['props_str'].'】的库存不能超过'.$product['stock']]);
                }
            }
        }

        $status = PIN_SUBMIT;
        if(strtotime($startTime) <= strtotime(Carbon::now())){
            $status = PIN_ACTIVE;
        }

        $tuanData = [
            'merchant_id' => $this->merchant_id,
            'status' => $status,
            'goods_id' => $goodsId,
            'is_sku' => $goodsInfo['is_sku'],
            'goods_price' => $goodsInfo['price'],
            'goods_title' => $goodsInfo['title'],
            'goods_img' => $goodsInfo['img'],
            'goods_props' => $goodsProps,
            'title' => $title,
            'start_time' => $startTime,
            'remark' => $remark,
            'join_limit' => $joinLimit,
            'share_activity' => json_encode($share_activity),
            'share_detail' => json_encode($share_detail),
            'created_time' => Carbon::now(),
            'updated_time' => Carbon::now(),
            'is_remind' => $is_remind
        ];

        $common = [
            'pin_item' => $pinItem,
            'is_sku' => $goodsInfo['is_sku'],
            'goods_setting' => $goodsSetting,
            'goods_id' => $goodsId,
            'status' => $status
        ];


        DB::beginTransaction();
        try{
            $tuanId = Fightgroup::insertGetId($tuanData);
            if($tuanId) {
                $ladderArr = [];
                foreach($common['pin_item'] as $_pinItem) {
                    $ladderData = [
                        'fightgroup_id' => $tuanId,
                        'merchant_id' => $this->merchant_id,
                        'status' => $common['status'],
                        'cpeoples' => $_pinItem['pin_num'],
                        'expire_in' => $_pinItem['expire_in'],
                        'created_time' => Carbon::now(),
                        'updated_time' => Carbon::now()
                    ];
                    $ladderArr[$ladderData['cpeoples']] = FightgroupLadder::insertGetId($ladderData);
                }

                if($common['is_sku'] == 1) {
                    $stocks = 0;
                    //多规格库存锁定
                    $aloneData = [
                        'merchant_id' => $this->merchant_id,
                        'goods_id' => $common['goods_id'],
                        //'goods_spec_id' => $list['id'],
                        'alone_id' => $tuanId,
                        'start_time' => $startTime,
                        //'stock_lock'=> $list['activity_stock'],
                        'act_type' => 'tuan',
                        'created_time' => Carbon::now(),
                        'updated_time' => Carbon::now()
                    ];
                    $tmp = AloneActivityRecode::insertGetId($aloneData);
                    if(!$tmp){
                        throw new \Exception('锁定活动库存失败');
                    }
                    foreach($common['goods_setting'] as $list) {
                        $stockData = [
                            'merchant_id'=>$this->merchant_id,
                            'fightgroup_id' => $tuanId,
                            'goods_id' => $common['goods_id'],
                            'is_sku' => $common['is_sku'],
                            'spec_id' => isset($list['id']) ? $list['id'] : '',
                            'props' => isset($list['props']) ? $list['props'] : '',
                            'props_str' => isset($list['props_str']) ? $list['props_str'] : '',
                            'limit_num' => $list['is_limit'],
                            'total_stock' => $list['activity_stock'],
                            'stock' => $list['activity_stock'],
                            'created_time' => Carbon::now(),
                            'updated_time' => Carbon::now()
                        ];
                        $stockId = FightgroupStock::insertGetId($stockData);

                        //扣除商品库存-调用公共方法
                        $desStock = [
                            'merchant_id'=>$this->merchant_id,
                            'stock_num'=>$list['activity_stock'],
                            'goods_id'=>$common['goods_id'],
                            'goods_spec_id'=>isset($list['id']) ? $list['id'] : 0,
                            'activity'=>''
                        ];
                        $this->goodsService->desStock($desStock);

                        foreach($list['pin_price'] as $_list) {
                            if(isset($ladderArr[$_list['pin_num']]) && $ladderArr[$_list['pin_num']]) {
                                $ladderId = $ladderArr[$_list['pin_num']];
                            }else{
                                throw new \Exception('数据异常,活动创建失败');
                            }

                            $itemData = [
                                'merchant_id' => $this->merchant_id,
                                'fightgroup_id' => $tuanId,
                                'ladder_id' => $ladderId,
                                'stock_id' => $stockId,
                                'goods_id' => $common['goods_id'],
                                'is_sku' => $common['is_sku'],
                                'spec_id' => $list['id'],
                                'goods_sn' => isset($list['product_sn']) ? $list['product_sn'] : '',
                                'barcode' => $list['barcode'],
                                'props' => $list['props'],
                                'props_str' => $list['props_str'],
                                'img' => isset($list['img']) ? $list['img'] : '',
                                'product_price' => $list['price'],
                                'ladder_price' => $_list['price'],
                                'limit_num' => $list['is_limit'],
                                'created_time' => Carbon::now(),
                                'updated_time' => Carbon::now()
                            ];
                            FightgroupItem::insertGetId($itemData);
                        }
                        $stocks = $stocks + $list['activity_stock'];
                    }
                }else{
                    $stocks = 0;
                    //单规格库存锁定
                    $aloneData = [
                        'merchant_id' => $this->merchant_id,
                        'goods_id' => $common['goods_id'],
                        'alone_id' => $tuanId,
                        'start_time' => $startTime,
                        //'stock_lock'=>$list['activity_stock'],
                        'act_type' => 'tuan'
                    ];
                    AloneActivityRecode::create($aloneData);

                    foreach($common['goods_setting'] as $list) {
                        $stockData = [
                            'merchant_id'=>$this->merchant_id,
                            'fightgroup_id' => $tuanId,
                            'goods_id' => $common['goods_id'],
                            'is_sku' => $common['is_sku'],
                            'limit_num' => $list['is_limit'],
                            'total_stock' => $list['activity_stock'],
                            'stock' => $list['activity_stock'],
                            'created_time' => Carbon::now(),
                            'updated_time' => Carbon::now()
                        ];
                        $stockId = FightgroupStock::insertGetId($stockData);

                        //扣除商品库存-调用公共方法
                        $desStock = [
                            'merchant_id'=>$this->merchant_id,
                            'stock_num'=>$list['activity_stock'],
                            'goods_id'=>$common['goods_id'],
                            'goods_spec_id'=>0,
                            'activity'=>''
                        ];
                        $this->goodsService->desStock($desStock);

                        foreach($list['pin_price'] as $_list) {
                            if(isset($ladderArr[$_list['pin_num']]) && $ladderArr[$_list['pin_num']]) {
                                $ladderId = $ladderArr[$_list['pin_num']];
                            }else{
                                throw new \Exception('数据异常,活动创建失败');
                            }

                            $itemData = [
                                'merchant_id' => $this->merchant_id,
                                'fightgroup_id' => $tuanId,
                                'ladder_id' => $ladderId,
                                'stock_id' => $stockId,
                                'goods_id' => $common['goods_id'],
                                'is_sku' => $common['is_sku'],
                                'product_price' => $list['price'],
                                'ladder_price' => $_list['price'],
                                'limit_num' => $list['is_limit'],
                                'created_time' => Carbon::now(),
                                'updated_time' => Carbon::now()
                            ];
                            FightgroupItem::insertGetId($itemData);
                        }
                        $stocks = $stocks + $list['activity_stock'];
                    }
                }
            }else{
                throw new \Exception('活动创建失败');
            }
            DB::commit();
            return Response::json(['errcode' => 0, 'errmsg' => '活动创建成功']);
        }catch (\Exception $e) {
            DB::rollBack();
            return Response::json(['status' => 15, 'errmsg' => $e->getMessage()]);
        }
    }

    /**
     * 库存明细
     */
    public function getStocks($tuanId){
        $tuanStore = FightgroupStock::select('id','props','stock','total_stock','sell_stock')->where('fightgroup_id',$tuanId)->get();
        if($tuanStore){
            $goods_props = Fightgroup::where('id',$tuanId)->pluck('goods_props');
            foreach($tuanStore as &$v){
                $sell_num = FightgroupItem::where('fightgroup_id',$tuanId)->where('stock_id',$v['id'])->sum('sell_num');
                $v['ladder_stock'] = $v['total_stock'];
                $v['sell_num'] = $sell_num;
                $v['surplus_num'] = $v['stock'];
                $props_str = $this->convertGoodsProp($goods_props,$v['props']);
                $v['props_str'] = $props_str ? $props_str : '单规格';
            }
        }
        return Response::json(['errcode' => 0, 'data' => $tuanStore]);
    }

    /**
     * 开团列表
     */
    public function getLaunchs($tuanid,Request $request){
        $data = $request->all();
        // 检索参数
        $offset      = isset($data['offset']) ? $data['offset'] : 0;
        $limit       = isset($data['limit']) ? $data['limit'] : 10;
        $status      = isset($data['status']) && is_numeric($data['status']) ? intval($data['status']) : 'all';
        //所需人数(几人团)
        $nums    = isset($data['nums']) ? $data['nums'] : '';

        $startDate   = isset($data['startDate']) ? trim($data['startDate']) : '';
        $endDate     = isset($data['endDate']) ? trim($data['endDate']).' 23:59:59' : date('Y-m-d',time()).' 23:59:59';


        // 搜索条件
        $wheres = array(
            array('column' => 'merchant_id', 'value' => $this->merchant_id, 'operator' => '='),
            array('column' => 'fightgroup_id', 'value' => $tuanid, 'operator' => '=')
        );

        //拼团状态
        if (is_numeric($status)) {
            if($status==0){//开团待支付
                $condition = array(
                    array('column' => 'status', 'value' => PIN_INIT_SUBMIT, 'operator' => '=')
                );
            }else if($status==1){//拼团中
                $condition = array(
                    array('column' => 'status', 'value' => PIN_INIT_ACTIVE, 'operator' => '=')
                );
            }else if($status==2){//成功
                $condition = array(
                    array('column' => 'status', 'value' => PIN_INIT_SUCCESS, 'operator' => '=')
                );
            }else if($status==3){//失败
                $condition = array(
                    array('column' => 'status', 'value' => PIN_INIT_FAIL, 'operator' => '>='),
                    array('column' => 'status', 'value' => PIN_INIT_FAIL_MERCHANT, 'operator' => '<=')
                );
            }
            $wheres = array_merge($condition, $wheres);
        }
        //几人团
        if ($nums && $nums!="all"){
            $condition = array(array('column' => 'nums', 'value' => $nums, 'operator' => '='));
            $wheres = array_merge($condition, $wheres);
        }

        //团长昵称
        $nickname = isset($data['nickname']) ? trim($data['nickname']) : '';
        if($nickname){
            $condition = array(array('column' => 'nickname', 'value' => $nickname, 'operator' => '='));
            $wheres = array_merge($condition, $wheres);
        }
        //开团时间
        if($startDate && $endDate){
            $condition = array(
                array('column' => 'created_time', 'value' => $startDate, 'operator' => '>='),
                array('column' => 'created_time', 'value' => $endDate, 'operator' => '<=')
            );
            $wheres = array_merge($condition, $wheres);
        }

        //根据订单号搜索
        $order_sn = isset($data['order_sn']) && $data['order_sn'] ? trim($data['order_sn']) : '';
        if($order_sn){
            $launch_id = FightgroupJoin::where('order_sn',$order_sn)->value('launch_id');
            $condition = array(array('column' => 'id', 'value' => $launch_id, 'operator' => '='));
            $wheres = array_merge($condition, $wheres);
        }

        $query = FightgroupLaunch::select('*');

        foreach ($wheres as $value) {
            $query->where($value['column'], $value['operator'], $value['value']);
        }
        $count = $query->count();

        $initiates = array();

        if($count > 0){
            $initiates = $query->orderBy('id', 'DESC')->skip($offset)->take($limit)->get();
            foreach($initiates as $k => $i){
                $ladder = FightgroupLadder::where('id',$i['ladder_id'])->first();
                //拼团需要人数
                $initiates[$k]['cpeoples'] = $ladder['cpeoples'];
                //所需时间
                $initiates[$k]['expire_in'] = $ladder['expire_in'];
                $initiates[$k]['is_edit_time'] = in_array($i['status'],[PIN_INIT_SUBMIT,PIN_INIT_ACTIVE]) ? 1 : 0;//团长开团未支付和拼团中允许修改时间
                //拼团状态
                $initiates[$k]['status_name'] = $this->initiate_status[$i['status']];

            }
        }
        return Response::json(['errcode' => 0, '_count' => $count,'data'=>$initiates]);
    }

    /**
     * 参团列表
     */
    public function getJoins($launch_id,Request $request){
        $params = $request->all();
        if(!$launch_id){
            return Response::json(['errcode'=>1,'errmsg'=>'非法请求']);
        }
        $order_sn = isset($params['order_sn']) && $params['order_sn'] ? $params['order_sn'] : '';//订单号
        $nickname = isset($params['nickname']) && $params['nickname'] ? $params['nickname'] : '';//参与人昵称
        $offset   = isset($params['offset']) ? $params['offset'] : 0;
        $limit    = isset($params['limit']) ? $params['limit'] : 10;
        $data = array('_count'=>0,'data'=>[]);
        $query = FightgroupJoin::select('id','avatar','nickname','num','item_id','pay_time','order_id','order_sn','status','is_captain')->where('launch_id',$launch_id)->where('merchant_id',$this->merchant_id);
        //按订单号搜索
        if($order_sn){
            $query->where('order_sn',$order_sn);
        }
        //按参与人昵称搜索
        if($nickname){
            $query->where('nickname',$nickname);
        }
        $data['_count'] = $query->count();
        if($data['_count']){
            $data['data'] = $query->skip($offset)->take($limit)->get();
            foreach($data['data'] as &$v){
                $itemInfo = FightgroupItem::where('id',$v['item_id'])->first();
                $v['props_str'] = isset($itemInfo['props_str']) ? $itemInfo['props_str'] : '单规格';
                $v['price'] = isset($itemInfo['ladder_price']) ? $itemInfo['ladder_price'] : 0;//拼团单价
                $v['total_price'] = $v['price']*$v['num'];//拼团总价
                $v['status_str'] = $this->initiate_join_status[$v['status']];
                if($v['pay_at']=="0000-00-00 00:00:00"){
                    $v['pay_at'] = "--";
                }

                //已经退款
                if($v['is_refund']>0){
                    $v['refund_id'] = OrderRefund::where('order_id',$v['order_id'])->pluck('id');
                }
            }
        }
        return Response::json(['errcode'=>0,'_count'=>$data['_count'],'data'=>$data['data']]);
    }

    /**
     * 手动结束拼团
     */
    public function closeLadder($ladderId){
        $ladderInfo = FightgroupLadder::where('id',$ladderId)->where('merchant_id',$this->merchant_id)->first();
        if(!$ladderInfo){
            return Response::json(['errcode'=>1,'errmsg'=>'拼团活动记录不存在']);
        }
        //等状态全部确定后再做
        //1、修改拼团阶梯表状态
        if(!in_array($ladderInfo['status'],[PIN_LADDER_SUBMIT,PIN_LADDER_ACTIVE])){
            return Response::json(['errcode'=>2,'errmsg'=>'当前状态不允许关闭']);
        }
        $ladderInfo->status = PIN_LADDER_MERCHANT_END;
        if($ladderInfo->save()){
            $initiates = FightgroupLaunch::where('ladder_id',$ladderId)->whereIn('status',[PIN_INIT_SUBMIT,PIN_INIT_ACTIVE])->get()->toArray();
            if($initiates){
                //2、修改拼团发起表状态
                $fightgroup_launch_arr = FightgroupLaunch::where('ladder_id',$ladderId)->whereIn('status',[PIN_INIT_ACTIVE])->get();
                if($fightgroup_launch_arr){
                    foreach($fightgroup_launch_arr as $fls) {
                        FightgroupLaunch::update_data($fls['id'], $this->merchant_id, array('status' => PIN_INIT_FAIL_MERCHANT));
                    }
                }

                //3、修改拼团参与人表状态并执行退款操作
                foreach($initiates as $k=>$v) {
                    $this->fightgroupService->launchRefund($v['id'],1,$this->merchant_id);
                }
            }
            //4、判断商品阶梯状态-解除商品锁定
            $goLadderCount = FightgroupLadder::where('fightgroup_id',$ladderInfo['fightgroup_id'])->whereIn('status',[PIN_LADDER_SUBMIT,PIN_LADDER_ACTIVE])->count();
            if($goLadderCount==0){
                //结束拼团活动
                $fightgroup_arr = Fightgroup::where('id',$ladderInfo['fightgroup_id'])->whereIn('status',[PIN_SUBMIT,PIN_ACTIVE])->get();
                if($fightgroup_arr) {
                    foreach($fightgroup_arr as $far) {
                        Fightgroup::update_data($far['id'], $this->merchant_id, ['status' => PIN_MERCHANT_END, 'end_time' => Carbon::now()]);
                    }
                }

                //解除商品锁定
                AloneActivityRecode::where('alone_id',$ladderInfo['fightgroup_id'])->where('act_type','tuan')->where('finish_time','0000-00-00 00:00:00')->update(['finish_time'=>Carbon::now()]);

                //调用剩余库存的方法-公共
                $items = FightgroupItem::where('ladder_id',$ladderId)->get();
                if($items){
                    foreach($items as $k=>$v){
                        $total_stock = FightgroupStock::where('id',$v['stock_id'])->value('stock');
                        if($total_stock) {
                            $incStock = [
                                'merchant_id' => $this->merchant_id,
                                'stock_num' => $total_stock,
                                'goods_id' => $v['goods_id'],
                                'goods_spec_id' => $v['spec_id'],
                                'activity' => ''
                            ];
                            $this->goodsService->incStock($incStock);
                        }
                    }
                }

            }
            return Response::json(['errcode'=>0,'errmsg'=>'操作成功']);
        }
        return Response::json(['errcode'=>3,'errmsg'=>'操作失败']);
    }

    /**
     * 拼团列表
     */
    public function getFightgroups(Request $request){
        $params = $request->all();
        $title       = isset($params['title']) ? $params['title'] : '';//活动名称
        $goods_title = isset($params['goods_title']) ? $params['goods_title'] : '';//商品名称
        $offset   = isset($params['offset']) ? $params['offset'] : 0;
        $limit    = isset($params['limit']) ? $params['limit'] : 10;
        $sn_id    = isset($params['fightgroup_id']) ? $params['fightgroup_id'] : '';//编号
        $status   = isset($params['status']) ? $params['status'] : 0;//0全部 1未开始 2活动中 3已结束
        $query = Fightgroup::where('merchant_id',$this->merchant_id);
        //按编号搜索
        if($sn_id){
            $query->where('id',$sn_id);
        }
        //未开始
        if($status==1){
            $query->where('status',PIN_SUBMIT);
        }
        //活动中
        if($status==2){
            $query->where('status',PIN_ACTIVE);
        }
        //已结束
        if($status==3){
            $query->whereIn('status',[PIN_TIME_END,PIN_MERCHANT_END]);
        }

        //按活动名称搜索
        if($title){
            $query->where('title','like',"%$title%");
        }
        //按商品名称搜索
        if($goods_title){
            $query->where('goods_title','like',"%$goods_title%");
        }
        $data['_count'] = $query->count();
        $data['data']   = array();
        if($data['_count']){
            $data['data'] = $query->orderBy('id', 'desc')->skip($offset)->take($limit)->get();
            if($data['data']){
                foreach($data['data'] as &$v){
                    $tuanStock = FightgroupStock::where('fightgroup_id',$v['id'])->get()->toArray();
                    $total_stock = $surplus_num = 0;
                    if($tuanStock){
                        foreach($tuanStock as $ks=>$vs){
                            $total_stock+=$vs['total_stock'];
                            $surplus_num+=$vs['stock'];
                        }
                    }
                    $v['total_stock'] = $total_stock;//总库存
                    $v['surplus_num'] = $surplus_num;//剩余库存
                    $ladder = FightgroupLadder::where('fightgroup_id',$v['id'])->get();
                    if($ladder){
                        foreach($ladder as &$vs){
                            $vs['status_msg'] = $this->ladder_status[$vs['status']];
                        }
                    }
                    $v['ladder'] = $ladder;
                    //------------------------↓------------------------Qrcode start 
                    //是否生成二维码
                    //author :renruiqi@dodoca.com
                    if(isset($params['appid'])  && !empty(trim($params['appid'])) ){
                        $appid_qr = trim($params['appid']);
                        $v['is_qrcode'] = count(WeixinQrcode::check($v['id'],3,$appid_qr))>0 ? 1 :2; //二维码是否生成 1:是 2:否
                    }
                    //------------------------↑------------------------Qrcodeend
                }
            }
        }
        return Response::json(['errcode'=>0,'_count'=>$data['_count'],'data'=>$data['data']]);
    }

    /**
     * 20171213
     * changzhixian@dodoca.com
     * 拼团列表（装修用此接口）
     */
    public function fightgroupsGoodInfo(Request $request){
        $params = $request->all();
        $goods_title = isset($params['goods_title']) ? $params['goods_title'] : '';//商品名称
        $offset   = isset($params['offset']) ? $params['offset'] : 0;
        $limit    = isset($params['limit']) ? $params['limit'] : 10;
        $status   = isset($params['status']) ? $params['status'] : 0;//0全部 1未开始 2活动中 3已结束
        $query = Fightgroup::select('id','goods_id','goods_title','goods_price','goods_img')->where('merchant_id',$this->merchant_id);

        //未开始，活动中
        if($status==3){
            $query->whereIn('status',[PIN_SUBMIT,PIN_ACTIVE]);
        }

        //按商品名称搜索
        if($goods_title){
            $query->where('goods_title','like',"%$goods_title%");
        }
        $data['_count'] = $query->count();
        $data['data']   = array();
        if($data['_count']){
            $data['data'] = $query->orderBy('id', 'desc')->skip($offset)->take($limit)->get();

        }
        return Response::json(['errcode'=>0,'_count'=>$data['_count'],'data'=>$data['data']]);
    }
    /**
     * 拼团数据统计
     */
    public function getStatis($fightgroup_id){
        $githtgroupInfo = Fightgroup::where('id',$fightgroup_id)->where('merchant_id',$this->merchant_id)->first();
        if(!$githtgroupInfo){
            return Response::json(['errcode'=>1,'errmsg'=>'拼团不存在']);
        }

        //全部阶梯
        $ladders = FightgroupLadder::select('id','cpeoples')->where('fightgroup_id',$fightgroup_id)->get()->toArray();

        $items = FightgroupItem::select('id','props','props_str','spec_id','stock_id')->where('fightgroup_id',$fightgroup_id)->groupBy('spec_id')->get()->toArray();
        if($items){
            foreach ($items as $k=>$v){
                $ladder = array();
                $total_sell_num = 0;//销量
                foreach ($ladders as $ks=>$vs){
                    $item = FightgroupItem::where('spec_id',$v['spec_id'])->where('ladder_id',$vs['id'])->first();
                    $total_sell_num+=$item['sell_num'];
                    $ladder[] = ['cpeoples'=>$vs['cpeoples'],'finished_num'=>$item['finished_num'],'virtual_num'=>$item['virtual_num']];
                }
                $items[$k]['ladder'] = $ladder;
                $items[$k]['sell_num'] = FightgroupStock::where('id',$v['stock_id'])->value('sell_stock');
                //商品规格名转换
                $items[$k]['props_str'] = $v['props_str'] ? $this->convertGoodsProp($githtgroupInfo['goods_props'],$v['props']) : '--';
            }
        }
        return Response::json(['errcode'=>0,'data'=>$items]);
    }

    /**
     * 拼团活动详情
     */
    public function getFightgroup($fightgroup_id){
        $tuanInfo = Fightgroup::select('id', 'goods_id', 'is_sku', 'goods_price', 'goods_title', 'goods_img', 'goods_props AS props', 'title', 'status', 'start_time', 'remark', 'join_limit', 'created_time','share_activity','share_detail','is_remind')
            ->where(['merchant_id' => $this->merchant_id, 'id' => $fightgroup_id])
            ->first();
        if(!$tuanInfo) {
            return Response::json(['errcode'=>1,'errmsg'=>'信息不存在']);
        }
        $tuanInfo['stock'] = Goods::where('id', $tuanInfo['goods_id'])->value('stock');

        $tuanInfo['goods_props'] = [];
        if(isset($tuanInfo['is_sku']) && $tuanInfo['is_sku'] == 1) {
            $tuanInfo['goods_props'] = json_decode($tuanInfo['props'], true);
        }
        unset($tuanInfo['props']);

        $stockInfo = FightgroupStock::select('id', 'goods_id', 'is_sku', 'spec_id', 'props', 'props_str', 'stock')
            ->where('fightgroup_id', $fightgroup_id)
            ->get();
        $tuanInfo['stock_info'] = $stockInfo;

        $tuanInfo['products'] = FightgroupItem::select('id', 'stock_id', 'ladder_id', 'goods_id', 'is_sku', 'spec_id', 'barcode', 'props', 'props_str', 'product_price', 'ladder_price', 'sell_num', 'limit_num', 'created_num', 'finished_num', 'failed_num')
            ->where(['merchant_id' => $this->merchant_id, 'fightgroup_id' => $fightgroup_id])
            ->get();

        $tuanInfo['ladder'] = FightgroupLadder::select('id', 'status', 'cpeoples', 'expire_in', 'created_num', 'finished_num', 'failed_num')
            ->where(['merchant_id' => $this->merchant_id, 'fightgroup_id' => $fightgroup_id])
            ->get();

        foreach($tuanInfo['products'] as &$val) {
            $val['cpeoples'] = FightgroupLadder::where('id', $val['ladder_id'])->value('cpeoples');
        }
        foreach($tuanInfo['ladder'] as &$val) {
            $val['item'] = FightgroupItem::select('id', 'stock_id', 'goods_id', 'is_sku', 'spec_id', 'barcode', 'props', 'props_str', 'product_price', 'ladder_price', 'sell_num', 'limit_num', 'created_num', 'finished_num', 'failed_num')
                ->where(['merchant_id' => $this->merchant_id, 'ladder_id' => $val['id']])
                ->get();
        }
        $tuanInfo['share_activity'] = $tuanInfo['share_activity'] ? json_decode($tuanInfo['share_activity'],true) : $this->shareActivity;
        $tuanInfo['share_detail'] = $tuanInfo['share_detail'] ? json_decode($tuanInfo['share_detail'],true) : $this->shareDetail;
        
        
        $tuanInfo['is_onoff'] = $tuanInfo['is_remind'];//活动开始提醒：1-提醒，0-不提醒
        
        return Response::json(['errcode'=>0,'data'=>$tuanInfo]);
    }

    /**
     * 拼团退款列表
     */
    public function getRefunds(Request $request){
        $params = $request->all();
        $launch_id = isset($params['launch_id']) && $params['launch_id'] ? $params['launch_id'] : '';
        $fightgroup_id = isset($params['fightgroup_id']) && $params['fightgroup_id'] ? $params['fightgroup_id'] : '';
        if(!$fightgroup_id){
            return Response::json(['errcode'=>1,'errmsg'=>'非法请求']);
        }
        //支付方式
        $payway = isset($params['payway']) && $params['payway'] ? $params['payway'] : '';
        $nickname = isset($params['nickname']) && $params['nickname'] ? $params['nickname'] : '';//团员昵称
        //$status = isset($params['status']) && $params['status'] ? $params['status'] : '';//退款状态
        $offset   = isset($params['offset']) ? $params['offset'] : 0;
        $limit    = isset($params['limit']) ? $params['limit'] : 10;
        $query = FightgroupRefund::where('fightgroup_id',$fightgroup_id)->where('merchant_id',$this->merchant_id);
        if($launch_id){
            $query->where('launch_id',$launch_id);
        }
        //按支付方式搜索
        if($payway){
            $query->where('payway',$payway);
        }
        //按团员昵称搜索
        if($nickname){
            $query->where('nickname',$nickname);
        }
        
        //退款状态
        if(isset($params['status']) && is_numeric($params['status'])){
            $status = (int)$params['status'];
        }else{
            $status = 'all';
        }
        
        if(is_numeric($status)){
            $query->where('status',$status);
        }
        
        
        $data = ['_count'=>0,'data'=>[]];
        $data['_count'] = $query->count();
        if($data['_count']){
            $data['data'] = $query->skip($offset)->take($limit)->get();
            if($data['data']){
                foreach ($data['data'] as &$v){
                    if($v['status']==1){
                        $v['status_name'] = '退款失败';
                    }else if($v['status']==2){
                        $v['status_name'] = '退款成功';
                    }else{
                        $v['status_name'] = '申请退款中';
                    }
                    $v['refund_id'] = OrderRefund::where('order_id',$v['order_id'])->pluck('id');
                }
            }
        }
        return Response::json(['errcode'=>0,'_count'=>$data['_count'],'data'=>$data['data']]);
    }

    /**
     * 编辑拼团
     */
    public function putFightgroup($id,Request $request){
        $params = $request->all();
        $goodsId = isset($params['goods_id']) ? intval($params['goods_id']) : 0;
        $title = isset($params['title']) && $params['title'] ? htmlspecialchars($params['title']) : '';         // 活动标题
        $remark = isset($params['remark']) && $params['remark'] ? htmlspecialchars($params['remark']) : '';     // 活动说明
        $joinlimit = isset($params['setting']) ? intval($params['setting']) : 1;
        $ladder = isset($params['ladder']) && !empty($params['ladder']) ? $params['ladder'] : '';
        $goodsSetting = isset($params['goods_setting']) && !empty($params['goods_setting']) ? $params['goods_setting'] : '';
        $share_activity = isset($params['share_activity']) && $params['share_activity'] ? $params['share_activity'] : $this->shareActivity;//活动分享  json {"name":"标题"}
        $share_detail = isset($params['share_detail']) && $params['share_detail'] ? $params['share_detail'] : $this->shareDetail;//活动详情分享  json {"name":"标题"}

        
        //活动提醒开关
        $is_remind = isset($params['is_onoff']) ? intval($params['is_onoff']) : 0;
        
        
        
        if(!$title || !$ladder || !$goodsSetting) {
            return Response::json(['errcode' => 1, 'errmsg' => '参数错误']);
        }

        //$tuanInfo = Fightgroup::where(['id'=>$id, 'merchant_id'=>$this->merchant_id])->whereIn('status', [0, 10])->first();
        //if($tuanInfo['goods_id'] != $goodsId) {
        //    return Response::json(['errcode' => 2, 'errmsg' => '参数错当前活动已失效,编辑失败误']);
        //}
        $ladderInfo = FightgroupLadder::where(['fightgroup_id'=>$id, 'merchant_id'=>$this->merchant_id])->get();

        Fightgroup::update_data($id,$this->merchant_id,['title'=>$title, 'remark'=>$remark, 'join_limit'=>$joinlimit,'share_activity'=>json_encode($share_activity),'share_detail'=>json_encode($share_detail),'is_remind'=>$is_remind]);

        $setLadder = [];
        foreach($ladder as $_item) {
            $setLadder[$_item['cpeoples']] = $_item['expire_in'];
        }

        foreach($ladderInfo as $_ladderItem) {
            if(isset($setLadder[$_ladderItem['cpeoples']])) {
                FightgroupLadder::update_data($_ladderItem['id'],$this->merchant_id,['expire_in'=>$setLadder[$_ladderItem['cpeoples']]]);

            }
        }
        //$totalStock = FightgroupStock::where('fightgroup_id', $id)->sum('stock');
        //Cache::forever('tuanV3.0_.'.$id, $totalStock);
        //已锁定库存
        $aloneActive = AloneActivityRecode::where(['alone_id'=>$id,'act_type'=>'tuan','finish_time'=>'0000-00-00 00:00:00'])->get();
        $settings = [];
        foreach($goodsSetting as $list) {
            $tuanStock = FightgroupStock::where('id', $list['stock_id'])->first();
            //库存是否足够,值为正 则是增加的库存数,负数为减少的库存量
            $_stock = $list['stock']-$tuanStock['stock'];

            //增加库存时 判断是否足够
            if($_stock > 0){
                //单规格
                if($list['is_sku'] == 0){
                    //已锁定库存
                    foreach($aloneActive as $alone){
                        $goods_stock = Goods::where('id',$alone['goods_id'])->value('stock');
                        //商品剩余库存
                        //$_goodsStock = $goods_stock-$alone['stock_lock'];
                        $_goodsStock = $goods_stock;
                        if($_stock > $_goodsStock){
                            //return Response::json(['errcode' => 3, 'errmsg' => '库存不足,请先增加商品库存数']);
                        }
                        //增加锁定库存
                        //AloneActivityRecode::where(['id'=>$alone['id']])->increment('stock_lock',$_stock);
                    }
                }else{
                    $product = GoodsSpec::where('id',$tuanStock['spec_id'])->first();
                    if($product['stock'] == 0){
                        //return Response::json(['errcode' => 4, 'errmsg' => '库存不足,请先添加商品【 '.$product['props_str'].' 】的库存']);
                    }
                }
            }
            $settings[] = [
                'stock_id' => $list['stock_id'],
                'stock' => $list['stock'],
                'limit_num' => $list['limit_num']
            ];
        }
        if(!empty($settings)) {
            foreach($settings as $var) {
                $tuanStock = FightgroupStock::where('id', $var['stock_id'])->first();
                //值为正 则是增加的库存数,负数为减少的库存量
                $_stock = $var['stock']-$tuanStock['stock'];
                $totalStock = $tuanStock['total_stock']+$_stock;
                //修改团库存
                FightgroupStock::update_data($var['stock_id'],$this->merchant_id,['total_stock'=>$totalStock, 'stock'=>$var['stock'],'limit_num'=>$var['limit_num']]);
                $fightgroup_item_arr = FightgroupItem::where(['merchant_id'=>$this->merchant_id, 'stock_id'=>$var['stock_id']])->get();
                if($fightgroup_item_arr){
                    foreach($fightgroup_item_arr as $fia){
                        FightgroupItem::update_data($fia['id'],$this->merchant_id,['limit_num'=>$var['limit_num']]);
                    }
                }
            }
        }
        return Response::json(['errcode' => 0, 'errmsg' => '活动编辑成功']);
    }

    /**
     * 商品规格
     */
    public function getGoodsProp($goodsId){
        $result = $this->getGoodsProps($goodsId);
        if(isset($result['errcode'])){
            return Response::json($result);
        }else{
            return Response::json(['errcode'=>0,'data'=>$result]);
        }
    }

    /**
     * 修改拼团时间
     */
    public function putExtendLaunch($launch_id,Request $request){
        $params = $request->all();
        $end_time = isset($params['end_time']) && $params['end_time'] ? $params['end_time'] : '';//结束时间
        if(!$end_time){
            return Response::json(['errcode'=>5,'errmsg'=>'缺少参数']);
        }
        $end_time = date('Y-m-d H:i:s',strtotime($end_time));//防止出现24点问题
        $year = date('Y',strtotime($end_time));
        if($year>2039){
            return Response::json(['errcode'=>4,'errmsg'=>'不能超过2039年']);
        }
        if(strtotime($end_time) <= time()){
            return Response::json(['errcode'=>3,'errmsg'=>'结束时间不能小于当前时间']);
        }
        $launch = FightgroupLaunch::where('id',$launch_id)->where('merchant_id',$this->merchant_id)->first();
        if(!$launch){
            return Response::json(['errcode'=>2,'errmsg'=>'开团信息不存在']);
        }
        if($launch['status']!=PIN_INIT_ACTIVE){
            return Response::json(['errcode'=>6,'errmsg'=>'当前状态不允许修改']);
        }
        $result = FightgroupLaunch::update_data($launch_id,$this->merchant_id,array('end_time'=>$end_time));
        if($result){
            return Response::json(['errcode'=>0,'errmsg'=>'修改成功']);
        }else{
            return Response::json(['errcode'=>1,'errmsg'=>'修改失败']);
        }
    }

    //校验活动商品
    private function checkGoods($goodsId) {
        $goodsRes = Goods::select('id','title','img', 'price', 'is_sku', 'cquota')
            ->where(['id' => $goodsId, 'merchant_id' => $this->merchant_id,'onsale' => 1, 'is_delete' => 1])
            ->first();
        if(!$goodsRes) {
            return false;
        }
        /*$aloneRes = AloneActivityRecode::where(['merchant_id'=> $this->merchant_id, 'goods_id'=>$goodsId, 'finish_time'=>'0000-00-00 00:00:00'])->first();
        if($aloneRes) {
            return false;
        }*/
        return $goodsRes;
    }

    //商品规格
    private function getGoodsProps($goodsId) {
        $goodsId = isset($goodsId) ? intval($goodsId) : 0;
        $goodsInfo = Goods::select('id', 'goods_cat_id', 'img', 'price', 'is_sku', 'stock')
            ->where(['id' => $goodsId, 'merchant_id' => $this->merchant_id])
            ->first();

        if(empty($goodsInfo)) {
            return ['errcode' => 1, 'errmsg' => '商品不存在'];
        }

        if($goodsInfo['is_sku'] == 0) {
            return $goodsInfo;
        }
        $props = Prop::where(['goods_cat_id'=>$goodsInfo['goods_cat_id'], 'prop_type'=>1,'is_delete'=>1])
            ->whereIn('merchant_id', [$this->merchant_id, 0])
            ->get();

        $products = GoodsSpec::select('id', 'price', 'spec_sn', 'props', 'props_str', 'img', 'barcode', 'stock')
            ->where('goods_id', $goodsId)
            ->where('is_delete', 1)
            ->get();

        $productProps = [];
        foreach($products as $_plist) {
            if($_plist['props']) {
                $propsArr = explode(';', $_plist['props']);
                if(is_array($propsArr) && !empty($propsArr)) {
                    foreach($propsArr as $_tprop) {
                        $bprop = explode(':', $_tprop);
                        if(isset($bprop[0])) {
                            $productProps[] = $bprop[0];
                        }
                    }
                }
            }
        }

        $productProps = array_unique($productProps);

        foreach($props as $key => $list) {
            if(!in_array($list['id'], $productProps)) {
                unset($props[$key]);
            }
        }

        $goodsProps = [];
        foreach ($props as $_prop) {
            if ($_prop['prop_type'] == 1) {
                if (!isset($goodsProps[$_prop['id']])) {
                    $propItem = [];
                    $goodsProp = GoodsProp::select('prop_vid', 'prop_value')
                        ->where('prop_id', $_prop['id'])
                        ->where('goods_id', $goodsId)
                        ->where('is_delete',1)
                        ->get();
                    $propItem['id'] = $_prop['id'];
                    $propItem['name'] = $_prop['title'];
                    $propItem['listorder'] = $_prop['listorder'];
                    $propItem['gps'] = $goodsProp;
                    $goodsProps[$_prop['id']] = $propItem;
                }
            }
        }

        $data = [
            'is_sku' => $goodsInfo['is_sku'],
            'goods_props' => $goodsProps,
            'products' => $products,
        ];

        return $data;

    }

    //多规格商品属性转换
    private function convertGoodsProp($goodsProps = '', $props = '') {
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

}

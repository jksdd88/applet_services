<?php

namespace App\Http\Controllers\Weapp\Goods;

use App\Http\Requests;
use App\Models\GoodsAppt;
use App\Models\Shipment;
use App\Models\Shop;
use App\Services\DiscountService;
use App\Services\FightgroupService;
use App\Services\SeckillService;
use App\Services\VipcardService;
use Illuminate\Support\Facades\Response;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Facades\Member;
use App\Models\GoodsSpec;
use App\Models\Goods;
use App\Models\GoodsProp;
use App\Models\Prop;
use App\Models\PropValue;
use App\Services\GoodsService;
use App\Models\GoodsComponent;
use App\Models\GoodsImg;
use App\Services\VirtualGoodsService;
use App\Models\DistribSetting;
use App\Models\ShareCard;
use App\Models\NewUserGift;
use App\Models\LiveGoods;
use App\Models\LiveInfo;
use App\Models\AloneActivityRecode;
use App\Models\Bargain;
use App\Models\Seckill;
use App\Models\FightgroupItem;
use Carbon\Carbon;

class GoodsController extends Controller
{
    protected $merchant_id = null;                  //商户id

    public function __construct()
    {
        $this->merchant_id = Member::merchant_id();         //商户id
        $this->weapp_id = Member::weapp_id(); //小程序ID
        $this->res = ['errcode' => 0, 'errmsg' => '操作成功', 'data' => []];
        $this->config_err = config('err');

    }
    /**
     *
     *查询商品列表
     * @param int $_GET ['merchant_id']  商户id(必选)
     * @param int $_GET ['name']  商品名称(可选)
     * @param int $_GET ['pagesize']  每页显示条数(可选,默认10)
     * @param int $_GET ['page']      显示页数(可选,默认1)
     * @param string $_GET ['csale']  排序条件:销量(可选)
     * @param string $_GET ['price']  排序条件:价格(可选)
     * @param string $_GET ['shelve_at']  排序条件:上架时间(可选)
     * @param string $_GET ['all_nums']  是否显示商品数量 (默认不显示) 可选参数:show为显示
     * @param string $_GET ['new_nums']  是否显示新品数量   (默认不显示)   可选参数:show为显示
     * @param string $_search ['new_goods']   为show只显示新品数量
     *email renruiqi@dodoca.com
     */
    //获取商品列表
    public function index(Request $request, GoodsService $goodsService)
    {

        $_search = $request->all();

        $_search['merchant_id'] = $this->merchant_id;   //获取当前商铺的id
        // dd($_search);
        $hash = $goodsService->goods_list($_search);
        return Response::json($hash);
    }


    /**
     * 商品详情（含营销活动信息）
     * @param $goods_id
     * @param Request $request
     * @param GoodsService $goodsService
     * @return mixed
     * @author: tangkang@dodoca.com
     */
    public function show($goods_id, GoodsService $goodsService, DiscountService $discountService)
    {
        $merchant_id = $this->merchant_id;
        $weapp_id = $this->weapp_id;

        $goods = Goods::get_data_by_id($goods_id, $merchant_id);
        if (empty($goods)) return $this->config_err['80004'];//商品不存在

        $member_id = Member::id();
        if (!empty($member_id)) {
            $vipcardService = new VipcardService();
            $param = [
                'merchant_id' => $this->merchant_id,
                'member_id' => $member_id,
                'goods_id' => $goods->id,
            ];
            $vipcard_res = $vipcardService->goodsDiscount($param);

            if ($vipcard_res['errcode'] != 0) $goods->discount = 0;//不抛出错误
            $goods->discount = $vipcard_res['data']['discount'];
        }
        //运费模版
        if (!empty($goods->shipment_id)) {
            $goods->start_fee = 0;
            $Shipment = Shipment::get_data_by_id($goods->shipment_id);
            if (!empty($Shipment) && isset($Shipment->start_fee)) {
                $goods->start_fee = $Shipment->start_fee;
            }
        }
        $goods->csale = intval($goods->csale) + intval($goods->base_csale);
        $goods->imgs = GoodsImg::get_data_by_goods_id($goods_id, $merchant_id);//图片
        foreach ($goods->imgs as $key => $img) {
            if ($img['goods_display_type'] != $goods->display_type) unset($goods->imgs[$key]);
        }
        $goods->imgs = array_values($goods->imgs->toArray());
        $goods->description = '';
        $goods_componet_res = GoodsComponent::where(['goods_id' => $goods_id])->value('content');
        if (!empty($goods_componet_res)) {
            $goods_componet_res_decode = json_decode($goods_componet_res);
            if (!empty($goods_componet_res_decode->text)) {
                $goods->description = $goods_componet_res_decode->text;//详情
            }
        }
        $goods_label = $goodsService->getLabel($merchant_id, $goods_id, $goods->serve_label);
        if ($goods_label['errcode'] == 0) {//服务标签
            $goods->goods_label = $goods_label['data'];
        } else {
            $goods->goods_label = [];
        }

        $shop_res = Shop::where('merchant_id', $this->merchant_id)->orderBy('id', 'desc')->first();//原价字段
        if (empty($shop_res)) {//获取原价信息失败，不抛出错误
            if (empty($shop_res->price_field_alias)) {
                $goods->price_field_alias = '原价';
            } else {
                $goods->price_field_alias = $shop_res->price_field_alias;
            }
        }

        $goods->csale_show = $shop_res->csale_show;//列表是否显示销量

        $ump_res = $goodsService->getGoodsUmp($merchant_id, $goods_id);//获取营销活动信息
        if (isset($ump_res['errcode']) && $ump_res['errcode'] > 0) return $ump_res;//秒杀活动商品，获取不到活动信息---抛出错误
        $goods->ump = isset($ump_res['data']) ? $ump_res['data'] : [];

        $discount_res = $discountService->getgoodsDiscountInfo($merchant_id, $goods_id);//获取满减信息
        if ($discount_res['errcode'] == 0) {//满减
            $goods->discount_activity = $discount_res['data'];
        } else {
            $goods->discount_activity = '';
        }
        $comment_list_res = $goodsService->comment_list(['goods_id' => $goods_id, 'pagesize' => 1, 'merchant_id' => $merchant_id]);
        if ($comment_list_res['errcode'] > 0) $goods->comments = [];//获取评论信息失败，不抛出错误
        unset($comment_list_res['errcode'], $comment_list_res['errmsg']);
        $goods->comments = $comment_list_res;
        if ($goods->is_sku == 0) {
            //从缓存获取库存
            $param = [
                'merchant_id' => $this->merchant_id,
                'goods_id' => $goods_id,
            ];
            $goods_stock_res = $goodsService->getGoodsStock($param);
            if ($goods_stock_res['errcode'] == 0) {
                $goods->stock = $goods_stock_res['data'];
            }
        } elseif ($goods->is_sku == 2) {//二期预约信息
            $goods->appt_date = '请选择';
            $res = GoodsAppt::get_data_by_goods_id($goods_id, $merchant_id);
            if (!empty($res)) {
                $goods->appt = $res;
            } else {
                $goods->appt = '';
            }
        }
        
        //虚拟商品信息
        $goods['if_virtual'] = 0;//是否虚拟商品：0->否，1->是
        if($goods['goods_type'] == 1){
            $goods['if_virtual'] = 1;
            $virtualGoodsService = new VirtualGoodsService();
            $goods_virtual_info = $virtualGoodsService->getVirtualGoodsInfo($goods_id, $merchant_id);
            
            if($goods_virtual_info['errcode'] == 0){
                $goods['goods_virtual_info'] = $goods_virtual_info['data']['goods_virtual_info'];
            }else{
                $goods['goods_virtual_info'] = '';
            }
        }
        
        //新用户有礼
        $is_gift = NewUserGift::where('merchant_id', $merchant_id)
            ->where('begin_time', '<=', Carbon::now())
            ->where('end_time', '>=', Carbon::now())
            ->where('status', '!=', 2)
            ->where('is_delete', 1)
            ->value('id');
        $goods->is_gift = $is_gift ? 1: 0;
        
        //分销开关
        $distrib_setting = DistribSetting::get_data_by_merchant_id($merchant_id);
        if ($distrib_setting && $distrib_setting['status'] == 1) {
            $goods->distrib_on_off = 1;
        }

        //分享卡片开关
        $sharecard_setting = ShareCard::get_data($merchant_id, $weapp_id);
        if($sharecard_setting && $sharecard_setting['is_whether'] == 1){
            $scope = json_decode($sharecard_setting['page_id'], true);
            if(is_array($scope) && in_array('goods', $scope)){
                $goods->sharecard_on_off = 1;
                $activity_recode = AloneActivityRecode::get_data_by_goodsid($goods_id, $merchant_id);
                if($activity_recode){
                    switch ($activity_recode->act_type) {
                        case 'bargain':
                            $bargain = Bargain::get_data_by_id($activity_recode->alone_id, $merchant_id);
                            $minimum = $bargain ? $bargain->minimum : 0;
                            break;
                        case 'seckill':
                            $seckill = Seckill::get_data_by_id($activity_recode->alone_id, $merchant_id);
                            $minimum = $seckill ? $seckill->price : 0;
                            break;
                        case 'tuan':
                            $minimum = FightgroupItem::where('merchant_id', $merchant_id)->where('fightgroup_id', $activity_recode->alone_id)->orderBy('ladder_price', 'asc')->value('ladder_price');
                            break;
                    }
                    $goods->sharecard_data = [
                        'act_type' => $activity_recode->act_type,
                        'minimum' => $minimum
                    ];
                }

            }
        }

        $this->config_err['0']['data'] = $goods;
        return $this->config_err['0'];
    }

    /**
     * 商品Sku
     * @param $goods_id
     * @param Request $request
     * @author: tangkang@dodoca.com
     */
    public function getSku($goods_id, GoodsService $goodsService, FightgroupService $fightgroupService, SeckillService $seckillService)
    {
        $goods = Goods::where(['id' => $goods_id, 'merchant_id' => $this->merchant_id])->first();
        if (empty($goods)) return $this->config_err['80004'];//商品不存在
        if (empty($goods->onsale)) return $this->config_err['80011'];//商品下架
        $member_id = Member::id();
        if (!empty($member_id)) {
            $vipcardService = new VipcardService();
            $param = [
                'merchant_id' => $this->merchant_id,
                'member_id' => $member_id,
                'goods_id' => $goods->id,
            ];
            $vipcard_res = $vipcardService->goodsDiscount($param);
//            if ($vipcard_res['errcode'] != 0) return $vipcard_res;
            if ($vipcard_res['errcode'] != 0) $goods->discount = 0;//不抛出错误,获取失败返回0
            $goods->discount = $vipcard_res['data']['discount'];
        }
        //规格信息 不需要？加购物车、立即购买单独sku
//        if ($goods->is_sku == 1 || $goods->is_sku == 2) {
        if ($goods->is_sku == 1) {//新版预约不走此接口
            $goods_spec_res = GoodsSpec::get_data_by_goods_id($goods_id, $this->merchant_id);
//            $goods->goods_spec = GoodsSpec::get_data_by_goods_id($goods_id, $this->merchant_id);
            if (empty($goods_spec_res)) return ['errcode' => 1, 'errmsg' => '获取规格信息失败'];//商品不存在
            $goods_spec = [];
//            return ['errcode'=>1,'data'=>$goods_spec_res];

            foreach ($goods_spec_res as $value) {
                $goods_spec[$value->props] = $value;
                $param = [
                    'merchant_id' => $this->merchant_id,
                    'goods_id' => $goods_id,
                    'goods_spec_id' => $value->id,
                ];
                $goods_stock_res = $goodsService->getGoodsStock($param);
                if ($goods->is_sku == 1) {//多规格库存
                    if ($goods_stock_res['errcode'] != 0) return ['errcode' => 1, 'errmsg' => '获取库存信息失败'];
                    $goods_spec[$value->props]->stock = $goods_stock_res['data'];
                } /*elseif ($goods->is_sku == 2) {//预约库存
                    $goods_spec[$value->props]->stock = $value->stock;
                }*/

            }
            $goods->goods_spec = $goods_spec;

            $prop_res = $goodsService->getProps($this->merchant_id, $goods_id);
            if ($prop_res['errcode'] > 0) return $this->config_err['80005'];//商品属性、规格异常
            $goods->goods_props = $prop_res['data'];
//            if ($goods->is_sku == 2) {//预约
//                $appt = [];
////                return ['errcode'=>1,'data'=>$goods->goods_props];
//                foreach ($goods->goods_props as $value) {
//                    if (urldecode($value['name']) == urldecode('预约日期')) {
//                        foreach ($value['gps'] as $gps) {
//                            $day_cat = urldecode($gps['prop_value']);
//                            if ($day_cat === urldecode('周一至周五')) {
//                                $appt['weekdays'] = $gps['prop_vid'];
//                            } elseif ($day_cat === urldecode('周六至周日')) {
//                                $appt['weekends'] = $gps['prop_vid'];
//                            } elseif ($day_cat === urldecode('法定节假日')) {
//                                $appt['holiday'] = $gps['prop_vid'];
//                            }/* else {
//                                return $this->config_err['80005'];//商品属性、规格异常
//                            }*/
//                        }
//                    }
//                }
//                $goods->day = Calendar::get_month_calendar($appt);
////                ['weekdays'=>40,'weekends'=>41,'holiday'=>42];
//                //二期预约
//                $res = GoodsAppt::get_data_by_goods_id($goods_id, $this->merchant_id);
//                if (!empty($res)) {
//                    $goods->appt = $res;
//                } else {
//                    $goods->appt = '';
//                }
//                $ApptService = new ApptService();
//                $range_param = [
//                    'goods_id' => $goods_id,
//                    'merchant_id' => $this->merchant_id,
//                ];
//                //可预约日期范围
//                $range_res = $ApptService->getApptDateRange($range_param);
//                if ($range_res < 1) {
//                    $goods->date_list = $range_res['data'];
//                } else {
//                    $errData = [
//                        'activity_id' => $goods_id,
//                        'data_type' => 'goods',
//                        'content' => '商品详情：获取预约商品可预约日期范围列表失败->' . json_encode($res, JSON_UNESCAPED_UNICODE),
//                    ];
//                    CommonApi::errlog($errData);
//                }
//                //默认今天的时间段
//                $param = ['date' => date('Y-m-d'), 'goods_id' => $goods_id, 'merchant_id' => $this->merchant_id];
//                $res = $ApptService->getApptTime($param);
//                if ($res > 0) {
//                    $errData = [
//                        'activity_id' => $goods_id,
//                        'data_type' => 'goods',
//                        'content' => '商品详情：获取预约商品预约时段列表失败->' . json_encode($res, JSON_UNESCAPED_UNICODE),
//                    ];
//                    CommonApi::errlog($errData);
//                }
//                if ($res < 1) {
//                    $goods->appt_time = $res['data'];//某天所有预约时段,格式['value_id'=>'125.50']
//                }
//            }
        }
        if ($goods->is_sku == 0) {
            //从缓存获取库存
            $param = [
                'merchant_id' => $this->merchant_id,
                'goods_id' => $goods_id,
            ];
            $goods_stock_res = $goodsService->getGoodsStock($param);
            if ($goods_stock_res['errcode'] == 0) {
                $goods->stock = $goods_stock_res['data'];
            }
        }

        $this->config_err['0']['data'] = $goods;
        return $this->config_err['0'];
    }

    /**
     *
     *查询商品所有的属性
     * @param int $_GET ['id']  商品ID(必选)
     *email renruiqi@dodoca.com
     *时间  2017-9-12
     */
    public function getGoodsProps(Request $request, $id)
    {
        $goods_id = (int)$id;
        //查询所有的属性
        // $prop_list  = GoodsProp::query()
        //             ->where('goods_id',$goods_id)
        //             ->where('is_delete',1)            //未删除
        //             ->lists('prop_vid','prop_id')     //prop_vid做值,prop_id做键名
        //             ->toArray();

        $wheres['where'] = [
            ['column' => 'goods_id', 'operator' => '=', 'value' => $goods_id],
            ['column' => 'is_delete', 'operator' => '=', 'value' => 1],
        ];
        $wheres['lists'] = ['prop_vid', 'prop_id'];
        $wheres['toArray'] = true;
        $prop_list = GoodsProp::get_data_list_new($wheres);
        $data = [];
        if (!empty($prop_list)) {
            foreach ($prop_list as $k => $v) {
                $k_info = Prop::where('is_delete', 1)->where('prop_type', 0)->find($k);
                $v_info = PropValue::where('is_delete', 1)->where('prop_type', 0)->find($v);
                if ($k_info && $v_info) {
                    $data[] = ['name' => $k_info->title, 'prop_value' => $v_info->title];
                }
            }
        }
        return Response::json(['errcode' => 0, 'errmsg' => '操作成功', 'data' => $data]);
    }

    /**
     *根据商品id数组返回商品基础信息
     * @param  $ids array 商品id数组 (必选)
     *author  renruiqi@dodoca.com
     */
    public function getGoodsByIds(GoodsService $goodsService, Request $request)
    {
        $ids = json_decode($request->ids);
        if ((!is_array($ids)) || count($ids) < 1) return Response::json(['errcode' => 70024, 'errmsg' => '参数缺失', 'data' => []]);
        $goods = $goodsService->getGoodsByIds($this->merchant_id, $ids);
        // $goods = $goodsService->getSeckillGoods(array('merchant_id'=>$this->merchant_id));
        return $goods;
    }


    /**
     *根据秒杀表id数组返回商品基础信息
     * @param  $seckill_ids string  商品id数组字符串 "[1,2,3,4,6]" (必选)
     *author  renruiqi@dodoca.com
     */
    public function getGoodsBySeckillIds(GoodsService $goodsService, Request $request)
    {
        $seckill_ids = json_decode($request->seckill_ids);
        if ((!is_array($seckill_ids)) || count($seckill_ids) < 1) return Response::json(['errcode' => 70024, 'errmsg' => '参数缺失', 'data' => []]);
        $goods = $goodsService->getGoodsBySeckillIds(array('merchant_id' => $this->merchant_id, 'seckill_ids' => $seckill_ids));
        return $goods;
    }

    /**
     * 获取商品库存（暂废弃掉，稍后删除）
     * @author zhangchangchun@dodoca.com
     * date 2017-09-08
     */
    /* public function getSkudata($id)
     {
         if (!$id || !is_numeric($id)) {
             return Response::json(array('errcode' => '80001', 'errmsg' => '缺少商品id参数'));
         }
         $str = '{"id":109023667,"original_id":0,"shop_id":13296359,"title":"\u7ebf\u4e0b\u6838\u9500","img":"2017\/08\/21\/FlmNoIxtspjFwfDyJSt609eLfS1Z.jpg","price":"100.00","max_price":"0.00","is_sku":0,"collect_fields":[],"cquota":0,"stock":100,"is_discount":0,"is_virtual":0,"is_hexiao":1,"vip_price":false,"repurchase_price":null,"goods_props":[],"products":[],"ump":{"alone":[],"coexist":[]}}';
         $str = '{"id":108923386,"original_id":0,"shop_id":13296359,"title":"\u89c4\u683c\u5546\u54c1","img":"2017\/08\/21\/FlmNoIxtspjFwfDyJSt609eLfS1Z.jpg","price":"10.00","max_price":"20.00","is_sku":1,"collect_fields":[],"cquota":0,"stock":296,"is_discount":0,"is_virtual":0,"is_hexiao":0,"vip_price":false,"repurchase_price":null,"goods_props":{"760":{"id":760,"name":"\u989c\u8272","listorder":1,"gps":[{"prop_vid":2513,"prop_value":"\u6df1\u84dd\u8272"}]},"1818":{"id":1818,"name":"\u7248\u672c","listorder":2,"gps":[{"prop_vid":7277,"prop_value":"\u6e2f\u7248"},{"prop_vid":7278,"prop_value":"\u884c\u8d27"}]}},"products":[{"id":13046101,"original_id":0,"supplier_goods_id":0,"supplier_product_id":0,"goods_id":108923386,"price":"10.00","stock":98,"product_sn":"121212","props":"760:2513;1818:7277","props_str":"\u6df1\u84dd\u8272:\u6e2f\u7248","img":"2017\/08\/21\/FlmNoIxtspjFwfDyJSt609eLfS1Z.jpg","barcode":"121121","csale":2,"is_enabled":1,"created_at":"2017-09-05 11:27:31","updated_at":"0000-00-00 00:00:00","settle_price":"0.00","adjust_price":"0.00","vip_price":false,"repurchase_price":null},{"id":13046102,"original_id":0,"supplier_goods_id":0,"supplier_product_id":0,"goods_id":108923386,"price":"20.00","stock":198,"product_sn":"212154","props":"760:2513;1818:7278","props_str":"\u6df1\u84dd\u8272:\u884c\u8d27","img":"2017\/06\/15\/FsHveRBEIRbD4G4J93Hmz0LE6tLV.jpg","barcode":"5545","csale":2,"is_enabled":1,"created_at":"2017-09-05 11:27:31","updated_at":"0000-00-00 00:00:00","settle_price":"0.00","adjust_price":"0.00","vip_price":false,"repurchase_price":null}],"ump":{"alone":[],"coexist":[]}}';
         $data = json_decode($str, true);
         $data = array(
             'id' => 1,
             'title' => '商品名称',
             'img' => '2017/08/21/FlmNoIxtspjFwfDyJSt609eLfS1Z.jpg',
             'price' => 10,
             'max_price' => 100,
             'is_sku' => 1,
             'collect_fields' => array(
                 0 => array(
                     'name' => '姓名',
                     'type' => 'text',
                     'required' => 1,
                 ),
                 1 => array(
                     'name' => '性别',
                     'type' => 'text',
                     'required' => 1,
                 ),
             ),
             'stock' => 10,
             'props' => array(
                 100 => array(
                     'id' => 100,
                     'name' => '颜色',
                     'listorder' => 1,
                     'list' => array(
                         0 => array(
                             'prop_vid' => 11,
                             'prop_value' => '深蓝色',
                         ),
                     ),
                 ),
                 101 => array(
                     'id' => 101,
                     'name' => '大小',
                     'listorder' => 2,
                     'list' => array(
                         0 => array(
                             'prop_vid' => 12,
                             'prop_value' => '1寸',
                         ),
                         1 => array(
                             'prop_vid' => 13,
                             'prop_value' => '3寸',
                         ),
                     ),
                 ),
             ),
             'goods_props' => array(
                 0 => array(
                     'id' => 1,
                     'goods_id' => 1,
                     'price' => 30,
                     'stock' => 100,
                     'props' => '100:11;101:12',
                     'props_str' => '深蓝色:1寸',
                 ),
                 1 => array(
                     'id' => 2,
                     'goods_id' => 1,
                     'price' => 40,
                     'stock' => 120,
                     'props' => '100:11;101:13',
                     'props_str' => '深蓝色:2寸',
                 ),
             ),
         );
         return Response::json(array('errcode' => '0', 'errmsg' => '返回成功', 'data' => $data));
     }*/


}

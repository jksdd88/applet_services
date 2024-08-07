<?php
/**
 * Created by PhpStorm.
 * User: tang
 * Date: 2017-08-31
 * Time: 15:20
 */
namespace App\Services;

use App\Jobs\GoodsStock;
use App\Models\AloneActivityRecode;
use App\Models\Goods;

use App\Models\GoodsProp;
use App\Models\GoodsServeLabel;
use App\Models\GoodsSpec;
use App\Models\GoodsTagRs;
use App\Models\MerchantSetting;
use App\Models\OrderInfo;
use App\Models\Prop;
use App\Models\DiscountActivity;
use App\Models\DiscountGoods;
use App\Models\Seckill;
use App\Models\ServeLabel;
use App\Utils\CacheKey;
use App\Utils\CommonApi;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Models\OrderComment;
use App\Models\OrderCommentImg;
use App\Models\OrderGoods;
use App\Models\OrderGoodsUmp;
use App\Models\Member;
use App\Models\FightgroupStock;
use App\Models\Shop;
use App\Models\CommentVirtualMember;
use App\Models\CouponGoods;
use Illuminate\Support\Facades\Response;

class GoodsService
{
    use DispatchesJobs;

    private $config_err;

    /**
     * 获取商品属性规格
     * @param $merchant_id
     * @param $goods_id
     * @param int $type 1：规格，0：属性
     * @return mixed
     * @author: tangkang@dodoca.com
     */
    public function getProps($merchant_id, $goods_id, $type = 1)
    {
        $goods_prop_res = GoodsProp::where('goods_prop.merchant_id', $merchant_id)
            ->where('goods_prop.goods_id', $goods_id)
            ->where('goods_prop.is_delete', 1)
            ->leftJoin('prop', 'goods_prop.prop_id', '=', 'prop.id')
            ->where('prop.merchant_id', $merchant_id)
            ->where('prop.prop_type', 1)
            ->where('prop.is_delete', 1)
            ->get(
                [
                    'prop.id as id',
                    'prop.title as name',
                    'prop.listorder as listorder',
                    'prop.prop_value_type as prop_value_type',
                    'goods_prop.prop_value as prop_value',
                    'goods_prop.prop_vid as prop_vid',
                ]
            );
        if (empty($goods_prop_res)) return ['errcode' => 0, 'errmsg' => '无商品属性/规格', 'data' => []];
        $return_props = [];
        foreach ($goods_prop_res as $prop) {
            $return_props[$prop->id]['id'] = $prop->id;
            $return_props[$prop->id]['listorder'] = $prop->listorder;
            $return_props[$prop->id]['name'] = $prop->name;
            $return_props[$prop->id]['gps'][] = ['prop_value' => $prop->prop_value, 'prop_vid' => $prop->prop_vid];
        }
        $this->config_err['0']['data'] = $return_props;
        return $this->config_err['0'];


        $this->config_err = config('err');
        $props = [];
        $goods_prop_res = GoodsProp::get_data_by_goods($goods_id, $merchant_id);
        $prop_vid = array_pluck($goods_prop_res, 'prop_vid');
        $prop_id = array_pluck($goods_prop_res, 'prop_id');
        $prop_id = array_unique($prop_id);

        if ($prop_id) {
            $props = Prop::whereIn('id', $prop_id)
                ->where(function ($query) use ($merchant_id) {//默认的商户为0是公共的--也要
                    $query->where('merchant_id', $merchant_id)->orWhere('merchant_id', 0);
                })
                ->where('prop_type', $type)
                ->where('is_delete', 1)
                ->with(['propValues' => function ($query) use ($prop_vid) {
                    $query->where('is_delete', 1)->whereIn('id', $prop_vid)->select('id', 'title', 'prop_id');
                }])
                ->get(['id', 'title', 'listorder']);//规格、属性名称&& 规格值、属性值
        }
        $return_props = [];
        foreach ($props as $prop) {
            $return_props[$prop->id]['id'] = $prop->id;
            $return_props[$prop->id]['listorder'] = $prop->listorder;
            $return_props[$prop->id]['name'] = $prop->title;
            foreach ($prop->propValues as $prop_value) {
                $return_props[$prop->id]['gps'][] = ['prop_value' => $prop_value->title, 'prop_vid' => $prop_value->id];
            }
        }
        $this->config_err['0']['data'] = $return_props;
        return $this->config_err['0'];
    }

    /**
     * 获取商品服务标签
     * @param $merchant_id
     * @param $goods_id
     * @param int $serve_label 0系统默认，1自定义
     * @return mixed
     * @author: tangkang@dodoca.com
     */
    public function getLabel($merchant_id, $goods_id, $serve_label = 0)
    {
        $this->config_err = config('err');
        $merchant_setting_res = MerchantSetting::get_data_by_id($merchant_id);
        if (!empty($merchant_setting_res) && $merchant_setting_res->label_enabled != 1) {//商家设置里未开启
            $this->config_err['0']['data'] = [];
        } else {
            if (empty($serve_label)) {
                $goods_label = ServeLabel::where(array('status' => 1, 'merchant_id' => $merchant_id))
                    ->select('id', 'title', 'logo', 'content')
                    ->get();//系统默认只查status状态就好
            } else {
                $goods_label = GoodsServeLabel::where('goods_serve_label.goods_id', $goods_id)
                    ->where(array('serve_label.is_enabled' => 1, 'serve_label.merchant_id' => $merchant_id))
                    ->leftJoin('serve_label', 'serve_label.id', '=', 'goods_serve_label.label_id')
                    ->select('serve_label.id', 'serve_label.title', 'serve_label.logo', 'serve_label.content')->get();//自定义只查is_enabled状态就号
            }
            $this->config_err['0']['data'] = $goods_label;
        }

        return $this->config_err['0'];
    }

    /**
     * 营销商品数据详情
     * @param $merchant_id
     * @param $goods_id
     * @return array
     * @author: tangkang@dodoca.com
     */
    public function getGoodsUmp($merchant_id, $goods_id)
    {
        $this->config_err = config('err');

//        \DB::connection()->enableQueryLog(); // 开启查询日志
        $ump = [];
        $ump_res = AloneActivityRecode::get_data_by_goodsid($goods_id, $merchant_id);
        if (empty($ump_res) || empty($ump_res->act_type)) return $this->config_err['0'];
        $ump_type = $ump_res->act_type;
        switch ($ump_type) {
            case 'bargain':
                //砍价还可当作普通商品销售，查不到砍价信息---不报错
                $bargain = new BargainService();
                $bargain_res = $bargain->bargainInfo($merchant_id, $goods_id);
                if ($bargain_res['errcode'] == 0) {
                    $ump['type'] = 'bargain';
                    $ump['data'] = $bargain_res['data'];
                }
                break;
            case 'tuan':
                //参团还可当作普通商品销售，查不到团购信息---不报错
                $fightgroupService = new FightgroupService();
                $fight_group_res = $fightgroupService->fightgroupLadderList($merchant_id, $goods_id);
                if ($fight_group_res['errcode'] == 0) {
                    $ump['type'] = 'tuan';
                    $ump['data'] = $fight_group_res['data'];
                }
                break;
            case 'seckill':
                $seckillService = new SeckillService();
                $seckill_res = $seckillService->getSeckill($merchant_id, $ump_res->alone_id);
                //秒杀只有一种通道（设置秒杀商品后 不能当普通商品购买）
                if ($seckill_res['errcode'] == 0) {
                    $ump['type'] = 'seckill';
                    $ump['data'] = $seckill_res['data'];
                    $goods_res = Goods::get_data_by_id($goods_id, $merchant_id);
                    if (!empty($goods_res) && !empty($goods_res->stock) && $goods_res->stock < 1) {
                        $update['end_time'] = date('Y-m-d H:i:s');
                        if ($update['end_time'] < $ump_res->finish_time) {//结束时间没到， 无库存强制结束
                            Seckill::update_data($seckill_res->id, $merchant_id, $update);
                        }
                    }
                } else {//秒杀商品不能当作普通商品卖。查不到信息--报错
                    return $seckill_res;
                }
                break;
            default:
                break;
        }
        $this->config_err['0']['data'] = $ump;
        return $this->config_err['0'];
    }

    /**
     * 获取商品库存（预约商品下单时存）
     * @param $merchant_id 商户id
     * @param $goods_id 商品id
     * @param $goods_spec_id 规格id 没有传0
     * @param $activity 商品所需操作库存类型  普通商品：可不传  拼团：tuan
     * @param $date 预约商品-预约日期 2017-09-11
     * @author: tangkang@dodoca.com
     */
    public function getGoodsStock(array $param)
    {
        $this->config_err = config('err');

        try {
            $data = $this->verify_stock_param($param);  //参数验证
            if ($data['errcode'] !== 0) return $data;

            $this->config_err['0']['data'] = 0;

            if ($data['activity'] === 'tuan') {
                //商品当前还在拼团活动进行中 库存加到拼团库存中
                $activity_info = AloneActivityRecode::get_data_by_goodsid($data['goods_id'], $data['merchant_id']);

                if (!empty($activity_info['alone_id']) && $activity_info['act_type'] === $data['activity']) {
                    //获取团购商品表id
                    $fight_group_stock_id = FightgroupStock::get_id_by_ids($data['merchant_id'], $activity_info['alone_id'], $data['goods_id'], $data['goods_spec_id']);

                    if (!empty($fight_group_stock_id)) {
                        //调用拼团services 查询团购库存
                        $fightgroupService_obj = new FightgroupService();
                        $fightgroup_stock = $fightgroupService_obj->getStock($fight_group_stock_id, $data['merchant_id']);
                        $this->config_err['0']['data'] = !empty($fightgroup_stock) && (int)$fightgroup_stock > 0 ? $fightgroup_stock : 0;
                    }
                }

                //查不到活动默认库存为0
                return $this->config_err['0'];
            } else {
                //获取商品KEY
                $key = CacheKey::get_goods_stock_key($data['goods_id'], $data['goods_spec_id'], $data['date_timestamp']);
                //获取标签KEY
                $tags_key = CacheKey::get_tags_goods_stock($data['goods_id'], $data['goods_spec_id']);
                //dd($tags_key);
                //查看库存缓存是否存在 不存在操作数据库
                if (!Cache::tags($tags_key)->has($key)) {

                    if ($data['is_sku'] == 0) {
                        //获取总库存
                        $stock = Goods::where('id', $data['goods_id'])
                            ->where('merchant_id', $data['merchant_id'])
//                            ->where('is_delete', 1)
                            ->value('stock');//商品库存
                    } else {
                        //获取规格（总）库存
                        $stock = GoodsSpec::where('id', $data['goods_spec_id'])
                            ->where('merchant_id', $data['merchant_id'])
                            ->where('goods_id', $data['goods_id'])
//                            ->where('is_delete', 1)
                            ->value('stock');//sku库存
                    }

                    if (empty($stock) && $stock !== 0) return $this->config_err['80004'];//商品不存在
                    Cache::tags($tags_key)->forever($key, intval($stock));

                }

                $goods_stock = Cache::tags($tags_key)->get($key);

                if (!empty($goods_stock) || $goods_stock === 0 || $goods_stock === '0') {
                    $this->config_err['0']['data'] = (int)$goods_stock;
                    return $this->config_err['0'];
                } else {
                    return $this->config_err['80004'];
                }
            }

        } catch (\Exception $exception) {
            //记录异常
            $except = [
                'activity_id' => $param['goods_id'],
                'data_type' => 'goods_stock',
                'content' => '查库存失败，' . $exception->getMessage() . '，' . json_encode($param, JSON_UNESCAPED_UNICODE),
            ];
            CommonApi::errlog($except);
            if (app()->isLocal()) $this->config_err['99003']['errmsg'] .= $exception->getMessage();
            return $this->config_err['99003'];
        }
    }

    /**
     * 加库存
     * @param $merchant_id 商户id
     * @param $stock_num 加库存量
     * @param $goods_id 商品id
     * @param $goods_spec_id 规格id 没有传0
     * @param $activity 商品所需操作库存类型  普通商品：可不传  拼团：tuan
     * @param $date 预约商品-预约日期 2017-09-11
     * @return mixed 返回加量后库存
     * @author: tangkang@dodoca.com
     */
    public function incStock(array $param)
    {
        $this->config_err = config('err');

        try {
            $data = $this->verify_stock_param($param);  //参数验证
            if ($data['errcode'] !== 0) return $data;

            $goods_stock = $this->getGoodsStock($param);//获取当前商品库存

            //如果缓存中商品库存合法 进行加库存操作
            if ($goods_stock['errcode'] === 0 && isset($goods_stock['data'])) {
                //进入拼团活动商品规则
                if ($data['activity'] === 'tuan') {
                    //商品当前还在拼团活动进行中 库存加到拼团库存中
                    $activity_info = AloneActivityRecode::get_data_by_goodsid($data['goods_id'], $data['merchant_id']);

                    if ($activity_info['act_type'] === $data['activity'] && !empty($activity_info['alone_id'])) {
                        //获取团购商品表id
                        $fight_group_stock_id = FightgroupStock::get_id_by_ids($data['merchant_id'], $activity_info['alone_id'], $data['goods_id'], $data['goods_spec_id']);

                        if (!empty($fight_group_stock_id)) {
                            //调用团购商品库存自增函数
                            $fightgroupService_obj = new FightgroupService();
                            $fightgroup_stock = $fightgroupService_obj->incStock($data['merchant_id'], $fight_group_stock_id, $data['stock_num']);

                            $this->config_err['0']['data'] = empty($fightgroup_stock['data']) ? 0 : $fightgroup_stock['data'];
                            return $this->config_err['0'];

                        } else {
                            return $this->config_err['80009'];
                        }
                    }

                }

                //获取商品KEY
                $key = CacheKey::get_goods_stock_key($data['goods_id'], $data['goods_spec_id'], $data['date_timestamp']);
                //获取标签KEY
                $tags_key = CacheKey::get_tags_goods_stock($data['goods_id'], $data['goods_spec_id']);

                //进入普通加商品库存规则
                if (is_int($stock = Cache::tags($tags_key)->incrementGetValue($key, $data['stock_num']))) {
                    $goodsStockJob = (new GoodsStock($data))->onQueue('GoodsStock');
                    $this->dispatch($goodsStockJob);//改数据库
                } else {
                    //操作缓存中商品库存异常 抛出错误码
                    return $this->config_err['80008'];
                }

                $this->config_err['0']['data'] = $stock;
                return $this->config_err['0'];

            } else {
                //缓存中商品库存异常抛出错误码
                return $goods_stock['errcode'] === 0 ? $this->config_err['80008'] : $goods_stock;
            }

        } catch (\Exception $exception) {
            //记录异常
            $except = [
                'activity_id' => $param['goods_id'],
                'data_type' => 'goods_stock',
                'content' => '加库存失败，' . $exception->getMessage() . '，' . json_encode($param, JSON_UNESCAPED_UNICODE),
            ];
            CommonApi::errlog($except);
            if (app()->isLocal()) $this->config_err['99003']['errmsg'] .= $exception->getMessage();
            return $this->config_err['99003'];
        }

    }

    /**
     * 减库存
     * @param $merchant_id 商户id
     * @param $stock_num 减库存量
     * @param $goods_id 商品id
     * @param $goods_spec_id 规格id 没有传0
     * @param $activity 商品所需操作库存类型  普通商品：可不传  拼团：tuan
     * @param $date 预约商品-预约日期 2017-09-11
     * @return mixed 小于0：库存不足（未减库存），其他值：减库存后剩余库存
     * @author: tangkang@dodoca.com
     */
    public function desStock(array $param)
    {
        $this->config_err = config('err');

        try {
            $data = $this->verify_stock_param($param);  //参数验证
            if ($data['errcode'] !== 0) return $data;

            $goods_stock = $this->getGoodsStock($param);//获取当前商品库存

            //如果缓存中商品库存合法 进行加库存操作
            if ($goods_stock['errcode'] === 0 && preg_match("/^[0-9][0-9]*$/", $goods_stock['data'])) {
                //进入拼团活动商品规则
                if ($data['activity'] === 'tuan') {
                    //商品当前还在拼团活动进行中 到拼团库存中自减库存
                    $activity_info = AloneActivityRecode::get_data_by_goodsid($data['goods_id'], $data['merchant_id']);

                    if ($activity_info['act_type'] === $data['activity'] && !empty($activity_info['alone_id'])) {
                        //获取团购商品表id
                        $fight_group_stock_id = FightgroupStock::get_id_by_ids($data['merchant_id'], $activity_info['alone_id'], $data['goods_id'], $data['goods_spec_id']);

                        if (!empty($fight_group_stock_id)) {
                            //调用团购商品库存自减函数
                            $fightgroupService_obj = new FightgroupService();
                            $fightgroup_stock = $fightgroupService_obj->decStock($data['merchant_id'], $fight_group_stock_id, $data['stock_num']);

                            if ($fightgroup_stock['errcode'] == 0) {
                                $this->config_err['0']['data'] = $fightgroup_stock['data'];
                                return $this->config_err['0'];
                            } else {
                                return $this->config_err['80003'];//库存不足
                            }
                        } else {
                            return $this->config_err['80009'];//团购活动数据异常
                        }
                    }
                }


                //获取商品KEY
                $key = CacheKey::get_goods_stock_key($data['goods_id'], $data['goods_spec_id'], $data['date_timestamp']);
                //获取标签KEY
                $tags_key = CacheKey::get_tags_goods_stock($data['goods_id'], $data['goods_spec_id']);

                //进入普通减商品库存规则
                if (preg_match("/^[0-9][0-9]*$/", $stock = Cache::tags($tags_key)->decrementGetValue($key, $data['stock_num']))) {
                    $data['stock_num'] = -$data['stock_num'];
                    $goodsStockJob = (new GoodsStock($data))->onQueue('GoodsStock');
                    $this->dispatch($goodsStockJob);//改数据库
                } else {
                    Cache::tags($tags_key)->incrementGetValue($key, $data['stock_num']);//如果超扣 还回库存
                    //操作缓存中商品库存异常 抛出错误码
                    return $this->config_err['80003'];
                }

                $this->config_err['0']['data'] = $stock;
                return $this->config_err['0'];

            } else {
                //缓存中商品库存异常抛出错误码
                return $goods_stock['errcode'] === 0 ? $this->config_err['80003'] : $goods_stock;
            }

        } catch (\Exception $exception) {
            //记录异常
            $except = [
                'activity_id' => $param['goods_id'],
                'data_type' => 'goods_stock',
                'content' => '扣库存失败，' . $exception->getMessage() . '，' . json_encode($param, JSON_UNESCAPED_UNICODE),
            ];
            CommonApi::errlog($except);
            if (app()->isLocal()) $this->config_err['99003']['errmsg'] .= $exception->getMessage();
            return $this->config_err['99003'];
        }
    }

    /**
     *
     *查询商品列表
     * @param int $_search ['merchant_id']  商户id(必选)
     * @param int $_search ['coupon_id']  优惠劵id(选填)
     * @param int $_search ['name']  商品名称(可选)
     * @param int $_search ['pagesize']  每页显示条数(可选,默认16)
     * @param int $_search ['page']      显示页数(可选,默认1)
     * @param string $_search ['csale']  排序条件:销量(可选)
     * @param string $_search ['price']  排序条件:价格(可选)
     * @param string $_search ['shelve_at']  排序条件:上架时间(可选)
     * @param string $_search ['all_nums']  是否显示商品数量 (默认不显示) 可选参数:show为显示
     * @param string $_search ['new_nums']  是否显示新品数量   (默认不显示)   可选参数:show为显示
     * @param string $_search ['new_goods']   为show只显示新品数量
     *邮箱  renruiqi@dodoca.com
     */
    public function goods_list($_search = array())
    {
        if (empty($_search['merchant_id']) || (int)$_search['merchant_id'] < 1) return ['errcode' => '99004', 'errmsg' => '网络繁忙，请稍后再试'];
        $_search['merchant_id'] = (int)$_search['merchant_id'];
//        $time_str = date('Y-m-d H:i:s');
//        $price_field_alias = Shop::where('merchant_id', $_search['merchant_id'])
//            ->orderBy('id', 'desc')
//            ->value('price_field_alias');                                      //原价字段
//        $hash['price_field_alias'] = $price_field_alias ? $price_field_alias : '原价';

        $shop_res = Shop::where('merchant_id', $_search['merchant_id'])->orderBy('id', 'desc')->first();//原价字段
        if (empty($shop_res)) return ['errcode' => 1, 'errmsg' => '获取商户信息失败'];
        if (empty($shop_res->price_field_alias)) {
            $hash['price_field_alias'] = '原价';
        } else {
            $hash['price_field_alias'] = $shop_res->price_field_alias;
        }
        $hash['csale_show'] = $shop_res->csale_show;//列表是否显示销量


        //接收数据(页码和显示条数)
        $pagesize = isset($_search['pagesize']) && (int)$_search['pagesize'] > 0 ? (int)$_search['pagesize'] : 16;      //默认显示16条数据
        $page = isset($_search['page']) && (int)$_search['page'] > 0 ? (int)$_search['page'] : 1;                       //默认显示第一页
        $offset = ($page - 1) * $pagesize;
//        $ids_str = empty($ids) ? 0 : implode(',', $ids);          //数组转为字符串
        $wheres = [
            ['column' => 'merchant_id', 'operator' => '=', 'value' => $_search['merchant_id']],
            ['column' => 'is_delete', 'operator' => '=', 'value' => 1],                          //商品未删除
            ['column' => 'onsale', 'operator' => '=', 'value' => 1],                             //商品未下架
        ];
        //显示指定商品
        if (isset($_search['coupon_id']) && $_search['coupon_id']) {
            $coupon_id    = $_search['coupon_id'];
            $coupon_goods = CouponGoods::select('goods_id')->where('coupon_id', $coupon_id)->where('status', 1)->where('is_delete', 1)->get()->toArray();

            $goods_ids = array_column($coupon_goods, 'goods_id');
            if($goods_ids){
                $wheres[] = ['column' => 'id', 'operator' => 'in', 'value' => $goods_ids];
            }
        }
        //是否显示商品总数
        if (isset($_search['all_nums']) && $_search['all_nums'] === 'show') {
            $hash['all_nums'] = Goods::get_data_count($wheres);                                     //商品总数
        }
        $new_data = date('Y-m-d H:i:s', time() - 7 * 24 * 60 * 60);
        //是否显示上新总数
        if (isset($_search['new_nums']) && $_search['new_nums'] === 'show') {
            $wheres[] = ['column' => 'shelve_at', 'operator' => '>', 'value' => $new_data];
            $hash['new_nums'] = Goods::get_data_count($wheres);                                     //上新总数
            array_pop($wheres);
        }
        //只显示新品
        if (isset($_search['new_goods']) && $_search['new_goods'] === 'show') {
            $wheres[] = ['column' => 'shelve_at', 'operator' => '>', 'value' => $new_data];
        }
        //商品分组
        if (isset($_search['tag_id']) && intval($_search['tag_id']) > 0) {
            $wheres_tag = array(
                array('column' => 'merchant_id', 'value' => $_search['merchant_id'], 'operator' => '='),
                array('column' => 'tag_id', 'value' => $_search['tag_id'], 'operator' => '=')
            );
            $tag_rs = GoodsTagRs::getDataByWhere($wheres_tag, 'id,tag_id,goods_id')->toArray();
            if (!empty($tag_rs)) {
                $goods_ids = array_column($tag_rs, 'goods_id');
                $wheres[] = ['column' => 'id', 'operator' => 'in', 'value' => $goods_ids];
            } else {
                $wheres[] = ['column' => 'id', 'operator' => '=', 'value' => 0];
            }
        }
        //搜索商品名称
        if (isset($_search['name']) && trim($_search['name'])) {
            $wheres[] = ['column' => 'title', 'operator' => 'like', 'value' => '%' . trim($_search['name']) . '%'];    //搜索商品名称
        }
        $hash['search_count'] = Goods::get_data_count($wheres);                                 //符合条件的商品数
        $fields = 'id,merchant_id,title,price,original_price,img,base_csale,is_sku,shelve_at,(base_csale + csale) as csale,csale as goods_csale,csale_show,goods_type';
        //排序 【首页商家时间优先、列表页销量优先。】
        $order = ['column' => 'csale', 'direct' => 'desc'];    //默认销量优先【首页调接口请传参】
        if (isset($_search['csale'])) {//销量排序
            if (trim($_search['csale']) == 'desc') {
                $order = ['column' => 'csale', 'direct' => 'desc'];
            } elseif (trim($_search['csale']) == 'asc') {
                $order = ['column' => 'csale', 'direct' => 'asc'];
            }
        } elseif (isset($_search['price'])) {//商品价格排序
            if (trim($_search['price']) == 'desc') {
                $order = ['column' => 'price', 'direct' => 'desc'];
            } elseif (trim($_search['price']) == 'asc') {
                $order = ['column' => 'price', 'direct' => 'asc'];
            }
        } elseif (isset($_search['shelve_at'])) {//商品上架时间排序
            if (trim($_search['shelve_at']) == 'desc') {
                $order = ['column' => 'shelve_at', 'direct' => 'desc'];
            } elseif (trim($_search['shelve_at']) == 'asc') {
                $order = ['column' => 'shelve_at', 'direct' => 'asc'];
            }
        }
        $orders[] = $order;
        if ($order['column'] != 'shelve_at') {
            $orders[] = ['column' => 'shelve_at', 'direct' => 'desc'];  //第二排序字段:上架时间
        }
        $hash['data'] = Goods::get_data_list($wheres, $fields, $offset, $pagesize, $orders);
        $hash['errcode'] = 0;
        $hash['errmsg'] = '请求成功';
        if (count($hash['data']) == 0) return $hash;
        $goods_ids = array_column($hash['data'], 'id');
        $AloneActivityRecodeService = new AloneActivityRecodeService();
        $list = $AloneActivityRecodeService->getStarting($goods_ids, $_search['merchant_id']);

        $alone_list = [];                               //拼团,秒杀活动数组
        if (!empty($list) && count($list) > 0) {
            foreach ($list as $k => $v) {
                $alone_list[$v->goods_id] = $v->act_type;
            }
        }

        //从满减表中查询活动id
//        $is_all = 1;                                    //初始化值 1为部分商品参加满减,2位全部商品

        $date = date('Y-m-d H:i:s');

        $discount_res_all = DiscountActivity::where('is_delete', 1)
            ->where('merchant_id', $_search['merchant_id'])
            ->where('status', 1)
            ->where('range', 2)
            ->where('start_time', '<=', $date)
            ->where('end_time', '>', $date)
            ->orderBy('id', 'desc')->value('id');
        $discount_ids = [];
        if (!empty($discount_res_all)) {//有满减活动（全部商品都参与）
            $is_all = 2;
        } else {
            $is_all = 1;
            $discount_ids = DiscountActivity::where('discount_activity.is_delete', 1)
                ->where('discount_activity.merchant_id', $_search['merchant_id'])
                ->where('discount_activity.status', 1)
                ->where('discount_activity.start_time', '<=', $date)
                ->where('discount_activity.end_time', '>', $date)
                ->leftjoin('discount_goods', 'discount_goods.discount_id', '=', 'discount_activity.id')
                ->where('discount_activity.range', 1)
                ->whereIn('discount_goods.goods_id', $goods_ids)
                ->lists('discount_goods.goods_id');
            if (!empty($discount_ids)) {
                $discount_ids = $discount_ids->toArray();
            } else {
                $discount_ids = [];
            }
        }
        // 添加活动信息
        if ($hash['data']) {
            foreach ($hash['data'] as $k => &$v) {
                if (array_key_exists($v['id'], $alone_list)) {
                    if($alone_list[$v['id']] == 'seckill'){
                        $hash['data'][$k]['act_type'] = 2;     //1是拼团2是秒杀3是砍价
                    }elseif($alone_list[$v['id']] == 'tuan'){
                        $hash['data'][$k]['act_type'] = 1;
                    }else{
                        $hash['data'][$k]['act_type'] = 3;
                    }
                } else {
                    $hash['data'][$k]['act_type'] = -1;
                }
                if ($is_all == 2) {
                    $hash['data'][$k]['is_promotion'] = 1;
                } else {
                    $hash['data'][$k]['is_promotion'] = in_array($v['id'], $discount_ids) ? 1 : -1;
                }
            }
        }

        return $hash;
    }

    //与前台列表不同-》此处满减获取没开始和已开始的
    public function admin_goods_list($_search = array())
    {
        if (empty($_search['merchant_id']) || (int)$_search['merchant_id'] < 1) 
            return ['errcode' => '99004', 'errmsg' => '商户不能为空'];
        $_search['merchant_id'] = (int)$_search['merchant_id'];
        $time_str = date('Y-m-d H:i:s');
        //原价别名
        $price_field_alias = Shop::get_data_by_merchant_id($_search['merchant_id']);                                      //原价字段
        $hash['price_field_alias'] = isset($price_field_alias['price_field_alias'])&&!empty($price_field_alias['price_field_alias']) ? $price_field_alias['price_field_alias'] : '原价';
        //搜索条件
        //接收数据(页码和显示条数)
        $pagesize = isset($_search['pagesize']) && (int)$_search['pagesize'] > 0 ? (int)$_search['pagesize'] : 16;      //默认显示16条数据
        $page = isset($_search['page']) && (int)$_search['page'] > 0 ? (int)$_search['page'] : 1;                       //默认显示第一页
        $offset = ($page - 1) * $pagesize;
        //$ids_str = empty($ids) ? 0 : implode(',', $ids);          //数组转为字符串
        $wheres = [
            ['column' => 'merchant_id', 'operator' => '=', 'value' => $_search['merchant_id']],
            ['column' => 'is_delete', 'operator' => '=', 'value' => 1],                          //商品未删除
            ['column' => 'onsale', 'operator' => '=', 'value' => 1],                             //商品未下架
        ];
        //是否显示商品总数
        if (isset($_search['all_nums']) && $_search['all_nums'] === 'show') {
            $hash['all_nums'] = Goods::get_data_count($wheres);                                     //商品总数
        }
        $new_data = date('Y-m-d H:i:s', time() - 7 * 24 * 60 * 60);
        //是否显示上新总数
        if (isset($_search['new_nums']) && $_search['new_nums'] === 'show') {
            $wheres[] = ['column' => 'shelve_at', 'operator' => '>', 'value' => $new_data];
            $hash['new_nums'] = Goods::get_data_count($wheres);                                     //上新总数
            array_pop($wheres);
        }
        //只显示新品
        if (isset($_search['new_goods']) && $_search['new_goods'] === 'show') {
            $wheres[] = ['column' => 'shelve_at', 'operator' => '>', 'value' => $new_data];
        }
        //商品分组
        if (isset($_search['tag_id']) && intval($_search['tag_id']) > 0) {
            $wheres_tag = array(
                array('column' => 'merchant_id', 'value' => $_search['merchant_id'], 'operator' => '='),
                array('column' => 'tag_id', 'value' => $_search['tag_id'], 'operator' => '=')
            );
            $tag_rs = GoodsTagRs::getDataByWhere($wheres_tag, 'id,tag_id,goods_id')->toArray();
            if (!empty($tag_rs)) {
                $goods_ids = array_column($tag_rs, 'goods_id');
                $wheres[] = ['column' => 'id', 'operator' => 'in', 'value' => $goods_ids];
            } else {
                $wheres[] = ['column' => 'id', 'operator' => '=', 'value' => 0];
            }
        }
        //搜索商品名称
        if (isset($_search['name']) && trim($_search['name'])) {
            $wheres[] = ['column' => 'title', 'operator' => 'like', 'value' => '%' . trim($_search['name']) . '%'];    //搜索商品名称
        }
        $hash['search_count'] = Goods::get_data_count($wheres);                                 //符合条件的商品数
        $fields = 'id,merchant_id,title,price,original_price,img,base_csale,is_sku,shelve_at,(base_csale + csale) as csale,csale as goods_csale,csale_show,created_time as goods_created_time,stock,goods_type';
        //排序 【首页商家时间优先、列表页销量优先。】
        $order = ['column' => 'csale', 'direct' => 'desc'];    //默认销量优先【首页调接口请传参】
        if (isset($_search['csale'])) {//销量排序
            if (trim($_search['csale']) == 'desc') {
                $order = ['column' => 'csale', 'direct' => 'desc'];
            } elseif (trim($_search['csale']) == 'asc') {
                $order = ['column' => 'csale', 'direct' => 'asc'];
            }
        } elseif (isset($_search['price'])) {//商品价格排序
            if (trim($_search['price']) == 'desc') {
                $order = ['column' => 'price', 'direct' => 'desc'];
            } elseif (trim($_search['price']) == 'asc') {
                $order = ['column' => 'price', 'direct' => 'asc'];
            }
        } elseif (isset($_search['shelve_at'])) {//商品上架时间排序
            if (trim($_search['shelve_at']) == 'desc') {
                $order = ['column' => 'shelve_at', 'direct' => 'desc'];
            } elseif (trim($_search['shelve_at']) == 'asc') {
                $order = ['column' => 'shelve_at', 'direct' => 'asc'];
            }
        } elseif (isset($_search['goods_created_time'])) {//商品创建时间排序
            if (trim($_search['goods_created_time']) == 'desc') {
                $order = ['column' => 'goods_created_time', 'direct' => 'desc'];
            } elseif (trim($_search['goods_created_time']) == 'asc') {
                $order = ['column' => 'goods_created_time', 'direct' => 'asc'];
            }
        } elseif (isset($_search['stock'])) {//商品库存
            if (trim($_search['stock']) == 'desc') {
                $order = ['column' => 'stock', 'direct' => 'desc'];
            } elseif (trim($_search['stock']) == 'asc') {
                $order = ['column' => 'stock', 'direct' => 'asc'];
            }
        }
        $orders[] = $order;
        if ($order['column'] != 'shelve_at') {
            $ordrs[] = ['column' => 'shelve_at', 'direct' => 'desc'];  //第二排序字段:上架时间
        }
        $hash['data'] = Goods::get_data_list($wheres, $fields, $offset, $pagesize, $orders);
        //echo Goods;
        $hash['errcode'] = 0;
        $hash['errmsg'] = '请求成功';
        if (count($hash['data']) == 0) 
            return $hash;
        $goods_ids = array_column($hash['data'], 'id');
        //校验是否已参加活动
        $AloneActivityRecodeService = new AloneActivityRecodeService();
        $list = $AloneActivityRecodeService->getCreated($goods_ids, $_search['merchant_id']);
        $alone_list = [];                               //拼团,秒杀活动数组
        if (count($list) > 0) {
            foreach ($list as $k => $v) {
                $alone_list[$v->goods_id]['act_type'] = $v->act_type;
                $alone_list[$v->goods_id]['start_time'] = $v->start_time;
                $alone_list[$v->goods_id]['created_time'] = $v->created_time;
                $alone_list[$v->goods_id]['finish_time'] = $v->finish_time;
            }
        }
        //从满减表中查询活动id
        //满减活动-自选商品
        $start_date = isset($_search['start_date'])?$_search['start_date']:'';
        $end_date = isset($_search['end_date'])?$_search['end_date']:'';
        
        $discount_ids = DiscountActivity::where('discount_activity.is_delete', 1)
            ->leftjoin('discount_goods', 'discount_goods.discount_id', '=', 'discount_activity.id')
            ->where('discount_activity.merchant_id', $_search['merchant_id'])
            ->where('discount_activity.range', 1)
            ->whereIn('discount_goods.goods_id', $goods_ids)
            ->havingRaw('`discount_goods`.`goods_id` IN ('.implode(',',$goods_ids).')');
        if(!empty($start_date) && !empty($end_date)){
            $discount_ids = $discount_ids->where(function ($discount_ids) use ($start_date,$end_date) {
                $discount_ids->where(function ($discount_ids) use ($start_date,$end_date) {
                    $discount_ids->where('discount_activity.start_time','<=',$start_date)->where('discount_activity.end_time','>=',$start_date);
                })->orWhere(function ($discount_ids) use ($start_date,$end_date) {
                    $discount_ids->where('discount_activity.start_time','<=',$end_date)->where('discount_activity.end_time','>=',$end_date);
                });
            });
        }
        
        $discount_ids = $discount_ids->get(['discount_goods.discount_id as id', 'discount_activity.end_time', 'discount_activity.start_time', 'discount_goods.goods_id']);
        $discount_ids = collect($discount_ids);
        $discount_ids = $discount_ids->keyBy('goods_id')->toArray();
        //dd($goods_ids);
        //dd($discount_ids);
        //dd($hash['data']);
        // 添加活动信息
        if ($hash['data']) {
            foreach ($hash['data'] as $k => &$v) {
                if (!empty($alone_list) && array_key_exists($v['id'], $alone_list)) {
                    //$hash['data'][$k]['act_type'] = $alone_list[$v['id']]['act_type'] == 'seckill' ? 2 : 1;     //1是拼团2是秒杀
                    if($alone_list[$v['id']]['act_type'] == 'tuan') $hash['data'][$k]['act_type'] = 1; //拼团
                    if($alone_list[$v['id']]['act_type'] == 'seckill') $hash['data'][$k]['act_type'] = 2; //秒杀
                    if($alone_list[$v['id']]['act_type'] == 'bargain') $hash['data'][$k]['act_type'] = 3; //砍价
                    $hash['data'][$k]['start_time'] = $alone_list[$v['id']]['start_time'];     //活动开始时间
                    $hash['data'][$k]['created_time'] = $alone_list[$v['id']]['created_time'];     //创建时间
                    $hash['data'][$k]['finish_time'] = $alone_list[$v['id']]['finish_time'];     //创建时间
                } else {
                    $hash['data'][$k]['act_type'] = -1;
                    $hash['data'][$k]['start_time'] = '';
                    $hash['data'][$k]['created_time'] = '';
                    $hash['data'][$k]['finish_time'] = '';
                }
                if (!empty($discount_ids) && array_key_exists($v['id'], $discount_ids)) {
                    $hash['data'][$k]['is_promotion'] = 1;
                    $hash['data'][$k]['discount'][] = $discount_ids[$v['id']];//活动信息
                } else {
                    $hash['data'][$k]['is_promotion'] = -1;
                }
            }
        }
        //满减活动-全店参与
        $discount_res_all = DiscountActivity::where('is_delete', 1)
                            ->where('merchant_id', $_search['merchant_id'])
                            ->where('range', 2)
                            ->orderBy('id', 'desc');
        $discount_res_all_array = $discount_res_all->get()->toArray();
        if(isset($discount_res_all_array) && !empty($discount_res_all_array)){
            if(!empty($start_date) && !empty($end_date)){
                $discount_res_all = $discount_res_all->where(function ($discount_res_all) use ($start_date,$end_date) {
                    $discount_res_all->where('discount_activity.start_time','>=',$start_date)->where('discount_activity.start_time','<=',$end_date);
                })->orWhere(function ($discount_res_all) use ($start_date,$end_date) {
                    $discount_res_all->where('discount_activity.end_time','>=',$start_date)->where('discount_activity.end_time','<=',$end_date);
                });
            }
            $discount_res_all = $discount_res_all->get(['id','start_time', 'end_time']);
            $hash['discount'] = $discount_res_all;
            if ($hash['data'] && !$discount_res_all->isEmpty()) {
                foreach ($hash['data'] as $k => &$v) {
                    if (!empty($discount_res_all)) {
                        $hash['data'][$k]['is_promotion'] = 1;
                        $hash['data'][$k]['discount'][] = $discount_res_all[0]['id'];//活动信息
                    }
                }
            }
        }

        return $hash;
    }


    /**
     *
     *查询某个商品的所有评论
     * @param int $_search ['merchant_id']  商户ID(必选)
     * @param int $_search ['goods_id']  商品ID(必选)
     * @param string $_search ['has_img']  1只显示有图评论,其他值为显示所有评论
     * @param int $_search ['pagesize']  每页显示条数(可选,默认10)
     * @param int $_search ['page']      显示页数(可选,默认1)
     *email  renruiqi@dodoca.com
     */
    public function comment_list($_search = array())
    {
        if (!isset($_search['goods_id']) || (int)$_search['goods_id'] <= 0) {
            return (array('errcode' => 80001, 'errmsg' => '网络繁忙，请稍后再试'));
        }
        if(!isset($_search['merchant_id'])){
            return (array('errcode' => 99004, 'errmsg' => '商户id缺失'));

        }
        //接收数据(页码和显示条数)
        $pagesize = isset($_search['pagesize']) && (int)$_search['pagesize'] > 0 ? (int)$_search['pagesize'] : 10;     //默认显示10条数据
        $page = isset($_search['page']) && (int)$_search['page'] > 0 ? (int)$_search['page'] : 1;                      //默认显示第一页
        $offset = ($page - 1) * $pagesize;
        //评论筛选条件:是否有图
        $has_img = isset($_search['has_img']) && (int)$_search['has_img'] == 1 ? 1 : 0;                                 //1为有图,0为无图评论
        //保留字段:id,member_id,order_id,用户昵称,has_img 0:无图 1:有图,用户评论,商家回复,商家回复时间,评分,is_anonymous是否匿名0:否 1:是,'评论时间'
        $fields = "id,member_id,order_id,nickname,has_img,content,reply,reply_time,score,is_anonymous,created_time,props_str as props";

        $wheres = [

            ['column' => 'goods_id', 'operator' => '=', 'value' => $_search['goods_id']],
            ['column' => 'is_show', 'operator' => '=', 'value' => 1],            //1为显示
            ['column' => 'nopass', 'operator' => '=', 'value' => 0],            //0为审核通过
        ];
        $hash['count'] = OrderComment::get_data_count($wheres);                                                     //评论数量总数量
        $wheres[] = ['column' => 'has_img', 'operator' => '=', 'value' => 1];
        $hash['img_count'] = OrderComment::get_data_count($wheres);                                                 //有图片的评论数量
        if ($has_img != 1) {
            array_pop($wheres);     //弹出图片的查询条件
        }

        $hash['data'] = OrderComment::get_data_list($wheres, $fields, $offset, $pagesize);
        if (!empty($hash['data'])) {
            foreach ($hash['data'] as $key => &$v) {
                //如果有评论图片,则查询评论图片表
                if ($v['has_img'] == 1) {
                    $img_list = OrderCommentImg::query()
                        ->where('comment_id', $v['id'])
                        ->where('is_delete', 1)//未删除
                        ->lists('img');                             //保留图片地址列
                    $v['img_list'] = count($img_list) > 0 ? $img_list : [];
                }

                //获取用户头像
                if ((int)$v['order_id'] === 0) {
                    //如果为预设评论
                    $qiniu = env('QINIU_STATIC_DOMAIN');
                    $avatar = CommentVirtualMember::select('avatar')->where('id', '=', $v['member_id'])->first();
                    $v['member_avatar'] = $avatar == null ? '' : $qiniu . '/' . $avatar->avatar;
                } else {
                    //订单评论
                    // $avatar = Member::select('avatar')->where('id', '=', $v['member_id'])->first();
                    $avatar = Member:: get_data_by_id($v['member_id'], $_search['merchant_id']);
                    $v['member_avatar'] = $avatar == null ? '' : $avatar->avatar;
                }
                //获取产品规格
                $props = explode(';', $v['props']);
                if (mb_substr($props[0], 0, 4) !== '服务门店') {
                    $props = [];            //普通商品废除规格
                } else {
                    foreach ($props as $kk => $vv) {
                        $str = (mb_substr($vv, 0, 4));
                        if ($str == '服务日期' || $str == '服务时间') {      //释放服务日期和服务时间段
                            unset($props[$kk]);
                        }

                    }
                }
                $v['props'] = $props;
                // $v['props'] = $props[0];
                // array_pop($v['props']);
                // array_pop($v['props']);
//                if (!env('APP_URL', '') == 'https://applet.rrdoy.com') {
//                    $props = OrderGoods::query()
//                        ->where('order_id', $v['order_id'])
//                        ->first();
//                    $v['props'] = $props ? $props->props : '';
//                }
            }
        }
        $hash['errcode'] = 0;
        $hash['errmsg'] = '请求成功';
        return $hash;
    }


    //验证获取库存 增加库存 自减库存函数参数
    public function verify_stock_param($param)
    {
        $this->config_err = config('err');

        $data['errcode'] = 0;
        $data['merchant_id'] = isset($param['merchant_id']) ? $param['merchant_id'] : null;;
        $data['activity'] = empty($param['activity']) ? '' : $param['activity'];
        $data['stock_num'] = isset($param['stock_num']) ? $param['stock_num'] : 0;
        $data['goods_id'] = isset($param['goods_id']) ? $param['goods_id'] : null;;
        $data['goods_spec_id'] = isset($param['goods_spec_id']) ? $param['goods_spec_id'] : null;
        $data['date'] = empty($param['date']) ? null : $param['date'];
        $data['date_timestamp'] = 0;

        if (empty($data['merchant_id'])) {
            return $this->config_err['99004'];//商家id缺失
        }

        if (empty($data['goods_id'])) {
            return $this->config_err['80001'];//缺少商品id
        }

        if (!preg_match("/^[0-9][0-9]*$/", $data['stock_num'])) {
            return $this->config_err['80008'];//系统错
        }

        $goods_res = Goods::get_data_by_id($data['goods_id'], $data['merchant_id']);

        $data['is_sku'] = $goods_res['is_sku']; //获取商品库存规则

        //单规格商品 规格id=0
        if ($data['is_sku'] === 0) {
            $data['goods_spec_id'] = 0;
        } elseif ($data['is_sku'] === 1 || $data['is_sku'] === 2) {
            if (empty($data['goods_spec_id'])) return $this->config_err['80002'];//缺少商品规格id

            if ($data['is_sku'] === 2) {
                if (empty($param['date'])) return $this->config_err['80006'];//缺少预约商品日期
                $data['date_timestamp'] = strtotime($param['date']);
                if (empty($data['date_timestamp'])) return $this->config_err['80007'];//预约商品日期非法

            }
        } else {
            return $this->config_err['80010'];//系统错误
        }

        return $data;
    }

    /**
     * 增加商品销量
     * @author zhangchangchun@dodoca.com
     * @merchant_id 商户id
     * @param $stock_num 减库存量
     * @param $goods_id 商品id
     * @param $goods_spec_id 规格id 没有传0
     */
    public function incCsale($data)
    {
        $merchant_id = isset($data['merchant_id']) ? (int)$data['merchant_id'] : 0;
        $goods_id = isset($data['goods_id']) ? (int)$data['goods_id'] : 0;
        $goods_spec_id = isset($data['goods_spec_id']) ? (int)$data['goods_spec_id'] : 0;
        $stock_num = isset($data['stock_num']) ? (int)$data['stock_num'] : 0;
        if ($stock_num) {
            $r = Goods::where(['id' => $goods_id, 'merchant_id' => $merchant_id])->increment('csale', $stock_num);
            Goods::forgetCache($goods_id, $merchant_id);
            if ($goods_spec_id) {
                GoodsSpec::where(['id' => $goods_spec_id, 'merchant_id' => $merchant_id])->increment('csale', $stock_num);
                GoodsSpec::forgetCache($goods_id, $merchant_id);
            }
            return true;
        }
        return false;
    }

    /**
     * 减少商品销量
     * @author zhangchangchun@dodoca.com
     * @merchant_id 商户id
     * @param $stock_num 减库存量
     * @param $goods_id 商品id
     * @param $goods_spec_id 规格id 没有传0
     */
    public function desCsale($data)
    {
        $merchant_id = isset($data['merchant_id']) ? (int)$data['merchant_id'] : 0;
        $goods_id = isset($data['goods_id']) ? (int)$data['goods_id'] : 0;
        $goods_spec_id = isset($data['goods_spec_id']) ? (int)$data['goods_spec_id'] : 0;
        $stock_num = isset($data['stock_num']) ? (int)$data['stock_num'] : 0;
        if ($stock_num) {
            Goods::whereRaw("csale-" . $stock_num . ">=0")->where(['id' => $goods_id, 'merchant_id' => $merchant_id])->decrement('csale', $stock_num);
            Goods::forgetCache($goods_id, $merchant_id);
            if ($goods_spec_id) {
                GoodsSpec::whereRaw("csale-" . $stock_num . ">=0")->where(['id' => $goods_spec_id, 'merchant_id' => $merchant_id])->decrement('csale', $stock_num);
                GoodsSpec::forgetCache($goods_id, $merchant_id);
            }
            return true;
        }
        return false;
    }

    /**
     * 获取限购数量
     * @author: tangkang@dodoca.com
     */
    public function getCquota($buy_quantity, $goods_id, $member_id, $merchant_id,$data=[])
    {
        /*
        无效订单逻辑（除了无效订单，其它的都算购买了）
        order_info的status in (1,2,3) 并且未支付
        pay_status
        */
        if (empty($goods_id)) return ['errcode' => 1, 'errmsg' => '缺少商品参数（获取限购数量）'];
        if (empty($member_id)) return ['errcode' => 1, 'errmsg' => '缺少会员ID（获取限购数量）'];
        if (empty($merchant_id)) return ['errcode' => 1, 'errmsg' => '缺少商家ID（获取限购数量）'];
        $goods_res = Goods::get_data_by_id($goods_id, $merchant_id);
        if (empty($goods_res)) return ['errcode' => 1, 'errmsg' => '获取商品信息失败（获取限购数量）'];
		
		$cquota_sum = $goods_res->cquota; //限购数量
		
		//优惠活动限购
		$order_type = isset($data['order_type']) ? $data['order_type'] : 0;
		$ump_id = isset($data['ump_id']) ? $data['ump_id'] : 0;
		if($order_type==ORDER_SECKILL && $ump_id) {	//秒杀限购
			$seckillInfo = Seckill::get_data_by_id($ump_id,$merchant_id);
			if(!$seckillInfo) {
				return ['errcode' => 1, 'errmsg' => '秒杀不存在', 'data' => ''];
			}
			
			$cquota_sum = $seckillInfo['cquota'];
			if($seckillInfo['cquota']>0) {
				
				// 已关闭 ,交易自动取消(未付款情况)
				// 已关闭 ,买家自己取消订单
				// 已关闭 ,商家关闭
				$bought_quantity = OrderGoods::where('order_goods.member_id', $member_id)
					->where('order_goods.merchant_id', $merchant_id)
					->where('order_goods.goods_id', $goods_id)
					->leftjoin('order_info', 'order_info.id', '=', 'order_goods.order_id')
					->whereNotIn('order_info.status', [ORDER_AUTO_CANCELED, ORDER_BUYERS_CANCELED, ORDER_MERCHANT_CANCEL])
					->leftjoin('order_goods_ump', function($join) {
						$join->on('order_goods_ump.goods_id','=','order_goods.goods_id')
							 ->on('order_goods_ump.order_id','=','order_goods.order_id');
					})
					->where('order_goods_ump.ump_id', $seckillInfo['id'])
					->where('order_goods_ump.ump_type', 6)
					->sum('quantity');
			}
		} else if($cquota_sum > 0) {
			
			// 已关闭 ,交易自动取消(未付款情况)
			// 已关闭 ,买家自己取消订单
			// 已关闭 ,商家关闭
			$bought_quantity = OrderGoods::where('order_goods.member_id', $member_id)
				->where('order_goods.merchant_id', $merchant_id)
				->where('order_goods.goods_id', $goods_id)
				->leftjoin('order_info', 'order_info.id', '=', 'order_goods.order_id')
				->whereNotIn('order_info.status', [ORDER_AUTO_CANCELED, ORDER_BUYERS_CANCELED, ORDER_MERCHANT_CANCEL])
				->sum('quantity');
			
		}
        
		if ($cquota_sum > 0) {//限购
			
            if ((intval($bought_quantity) + intval($buy_quantity)) <= $cquota_sum) {//限购数量
                return ['errcode' => 0, 'errmsg' => '获取限购数量成功', 'data' => $cquota_sum];
            } else {
                return ['errcode' => 1, 'errmsg' => '每人限购 ' . $cquota_sum . ' 件', 'data' => $cquota_sum];
            }
        }
        return ['errcode' => 0, 'errmsg' => '商品没有限购', 'data' => $cquota_sum];
    }

    /***
     * @param $goods_id      商品id
     * @param $merchant_id   用户id
     * @param $spec_id       规格id
     * @Author  DuMing
     * 获取商品规格参与活动的最大价格
     */
    public function getMaxPrice($goods_id, $merchant_id, $spec_id)
    {
        //查询商品参加的活动
        $time_str = $time_str = date('Y-m-d H:i:s');
        $tuan_list = AloneActivityRecode::select('goods_id', 'act_type', 'start_time', 'created_time', 'finish_time')//'拼团'
        ->where('merchant_id', $merchant_id)
            ->where('act_type', 'tuan')
//            ->where('start_time', '<', $time_str)
            ->where('finish_time', '=', '0000-00-00 00:00:00')
            ->where('goods_id', $goods_id);
        $list = AloneActivityRecode::select('goods_id', 'act_type', 'start_time', 'created_time', 'finish_time')//'秒杀'
        ->where('merchant_id', $merchant_id)
            ->where('act_type', 'seckill')
//            ->where('start_time', '<', $time_str)
            ->where('finish_time', '>', $time_str)
            ->where('goods_id', $goods_id)
            ->union($tuan_list)
            ->get()->toArray();
        if (!empty($list)) {
            $aloneActivityRecode = $list[0];
        } else {
            $aloneActivityRecode = [];
        }
        $return_data = [];
        //判断参加的秒杀，或者团购
        if (isset($aloneActivityRecode['act_type'])) {
            //团购
            if ($aloneActivityRecode['act_type'] == 'tuan') {
                $fightGroupService = new FightgroupService();
                $return_data = $fightGroupService->maxSpecLadderPrice($merchant_id, $goods_id, $aloneActivityRecode['alone_id']);
                //秒杀
            } else if ($aloneActivityRecode['act_type'] == 'tuan') {
                $seckillService = new SeckillService();
                $return_data = $seckillService->getMaxPrice($goods_id, $merchant_id);
            }
        }
        return $return_data;
    }

    /**
     *根据商品id数组返回商品基础信息
     * @param  $ids array 商品id数组 (必选)
     * @param  $merchant_id int 商户id (必选)
     *author  renruiqi@dodoca.com
     */
    public function getGoodsByIds($merchant_id = null, $ids = array())
    {
        if (count($ids) < 1) return ['errcode' => 0, 'errmsg' => '操作成功', 'data' => []];
        if ((int)($merchant_id) < 1) return ['errcode' => 99004, 'errmsg' => '商户id缺失', 'data' => []];
        $goods = Goods::select('title', 'id', 'original_price', 'price', 'max_price', 'img', 'base_csale', 'csale', 'is_sku','goods_type')
            ->where('merchant_id', $merchant_id)
            ->where('is_delete', 1)
            ->whereIn('id', $ids)
            ->get();
        if ($goods->count() > 0) {
            //查询活动信息
            $goods = $goods->toArray();
            //循环将数组拼接为 'id'=>val的形式
            foreach ($goods as $v) {
                $goods_new[$v['id']] = $v;
            }
            $goods_id = array_column($goods, 'id');
            $goods = null;  //清除 而后重新赋值
            //循环将数据按照whereIn中数据排序
            foreach ($ids as $vv) {
                if (in_array($vv, $goods_id)) {
                    $goods[] = $goods_new[$vv];
                }
            }
            $AloneActivityRecodeService = new AloneActivityRecodeService();
            $list = $AloneActivityRecodeService->getStarting($goods_id, $merchant_id);
            if (!empty($list)) {
                $list = collect($list);
                $alone_list = $list->keyBy('goods_id')->toArray();
            }
            $date = date('Y-m-d H:i:s');
            // 是否全部满减
            $discount_res_all = DiscountActivity::where('is_delete', 1)
                ->where('merchant_id', $merchant_id)
                ->where('start_time', '<=', $date)
                ->where('range', 2)//全部商品
                ->where('end_time', '>', $date)//未结束的满减活动
                ->orderBy('id', 'desc')->get(['id', 'start_time', 'end_time', 'title']);
            if (count($discount_res_all) < 1) {
                //从满减表中查询活动商品id
                $discount_ids = DiscountActivity::where('discount_activity.is_delete', 1)
                    ->where('discount_activity.merchant_id', $merchant_id)
                    ->where('discount_activity.start_time', '<=', $date)
                    ->where('discount_activity.end_time', '>', $date)//未结束的满减活动
                    ->leftjoin('discount_goods', 'discount_goods.discount_id', '=', 'discount_activity.id')
                    ->where('discount_activity.range', 1)
                    ->whereIn('discount_goods.goods_id', $goods_id)
                    ->get(['discount_goods.discount_id as id', 'discount_activity.end_time', 'discount_activity.start_time', 'discount_goods.goods_id']);
                $discount_ids = collect($discount_ids);
                $discount_ids = $discount_ids->keyBy('goods_id')->toArray();
            }
            //添加活动信息
            foreach ($goods as &$v) {
                if (!empty($discount_ids) && array_key_exists($v['id'], $discount_ids)) {
                    $v['is_promotion'] = 1;
                    // $v['discount'][] = $discount_ids[$v['id']];//活动信息
                } else {
                    if (count($discount_res_all) > 0) {//全店参加满减
                        $v['is_promotion'] = 1;
                        // $v['discount'][] = $discount_res_all;//活动信息
                    } else {
                        $v['is_promotion'] = -1;
                    }
                }
                if (!empty($alone_list) && array_key_exists($v['id'], $alone_list)) {
                    $v['alone_type'] = $alone_list[$v['id']]['act_type'];
                    // $v['alone_id'] = $alone_list[$v['alone_id']];
                } else {
                    $v['alone_type'] = '';
                }
            }
            // return ['errcode' => 0, 'errmsg' => '操作成功', 'data' => $goods];
            return ['errcode' => 0, 'errmsg' => '操作成功', 'data' => $goods];

        } else {
            return ['errcode' => 0, 'errmsg' => '操作成功', 'data' => []];
        }
    }

    /**
     *
     *根据商户id查询秒杀商品列表
     * @param int $_search ['merchant_id']  商户id(必选)
     * @param int $_search ['pagesize']  每页显示条数(可选,默认10)
     * @param int $_search ['page']      显示页数(可选,默认1)
     *邮箱  renruiqi@dodoca.com
     */
    public function getSeckillGoods(array $_search)
    {
        if (empty($_search['merchant_id']) || (int)$_search['merchant_id'] < 1) return ['errcode' => '99004', 'errmsg' => '缺少总店id'];
        $merchant_id = (int)$_search['merchant_id'];
        $pagesize = isset($_search['pagesize']) && (int)$_search['pagesize'] > 0 ? (int)$_search['pagesize'] : 10;      //默认显示10条数据
        $page = isset($_search['page']) && (int)$_search['page'] > 0 ? (int)$_search['page'] : 1;                       //默认显示第一页
        $offset = ($page - 1) * $pagesize;
        $date = date('Y-m-d H:i:s');
        $query = Seckill::select('id', 'goods_id', 'goods_title', 'goods_img', 'price')
            ->where('merchant_id', $merchant_id)
            ->where('end_time', '>', $date);
        $data['count'] = $query->count();
        $data['list'] = $query->offset($offset)
            ->limit($pagesize)
            ->orderby('id', 'desc')
            ->get();
        if (count($data['list']) > 0) $data['list'] = $data['list']->toArray();
        return ['errcode' => 0, 'errmsg' => '操作成功', 'data' => $data];
    }

    /**
     *
     *根据商户id+活动表id数组  查询秒杀商品列表
     * @param int $_search ['merchant_id']  商户id(必选)
     * @param int $_search ['seckill_ids']  秒杀表id数组 (必选)
     * @param int $_search ['pagesize']  每页显示条数(可选,默认10)            //分页保留
     * @param int $_search ['page']      显示页数(可选,默认1)
     *邮箱  renruiqi@dodoca.com
     */
    public function getGoodsBySeckillIds(array $_search)
    {
        if (empty($_search['merchant_id']) || (int)$_search['merchant_id'] < 1) return ['errcode' => '99004', 'errmsg' => '缺少总店id'];
        if (empty($_search['seckill_ids']) || count($_search['seckill_ids']) < 1 || (!is_array($_search['seckill_ids']))) return ['errcode' => '70024', 'errmsg' => '缺少商品数组'];
        $merchant_id = (int)$_search['merchant_id'];
        // $pagesize = isset($_search['pagesize']) && (int)$_search['pagesize'] > 0 ? (int)$_search['pagesize'] : 10;      //默认显示10条数据
        // $page = isset($_search['page']) && (int)$_search['page'] > 0 ? (int)$_search['page'] : 1;                       //默认显示第一页
        // $offset = ($page - 1) * $pagesize;
        $data['date'] = date('Y-m-d H:i:s');
        $data['list'] = [];
        // $data['list'] = Seckill::select('id', 'goods_id', 'goods_title', 'goods_img', 'price', 'start_time', 'presale_time', 'end_time')
        //     ->where('merchant_id', $merchant_id)
        //     ->whereIn('id', $_search['seckill_ids'])//传递的活动表id
        //     // ->where('end_time', '>', $data['data'])
        //     // ->offset($offset)
        //     // ->limit($pagesize)
        //     // ->orderby('id', 'desc')
        //     ->get();
        foreach ($_search['seckill_ids'] as $v) {
            $res = Seckill::select('id', 'goods_id', 'goods_title', 'goods_img', 'price', 'start_time', 'presale_time', 'end_time')
                ->where('merchant_id', $merchant_id)
                ->find((int)$v);
            if ($res) {
                $data['list'][] = $res->toArray();
            }
        }
        return ['errcode' => 0, 'errmsg' => '操作成功', 'data' => $data];
    }


}


<?php

namespace App\Http\Controllers\Weapp\Appt;

use App\Http\Requests\ApptRequest;
use App\Http\Requests\Weapp\ApptTimeListRequest;
use App\Http\Requests\Weapp\ApptDateListRequest;
use App\Models\ApptStaff;
use App\Models\Goods;
use App\Models\GoodsAppt;
use App\Models\GoodsProp;
use App\Models\GoodsSpec;
use App\Models\OrderAppt;
use App\Models\Prop;
use App\Models\PropValue;
use App\Models\Store;
use App\Services\ApptService;
use App\Services\BuyService;
use App\Services\GoodsService;
use App\Utils\CommonApi;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Facades\Member;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\Weapp\NearestStoreRequest;
use App\Http\Requests\Weapp\GoodsStaffRequest;

class ApptController extends Controller
{
    public function __construct()
    {
        $this->merchant_id = Member::merchant_id();
        $this->member_id = Member::id();
        $this->config_err = config('err');
    }

    /**
     * 获取预约商品库存
     * @param $goods_id
     * @param Request $request
     */
    public function getApptStock(Request $request, GoodsService $goodsService)
    {
        $param = [
            'merchant_id' => $this->merchant_id,
            'goods_id' => $request->get('goods_id', null),
            'goods_spec_id' => $request->get('goods_spec_id', null),
            'date' => $request->get('date', null),
//            'type' => 2,//预约商品库存
        ];
        $stock_res = $goodsService->getGoodsStock($param);
        if ($stock_res['errcode'] != 0) return $stock_res;
        return $stock_res;
    }

    /**
     * 获取商品售卖的门店列表
     * @author: tangkang@dodoca.com
     */
    public function getNearestStore(NearestStoreRequest $request)
    {
        $goods_id = $request->get('goods_id');
        $goods_res = Goods::get_data_by_id($goods_id, $this->merchant_id);
        if (empty($goods_res)) return ['errcode' => 1, 'errmsg' => '商品信息查询失败'];
        $goods_prop_res = GoodsProp::where('goods_prop.merchant_id', $this->merchant_id)
            ->where('goods_prop.is_delete', 1)
            ->where('goods_prop.goods_id', $goods_id)
            ->leftJoin('prop', 'goods_prop.prop_id', '=', 'prop.id')
            ->where('prop.is_delete', 1)
            ->whereIn('prop.merchant_id', [$this->merchant_id, 0])
            ->where('prop.goods_cat_id', $goods_res->goods_cat_id)
            ->where('prop.prop_type', 1)
            ->where('prop.prop_value_type', 1)
            ->lists('goods_prop.prop_vid')->all();
        if (empty($goods_prop_res)) return ['errcode' => 0, 'errmsg' => '无门店可预约', 'data' => []];
        $store_res = [];
        $request_lng = $request->get('lng', null);
        $request_lat = $request->get('lat', null);
        if (empty($request_lat) || empty($request_lng)) {
            foreach ($goods_prop_res as $value) {
                $tmp = Store::get_data_by_id($value, $this->merchant_id);
                if ($tmp->enabled == 1 && $tmp->is_delete == 1) {
                    $store_res[] = $tmp;
                }
            }
            if (empty($store_res)) return ['errcode' => 0, 'errmsg' => 'OK', 'data' => ['lists' => $store_res, 'count' => 0]];
//            if (empty($store_res)) return ['errcode' => 1, 'errmsg' => '门店信息查询失败'];
        } else {
            $distance_ = array();
            foreach ($goods_prop_res as $value) {
                $tmp = Store::get_data_by_id($value, $this->merchant_id);
                if ($tmp->enabled == 1 && $tmp->is_delete == 1) {
                    $from = [$request_lng, $request_lat];
                    $to = [$tmp['lng'], $tmp['lat']];
                    $tmp['distance'] = self::get_distance($from, $to) . 'km';
                    $distance_[] = $tmp['distance'];
                    $store_res[] = $tmp;
                }
            }
            if (empty($store_res)) return ['errcode' => 0, 'errmsg' => 'OK', 'data' => ['lists' => $store_res, 'count' => 0]];
//            if (empty($store_res)) return ['errcode' => 1, 'errmsg' => '门店信息查询失败'];
            array_multisort($distance_, SORT_ASC, SORT_NUMERIC, $store_res);
        }
        $data['lists'] = $store_res;
        $data['count'] = count($store_res);
        return ['errcode' => 0, 'errmsg' => 'OK', 'data' => $data];
    }

    /**
     * 查商品下可选技师
     * @author: tangkang@dodoca.com
     */
    public function getStaff(GoodsStaffRequest $request)
    {
        $goods_id = $request->get('goods_id', null);
        $store_id = $request->get('store_id', null);
        $goods_res = Goods::get_data_by_id($goods_id, $this->merchant_id);
        if (empty($goods_res)) return ['errcode' => 1, 'errmsg' => '商品信息查询失败'];
        $staff_res = GoodsProp::where('goods_prop.merchant_id', $this->merchant_id)
            ->where('goods_prop.goods_id', $goods_id)
            ->where('goods_prop.is_delete', 1)
            ->leftJoin('prop', 'goods_prop.prop_id', '=', 'prop.id')
            ->whereIn('prop.merchant_id', [$this->merchant_id, 0])
            ->where('prop.goods_cat_id', $goods_res->goods_cat_id)
            ->where('prop.prop_type', 1)
            ->where('prop.prop_value_type', 2)
            ->where('prop.is_delete', 1)
            ->leftJoin('appt_staff', 'goods_prop.prop_vid', '=', 'appt_staff.id')
            ->where('appt_staff.merchant_id', $this->merchant_id)
            ->where('appt_staff.store_id', $store_id)
            ->where('appt_staff.is_delete', 1)
            ->get(['appt_staff.id', 'appt_staff.nickname']);
        $data['count'] = 0;
        $data['lists'] = $staff_res;
        if (!empty($staff_res)) {
            $data['count'] = count($staff_res->toArray());
        }
        return ['errcode' => 0, 'errmsg' => 'OK', 'data' => $data];
    }

    /**
     * 根据起点坐标和终点坐标测距离
     * @param  [array]   $from  [起点坐标(经纬度),例如:array(118.012951,36.810024)]
     * @param  [array]   $to    [终点坐标(经纬度)]
     * @param  [bool]    $km        是否以公里为单位 false:米 true:千米(公里)
     * @param  [int]     $decimal   精度 保留小数位数
     * @return [string]  距离数值
     */
    function get_distance($from, $to, $km = true, $decimal = 2)
    {
        sort($from);
        sort($to);
        $EARTH_RADIUS = 6370.996; // 地球半径系数
        $distance = $EARTH_RADIUS * 2 * asin(sqrt(pow(sin(($from[0] * pi() / 180 - $to[0] * pi() / 180) / 2), 2) + cos($from[0] * pi() / 180) * cos($to[0] * pi() / 180) * pow(sin(($from[1] * pi() / 180 - $to[1] * pi() / 180) / 2), 2))) * 1000;
        if ($km) {
            $distance = $distance / 1000;
        }
        return round($distance, $decimal);
    }

    /**
     * 预约商品订单提交
     * @param Request $request
     * @return mixed
     * @author: tangkang@dodoca.com
     */
    public function postApptOrder(ApptRequest $request, BuyService $buyService)
    {
        $goods_id = $request->get('goods_id', 0);
        $goods_spec_id = $request->get('goods_spec_id', 0);
        $date = $request->get('date', 0);
        $quality = $request->get('quality', 0);
        $appt_staff_id = $request->get('appt_staff_id', 0);
        $source = $request->get('source', 0);
        $source_id = $request->get('source_id', 0);
        //非法字符串日期
        $data_temp = date('Y-m-d', strtotime($date));
        if ($data_temp == '1970-01-01') return $this->config_err['80007'];//预约商品日期非法

        $date_type = self::getWeekDay($date);
        $goods_res = Goods::get_data_by_id($goods_id, $this->merchant_id);
        if (empty($goods_res)) return $this->config_err['80004'];//商品下架
        if (empty($goods_res->onsale)) return $this->config_err['80011'];//商品下架
        if ($goods_res->is_delete != 1) return ['errcode' => 1, 'errmsg' => '商品已删除'];
        $goods_appt_res = GoodsAppt::get_data_by_goods_id($goods_id, $this->merchant_id);
        if (empty($goods_appt_res)) {
            return ['errcode' => 1, 'errmsg' => '获取预约信息失败，清重新下单'];
        } else {
            if ($goods_appt_res->mode == 2) {
                if (empty($appt_staff_id)) return ['errcode' => 1, 'errmsg' => '请选择预约技师'];
                $appt_staff_res = ApptStaff::get_data_by_id($appt_staff_id, $this->merchant_id);
                if (empty($appt_staff_res)) return ['errcode' => 1, 'errmsg' => '该技师已停止服务，请重新选择其它技师'];
            }
        }

        $goods_spec_res = GoodsSpec::get_data_by_id($goods_spec_id, $this->merchant_id);
        if (empty($goods_spec_res)) return ['errcode' => 1, 'errmsg' => '获取商品规格信息失败'];
        $date_string = date('Y年m月d日', strtotime($date));//预约时间
        //判断是否可预约
        $param = [
            'date' => $date,
            'goods_spec_id' => $goods_spec_id,
            'merchant_id' => $this->merchant_id,
        ];
        $ApptService = new ApptService();
        $res = $ApptService->getIfAppt($param);//如可预约，返回预约时间段
        if ($res['errcode'] != 0) return $res;//不可预约
        $time = $res['data'];

        if (!empty($time)) {//时间格式08:42-09:10
            $date_string .= '（' . $date_type . '）' . $time;
        } else {
            $date_string .= '（' . $date_type . '）' . 'Error';
        }

        $order_param = [
            'merchant_id' => $this->merchant_id,  //商户id
            'member_id' => $this->member_id,  //会员id
            'order_type' => ORDER_APPOINT,  //订单类型---预约下单
            'source' => $source,//订单来源
            'source_id' => $source_id,//订单来源id
            'goods' => [  //订单商品
                0 => [
                    'goods_id' => $goods_id,  //商品id
                    'spec_id' => $goods_spec_id,  //商品规格id
                    'sum' => $quality,  //购买数量
                    'appoint_date' => $date,
                    'pay_price' => number_format($goods_spec_res->price * $quality, 2, '.', ''),  //购买价格(多个商品总价)
//                    'ump_type'=> 0,  //优惠类型（config/varconfig.php -> order_ump_ump_type）,没有为空
//                    'ump_id'    => 0,  //优惠活动id
                ],
            ],
        ];
        $store_res = Store::get_data_by_id($request->get('store_id'), $this->merchant_id);
        if (empty($store_res) || $store_res->is_delete != 1 || $store_res->enabled != 1) return ['errcode' => 1, 'errmsg' => '获取门店信息失败，请重新下单。'];
        DB::beginTransaction();
        try {
            $order_res = $buyService->createorder($order_param);
            if ($order_res['errcode'] > 0) return $order_res;
            $order_appt_data = [
                'merchant_id' => $this->merchant_id,
                'member_id' => $this->member_id,
                'order_id' => $order_res['data']['order_id'],
                'order_sn' => $order_res['data']['order_sn'],
                'hexiao_status' => 0,
                'goods_id' => $request->get('goods_id'),
                'goods_title' => $goods_res->title,
                'goods_img' => $goods_res->img,
                'quantity' => $quality,
                'store_id' => $request->get('store_id'),
                'store_name' => $store_res->name,
//                'customer' => $request->get('customer'),
//                'customer_mobile' => $request->get('customer_mobile'),
                'appt_date' => $date,//2017-09-12
                'appt_string' => $date_string,//2017年09月30日（周日）9:00-12:00
            ];
            if (isset($goods_appt_res) && $goods_appt_res->mode == 2) {
                $order_appt_data['appt_staff_id'] = $appt_staff_id;
                $order_appt_data['appt_staff_nickname'] = empty($appt_staff_res['nickname']) ? 'exception' : $appt_staff_res['nickname'];
            }
            OrderAppt::create($order_appt_data);
            DB::commit();
            $data = [
                'order_id' => $order_res['data']['order_id'],
                'order_sn' => $order_res['data']['order_sn'],
            ];
            $this->config_err[0]['data'] = $data;
            return $this->config_err[0];
        } catch (\Exception $e) {
            DB::rollBack();
            //记录异常
            $except = [
                'activity_id' => $goods_id,
                'data_type' => 'order_appt',
                'content' => '预约订单创建失败.line:' . $e->getLine() . ',msg:' . $e->getMessage(),
            ];
            CommonApi::errlog($except);
//            if (app()->isLocal()) $this->config_err['40059']['errmsg'] .= $e->getMessage();
            return $this->config_err['40059'];
        }
    }

    /**
     * 获取周几
     * @param $datetime
     * @return string
     * @author: tangkang@dodoca.com
     */
    private function getWeekDay($datetime)
    {
        $weekarray = array('日', '一', '二', '三', '四', '五', '六');
        $date_type = '周' . $weekarray[date("w", strtotime($datetime))];
        return $date_type;
    }

    /**
     * 获取指定日期可预约时间段列表
     *
     * @param Request $request
     * @return array
     * @author: tangkang@dodoca.com
     */
    public function getTimePropValueLists(ApptTimeListRequest $request)
    {
        $date = $request->get('date', null);
        $goods_id = $request->get('goods_id', null);
        $store_id = $request->get('store_id', null);
        $staff_id = $request->get('staff_id', null);
        $goods_appt_res = GoodsAppt::get_data_by_goods_id($goods_id, $this->merchant_id);
        if (empty($goods_appt_res)) return ['errcode' => 1, 'errmsg' => '获取预约商品信息失败'];
        $param = [
            'date' => $date,
            'goods_id' => $goods_id,
            'merchant_id' => $this->merchant_id,
            'store_id' => $store_id,
        ];
        if ($goods_appt_res->mode == 2) {//按人员预约
            if (empty($staff_id)) return ['errcode' => 1, 'errmsg' => '请选择预约人员'];
            $param['staff_id'] = $staff_id;
        }
        $ApptService = new ApptService();
        $res = $ApptService->getApptTime($param);
        if ($res['errcode'] != 0) {
            $errData = [
                'activity_id' => $goods_id,
                'data_type' => 'goods',
                'content' => '商品详情：获取预约商品预约时段列表失败->' . json_encode($res, JSON_UNESCAPED_UNICODE),
            ];
            CommonApi::errlog($errData);
            return ['errcode' => 1, 'errmsg' => 'Failed', 'data' => $res['data']];//某天所有预约时段,格式['value_id'=>'125.50']
        } else {
            return ['errcode' => 0, 'errmsg' => 'OK', 'data' => $res['data']];//某天所有预约时段,格式['value_id'=>'125.50']
        }
    }

    /**
     * 预约日期列表
     * @author: tangkang@dodoca.com
     */
    public function getDateLists(ApptDateListRequest $request)
    {
        $goods_id = $request->get('goods_id', null);
        $range_param = [
            'goods_id' => $goods_id,
            'merchant_id' => $this->merchant_id,
        ];
        $goods = Goods::get_data_by_id($goods_id, $this->merchant_id);
        if ($goods->is_sku !== 2) return ['errcode' => 1, 'errmsg' => '非预约商品无预约时段'];
        $ApptService = new ApptService();
        //可预约日期范围
        $range_res = $ApptService->getApptDateRange($range_param);
        if ($range_res['errcode'] == 0) {
            $data['lists'] = $range_res['data'];
            return ['errcode' => 0, 'errmsg' => 'OK', 'data' => $data];
        } else {
            $errData = [
                'activity_id' => $goods_id,
                'data_type' => 'goods',
                'content' => '商品详情：获取预约商品可预约日期范围列表失败->' . json_encode($range_res, JSON_UNESCAPED_UNICODE),
            ];
            CommonApi::errlog($errData);
            return ['errcode' => 1, 'errmsg' => 'Failed'];
        }
    }
}

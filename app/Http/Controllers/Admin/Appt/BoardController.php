<?php

namespace App\Http\Controllers\Admin\Appt;

use App\Models\ApptStaff;
use App\Models\Goods;
use App\Models\GoodsAppt;
use App\Models\GoodsProp;
use App\Models\GoodsSpec;
use App\Models\Prop;
use App\Models\PropValue;
use App\Services\ApptService;
use App\Services\GoodsService;
use App\Utils\Calendar;
use App\Utils\CommonApi;
//use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BoardRequest;
use Illuminate\Support\Facades\Auth;

class BoardController extends Controller
{
    public function __construct()
    {
        $this->merchant_id = Auth::user()->merchant_id;
    }

    /**
     *
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(BoardRequest $request)
    {
        $type = $request->get('type');//服务名称
        if ($type == 2) {
            $board_staff_data = self::BoardStaff($request);
            return $board_staff_data;
        }
        //服务--预约看板
        $store_id = $request->get('store_id');
        $title = $request->get('title', null);//服务名称
        $date = $request->get('date', date('Y-m-d'));
        $page = intval($request->get('page', 1)) - 1;//第几页
        $limit = intval($request->get('limit', 10));//每页显示10条

        $merchant_id = $this->merchant_id;
        $date_res = Calendar::get_date_type($date);//获取指定日期的类型
        if ($date_res['errcode'] != 0) {
            $prop_value = '';
        } else {
            $prop_value = $date_res['data'];
        }
        $prop_value_ids = PropValue::where(['title' => $prop_value, 'prop_type' => 1, 'is_delete' => 1])->whereIn('merchant_id', [$this->merchant_id, 0])->lists('id');

        if (empty($prop_value_ids)) {
            $errData = [
                'activity_id' => $store_id,
                'data_type' => 'Appt-Board',
                'content' => '查询日期规格值ID失败。title:' . $prop_value . '，返回结果：' . json_encode($prop_value_ids, JSON_UNESCAPED_UNICODE),
            ];
            CommonApi::errlog($errData);
            return ['errcode' => 1, 'errmsg' => '查询日期规格值ID失败'];
        }
        $prop_value_ids = $prop_value_ids->toArray();
        $prop_date_ids = Prop::getPropIdByName('date');
        if (empty($prop_date_ids)) return ['errcode' => 1, 'errmsg' => '查询商品规格预约日期名ID失败'];
        $prop_store_ids = Prop::getPropIdByName('store');
        if (empty($prop_store_ids)) return ['errcode' => 1, 'errmsg' => '查询商品规格预约门店名ID失败'];
        $data['count'] = 0;

        $query = Goods::where('goods.merchant_id', $this->merchant_id)
            ->where('goods.is_delete', 1)
            ->where('goods.is_sku', 2);
        if (!empty($title)) {
            $query->where('goods.title', 'like', "%$title%");
        }

        $query->leftJoin('goods_prop', 'goods_prop.goods_id', '=', 'goods.id')
            ->where('goods_prop.merchant_id', $merchant_id)
            ->whereIn('goods_prop.prop_id', $prop_store_ids)
            ->where('goods_prop.prop_vid', $store_id)
            ->where('goods_prop.is_delete', 1);

        $query->leftJoin('goods_prop as goods_prop02', 'goods_prop02.goods_id', '=', 'goods.id')
            ->where('goods_prop02.merchant_id', $merchant_id)
            ->whereIn('goods_prop02.prop_id', $prop_date_ids)
            ->where('goods_prop02.prop_value', $prop_value)
            ->where('goods_prop02.is_delete', 1);
        $data['count'] = $query->distinct()->count('goods.id');
        $lists_res = $query->groupBy('goods.id')->skip($page * $limit)
            ->limit($limit)
            ->get(['goods.id', 'goods.title', 'goods.goods_cat_id']);//默认10条数据

        if (empty($lists_res)) return ['errcode' => 0, 'errmsg' => '暂无数据', 'data' => []];
        $GoodsService = new GoodsService();
        $param_stock = [
            'merchant_id' => $this->merchant_id,
            'date' => $date,
        ];
        $lists_res = $lists_res->toArray();
        $lists_data = [];
        foreach ($lists_res as $k => $list) {//$list [staff_id=>技师id,title=>技师名字,id=>商品id]
            $lists_data[$k]['id'] = $list['id'];//服务id
            $lists_data[$k]['title'] = $list['title'];
            $spec_res = GoodsSpec::get_data_by_goods_id($list['id'], $merchant_id)->toArray();
            $param_stock['goods_id'] = $list['id'];
//            $lists_data[$k]['spec'] = [];
            $spec_tem = [];
            foreach ($spec_res as $key => $value) {//服务各规格
                $prop_arr_res = explode(';', $value['props']);
                $prop_arr_store_id = explode(':', $prop_arr_res[0]);
                if ($prop_arr_store_id[1] != $store_id) {
                    continue;//非本店铺不要
                }
                $date_prop_value_tem = end($prop_arr_res);//周六至周日、周一至周五
                $date_prop_value = explode(':', $date_prop_value_tem);
                if (!in_array($date_prop_value[1], $prop_value_ids)) {
                    continue;//非今天的不要
                }
                $param_stock['goods_spec_id'] = $value['id'];
                $res = $GoodsService->getGoodsStock($param_stock);//剩余库存
                if ($res['errcode'] != 0) $res['data'] = 'Error';//获取库存失败

                $ApptService = new ApptService();
                $time_value_res = $ApptService->getApptTimeValue($value['id'], $this->merchant_id);

                if (empty($time_value_res) || $time_value_res['errcode'] != 0) continue;//异常数据不展示

                $time = $time_value_res['data']['title'];//已删除的也显示

                if (array_key_exists($time, $lists_data[$k])) {//按服务//合并同时段服务
                    $key_tem = $lists_data[$k][$time];
                    $spec_tem[$key_tem]['appt_stock'] = $spec_tem[$key_tem]['appt_stock'] + intval($res['data']);//剩余库存
                    $spec_tem[$key_tem]['appt_csale'] = $spec_tem[$key_tem]['appt_csale'] + intval($value['stock']) - intval($res['data']);//已预约量
                    $spec_tem[$key_tem]['stock'] = $spec_tem[$key_tem]['stock'] + intval($value['stock']);//总库存
                } else {
                    $lists_data[$k][$time] = $key;

                    $spec_tem[$key]['spec_id'] = $value['id'];//规格id
                    $spec_tem[$key]['time'] = $time;//时段
                    $spec_tem[$key]['appt_stock'] = $res['data'];//剩余库存
                    $spec_tem[$key]['appt_csale'] = intval($value['stock']) - intval($res['data']);//已预约量
                    $spec_tem[$key]['stock'] = $value['stock'];
                }
            }
            //json
            $lists_data[$k]['spec'] = array_values($spec_tem);
        }
        $data['lists'] = $lists_data;
        return ['errcode' => 0, 'errmsg' => '操作成功', 'data' => $data];
    }

    /**
     * 技师-预约看板
     * @param $request
     * @return array
     * @author: tangkang@dodoca.com
     */
    public function BoardStaff($request)
    {
        $store_id = $request->get('store_id');
        $title = $request->get('title', null);//服务名称
        $date = $request->get('date', date('Y-m-d'));
        $page = intval($request->get('page', 1)) - 1;//第几页
        $limit = intval($request->get('limit', 10));//每页显示10条

        $merchant_id = $this->merchant_id;
        $date_res = Calendar::get_date_type($date);//获取指定日期的类型
        if ($date_res['errcode'] != 0) {
            $prop_value = '';
        } else {
            $prop_value = $date_res['data'];
        }
        $prop_value_ids = PropValue::where(['title' => $prop_value, 'prop_type' => 1, 'is_delete' => 1])->whereIn('merchant_id', [$this->merchant_id, 0])->lists('id');

        if (empty($prop_value_ids)) {
            $errData = [
                'activity_id' => $store_id,
                'data_type' => 'Appt-Board',
                'content' => '查询日期规格值ID失败。title:' . $prop_value . '，返回结果：' . json_encode($prop_value_ids, JSON_UNESCAPED_UNICODE),
            ];
            CommonApi::errlog($errData);
            return ['errcode' => 1, 'errmsg' => '查询日期规格值ID失败'];
        }
        $prop_value_ids = $prop_value_ids->toArray();//今天的value-id
        $prop_date_ids = Prop::getPropIdByName('date');
        $prop_store_ids = Prop::getPropIdByName('store');
        $prop_staff_ids = Prop::getPropIdByName('staff');

        if (empty($prop_date_ids)) return ['errcode' => 1, 'errmsg' => '查询商品规格预约日期名ID失败'];
        $data['count'] = 0;

        $GoodsService = new GoodsService();
        $param_stock = [
            'merchant_id' => $this->merchant_id,
            'date' => $date,
        ];

        $lists_data = [];
        $query = GoodsProp::where('goods_prop.merchant_id', $merchant_id)
            ->whereIn('goods_prop.prop_id', $prop_date_ids)
            ->where('goods_prop.prop_value', $prop_value)//对日期
            ->where('goods_prop.is_delete', 1)
            ->leftJoin('goods_prop as goods_prop02', 'goods_prop.goods_id', '=', 'goods_prop02.goods_id')
            ->whereIn('goods_prop02.prop_id', $prop_store_ids)
            ->where('goods_prop02.prop_vid', $store_id)//对门店规格
            ->where('goods_prop02.is_delete', 1)
            ->leftJoin('goods_prop as goods_prop03', 'goods_prop.goods_id', '=', 'goods_prop03.goods_id')
            ->whereIn('goods_prop03.prop_id', $prop_staff_ids)//对应人员
            ->where('goods_prop03.is_delete', 1);

        //服务人员规格名称id
        if (!empty($title)) {
            $query->where('goods_prop03.prop_value', 'like', "%$title%");//对服务人员名字
        }
        $data['count'] = $query->distinct()->count('goods_prop03.prop_vid');
        $data['lists'] = [];
        if (empty($data['count'])) return ['errcode' => 0, 'errmsg' => '暂无数据', 'data' => $data];
        $goods_props_res = $query->skip($page * $limit)
            ->limit($limit)->get(['goods_prop03.goods_id', 'goods_prop03.prop_vid', 'goods_prop03.prop_value']);
        //返回以技师id为键的多维数组

        $goods_arr = [];
        foreach ($goods_props_res as $key => $goods_staff) {
            $staff_id = $goods_staff['prop_vid'];
            $goods_arr[$staff_id]['id'] = $staff_id;//服务人员id
            $goods_arr[$staff_id]['title'] = $goods_staff['prop_value'];//服务人员名称
            $goods_arr[$staff_id]['goods_id'][] = $goods_staff['goods_id'];//服务商品id
        }
        $goods_arr = array_values($goods_arr);
        $ApptService = new ApptService();

        foreach ($goods_arr as $k => $list) {
            $lists_data[$k]['id'] = $list['id'];//服务人员id
            $lists_data[$k]['title'] = $list['title'];//服务人员名称
            $goods_id_tem = [];
            foreach ($list['goods_id'] as $goods_list_id) {
                if (!empty($goods_id_tem) && in_array($goods_list_id, $goods_id_tem)) continue;//同商品只走一次，保存一次同规格
                $goods_id_tem[] = $goods_list_id;

                $goods_res = GoodsAppt::get_data_by_goods_id($goods_list_id, $merchant_id);
                if ($goods_res->mode != 2) continue;//非按人员预约不显示

                $tem = GoodsSpec::get_data_by_goods_id($goods_list_id, $merchant_id)->toArray();

                if (isset($spec_res)) {
                    $spec_res = array_merge($tem, $spec_res);
                } else {
                    $spec_res = $tem;
                }
            }
            if (empty($spec_res)) return ['errcode' => 1, 'errmsg' => '获取规格信息失败'];

//            $lists_data[$k]['spec'] = [];
            $spec_tem = [];
            foreach ($spec_res as $key => $value) {//服务各规格

                $prop_arr_res = explode(';', $value['props']);

                $date_prop_value_tem = end($prop_arr_res);//周六至周日、周一至周五
                $date_prop_value = explode(':', $date_prop_value_tem);

                if (!in_array($date_prop_value[1], $prop_value_ids)) {
                    continue;//非今天的不要
                }

                $staff_arr = explode(':', $prop_arr_res[1]);
                if ($staff_arr[1] != $list['id']) continue;

                $param_stock['goods_id'] = $value['goods_id'];
                $param_stock['goods_spec_id'] = $value['id'];
                $res = $GoodsService->getGoodsStock($param_stock);//剩余库存
                if ($res['errcode'] != 0) $res['data'] = 'Error';//获取库存失败

                $time_value_res = $ApptService->getApptTimeValue($value['id'], $this->merchant_id);

                if (empty($time_value_res) || $time_value_res['errcode'] != 0) continue;//异常数据不展示

                $time = $time_value_res['data']['title'];//已删除的也显示

                if (array_key_exists($time, $lists_data[$k])) {//按服务//合并同时段服务
                    $key_tem = $lists_data[$k][$time];
                    $spec_tem[$key_tem]['appt_stock'] = $spec_tem[$key_tem]['appt_stock'] + intval($res['data']);//剩余库存
                    $spec_tem[$key_tem]['appt_csale'] = $spec_tem[$key_tem]['appt_csale'] + intval($value['stock']) - intval($res['data']);//已预约量
                    $spec_tem[$key_tem]['stock'] = $spec_tem[$key_tem]['stock'] + intval($value['stock']);//总库存
                } else {
                    $lists_data[$k][$time] = $key;

                    $spec_tem[$key]['spec_id'] = $value['id'];//规格id
                    $spec_tem[$key]['time'] = $time;//时段
                    $spec_tem[$key]['appt_stock'] = $res['data'];//剩余库存
                    $spec_tem[$key]['appt_csale'] = intval($value['stock']) - intval($res['data']);//已预约量
                    $spec_tem[$key]['stock'] = $value['stock'];
                }
            }
            $lists_data[$k]['spec'] = array_values($spec_tem);
        }
        $data['lists'] = $lists_data;
        return ['errcode' => 0, 'errmsg' => '操作成功', 'data' => $data];
    }

    /**
     * Display the specified resource.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

}

<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017/9/6
 * Time: 10:01
 * Author: DuMing
 */
namespace App\Http\Controllers\Admin\Goods;

use App\Http\Controllers\Controller;
use App\Models\ApptStaff;
use App\Models\Goods;
use App\Models\GoodsCat;
use App\Models\GoodsSpec;
use App\Models\Prop;
use App\Models\PropValue;
use App\Models\Store;
use App\Services\UserPrivService;
use App\Services\GoodsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Auth;

class GoodsPropController extends Controller
{
    protected $param;
    protected $prop_model;
    protected $propval_model;
    protected $spec_model;
    protected $fields;
//    protected $appointment_ids = array();//所有预约服务id
    protected $goods_cat;
    protected $user_id;
    const APPOINTMENT_CAT = 4189;//预约服务分类id

    public function __construct(Request $request, Prop $prop_model, PropValue $propval_model, GoodsSpec $spec_model, GoodsCat $goodsCat)
    {
        $this->param = $request->all();
        $this->prop_model = $prop_model;
        $this->propval_model = $propval_model;
        $this->spec_model = $spec_model;
        $this->fields = array(
            'id', 'merchant_id', 'goods_id', 'price', 'stock', 'spec_sn', 'barcode', 'props', 'props_str', 'img', 'csale'
        );
        $this->goods_cat = $goodsCat;
        $user = Auth::user();
        $this->user_id = $user->merchant_id;
    }

    /**
     * @Author  DuMing
     * 商品规格&属性列表
     */
    public function index()
    {
        $res_data = [];
        $goods_cat_id = isset($this->param['goods_cat_id']) ? intval($this->param['goods_cat_id']) : 0;
        if (!$goods_cat_id) {
            $data['errcode'] = 99001;
            $data['errmsg'] = '商品分类id缺失';
            return Response::json($data, 200);
        }
        $res_data['data'] = Prop::getDataByCat($goods_cat_id, $this->user_id);
        $props = array();
        if (!empty($res_data['data'])) {
            $props = $res_data['data']->toArray();
            $props_ = [];
            foreach ($props as $propkey => $prop) {
                if ($prop['prop_value_type'] == 0) {//非预约规格名
                    $props_[] = $prop;
                }
            }
            //prop_value表
            if (!empty($props_)) {
                $prop_ids = array_pluck($props_, 'id');
                //获取对应规格属性下的规格属性值
                $propval_wheres = array(
                    array('column' => 'merchant_id', 'value' => array($this->user_id, 0), 'operator' => 'in'),
                    array('column' => 'prop_id', 'value' => $prop_ids, 'operator' => 'in'),
                    array('column' => 'is_delete', 'value' => 1, 'operator' => '=')
                );
                $propvals = $this->propval_model->getDataByWhere($propval_wheres, array('id', 'prop_id', 'prop_type', 'title'))->toArray();
            }
            $prop_store_id=Prop::getPropIdByName('store',$goods_cat_id);
            $prop_staff_id=Prop::getPropIdByName('staff',$goods_cat_id);
           
            $store_list = Store::where(['merchant_id' => $this->user_id, 'is_delete' => 1, 'enabled' => 1])
                ->select(\DB::raw('id,name as title,"' . $prop_store_id . '" as prop_id'))
                ->get();
            if (!empty($store_list)) {
                foreach ($store_list as &$store) {
                    $store['staff_values'] = ApptStaff::where('store_id', $store->id)
                        ->where(['merchant_id' => $this->user_id, 'is_delete' => 1])
                        ->select(\DB::raw('id,nickname as title,"' . $prop_staff_id . '" as prop_id'))->get();
                }
            }
            $cat = $this->goods_cat->get_data_by_id($goods_cat_id);
            if (!empty($cat)) {
                $res_data['is_appointment'] = $cat['cat_type'] == 1 ? 1 : 0;
                $res_data['is_virtualgoods'] = $cat['cat_type'] == 2 ? 1 : 0;
            }
            foreach ($props as $k => &$val) {
                if ($val['prop_type'] == 1) {//1:商品规格
                    //门店和服务人员-----等调接口
                    if ($val['prop_value_type'] == 1) {//门店规格
                        $val['key'] = 'store';
                    } elseif ($val['prop_value_type'] == 2) {//人员规格
                        $val['key'] = 'staff';
                    } elseif ($val['prop_value_type'] == 0) {
                        if ($val['title'] == '预约日期') {//日期规格
                            $val['key'] = 'date';
                        } else {//时段规格
                            $val['key'] = 'time';
                        }
                    }
                    if ($val['prop_value_type'] == 1 || $val['prop_value_type'] == 2) {
                        $val['store_values'] = $store_list;
                        continue;
                    }
                }
                if (!empty($propvals)) {
                    foreach ($propvals as $key => $value) {
                        if ($val['id'] == $value['prop_id']) {
                            if ($val['prop_type'] == $value['prop_type'] && $val['prop_type'] == 0) {
                                $val['input_values'][] = $value;
                            } else {
                                $val['prop_values'][] = $value;
                            }
                        }
                    }
                }
                if ($val['prop_type'] == 0) {
                    $val['prop_values'] = [];
                } elseif ($val['prop_type'] == 1) {
                    $val['input_values'] = [];
                }
                if (!isset($val['prop_values'])) {
                    $val['prop_values'] = [];
                }

                if (!isset($val['input_values'])) {
                    $val['input_values'] = [];
                }

            }
        }

        $appointservice_priv = UserPrivService::getUserPrivs();
        $appint_priv = true;
        if (!in_array('trade_apptmanage', $appointservice_priv)) {
            $appint_priv = false;
        }
        $res_data['data'] = $props;
        $res_data['errcode'] = 0;
        $res_data['errmsg'] = '';
        $res_data['appoint_priv'] = $appint_priv;
        return Response::json($res_data, 200);

    }

    /***
     * @Author  DuMing
     * 商品规格&属性添加
     */
    public function add()
    {
        if (!isset($this->param['prop_type'])) {
            $data['errcode'] = 99001;
            $data['errmsg'] = '参数缺失,区分类型值不能为空';
            return Response::json($data, 200);
        }
        //区分属性，规格类型
        $prop_type = intval(trim($this->param['prop_type']));
        if (!in_array($prop_type, array(1, 2))) {
            $data['errcode'] = 99001;
            $data['errmsg'] = '参数错误';
            return Response::json($data, 200);
        }
        //商品分类
        $goods_cat_id = isset($this->param['goods_cat_id']) ? intval(trim($this->param['goods_cat_id'])) : 0;
        if (!$goods_cat_id) {
            $data['errcode'] = 99001;
            $data['errmsg'] = '商品分类参数缺失';
            return Response::json($data, 200);
        }
        //属性，规格名称
        $title = isset($this->param['title']) ? $this->param['title'] : '';
        if (empty($title)) {
            $data['errcode'] = 99001;
            $data['errmsg'] = $prop_type == 1 ? '商品属性名称参数缺失' : '商品规格名称参数缺失';
            return Response::json($data, 200);
        }
        //属性，规格名称
        $val_title = isset($this->param['val_title']) ? $this->param['val_title'] : '';
        if (empty($val_title)) {
            $data['errcode'] = 99001;
            $data['errmsg'] = $prop_type == 1 ? '商品属性值名称参数缺失' : '商品规格值名称参数缺失';
            return Response::json($data, 200);
        }

        $prop_data['merchant_id'] = $this->user_id;
        $prop_data['goods_cat_id'] = $goods_cat_id;
        $prop_data['prop_type'] = $prop_type == 1 ? 0 : 1;
        $prop_data['title'] = $title;
        $prop_data['created_time'] = date('Y-m-d H:i:s');
        $prop_data['is_delete'] = 1;
        //规格
        $prop_rs = $this->prop_model->insert_data($prop_data);
        if ($prop_rs) {
            if (is_array($val_title)) {
                $return_arr = array();
                foreach ($val_title as $key => $val) {
                    $propval_data['merchant_id'] = $this->user_id;
                    $propval_data['prop_id'] = $prop_rs;
                    $propval_data['prop_type'] = $prop_type == 1 ? 0 : 1;
                    $propval_data['title'] = $val;
                    $propval_data['created_time'] = date('Y-m-d H:i:s');
                    $propval_data['is_delete'] = 1;
                    //规格值
                    $propval_rs = $this->propval_model->insert_data($propval_data);
                    if ($propval_rs) {
                        $return_arr[$key]['merchant_id'] = $this->user_id;
                        $return_arr[$key]['prop_id'] = $prop_rs;
                        $return_arr[$key]['title'] = $val;
                        $return_arr[$key]['id'] = $propval_rs;
                    } else {
                        $data['errcode'] = 1;
                        $data['errmsg'] = $prop_type == 1 ? '属性值添加失败' : '规格值添加失败';
                        return Response::json($data, 200);
                    }
                }
                $data['data']['goods_cat_id'] = $goods_cat_id;
                $data['data']['id'] = $prop_rs;//规格id
                $data['data']['type'] = $prop_type;
                $data['data']['merchant_id'] = $this->user_id;
                $data['data']['title'] = $title;
                if ($prop_type == 1) {
                    $data['data']['input_values'] = $return_arr;
                } else {
                    $data['data']['prop_values'] = $return_arr;
                }
                $data['errcode'] = 0;
                $data['errmsg'] = '添加成功';
            } else {
                $propval_data['merchant_id'] = $this->user_id;
                $propval_data['prop_id'] = $prop_rs;
                $propval_data['prop_type'] = $prop_type == 1 ? 0 : 1;
                $propval_data['title'] = $val_title;
                $propval_data['created_time'] = date('Y-m-d H:i:s');
                $propval_data['is_delete'] = 1;
                //规格值
                $propval_rs = $this->propval_model->insert_data($propval_data);
                if ($propval_rs) {
                    $propval = array(
                        'title' => $val_title,
                        'merchant_id' => $this->user_id,
                        'prop_id' => $prop_rs,
                        'id' => $propval_rs
                    );
                    if ($prop_type == 1) {
                        $data['data']['input_values'] = $propval;
                    } else {
                        $data['data']['prop_values'] = $propval;
                    }
                    $data['data']['goods_cat_id'] = $goods_cat_id;
                    $data['data']['id'] = $prop_rs;//规格id
                    $data['data']['type'] = $prop_type;
                    $data['data']['merchant_id'] = $this->user_id;
                    $data['data']['title'] = $title;
                    $data['errcode'] = 0;
                    $data['errmsg'] = '添加成功';
                } else {
                    $data['errcode'] = 1;
                    $data['errmsg'] = $prop_type == 1 ? '属性值添加失败' : '规格值添加失败';
                }
            }
        } else {
            $data['errcode'] = 1;
            $data['errmsg'] = $prop_type == 1 ? '属性添加失败' : '规格添加失败';
        }
        return Response::json($data, 200);
    }

    /***
     * @Author  DuMing
     * 商品规格&属性删除
     */
    public function delete()
    {
        if (!isset($this->param['prop_type'])) {
            $data['errcode'] = 99001;
            $data['errmsg'] = '参数缺失,区分类型值不能为空';
            return Response::json($data, 200);
        }else if( $this->param['prop_type']==2 ) {
            $data['errcode'] = 99001;
            $data['errmsg'] = '目前不支持删除商品规格';
            return Response::json($data, 200);
        }
        //区分属性，规格类型
        $prop_type = intval(trim($this->param['prop_type']));
        if (!in_array($prop_type, array(1, 2))) {
            $data['errcode'] = 99001;
            $data['errmsg'] = '参数错误';
            return Response::json($data, 200);
        }
        $id = isset($this->param['id']) ? intval(trim($this->param['id'])) : 0;
        if (!$id) {
            $data['errcode'] = 99001;
            $data['errmsg'] = '参数缺失,id不能为空';
            return Response::json($data, 200);
        }
        $prop_type = $prop_type == 1 ? 0 : 1;
        $wheres_prop = array(
            array('column' => 'id', 'value' => $id, 'operator' => '='),
            array('column' => 'merchant_id', 'value' => $this->user_id, 'operator' => '='),
            array('column' => 'prop_type', 'value' => $prop_type, 'operator' => '=')
        );
        $prop_update_data['is_delete'] = -1;
        $prop_update_data['updated_time'] = date('Y-m-d H:i:s', time());
        $prop_res = $this->prop_model->updateByWhere($wheres_prop, $prop_update_data);
        if ($prop_res) {
            $wheres_propval = array(
                array('column' => 'prop_id', 'value' => $id, 'operator' => '='),
                array('column' => 'merchant_id', 'value' => $this->user_id, 'operator' => '='),
                array('column' => 'prop_type', 'value' => $prop_type, 'operator' => '=')
            );
            $propval_update_data['is_delete'] = -1;
            $propval_update_data['updated_time'] = date('Y-m-d H:i:s', time());
            $propval_res = $this->propval_model->updateByWhere($wheres_propval, $propval_update_data);
            if ($propval_res) {
                $data['errcode'] = 0;
                $data['errmsg'] = '删除成功';
            } else {
                $data['errcode'] = 1;
                $data['errmsg'] = $prop_type == 1 ? '规格值删除失败' : '属性值删除失败';
            }
        } else {
            $data['errcode'] = 1;
            $data['errmsg'] = $prop_type == 1 ? '规格删除失败' : '属性删除失败';
        }
        return Response::json($data);
    }

    /***
     * @Author  DuMing
     * 商品规格&属性编辑
     */
    public function edit()
    {
        $params = $this->param;
        if (!isset($this->param['prop_type'])) {
            $data['errcode'] = 99001;
            $data['errmsg'] = '参数缺失,区分类型值不能为空';
            return Response::json($data, 200);
        }
        //区分属性，规格类型
        $prop_type = intval(trim($this->param['prop_type']));
        if (!in_array($prop_type, array(1, 2))) {
            $data['errcode'] = 99001;
            $data['errmsg'] = '参数错误';
            return Response::json($data, 200);
        }

        $id = isset($this->param['id']) ? intval(trim($this->param['id'])) : 0;
        if ($id <= 0) {
            $data['errcode'] = 99001;
            $data['errmsg'] = '参数缺失,id不能为空';
            return Response::json($data, 200);
        }

        //属性，规格名称
        $title = isset($this->param['title']) ? trim($this->param['title']) : '';
        if (!$title) {
            $data['errcode'] = 99001;
            $data['errmsg'] = $prop_type == 1 ? '商品属性名称参数缺失' : '商品规格名称参数缺失';
            return Response::json($data, 200);
        }
        $prop_type = $prop_type == 1 ? 0 : 1;
        $wheres_prop = array(
            array('column' => 'id', 'value' => $id, 'operator' => '='),
            array('column' => 'prop_type', 'value' => $prop_type, 'operator' => '='),
            array('column' => 'merchant_id', 'value' => $this->user_id, 'operator' => '=')
        );

        $update_data['title'] = $title;
        $update_data['updated_time'] = date('Y-m-d H:i:s', time());
        $res = $this->prop_model->updateByWhere($wheres_prop, $update_data);
        if ($res === false) {
            $data['data'] = [];
            $data['errcode'] = 1;
            $data['errmsg'] = $prop_type == 0 ? '商品属性名称更新失败' : '商品规格名称更新失败';
        } else {
            if (empty($params['prop_values']) && $id <= 0) {
                $data['data'] = [];
                $data['errcode'] = 1;
                $data['errmsg'] = '参数缺失';
            } else {
                $propvals = [];
                $wheres_propval = array(
                    array('column' => 'prop_id', 'value' => $id, 'operator' => '='),
                    array('column' => 'prop_type', 'value' => $prop_type, 'operator' => '='),
                    array('column' => 'merchant_id', 'value' => $this->user_id, 'operator' => '='),
                    array('column' => 'is_delete', 'value' => 1, 'operator' => '=')
                );
                /*
                 * 取消删除功能
                $db_propvals = $this->propval_model->getDataByWhere($wheres_propval, array('id', 'title'))->toArray();
                if (!empty($db_propvals)) {
                    $db_propval_ids = array_column($db_propvals, 'id');
                    $propval_ids = array_column($params['prop_values'], 'id');
                    $diff = array_diff($db_propval_ids, $propval_ids);
                }
                */
                foreach ($params['prop_values'] as $key => $item) {
                    if (isset($item['id']) && $item['id'] > 0) {
                        $this->propval_model->updateByWhere(
                                                                array(
                                                                    array('column' => 'id', 'value' => $item['id'], 'operator' => '='),
                                                                    array('column' => 'prop_type', 'value' => $prop_type, 'operator' => '='),
                                                                    array('column' => 'merchant_id', 'value' => $this->user_id, 'operator' => '=')
                                                                ), 
                                                                array(
                                                                    'title' => $item['title'],
                                                                    'updated_time' => date('Y-m-d H:i:s', time())
                                                                )
                                                            );
                        $propvals[$key]['id'] = $item['id'];
                    } else {
                        $propval_id = $this->propval_model->insert_data(
                                                                            array(
                                                                                'merchant_id' => $this->user_id,
                                                                                'prop_id' => $id,
                                                                                'prop_type' => $prop_type,
                                                                                'title' => $item['title'],
                                                                                'created_time' => date('Y-m-d H:i:s'),
                                                                                'is_delete' => 1
                                                                            )
                                                                        );
                        $propvals[$key]['id'] = $propval_id;
                    }
                    $propvals[$key]['merchant_id'] = $this->user_id;
                    $propvals[$key]['title'] = $item['title'];
                    $propvals[$key]['prop_id'] = $id;
                }
                /*
                 * 取消删除功能
                if (isset($diff) && !empty($diff)) {
                    $this->propval_model->updateByWhere(
                                                            array(
                                                                array('column' => 'id', 'value' => $diff, 'operator' => 'in'),
                                                                array('column' => 'prop_type', 'value' => $prop_type, 'operator' => '='),
                                                                array('column' => 'merchant_id', 'value' => $this->user_id, 'operator' => '=')
                                                            ), 
                                                            array('is_delete' => -1, 'updated_time' => date('Y-m-d H:i:s'))
                                                        );
                }
                */
                $data['data'] = $propvals;
                $data['errcode'] = 0;
                $data['errmsg'] = '更新成功';
            }
        }
        return Response::json($data);
    }

    /***
     * 获取商品多规格数据
     */
    public function getMultiSku()
    {

        $id = isset($this->param['id']) ? intval($this->param['id']) : 0;
        if ($id <= 0) {
            $data['errcode'] = 99001;
            $data['errmsg'] = '参数缺失,id不能为空';
            return Response::json($data, 200);
        }
        
        //单规格商品
        $rs_goods = Goods::where(['id'=>$id, 'merchant_id'=>Auth::user()->merchant_id])->first();
        ////////////////从缓存中读库存 start///////////////////////
        $service_goodsservice = new GoodsService();
        //dd($cache_goods);
        if( $rs_goods['is_sku']==0 ){
            $param['merchant_id']   = Auth::user()->merchant_id;
            $param['goods_id']      = $rs_goods['id'];
            $cache_goods = $service_goodsservice->getGoodsStock($param);
            //dd($cache_goods);
            if(isset($cache_goods['data'])){
                $res_data['data'][0]['id'] = 0;
                $res_data['data'][0]['is_sku'] = $rs_goods['is_sku'];
                $res_data['data'][0]['goods_id'] = $rs_goods['id'];
                $res_data['data'][0]['price'] = $rs_goods['price'];
                $res_data['data'][0]['stock'] = $cache_goods['data'];
                $res_data['data'][0]['goods_sn'] = $rs_goods['spec_sn'];
                $res_data['data'][0]['barcode'] = $rs_goods['barcode'];
                $res_data['data'][0]['img'] = $rs_goods['img'];
                
                $res_data['errcode'] = 0;
                $res_data['errmsg'] = '';
                return Response::json($res_data);
            }
        }
        ////////////////从缓存中读库存 end///////////////////////
        
        //多规格商品
        $wheres = array(
            array('column' => 'goods_id', 'value' => $id, 'operator' => '='),
            array('column' => 'merchant_id', 'value' => $this->user_id, 'operator' => '='),
            array('column' => 'is_delete', 'value' => 1, 'operator' => '='),
        );
        $data = $this->spec_model->getDataByWhere($wheres, $this->fields);
        $res_data = [];
        if ($data) {
            $data = $data->toArray();
            foreach ($data as $key => $item) {
                ////////////////从缓存中读库存 start///////////////////////
                $param['merchant_id']   = Auth::user()->merchant_id;
                $param['goods_id']      = $item['goods_id'];
                $param['goods_spec_id'] = $item['id'];
                $cache_goodsspec = $service_goodsservice->getGoodsStock($param);
                if(isset($cache_goodsspec['data'])){
                    $res_data['data'][$key]['stock'] = $cache_goodsspec['data'];
                }
                ////////////////从缓存中读库存 end///////////////////////
                
                $res_data['data'][$key]['is_sku'] = $rs_goods['is_sku'];
                $res_data['data'][$key]['id'] = $item['id'];
                $res_data['data'][$key]['goods_id'] = $item['goods_id'];
                $res_data['data'][$key]['price'] = $item['price'];
                $res_data['data'][$key]['spec_sn'] = $item['spec_sn'];
                $res_data['data'][$key]['barcode'] = $item['barcode'];
                $res_data['data'][$key]['img'] = $item['img'];
                if (strpos($item['props'], ';') !== false) {
                    $prop_id_arr = explode(';', $item['props']);
                    $res_data['data'][$key]['prop_id'] = implode(',', $prop_id_arr);
                } else {
                    $res_data['data'][$key]['prop_id'] = $item['props'];
                }
                if (strpos($item['props_str'], ';') !== false) {
                    $prop_str_arr = explode(';', $item['props_str']);
                    $res_data['data'][$key]['prop_str'] = implode(':', $prop_str_arr);
                } else {
                    $res_data['data'][$key]['prop_str'] = $item['props_str'];
                }
            }
        }
        $res_data['errcode'] = 0;
        $res_data['errmsg'] = '';
        return Response::json($res_data);
    }

    /***
     * @Author  DuMing
     * 编辑多库存数据
     */
    public function editMultiSku()
    {
        $data = isset($this->param['data']) ? $this->param['data'] : '';
        if (!is_array($data) || empty($data)) {
            $res_data['errcode'] = 99001;
            $res_data['errmsg'] = '商品规格值参数错误';
            return Response::json($res_data);
        }

//        $goods_id = isset($data['goods_id'])?$data['goods_id']:0;
//        if($goods_id<=0){
//            $res_data['errcode'] = 99001;
//            $res_data['errmsg'] = '商品id参数缺失';
//            return Response::json($res_data);
//        }

        $column = isset($this->param['column']) ? $this->param['column'] : '';
        if (!in_array($column, array('price', 'stock'))) {
            $res_data['errcode'] = 99001;
            $res_data['errmsg'] = '区分规格价格或库存类型的参数错误';
            return Response::json($res_data);
        }
//        $wheres = array(
//            0=>array('column' => 'merchant_id', 'value' => $this->user_id, 'operator' => '='),
//            1=>array('column' => 'is_delete', 'value' => 1, 'operator' => '='),
//        );
        foreach ($data as $key => $item) {
            if (!is_numeric($item['val'])) {
                $res_data['errcode'] = 99001;
                $res_data['errmsg'] = '参数类型错误,必须是数字类型';
                return Response::json($res_data);
            }
        }

        $error = [];
        foreach ($data as $key => $item) {
//            $wheres[2] = array('column' => 'id', 'value' => $item['id'], 'operator' => '=');
            $update_data[$column] = $item['val'];
            $update_data['updated_time'] = date('Y-m-d H:i:s', time());
            if ($this->param['column'] == 'price') {
                if (!isset($min_price)) {
                    $min_price = $item['val'];
                } else {
                    if ($min_price >= $item['val']) {
                        $min_price = $item['val'];
                    }
                }

                if (!isset($max_price)) {
                    $max_price = $item['val'];
                } else {
                    if ($max_price <= $item['val']) {
                        $max_price = $item['val'];
                    }
                }
            } else if ($this->param['column'] == 'stock') {
                if (!isset($total_stock)) {
                    $total_stock = $item['val'];
                } else {
                    $total_stock = $total_stock + $item['val'];
                }
            }
//            $res = $this->spec_model->updateByWhere($wheres,$update_data);
//            Goods::forgetStockCacheBySpecId($goods_id,$item['id']);
            $res = GoodsSpec::update_data($item['id'], $this->user_id, $update_data);
            if ($res === false) {
                $error[] = $item;
            }
        }
        $goods_id = 0;
        if (isset($data[0]['id']) && $data[0]['id'] > 0) {
            $spec_data = $this->spec_model->getDataByWhere(array(
                array('column' => 'id', 'value' => $data[0]['id'], 'operator' => '='),
                array('column' => 'merchant_id', 'value' => $this->user_id, 'operator' => '='),
                array('column' => 'is_delete', 'value' => 1, 'operator' => '='),
            ), array('id', 'goods_id'))->toArray();
            if (!empty($spec_data)) {
                $goods_id = $spec_data[0]['goods_id'];
                if ($this->param['column'] == 'price' && isset($min_price) && $goods_id > 0) {
                    $res_goods = Goods::update_data($goods_id, $this->user_id, array('price' => $min_price, 'max_price' => $max_price));
//                    $res_goods = Goods::updateByWhere(array(
//                        array('column' => 'id', 'value' => $goods_id, 'operator' => '='),
//                        array('column' => 'merchant_id', 'value' => $this->user_id, 'operator' => '='),
//                        array('column' => 'is_delete', 'value' => 1, 'operator' => '='),
//                    ),array('price'=>$min_price,'max_price'=>$max_price));
                } else if ($this->param['column'] == 'stock' && isset($total_stock) && $goods_id > 0) {
                    $res_goods = Goods::update_data($goods_id, $this->user_id, array('stock' => $total_stock));
//                    $res_goods = Goods::updateByWhere(array(
//                        array('column' => 'id', 'value' => $goods_id, 'operator' => '='),
//                        array('column' => 'merchant_id', 'value' => $this->user_id, 'operator' => '='),
//                        array('column' => 'is_delete', 'value' => 1, 'operator' => '='),
//                    ),array('stock'=>$total_stock));
                }
            }
        }

        if (!empty($error) || $res_goods === false) {
            $res_data['errcode'] = 1;
            $res_data['errmsg'] = '更新失败';
        } else {
            GoodsSpec::forgetCacheByGoods($goods_id, $this->user_id);
            $res_data['errcode'] = 0;
            $res_data['errmsg'] = '更新成功';
        }
        return Response::json($res_data);
    }

    /***
     * @Author  DuMing
     * 添加预约商品默认值
     */
    public function addDefaultProp()
    {
        $appointment = $this->goods_cat->getAllChildCat(self::APPOINTMENT_CAT);
        $appointment_ids = array_merge($appointment, [self::APPOINTMENT_CAT]);
        foreach ($appointment_ids as $key => $prop) {
            $where_props = array(
                array('column' => 'merchant_id', 'value' => 0, 'operator' => '='),
                array('column' => 'prop_type', 'value' => 1, 'operator' => '='),
                array('column' => 'goods_cat_id', 'value' => $prop, 'operator' => '=')
            );
            $props = $this->prop_model->getDataByWhere($where_props, array('id', 'title'))->toArray();
            $titles = [];
            if (!empty($props)) {
                $titles = array_column($props, 'title');
            }
            if (!in_array('预约时段', $titles)) {
                $item['merchant_id'] = 0;
                $item['goods_cat_id'] = $prop;
                $item['prop_type'] = 1;
                $item['title'] = '预约时段';
                $item['created_time'] = date('Y-m-d H:i:s');
                $item['is_delete'] = 1;
                $prop_id = $this->prop_model->insert_data($item);
            }

            if (!in_array('预约日期', $titles)) {
                $item['merchant_id'] = 0;
                $item['goods_cat_id'] = $prop;
                $item['prop_type'] = 1;
                $item['title'] = '预约日期';
                $item['created_time'] = date('Y-m-d H:i:s');
                $item['is_delete'] = 1;
                $item['is_edit'] = 0;
                $prop_id = $this->prop_model->insert_data($item);
                $where_propvals = array(
                    array('column' => 'merchant_id', 'value' => 0, 'operator' => '='),
                    array('column' => 'prop_id', 'value' => $prop_id, 'operator' => '='),
                    array('column' => 'prop_type', 'value' => 1, 'operator' => '=')
                );
                $prop_vals = $this->propval_model->getDataByWhere($where_propvals, array('id', 'title'))->toArray();
                $titles = [];
                if (!empty($prop_vals)) {
                    $titles = array_column($prop_vals, 'title');
                }
                if (!in_array('周一至周五', $titles)) {
                    $item_val['merchant_id'] = 0;
                    $item_val['prop_id'] = $prop_id;
                    $item_val['prop_type'] = 1;
                    $item_val['title'] = '周一至周五';
                    $item_val['created_time'] = date('Y-m-d H:i:s');
                    $item_val['is_delete'] = 1;
                    $propval_id = $this->propval_model->insert_data($item_val);
                }
                if (!in_array('周六至周日', $titles)) {
                    $item_val['merchant_id'] = 0;
                    $item_val['prop_id'] = $prop_id;
                    $item_val['prop_type'] = 1;
                    $item_val['title'] = '周六至周日';
                    $item_val['created_time'] = date('Y-m-d H:i:s');
                    $item_val['is_delete'] = 1;
                    $propval_id = $this->propval_model->insert_data($item_val);
                }
                if (!in_array('法定节假日', $titles)) {
                    $item_val['merchant_id'] = 0;
                    $item_val['prop_id'] = $prop_id;
                    $item_val['prop_type'] = 1;
                    $item_val['title'] = '法定节假日';
                    $item_val['created_time'] = date('Y-m-d H:i:s');
                    $item_val['is_delete'] = 1;
                    $propval_id = $this->propval_model->insert_data($item_val);
                }
            }
        }
    }
}
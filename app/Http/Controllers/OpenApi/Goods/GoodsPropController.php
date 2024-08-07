<?php

namespace App\Http\Controllers\OpenApi\Goods;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Config;
use App\Http\Controllers\Controller;
use App\Models\ApptStaff;
use App\Models\Goods;
use App\Models\Prop;
use App\Models\PropValue;
use App\Models\Store;
use App\Models\ServeLabel;
use App\Models\GoodsCat;
use App\Models\MerchantSetting;
use App\Models\GoodsSpec;
use App\Services\UserPrivService;
use App\Services\GoodsService;


class GoodsPropController extends Controller
{

    private $request;
    private $param;
    private $merchantId;
    private $fields;

    public function __construct(Request $request)
    {
        $auth              = $request->user();
        $this->request     = $request;
        $this->param       = $request->all();
        $this->merchantId  = $auth['merchant_id'];
    }
    //商品属性列表
    public function getProp(){
        $goods_cat_id = isset($this->param['goods_cat_id']) ? (int)$this->param['goods_cat_id'] : 0;
        if (!$goods_cat_id) {
            return Response::json(['errcode'=>99001 ,'errmsg'=>  '商品分类id缺失' ], 200);
        }
        $props = Prop::getDataByCat($goods_cat_id, $this->merchantId,['id','prop_type','prop_value_type','is_edit','title']);
        if(empty($props)){
            return Response::json(['errcode' => 0, 'errmsg'=> '' , 'data' => []  ], 200);
        }
        $props_ = array_filter($props,function ($v,$k){  return $v['prop_value_type'] == 0 ; },ARRAY_FILTER_USE_BOTH);
        //prop_value表
        if (!empty($props_)) {
            $propvals = PropValue::getDataByWhere([
                ['column' => 'merchant_id', 'value' => [$this->merchantId, 0], 'operator' => 'in'],
                ['column' => 'prop_id', 'value' => array_pluck($props_, 'id'), 'operator' => 'in'],
                ['column' => 'is_delete', 'value' => 1, 'operator' => '=']
            ], ['id', 'prop_id', 'prop_type', 'title'])->toArray();
        }else{
            $propvals = [];
        }
        //Store表
        $prop_store_id=Prop::getPropIdByName('store',$goods_cat_id);
        $prop_staff_id=Prop::getPropIdByName('staff',$goods_cat_id);
        $store_list = Store::where(['merchant_id' => $this->merchantId, 'is_delete' => 1, 'enabled' => 1])  ->select(\DB::raw('id,name as title,"' . $prop_store_id . '" as prop_id'))  ->get() ->toArray();
        foreach ($store_list as &$store) {
            $store['staff_values'] = ApptStaff::where('store_id', $store['id'])  ->where(['merchant_id' => $this->merchantId, 'is_delete' => 1])  ->select(\DB::raw('id,nickname as title,"' . $prop_staff_id . '" as prop_id'))->get();
        }
        //组合
        foreach ($props as $k => &$val) {
            if ($val['prop_type'] == 1) {//1:商品规格
                //门店和服务人员-----等调接口
                if ($val['prop_value_type'] == 1) {//门店规格
                    $val['key'] = 'store';
                } elseif ($val['prop_value_type'] == 2) {//人员规格
                    $val['key'] = 'staff';
                } elseif ($val['prop_value_type'] == 0 && $val['title'] == '预约日期') {
                    $val['key'] = 'date';
                }elseif($val['prop_value_type'] == 0 ){
                    $val['key'] = 'time';
                }
                if ($val['prop_value_type'] == 1 || $val['prop_value_type'] == 2) {
                    $val['store_values'] = $store_list;
                    continue;
                }
            }
            foreach ($propvals as $key => $value) {
                if ($val['id'] == $value['prop_id']) {
                    if ($val['prop_type'] == $value['prop_type'] && $val['prop_type'] == 0) {
                        $val['input_values'][] = $value;
                    } else {
                        $val['prop_values'][] = $value;
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
        $cat = (new GoodsCat())->get_data_by_id($goods_cat_id);
        if (!empty($cat)) {
            $attr['appointment'] = $cat['cat_type'] == 1 ? 1 : 0;
            $attr['virtual'] = $cat['cat_type'] == 2 ? 1 : 0;
        }else{
            $attr = [];
        }
        return Response::json(['errcode' => 0, 'errmsg'=> '' , 'data' => $props ,'cat_attr' => $attr ], 200);

    }
    //商品属性添加
    public function addProp(){
        //参数验证
        $validator = Validator::make($this->param,[
            'prop_type' => ['required','in:0,1'],
            'goods_cat_id'=>['required','integer'],
            'title'=>['required','string'],
            'val_title'=>['required','array'],
        ],[
            'prop_type.required' => '参数缺失,区分类型值不能为空',
            'prop_type.in' => '参数错误',
            'goods_cat_id.required' => '商品分类参数缺失',
            'goods_cat_id.integer' => '商品分类参数请传入数字类型值',
            'title.required' => '名称参数缺失',
            'title.string' => '名称参数请传入字符类型值',
            'val_title.required' => '属性值名称参数缺失',
            'val_title.array' => '属性值名称请传入数数组型值'
        ]);
        if($validator -> fails()){
            return Response::json(['errcode'=>99001, 'errmsg'=>$validator->messages()->first()],200);
        }
        $prop_type    = $this->param['prop_type'];
        $goods_cat_id = $this->param['goods_cat_id'];
        $title        = $this->param['title'];
        $val_title    = $this->param['val_title'];

        //规格添加
        $prop_rs = Prop::insert_data([ 'merchant_id' => $this->merchantId , 'goods_cat_id' => $goods_cat_id, 'prop_type' => $prop_type , 'title' => $title ]);
        if (!$prop_rs) {
            return Response::json(['errcode'=>99002 , 'errmsg' => ($prop_type == 0 ? '属性添加失败' : '规格添加失败') ], 200);
        }
        //规格多条规格值添加
        $return_arr = array();
        foreach ($val_title as $key => $val) {
            $propval_rs = PropValue::insert_data(['merchant_id'=>$this->merchantId , 'prop_id' => $prop_rs , 'prop_type' => $prop_type , 'title' => $val]);
            if (!$propval_rs) {
                return Response::json(['errcode'=>99002 , 'errmsg' => ($prop_type == 0 ? '属性添加失败' : '规格添加失败') ], 200);
            }
            $return_arr[$key] = [  'title' => $val, 'id' =>  $propval_rs ] ;
        }
        $data = [
            'id' => $prop_rs,
            'title' => $title,
            'value' => $return_arr,
        ];
        return Response::json(['errcode'=>0 , 'errmsg' =>'添加成功', 'data' => $data], 200);
    }
    //商品属性编辑
    public function delProp(){
        //参数验证
        $validator = Validator::make($this->param,[
            'id'        => ['required','integer'],
            'prop_type' => ['required','in:0,1'],
        ],[
            'id.required' => '参数缺失,id不能为空',
            'id.integer' => 'id传入数字类型值',
            'prop_type.required' => '参数缺失,区分类型值不能为空',
            'prop_type.in' => '参数错误',
        ]);
        if($validator -> fails()){
            return Response::json(['errcode'=>99001, 'errmsg'=>$validator->messages()->first()],200);
        }
        $id = $this->param['id'];
        $prop_type    = $this->param['prop_type'] ;
        //上传属性
        $prop_res = Prop::updateOpenApi(['id' => $id , 'merchant_id' =>$this->merchantId , 'prop_type' => $prop_type  ], ['is_delete' => -1 , 'updated_time' =>  date('Y-m-d H:i:s') ]);
        if (!$prop_res) {
            return Response::json(['errcode'=>99002, 'errmsg'=> $prop_type == 0 ? '属性删除失败' : '规格删除失败'   ],200);
        }
        //删除属性值
        $propval_res = PropValue::updateOpenApi(['prop_id' => $id , 'merchant_id' =>$this->merchantId , 'prop_type' => $prop_type  ], ['is_delete' => -1 , 'updated_time' =>  date('Y-m-d H:i:s') ]);
        if (!$propval_res) {
            return Response::json(['errcode'=>99002, 'errmsg'=> $prop_type == 0 ?   '属性值删除失败' : '规格值删除失败'],200);
        }
        return Response::json(['errcode'=>0, 'errmsg'=> '删除成功' ],200);
    }
    //商品属性删除
    public function editProp(){
        //参数验证
        $validator = Validator::make($this->param,[
            'id'          => ['required','integer'],
            'prop_type'   => ['required','in:0,1'],
            'title'       => ['required','string'],
            'prop_values' => ['array'],
        ],[
            'id.required' => '参数缺失,id不能为空',
            'id.integer' => 'id传入数字类型值',
            'prop_type.required' => '参数缺失,区分类型值不能为空',
            'prop_type.in' => '参数错误',
            'title.required' => '名称参数缺失',
            'title.string' => '名称参数请传入字符中类型值',
            'prop_values.array' => '属性值请出入数组类型值',
        ]);
        if($validator -> fails()){
            return Response::json(['errcode'=>99001, 'errmsg'=>$validator->messages()->first()],200);
        }
        $id           = $this->param['id'];
        $prop_type    = $this->param['prop_type']  ;
        $title        = $this->param['title'];
        $prop_values  = $this->param['prop_values'];
        //编辑属性
        $res = Prop::updateOpenApi(['id' => $id , 'merchant_id' =>$this->merchantId , 'prop_type' => $prop_type  ], ['title'=>$title,'updated_time'=>date('Y-m-d H:i:s')]);
        if (!$res) {
            return Response::json(['errcode'=>99002, 'errmsg'=>$prop_type == 0 ? '商品属性名称更新失败' : '商品规格名称更新失败' ,'data' => []],200);
        }
        if (empty($prop_values) && $id <= 0) {
            return Response::json(['errcode'=>99002, 'errmsg'=>$prop_type == 0 ? '商品属性名称更新失败' : '商品规格名称更新失败' ,'data' => []],200);
        }
        //编辑或添加 属性值
        $data = [];
        if(!empty($prop_values)){
            $db_propvals = PropValue::query()->where([ 'prop_id' => $id, 'prop_type' => $prop_type, 'merchant_id' => $this->merchantId, 'is_delete' => 1 ])->get(['id', 'title'])->toArray();
            foreach ($prop_values as $key => $item) {
                $data[$key] =  [ 'title' => $item['title']] ;
                if (isset($item['id']) && $item['id'] > 0) {
                    PropValue::updateOpenApi([ 'id' => $item['id'], 'prop_type' => $prop_type, 'merchant_id' => $this->merchantId ], ['title' => $item['title'], 'updated_time' => date('Y-m-d H:i:s')]);
                    $data[$key]['id'] = $item['id'];
                } else {
                    $data[$key]['id'] = PropValue::insert_data(['merchant_id' => $this->merchantId, 'prop_id' => $id,  'prop_type' => $prop_type, 'title' => $item['title']]);
                }
            }
            //删除多余属性值
            if (!empty($db_propvals)) {
                $diff = array_diff(array_column($db_propvals, 'id'), array_column($prop_values, 'id'));
                if (!empty($diff)) {
                    PropValue::updateByWhere([
                        ['column' => 'id', 'value' => $diff, 'operator' => 'in'],
                        ['column' => 'prop_type', 'value' => $prop_type, 'operator' => '='],
                        ['column' => 'merchant_id', 'value' => $this->merchantId, 'operator' => '=']
                    ], ['is_delete' => -1, 'updated_time' => date('Y-m-d H:i:s')] );
                }
            }
        }


        return Response::json(['errcode'=>0, 'errmsg'=>'更新成功' ,'data' => $data ],200);
    }
    //商品属性值列表
    public function getValue(){
        //参数验证
        $validator = Validator::make($this->param,[
            'prop_id'          => ['required','integer'],
            'prop_type'   => ['required','in:0,1'],
        ],[
            'id.required' => '参数缺失,id不能为空',
            'id.integer' => 'id传入数字类型值',
            'prop_type.required' => '参数缺失,区分类型值不能为空',
            'prop_type.in' => '参数错误',
        ]);
        if($validator -> fails()){
            return Response::json(['errcode'=>99001, 'errmsg'=>$validator->messages()->first()],200);
        }
        $prop_id   = $this->param['prop_id'] ;
        $prop_type = $this->param['prop_type'] ;

        $data = PropValue::query()->where([ 'merchant_id' => $this->merchantId , 'prop_id' => $prop_id , 'prop_type' => $prop_type , 'is_delete' => 1])->get(['id','title'])->toArray();
        return Response::json(['errcode'=>0, 'errmsg'=>'' , 'data' => $data],200);
    }
    //商品属性值添加
    public function addValue(){
        //参数验证
        $validator = Validator::make($this->param,[
            'prop_id'          => ['required','integer'],
            'prop_type'   => ['required','in:0,1'],
            'title'       =>  ['required','string'],
        ],[
            'id.required' => '参数缺失,id不能为空',
            'id.integer' => 'id传入数字类型值',
            'prop_type.required' => '参数缺失,区分类型值不能为空',
            'prop_type.in' => '参数错误',
            'prop_type.required' => '标题不能为空不能为空',
            'prop_type.string' => '标题请传入字符类型',
        ]);
        if($validator -> fails()){
            return Response::json(['errcode'=>99001, 'errmsg'=>$validator->messages()->first()],200);
        }

        $prop_id   = $this->param['prop_id'];
        $title     = $this->param['title'];
        $prop_type = $this->param['prop_type'];

        $res = PropValue::insert_data([ 'merchant_id' => $this->merchantId , 'prop_id' => $prop_id , 'prop_type' => $prop_type , 'title' => $title]);
        if(!$res){
            return Response::json(['errcode'=>99002, 'errmsg'=>'添加意外出错'],200);
        }
        return Response::json(['errcode'=>0, 'errmsg'=>'' , 'data' => [ 'id' => $res  , 'title' => $title] ],200);
    }
    //商品属性值删除
    public function delValue(){
        $id = isset($this->param['id'])?intval($this->param['id']):0;
        if($id<=0){
            return Response::json(['errcode' => 99001 ,'errmsg' => '参数错误,规格属性值不能为空'], 200);
        }
        $res = PropValue::updateOpenApi([ 'id' => $id , 'merchant_id' => $this->merchantId ,'is_delete' => 1 ],[ 'is_delete' => -1 , 'updated_time' =>  date('Y-m-d H:i:s')]);
        if(!$res){
            return Response::json(['errcode' => 99002 ,'errmsg' => '删除失败'], 200);
        }
        return Response::json(['errcode' => 0 ,'errmsg' => '删除成功'], 200);
    }
    //商品标签
    public function getTag(){

        $title = isset($this->param['title']) ? $this->param['title'] : '';
        $offset = isset($this->param['page']) ? $this->param['page'] : 1;
        $limit = isset($this->param['length']) ? $this->param['length'] : 25;
        /*
        $merchant_setting_res = MerchantSetting::get_data_by_id($this->merchantId);
        if (!empty($merchant_setting_res) && $merchant_setting_res->label_enabled != 1) {//商家设置里未开启
            return Response::json(['errcode' => 0, 'errmsg' => '商家未开启服务标签', 'data' => [] ,'count' => 0 ], 200);
        }
        */
        $res = ServeLabel::where(['merchant_id' => $this->merchantId])->get()->toArray();
        if (empty($res)) {
            $config = Config::get('servelabel');
            foreach ($config as $v) {
                ServeLabel::create([ 'merchant_id' => $this->merchantId , 'title' => $v['title'] , 'content' => $v['content'] ,'logo' => $v['logo'] ,'label_type' => $v['label_type'] ,'is_enabled' =>$v['is_enabled'] ,'status' => $v['status'] ]);
            }
        }
        $query = ServeLabel::query() ->where('merchant_id', '=', $this->merchantId) ;
        if ($title) {
            $query ->where('title', 'like',  '%' . $title . '%') ;
        }
        $count = $query->count();
        if (!empty($offset)) {
            $query->skip(($offset -1 ) * $limit )->take($limit);
        }
        $staticHost = Config::get('weixin.static_host');
        $qnHost = Config::get('weixin.qn_host');
        $list = $query->get(['id','title','content','label_type','logo'])->toArray();
        foreach ($list as $k => &$v) {
            $v['logo'] = $v['label_type'] == 0 ? $staticHost.'/'.$v['logo'] :  $qnHost.'/'.$v['logo'] ;
        }

        return Response::json(['errcode' => 0, 'errmsg' => '', 'data' => $list ,'count' => $count ], 200);
    }
    
    /***
     * 获取商品多规格数据
     */
    public function getMultiSku()
    {
    
        $id = isset($this->param['id']) ? intval($this->param['id']) : 0;
        if ($id <= 0) {
            $data['errcode'] = 81801;
            $data['errmsg'] = '参数缺失,id不能为空';
            return Response::json($data, 200);
        }
    
        //单规格商品
        $rs_goods = Goods::where(['id'=>$id, 'merchant_id'=>$this->merchantId])->first();
        ////////////////从缓存中读库存 start///////////////////////
        $service_goodsservice = new GoodsService();
        //dd($cache_goods);
        if( $rs_goods['is_sku']==0 ){
            $param['merchant_id']   = $this->merchantId;
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
            array('column' => 'merchant_id', 'value' => $this->merchantId, 'operator' => '='),
            array('column' => 'is_delete', 'value' => 1, 'operator' => '='),
        );
        $data = GoodsSpec::getDataByWhere($wheres, $this->fields);
        $res_data = [];
        if ($data) {
            $data = $data->toArray();
            foreach ($data as $key => $item) {
                ////////////////从缓存中读库存 start///////////////////////
                $param['merchant_id']   = $this->merchantId;
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
            $res_data['errcode'] = 82001;
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
            $res_data['errcode'] = 82002;
            $res_data['errmsg'] = '区分规格价格或库存类型的参数错误';
            return Response::json($res_data);
        }
        //        $wheres = array(
        //            0=>array('column' => 'merchant_id', 'value' => $this->merchantId, 'operator' => '='),
        //            1=>array('column' => 'is_delete', 'value' => 1, 'operator' => '='),
        //        );
        foreach ($data as $key => $item) {
            if (!is_numeric($item['val'])) {
                $res_data['errcode'] = 82003;
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
            //            $res = GoodsSpec::updateByWhere($wheres,$update_data);
            //            Goods::forgetStockCacheBySpecId($goods_id,$item['id']);
            $res = GoodsSpec::update_data($item['id'], $this->merchantId, $update_data);
            if ($res === false) {
                $error[] = $item;
            }
        }
        $goods_id = 0;
        if (isset($data[0]['id']) && $data[0]['id'] > 0) {
            $spec_data = GoodsSpec::getDataByWhere(array(
                array('column' => 'id', 'value' => $data[0]['id'], 'operator' => '='),
                array('column' => 'merchant_id', 'value' => $this->merchantId, 'operator' => '='),
                array('column' => 'is_delete', 'value' => 1, 'operator' => '='),
            ), array('id', 'goods_id'))->toArray();
            if (!empty($spec_data)) {
                $goods_id = $spec_data[0]['goods_id'];
                if ($this->param['column'] == 'price' && isset($min_price) && $goods_id > 0) {
                    $res_goods = Goods::update_data($goods_id, $this->merchantId, array('price' => $min_price, 'max_price' => $max_price));
                    //                    $res_goods = Goods::updateByWhere(array(
                    //                        array('column' => 'id', 'value' => $goods_id, 'operator' => '='),
                    //                        array('column' => 'merchant_id', 'value' => $this->merchantId, 'operator' => '='),
                    //                        array('column' => 'is_delete', 'value' => 1, 'operator' => '='),
                    //                    ),array('price'=>$min_price,'max_price'=>$max_price));
                } else if ($this->param['column'] == 'stock' && isset($total_stock) && $goods_id > 0) {
                    $res_goods = Goods::update_data($goods_id, $this->merchantId, array('stock' => $total_stock));
                    //                    $res_goods = Goods::updateByWhere(array(
                    //                        array('column' => 'id', 'value' => $goods_id, 'operator' => '='),
                    //                        array('column' => 'merchant_id', 'value' => $this->merchantId, 'operator' => '='),
                    //                        array('column' => 'is_delete', 'value' => 1, 'operator' => '='),
                    //                    ),array('stock'=>$total_stock));
                }
            }
        }
    
        if (!empty($error) || $res_goods === false) {
            $res_data['errcode'] = 82004;
            $res_data['errmsg'] = '更新失败';
        } else {
            GoodsSpec::forgetCacheByGoods($goods_id, $this->merchantId);
            $res_data['errcode'] = 0;
            $res_data['errmsg'] = '更新成功';
        }
        return Response::json($res_data);
    }
}

<?php
/**
 * 商品管理
 */
namespace App\Http\Controllers\Super;
use App\Models\Goods;
use App\Models\GoodsAppt;
use App\Models\GoodsCat;
use App\Models\GoodsComponent;
use App\Models\GoodsImg;
use App\Models\GoodsLabelRs;
use App\Models\GoodsProp;
use App\Models\GoodsRegionExclude;
use App\Models\GoodsServeLabel;
use App\Models\GoodsSpec;
use App\Models\GoodsTag;
use App\Models\GoodsTagRs;
use App\Models\MerchantSetting;
use App\Models\Prop;
use App\Models\Region;
use App\Models\ServeLabel;
use App\Models\Shipment;
use App\Models\AloneActivityRecode;
use App\Models\DiscountActivity;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
class GoodsController extends Controller{

    protected $params;//参数
    protected $goods_model;//商品model
    protected $component_model;//商品详情
    protected $img_model;//商品图片
    protected $prop_model;//商品属性
    protected $spec_model;//商品规格
    protected $tag_res_model;//商品分组
    protected $region_exclude_model;//不可配送区域
    protected $serve_label_model;//商品服务标签
    protected $tag_model;        //商品分组
    protected $servelabel;       //服务标签
    protected $fields;           //查询字段
    protected $user_id;          //商户id
    protected $version;         //当前用户的版本
    protected $version_tag ;     //用于判断的版本标识
    const ENABLE_ONSALE_GOODS_NUM = 6;
    public function __construct(Request $request,Goods $goods_model,
                                GoodsComponent $component_model,GoodsImg $img_model,
                                GoodsProp $prop_model,GoodsSpec $spec_model,GoodsTag $tag_model,
                                GoodsTagRs $tag_res_model,GoodsRegionExclude $region_exclude_model,
                                GoodsServeLabel $serve_label_model,ServeLabel $servelabel){
        $this->params = $request->all();
        $this->goods_model = $goods_model;
        $this->component_model = $component_model;
        $this->img_model = $img_model;
        $this->prop_model = $prop_model;
        $this->spec_model = $spec_model;
        $this->tag_res_model = $tag_res_model;
        $this->region_exclude_model = $region_exclude_model;
        $this->serve_label_model = $serve_label_model;
        $this->tag_model = $tag_model;
        $this->fields = array(
            'id','merchant_id','goods_cat_id','title','goods_sn','barcode','original_price','price','max_price',
            'img','intro','weight','volume','stock','stock_type','shipment_id','postage','cquota',
            'csale','base_csale','csale_show','crate','onsale','onsale_type','onsale_time','listorder','is_sku',
            'is_discount','recommend','qrcode_ticket','shelve_at','unshelve_at','serve_label','enable_logistics',
            'is_locked','is_givecredit','created_time','updated_time','is_delete'
        );
        $this->servelabel = $servelabel;
    }

    /**
     *
     *查询商品列表
     */
    public function goodsList(Request $request)
    {
        $offset = isset($this->params['offset']) ? $this->params['offset'] : 0;
        $limit  = isset($this->params['limit']) ? $this->params['limit'] : 20;
        $wheres = [
            ['column' => 'is_delete', 'operator' => '=', 'value' => 1],                          //商品未删除
        ];
        //是否显示商品总数
        if (isset($this->params['all_nums']) && $this->params['all_nums'] === 'show') {
            $hash['all_nums'] = Goods::get_data_count($wheres);                                     //商品总数
        }
        $new_data = date('Y-m-d H:i:s', time() - 7 * 24 * 60 * 60);
        //是否显示上新总数
        if (isset($this->params['new_nums']) && $this->params['new_nums'] === 'show') {
            $wheres[] = ['column' => 'shelve_at', 'operator' => '>', 'value' => $new_data];
            $hash['new_nums'] = Goods::get_data_count($wheres);                                     //上新总数
            array_pop($wheres);
        }
        //根据商品id过滤
        if(isset($this->params['id']) && $this->params['id'] >0){
            $wheres[] = ['column' => 'id', 'operator' => '=', 'value' => $this->params['id']];
        }
        //只显示新品
        if(isset($this->params['new_goods']) && $this->params['new_goods'] === 'show') {
            $wheres[] = ['column' => 'shelve_at', 'operator' => '>', 'value' => $new_data];
        }
        //根据商家账号
        if(isset($this->params['merchant_user_name']) && $this->params['merchant_user_name']){
            $this->params['merchant_id'] = User::where(['username'=>$this->params['merchant_user_name'],'is_delete'=>1])->pluck('merchant_id');
        }
        //根据商家id过滤
        if(isset($this->params['merchant_id']) && !empty($this->params['merchant_id'])){
            $wheres[] = ['column'=>'merchant_id','operator' => '=','value' =>$this->params['merchant_id']];
        }
        //商品分组
        if (isset($this->params['tag_id']) && intval($this->params['tag_id']) > 0) {
            $wheres_tag = array(
                array('column' => 'tag_id', 'value' => $this->params['tag_id'], 'operator' => '=')
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
        if (isset($this->params['title']) && trim($this->params['title'])) {
            $wheres[] = ['column' => 'title', 'operator' => 'like', 'value' => '%' . trim($this->params['title']) . '%'];    //搜索商品名称
        }
        $hash['search_count'] = Goods::get_data_count($wheres);                                 //符合条件的商品数
        $fields = 'id,merchant_id,title,price,onsale,original_price,img,base_csale,is_sku,shelve_at,(base_csale + csale) as csale,csale as goods_csale,csale_show';

        //排序 【首页商家时间优先、列表页销量优先。】
        $order = ['column' => 'merchant_id', 'direct' => 'asc'];    //默认销量优先【首页调接口请传参】
        if (isset($this->params['csale'])) {//销量排序
            if (trim($this->params['csale']) == 'desc') {
                $order = ['column' => 'csale', 'direct' => 'desc'];
            } elseif (trim($this->params['csale']) == 'asc') {
                $order = ['column' => 'csale', 'direct' => 'asc'];
            }
        } elseif (isset($this->params['price'])) {//商品价格排序
            if (trim($this->params['price']) == 'desc') {
                $order = ['column' => 'price', 'direct' => 'desc'];
            } elseif (trim($_search['price']) == 'asc') {
                $order = ['column' => 'price', 'direct' => 'asc'];
            }
        } elseif (isset($this->params['shelve_at'])) {//商品上架时间排序
            if (trim($this->params['shelve_at']) == 'desc') {
                $order = ['column' => 'shelve_at', 'direct' => 'desc'];
            } elseif (trim($this->params['shelve_at']) == 'asc') {
                $order = ['column' => 'shelve_at', 'direct' => 'asc'];
            }
        }
        $orders[] = $order;
        if ($order['column'] != 'shelve_at') {
            $orders[] = ['column' => 'shelve_at', 'direct' => 'desc'];  //第二排序字段:上架时间
        }
        $hash['data'] = Goods::get_data_list($wheres, $fields, $offset, $limit, $orders);
        if($hash['search_count'] >0){
            foreach($hash['data'] as $key=>$item){
                $hash['data'][$key]['merchant_user_name'] = User::where(['merchant_id'=>$item['merchant_id'],'is_delete'=>1])->pluck('username');
            }
        }
        $hash['errcode'] = 0;
        $hash['errmsg'] = '获取成功';
        return Response::json( $hash );
    }

    /**
     * 商品详情
     */
    public function goodsDetail(Request $request){
        $id = $request->id;
        if($id<=0){
            $res['errcode'] = 99001;
            $res['errmsg'] = '参数缺失,商品id不能为空';
            return Response::json($res, 200);
        }
        //商品主表数据
        $goods_wheres = array(
            array('column'=>'id','value'=>$id,'operator'=>'='),
            array('column'=>'is_delete','value'=>1,'operator'=>'=')
        );
        $goods_data = $this->goods_model->get_data_list($goods_wheres,$this->fields);
        if($goods_data){
            $data['data'] = $goods_data[0];
            $cat_model = new GoodsCat();
            $cat = $cat_model->get_data_by_id($data['data']['goods_cat_id']);
            if(!empty($cat)){
                $data['data']['appointment'] = $cat['cat_type'] == 1?1:0;
            }
            //商品分类名称
            $cat_model = new GoodsCat();
            $cats = array();
            $cat_model->getParentCat($data['data']['goods_cat_id'],$cats);
            if(!empty($cats)){
                $cats = array_reverse($cats);
                $data['data']['cat_name'] = implode('>',$cats);
            }else{
                $data['data']['cat_name'] = '';
            }
            //商品属性信息
            $prop_wheres = array(
                array('column'=>'goods_id','value'=>$id,'operator'=>'='),
                array('column'=>'is_delete','value'=>1,'operator'=>'=')
            );
            $goods_prop = $this->prop_model->getDataByWhere($prop_wheres,array('id','merchant_id','goods_id','prop_id','prop_vid','prop_value'));
            $goods_prop = $goods_prop->toArray();
            if(!empty($goods_prop)){
                $props = [];
                $prop_ids = array_unique(array_column($goods_prop,'prop_id'));
                $prop_title = Prop::getDataByWhere(array(
                    array('column'=>'prop_type','value'=>0,'operator'=>'='),
                    array('column'=>'is_delete','value'=>1,'operator'=>'='),
                    array('column'=>'id','value'=>$prop_ids,'operator'=>'in'),
                ),array('goods_cat_id','prop_type','title','id'))->toArray();
                foreach($prop_title as $key=>$item){
                    $prop_title[$item['id']] = $item;
                }
                foreach($goods_prop as $key => $item){
                    $title = isset($prop_title[$item['prop_id']]['title'])?$prop_title[$item['prop_id']]['title']:'';
                    $props[$item['prop_id']]['goods_cat_id'] = $data['data']['goods_cat_id'];
                    $props[$item['prop_id']]['id'] = $item['id'];
                    $props[$item['prop_id']]['prop_id'] = $item['prop_id'];
                    $props[$item['prop_id']]['title'] = $title;
                    $props[$item['prop_id']]['prop_vid'] = $item['prop_vid'];
                    $props[$item['prop_id']]['prop_value'] = $item['prop_value'];
                    $props[$item['prop_id']]['type'] = 0;
                }
                $props = array_values($props);
                $data['data']['props'] = $props;
            }else{
                $data['data']['props'] = [];
            }
            //商品图片
            $img_wheres = array(
                array('column'=>'merchant_id','value'=>$this->user_id,'operator'=>'='),
                array('column'=>'goods_id','value'=>$id,'operator'=>'=')
            );
            $goods_img = $this->img_model->getDataByWhere($img_wheres,array('id','img'));
            if($goods_img){
                $data['data']['imgs'] = $goods_img->toArray();
            }else{
                $data['data']['imgs'] = [];
            }
            //商品分组
            $tag_rs_wheres = array(
                array('column'=>'goods_id','value'=>$id,'operator'=>'=')
            );
            $goods_tag_rs = $this->tag_res_model->getDataByWhere($tag_rs_wheres,'id,tag_id')->toArray();
            if($goods_tag_rs){
                $data['data']['tags'] = $goods_tag_rs;
                foreach($data['data']['tags'] as $key => $tag){
                    $tag =  $this->tag_model->getDataByWhere(array(
                        array('column'=>'id','value'=>$tag['tag_id'],'operator'=>'='),
                        array('column'=>'is_delete','value'=>1,'operator'=>'=')
                    ),array('id','title'))->toArray();
                    $data['data']['tags'][$key]['title'] = isset($tag[0]['title'])?$tag[0]['title']:'';
                }
            }else{
                $data['data']['tags'] = [];
            }
            //商品服务标签
            $server_wheres = array(
                array('column'=>'goods_id','value'=>$id,'operator'=>'=')
            );
            $serves = $this->serve_label_model->getDataByWhere($server_wheres,'id,label_id')->toArray();
            if($serves){
                $data['data']['label_ids'] = $serves;
                foreach($data['data']['label_ids'] as $k => $v){
                    $serve = $this->servelabel->getDataByWhere(
                        array(
                            array('column'=>'id','value'=>$v['label_id'],'operator'=>'='),
                        ),array('id','title'),''
                    )->toArray();
                    $data['data']['label_ids'][$k]['title'] =  isset($serve[0]['title'])?$serve[0]['title']:'';
                }
            }else{
                $data['data']['label_ids'] = [];
            }
            //商品规格
            $spec_wheres = array(
                array('column'=>'goods_id','value'=>$id,'operator'=>'='),
                array('column'=>'is_delete','value'=>1,'operator'=>'=')
            );
            $spec = $this->spec_model->getDataByWhere($spec_wheres,array(
                'id','merchant_id','goods_id','price','stock','spec_sn','barcode','props','props_str','img','csale'
            ));
            $spec = $spec->toArray();
            if($spec){
                foreach($spec as $k => $item){
                    if(!empty($item['props_str'])){
                        $spec[$k]['props_str'] =  str_replace(';',':',$item['props_str']);
                    }
                }
                $data['data']['products'] = $spec;
            }else{
                $data['data']['products'] = [];
            }
            //商品不配送区域
            $region_wheres = array(
                array('column'=>'goods_id','value'=>$id,'operator'=>'=')
            );
            $region = $this->region_exclude_model->getDataByWhere($region_wheres,'id,region_ids');
            $region = $region->toArray();
            if(!empty($region)){
                if($region){
                    $regions = explode(',',$region[0]['region_ids']);
                    $regions_arr = [];
                    foreach($regions as $k => $val){
                        $title = Region::get_title_by_id($val);
                        $regions_arr[$k]['id'] = $val;
                        $regions_arr[$k]['title'] = $title;
                    }
                    $data['data']['regions'] = $regions_arr;

                }else{
                    $data['data']['regions'] = [];
                }
            }else{
                $data['data']['regions'] = [];
            }
            //商品详情组件
            $component_wheres = array(
                array('column'=>'goods_id','value'=>$id,'operator'=>'=')
            );
            $component = $this->component_model->getDataByWhere($component_wheres,array('id','content'));
            $component = $component->toArray();
            if(!empty($component)){
                $component = $component[0];
                $context = $component['content'];
                $data['data']['content'] = json_decode($context);
            }else{
                $data['data']['content'] = [];
            }

        }else{
            $data['data'] = [];
        }
        $data['errmsg'] = '';
        $data['errcode'] = 0;
        return Response::json($data);
    }

    /**
     * 商品上下架，单个或者批量
     */
    public function goodsOnsale(){
        if(!isset($this->params['id']) || ((!is_array($this->params['id'])) && intval($this->params['id']) == 0)){
            $res['errcode'] = 99001;
            $res['errmsg'] = '参数缺失,商品id不能为空';
            return Response::json($res, 200);
        }
        $id = $this->params['id'];
        $ids = [];
        if(!is_array($id)){
            $id = intval($id);
            $ids = [$id];
        }else if(is_array($id)){
            $ids = $id;
        }
        if(empty($ids)){
            $res['errcode'] = 99001;
            $res['errmsg'] = '参数错误';
            return Response::json($res, 200);
        }

        $type = isset($this->params['type'])?intval($this->params['type']):0;
        if(!in_array($type,array(0,1))){
            $res['errcode'] = 99001;
            $res['errmsg'] = '上下架类型参数错误';
            return Response::json($res, 200);
        }
        if($type == 1){
            $update_data['onsale'] =  1;
            $update_data['shelve_at'] =  date('Y-m-d H:i:s',time());
        }else{
            $update_data['onsale'] =  0;
            $update_data['unshelve_at'] =  date('Y-m-d H:i:s',time());
        }
        $return_res = Goods::whereIn('id',$ids)->update($update_data);

        if($return_res === false){
            $res['errcode'] = 1;
            $res['errmsg'] = '操作失败';
        }else{
            $res['errcode'] = 0;
            $res['errmsg'] = '操作成功';
        }
        return Response::json($res, 200);
    }
}
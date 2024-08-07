<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017/9/5
 * Time: 11:01
 * Author: DuMing
 * 后台商品
 */

namespace App\Http\Controllers\OpenApi\Goods;

use App\Http\Requests\Admin\GoodsRequest;
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
use App\Models\Merchant;
use App\Models\MerchantSetting;
use App\Models\Prop;
use App\Models\Region;
use App\Models\ServeLabel;
use App\Models\Shipment;
use App\Services\GoodsService;
use App\Services\UserPrivService;
use App\Utils\CommonApi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use DB;
use App\Models\WeixinQrcode;
use App\Models\GoodsCsv;
use App\Models\GoodsVirtual;

class GoodsController extends Controller
{

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
    protected $version_tag;     //用于判断的版本标识
    const ENABLE_ONSALE_GOODS_NUM = 6;

    public function __construct(Request $request, Goods $goods_model,
                                GoodsComponent $component_model, GoodsImg $img_model,
                                GoodsProp $prop_model, GoodsSpec $spec_model, GoodsTag $tag_model,
                                GoodsTagRs $tag_res_model, GoodsRegionExclude $region_exclude_model,
                                GoodsServeLabel $serve_label_model, ServeLabel $servelabel, Merchant $merchant)
    {
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
            'id', 'goods_cat_id', 'title', 'goods_sn', 'barcode', 'original_price', 'price', 'max_price',
            'img', 'intro', 'weight', 'volume', 'stock', 'stock_type', 'postage', 'cquota',
            'csale', 'base_csale', 'csale_show', 'crate', 'onsale', 'onsale_type', 'onsale_time', 'listorder', 'is_sku',
            'is_discount', 'recommend', 'qrcode_ticket', 'shelve_at', 'unshelve_at', 'serve_label', 'enable_logistics',
            'is_locked', 'is_givecredit', 'created_time', 'updated_time', 'is_delete', 'goods_type', 'display_type'
        );
        $user_openapi = $request->user();
        $this->merchant_id = $user_openapi['merchant_id'];
        $this->servelabel = $servelabel;
        $this->version = 1;
        $result = $merchant->get_data_by_id($this->merchant_id);
        if (!empty($result) && !empty($result['version_id'])) {
            $this->version = $result['version_id'];
        }
        
    }

    /**
     * 商品列表（出售中，库存紧张，仓库中）
     */
    public function index()
    {
        //排序字段
        $column = (!empty($this->params['column']) && trim($this->params['column'])) ? $this->params['column'] : 'created_time';
        if (!in_array($column, array('price', 'stock', 'csale', 'created_time'))) {
            $res['errcode'] = 81101;
            $res['errmsg'] = '排序字段参数错误';
            return Response::json($res, 200);
        }
        
        //排序
        $direction = (!empty($this->params['direct']) && trim($this->params['direct'])) ? $this->params['direct'] : 'desc';
        if (!in_array($direction, array('desc', 'asc'))) {
            $res['errcode'] = 81102;
            $res['errmsg'] = '排序参数错误';
            return Response::json($res, 200);
        }
        $order['column'] = $column;
        $order['direct'] = $direction;
        $query = Goods::where('goods.merchant_id', $this->merchant_id)->where('goods.is_delete', 1)->orderBy('goods.' . $column, $direction);
        
        //商品类型  
        if( isset($this->params['type']) ){
            if ( $this->params['type'] == 1) {
                //1：普通商品
                $query->whereIn('goods.is_sku', [0, 1]);
            } elseif ($this->params['type'] == 2) {
                //2：预约商品
                $query->where('goods.is_sku', 2);
            }
        }
        
        //商品名称模糊查询
        $title = isset($this->params['title']) ? trim($this->params['title']) : '';
        if (!empty($title)) {
            $query->where('goods.title', 'like', '%' . $title . '%');
        }

        //商品货号
        $goods_sn = isset($this->params['goods_sn']) ? trim($this->params['goods_sn']) : '';
        if (!empty($goods_sn)) {
            $query->where('goods.goods_sn', 'like', '%' . $goods_sn . '%');
        }
        
        //商品标签
        $tag_id = isset($this->params['tag_id']) ? $this->params['tag_id'] : 0;
        if ($tag_id > 0) {
            $tag_wheres = array(
                array('column' => 'merchant_id', 'value' => $this->merchant_id, 'operator' => '='),
                array('column' => 'tag_id', 'value' => $tag_id, 'operator' => '=')
            );
            $tags = $this->tag_res_model->getDataByWhere($tag_wheres, 'goods_id')->toArray();
            if (!empty($tags)) {
                $tag_ids = array_column($tags, 'goods_id');
            } else {
                $tag_ids = [-1];
            }
            $query->whereIn('goods.id', $tag_ids);
        }

        //上架/下架 状态
        $on_sale = !isset($this->params['sale'])||trim($this->params['sale'])=='' ? 2 : intval($this->params['sale']) ;
        if ($on_sale == 1) {
            //上架
            $query->where('goods.onsale', 1);
            //库存紧张商品
            if (isset($this->params['stock'])) {
                $stock = intval($this->params['stock']);
                $query->where('goods.stock', '<=', $stock);
            }
        } elseif ($on_sale == 0) {
            //下架
            $query->where('onsale', 0);
            //售完下架
            if (isset($this->params['stock']) && $this->params['stock'] == 0) {
                $query->where('goods.stock', 0);
            }
            //仓库商品即将上架
            $onsale_type = isset($this->params['onsale_type']) ? intval($this->params['onsale_type']) : 0;
            if ($onsale_type == 1) {
                $query->where('goods.stock', '>', 0)->where('goods.onsale_time', '<>', '0000-00-00 00:00:00')->where('goods.onsale_type', '1');
            }
        } elseif ($on_sale == 2) {
            //无限制，查看所有的
        }
        //门店id
        $prop_store_id = Prop::where('merchant_id', $this->merchant_id)->where('is_delete', 1)->where('prop_value_type', 1)->value('id');
        if (!empty($this->params['store_id'])) {
            $query->leftJoin('goods_prop', 'goods.id', '=', 'goods_prop.goods_id')
                ->where('goods_prop.merchant_id', $this->merchant_id)
                ->where('goods_prop.is_delete', 1)
                ->where('goods_prop.prop_id', $prop_store_id)
                ->where('goods_prop.prop_vid', $this->params['store_id']);
        }
        $count = $query->count();
        $page = isset($this->params['page']) ? intval($this->params['page']) <= 0 ? 1 : intval($this->params['page']) : 1;
        $pagesize = isset($this->params['pagesize']) ? intval($this->params['pagesize']) <= 0 ? 10 : intval($this->params['pagesize']) : 10;
        $offset = ($page - 1) * $pagesize > $count ? floor($count / $pagesize) * $pagesize : ($page - 1) * $pagesize;
        $limit = $pagesize;
        $data['data'] = $query->offset($offset)->limit($limit)->get();
//        $data['data'] = $this->goods_model->get_data_list($wheres, $this->fields, $offset, $limit, $order);
        //商品类目
        $cat_model = new GoodsCat();
        $service_goodsservice = new GoodsService();
        foreach ($data['data'] as $key => $item) {
            unset($data['data'][$key]['merchant_id']);
            unset($data['data'][$key]['shipment_id']);
            $cat = $cat_model->get_data_by_id($item['goods_cat_id']);

            ////////////////从缓存中读库存 start///////////////////////
            if ($item['is_sku'] == 0) {
                //单规格
                $param['merchant_id'] = $this->merchant_id;
                $param['goods_id'] = $item['id'];
                $cache_goods = $service_goodsservice->getGoodsStock($param);
                if (isset($cache_goods['data'])) {
                    $data['data'][$key]['stock'] = $cache_goods['data'];
                }
            } else if ($item['is_sku'] == 1) {
                //多规格
                $stock_goodsspec = 0;
                $rs_goodsspec = GoodsSpec::get_data_by_goods_id($item['id'], $this->merchant_id);
                //dd($rs_goodsspec);
                if (!empty($rs_goodsspec)) {
                    $num_goodsspec = 0;
                    foreach ($rs_goodsspec as $key_gs => $val_gs) {
                        $cache_goodsspec = array();

                        $param['merchant_id'] = $this->merchant_id;
                        $param['goods_id'] = $item['id'];
                        $param['goods_spec_id'] = $val_gs['id'];
                        $cache_goodsspec = $service_goodsservice->getGoodsStock($param);

                        if (isset($cache_goodsspec['data'])) {
                            $num_goodsspec += $cache_goodsspec['data'];
                        }
                    }
                    $data['data'][$key]['stock'] = $num_goodsspec;
                }
            }
            ////////////////从缓存中读库存 end///////////////////////
            //预约服务库存
            if (!empty($cat)) {
                $data['data'][$key]['stock'] = $cat['cat_type'] == 1 ? '--' : $data['data'][$key]['stock'];
                $data['data'][$key]['is_appointment'] = $cat['cat_type'] == 1 ? 1 : 0;
            }
            //------------------------↓------------------------Qrcode start 
            //是否生成二维码start
            //author :renruiqi@dodoca.com
            if (isset($this->params['appid']) && !empty(trim($this->params['appid']))) {
                $appid_qr = trim($this->params['appid']);
                $data['data'][$key]['is_qrcode'] = count(WeixinQrcode:: check($item->id, 1, $appid_qr)) > 0 ? 1 : 2; //二维码是否生成 1:是 2:否
            }
            //------------------------↑------------------------Qrcodeend
        }
        $data['_count'] = $count;
        $data['errcode'] = 0;
        $data['errmsg'] = '';
        return Response::json($data);
    }

    /***
     * 单个，多个批量删除商品
     */
    public function delete()
    {
        if (!isset($this->params['id']) || ((!is_array($this->params['id'])) && intval($this->params['id']) == 0)) {
            $res['errcode'] = 81501;
            $res['errmsg'] = '参数缺失,商品id不能为空';
            return Response::json($res, 200);
        }
        $id = $this->params['id'];
        $ids = [];
        if (!is_array($id)) {
            $id = intval($id);
            $ids = [$id];
        } else if (is_array($id)) {
            $ids = $id;
        }
        if (empty($ids)) {
            $res['errcode'] = 81502;
            $res['errmsg'] = '参数错误';
            return Response::json($res, 200);
        }

//        //删除条件
//        $wheres = array(
//            array('column'=>'merchant_id','value'=>$this->merchant_id,'operator'=>'='),
//            array('column'=>'id','value'=>$ids,'operator'=>'in'),
//            array('column'=>'is_delete','value'=>1,'operator'=>'=')
//        );
//        $update_data['is_delete'] = -1;
        //删除goods表数据
//        $goods_res = $this->goods_model->updateByWhere($wheres,$update_data);
        foreach ($ids as $id) {
            $tem_res = Goods::get_data_by_id($id, $this->merchant_id);
            if ($tem_res->is_sku == 2) {
                //是否有预约商品的权限
                $appointservice_priv = UserPrivService::getUserPrivs();
                if (!in_array('trade_apptmanage', $appointservice_priv)) {
                    return ['errcode' => 81503, 'errmsg' => '无操作预约商品权限'];
                }
            }

            $goods_res = Goods::delete_data($id, $this->merchant_id);
            if ($goods_res === false) {
                break;
            }
        }

        if ($goods_res === false) {
            $res['errcode'] = 81504;
            $res['errmsg'] = '删除失败';
        } else {
            $res['errcode'] = 0;
            $res['errmsg'] = '删除成功';
        }
        return Response::json($res);
    }

    /**
     * 商品详情
     */
    public function detail()
    {
        $id = isset($this->params['id']) ? trim($this->params['id']) : 0;
        if ($id <= 0) {
            $res['errcode'] = 81201;
            $res['errmsg'] = '参数缺失,商品id不能为空';
            return Response::json($res, 200);
        }
        //商品主表数据
        $goods_wheres = array(
            array('column' => 'merchant_id', 'value' => $this->merchant_id, 'operator' => '='),
            array('column' => 'id', 'value' => $id, 'operator' => '='),
            array('column' => 'is_delete', 'value' => 1, 'operator' => '=')
        );
        $goods_data = $this->goods_model->get_data_list($goods_wheres, $this->fields);
        if ($goods_data) {
            $data['data'] = $goods_data[0];
            if ($data['data']['is_sku'] == 2) {
                $data['data']['goods_appt'] = GoodsAppt::where('merchant_id', $this->merchant_id)->where('goods_id', $id)->first();
            }
            $cat_model = new GoodsCat();
            $cat = $cat_model->get_data_by_id($data['data']['goods_cat_id']);
            if (!empty($cat)) {
                $data['data']['appointment'] = $cat['cat_type'] == 1 ? 1 : 0;
            }
            //搜集字段
//            $data['data']['cat_name'] = json_decode($data['data']['collect_fields'],true);
            //商品分类名称
            $cat_model = new GoodsCat();
            $cats = array();
            $cat_model->getParentCat($data['data']['goods_cat_id'], $cats);
            if (!empty($cats)) {
                $cats = array_reverse($cats);
                $data['data']['cat_name'] = implode('>', $cats);
            } else {
                $data['data']['cat_name'] = '';
            }
            //商品属性信息
            $prop_wheres = array(
                array('column' => 'merchant_id', 'value' => $this->merchant_id, 'operator' => '='),
                array('column' => 'goods_id', 'value' => $id, 'operator' => '='),
                array('column' => 'is_delete', 'value' => 1, 'operator' => '=')
            );
            $goods_prop = $this->prop_model->getDataByWhere($prop_wheres, array('id', 'merchant_id', 'goods_id', 'prop_id', 'prop_vid', 'prop_value'));
            $goods_prop = $goods_prop->toArray();
            if (!empty($goods_prop)) {
                $props = [];
                $prop_ids = array_unique(array_column($goods_prop, 'prop_id'));
                $prop_title = Prop::getDataByWhere(array(
                    array('column' => 'merchant_id', 'value' => $this->merchant_id, 'operator' => '='),
                    array('column' => 'prop_type', 'value' => 0, 'operator' => '='),
                    array('column' => 'is_delete', 'value' => 1, 'operator' => '='),
                    array('column' => 'id', 'value' => $prop_ids, 'operator' => 'in'),
                ), array('goods_cat_id', 'prop_type', 'title', 'id'))->toArray();
                foreach ($prop_title as $key => $item) {
                    $prop_title[$item['id']] = $item;
                }
                foreach ($goods_prop as $key => $item) {
                    $title = isset($prop_title[$item['prop_id']]['title']) ? $prop_title[$item['prop_id']]['title'] : '';
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
            } else {
                $data['data']['props'] = [];
            }
            //商品图片
            $img_wheres = array(
                array('column' => 'merchant_id', 'value' => $this->merchant_id, 'operator' => '='),
                array('column' => 'goods_id', 'value' => $id, 'operator' => '='),
                array('column' => 'goods_display_type', 'value' => 1, 'operator' => '=')
            );
            $goods_img = $this->img_model->getDataByWhere($img_wheres, array('id', 'img'));
            if ($goods_img) {
                $data['data']['imgs'] = $goods_img->toArray();
            } else {
                $data['data']['imgs'] = [];
            }
            $img_videos_wheres = array(
                array('column' => 'merchant_id', 'value' => $this->merchant_id, 'operator' => '='),
                array('column' => 'goods_id', 'value' => $id, 'operator' => '='),
                array('column' => 'goods_display_type', 'value' => 2, 'operator' => '=')
            );
            $goods_videos = $this->img_model->getDataByWhere($img_videos_wheres, array('id', 'img'));
            if ($goods_videos) {
                $data['data']['imgs_videos'] = $goods_videos->toArray();
            } else {
                $data['data']['imgs_videos'] = [];
            }
            //商品分组
            $tag_rs_wheres = array(
                array('column' => 'merchant_id', 'value' => $this->merchant_id, 'operator' => '='),
                array('column' => 'goods_id', 'value' => $id, 'operator' => '=')
            );
            $goods_tag_rs = $this->tag_res_model->getDataByWhere($tag_rs_wheres, 'id,tag_id')->toArray();
            if ($goods_tag_rs) {
                $data['data']['tags'] = $goods_tag_rs;
                foreach ($data['data']['tags'] as $key => $tag) {
                    $tag = $this->tag_model->getDataByWhere(array(
                        array('column' => 'merchant_id', 'value' => $this->merchant_id, 'operator' => '='),
                        array('column' => 'id', 'value' => $tag['tag_id'], 'operator' => '='),
                        array('column' => 'is_delete', 'value' => 1, 'operator' => '=')
                    ), array('id', 'title'))->toArray();
                    $data['data']['tags'][$key]['title'] = isset($tag[0]['title']) ? $tag[0]['title'] : '';
                }
            } else {
                $data['data']['tags'] = [];
            }
            //商品服务标签
            $server_wheres = array(
                array('column' => 'merchant_id', 'value' => $this->merchant_id, 'operator' => '='),
                array('column' => 'goods_id', 'value' => $id, 'operator' => '=')
            );
            $serves = $this->serve_label_model->getDataByWhere($server_wheres, 'id,label_id')->toArray();
            if ($serves) {
                $data['data']['label_ids'] = $serves;
                foreach ($data['data']['label_ids'] as $k => $v) {
                    $serve = $this->servelabel->getDataByWhere(
                        array(
                            array('column' => 'merchant_id', 'value' => $this->merchant_id, 'operator' => '='),
                            array('column' => 'id', 'value' => $v['label_id'], 'operator' => '='),
                        ), array('id', 'title'), ''
                    )->toArray();
                    $data['data']['label_ids'][$k]['title'] = isset($serve[0]['title']) ? $serve[0]['title'] : '';
                }
            } else {
                $data['data']['label_ids'] = [];
            }
            //商品规格
            $spec_wheres = array(
                array('column' => 'merchant_id', 'value' => $this->merchant_id, 'operator' => '='),
                array('column' => 'goods_id', 'value' => $id, 'operator' => '='),
                array('column' => 'is_delete', 'value' => 1, 'operator' => '=')
            );
            $spec = $this->spec_model->getDataByWhere($spec_wheres, array(
                'id', 'goods_id', 'price', 'stock', 'spec_sn', 'barcode', 'props', 'props_str', 'img', 'csale'
            ));
            $spec = $spec->toArray();
            if ($spec) {
                foreach ($spec as $k => $item) {
                    if (!empty($item['props_str'])) {
                        $spec[$k]['props_str'] = str_replace(';', ':', $item['props_str']);
                    }
                }
                $data['data']['products'] = $spec;
            } else {
                $data['data']['products'] = [];
            }
            //商品不配送区域
            /*
            $region_wheres = array(
                array('column' => 'merchant_id', 'value' => $this->merchant_id, 'operator' => '='),
                array('column' => 'goods_id', 'value' => $id, 'operator' => '=')
            );
            $region = $this->region_exclude_model->getDataByWhere($region_wheres, 'id,region_ids');
            $region = $region->toArray();
            if (!empty($region)) {
                if ($region) {
                    $regions = explode(',', $region[0]['region_ids']);
                    $regions_arr = [];
                    foreach ($regions as $k => $val) {
                        $title = Region::get_title_by_id($val);
                        $regions_arr[$k]['id'] = $val;
                        $regions_arr[$k]['title'] = $title;
                    }
                    $data['data']['regions'] = $regions_arr;

                } else {
                    $data['data']['regions'] = [];
                }
            } else {
                $data['data']['regions'] = [];
            }
            */
            //商品详情组件
            $component_wheres = array(
                array('column' => 'merchant_id', 'value' => $this->merchant_id, 'operator' => '='),
                array('column' => 'goods_id', 'value' => $id, 'operator' => '=')
            );
            $component = $this->component_model->getDataByWhere($component_wheres, array('id', 'content'));
            $component = $component->toArray();
            if (!empty($component)) {
                $component = $component[0];
                $context = $component['content'];
                $data['data']['content'] = json_decode($context);
            } else {
                $data['data']['content'] = [];
            }
            //虚拟商品
            $rs_goods_virtual = GoodsVirtual::get_data_by_id($data['data']['id'],$this->merchant_id);
            $data['data']['time_type'] = isset($rs_goods_virtual['time_type'])?$rs_goods_virtual['time_type']:0;
            $data['data']['start_time'] = isset($rs_goods_virtual['start_time'])?$rs_goods_virtual['start_time']:0;
            $data['data']['end_time'] = isset($rs_goods_virtual['end_time'])?$rs_goods_virtual['end_time']:0;

        } else {
            $data['data'] = [];
        }
        $data['errmsg'] = '';
        $data['errcode'] = 0;
        return Response::json($data);
    }

    /**
     * 更新商品名称，价格，，库存
     */
    public function columnChange()
    {
        $id = isset($this->params['id']) ? trim($this->params['id']) : 0;
        if ($id <= 0) {
            $res['errcode'] = 81701;
            $res['errmsg'] = '参数缺失,商品id不能为空';
            return Response::json($res);
        }

        $column = isset($this->params['column']) ? trim($this->params['column']) : '';
        if (!in_array($column, array('title', 'price', 'stock'))) {
            $res['errcode'] = 81702;
            $res['errmsg'] = '参数错误,字段类型错误';
            return Response::json($res);
        }

        $val = isset($this->params['val']) ? trim($this->params['val']) : '';
        if ($column == 'title') {
            if (empty($val)) {
                $res['errcode'] = 81703;
                $res['errmsg'] = '参数缺失,值不能为空';
                return Response::json($res);
            }
        } else if (in_array($column, array('price', 'stock'))) {
            if (empty($val)) {
                $val = 0;
            }

            if ($column == 'price') {
                $update_data['max_price'] = $val;
            }
        }

        if (($column == 'price' || $column == 'stock') && (!is_numeric($val))) {
            $res['errcode'] = 81704;
            $res['errmsg'] = '参数类型错误';
            return Response::json($res);
        }

//        $wheres = array(
//            array('column'=>'merchant_id','value'=>$this->merchant_id,'operator'=>'='),
//            array('column'=>'id','value'=>$id,'operator'=>'='),
//            array('column'=>'is_delete','value'=>1,'operator'=>'=')
//        );

        $update_data[$column] = $val;
//        if($column == 'stock'){
        $return_res = Goods::update_data($id, $this->merchant_id, $update_data);
//        }else{
//            $return_res = $this->goods_model->updateByWhere($wheres,$update_data);
//        }
        if ($return_res === false) {
            $res['errcode'] = 81705;
            $res['errmsg'] = '操作失败';
        } else {
            $res['errcode'] = 0;
            $res['errmsg'] = '操作成功';
        }
        return Response::json($res);
    }

    /**
     * 商品上下架，单个或者批量
     */
    public function onsale()
    {
        if (!isset($this->params['id']) || ((!is_array($this->params['id'])) && intval($this->params['id']) == 0)) {
            $res['errcode'] = 81601;
            $res['errmsg'] = '参数缺失,商品id不能为空';
            return Response::json($res, 200);
        }
        $id = $this->params['id'];
        $ids = [];
        if (!is_array($id)) {
            $id = intval($id);
            $ids = [$id];
        } else if (is_array($id)) {
            $ids = $id;
        }
        if (empty($ids)) {
            $res['errcode'] = 81602;
            $res['errmsg'] = '参数错误';
            return Response::json($res, 200);
        }

        $type = isset($this->params['type']) ? intval($this->params['type']) : 0;
        if (!in_array($type, array(1, 2))) {
            $res['errcode'] = 81603;
            $res['errmsg'] = '上下架类型参数错误';
            return Response::json($res, 200);
        }

//        $wheres = array(
//            array('column'=>'merchant_id','value'=>$this->merchant_id,'operator'=>'='),
//            array('column'=>'id','value'=>$ids,'operator'=>'in'),
//            array('column'=>'is_delete','value'=>1,'operator'=>'=')
//        );
        if ($type == 1) {
            $version_rs = $this->versionFunction($this->version, count($ids));
            if ($version_rs['errcode'] != 0) {
                return Response::json($version_rs, 200);
            }
            $update_data['onsale'] = 1;
            $update_data['shelve_at'] = date('Y-m-d H:i:s', time());
        } else {
            $update_data['onsale'] = 0;
            $update_data['unshelve_at'] = date('Y-m-d H:i:s', time());
        }
//        $return_res = $this->goods_model->updateByWhere($wheres,$update_data);
        $flag = false;
        foreach ($ids as $id) {
//            Goods::forgetCache($id,$this->merchant_id);
            if ($update_data['onsale'] == 1) {//上架
                //第一版预约商品上架检测
                $goods_res = Goods::get_data_by_id($id, $this->merchant_id);
                if ($goods_res->is_sku == 2) {
                    $goods_appt_res = GoodsAppt::get_data_by_goods_id($id, $this->merchant_id);
                    if (empty($goods_appt_res)) $flag = true;
                }
            }

            $return_res = Goods::update_data($id, $this->merchant_id, $update_data);
            if ($return_res === false) {
                break;
            }
        }
        if ($flag === true) {
            if (count($ids) == 1) {
                return ['errcode' => 81605, 'errmsg' => '商品上架失败，请更新预约商品信息再上架'];
            } else {
                return ['errcode' => 81606, 'errmsg' => '部分商品上架失败，请更新预约商品信息再上架'];
            }
        }
        if ($return_res === false) {
            $res['errcode'] = 81607;
            $res['errmsg'] = '操作失败';
        } else {
            $res['errcode'] = 0;
            $res['errmsg'] = '操作成功';
        }
        return Response::json($res, 200);
    }

    /***
     * 批量设置商品分组
     */
    public function setGoodsGroup()
    {
        if (!isset($this->params['id']) || ((!is_array($this->params['id'])) && intval($this->params['id']) == 0)) {
            $res['errcode'] = 99001;
            $res['errmsg'] = '参数缺失,商品id不能为空';
            return Response::json($res, 200);
        }
        $id = $this->params['id'];
        $ids = [];
        if (!is_array($id)) {
            $id = intval($id);
            $ids = [$id];
        } else if (is_array($id)) {
            $ids = $id;
        }
        if (empty($ids)) {
            $res['errcode'] = 99001;
            $res['errmsg'] = '参数错误';
            return Response::json($res, 200);
        }

        if (!isset($this->params['tag_id']) || ((!is_array($this->params['tag_id'])) && intval($this->params['tag_id']) == 0)) {
            $res['errcode'] = 99001;
            $res['errmsg'] = '参数缺失,分组id不能为空';
            return Response::json($res, 200);
        }
        $tag_id = $this->params['tag_id'];
        //所有分组
        if ($tag_id === 0) {
            $tag_ids = [];
            $tags = GoodsTag::getDataByWhere(array(
                array('column' => 'merchant_id', 'value' => $this->merchant_id, 'operator' => '='),
                array('column' => 'is_delete', 'value' => 1, 'operator' => '=')
            ), array('id', 'title'))->toArray();
            if (!empty($tags)) {
                $tag_ids = array_column($tags, 'id');
            }
        } else {
            $tag_ids = [];
            if (!is_array($tag_id)) {
                $tag_id = intval($tag_id);
                $tag_ids = [$tag_id];
            } else if (is_array($tag_id)) {
                $tag_ids = $tag_id;
            }
        }
        if (empty($tag_ids)) {
            $res['errcode'] = 99001;
            $res['errmsg'] = '商品分组参数错误';
            return Response::json($res, 200);
        }

        $rs = GoodsTagRs::deleteDataByWhere(array(
            array('column' => 'merchant_id', 'value' => $this->merchant_id, 'operator' => '='),
            array('column' => 'goods_id', 'value' => $ids, 'operator' => 'in')
        ));

        $insert_data = [];
        $i = 0;
        foreach ($ids as $k => $val) {
            foreach ($tag_ids as $key => $value) {
                $insert_data[$i]['merchant_id'] = $this->merchant_id;
                $insert_data[$i]['goods_id'] = $val;
                $insert_data[$i]['tag_id'] = $value;
                $insert_data[$i]['created_time'] = date('Y-m-d H:i:s');
                $i++;
            }
        }
        $res = \DB::table('goods_tag_rs')->insert($insert_data);
        foreach ($ids as $k => $val) {
            Goods::forgetCache($val, $this->merchant_id);
        }
        if (!$res) {
            $data['errcode'] = 1;
            $data['errmsg'] = '操作失败';
        } else {
            $data['errcode'] = 0;
            $data['errmsg'] = '操作成功';
        }
        return Response::json($data);

    }

    /***
     * 商品不配送区域批量设置
     */
    public function setArea()
    {
        if (!isset($this->params['id']) || ((!is_array($this->params['id'])) && intval($this->params['id']) == 0)) {
            $res['errcode'] = 99001;
            $res['errmsg'] = '参数缺失,商品id不能为空';
            return Response::json($res, 200);
        }
        $id = $this->params['id'];
        $ids = [];
        if (!is_array($id)) {
            $id = intval($id);
            $ids = [$id];
        } else if (is_array($id)) {
            $ids = $id;
        }
        if (empty($ids)) {
            $res['errcode'] = 99001;
            $res['errmsg'] = '商品id参数错误';
            return Response::json($res, 200);
        }

        if (!isset($this->params['region_id']) || ((!is_array($this->params['region_id'])) && intval($this->params['region_id']) == 0)) {
            $res['errcode'] = 99001;
            $res['errmsg'] = '参数缺失,区域id不能为空';
            return Response::json($res, 200);
        }
        $region_id = $this->params['region_id'];
        $region_ids = [];
        if (!is_array($region_id)) {
            $region_id = intval($region_id);
            $region_ids = [$region_id];
        } else if (is_array($region_id)) {
            $region_ids = $region_id;
        }
        if (empty($region_ids)) {
            $res['errcode'] = 99001;
            $res['errmsg'] = '区域参数错误';
            return Response::json($res, 200);
        }

        $rs = GoodsRegionExclude::deleteDataByWhere(array(
            array('column' => 'merchant_id', 'value' => $this->merchant_id, 'operator' => '='),
            array('column' => 'goods_id', 'value' => $id, 'operator' => 'in')
        ));

        $insert_data = [];
        foreach ($ids as $k => $val) {
            $goodsInfo = Goods::get_data_by_id($val, $this->merchant_id);
            if ($this->isAppointmentGoods($goodsInfo['goods_cat_id'])) {
                continue;
            }
            $insert_data[$k]['merchant_id'] = $this->merchant_id;
            $insert_data[$k]['goods_id'] = $val;
            $insert_data[$k]['region_ids'] = implode(',', $region_ids);
            $insert_data[$k]['created_time'] = date('Y-m-d H:i:s');
        }
        $res = \DB::table('goods_region_exclude')->insert($insert_data);
        if (!$res) {
            $data['errcode'] = 1;
            $data['errmsg'] = '操作失败';
        } else {
            $data['errcode'] = 0;
            $data['errmsg'] = '操作成功';
        }
        return Response::json($data);
    }

    /***
     * 是否参与会员折扣批量设置
     */
    public function isDiscount()
    {
        if (!isset($this->params['id']) || ((!is_array($this->params['id'])) && intval($this->params['id']) == 0)) {
            $res['errcode'] = 99001;
            $res['errmsg'] = '参数缺失,商品id不能为空';
            return Response::json($res, 200);
        }
        $id = $this->params['id'];
        $ids = [];
        if (!is_array($id)) {
            $id = intval($id);
            $ids = [$id];
        } else if (is_array($id)) {
            $ids = $id;
        }
        if (empty($ids)) {
            $res['errcode'] = 99001;
            $res['errmsg'] = '参数错误';
            return Response::json($res, 200);
        }

        $is_discount = isset($this->params['is_discount']) ? intval($this->params['is_discount']) : 0;
        if (!in_array($is_discount, array(1, 2))) {
            $res['errcode'] = 99001;
            $res['errmsg'] = '是否参与会员折扣参数错误';
            return Response::json($res, 200);
        }

//        $wheres = array(
//            array('column'=>'merchant_id','value'=>$this->merchant_id,'operator'=>'='),
//            array('column'=>'id','value'=>$ids,'operator'=>'in'),
//            array('column'=>'is_delete','value'=>1,'operator'=>'=')
//        );
        if ($is_discount == 1) {
            $update_data['is_discount'] = 1;
        } else {
            $update_data['is_discount'] = 0;
        }
        foreach ($ids as $item) {
            $return_res = Goods::update_data($item, $this->merchant_id, $update_data);
//            Goods::forgetCache($item,$this->merchant_id);
            if ($return_res === false) {
                break;
            }
        }
//        $return_res = $this->goods_model->updateByWhere($wheres,$update_data);
        if ($return_res === false) {
            $res['errcode'] = 1;
            $res['errmsg'] = '操作失败';
        } else {
            $res['errcode'] = 0;
            $res['errmsg'] = '操作成功';
        }
        return Response::json($res, 200);
    }

    /**
     * 发布商品
     */
    public function add(GoodsRequest $request)
    {
        $data = $this->params;
        $goods_data = $this->parseGoodsData($this->merchant_id, $data);
        if ($goods_data['errcode'] != 0) {
            return Response::json($goods_data);
        }

        //添加商品主表
        $goods_id = $this->goods_model->insert_data($goods_data['goods_data']);
        if ($goods_id > 0) {
            //属性添加
            if (isset($data['props']) && (!empty($data['props']))) {
                $attrs = $this->parseAddAttr($this->merchant_id, $goods_id, $data['props']);
                $rs = \DB::table('goods_prop')->insert($attrs);
                if (!$rs) {
                    $return_data = array(
                        'errcode' => 1,
                        'errmsg' => '属性添加失败'
                    );
                    return Response::json($return_data);
                }
            }

            $display_type = empty($data['display_type']) ? 1 : $data['display_type'];
            if ($display_type == 1) {
                //商品主图:1图片
                $imgs = $this->parseSimpleData($this->merchant_id, $goods_id, $data['imgs'], 'img');
            } else { 
                //商品主图:2视频
                $imgs = $this->parseSimpleData($this->merchant_id, $goods_id, $data['imgs_videos'], 'img');
            }
            $rs = \DB::table('goods_img')->insert($imgs);

            if (!$rs) {
                $return_data = array(
                    'errcode' => 1,
                    'errmsg' => '图片添加失败'
                );
                return Response::json($return_data);
            }
            //分组添加
            if (isset($data['tags']) && (!empty($data['tags']))) {
                $tags = $this->parseSimpleData($this->merchant_id, $goods_id, $data['tags'], 'tag_id');
                $rs = \DB::table('goods_tag_rs')->insert($tags);
                if (!$rs) {
                    $return_data = array(
                        'errcode' => 1,
                        'errmsg' => '分组添加失败'
                    );
                    return Response::json($return_data);
                }
            }
            //服务标签
            if (isset($data['label_id']) && (!empty($data['label_id']))) {
                $labels = $this->parseSimpleData($this->merchant_id, $goods_id, $data['label_id'], 'label_id');
                $rs = \DB::table('goods_serve_label')->insert($labels);
                if (!$rs) {
                    $return_data = array(
                        'errcode' => 1,
                        'errmsg' => '服务标签添加失败'
                    );
                    return Response::json($return_data);
                }
            }
            //不配送区域
            if (isset($data['region_ids']) && (!empty($data['region_ids']))) {
                $region_ids = implode(',', $data['region_ids']);
                $region_data['merchant_id'] = $this->merchant_id;
                $region_data['goods_id'] = $goods_id;
                $region_data['region_ids'] = $region_ids;
                $region_data['created_time'] = date('Y-m-d H:i:s', time());
                $rs = $this->region_exclude_model->insert_data($region_data);
                if (!$rs) {
                    $return_data = array(
                        'errcode' => 1,
                        'errmsg' => '商品配送区域添加失败'
                    );
                    return Response::json($return_data);
                }
            }
            //商品详情
            if (isset($data['content']) && (!empty($data['content']))) {
                $component_data['merchant_id'] = $this->merchant_id;
                $component_data['goods_id'] = $goods_id;
                $component_data['goods_id'] = $goods_id;
                $component_data['content'] = json_encode($data['content']);
                $rs = GoodsComponent::insert_data($component_data);
                if (!$rs) {
                    $return_data = array(
                        'errcode' => 1,
                        'errmsg' => '商品配送区域添加失败'
                    );
                    return Response::json($return_data);
                }
            }

            //解析商品规格
            if (isset($data['specs_val']) && (!empty($data['specs_val']))) {
                foreach ($data['specs_val'] as $key => $item) {
                    $specs[$key]['merchant_id'] = $this->merchant_id;
                    $specs[$key]['goods_id'] = $goods_id;
                    $specs[$key]['price'] = $item['price'];
                    $specs[$key]['stock'] = $item['stock'];
                    if ($request->get('is_sku') == 1) {//普通多规格
                        $specs[$key]['spec_sn'] = $item['spec_sn'];
                        $specs[$key]['barcode'] = $item['barcode'];
                    }
                    $specs[$key]['props'] = $key;
                    if (!empty($item['props_str'])) {
//                        $specs[$key]['props_str'] = implode(';', explode(':', $item['props_str']));
                        $specs[$key]['props_str'] = $item['props_str'];
                    } else {
                        $specs[$key]['props_str'] = '';
                    }
                    $specs[$key]['img'] = $item['img'];
                    $specs[$key]['is_delete'] = 1;
                }
                $rs = \DB::table('goods_spec')->insert($specs);
                if (!$rs) {
                    $return_data = array(
                        'errcode' => 1,
                        'errmsg' => '商品规格添加失败'
                    );
                    return Response::json($return_data);
                }
            }

            if (isset($data['specs']) && (!empty($data['specs']))) {
                $attrs = $this->parseAddAttr($this->merchant_id, $goods_id, $data['specs']);
                $rs = \DB::table('goods_prop')->insert($attrs);
                if (!$rs) {
                    $return_data = array(
                        'errcode' => 1,
                        'errmsg' => '规格添加失败'
                    );
                    return Response::json($return_data);
                }
            }

            //预约服务
            if ($this->isAppointmentGoods($data['goods_cat_id'])) {
                $update_data = [
                    'merchant_id' => $this->merchant_id,
                    'goods_id' => $goods_id,
                    'leadtime_hr' => $request->get('leadtime_hr'),
                    'leadtime_day' => $request->get('leadtime_day'),
                    'mode' => $request->get('mode'),
                ];
                $rs = GoodsAppt::insert_data($update_data);
                if (empty($rs)) {
                    $return_data = array(
                        'errcode' => 1,
                        'errmsg' => '预约模式/时间限制添加失败'
                    );
                    return Response::json($return_data);
                }
            }
            
            //虚拟商品
            //dd($this->merchant_id);
            if ( $this->getGoodscatCattype($data['goods_cat_id'])==2 ) {
                $data_goodsvirtual = [
                    'merchant_id' => $this->merchant_id,
                    'goods_id' => $goods_id,
                    'time_type' => $request->get('time_type'),
                    'start_time' => date('Y-m-d',strtotime($request->get('start_time'))),
                    'end_time' => date('Y-m-d',strtotime($request->get('end_time'))).' 23:59:59',
                ];
                $rs = GoodsVirtual::insert_data($data_goodsvirtual);
                if (empty($rs)) {
                    $return_data = array(
                        'errcode' => 1,
                        'errmsg' => '虚拟商品添加失败'
                    );
                    return Response::json($return_data);
                }
            }

        } else {
            $return_data = array(
                'errcode' => 1,
                'errmsg' => '发布失败'
            );
            return Response::json($return_data);
        }

        $return_data = array(
            'errcode' => 0,
            'errmsg' => '发布成功'
        );

        //商品发布成功删除商品导入暂存表的本条数据
        if (isset($data['csv_goods_id']) && (!empty($data['csv_goods_id']))) {
            GoodsCsv::delete_data($data['csv_goods_id'], $this->merchant_id);
        }

        return Response::json($return_data);
    }

    /***
     * 商品编辑提交
     */
    public function edit(GoodsRequest $request)
    {
        $data = $this->params;
        $id = $data['id'];
        if (intval($id <= 0)) {
            return Response::json(array('errcode' => 81401, 'errmsg' => '参数错误,商品id不能为空'));
        }
        //修改商品主表
        $goods_data = $this->parseGoodsData($this->merchant_id, $data);
        if ($goods_data['errcode'] != 0) {
            return Response::json($goods_data);
        }

        $res = Goods::update_data($id, $this->merchant_id, $goods_data['goods_data']);
        if ($res === false) {
            $return_data = array(
                'errcode' => 81402,
                'errmsg' => '商品主表修改失败'
            );
            return Response::json($return_data);
        }

        //虚拟商品
        //dd($request->get('start_time'));
        if ( $this->getGoodscatCattype($data['goods_cat_id'])==2 ) {
                $data_goodsvirtual = [
                    'time_type' => $request->get('time_type'),
                    'start_time' => date('Y-m-d',strtotime($request->get('start_time'))),
                    'end_time' => date('Y-m-d',strtotime($request->get('end_time'))).' 23:59:59',
                ];
                $rs = GoodsVirtual::update_data($id, $this->merchant_id, $data_goodsvirtual);
                if (empty($rs)) {
                    $return_data = array(
                        'errcode' => 81403,
                        'errmsg' => '虚拟商品添加失败'
                    );
                    return Response::json($return_data);
                }
            }
        //预约服务
        elseif ($this->isAppointmentGoods($request->get('goods_cat_id'))) {
            $update_data = [
                'leadtime_hr' => $request->get('leadtime_hr'),
                'leadtime_day' => $request->get('leadtime_day'),
                'mode' => $request->get('mode'),
            ];
            if (GoodsAppt::where('goods_id', $id)->where('merchant_id', $this->merchant_id)->count()) {
                $rs = GoodsAppt::update_data($id, $this->merchant_id, $update_data);
            } else {
                $update_data['goods_id'] = $id;
                $update_data['merchant_id'] = $this->merchant_id;
                $rs = GoodsAppt::insert($update_data);
            }
            if (empty($rs)) {
                $return_data = array(
                    'errcode' => 81404,
                    'errmsg' => '预约模式/时间限制更新失败'
                );
                return Response::json($return_data);
            }
        }
        //商品图片添加
        $display_type = empty($data['display_type']) ? 1 : $data['display_type'];
        if ($display_type == 1) {
            $imgs = $data['imgs'];
        } else { //视频----商品图片添加
            $imgs = $data['imgs_videos'];
        }
        if (!empty($imgs)) {
            $rs_img = GoodsImg::deleteDataByWhere(array(
                array('column' => 'merchant_id', 'value' => $this->merchant_id, 'operator' => '='),
                array('column' => 'goods_id', 'value' => $id, 'operator' => '='),
                array('column' => 'goods_display_type', 'value' => $display_type, 'operator' => '='),
            ));
            //获取当前商品的已有的商品图片
            foreach ($imgs as $key => $item) {
                $img_data[$key]['merchant_id'] = $this->merchant_id;
                $img_data[$key]['goods_id'] = $id;
                $img_data[$key]['img'] = $item['url'];
                $img_data[$key]['goods_display_type'] = $display_type;
                $img_data[$key]['created_time'] = date('Y-m-d H:i:s', time());
            }
            $rs = \DB::table('goods_img')->insert($img_data);
            if (!$rs) {
                $return_data = array(
                    'errcode' => 81405,
                    'errmsg' => '图片添加失败'
                );
                return Response::json($return_data);
            }
        } else {
            $return_data = array(
                'errcode' => 81406,
                'errmsg' => '展示图片/视频不能为空'
            );
            return Response::json($return_data);
        }
        GoodsImg::forgetCache($id, $this->merchant_id);

        //分组添加
        $res_tag = GoodsTagRs::deleteDataByWhere(
            array(
                array('column' => 'merchant_id', 'value' => $this->merchant_id, 'operator' => '='),
                array('column' => 'goods_id', 'value' => $id, 'operator' => '=')
            )
        );
        if (isset($data['tags']) && !empty($data['tags'])) {
            $tag_rs = $data['tags'];
            foreach ($tag_rs as $key => $item) {
                $tag_data[$key]['merchant_id'] = $this->merchant_id;
                $tag_data[$key]['goods_id'] = $id;
                $tag_data[$key]['tag_id'] = $item;
                $tag_data[$key]['created_time'] = date('Y-m-d H:i:s', time());
            }
            $rs = \DB::table('goods_tag_rs')->insert($tag_data);
            if (!$rs) {
                $return_data = array(
                    'errcode' => 91407,
                    'errmsg' => '分组添加失败'
                );
                return Response::json($return_data);
            }
        }

        //服务标签
        $rs_label = GoodsServeLabel::deleteDataByWhere(array(
            array('column' => 'merchant_id', 'value' => $this->merchant_id, 'operator' => '='),
            array('column' => 'goods_id', 'value' => $id, 'operator' => '=')
        ));

        if (isset($data['label_id']) && !empty($data['label_id'])) {
            $labels = $data['label_id'];
            foreach ($labels as $key => $item) {
                $label_data[$key]['merchant_id'] = $this->merchant_id;
                $label_data[$key]['goods_id'] = $id;
                $label_data[$key]['label_id'] = $item;
                $label_data[$key]['created_time'] = date('Y-m-d H:i:s', time());
            }
            $rs = \DB::table('goods_serve_label')->insert($label_data);
            if (!$rs) {
                $return_data = array(
                    'errcode' => 81408,
                    'errmsg' => '服务标签添加失败'
                );
                return Response::json($return_data);
            }
        }

        $rs_regionexclude = GoodsRegionExclude::deleteDataByWhere(array(
            array('column' => 'merchant_id', 'value' => $this->merchant_id, 'operator' => '='),
            array('column' => 'goods_id', 'value' => $id, 'operator' => '=')
        ));
        //不配送区域
        if (isset($data['region_ids']) && !empty($data['region_ids'])) {
            $region_update['merchant_id'] = $this->merchant_id;
            $region_update['goods_id'] = $id;
            $region_update['region_ids'] = implode(',', $data['region_ids']);
            $region_update['created_time'] = date('Y-m-d H:i:s', time());
            $rs = GoodsRegionExclude::insert_data($region_update);
            if (!$rs) {
                $return_data = array(
                    'errcode' => 81409,
                    'errmsg' => '商品配送区域添加失败'
                );
                return Response::json($return_data);
            }
        }
        GoodsRegionExclude::forgetCache($id);


        $rs_component = GoodsComponent::deleteDataByWhere(array(
            array('column' => 'merchant_id', 'value' => $this->merchant_id, 'operator' => '='),
            array('column' => 'goods_id', 'value' => $id, 'operator' => '=')
        ));
        //商品详情
        if (isset($data['content']) && !empty($data['content'])) {
            $component_update['merchant_id'] = $this->merchant_id;
            $component_update['goods_id'] = $id;
            $component_update['created_time'] = date('Y-m-d H:i:s', time());
            $component_update['content'] = json_encode($data['content']);
            $rs = GoodsComponent::insert_data($component_update);
            if (!$rs) {
                $return_data = array(
                    'errcode' => 81410,
                    'errmsg' => '商品详情添加失败'
                );
                return Response::json($return_data);
            }
        }


        $rs = GoodsProp::updateByWhere(
            array(
                array('column' => 'merchant_id', 'value' => $this->merchant_id, 'operator' => '='),
                array('column' => 'goods_id', 'value' => $id, 'operator' => '=')
            ),
            array('is_delete' => -1, 'updated_time' => date('Y-m-d H:i:s', time()))
        );
        //商品属性
        if (isset($data['props']) && !empty($data['props']) && is_array($data['props'])) {
            $rs = $this->dealGoodsProp($this->merchant_id, $id, $data['props']);
            if ($rs['errcode'] != 0) {
                return Response::json($rs);
            }
        }


        //商品规格
        if ($data['is_sku'] == 1 || $data['is_sku'] == 2) {
            if (isset($data['specs']) && !empty($data['specs']) && is_array($data['specs'])) {
                $rs = $this->dealGoodsProp($this->merchant_id, $id, $data['specs']);
                if ($rs['errcode'] != 0) {
                    return Response::json($rs);
                }
            }
        }

        GoodsProp::forgetCache($id, $this->merchant_id);

        $rs = GoodsSpec::updateByWhere(array(
            array('column' => 'merchant_id', 'value' => $this->merchant_id, 'operator' => '='),
            array('column' => 'goods_id', 'value' => $id, 'operator' => '='),
            array('column' => 'is_delete', 'value' => 1, 'operator' => '=')
        ), array('is_delete' => -1, 'updated_time' => date('Y-m-d H:i:s', time())));
        //更新商品规格值
        if ($data['is_sku'] == 1 || $data['is_sku'] == 2) {
            if (isset($data['specs_val']) && !empty($data['specs_val'])) {
                $specs = $data['specs_val'];
                foreach ($specs as $key => $item) {
                    $specs_data[$key]['merchant_id'] = $this->merchant_id;
                    $specs_data[$key]['goods_id'] = $id;
                    $specs_data[$key]['price'] = $item['price'];
                    $specs_data[$key]['stock'] = $item['stock'];

                    if ($request->get('is_sku') == 1) {//普通多规格
                        $specs_data[$key]['spec_sn'] = $item['spec_sn'];
                        $specs_data[$key]['barcode'] = $item['barcode'];
                    }

                    $specs_data[$key]['props'] = $key;
                    if (!empty($item['props_str'])) {
//                    $specs_data[$key]['props_str'] = implode(';', explode(':', $item['props_str']));
                        $specs_data[$key]['props_str'] = $item['props_str'];
                    } else {
                        $specs_data[$key]['props_str'] = '';
                    }
                    $specs_data[$key]['img'] = $item['img'];
                    $specs_data[$key]['created_time'] = date('Y-m-d H:i:s', time());
                    $specs_data[$key]['is_delete'] = 1;
                }
                $rs = \DB::table('goods_spec')->insert($specs_data);
                if (!$rs) {
                    $return_data = array(
                        'errcode' => 81411,
                        'errmsg' => '商品规格添加失败'
                    );
                    return Response::json($return_data);
                }
            }
        }
        GoodsSpec::forgetCacheByGoods($id, $this->merchant_id);
        $return_data = array(
            'errcode' => 0,
            'errmsg' => '修改成功'
        );
        return Response::json($return_data);

    }

    private function isAppointmentGoods($goods_cat_id)
    {
        $goods_cat = new GoodsCat();
        $cat = $goods_cat->get_data_by_id($goods_cat_id, 'id,parent_id,title,cat_type');
        if (!empty($cat) && $cat['cat_type'] == 1) {
            return true;
        }
        return false;
    }
    
    /***
     * 商品类目类型：0-普通分类，1-预约分类, 2-虚拟商品  配置config/varconfig.php
     */
    private function getGoodscatCattype($goods_cat_id)
    {
        $goods_cat = new GoodsCat();
        $cat = $goods_cat->get_data_by_id($goods_cat_id, 'id,parent_id,title,cat_type');
        if (!empty($cat) && isset($cat['cat_type']) ) {
            return $cat['cat_type'];
        }
        return false;
    }

    /***
     * 处理商品属性规格数据
     * @param $user_id      商户id
     * @param $goods_id     商品id
     * @param $data         要处理的数据
     * @return array
     */
    private function dealGoodsProp($user_id, $goods_id, $data)
    {
        $temp_data = [];
        foreach ($data as $key => $item) {
            if (!isset($item['prop_id']) || !isset($item['prop_vid']) || !isset($item['prop_value'])) {//没有值
                continue;
            }

            if (!empty($temp_data) && in_array($item['prop_id'] . ':' . $item['prop_vid'], $temp_data)) {//已处理过，防止重复
                continue;
            }
            $temp_data[] = $item['prop_id'] . ':' . $item['prop_vid'];
            $prop_data[$key]['merchant_id'] = $user_id;
            $prop_data[$key]['goods_id'] = $goods_id;
            $prop_data[$key]['prop_id'] = $item['prop_id'];
            $prop_data[$key]['prop_vid'] = $item['prop_vid'];
            $prop_data[$key]['prop_value'] = $item['prop_value'];
            $prop_data[$key]['created_time'] = date('Y-m-d H:i:s', time());
            $prop_data[$key]['is_delete'] = 1;
        }
        if (empty($prop_data)) return ['errcode' => 1, 'errmsg' => '商品属性规格数据值不能为空'];
        $rs = \DB::table('goods_prop')->insert($prop_data);
        if (!$rs) {
            $return_data = array(
                'errcode' => 1,
                'errmsg' => '广义的属性添加失败'
            );
            return $return_data;
        }
        $return_data = array(
            'errcode' => 0,
            'errmsg' => '广义的属性添加成功'
        );
        return $return_data;
    }

    /***
     * 组装商品主表数据
     * @param $data
     * @return array
     */
    private function parseGoodsData($user_id, $data)
    {
        $return_data = [];
        $return_data['merchant_id'] = $user_id;//商户id
        $goods_cat_id = isset($data['goods_cat_id']) ? intval($data['goods_cat_id']) : 0;
        if ($goods_cat_id <= 0) {
            return array('errcode' => 81301, 'errmsg' => '参数错误,商品分类不能为空');
        }
        $return_data['goods_cat_id'] = $goods_cat_id;//商品分类id
        $title = isset($data['title']) ? trim($data['title']) : '';
        if (empty($title)) {
            return array('errcode' => 81302, 'errmsg' => '参数错误,商品标题不能为空');
        }
        
        //虚拟商品验证
        if ($this->getGoodscatCattype($data['goods_cat_id'])==2) {
            $return_data['goods_type'] = 1;

            if (!isset($data['goods_type']) || empty($data['goods_type'])) {
                return array('errcode' => 81303, 'errmsg' => '参数错误,虚拟商品的商品类型不能为空');
            }
            if (!isset($data['time_type'])) {
                return array('errcode' => 81304, 'errmsg' => '参数错误,请选择虚拟商品的虚拟商品使用时间类型');
            }
            if ( $data['time_type']==1 ) {
                if ( !isset($data['start_time']) || empty($data['start_time']) ) {
                    return array('errcode' => 81305, 'errmsg' => '参数错误,虚拟商品有效期的开始日期不能为空');
                }elseif ( !isset($data['end_time']) || empty($data['end_time']) ) {
                    return array('errcode' => 81306, 'errmsg' => '参数错误,虚拟商品有效期的开始日期不能为空');
                }
            }
        }
        //预约验证
        elseif ($this->isAppointmentGoods($data['goods_cat_id'])) {
            //是否有预约商品的权限
            $appointservice_priv = UserPrivService::getUserPrivs();
            if (!in_array('trade_apptmanage', $appointservice_priv)) {
                return ['errcode' => 81307, 'errmsg' => '无操作预约商品权限'];
            }
            if (!isset($data['specs']) || empty($data['specs'])) {
                return array('errcode' => 81308, 'errmsg' => '参数错误,预约商品的规格不能为空');
            }
            if (empty($data['mode'])) {
                return array('errcode' => 81309, 'errmsg' => '参数错误,请选择预约商品模式');
            }
            if (!isset($data['leadtime_hr']) || !isset($data['leadtime_day'])) {
                return array('errcode' => 81310, 'errmsg' => '参数错误,预约时间限制不能为空');
            } else {
                if (intval($data['leadtime_hr']) < 0 || intval($data['leadtime_day']) < 0) {
                    return array('errcode' => 81311, 'errmsg' => '参数错误,预约时间限制不能小于0');
                }
                if (intval($data['leadtime_hr']) > 48) {
                    return array('errcode' => 81312, 'errmsg' => '参数错误,最少需提前不可大于48小时');
                }
                if (intval($data['leadtime_day']) > 30) {
                    return array('errcode' => 81313, 'errmsg' => '参数错误,最多可提前不可大于30天');
                }
            }
            if (isset($data['specs']) && !empty($data['specs'])) {
                $spec_val_arr = array_column($data['specs'], 'prop_value');
                $spec_str = implode('', $spec_val_arr);
                if (strpos($spec_str, ':') === false && strpos($spec_str, '-') === false) {
                    return array('errcode' => 81314, 'errmsg' => '参数错误,预约商品预约时段不能为空');
                }

                if (strpos($spec_str, '周') === false && strpos($spec_str, '假') === false) {
                    return array('errcode' => 81315, 'errmsg' => '参数错误,预约商品预约日期不能为空');
                }
            }
        }

        $goods_id = isset($data['id']) ? intval($data['id']) : 0;
        //编辑
        if ($goods_id > 0) {
            $goods = $this->goods_model->getDataByWhere(array(
                array('column' => 'merchant_id', 'value' => $this->merchant_id, 'operator' => '='),
                array('column' => 'id', 'value' => $goods_id, 'operator' => '=')
            ), array('id', 'onsale'))->toArray();
            if (!empty($goods)) {
                $data['onsale'] = $goods[0]['onsale'];
            } else {
                $data['onsale'] = 0;

            }
            $return_data['updated_time'] = date('Y-m-d H:i:s', time());
            //添加
        } else {
            $data['onsale'] = 0;
            $return_data['created_time'] = date('Y-m-d H:i:s', time());
        }

        if ($data['onsale_type'] == 1 && !isset($data['onsale_time'])) {
            return array('errcode' => 81316, 'errmsg' => '参数错误,定时状态时间必选');
        }

        //立即上架
        $versionFunction = 0;//用来判断定时商品是否需要验证商品数量
        if ($data['onsale_type'] == 0) {
            $data['onsale'] = 1;
            $return_data['shelve_at'] = date('Y-m-d H:i:s', time());
            //放入仓库
        } else if ($data['onsale_type'] == 2) {
            $data['onsale'] = 0;
            $return_data['unshelve_at'] = date('Y-m-d H:i:s', time());
        } else if ($data['onsale_type'] == 1) {
            $t = strtotime($data['onsale_time']);
            $now = time();
            //定时上架时间小于当前时间
            if ($t < $now) {
                $data['onsale'] = 1;
                $return_data['shelve_at'] = date('Y-m-d H:i:s', $now);
                $return_data['onsale_time'] = $data['onsale_time'];
                //定时上架时间大于当前时间
            } else {
                $data['onsale'] = 0;
                $versionFunction = 1;
                $return_data['unshelve_at'] = date('Y-m-d H:i:s', time());
                $return_data['onsale_time'] = $data['onsale_time'];
            }
        }


        if (($data['onsale'] == 1) || ($versionFunction == 1)) {
            $increased = 1;
            if ($goods_id > 0) {
                if (isset($goods) && $goods[0]['onsale'] == 0) {
                    $increased = 1;
                } else {
                    $increased = 0;
                }
            }
            $version_rs = $this->versionFunction($this->version, $increased);
            if ($version_rs['errcode'] != 0) {
                return $version_rs;
            }

        }

        $goods = $this->goods_model->getDataByWhere(array(
            array('column' => 'merchant_id', 'value' => $this->merchant_id, 'operator' => '='),
            array('column' => 'title', 'value' => $title, 'operator' => '='),
            array('column' => 'is_delete', 'value' => 1, 'operator' => '=')
        ), array('id', 'title'))->toArray();
        if (!empty($goods)) {
            if ($goods_id > 0) {
                $goods_ids = array_column($goods, 'id');
                $diff_ids = array_diff($goods_ids, [$goods_id]);
                if (!empty($diff_ids)) {
                    return array('errcode' => 81316, 'errmsg' => '参数错误,已存在同样商品名称的商品');
                }
            } else {
                return array('errcode' => 81317, 'errmsg' => '参数错误,已存在同样商品名称的商品');
            }
        }

        $return_data['title'] = $title;//商品标题
        $imgs = isset($data['imgs']) ? $data['imgs'] : '';
        $imgs_videos = isset($data['imgs_videos']) ? $data['imgs_videos'] : '';
        if (empty($data['imgs']) && empty($imgs_videos)) {
            return array('errcode' => 81318, 'errmsg' => '参数错误,商品图片不能为空');
        }
        //展示类型，1图片，2视频
        $data['display_type'] = empty($data['display_type']) ? 1 : $data['display_type'];
        if ($data['display_type'] == 1) {
            $return_data['img'] = $imgs[0]['url'];//商品图片
        } elseif ($data['display_type'] == 2) {
            $return_data['img'] = $imgs_videos[0]['url'];//商品图片
        }
        $return_data['serve_label'] = $data['serve_label'];//商品图片
        if (isset($data['weight'])) {
            $return_data['weight'] = $data['weight'];
        }
        if (isset($data['volume'])) {
            $return_data['volume'] = $data['volume'];
        }

//        if(isset($data['collect_fields'])){
//            $return_data['goods_data']['collect_fields'] = $data['collect_fields'];
//        }
        //单规格
        if ($data['is_sku'] == 0) {
            $return_data['price'] = $data['price'];
            $return_data['max_price'] = $data['price'];
            $return_data['stock'] = $data['stock'];
            $return_data['is_sku'] = 0;
            //商品货号
            if (isset($data['goods_sn'])) {
                $return_data['goods_sn'] = $data['goods_sn'];
            }
            //商品条形码
            if (isset($data['barcode'])) {
                $return_data['barcode'] = $data['barcode'];
            }
        } else {
            $cat_model = new GoodsCat();
            $cat = $cat_model->get_data_by_id($goods_cat_id);
            if (!empty($cat)) {
                $return_data['is_sku'] = $cat['cat_type'] == 1 ? 2 : 1;
            }
            if (!isset($data['specs']) || empty($data['specs'])) {
                return array('errcode' => 81319, 'errmsg' => '参数错误,多规格商品规格不能为空');
            }
            $stock = 0;

            foreach ($data['specs_val'] as $item) {
                if (!isset($price)) {
                    $price = $item['price'];
                } else {
                    if ($price >= $item['price']) {
                        $price = $item['price'];
                    }
                }

                if (!isset($max_price)) {
                    $max_price = $item['price'];
                } else {
                    if ($max_price <= $item['price']) {
                        $max_price = $item['price'];
                    }
                }

                $stock = $stock + $item['stock'];
            }
            $return_data['price'] = $price;
            $return_data['stock'] = $stock;
            $return_data['max_price'] = $max_price;
        }

        //运费模板id
        if (isset($data['shipment_id'])) {
            $return_data['shipment_id'] = $data['shipment_id'];
        }
        //运费
        if (isset($data['postage'])) {
            $return_data['postage'] = $data['postage'];
        }
        //是否参与折扣
        $return_data['is_discount'] = $data['is_discount'];
        //是否参与送积分
        $return_data['is_givecredit'] = $data['is_givecredit'];
        //开始时间类型
        $return_data['onsale_type'] = $data['onsale_type'];
        //减库存方式
//        $return_data['stock_type'] = $data['stock_type'];
        $return_data['stock_type'] = 1;//商品全部改为拍下减库存
        //每人限购
        if (isset($data['cquota'])) {
            $return_data['cquota'] = $data['cquota'];
        }
        //基础销量
        if (isset($data['base_csale'])) {
            $return_data['base_csale'] = $data['base_csale'];
        }
        //市场原价
        if (isset($data['original_price'])) {
            $return_data['original_price'] = $data['original_price'];
        }
        //是否上下架
        $return_data['onsale'] = $data['onsale'];

        //展示类型，1图片，2视频
        $return_data['display_type'] = empty($data['display_type']) ? 1 : $data['display_type'];

        $return_data['is_delete'] = 1;
        return array('errcode' => 0, 'errmsg' => '', 'goods_data' => $return_data);
    }

    /***
     * 解析商品属性添加数据
     * @param $goods_id
     * @param $params
     */
    private function parseAddAttr($user_id, $goods_id, $attrs)
    {
        $data = array();
        foreach ($attrs as $k => $attr) {
            if ((!isset($attr['prop_id'])) || (!isset($attr['prop_vid'])) || (!isset($attr['prop_value']))
            ) {
                continue;
            }
            if (!empty($data)) {
                foreach ($data as $data_list) {//去重
                    if ($attr['prop_id'] == $data_list['prop_id'] && $attr['prop_vid'] == $data_list['prop_vid']) {
                        continue 2;
                    }
                }
            }
            $data[$k]['merchant_id'] = $user_id;
            $data[$k]['goods_id'] = $goods_id;
            $data[$k]['prop_id'] = $attr['prop_id'];
            $data[$k]['prop_vid'] = $attr['prop_vid'];
            $data[$k]['prop_value'] = $attr['prop_value'];
            $data[$k]['created_time'] = date('Y-m-d H:i:s', time());
            $data[$k]['is_delete'] = 1;
        }
        if (!empty($data)) {
            $data = array_values($data);
        }
        return $data;
    }

    /***
     * 解析商品添加图片数据
     * @param $user_id
     * @param $goods_id
     * @param $params
     */
    private function parseSimpleData($user_id, $goods_id, $params, $column)
    {
        $data = [];
        foreach ($params as $key => $item) {
            $data[$key]['merchant_id'] = $user_id;
            $data[$key]['goods_id'] = $goods_id;
            if ($column == 'img') {
                $data[$key][$column] = $item['url'];
                $display_type = empty($this->params['display_type']) ? 1 : $this->params['display_type'];//展示类型，1图片(默认)，2视频
                $data[$key]['goods_display_type'] = $display_type;
            } else {
                $data[$key][$column] = $item;
            }
            $data[$key]['created_time'] = date('Y-m-d H:i:s', time());
        }
        return $data;
    }

    /***
     * 获取预警库存值
     */
    public function getWarning()
    {
        $data = MerchantSetting::get_data_by_id($this->merchant_id);
        $res['data'] = isset($data['warning_stock']) ? $data['warning_stock'] : 0;
        $res['errcode'] = 0;
        $res['errmsg'] = '';
        return Response::json($res, 200);
    }

    /***
     * 设置预警库存值
     */
    public function setWarning()
    {
        if (!isset($this->params['warming_stock'])) {
            $res_data['errcode'] = 99001;
            $res_data['errmsg'] = '参数缺失,没有库存预警值';
            return Response::json($res_data, 200);
        }
        if (isset($this->params['warming_stock']) && (!is_numeric($this->params['warming_stock']))) {
            $res_data['errcode'] = 1;
            $res_data['errmsg'] = '数据类型错误，库存预警必须是数字型';
            return Response::json($res_data, 200);
        }
        $warning_stock = isset($this->params['warming_stock']) ? intval($this->params['warming_stock']) : 0;
        $update_data['warning_stock'] = $warning_stock;
        $update_data['updated_time'] = date('Y-m-d H:i:s');

        $res = MerchantSetting::update_data($this->merchant_id, $update_data);
        if ($res === false) {
            $res_data['errcode'] = 1;
            $res_data['errmsg'] = '设置库存预警失败';
        } else {
            $res_data['errcode'] = 0;
            $res_data['errmsg'] = '成功设置库存预警';
        }
        return Response::json($res_data, 200);

    }

    /***
     * 版本功能，根据版本限制用户功能
     * $version: 1免费版
     */
    private function versionFunction($version, $num = 1)
    {
        $res['errcode'] = 0;
        $res['errmsg'] = '';
        switch ($version) {
            //免费版
            case 1:
                $MerchantSetting = MerchantSetting::get_data_by_id($this->merchant_id);
                $reward = isset($MerchantSetting) ? $MerchantSetting->reward : 0;//奖励商品上架数量（operate_reward_detail.act_type为1时）
                //已上架商品数量
                $count = $this->goods_model->get_data_count(array(
                    array('column' => 'merchant_id', 'value' => $this->merchant_id, 'operator' => '='),
                    array('column' => 'is_delete', 'value' => 1, 'operator' => '='),
                    array('column' => 'onsale', 'value' => 1, 'operator' => '='),
                    array('column' => 'is_sku', 'value' => 2, 'operator' => '!=')
                ));
                //当用户版本是免费版的时候定时上架商品数量
                $count_ds = $this->goods_model->get_data_count(array(
                    array('column' => 'merchant_id', 'value' => $this->merchant_id, 'operator' => '='),
                    array('column' => 'is_delete', 'value' => 1, 'operator' => '='),
                    array('column' => 'onsale', 'value' => 0, 'operator' => '='),
                    array('column' => 'is_sku', 'value' => 2, 'operator' => '!='),
                    array('column' => 'onsale_type', 'value' => 1, 'operator' => '='),
                    array('column' => 'onsale_time', 'value' => date('Y-m-d H:i:s', time()), 'operator' => '>=')
                ));
                $number = self::ENABLE_ONSALE_GOODS_NUM + $reward;//最大上架商品数量
                $diff = $number - $count - $count_ds;
                $diff = $diff <= 0 ? 0 : $diff;
                $count_sum = $count + $num + $count_ds;

                if ($count_sum > $number) {
                    $res['errcode'] = 81604;
                    $str_ds = $count_ds > 0 ? "和" . $count_ds . "件定时上架商品" : "";
                    $res['errmsg'] = '您当前的版本是免费版，已添加' . $count . '件上架商品' . $str_ds . '<br/>还可以上架' . $diff . '件商品';
                }
                break;
            default:
        }
        return $res;
    }

    /***
     * 查询商品列表
     * @param int $_GET ['merchant_id']  商户id(必选)
     * @param int $_GET ['name']  商品名称(可选)
     * @param int $_GET ['pagesize']  每页显示条数(可选,默认10)
     * @param int $_GET ['page']      显示页数(可选,默认1)
     * @param string $_GET ['csale']  排序条件:销量(可选)
     * @param string $_GET ['price']  排序条件:价格(可选)
     * @param string $_GET ['shelve_at']  排序条件:上架时间(可选)
     * @param string $_GET ['all_nums']  是否显示商品数量 (默认不显示) 可选参数:show为显示
     * @param string $_GET ['new_nums']  是否显示新品数量   (默认不显示)   可选参数:show为显示
     * email renruiqi@dodoca.com
     * 时间  2017-9-13
     */
    public function getMarkingList(Request $request, GoodsService $goodsService)
    {
        $_search = $request->all();
        $_search['merchant_id'] = $this->merchant_id;   //获取当前商铺的id
        $_search['pagesize'] = isset($_search['pagesize']) ? $_search['pagesize'] : 10;

        $hash = $goodsService->admin_goods_list($_search);
        return Response::json($hash);
    }

    /**
     * 出库/入库
     */
    public function putStock(GoodsService $GoodsService)
    {
        //操作类型
        if (!isset($this->params['op']) || (isset($this->params['op']) && !in_array($this->params['op'], array('output', 'putin')))) {
            $res['errcode'] = 82001;
            $res['errmsg'] = '出库/入库 参数错误';
            return Response::json($res);
        }

        //goods_id
        if (!isset($this->params['goods_id']) || empty($this->params['goods_id'])) {
            $res['errcode'] = 82002;
            $res['errmsg'] = '商品id不能为空';
            return Response::json($res);
        }
        //出库/入库
        if (!isset($this->params['specs_val']) || empty($this->params['specs_val'])) {
            $res['errcode'] = 82003;
            $res['errmsg'] = '操作 出库/入库 出错';
            return Response::json($res);
        } else if (!empty($this->params['specs_val'])) {
            //dd($this->params['specs_val']);
            foreach ($this->params['specs_val'] as $key => $val) {
                if ((int)$val['num'] < 0) {
                    $res['errcode'] = 82004;
                    $res['errmsg'] = ' 出库/入库 数量必须大于0';
                    return Response::json($res);
                }
            }
        }

        $err = 0;
        $errmsg = '';
        foreach ($this->params['specs_val'] as $key => $val) {
            $param['merchant_id'] = $this->merchant_id;
            $param['stock_num'] = $val['num'];
            $param['goods_id'] = (int)$this->params['goods_id'];
            $param['goods_spec_id'] = isset($val['spec_id']) && !empty($val['spec_id']) ? (int)$val['spec_id'] : "0";//规格id 没有传0
            if (!$param['stock_num']) {
                continue;
            }
            if ($this->params['op'] == 'putin') {
                $rt = $GoodsService->incStock($param);
            } else {
                $rt = $GoodsService->desStock($param);
            }
            //dd($rt);
            if (!$rt || !isset($rt['errcode']) || $rt['errcode'] != 0) {
                $err = 1;
                $errmsg = $rt['errmsg'];
            }
        }

        if ($err === 1) {
            $res['errcode'] = 82005;
            $res['errmsg'] = $errmsg;
        } else {
            $res['errcode'] = 0;
            $res['errmsg'] = '操作成功';
        }
        return Response::json($res);
    }


}
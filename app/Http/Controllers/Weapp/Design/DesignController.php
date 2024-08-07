<?php

namespace App\Http\Controllers\Weapp\Design;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Models\WeixinInfo;
use App\Models\ShopDesign;
use App\Models\ShopDesignComponent;
use App\Models\DesignTemplate;
use App\Models\DesignTemplatePage;
use App\Models\Merchant;
use App\Models\Goods;
use App\Models\Shop;
use App\Models\NewUserGift;
use App\Facades\Member;
use App\Services\GoodsService;
use App\Services\DesignService;
use App\Services\WeixinService;
use Carbon\Carbon;

class DesignController extends Controller
{   
    public function __construct(GoodsService $goodsService, WeixinService $weixinService, DesignService $designService)
    {
        $this->goodsService  = $goodsService;
        $this->weixinService = $weixinService;
        $this->designService = $designService;
    }	
	/**
     * 小程序首页
     *
     * @param int $merchant_id  商户id
     * @author denghongmei@dodoca.com
     *
     * @return \Illuminate\Http\Response
     */
    public function getBanner(Request $request){
        $param = $request->all();
        $merchant_id = Member::merchant_id();
        $wxinfo_id   = Member::weapp_id();
        if(!$merchant_id){
            return ['errcode' => 99001, 'errmsg' => '商户ID不存在'];
        }

        //statr  wangshiliang@dodoca.com
        $shopDesign = ShopDesign::where('merchant_id', $merchant_id)->where('wxinfo_id', $wxinfo_id)->where('is_delete', 1)->first();
        if($shopDesign){
            $shopComponent = $shopDesign->shopComponents;
            if($shopComponent){
                //处理组件数据
                $components = $this->designService->handleComponent($merchant_id, $shopComponent);
                
                $shop = Shop::where('merchant_id', $merchant_id)->first();

                $data['shop_name']  = $shop && !empty($shop['name']) ? $shop['name'] : '商城名称';
                $data['logo']       = $shop && !empty($shop['logo']) ? $shop['logo'] : '';
                $data['csale_show'] = $shop && !empty($shop['csale_show']) ? $shop['csale_show'] : 0;

                $design_data = [];
                if($components){
                    foreach($components as $k=>$v){
                        $design_data[$v['type']]['id'] = $v['id'];
                        $design_data[$v['type']]['type'] = $v['type'];
                        $content = $v['content'];
                        if(empty($content['banner_img'])){
                            $content['banner_img'] = '2017/10/11/FgEJQNvQ-TeUIIOU5pp6GumMKeqT.png';
                        }
                        $design_data[$v['type']]['content'] = $content;
                        $design_data[$v['type']]['listorder'] = $v['listorder'];
                    }
                }else{
                    $design_data['banner']['content']['banner_img'] = '2017/10/11/FgEJQNvQ-TeUIIOU5pp6GumMKeqT.png';
                }
                $data['design_data'] = $design_data;

                $arr = [
                    'merchant_id' => $merchant_id,
                    'all_nums'    => 'show',
                    'new_nums'    => 'show',
                    'shelve_at'   => 'desc'
                ];
                $goods_data = $this->goodsService->goods_list($arr);
                
                $data['goods_data'] = $goods_data['data'];
                $data['good_count'] = $goods_data['all_nums'];
                $data['goods_new']  = $goods_data['new_nums'];

                return ['errcode' => 0, 'errmsg' => '获取数据成功', 'data' => $data];
            }else{
                return ['errcode' => 110003, 'errmsg' => '暂无数据！'];
            }
        }else{
            return ['errcode' => 110003, 'errmsg' => '暂无数据！'];
        }
    }

    /**
     * 二期装修
     *
     * @param int $merchant_id  商户id
     * @param int $weapp_id    小程序id
     * @param int $shop_design_id    装修页面id或标识
     * @author denghongmei@dodoca.com
     *
     * @return \Illuminate\Http\Response
     */
    public function getFeature(Request $request){
        $merchant_id    = Member::merchant_id();
        $weapp_id       = Member::weapp_id();
        // $merchant_id    = 1;
        // $weapp_id       = 5;
        $appid          = Member::appid();
        $shop_design_id = $request->shop_design_id;
        
        if(!$merchant_id){
            return ['errcode' => 99001, 'errmsg' => '商户ID不存在'];
        }

        if(!$weapp_id){
            return ['errcode' => 99001, 'errmsg' => '小程序ID不存在'];
        }

        if(!$appid){
            return ['errcode' => 99001, 'errmsg' => 'APPID不存在'];
        }

        $exist = $this->weixinService->checkApp($merchant_id, $appid);
        if($exist['errcode'] != 0){
            return ['errcode' => $exist['errcode'], 'errmsg' => $exist['errmsg']];
        }

        if(!$shop_design_id){
            //取首页ID
            $weixin_info = WeixinInfo::get_one('id', $weapp_id);
            
            $shop_design_id = $weixin_info['index_id'];

            //-1表示门店做为首页
            if($shop_design_id === -1){
                $shop_design_id = 'store';
            }
            //未设置首页，取装修第一条数据
            if($shop_design_id === 0){
                $shop_design_id = ShopDesign::where('merchant_id', $merchant_id)
                    ->where('wxinfo_id', $weapp_id)
                    ->where('is_delete', 1)
                    ->orderBy('created_time', 'asc')
                    ->orderBy('id', 'asc')
                    ->value('id');
            }
        }

        $data = [];

        //如果是数字为装修页ID，如果是字符并且长度为20，则为后期兼容的link_tab字段
        if(is_numeric($shop_design_id) || (is_string($shop_design_id) && strrpos($shop_design_id, 'L') == 0 && strlen($shop_design_id) == 20)){
            if(is_numeric($shop_design_id)){
                $shopDesign = ShopDesign::where('id', $shop_design_id)
                    ->where('merchant_id', $merchant_id)
                    ->where('wxinfo_id', $weapp_id)
                    ->where('is_delete', 1)
                    ->first();
            }else{
                $shopDesign = ShopDesign::where('link_tab', $shop_design_id)
                ->where('merchant_id', $merchant_id)
                ->where('wxinfo_id', $weapp_id)
                ->where('is_delete', 1)
                ->first();
            }
            

            if(!$shopDesign){
                return ['errcode' => 99001, 'errmsg' => '装修模板不存在'];
            }

            //改版后的shop_design_id的值有可能为link_tab字段，所以需要重新赋值真实的模板ID，供后续操作使用
            $shop_design_id = $shopDesign->id;

            $shopComponent = $shopDesign->shopComponents;

            //处理组件数据
            $components = $this->designService->handleComponent($merchant_id, $shopComponent);
            //获取底部菜单
            $designNav = $this->designService->getNavigation($merchant_id, $weapp_id, $shop_design_id);
            $data = $shopDesign->toArray();
            unset($data['shop_components']);
            //合并底部菜单至组件中
            if($designNav['errcode'] == 0){
                $components = array_merge($components, [$designNav['data']]);
            }

            //获取客服设置
            $customer = $this->designService->getCustomer($merchant_id, $weapp_id, $shop_design_id);
            //合并客服设置至组件中
            if($customer['errcode'] == 0){
                $components = array_merge($components, [$customer['data']]);
            }

            //获取分享卡片设置
            $sharecard = $this->designService->getShareCard($merchant_id, $weapp_id, $shop_design_id);
            //合并分享卡片至组件中
            if($sharecard['errcode'] == 0){
                $components = array_merge($components, [$sharecard['data']]);
            }

            $data['component'] = $components;
            $data['page_type'] = 'custom';

            //新用户有礼
            $is_gift = NewUserGift::where('merchant_id', $merchant_id)
                ->where('begin_time', '<=', Carbon::now())
                ->where('end_time', '>=', Carbon::now())
                ->where('status', '!=', 2)
                ->where('is_delete', 1)
                ->value('id');

            $data['is_gift'] = $is_gift ? 1: 0;

        }else{
            $defaultDesign = [
                'cart'     =>'购物车',
                'personal' =>'我的',
                'order'    =>'订单',
                'store'    =>'门店'
            ];

            if(isset($defaultDesign[$shop_design_id])){
                //获取底部菜单
                $data['title'] = $defaultDesign[$shop_design_id];
                $data['page_type'] = $shop_design_id;

                //获取底部菜单
                $designNav = $this->designService->getNavigation($merchant_id, $weapp_id, $shop_design_id);
                if($designNav['errcode'] == 0){
                    $data['component'] = [$designNav['data']];
                }

                //获取客服设置
                $customer = $this->designService->getCustomer($merchant_id, $weapp_id, $shop_design_id);
                //合并客服设置至组件中
                if($customer['errcode'] == 0){
                    $data['component']  = array_merge($data['component'] , [$customer['data']]);
                }

                //获取分享卡片设置
                $sharecard = $this->designService->getShareCard($merchant_id, $weapp_id, $shop_design_id);
                //合并分享卡片至组件中
                if($sharecard['errcode'] == 0){
                    $data['component'] = array_merge($data['component'], [$sharecard['data']]);
                }
            }
        }

        return ['errcode' => 0, 'errmsg' => '获取数据成功', 'data' => $data];
    }

    public function getDesignNav(Request $request)
    {
        $merchant_id    = Member::merchant_id();
        $weapp_id       = Member::weapp_id();
        $shop_design_id = $request->input('shop_design_id', 0);
        $data           = [];

        //获取底部导航
        $designNav = $this->designService->getNavigation($merchant_id, $weapp_id, $shop_design_id);
        if($designNav['errcode'] == 0){
            $data['component'] = [$designNav['data']];
        }

        //获取客服设置
        $customer = $this->designService->getCustomer($merchant_id, $weapp_id, $shop_design_id);
        //合并客服设置至组件中
        if($customer['errcode'] == 0){
            $data['component']  = array_merge($data['component'] , [$customer['data']]);
        }

        //获取分享卡片设置
        $sharecard = $this->designService->getShareCard($merchant_id, $weapp_id, $shop_design_id);
        //合并分享卡片至组件中
        if($sharecard['errcode'] == 0){
            $data['component'] = array_merge($data['component'], [$sharecard['data']]);
        }

        return ['errcode' => 0, 'errmsg' => '获取数据成功', 'data' => $data];
    }
	
	/**
     * 扫码预览
     *
     * @param int $merchant_id  商户id
     * @param int $weapp_id     小程序id
     * @param int $shop_design_id  页面id
     * @param int $template_id    系统模板id
     * @author denghongmei@dodoca.com
     *
     * @return \Illuminate\Http\Response
     */
    public function getPreview(Request $request)
    {
        $shop_design_id = $request->shop_design_id;
        $template_id    = $request->template_id;

        $shopDesign = '';
        //公用模板预览 template_id
        if($template_id){
            $json = DesignTemplate::where('id', $template_id)->value('shop_design_ids');
            $shop_design_ids = json_decode($json, true);
            $shop_design_id = $shop_design_ids[0];
            $shopDesign = ShopDesign::find($shop_design_id);
        }

        //单页模板预览 shop_design_id
        if($shop_design_id){
            $shopDesign = ShopDesign::find($shop_design_id);
        }

        if($shopDesign){
            $merchant_id = $shopDesign->merchant_id;
            $weapp_id    = $shopDesign->wxinfo_id;

            $shopComponent = $shopDesign->shopComponents;

            //处理组件数据
            $components = $this->designService->handleComponent($merchant_id, $shopComponent);
            //获取底部菜单
            $designNav = $this->designService->getNavigation($merchant_id, $weapp_id, $shop_design_id);
            $data = $shopDesign->toArray();
            unset($data['shop_components']);
            //合并底部菜单至组件中
            if($designNav['errcode'] == 0){
                $components = array_merge($components, [$designNav['data']]);
            }

            //获取客服设置
            $customer = $this->designService->getCustomer($merchant_id, $weapp_id, $shop_design_id);
            //合并客服设置至组件中
            if($customer['errcode'] == 0){
                $components = array_merge($components, [$customer['data']]);
            }

            //获取分享卡片设置
            $sharecard = $this->designService->getShareCard($merchant_id, $weapp_id, $shop_design_id);
            //合并分享卡片至组件中
            if($sharecard['errcode'] == 0){
                $components = array_merge($components, [$sharecard['data']]);
            }
            
            $data['component'] = $components;
            $data['page_type'] = 'custom';

            //新用户有礼
            $is_gift = NewUserGift::where('merchant_id', $merchant_id)
                ->where('begin_time', '<=', Carbon::now())
                ->where('end_time', '>=', Carbon::now())
                ->where('status', '<>', 2)
                ->where('is_delete', 1)
                ->value('id');
                
            $data['is_gift'] = $is_gift ? 1: 0;

            return ['errcode' => 0, 'errmsg' => '获取数据成功', 'data' => $data];
        }
    }

     /**
     * 装修组件异步加载
     *
     * @param int $merchant_id  商户id
     * @param int $weapp_id    小程序id
     * @param int $shop_design_id    装修页面id或标识
     *
     * @return \Illuminate\Http\Response
     */
    public function getComponents(Request $request){
        $merchant_id    = Member::merchant_id();
        $weapp_id       = Member::weapp_id();
        $appid          = Member::appid();
        $shop_design_id = $request->shop_design_id;
        $offset = isset($request->offset) ?$request->offset : 0;
        $limit = isset($request->limit) ? $request->limit : 10;

        $data = [];
        if(!$merchant_id){
            return ['errcode' => 99001, 'errmsg' => '商户ID不存在'];
        }

        if(!$weapp_id){
            return ['errcode' => 99001, 'errmsg' => '小程序ID不存在'];
        }

        if(!$appid){
            return ['errcode' => 99001, 'errmsg' => 'APPID不存在'];
        }

        $exist = $this->weixinService->checkApp($merchant_id, $appid);
        if($exist['errcode'] != 0){
            return ['errcode' => $exist['errcode'], 'errmsg' => $exist['errmsg']];
        }
        if(!$shop_design_id){
            //取首页ID
            $weixin_info = WeixinInfo::get_one('id', $weapp_id);
            
            $shop_design_id = $weixin_info['index_id'];

            //-1表示门店做为首页
            if($shop_design_id === -1){
                $shop_design_id = 'store';
            }
            //未设置首页，取装修第一条数据
            if($shop_design_id === 0){
                $shop_design_id = ShopDesign::where('merchant_id', $merchant_id)
                    ->where('wxinfo_id', $weapp_id)
                    ->where('is_delete', 1)
                    ->orderBy('created_time', 'asc')
                    ->orderBy('id', 'asc')
                    ->value('id');
            }
        }

        //如果是数字为装修页ID，如果是字符并且长度为20，则为后期兼容的link_tab字段
        if(is_numeric($shop_design_id) || (is_string($shop_design_id) && strrpos($shop_design_id, 'L') == 0 && strlen($shop_design_id) == 20)){
            if(is_numeric($shop_design_id)){
                $shopDesign = ShopDesign::where('id', $shop_design_id)
                    ->where('merchant_id', $merchant_id)
                    ->where('wxinfo_id', $weapp_id)
                    ->where('is_delete', 1)
                    ->first();
            }else{
                $shopDesign = ShopDesign::where('link_tab', $shop_design_id)
                ->where('merchant_id', $merchant_id)
                ->where('wxinfo_id', $weapp_id)
                ->where('is_delete', 1)
                ->first();
            }
            

            if(!$shopDesign){
                return ['errcode' => 99001, 'errmsg' => '装修模板不存在'];
            }

            //改版后的shop_design_id的值有可能为link_tab字段，所以需要重新赋值真实的模板ID，供后续操作使用
            $shop_design_id = $shopDesign->id;
            $query = ShopDesignComponent::where(array('shop_design_id' => $shop_design_id));
            $count = $query->count();
            $shopComponent = $query->orderBy('listorder', 'asc')->skip($offset)->take($limit)->get();
            //处理组件数据
            $components = $this->designService->handleComponent($merchant_id, $shopComponent);
            $data['component'] = $components;
            $data['count'] = $count;
        }
        return ['errcode' => 0, 'errmsg' => '获取数据成功', 'data' => $data];
    }

     /**
     * 二期装修
     *
     * @param int $merchant_id  商户id
     * @param int $weapp_id    小程序id
     * @param int $shop_design_id    装修页面id或标识
     * @author denghongmei@dodoca.com
     *
     * @return \Illuminate\Http\Response
     */
    public function getFeatures(Request $request){
        $merchant_id    = Member::merchant_id();
        $weapp_id       = Member::weapp_id();
        // $merchant_id    = 1;
        // $weapp_id       = 5;
        $appid          = Member::appid();
        $shop_design_id = $request->shop_design_id;
        
        if(!$merchant_id){
            return ['errcode' => 99001, 'errmsg' => '商户ID不存在'];
        }

        if(!$weapp_id){
            return ['errcode' => 99001, 'errmsg' => '小程序ID不存在'];
        }

        if(!$appid){
            return ['errcode' => 99001, 'errmsg' => 'APPID不存在'];
        }

        $exist = $this->weixinService->checkApp($merchant_id, $appid);
        if($exist['errcode'] != 0){
            return ['errcode' => $exist['errcode'], 'errmsg' => $exist['errmsg']];
        }
        if(!$shop_design_id){
            //取首页ID
            $weixin_info = WeixinInfo::get_one('id', $weapp_id);
            
            $shop_design_id = $weixin_info['index_id'];

            //-1表示门店做为首页
            if($shop_design_id === -1){
                $shop_design_id = 'store';
            }
            //未设置首页，取装修第一条数据
            if($shop_design_id === 0){
                $shop_design_id = ShopDesign::where('merchant_id', $merchant_id)
                    ->where('wxinfo_id', $weapp_id)
                    ->where('is_delete', 1)
                    ->orderBy('created_time', 'asc')
                    ->orderBy('id', 'asc')
                    ->value('id');
            }
        }

        $data = [];

        //如果是数字为装修页ID，如果是字符并且长度为20，则为后期兼容的link_tab字段
        if(is_numeric($shop_design_id) || (is_string($shop_design_id) && strrpos($shop_design_id, 'L') == 0 && strlen($shop_design_id) == 20)){
            if(is_numeric($shop_design_id)){
                $shopDesign = ShopDesign::where('id', $shop_design_id)
                    ->where('merchant_id', $merchant_id)
                    ->where('wxinfo_id', $weapp_id)
                    ->where('is_delete', 1)
                    ->first();
            }else{
                $shopDesign = ShopDesign::where('link_tab', $shop_design_id)
                ->where('merchant_id', $merchant_id)
                ->where('wxinfo_id', $weapp_id)
                ->where('is_delete', 1)
                ->first();
            }
            

            if(!$shopDesign){
                return ['errcode' => 99001, 'errmsg' => '装修模板不存在'];
            }
            $data = $shopDesign->toArray();

            //改版后的shop_design_id的值有可能为link_tab字段，所以需要重新赋值真实的模板ID，供后续操作使用
            $shop_design_id = $shopDesign->id;

            //获取底部菜单
            $designNav = $this->designService->getNavigation($merchant_id, $weapp_id, $shop_design_id);
            unset($data['shop_components']);
            //底部菜单
            if($designNav['errcode'] == 0){
                $data['nav']= $designNav['data'];
            }

            //获取客服设置
            $customer = $this->designService->getCustomer($merchant_id, $weapp_id, $shop_design_id);
            //客服设置
            if($customer['errcode'] == 0){
                $data['customer'] = $customer['data'];
            }

            //获取分享卡片设置
            $sharecard = $this->designService->getShareCard($merchant_id, $weapp_id, $shop_design_id);
            //分享卡片
            if($sharecard['errcode'] == 0){
                $data['sharecard'] = $sharecard['data'];
            }

            $data['page_type'] = 'custom';

            //新用户有礼
            $is_gift = NewUserGift::where('merchant_id', $merchant_id)
                ->where('begin_time', '<=', Carbon::now())
                ->where('end_time', '>=', Carbon::now())
                ->where('status', '!=', 2)
                ->where('is_delete', 1)
                ->value('id');

            $data['is_gift'] = $is_gift ? 1: 0;

        }else{
            $defaultDesign = [
                'cart'     =>'购物车',
                'personal' =>'我的',
                'order'    =>'订单',
                'store'    =>'门店'
            ];

            if(isset($defaultDesign[$shop_design_id])){
                //获取底部菜单
                $data['title'] = $defaultDesign[$shop_design_id];
                $data['page_type'] = $shop_design_id;

                //获取底部菜单
                $designNav = $this->designService->getNavigation($merchant_id, $weapp_id, $shop_design_id);
                if($designNav['errcode'] == 0){
                    $data['nav'] = $designNav['data'];
                }

                //获取客服设置
                $customer = $this->designService->getCustomer($merchant_id, $weapp_id, $shop_design_id);
                //合并客服设置
                if($customer['errcode'] == 0){
                    $data['customer']  = $customer['data'];
                }

                //获取分享卡片设置
                $sharecard = $this->designService->getShareCard($merchant_id, $weapp_id, $shop_design_id);
                //合并分享卡片
                if($sharecard['errcode'] == 0){
                    $data['sharecard'] = $sharecard['data'];
                }
            }
        }
        return ['errcode' => 0, 'errmsg' => '获取数据成功', 'data' => $data];
    }

}

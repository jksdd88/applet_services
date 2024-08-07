<?php

namespace App\Services;

/**
 * 装修服务类
 *
 * @package default
 * @author denghongmei@dodoca.com
 **/
use App\Models\ShopDesign;
use App\Models\ShopDesignComponent;
use App\Models\Shop;
use App\Models\ShopDesignNav;
use App\Models\ShopCustomer;
use App\Models\WeixinInfo;
use App\Models\Coupon;
use App\Models\ShareCard;
use App\Models\DesignTemplate;
use App\Models\LiveInfo;
use App\Models\LiveRecord;
use App\Models\LiveChannel;
use App\Services\LiveService;
use DB;
class DesignService
{
	public function __construct(GoodsService $goodsService, ArticleService $articleService, FightgroupService $fightgroupService, BargainService $bargainService,LiveService $liveService){
        $this->goodsService      = $goodsService;
        $this->articleService    = $articleService;
        $this->fightgroupService = $fightgroupService;
        $this->bargainService    = $bargainService;
        $this->liveService       = $liveService;
    }

    /**
     * 处理装修组件
     * @param $merchant_id 商户ID
     * @param $component 组件数组
     * @param $is_default 是否为某些组件设置默认数据
     *
     * @return void
     * @author
     **/
	public function handleComponent($merchant_id, $component, $is_default = 1)
	{
		if(!$merchant_id){
            return ['errcode' => 99001, 'errmsg' => '商户ID不存在'];
        }

        $list = [];

        if($component){
            foreach($component as $k => $v){
                $list[$k]['id']        = isset($v['id']) ? $v['id'] : 0;
                $list[$k]['type']      = $v['type'];
                $list[$k]['module_type'] = isset($v['module_type']) ? $v['module_type'] : '';
                $list[$k]['listorder'] = isset($v['listorder']) ? $v['listorder'] : 0;
                $list[$k]['content']   = is_array($v['content']) ? $v['content'] : json_decode($v['content'], true);

                //自定义商品、横滑商品
                if($v['type'] == 'customGoods' || $v['type'] == 'sliderGoods'){
                    if($list[$k]['content']['goods']){
                        $result = $this->goodsService->getGoodsByIds($merchant_id, $list[$k]['content']['goods']);
                        if(isset($result['data'])){
                            $list[$k]['content']['goods'] = $result['data'];
                        }else{
                            $list[$k]['content']['goods'] = '';
                        }
                    }
                }

                //店铺名称、LOGO、背景图
                if($v['type'] == 'banner'){
                    //设置默认数据
                    if($is_default == 1){
                        if(empty($list[$k]['content']['logo'])){
                            $shop_logo = Shop::where('merchant_id', $merchant_id)->value('logo');
                            $list[$k]['content']['logo'] = $shop_logo;
                        }
                        if(empty($list[$k]['content']['banner_img'])){
                            $list[$k]['content']['banner_img'] = '2017/10/11/FgEJQNvQ-TeUIIOU5pp6GumMKeqT.png';
                        }
                        if(empty($list[$k]['content']['shop_name'])){
                            $shop_name = Shop::where('merchant_id', $merchant_id)->value('name');
                            $list[$k]['content']['shop_name'] = $shop_name;
                        }
                    }
                }

                //秒杀商品
                if($v['type'] == 'seckill'){
                    if($list[$k]['content']['seckills']){
                        $search = ['merchant_id'=>$merchant_id,'seckill_ids'=>$list[$k]['content']['seckills']];
                        $result = $this->goodsService->getGoodsBySeckillIds($search);
                        if(isset($result['data'])){
                            $list[$k]['content']['seckills'] = $result['data']['list'];
                        }else{
                            $list[$k]['content']['seckills'] = '';
                        }
                    }

                }

                //自定义文章、横滑文章
                if($v['type'] == 'customArticle' || $v['type'] == 'sliderArticle'){
                    if($list[$k]['content']['article']){
                        $search = ['merchant_id'=>$merchant_id,'article_ids'=>$list[$k]['content']['article']];
                        $result = $this->articleService->getArticleListByIds($search);
                        if(isset($result['data'])){
                            $list[$k]['content']['article'] = $result['data']['list'];
                        }else{
                            $list[$k]['content']['article'] = '';
                        }
                    }
                }

                //拼团
                if($v['type'] == 'fightGroup'){
                    if($list[$k]['content']['groups']){
                        $result = $this->fightgroupService->fightgroupInfo($merchant_id, $list[$k]['content']['groups']);
                        if(isset($result['data'])){
                            $list[$k]['content']['groups'] = $result['data'];
                        }else{
                            $list[$k]['content']['groups'] = '';
                        }
                    }
                }

                //优惠劵
                if($v['type'] == 'couponGroup'){
                    if($list[$k]['content']['data']){
                        $coupon_ids = $list[$k]['content']['data'];

                        $result = [];

                        if($coupon_ids){
                            foreach($coupon_ids as $coupon_id){
                                $query = Coupon::query();
                                $query->select('id', 'name', 'content_type', 'coupon_val', 'is_condition', 'condition_val');
                                $query->where('id', $coupon_id);
                                $info = $query->first();
                                $result[] = $info;
                            }
                        }

                        $list[$k]['content']['data'] = $result;
                    }
                }

                //砍价
                if($v['type'] == 'bargain'){
                    if($list[$k]['content']['bargain']){
                        $bargain_ids = $list[$k]['content']['bargain'];

                        $bargain = [];

                        if($bargain_ids){
                            foreach($bargain_ids as $bargain_id){
                                $result = $this->bargainService->bargainIdInfo($merchant_id, $bargain_id);
                                if(isset($result['data'])){
                                    $bargain[] = $result['data'];
                                }else{
                                    $bargain[] = '';
                                }
                            }
                        }

                        $list[$k]['content']['bargain'] = $bargain;
                    }
                }

                 //直播，数据格式：{"style":"1","live_live":[1,2],"live_record":[1,2]}
                if($v['type'] == 'live'){
                    //直播数据
                    if($list[$k]['content']['live_live']){
                        $live_live_ids = $list[$k]['content']['live_live'];
                
                        $live_live = [];
                
                        if($live_live_ids){
                            foreach($live_live_ids as $key => $live_live_id){
                                //数据
                                $info = LiveInfo::get_data_by_id($live_live_id, $merchant_id);
                                if($info){
                                    $status = 0;
                                    if($info->status == 2){ //已取消
                                        $status = 6;
                                    }else{
                                        if(strtotime($info->start_time) > time()) { //未开始
                                            $status = 1;
                                        } else if(strtotime($info->end_time) < time()) { //已结束
                                            $status = 2;
                                        } else if($info->status == 1) { //暂停中
                                            $status = 4;          
                                        } else { //直播中
                                            $status = 3;            
                                        }
                                    }
                                    
                                    if($status == 3){
                                        $views_number = $this->liveService->getRealViewsNumber(1, $live_live_id, $merchant_id);
                                    }
                                    $live_live[] = [
                                        'id'           => $live_live_id,                   
                                        'title'        => $info['title'],             //直播名称
                                        'cover_img'    => $info['cover_img'],     //直播封面
                                        'type'    => $info['type'],     //直播类型
                                        'status'       => $status,          //状态
                                        'virtual_sum'  => $info['virtual_sum'],
                                        'views_number' => isset($views_number) ? (int)$views_number : 0    //参与人数
                                    ];
                                }else{
                                    unset($live_live_ids[$key]);
                                }
                            }
                        }
                
                        $list[$k]['content']['live_live'] = $live_live;
                    }
                    
                    //录播数据
                    if($list[$k]['content']['live_record']){
                        $live_record_ids = $list[$k]['content']['live_record'];
                    
                        $live_record = [];
                    
                        if($live_record_ids){
                            foreach($live_record_ids as $key => $live_record_id){
                                //数据
                                $record = LiveRecord::get_one('id', $live_record_id);
                                if($record && $record['merchant_id'] == $merchant_id){
                                    if($record['publish_status'] == 1){
                                        $views_number = $this->liveService->getRealViewsNumber(2, $live_record_id, $merchant_id);
                                    }
                                    $live_record[] = [
                                        'id'           => $live_record_id,                   
                                        'title'        => $record['title'],             //直播名称
                                        'img_url'      => $record['img_url'],     //直播封面
                                        'type'      => $record['type'],     //视频类型：0->电商，1->普通
                                        'virtual_sum'      => $record['virtual_sum'],     //虚拟观看人数
                                        'views_number' => isset($views_number) ? (int)$views_number : 0    //参与人数
                                    ];
                                }else{
                                    unset($live_record_ids[$key]);
                                }
                            }
                        }
                    
                        $list[$k]['content']['live_record'] = $live_record;
                    }
                    
                }
            }
        }
        return $list;
	}
	/**
     * 根据装修页标识获取底部导航数据
     *
     * @param string $merchant_id  商户ID
     * @param string $weapp_id     小程序ID
     * @param string $page_type    装修页标识  0 新建页面  1 商城 2 订单  3 购物车 4 我的 5 门店
     *
     * @return \Illuminate\Http\Response
     */
    static public function getNavigation($merchant_id, $weapp_id, $shop_design_id)
    {
		if(!$merchant_id){
			return ['errcode' => 99001, 'errmsg' => '商户ID不存在'];
		}

		if(!$weapp_id){
			return ['errcode' => 99001, 'errmsg' => '小程序ID不存在'];
		}

		if(!$shop_design_id){
			return ['errcode' => 99001, 'errmsg' => '装修页ID不存在'];
		}

		$designNav = ShopDesignNav::where('merchant_id', $merchant_id)->where('wxinfo_id', $weapp_id)->orderBy('updated_time', 'desc')->first();

        $data = [];
		if($designNav){
			$scope = json_decode($designNav->scope, true);

            if(is_array($scope) && in_array($shop_design_id, $scope)){
                $content = json_decode($designNav->content, true);
                $data['type'] = 'bottomNav';
                if($content['data']){
                    foreach($content['data'] as &$val){
                        $link_design_id = isset($val['nav_link']['id']) ? $val['nav_link']['id'] : 0;

                        if($link_design_id && strrpos($link_design_id, 'L') == 0 && strlen($link_design_id) == 20){
                            $shopDesign = ShopDesign::select('id')
                                ->where('link_tab', $link_design_id)
                                ->where('merchant_id', $merchant_id)
                                ->where('wxinfo_id', $weapp_id)
                                ->where('is_delete', 1)
                                ->first();
                                
                            if($shopDesign){
                                $val['nav_link']['id'] = $shopDesign->id;
                            }
                        }
                    }
                }
                $data['content'] = $content;
            }
		}else{
            $data = self::defaultNav($merchant_id, $weapp_id);
        }

        return ['errcode' => 0, 'data' => $data];
    }

    static public function defaultNav($merchant_id, $weapp_id)
    {
        $shop_design = '';
        //取首页ID
        $weixin_info = WeixinInfo::get_one('id', $weapp_id);

        $shop_design_id = $weixin_info['index_id'];

        if($shop_design_id > 0){
            $shop_design = ShopDesign::where('id', $shop_design_id)->where('is_delete', 1)->first();
        }else{
            //-1表示门店做为首页
            if($shop_design_id === -1){
                $shop_design = 'store';
            }
            //未设置首页，取装修第一条数据
            if($shop_design_id === 0){
                $shop_design = ShopDesign::where('merchant_id', $merchant_id)->where('wxinfo_id', $weapp_id)->where('is_delete', 1)->orderBy('created_time', 'asc')->orderBy('id', 'asc')->first();
                if($shop_design){
                    $shop_design_id = $shop_design['id'];
                }
            }
        }

        if($shop_design){
            $data['type'] = 'bottomNav';
            //默认数据
            $content = [
                'bgcolor' => '#fff',
                'data' => [
                    [
                        'nav_name'        => $shop_design == 'store' ? '门店' : $shop_design['title'],
                        'nav_color'       => '#333',
                        'nav_hover_color' => '#333',
                        'nav_icon'        => '/2017/11/20/FoaIUX9pgG5soBLDdFIv4p7eN3Kw.png',
                        'nav_hover_icon'  => '/2017/11/20/Fl85XlrvHiIFjB_-n11RNrfyOxVh.png',
                        'nav_link' => [
                            'type' => $shop_design == 'store' ? 8 : 9,
                            'name' => $shop_design == 'store' ? '门店' : $shop_design['title'],
                            'id'   => $shop_design_id ? $shop_design_id : 0
                        ]
                    ],
                    [
                        'nav_name'        => '订单',
                        'nav_color'       => '#333',
                        'nav_hover_color' => '#333',
                        'nav_icon'        => '/2017/11/20/FhExodu-wzqtWBFBKVWLqFgkI9gH.png',
                        'nav_hover_icon'  => '/2017/11/20/FgZzwDwXnXwlgGRyA9OHfRFEq0lb.png',
                        'nav_link' => [
                            'type' => 4,
                            'name' => '订单列表页面'
                        ]
                    ],
                    [
                        'nav_name'        => '购物车',
                        'nav_color'       => '#333',
                        'nav_hover_color' => '#333',
                        'nav_icon'        => '/2017/11/20/Fr5cIoYmtIQQuOv1ad9ExS_nyQTq.png',
                        'nav_hover_icon'  => '/2017/11/20/Fmx7ZxzZXVK7c9K5Jb3wdqvMk5Xh.png',
                        'nav_link' => [
                            'type' => 3,
                            'name' => '购物车页面'
                        ]
                    ],
                    [
                        'nav_name'        => '我的',
                        'nav_color'       => '#333',
                        'nav_hover_color' => '#333',
                        'nav_icon'        => '/2017/11/20/FuVRARibPqMPJfzZ52UJrukSTJIg.png',
                        'nav_hover_icon'  => '/2017/11/20/FjW283IfindgyPrZ9Oy-wZhNj6Ig.png',
                        'nav_link' => [
                            'type' => 5,
                            'name' => '会员中心页面'
                        ]
                    ]
                ]
            ];
            $data['content'] = $content;

            return $data;
        }
    }

    /**
     * 删除小程序一并删除小程序里面的装修数据
     *
     * @param string $wxinfo_id     小程序ID
     *
     * @return \Illuminate\Http\Response
     */
    static public function deleteDesign($param)
    {
		$wxinfo_id = isset($param['wxinfo_id']) ? intval($param['wxinfo_id']) : 0;

		$merchant_id = isset($param['merchant_id']) ? intval($param['merchant_id']) : 0;

		if(!$wxinfo_id){
			return ['errcode' => 99001, 'errmsg' => '小程序ID不存在'];
		}
		if(!$merchant_id){
			return ['errcode' => 99001, 'errmsg' => '商户ID不存在'];
		}
		//根据小程序id获取小程序下的装修数据
		$designData = ShopDesign::select('*')->where('merchant_id',$merchant_id)->where('wxinfo_id',$wxinfo_id)->where('is_delete',1)->get();
		if($designData){
	        $designData = $designData->toArray();
	        foreach ($designData as $k => $v) {
	        	//删除页面数据
	            $datas['is_delete'] = -1;
	            $isdelete = ShopDesign::update_data($v['id'],$merchant_id,$datas);
	            if ($isdelete) {
	            	//删除页面下的装修数据
	                $result = ShopDesignComponent::where('shop_design_id',$v['id'])->delete();
	            }
	        }
	        return 	['errcode' => 0, 'errmsg' => '删除成功'];
	    }
	    return 	['errcode' => 0, 'errmsg' => '小程序无装修数据'];
    }
    /**
     * 更新小程序ID
     *
     * @param int $old_wxinfo_id     旧的小程序ID
     * @param int $new_wxinfo_id     新的小程序ID
     *
     * @return \Illuminate\Http\Response
     */
    static public function updateWxinfoid($merchant_id,$old_wxinfo_id,$new_wxinfo_id){
        //事物控制  防止数据异常
        DB::beginTransaction();
        try{
            //更新shop_design表
            $designdata = ShopDesign::update_wxinfo_id($merchant_id,$old_wxinfo_id,$new_wxinfo_id);
            //更新shop_design_nav表
            $navdata =ShopDesignNav::update_wxinfo_id($merchant_id,$old_wxinfo_id,$new_wxinfo_id);
            DB::commit();
            return  ['errcode' => 0, 'errmsg' => '小程序ID更新成功'];
        }catch (\Exception $e) {
            DB::rollBack();
            return  ['errcode' => -1, 'errmsg' => '小程序ID更新失败'];
        }

    }

    /**
     * 根据装修页标识获取客服设置
     *
     * @param string $merchant_id  商户ID
     * @param string $weapp_id     小程序ID
     * @param string $shop_design_id    装修页标识  0 新建页面  1 商城 2 订单  3 购物车 4 我的 5 门店
     *
     * @return \Illuminate\Http\Response
     */
    static public function getCustomer($merchant_id, $weapp_id, $shop_design_id)
    {
		if(!$merchant_id){
			return ['errcode' => 99001, 'errmsg' => '商户ID不存在'];
		}

		if(!$weapp_id){
			return ['errcode' => 99001, 'errmsg' => '小程序ID不存在'];
		}

		if(!$shop_design_id){  
			return ['errcode' => 99001, 'errmsg' => '装修页ID不存在'];
		}

		$customer_data = ShopCustomer::where('merchant_id', $merchant_id)->where('wxinfo_id', $weapp_id)->where('is_onoff',1)->first();

        $data = [];
		if($customer_data){
			$scope = json_decode($customer_data->scope, true);

            if(is_array($scope) && in_array($shop_design_id, $scope)){
            	$data['type'] = 'customerSet';
                $data['content']['icon_id']    = $customer_data['icon'];
                $data['content']['icon_image'] = $customer_data['icon_image'];
            }
		}
		if($data){
        	return ['errcode' => 0, 'data' => $data];
        }else{
        	return ['errcode' => -1];
        }
    }

    /**
     * 根据装修页标识获取分享卡片设置
     *
     * @param string $merchant_id  商户ID
     * @param string $weapp_id     小程序ID
     * @param string $shop_design_id    装修页标识  0 新建页面  1 商城 2 订单  3 购物车 4 我的 5 门店
     *
     * @return \Illuminate\Http\Response
     */
    static public function getShareCard($merchant_id, $weapp_id, $shop_design_id)
    {
        if(!$merchant_id){
            return ['errcode' => 99001, 'errmsg' => '商户ID不存在'];
        }

        if(!$weapp_id){
            return ['errcode' => 99001, 'errmsg' => '小程序ID不存在'];
        }

        if(!$shop_design_id){
            return ['errcode' => 99001, 'errmsg' => '装修页ID不存在'];
        }

        $sharecard_data = ShareCard::get_data($merchant_id, $weapp_id);

        $data = [];
        if($sharecard_data && $sharecard_data['is_whether'] == 1){
            $scope = json_decode($sharecard_data->page_id, true);

            if(is_array($scope) && in_array($shop_design_id, $scope)){
                $data['type'] = 'shareCard';
                $data['content'] = [
                    'desc'          => $sharecard_data->desc,
                    'share_img_url' => $sharecard_data->share_img_url
                ];
            }
        }
        if($data){
            return ['errcode' => 0, 'data' => $data];
        }else{
            return ['errcode' => -1];
        }
    }

 
     /**
     * 新建或修改模板
     *
     * @param string $merchant_id  商户ID
     * @param string $wxinfo_id     小程序ID
     * @param string $id    模板ID
     *
     * @return \Illuminate\Http\Response
     */
    public function getTemplet($merchant_id,$wxinfo_id,$id) {
        if(!$merchant_id){
            return ['errcode' => 99004, 'errmsg' => '商户ID不存在'];
        }

        if(!$wxinfo_id){
            return ['errcode' => 99001, 'errmsg' => '小程序ID不存在'];
        }

        if(!$id){
            return ['errcode' => 99001, 'errmsg' => '模板ID不存在'];
        }
        //查询原来模板的使用人数
        $use_count = DesignTemplate::select('use_count')->where('id',$id)->first();
        $template['use_count'] = $use_count['use_count'] +1;
        //更新使用人数
        DesignTemplate::update_data($id,$template);
        
        //先查询小程序下是否有内容了
        $data = ShopDesign::select('*')->where('merchant_id',$merchant_id)->where('wxinfo_id','=',$wxinfo_id)->where('is_delete',1)->get();
        //print_r($data);exit;
        if($data){//删除小程序下原有的内容
            $data = $data->toArray();
            foreach ($data as $k => $v) {
                $datas['is_delete'] = -1;
                $isdelete = ShopDesign::update_data($v['id'],$merchant_id,$datas);
                if ($isdelete) {
                    $result = ShopDesignComponent::where('shop_design_id',$v['id'])->delete();
                }    
            }
            //清空首页设置
            WeixinInfo::update_data('id', $wxinfo_id, ['index_id' => 0]);
        }
        
        $template_data = DesignTemplate::get_data_by_id($id);
        $design_ids = (!empty($template_data['shop_design_ids'])) ? json_decode($template_data['shop_design_ids'],true):0;
        //页面数据
        $design_data = ShopDesign::select('*')->whereIn('id',$design_ids)->where('is_delete',1)->get();
        if($design_data){
            $design_data = $design_data->toArray();
            foreach ($design_data as $k => $v) {
                if (isset($v['id']) && $v['id']) {
                    $componentlist = ShopDesignComponent::where(array('shop_design_id' => $v['id']))->orderBy('listorder', 'asc')->get();
                    $list = array();
                    if (isset($componentlist) && $componentlist) {
                        foreach ($componentlist as $key => $val) {
                            $list[$key]['content'] = isset($val['content']) && $val['content'] ? json_decode($val['content']) : '';
                            $list[$key]['type'] = $val['type'];
                        }
                    }
                    $design_data[$k]['data'] = $list;
                }
                unset($design_data[$k]['id']);
                unset($design_data[$k]['wxinfo_id']);
                unset($design_data[$k]['merchant_id']);
            }
            $design_data['data'] = $design_data;
        }else{
            return ['errcode'=>-1,'errmsg'=>"模板内无数据"];
        }
        $wxinfodata = $this->postByXcxid($merchant_id,$wxinfo_id, $design_data);        
        return $wxinfodata;
    }

    //根据小程序id编辑整套模板数据
    private function postByXcxid($merchant_id,$wxinfo_id,$design_data) {
        if(!$merchant_id){
            return ['errcode' => 99004, 'errmsg' => '商户ID不存在'];
        }
        if(!$wxinfo_id){
            return ['errcode' => 99008, 'errmsg' => '小程序ID不存在'];
        }
        if($wxinfo_id && $design_data){
            //如果小程序id存在
            DB::beginTransaction();
            try{
                foreach ($design_data['data'] as $k => $v) {
                    $shop_design = array(
                        'merchant_id' => $merchant_id,
                        'title' => isset($v['title']) ? $v['title'] : '',
                        'bg_color' => isset($v['bg_color']) ? $v['bg_color'] : '',
                        'bg_img' => isset($v['bg_img']) ? $v['bg_img'] : '',
                        'bg_transparency' => isset($v['bg_transparency']) ? $v['bg_transparency'] : 0,
                        'interactive' => isset($v['interactive']) ? $v['interactive'] : 0,
                        'wxinfo_id' => $wxinfo_id,
                        'link_tab' => isset($v['link_tab']) ? $v['link_tab'] : '',
                    );
                    $shop_design_id = ShopDesign::insert_data($shop_design);
                    if ($shop_design_id && isset($v['data'])) {
                        $result = $this->addComponent($v['data'], $shop_design_id);
                    }      
                }
                \Log::info("装修2".$wxinfo_id.'--'.json_encode($design_data['data']));
                DB::commit();
                return ['errcode' => 0, 'wxinfo_id' => $wxinfo_id]; 
            }catch (\Exception $e) {
                DB::rollBack();
                return ['errcode' => -1, 'errmsg' => '装修失败'];
            }
        }
        
    }
    //执行ShopDesignComponent表添加
    private function addComponent($componentlist, $id) {
        foreach ($componentlist as $k => $v) {
            $data = array();
            $data['shop_design_id'] = $id;
            $data['type'] = $v['type'];
            $data['content'] = isset($v['content']) ? json_encode($v['content']) : '';
            $data['listorder'] = $k;
            ShopDesignComponent::insert_data($data);
        }
        return true;
    }

} // END class 

<?php

namespace App\Http\Controllers\Admin\Design;


use App\Http\Controllers\Controller;
use App\Models\ShopDesign;
use App\Models\ShopDesignComponent;
use App\Models\WeixinInfo;
use App\Models\Merchant;
use App\Models\DesignTemplate;
use App\Models\DesignTemplatePage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Response;
use Config;
use App\Services\WeixinService;
use \Milon\Barcode\DNS2D;
use App\Services\DesignService;
use DB;
/*
 * 首页装修
 * 
 */
class ShopDesignController extends Controller {

    function __construct(ShopDesign $ShopDesignModel,WeixinService $WeixinService,DesignService $designService) {
        $this->model = $ShopDesignModel;
        $this->WeixinService = $WeixinService;
        $this->designService = $designService;
        $this->merchant_id = isset(Auth::user()->merchant_id) ? Auth::user()->merchant_id :0;
    }
       
    //新增或编辑
    public function putDesign(Request $request){
        $data = $request->all();
        //$this->merchant_id = 1;
        if(!$this->merchant_id){
            return ['errcode' => 99004, 'errmsg' => '商户ID不存在'];
        }
        $shop_design_id = isset($data['shop_design_id']) && $data['shop_design_id'] ? intval($data['shop_design_id']) : 0;
        $wxinfo_id = isset($data['wxinfo_id']) && $data['wxinfo_id'] ? intval($data['wxinfo_id']) : 0;
        if($shop_design_id){
            $result = $this->postByPageid($shop_design_id,$wxinfo_id,$data);//编辑
        }else{
            $result = $this->postData($wxinfo_id,$data);//新建
        }
        return $result;

    }

    /*
     * 新建整套模板装修数据
    */
    /*private function postPage($data) {
        $merchant_id = $this->merchant_id;
        if(!$merchant_id){
            return ['errcode' => 99004, 'errmsg' => '商户ID不存在'];
        }
        $wxinfo = $this->WeixinService->createApp($merchant_id);
        if(isset($wxinfo['id']) && !empty($wxinfo['id']) ){
            $wxinfo_id = $wxinfo['id'];
        }else{
            return $wxinfo;
        }
        if($data['data']){
            //事物控制  防止数据异常
            DB::beginTransaction();
            try{  
                foreach ($data['data'] as $k => $v) {
                    $title = isset($v['title']) ? $v['title'] : '';
                    $bg_color = isset($v['bg_color']) ? $v['bg_color'] : '';
                    $bg_img = isset($v['bg_img']) ? $v['bg_img'] : '';
                    $bg_transparency = isset($v['bg_transparency']) ? $v['bg_transparency'] : 0;
                    $link_tab = isset($v['link_tab']) ? $v['link_tab'] : 0;
                    $interactive = isset($v['interactive']) ? $v['interactive'] : 0;
                    //$wxinfo_id = 9;
                    $shop_design = array(
                    'merchant_id' => $merchant_id,
                    'title' => $title,
                    'wxinfo_id' => $wxinfo_id,
                    'bg_color' => $bg_color,
                    'bg_img' => $bg_img,
                    'bg_transparency' => $bg_transparency,
                    'link_tab' => $link_tab,
                    'interactive' => $interactive
                     );
                    $shop_design_id = ShopDesign::insert_data($shop_design);
                    if ($shop_design_id && isset($v['data'])) {
                        $result = $this->addComponent($v['data'], $shop_design_id);
                    } 
                }
                //\Log::info("装修".json_encode($data['data']));
                DB::commit();
            }catch (\Exception $e) {
                DB::rollBack();
                return ['errcode' => -1, 'errmsg' => '装修失败'];
            }
        }
        return ['errcode' => 0, 'wxinfo_id' => $wxinfo_id];
    }


    //根据小程序id编辑数据
    private function postByXcxid($wxinfo_id,$design_data) {
        $merchant_id = $this->merchant_id;
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
                        'wxinfo_id' => $wxinfo_id,
                        'link_tab' => isset($v['link_tab']) ? $v['link_tab'] : '',
                        'interactive' => isset($v['interactive']) ? $v['interactive'] : 0,
                    );
                    if(isset($v['id'])&&$v['id']){//编辑原有数据  
                        $shopDesign_data = ShopDesign::update_data($v['id'],$merchant_id,$shop_design);
                        if ($shopDesign_data && isset($v['data'])) {
                            //删除信息
                            ShopDesignComponent::where('shop_design_id', '=', $v['id'])->delete();
                            //有数据就添加信息
                            if($v['data']){
                                $result = $this->addComponent($v['data'], $v['id']);
                            }
                            
                        }
                    }else{//新增
                        $shop_design_id = ShopDesign::insert_data($shop_design);
                        if ($shop_design_id && isset($v['data'])) {
                            $result = $this->addComponent($v['data'], $shop_design_id);
                        }
                    }   
                }
                //\Log::info("装修2".$wxinfo_id.'--'.json_encode($design_data['data']));
                DB::commit();
                return ['errcode' => 0, 'wxinfo_id' => $wxinfo_id]; 
            }catch (\Exception $e) {
                DB::rollBack();
                return ['errcode' => -1, 'errmsg' => '装修失败'];
            }
        }
        
    }*/
    /*
     * 新建页面及装修数据
    */
    private function postData($wxinfo_id,$data) {
        $merchant_id = $this->merchant_id;
        if(!$merchant_id){
            return ['errcode' => 99004, 'errmsg' => '商户ID不存在'];
        }
        if(!$wxinfo_id){
            return ['errcode' => 99008, 'errmsg' => '小程序ID不存在'];
        }
        if($data){
            //事物控制  防止数据异常
            DB::beginTransaction();
            try{  
                $title = isset($data['title']) ? $data['title'] : '';
                $bg_color = isset($data['bg_color']) ? $data['bg_color'] : '';
                $bg_img = isset($data['bg_img']) ? $data['bg_img'] : '';
                $bg_transparency = isset($data['bg_transparency']) ? $data['bg_transparency'] : 0;
                $interactive = isset($data['interactive']) ? $data['interactive'] : 0;
                $shop_design = array(
                'merchant_id' => $merchant_id,
                'title' => $title,
                'wxinfo_id' => $wxinfo_id,
                'bg_color' => $bg_color,
                'bg_img' => $bg_img,
                'bg_transparency' => $bg_transparency,
                'link_tab' => self::get_link_tab($wxinfo_id),
                'interactive' => $interactive
                 );
                $shop_design_id = ShopDesign::insert_data($shop_design);
                if ($shop_design_id && $data['data']) {
                    $result = $this->addComponent($data['data'], $shop_design_id);
                } 
                DB::commit();
            }catch (\Exception $e) {
                DB::rollBack();
                return ['errcode' => -1, 'errmsg' => '装修失败'];
            }
        }
        return ['errcode' => 0, 'errmsg' => '装修成功'];
    }

    //根据页面id编辑页面及组件数据
    private function postByPageid($shop_design_id,$wxinfo_id,$data) {
        $merchant_id = $this->merchant_id;
        if(!$merchant_id){
            return ['errcode' => 99004, 'errmsg' => '商户ID不存在'];
        }
        if(!$shop_design_id){
            return ['errcode' => -1, 'errmsg' => '页面ID不能为空'];
        }
        if(!$wxinfo_id){
            return ['errcode' => 99008, 'errmsg' => '小程序ID不存在'];
        }
        if($data){
            $title = isset($data['title']) ? $data['title'] : '';
            $bg_color = isset($data['bg_color']) ? $data['bg_color'] : '';
            $bg_img = isset($data['bg_img']) ? $data['bg_img'] : '';
            $bg_transparency = isset($data['bg_transparency']) ? $data['bg_transparency'] : 0;
            $interactive = isset($data['interactive']) ? $data['interactive'] : 0;
            $shop_design = array(
                'merchant_id' => $merchant_id,
                'title' => $title,
                'wxinfo_id' => $wxinfo_id,
                'bg_color' => $bg_color,
                'bg_img' => $bg_img,
                'bg_transparency' => $bg_transparency,
                'interactive' => $interactive
             );
            $result = ShopDesign::update_data($shop_design_id,$merchant_id,$shop_design);
            if($result){
                //删除信息
                ShopDesignComponent::where('shop_design_id', '=', $shop_design_id)->delete();
                //有数据就添加信息
                $this->addComponent($data['data'], $shop_design_id);
                return ['errcode' => 0, 'errmsg' => '保存成功'];
            }
            return ['errcode' => -1, 'errmsg' => '保存失败'];
        }else{
            return ['errcode' => -1, 'errmsg' => '网络异常，请稍后再试'];
        } 
    }

    //根据页面id读取组件数据
    public function getByPageid(Request $request) {
        $params = $request->all();
        $merchant_id = $this->merchant_id;
        if(!$merchant_id){
            return ['errcode' => 99004, 'errmsg' => '商户ID不存在'];
        }
        $shop_design_id = isset($params['shop_design_id']) ? $params['shop_design_id'] : 0;
        if(!$shop_design_id){
            return ['errcode' => 99004, 'errmsg' => '装修页ID不存在'];
        }
        $componentlist = ShopDesignComponent::select('shop_design_id','type','content','listorder')->where(array('shop_design_id' => $shop_design_id))->orderBy('listorder', 'asc')->get();
        $list = $this->designService->handleComponent($merchant_id, $componentlist,2);
        return ['errcode'=>0,'data'=>$list];
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

    //获取增长人数 200秒增长一个人 每天增长432人
    private function getNum(){
        $current_time = time();
        $start_time = strtotime('2018-02-06 20:00:00');
        return floor(($current_time - $start_time) / 200) ;
    }


    /**
     *整套页面模板列表
     * @return array
     */
    public function templetList(Request $request)
    {
        $params = $request->all();
        $merchant_id = $this->merchant_id;
        if(!$merchant_id){
            return ['errcode' => 99004, 'errmsg' => '商户ID不存在'];
        }
        $offset = isset($params['offset']) ? $params['offset'] : 0;
        $limit = isset($params['limit']) ? $params['limit'] : 10;
        $catid = (isset($params['catid']) && (!empty($params['catid']))) ? $params['catid'] : 0;   
        $query = DesignTemplate::select('*');
        if(!empty($catid)) {
            $query->whereRaw("FIND_IN_SET(".$catid.",group_id)");
        }

        $count = $query->count();
        $data  = $query->skip($offset)->take($limit)->orderBy('sort', 'asc')->get();
        if($data){
            $data = $data->toArray();
            foreach ($data as $k => $v) {
                // $num = $this->getNum();
                // $num<0 ? 0 : $num;
                // $data[$k]['use_count'] = $v['use_count'] +$num;
                $data[$k]['use_count'] = $v['use_count'] +$v['edit_num'];
            }
        }
        //行业分类
        $industry = Config::get('industrycat') ? Config::get('industrycat') : '';
        $version_id = $this->getversion($merchant_id);
        return [
            'errcode' => 0,
            '_count' => $count,
            'data' => $data,
            'version' => $version_id,
            'industry' => $industry
        ];
    }


    /**
     * 单页模板列表
     */
    public function templetPageList(Request $request)
    {
        $params = $request->all();
        $merchant_id = $this->merchant_id;
        if(!$merchant_id){
            return ['errcode' => 99004, 'errmsg' => '商户ID不存在'];
        }
        $offset = isset($params['offset']) ? $params['offset'] : 0;
        $limit = isset($params['limit']) ? $params['limit'] : 10;
        $catid = (isset($params['catid']) && (!empty($params['catid']))) ? $params['catid'] : 0;   
        $query = DesignTemplatePage::select('*');
        if(!empty($catid)) {
            $query->whereRaw("FIND_IN_SET(".$catid.",group_id)");
        }

        $count = $query->count();
        $data  = $query->skip($offset)->take($limit)->orderBy('sort', 'asc')->get();
        if($data){
            $data = $data->toArray();
            foreach ($data as $k => $v) {
                $data[$k]['use_count'] = $v['use_count'] +$v['edit_num'];
            }
        }
        //行业分类
        $industry = Config::get('industrycat') ? Config::get('industrycat') : '';
        return [
            'errcode' => 0,
            '_count' => $count,
            'data' => $data,
            'industry' => $industry
        ];
    }


    /*
     * 读取整套模板数据并新建
     */
    public function getTemplet(Request $request) {
        $merchant_id = $this->merchant_id;
        if(!$merchant_id){
            return ['errcode' => 99004, 'errmsg' => '商户ID不存在'];
        }
        $param = $request->all();
        $wxinfo_id = isset($param['wxinfo_id']) && $param['wxinfo_id'] ? intval($param['wxinfo_id']) : 0;
        if($merchant_id == 14761){
            $wxinfo = $this->WeixinService->createApp($merchant_id);
            if(isset($wxinfo['id']) && !empty($wxinfo['id']) ){
                $wxinfo_id = $wxinfo['id'];
            }else{
                return $wxinfo;
            }
        }
        if(!$wxinfo_id){
            return ['errcode' => 99008, 'errmsg' => '小程序id不存在'];
        }
        $id = isset($param['id']) ? intval($param['id']) : 0;
        if(!$id){
            return ['errcode' => 99001, 'errmsg' => '必传参数为空'];
        }
        $result = $this->designService->getTemplet($merchant_id,$wxinfo_id,$id);
        return $result;
    }


    /*
     * 读取单页模板数据
     */
    public function getTempletPage(Request $request) {
        $param = $request->all();
        $id = $param['id'];
        if(!$id){
             return ['errcode'=>99001,'errmsg'=>"模板页面id不能为空"];
        }

        //查询原来模板的使用人数 并+1
        $use_count = DesignTemplatePage::select('use_count')->where('id',$id)->first();
        $template['use_count'] = $use_count['use_count'] +1;
        //更新使用人数
        DesignTemplatePage::update_data($id,$template);
        //查修单页装修数据
        $design_id = DesignTemplatePage::get_templatepage_id($id, 'shop_design_id');
        if(!$design_id){
             return ['errcode'=>-1,'errmsg'=>"数据为空"];
        }
        $data = ShopDesign::select('*')->where('id',$design_id['shop_design_id'])->where('is_delete',1)->first();
        //print_r($data);exit;
        if($data){
            $data = $data->toArray();
            $componentlist = ShopDesignComponent::where(array('shop_design_id' => $data['id']))->orderBy('listorder', 'asc')->get();
            $list = array();
            if (isset($componentlist) && $componentlist) {
                foreach ($componentlist as $key => $val) {
                    //清空单页模板里的链接数据
                    $content = isset($val['content']) && $val['content'] ? json_decode($val['content'],true) : '';
                    if($val['type'] == 'carousel' || $val['type'] == 'images'){
                        if($content['data']){
                            foreach($content['data'] as &$v){
                               $v['img_link']['type']='';
                               $v['img_link']['name']='';
                               $v['img_link']['id']='';
                               if(isset($v['img_link']['link_tab'])){
                                    $v['img_link']['link_tab']='';
                               }
                            }
                        }
                    }
                    if($val['type'] == 'middleNav'){
                        if($content['data']){
                            foreach($content['data'] as &$v){
                               $v['nav_link']['type']='';
                               $v['nav_link']['name']='';
                               $v['nav_link']['id']='';
                               if(isset($v['nav_link']['link_tab'])){
                                    $v['nav_link']['link_tab']='';
                               }
                            }
                        }
                    }
                    if($val['type'] == 'title'){
                        if($content){
                            $content['right_link']['type']='';
                            $v['right_link']['name']='';
                            $v['right_link']['id']='';
                           if(isset($v['img_link']['link_tab'])){
                                $v['img_link']['link_tab']='';
                           }
                        }
                    }
                    if($val['type'] == 'information'){
                        if($content['tags']){
                            foreach($content['tags'] as &$v){
                               $v['nav_link']['type']='';
                               $v['nav_link']['name']='';
                               $v['nav_link']['id']='';
                               if(isset($v['nav_link']['link_tab'])){
                                    $v['nav_link']['link_tab']='';
                               }
                            }
                        }
                    }
                    $list[$key]['content'] = $content;
                    $list[$key]['type'] = $val['type'];
                }
            }
            $data['data'] = $list;
            return ['errcode'=>0,'data'=>$data];
        }else{
            return ['errcode'=>-1,'errmsg'=>"模板页面不存在"];
        }
        
    }
    
    //删除页面
    public function deletepage($id) {
        $merchant_id = $this->merchant_id;
        if(!$merchant_id){
            return ['errcode' => 99004, 'errmsg' => '商户ID不存在'];
        }
        if(!$id){
            return ['errcode' => 99001, 'errmsg' => '页面ID不能为空'];
        }
        $isindex = WeixinInfo::select('id')->where('merchant_id',$merchant_id)->where('index_id',$id)->first();
        if($isindex['id']){
             return ['errcode' => -1, 'errmsg' => '首页不能删除'];
        }
        $data['is_delete'] = -1;
        $isdelete = ShopDesign::update_data($id,$this->merchant_id,$data);
        if ($isdelete) {
            ShopDesignComponent::where('shop_design_id', '=', $id)->delete();
            return ['errcode' => 0, 'errmsg' => '删除成功'];
          
        }else{
            return ['errcode' => -1, 'errmsg' => '删除失败'];
        }      
    }


    //根据小程序id获取装修页面及第一页装修数据
    public function getDesignByXcxid($id) {
        $wxinfo_id = (isset($id) && !empty($id)) ? intval($id) : 0 ;
        if(!$wxinfo_id){
            return ['errcode' => 99008, 'errmsg' => '小程序ID不存在'];
        }
        $merchant_id = $this->merchant_id;
        if(!$merchant_id){
            return ['errcode' => 99004, 'errmsg' => '商户ID不存在'];
        }
        $result=[];
        //商户版本信息
        $version_id = $this->getversion($merchant_id);
        $data = ShopDesign::select('*')->where('merchant_id',$merchant_id)->where('wxinfo_id',$wxinfo_id)->where('is_delete',1)->get();
        //print_r($data);exit;
        if(count($data)>0){
            $data = $data->toArray();
            if (isset($data[0]['id']) && $data[0]['id']) {
                $componentlist = ShopDesignComponent::select('shop_design_id','type','content','listorder')->where(array('shop_design_id' => $data[0]['id']))->orderBy('listorder', 'asc')->get();
                $list = $this->designService->handleComponent($merchant_id, $componentlist,2);
                $result['compent'] = $list;
            } 
        }
        $result['list'] = $data;
        return ['errcode'=>0,'data'=>$result,'version'=>$version_id];
    }


    //生成二维码
    public function template_qrcode(Request $request){
        $params = $request->all();
        $merchant_id = $this->merchant_id;
        //模板id
        $template_id = isset($params['template_id']) ? intval($params['template_id']):0;
        //页面id
        $shop_design_id = isset($params['shop_design_id']) ? intval($params['shop_design_id']):0;
        if($template_id){
            //模板列表页二维码
            $url = ENV('APP_URL').'/wap/page?mode='.$template_id; 
        }else{//内页预览二维码
            $url = ENV('APP_URL').'/wap/page?id='.$shop_design_id.'&merchant_id='.$merchant_id;
        }
        $qrcode = 'data:image/png;base64,'.DNS2D::getBarcodePNG($url, "QRCODE","10","10");
       
        return ['errcode'=>0,'data'=>$qrcode];
    }

    //默认链接组件数据
    private $linkData = [
                            ["type"=>"1","title"=>"商品"],
                            ["type"=>"2","title"=>"商品分组"],
                            ["type"=>"3","title"=>"购物车"],
                            ["type"=>"4","title"=>"订单列表"],
                            ["type"=>"5","title"=>"会员中心"],
                            ["type"=>"6","title"=>"领取会员卡"],
                            ["type"=>"7","title"=>"优惠买单"],
                            ["type"=>"8","title"=>"门店页面"],
                            ["type"=>"10","title"=>"文章"],
                            ["type"=>"11","title"=>"文章分组"]
                        ];


    //链接组件
    public function link_url(Request $request){
        $param = $request->all();
        $merchant_id = $this->merchant_id;
        if(!$merchant_id){
            return ['errcode' => 99004, 'errmsg' => '商户ID不存在'];
        }
        $linkdata  = $this->linkData;
        if(isset($param['wxinfo_id']) && $param['wxinfo_id']){
            $wxinfo_id = intval($param['wxinfo_id']);
            if($wxinfo_id){
                $data = ShopDesign::select('id','title')->where('merchant_id',$merchant_id)->where('wxinfo_id',$wxinfo_id)->where('is_delete',1)->get();
                if($data){
                    $data = $data -> toArray();
                    foreach ($data as $key => $value) {
                        $data[$key]['type'] = 9;
                    }
                }
                $linkdata = array_merge($linkdata,$data);
            }
        }
        return ['errcode'=>0,'data'=>$linkdata];
    }


    //设为首页
    public function setIndex(Request $request){
        $params = $request->all();
        $merchant_id =  $this->merchant_id;
        if(!$merchant_id){
            return ['errcode' => 99004, 'errmsg' => '商户ID不存在'];
        }
        // 小程序id
        $wxinfo_id = isset($params['wxinfo_id']) ? intval($params['wxinfo_id']):0;
        if(!$wxinfo_id){
            return ['errcode' => 99008, 'errmsg' => '小程序ID不能为空'];
        }
        // 页面id
        $id = isset($params['id']) ? intval($params['id']):0;
        if(!$id){
            return ['errcode' => 99001, 'errmsg' => '页面ID不能为空'];
        }
        $result = WeixinInfo::update_data('id', $wxinfo_id, ['index_id' => $id]);
        if($result){
            return ['errcode' => 0, 'errmsg' => '设置成功'];
        }else{
            return ['errcode' => -1, 'errmsg' => '设置失败'];
        }
    }

    //读取首页设置
    public function getIndex(Request $request){
        $params = $request->all();
        $merchant_id =  $this->merchant_id;
        if(!$merchant_id){
            return ['errcode' => 99004, 'errmsg' => '商户ID不存在'];
        }
        // 小程序id
        $wxinfo_id = isset($params['wxinfo_id']) ? intval($params['wxinfo_id']):0;
        if(!$wxinfo_id){
            return ['errcode' => 99008, 'errmsg' => '小程序ID不能为空'];
        }
        $result = WeixinInfo::select('weixin_info.index_id','shop_design.title')->where('weixin_info.merchant_id',$merchant_id)->where('weixin_info.id',$wxinfo_id)->leftJoin("shop_design","shop_design.id","=","weixin_info.index_id")->first();
        $result['title'] = ($result['index_id'] == -1) ? "门店":$result['title'];
        return ['errcode' => 0, 'data' => $result];

    }

    private function getversion($merchant_id){
        if(!$merchant_id){
            return ['errcode' => 99004, 'errmsg' => '商户ID不存在'];
        }
        $merchantinfo = Merchant::get_data_by_id($merchant_id);
        if($merchantinfo){
            $version_id = $merchantinfo['version_id'];
            return $version_id;
        }else{
            return ['errcode' => -1, 'errmsg' => '数据异常'];
        }
    }

    //复制
    public function getDesignCopy(Request $request) {
        $params = $request->all();
        $merchant_id = Auth::user()->merchant_id;
        if(!$merchant_id){
            return ['errcode' => 99004, 'errmsg' => '商户ID不存在'];
        }
        // 小程序id
        $wxinfo_id = isset($params['wxinfo_id']) ? intval($params['wxinfo_id']):0;
        $id = isset($params['id']) ? intval($params['id']):0;
        $designinfo = ShopDesign::select('*')->where('merchant_id',$merchant_id)->where('wxinfo_id',$wxinfo_id)->where('id',$id)->where('is_delete',1)->first();
        if($designinfo){
            $designinfo = $designinfo->toArray();
            $data = array();
            $data['merchant_id'] = $merchant_id;
            $data['wxinfo_id'] = $wxinfo_id;
            $data['title'] = $designinfo['title'];
            $data['bg_color'] = $designinfo['bg_color'];
            $data['bg_img'] = $designinfo['bg_img'];
            $data['bg_transparency'] = $designinfo['bg_transparency'];
            $data['link_tab'] = self::get_link_tab($wxinfo_id);
            $designid = ShopDesign::insert_data($data);
            if ($designid) {
                $list = ShopDesignComponent::where(array('shop_design_id' => $id))->orderBy('listorder', 'asc')->get();
                foreach ($list as &$v) {
                    $adddata = array();
                    $adddata['shop_design_id'] = $designid;
                    $adddata['type'] = $v['type'];
                    $adddata['content'] = $v['content'];
                    $adddata['listorder'] = $v['listorder'];
                    ShopDesignComponent::insert_data($adddata);
                }
            }
            return ['errcode' => 0, 'errmsg' => '复制成功','data' => $designid];
        }else{
            return ['errcode' => -1, 'errmsg' => '复制失败'];
        }         
        
    }
    /**
     * 获取链接标识
     * prefix 前缀
     */
    public static function get_link_tab($wxinfo_id,$prefix='L') {
        $link_tab = $prefix.date('YmdHis').str_pad(mt_rand(1,99999),5,'0',STR_PAD_LEFT);
        $link = ShopDesign::select('link_tab')->where(array('link_tab' => $link_tab,'wxinfo_id' => $wxinfo_id))->first();
        if(!$link) {
            return $link_tab;
        }
        return self::get_link_tab($prefix);
    }

    /**
     * 获取装修默认模板id
     */
    public static function getDfaultTemplateId() {

        $template_type = config('config.template_type');
        return ['errcode' => 0, 'errmsg' => '复制成功','data' => $template_type];
    }
 
}

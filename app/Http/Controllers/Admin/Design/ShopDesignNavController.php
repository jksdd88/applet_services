<?php

namespace App\Http\Controllers\Admin\Design;


use App\Http\Controllers\Controller;
use App\Models\ShopDesign;
use App\Models\ShopDesignNav;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Response;
use Config;
use App\Services\WeixinService;
use \Milon\Barcode\DNS2D;
use App\Services\GoodsService;
use App\Services\ArticleService;
use DB;
/*
 * 底部导航装修
 * 
 */
class ShopDesignNavController extends Controller {

    function __construct() {
        $this->merchant_id = isset(Auth::user()->merchant_id) ? Auth::user()->merchant_id :0;
    }

    private $_page = array(
        'cart'=>'购物车',
        'personal'=>'我的',
        'order'=>'订单',
        'store'=>'门店'
        );
    //查询商户下的底部导航
    public function getNav($wxinfo_id){
        $merchant_id =  $this->merchant_id;
        //$merchant_id = 1;
        if(!$merchant_id){
            return ['errcode' => 99004, 'errmsg' => '商户ID不存在'];
        }
        // 小程序id
        $wxinfo_id = isset($wxinfo_id) ? intval($wxinfo_id):0;
        if(!$wxinfo_id){
            return ['errcode' => 99008, 'errmsg' => '小程序ID不存在'];
        }
        $data = [];
        $data = ShopDesignNav::where('wxinfo_id',$wxinfo_id)->where('merchant_id',$merchant_id)->orderBy('updated_time', 'desc')->first();
        if($data){
            $data = $data->toArray();
            if($data['scope']){
                $data['scope'] = json_decode($data['scope'],true);
            }

            $data['content'] = isset($data['content'])?json_decode($data['content']):'';

        }
        //获取页面数据
        $data['scope_list'] = $this->pageList($wxinfo_id);
        //追加固定页面作用域
        foreach($this->_page as $k=>$v){
            $data['scope_list'][]=['id'=>$k,'title'=>$v];
        }
        $data['scope_list'] = $data['scope_list']->toArray();
        //获取作用域选中状态
        foreach($data['scope_list'] as $key=>$val){
            $hasActive=false;
            if(isset($data['scope']) && is_array($data['scope'])){
                foreach($data['scope'] as $va){
                    if($va==$val['id']){
                        $hasActive=true;
                    }
                }
            }
            $data['scope_list'][$key]['status']=$hasActive;
            
        }
        return ['errcode' => 0, 'data' => $data];
    }

    //修改底部导航信息 
    public function editNav(Request $request){
        $params = $request->all();
        $merchant_id =  $this->merchant_id;
        if(!$merchant_id){
            return ['errcode' => 99004, 'errmsg' => '商户ID不存在'];
        }
        // 小程序id
        $wxinfo_id = isset($params['wxinfo_id']) ? intval($params['wxinfo_id']):0;
        //装修数据
        $content = isset($params['content']) ? json_encode($params['content']):0;
        //作用页面
        $scope = isset($params['scope']) ? json_encode($params['scope']):0;
        //导航id
        $id = isset($params['id']) ? $params['id']:0;
        if($id){
            //组织数据
            $design_nav = array(
                        'content' => $content,
                        'scope' => $scope
                    );
            //更新小程序底部导航信息
            $result = ShopDesignNav::update_data($id,$merchant_id,$wxinfo_id,$design_nav);
        }else{
            //查询小程序是否已经存在底部导航
            $exist = ShopDesignNav::select('id')->where(['merchant_id'=>$merchant_id,'wxinfo_id'=>$wxinfo_id])->orderBy('updated_time', 'desc')->first();
            if($exist){
                //组织数据
                $design_nav = array(
                            'content' => $content,
                            'scope' => $scope
                        );
                //更新小程序底部导航信息
                $result = ShopDesignNav::update_data($exist->id,$merchant_id,$wxinfo_id,$design_nav);
                
            }else{
                //组织数据
                $design_nav = array(
                            'content' => $content,
                            'scope' => $scope,
                            'merchant_id'=>$this->merchant_id,
                            'wxinfo_id'=>$wxinfo_id
                        );
                //写入小程序底部导航信息
                $result = ShopDesignNav::insert_data($design_nav);
            }
            
        }
        if($result){
            return ['errcode' => 0, 'errmsg' => '编辑成功'];
        }else{
            return ['errcode' => -1, 'errmsg' => '编辑失败'];
        }

    }
    //根据小程序id获取装修页面
    private function pageList($wxinfo_id){
        $merchant_id =  $this->merchant_id;
        if(!$merchant_id){
            return ['errcode' => 99004, 'errmsg' => '商户ID不存在'];
        }
        if(!$wxinfo_id){
            return ['errcode' => 99008, 'errmsg' => '小程序ID不存在'];
        }
        $data = ShopDesign::select('id','title')->where('wxinfo_id',$wxinfo_id)->where('merchant_id',$merchant_id)->where('is_delete',1)->get();
        return $data;
    }

 
}

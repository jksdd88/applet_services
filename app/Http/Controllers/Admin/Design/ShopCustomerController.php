<?php

namespace App\Http\Controllers\Admin\Design;


use App\Http\Controllers\Controller;
use App\Models\ShopDesign;
use App\Models\ShopCustomer;
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
 * 小程序-客服设置
 * 
 */
class ShopCustomerController extends Controller {

    function __construct() {
        $this->merchant_id = isset(Auth::user()->merchant_id) ? Auth::user()->merchant_id :0;
        // $this->merchant_id = 2;
    }

    private $_page = array(
        'cart'=>'购物车',
        'personal'=>'我的',
        'order'=>'订单',
        'store'=>'门店',
        'goods'=>'商品详情页'
        );
    //查询商户下的底部导航
    public function getCustomer($wxinfo_id){
        $merchant_id =  $this->merchant_id;
        if(!$merchant_id){
            return ['errcode' => 99004, 'errmsg' => '商户ID不存在'];
        }
        // 小程序id
        $wxinfo_id = isset($wxinfo_id) ? intval($wxinfo_id):0;
        if(!$wxinfo_id){
            return ['errcode' => 99008, 'errmsg' => '小程序ID不存在'];
        }
        $data = [];
        $scope =[];
        $data = ShopCustomer::select('*')->where('wxinfo_id',$wxinfo_id)->where('merchant_id',$merchant_id)->first();
        if($data){
            $data = $data->toArray();
            if($data['scope']){
                $data['scope'] = $scope = json_decode($data['scope'],true);
            }
            $data['icon_list'] = Config::get('config.customer_icon');


        }
        //获取页面数据
        $data['scope_list'] = $this->pageList($wxinfo_id);
        //追加固定页面作用域
        foreach($this->_page as $k=>$v){
            $data['scope_list'][]=['id'=>$k,'title'=>$v];
        }
        //获取作用域选中状态
        foreach($data['scope_list'] as $key=>$val){
            $hasActive=false;
            if(is_array($scope)){
                foreach($scope as $va){
                    if($va==$val['id']){
                        $hasActive=true;
                    }
                }
            }
            $data['scope_list'][$key]['status']=$hasActive;
        }
        return ['errcode' => 0, 'data' => $data];
    }

    //修改客服设置信息 
    public function editCustomer(Request $request){
        $params = $request->all();
        $merchant_id =  $this->merchant_id;
        if(!$merchant_id){
            return ['errcode' => 99004, 'errmsg' => '商户ID不存在'];
        }
        // 小程序id
        $wxinfo_id  = isset($params['wxinfo_id']) ? intval($params['wxinfo_id']) : 0;
        //图标数据
        $icon       = isset($params['icon']) ? intval($params['icon']) : 0;
        //上传图标图片
        $icon_image = isset($params['icon_image']) ? $params['icon_image'] : '';
        //是否开启客服
        $is_onoff   = isset($params['is_onoff']) ? intval($params['is_onoff']) : 0;
        //作用页面
        $scope      = isset($params['scope']) ? json_encode($params['scope']) : 0;
        //导航id
        $id         = isset($params['id']) ? $params['id']:0;
        if($id){
            //组织数据
            $customer_data = array(
                'icon'       => $icon,
                'icon_image' => $icon_image,
                'scope'      => $scope,
                'is_onoff'   => $is_onoff
            );
            //更新小程序客服设置信息
            $result = ShopCustomer::update_data($id,$merchant_id,$wxinfo_id,$customer_data);
        }else{
            $exist = ShopCustomer::select('id')->where(['merchant_id'=>$merchant_id,'wxinfo_id'=>$wxinfo_id])->first();
            if($exist){
                //组织数据
                $customer_data = array(
                    'icon'       => $icon,
                    'icon_image' => $icon_image,
                    'scope'      => $scope,
                    'is_onoff'   => $is_onoff
                );
                //更新小程序客服设置信息
                $result = ShopCustomer::update_data($exist->id,$merchant_id,$wxinfo_id,$customer_data);
            }else{
                //组织数据
                $customer_data = array(
                    'icon'        => $icon,
                    'icon_image'  => $icon_image,
                    'is_onoff'    => $is_onoff,
                    'scope'       => $scope,
                    'merchant_id' => $this->merchant_id,
                    'wxinfo_id'   => $wxinfo_id
                );
                //写入小程序客服设置信息
                $result = ShopCustomer::insert_data($customer_data);
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
        $data = ShopDesign::select('id','title')->where('wxinfo_id',$wxinfo_id)->where('merchant_id',$merchant_id)->where('is_delete',1)->get()->toArray();
        return $data;
    }

 
}

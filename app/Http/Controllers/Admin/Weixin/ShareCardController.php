<?php
/**
 * Created by PhpStorm.
 * User: zhangyu1@dodoca.com
 * Date: 2018/4/4
 * Time: 17:07
 */

namespace App\Http\Controllers\Admin\Weixin;

use App\Http\Controllers\Controller;
use App\Models\ShareCard;
use App\Models\Merchant;
use App\Models\ShopDesign;
use App\Models\WeixinInfo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Config;
use Illuminate\Support\Facades\Auth;


class ShareCardController extends Controller
{

    public function __construct()
    {
        if (app()->isLocal()) {

            $this->merchant_id =1; //Member::merchant_id();

        } else {

            $this->merchant_id = Auth::user()->merchant_id;
        }
    }

    private $_page = array(
        'goods'=>'商品详情页'
    );

    /**
     * 商家分享卡片详情
     *
     */
    public function getCardDetail($wxinfo_id){

        $merchant_id =  $this->merchant_id;

        if(!$merchant_id){

            return ['errcode' => 99004, 'errmsg' => '商户ID不存在'];
        }

        // 小程序id
        $wxinfo_id = isset($wxinfo_id) ? intval($wxinfo_id):0;

        if(!$wxinfo_id){

            return ['errcode' => 99008, 'errmsg' => '小程序ID不存在'];
        }

        $scope =[];

        $data = [];

        $data = ShareCard::select('*')->where('wxinfo_id',$wxinfo_id)->where('merchant_id',$merchant_id)->where('is_delete',1)->first();

        if($data){

            $data = $data->toArray();

            if($data['page_id']){

                $data['page_id'] = $data['scope'] = $scope = json_decode($data['page_id'],true);

            }
        }

        //可分享页面
        $all_pages_created = ShopDesign::select('id','title')->where(array('merchant_id'=>$merchant_id,'wxinfo_id'=>$wxinfo_id,'is_delete'=>1))->get()->toArray();

        $data['scope_list'] = $all_pages_created;

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

        return Response::json(array('errcode'=>0,'errmsg'=>'查询成功','data'=>$data));

    }


    /*
     * 修改分享设置信息
     *
     * Author:zhangyu1@dodoca.com
     *
     */
    public function editCardDetail(Request $request){

        $params = $request->all();

        $merchant_id =  $this->merchant_id;

        if(!$merchant_id){

            return ['errcode' => 99004, 'errmsg' => '商户ID不存在'];
        }
        // 小程序id
        $wxinfo_id = isset($params['wxinfo_id']) ? intval($params['wxinfo_id']):0;

        //是否开启分享
        $is_whether = isset($params['is_whether']) ? intval($params['is_whether']):0;

        //作用页面
        $scope = isset($params['scope']) ? json_encode($params['scope']) :0;

        //分享描述
        $desc = isset($params['desc']) ? $params['desc'] : '';

        //分享图片
        $share_img_url = isset($params['url']) && $params['url'] ? $params['url'] : '';

        if(empty($share_img_url)){

            return Response::json(array('errcode' => 10001, 'errmsg' => '分享图片不可为空'));
        }

        //导航id
        $id = isset($params['id']) ? $params['id']:0;

        if($id){
            //组织数据
            $share_data = array(

                'desc' => $desc,

                'page_id' => $scope,

                'is_whether' => $is_whether,

                'share_img_url' => $share_img_url
            );

            //更新小程序分享卡片设置信息
            $result = ShareCard::update_data($merchant_id,$wxinfo_id,$share_data);

        }else{

            $exist = ShareCard::select('id')->where(['merchant_id'=>$merchant_id,'wxinfo_id'=>$wxinfo_id,'is_delete'=>1])->first();

            if($exist){
                //组织数据
                $share_data = array(

                    'desc' => $desc,

                    'page_id' => $scope,

                    'is_whether' => $is_whether,

                    'share_img_url' => $share_img_url
                );
                //更新小程序分享卡片设置信息
                $result = ShareCard::update_data($merchant_id,$wxinfo_id,$share_data);

            }else{
                //组织数据
                $share_data = array(
                    'desc' => $desc,

                    'page_id' => $scope,

                    'is_whether' => $is_whether,

                    'share_img_url' => $share_img_url,

                    'merchant_id'=>$this->merchant_id,

                    'wxinfo_id'=>$wxinfo_id
                );
                //写入小程序分享卡片设置信息
                $result = ShareCard::insert_data($share_data);
            }
        }

        if($result){

            return Response::json(array('errcode' => 0, 'errmsg' => '编辑成功'));

        }else{

            return Response::json(array('errcode' => 10001, 'errmsg' => '编辑失败'));
        }

    }

}
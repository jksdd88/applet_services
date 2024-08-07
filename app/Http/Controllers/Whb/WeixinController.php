<?php

/**
 * Created by PhpStorm.
 * User: qinyuan
 * Date: 2017/11/1
 * Time: 9:49
 */
namespace App\Http\Controllers\Whb;

use App\Facades\Member;
use App\Models\WeixinInfo;
use App\Services\AuthService;
use App\Services\WeixinService;
use App\Services\MerchantService;
use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Response;

class WeixinController extends Controller
{
    private $request;
    protected $params;//参数

    public function __construct(Request $request){
        $this->request = $request;
        $this->params = $request->all();
    }

    public function officialAccount(){
        $merchant_id = trim($this->params['merchant_id']);
        $refresh     = $this->request->get('refresh',0);

        if(!$merchant_id){
            $rt['errcode']=99001;
            $rt['errmsg']='商户ID不存在';
            return Response::json($rt);
        }
        if(isset($refresh) && $refresh == 1){
            $info = (new WeixinService())->updateAppInfo($merchant_id);
        }else{
            $info = (new WeixinService())->officialAccount($merchant_id);
        }
        if($info['errcode'] != 0){
            return Response::json([ 'errcode' => 1, 'errmsg' => '该公众账号不存在']);
        }
        return Response::json([ 'errcode' => 0, 'errmsg' => '公众账号信息获取成功', 'data' => $info['data'] ]);
    }
    
     //小程序
    public function appletList(){        
        $merchant_id = trim($this->params['merchant_id']);
        $name = trim($this->params['name']);
        $page = trim($this->params['page']);
        $WeixinInfo =(new WeixinService())->appletlist($merchant_id,$name,$page,9);
        return Response::json($WeixinInfo);
    }
    //解除绑定
    public function deleteBinding() {
        $merchant_id = trim($this->params['merchant_id']);
        $weixinService = new WeixinService();
        $WeixinInfo=$weixinService->officialDelete($merchant_id,0);
        $weixincount =   WeixinInfo::count_app_data($merchant_id);
        $data=array('is_delete'=>$WeixinInfo,'is_check'=>['last'=>($weixincount == 0 ? 1 : 0)]);
        return Response::json($data);
    }
    // 清除主体
    public function opendel(){
        $merchant_id = trim($this->params['merchant_id']);
        $response =  (new WeixinService())->delOpen($merchant_id);
        return Response::json($response);
    }
    //获取版本（根据商户id）
    public function getVersion() {
        $merchant_id = trim($this->params['merchant_id']);
        $WeixinInfo=MerchantService::getMerchantVersion($merchant_id);
        return Response::json($WeixinInfo);
    }
}
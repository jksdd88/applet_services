<?php
/**
 * Created by PhpStorm.
 * User: wangshiliang@dodoca.com
 * Date: 2018/8/1
 * Time: 9:52
 */

namespace App\Http\Controllers\Admin\Express;

use App\Http\Controllers\Controller;
use App\Models\ExpressShop;
use App\Models\ExpressMerchant;
use App\Models\Shop;
use App\Models\Store;
use App\Services\ExpressService;
use App\Services\UserPrivService;
use App\Utils\Dada\DadaCharge;
use App\Utils\Dada\DadaMerchant;
use App\Utils\Dada\DadaShop;
use App\Utils\Dada\DadaBasis;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Config;


class IndexController extends Controller {

    private $request;
    private $merchant_id;
    private $app_key;
    private $app_secret;
    private $merchant_dada;
    private $env;

    public function __construct(Request $request) {
        $this->request = $request;
        $this->app_key  = Config::get('express.app_key');
        $this->app_secret = Config::get('express.app_secret');
        $this->merchant_id =  Auth::user()->merchant_id;
        $if_auth = UserPrivService::getHexiaoPriv(Auth::user()->id,Auth::user()->merchant_id,Auth::user()->is_admin,'setting_appoint_dada');
        if( !isset($if_auth['errcode']) || $if_auth['errcode']!='has_priv' ){
            return ['errcode'=>111302,'errmsg'=>'您没有骑手专送权限'];
        }
        $expressService = new ExpressService();
        $this->merchant_dada = $expressService->getDadaMerchant($this->merchant_id);
        $this->env =  $expressService->getEnv($this->merchant_id);

    }

    //测试
    public function index(){
        return Response::json(['errcode'=>0, 'errmsg'=>'成功']);
    }


    //业务类型
    public function typeList(){
        return Response::json(['errcode'=>0, 'errmsg'=>'成功', 'data'=>Config::get('express.type_list')]);
    }


    //达达支持城市列表
    public function getCity(){
        $response = (new ExpressService())->getCity($this->merchant_id);
        return Response::json(['errcode'=>0, 'errmsg'=>'成功', 'data'=>$response]);
    }

    //取消订单原因列表
    public function getCancel(){
        $response = (new ExpressService())->getCancel($this->merchant_id);
        return Response::json(['errcode'=>0, 'errmsg'=>'成功', 'data'=>$response]);
    }

    //投诉达达原因列表
    public function getComplaint(){
        $response = (new ExpressService())->getComplaint($this->merchant_id);
        return Response::json(['errcode'=>0, 'errmsg'=>'成功', 'data'=>$response]);
    }

    //可用骑手列表
    public function getTransporter(){
        $id = $this->request->get('id',0);
        if(empty($id)){
            return Response::json(['errcode'=>1, 'errmsg'=>'参数有误']);
        }
        $info = ExpressShop::get_one('id',$id);
        if( !isset($info['id']) || $info['merchant_id'] != $this->merchant_id){
            return Response::json(['errcode'=>1, 'errmsg'=>'参数有误']);
        }
        $dada = (new DadaBasis()) ->setConfig($this->app_key,$this->app_secret,$this->merchant_dada,$this->env);
        $response = $dada->transporterList($info['shop_id']);
        return Response::json(['errcode'=>0, 'errmsg'=>'成功', 'data'=>$response]);
    }

    //查看商户信息
    public function getMerchant(){
        $info = ExpressMerchant::get_one('merchant_id',$this->merchant_id);
        if(isset($info['id'])){
            $data = ['status'=>$info['status'],'mobile'=>$info['mobile'],  'city_name' => $info['city_name'],'enterprise'=>$info['enterprise'],'address'=>$info['address'],'contact'=>$info['contact'],'email'=>$info['email']];
            return Response::json(['errcode'=>0, 'errmsg'=>'成功', 'data'=>$data ]);
        }
        return Response::json(['errcode'=>0, 'errmsg'=>'未授权', 'data'=>['status'=>-1] ]);
    }

    //商户开关
    public function switchMerchant(){
        $status = $this->request->input('status');
        if($status != 0 && $status != $status){
            return Response::json(['errcode'=>1, 'errmsg'=>'参数有误' ]);
        }
        $info = ExpressMerchant::get_one('merchant_id',$this->merchant_id);
        if(!isset($info['id'])){
            return Response::json(['errcode'=>2, 'errmsg'=>'请先注册' ]);
        }
        ExpressMerchant::update_data('id',$info['id'],['status'=>$status]);
        return Response::json(['errcode'=>0, 'errmsg'=>'操作成功' ]);
    }

    //注册商户
    public function register(){
        $info = ExpressMerchant::get_one('merchant_id',$this->merchant_id);
        if(isset($info['id']) && $info['status'] == 1){
           return Response::json(['errcode'=>0, 'errmsg'=>'已注册' ]);
        }
        $param = $this->request->input();
        $validator = Validator::make($param,[
            'mobile' => ['required','regex:/^1[34578][0-9]{9}$/'],
            'city_name'=>['required','string','max:128'],
            'enterprise'=>['required','string','max:256'],
            'address'=>['required','string','max:256'],
            'contact'=>['required','string','max:64'],
            'email'=>['required','email','max:128'],
        ],[
            'mobile.required'=>'请填写手机号',
            'mobile.regex'=>'请填写正确格式的手机号',
            'city_name.required'=>'请填写城市名称',
            'city_name.string'=>'请填写字符形式城市名',
            'city_name.max'=>'请填写小于128个字的城市名称',
            'enterprise.required'=>'请填写公司名称',
            'enterprise.string'=>'请填写字符形式公司名称',
            'enterprise.max'=>'请填写小于256个字的城市名称',
            'address.required'=>'请填写公司地址',
            'address.string'=>'请填写字符形式公司地址',
            'address.max'=>'请填写小于256个字的公司地址',
            'contact.required'=>'请填写联系人姓名',
            'contact.string'=>'请填写字符形式联系人姓名',
            'contact.max'=>'请填写小于64个字的联系人姓名',
            'email.required'=>'请填写邮箱地址',
            'email.email'=>'请填写正确格式的邮箱地址',
            'email.max'=>'请填写小于128个字的邮箱地址',
        ]);
        if($validator -> fails()){
            return Response::json(['errcode'=>1, 'errmsg'=>$validator->messages()->first()]);
        }
        DB::beginTransaction();
        $id = ExpressMerchant::insert_data([
            'merchant_id'      => $this->merchant_id,
            'mobile'           => $param['mobile'],
            'city_name'        => $param['city_name'],
            'enterprise'       => $param['enterprise'],
            'address'          => $param['address'],
            'contact'          => $param['contact'],
            'email'            => $param['email'],
        ]);
        $dada = (new DadaMerchant()) ->setConfig($this->app_key,$this->app_secret,'',$this->env);
        $response  = $dada ->add($param['mobile'],$param['city_name'],$param['enterprise'],$param['address'],$param['contact'],$param['email']);
        if(isset($response['code']) && $response['code'] == 0 && isset($response['result'])){
            ExpressMerchant::update_data('id',$id,['dada_id'=>$response['result']]);
            DB::commit();
            return Response::json(['errcode'=>0, 'errmsg'=>'注册成功','env'=>$this->env]);
        }else{
            DB::rollBack();
            return Response::json(['errcode'=>2, 'errmsg'=>(isset($response['msg']) ? $response['msg'] : '意外错误') ,'env'=>$this->env ]);
        }
    }

    //查看店铺列表
    public function listShop(){
        $page = $this->request->get('page',1);
        $length  = $this->request->get('length',15);
        $is      = $this->request->get('is_page',0);
        $query = ExpressShop::query()->where(['merchant_id'=>$this->merchant_id]);
        if($is == 1){
            $query->where('status','=',1);
        }
        $count =  $query->count();
        if($is == 0){
            $query->skip(($page-1)*$length)->take($length);
        }
        $list = $query->get(['store_id','name','business','city','city_name','area','address','contact','mobile','lng','lat','status'])->toArray();
        return Response::json(['errcode'=>0, 'errmsg'=>'注册成功','data'=>$list,'count'=>$count]);
    }

    //查看店铺详情
    public function getShop(){
        $id = $this->request->get('store_id',0);
        if(empty($id)){
            return Response::json(['errcode'=>1, 'errmsg'=>'参数有误']);
        }
        $info = ExpressShop::get_one('store_id',$id);
        if(!isset($info['id'])){
            return Response::json(['errcode'=>100, 'errmsg'=>'未添加']);
        }
        if($info['merchant_id'] != $this->merchant_id){
            return Response::json(['errcode'=>1, 'errmsg'=>'参数有误']);
        }
        unset($info['id']);
        unset($info['merchant_id']);
        unset($info['username']);
        unset($info['password']);
        unset($info['is_delete']);
        return Response::json(['errcode'=>0, 'errmsg'=>'成功','data'=>$info]);
    }

    //添加店铺
    public function addShop(){
        $param = $this->request->input();
        $validator = Validator::make($param,[
            'store_id'   => ['required','integer'],
            'name' => ['required','string','max:256'],
            'city'=>['required'],
            'business' => ['required','integer'],
            'lng' => ['required'],
            'lat' => ['required'],
            'area'=>['required','string','max:128'],
            'address'=>['required','string','max:256'],
            'contact'=>['required','string','max:64'],
            'mobile' => ['required','regex:/^1[34578][0-9]{9}$/'],
        ],[
            'store_id.required'=>'请传入门店id',
            'store_id.integer'=>'请传入正确格式的门店id',
            'name.required'=>'请填写店铺名称',
            'name.string'=>'请填写字符串格式点名',
            'name.max'=>'请填写店铺名不超过256个字',
            'city.required'=>'请选择城市',
            'business.required'=>'请选择业务类型',
            'business.integer'=>'请选择正确的业务类型',
            'area.required'=>'请填写店铺区域',
            'area.string'=>'请填写字符形式店铺区域',
            'area.max'=>'请填写小于256个字的店铺区域',
            'lng.required'=>'请传入位置经度',
            'lat.required'=>'请传入位置纬度',
            'address.required'=>'请填写店铺地址',
            'address.string'=>'请填写字符形式店铺地址',
            'address.max'=>'请填写小于256个字的店铺地址',
            'contact.required'=>'请填写联系人姓名',
            'contact.string'=>'请填写字符形式联系人姓名',
            'contact.max'=>'请填写小于64个字的联系人姓名',
            'mobile.required'=>'请填写手机号',
            'mobile.regex'=>'请填写正确格式的手机号',
        ]);
        if($validator -> fails()){
            return Response::json(['errcode'=>1, 'errmsg'=>$validator->messages()->first()]);
        }
        $city_name = '';
        $city_list = (new ExpressService())->getCity($this->merchant_id);
        foreach ($city_list as $k => $v) {
            if($v['cityCode'] == $param['city']){
                $city_name = $v['cityName'];
            }
        }
        if(empty($city_name)){
            return Response::json(['errcode'=>1, 'errmsg'=>'请选择正确的城市']);
        }
        $storeInfo = Store::get_data_by_id($param['store_id'],$this->merchant_id);
        if(!isset($storeInfo['id'])){
            return Response::json(['errcode'=>2, 'errmsg'=>'请传入正确的门店id']);
        }
        $checkInfo = ExpressShop::get_one('store_id',$param['store_id']);
        if(isset($checkInfo['id'])){
            return Response::json(['errcode'=>2, 'errmsg'=>'门店已添加过']);
        }
        DB::beginTransaction();
        $id = ExpressShop::insert_data([
            'merchant_id'  => $this->merchant_id,
            'store_id'     => $param['store_id'],
            'name'         => $param['name'],
            'business'     => $param['business'],
            'city'         => $param['city'],
            'city_name'    => $city_name,
            'area'         => $param['area'],
            'address'      => $param['address'],
            'contact'      => $param['contact'],
            'mobile'       => $param['mobile'],
            'lng'          => $param['lng'],
            'lat'          => $param['lat']
        ]);
        $dada = (new DadaShop())->setConfig($this->app_key,$this->app_secret,$this->merchant_dada,$this->env);
        $response = $dada->addShop($param['name'],$param['business'],$city_name,$param['area'],$param['address'],$param['lng'],$param['lat'],$param['contact'],$param['mobile'],$id);
        if(isset($response['code']) && $response['code'] == 0 && isset($response['result']['successList'][0]['originShopId'])){
            DB::commit();
            return Response::json(['errcode'=>0, 'errmsg'=>'添加成功']);
        }else{
            DB::rollBack();
            $msg = isset($response['result']['failedList'][0]['msg']) ?  $response['result']['failedList'][0]['msg'] : '';
            return Response::json(['errcode'=>2, 'errmsg'=>$msg,'data'=>$param]);
        }
    }

    //编辑店铺
    public function editShop(){
        $param = $this->request->input();
        $validator = Validator::make($param,[
            'store_id' => ['required','integer'],
            'name' => ['string','max:256'],
            'business' => ['integer'],
            'area'=>['string','max:128'],
            'address'=>['string','max:256'],
            'contact'=>['string','max:64'],
            'mobile' => ['regex:/^1[34578][0-9]{9}$/'],
            'status' => ['in:0,1']

        ],[
            'store_id.required'=>'请填传入店铺id',
            'store_id.integer'=>'请填传入正确格式店铺id',
            'name.string'=>'请填写字符串格式点名',
            'name.max'=>'请填写店铺名不超过256个字',
            'business.integer'=>'请选择正确的业务类型',
            'area.string'=>'请填写字符形式店铺区域',
            'area.max'=>'请填写小于256个字的店铺区域',
            'address.string'=>'请填写字符形式店铺地址',
            'address.max'=>'请填写小于256个字的店铺地址',
            'contact.string'=>'请填写字符形式联系人姓名',
            'contact.max'=>'请填写小于64个字的联系人姓名',
            'mobile.regex'=>'请填写正确格式的手机号',
            'status.in'=>'请填传入正确的状态',
        ]);
        if($validator -> fails()){
            return Response::json(['errcode'=>1, 'errmsg'=>$validator->messages()->first()]);
        }
        $info = ExpressShop::get_one('store_id',$param['store_id']);
        if(!isset($info['id']) || $info['merchant_id'] != $this->merchant_id){
            return Response::json(['errcode'=>1, 'errmsg'=>'门店id有误','info'=>$info]);
        }
        $data = [];
        if(isset($param['name']) && !empty($param['name']))          $data['name'] = $param['name'];
        if(isset($param['business']) && !empty($param['business']))  $data['business'] = $param['business'];
        if(isset($param['area']) && !empty($param['area']))          $data['area'] = $param['area'];
        if(isset($param['address']) && !empty($param['address']))    $data['address'] = $param['address'];
        if(isset($param['contact']) && !empty($param['contact']))    $data['contact'] = $param['contact'];
        if(isset($param['mobile']) && !empty($param['mobile']))      $data['mobile'] = $param['mobile'];
        if(isset($param['status']) && ($param['status'] == 0 || $param['status'] == 1) )      $data['status'] = $param['status'];
        if (isset($param['city']) && !empty($param['city'])){
            $city_list = (new ExpressService())->getCity($this->merchant_id);
            foreach ($city_list as $k => $v) {
                if($v['cityCode'] == $param['city']){
                    $data['city_name'] = $v['cityName'];
                }
            }
        }
        if(empty($data)){
            return Response::json(['errcode'=>1, 'errmsg'=>'没有有效的更新内容']);
        }
        $dada = (new DadaShop())->setConfig($this->app_key,$this->app_secret,$this->merchant_dada,$this->env);
        $response = $dada->updateShop($info['id'],$data);
        if(isset($response['code']) && $response['code'] == 0){
            ExpressShop::update_data('id',$info['id'],$data);
            return Response::json(['errcode'=>0, 'errmsg'=>'编辑成功']);
        }
        return Response::json(['errcode'=>2, 'errmsg'=>isset($response['msg'])?$response['msg']:'编辑意外出错','response'=>$response]);
    }

    //查看余额
    public function getPrice(){
        $info = ExpressMerchant::get_one('merchant_id',$this->merchant_id);
        if(!isset($info['id'])){
            return Response::json(['errcode'=>0, 'errmsg'=>'未授权']);
        }
        $dada = (new DadaCharge()) ->setConfig($this->app_key,$this->app_secret,$this->merchant_dada,$this->env);
        $response = $dada ->get(3);
        if(!isset($response['code']) || $response['code'] != 0 || !isset($response['result']['deliverBalance']) ){
            return Response::json(['errcode'=>1, 'errmsg'=>isset($response['msg'])?$response['msg']:'获取出错','response'=>$response]);
        }
        return Response::json(['errcode'=>0, 'errmsg'=>'ok','data'=>['balance'=>$response['result']['deliverBalance'],'redbalance' => $response['result']['redPacketBalance'] ]]);
    }

    public function recharge(){
        $info = ExpressMerchant::get_one('merchant_id',$this->merchant_id);
        if(!isset($info['id'])){
            return Response::json(['errcode'=>0, 'errmsg'=>'未授权']);
        }
        $dada = (new DadaCharge()) ->setConfig($this->app_key,$this->app_secret,$this->merchant_dada,$this->env);
        $response = $dada->getPayUrl(1,'pc',( new ExpressService())->priceCallbackUrl());
        if(!isset($response['code']) || $response['code'] != 0 || !isset($response['result']) ){
            return Response::json(['errcode'=>1, 'errmsg'=>isset($response['msg'])?$response['msg']:'获取出错','response'=>$response]);
        }
        return Response::json(['errcode'=>0, 'errmsg'=>'ok','data'=>[ 'url' => $response['result'] ]]);
    }
}

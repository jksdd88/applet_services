<?php

namespace App\Http\Controllers\Admin\Merchant;

use Hash;
use App\Http\Controllers\Controller;
use App\Models\Merchant;
use App\Models\MerchantSetting;
use App\Models\Shop;
use App\Models\User;
use App\Models\UserLog;
use App\Models\WeixinInfo;
use App\Models\Region;
use App\Models\Industry;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;
use Illuminate\Http\Request;
use function Predis\select;
use function GuzzleHttp\json_encode;
use function Qiniu\json_decode;
use App\Utils\SSOFun;
use App\Utils\Encrypt;

use App\Services\MerchantService;

class MerchantController extends Controller
{
    
    /**
     * CRM开账号
     * Author: songyongshang@dodoca.com
     */
    function postMerchant(Request $request){
        //校验码
        if($request['validation']!='81c0350e8118b01a02a283129c79419f'){
            $rt['errcode']=110001;
            $rt['errmsg']='检验码 不正确';
            return Response::json($rt);
        }
        //商户信息
        //商户名称
        $data_Merchant['company'] = isset($request['company'])?$request['company']:'';
        //国家—对应地区表ID
        $data_Merchant['country'] = isset($request['country'])?$request['country']:1;
        //省份—对应地区表ID
        $data_Merchant['province'] = isset($request['province'])?$request['province']:'';
        //城市—对应地区表ID
        $data_Merchant['city'] = isset($request['city'])?$request['city']:'';
        //区/县—对应地区表ID
        $data_Merchant['district'] = isset($request['district'])?$request['district']:'';
        //详细地址
        $data_Merchant['address'] = isset($request['address'])?$request['address']:'';
        //商户联系人
        $data_Merchant['contact'] = isset($request['contact'])?$request['contact']:'';
        //商户联系电话
        if(empty($request['mobile'])){
            $rt['errcode']=110002;
            $rt['errmsg']='商户联系人的手机号 不能为空';
            return Response::json($rt);
        }
        $data_Merchant['mobile'] = isset($request['mobile'])?$request['mobile']:'';
        //商户过期时间
        if($request['version_id']==1){
            $data_Merchant['expire_time']='2099-12-30 01:01:01';
        }else if($request['version_id']==5){
            $data_Merchant['expire_time']=date('Y-m-d H:i:s',strtotime('+1 month'));
        }else if($request['version_id']!=1 && empty($request['expire_time'])){
            $rt['errcode']=110003;
            $rt['errmsg']='到期时间 不能为空';
            return Response::json($rt);
        }else{
            $data_Merchant['expire_time']=$request['expire_time'];
        }
        //产品类型
        if(empty($request['version_id'])){
            $rt['errcode']=110004;
            $rt['errmsg']='产品类型 不能为空';
            return Response::json($rt);
        }
        //版本
        if(isset($request['industry_sign'])){
            $data_Merchant['industry_sign'] = $request['industry_sign'];
        }
        //默认为免费版（新）
        $data_Merchant['version_id'] = isset($request['version_id'])?$request['version_id']:5;
        //商户logo
        $data_Merchant['logo'] = '2017/09/25/FlZpZ4MoG_ao5G8V_lHlORjk067y.jpg';
        //ddcid
        $data_Merchant['ddcid'] = $this->ddcid_valid($data_Merchant);
        if(empty($data_Merchant['ddcid'])){
            $rt['errcode']=110004;
            $rt['errmsg']='商户名 不能为空.';
            return Response::json($rt);
        }
        $data_Merchant['ddcsecret'] = md5($data_Merchant['ddcid'].time().rand(0,100000));
        
        //店铺性质
        if(!isset($request['type'])){
            $rt['errcode']=110005;
            $rt['errmsg']='店铺性质 不能为空';
            return Response::json($rt);
        }
        //拉新用户有礼
        $Encrypt   = new Encrypt;
        $data_Merchant['referee_merchant_id'] = isset($request['referee_merchant_id'])?$request['referee_merchant_id']:'';
        $data_Merchant['type'] = isset($request['type'])?$request['type']:'';
        $data_Merchant['source'] = isset($request['source'])?$request['source']:1;
        $data_Merchant['is_demo'] = isset($request['is_demo'])?$request['is_demo']:0;
        
        $data_Merchant['status'] =1;
        $data_Merchant['created_time'] =date('Y-m-d H:i:s');
        $data_Merchant['updated_time'] =date('Y-m-d H:i:s');
        
        //店铺
        //店铺名称
        if( isset($request['shop_name']) && !empty($request['shop_name']) ){
            $data_Shop['name'] = $request['shop_name'];
        }else{
            $data_Shop['name'] = '商城名称';
        }
        $data_Shop['logo'] = '2017/09/25/FlZpZ4MoG_ao5G8V_lHlORjk067y.jpg';
        $data_Shop['created_time'] =date('Y-m-d H:i:s');
        $data_Shop['updated_time'] =date('Y-m-d H:i:s');
        
        //用户信息
        //管理员用户名
        if(empty($request['username'])){
            $rt['errcode']=110006;
            $rt['errmsg']='管理员用户名 不能为空';
            return Response::json($rt);
        }else{
            $slt_User_username=User::where(['username'=>$request['username']])->orWhere(['mobile'=>$request['username']])->first();
            if(!empty($slt_User_username)){
                $rt['errcode']=110007;
                $rt['errmsg']='此用户名已被注册';
                return Response::json($rt);
            }
            $data_User['username']=$request['username'];
        }
        //管理员密码
        if(empty($request['password'])){
            $rt['errcode']=110008;
            $rt['errmsg']='管理员密码 不能为空';
            return Response::json($rt);
        }else if( strlen($request['password'])<6 ){
            $rt['errcode']=110009;
            $rt['errmsg']='管理员密码至少6位';
            return Response::json($rt);
        }else{
            $pass_nocrypt = $request['password'];
            $data_User['password']=bcrypt($request['password']);
        }
        //管理员手机
        if(empty($request['admin_mobile'])){
            $rt['errcode']=110010;
            $rt['errmsg']='管理员手机 不能为空';
            return Response::json($rt);
        }else{
            $slt_User_mobile=User::where(['mobile'=>$request['admin_mobile']])->orWhere(['username'=>$request['admin_mobile']])->first();
            if(!empty($slt_User_mobile)){
                $rt['errcode']=110011;
                $rt['errmsg']='您的手机号已经注册了小程序.';
                return Response::json($rt);
            }
            $data_User['mobile']=$request['admin_mobile'];
        }
        //管理员电子邮箱
        $data_User['email']=isset($request['email'])?$request['email']:'';
        //管理员头像
        if(empty($request['avatar'])){
            $data_User['avatar']='2017/09/25/FlZpZ4MoG_ao5G8V_lHlORjk067y.jpg';
        }else{
            $data_User['avatar']=$request['avatar'];
        }
        $data_User['is_admin']=1;
        $data_User['is_delete']=1;
        $data_User['created_time']=date('Y-m-d H:i:s');
        $data_User['updated_time']=date('Y-m-d H:i:s');
        
        //SSO:检测用户名
        $rt_ssocheck = json_decode(SSOFun::checkaccount($request));
        if(empty($rt_ssocheck) || !isset($rt_ssocheck->errcode) || $rt_ssocheck->errcode!=0){
            $rt['errcode']=110012;
            $rt['errmsg']=is_object($rt_ssocheck)?$rt_ssocheck->errmsg:'SSO接口异常';
            return Response::json($rt);
        }
        
        DB::beginTransaction();
        try{
            //商户:保存信息
            $rs_Merchant_id = Merchant::insertGetId($data_Merchant);
            if(empty($rs_Merchant_id)){
                $rt['errcode']=110013;
                $rt['errmsg']='商户:保存信息 失败!';
                return Response::json($rt);
            }
            //店铺:保存信息
            $data_Shop['merchant_id']=  $rs_Merchant_id;
            $rs_Shop_id = Shop::insertGetId($data_Shop);
            if(empty($rs_Shop_id)){
                $rt['errcode']=110014;
                $rt['errmsg']='店铺:保存信息 失败';
            }
            //管理员:保存信息
            $data_User['merchant_id']=  $rs_Merchant_id;
            $rs_User_id = User::insertGetId($data_User);
            if(empty($rs_User_id)){
                $rt['errcode']=110015;
                $rt['errmsg']='管理员:保存信息 失败';
            }
    
            //MerchantSetting
            $data_MerchantSetting['merchant_id']=  $rs_Merchant_id;
            $data_MerchantSetting['warning_stock']=  10;
            $rs_MerchantSetting_id = MerchantSetting::insertGetId($data_MerchantSetting);
            if(empty($rs_MerchantSetting_id)){
                $rt['errcode']=110016;
                $rt['errmsg']='管理员:保存信息 失败';
            }
            
            //-------------日志 start-----------------
            $request['password'] = md5($request['password']);
            $data_UserLog['merchant_id']=$rs_Merchant_id;
            $data_UserLog['user_id']=$rs_User_id;
            $data_UserLog['type']=isset($request['source'])&&$request['source']==1?1:(isset($request['password'])&&$request['password']?26:27);
            $data_UserLog['url']='merchant/merchant.json';
            $data_UserLog['content']=json_encode($request->all());
            $data_UserLog['ip']=get_client_ip();
            $data_UserLog['created_time']=date('Y-m-d H:i:s');
            $data_UserLog['updated_time']=date('Y-m-d H:i:s');
            UserLog::insertGetId($data_UserLog);
            //-------------日志 end-----------------
    
            //SSO:注册
            $request['password'] = $pass_nocrypt;
            $request['data_id'] = $rs_User_id;
            $request['data_level'] = 1;
            $rt_register = json_decode(SSOFun::register($request,$rs_Merchant_id,$rs_User_id));
            if(empty($rt_register) || !isset($rt_register->errcode) || $rt_register->errcode!=0){
                $rt['errcode']=110017;
                $rt['errmsg']=is_object($rt_register)?$rt_register->errmsg:'SSO接口异常';
                return Response::json($rt);
            }
            
            //开通收费版商家账号，送1个直播包
            $arr_version = config('version');
            //dd($arr_version[$data_Merchant['version_id']]['live_money']);
            if( isset($arr_version[$data_Merchant['version_id']]['live_money']) && !empty($arr_version[$data_Merchant['version_id']]['live_money']) ){
                $data_live['merchant_id'] = $rs_Merchant_id;
                $data_live['ctype'] = 1;
                $data_live['type'] = 11;
                $data_live['sum'] = 1;
                $data_live['memo'] = '开通收费版商家账号，送1个直播包';
                MerchantService::changeLiveMoney($data_live);
            }
            
            
            DB::commit();
    
        }catch (\Exception $e) {
            $rt['errcode']=110018;
            $rt['errmsg']='开账号 失败';
            return Response::json($rt);
        }
        
        $rt['errcode']=0;
        $rt['errmsg']='开账号 成功';
        $rt['data'] = array('id'=>$rs_Merchant_id);
        return Response::json($rt);
    }
    
    /**
     * CRM修改账号
     * 有效期限/密码/删除状态/版本类型
     * Author: songyongshang@dodoca.com
     */
    function postMerchantInfo(Request $request){
        //校验码
        if($request['validation']!='81c0350e8118b01a02a283129c79419f'){
            $rt['errcode']=100001;
            $rt['errmsg']='检验码 不正确';
            return Response::json($rt);
        }
        //商户信息
        //商户id
        if( !isset($request['id']) || empty($request['id']) ){
            $rt['errcode']=100002;
            $rt['errmsg']='商户id 不能为空';
            return Response::json($rt);
        }
        
        //商户名称
        if( isset($request['company']) && !empty($request['company']) ){
            $data_Merchant['company'] = $request['company'];
        }
        //国家—对应地区表ID
        if( isset($request['country']) && !empty($request['country']) ){
            $data_Merchant['country'] = $request['country'];
        }
        //省份—对应地区表ID
        if( isset($request['province']) && !empty($request['province']) ){
            $data_Merchant['province'] = $request['province'];
        }
        //城市—对应地区表ID
        if( isset($request['city']) && !empty($request['city']) ){
            $data_Merchant['city'] = $request['city'];
        }
        //区/县—对应地区表ID
        if( isset($request['district']) && !empty($request['district']) ){
            $data_Merchant['district'] = $request['district'];
        }
        //详细地址
        if( isset($request['address']) && !empty($request['address']) ){
            $data_Merchant['address'] = $request['address'];
        }
        //商户联系人
        if( isset($request['contact']) && !empty($request['contact']) ){
            $data_Merchant['contact'] = $request['contact'];
        }
        //商户联系电话
        if( isset($request['mobile']) && !empty($request['mobile']) ){
            $data_Merchant['mobile'] = $request['mobile'];
        }
        //商户过期时间
        if( isset($request['expire_time']) && !empty($request['expire_time']) ){
            if( isset($request['version_id']) && $request['version_id']==1 ){
                $data_Merchant['expire_time']='2099-12-30 01:01:01';
            }else if( isset($request['version_id']) && $request['version_id']==5 ){
                $data_Merchant['expire_time']=date('Y-m-d H:i:s',strtotime('+1 month'));
            }else{
                $data_Merchant['expire_time']=$request['expire_time'];
            }
        }
        //版本
        if( isset($request['version_id']) && !empty($request['version_id']) ){
            $data_Merchant['version_id'] = $request['version_id'];
        }
        //行业
        if( isset($request['industry_sign']) && !empty($request['industry_sign']) ){
            $data_Merchant['industry_sign'] = $request['industry_sign'];
        }
        //商户logo
        if( isset($request['logo']) && !empty($request['logo']) ){
            $data_Merchant['logo'] = $request['logo'];
        }
        //店铺性质
        if( isset($request['type']) && !empty($request['type']) ){
            $data_Merchant['type'] = $request['type'];
        }
        //删除状态
        if(!empty($request['status'])){
            $data_Merchant['status'] = $request['status'];
        }
		//是否演示账号
        $data_Merchant['is_demo'] = isset($request['is_demo'])?$request['is_demo']:0;
        
        $rs_user = User::where(['merchant_id'=>$request['id'],'is_admin'=>1])->first();
        if($request['status']=='-1'){
            $data_User['mobile']=null;
            $data_User['is_delete']='-1';
        }
        
        //店铺
        //店铺名称
        if( isset($request['shop_name']) && !empty($request['shop_name']) ){
            $data_Shop['name'] = $request['shop_name'];
        }
        //店铺logo
        if( isset($request['logo']) && !empty($request['logo']) ){
            $data_Shop['logo'] = $request['logo'];
        }
        
        //用户信息
        //管理员密码
        if( isset($request['password']) && !empty($request['password']) ){
            if( strlen($request['password'])<6 ){
                $rt['errcode']=100018;
                $rt['errmsg']='管理员密码至少6位';
                return Response::json($rt);
            }else{
                $pass_nocrypt = $request['password'];
                $data_User['password']=bcrypt($request['password']);
                $data_User['updated_time']=date('Y-m-d H:i:s');
            }
        }
        //管理员手机
        if( isset($request['admin_mobile']) && !empty($request['admin_mobile'])){
            //小程序:检测手机是否已注册
            $slt_User_mobile=User::where(['mobile'=>$request['admin_mobile']])->orWhere(['username'=>$request['admin_mobile']])->first();
            if(!empty($slt_User_mobile)){
                $rt['errcode']=110011;
                $rt['errmsg']='您的手机号已经注册了小程序';
                return Response::json($rt);
            }
            //SSO:换绑手机检测
            $data_sso['data_id'] = $request['id'];
            $data_sso['username'] = $rs_user['username'];
            $data_sso['old_mobile'] = $rs_user['mobile'];
            $data_sso['mobile'] = $request['admin_mobile'];
            $rt_ssocheck = json_decode(SSOFun::checkmobile($data_sso,$request['id'],$rs_user['id']));
            if(empty($rt_ssocheck) || !isset($rt_ssocheck->errcode) || $rt_ssocheck->errcode!=0){
                $rt['errcode']=100027;
                $rt['errmsg']=is_object($rt_ssocheck)?$rt_ssocheck->errmsg:'SSO接口异常';
                return Response::json($rt);
            }
            unset($data_sso);
            
            $data_User['mobile']=$request['admin_mobile'];
        }
        //管理员电子邮箱
        if( isset($request['email']) && !empty($request['email'])){
            $data_User['email']=$request['email'];
        }
        //管理员头像
        if( isset($request['avatar']) && empty($request['avatar']) ){
            $data_User['avatar']=$request['avatar'];
        }
        
        if( empty($data_Merchant) && empty($data_User) && empty($data_Shop) ){
            $rt['errcode']=100001;
            $rt['errmsg']='修改的字段 不能为空';
            return Response::json($rt);
        }
        
        $data_Merchant['updated_time'] =date('Y-m-d H:i:s');
    
        DB::beginTransaction();
        try{
            //查询商户信息
            $rs_Merchant = Merchant::get_data_by_id($request['id']);
            if(empty($rs_Merchant)){
                $rt['errcode']=100026;
                $rt['errmsg']='查不到此商户信息;';
                return Response::json($rt);
            }
            //商户:保存信息
            $rs_Merchant_id = Merchant::update_data($request['id'],$data_Merchant);
            if(empty($rs_Merchant_id)){
                $rt['errcode']=100026;
                $rt['errmsg']='商户:保存信息 失败;';
                return Response::json($rt);
            }
            
            //管理员:保存信息
            $rs_User_id=0;
            if( (!empty($request['password']) || isset($data_Merchant['status'])) && !empty($data_User) ){
                //$data_User['merchant_id']=  $rs_Merchant_id;
                $rs_User_id = User::where(['merchant_id'=>$request['id'],'is_admin'=>1])->update($data_User);
                if(empty($rs_User_id)){
                    $rt['errcode']=0;
                    $rt['errmsg']='管理员:保存密码 失败';
                }
            }
    
            //-------------日志 start-----------------
            $data_UserLog['merchant_id']=$request['id'];
            $data_UserLog['user_id']='';
            $data_UserLog['type']=2;
            $data_UserLog['url']='merchant/merchant.json';
            $data_UserLog['content']=json_encode($request->all());
            $data_UserLog['ip']=get_client_ip();
            $data_UserLog['created_time']=date('Y-m-d H:i:s');
            $data_UserLog['updated_time']=date('Y-m-d H:i:s');
            UserLog::insertGetId($data_UserLog);
            //-------------日志 end-----------------
    
            //商家账号免费版升级到收费版，送1个直播包
            $arr_version = config('version');
            if( 
                in_array($rs_Merchant['version_id'], array(1,5)) && isset($arr_version[$request['version_id']]['live_money']) && !empty($arr_version[$request['version_id']]['live_money']) 
                && ( (isset($request['status']) && $request['status']!='-1') || !isset($request['status']) )
                ){
                $data_live['merchant_id'] = $request['id'];
                $data_live['ctype'] = 1;
                $data_live['type'] = 11;
                $data_live['sum'] = 1;
                $data_live['memo'] = '商家账号'.$arr_version[$rs_Merchant['version_id']]['name'].'('.$rs_Merchant['version_id'].')'.'升级到'.$arr_version[$request['version_id']]['name'].'('.$request['version_id'].')'.'，送'.$data_live['sum'].'个直播包';
                MerchantService::changeLiveMoney($data_live);
            }
            
            DB::commit();
    
        }catch (\Exception $e) {
            $rt['errcode']=100027;
            $rt['errmsg']='修改账号 失败.';
            //$rt['e']=$e;
            return Response::json($rt);
        }
    
        //SSO:删除帐号
        if( isset($request['status']) && $request['status']=='-1' ){
            if(empty($rs_user)){
                $rt['errcode']=100027;
                $rt['errmsg']='没有查到主账号';
                return Response::json($rt);
            }
            $data_sso['data_id'] = $request['id'];
            $data_sso['username'] = $rs_user['username'];
            $data_sso['mobile'] = $rs_user['mobile'];
            $rt_ssocheck = json_decode(SSOFun::deleteaccount($data_sso,$rs_user['merchant_id'],$rs_user['id']));
            if(empty($rt_ssocheck) || !isset($rt_ssocheck->errcode) || $rt_ssocheck->errcode!=0){
                $rt['errcode']=100027;
                $rt['errmsg']=is_object($rt_ssocheck)?$rt_ssocheck->errmsg:'SSO接口异常';
                return Response::json($rt);
            }
            unset($data_sso);
        }
        //SSO:修改密码
        if( isset($request['password']) && !empty($request['password']) ){
            if(empty($rs_user)){
                $rt['errcode']=100027;
                $rt['errmsg']='没有查到主账号';
                return Response::json($rt);
            }
            $data_sso['data_id'] = $request['id'];
            $data_sso['username'] = $rs_user['username'];
            $data_sso['password'] = $pass_nocrypt;
            $data_sso['mobile'] = $rs_user['mobile'];
            $rt_ssocheck = json_decode(SSOFun::changepasswd($data_sso,$rs_user['merchant_id'],$rs_user['id']));
            if(empty($rt_ssocheck) || !isset($rt_ssocheck->errcode) || $rt_ssocheck->errcode!=0){
                $rt['errcode']=100027;
                $rt['errmsg']=is_object($rt_ssocheck)?$rt_ssocheck->errmsg:'SSO接口异常';
                return Response::json($rt);
            }
            unset($data_sso);
        }
        //SSO:换绑手机
        if( isset($request['admin_mobile']) && !empty($request['admin_mobile'])){
            $data_sso['data_id'] = $request['id'];
            $data_sso['username'] = $rs_user['username'];
            $data_sso['old_mobile'] = $rs_user['mobile'];
            $data_sso['mobile'] = $request['admin_mobile'];
            $rt_ssobind = json_decode(SSOFun::bindmobile($data_sso,$request['id'],$rs_user['id']));
            if(empty($rt_ssobind) || !isset($rt_ssobind->errcode) || $rt_ssobind->errcode!=0){
                $rt['errcode']=100027;
                $rt['errmsg']=$rt_ssobind->errmsg;
                return Response::json($rt);
            }
        }
        $rt['errcode']=0;
        $rt['errmsg']='修改账号 成功';
        return Response::json($rt);
    }
    
    /**
     * 登录注册第二步:完善信息
     * Author: songyongshang@dodoca.com
     */
    function putImproveInfo(Request $request){
        //手机端补全信息
        if( isset($request['token']) && !empty($request['token']) ){
            //联系电话
            if( !isset($request['token']) || empty($request['mobile']) ){
                $rt['errcode']=100002;
                $rt['errmsg']='请填写 联系电话';
                return Response::json($rt);
            }
            //dd(base64_encode(encrypt('15901810470', "E", 'AppletAcountImproveInfo')));//加密
            //dd(encrypt(base64_decode($request['token']), "D", 'AppletAcountImproveInfo'));//解密
            if( $request['mobile']!=encrypt(base64_decode($request['token']), "D", 'AppletAcountImproveInfo') ){
                $rt['errcode']=1000021;
                $rt['errmsg']='请输入正确的手机号码';
                return Response::json($rt);
            }
            $rs_user = User::where(['mobile'=>$request['mobile']])->first();
            if(empty($rs_user)){
                $rt['errcode']=1000023;
                $rt['errmsg']='没有此手机相关的信息';
                return Response::json($rt);
            }
            $rs_merchant = Merchant::where(['id'=>$rs_user['merchant_id']])->first();
            if(isset($rs_merchant['company']) && !empty($rs_merchant['company'])){
                $rt['errcode']=1000001;
                $rt['errmsg']='公司名称已存在,请登录';
                return Response::json($rt);
            }
            $merchant_id = $rs_user['merchant_id'];
            $user_id = $rs_user['id'];
        }
        //pc端补全信息
        else {
            if( empty(Auth::user()->merchant_id) ){
                $rt['errcode']=1000022;
                $rt['errmsg']='请登录后 再补全信息';
                return Response::json($rt);
            }
            $merchant_id = Auth::user()->merchant_id;
            $user_id = Auth::user()->id;
            
            $rs_merchant = Merchant::where(['id'=>Auth::user()->merchant_id])->first();
        }
        //公司名称
        if(empty($request['company'])){
            $rt['errcode']=100002;
            $rt['errmsg']='请填写 公司名称';
            return Response::json($rt);
        }
        $data_Merchant['company'] =$request['company'];
        
        //公司地址:省
        if(empty($request['province'])){
            $rt['errcode']=100002;
            $rt['errmsg']='请选择 公司地址:省份';
            return Response::json($rt);
        }
        $data_Merchant['province'] =$request['province'];
        
        //公司地址:市
        if(empty($request['city'])){
            $rt['errcode']=100002;
            $rt['errmsg']='请选择 公司地址:地区/市';
            return Response::json($rt);
        }
        $data_Merchant['city'] =$request['city'];
        
        //公司地址:县
        if(empty($request['district'])){
            $rt['errcode']=100002;
            $rt['errmsg']='请选择 公司地址:区县';
            return Response::json($rt);
        }
        $data_Merchant['district'] =$request['district'];
        
        //详细地址
        $data_Merchant['address'] =isset($request['address'])?$request['address']:'';
        
        //行业分类一级
        if(empty($request['industry_one'])){
            $rt['errcode']=100002;
            $rt['errmsg']='请选择 行业分类一级';
            return Response::json($rt);
        }
        
        //行业分类二级
        if(empty($request['industry_two'])){
            $rt['errcode']=100002;
            $rt['errmsg']='请选择 行业分类二级';
            return Response::json($rt);
        }
        
        //行业分类三级
        if(empty($request['industry_three'])){
            $rt['errcode']=100002;
            $rt['errmsg']='请选择 行业分类三级';
            return Response::json($rt);
        }
        
        //联系人
        if(empty($request['contact'])){
            $rt['errcode']=100002;
            $rt['errmsg']='请填写 联系人';
            return Response::json($rt);
        }
        $data_Merchant['contact'] =$request['contact'];
        
        $data_Merchant['updated_time'] =date('Y-m-d H:i:s');
    
        DB::beginTransaction();
        try{
            //商户:保存信息
            $rs_Merchant_id = Merchant::update_data($merchant_id,$data_Merchant);
            if(empty($rs_Merchant_id)){
                $rt['errcode']=100026;
                $rt['errmsg']='商户:保存信息 失败.';
                return Response::json($rt);
            }
    
            //-------------日志 start-----------------
            $data_UserLog['merchant_id']=$merchant_id;
            $data_UserLog['user_id']=$user_id;
            $data_UserLog['type']=8;
            $data_UserLog['url']='merchant/improveinfo.json';
            $data_UserLog['content']=json_encode($data_Merchant);
            $data_UserLog['ip']=get_client_ip();
            $data_UserLog['created_time']=date('Y-m-d H:i:s');
            $data_UserLog['updated_time']=date('Y-m-d H:i:s');
            UserLog::insertGetId($data_UserLog);
            //-------------日志 end-----------------
    
            DB::commit();
    
        }catch (\Exception $e) {
            $rt['errcode']=100027;
            $rt['errmsg']='修改账号 失败;';
            //$rt['e']=$e;
            return Response::json($rt);
        }
        
        //王珅接口
        $data_ImporveInfo = $data_Merchant;
        //merchant_id
        $data_ImporveInfo['merchant_id'] =$merchant_id;
        //邀请码
        $data_ImporveInfo['service_name'] =isset($request['invite_code'])?$request['invite_code']:'';
        //是否需要销售顾问
        //$data_ImporveInfo['if_consultant'] =isset($request['sales_consultant'])?$request['sales_consultant']:'';
        $data_ImporveInfo['if_consultant'] = 1;//2018_08_17 孙总调整
        //行业分类
        $data_ImporveInfo['industry'] =Industry::get_title_by_id($request['industry_one']).'-'.Industry::get_title_by_id($request['industry_two']).'-'.Industry::get_title_by_id($request['industry_three']);
        //省市区名称
        $data_ImporveInfo['province_msg'] = Region::get_title_by_id($data_ImporveInfo['province']);
        $data_ImporveInfo['city_msg'] = Region::get_title_by_id($data_ImporveInfo['city']);
        $data_ImporveInfo['district_msg'] = Region::get_title_by_id($data_ImporveInfo['district']);
        
        //dd($data_ImporveInfo);
        if(env('APP_ENV')=='production' ){
            $curl_rs = $this->curl('http://www.dodoca.com/useradd/appletcomplete',$data_ImporveInfo,'POST');//正式
        }else{
            $curl_rs = $this->curl('http://twww.dodoca.com/useradd/appletcomplete',$data_ImporveInfo,'POST');//开发
        }
        
        //----------日志 start----------
        $data_UserLog['merchant_id']=$merchant_id;
        $data_UserLog['user_id']=$user_id;
        $data_UserLog['type']=( isset($request['token']) && !empty($request['token']) )?18:19;
        $data_UserLog['url']='merchant/merchant.json';
        $data_UserLog['content']=json_encode(array(
            'data_request'=>$data_ImporveInfo,
            'data_response'=>$curl_rs,
        ));
        $data_UserLog['ip']=get_client_ip();
        $data_UserLog['created_time']=date('Y-m-d H:i:s');
        $data_UserLog['updated_time']=date('Y-m-d H:i:s');
        UserLog::insertGetId($data_UserLog);
        //----------日志 end----------
        
        //----------新注册的50个 1.上海地区 2.免费版商家 3.没有邀请码 开通客服 日志 start----------
        if($request['province']==310000 && $rs_merchant['version_id']==1 && (!isset($request['invite_code']) || empty($request['invite_code'])) ){
            //3 是否已到50个商家(1个测试账号,所以为51家) 4 还未开通客服的商家
            $data_userlog = UserLog::get_data_of_custserviceWithFree();
            
            if($data_userlog['count']<50 && empty($data_userlog['merchant_id'][$merchant_id])){
                $data2_UserLog['merchant_id']=$merchant_id;
                $data2_UserLog['user_id']=$user_id;
                $data2_UserLog['type']=50;
                $data2_UserLog['url']='merchant/improveinfo.json';
                $data2_UserLog['content']=json_encode($data2_UserLog);
                $data2_UserLog['ip']=get_client_ip();
                UserLog::insert_data($data2_UserLog);
            }
        }
        //----------新注册的50个 1.上海地区 2.免费版商家 3.没有邀请码 开通客服 日志 end----------
        
        $rt['errcode']=0;
        $rt['errmsg']='完善信息成功！';
        return Response::json($rt);
    }
    
    /**
     * 原力系统验重
     * Author: songyongshang@dodoca.com
     */
    function postCheckagain(Request $request){
        //校验码
        if($request['validation']!='81c0350e8118b01a02a283129c79419f'){
            $rt['errcode']=110001;
            $rt['errmsg']='检验码 不正确';
            return Response::json($rt);
        }
        
        //用户信息
        //管理员用户名
        if( isset($request['username']) && !empty($request['username'])){
            $slt_User_username=User::where(['username'=>$request['username']])->first();
            if(!empty($slt_User_username)){
                $rt['errcode']=110007;
                $rt['errmsg']='此用户名已被注册';
                return Response::json($rt);
            }
            $data_sso['username']=$request['username'];
        }
        //管理员手机
        if( isset($request['admin_mobile']) && !empty($request['admin_mobile']) ){
            $slt_User_mobile=User::where(['mobile'=>$request['admin_mobile']])->first();
            if(!empty($slt_User_mobile)){
                $rt['errcode']=110011;
                $rt['errmsg']='您的手机号已经注册了小程序';
                return Response::json($rt);
            }
            $data_sso['mobile']=$request['admin_mobile'];
        }
        if( empty($data_sso) ){
            $rt['errcode']=110011;
            $rt['errmsg']='请提交 用户名或手机号';
            return Response::json($rt);
        }
        
        //SSO:检测用户名和手机
        $rt_ssocheck = json_decode(SSOFun::checkaccount($request));
        if(empty($rt_ssocheck) || !isset($rt_ssocheck->errcode) || $rt_ssocheck->errcode!=0){
            $rt['errcode']=110012;
            $rt['errmsg']=is_object($rt_ssocheck)?$rt_ssocheck->errmsg:'SSO接口异常';
            return Response::json($rt);
        }
    
        $rt['errcode']=0;
        $rt['errmsg']='可注册';
        $rt['data'] = array();
        return Response::json($rt);
    }
    
    /**
     * 获取商户信息
     * Author: songyongshang@dodoca.com
     */
    public function getMerchant() {
        $arr_version = config('version');
        $arr_industrysign = config('industrysign');
        //dd($arr_version);
        $rt['data']=Merchant::get_data_by_id(Auth::user()->merchant_id);
        $rs_merchant_setting = MerchantSetting::get_data_by_id(Auth::user()->merchant_id);
        $rt['data']['staff_alias'] = !empty($rs_merchant_setting['staff_alias'])?$rs_merchant_setting['staff_alias']:"技师";
        
        //商城信息
        $rs_shop = Shop::get_data_by_merchant_id(Auth::user()->merchant_id);
        if(!empty($rs_shop)){
            $rt['data']['shop_name'] = $rs_shop['name'];
            $rt['data']['shop_logo'] = $rs_shop['logo'];
            $rt['data']['shop_price_field_alias'] = $rs_shop['price_field_alias'];
            $rt['data']['shop_csale_show'] = $rs_shop['csale_show'];
            $rt['data']['shop_kefu_mobile'] = $rs_shop['kefu_mobile'];
        }
        
        //dd($rt['data']);
        //增加登录用户名
        $rt['data']['username'] = Auth::user()->username;
        $rt['data']['user_mobile'] = Auth::user()->mobile;
        $expired_time = $rt['data']['expire_time'];
        //dd($rt['data']['expire_time']);
        $rt['data']['countdown_deadline'] = '';
        if($rt['data']['version_id']==1){
            $rt['data']['expire_days'] = $arr_version[$rt['data']['version_id']]['name'];
        }else if($rt['data']['version_id']==5){
            //免费版（新） 创建时间超过1个月即过期
            if( date('Y-m-d H:i:s')>=strtotime('+1 month',strtotime($rt['data']['created_time'])) ){
                $rt['data']['expire_days'] = $arr_version[$rt['data']['version_id']]['name'].':已过期';
            }else{
                $rt['data']['expire_days'] = date('Y年m月d日 ',strtotime('+1 month',strtotime($rt['data']['created_time']))).'到期';
            }
            //倒计时截止日期时间
            $rt['data']['countdown_deadline'] = date('Y-m-d H:i:s',strtotime('+1 month',strtotime($rt['data']['created_time'])));
        }else if($expired_time<date('Y-m-d H:i:s')){
            $rt['data']['expire_days'] = $arr_version[$rt['data']['version_id']]['name'].':已过期';
            $rt['data']['force_logout'] = 1;
            $rt['data']['force_logout_msg'] = '您的账号已过期，请联系代理商进行续费';
        }else{
            $days = round((strtotime($expired_time) - time()) / 3600 / 24);
            //dd( strtotime($expired_time) .'-'. time() );
            $result = '';
            if ($days >= 365) {
                $temp1 = intval($days / 365);
                $temp = $days % 365;
                if ($temp1 > 0) {
                    $result .= $temp1 . '年';
                }
                if ($temp > 0) {
                    $result .= $temp . '天';
                }
            } else {
                $result = $days . '天';
            }
            if (!empty($result)) {
                //dd($rt['data']['version_id']);
                $rt['data']['expire_days'] = date('Y年m月d日 ',strtotime($expired_time)).'到期';
            }
        }
        //微信是否授权
        $WeixinInfo = WeixinInfo::where(['merchant_id'=>Auth::user()->merchant_id])->first();
        if(!empty($WeixinInfo)){
            $rt['data']['weixin_accredit'] = 1;
        }else{
            $rt['data']['weixin_accredit'] = 0;
        }
        //版本名称
        $rt['data']['version_name'] = $arr_version[$rt['data']['version_id']]['name'];
        //行业名称
        $rt['data']['industrysign_name'] = (isset($arr_industrysign[$rt['data']['industry_sign']]['name']) && $rt['data']['version_id']!=5)?$arr_industrysign[$rt['data']['industry_sign']]['name']:'';
        if(empty($rt['data'])){
            $rt['errcode']=10000;
            $rt['errmsg']='获取商户信息 失败';
        }else{
            $rt['errcode']=0;
            $rt['errmsg']='获取商户信息 成功';
        }
        
        //是否需完善信息
        $rt['data']['improveinfo'] = 0;
        if( 
            in_array($rt['data']['version_id'], array(1,5))
            && (
                empty($rt['data']['company']) || empty($rt['data']['contact']) || empty($rt['data']['mobile']) 
                || empty($rt['data']['province']) || empty($rt['data']['city']) || empty($rt['data']['district']) 
                )
           ){
                $rt['data']['improveinfo'] = 1;
        }
        
        //现有的小程序数量
        $rt['data']['applet_num'] = WeixinInfo::list_count($rt['data']['id'],'');
        //dd($rt['data']);
        $rt['data']['applet_max_num'] = isset(config('version')[$rt['data']['version_id']]['applet_max_nums'])?config('version')[$rt['data']['version_id']]['applet_max_nums']:'';
        if( Auth::user()->merchant_id==14761 ){
            $rt['data']['applet_max_num'] += 10000;
        }
        return Response::json($rt);
    }
    
    /**
     * 是否需要销售顾问跟进
     * Author: songyongshang@dodoca.com
     */
    public function postCounselorTrail(Request $request) {
        $rs_merchant=Merchant::get_data_by_id(Auth::user()->merchant_id);
        if(!empty($rs_merchant) ){
            $data['commerce_uid']=Auth::user()->merchant_id;
            //$data['commerce_uid']=88;
            $data['is_consultant']=isset($request['is_consultant'])?$request['is_consultant']:'';
            if(env('APP_ENV')=='production' ){
                $curl_rt = $this->curl('http://we.dodoca.com/api/consultant',$data,'POST');//正式
            }else{
                $curl_rt = $this->curl('http://twe.dodoca.com/api/consultant',$data,'POST');//测试
            }
            //----------日志 start----------
            $userlog_type = 22;
            if( isset($request['source']) ){
                switch ($request['source']){
                    case "profile":
                        $userlog_type = 20;
                        break;
                    case "popup":
                        $userlog_type = 21;
                        break;
                    case "speedybutton":
                        $userlog_type = 24;
                        break;
                    default:
                        $userlog_type = 25;
                        break;
                }
            }
            $data_UserLog['merchant_id']=Auth::user()->merchant_id;
            $data_UserLog['user_id']=Auth::user()->id;
            $data_UserLog['type']=$userlog_type;
            $data_UserLog['url']='merchant/merchant.json';
            $data_UserLog['content']=json_encode(array(
                'data_request'=>$data,
                'data_response'=>$curl_rt,
                'ip'=>$_SERVER,
                'ipx'=>array(
                    'HTTP_X_FORWARDED_FOR'=>getenv("HTTP_X_FORWARDED_FOR")?getenv("HTTP_X_FORWARDED_FOR"):'',
                    'HTTP_X_REAL_IP'=>getenv('HTTP_X_REAL_IP'),
                    'HTTP_CLIENT_IP'=>getenv("HTTP_CLIENT_IP"),
                    'REMOTE_ADDR'=>getenv("REMOTE_ADDR"),
                    'REMOTE_ADDR'=>$_SERVER['REMOTE_ADDR']
                )
            ));
            $data_UserLog['ip']=get_client_ip();
            $data_UserLog['created_time']=date('Y-m-d H:i:s');
            $data_UserLog['updated_time']=date('Y-m-d H:i:s');
            UserLog::insertGetId($data_UserLog);
            //----------日志 end----------
            
            return Response::json( json_decode($curl_rt) );
        }
    }
    
    /** 
     * 编辑商户信息 
     * Author: songyongshang@dodoca.com
     */
    public function putMerchant(Request $request){
        $slt_Merchant = User::where(['user.id'=>Auth::user()->id]) 
                ->leftjoin('merchant','merchant.id','=','user.merchant_id')
                ->select('merchant.id')->first();

        if(empty($slt_Merchant)){
            $rt['errcode']=100001;
            $rt['errmsg']='查不到此商户信息';
            return Response::json($rt);
        }
        
        //商户名称
//         if(empty($request['company'])){
//             $rt['errcode']=100001;
//             $rt['errmsg']='商户名称 不能为空';
//             return Response::json($rt);
//         }else{
//             $data_Merchant['company'] = $request['company'];
//         }
        //国家—对应地区表ID
        $data_Merchant['country'] = 1;
        //省份—对应地区表ID
        if(empty($request['province'])){
            $rt['errcode']=100003;
            $rt['errmsg']='省份 不能为空';
            return Response::json($rt);
        }else{
            $data_Merchant['province'] = $request['province'];
        }
        //城市—对应地区表ID
        if(empty($request['city'])){
            $rt['errcode']=100004;
            $rt['errmsg']='城市 不能为空';
            return Response::json($rt);
        }else{
            $data_Merchant['city'] = $request['city'];
        }
        //区/县—对应地区表ID
        if(empty($request['district'])){
            $rt['errcode']=100005;
            $rt['errmsg']='区/县 不能为空';
            return Response::json($rt);
        }else{
            $data_Merchant['district'] = $request['district'];
        }
        //详细地址
        if(empty($request['address'])){
            $rt['errcode']=100007;
            $rt['errmsg']='详细地址 不能为空';
            return Response::json($rt);
        }else{
            $data_Merchant['address'] = $request['address'];
        }
        //联系人
        if(empty($request['contact'])){
            $rt['errcode']=100008;
            $rt['errmsg']='联系人 不能为空';
            return Response::json($rt);
        }else{
            $data_Merchant['contact'] = $request['contact'];
        }
        //联系电话
        if(empty($request['mobile'])){
            $rt['errcode']=100009;
            $rt['errmsg']='联系电话 不能为空';
            return Response::json($rt);
        }else{
            $data_Merchant['mobile'] = $request['mobile'];
        }
        //商户logo
        if(isset($request['logo']) && !empty($request['logo'])){
            $data_Merchant['logo'] = $request['logo'];
        }else if(empty($slt_MerchantInfo['logo'])){
            $data_Merchant['logo'] = '2017/09/25/FlZpZ4MoG_ao5G8V_lHlORjk067y.jpg';
        }
        //店铺性质
//         if(!isset($request['type'])){
//             $rt['errcode']=100009;
//             $rt['errmsg']='店铺性质 不能为空';
//             return Response::json($rt);
//         }else{
//             $data_Merchant['type'] = $request['type'];
//         }
        
        //过期时间
//         if(empty($request['expire_time'])){
//             $rt['errcode']=100009;
//             $rt['errmsg']='过期时间 不能为空';
//             return Response::json($rt);
//         }else{
//             $data_Merchant['expire_time']=date('Y-m-d H:i:s',strtotime('+ '.intval($request['expire_time']).' years',time()));
//         }
        $data_Merchant['updated_time'] =date('Y-m-d H:i:s');
        
        //更新商户
        $rs_Merchant = Merchant::update_data(Auth::user()->merchant_id,$data_Merchant);
        if(!$rs_Merchant){
            $rt['errcode']=100010;
            $rt['errmsg']='商户信息设置 失败.';
            return Response::json($rt);
        }
        //-------------日志 start-----------------
        $data = array(
            'merchant_id'    => Auth::user()->merchant_id,
            'user_id'    => Auth::user()->id,
            'type' => 47,
            'url' => 'merchant/merchant.json',
            'content' => json_encode($request->all()),
            'ip' => $request->ip(),
            'created_time' => date('Y-m-d H:i:s')
        );
        UserLog::create($data);
        //-------------日志 end-----------------
        
        $rt['errcode']=0;
        $rt['errmsg']='更新商户信息 成功';
        return Response::json($rt);
    }
    
    /**
     * 更新商户开放API密码
     * Author: songyongshang@dodoca.com
     */
    public function putMerchantDdcsecret(){
        $data_Merchant['ddcsecret'] = md5(time().rand(0,100000));
        //更新商户
        $rs_Merchant = Merchant::update_data(Auth::user()->merchant_id,$data_Merchant);
        if(!$rs_Merchant){
            $rt['errcode']=100010;
            $rt['errmsg']='更新开放API密码 失败.';
            return Response::json($rt);
        }
        //-------------日志 start-----------------
        $data = array(
            'merchant_id'    => Auth::user()->merchant_id,
            'user_id'    => Auth::user()->id,
            'type' => 54,
            'url' => '',
            'content' => $data_Merchant['ddcsecret'],
            'ip' => '',
            'created_time' => date('Y-m-d H:i:s')
        );
        UserLog::create($data);
        //-------------日志 end-----------------
    
        $rt['errcode']=0;
        $rt['errmsg']='更新商户信息 成功';
        $rt['data']=array(
            'ddcsecret'=>$data_Merchant['ddcsecret'],
        );
        return Response::json($rt);
    }

    /** 
     * 修改管理员密码
     * Author: songyongshang@dodoca.com
     */
    public function putModifypass(Request $request){
       //原登录密码
        if(empty($request['oldpassword'])){
            $rt['errcode']=100001;
            $rt['errmsg']='原登录密码 不能为空';
            return Response::json($rt);
        }else{
            if(!Hash::check($request['oldpassword'], Auth::user()->password)){
                $rt['errcode']=100006;
                $rt['errmsg']='原登录密码 不正确';
                return Response::json($rt);
            }
        }
        
        //新登录密码
        if(empty($request['password'])){
            $rt['errcode']=100002;
            $rt['errmsg']='新登录密码 不能为空';
            return Response::json($rt);
        }else if( strlen($request['password'])<6 ){
            $rt['errcode']=100001;
            $rt['errmsg']='密码至少6位';
            return Response::json($rt);
        }else {
            $pass_nocrypt = $request['password'];
            $data_User['password'] = bcrypt($request['password']);
        }
        //确认新密码
        if(empty($request['confirmpassword'])){
            $rt['errcode']=100003;
            $rt['errmsg']='确认新密码 不能为空';
            return Response::json($rt);
        }else if($request['confirmpassword'] != $request['password']){
            $rt['errcode']=100003;
            $rt['errmsg']='新密码与确认新密码 不相同';
            return Response::json($rt);
        }
        $data_User['password_at'] = date('Y-m-d H:i:s');
        $rs_User = User::where(['id'=>Auth::user()->id])->update($data_User);
        if(!$rs_User){
            $rt['errcode']=100010;  
            $rt['errmsg']='修改密码 失败';
            return Response::json($rt);
        }

        //SSO:修改账号密码
        $request['username'] = Auth::user()->username;
        $request['password'] = $pass_nocrypt;
        $request['mobile'] = Auth::user()->mobile;
        $rt_ssocheck = json_decode(SSOFun::changepasswd($request,Auth::user()->merchant_id,Auth::user()->id));
        if(empty($rt_ssocheck) || !isset($rt_ssocheck->errcode) || $rt_ssocheck->errcode!=0){
            $rt['errcode']=100027;
            $rt['errmsg']=is_object($rt_ssocheck)?$rt_ssocheck->errmsg:'SSO接口异常';
            return Response::json($rt);
        }
        unset($request['username']);
        
        //-------------日志 start-----------------
        $data = array(
            'merchant_id'    => Auth::user()->merchant_id,
            'user_id'    => Auth::user()->id,
            'type' => 49,
            'url' => 'merchant/merchant.json',
            'content' => json_encode($request),
            'ip' => $request->ip(),
            'created_time' => date('Y-m-d H:i:s')
        );
        UserLog::create($data);
        //-------------日志 end-----------------

        Auth::logout();
        
        $rt['errcode']=0;
        $rt['errmsg']='修改密码 成功';
        return Response::json($rt);
    }
    
    /**
     * curl
     * Author: songyongshang@dodoca.com
     */
    public static function curl($url,$data='',$method='GET') {
        $headers = array(
            'App-Key: 9d2ae8uaf83y3b45',
        );
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL,$url);
        curl_setopt($ch, CURLOPT_TIMEOUT,10);
        if(strtoupper($method)=='PUT'){
            $method='POST';
            $data['_method']='PUT';
        }else if(strtoupper($method)=='DELETE'){
            $method='POST';
            $data['_method']='DELETE';
        }
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        //curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); //不验证SSL接口 本地打开,服务器上注释掉 
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $cont = curl_exec($ch);
        if (curl_errno($ch)) {	//抓取异常
            return json_encode(array('state'=>'-1','cont'=>curl_error($ch),'url'=>$url,'errno'=>curl_errno($ch)));
        }
        curl_close($ch);
        return $cont;
    
    }
    
    public function redirect(){
        dd('a');
        redirect('manage/login');
    }

    public function repairMerchant(){
        //查日志,把错误的都修复过来
        $rs_userlog = UserLog::where(['type'=>10])->where('content','like','%"data_response":""%')
                    ->leftjoin('user','user.id','=','user_log.user_id')
                    ->select('user.username','user.mobile','user.is_admin',
                        'user_log.content','user_log.id','user_log.merchant_id','user_log.user_id')
                    ->offset(0)->limit(100)
                    ->orderBy('user_log.user_id', 'DESC')
                    ->get();
        //dd($rs_userlog);
        if(!empty($rs_userlog)){
            foreach ($rs_userlog as $key=>$val){
                $rs_content = array();
                $rs_content = json_decode($val['content']);
                //dd($rs_content);
                //SSO:注册
                $data_sso = array();
                $data_sso['data_id'] = $val['user_id'];
                $data_sso['data_level'] = $val['is_admin']==1?1:2;
                $data_sso['username'] = $val['username'];
                $data_sso['admin_mobile'] = $val['mobile'];
                $data_sso['password'] = $rs_content->data_request->password;
                $rt_register = json_decode(SSOFun::register($data_sso,$val['merchant_id'],$val['user_id']));
                
                if(isset($rt_register->errcode) && $rt_register->errcode==0){
                    $data_UserLog['content']=json_encode(array(
                        'data_request'=>$rs_content->data_request,
                        'data_response'=>$rt_register,
                    ));
                    $data_UserLog['type'] = 45;
                    UserLog::update_data($val['id'],$data_UserLog);
                }
//                 else{
//                     echo $val['mobile'].$data_sso['username'];
//                     dd($rt_register);
//                 }
            }
        }
    }

    public function ddcid_valid($data_merchant){
        if(empty($data_merchant)){
            return false;
        }
        $ddcid = 'ddc'.substr(md5($data_merchant['company'].time().rand(0,100000)),0, 13);
        $rs_ddcid = Merchant::where(['ddcid'=>$ddcid])->first();
        if(!empty($rs_ddcid)){
            $this->ddcid_valid($data_merchant);
        }else{
            return $ddcid;
        }
    }

    //开放Api：初始化数据ddcid,ddcsecret
    public function OpenApiInit(){
        //小程序id
        $arr_info = Merchant::chunk(100, function($list){
            if($list) {
                $dump = [];
                foreach($list as $row){
                    if( empty($row['ddcid']) || empty($row['ddcsecret']) ){
                        $data = array();
                        $data['ddcid'] = $this->ddcid_valid($row);
                        $data['ddcsecret'] = md5($data['ddcid'].time().rand(0,100000));
                        Merchant::where(['id'=>$row['id']])->update($data);
                    }
                }
            }
        });
    
        $rt = [ 'errcode'=>0, 'errmsg'=>'ok'];
        return Response::json($rt);
    }
}

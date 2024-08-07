<?php

/**
 * 小程序端注册小程序
 * @author wangshen@dodoca.com
 * @cdate 2017-12-5
 * 
 */
namespace App\Http\Controllers\Weapp\Register;

use Illuminate\Http\Request;
use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Facades\Member;


use App\Utils\SendMessage;
use App\Utils\CacheKey;
use Illuminate\Support\Facades\Cache;




class RegisterController extends Controller {

    
    public function __construct(){
        
        $this->merchant_id = Member::merchant_id();//商户id
        
    }
        
    /**
     * 发送手机验证码
     * @author wangshen@dodoca.com
     * @cdate 2017-12-5
     *
     * @param string $mobile 手机号
     * @param string $captcha 图片验证码
     */
    public function sendPhone(Request $request){
        
        //参数
        $params = $request->all();
        
        $mobile = isset($params['mobile']) ? $this->repalce_str($params['mobile']) : '';//手机号
        
        //注册页面前端生成的随机数
        $random = isset($params['random']) ? $params['random'] : '';
        
        if(!$random){
            return ['errcode' => 99001,'errmsg' => 'random参数缺失'];
        }
        
        $merchant_id = $this->merchant_id;//商户id
        
        
        if(!$merchant_id){
            return ['errcode' => 99001,'errmsg' => '商户ID不存在'];
        }
        
        if(!$mobile){
            return ['errcode' => 180001,'errmsg' => '请输入手机号'];
        }
        
        if(!is_numeric($mobile) || strlen($mobile) != 11){
            return ['errcode' => 180002,'errmsg' => '手机号格式不正确'];
        }
        
        
        
        //图片验证码判断
        $captcha = isset($params['captcha']) ? $this->repalce_str($params['captcha']) : '';
        
        if(!$captcha){
            return ['errcode' => 180003, 'errmsg' => '请输入图片验证码'];
        }
        
        $captcha_key = CacheKey::get_register_member_captcha_key($merchant_id, $random);
        $captcha_code = Cache::get($captcha_key);
        
        
        $captcha_code = isset($captcha_code) ? $captcha_code : '';
        
        if($captcha_code != strtolower($captcha)){
            return ['errcode' => 180004, 'errmsg' => '图片验证码不正确'];
        }
        
        
        //增加60秒内不能重复发送限制
        $sixty_key = CacheKey::get_member_sms_sixty_by_mobile_key($mobile, $merchant_id);
        $sixty_data = Cache::get($sixty_key);
        
        if($sixty_data){
            return ['errcode' => 180005,'errmsg' => '60秒内不能重复发送'];
        }
        
        
        //发送验证码
        $sms_str = $this->_createSmsStr();
        $sms_content = '您的验证码是：'.$sms_str;
        
        $result = SendMessage::send_sms($mobile,$sms_content,7);//7->小程序端注册小程序
        
        if($result){
            
            //短信验证码存入缓存，有效期5分钟
            $key = CacheKey::get_register_member_sms_by_mobile_key($mobile, $merchant_id);
            Cache::put($key, $sms_str, 5);   //有效期5分钟
            
            //增加60秒内不能重复发送限制
            Cache::put($sixty_key, $mobile, 1);
            
            
            return ['errcode' => 0,'errmsg' => '发送成功'];
        }else{
            return ['errcode' => 180006,'errmsg' => '发送失败'];
        }
        
        
    }
    
    
    
    /**
     * 立即注册
     * @author wangshen@dodoca.com
     * @cdate 2017-12-5
     *
     * @param string $mobile 手机号
     * @param string $sms_str 短信验证码
     * @param string $password 密码
     */
    public function doRegister(Request $request){
        
        //参数
        $params = $request->all();
        
        $mobile = isset($params['mobile']) ? $this->repalce_str($params['mobile']) : '';//手机号
        $sms_str = isset($params['sms_str']) ? $this->repalce_str($params['sms_str']) : '';//短信验证码
        $password = isset($params['password']) ? $params['password'] : '';//密码
        
        //注册页面前端生成的随机数
        $random = isset($params['random']) ? $params['random'] : '';
        
        if(!$random){
            return ['errcode' => 99001,'errmsg' => 'random参数缺失'];
        }
        
        $merchant_id = $this->merchant_id;//商户id
       
        
        
        if(!$merchant_id){
            return ['errcode' => 99001,'errmsg' => '商户ID不存在'];
        }
        
        if(!$mobile){
            return ['errcode' => 180001,'errmsg' => '请输入手机号'];
        }
        
        if(!is_numeric($mobile) || strlen($mobile) != 11){
            return ['errcode' => 180002,'errmsg' => '手机号格式不正确'];
        }
        
        if(!$sms_str){
            return ['errcode' => 180007,'errmsg' => '请输入短信验证码'];
        }
        
        //判断短信验证码
        $key = CacheKey::get_register_member_sms_by_mobile_key($mobile, $merchant_id);
        $sms_data = Cache::get($key);
        
        
        $sms_data = isset($sms_data) ? $sms_data : '';
        
        if(!$sms_data){
            return ['errcode' => 180008,'errmsg' => '短信验证码失效，请重新获取'];
        }
        
        if($sms_data != $sms_str){
            return ['errcode' => 180009,'errmsg' => '短信验证码错误'];
        }
        
        if(!$password){
            return ['errcode' => 180010,'errmsg' => '请输入密码'];
        }
        
        
        //调用微伙伴接口
        $register_data = [
            'mobile' => $mobile,
            'password' => $password,
            'from_mobile' => 2  //小程序来源
        ];
        
        //接口地址
        if(ENV('APP_ENV')=='production'){
            $apiurl = 'http://www.dodoca.com/useradd/xcxregister';
        }else{
            $apiurl = 'http://twww.dodoca.com/useradd/xcxregister';
        }
        
        $register_rs = mxCurl($apiurl, $register_data);
        $register_rs = json_decode($register_rs,true);
        
        
        if(isset($register_rs) && $register_rs['errcode'] == 0){
            
            //清除短信验证码缓存
            Cache::forget($key);
            
            return ['errcode' => 0,'errmsg' => '注册成功'];
            
        }else{
            
            return ['errcode' => 180011,'errmsg' => $register_rs['errmsg']];
            
        }
        
        
        
    }
    
    
    
    
    
    
    
    
    
    private function _createSmsStr()
    {
        /* 选择一个随机的方案 */
        mt_srand((double) microtime() * 1000000);
        $str = str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
        return $str;
    }
    
    public function repalce_str($str) {
        return str_replace(array("'",'"','>','<',"\\"),array('','','','',''),$str);
    }
    
}

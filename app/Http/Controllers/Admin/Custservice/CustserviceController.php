<?php

namespace App\Http\Controllers\Admin\Custservice;

use App\Http\Controllers\Controller;
//use App\Http\Controllers\Admin\Auth;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;
use App\Models\Merchant;
use App\Models\UserLog;
use App\Models\WeixinInfo;

class CustserviceController extends Controller {

    /**
     * 获取公共链接地址
     *
     * @return Response
     */
    public function getLink() {
        $rt['errcode']=0;
        $rt['errmsg']='获取公共链接地址';
        $rt['data'] = array();
        
        //客服中心 1.1 版本是否支持客服功能,是否管理员登录
        $custservice_link = config('custservice.custservice_link');
        $custservice_link .= '&customerId=xcx-'.Auth::user()->id.'&nickName='.Auth::user()->username;
        $merchant = Merchant::where(['id'=>Auth::user()->merchant_id,])->whereRaw('status!=-1')->first();
        
        $version = config('version');
        //客服中心
        if( !isset($version[$merchant['version_id']]['cust_service']) || empty($version[$merchant['version_id']]['cust_service']) || Auth::user()->is_admin!=1){
            $rt['data']['custservice_link'] = '';
        }else{
            $rt['data']['custservice_link'] = $custservice_link;
        }
        
        //客服中心 1.2  上海地区50家免费商家可以联系客服 
        $custservice_link_free = config('custservice.custservice_link_free');
        $custservice_link_free .= '&customerId=xcx-'.Auth::user()->id.'&nickName='.Auth::user()->username;
        //是否开通客服的免费商家
        $data_userlog = UserLog::get_data_of_custserviceWithFree();
        if( $merchant['version_id']==1 ){
            if(Auth::user()->is_admin==1 && isset($data_userlog['merchant_id'][Auth::user()->merchant_id])){
                $rt['data']['custservice_link'] = $custservice_link_free;
                $rt['data']['professional_support'] = 0;
            }else{
                $rt['data']['professional_support'] = 0;
            }
        }
        
        //点点秀（小场景）
        $xiu_link = 'https://xiu.dodoca.com/admin/index.html?token='.$this->encrypt('xiaochengxu:'.Auth::user()->merchant_id,'E','dodoca2014AdminJS');
        $merchant_info = Merchant::get_data_by_id(Auth::user()->merchant_id);
        
        if( !isset($version[$merchant['version_id']]['xiu']) || empty($version[$merchant['version_id']]['xiu'])){
            $rt['data']['xiu_link'] = '';
        }else{
            $rt['data']['xiu_link'] = $xiu_link;
        }
        
        //开放平台文档
        if( Auth::user()->is_admin==1 ){
            $rt['data']['doc_openapi'] = env('APP_URL').'/manage/helpcenter/help';
        }else{
            $rt['data']['doc_openapi'] = '';
        }
        return response::json($rt);
        //return Redirect::to(custservice_link);
    }
    
    /**
     * 函数名称:encrypt
     * 函数作用:加密解密字符串
     * 使用方法:
     * 加密     :encrypt('str','E','nowamagic');
     * 解密     :encrypt('被加密过的字符串','D','nowamagic');
     * 参数说明:
     * $string   :需要加密解密的字符串
     * $operation:判断是加密还是解密:E:加密   D:解密
     * $key      :加密的钥匙(密匙);
     *********************************************************************/
    
    static function encrypt($string, $operation, $key = 'dodoca2014AdminJS')
    {
        $key = md5($key);
        $key_length = strlen($key);
        $string = $operation == 'D' ? base64_decode($string) : substr(md5($string . $key), 0, 8) . $string;
        $string_length = strlen($string);
        $rndkey = $box = array();
        $result = '';
        for($i = 0; $i <= 255; $i++)
        {
            $rndkey[$i] = ord($key[$i % $key_length]);
            $box[$i] = $i;
        }
    
    
        for($j = $i = 0; $i < 256; $i++)
        {
    
            $j = ($j + $box[$i] + $rndkey[$i]) % 256;
            $tmp = $box[$i];
            $box[$i] = $box[$j];
            $box[$j] = $tmp;
        }
    
        for($a = $j = $i = 0; $i < $string_length; $i++)
        {
    
            $a = ($a + 1) % 256;
            $j = ($j + $box[$a]) % 256;
            $tmp = $box[$a];
            $box[$a] = $box[$j];
            $box[$j] = $tmp;
            $result .= chr(ord($string[$i]) ^ ($box[($box[$a] + $box[$j]) % 256]));
        }
    
        if($operation == 'D')
        {
    
            if(substr($result, 0, 8) == substr(md5(substr($result, 8) . $key), 0, 8))
            {
                return substr($result, 8);
            }
            else
            {
                return '';
            }
        }
        else
        {
            return str_replace('=', '', base64_encode($result));
        }
    }
}

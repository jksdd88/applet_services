<?php
namespace App\Utils;

use App\Models\UserLog;

//关键词过滤
$_REQUEST = filter_string($_REQUEST);
$_GET = filter_string($_GET);
$_POST = filter_string($_POST);

class SSOFun
{
/**
     * SSO:检测帐号是否可以注册
     * @param string $val
     * @param int $exit
     */
    public static function checkaccount($data,$merchant_id='',$user_id=''){
        $data_curl['type'] = 1;
        $data_curl['app_env'] = ENV('APP_ENV');
        $data_curl['username'] = $data['username'];
        if(isset($data['admin_mobile'])&&!empty($data['admin_mobile'])){
            $data_curl['mobile'] = $data['admin_mobile'];
        }else{
            $data_curl['mobile'] = $data['mobile'];
        }
        //SSO接口域名
        if(ENV('APP_ENV')=='production'){
            $url = 'https://sso.dodoca.com/accountapi/checkaccount';
        }else{
            $url = 'https://tsso.dodoca.com/accountapi/checkaccount';
        }
        $rt_Mxcurl = mxCurl($url, $data_curl);
        
        //----------日志 start----------
        $data_UserLog['merchant_id']=$merchant_id;
        $data_UserLog['user_id']=$user_id;
        $data_UserLog['type']=9;
        $data_UserLog['url']='merchant/merchant.json';
        $data_UserLog['content']=json_encode(array(
            'data_request'=>$data_curl,
            'data_response'=>$rt_Mxcurl,
        ));
        $data_UserLog['ip']=get_client_ip();
        $data_UserLog['created_time']=date('Y-m-d H:i:s');
        $data_UserLog['updated_time']=date('Y-m-d H:i:s');
        UserLog::insertGetId($data_UserLog);
        //----------日志 end----------
        
        return $rt_Mxcurl;
    }
    
    /**
     * SSO:注册帐号
     * @param string $val
     * @param int $exit
     */
    public static function register($data,$merchant_id='',$user_id='')
    {
        $data_curl['type'] = 1;
        $data_curl['app_env'] = ENV('APP_ENV');
        $data_curl['data_id'] = $data['data_id'];
        $data_curl['data_level'] = $data['data_level'];//1.主账号 2.子账号
        $data_curl['username'] = $data['username'];
        if(isset($data['admin_mobile'])&&!empty($data['admin_mobile'])){
            $data_curl['mobile'] = $data['admin_mobile'];
        }else{
            $data_curl['mobile'] = $data['mobile'];
        }
        $data_curl['password'] = encrypt($data['password'],'E','dodoca_sso');
        //SSO接口域名
        if(ENV('APP_ENV')=='production'){
            $url = 'https://sso.dodoca.com/accountapi/register';
        }else{
            $url = 'https://tsso.dodoca.com/accountapi/register';
        }
        //dd($data_curl);
        $rt_Mxcurl = mxCurl($url, $data_curl);
        
        //----------日志 start----------
        $data_UserLog['merchant_id']=$merchant_id;
        $data_UserLog['user_id']=$user_id;
        $request['password'] = md5($data['password']);
        $data_UserLog['type']=10;
        $data_UserLog['url']='merchant/merchant.json';
        $data_UserLog['content']=json_encode(array(
            'data_request'=>is_object($data)?$data->all():$data,
            'data_response'=>$rt_Mxcurl,
        ));
        $data_UserLog['ip']=get_client_ip();
        $data_UserLog['created_time']=date('Y-m-d H:i:s');
        $data_UserLog['updated_time']=date('Y-m-d H:i:s');
        UserLog::insertGetId($data_UserLog);
        //----------日志 end----------
        
        return $rt_Mxcurl;
    }
    
    /**
     * SSO:换绑手机号检测
     * @param string $val
     * @param int $exit
     */
    public static function checkmobile($data,$merchant_id='',$user_id='')
    {
        $data_curl['type'] = 1;
        $data_curl['app_env'] = ENV('APP_ENV');
        $data_curl['data_id'] = $data['data_id'];
        $data_curl['username'] = $data['username'];
        $data_curl['old_mobile'] = $data['old_mobile'];
        $data_curl['mobile'] = $data['mobile'];
        //SSO接口域名
        if(ENV('APP_ENV')=='production'){
            $url = 'https://sso.dodoca.com/accountapi/checkmobile';
        }else{
            $url = 'https://tsso.dodoca.com/accountapi/checkmobile';
        }
        $rt_Mxcurl = mxCurl($url, $data_curl);
    
        //----------日志 start----------
        $data_UserLog['merchant_id']=$merchant_id;
        $data_UserLog['user_id']=$user_id;
        //$request['password'] = md5($data['password']);
        $data_UserLog['type']=13;
        $data_UserLog['url']='merchant/merchant.json';
        $data_UserLog['content']=json_encode(array(
            'data_request'=>$data,
            'data_response'=>$rt_Mxcurl,
        ));
        $data_UserLog['ip']=get_client_ip();
        $data_UserLog['created_time']=date('Y-m-d H:i:s');
        $data_UserLog['updated_time']=date('Y-m-d H:i:s');
        UserLog::insertGetId($data_UserLog);
        //----------日志 end----------
    
        return $rt_Mxcurl;
    }
    
    /**
     * SSO:换绑手机号
     * @param string $val
     * @param int $exit
     */
    public static function bindmobile($data,$merchant_id='',$user_id='')
    {
        $data_curl['type'] = 1;
        $data_curl['app_env'] = ENV('APP_ENV');
        $data_curl['data_id'] = $data['data_id'];
        $data_curl['username'] = $data['username'];
        $data_curl['old_mobile'] = $data['old_mobile'];
        $data_curl['mobile'] = $data['mobile'];
        //SSO接口域名
        if(ENV('APP_ENV')=='production'){
            $url = 'https://sso.dodoca.com/accountapi/bindmobile';
        }else{
            $url = 'https://tsso.dodoca.com/accountapi/bindmobile';
        }
        $rt_Mxcurl = mxCurl($url, $data_curl);
    
        //----------日志 start----------
        $data_UserLog['merchant_id']=$merchant_id;
        $data_UserLog['user_id']=$user_id;
        //$request['password'] = md5($data['password']);
        $data_UserLog['type']=14;
        $data_UserLog['url']='merchant/merchant.json';
        $data_UserLog['content']=json_encode(array(
            'data_request'=>$data,
            'data_response'=>$rt_Mxcurl,
        ));
        $data_UserLog['ip']=get_client_ip();
        $data_UserLog['created_time']=date('Y-m-d H:i:s');
        $data_UserLog['updated_time']=date('Y-m-d H:i:s');
        UserLog::insertGetId($data_UserLog);
        //----------日志 end----------
    
        return $rt_Mxcurl;
    }

    /**
     * SSO:修改账号密码
     * @param string $val
     * @param int $exit
     */
    public static function changepasswd($data,$merchant_id='',$user_id='')
    {
        $data_curl['type'] = 1;
        $data_curl['app_env'] = ENV('APP_ENV');
        $data_curl['data_id'] = $data['data_id'];
        $data_curl['username'] = $data['username'];
        $data_curl['mobile'] = $data['mobile'];
        $data_curl['password'] = encrypt($data['password'],'E','dodoca_sso');
        //SSO接口域名
        if(ENV('APP_ENV')=='production'){
            $url = 'https://sso.dodoca.com/accountapi/changepasswd';
        }else{
            $url = 'https://tsso.dodoca.com/accountapi/changepasswd';
        }
        $rt_Mxcurl = mxCurl($url, $data_curl);
    
        //----------日志 start----------
        $data_UserLog['merchant_id']=$merchant_id;
        $data_UserLog['user_id']=$user_id;
        $request['password'] = md5($data['password']);
        $data_UserLog['type']=15;
        $data_UserLog['url']='merchant/merchant.json';
        $data_UserLog['content']=json_encode(array(
            'data_request'=>$data,
            'data_response'=>$rt_Mxcurl,
        ));
        $data_UserLog['ip']=get_client_ip();
        $data_UserLog['created_time']=date('Y-m-d H:i:s');
        $data_UserLog['updated_time']=date('Y-m-d H:i:s');
        UserLog::insertGetId($data_UserLog);
        //----------日志 end----------
    
        return $rt_Mxcurl;
    }
    
    /**
     * SSO:删除账号
     * @param string $val
     * @param int $exit
     */
    public static function deleteaccount($data,$merchant_id='',$user_id='')
    {
        $data_curl['type'] = 1;
        $data_curl['app_env'] = ENV('APP_ENV');
        $data_curl['data_id'] = $data['data_id'];
        $data_curl['username'] = $data['username'];
        $data_curl['mobile'] = $data['mobile'];
        //dd($data_curl);
        $rt_serialize = serialize($data_curl);
        $rt_base64encode['param'] = base64_encode(encrypt($rt_serialize, 'E', 'dodoca_sso'));
        //dd($rt_base64encode);
        //SSO接口域名
        if(ENV('APP_ENV')=='production'){
            $url = 'https://sso.dodoca.com/accountapi/delaccount';
        }else{
            $url = 'https://tsso.dodoca.com/accountapi/delaccount';
        }
        //dd($rt_base64encode);
        $rt_Mxcurl = mxCurl($url, $rt_base64encode);
        //dd($rt_Mxcurl);
        //----------日志 start----------
        $data_UserLog['merchant_id']=$merchant_id;
        $data_UserLog['user_id']=$user_id;
        //$request['password'] = md5($data['password']);
        $data_UserLog['type']=17;
        $data_UserLog['url']='merchant/merchant.json';
        $data_UserLog['content']=json_encode(array(
            'data_request'=>$data,
            'data_response'=>$rt_Mxcurl,
        ));
        $data_UserLog['ip']=get_client_ip();
        $data_UserLog['created_time']=date('Y-m-d H:i:s');
        $data_UserLog['updated_time']=date('Y-m-d H:i:s');
        UserLog::insertGetId($data_UserLog);
        //----------日志 end----------
    
        return $rt_Mxcurl;
    }
}
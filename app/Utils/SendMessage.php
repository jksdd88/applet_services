<?php
/**
 * @ 发送短信验证码 注：不可随意制定短信内容
 * @author wangyu
 * @time 2017-05-18
 */
namespace App\Utils;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;
use App\Models\SmsHistory;

class SendMessage
{

    /**
     * @param $mobile       手机号
     * @param $content      短信内容
     * @param int $counts   每天限制条数
     * @param int $type     消耗渠道
     * @return bool|string
     */
    public static function send_sms($mobile,$content,$type,$counts=5){

        $mobile = trim($mobile);
        if(!$mobile) {
            return 'mobile is null';
        }
        if(!is_numeric($mobile)) {
            return 'mobile is error:'.$mobile.":";
        }

        $key = $mobile."s".date("Y-m-d");
        $send_count = Cache::get($key);

        if($send_count < $counts)
        {
            Cache::increment($key, 1);

            $maccont = env('SINASRV_SMS_USER_APPLET');
            $mpasswd = env('SINASRV_SMS_PASS_APPLET');
            $postData = array('account' => $maccont,'pswd' => $mpasswd,'mobile' => urlencode($mobile),'msg' => urlencode($content),'needstatus' => true,'extno' => '');

//            $client = new Client(['verify' => false]);
//            $result = $client->request('POST','http://175.102.15.131/msg/HttpSendSM',['form_params' => $postData])->getBody();

            $config = empty(env('PROXY_IP')) ? null : array('proxy' => env('PROXY_IP'). ':' . env('PROXY_PORT'));
            $result = mxCurl('http://175.102.15.131/msg/HttpSendSM'.'?'.http_build_query($postData), array(), true, $config);

            SmsHistory::insert_data(array('sms_content' => $content ,'sms_recipient' => $mobile ,'type' => $type ,'ip' => get_client_ip() ,'send_status' => $result,'send_time' => date('Y-m-d H:i:s')));

            return $result;
        } else {
            return 'sms count:'.$send_count;
        }
        return false;
    }
}
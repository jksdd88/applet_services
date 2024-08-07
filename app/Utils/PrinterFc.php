<?php
/**
 * Created by PhpStorm.
 * User: lizhenheng
 * Date: 2018-05-07
 * Time: 下午 01:57
 */
namespace App\Utils;

use Config;
use App\Utils\Weixin\Http;

class PrinterFc
{

    private $status = array();

    public function __construct()
    {
        
    }

    //开始打印
    public static function sendFreeMessage($Message) {

        $key=md5($Message['api_key'].$Message['dno']."aykj");
        if(isset($Message['api_key'])) unset($Message['api_key']);
        $Message['key']=$key;
        $Message['op']="pf";
        if($Message['mode']==1){ $Message['content']='|1'.$Message['content']; }
        $Message['mode']="|".$Message['mode'];
        $status = self::sendMessage ("http://api.aykj0577.com/WS/DealData.ashx?", $Message); // 打印

        return $status;
    }

    public static function queryState($Message) {//打印机状态
        $key=md5($Message['api_key'].$Message['dno']."aykj");
        $Message['key']=$key;
        $Message['op']="cx";
        if(isset($Message['api_key'])) unset($Message['api_key']);
        $Message['mode']="cx";
        //dump($Message);
        $status = self::sendMessage ("http://api.aykj0577.com/WS/DealData.ashx?", $Message); // 查询
        return $status;
    }

    public static function oder($Message) {//查询订单状态
        $key=md5($Message['api_key'].$Message['dno']."aykj");
        if(isset($Message['api_key'])) unset($Message['api_key']);
        $Message['key']=$key;
        $Message['op']="cxdd";

        $Message['mode']="cxdd";
        $status = self::sendMessage ("http://api.aykj0577.com/WS/DealData.ashx?", $Message); // 查询
        return $status;
    }

    //绑定打印机
    public static function setFcprint($Message) {
//        $Message['unm']="dodoca"; //fc账号
//        $Message['dno']="670214601"; //打印机编号
//        $Message['api_key'] = "t0t2l8t446tp8628";
        $key=md5($Message['dno'].$Message['api_key']."printer");
        if(isset($Message['api_key'])) unset($Message['api_key']);
        $Message['key']=$key;
        $Message['op']="add";
//        $Message['unm']=""; //fc账号
//        $Message['dno']=""; //打印机编号


        $status = self::sendMessage ("http://api.aykj0577.com/WS/CealData.ashx?", $Message); // 绑定
        return $status;
    }

    //删除打印机
    public static function deleteFcprint($Message) {
//        $Message['unm']="dodoca"; //fc账号
//        $Message['dno']="670214601"; //打印机编号
//        $Message['api_key'] = "t0t2l8t446tp8628";
        $key=md5($Message['dno'].$Message['api_key']."printer");
        if(isset($Message['api_key'])) unset($Message['api_key']);
        $Message['key']=$key;
        $Message['op']="dele";
        $status = self::sendMessage ("http://api.aykj0577.com/WS/CealData.ashx?", $Message); // 删除
        return $status;
    }

    public static function sendMessage($url, $post) {
        $options = array (
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => FALSE,
            CURLOPT_BINARYTRANSFER => true,
            CURLOPT_POSTFIELDS => $post
        );

        $ch = curl_init ( $url );
        curl_setopt_array ( $ch, $options );
        $result = curl_exec ( $ch );
        if ($result === FALSE) {
            $result = "cURL Error: " . curl_error ( $ch );
        }
        curl_close ( $ch );
        return $result;
    }
}
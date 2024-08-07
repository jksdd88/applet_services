<?php
/**
 * @ SocketLog
 * @author wangyu
 * @time 2017-05-24
 */
namespace App\Utils;

include_once 'SocketLog/slog.function.php';

class SocketLog
{
    /**
     * @ 推送日志到 Socket-Service
     * @param $txt                  日志信息
     * @param string $user          client_id 用来辨别身份 身份匹配 (简单点说这边指定ABC，客户端只有通过ABC才能接收日志信息)
     * @param bool $flag
     * @param string $filename      'log' -> 一般日志 ，'error'->错误日志 ，'info'->信息日志 ，'warn'->警告日志 ，'trace'->输入日志同时会打出调用栈 ，'alert'->将日志以alert方式弹出
     */
    public static function slog($txt ,$client_id = 'ddc2017' ,$flag = false ,$filename = 'log')
    {
        //配置
        slog(array(
            'host'                   => '121.43.183.199',//$_SERVER['SINASRV_SOCKETLOG_SERVERS'],	//websocket服务器地址，默认localhost
            'optimize'              => $flag,				//是否显示利于优化的参数，如果运行时间，消耗内存等，默认为false
            'show_included_files' => false,				//是否显示本次程序运行加载了哪些文件，默认为false
            'error_handler'        => $flag,				//是否接管程序错误，将程序错误显示在console中，默认为false
            'force_client_ids'    => array(				//日志强制记录到配置的client_id,默认为空,client_id必须在allow_client_ids中
                $client_id,
            ),
            'allow_client_ids'    => array(				//限制允许读取日志的client_id，默认为空,表示所有人都可以获得日志。
                $client_id,
            ),
        ),'config');

        //输出日志
        slog($txt , $filename);  							//slog('msg','log','color:red;font-size:20px;');//自定义日志的样式，第三个参数为css样式
    }
}
<?php
/**
 * User: yanghui@dodoca.com
 * Desc: 思普瑞特 小票打印机
 */

namespace App\Utils;

use Config;
use App\Utils\Weixin\Http;

class Printer
{

    const API = 'http://118.26.65.219:7003/yun/k/api/';

    private $printeruid;	//打印机用户编号
	private $printerid;		//打印机编号
	private $partnerID;		//打印机身份ID
	private $partnerKEY;	//打印机身份ID密码

	private $errorMsg = [
		'0x000'	=> '用户编号不存在',
		'0x001'	=> '打印机编号不存在',
		'0x002' => '签名错误',
		'0'		=> '初始化失败',
	];
	//序列号 1801715577 设备id 1711150074 型号POS58 版本号UC15EQAR03A10E1G
    public function __construct()
    {
		$this->printeruid = '1088201803198431'; //用户编号  SPRT打印机管理系统添加
		$this->printerid = '1801715577'; //打印机编号
		$this->partnerID = '2088703603635978'; //身份id SPRT打印机管理系统添加打印机后生成
		$this->partnerKEY = 'TSCil97z1zTkgDnWfrnYR78VlylC2pzK'; //身份密码 SPRT打印机管理系统添加打印机后生成
    }

	/*
	 * @name 初始化打印机接口
	 * @return {"message":true} 初始化成功 {"message":false} 初始化失败
	 */
	public function init()
	{
		$data = ['uid'=>$this->printeruid];
		return $this->postCurl(static::API.'do_init_printer.php', $data);
	}
	
	/**
     * @name 打印订单接口
     * @param string $oid 订单号
	 * @param string $content 打印内容
	 * @return {"state":$state,"taskid":$taskid} 返回打印状态及任务ID
	 * @note 打印状态包含：0(等待)、1(成功)、2(失败)，任务ID为一字符串，用于查询打印状态 
	 */
	public function do_print($oid,$content)
	{
		$data = [
			'uid'			=> $this->printeruid,
			'time'			=> time(),
			'printerid'		=> $this->printerid,
			'partnerID'		=> $this->partnerID,
			'partnerKEY'	=> $this->partnerKEY,
			'oid'			=> $oid,
			'content'		=> $content,
		];

		$data['sign'] = $this->getSign($data);

		//print_r($data);exit;

		return $this->postCurl(static::API.'do_print.php', $data);
	}
	
	/**
     * @name 查询订单打印结果接口
     * @param string $oid 订单号
	 * @return {"state":$state} 返回此订单打印状态，打印状态包含：0(等待)、1(成功)、2(失败)
	 */
	public function get_result($oid)
	{
		$data = [
			'uid'			=> $this->printeruid,
			'oid'			=> $oid,
		];
		
		return $this->postCurl(static::API.'do_get_order_print_result.php', $data);
	}

	/**
     * @name 查询打印机订单列表接口
     * @param string $stime 开始时间 2018-01-05
	 * @param string $etime 结束时间 2018-08-05
	 * @return {"Success":1,"Waiting":0,"Failed":0} 返回此打印机订单打印状态统计，成功的个数，等待的个数，失败的个数
	 */
	public function get_list($stime,$etime)
	{
		$data = [
			'uid'			=> $this->printeruid,
			'time'			=> time(),
			'printerid'		=> $this->printerid,
			'partnerID'		=> $this->partnerID,
			'partnerKEY'	=> $this->partnerKEY,
			'riqi_from'		=> $stime,
			'Riqi_to'		=> $etime,
		];

		$data['sign'] = $this->getSign($data);
		
		return $this->postCurl(static::API.'do_get_printer_order_list.php', $data);
	}

	/**
     * @name 查询打印机状态接口
	 * @return {"state": state } 打印机状态，具体状态包括0在线、1缺纸、2忙碌、 3其它错误、4离线
	 */
	public function get_status()
	{
		$data = [
			'uid'			=> $this->printeruid,
			'time'			=> time(),
			'printerid'		=> $this->printerid,
			'partnerID'		=> $this->partnerID,
			'partnerKEY'	=> $this->partnerKEY,
		];

		$data['sign'] = $this->getSign($data);
		
		return $this->postCurl(static::API.'do_get_printer_state.php', $data);
	}

	/**
     * @name 异步发送打印数据接口 发送打印数据接口是一个异步的过程 如需查询订单的打印状态可调用查询订单打印结果接口
     * @param string $oid 订单号
	 * @param string $content 打印内容
	 * @return {"message":true} 发送成功，{"message":false} 发送失败
	 */
	public function print_data($oid,$content)
	{
		$data = [
			'uid'			=> $this->printeruid,
			'time'			=> time(),
			'printerid'		=> $this->printerid,
			'partnerID'		=> $this->partnerID,
			'partnerKEY'	=> $this->partnerKEY,
			'oid'			=> $oid,
			'content'		=> $content,
		];

		$data['sign'] = $this->getSign($data);
		
		return $this->postCurl(static::API.'do_print_data.php', $data);
	}

	/**
     * @name 获取打印机信息接口 查询在指定的时间段内新添加的打印机
     * @param string $stime 开始时间 2018-01-05
	 * @param string $etime 结束时间 2018-08-05
	 * @return [{"printerid":"99","riqi":"2016-09-14 17:14:45","province":"\u6cb3\u5317\u7701","city":"\u5510\u5c71\u5e02","state":3,"Success":3,"Waiting":12,"Failed":0},...]
	 */
	public function get_printer_info($stime,$etime)
	{
		$data = [
			'uid'			=> $this->printeruid,
			'time'			=> time(),
			'printerid'		=> $this->printerid,
			'partnerID'		=> $this->partnerID,
			'partnerKEY'	=> $this->partnerKEY,
			'riqi_from'		=> $stime,
			'Riqi_to'		=> $etime,
		];

		$data['sign'] = $this->getSign($data);
		
		return $this->postCurl(static::API.'do_get_printer_info.php', $data);
	}

	/**
     * @name 打印机状态统计数据接口
	 * @return {"normalCount":0,"outOfPaperCount":0,"busyCount":0,"breakdownCount":0,,"otherErrorCount":0}
	 * @note 即：正常、缺纸、忙碌、离线、其它错误的打印机个数
	 */
	public function get_state_count()
	{
		$data = [
			'uid'			=> $this->printeruid,
		];
		
		return $this->postCurl(static::API.'do_get_printers_state_count.php', $data);
	}

	public function getSign($data)
	{
		$params = array(
			'printerid' => $this->printerid,
			'time' => $data['time']
		);
		ksort($params);
        $stringToBeSigned = $this->partnerID;
        foreach ($params as $k => $v) {
            $stringToBeSigned .= urldecode($k . $v);
        }
        unset($k, $v);
        $stringToBeSigned .= $this->partnerKEY;
        return strtoupper(md5($stringToBeSigned));
	}

	//
    public function postCurl($url , $data){
        $ch = curl_init($url);
		curl_setopt($ch,CURLOPT_POST,1);
		curl_setopt($ch,CURLOPT_HEADER,0);
		curl_setopt($ch,CURLOPT_FRESH_CONNECT,1);
		curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
		curl_setopt($ch,CURLOPT_FORBID_REUSE,1);
		curl_setopt($ch,CURLOPT_POSTFIELDS,$data);
		$response =  curl_exec($ch);
		$errno = curl_errno($ch);
        $error = curl_error($ch);
		curl_close($ch);
		if($errno) {
			return ['errcode'=>$errno,'errmsg'=>$error,'data'=>null ];
		} else {
			$response = json_decode($response,true);
			if(isset($response['message'])) {
				if($response['message']==false){
					return ['errcode'=>'false','errmsg'=>$this->errorMsg[$response['message']]];
				} elseif(array_key_exists((string)$response['message'],$this->errorMsg)) {
					return ['errcode'=>$response['message'],'errmsg'=>$this->errorMsg[$response['message']]];
				} else {
					return ['errcode'=>0,'errmsg'=>'error','data'=>$response];
				}
			} else {
				return ['errcode'=>0,'data'=>$response];
			}
		}
    }
}
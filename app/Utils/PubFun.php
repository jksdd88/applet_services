<?php

//关键词过滤
$_REQUEST = filter_string($_REQUEST);
$_GET = filter_string($_GET);
$_POST = filter_string($_POST);

/**
 * 打印函数
 * @param string $val
 * @param int $exit
 */
function s($val = '', $exit = 0)
{

    $val = $val === '' ? time() : $val;
    echo "<pre>";
    print_r($val);
    echo "</pre>";
    echo("<hr>");
    if ($exit) exit;
    return;

    echo("<pre style='background:#ffffff'>");
    if (is_bool($val)) {
        var_dump($val);
    } else {
        print_r($val);
    }
    echo("</pre>");
    echo("<hr>");

}

/***
 * 读取用户IP
 * @return string
 */
function get_client_ip()
{
    if(isset($_SERVER["HTTP_X_FORWARDED_FOR"])){
        $onlineIP = $_SERVER["HTTP_X_FORWARDED_FOR"];
        if(strpos($onlineIP, ',') !== false)
        {
            $t = explode(',', $onlineIP);
            $onlineIP = $t[0];
        }
        return $onlineIP;
    }else{
        return \Illuminate\Support\Facades\Request::getClientIp();
    }
//    if(getenv("HTTP_X_FORWARDED_FOR")) {
//        $onlineIP = getenv("HTTP_X_FORWARDED_FOR");
//    } elseif(getenv('HTTP_X_REAL_IP')) {
//        $onlineIP = getenv('HTTP_X_REAL_IP');
//    } elseif(getenv("HTTP_CLIENT_IP")) {
//        $onlineIP = getenv("HTTP_CLIENT_IP");
//    } elseif(getenv("REMOTE_ADDR")) {
//        $onlineIP = getenv("REMOTE_ADDR");
//    } else {
//        $onlineIP = $_SERVER['REMOTE_ADDR'];
//    }
//    if(strpos($onlineIP,',')!==false)
//    {
//        $t=explode(',',$onlineIP);
//        $onlineIP=$t[0];
//    }
//    return $onlineIP;
}

/***
 * 获取七牛图片链接域名
 * @return string
 */
function getImageUrl($url)
{
    if (stripos($url, 'http://') === FALSE && stripos($url, 'https://') === FALSE) {
        $url = env('QINIU_DOMAIN') . $url;
    }
    return $url;
}

/***
 * @name curl
 * @param  $curl string 地址
 * @param  $data string|array 数据
 * @param  $is_post bool 是否post请求
 * @param  $config array  [ 'proxy' => '127.0.0.1' , 'timeout' => 5]
 * @param
 * @return string
 */
function mxCurl($url, $data, $is_post = true, $config = array())
{
    $ch = curl_init();
    if (!$is_post && !empty($data))//get 请求
    {
        $url = $url . '?' . http_build_query($data);
    }
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, $is_post);
    if ($is_post) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    }
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, isset($config['timeout']) && !empty($config['timeout']) ? $config['timeout'] : 10);
    !isset($config['proxy']) || empty($config['proxy']) ?: curl_setopt($ch, CURLOPT_PROXY, $config['proxy']);

    $info = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $info;
}

/*
* 函数名称:encrypt
* 函数作用:加密解密字符串
* 使用方法:
* 加密     :encrypt('str','E','nowamagic');
* 解密     :encrypt('被加密过的字符串','D','nowamagic');
* 参数说明:
* $string   :需要加密解密的字符串
* $operation:判断是加密还是解密:E:加密   D:解密
* $key      :加密的钥匙(密匙);
*/
function encrypt($string, $operation, $key = 'applet_services')
{
    $key = md5($key);
    $key_length = strlen($key);
    $string = $operation == 'D' ? base64_decode($string) : substr(md5($string . $key), 0, 8) . $string;
    $string_length = strlen($string);
    $rndkey = $box = array();
    $result = '';

    for ($i = 0; $i <= 255; $i++) {
        $rndkey[$i] = ord($key[$i % $key_length]);
        $box[$i] = $i;
    }

    for ($j = $i = 0; $i < 256; $i++) {
        $j = ($j + $box[$i] + $rndkey[$i]) % 256;
        $tmp = $box[$i];
        $box[$i] = $box[$j];
        $box[$j] = $tmp;
    }

    for ($a = $j = $i = 0; $i < $string_length; $i++) {
        $a = ($a + 1) % 256;
        $j = ($j + $box[$a]) % 256;
        $tmp = $box[$a];
        $box[$a] = $box[$j];
        $box[$j] = $tmp;
        $result .= chr(ord($string[$i]) ^ ($box[($box[$a] + $box[$j]) % 256]));
    }

    if ($operation == 'D') {
        if (substr($result, 0, 8) == substr(md5(substr($result, 8) . $key), 0, 8)) {
            return substr($result, 8);
        } else {
            return '';
        }
    } else {
        return str_replace('=', '', base64_encode($result));
    }
}

/**
 * 查找含指定字符的值在数组内的key
 * @param $str
 * @param $array
 * @return bool|int|string
 * @author: tangkang@dodoca.com
 */
function array_search_like($str, $array)
{
    foreach ($array as $key => $value) {
        if (mb_strpos($value, $str) !== false) {
            return $key;
        }
    }
    return false;
}


/**
 * 用于数据请求过滤处理
 * @param $data
 * @return array|mixed
 */
function filter_string($data)
{
    if(!is_array($data)) return;
    $file_keyword = get_filter_word();
    foreach($data as $key=>$val)
    {
        $data[$key] = is_array($val) ? $val : str_ireplace($file_keyword,'',$val);
    }
    return $data;
}

//参数需要过滤的敏感词
function get_filter_word()
{
    $xxsword = get_filter_xssword();
    $sqlword = array("insert","update","delete","select","sleep","truncate","union","GROUP BY","substr","concat","length(","INFORMATION_SCHEMA","weixin2014",);
    return array_merge($sqlword,$xxsword);
}
//XSS
function get_filter_xssword()
{
    //return array("红包","一元","1元","壹元");
    return array("红包","一元","1元","壹元","init_src","onerror","eval(","unescape","document","navigator","indexOf","MicroMessenger","appendChild","getElementsBy","outerHTML","script","setTimeout","function","getScript",".js","onabort","onactivate","onafterprint","onafterupdate","onbeforeactivate","onbeforecopy","onbeforecut","onbeforedeactivate","onbeforeeditfocus","onbeforepaste","onbeforeprint","onbeforeunload","onbeforeupdate","onblur","onbounce","oncellchange","onchange","oncontextmenu","oncontrolselect","oncopy","oncut","ondataavailable","ondatasetchanged","ondatasetcomplete","ondblclick","ondeactivate","ondrag","ondragend","ondragenter","ondragleave","ondragover","ondragstart","ondrop","onerrorupdate","onfilterchange","onfinish","onfocus","onfocusin","onfocusout","onhelp","onkeydown","onkeypress","onkeyup","onlayoutcomplete","onload","onlosecapture","onmousedown","onmouseenter","onmouseleave","onmousemove","onmouseout","onmouseover","onmouseup","onmousewheel","onmove","onmoveend","onmovestart","onpaste","onpropertychange","onreadystatechange","onreset","onresize","onresizeend","onresizestart","onrowenter","onrowexit","onrowsdelete","onrowsinserted","onscroll","onselect","onselectionchange","onselectstart","onstart","onstop","onsubmit","onunload","fromcharcode","user(","alert(","expression","innerHTML","innerText","javascript","vbscript","<applet",".xml","<object","<frameset","<ilayer","<layer","<bgsound");//,"<meta",,"<iframe","<frame","onclick"
}

/**
 * 冒泡排序
 * @author zhangchangchun@dodoca.com
 * date 2017-11-28
 * arr 需要排序的数组
 * key 排序字段
 * sort 排序方式
 */
function mArrSort($arr,$key,$sort='asc'){
	$count=count($arr);
	$sort=strtolower($sort);
	for($i=0;$i<$count;$i++){
		for($j=$count-1; $j>$i; $j--){
			if($sort=='asc'){
				if($arr[$j][$key]<$arr[$j-1][$key]){
					$temp=$arr[$j];
					$arr[$j]=$arr[$j-1];
					$arr[$j-1]=$temp;
				}
			}elseif($sort=='desc'){
				if($arr[$j][$key]>$arr[$j-1][$key]){
					$temp=$arr[$j];
					$arr[$j]=$arr[$j-1];
					$arr[$j-1]=$temp;
				}
			}
		}
	}
	return $arr;
}

// 过滤掉emoji表情
function filterEmoji($str){
    $str = preg_replace_callback(
        '/./u',
        function (array $match) {
            return strlen($match[0]) >= 4 ? '' : $match[0];
        },
        $str);

    return $str;
}
/**
 * 过滤4字节utf8mb4字符(在excel中异常)
 * @param $str
 * @return mixed
 */
function filter_emoji($str) {
    $regex = '/(\\\u[ed][0-9a-f]{3})/i';
    $str = json_encode($str);
    $str = preg_replace($regex, '', $str);
    return json_decode($str);
}

/**
 * 字符截取 支持UTF8/GBK
 * @param $string
 * @param $length
 * @param $dot
 */
function str_cut($string, $length, $dot = '...', $charset = 'utf-8') {
    $strlen = strlen($string);
    if($strlen <= $length) return $string;
    $string = str_replace(array(' ','&nbsp;', '&amp;', '&quot;', '&#039;', '&ldquo;', '&rdquo;', '&mdash;', '&lt;', '&gt;', '&middot;', '&hellip;'), array('∵',' ', '&', '"', "'", '“', '”', '—', '<', '>', '·', '…'), $string);
    $strcut = '';
    if(strtolower($charset) == 'utf-8') {
        $length = intval($length-$length/3);
        $n = $tn = $noc = 0;
        while($n < strlen($string)) {
            $t = ord($string[$n]);
            if($t == 9 || $t == 10 || (32 <= $t && $t <= 126)) {
                $tn = 1; $n++; $noc++;
            } elseif(194 <= $t && $t <= 223) {
                $tn = 2; $n += 2; $noc += 2;
            } elseif(224 <= $t && $t <= 239) {
                $tn = 3; $n += 3; $noc += 2;
            } elseif(240 <= $t && $t <= 247) {
                $tn = 4; $n += 4; $noc += 2;
            } elseif(248 <= $t && $t <= 251) {
                $tn = 5; $n += 5; $noc += 2;
            } elseif($t == 252 || $t == 253) {
                $tn = 6; $n += 6; $noc += 2;
            } else {
                $n++;
            }
            if($noc >= $length) {
                break;
            }
        }
        if($noc > $length) {
            $n -= $tn;
        }
        $strcut = substr($string, 0, $n);
        $strcut = str_replace(array('∵', '&', '"', "'", '“', '”', '—', '<', '>', '·', '…'), array(' ', '&amp;', '&quot;', '&#039;', '&ldquo;', '&rdquo;', '&mdash;', '&lt;', '&gt;', '&middot;', '&hellip;'), $strcut);
    } else {
        $maxi = $length - 1;
        $current_str = '';
        $search_arr = array('&',' ', '"', "'", '“', '”', '—', '<', '>', '·', '…','∵');
        $replace_arr = array('&amp;','&nbsp;', '&quot;', '&#039;', '&ldquo;', '&rdquo;', '&mdash;', '&lt;', '&gt;', '&middot;', '&hellip;',' ');
        $search_flip = array_flip($search_arr);
        for ($i = 0; $i < $maxi; $i++) {
            $current_str = ord($string[$i]) > 127 ? $string[$i].$string[++$i] : $string[$i];
            if (in_array($current_str, $search_arr)) {
                $key = $search_flip[$current_str];
                $current_str = str_replace($search_arr[$key], $replace_arr[$key], $current_str);
            }
            $strcut .= $current_str;
        }
    }
    return $strcut.$dot;
}

/**
 * curl请求
 * @author		zhangchangchun@dodoca.com
 * @ url		请求地址
 * @ data		请求数据
 * @ method		请求方式：GET / POST
 * @ header		$headers = array("Content-type: application/json;charset='utf-8'","Accept: application/json");
 */
function sycurl($url,$data='',$method='GET',$headers=array()) {
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL,$url);
	curl_setopt($ch, CURLOPT_TIMEOUT,30);
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
	if($headers) {
		curl_setopt($ch, CURLOPT_HTTPHEADER,$headers);
	}
	if(strtoupper($method)=='POST') {
		if(is_array($data)) {
			if(class_exists('CURLFile',false)) {
				foreach($data as $key => $v) {
					if(substr(trim($v),0,1)=='@') {	//文件处理
						$data[$key] = new CURLFile(realpath(substr(trim($v),1)));
					}
				}
			} else {
				if (defined('CURLOPT_SAFE_UPLOAD')) {
					curl_setopt($ch, CURLOPT_SAFE_UPLOAD, FALSE);
				}
			}
		}
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
	}
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // 对认证证书来源的检查
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	$cont = curl_exec($ch);
	if (curl_errno($ch)) {	//抓取异常
		return array('code'=>0,'result'=>curl_error($ch),'url'=>$url,'errno'=>curl_errno($ch));
	}
	$code = curl_getinfo($ch,CURLINFO_HTTP_CODE);
	$time = curl_getinfo($ch,CURLINFO_TOTAL_TIME);
	curl_close($ch);
	return array('code'=>$code,'result'=>$cont,'time'=>$time);
}

/**
 * 取得文件扩展
 *
 * @param $filename 文件名
 * @return 扩展名
 */
function fileext($filename) {
    return strtolower(trim(substr(strrchr($filename, '.'), 1, 10)));
}
<?php
/**
 * Created by PhpStorm.
 * User: gongruimin@dodoca.com
 * Date: 2018/6/28
 * Time: 16:58
 */

namespace App\Services;

use App\Facades\Member;
use App\Facades\Suppliers;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Request;
use App\Models\SuperCase;
use Qiniu\Auth;
use Qiniu\Storage\BucketManager;

class SuperCaseService
{
    private $fontFile = "font/Microsoft-Yahei.ttf"; //字体文件，微软雅黑
   
    private $_version = array(
        '1'=>'免费版',
        '2'=>'普通版',
        '3'=>'标准版',
        '4'=>'基础版'
        );
   //生成案例图片
    public function caseCard($caseData)
    { 
        $canvasWidth         = 648; //卡片宽度
        $shareImgMaxHeight   = 648; //卡片高度
        $qrcodeWidth         = 446; //二维码固定高宽度
        $qrcodeHeight        = 446;//

        $shareImgMaxWidth    = 648; //图片最大宽度高度，图片为1:1 
        //$space               = 20; //左右两侧空白
        $shareImgBottomSpace = 40; //图片底部空白
        $topSpace            = 30; //画布顶部空白
        $bottomSpace         = 64; //画布底部空白
        $appletNameFontSize = 20; //小程序名字字号大小
        $appletNameBottomHeight        = 30;//小程序名称底部空白
        $appletVersionFontSize = 15; //小程序版本字号大小
        $appletNameInterspace = 400; //小程序名字上下行高
        $fontFile            = $this->fontFile;

        //卡片高度(二维码的高度+小程序名字高度+版本高度+空白距离)
        $canvasHeight = 648; 
        $canvas = imagecreatetruecolor($canvasWidth, $canvasHeight); //创建画布
        $bgColor = imagecolorallocate($canvas, 255, 255, 255); //画布背景色
        imagefill($canvas, 0, 0, $bgColor);

        //小程序码地址
        $qrcodeUrl = env('QINIU_STATIC_DOMAIN').'/'.ltrim($caseData['xcxcode'], '/'); //默认小程序码
        //$qrcodeUrl = 'https://xcx.wrcdn.com/2018/07/09/Ft1nt_l2uNHU_zmaFJMWmyq2G9vV.jpg';
        list($qrcodeOriginalWidth, $qrcodeOriginalHeight, $qrcodeType) = getimagesize($qrcodeUrl); //获取图像尺寸

        switch ($qrcodeType) {
            case IMAGETYPE_JPEG:
                $qrcodeSrcImage = @imagecreatefromjpeg($qrcodeUrl);
                break;
            case IMAGETYPE_PNG:
                $qrcodeSrcImage = @imagecreatefrompng($qrcodeUrl);
                break;
            case IMAGETYPE_GIF:
                $qrcodeSrcImage = @imagecreatefromgif($qrcodeUrl);
                break;
        }

        $qrcodeImgX = ($canvasWidth - $qrcodeWidth) / 2 ; //二维码横向显示位置(画布宽度-二维码宽度/2)
        $rqcodeImgY = $topSpace; //二维码纵向显示位置(画布顶部距离)
        imagecopyresized($canvas, $qrcodeSrcImage, $qrcodeImgX, $rqcodeImgY, 0, 0, $qrcodeWidth, $qrcodeHeight, $qrcodeOriginalWidth, $qrcodeOriginalHeight);

        //小程序名称部分
        $appletName           = $caseData['xcxname'];
        $appletNameFontWidth  = imagefontwidth($appletNameFontSize); //商品名称单字的宽度 
        $appletNameFontHeight = imagefontheight($appletNameFontSize); //商品名称的高度
        $appletNameY          = 480; //小程序名称高度(画布顶部距离+二维码高度)
        $appletNameTextColor  = imagecolorallocate($canvas, 51, 51, 51); //文本颜色
        //文字换行
        $appletName = $this->autoWrap($appletNameFontSize, 0, $fontFile, $appletName, $appletNameInterspace);
        foreach ($appletName as $key => $value) {
            $appletNameY += 30;
            $appletNameTextWidth  = $appletNameFontWidth * mb_strlen($value)*2;
            if($key>=2){
                $value = '...';
            }
            //计算字符宽度
            // $dd = imagettfbbox($appletNameFontSize, 0, $fontFile, $value);
            // $appletNameTextWidth  = $dd[2];
            $appletNameX = ($canvasWidth - $appletNameTextWidth) / 2  ; 
            imagefttext($canvas, $appletNameFontSize, 0, $appletNameX, $appletNameY, $appletNameTextColor, $fontFile, $value); //把文字写入到图像上
        }
       

        //小程序版本
        $appletVersion         = $this->_version[$caseData['version']];
        $appletVersionFontWidth  = imagefontwidth($appletVersionFontSize); //小程序版本宽度
        $appletVersionFontHeight = imagefontheight($appletVersionFontSize); //小程序版本高度
        $appletVersionTextWidth  = $appletVersionFontWidth * mb_strlen($appletVersion)*2;
        $appletVersionX          = ($canvasWidth - $appletVersionTextWidth) / 2 ;
        $appletVersionY          = 580; //小程序名称高度(画布顶部距离+二维码高度)
        $appletVersionTextColor  = imagecolorallocate($canvas, 51, 51, 51); //文本颜色
        imagefttext($canvas, 15, 0, $appletVersionX , $appletVersionY, $appletVersionTextColor, $fontFile, $appletVersion);


        //创建矩形
        $rectangleColor = imagecolorallocate($canvas, 245, 245, 245);
        $rectangle = imagefilledrectangle($canvas, 0, 600, 648, 648, $rectangleColor);    //图片的长和宽减1


        $qrcodeTextColor  = imagecolorallocate($canvas, 102, 102, 102); //文本颜色
        imagefttext($canvas, 12, 0, 240, 630, $qrcodeTextColor, $fontFile, '长按识别小程序码，查看案例');
        
        ob_start();
        imagepng($canvas);
        $content = ob_get_contents();
        imagedestroy($canvas);

        $content = base64_encode($content);
        ob_clean();
        $imgData = $this->upload($content);
        if(!empty($imgData)){
            return ['errcode' => 0, 'data' => $imgData];
        }else{
            return ['errcode' => -1, 'errmsg' => '获取卡片失败'];
        }
    }

     //文字自动换行
    private function autoWrap($fontSize, $angle, $fontFile, $string, $width)
    {
        $content = [];
        // 将字符串拆分成一个个单字 保存到数组 letter 中
        for ($i = 0; $i < mb_strlen($string); $i ++) {
            $letter[] = mb_substr($string, $i, 1);
        }
        $ix =0;
        $content[$ix] = "";
        foreach ($letter as $l) {
            $teststr = $content[$ix]."".$l;
            $testbox = imagettfbbox($fontSize, $angle, $fontFile, $teststr);
            // 判断拼接后的字符串是否超过预设的宽度
            if (($testbox[2] > $width) && ($content !== "")) {
                $ix++;
                $content[$ix] = "";
            }
            $content[$ix] .= $l;
        }
        // foreach($content as $key => $value) {
        //     $content[$key] = mb_convert_encoding($value, "html-entities", "UTF-8");
        // }
        return $content;
    }

/**
     * 上传图片源码到七牛
     * @return array
     * @author: tangkang@dodoca.com
     */
    private function upload($content)
    {
        $bucket    = env('QINIU_BUCKET');
        $accessKey = env('QINIU_ACCESS_KEY');
        $secretKey = env('QINIU_SECRET_KEY');

        $auth = new Auth($accessKey, $secretKey);
        $upToken = $auth->uploadToken($bucket);//获取上传所需的token

        $filename = $this->getRandChar().'.png';
        $key = date('Y').'/'.date('m').'/'.date('d').'/'.$filename;
        $base_key = \Qiniu\base64_urlSafeEncode($key);

        $client = new Client();
        $res = $client->request('POST', 'http://upload.qiniu.com/putb64/-1/key/'.$base_key, [
            'body' => $content,
            'headers' => [
                'Content-Type'  => 'image/png',
                'Authorization' => 'UpToken '.$upToken
            ]
        ]);

        $result = json_decode($res->getBody(), true);

        if(isset($result['key'])){
            return $result['key'];
        }
    }

    /**
     * 生成文件名称
     * @return array
     * @author: tangkang@dodoca.com
     */
    private function getRandChar(){
        $str = null;
        $strPol = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789abcdefghijklmnopqrstuvwxyz";
        $max = strlen($strPol)-1;

        for($i=0;$i<28;$i++){
            $str.=$strPol[rand(0,$max)];
        }
        return $str;
    }



}
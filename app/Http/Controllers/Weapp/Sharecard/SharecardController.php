<?php

namespace App\Http\Controllers\Weapp\Sharecard;

use Illuminate\Http\Request;
use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Facades\Member;
use App\Models\ShareCard;
use App\Models\WeixinInfo;
use App\Models\Goods;
use App\Models\AloneActivityRecode;
use App\Models\Bargain;
use App\Models\Seckill;
use App\Models\FightgroupItem;
use App\Utils\CacheKey;
use Qiniu\Auth;
use Qiniu\Storage\BucketManager;
use Cache;
use GuzzleHttp\Client;
use Carbon\Carbon;
use App\Services\WeixinService;

class SharecardController extends Controller
{
    private $fontFile = "font/Microsoft-Yahei.ttf"; //字体文件，微软雅黑

    public function getcard(Request $request)
    {
        $member_id   = Member::id();
        $weapp_id    = Member::weapp_id();
        $merchant_id = Member::merchant_id();
        $goods_id    = $request->goods_id;

        $data = ShareCard::get_data($merchant_id, $weapp_id);

        if(!$data || $data->is_whether != 1){
            return ['errcode' => 99001, 'errmsg' => '商家未启用此功能'];
        }

        if($goods_id){
            return $this->goodsCard($merchant_id, $weapp_id, $member_id, $goods_id);
        }else{
            return $this->shopCard($merchant_id, $weapp_id, $member_id);
        }
    }

    //生成店铺卡片
    private function shopCard($merchant_id, $weapp_id, $member_id)
    {
        $cacheKey = CacheKey::share_card_custom_key($merchant_id, $weapp_id);
        $imgData  = Cache::get($cacheKey);

        if(!$imgData){
            $data   = ShareCard::get_data($merchant_id, $weapp_id);
            $wxinfo = WeixinInfo::get_one('id', $weapp_id, 1);
            $appid  = $wxinfo['appid'];

            $width  = 900;
            $height = 1200;

            $canvas = new \Imagick();
            $canvas->newImage($width, $height, new \ImagickPixel('white'));
            $canvas->setImageFormat('png');
            $canvas->setCompressionQuality(100);
            $canvas->enhanceImage();

            $back_img = env('QINIU_STATIC_DOMAIN').'/'.ltrim($data->share_img_url, '/');
            $back = new \Imagick();
            $back->readImageBlob(file_get_contents($back_img));
            $back->thumbnailImage(900, 900);
            $canvas->compositeImage($back, \Imagick::COMPOSITE_OVER, 0, 0);

            //小程序名称
            $applet_name = $wxinfo['nick_name'];
            $applet_name_draw = new \ImagickDraw();
            $applet_name_draw->setFont(realpath('font/Microsoft-Yahei.ttf'));
            $applet_name_draw->setFontSize(44);
            $applet_name_draw->setFillColor('#333333');
            $applet_name_font_metrics = $canvas->queryFontMetrics($applet_name_draw, $applet_name);
            $canvas->annotateImage($applet_name_draw, 40, 930+$applet_name_font_metrics['characterHeight'], 0, $applet_name);

            //描述
            if(!empty($data->desc)){
                $share_desc = $data->desc;
                $share_desc_draw = new \ImagickDraw();
                $share_desc_draw->setFont(realpath('font/Microsoft-Yahei.ttf'));
                $share_desc_draw->setFontSize(32);
                $share_desc_draw->setFillColor('#333333');
                $share_desc_font_metrics = $canvas->queryFontMetrics($share_desc_draw, $share_desc);
                $share_desc_width = $share_desc_font_metrics['textWidth'];
                //文字换行
                $share_desc_wrap = $this->autoWrap(32, 0, realpath('font/Microsoft-Yahei.ttf'), $share_desc, 700);
                if($share_desc_wrap){
                    $text_x = 1150;
                    if(count($share_desc_wrap) > 1){
                        $text_x -= $share_desc_font_metrics['characterHeight'] + 5;
                    }
                    foreach($share_desc_wrap as $str){
                        $font_metrics = $canvas->queryFontMetrics($share_desc_draw, $str);
                        $canvas->annotateImage($share_desc_draw, 40, $text_x, 0, $str);
                        $text_x += $font_metrics['textHeight'] + 3;
                    }
                }
            }

            //二维码地址
            $qrcode_url = env('QINIU_STATIC_DOMAIN').'/'.ltrim($wxinfo['qrcode'], '/'); //默认小程序码
            if($member_id){
                $encrypt_member_id = encrypt($member_id, 'E');
                $qrcode_type = env('APP_ENV') == 'production' ? 1 : 4;
                $create_qrcode = (new WeixinService())->qrcodeAll($appid, $qrcode_type, 'pages/decorate/decorate?distrib_member_id='.$encrypt_member_id);
                if (isset($create_qrcode['errcode']) && $create_qrcode['errcode'] == 0) {
                    $qrcode_url = env('QINIU_STATIC_DOMAIN').'/'.ltrim($create_qrcode['url'], '/');
                }
            }
            $qrcode = new \Imagick();
            $qrcode->readImageBlob(file_get_contents($qrcode_url));
            $qrcode->thumbnailImage(200, 200, true);
            $canvas->compositeImage($qrcode, \Imagick::COMPOSITE_OVER, $width-200-40, 930);

            //识别小程序
            $qrcode_desc = '长按识别二维码';
            $qrcode_desc_draw = new \ImagickDraw();
            $qrcode_desc_draw->setFont(realpath('font/Microsoft-Yahei.ttf'));
            $qrcode_desc_draw->setFontSize(22);
            $qrcode_desc_draw->setFillColor('#666666');
            $canvas->annotateImage($qrcode_desc_draw, $width-216, 1160, 0, $qrcode_desc);

            $content = $canvas->getImageBlob();
            $canvas->clear(); //释放资源
            $content = base64_encode($content);
            $imgData = $this->upload($content);
            Cache::forever($cacheKey, $imgData);
        }
        
        if(!empty($imgData)){
            return ['errcode' => 0, 'data' => $imgData];
        }else{
            return ['errcode' => -1, 'errmsg' => '获取卡片失败'];
        }
    }

    //生成商品卡片
    private function goodsCard($merchant_id, $weapp_id, $member_id, $goods_id)
    {
        $activity_recode = AloneActivityRecode::get_data_by_goodsid($goods_id, $merchant_id);
        $alone_id = $activity_recode ? $activity_recode->alone_id : 0;
        $cacheKey = CacheKey::share_card_goods_key($goods_id, $merchant_id, $alone_id);
        $imgData  = Cache::tags('goods_sharecard_'.$goods_id.'_'.$merchant_id)->get($cacheKey);

        if(!$imgData){
            $data = Goods::get_data_by_id($goods_id, $merchant_id);

            if(!$data){
                return ['errcode' => 99001, 'errmsg' => '商品不存在'];
            }

            if($alone_id){
                $act_type = '';
                switch ($activity_recode->act_type) {
                    case 'bargain':
                        $act_type = '砍价';
                        $bargain = Bargain::get_data_by_id($activity_recode->alone_id, $merchant_id);
                        $data['price'] = $bargain ? $bargain->minimum : 0;
                        break;
                    case 'seckill':
                        $act_type = '秒杀';
                        $seckill = Seckill::get_data_by_id($activity_recode->alone_id, $merchant_id);
                        $data['price'] = $seckill ? $seckill->price : 0;
                        break;
                    case 'tuan':
                        $act_type = '拼团';
                        $data['price'] = FightgroupItem::where('merchant_id', $merchant_id)->where('fightgroup_id', $activity_recode->alone_id)->orderBy('ladder_price', 'asc')->value('ladder_price');
                        break;
                }
            }

            $wxinfo = WeixinInfo::get_one('id', $weapp_id, 1);
            $appid  = $wxinfo['appid'];

            $width  = 900;
            $height = 1200;

            $canvas = new \Imagick();
            $canvas->newImage($width, $height, new \ImagickPixel('white'));
            $canvas->setImageFormat('png');
            $canvas->setCompressionQuality(100);
            $canvas->enhanceImage();

            $back_img = env('QINIU_STATIC_DOMAIN').'/'.ltrim($data['img'], '/');
            $back = new \Imagick();
            $back->readImageBlob(file_get_contents($back_img));
            $back->thumbnailImage(900, 900);
            $canvas->compositeImage($back, \Imagick::COMPOSITE_OVER, 0, 0);

            //店铺图标地址
            $icon_img = 'https://s.dodoca.com/applet_weapp/images/icon/share_card_icon_1.png';
            $icon = new \Imagick();
            $icon->readImageBlob(file_get_contents($icon_img));
            $icon->thumbnailImage(32, 32, true);
            $canvas->compositeImage($icon, \Imagick::COMPOSITE_OVER, 40, 940);

            //小程序名称
            $applet_name = $wxinfo['nick_name'];
            $applet_name_draw = new \ImagickDraw();
            $applet_name_draw->setFont(realpath('font/Microsoft-Yahei.ttf'));
            $applet_name_draw->setFontSize(28);
            $applet_name_draw->setFillColor('#333333');
            $applet_name_font_metrics = $canvas->queryFontMetrics($applet_name_draw, $applet_name);
            $canvas->annotateImage($applet_name_draw, 80, 940+$applet_name_font_metrics['characterHeight'], 0, $applet_name);

            //商品名称
            $goods_name = $data['title'];
            $goods_name_draw = new \ImagickDraw();
            $goods_name_draw->setFont(realpath('font/Microsoft-Yahei.ttf'));
            $goods_name_draw->setFontSize(40);
            $goods_name_draw->setFillColor('#333333');
            $goods_name_font_metrics = $canvas->queryFontMetrics($goods_name_draw, $goods_name);
            $goods_name_width = $goods_name_font_metrics['textWidth'];
            if($goods_name_width > 560){
                $length = round(560 / $goods_name_font_metrics['characterWidth']) * 3;
                $goods_name = str_cut($goods_name, $length);
            }
            $canvas->annotateImage($goods_name_draw, 40, 1010+$applet_name_font_metrics['characterHeight'], 0, $goods_name);

            //价格符号
            $symbol = '￥';
            $symbol_draw = new \ImagickDraw();
            $symbol_draw->setFont(realpath('font/Microsoft-Yahei.ttf'));
            $symbol_draw->setFontSize(32);
            $symbol_draw->setFillColor('#ff5500');
            $symbol_font_metrics = $canvas->queryFontMetrics($symbol_draw, $symbol);
            $symbol_width = $symbol_font_metrics['textWidth'];
            $canvas->annotateImage($symbol_draw, 40, 1160, 0, $symbol);

            //价格
            $price = $data['price'];
            $price_draw = new \ImagickDraw();
            $price_draw->setFont(realpath('font/Microsoft-Yahei.ttf'));
            $price_draw->setFontSize(52);
            $price_draw->setFillColor('#ff5500');
            $price_font_metrics = $canvas->queryFontMetrics($price_draw, $price);
            $price_width = $price_font_metrics['textWidth'];
            $canvas->annotateImage($price_draw, 50+$symbol_width, 1160, 0, $price);

            if($alone_id){
                $act_back = new \Imagick();
                $act_back->newImage(80, 40, '#FF7F00');
                $canvas->compositeImage($act_back, \Imagick::COMPOSITE_OVER, 50+$symbol_width+$price_width+20, 1125);

                $original_price = $act_type;
                $original_price_draw = new \ImagickDraw();
                $original_price_draw->setFont(realpath('font/Microsoft-Yahei.ttf'));
                $original_price_draw->setFontSize(28);
                $original_price_draw->setFillColor('#FFFFFF');
                $canvas->annotateImage($original_price_draw, 50+$symbol_width+$price_width+30, 1155, 0, $original_price);
            }else if($data['original_price'] > 0){
                $original_price = '原价￥'.$data['original_price'];
                $original_price_draw = new \ImagickDraw();
                $original_price_draw->setFont(realpath('font/Microsoft-Yahei.ttf'));
                $original_price_draw->setFontSize(28);
                $original_price_draw->setFillColor('#8e8e8e');
                $canvas->annotateImage($original_price_draw, 50+$symbol_width+$price_width+20, 1160, 0, $original_price);
            }

            //二维码
            $qrcode_url = env('QINIU_STATIC_DOMAIN').$wxinfo['qrcode']; //默认小程序二维码
            //商品二维码地址
            if($member_id){
                $encrypt_member_id = encrypt($member_id, 'E');
                $qrcode_type = env('APP_ENV') == 'production' ? 1 : 4;
                $create_qrcode = (new WeixinService())->qrcodeAll($appid, $qrcode_type, 'pages/goods/detail/detail?id='.$goods_id.'&distrib_member_id='.$encrypt_member_id);
                if (isset($create_qrcode['errcode']) && $create_qrcode['errcode'] == 0) {
                    $qrcode_url = env('QINIU_STATIC_DOMAIN').'/'.ltrim($create_qrcode['url'], '/');
                }
            }else{
                $qrcode_type = env('APP_ENV') == 'production' ? 1 : 4;
                $create_qrcode = (new WeixinService())->qrcodeAll($appid, $qrcode_type, 'pages/goods/detail/detail?id='.$goods_id);
                if (isset($create_qrcode['errcode']) && $create_qrcode['errcode'] == 0) {
                    $qrcode_url = env('QINIU_STATIC_DOMAIN').'/'.ltrim($create_qrcode['url'], '/');
                }
            }
            $qrcode = new \Imagick();
            $qrcode->readImageBlob(file_get_contents($qrcode_url));
            $qrcode->thumbnailImage(200, 200, true);
            $canvas->compositeImage($qrcode, \Imagick::COMPOSITE_OVER, $width-200-40, 930);

            //识别小程序
            $qrcode_desc = '长按识别二维码';
            $qrcode_desc_draw = new \ImagickDraw();
            $qrcode_desc_draw->setFont(realpath('font/Microsoft-Yahei.ttf'));
            $qrcode_desc_draw->setFontSize(22);
            $qrcode_desc_draw->setFillColor('#666666');
            $canvas->annotateImage($qrcode_desc_draw, $width-216, 1160, 0, $qrcode_desc);

            $content = $canvas->getImageBlob();
            $canvas->clear(); //释放资源
            $content = base64_encode($content);
            $imgData = $this->upload($content);
            Cache::tags('goods_sharecard_'.$goods_id.'_'.$merchant_id)->forever($cacheKey, $imgData);
        }

        if(!empty($imgData)){
            return ['errcode' => 0, 'data' => $imgData];
        }else{
            return ['errcode' => -1, 'errmsg' => '获取卡片失败'];
        }
    }

    //文字自动换行
    private function autoWrap($fontSize, $angle, $fontFile, $string, $width) 
    {
        $content = "";
        // 将字符串拆分成一个个单字 保存到数组 letter 中
        for ($i = 0; $i < mb_strlen($string); $i ++) {
            $letter[] = mb_substr($string, $i, 1);
        }

        foreach ($letter as $l) {
            $teststr = $content."".$l;
            $testbox = imagettfbbox($fontSize, $angle, $fontFile, $teststr);
            // 判断拼接后的字符串是否超过预设的宽度
            if (($testbox[2] > $width) && ($content !== "")) {
                $content .= "\r\n";
            }
            $content .= $l;
        }
       
        return explode("\r\n", $content);
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

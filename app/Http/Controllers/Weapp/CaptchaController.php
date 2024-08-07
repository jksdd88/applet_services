<?php 

namespace App\Http\Controllers\weapp;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

use Cache;
use App\Facades\Member;
use App\Utils\CacheKey;
use App\Utils\ValidateCode;



class CaptchaController extends Controller {

    public function index(Request $request){
		$member_id   = Member::id();
		$merchant_id = Member::merchant_id();

		$validateCode = new ValidateCode;
		$data = 'data:image/png;base64,'.$validateCode->doimg();

		$key = CacheKey::get_member_captcha_key($merchant_id, $member_id);
        Cache::put($key, $validateCode->getCode(), 10);
        return ['errcode' => 0, 'data' => $data];
    }
    
    
    //通过点点客技术支持链接，注册
    public function register(Request $request){
        
        //参数
        $params = $request->all();
        
        //注册页面前端生成的随机数
        $random = isset($params['random']) ? $params['random'] : '';
        
        if(!$random){
            return ['errcode' => 99001,'errmsg' => 'random参数缺失'];
        }
        
        $merchant_id = Member::merchant_id();
    
        $validateCode = new ValidateCode;
        $data = 'data:image/png;base64,'.$validateCode->doimg();
    
        $key = CacheKey::get_register_member_captcha_key($merchant_id, $random);
        Cache::put($key, $validateCode->getCode(), 10);
        
        return ['errcode' => 0,'errmsg' => '获取成功','data' => $data];
    }
}

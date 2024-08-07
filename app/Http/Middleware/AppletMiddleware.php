<?php

namespace App\Http\Middleware;

use Closure;
use Cache;
use App\Utils\Encrypt;
use App\Facades\Member;
use App\Models\Merchant;
use App\Models\WeixinInfo;
use App\Models\User;
use App\Services\WeixinService;

/**
 * 无需授权token接口中间件
 *
 * @package default
 * @author 郭其凯
 **/

class AppletMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if ($request->has('merchant_id')) {
            //兼容有授权token
            if ($request->has('token')) {
                $token = $request->token;

                $data = Cache::get($token);
                if (!$data) {
                    return ['errcode' => 10001, 'errmsg' => '请重新登录'];
                }

                if(is_object($data)){
                    $data = $data->toArray();
                }
                Member::set($data);

                $merchant_id = $data['merchant_id'];
            }else{
                $merchant_id = $request->merchant_id;

                if(!is_numeric($merchant_id)){
                    $Encrypt = new Encrypt;
                    $merchant_id = $Encrypt->decode($merchant_id);
                    //兼容表单验证
                    $request->offsetSet('merchant_id', $merchant_id);
                }
            }
            
            $merchant = Merchant::get_data_by_id($merchant_id);
            if(!$merchant || $merchant['status'] == -1){
                return ['errcode' => 99005, 'errmsg' => '商家不存在'];
            }
            if($merchant){
                if($merchant['status'] == 2){
                    return ['errcode' => 99006, 'errmsg' => '小程序审核失败，请联系客服'];
                }
                if(in_array($merchant['version_id'], [2, 3, 4, 6])){
                    if(strtotime($merchant['expire_time']) < time()){
                        return ['errcode' => 99007, 'errmsg' => '商家服务已到期'];
                    }
                }
            }
            Member::setField('merchant_id', $merchant_id);

            //获取小程序ID
            $weapp_id = $request->weapp_id;
            $appid    = '';
            if (!$weapp_id) {
                $firstinfo = WeixinInfo::where('merchant_id', $merchant_id)
                    ->where('appid', '!=', '')
                    ->where('type', 1)
                    ->where('status', 1)
                    ->where('auth', 1)
                    ->first();

                if($firstinfo){
                    $weapp_id = $firstinfo['id'];
                    $appid    = isset($firstinfo['appid']) && !empty($firstinfo['appid']) ? $firstinfo['appid'] : '';
                }                
            }else{
                if(!is_numeric($weapp_id)){
                    $Encrypt = new Encrypt;
                    $weapp_id = $Encrypt->decode($weapp_id);
                }
                $firstinfo = WeixinInfo::check_one_id($weapp_id);
				//做下兼容：若当前小程序删除，读取当前appid下最新记录
				if(!$firstinfo || $firstinfo['status'] == -1) {
					$lastinfo = WeixinInfo::get_one_appid($firstinfo['merchant_id'], $firstinfo['appid']);
					if($lastinfo) {
						$weapp_id = $lastinfo['id'];	//小程序id
                        $appid    = isset($lastinfo['appid']) && !empty($lastinfo['appid']) ? $lastinfo['appid'] : '';
					}
				}else{
                    $weapp_id = $firstinfo['id'];    //小程序id
                    $appid    = isset($firstinfo['appid']) && !empty($firstinfo['appid']) ? $firstinfo['appid'] : '';
                }
            }

            $template_type_map = config('weixin.template_type_map');
            if(isset($firstinfo) && isset($lastinfo)){
                if($lastinfo['status'] == -1 || empty($lastinfo['appid']) || $template_type_map[$lastinfo['tpl_type']] != $template_type_map[$firstinfo['tpl_type']]){
                    $mobile = User::where('merchant_id', $merchant_id)->where('is_admin', 1)->value('mobile');
                    return ['errcode' => 99009, 'errmsg' => '小程序已关闭，如有需要，请联系商家', 'mobile' => $mobile];
                }
            }else{
                if(!$firstinfo || $firstinfo['status'] == -1 || empty($firstinfo['appid'])){
                    $mobile = User::where('merchant_id', $merchant_id)->where('is_admin', 1)->value('mobile');
                    return ['errcode' => 99009, 'errmsg' => '小程序已关闭，如有需要，请联系商家', 'mobile' => $mobile];
                }
            }
            
            Member::setField('weapp_id', $weapp_id);
            Member::setField('appid', $appid);
        }else{
            return ['errcode' => 99004, 'errmsg' => '商户ID不存在'];
        }
        return $next($request);
    }
}

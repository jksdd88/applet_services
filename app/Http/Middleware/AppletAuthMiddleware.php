<?php

namespace App\Http\Middleware;

use Closure;
use Cache;
use App\Facades\Member;
use App\Models\Merchant;
use App\Utils\Encrypt;

/**
 * 必须已获取授权token的中间件
 *
 * @package default
 * @author 郭其凯
 **/

class AppletAuthMiddleware
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
        if ($request->has('token')) {
            $token = $request->token;
            $data = Cache::get($token);

            if (!$data) {
                return ['errcode' => 10001, 'errmsg' => '请重新登录'];
            }

            $merchant_id = $data['merchant_id'];
            $merchant = Merchant::get_data_by_id($merchant_id);

            if(!$merchant || $merchant['status'] == -1){
                return ['errcode' => 99005, 'errmsg' => '商户不存在'];
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

            if((!isset($data['weapp_id']) && !$data['weapp_id']) || (isset($data['appid']) && !$data['appid'])){
                return ['errcode' => 99008, 'errmsg' => '小程序不存在'];
            }
            if(is_object($data)){
                $data = $data->toArray();
            }
            Member::set($data);
        }else{
            return ['errcode' => 10001, 'errmsg' => 'token不能为空'];
        }

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use Illuminate\Support\Facades\Response;
use App\Models\ApiLimit;
use App\Utils\CacheKey;
use Closure;
use Cache;

class OpenApiMiddleware
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
        //access_token 验证
        $access_token = $request->get('access_token');
        if(!$access_token){
            return Response::json(['errcode'=>20011, 'errmsg'=>'请传入access_token']);
        }
        $userInfo = Cache::get(CacheKey::get_open_api($access_token));
        if(!$userInfo || !isset($userInfo['id'])){
            return Response::json(['errcode'=>20004, 'errmsg'=>'access_token 已过期']);
        }
        //请求频率限制
        $cacheKey = $request->route()->getActionName();
        $cacheKey .= $request->method();
        $cacheKey .= $userInfo['id'];
        $cacheKey =  CacheKey::get_open_api( $cacheKey );
        $cacheLimit = (int)Cache::get($cacheKey);
        if($cacheLimit > 100){
            return Response::json(['errcode'=>20005, 'errmsg'=>'请求过于频繁，请一分钟后再尝试']);
        }
        if($cacheLimit < 1 ){
            Cache::put($cacheKey,1,1);
        }else{
            Cache::increment($cacheKey);
        }
        //注入商户信息
        $request->setUserResolver(function () use ($userInfo){
            return ['merchant_id'=>$userInfo['id']];
        });
        //请求统计
        ApiLimit::apiLimit($userInfo['id'],'index_all',0); 
        return $next($request);
    }

    //请求限制
    private function requestLimit($merchantId){
        $key =  CacheKey::get_open_api('Middleware_limit_'.$merchantId);
        if(Cache::has($key)){
            Cache::increment($key);
            $limit = Cache::get($key);
            return $limit > 1000000 ? false : true;
        }
        $time = strtotime(date('Y-m-d').' 23:59:59');
        $time -= time();
        $time = intval($time/60);
        Cache::put($key,1,$time);
        return true;
    }

    //签名
    private function getSign($param){
        unset($param['sign']);
        ksort($param);
        $param = http_build_query($param);
        return sha1($param);
    }
}

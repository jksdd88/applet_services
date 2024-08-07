<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class SuperAuth
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
		//修改一下auth的默认登录表
        config(['auth.model' => 'App\SuperUser','auth.table'=>'super_user']);

 		if(Session::get('super_user.id')){

            //已经登录	
            //dd('s');	
        }
        else{
            //还没有登录
            //dd('not login');
            return redirect()->guest('admin/login');
        }
        
        return $next($request);
    }
}

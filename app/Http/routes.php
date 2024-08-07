<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It's a breeze. Simply tell Laravel the URIs it should respond to
| and give it the controller to call when that URI is requested.
|
*/

//加载小程序接口路由
require __DIR__ . '/routes_app.php';

//加载商家后台接口路由
require __DIR__ . '/routes_admin.php';

//微伙伴模块路由
require __DIR__ . '/routes_whb.php';

//超级管理员后台
require __DIR__ . '/routes_super.php';

//开发接口API
require __DIR__ . '/routes_openapi.php';

//特殊接口路由（支付、等）
require __DIR__ . '/routes_com.php';

//定制路由
require __DIR__ . '/routes_custom.php';

//进入商家后台前端项目
Route::any('{all}', function () {
	return Redirect::to('manage/');
})->where('all', '.*');

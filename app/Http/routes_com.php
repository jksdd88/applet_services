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
$weapp_prefix          = 'com';		//项目前缀
$weapp_namespace       = 'Com';	//项目命名空间

Route::group(['prefix' => $weapp_prefix, 'namespace' => $weapp_namespace], function () {
	
	//支付相关
	Route::any('cashier/paynotify/{id}/{type}', 'Cashier\CashierController@paynotify');
	Route::any('cashier/notifytest/{id}', 'Cashier\CashierController@notifytest');	//模拟支付仅测试环境有效
	
	//支付宝pc网页支付异步通知
	Route::any('cashier/alipcnotify', 'Cashier\CashierController@alipcnotify');
	//支付宝pc网页支付同步通知
	Route::any('cashier/alipcreturn', 'Cashier\CashierController@alipcreturn');

    //修复数据、缓存等
    Route::group(['prefix' => 'restore', 'namespace' => 'Restore'], function () {
        Route::get('flush_cache', 'CacheController@flush');//清缓存，Request参数：：method:获取Cache键值的函数名称，param：函数参数，逗号分隔
        Route::get('flush_cart', 'CacheController@flushCart');//清空购物车
        Route::get('flush_goods', 'GoodsController@flushGoods');//清空购物车
        Route::get('findkey', 'CacheController@findkey');//查缓存key值
        Route::get('cart', 'CacheController@getCart');//购物车列表
        Route::get('goods/stock/{goods_id}.json', 'GoodsController@stock')->where('goods_id', '[0-9]+');//修复总库存
    });

});

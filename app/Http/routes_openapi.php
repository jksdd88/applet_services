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
$api_prefix          = 'api';		//项目前缀
$api_middleware      = 'auth.openapi';
$api_domain           = '';
if(env('APP_ENV') == 'production') $api_domain = 'applet-api.dodoca.com';
//文档
Route::group([ 'prefix' => $api_prefix ], function () {
    Route::get('opendoc/list.json','Super\OpenDocController@menuList');
    Route::get('opendoc/details.json','Super\OpenDocController@docDetails');
});
//token
Route::group(['domain' => $api_domain , 'prefix' => $api_prefix ], function () {
	Route::post('auth/token.json', 'OpenApi\Index\IndexController@login');
	Route::post('auth/refresh.json', 'OpenApi\Index\IndexController@refresh');
});
//商品
Route::group(['domain' => $api_domain , 'prefix' => $api_prefix ,'middleware' => $api_middleware,  'namespace' => 'OpenApi'], function () {
    //测试
    Route::any('data/test.json', 'Index\IndexController@index');
    //商品属性
    Route::get('goods/servelabel.json', 'Goods\GoodsPropController@getTag');//服务承诺
    //商品属性
    Route::get('goods/prop.json', 'Goods\GoodsPropController@getProp');//规格列表
    Route::post('goods/prop/add.json',  'Goods\GoodsPropController@addProp');//规格新增
    Route::delete('goods/prop/del.json', 'Goods\GoodsPropController@delProp');//规格删除
    Route::put('goods/prop/edit.json',   'Goods\GoodsPropController@editProp');//规格编辑
    Route::get('goods/propval.json', 'Goods\GoodsPropController@getValue');//规格值列表
    Route::post('goods/propval/add.json', 'Goods\GoodsPropController@addValue');//规格值新增
    Route::delete('goods/propval/del.json', 'Goods\GoodsPropController@delValue');//规格值删除
    //商品
    Route::get('goods/list.json', 'Goods\GoodsController@index'); //商品列表
    Route::get('goods/detail.json', 'Goods\GoodsController@detail');//商品详情
    Route::post('goods/add.json', 'Goods\GoodsController@add');//商品添加
    Route::put('goods/edit.json', 'Goods\GoodsController@edit');//商品编辑
    Route::post('goods/del.json', 'Goods\GoodsController@delete');//商品删除(单个或者批量删除)
    Route::post('goods/onsale.json', 'Goods\GoodsController@onsale');  //商品上下架（单个或者批量）
    //商品类目
    Route::get('goods/cat.json', 'Goods\GoodsCatController@getGoodsCat'); //商品类目
    //商品分组
    Route::get('goods/group.json', 'Goods\GoodsGroupController@index');//商品分组列表
    Route::delete('goods/group/del.json', 'Goods\GoodsGroupController@delete');//商品分组删除
    Route::post('goods/group/add.json', 'Goods\GoodsGroupController@add');//商品分组添加
    Route::put('goods/group/edit.json', 'Goods\GoodsGroupController@edit');//商品分组编辑
    Route::get('goods/group/detail.json', 'Goods\GoodsGroupController@detail');//商品分组详情
    //商品列表 编辑 单规格/多规格商品的名称/库存/价格
    Route::post('goods/column/change.json', 'Goods\GoodsController@columnChange');//单规格商品 编辑名称/价格
    Route::put('goods/stock.json', 'Goods\GoodsController@putStock');//单规格/多规格 商品 编辑库存
    Route::get('goods/getmultisku.json', 'Goods\GoodsPropController@getMultiSku');//获取多规格数据
    Route::put('goods/editmultisku.json', 'Goods\GoodsPropController@editMultiSku');//编辑多规格数据
});

//订单
Route::group(['domain' => $api_domain , 'prefix' => $api_prefix ,'middleware' => $api_middleware,  'namespace' => 'OpenApi'], function () {
	
    Route::get('order/list.json', 'Order\OrderController@getList');				//订单列表
    Route::get('order/order/{order_sn}.json', 'Order\OrderController@getInfo');	//订单详情
    Route::get('order/delivery.json', 'Order\OrderController@getDelivery');		//获取物流公司
    Route::post('order/shipping.json', 'Order\OrderController@postShipping');	//订单发货
});

//会员
Route::group(['domain' => $api_domain , 'prefix' => $api_prefix ,'middleware' => $api_middleware,  'namespace' => 'OpenApi'], function () {
    Route::get('member/list.json', 'Member\MemberController@getList');//订单列表
    Route::get('member/detail/{id}.json', 'Member\MemberController@detail');//订单详情
});

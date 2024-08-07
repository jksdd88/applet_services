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
$weapp_prefix          = 'weapp';       //小程序端:项目前缀 
$weapp_namespace       = 'Custom';      //小程序端:项目命名空间
$weapp_middleware      = 'applet';      //小程序端:不需要授权的中间件
$weapp_auth_middleware = 'auth.applet'; //小程序端:需要授权的中间件
//小程序端:投票
Route::group(['prefix' => $weapp_prefix, 'namespace' => $weapp_namespace, 'middleware' => $weapp_auth_middleware], function () {
    Route::get('vote/getvotedetail.json', 'Vote\VoteappController@getVoteDetail');    //查看投票选项列表
    Route::post('vote/votemember.json', 'Vote\VoteappController@postVoteMember');     //会员投票
});

//小程序端：提交小程序加盟线索
Route::group(['prefix' => $weapp_prefix, 'namespace' => $weapp_namespace], function () {
    Route::post('xcxjoinclue/xcxjoinclue.json', 'Xcxjoinclue\XcxJoinclueController@postClue');     //提交线索
    Route::get('xcxjoinclue/region.json', 'Xcxjoinclue\XcxJoinclueController@getRegion');//获取省市区信息
});


$admin_prefix = 'admin';            //PC端:项目前缀
$admin_namespace = 'Custom';        //PC端:项目命名空间
$admin_middleware = 'auth.api';     //PC端:项目中间件，需要会员信息接口使用
//PC端:投票
Route::group(['prefix' => $admin_prefix, 'namespace' => $admin_namespace, 'middleware' => $admin_middleware], function () {
    Route::post('vote/vote.json', 'Vote\VoteController@postVote');          //投票主题 新增
    Route::get('vote/votelist.json', 'Vote\VoteController@getVoteList');    //投票主题 查看
    Route::put('vote/vote.json', 'Vote\VoteController@putVote');            //投票主题 修改
    //Route::delete('vote/vote.json', 'Vote\VoteController@deleteVote');    //投票主题 删除
    Route::put('vote/voteswitch.json', 'Vote\VoteController@putVoteSwitch');//投票主题 开关
    
    Route::post('vote/votedetail.json', 'Vote\VoteController@postVoteDetail');      //投票选项 创建
    Route::get('vote/votedetaillist.json', 'Vote\VoteController@getVoteDetailList');//投票选项 查看
    Route::put('vote/votedetail.json', 'Vote\VoteController@putVoteDetail');        //投票选项 修改
    Route::delete('vote/votedetail.json', 'Vote\VoteController@deleteVoteDetail');  //投票选项 删除
    
    Route::get('vote/getflushcache.json', 'Vote\VoteController@getflushcache');     //手工清除缓存
    
});


$super_prefix          = 'super_api';		//项目前缀
$super_namespace       = 'Custom';	//项目命名空间
$super_middleware      = 'auth.super'; //项目中间件，需要登录信息接口使用
/**年会投票**/
Route::group(['prefix' => $super_prefix, 'namespace' => $super_namespace,'middleware' => $super_middleware],function(){

    Route::get('vote/list.json', 'Superpc\VoteController@getVoteLists');//所有活动

    Route::get('vote/update.json', 'Superpc\VoteController@getVoteStatus');//更新活动状态

});
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
$whb_prefix          = 'whb';		//项目前缀
$whb_namespace       = 'Whb';	//项目命名空间
$whb_middleware      = 'applet'; //不需要授权的中间件

Route::group(['prefix' => $whb_prefix, 'namespace' => $whb_namespace], function () {
    Route::get('coupon/list.json', 'Coupon\CouponController@getLists'); //获取商户优惠劵列表
    Route::get('coupon/detail.json', 'Coupon\CouponController@couponDetails'); //获取商户单个优惠劵详情
    Route::get('coupon/codedetail.json', 'Coupon\CouponController@couponCodeDetails'); //获取商户单个优惠劵码详情
    Route::get('coupon/give.json', 'Coupon\CouponController@giveMemberCoupon'); //发放会员优惠券
    Route::get('coupon/formember.json', 'Coupon\CouponController@forMember'); //发放会员优惠券
});

Route::group(['prefix' => $whb_prefix, 'namespace' => $whb_namespace], function () {
    Route::get('member/getinfo.json', 'Member\MemberController@getMemberInfo'); //获取用户信息
    Route::get('member/tokentoopenid.json', 'Member\MemberController@getMemberOpenid');
    Route::get('member/accesstoken.json', 'Member\MemberController@getAccessToken'); //获取用户信息
    Route::get('officialaccount.json', 'WeixinController@officialAccount'); //获取公众号信息
    Route::get('member/givecredit.json', 'Member\MemberController@giveCredit'); //会员积分变动
    Route::get('member/getcredit.json', 'Member\MemberController@getCredit'); //会员积分
    Route::get('applet.json', 'WeixinController@appletList'); //获取小程序信息
    Route::get('apitoken.json', 'WeixinController@getToken'); //获取apiToken
    Route::get('merchat.json', 'WeixinController@getMerchant'); //获取商户信息
    Route::get('delbind.json', 'WeixinController@deleteBinding'); //解除公众号绑定
    Route::get('delopen.json', 'WeixinController@opendel'); //清除主体
    Route::get('version.json', 'WeixinController@getVersion'); //获取版本（根据商户id）

});

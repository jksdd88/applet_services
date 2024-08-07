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

$admin_prefix = 'admin';    //项目前缀  
$admin_namespace = 'Admin';    //项目命名空间 
$admin_middleware = 'auth.api'; //项目中间件，需要会员信息接口使用

//登录
Route::group(['prefix' => $admin_prefix, 'namespace' => $admin_namespace], function () {
    Route::post('auth/login.json', 'Auth\AuthController@postLogin');//登录
    Route::get('auth/send_verfity_message.json', 'Auth\AuthController@SendsmsMessage');//短信验证码
    Route::put('auth/resetpwd', 'Auth\AuthController@resetPwd');//重置密码
    Route::get('auth/logout.json', 'Auth\AuthController@getLogout');//退出
    //登录跳转
    Route::get('login', function () {
        return Redirect::to('manage/login');
    });
    //Route::put('modifypass.json', 'Auth\AuthController@putModifypass');//修改密码
    Route::get('auth/ssologin.json', 'Auth\AuthController@getSSOLogin');//SSO:单点登录
    Route::get('auth/ssoaccountenable.json', 'Auth\AuthController@getAccountEnable');//SSO登录:账号是否过期
    
    Route::get('user/ssomodifypass.json', 'User\UserController@getSSOModifypass');//SSO:修改密码同步到小程序
    Route::get('user/ssooldlogin.json', 'User\UserController@getSSOOldLogin');//SSO登录:兼容旧账号
});

//权限模块
Route::group(['prefix' => $admin_prefix, 'middleware' => $admin_middleware, 'namespace' => $admin_namespace], function () {
    Route::post('priv/priv.json', 'Priv\PrivController@postPriv');//权限模块:新增
    Route::get('priv/all_privs.json', 'Priv\PrivController@getAllPrivs');//权限模块:显示列表
    Route::get('priv/version_privs.json', 'Priv\PrivController@getVersionPrivs');//权限模块:显示列表
    Route::get('priv/user_privs.json', 'Priv\PrivController@getUserPrivs');//权限模块:显示列表
    Route::put('priv/{id}.json', 'Priv\PrivController@putPriv');//权限模块:修改
    Route::delete('priv/{id}.json', 'Priv\PrivController@deletePriv');//权限模块:删除
    
    Route::get('optpriv/all_privs.json', 'Priv\PrivController@get_all_priv');//优化权限:显示列表
});

//角色
Route::group(['prefix' => $admin_prefix, 'middleware' => $admin_middleware, 'namespace' => $admin_namespace], function () {
    Route::post('role/role.json', 'Priv\RoleController@postRole');//角色:新增
    Route::get('role/roles.json', 'Priv\RoleController@getRoles');//角色:显示列表
    Route::put('role/{id}.json', 'Priv\RoleController@putRole');//修改角色
    Route::delete('role/{id}.json', 'Priv\RoleController@deleteRole');//删除角色

    Route::get('role/role_priv.json', 'Priv\RolePrivController@getRolePriv');//角色:显示列表
    Route::put('setpriv/{role_id}.json', 'Priv\RoleController@setRolePrivs');//设置角色权限
});

//个人设置:需要登录
Route::group(['middleware' => $admin_middleware, 'prefix' => $admin_prefix], function () {
    Route::get('me.json', 'Admin\User\UserController@getMe');//个人信息
    Route::post('verify_mobile_message.json', 'Admin\User\UserController@postVerifyMobileMessage');//修改手机号下一步
    Route::put('admin_mobile.json', 'Admin\User\UserController@putAdminMobile');//修改手机号
    Route::get('send_verfity_message.json', 'Admin\User\UserController@sendSmsMessage');//发送手机验证码
    Route::get('user/bindwechatbindurl.json', 'Admin\User\UserController@getBindwechatBindURL');//核销验证微信:绑定微信
    Route::put('user/unbindwechat.json', 'Admin\User\UserController@putUnbindWechat');//核销验证微信:解除绑定
});
//个人设置:不需要登录
Route::group(['prefix' => $admin_prefix, 'namespace' => $admin_namespace], function () {
    Route::get('user/bindwechatcallbackopenid.json', 'User\UserController@getBindwechatCallbackOpenid');//核销验证微信:回调接收open_id并保存
    Route::get('user/user_version.json', 'User\UserController@getUserVersion');//获取用户版本
});

//管理员
Route::group(['namespace' => $admin_namespace], function () {
    Route::post('admin/user.json', ['middleware' => 'auth.api', 'uses' => 'User\UserController@postUser']); //管理员:新增
    Route::put('admin/user/{id}.json', ['as' => 'putUser', 'middleware' => 'auth.api', 'uses' => 'User\UserController@putUser']);//管理员:编辑
    Route::get('admin/user.json', ['middleware' => 'auth.api:setting_admin', 'uses' => 'User\UserController@getUsers']); //管理员:列表
    Route::get('admin/user/{id}.json', ['middleware' => 'auth.api', 'uses' => 'User\UserController@getUser']); //管理员:详情
    Route::delete('admin/user/{id}.json', ['middleware' => 'auth.api', 'uses' => 'User\UserController@deleteUser']); //管理员:删除
    //管理员权限
    Route::get('admin/user_privs/{id}.json', ['middleware' => 'auth.api', 'uses' => 'User\UserController@getUserPrivs']);//设置权限:获取(管理员权限+角色权限)
    Route::get('admin/user_priv/{id}.json', ['middleware' => 'auth.api', 'uses' => 'User\UserController@getUserPriv']);//设置权限:获取(管理员权限)
    Route::get('user_login_privs.json', ['middleware' => 'auth.api', 'uses' => 'User\UserController@getUserLoginPrivs']);//设置权限:登录的管理员权限
    Route::put('admin/user_privs/{id}.json', ['middleware' => 'auth.api', 'uses' => 'User\UserController@putUserPrivs']);//设置权限:修改管理员权限

    Route::get('admin/user_init.json', ['middleware' => 'auth.api', 'uses' => 'User\UserController@getInitUser']);//初始化管理员
    //密码
    Route::put('pass.json', ['as' => 'userPass', 'uses' => 'User\UserController@putPassword']);//修改密码
    //Route::put('forgetpass.json', ['as' => 'forgetUserPass', 'uses' => 'User\UserController@putForgetPassword']);
    Route::put('verifiyphone.json', ['as' => 'verifiyUserPhone', 'uses' => 'User\UserController@putVerifyPhone']);//验证手机
});

//版本
Route::group(['prefix' => $admin_prefix, 'namespace' => $admin_namespace], function () {
    Route::post('version/version.json', 'Priv\VersionController@postVersion');//版本:新增
    Route::get('version/versions.json', ['middleware' => 'auth.api', 'uses' => 'Priv\VersionController@getVersions']);//版本:显示列表
    Route::put('version/{id}.json', 'Priv\VersionController@putVersion');//版本:修改
    Route::delete('version/{id}.json', 'Priv\VersionController@deleteVersion');//版本：删除
    Route::get('version/version_priv/{id}.json', 'Priv\VersionController@getVersionPriv');//版本-权限：查看
    Route::put('version/version_priv.json', 'Priv\VersionController@putVersionPriv');//版本-权限：修改
    Route::post('version/version_priv.json', 'Priv\VersionController@postVersionPriv');//版本-权限：修改
    Route::get('version_userpriv.json', 'Priv\VersionController@getUserPriv');   // 在满减中测试UserPrivService服务
});


//商户:需要登录
Route::group(['prefix' => $admin_prefix, 'middleware' => $admin_middleware, 'namespace' => $admin_namespace], function () {//
    Route::get('merchant/merchant.json', 'Merchant\MerchantController@getMerchant'); //获取商户信息
    Route::put('merchant/merchant.json', 'Merchant\MerchantController@putMerchant'); //修改商户信息设置
    Route::put('merchant/improveinfo.json', 'Merchant\MerchantController@putImproveInfo'); //简化手机注册,第二步:完善信息
    Route::put('merchant/modifypass.json', 'Merchant\MerchantController@putModifypass'); //修改密码-商户管理员
    Route::post('merchant/counselor_trail.json', 'Merchant\MerchantController@postCounselorTrail'); //是否需要销售顾问跟进
    Route::get('merchant/openapi_init.json', 'Merchant\MerchantController@OpenApiInit'); //是否需要销售顾问跟进
    Route::get('merchant/refreshmerchantsecret.json', 'Merchant\MerchantController@putMerchantDdcsecret'); //更新开放API密码
});
//商户:不要登录
Route::group(['prefix' => $admin_prefix, 'namespace' => $admin_namespace], function () {//
    Route::post('merchant/merchant.json', 'Merchant\MerchantController@postMerchant'); //开账号
    Route::post('merchant/merchantinfo.json', 'Merchant\MerchantController@postMerchantInfo'); //修改账号
    Route::put('merchant/m_improveinfo.json', 'Merchant\MerchantController@putImproveInfo'); //简化手机注册,第二步:手机端完善信息
    Route::post('merchant/merchantinfo.json', 'Merchant\MerchantController@postMerchantInfo'); //开账号
    Route::get('merchant/mimproveinfo.json', 'Merchant\MerchantController@putImproveInfo'); //简化手机注册,第二步:完善信息
    Route::get('merchant/repairmerchant.json', 'Merchant\MerchantController@repairMerchant'); //调用单点登录接口出错,修复数据
    Route::get('merchant/checkagain.json', 'Merchant\MerchantController@postCheckagain'); //原力系统:验重
    Route::get('redirecttodd/{url}', function ($url) {
        //dd($url);
        return Redirect::to(base64_decode($url));
    });
    
    Route::post('liveaccount/agentaddbalance.json', 'Live\LiveAccountController@agentAddBalance'); //代理商给商家充值点点币
    Route::get('liveaccount/agentgetbalance.json', 'Live\LiveAccountController@agentGetBalance'); //代理商获取商家点点币数量
    
    
});

//商家日志
Route::group(['prefix' => $admin_prefix, 'middleware' => $admin_middleware, 'namespace' => $admin_namespace], function () {
    Route::get('userlog/userlog.json', 'Userlog\UserlogController@putUserlog'); //手工调整日志记录
});

//公用接口
//七牛云:需要登录
Route::group(['prefix' => 'attachment', 'namespace' => $admin_namespace], function () {
    Route::get('qiniu_token', ['middleware' => 'auth.api', 'uses' => 'Attachment\AttachmentController@qiniuToken']);  //获取七牛token
    Route::delete('attachment.json', ['middleware' => 'auth.api', 'uses' => 'Attachment\AttachmentController@deleteAttachments']);//删除文件
    Route::put('attachment/{id}.json', ['middleware' => 'auth.api', 'uses' => 'Attachment\AttachmentController@putAttachment']);//修改名称
    Route::put('attachments.json', ['middleware' => 'auth.api', 'uses' => 'Attachment\AttachmentController@putAttachments']);//批量设置图片分组
    Route::get('attachment.json', ['middleware' => 'auth.api', 'uses' => 'Attachment\AttachmentController@getAttachments']);//获取图片列表
    Route::post('attachmentupload.json', ['middleware' => 'auth.api', 'uses' => 'Attachment\AttachmentController@uploadQiniu']);//上传图片
});
//七牛云:不要登录
Route::group(['prefix' => 'attachment', 'namespace' => $admin_namespace], function () {
    Route::post('qiniu_callback', ['as' => 'qiniu_callback', 'uses' => 'Attachment\AttachmentController@qiniuCallback']);//
    Route::get('qiniu_callback', ['as' => 'qiniu_callback', 'uses' => 'Attachment\AttachmentController@qiniuCallback']);
});
//七牛云分组:需要登录
Route::group(['middleware' => $admin_middleware, 'namespace' => $admin_namespace], function () {
    Route::post('attachment/group.json', 'Attachment\AttachmentGroupController@postGroups');//分组：新增
    Route::put('attachment/group.json', 'Attachment\AttachmentGroupController@postGroups');//分组：编辑
    Route::delete('attachment/group/{id}.json', 'Attachment\AttachmentGroupController@deleteGroup');//删除分组
    //Route::post('attachment/setGroup.json', 'Admin\Attachment\AttachmentGroupController@postSetGroup');//设置分组
    Route::get('attachment', 'Attachment\AttachmentController@getAttachments');//获取文件列表
    Route::get('attachment/group.json', 'Attachment\AttachmentGroupController@getGroups');//获取分组
});

//虚拟人物
Route::group(['middleware' => $admin_middleware, 'namespace' => $admin_namespace, 'prefix' => 'virtual'], function () {
    Route::get('members.json', 'Attachment\VirtualMemberController@getMembers');//获取列表
    Route::get('member/{id}.json', 'Attachment\VirtualMemberController@getMember');//获取单个
    Route::post('member.json', 'Attachment\VirtualMemberController@postMember');//新增
    Route::put('member/{id}.json', 'Attachment\VirtualMemberController@putMember');//修改
    Route::delete('member/batch.json', 'Attachment\VirtualMemberController@batchDeleteMember');//批量删除
});

//获取城市数据
Route::get('regions.json', 'Admin\Region\RegionController@getDtree');
Route::get('regionAll.json', 'Admin\Region\RegionController@getRegion');
Route::get('region.json', 'Admin\Region\RegionController@getSubRegion');
//获取行业分类
Route::get('industry.json', 'Admin\Industry\IndustryController@getDtree');
//客服中心
Route::group(['middleware' => $admin_middleware, 'namespace' => $admin_namespace], function () {
    Route::get('customer_service.json', 'Custservice\CustserviceController@getLink');
});
//点点秀
Route::group(['middleware' => $admin_middleware, 'namespace' => $admin_namespace], function () {
    Route::get('xiu_link.json', 'Xiu\XiuController@getLink');
});

//达达物流回调
Route::group(['prefix' => 'dada', 'namespace' => $admin_namespace], function () {
    Route::any('order/callback', 'Express\OrderEventController@callback');//订单回到
});
//达达物流
Route::group(['prefix' => $admin_prefix.'/express', 'middleware' => $admin_middleware , 'namespace' => $admin_namespace], function () { //
    Route::any('order/cache.json', 'Express\OrderController@cacheAction');//测试代码

    Route::get('typelist.json', 'Express\IndexController@typeList');//业务类型
    Route::get('citylist.json', 'Express\IndexController@getCity');//支持城市列表
    Route::get('cancellist.json', 'Express\IndexController@getCancel');//取消原因列表
    Route::get('complaintlist.json', 'Express\IndexController@getComplaint');//投诉原因列表
    Route::get('transporterlist.json', 'Express\IndexController@getTransporter');//获取骑手列表

    Route::get('account.json', 'Express\IndexController@getPrice');//账户余额
    Route::get('recharge.json', 'Express\IndexController@recharge');//账户充值

    Route::get('info.json', 'Express\IndexController@getMerchant');//达达商户信息
    Route::put('switch.json', 'Express\IndexController@switchMerchant');//达达商户开发
    Route::post('register.json', 'Express\IndexController@register');//达达商户注册
    Route::get('shop/list.json', 'Express\IndexController@listShop');//店铺列表
    Route::get('shop/detail.json', 'Express\IndexController@getShop');//店铺详情
    Route::post('shop/add.json', 'Express\IndexController@addShop');//添加店铺
    Route::put('shop/edit.json', 'Express\IndexController@editShop');//编辑店铺

    Route::post('order/subscribe.json', 'Express\OrderController@subscribe');//预约订单
    Route::put('order/resend.json', 'Express\OrderController@resend');//确认订单发送
    Route::get('order/details.json', 'Express\OrderController@details');//订单详情
    Route::delete('order/cancel.json', 'Express\OrderController@cancel');//取消订单
    Route::put('order/distribution.json', 'Express\OrderController@distribution');//分配订单
    Route::put('order/tips.json', 'Express\OrderController@tips');//增加小费
    Route::post('order/complaints.json', 'Express\OrderController@complaints');//投诉达达

});


//咪咕直播回调通知事件
Route::group(['prefix' => 'migu', 'namespace' => $admin_namespace], function () {
    //通知
    Route::any('notice/live.json', 'Admin\Live\LiveEventContrller@liveStatus');
    Route::any('notice/recordc.json', 'Admin\Live\LiveEventContrller@recordComplete');
    Route::any('notice/record.json', 'Admin\Live\LiveEventContrller@record');
    Route::any('notice/imagestart.json', 'Admin\Live\LiveEventContrller@imageStart');
    Route::any('notice/imageend.json', 'Admin\Live\LiveEventContrller@imageEnd');
    Route::any('notice/pornographic.json', 'Admin\Live\LiveEventContrller@pornographicNotice');
    Route::any('notice/terrorism.json', 'Admin\Live\LiveEventContrller@terrorismNotice');
    Route::any('notice/political.json', 'Admin\Live\LiveEventContrller@politicalNotice');
    Route::any('notice/ccnotify.json', 'Admin\Live\LiveEventContrller@ccnotifyNotice');
    Route::any('notice/ccstatic.json', 'Admin\Live\LiveEventContrller@ccstaticNotice');
    //上传通知
    Route::any('notice/uploadfinish.json', 'Admin\Live\LiveEventContrller@uploadFinish');
    Route::any('notice/upload.json', 'Admin\Live\LiveEventContrller@upload');
    Route::any('notice/uploadtrans.json', 'Admin\Live\LiveEventContrller@uploadTrans');
    Route::any('notice/uploadreview.json', 'Admin\Live\LiveEventContrller@uploadReview');
    Route::any('notice/uploadctrl.json', 'Admin\Live\LiveEventContrller@uploadCtrl');
});

//微信平台通信和操作
Route::group(['prefix' => 'weixin', 'namespace' => $admin_namespace], function () {
    //第三方平台通信
    Route::any('component/receive', 'Weixin\ComponentController@receive');
    Route::post('message/{app_id}', 'Weixin\ComponentController@message');
    //super 操作
    Route::get('operation/openDelete.json', 'Weixin\OperationController@openDelete');//清除主体信息
    Route::get('operation/version.json', 'Weixin\OperationController@appletVersion');//版本上传
    Route::get('operation/onlinever.json', 'Weixin\OperationController@onlineVersion');//版本上线
    Route::get('operation/getver.json', 'Weixin\OperationController@versionList');//版本已上传列表
    Route::get('operation/versionback.json', 'Weixin\OperationController@versionBack');//版本撤销
    Route::get('operation/verifyback.json', 'Weixin\OperationController@verifyBack');//审核撤销
    Route::get('operation/upgrade.json', 'Weixin\OperationController@upgrade');//版本升级 同脚本
    Route::get('operation/verify.json', 'Weixin\OperationController@verify');//版本检测 同脚本
    Route::get('operation/formidDelete.json', 'Weixin\OperationController@formidDelete');//清除 formid
    Route::get('operation/clearQuota.json', 'Weixin\OperationController@clearQuota');//清除平台调用限制
    Route::get('operation/clearToken.json', 'Weixin\OperationController@clearToken');//清除token 缓存
    Route::get('operation/orderRefund.json', 'Weixin\OperationController@orderRefund');//退款 处理
    Route::get('operation/order.json', 'Weixin\OperationController@order');//订单 处理
    Route::get('operation/search.json', 'Weixin\OperationController@search');//搜索设置
    Route::get('operation/clearapp.json', 'Weixin\OperationController@clearApp');//清理app

    Route::any('operation/test', 'Weixin\OperationController@index');//测试代码

});
//微信授权和小程序
Route::group(['prefix' => 'weixin', 'middleware' => $admin_middleware, 'namespace' => $admin_namespace], function () {
    Route::get('appinfo.json', 'Weixin\OfficialController@appServer');//公众账号信息
    Route::post('msgtpl.json', 'Weixin\OfficialController@setMsgTemplate');//设置消息模板id
    Route::get('getmsgtpl.json', 'Weixin\OfficialController@getMsgTemlate');//查看消息模板id
    Route::get('authback', 'Weixin\AuthorizeController@authorizeback');//授权回调
    Route::get('authorizes.json', 'Weixin\AuthorizeController@authorizes');//获取授权链接
    Route::get('refresh.json', 'Weixin\AuthorizeController@refresh');//刷新账号信息
    Route::delete('authdelete.json', 'Weixin\AuthorizeController@authdel');//删除授权小程序
    Route::get('info.json', 'Weixin\OfficialController@info');//小程序详细信息
    Route::get('list.json', 'Weixin\OfficialController@appList'); //小程序列表
    Route::post('setpay.json', 'Weixin\OfficialController@setPayInfo');//设置支付信息
    Route::post('uplpay.json', 'Weixin\OfficialController@uplPayInfo');//上传支付证书
    Route::post('changetype.json', 'Weixin\AuthorizeController@changeTpl');//切换小程序
    Route::get('category.json', 'Weixin\AuthorizeController@category');//获取类目
    Route::post('verify.json', 'Weixin\AuthorizeController@verify');//提交审核
    Route::post('qrcode.json', 'Weixin\AuthorizeController@refreshQrcode');//刷新二维码
    Route::post('upgrade.json', 'Weixin\AuthorizeController@upgrade');//更新版本
    Route::get('experience.jpg', 'Weixin\AuthorizeController@experience');//体验二维码
    Route::get('dynamicQrcode.jpg', 'Weixin\AuthorizeController@dynamicQrcode');//动态参数二维码
    Route::post('autotpl.json', 'Weixin\AuthorizeController@autoTplId');//消息模板id自动生成 开关
    Route::get('getspread.json', 'Weixin\OfficialController@getSpreadQrcode');//小程序码列表
    Route::post('delspreadqr.json', 'Weixin\OfficialController@delSpreadQrcode');//小程序码删除
    Route::post('spreadqrcode.json', 'Weixin\AuthorizeController@spreadQrcode');//小程序码
    Route::get('downqrcode.json', 'Weixin\OfficialController@downloadQrcode');//小程序码下载
    //小程序支付设置开关
    Route::post('paysettingswitch.json', 'Weixin\AuthorizeController@paysettingSwitch');//小程序 支付方式 开关设置
    Route::get('paysettinginit.json', 'Weixin\AuthorizeController@paysettingInit');//小程序 支付方式 开关设置：初始化数据
});
//公众账号代码
Route::group(['prefix' => 'weixin', 'namespace' => $admin_namespace], function () {
    Route::get('official/getqrcode.json', 'Weixin\WechatOfficialController@getQrcode');//带参数二维码
    Route::get('official/getnotice.json', 'Weixin\WechatOfficialController@listNotice');//绑定接收者
    Route::post('official/delnotice.json', 'Weixin\WechatOfficialController@deleteNotice');//删除接收者
    Route::post('official/setnotice.json', 'Weixin\WechatOfficialController@setNotice');//通知成员设置
    Route::get('official/gettpl.json', 'Weixin\WechatOfficialController@getTplId');//模板id状态
    Route::post('official/settpl.json', 'Weixin\WechatOfficialController@autoTplId');//模板id设置
    Route::get('official/get.json', 'Weixin\WechatOfficialController@whtest');//综合获取

});



//Test
Route::group(['prefix' => 'test'], function () {
    Route::any('wy', 'Test\TestController@wy');
    Route::any('wy2', 'Test\TestController@wy2');
    Route::get('weixin', 'weixin\CommonController@test');
    Route::get('order_daily', 'Test\TestController@orderDaily');//商家每日统计数据
    Route::post('attachmentupload_test.json', 'Admin\Attachment\AttachmentController@request_by_curl');//上传图片
});

Route::group(['prefix' => 'weapp', 'namespace' => 'Weapp'], function () {
    Route::get('coupon/list.json', 'CouponController@getList');
});

//积分:需要登录
//Route::group(['prefix' => 'credit', 'middleware' => $admin_middleware], function () {//
Route::group(['prefix' => 'credit'], function () {//
    Route::get('credits.json', ['middleware' => 'auth.api', 'uses' => 'Admin\Credit\CreditController@getCredits']); //获取积分列表
    Route::post('rule.json', ['middleware' => 'auth.api:member_creditsetting', 'uses' => 'Admin\Credit\CreditController@postCreditRule']); //积分设置
    Route::post('setting.json', ['middleware' => 'auth.api', 'uses' => 'Admin\Credit\CreditController@Setting']); //积分抵扣设置
    Route::get('setting.json', ['middleware' => 'auth.api:member_creditsetting', 'uses' => 'Admin\Credit\CreditController@getSetting']); //获取积分抵扣设置
    Route::get('survey.json', ['middleware' => 'auth.api:member_creditoverview', 'uses' => 'Admin\Credit\CreditController@getCreditSurvey']); //积分概况
    Route::post('memberCredit.json', ['middleware' => 'auth.api', 'uses' => 'Admin\Credit\CreditController@putMemberCredit']); //送积分
    Route::get('rule.json', ['middleware' => 'auth.api', 'uses' => 'Admin\Credit\CreditController@getCreditRules']); //获取积分设置
    Route::post('giveCredit.json', ['middleware' => 'auth.api', 'uses' => 'Admin\Credit\CreditController@putMemberCredit']); //获取积分设置
});
//配送:需要登录
Route::group(['prefix' => 'delivery'], function () {
    //物流公司配置
    Route::get('delivery.json', ['middleware' => 'auth.api', 'uses' => 'Admin\Delivery\DeliveryController@getDelivery']);//列表
    Route::post('delivery.json', ['middleware' => 'auth.api', 'uses' => 'Admin\Delivery\MerchantDeliveryController@postMerchantDeliver']);//新增
    Route::delete('delivery/{id}.json', ['middleware' => 'auth.api', 'uses' => 'Admin\Delivery\MerchantDeliveryController@deleteMerchantDeliver']);//删除
    //运费模板
    Route::get('shipment.json', ['middleware' => 'auth.api', 'uses' => 'Admin\Delivery\ShipmentController@getShipments']);//列表
    Route::get('shipment/{id}.json', ['middleware' => 'auth.api:trade_freighttemplate', 'uses' => 'Admin\Delivery\ShipmentController@getShipment']);//详情
    Route::post('shipment.json', ['middleware' => 'auth.api:trade_freighttemplate', 'uses' => 'Admin\Delivery\ShipmentController@postShipment']);//新增
    Route::put('shipment/{id}.json', ['middleware' => 'auth.api:trade_freighttemplate', 'uses' => 'Admin\Delivery\ShipmentController@putShipment']);//编辑
    Route::delete('shipment/{id}.json', ['middleware' => 'auth.api:trade_freighttemplate', 'uses' => 'Admin\Delivery\ShipmentController@deleteShipment']);//删除
    Route::post('copy_shipment/{id}.json', ['middleware' => 'auth.api:trade_freighttemplate', 'uses' => 'Admin\Delivery\ShipmentController@copyShipment']);//新增
    //运单模板
    Route::get('waybill.json', ['middleware' => 'auth.api', 'uses' => 'Admin\Delivery\WaybillController@getWaybills']); //查询列表
    Route::get('waybill/{id}.json', ['middleware' => 'auth.api:trade_waybilltemplate', 'uses' => 'Admin\Delivery\WaybillController@getWaybill']); //单运单查询
    Route::post('waybill.json', ['middleware' => 'auth.api:trade_waybilltemplate', 'uses' => 'Admin\Delivery\WaybillController@postWaybill']); //添加
    Route::put('waybill/{id}.json', ['middleware' => 'auth.api:trade_waybilltemplate', 'uses' => 'Admin\Delivery\WaybillController@putWaybill']); //修改
    Route::delete('waybill/{id}.json', ['middleware' => 'auth.api:trade_waybilltemplate', 'uses' => 'Admin\Delivery\WaybillController@deleteWaybill']); //删除
    //配送 自定义命名

    //预约配送
    Route::get('appoint/detail.json', ['middleware' => 'auth.api', 'uses' => 'Admin\Delivery\AppointController@getdetail']); //详情
    Route::post('appoint/edit.json', ['middleware' => 'auth.api', 'uses' => 'Admin\Delivery\AppointController@edit']); //编辑
    Route::get('appoint/aa.json', ['middleware' => 'auth.api', 'uses' => 'Admin\Delivery\AppointController@isShow']);
    Route::put('appoint/putenabled_store.json', ['middleware' => 'auth.api', 'uses' => 'Admin\Delivery\AppointController@putEnabledStore']); //同城配送门店开启/关闭
	
	//物流配送
    Route::put('enabled/delivery.json', ['middleware' => 'auth.api', 'uses' => 'Admin\Enabled\EnabledController@putDelivery']); //物流配送开启/关闭
	
    Route::get('delivery_alias.json', ['middleware' => 'auth.api', 'uses' => 'Admin\Delivery\MerchantDeliveryController@getAliasSetting']);//获取配送别名
    Route::put('delivery_alias.json', ['middleware' => 'auth.api', 'uses' => 'Admin\Delivery\MerchantDeliveryController@setAliasSetting']);//设置配送别名

});

//拼团:需要登录
Route::group(['prefix' => 'fightgroup', 'middleware' => $admin_middleware], function () {
    Route::get('fightgroups.json', 'Admin\Fightgroup\TuanController@getFightgroups');          // 拼团列表
    Route::get('fightgroupsGoodInfo.json', 'Admin\Fightgroup\TuanController@fightgroupsGoodInfo');     // 拼团列表（装修用此接口）
    Route::get('stocks/{id}.json', 'Admin\Fightgroup\TuanController@getStocks');               // 拼团规格库存明细
    Route::get('refunds.json', 'Admin\Fightgroup\TuanController@getRefunds');                  // 退款列表
    Route::put('close_ladder/{id}.json', 'Admin\Fightgroup\TuanController@closeLadder');       // 关闭指定人数团活动
    Route::post('fightgroup.json', 'Admin\Fightgroup\TuanController@postTuan');                // 添加拼团
    Route::put('fightgroup/{id}.json', 'Admin\Fightgroup\TuanController@putFightgroup');       // 编辑拼团
    Route::get('launchs/{id}.json', 'Admin\Fightgroup\TuanController@getLaunchs');             // 开团列表(活动详情)
    Route::get('statis/{id}.json', 'Admin\Fightgroup\TuanController@getStatis');               // 数据统计
    Route::get('joins/{id}.json', 'Admin\Fightgroup\TuanController@getJoins');                 // 参团列表
    Route::get('fightgroup/{id}.json', 'Admin\Fightgroup\TuanController@getFightgroup');       // 拼团详情
    Route::get('goods_props/{id}.json', 'Admin\Fightgroup\TuanController@getGoodsProp');       // 商品规格
    Route::put('extend_launch/{id}.json', 'Admin\Fightgroup\TuanController@putExtendLaunch');  // 修改拼团时间
});

//商品导入需要登录
Route::group(['prefix' => $admin_prefix, 'middleware' => $admin_middleware, 'namespace' => $admin_namespace], function () {
    Route::post('goods/goodsCsv.json', 'Goods\GoodsCsvController@postGoodsCsv');//导入淘宝助手导出的商品文件
    Route::get('goods/goodslist.json', 'Goods\GoodsCsvController@getGoodsList');//导入的商品文件列表
    Route::get('goods/goodsInfo/{id}.json', 'Goods\GoodsCsvController@getGoodsInfo');//获取商品信息
    Route::put('goods/goods.json', 'Goods\GoodsCsvController@putGoods');//删除商品（单个或批量删除）

});


//商品:需要登录
Route::group(['prefix' => $admin_prefix, 'namespace' => $admin_namespace], function () {
    Route::get('goods/cat.json', ['middleware' => 'auth.api', 'uses' => 'Goods\GoodsCatController@getGoodsCat']); //商品分类列表
    Route::get('goods/group.json', ['middleware' => 'auth.api', 'uses' => 'Goods\GoodsGroupController@index']);//商品分组列表
    Route::delete('goods/group/del.json', ['middleware' => 'auth.api', 'uses' => 'Goods\GoodsGroupController@delete']);//商品分组删除
    Route::get('goods/getwarning.json', ['middleware' => 'auth.api', 'uses' => 'Goods\GoodsController@getWarning']);//获取预警数值
    Route::post('goods/setwarning.json', ['middleware' => 'auth.api', 'uses' => 'Goods\GoodsController@setWarning']);//设置预警数值
    Route::post('goods/group/add.json', ['middleware' => 'auth.api', 'uses' => 'Goods\GoodsGroupController@add']);//商品分组添加
    Route::put('goods/group/edit.json', ['middleware' => 'auth.api', 'uses' => 'Goods\GoodsGroupController@edit']);//商品分组编辑
    Route::get('goods/group/detail.json', ['middleware' => 'auth.api', 'uses' => 'Goods\GoodsGroupController@detail']);//商品分组详情
    Route::post('goods/prop/add.json', ['middleware' => 'auth.api', 'uses' => 'Goods\GoodsPropController@add']);//商品属性&规格新增
    Route::delete('goods/prop/del.json', ['middleware' => 'auth.api', 'uses' => 'Goods\GoodsPropController@delete']);//商品属性&规格删除
    Route::put('goods/prop/edit.json', ['middleware' => 'auth.api', 'uses' => 'Goods\GoodsPropController@edit']);//商品属性&规格编辑
    Route::get('goods/prop.json', ['middleware' => 'auth.api', 'uses' => 'Goods\GoodsPropController@index']);//商品属性&规格列表
    Route::get('goods/propval.json', ['middleware' => 'auth.api', 'uses' => 'Goods\GoodsPropValController@index']);//商品属性&规格值列表
    Route::post('goods/propval/add.json', ['middleware' => 'auth.api', 'uses' => 'Goods\GoodsPropValController@add']);//商品属性&规格值新增
    Route::delete('goods/propval/del.json', ['middleware' => 'auth.api', 'uses' => 'Goods\GoodsPropValController@delete']);//商品属性&规格值删除
    Route::post('goods/isdiscount.json', ['middleware' => 'auth.api', 'uses' => 'Goods\GoodsController@isDiscount']);//是否参与会员折扣
    Route::post('goods/onsale.json', ['middleware' => 'auth.api', 'uses' => 'Goods\GoodsController@onsale']);  //商品上下架（单个或者批量）
    Route::post('goods/column/change.json', ['middleware' => 'auth.api', 'uses' => 'Goods\GoodsController@columnChange']);//更新商品名称，单规格价格，单规格库存
    Route::get('goods/getmultisku.json', ['middleware' => 'auth.api', 'uses' => 'Goods\GoodsPropController@getMultiSku']);//获取多规格数据
    Route::put('goods/editmultisku.json', ['middleware' => 'auth.api', 'uses' => 'Goods\GoodsPropController@editMultiSku']);//编辑多规格数据
    Route::post('goods/del.json', ['middleware' => 'auth.api:goods_del', 'uses' => 'Goods\GoodsController@delete']);//商品删除(单个或者批量删除)
    Route::post('goods/goodsgroup.json', ['middleware' => 'auth.api', 'uses' => 'Goods\GoodsController@setGoodsGroup']);  //设置商品分组（批量设置商品分组）
    Route::post('goods/setarea.json', ['middleware' => 'auth.api', 'uses' => 'Goods\GoodsController@setArea']);//区域设置(批量设置不配送区域)
    Route::get('goods/list.json', ['middleware' => 'auth.api', 'uses' => 'Goods\GoodsController@index']); //商品列表(出售中，库存紧张，仓库中)
    Route::get('goods/detail.json', ['middleware' => 'auth.api', 'uses' => 'Goods\GoodsController@detail']);//商品详情
    Route::post('goods/add.json', ['middleware' => 'auth.api', 'uses' => 'Goods\GoodsController@add']);//发布商品
    Route::put('goods/edit.json', ['middleware' => 'auth.api', 'uses' => 'Goods\GoodsController@edit']);//商品编辑

    Route::get('goods/markinglist.json', ['middleware' => 'auth.api', 'uses' => 'Goods\GoodsController@getMarkingList']);//营销活动商品
    Route::get('goods/prop/default.json', ['middleware' => 'auth.api', 'uses' => 'Goods\GoodsPropController@addDefaultProp']);//添加预约商品默认规格属性值

    //服务
    Route::get('appt/board.json', ['middleware' => 'auth.api', 'uses' => 'Appt\BoardController@index']); //预约看板

    //出库/入库
    Route::put('goods/stock.json', ['middleware' => 'auth.api', 'uses' => 'Goods\GoodsController@putStock']);//商品编辑
});

//知识付费
Route::group(['prefix' => $admin_prefix, 'namespace' => $admin_namespace, 'middleware' => $admin_middleware], function () {
    Route::get('knowledge/column.json', 'Knowledge\KnowledgeColumnController@index');//专栏列表
    Route::get('knowledge/column/{id}.json', 'Knowledge\KnowledgeColumnController@show')->where('id', '[0-9]+');//专栏详情
    Route::post('knowledge/column.json', 'Knowledge\KnowledgeColumnController@store');//新建专栏
    Route::put('knowledge/column/{id}.json', 'Knowledge\KnowledgeColumnController@update')->where('id', '[0-9]+');//更新专栏
    Route::delete('knowledge/column/{id}.json', 'Knowledge\KnowledgeColumnController@destroy')->where('id', '[0-9]+');//删除专栏
    Route::put('knowledge/column/field/{id}.json', 'Knowledge\KnowledgeColumnController@update_field')->where('id', '[0-9]+');//上下架等单子段
    Route::get('knowledge/column/content/{column_id}.json', 'Knowledge\KnowledgeColumnController@showContentLists')->where('column_id', '[0-9]+');//专栏内容清单列表
    Route::post('knowledge/column/rel/{id}.json', 'Knowledge\KnowledgeColumnController@store_rel')->where('id', '[0-9]+');//添加内容到专栏
    Route::delete('knowledge/column/rel/{id}.json', 'Knowledge\KnowledgeColumnController@destroy_rel')->where('id', '[0-9]+');//从专栏移除内容

    Route::get('knowledge/content.json', 'Knowledge\KnowledgeContentController@index');//内容列表
    Route::get('knowledge/getcontentbycolumnid.json', 'Knowledge\KnowledgeColumnController@get_content_by_id');//根据专栏id查询该专栏下的内容
    Route::get('knowledge/getcolumnbycolumnids.json', 'Knowledge\KnowledgeColumnController@get_columns_by_ids');//根据专栏ids查询所属专栏

    Route::get('knowledge/content/{id}.json', 'Knowledge\KnowledgeContentController@show')->where('id', '[0-9]+');//内容详情
    Route::post('knowledge/content.json', 'Knowledge\KnowledgeContentController@store');//新建内容
    Route::put('knowledge/content/{id}.json', 'Knowledge\KnowledgeContentController@update')->where('id', '[0-9]+');//更新内容
    Route::delete('knowledge/content/{id}.json', 'Knowledge\KnowledgeContentController@destroy')->where('id', '[0-9]+');//删除内容
    Route::put('knowledge/content/field/{id}.json', 'Knowledge\KnowledgeContentController@update_field')->where('id', '[0-9]+');//上下架等单子段
    Route::get('knowledge/qrcode.json', 'Knowledge\KnowledgeContentController@qrcode');//预览二维码
    //商家设置
    Route::put('knowledge/setting.json', 'Merchant\MerchantSettingController@putKnowlageSetting');//修改设置
    Route::get('knowledge/setting.json', 'Merchant\MerchantSettingController@getKnowlageSetting');//查询设置


});

//会员
Route::group(['prefix' => $admin_prefix, 'namespace' => $admin_namespace], function () {
    Route::get('member/cardlist.json', ['middleware' => 'auth.api', 'uses' => 'Member\MemberController@getCardList']);  //会员卡列表
    Route::get('member/card.json', ['middleware' => 'auth.api', 'uses' => 'Member\MemberController@getCard']);  //会员卡查看
    Route::post('member/addcard.json', ['middleware' => 'auth.api', 'uses' => 'Member\MemberController@addCard']);  //会员卡添加
    Route::put('member/editcard/{id}.json', ['middleware' => 'auth.api', 'uses' => 'Member\MemberController@editCard']);  //会员卡编辑
    Route::delete('member/deletecard/{id}.json', ['middleware' => 'auth.api', 'uses' => 'Member\MemberController@deleteCard']);//会员卡：删除

    Route::get('member/statistics.json', ['middleware' => 'auth.api', 'uses' => 'Member\MemberController@statisticsInfo']);  //数据统计
    Route::get('member/addtrend.json', ['middleware' => 'auth.api:member_info', 'uses' => 'Member\MemberController@addTrends']);  //新增用户统计
    Route::get('member/level.json', ['middleware' => 'auth.api:member_info', 'uses' => 'Member\MemberController@levelInfo']);  //等级分布

    Route::get('member/list.json', ['middleware' => 'auth.api', 'uses' => 'Member\MemberController@memberList']);  //会员列表
    Route::post('member/setlevel.json', ['middleware' => 'auth.api', 'uses' => 'Member\MemberController@setLevel']);  //设置等级

});

//订单:需要登录
Route::group(['prefix' => $admin_prefix . "/order", 'namespace' => $admin_namespace], function () {
    Route::get('order.json', ['middleware' => 'auth.api', 'uses' => 'Order\OrderController@index']); //订单列表
    Route::get('order_info.json', ['middleware' => 'auth.api', 'uses' => 'Order\OrderController@getOrderInfo']); //订单详情
    Route::get('order_refund_goods.json', ['middleware' => 'auth.api', 'uses' => 'Order\OrderController@getOrderRefundGoodsInfo']); //订单详情
    Route::put('order_update_price.json', ['middleware' => 'auth.api', 'uses' => 'Order\OrderController@updateOrderGoodsPrice']); //订单改价
    Route::put('order_edit_remarks.json', ['middleware' => 'auth.api', 'uses' => 'Order\OrderController@editOrderRemarks']);//订单卖家备注
    Route::put('order_edit_address.json', ['middleware' => 'auth.api', 'uses' => 'Order\OrderController@editOrderAddress']);//订单收货地址修改
    Route::put('order_cance.json', ['middleware' => 'auth.api', 'uses' => 'Order\OrderController@canceOrder']);//订单取消
    Route::post('order_shipping.json', ['middleware' => 'auth.api', 'uses' => 'Order\OrderController@shippingGoods']);//订单发货
    Route::get('order_shipping_list.json', ['middleware' => 'auth.api', 'uses' => 'Order\OrderController@shippingGoodsList']);//订单发货记录
    Route::post('order_shipping_excel.json', ['middleware' => 'auth.api', 'uses' => 'Order\OrderController@shippingGoodsExcel']);//订单批量发货excel
    Route::post('batchShipment.json', ['middleware' => 'auth.api', 'uses' => 'Order\OrderController@postBatchShipments']);    // 批量发货
    Route::put('order_edit_shipping.json', ['middleware' => 'auth.api', 'uses' => 'Order\OrderController@editOrderShipping']);//订单修改物流
    //Route::put('order_refund.json', ['middleware' => 'auth.api','uses'=>'Order\OrderController@updateOrderRefund');//订单退款处理 wangshiliang@dodoca.com
    Route::put('order_refund.json', ['middleware' => 'auth.api', 'uses' => 'Order\RefundController@postRefund']);//订单退款处理  wangshiliang@dodoca.com
    Route::put('order_extend_days.json', ['middleware' => 'auth.api', 'uses' => 'Order\OrderController@updateOrderExtendDays']);//订单延期收货
    Route::put('order_set.json', ['middleware' => 'auth.api', 'uses' => 'Order\OrderController@orderSet']);//订单設置
    Route::get('get_order_set.json', ['middleware' => 'auth.api', 'uses' => 'Order\OrderController@getOrderSet']);//订单設置
    Route::put('order_update_comment_score.json', ['middleware' => 'auth.api', 'uses' => 'Order\OrderController@updateOrderCommentScore']);//订单评价加星
    Route::get('order_set_msg_format.json', ['middleware' => 'auth.api', 'uses' => 'Order\OrderController@getOrderSetMsgFormat']);//订单设置留言文本格式
    Route::get('order_trade_info.json', ['middleware' => 'auth.api', 'uses' => 'Order\OrderController@orderTradeInfo']);//单个订单物流商品地址信息
    Route::post('print_delivery.json', ['middleware' => 'auth.api', 'uses' => 'Order\OrderController@printDelivery']);//打印发货单
    Route::post('print_express.json', ['middleware' => 'auth.api', 'uses' => 'Order\OrderController@printExpress']);//打印发货单
    Route::get('order_address.json', ['middleware' => 'auth.api', 'uses' => 'Order\OrderController@getOrderAddress']);//打印发货单
    Route::get('waybill.json', ['middleware' => 'auth.api', 'uses' => 'Order\OrderController@getWayBill']);//选择运单模板
    Route::get('logistics_tracking.json', ['middleware' => 'auth.api', 'uses' => 'Order\OrderController@logisticsTracking']);//物流跟踪
    Route::get('comments.json', ['middleware' => 'auth.api', 'uses' => 'Order\CommentController@getCommentList']);   // 订单商品评论列表
    Route::get('sellerreply.json', ['middleware' => 'auth.api', 'uses' => 'Order\CommentController@getSellerReply']);   // 获取商家回复
    Route::post('sellerreply.json', ['middleware' => 'auth.api', 'uses' => 'Order\CommentController@getSellerReply']);   // 添加商家回复
    Route::put('sellerreply.json', ['middleware' => 'auth.api', 'uses' => 'Order\CommentController@getSellerReply']);   // 修改商家回复
    Route::get('comment/preinstall.json', ['middleware' => 'auth.api', 'uses' => 'Order\CommentController@setPreinstallComment']);   // 预设评论
    Route::get('comment/goods_props.json', ['middleware' => 'auth.api', 'uses' => 'Order\CommentController@getGoodsProps']);   // 预设评论商品規格信息
    Route::post('comment/preinstall.json', ['middleware' => 'auth.api', 'uses' => 'Order\CommentController@setPreinstallComment']);   // 添加预设评论
    Route::get('comment/preinstalls.json', ['middleware' => 'auth.api', 'uses' => 'Order\CommentController@putPreinstallComment']);   // 获取修改预设评论信息
    Route::put('comment/preinstalls.json', ['middleware' => 'auth.api', 'uses' => 'Order\CommentController@putPreinstallComment']);   // 修改预设评论信息
    Route::put('comments.json', ['middleware' => 'auth.api:trade_ordercomment', 'uses' => 'Order\CommentController@putComment']);       // 订单商品评论开启&&关闭
    Route::get('comment/comments.json', ['middleware' => 'auth.api', 'uses' => 'Order\CommentController@getComment']);       // 获取评论是否开启
    Route::put('comment/comments.json', ['middleware' => 'auth.api', 'uses' => 'Order\CommentController@setComment']);       // 设置评论是否开启
    Route::put('modifystore.json', ['middleware' => 'auth.api', 'uses' => 'Order\OrderController@putModifyStore']);       // 订单详情:修改门店
    Route::put('hadpickup.json', ['middleware' => 'auth.api', 'uses' => 'Order\OrderController@putHadPickup']);       // 订单详情:确认提货
    Route::post('mer_refundover.json', ['middleware' => 'auth.api', 'uses' => 'Order\RefundController@MerRefundOver']);//结束维权
});

//营销
Route::group(['prefix' => 'marketing', 'namespace' => $admin_namespace], function () {
    //优惠券
    Route::get('coupon.json', ['middleware' => 'auth.api:marketing_coupon', 'uses' => 'Marketing\CouponController@getCouponList']);                          // 优惠券列表
    Route::post('coupon.json', ['middleware' => 'auth.api', 'uses' => 'Marketing\CouponController@postCoupon']);                            // 添加优惠券
    Route::get('coupon/{id}.json', ['middleware' => 'auth.api', 'uses' => 'Marketing\CouponController@getCouponDetail'])->where('id', '[0-9]+');                   // 优惠券详情
    Route::put('coupon/{id}.json', ['middleware' => 'auth.api', 'uses' => 'Marketing\CouponController@putCoupon']);                         // 修改优惠券
    Route::put('coupon_close/{id}.json', ['middleware' => 'auth.api', 'uses' => 'Marketing\CouponController@putCloseCoupon']);              // 开启/关闭优惠券
    Route::delete('coupon/{id}.json', ['middleware' => 'auth.api', 'uses' => 'Marketing\CouponController@deleteCoupon']);                   // 删除优惠券
    Route::get('coupon.code.json', ['middleware' => 'auth.api', 'uses' => 'Marketing\CouponController@getCodeList']);                       // 优惠码列表
    Route::post('coupon.batch_code.json', ['middleware' => 'auth.api', 'uses' => 'Marketing\CouponController@postBatchDistributeCode']);    // 批量 - 派发优惠码
    Route::get('coupon_records/{id}.json', ['middleware' => 'auth.api', 'uses' => 'Marketing\CouponController@getRecord']);                 // 数据（领取记录&&使用记录）
    Route::get('activity_goods.json', ['middleware' => 'auth.api', 'uses' => 'Marketing\CouponController@getActivityGoods']);               // 自选商品
    Route::get('day.json', ['middleware' => 'auth.api', 'uses' => 'Marketing\CouponController@getCouponStatDays']);                         // 优惠券领取使用量
    Route::get('coupon/down_daily.json', ['middleware' => 'auth.api', 'uses' => 'Marketing\CouponController@downDaily']);  //下载优惠劵数据报表

    // 满就送活动
    Route::get('discount.json', ['middleware' => 'auth.api:marketing_fullsent', 'uses' => 'Marketing\DiscountActivityController@getActivity']);            // 满就减活动列表
    Route::delete('discount/{id}.json', ['middleware' => 'auth.api', 'uses' => 'Marketing\DiscountActivityController@deleteActivity']); // 删除满就减活动
    Route::post('discount.json', ['middleware' => 'auth.api', 'uses' => 'Marketing\DiscountActivityController@postActivity']);          // 添加满就减活动
    Route::get('discount/{id}.json', ['middleware' => 'auth.api', 'uses' => 'Marketing\DiscountActivityController@getActivityDetail']); // 满就减活动详情
    Route::get('records.json', ['middleware' => 'auth.api', 'uses' => 'Marketing\DiscountActivityController@getPreferentialRecord']);   // 满就减活动_优惠记录
    Route::get('discount_check.json', ['middleware' => 'auth.api', 'uses' => 'Marketing\DiscountActivityController@getDiscountCheck']); // 满就减活动_校验活动时间
    Route::get('discount_goods.json', ['middleware' => 'auth.api', 'uses' => 'Marketing\DiscountActivityController@getGoodsList']);     // 满就减活动商品


    //秒杀
    Route::get('seckill.json', ['middleware' => 'auth.api:marketing_seckill', 'uses' => 'Marketing\SeckillController@getSeckillList']);               //秒杀列表
    Route::post('seckill.json', ['middleware' => 'auth.api', 'uses' => 'Marketing\SeckillController@postSeckill']);                 //添加秒杀
    Route::put('seckill/{id}.json', ['middleware' => 'auth.api', 'uses' => 'Marketing\SeckillController@putSeckill']);              //修改秒杀
    Route::get('seckill/{id}.json', ['middleware' => 'auth.api', 'uses' => 'Marketing\SeckillController@getSeckill']);              //秒杀详细
    Route::put('putSeckill.json', ['middleware' => 'auth.api', 'uses' => 'Marketing\SeckillController@putFinishSeckill']);          //结束活动
    Route::get('seckill_goods/{id}.json', ['middleware' => 'auth.api', 'uses' => 'Marketing\SeckillController@getGoodsStatus']);    //检测商品是否正在参加其他活动

    //新用户有礼
    Route::get('newusergift.json', ['uses' => 'Marketing\NewUserGiftController@lists']);
    Route::get('newusergift/{id}.json', ['uses' => 'Marketing\NewUserGiftController@detail']);
    Route::post('newusergift.json', ['uses' => 'Marketing\NewUserGiftController@store']);
    Route::put('newusergift/{id}.json', ['uses' => 'Marketing\NewUserGiftController@edit']);
    Route::delete('newusergift/{id}.json', ['uses' => 'Marketing\NewUserGiftController@delete']);
    Route::get('newusergift/data/{id}.json', ['uses' => 'Marketing\NewUserGiftController@data']);
    Route::get('newusergift/coupon_data/{id}.json', ['uses' => 'Marketing\NewUserGiftController@couponData']);
    Route::get('newusergift/daily_data/{id}.json', ['uses' => 'Marketing\NewUserGiftController@dailyData']);
    Route::put('newusergift/change_status/{id}.json', ['uses' => 'Marketing\NewUserGiftController@changeStatus']);

    //邀请好友开店获得商品上架数量--需登录
    Route::get('operateRewardLink.json', ['middleware' => 'auth.api', 'uses' => 'Marketing\OperateRewardController@getoperateRewardLink']);//邀请链接
    Route::get('operateRewardList.json', ['middleware' => 'auth.api', 'uses' => 'Marketing\OperateRewardController@getoperateRewardList']);//奖励记录

});

//交易
Route::group(['prefix' => $admin_prefix, 'namespace' => $admin_namespace], function () {
    Route::get('trade.json', ['middleware' => 'auth.api', 'uses' => 'Trade\TradeController@getTrades']);                 // 收支明细列表
    Route::get('income.json', ['middleware' => 'auth.api', 'uses' => 'Trade\TradeController@getIncome']);                // 我的收入&&可提现余额
    Route::get('day.json', ['middleware' => 'auth.api', 'uses' => 'Trade\TradeController@getTradeDays']);           // 交易趋势
    Route::get('trade/recordExport', ['middleware' => 'auth.api', 'uses' => 'Trade\TradeController@recordExport']); //交易明细导出
    Route::get('sevenday.json', ['middleware' => 'auth.api', 'uses' => 'Trade\TradeController@getSevenDay']); //近7日收入
    Route::get('goods.json', ['middleware' => 'auth.api', 'uses' => 'Trade\TradeController@getSaleGoods']); //商品销量前十的商品
    Route::get('taggoods.json', ['middleware' => 'auth.api', 'uses' => 'Trade\TradeController@getTagGoods']); //分组商品销量
});

//首页
Route::group(['prefix' => $admin_prefix, 'middleware' => $admin_middleware, 'namespace' => $admin_namespace], function () {
    //Route::group(['prefix' =>$admin_prefix, 'namespace' =>$admin_namespace], function () {
    Route::get('index/days.json', 'Index\IndexController@daysTrend');  //数据统计
    Route::get('index/orders.json', 'Index\IndexController@orderStatistics');  //新增用户统计
    Route::get('index/sales.json', 'Index\IndexController@salesSort');  //等级分布
    Route::get('index/qrcode.json', 'Index\IndexController@qrcode');  //等级分布
    Route::get('index/orderdata.json', 'Index\IndexController@getIndexData');  //昨日数据统计
    Route::post('index/feedback.json', 'Index\FeedbackController@postFeedback');  //提交意见反馈
    Route::get('index/feedbacklist.json', 'Index\FeedbackController@getFeedbackList');  //提交意见列表
    Route::get('index/feedbackdetail/{id}.json', 'Index\FeedbackController@getFeedbackDetail');  //获取意见反馈详情
    Route::get('index/feedcount.json', 'Index\FeedbackController@getTodayCount');  //获取今日提交次数
    Route::get('index/feedread/{id}.json', 'Index\FeedbackController@getFeedRead');   //标记已读
    Route::post('index/marketingid.json', 'Index\HolidaymarketingController@postMarketingid');  //选择节日营销活动
    Route::get('index/holidaymarketingmerchantlist.json', 'Index\HolidaymarketingController@getHolidaymarketingMerchantList');  //查看节日营销活动
    Route::get('index/trade.json', 'Index\IndexController@getTrade');
    Route::get('index/allpictures.json', 'Index\IndexController@getAllPictures');
});
//店铺装修
Route::group(['prefix' => $admin_prefix, 'middleware' => $admin_middleware, 'namespace' => $admin_namespace], function () {
    Route::put('design/design.json', 'Design\ShopDesignController@putDesign');     // 新增/编辑装修
    Route::get('design/templetlist.json', 'Design\ShopDesignController@templetList');       // 整套装修模板列表
    Route::get('design/templetpagelist.json', 'Design\ShopDesignController@templetPageList');       // 单页装修模板列表
    Route::get('design/templet.json', 'Design\ShopDesignController@getTemplet');       // 获取整套模板数据
    Route::get('design/{id}.json', 'Design\ShopDesignController@getDesignByXcxid')->where('id', '[0-9]+');       // 根据小程序id获取装修详情
    Route::get('design/templetpage.json', 'Design\ShopDesignController@getTempletPage');       // 获取单页模板数据
    Route::post('design/qrcode.json', 'Design\ShopDesignController@template_qrcode');     // 生成二维码
    Route::delete('design/{id}.json', 'Design\ShopDesignController@deletepage');     // 删除页面
    Route::get('design/link.json', 'Design\ShopDesignController@link_url');       // 链接组件数据
    Route::post('design/setindex.json', 'Design\ShopDesignController@setIndex');     // 设为首页接口
    Route::get('design/getindex.json', 'Design\ShopDesignController@getIndex');     // 获取首页接口
    Route::post('design/copy.json', 'Design\ShopDesignController@getDesignCopy');     // 复制
    Route::post('design/getcompent.json', 'Design\ShopDesignController@getByPageid');     // 单页面获取装修数据
    Route::get('design/linktab.json', 'Design\ShopDesignController@link_tab');     // 链接组件接口
    Route::get('design/templateid.json', 'Design\ShopDesignController@getDfaultTemplateId');     // 默认模板id
});

//底部导航装修
Route::group(['prefix' => $admin_prefix, 'middleware' => $admin_middleware, 'namespace' => $admin_namespace], function () {
    Route::get('designnav/{id}.json', 'Design\ShopDesignNavController@getNav');     // 获取底部装修
    Route::post('designnav/edit.json', 'Design\ShopDesignNavController@editNav');     // 编辑底部装修
});

//客服设置
Route::group(['prefix' => $admin_prefix, 'middleware' => $admin_middleware, 'namespace' => $admin_namespace], function () {
    Route::get('customer/{id}.json', 'Design\ShopCustomerController@getCustomer');     // 获取客服设置
    Route::post('customer/edit.json', 'Design\ShopCustomerController@editCustomer');     // 编辑客服设置
});

//分享卡片设置
Route::group(['prefix' =>$admin_prefix, 'middleware' => $admin_middleware, 'namespace' =>$admin_namespace], function () {
    Route::get('sharecard/{id}.json', 'Weixin\ShareCardController@getCardDetail');     // 获取客服设置
    Route::post('sharecard/edit.json', 'Weixin\ShareCardController@editCardDetail');     // 编辑客服设置
});

//商城设置
Route::group(['namespace' => $admin_namespace], function () {
    Route::get('shop.json', ['middleware' => 'auth.api', 'uses' => 'Shop\ShopController@getShop']);
    Route::post('shop.json', ['middleware' => 'auth.api', 'uses' => 'Shop\ShopController@postShop']);
    Route::put('shop.json', ['middleware' => 'auth.api:setting_mall', 'uses' => 'Shop\ShopController@putShop']);

    //商家设置
    Route::post('merchant_setting.json', ['middleware' => 'auth.api', 'uses' => 'Merchant\MerchantSettingController@postSetting']);
    Route::put('merchant_setting.json', ['middleware' => 'auth.api', 'uses' => 'Merchant\MerchantSettingController@putSettings']);
    Route::get('merchant_setting.json', ['middleware' => 'auth.api', 'uses' => 'Merchant\MerchantSettingController@getSetting']);
});

//服务承诺
Route::group(['prefix' => 'serve'], function () {
    Route::get('servelabel.json', ['middleware' => 'auth.api', 'uses' => 'Admin\ServeLabel\ServeLabelController@getServeLabels']);
    Route::get('label.json', ['middleware' => 'auth.api', 'uses' => 'Admin\ServeLabel\ServeLabelController@getLabels']);
    Route::get('servelabel/{id}.json', ['middleware' => 'auth.api', 'uses' => 'Admin\ServeLabel\ServeLabelController@getServeLabel']);
    Route::post('servelabel.json', ['middleware' => 'auth.api', 'uses' => 'Admin\ServeLabel\ServeLabelController@postServeLabel']);
    Route::put('servelabel/{id}.json', ['middleware' => 'auth.api', 'uses' => 'Admin\ServeLabel\ServeLabelController@putServeLabel']);
    Route::put('servelabelstatus/{id}.json', ['middleware' => 'auth.api', 'uses' => 'Admin\ServeLabel\ServeLabelController@putServeLabelStatus']);
    Route::delete('servelabel/{id}.json', ['middleware' => 'auth.api', 'uses' => 'Admin\ServeLabel\ServeLabelController@deleteServeLabel']);
});

//核销记录 shangyazhao@dodoca.com
Route::group(['prefix' => $admin_prefix, 'middleware' => $admin_middleware, 'namespace' => $admin_namespace], function () {
    Route::get('orderappt/code.json', 'Order\OrderApptController@getHexiaoCode');//订单->核销记录:核销码信息
    Route::put('orderappt/do.json', 'Order\OrderApptController@dealCode');       //订单->核销记录:核销操作
    Route::get('orderappt/list.json', 'Order\OrderApptController@getList');      //订单->核销记录:预约服务日志
    Route::get('orderappt/selffetchlist.json', 'Order\OrderApptController@getSelfFetchList');      //订单->核销记录:上门自提日志
    Route::get('orderappt/virtualgoods.json', 'Order\OrderApptController@getVirtualgoodsList');   //订单->核销记录:虚拟商品日志
    Route::get('orderappt/apptorder.json', 'Order\OrderApptController@getList'); //服务管理->服务订单
});

//预约服务->技师管理
Route::group(['prefix' => $admin_prefix, 'middleware' => $admin_middleware, 'namespace' => $admin_namespace], function () {
// Route::group(['prefix' =>$admin_prefix,'namespace' =>$admin_namespace],function(){
    Route::post('appt/technicians.json', 'Appt\TechnicianController@create');             //添加技师
    Route::get('appt/technicians/{staff_id}.json', 'Appt\TechnicianController@getOneById')->where('staff_id', '[0-9]+');;             //查询技师
    Route::delete('appt/technicians/{staff_id}.json', 'Appt\TechnicianController@destroy')->where('staff_id', '[0-9]+');  //删除技师 staff_id为技师id
    Route::put('appt/technicians/{staff_id}.json', 'Appt\TechnicianController@edit')->where('staff_id', '[0-9]+');  //修改技师 staff_id为技师id
    Route::get('appt/technicians.json', 'Appt\TechnicianController@index');             //技师列表
});

//门店
Route::group(['prefix' => $admin_prefix, 'middleware' => $admin_middleware, 'namespace' => $admin_namespace], function () {
    Route::get('store/getstorelist.json', 'Store\StoreController@getStoreList'); //获取门店列表
    Route::post('store/poststore.json', 'Store\StoreController@postStore'); //新增门店
    Route::post('store/poststorename.json', 'Store\StoreController@postStoreName'); //新增门店，验证此商户下门店名称是否存在
    Route::put('store/putstore.json', 'Store\StoreController@putStore'); //编辑门店
    Route::get('store/getstore.json', 'Store\StoreController@getStore'); //门店详情
    Route::put('store/putenabled.json', 'Store\StoreController@putEnabled'); //门店开启/关闭
    Route::post('store/setting.json', 'Store\StoreSettingController@StoreSetting'); //编辑门店设置
    Route::get('store/{wxinfo_id}.json', 'Store\StoreSettingController@getStoreSetting'); //读取小程序门店设置

});
//优惠买单
Route::group(['prefix' => $admin_prefix, 'middleware' => $admin_middleware, 'namespace' => $admin_namespace], function () {//
    Route::get('salepay/getsalepay.json', 'Salepay\SalepayController@getSalepay'); //获取优惠买单列表
    Route::put('salepay/putsalepay.json', 'Salepay\SalepayController@putSalepay'); //新增门店

});
//门店自提相关
Route::group(['prefix' => $admin_prefix, 'middleware' => $admin_middleware, 'namespace' => $admin_namespace], function () {
    //Route::get('enabled/getenabled_name.json', 'Enabled\EnabledController@getEnabledName'); //上门自提别名读取
    Route::put('enabled/putenabled_name.json', 'Enabled\EnabledController@putEnabledName'); //上门自提别名设置
    Route::put('enabled/putenabled.json', 'Enabled\EnabledController@putEnabled'); //上门自提功能开启/关闭
    Route::put('enabled/putenabled_store.json', 'Enabled\EnabledController@putEnabledStore'); //门店自提功能开启/关闭

});

//文章分组
// Route::group(['prefix' => $admin_prefix,'namespace' => $admin_namespace], function () {//
Route::group(['prefix' => $admin_prefix, 'middleware' => $admin_middleware, 'namespace' => $admin_namespace], function () {
    Route::get('article_group/getonegroup/{group_id}.json', 'Article\ArticleGroupController@getOneGroup')->where('group_id', '[0-9]+');            //获取单一分组
    Route::get('article_group/getgrouplist.json', 'Article\ArticleGroupController@getGroup');            //获取所有分组
    Route::post('article_group/postgroup.json', 'Article\ArticleGroupController@postGroup');            //添加分组
    Route::put('article_group/putgroup/{group_id}.json', 'Article\ArticleGroupController@putGroup')->where('group_id', '[0-9]+');            //修改分组
    Route::delete('article_group/deletegroup/{group_id}.json', 'Article\ArticleGroupController@deleteGroup')->where('group_id', '[0-9]+');            //删除分组
});


//文章模块
Route::group(['prefix' => $admin_prefix, 'middleware' => $admin_middleware, 'namespace' => $admin_namespace], function () {
// Route::group(['prefix' => $admin_prefix, 'namespace' => $admin_namespace], function() {
    Route::post('article/postarticle.json', 'Article\ArticleController@postArticle');            //添加文章
    Route::delete('article/deletearticle.json', 'Article\ArticleController@deleteArticle');            //删除文章
    Route::put('article/putarticle/{article_id}.json', 'Article\ArticleController@putArticle')->where('article_id', '[0-9]+');            //修改文章
    Route::get('article/getonearticle/{article_id}.json', 'Article\ArticleController@getOneArticle')->where('article_id', '[0-9]+');            //获取单一文章
    Route::get('article/getaddarticle/{article_id}.json', 'Article\ArticleController@addReadNum')->where('article_id', '[0-9]+');            //增加阅读量
    Route::get('article/getacticlelist.json', 'Article\ArticleController@getArticle');            //获取所有文章
    Route::delete('article/deletearticle.json', 'Article\ArticleController@deleteArticle');            //删除文章
    Route::put('article/putarticlegroup.json', 'Article\ArticleController@updateArticleGroup');            //批量修改文章分组
});

//超级表单
Route::group(['prefix' => $admin_prefix, 'middleware' => $admin_middleware, 'namespace' => $admin_namespace], function () {
    Route::get('formtest', 'SuperForm\FormCateController@test');//测试
    //表单
    Route::get('form/form.json', 'Form\FormController@index');//列表
    Route::post('form/form.json', 'Form\FormController@store');//新增
    Route::put('form/{id}.json', 'Form\FormController@update')->where('id', '[0-9]+');//编辑
    Route::get('form/{id}.json', 'Form\FormController@show')->where('id', '[0-9]+');//详情
    Route::put('form/field.json', 'Form\FormController@updateField');//表单操作
    Route::get('form/qrcode.json', 'Form\FormController@qrcode');//预览二维码
    //表单模版
    Route::get('form/template.json', 'Form\FormTemplateController@index');//模版列表
    Route::get('form/template_type.json', 'Form\FormTemplateController@getTemplate');//模版类型列表
    //查看反馈
    Route::get('form/feedback.json', 'Form\FormFeedbackController@index');//反馈列表
    Route::get('form/feedback/{id}.json', 'Form\FormFeedbackController@show')->where('id', '[0-9]+');//反馈详情
    Route::put('form/feedback/{id}.json', 'Form\FormFeedbackController@update')->where('id', '[0-9]+');//反馈详情
    Route::get('form/feedback/download.json', 'Form\FormFeedbackController@download');//反馈下载

    //表单分组模块
    Route::get('formcate/getformcate.json', 'Form\FormCateController@getFormCate');//查询表单分组
    Route::post('formcate/postformcate.json', 'Form\FormCateController@postFormCate');//添加表单分组
    Route::put('formcate/putformcate/{formcate_id}.json', 'Form\FormCateController@putFormCate')->where('formcate_id', '[0-9]+');//修改表单分组
    Route::delete('formcate/deleteformcate/{formcate_id}.json', 'Form\FormCateController@deleteFormCate')->where('formcate_id', '[0-9]+');//修改表单分组

    //统计;
    Route::get('formcate/statistics/{form_id}.json', 'Form\FormStatisticsController@getFormStatistics')->where('form_id', '[0-9]+');//查询表单数据情况
    // Route::get('formcate/test.json','Form\FormStatisticsController@index');//test

});
if (app()->isLocal()) $admin_middleware = 'auth.api';

//测试
Route::group(['prefix' => $admin_prefix, 'namespace' => 'Test'], function () {
    Route::get('test/{id}/{mobile}', 'TestController@setUserMobile');
    Route::get('test/{merchant_id}', 'TestController@clearServeLabel');
    Route::get('forget', 'TestController@forget');
    Route::get('account/create', 'AccountController@createAccount');
    Route::get('account/getinfo', 'AccountController@getinfo');
    Route::get('account_callback', ['as' => 'account_callback', 'uses' => 'AccountController@callback']);
    Route::get('getfromdata', 'TestController@getFromData');//超级表单数据拉取
    Route::get('ren', 'TestController@ren');
	Route::get('migu/index','MiguController@index');
});

//公告
Route::group(['prefix' => $admin_prefix, 'middleware' => $admin_middleware, 'namespace' => $admin_namespace], function () {
    Route::get('announcement/unread.json', 'Announcement\AnnouncementController@unreadCount');
    Route::get('announcement/list.json', 'Announcement\AnnouncementController@announcementList');
    Route::get('announcement/detail.json', 'Announcement\AnnouncementController@announcementDetail');
    Route::get('announcement/read.json', 'Announcement\AnnouncementController@announcementRead');
});


Route::get('logs', '\Rap2hpoutre\LaravelLogViewer\LogViewerController@index');

//微信
Route::group(['prefix' => 'weixin', 'middleware' => $admin_middleware], function () {
    Route::get('statistics/survey.json', 'Admin\Weixin\StatisticsController@getSurvey');//概况趋势
    Route::get('statistics/visit.json', 'Admin\Weixin\StatisticsController@getVisit');//访问趋势
    Route::get('statistics/visitdistribution.json', 'Admin\Weixin\StatisticsController@getVisitDistribution');//访问分布
    Route::get('statistics/visitpage.json', 'Admin\Weixin\StatisticsController@getVisitPage');//访问页面
    Route::get('statistics/visitretain.json', 'Admin\Weixin\StatisticsController@getVisitRetain');//访问留存
    Route::get('statistics/userportrait.json', 'Admin\Weixin\StatisticsController@getUserPortrait');//用户画像
    Route::get('statistics/orderhour.json', 'Admin\Weixin\StatisticsController@getOrderHour');//测试脚本

});


//用于ui在后台添加表单模板
Route::group(['prefix' => $admin_prefix, 'middleware' => $admin_middleware, 'namespace' => $admin_namespace], function () {
    //表单设置为模板 (注释时关闭入口)

    // Route::get('formtemplate/form_list','Access\AddTemplateController@getFormList');//表单列表
    // Route::get('formtemplate/form_one','Access\AddTemplateController@getFormOne');//获得页面
    // Route::get('formtemplate/add_template','Access\AddTemplateController@addFormlate');//验证逻辑
    // Route::get('formtemplate/template_one','Access\AddTemplateController@TempLateOne');//编辑模板页面
    // Route::get('formtemplate/check_template','Access\AddTemplateController@checkTempLate');//修改模板页面
});

//推客
if (app()->isLocal()) $admin_middleware = null;
Route::group(['prefix' => $admin_prefix, 'middleware' => $admin_middleware, 'namespace' => $admin_namespace], function () {
    Route::get('distrib/review.json', 'Distrib\DistribController@getReview');      //推广概况
    Route::get('distrib/orders.json', 'Distrib\DistribController@getOrders');      //推广订单
    Route::get('distrib/get_list.json', 'Distrib\DistribController@getList');//推客列表
    Route::get('distrib/commissionmemberlist.json', 'Distrib\DistribController@getCommissionMemberList'); //佣金下级用户列表
    Route::get('distrib/check_status.json', 'Distrib\DistribController@checkStatus');//开启/禁用推客
    Route::get('distrib/check_distrib.json', 'Distrib\DistribController@checkDistrib');//审核推客
    Route::post('distrib/become_distrib.json', 'Distrib\DistribController@becomeDistrib');//将会员设置成推客
    Route::get('distrib/ordergoods.json', 'Distrib\DistribController@getGoods');  //订单详情
    Route::get('distrib/orderdetail.json', 'Distrib\DistribController@getOrderDetail'); //佣金详情
    Route::post('distrib/setparent.json', 'Distrib\DistribController@setParent');//更改推客上级
    Route::get('distrib/get_setting.json', 'Distrib\DistribSetController@getSetting');//获取推客设置
    Route::post('distrib/save_setting.json', 'Distrib\DistribSetController@saveSetting');//推客设置
    Route::get('distrib/get_takecash_list.json', 'Distrib\DistribCashController@getTakecashList');//获取提现申请列表
    Route::post('distrib/takecash_agree.json', 'Distrib\DistribCashController@takecashAgree');//(同意/重新提交)提现到微信零钱
    Route::post('distrib/takecash_disagree.json', 'Distrib\DistribCashController@takecashDisagree');//拒绝提现
    Route::post('distrib/takecash_confirm.json', 'Distrib\DistribCashController@takecashConfirm');//线下打款确认

    Route::get('distrib/member_list.json', 'Distrib\DistribController@memberList');//未成为推客的 会员
    Route::get('distrib/tmp_command.json', 'Distrib\DistribTmpController@tmpCommand');//临时跑脚本

    //商品分销
    Route::get('distrib/goods.json', 'Distrib\DistribGoodsSettingController@goodsList');//商品列表
    Route::post('distrib/{goods_id}.json', 'Distrib\DistribGoodsSettingController@store')->where('goods_id','[1-9]\d*');//添加商品分销
    Route::put('distrib/{goods_id}.json', 'Distrib\DistribGoodsSettingController@save')->where('goods_id','[1-9]\d*');//修改商品分销
    Route::delete('distrib/{goods_id}.json', 'Distrib\DistribGoodsSettingController@destroy')->where('goods_id','[1-9]\d*');//删除商品分销
    Route::get('distrib/{goods_id}.json', 'Distrib\DistribGoodsSettingController@show')->where('goods_id','[1-9]\d*');//获取单一商品分销
    Route::get('distrib/distrib_goods_list.json', 'Distrib\DistribGoodsSettingController@index');//获取商品分销列表
    
    
    
    //推客活动素材
    Route::get('distrib/activity_list.json', 'Distrib\DistribActivityController@index');//列表
    Route::get('distrib/get_activity.json', 'Distrib\DistribActivityController@getRow');//获取一条数据
    Route::put('distrib/put_activity.json', 'Distrib\DistribActivityController@save');//新增/更新一条数据
    Route::delete('distrib/activity/{id}.json', 'Distrib\DistribActivityController@destroy');//删除一条数据

    //推客码
    Route::get('distrib/code_setting.json', 'Distrib\DistribCodeSettingController@get_row');//获取推客码设置
    Route::put('distrib/code_setting.json', 'Distrib\DistribCodeSettingController@save');//更新推客码设置

    //推客数据异步导出
    Route::post('distrib/export_task.json', 'Distrib\DistribController@exportTask'); //创建任务
    Route::get('distrib/task_lists.json', 'Distrib\DistribController@taskLists'); //任务列表

});

//直播
Route::group(['prefix' =>$admin_prefix, 'middleware' => $admin_middleware, 'namespace' =>$admin_namespace], function () {
    Route::post('live/save.json', 'Live\LiveController@save');      //创建直播
	Route::post('live/edit.json', 'Live\LiveController@edit');      //修改直播
	Route::get('live/info.json', 'Live\LiveController@info');      //直播信息
    Route::post('live/statistics.json', 'Live\LiveController@statistics');      //直播统计
    Route::post('live/orderdata.json', 'Live\LiveController@orderdata');      //直播统计
	Route::post('live/cancel.json', 'Live\LiveController@cancel');  //取消直播
	Route::post('live/del.json', 'Live\LiveController@del');      //删除直播
	Route::post('live/stop.json', 'Live\LiveController@stop');      //暂停直播
	Route::post('live/start.json', 'Live\LiveController@start');      //开始直播
	Route::post('live/renew.json', 'Live\LiveController@renew');      //直播续期
	Route::get('live/getlist.json', 'Live\LiveController@getlist');      //直播列表
	Route::get('live/getuserlist.json', 'Live\LiveController@getuserlist');      //观众列表
	Route::post('live/ban.json', 'Live\LiveController@ban');      //禁言
	Route::get('live/getcommentlist.json', 'Live\LiveController@getcommentlist');      //评论详情
	Route::get('live/getbuylist.json', 'Live\LiveController@getbuylist');      //购买详情
	Route::get('live/getplaylist.json', 'Live\LiveController@getplaylist');      //播放统计
	Route::get('record/getrecordlist.json', 'Live\RecordController@getrecordlist');      //录播列表
	Route::post('record/del.json', 'Live\RecordController@del');      //删除录播
	Route::get('record/getplaylist.json', 'Live\RecordController@getplaylist');      //播放统计
	Route::post('record/renew.json', 'Live\RecordController@renew');      //录播存储续期
	Route::post('record/publish.json', 'Live\RecordController@publish');	//录播上下线
	
	Route::get('liveaccount/getaccountinfo.json', 'Live\LiveAccountController@getAccountInfo');      //直播账户余额信息
	Route::get('liveaccount/getmerchantbalancelist.json', 'Live\LiveAccountController@getMerchantBalanceList');      //直播账户收支明细
	Route::post('liveaccount/buylive.json', 'Live\LiveAccountController@buyLive');      //购买直播余额
	Route::any('liveaccount/rechargeamount.json', 'Live\LiveAccountController@rechargeAmount');      //充值
	
	Route::get('record/info/{id}.json', 'Live\RecordController@info');      //录播信息
	Route::post('record/edit/{id}.json', 'Live\RecordController@edit');      //修改录播
	
	Route::post('record/uprecord.json', 'Live\RecordController@upRecord');      //录像上传
	Route::post('record/reportrecord.json', 'Live\RecordController@reportRecord');      //录像上传状态上报
	
	Route::get('record/reportgetlive.json', 'Live\RecordController@recordGetLive');      //录播同步直播数据
});


//素材模块
Route::group(['prefix' => $admin_prefix, 'middleware' => $admin_middleware, 'namespace' => $admin_namespace], function () {
    Route::get('material/getlist.json', 'Material\AttachmentController@index');//获取素材库文件
    Route::get('material/getgroup.json', 'Material\AttachmentGroupController@index');//获取素材库分组
});
//打印机
Route::group([ 'prefix' => $admin_prefix,'middleware' => $admin_middleware], function () {
    Route::get('printer/list.json', 'Admin\Printer\PrinterController@getList');//列表
    Route::post('printer/savedata.json', 'Admin\Printer\PrinterController@saveData');//保存数据
    Route::get('printer/editstatus.json', 'Admin\Printer\PrinterController@editStatus');
    Route::get('printer/delete.json', 'Admin\Printer\PrinterController@setDelete');
    Route::get('printer/detail.json', 'Admin\Printer\PrinterController@getDetail');
    Route::get('printer/aa.json', 'Admin\Printer\PrinterController@getDdd');
});



//砍价
Route::group(['prefix' => $admin_prefix,'middleware' => $admin_middleware, 'namespace' => $admin_namespace], function () {
    Route::get('bargain/bargains.json', 'Bargain\BargainController@getBargains');          // 砍价活动列表
    Route::get('bargain/bargainsGoodInfo.json', 'Bargain\BargainController@getBargainsGoodInfo');     //砍价列表（装修用此接口）
    Route::post('bargain/bargain.json', 'Bargain\BargainController@postBargain');                // 添加砍价活动
    Route::put('bargain/{id}.json', 'Bargain\BargainController@putBargain');       // 编辑砍价活动
    Route::get('bargain/{id}.json', 'Bargain\BargainController@getBargain');       // 砍价详细
    Route::delete('bargain/{id}.json', 'Bargain\BargainController@deleteBargain');       // 删除砍价活动
    Route::post('bargain/finish.json', 'Bargain\BargainController@putFinishBargain');       // 手动结束砍价
    Route::get('bargainstatis/{id}.json', 'Bargain\BargainController@getStatis');               // 数据统计
    Route::get('goodsprop/{id}.json', 'Bargain\BargainController@getGoodsProp');               // 商品规格信息
});

//群发优惠券
Route::group(['prefix' => $admin_prefix, 'namespace' => $admin_namespace, 'middleware' => $admin_middleware], function () {
    Route::post('couponsend/{id}.json', 'Marketing\CouponController@couponSend');//群发优惠券，生成记录
});

//广告投放
Route::group(['prefix' => $admin_prefix, 'namespace' => $admin_namespace, 'middleware' => $admin_middleware], function () {
//Route::group(['prefix' => $admin_prefix, 'namespace' => $admin_namespace], function () {
	Route::get('campaign/list.json', 'Campaign\CampaignController@index');//获取列表
	Route::get('campaign/get_row.json', 'Campaign\CampaignController@getRow');//获取单条详情
	Route::put('campaign/save.json', 'Campaign\CampaignController@save');//保存一条记录/新增
	Route::get('campaign/explod_efile.json', 'Campaign\CampaignController@explodefile');//下载
	Route::get('campaign/applet.json', 'Campaign\CampaignController@get_applet');//获取已绑定小程序信息
	Route::get('campaign/get_menu.json', 'Campaign\CampaignController@get_menu');//获取全部行业信息等菜单信息
	Route::get('campaign/get_account.json', 'Campaign\CampaignController@get_account');//获取公众号门店信息
	
});

//雷达
Route::group(['prefix' => $admin_prefix, 'namespace' => $admin_namespace, 'middleware' => $admin_middleware], function () {
	Route::get('radar/index.json', 'Radar\RadarController@index');				//获取列表
	Route::get('radar/goods_rank.json', 'Radar\RadarController@goodsRank');		//商品排行榜
	Route::get('radar/twitter_rank.json', 'Radar\RadarController@twitterRank');	//推客排行榜
});
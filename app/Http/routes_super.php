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
$weapp_prefix          = 'super_api';		//项目前缀
$weapp_namespace       = 'Super';	//项目命名空间
$weapp_middleware      = 'auth.super'; //项目中间件，需要登录信息接口使用
$weapp_namespace_admin      = 'Admin';
$weapp_namespace_app      = 'Weapp';

Route::group(['prefix' => $weapp_prefix, 'namespace' => $weapp_namespace], function () {
    Route::get('captcha', 'CaptchaController@index');//验证码

    Route::post('auth/login.json', 'AuthController@postLogin');//登录
    Route::get('auth/login.json', 'AuthController@postLogin');//登录

    Route::get('auth/logout.json', 'AuthController@getLogout');//退出
});

Route::group(['prefix' => $weapp_prefix, 'namespace' => $weapp_namespace, 'middleware' => $weapp_middleware], function () {
    Route::put('auth/resetpwd', 'AuthController@resetPwd');//  重置密码

    Route::get('auth/userinfo.json', 'AuthController@getUserinfo');//用户信息

    Route::get('auth/userprivs.json', 'AuthController@getSuperUserprivs');//用户权限

    Route::get('auth/codepriv.json', 'AuthController@getCodePriv');//用户权限

    //拼团
    Route::get('fightgroups.json', 'TuanController@getFightgroups');   // 拼团列表
    Route::get('launchs/{id}.json', 'TuanController@getLaunchs');      // 开团列表
    Route::get('joins/{id}.json', 'TuanController@getJoins');          // 参团详情

    //订单管理
    Route::get('order.json', 'OrderController@getOrderList');        // 订单列表
    Route::get('order/{id}.json', 'OrderController@getOrderInfo');   // 订单详情

    //满减管理
    Route::get('activitys.json', 'DiscountActivityController@getActivitys');   // 列表

    //秒杀
    Route::get('seckills.json', 'SeckillController@getSeckillList');   // 列表

    //用户管理
    Route::get('users.json','UserController@getUsers');
    Route::get('user/{id}.json','UserController@getUser');
    Route::post('usermodifypwd/{id}.json','UserController@ModifyPassWord');

    //管理员管理
    Route::get('superusers.json','SuperUserController@getUsers');
    Route::get('superuser/{id}.json','SuperUserController@getUser');
    Route::post('superuser.json','SuperUserController@addUser');
    Route::put('superuser/{id}.json','SuperUserController@putUser');
    Route::delete('superuser/{id}.json','SuperUserController@deleteUser');

    //会员管理
    Route::get('member/cardlist.json', 'MemberController@getCardList');  //会员卡列表
    Route::get('member/list.json','MemberController@memberList'); //会员列表
    Route::get('member/card/{id}.json','MemberController@getCard');//会员卡详情

    //商品管理
    Route::get('goods/list.json','GoodsController@goodsList');
    Route::get('goods/{id}.json','GoodsController@goodsDetail');
    Route::post('goods/onsale.json','GoodsController@goodsOnsale');

    //优惠券
    Route::get('coupon/list.json','CouponController@getCouponList');
    Route::get('coupon/{id}.json','CouponController@getCouponDetail');

    //积分管理
    Route::get('credit/list.json','CreditController@creditList');

    //交易记录
    Route::get('trade/list.json','TradeController@tradeList');
    //商户列表
    Route::get('merchant/list.json','MerchantController@merchantList');
    //商户小程序列表
    Route::get('merchant/weixin.json','MerchantController@weixinInfo');
    Route::get('merchant/qrcode.json','MerchantController@rQrcode');

    //商户记录列表
    Route::get('merchant/log/{id}.json','MerchantController@getMerchantLog');
    Route::get('merchant/inviteList.json','MerchantController@InviteList');


    //公告管理
    Route::get('announces.json','AnnounceController@getAnnouncements');
    Route::get('announces/{id}.json','AnnounceController@getAnnounce');
    Route::post('announces.json','AnnounceController@addAnnounce');
    Route::put('announces/{id}.json','AnnounceController@putAnnounce');
    Route::delete('announces/{id}.json','AnnounceController@deleteAnnounce');
    Route::get('announces/version','AnnounceController@getAnnouncever');
    Route::get('announces/popup','AnnounceController@getAnnounceshow');

    //统计管理
    Route::get('industryday.json','CountController@getIndustryDays');
    Route::get('orderday.json','CountController@getOrderCount');
    Route::get('tradeday.json','CountController@getTradeCount');
    Route::get('merchantday.json','CountController@getMerchantCount');
    Route::get('templateday.json','CountController@getTemplateDay');
    Route::get('templatetype.json','CountController@getTemplateType');
    Route::get('merchanttotal.json','CountController@getMerchantTotal');
    Route::get('xcxversion.json','CountController@getXcxVersionCount');

    Route::get('topmerchant.json','CountController@getTopMerchant');
    Route::get('merchantdaily.json','CountController@getMerchantDetail');

    //小程序版本升级
    Route::get('version.json','MerchantController@version');
    Route::get('weappupdate/online.json','WeappUpdateController@OnlineVersion');
    Route::get('weappupdate/versionlist.json','WeappUpdateController@VersionList');
    Route::get('weappupdate/publishnet.json','WeappUpdateController@PublishNet');
    Route::get('weappupdate/manualupdate.json','WeappUpdateController@ManualUpdate');
    Route::get('weappupdate/manualverify.json','WeappUpdateController@ManualVerify');
    //小程序版本搜索
    Route::get('app/list.json','WeappUpdateController@applist');


    //模板管理
    Route::get('template/list.json','TemplateController@designList');
    Route::post('template/add.json','TemplateController@addDesion');
    Route::post('template/addpage.json','TemplateController@addDesionPage');  //保存模板
    Route::get('template/desiondetail.json','TemplateController@desionDetail');
    Route::get('template/pagedetail.json','TemplateController@desionPageDetail');
    Route::get('template/templatelist.json','TemplateController@desionTemplateList');
    Route::get('template/templatepagelist.json','TemplateController@TemplatePageList');
    Route::get('template/templatedelete.json','TemplateController@deleteDesion');

    //轮播图管理
    Route::get('template/allpictures.json','TemplateController@getAllPictures');
    Route::post('template/addpicture.json','TemplateController@AddPictures');
    Route::post('template/changesort.json','TemplateController@ChangeSort');
    Route::delete('template/deletepicture.json','TemplateController@DeletePicture');



    //角色管理
    Route::get('rolelist.json','RoleController@getRoleLists'); //获取角色列表
    Route::get('role/{id}.json','RoleController@getRole');  //获取角色详情
    Route::post('addrole.json','RoleController@postAddRole');  //添加角色
    Route::put('role/{id}.json','RoleController@putRole');   // 修改角色
    Route::delete('role/{id}.json','RoleController@deleteRole'); // 删除角色
    Route::get('superrole/{id}.json','RoleController@getSuperRolePriv');  // 获取角色权限
    Route::put('superrolepriv.json','RoleController@putSuperRolePriv');  // 修改角色权限
    Route::get('role.json','RoleController@getrolelist'); //获取可用角色

    Route::get('privlist.json','SuperPrivController@getSuperPriv');   //获取权限列表
    Route::post('addsuperpriv.json','SuperPrivController@postAddSuperPriv'); //添加权限
    Route::put('superpriv/{id}.json','SuperPrivController@putSuperPriv');  //修改权限
    Route::delete('superpriv/{id}.json','SuperPrivController@deleteSuperPriv'); // 删除权限
    Route::get('superprivdetail/{id}.json','SuperPrivController@getSuperPrivDetail'); // 获取权限详情
    Route::get('getparentpriv.json','SuperPrivController@getAllParentPriv');   //获取父级权限
    Route::get('allpriv.json','SuperPrivController@getAllPrivs');

    //抓娃娃
    Route::get('actlist.json','ToyController@getActivityList'); //获取活动列表

    Route::get('datadetail.json','ToyController@getDataDetail'); //获取活动列表

    //商家后台权限
    Route::get('magprivlist.json','PrivController@getManagePriv');   //获取权限列表
    Route::post('addmagpriv.json','PrivController@postAddMagPriv'); //添加权限
    Route::put('magpriv/{id}.json','PrivController@putMagPriv');  //修改权限
    Route::delete('magpriv/{id}.json','PrivController@deleteMagPriv'); // 删除权限
    Route::get('magprivdetail/{id}.json','PrivController@getMagPrivDetail'); // 获取权限详情
    Route::get('getmagparpriv.json','PrivController@getAllMagParPriv'); // 获取权限详情

    //意见反馈
    Route::get('feedback/list.json','FeedbackController@getFeedbackList');  //获取反馈意见列表
    Route::get('feedbackdetail/{id}.json','FeedbackController@getFeedbackDetail');  //获取反馈意见详情
    Route::post('feedbackadd/{id}.json','FeedbackController@postFeedbackData');  //添加反馈

    Route::get('case/getcases.json', 'CaseController@getCases');          // 案例列表
    Route::put('case/case.json', 'CaseController@putCase');                // 添加编辑案例
    Route::get('case/{id}.json', 'CaseController@getCase');       // 案例详细
    Route::delete('case/{id}.json', 'CaseController@deleteCase');       // 删除案例
    Route::get('case/Industry.json', 'CaseController@getIndustry');               // 行业分类列表
    Route::post('case/onshow.json', 'CaseController@postOnshow');       // 上下架
    Route::get('case/addcases.json', 'CaseController@postCaseCsv');      // 数据导入


    //开放接口文档
    Route::get('opendoc/list.json','OpenDocController@menuList');//获取菜单
    Route::get('opendoc/details.json','OpenDocController@docDetails');//获取文档

    Route::post('opendoc/addmenu.json','OpenDocController@addMenu');//添加菜单
    Route::delete('opendoc/delmenu.json', 'OpenDocController@delMenu');//删除菜单
    Route::put('opendoc/editmenu.json','OpenDocController@editMenu');//编辑菜单

    Route::post('opendoc/adddoc.json','OpenDocController@addDoc');//添加文档
    Route::delete('opendoc/deldoc.json', 'OpenDocController@delDoc');//删除文档
    Route::put('opendoc/editdoc.json','OpenDocController@editDoc');//编辑文档
    
    //广告投放管理
    Route::get('campaign/list.json', 'CampaignController@index');//获取广告列表
    Route::get('campaign/get_row.json', 'CampaignController@getRow'); //获取一条广告数据/下载
    Route::get('campaign/explode_file.json', 'CampaignController@explodefile'); //下载
    Route::delete('campaign/delete.json', 'CampaignController@deleteRow'); //删除
});

Route::group(['prefix' => $weapp_prefix, 'namespace' => $weapp_namespace], function () {
    //广告投放管理
    //Route::get('campaign/list.json', 'CampaignController@index');//获取广告列表
    //Route::get('campaign/get_row.json', 'CampaignController@getRow'); //获取一条广告数据/下载
    //Route::get('campaign/explode_file.json', 'CampaignController@explodefile'); //下载
    //Route::delete('campaign/delete.json', 'CampaignController@deleteRow'); //删除
    
    //广告投放管理-代理商调用
    Route::get('campaign/agent_list.json', 'CampaignController@index');//获取广告列表
    Route::get('campaign/get_agent_row.json', 'CampaignController@getRow'); //获取一条广告数据/下载
    Route::get('campaign/explode_agent_file.json', 'CampaignController@explodefile'); //下载
});

Route::group(['prefix' => $weapp_prefix, 'namespace' => $weapp_namespace_admin],function(){
    Route::get('priv/all_privs.json', 'Priv\PrivController@getAllPrivs');//权限模块:显示列表
    Route::put('version/version_priv.json', 'Priv\VersionController@putVersionPriv');//版本-权限：修改
    Route::get('superversion/version_priv/{id}.json', 'Priv\VersionController@getSuperVersionPriv');//版本-权限：查看
    Route::get('version/versions.json', 'Priv\VersionController@getVersions');//版本:显示列表
    
    Route::get('optpriv/all_privs.json', 'Priv\PrivController@get_all_priv');//优化权限:显示列表

    Route::post('index/feedback.json', 'Index\FeedbackController@postFeedback');  //提交意见反馈
    Route::get('index/feedbacklist.json', 'Index\FeedbackController@getFeedbackList'); //获取意见列表
    Route::get('index/feedcount.json', 'Index\FeedbackController@getTodayCount');  //获取今日提交次数
    Route::get('index/feedbackdetail/{id}.json', 'Index\FeedbackController@getFeedbackDetail');
    Route::get('index/feedread/{id}.json','Index\FeedbackController@getFeedRead');
    Route::get('announcement/unread.json', 'Announcement\AnnouncementController@unreadCount');   //查询未读数
});

//管理员
Route::group(['prefix' => $weapp_prefix, 'namespace' => $weapp_namespace], function () {
    //Route::get('user_login_privs.json', ['middleware' => 'auth.api','uses'=>'UserController@getUserLoginPrivs']);//设置权限:登录的管理员权限
    Route::get('user_login_privs.json', 'UserController@getUserLoginPrivs');//设置权限:登录的管理员权限
    Route::get('init.json','TestController@getInitNum');  //初始化所有模板可编辑数据
    Route::get('gettest.json','TestController@getTest');  //初始化所有模板可编辑数据
});

Route::group(['prefix' => $weapp_prefix, 'namespace' => $weapp_namespace_app], function () {
    Route::get('bargain/bargainlist.json', 'Bargain\BargainController@getBargainList');//版本:显示列表
});

//公用接口
//七牛云:需要登录
Route::group(['prefix' => $weapp_prefix, 'namespace' => $weapp_namespace], function () {
    Route::get('attachment/qiniu_token', ['middleware' => 'auth.super','uses'=>'AttachmentController@qiniuToken']);  //获取七牛token
    Route::delete('attachment/attachment.json', ['middleware' => 'auth.super','uses'=>'AttachmentController@deleteAttachments']);//删除文件
    Route::put('attachment/attachment/{id}.json', ['middleware' => 'auth.super','uses'=>'AttachmentController@putAttachment']);//修改名称
    Route::put('attachment/attachments.json', ['middleware' => 'auth.super','uses'=>'AttachmentController@putAttachments']);//批量设置图片分组
    Route::get('attachment/attachment.json', ['middleware' => 'auth.super','uses'=>'AttachmentController@getAttachments']);//获取图片列表
    Route::post('attachment/attachmentupload.json', ['middleware' => 'auth.super','uses'=>'AttachmentController@uploadQiniu']);//上传图片
});
//七牛云:不要登录
Route::group(['prefix' => $weapp_prefix, 'namespace' => $weapp_namespace], function () {
    Route::post('qiniu_callback', ['as' => 'qiniu_callback', 'uses' => 'AttachmentController@qiniuCallback']);//
    Route::get('qiniu_callback', ['as' => 'qiniu_callback', 'uses' => 'AttachmentController@qiniuCallback']);
});
//七牛云分组:需要登录
Route::group(['prefix' => $weapp_prefix, 'middleware' => $weapp_middleware,'namespace' => $weapp_namespace], function () {
    Route::post('attachment/group.json', 'AttachmentGroupController@postGroups');//分组：新增
    Route::get('attachment/group.json','AttachmentGroupController@getGroups');//获取分组
    Route::delete('attachment/group/{id}.json', 'AttachmentGroupController@deleteGroup');//删除分组
    Route::put('attachment/group.json','AttachmentGroupController@putGroups');//分组：编辑

    //Route::post('attachment/setGroup.json', 'Admin\Attachment\AttachmentGroupController@postSetGroup');//设置分组

    Route::get('attachment', 'AttachmentController@getAttachments');//获取文件列表
});







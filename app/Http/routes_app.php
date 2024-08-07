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
$weapp_prefix = 'weapp';    //项目前缀
$weapp_namespace = 'Weapp';    //项目命名空间
$weapp_middleware = 'applet'; //不需要授权的中间件
$weapp_auth_middleware = 'auth.applet'; //需要授权的中间件

//订单:H5核销
Route::get('weapp/order/urllink.json', 'Weapp\Order\OrderController@getUrlLink');                       //买家小程序生成的二维码链接
Route::get('weapp/order/h5urllink.json', 'Weapp\Order\OrderController@getH5UrlLink');                   //买家小程序生成的二维码链接
Route::get('weapp/order/h5chargeoff.json', 'Weapp\Order\OrderController@getH5ChargeOff');               //生成核销url的过程
Route::get('weapp/order/h5chargeoff_affirm.json', 'Weapp\Order\OrderController@getH5ChargeOff_affirm'); //确认核销
Route::get('weapp/orderh5/{id}.json', 'Weapp\Order\OrderController@getInfo');                            //获取订单详情

//需要登录验证的接口
Route::group(['prefix' => $weapp_prefix, 'namespace' => $weapp_namespace, 'middleware' => $weapp_auth_middleware], function () {
    //订单
    Route::get('order.json', 'Order\OrderController@getList');                                //获取订单列表
    Route::get('order/{id}.json', 'Order\OrderController@getInfo');                            //获取订单详情
    Route::get('order_verify/{id}.json', 'Order\OrderController@getOrderVerify');           //下单验证数据
    //Route::put('order/{id}.json', 'Order\OrderController@putOrder');            			//修改订单
    Route::post('order.json', 'Order\OrderController@postOrder');                            //确认下单（直接购买，购物车购买）
    Route::post('order_buy.json', 'Order\OrderController@buyOrder');                        //去支付
    Route::get('order_success/{id}.json', 'Order\OrderController@getOrderSuccess');            //支付成功显示
    Route::post('delivery/{id}.json', 'Order\OrderController@postDelivery');                //确认收货
    Route::get('comment/{id}.json', 'Order\OrderController@getComment');                    //订单评价（获取订单）
    Route::post('comment/{id}.json', 'Order\OrderController@postComment');                    //发起订单评价
    Route::post('order_cancel/{id}.json', 'Order\OrderController@postOrderCancel');            //取消订单
    Route::post('extend_delivery/{id}.json', 'Order\OrderController@postExtendDelivery');    //延长收货
    Route::get('package/{id}.json', 'Order\OrderController@getPackage');                    //查看包裹

    //退款
    Route::get('refund/test', 'Order\RefundController@test'); //退款test
    Route::get('refund/get.json', 'Order\RefundController@getRefund'); //申请退款
    Route::post('refund/submit.json', 'Order\RefundController@postRefund'); //提交退款
    Route::get('refund/info.json', 'Order\RefundController@info'); //退款信息
    Route::get('refund/details.json', 'Order\RefundController@details'); //退款详情
    Route::get('refund/logistics.json', 'Order\RefundController@getLogistics'); //退款物流信息
    Route::post('refund/logisticsSubmit.json', 'Order\RefundController@postLogistics'); //退款物流提交
    Route::delete('refund/revocation.json', 'Order\RefundController@revocation'); //取消退款

    //收货地址
    Route::get('addr.json', 'Member\MemberAddrController@getAddress');                    //获取地址列表
    Route::get('addr/{id}.json', 'Member\MemberAddrController@getAddr');                //获取地址详情
    Route::post('addr.json', 'Member\MemberAddrController@postAddr');                    //添加地址
    Route::put('addr/{id}.json', 'Member\MemberAddrController@putAddr');                //修改地址
    Route::delete('addr/{id}.json', 'Member\MemberAddrController@deleteAddr');            //删除地址
    Route::put('addrdefault/{id}.json', 'Member\MemberAddrController@putAddrDefault');    //修改收货地址

    //个人信息
    Route::get('member_info.json', 'Member\MemberController@getMemberInfo');//获取用户信息
    Route::post('member/set_member_info.json', 'Member\MemberController@setMemberInfo'); //更新用户信息

    //手机短信验证
    Route::post('send_message.json', 'Member\MemberController@sendMessage');   //发送验证码
    Route::post('verify_message.json', 'Member\MemberController@verifyMessage');  //验证验证码
    Route::post('edit_mobile.json', 'Member\MemberController@postEditMobile'); //更改手机号

    //Cart
    Route::get('cart.json', 'Cart\CartController@index');////购物车列表
    Route::post('cart.json', 'Cart\CartController@store');//添加到购物车
    Route::put('cart/{str}.json', 'Cart\CartController@update');//编辑属性&数量，，，购物车id为字符串
    Route::delete('cart.json', 'Cart\CartController@destroy');//删除购物车商品[ids]

    //会员卡
    Route::get('vipcard/cardinfo.json', 'Vipcard\VipcardController@getVipCard');

    //优惠劵
    Route::post('coupon/give.json', 'Coupon\CouponController@giveMember'); //会员领取优惠劵
    Route::get('coupon/formember.json', 'Coupon\CouponController@forMember'); //获取买家已领取的优惠劵
    Route::get('coupon/code/{id}.json', 'Coupon\CouponController@codeDetails')->where('id', '[0-9]+');//优惠码详情
    Route::get('coupon/count.json', 'Coupon\CouponController@count'); //已领劵统计
    Route::get('coupon/order_usable_list.json', 'Coupon\CouponController@orderUsableList'); //订单可用优惠劵列表

    //秒杀订单提交
    //Route::get('seckill/154778/getSeckillSku.json','Seckill\SeckillController@getSeckillSku');
    Route::post('seckill/order.json', 'Seckill\SeckillController@postSeckillOrder');

    //预约商品订单提交
    Route::post('appt/order.json', 'Appt\ApptController@postApptOrder');

    //拼团
    Route::get('fightgroup/ladder_sku/{id}.json', 'Fightgroup\FightgroupController@getLadderSkuInfo');//获取拼团活动某阶梯   普通/规格商品库存信息
    Route::get('fightgroup/my_fightgroup_list.json', 'Fightgroup\FightgroupController@getMyFightgroupList');//我的拼团列表
    Route::post('fightgroup/open_fightgroup.json', 'Fightgroup\FightgroupController@openFightgroup');//开团
    Route::post('fightgroup/join_fightgroup.json', 'Fightgroup\FightgroupController@joinFightgroup');//参团
    Route::get('fightgroup/fightgroup_detail/{id}.json', 'Fightgroup\FightgroupController@getFightgroupDetail');//获取某个团的详情
    //Route::get('fightgroup/fightgroup_list', 'Fightgroup\FightgroupController@getfightgroupIist');//活动列表
    Route::get('fightgroup/fightgroup_info/{id}.json', 'Fightgroup\FightgroupController@getfightgroupInfo')->where('id', '[0-9]+');//活动详情

    //砍价
    Route::get('bargain/bargain.json', 'Bargain\BargainController@getBargainInfo');//获取砍价活动信息
    Route::post('bargain/bargain_join.json', 'Bargain\BargainController@bargainJoin');//砍价
    Route::post('bargain/bargain_buy.json', 'Bargain\BargainController@bargainBuy');//下单
    Route::get('bargain/bargainList.json','Bargain\BargainController@getBargainList');   //获取我的砍价活动列表
    Route::get('bargain/bargain_join_status.json','Bargain\BargainController@getBargainJoinStatus');   //获取是否发起过砍价

    //验证码
    Route::get('captcha.json', 'CaptchaController@index');

    //满减
    Route::get('discount/goods.json', 'Discount\DiscountController@getgoodsDiscountInfo');//满减：活动中某件商品详情的满减活动信息
    Route::get('cart', 'Discount\DiscountController@getCartGoodsDiscountInfo');//满减：购物车详情
    Route::get('discount/discountList.json', 'Discount\DiscountController@getDiscountActivityList');//满减：活动列表
    Route::get('discount/discount.json', 'Discount\DiscountController@getDiscountActivity');//满减：某个活动详情
    Route::get('discount/order.json', 'Discount\DiscountController@getOrderDiscountInfo');//满减：订单详情的满减金额
    Route::get('discount/orderprice.json', 'Discount\DiscountController@getOrderGoodsDiscountMoney');//满减：订单中商品的金额


    //积分列表
    Route::get('credit/list.json', 'Credit\CreditController@getCreditDetail');
    //积分规则
    Route::get('credit/rules.json', 'Credit\CreditController@getRegular');


    //领取会员卡
    Route::post('store/send_sms_message.json', 'Store\StoreController@sendSmsMessage');//发送验证码
    Route::post('store/open_card.json', 'Store\StoreController@openCard');//立即开卡
    Route::get('store/member_card.json', 'Store\StoreController@getMemberCard');//获取用户是否领取过会员卡


    //优惠买单
    Route::get('store/salepay_calculate.json', 'Store\StoreController@salepayCalculate');//优惠买单-输入金额或选择优惠券，计算优惠金额
    Route::post('store/salepay_topay.json', 'Store\StoreController@salepayTopay');//优惠买单去支付
    Route::get('store/salepay_list.json', 'Store\StoreController@getSalepayList');//买单记录
    Route::get('store/salepay_coupon_list.json', 'Store\StoreController@salepayCouponList');//用户的适用所有商品的优惠券列表


    //微信
    Route::post('weixin/formid.json', 'Weixin\AppController@formid');//发送验证码

    //超级表单
    Route::get('form/feedback/edit/{id}.json', 'Form\FormFeedbackController@showEdit')->where('id', '[0-9]+');//反馈详情(编辑)
    Route::get('form/feedback/{id}.json', 'Form\FormFeedbackController@show')->where('id', '[0-9]+');//反馈详情（查看）
    Route::get('form/{id}.json', 'Form\FormController@show')->where('id', '[0-9]+');//表单详情
    Route::post('form/feedback.json', 'Form\FormFeedbackController@store');//反馈
    Route::put('form/feedback/{id}.json', 'Form\FormFeedbackController@update')->where('id', '[0-9]+');//修改反馈
    Route::get('form/feedback.json', 'Form\FormFeedbackController@index');//反馈列表
    Route::get('form/feedback_times.json', 'Form\FormFeedbackController@getTimes');//反馈列表


    //抓娃娃兑换奖品记录
    Route::get('toy/record.json', 'Toy\ToyController@getRecord');
    //兑换奖品
    Route::post('toy/exchange.json', 'Toy\ToyController@postExchange');
    Route::get('toy/grab.json', 'Toy\ToyController@getGrab');//抓娃娃
    Route::get('toy/member.json', 'Toy\ToyController@getMember');//用户抓娃娃信息
    Route::post('toy/assist.json', 'Toy\ToyController@postAssist');//助力
    Route::get('toy/assist.json', 'Toy\ToyController@getAssist');//助力列表
    Route::get('toy/wxacode.json', 'Toy\ToyController@getWxacode');//获取分享的小程序码

    //推客
    Route::get('distrib.json', 'Distrib\DistribController@getInfo');
    Route::post('distrib/register.json', 'Distrib\DistribController@register'); //注册推客测试
    Route::post('distrib/bind_buyer_relation.json', 'Distrib\DistribController@bindBuyerRelation'); //绑定推客关系

    Route::get('distrib/center.json', 'Distrib\DistribController@getCenterInfo');//推广中心
    Route::get('distrib/order.json', 'Distrib\DistribController@getOrder');//推广订单列表
    Route::get('distrib/order/{order_id}.json', 'Distrib\DistribController@getOrderDetail')->where('order_id', '[0-9]+');//推广订单详情
    Route::get('distrib/superior.json', 'Distrib\DistribController@getSuperior');//我的下级
    Route::get('distrib/commission.json', 'Distrib\DistribController@getCommission');//佣金下级
    Route::get('distrib/partner/{id}.json', 'Distrib\DistribController@getPartner'); //获取一名推广员信息
    Route::put('distrib/partner/{id}.json', 'Distrib\DistribController@putPartner'); //修改推广员信息
    Route::get('distrib/activity_list.json', 'Distrib\DistribController@activityList'); //推广素材列表
    Route::get('distrib/activity_details/{id}.json', 'Distrib\DistribController@activityDetails'); //推广素材详情
    Route::get('distrib/downqrcode.json', 'Distrib\DistribController@downQrcode'); //下载推广二维码
    Route::get('distrib/downposter/{id}.json', 'Distrib\DistribController@downPoster'); //下载海报

    Route::get('member/banlance/log.json', 'Member\MemberController@banlanceLog');//会员、推客余额变动记录
    Route::get('member/banlance/withdrawing.json', 'Member\MemberController@withdrawing');//提现中金额

    Route::post('member/withdraw/wx.json', 'Member\MemberController@withdrawWx');//提现到微信零钱
    Route::post('member/withdraw/alipay.json', 'Member\MemberController@withdrawAlipay');//提现到支付宝
    Route::post('member/withdraw/bank.json', 'Member\MemberController@withdrawBank');//提现到银行卡
	
	//直播
	Route::post('live/push.json', 'Live\LiveController@push');//推送会员观看信息（直播，录播）
	Route::post('live/praise.json', 'Live\LiveController@praise');//直播点赞
    Route::get('live/{id}.json', 'Live\LiveController@getLive')->where('id', '[0-9]+'); //获取一条直播
    Route::get('live/record/{id}.json', 'Live\LiveController@getRecord')->where('id', '[0-9]+'); //获取一条录播
    Route::get('live/goods.json', 'Live\LiveController@getGoods'); //获取参与商品
    Route::get('live/card.json', 'Live\LiveController@downQrcode'); //生成卡片
    Route::post('live/carddata.json', 'Live\LiveController@cardData'); //生成卡片页面数据

    //知识付费
    Route::get('knowledge/my_column.json', 'Knowledge\KnowledgeOrderController@my_columns');//我的专栏列表
    Route::get('knowledge/my_content.json', 'Knowledge\KnowledgeOrderController@my_contents');//我的内容列表
    Route::post('knowledge/order.json', 'Knowledge\KnowledgeOrderController@store')->where('id', '[0-9]+');//付款购买

    //推客雷达
    Route::get('distrib/radar/chart.json', 'Distrib\DistribRadarController@chart');//图表
    Route::get('distrib/radar/clue.json', 'Distrib\DistribRadarController@clue');//销售线索
    Route::get('distrib/radar/ranking.json', 'Distrib\DistribRadarController@ranking');//推客、佣金排行

    //用户行为采集
    Route::post('behavior/collection.json', 'Behavior\BehaviorController@collection');
    //达达物流
    Route::get('express/order.json', 'Express\IndexController@index');
});

//不需要登录验证的接口
Route::group(['prefix' => $weapp_prefix, 'namespace' => $weapp_namespace, 'middleware' => $weapp_middleware], function () {

    //首页
    Route::get('banner.json', 'Design\DesignController@getBanner'); //获取头部banner

    //装修
    Route::get('feature.json', 'Design\DesignController@getFeature');  //获取装修内容
    //装修2
    Route::get('features.json', 'Design\DesignController@getFeatures');  //获取装修内容
    //获取装修底部菜单
    Route::get('design/navigation.json', 'Design\DesignController@getDesignNav');  //获取装修导航

    //装修组件
    Route::get('components.json', 'Design\DesignController@getComponents');  //异步加载装修组件

    //商品
    Route::get('goods/goods.json', 'Goods\GoodsController@index');                                                  //商品列表
    Route::get('goods/goods/{id}.json', 'Goods\GoodsController@show')->where('id', '[0-9]+');                       //商品详情
    Route::get('goods/comments/{good_id}.json', 'Goods\CommentController@index')->where('good_id', '[0-9]+');       //获取商品评论id:商品id
    Route::get('goods/comment_info/{comment_id}.json', 'Goods\CommentController@getOne')->where('comment_id', '[0-9]+');//获取单一评论详情
    Route::get('goods/goods_props/{id}.json', 'Goods\GoodsController@getGoodsProps')->where('id', '[0-9]+');        //获取商品参数
    Route::get('goods/get_goods_by_ids.json', 'Goods\GoodsController@getGoodsByIds');                                //根据商品id数组查询信息

    Route::get('goods/get_goods_by_seckillids.json', 'Goods\GoodsController@getGoodsBySeckillIds');                  //根据秒杀表id数组查询信息


    //Route::get('goods/skudata/{id}.json', 'Goods\GoodsController@getSkudata');                                      //获取商品、规格库存

    Route::get('goods/sku/{goods_id}.json', 'Goods\GoodsController@getSku')->where('goods_id', '[0-9]+');                                      //获取商品、规格库存

    //预约服务商品
    Route::get('appt/time_lists.json', 'Appt\ApptController@getTimePropValueLists');//-指定日期可预约时间段列表
    Route::get('appt/date_lists.json', 'Appt\ApptController@getDateLists');//-指定日期可预约时间段列表
    Route::get('goods/nearest_store.json', 'Appt\ApptController@getNearestStore');//商品售卖门店
    Route::get('goods/staff.json', 'Appt\ApptController@getStaff');//查商品下可选技师
    Route::get('goods/appt/stock.json', 'Appt\ApptController@getApptStock');//获取预约商品库存

    //优惠劵
    Route::get('coupon/lists.json', 'Coupon\CouponController@getLists');
    Route::get('coupon/{id}.json', 'Coupon\CouponController@details')->where('id', '[0-9]+');//优惠劵详情
    //授权登录(2018年5月以前使用)
    Route::post('member/login.json', 'Member\MemberController@login');
    //获取session_key
    Route::post('member/get_session_key.json', 'Member\MemberController@getSessionKey');
    //授权登录(2018年5月以后使用)
    Route::post('member/onlogin.json', 'Member\MemberController@onLogin');
    //获取微信绑定的手机号
    Route::post('member/get_phone_number.json', 'Member\MemberController@getPhoneNumber');

    //新改版授权
    Route::post('authorize/get_token.json', 'Authorize\AuthorizeController@getToken');
    Route::post('authorize/onlogin.json', 'Authorize\AuthorizeController@onLogin');
    
    //获取省市区信息
    Route::get('region.json', 'Member\MemberAddrController@getRegion');

    //门店
    Route::get('store/verify_store.json', 'Store\StoreController@verifyStore');//获取3KM内是否有门店
    Route::get('store/store_list.json', 'Store\StoreController@getStoreList');//门店列表
    Route::get('store/store_detail/{id}.json', 'Store\StoreController@getStoreDetail');//门店详情
    Route::post('store/getcustomer.json', 'Store\StoreController@getCustomer'); //门店获取客服设置


    //自提门店列表
    Route::get('store/pick_store_list.json', 'Store\StoreController@getPickStoreList');

    //优惠买单
    Route::get('store/salepay_info.json', 'Store\StoreController@getSalepayInfo');//优惠方式信息


    //文章
    Route::get('article/getonearticle/{article_id}.json', 'Article\ArticleController@getOneArticle')->where('article_id', '[0-9]+');            //获取单一文章
    Route::get('article/getacticlelist.json', 'Article\ArticleController@getArticle');            //获取所有文章


    //小程序端注册小程序
    Route::get('captcha/register.json', 'CaptchaController@register');//注册图片验证码
    Route::post('register/sendphone.json', 'Register\RegisterController@sendPhone');//发送手机验证码
    Route::post('register/doregister.json', 'Register\RegisterController@doRegister');//立即注册

    //获取商户信息
    Route::get('merchant/info.json', 'Merchant\MerchantController@info');

    //新用户有礼
    Route::get('newusergift/record.json', 'NewUserGift\NewUserGiftController@getRecord');

    //知识付费
    Route::get('knowledge/column/{id}.json', 'Knowledge\KnowledgeColumnController@show')->where('id', '[0-9]+');//专栏详情
    Route::get('knowledge/content/{id}.json', 'Knowledge\KnowledgeContentController@show')->where('id', '[0-9]+');//内容详情
    Route::get('knowledge/column/content.json', 'Knowledge\KnowledgeColumnController@contents');//详情内-根据专栏id查询所有内容（目录）
    Route::get('knowledge/getcolumnids.json', 'Knowledge\KnowledgeColumnController@get_columns_by_ids');//根据专栏ids查询所属专栏
    Route::get('knowledge/column.json', 'Knowledge\KnowledgeColumnController@index');//所有专栏
    Route::get('knowledge/content.json', 'Knowledge\KnowledgeContentController@index');//所有内容
    Route::get('knowledge/columnsbycontentid.json', 'Knowledge\KnowledgeContentController@getColumnByContentId');//根据内容id获取所属专栏

    //分享卡片
    Route::get('sharecard/getcard.json','Sharecard\SharecardController@getcard');

    //直播
    Route::get('live/list.json', 'Live\LiveController@liveList'); //直播列表
    Route::get('live/record/list.json', 'Live\LiveController@recordList'); //录播列表
    
});

//装修扫码预览
Route::get('weapp/preview.json', 'Weapp\Design\DesignController@getPreview');
Route::get('weapp/knowledge/getcolumnids_new.json', 'Weapp\Knowledge\KnowledgeColumnController@get_columns_by_ids_new');//根据专栏ids查询所属专栏
Route::get('weapp/knowledge/columns_design.json', 'Weapp\Knowledge\KnowledgeContentController@get_all_design');//所有内容无需token

//会员登录
Route::group(['prefix' => $weapp_prefix, 'namespace' => $weapp_namespace], function () {
    //调试期间登录用
    Route::get('member/templogin.json', 'Member\MemberController@index');
    //授权登录
    Route::get('member/webauth.json', 'Member\MemberController@webAuth');
    //授权回调
    Route::get('member/webauth_back.json', 'Member\MemberController@webAuthBack');
    //上传图片至七牛
    Route::post('attachment/qiniu.json', 'Attachment\AttachmentController@uploadQiniu');
    //案例
    Route::get('case/industry.json', 'SuperCase\SuperCaseController@getIndustry');          // 行业分类列表
    Route::get('case/getcases.json', 'SuperCase\SuperCaseController@getCases');          // 案例列表
    Route::post('case/case.json', 'SuperCase\SuperCaseController@postCase');                // 添加编辑案例
    //Route::get('case/caseimg.json','SuperCase\SuperCaseController@caseCard');
    Route::get('case/{id}.json', 'SuperCase\SuperCaseController@getCase');       // 案例详细
    //检测token是否过期
    Route::get('authorize/check_token.json', 'Authorize\AuthorizeController@checkToken');

});


//测试
Route::group(['prefix' => $weapp_prefix, 'namespace' => $weapp_namespace], function () {
    Route::get('test.json', 'TestController@index');
    Route::get('exportusersql.json', 'TestController@exportUserSql');
    //增加商品属性
    Route::get('update_goods_cat.json', 'TestController@updateGoodsCat');
    //下架原服务商品
    Route::get('soldout.json', 'TestController@soldout');
    //同步优惠劵发放数量
    Route::get('sync_coupon_send_num.json', 'TestController@syncCouponSendNum');
    Route::get('test/{id}/{type}', 'TestController@zcctest');
    //修改推客关系
    Route::get('edit_distrib_relation.json', 'TestController@editDistribRelation');
    //商品相关测试
    Route::delete('goods/test/{id}.json', 'Goods\TestController@destroy')->where('id', '[0-9]+');//清redis。 id：商品id
    Route::get('goods/test.json', 'Goods\TestController@index');//test
    Route::get('goods/test/{id}.json', 'Goods\TestController@show')->where('id', '[0-9]+');//test
    Route::put('goods/test/{id}.json', 'Goods\TestController@update')->where('id', '[0-9]+');//增减存测试

    Route::get('toy/cache_money.json', 'Toy\ToyController@getCacheMoney');//查看已抓中娃娃的缓存中的金额价值
    Route::get('toy/flush_cache.json', 'Toy\ToyController@flushToyCache');//清所有抓娃娃缓存


    Route::get('distrib/register.json', 'Distrib\DistribController@register');//手机端注册推客
    

});


//不需要登录验证的接口--拼团测试接口
Route::group(['prefix' => $weapp_prefix, 'namespace' => $weapp_namespace], function () {

    Route::get('fightgroup/getfightgrouptest', 'Fightgroup\FightgrouptestController@getfightgrouptest');//测试路由
    Route::get('fightgroup/fightgroupbacktest', 'Fightgroup\FightgroupController@fightgroupbacktest');//测试路由
    Route::get('fightgrouptest/fightgroupbacktest', 'Fightgroup\FightgrouptestController@fightgroupbacktest');//测试路由
	 Route::get('fightgroup/getfightgrouptest', 'Fightgroup\FightgrouptestController@getfightgrouptest');//测试路由
	 Route::get('fightgroup/fightgroupbacktest', 'Fightgroup\FightgroupController@fightgroupbacktest');//测试路由
	 Route::get('fightgrouptest/fightgroupbacktest', 'Fightgroup\FightgrouptestController@fightgroupbacktest');//测试路由

});


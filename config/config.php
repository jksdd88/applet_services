<?php
/**
 *  订单状态 order_status
 */
!defined('ORDER_AUTO_CANCELED') && define('ORDER_AUTO_CANCELED',1);		// 已关闭 ,交易自动取消(未付款情况)
!defined('ORDER_BUYERS_CANCELED') && define('ORDER_BUYERS_CANCELED',2);	// 已关闭 ,买家自己取消订单
!defined('ORDER_MERCHANT_CANCEL') && define('ORDER_MERCHANT_CANCEL',3);	// 已关闭 ,商家关闭
!defined('ORDER_REFUND_CANCEL') && define('ORDER_REFUND_CANCEL',4);		// 已关闭 ,所有维权申请处理完毕
!defined('ORDER_SUBMIT') && define('ORDER_SUBMIT',5);					// 已下单、未提交支付
!defined('ORDER_TOPAY') && define('ORDER_TOPAY',6);						// 待付款
!defined('ORDER_TOSEND') && define('ORDER_TOSEND',7);					// 待发货（货到付款 待发货）
!defined('ORDER_SUBMITTED') && define('ORDER_SUBMITTED',8);				// 货到付款 待发货（暂时没用到）
!defined('ORDER_FORPICKUP') && define('ORDER_FORPICKUP',9);				// 上门自提，待取货
!defined('ORDER_SEND') && define('ORDER_SEND',10);						// 商家发货 买家待收货(预约服务)
!defined('ORDER_SUCCESS') && define('ORDER_SUCCESS',11);				// 已完成 交易成功

/**
 *  订单类型 order_type
 */
!defined('ORDER_SHOPPING') && define('ORDER_SHOPPING',1);     	//商城订单
!defined('ORDER_FIGHTGROUP') && define('ORDER_FIGHTGROUP',2);	//拼团订单
!defined('ORDER_SECKILL') && define('ORDER_SECKILL',3);			//秒杀订单
!defined('ORDER_APPOINT') && define('ORDER_APPOINT',4);			//预约订单
!defined('ORDER_SALEPAY') && define('ORDER_SALEPAY',5);			//优惠买单订单
!defined('ORDER_BARGAIN') && define('ORDER_BARGAIN',6);			//砍价订单
!defined('ORDER_KNOWLEDGE') && define('ORDER_KNOWLEDGE',7);     //知识付费订单

/**
 *  订单商品类型 order_goods_type
 */
!defined('ORDER_GOODS_COMMON') && define('ORDER_GOODS_COMMON',0);   //普通订单
!defined('ORDER_GOODS_VIRTUAL') && define('ORDER_GOODS_VIRTUAL',1);	//虚拟商品订单

/**
 *  订单支付方式 pay_type
 */
!defined('ORDER_PAY_WEIXIN') && define('ORDER_PAY_WEIXIN',1);       //微信支付
!defined('ORDER_PAY_DELIVERY') && define('ORDER_PAY_DELIVERY',2);	//货到付款


/* 会员账号常量*/
!defined('MEMBER_CONST') && define('MEMBER_CONST',1000002017);

!defined('DELIVERY_EXTEND_DAYS') && define('DELIVERY_EXTEND_DAYS',3);	//延长收货时间

!defined('REFUND') && define('REFUND',10); //申请退款中
!defined('REFUND_AGAIN') && define('REFUND_AGAIN',11); //再次申请退款中
!defined('REFUND_AGREE') && define('REFUND_AGREE',20); //商家同意退款
!defined('REFUND_REFUSE') && define('REFUND_REFUSE',21);  //拒绝退款
!defined('REFUND_SEND') && define('REFUND_SEND',22); //买家已发货，等待卖家收货
!defined('REFUND_RECEIVE') && define('REFUND_RECEIVE',23); //卖家确认收货
!defined('REFUND_FAIL') && define('REFUND_FAIL',29); // 第三方退款失败
!defined('REFUND_TRADING') && define('REFUND_TRADING',30);  //第三方退款中
!defined('REFUND_FINISHED') && define('REFUND_FINISHED',31);  //已经退款，退款完成
!defined('REFUND_CLOSE') && define('REFUND_CLOSE',40);  //已经关闭
!defined('REFUND_CANCEL') && define('REFUND_CANCEL',41);  //用户取消退款
!defined('REFUND_MER_CANCEL') && define('REFUND_MER_CANCEL',42);  //商家关闭退款

/**
 * 拼团状态
 */
!defined('PIN_SUBMIT') && define('PIN_SUBMIT',0);//提交,未开始
!defined('PIN_ACTIVE') && define('PIN_ACTIVE',1);//活动中
!defined('PIN_TIME_END') && define('PIN_TIME_END',2);//已结束(所有拼团全部结束)
!defined('PIN_MERCHANT_END') && define('PIN_MERCHANT_END',3);//已结束(手动)

/**
 * 拼团层级状态
 */
!defined('PIN_LADDER_SUBMIT') && define('PIN_LADDER_SUBMIT',0);//未开始
!defined('PIN_LADDER_ACTIVE') && define('PIN_LADDER_ACTIVE',1);//拼团中
!defined('PIN_LADDER_END') && define('PIN_LADDER_END',2);//已结束
!defined('PIN_LADDER_STOCK_END') && define('PIN_LADDER_STOCK_END',3);//已结束(库存不足)
!defined('PIN_LADDER_MERCHANT_END') && define('PIN_LADDER_MERCHANT_END',4);//已结束(手动)


/**
 * 团长开团状态
 */
!defined('PIN_INIT_SUBMIT') && define('PIN_INIT_SUBMIT',0);//团长开团未支付
!defined('PIN_INIT_ACTIVE') && define('PIN_INIT_ACTIVE',1);//拼团中
!defined('PIN_INIT_SUCCESS') && define('PIN_INIT_SUCCESS',2);//拼团成功
!defined('PIN_INIT_FAIL') && define('PIN_INIT_FAIL',3);//失败(团长未支付)
!defined('PIN_INIT_FAIL_STOCK') && define('PIN_INIT_FAIL_STOCK',4);//失败(库存不足)
!defined('PIN_INIT_FAIL_END') && define('PIN_INIT_FAIL_END',5);//失败(人数未达到)
!defined('PIN_INIT_FAIL_MERCHANT') && define('PIN_INIT_FAIL_MERCHANT',6);//失败(手动)
!defined('PIN_INIT_FAIL_NOTWORKING') && define('PIN_INIT_FAIL_NOTWORKING',7);//开团失败（非进行中（活动、阶梯、团）超卖）

/**
 * 团员参团状态
 */
!defined('PIN_JOIN_SUBMIT') && define('PIN_JOIN_SUBMIT',0);//参团待支付
!defined('PIN_JOIN_PAID') && define('PIN_JOIN_PAID',1);//参团支付成功
!defined('PIN_JOIN_FAIL') && define('PIN_JOIN_FAIL',2);//失败 超时未支付
!defined('PIN_JOIN_FAIL_STOCK') && define('PIN_JOIN_FAIL_STOCK',3);//失败(库存不足)
!defined('PIN_JOIN_FAIL_END') && define('PIN_JOIN_FAIL_END',4);//失败(时间内未达到有效人数)
!defined('PIN_JOIN_FAIL_EXCEED') && define('PIN_JOIN_FAIL_EXCEED',5);//失败(超卖)
!defined('PIN_JOIN_SUCCESS') && define('PIN_JOIN_SUCCESS',6);//参团成功
!defined('PIN_JOIN_FAIL_MERCHANT') && define('PIN_JOIN_FAIL_MERCHANT',7);//失败(手动)
!defined('PIN_JOIN_FAIL_NOTWORKING') && define('PIN_JOIN_FAIL_NOTWORKING',8);//失败（非进行中（活动、阶梯、团）超卖）

/**
 * 拼团退款状态
 */
!defined('PIN_REFUND_SUBMIT') && define('PIN_REFUND_SUBMIT',0);//0:申请退款中
!defined('PIN_REFUND_FAIL') && define('PIN_REFUND_FAIL',1);//1退款失败
!defined('PIN_REFUND_SUCCESS') && define('PIN_REFUND_SUCCESS',2);//2退款成功

/**
 * 订单设置自定义留言的格式
 */
!defined('TEXT_FORMAT') && define('TEXT_FORMAT','text');//单行文本
!defined('MTEXT_FORMAT') && define('MTEXT_FORMAT','multipleText');//多行文本
!defined('NUMBER_FORMAT') && define('NUMBER_FORMAT','num');//数字格式
//!defined('EMAIL_FORMAT') && define('EMAIL_FORMAT',3);//邮件格式
//!defined('DATE_FORMAT') && define('DATE_FORMAT',4);//日期格式
//!defined('TIME_FORMAT') && define('TIME_FORMAT',5);//时间格式
!defined('ID_CARD_NO_FORMAT') && define('ID_CARD_NO_FORMAT','id_no');//身份证
//!defined('IMAGE_FORMAT') && define('IMAGE_FORMAT',7);//图片

/**
 * 证书上传目录
 */
!defined('PEM_UPLOAD_PATH') && define('PEM_UPLOAD_PATH','/data/nfs_disk/payment_pem/');
!defined('PEM_PATH') && define('PEM_PATH','data/nfs_disk/payment_pem/');


/**
 * 临时目录
 */
!defined('TEMPORARY_PATH') && define('TEMPORARY_PATH',dirname(dirname(__FILE__)).'/storage/');

/**
 * 订单分佣状态
 */
!defined('DISTRIB_AWAIT') && define('DISTRIB_AWAIT', 0);					//待处理
!defined('DISTRIB_NOT') && define('DISTRIB_NOT', 1);						//不参与分佣
!defined('DISTRIB_AWAIT_SETTLED') && define('DISTRIB_AWAIT_SETTLED', 2);	//已处理，待结算
!defined('DISTRIB_FINISH') && define('DISTRIB_FINISH', 3);					//已结算
!defined('DISTRIB_REFUND') && define('DISTRIB_REFUND', 4);					//已退单

/**
 * 推客提现状态
 */
!defined('TAKECASH_AWAIT') && define('TAKECASH_AWAIT', 1);				//待处理
!defined('TAKECASH_SUBMIT') && define('TAKECASH_SUBMIT', 2);			//提现中
!defined('TAKECASH_REFUSE') && define('TAKECASH_REFUSE', 3);			//商家拒绝
!defined('TAKECASH_SUCCESS') && define('TAKECASH_SUCCESS', 4);			//提现成功
!defined('TAKECASH_FAIL') && define('TAKECASH_FAIL', 5);				//提现失败（微信零钱返回）

return [
	'order_type'	=>	[	//订单类型
		ORDER_SHOPPING		=>	'商城订单',
		ORDER_FIGHTGROUP	=>	'拼团订单',
		ORDER_SECKILL		=>	'秒杀订单',
		ORDER_APPOINT		=>	'预约订单',
		ORDER_SALEPAY		=>	'优惠买单订单',
		ORDER_KNOWLEDGE		=>	'知识付费订单',
        ORDER_BARGAIN       =>	'砍价订单',
	],
    'order_status' => [		//订单状态
		ORDER_AUTO_CANCELED		=>	'已关闭 ,交易自动取消(未付款情况)',
        ORDER_BUYERS_CANCELED	=>	'已关闭 ,买家自己取消订单',
		ORDER_MERCHANT_CANCEL	=>	'已关闭 ,商家关闭',
		ORDER_REFUND_CANCEL		=>	'已关闭 ,所有维权申请处理完毕',
		ORDER_SUBMIT			=>	'已下单、未提交支付',
		ORDER_TOPAY				=>	'待付款',
		ORDER_TOSEND			=>	'待发货',
		ORDER_SUBMITTED			=>	'货到付款 待发货',
		ORDER_FORPICKUP			=>	'上门自提，待取货',
		ORDER_SEND				=>	'商家发货 买家待收货',
		ORDER_SUCCESS			=>	'已完成 交易成功',
    ],
    'refund_status' => [		//订单状态
        REFUND		        =>	'申请退款中',
        REFUND_AGAIN 	    =>	'再次申请退款中',
        REFUND_AGREE		=>	'商家同意退款',
        REFUND_REFUSE		=>	'拒绝退款',
        REFUND_SEND			=>	'已发货，等待卖家收货',
        REFUND_RECEIVE      =>  '卖家确认收货',
        REFUND_FAIL			=>	'第三方退款失败',
        REFUND_TRADING		=>	'第三方退款中',
        REFUND_FINISHED		=>	'已经退款，退款完成',
        REFUND_CLOSE		=>	'已经关闭',
        REFUND_CANCEL		=>	'用户取消退款',
    ],
	'marketing'	=>	[	//营销支持（1-支持，0-不支持）
		'vip'           =>	0,	//会员优惠
        'discount'      =>	0,	//满就送
        'coupon'        =>	0,	//优惠券
        'credit'        =>	0,	//积分抵扣
		'delivery'		=>	0,	//物流配送
		'goods'			=>	0,	//支付商品
	],
	'marketing_1'	=>	['vip','discount','coupon','credit','delivery','goods'],	//商城订单
	'marketing_2'	=>	['credit','delivery','goods'],	//拼团订单
	'marketing_3'	=>	['credit','delivery','goods'],	//秒杀订单
	'marketing_4'	=>	['vip','discount','coupon','credit','goods'],	//预约订单
	'marketing_5'	=>	['vip','coupon','credit'],	//优惠买单订单
    'marketing_6'	=>	['credit','delivery','goods'],	//砍价订单
	'marketing_7'	=>	[],//不走优惠（知识付费）
    'refund_reason' => [	//退款说明
        0 => '买/卖双方协商一致',
        1 => '买错/不想要',
        2 => '商品质量问题',
        3 => '未收到货',
        4 => '其他'
    ],
    'order_custom_message_format'   => [//订单留言格式
        TEXT_FORMAT => '单行文本',
//        MTEXT_FORMAT => '多行文本',
        NUMBER_FORMAT => '数字格式',
        //EMAIL_FORMAT => '邮件格式',
        //DATE_FORMAT => '日期',
        //TIME_FORMAT => '时间',
        ID_CARD_NO_FORMAT => '身份证号码',
        //IMAGE_FORMAT => '图片',
    ],
	'stock_type'	=>	[	//活动扣库存方式（0-根据商品属性，1-拍下口库存，2-付款扣库存）
		ORDER_SHOPPING		=>	1,	//'商城订单',(统一全部走拍下减少库存) 0 -> 1
		ORDER_FIGHTGROUP	=>	2,	//'拼团订单',
		ORDER_SECKILL		=>	1,	//'秒杀订单',
		ORDER_APPOINT		=>	1,	//'预约订单',(统一全部走拍下减少库存) 0 -> 1
        ORDER_BARGAIN       =>  1,  //砍价订单
	],	
	'takecash_status' => [
		TAKECASH_AWAIT		=> '待处理',
		TAKECASH_SUBMIT		=> '提现中',
		TAKECASH_REFUSE		=> '商家拒绝',
		TAKECASH_SUCCESS	=> '提现成功',
		TAKECASH_FAIL		=> '微信零钱提现失败',
	],
	'template_type' => [
		'mall'		=> 250,//线上商城
		'website'		=> 248,//公司官网
	],
    'live_buy' => [    //购买直播余额配置
    
        'live_bag' => [    //直播包
            1 => 128,    //key：数量，val：点点币
            10 => 1280,
            100 => 3800,
            1000 => 29800,
            5000 => 138000,
        ],
        
        'record_bag' => [    //录播包
            100 => 98,    //key：数量，val：点点币
            1000 => 680,
            10000 => 1680,
            50000 => 8100,
            100000 => 15800,
        ],
        
        'live_store' => [    //云存储（购买直播包，额外赠送的云存储）
            1 => 5,    //key：直播包数量，val：赠送的云存储数量
            10 => 10,
            100 => 100,
            1000 => 1000,
            5000 => 5000,
        ],
        
    ]
];

<?php
/**
 * @ 配置参数数据
 * @ author zhangchangchun@dodoca.com
 * @ time 2017-09-04
 */

return [
    'order_info_order_type' => [	//order_info表order_type字段
       	1	=>	'商城订单',
		2	=>	'拼团订单',
		3	=>	'秒杀订单',
		4	=>	'预约订单',
        5	=>	'优惠买单订单',
        6	=>	'砍价买单订单',
        7	=>	'知识付费订单',
    ],
    'order_info_order_goods_type' => [	//order_info表order_goods_type字段
        0	=>	'普通订单',
        1	=>	'虚拟商品订单',
    ],
    'order_info_pay_type' => [	//order_info表pay_type字段
        0   =>  '积分抵扣',
       	1	=>	'微信支付',
        2	=>	'货到付款',
    ],
    'order_ump_ump_type' => [	//order_ump表ump_type字段
        1	=>	'会员卡优惠',
		2	=>	'优惠券',
		3	=>	'积分抵扣',
		4	=>	'商家改价',
		5	=>	'拼团',
		6	=>	'秒杀',
        7	=>	'满减',
        8	=>	'满包邮',
        9	=>	'砍价',
    ],
    'order_goods_ump_ump_type' => [	//order_goods_ump表ump_type字段
        1	=>	'会员卡优惠',
		2	=>	'优惠券抵扣',
		3	=>	'积分抵扣',
		4	=>	'商家改价',
		5	=>	'拼团优惠',
		6	=>	'秒杀优惠',
        7	=>	'满减优惠',
        8	=>	'满包邮',
        9	=>	'砍价',
    ],
    'credit_type' => [ //credit_detail表type字段
        1   =>  '完善手机',
        2   =>  '下单送积分',
        3   =>  '主动确认收货',
        4   =>  '积分抵扣',
        5   =>  '手动修改',
		6	=>	'取消订单退积分',
		7	=>	'订单退款退积分',
    ],
    'except_data_data_type' => [ //except_data表data_type字段
        'order_cancel_pay'   			=>  '取消订单，已支付订单反转',
        'order_cancel_return_credit'   	=> 	'取消订单归还积分',
		'order_cancel_return_stock'		=>	'取消订单归还库存',
		'order_delivery_return_credit'	=>	'确认收货送积分',
    ],
    'queue_data_data_type' => [ //queue_data表data_type字段
        'order_cancel_job'   			=>  '自动取消队列',
		'order_delivery_job'			=>	'确认收货队列',
		'order_paysuccess_job'			=>	'支付成功回调队列',
    ],
    'order_refund_apply_apply_type' => [ //order_refund_apply表apply_type字段
        1   =>  '用户申请退款处理记录',
        2   =>  '拼团等自动退款记录',
        3   =>  '超卖自动退款',
    ],
    'order_appt_hexiao_status' => [ //order_appt表hexiao_status字段 
        0   =>  '未使用',
        1   =>  '已核销',
        2   =>  '已失效',//预留,根据时间判断
        3   =>  '超时',//该笔订单商品全部退款完成
        4   =>  '维权中',//该笔订单商品全部退款完成
    ],
    'order_virtualgoods_hexiao_status' => [ //order_virtualgoods表hexiao_status字段
        0   =>  '未使用',
        1   =>  '已使用',
        2   =>  '已退款',
    ],
    'goods_cat_cat_type' => [ //goods_cat表cat_type字段
        0   =>  '普通分类',
        1   =>  '预约分类',
        2   =>  '虚拟商品',
    ],
    'merchant_balance_type' => [ //merchant_balance表type字段
        1   =>  '充值点点币',
        2   =>  '购买直播包',
        3   =>  '购买录播包',
        4   =>  '购买云存储',
        5   =>  '代理商充值',
    ],
    'live_balance_type' => [ //live_balance表type字段
        1   =>  '购买直播包',
        2   =>  '删除返还直播包',
		3	=>	'购买录播包',
		4	=>	'购买云存储',
		5	=>	'清空录播包',
		6	=>	'续期直播包',
		7	=>	'续期云存储',
		8	=>	'使用直播包',
		9	=>	'使用录播包',
		10	=>	'使用云存储',
		11	=>	'赠送直播包',
        12  =>  '赠送云存储',
    ],
];

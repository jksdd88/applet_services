<?php
/**
 * Created by PhpStorm.
 * User: andery
 * Date: 18/7/30
 * Time: 下午5:36
 */
$weixinadConfigs = [
    'local' => [//本地开发
        'client_id'         => '1107156055',
        'client_secret'     => 'pMLpoWobtlTDO1kh',
        'redirect_domain'   => 'https://applet.rrdoy.com',
    ],
    'develop-applet' => [ //点点客小程序(开发)
        'client_id'         => '1107156055',
        'client_secret'     => 'pMLpoWobtlTDO1kh',
        'redirect_domain'   => 'https://applet.rrdoy.com',
    ],
    'develop' => [ //点点客小程序(开发)
        'client_id'         => '',
        'client_secret'     => '',
        'redirect_domain'   => '',
    ],
    'develop-xcx' => [ //点点客小程序(开发)
        'client_id'         => '',
        'client_secret'     => '',
        'redirect_domain'   => '',
    ],
    'test' => [//点点客小程序（QA）
        'client_id'         => '',
        'client_secret'     => '',
        'redirect_domain'   => '',
    ],
    'release' => [ //点点客小程序（集成）
        'client_id'         => '',
        'client_secret'     => '',
        'redirect_domain'   => '',
    ],
    'production' => [ //生产
        'client_id'         => '',
        'client_secret'     => '',
        'redirect_domain'   => '',
    ]
];
$weixinadConfigs = $weixinadConfigs[env('APP_ENV')];

//微信广告
return [
    'client_id'         => $weixinadConfigs['client_id'],
    'client_secret'     => $weixinadConfigs['client_secret'],
    'redirect_domain'   => $weixinadConfigs['redirect_domain'],
    
    //广告位
    'ad_location' => [
        1 => "朋友圈",
        2 => "公众号顶部",
        3 => "公众号底部"
    ],
    
    //微信相关信息
    'product_type' => [
        '1' => [
            'weixinad_title' => '品牌推广',
            'weixinad_enum' => 'PRODUCT_TYPE_ECOMMERCE'
        ],
        '2' => [
            'weixinad_title' => '商品推广',
            'weixinad_enum' => 'PRODUCT_TYPE_LINK_WECHAT'
        ],
        '3' => [
            'weixinad_title' => '门店推广',
            'weixinad_enum' => 'PRODUCT_TYPE_LBS_WECHAT'
        ]
    ],
    
    //推广计划类型
    'campaign_type' => [
        '1' => [
            'weixinad_title' => '普通展示广告',   //暂时不用
            'weixinad_enum' => 'CAMPAIGN_TYPE_NORMAL'
        ],
        '2' => [
            'weixinad_title' => '微信公众号广告',
            'weixinad_enum' => 'CAMPAIGN_TYPE_WECHAT_OFFICIAL_ACCOUNTS'
        ],
        '3' => [
            'weixinad_title' => '微信朋友圈广告',
            'weixinad_enum' => 'CAMPAIGN_TYPE_WECHAT_MOMENTS'
        ],
    ],
    
    //性别
    'gender' => [
        '1' => [
            'weixinad_title' => '男性',
            'weixinad_enum' => 'MALE'
        ],
        '2' => [
            'weixinad_title' => '女性',
            'weixinad_enum' => 'FEMALE'
        ],
    ],
    
    //学历枚举 
    'education' => [
        '0' => [
            'weixinad_title' => '未知',   
            'weixinad_enum' => 'UNKNOWN'
        ],
        '1' => [
            'weixinad_title' => '博士',   
            'weixinad_enum' => 'DOCTOR'
        ],
        '2' => [
            'weixinad_title' => '硕士',   
            'weixinad_enum' => 'MASTER'
        ],
        '3' => [
            'weixinad_title' => '本科',   
            'weixinad_enum' => 'BACHELOR'
        ],
        '4' => [
            'weixinad_title' => '专科',   
            'weixinad_enum' => 'JUNIOR_COLLEGE'
        ],
        '5' => [
            'weixinad_title' => '高中',   
            'weixinad_enum' => 'SENIOR'
        ],
        '6' => [
            'weixinad_title' => '初中',   
            'weixinad_enum' => 'JUNIOR'
        ],
        '7' => [
            'weixinad_title' => '小学',   
            'weixinad_enum' => 'PRIMARY'
        ]
    ],
    
    //兴趣枚举 
    'interest' => [
        '1' => [
            'weixinad_title' => '教育',   
            'weixinad_enum' => '1'
        ],
        '2' => [
            'weixinad_title' => '旅游',   
            'weixinad_enum' => '2'
        ],
        '3' => [
            'weixinad_title' => '金融',   
            'weixinad_enum' => '3'
        ],
        '4' => [
            'weixinad_title' => '汽车',   
            'weixinad_enum' => '4'
        ],
        '5' => [
            'weixinad_title' => '房产',   
            'weixinad_enum' => '5'
        ],
        '6' => [
            'weixinad_title' => '家居',   
            'weixinad_enum' => '6'
        ],
        '7' => [
            'weixinad_title' => '服饰鞋帽箱包',   
            'weixinad_enum' => '7'
        ],
        '8' => [
            'weixinad_title' => '餐饮美食',   
            'weixinad_enum' => '8'
        ],
        '9' => [
            'weixinad_title' => '生活服务',   
            'weixinad_enum' => '9'
        ],
        '10' => [
            'weixinad_title' => '商务服务',   
            'weixinad_enum' => '10'
        ],
        '11' => [
            'weixinad_title' => '美容',   
            'weixinad_enum' => '11'
        ],
        '12' => [
            'weixinad_title' => '互联网/电子产品',   
            'weixinad_enum' => '12'
        ],
        '13' => [
            'weixinad_title' => '体育运动',   
            'weixinad_enum' => '13'
        ],
        '14' => [
            'weixinad_title' => '医疗健康',   
            'weixinad_enum' => '14'
        ],
        '15' => [
            'weixinad_title' => '孕产育儿',   
            'weixinad_enum' => '15'
        ],
        '16' => [
            'weixinad_title' => '游戏',   
            'weixinad_enum' => '16'
        ],
        '21' => [
            'weixinad_title' => '政法',   
            'weixinad_enum' => '21'
        ],
        '25' => [
            'weixinad_title' => '娱乐休闲',   
            'weixinad_enum' => '25'
        ]
    ],
    
    //用户操作系统枚举
    'user_os' => [
        '0' => [
            'weixinad_title' => '未知',   
            'weixinad_enum' => 'UNKNOWN'
        ],
        '1' => [
            'weixinad_title' => 'IOS 系统',   
            'weixinad_enum' => 'IOS'
        ],
        '2' => [
            'weixinad_title' => '安卓系统',   
            'weixinad_enum' => 'ANDROID'
        ],/*
        '3' => [
            'weixinad_title' => 'Windows 系统',   
            'weixinad_enum' => 'WINDOWS'
        ],
        '4' => [
            'weixinad_title' => '塞班系统',   
            'weixinad_enum' => 'SYMBIAN'
        ],
        '5' => [
            'weixinad_title' => 'JAVA 系统',   
            'weixinad_enum' => 'JAVA'
        ],*/
    ],
    
    //年龄枚举
    'age' => [
        '1' => [
            'weixinad_title' => '1~17岁',   
            'weixinad_enum' => '1~17'
        ],
        '2' => [
            'weixinad_title' => '18~23岁',   
            'weixinad_enum' => '18~23'
        ],
        '3' => [
            'weixinad_title' => '24~30岁',   
            'weixinad_enum' => '24~30'
        ],
        '4' => [
            'weixinad_title' => '31~40岁',   
            'weixinad_enum' => '31~40'
        ],
        '5' => [
            'weixinad_title' => '41~50岁',   
            'weixinad_enum' => '41~50'
        ],
        '6' => [
            'weixinad_title' => '51~65岁',   
            'weixinad_enum' => '51~65'
        ],
        '7' => [
            'weixinad_title' => '66~127岁',   
            'weixinad_enum' => '66~127'
        ],
    ],
    
    //优化目标类型
    'optimization_goal' => [
        '1' => [
            'weixinad_title' => '点击量',   
            'weixinad_enum' => 'OPTIMIZATIONGOAL_CLICK'
        ],
        '2' => [
            'weixinad_title' => '曝光',   
            'weixinad_enum' => 'OPTIMIZATIONGOAL_IMPRESSION'
        ],
        '3' => [
            'weixinad_title' => 'App 安装',   
            'weixinad_enum' => 'OPTIMIZATIONGOAL_APP_INSTALL'
        ],
        '4' => [
            'weixinad_title' => '移动 App 激活',   
            'weixinad_enum' => 'OPTIMIZATIONGOAL_APP_ACTIVATE'
        ],
        '5' => [
            'weixinad_title' => 'App 注册',   
            'weixinad_enum' => 'OPTIMIZATIONGOAL_APP_REGISTER'
        ],
        '6' => [
            'weixinad_title' => 'App 购买',   
            'weixinad_enum' => 'OPTIMIZATIONGOAL_APP_PURCHASE'
        ],
        '7' => [
            'weixinad_title' => '下单',   
            'weixinad_enum' => 'OPTIMIZATIONGOAL_ECOMMERCE_ORDER'
        ],
        '8' => [
            'weixinad_title' => 'H5 购买',   
            'weixinad_enum' => 'OPTIMIZATIONGOAL_ECOMMERCE_CHECKOUT'
        ],
        '9' => [
            'weixinad_title' => 'H5 注册',   
            'weixinad_enum' => 'OPTIMIZATIONGOAL_PROMOTION_CLICK_KEY_PAGE'
        ],
        '10' => [
            'weixinad_title' => '表单预约',   
            'weixinad_enum' => 'OPTIMIZATIONGOAL_PAGE_RESERVATION'
        ],
    ],
    //扣费/计费类型
    'billing_event' =>[
        //仅可以在以下优化目标时使用
        //（ optimization_goal = OPTIMIZATIONGOAL_CLICK, OPTIMIZATIONGOAL_APP_ACTIVATE, OPTIMIZATIONGOAL_APP_REGISTER, OPTIMIZATIONGOAL_PROMOTION_CLICK_KEY_PAGE, OPTIMIZATIONGOAL_ECOMMERCE_ORDER, OPTIMIZATIONGOAL_APP_PURCHASE, OPTIMIZATIONGOAL_ECOMMERCE_CHECKOUT, OPTIMIZATIONGOAL_PAGE_RESERVATION 时）
        '1' => [
            'weixinad_title' => '按点击扣费',   
            'weixinad_enum' => 'BILLINGEVENT_CLICK'
        ],
        '2' => [
            'weixinad_title' => '按照转化扣费',   
            'weixinad_enum' => 'BILLINGEVENT_APP_INSTALL'
        ],
        //优化目标为根据曝光量优化（ optimization_goal = OPTIMIZATIONGOAL_IMPRESSION 时）使用
        '3' => [
            'weixinad_title' => '按曝光扣费',   
            'weixinad_enum' => 'BILLINGEVENT_IMPRESSION'
        ],
    ],
    //创意规格-跟广告位对应（1:朋友圈, 2:公众账号顶部, 3:公众账号底部）
    'adcreative_template' => [
        '1' => [
        
            '1' => [
                'weixinad_title' => '朋友圈消息流-800×640单图(文)',   
                'weixinad_enum' => '263'
            ],
            '2' => [
                'weixinad_title' => '朋友圈消息流-800×800单图(文)',   
                'weixinad_enum' => '311'
            ],
            '3' => [
                'weixinad_title' => '朋友圈消息流-640×800单图(文)',   
                'weixinad_enum' => '310'
            ],
            /*'4' => [
                'weixinad_title' => '朋友圈消息流-640×480视频',
                'weixinad_enum' => '460'
            ],*/
        ],
        '2' => [
            '1' => [
                'weixinad_title' => '微信公众号顶部-900×162单图(文)',   
                'weixinad_enum' => '166'
            ],
        ],
        '3' => [
            '1' => [
                'weixinad_title' => '公众号、新闻插件底部-114×114单图(文)',   
                'weixinad_enum' => '134'
            ],
            '2' => [
                'weixinad_title' => '公众号文章底部广告-960x334单图(文)',   
                'weixinad_enum' => '567'
            ],
            /*
            '3' => [
                'weixinad_title' => '公众号、新闻插件底部-465×230单图(文)',   
                'weixinad_enum' => '117'
            ],
            '4' => [
                'weixinad_title' => '公众号、新闻插件底部-582×166单图(文)',   
                'weixinad_enum' => '133'
            ],*/
        ],
    ],
    
    //客户设置的状态开关
    'configured_status' => [
        '1' => [
            'weixinad_title' => '有效',   
            'weixinad_enum' => 'AD_STATUS_NORMAL'
        ],
        '2' => [
            'weixinad_title' => '暂停',   
            'weixinad_enum' => 'AD_STATUS_SUSPEND'
        ],
    ],
    
    
    //链接名称类型
    //VIEW_DETAILS(查看详情), GET_COUPONS(领取优惠), MAKE_AN_APPOINTMENT(预约活动), BUY_NOW(立即购买), GO_SHOPPING(去逛逛), ENTER_MINI_PROGRAM(进入小程序)
    'link_name_type' => [
        '1' => [
            'weixinad_title' => '查看详情',   
            'weixinad_enum' => 'VIEW_DETAILS'
        ],
        '2' => [
            'weixinad_title' => '领取优惠',   
            'weixinad_enum' => 'GET_COUPONS'
        ],
        '3' => [
            'weixinad_title' => '预约活动',   
            'weixinad_enum' => 'MAKE_AN_APPOINTMENT'
        ],
        '4' => [
            'weixinad_title' => '进入小程序',   
            'weixinad_enum' => 'ENTER_MINI_PROGRAM'
        ],/*
        '5' => [
            'weixinad_title' => '立即购买',   
            'weixinad_enum' => 'BUY_NOW'
        ],
        '6' => [
            'weixinad_title' => '去逛逛',   
            'weixinad_enum' => 'GO_SHOPPING'
        ],*/
    ],
];

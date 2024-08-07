<?php
/**
 * Created by PhpStorm.
 * User: andery
 * Date: 15/7/20
 * Time: 下午9:36
 */
$weixinConfigs = [
    'local' => [//本地开发
        'wh_host' => 'https://twhb-applet.dodoca.com',

        'component_id'        => '',
        'component_appid'     => '',
        'component_appsecret' => '',
        'component_token'     => '',
        'component_key'       => '',

        'template_id'      => '',
        'template_version' => '',
        'template_date'    => '',
        'template_index'   => 'pages/decorate/decorate',

        'test_merchant_id' => []
    ],
    'develop' => [ //点点客小程序(开发)
        'wh_host' => 'https://twhb-applet.dodoca.com',

        'component_id'        => 6,
        'component_appid'     => 'wx1f3450006331d07f',
        'component_appsecret' => 'e319e5fade1e789c99f7b1f5ad976026',
        'component_token'     => 'dodocaweixin20180730',
        'component_key'       => 'dodocaweixin20180730diandianke0020030040069',

        'template_id'      => '0',
        'template_version' => 'v0.01',
        'template_date'    => '0',
        'template_index'   => 'pages/decorate/decorate',

        'test_merchant_id' => []
    ],
    'develop-applet' => [ //点点客小程序(开发)
        'wh_host' => 'https://twhb-applet.dodoca.com',

        'component_id'        => 1,
        'component_appid'     => 'wxe18e729e65f598d4',
        'component_appsecret' => '37d56268fa1ea3d89fbc6e6e2e1f20ed',
        'component_token'     => 'dodocaapplet20170831',
        'component_key'       => 'dodocaapplet20170831diandianke0020030040050',

        'template_id'      => 38,
        'template_version' => 'v2.26',
        'template_date'    => 171171,
        'template_index'   => 'pages/decorate/decorate',

        'test_merchant_id' => []
    ],
    'develop-xcx' => [ //点点客小程序(开发)
        'wh_host' => 'https://twhb-applet.dodoca.com',

        'component_id'        => 5,
        'component_appid'     => 'wxff66a2c8573ecee7',
        'component_appsecret' => '72567edd68ce5f125ab0b4cba8b3d9de',
        'component_token'     => 'dodocaweixin20170810',
        'component_key'       => 'dodocaweixin2017711diandianke00200300400699',

        'template_id'      => 0,
        'template_version' => 'v1.2.6',
        'template_date'    => 171172,
        'template_index'   => 'pages/decorate/decorate',

        'test_merchant_id' => []
    ],
    'test' => [//点点客小程序（QA）
        'wh_host' => 'https://twhb-applet.dodoca.com',

        'component_id'        => 4,
        'component_appid'     => 'wx4ce1a4cc587f1013',
        'component_appsecret' => 'eaf8d2cc962748f8b768094dee028a39',
        'component_token'     => 'dodocaweixin20170711',
        'component_key'       => 'dodocaweixin2017711diandianke00200300400699',

        'template_id'      => 49,
        'template_version' => 'V7.0',
        'template_date'    => 171170,
        'template_index'   => 'pages/decorate/decorate',

        'test_merchant_id' => [2905]
    ],
    'release' => [ //点点客小程序（集成）
        'wh_host' => 'https://twhb-applet.dodoca.com',

        'component_id'        => 2,
        'component_appid'     => 'wx12b856438bbe3d8a',
        'component_appsecret' => 'ccefdf2967a99822cf721b13debaee41',
        'component_token'     => 'dodocaweixin20170711',
        'component_key'       => 'dodocaweixin2017711diandianke00200300400699',

        'template_id'      => 48,
        'template_version' => 'V1.3.4',
        'template_date'    => 171176,
        'template_index'   => 'pages/decorate/decorate',

        'test_merchant_id' => []
    ],
    'production' => [ //点点客小程序
        'wh_host' => 'https://whb-applet.dodoca.com',

        'component_id'        => 3,
        'component_appid'     => 'wxd099723df01a8b6f',
        'component_appsecret' => 'ecfdb49a1c45c712587e1c26ef7572f9',
        'component_token'     => 'dodocaapplet20170919',
        'component_key'       => 'dodocaapplet20170919diandianke0020030040050',

        'template_id'      => 82,
        'template_version' => 'V1.3.4',
        'template_date'    => 171182,
        'template_index'   => 'pages/decorate/decorate',

        'test_merchant_id' => [1,6,10,18,11,43505,66,62289,62291,62288]//60629
    ]
];
$weixinConfigs = $weixinConfigs[ env('APP_ENV')];

return [
    'app_env'   => env('APP_ENV'),
    //域名
    'base_host'  => env('APP_URL', 'https://applet.dodoca.com'),
    'static_host' => env('STATIC_DOMAIN', 'https://s.dodoca.com'),
    'phone_host' => env('PHONE_DOMAIN', 'https://m.dodoca.com'),
    'xiu_host' => env('XIU_DOMAIN', 'https://xiu.dodoca.com'),
    'qn_host'  => env('QINIU_STATIC_DOMAIN', 'https://xcx.wrcdn.com'),
    'map_host' => env('MAP_DOMAIN', 'https://apis.map.qq.com'),
    'socket_host' => env('SOCKET_DOMAIN', 'https://socket.dodoca.com:8987'),
    'host' => '',

    //公众账号
    'wh_host' =>  $weixinConfigs['wh_host'],
    'official_qrcode_notice'  => 'MGS_MERCHANT',//商家模板消息通知 带参数二维码

    //授权
    'wechat_auth_callcack'    => env('AUTH_CALLBACK_URL', '/manage/main/appletNew?'), //小程序授权回调
    'wechat_auth_callcacks'   => env('AUTH_CALLBACK_URL', '/manage/main/whb/whb_rebind'),//公众账号授权回调
    'wechat_mch_server'   => env('RRD_API_DOMAIN', ''),
    'wechat_open_gateway' => env('WECHAT_OPEN_GATEWAY', 'https://api.weixin.qq.com'),
    'wechat_auth_count'   => 9,
    'wechat_auths_count'  => 21,

    //第三方账号
    'component_id'        => $weixinConfigs['component_id'],
    'component_appid'     => $weixinConfigs['component_appid'],
    'component_appsecret' => $weixinConfigs['component_appsecret'],
    'component_token'     => $weixinConfigs['component_token'],
    'component_key'       => $weixinConfigs['component_key'],

    'test_merchant_id'    =>  $weixinConfigs['test_merchant_id'],

    //小程序代码版本 新版本已废弃
    'template_index'   => $weixinConfigs['template_index'],//在使用
    'template_id'      => $weixinConfigs['template_id'],
    'template_version' => $weixinConfigs['template_version'],
    'template_date'    => $weixinConfigs['template_date'],
    'template_is_host' => env('TEMPLATE_IS_HOST', 0),
    'template_desc'    => env('TEMPLATE_DESC', '商品销售'),

    'template_type' => [
        'V',//商城模板
        'L',//直播
        'S',//门店
        'W',//网站
        'Z',//抓娃娃
        'T',//投票
    ],
    'template_type_map' => [
        'V' => 'V',//商城模板
        'L' => 'V',//直播
        'S' => 'S',//门店
        'W' => 'V',//网站
        'Z' => 'Z',//抓娃娃
        'T' => 'T',//投票
    ],

    //请求代理
    'proxy'      => 'tcp://'.env('PROXY_IP', '172.17.0.14').':'.env('PROXY_PORT', '11399'),
    'proxy_ip'   => env('PROXY_IP', '172.17.0.14'),
    'proxy_port' => env('PROXY_PORT', '11399'),
    'proxy_is'   => env('PROXY_IS', 1),
];

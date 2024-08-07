<?php
/**
 * Created by PhpStorm.
 * User: wangshiliang@dodoca.com
 * Date: 2018/7/24
 * Time: 16:04
 */

$configs = [
    'local' => [//本地开发
        'app_key' => 'dadae7c04e70e5cf929',
        'app_secret' => '50de58ae23abd3303976fbd5f1273f87',
        'env'  => 'local',
        'test_merchant_id' => []
    ],
    'develop' => [ //点点客小程序(开发)
        'app_key' => 'dadae7c04e70e5cf929',
        'app_secret' => '50de58ae23abd3303976fbd5f1273f87',
        'env'  => 'develop',
        'test_merchant_id' => []
    ],
    'develop-applet' => [ //点点客小程序(开发)
        'app_key' => 'dadae7c04e70e5cf929',
        'app_secret' => '50de58ae23abd3303976fbd5f1273f87',
        'env'  => 'develop-applet',
        'test_merchant_id' => []
    ],
    'develop-xcx' => [ //点点客小程序(开发)
        'app_key' => 'dadae7c04e70e5cf929',
        'app_secret' => '50de58ae23abd3303976fbd5f1273f87',
        'env'  => 'develop-xcx',
        'test_merchant_id' => []
    ],
    'test' => [//点点客小程序（QA）
        'app_key' => 'dadae7c04e70e5cf929',
        'app_secret' => '50de58ae23abd3303976fbd5f1273f87',
        'env'  => 'test',
        'test_merchant_id' => []
    ],
    'release' => [ //点点客小程序（集成）
        'app_key' => 'dadae7c04e70e5cf929',
        'app_secret' => '50de58ae23abd3303976fbd5f1273f87',
        'env'  => 'release',
        'test_merchant_id' => []
    ],
    'production' => [ //点点客小程序
        'app_key' => 'dadae7c04e70e5cf929',
        'app_secret' => '50de58ae23abd3303976fbd5f1273f87',
        'env'  => 'production',
        'test_merchant_id' => [1,6,10,18,11,43505,66,62289,62291,62288]
    ]
];
$configs = $configs[ env('APP_ENV')];
$configs['type_list'] = [
    [ 'id' => 1, 'name' => '食品小吃' ],
    [ 'id' => 2, 'name' => '饮料' ],
    [ 'id' => 3, 'name' => '鲜花' ],
    [ 'id' => 8, 'name' => '文印票务' ],
    [ 'id' => 9, 'name' => '便利店' ],
    [ 'id' => 13, 'name' => '水果生鲜' ],
    [ 'id' => 19, 'name' => '同城电商' ],
    [ 'id' => 20, 'name' => '医药' ],
    [ 'id' => 21, 'name' => '蛋糕' ],
    [ 'id' => 24, 'name' => '酒品' ],
    [ 'id' => 25, 'name' => '小商品市场' ],
    [ 'id' => 26, 'name' => '服装' ],
    [ 'id' => 27, 'name' => '汽修零配' ],
    [ 'id' => 28, 'name' => '数码' ],
    [ 'id' => 29, 'name' => '小龙虾' ],
    [ 'id' => 5, 'name' => '其他' ]
];
$configs['merchant'] =[
    0 => '关闭',
    1 => '正常'
];
$configs['shop'] =[
    0 => '关闭',
    1 => '正常'
];
$configs['cancel'] = [
    0 => '默认',
    1 => '达达配送员取消',
    2 => '商家主动取消',
    3 => '系统或客服取消',
];
$configs['order'] = [
    -1 => '已下单',
    0 => '待发货',
    1 => '待接单',
    2 => '待取货',
    3 => '配送中',
    4 => '已完成',
    5 => '已取消',
    7 => '已过期',
    8 => '指派单',
    9 => '妥投异常之物品返回中',
    10 => '妥投异常之物品返回完成',
    15 => '已取消 不可以重发',
];
return $configs;



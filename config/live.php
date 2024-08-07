<?php
/**
 * Created by PhpStorm.
 * User: wangshiliang@dodoca.com
 * Date: 2018/5/16
 * Time: 18:39
 */

$liveConfigs = [
    'local' => [//本地开发
        "uid"  => 0 ,
        "user" => '',
        "pwd"  => ''
    ],
    'develop' => [ //点点客小程序(开发)
        "uid"  => 1448 ,
        "user" => '15021417596',
        "pwd"  => 'ddk12345'
    ],
    'develop-applet' => [ //点点客小程序(开发)
        "uid"  => 1448 ,
        "user" => '15021417596',
        "pwd"  => 'ddk12345'
    ],
    'develop-xcx' => [ //点点客小程序(开发)
        "uid"  => 1448 ,
        "user" => '15021417596',
        "pwd"  => 'ddk12345'
    ],
    'test' => [//点点客小程序（QA）
        "uid"  => 1448 ,
        "user" => '15021417596',
        "pwd"  => 'ddk12345'
    ],
    'release' => [ //点点客小程序（集成）
        "uid"  => 1448 ,
        "user" => '15021417596',
        "pwd"  => 'ddk12345'
    ],
    'production' => [ //点点客小程序
        "uid"  => 1314 ,
        "user" => '13524664140',
        "pwd"  => 'Dodocaweixin2018'
    ]
];

$liveConfigs = $liveConfigs[ env('APP_ENV')];

return [
    "advance" => 600, //开始直播提前时间 10分钟
    "del_vod" => 432000 , //录像上传时间 五天后
    "claritys"=> [ 1 => 0, 2 => 0, 3 => 0 , 4 => 0 , 5 => 0 ],
    "uid"  => $liveConfigs['uid'] ,
    "user" => $liveConfigs['user'],
    "pwd"  => $liveConfigs['pwd']
];

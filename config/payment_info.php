<?php

/**
 *  payment info
 *  
 *  User: 狂奔的蚂蚁 <www.firstphp.com>
 */

return [
    'allinpay' => [
        'code' => 'allinpay',
        'name' => '通联支付',
        'desc' => '让资金更流畅，让支付更便捷',
        'online' => '1',
        'website' => 'http://www.allinpay.com/',
        'support_sys' => 0,
        'params' => [
            'merchantId' => [
                'text' => '商户号',
                'type' => 'text',
            ],
            'key' => [
                'text' => '秘钥',
                'desc' => 'key为MD5密钥，密钥是在通联支付网关商户服务网站上设置',
                'type' => 'text',
            ]
        ]
    ],
    'cod' => [
        'code' => 'cod',
        'name' => '货到付款',
        'desc' => '',
        'online' => '0',
        'website' => 'http://www.weiba66.com',
        'support_sys' => 0,
    ],
    'llpay' => [
        'code' => 'llpay',
        'name' => '银行卡快捷支付',
        'desc' => '银行卡快捷支付',
        'online' => 1,
        'support_sys' => 1,
        'params' => [
            'virtual' => [ //虚拟 101001
                'oid_partner' => [
                    'text' => '商户编号',
                    'desc' => '商户编号是商户在连连钱包支付平台上开设的商户号码，为18位数字，如：201306081000001016',
                    'type' => 'text',
                ],
                'key' => [
                    'text' => '安全检验码',
                    'desc' => '以数字和字母组成的字符',
                    'type' => 'text',
                ]
            ],
            'physical' => [
                'oid_partner' => [
                    'text' => '商户编号',
                    'desc' => '商户编号是商户在连连钱包支付平台上开设的商户号码，为18位数字，如：201306081000001016',
                    'type' => 'text',
                ],
                'key' => [
                    'text' => '安全检验码',
                    'desc' => '以数字和字母组成的字符',
                    'type' => 'text',
                ]
            ]
        ],
        'risk' => [
            'virtual' => [
                '1001' => '虚拟卡销售',
                '1002' => '虚拟账户充值',
                '1003' => '数字娱乐',
                '1004' => '网络虚拟服务',
                '1005' => '网络推广销售',
                '1006' => '娱乐票务',
                '1007' => '博彩类',
                '1008' => '中介\咨询服务',
                '1009' => '生活服务',
                '1010' => '个人话费充值',
                '1999' => '其他'
            ],
            'physical' => [
                '4001' => '家居百货',
                '4002' => '书籍/音像/文具',
                '4003' => '五金器材',
                '4004' => '数码家电',
                '4005' => '礼品、保健品',
                '4006' => '药品',
                '4007' => '收藏、工艺品',
                '4008' => '农产品',
                '4100' => '外贸出口类',
                '4999' => '其他'
            ]
        ]
    ],
    'paypal' => [
        'code' => 'paypal',
        'name' => 'Paypal',
        'desc' => 'Paypal 支付方式',
        'online' => '1',
        'website' => '',
        'support_sys' => 0,
        'params' => [
            'paypal_account' => [
                'text' => 'paypal 账号',
                'type' => 'text',
            ]
        ],
    ],
    'tenpay' => [
        'code' => 'tenpay',
        'name' => '财付通',
        'desc' => '财付通',
        'online' => '1',
        'website' => 'http://www.tenpay.com',
        'support_sys' => 0,
        'params' => [
            'partner_id' => [  //账号
                'text' => '商户号(PartnerID)',
                'desc' => '商户号(PartnerID)',
                'type' => 'text',
            ],
            'partner_key' => [ //密钥
                'text' => '密钥(PartnerKey)',
                'desc' => '密钥(PartnerKey)',
                'type' => 'text',
            ]
        ]
    ],
    'yeepay' => [
        'code' => 'yeepay',
        'name' => '易宝支付',
        'desc' => '易宝支付',
        'online' => '1',
        'website' => 'http://www.yeepay.com',
        'support_sys' => 0,
        'params' => [
            'product_catalog' => [ //商品类别码
                'text' => '商品类别码',
                'type' => 'text',
            ],
            'merchant_account' => [ //商户编号
                'text' => '商户编号',
                'type' => 'text',
            ],
            'merchant_private_key' => [ //商户私钥
                'text' => '商户私钥',
                'type' => 'text',
            ],
            'merchant_public_key' => [  //商户公钥
                'text' => '商户公钥',
                'type' => 'text',
            ],
            'yeepay_public_key' => [    //易宝公钥
                'text' => '易宝公钥',
                'type' => 'text',
            ],
        ]
    ],
    'alipay' => [
        'code' => 'alipay',
        'name' => '支付宝',
        'desc' => '支付宝网站(www.alipay.com) 是国内先进的网上支付平台',
        'online' => '1',
        'website' => 'http://www.alipay.com',
        'support_sys' => 1,
        'params' => [
            'is_global' => [
                'text' => '类型',
                'type' => 'radio',
            ],
            'alipay_account' => [  //账号
                'text' => '支付宝账号',
                'desc' => '输入您在支付宝的账号',
                'type' => 'text',
            ],
            'alipay_partner' => [  //合作者身份ID
                'text' => '合作者身份ID',
                'type' => 'text',
            ],
            'alipay_key' => [  //密钥
                'text' => '交易安全校验码',
                'desc' => '免费签约支付宝，获取校验码和ID',
                'type' => 'text',
            ]
        ],
        'pems' => [
            'cacert' => [   //账号
                'text' => 'cacert',
                'type' => 'text',
            ]
        ]
    ],
    'wxpay' => [
        'code' => 'wxpay',
        'name' => '微信支付(旧)',
        'desc' => '微信支付',
        'online' => '1',
        'website' => '',
        'support_sys' => 0,
        'help_label' => '商户功能设置',
        'help' => '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;支付请求类型&nbsp;&nbsp;&nbsp;&nbsp;JS API支付<br/>' .
        'JS API支付授权目录&nbsp;&nbsp;&nbsp;&nbsp;http://cashier.bama555.com/wxpay/<br/>' .
        'JS API支付授权目录&nbsp;&nbsp;&nbsp;&nbsp;http://cashier.bama555.com/wxpay/pay/<br/>' .
        'JS API支付请求实例&nbsp;&nbsp;&nbsp;&nbsp;http://cashier.bama555.com/wxpay/pay<br/>' .
        '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;共享收货地址&nbsp;&nbsp;&nbsp;&nbsp;是<br/>' .
        '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;维权通知URL&nbsp;&nbsp;&nbsp;&nbsp;http://cashier.bama555.com/wxpay/feedback<br/>' .
        '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;告警通知URL&nbsp;&nbsp;&nbsp;&nbsp;http://cashier.bama555.com/wxpay/warning<br/>',
        'help_label_dodoca' => '商户功能设置',
        'help_dodoca' => '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;支付请求类型&nbsp;&nbsp;&nbsp;&nbsp;JS API支付<br/>' .
        'JS API支付授权目录&nbsp;&nbsp;&nbsp;&nbsp;http://cashier.dj.dodoca.com/wxpay/<br/>' .
        'JS API支付授权目录&nbsp;&nbsp;&nbsp;&nbsp;http://cashier.dj.dodoca.com/wxpay/pay/<br/>' .
        'JS API支付请求实例&nbsp;&nbsp;&nbsp;&nbsp;http://cashier.dj.dodoca.com/wxpay/pay<br/>' .
        '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;共享收货地址&nbsp;&nbsp;&nbsp;&nbsp;是<br/>' .
        '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;维权通知URL&nbsp;&nbsp;&nbsp;&nbsp;http://cashier.dj.dodoca.com/wxpay/feedback<br/>' .
        '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;告警通知URL&nbsp;&nbsp;&nbsp;&nbsp;http://cashier.dj.dodoca.com/wxpay/warning<br/>',
        'params' => [
            'app_id' => [
                'text' => 'AppID',
                'type' => 'text',
            ],
            'app_secret' => [
                'text' => 'AppSecret',
                'type' => 'text',
            ],
            'pay_sign_key' => [
                'text' => 'PaySignKey',
                'type' => 'text',
            ],
            'partner_id' => [
                'text' => '商户号(PartnerID)',
                'type' => 'text',
            ],
            'partner_key' => [
                'text' => '密钥(PartnerKey)',
                'type' => 'text'
            ]
        ],
    ],
    'wxpay3' => [    
        'code' => 'wxpay3',
        'name' => '微信支付',
        'desc' => '新版微信支付，建议使用此支付方式',
        'online' => '1',
        'website' => '',
        'support_sys' => 0,
        'help_label' => '商户功能设置',
        'help' => '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;支付请求类型&nbsp;&nbsp;&nbsp;&nbsp;JS API支付<br/>' .
        'JS API支付授权目录&nbsp;&nbsp;&nbsp;&nbsp;http://cashier.bama555.com/wxpay/<br/>' .
        'JS API支付授权目录&nbsp;&nbsp;&nbsp;&nbsp;http://cashier.bama555.com/wxpay/pay/<br/>' .
        'JS API支付请求实例&nbsp;&nbsp;&nbsp;&nbsp;http://cashier.bama555.com/wxpay/pay<br/>' .
        '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;共享收货地址&nbsp;&nbsp;&nbsp;&nbsp;是<br/>' .
        '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;维权通知URL&nbsp;&nbsp;&nbsp;&nbsp;http://cashier.bama555.com/wxpay/feedback<br/>' .
        '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;告警通知URL&nbsp;&nbsp;&nbsp;&nbsp;http://cashier.bama555.com/wxpay/warning<br/>',
        'help_label_dodoca' => '商户功能设置',
        'help_dodoca' => '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;支付请求类型&nbsp;&nbsp;&nbsp;&nbsp;JS API支付<br/>' .
        'JS API支付授权目录&nbsp;&nbsp;&nbsp;&nbsp;http://cashier.dj.dodoca.com/wxpay/<br/>' .
        'JS API支付授权目录&nbsp;&nbsp;&nbsp;&nbsp;http://cashier.dj.dodoca.com/wxpay/pay/<br/>' .
        'JS API支付请求实例&nbsp;&nbsp;&nbsp;&nbsp;http://cashier.dj.dodoca.com/wxpay/pay<br/>' .
        '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;共享收货地址&nbsp;&nbsp;&nbsp;&nbsp;是<br/>' .
        '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;维权通知URL&nbsp;&nbsp;&nbsp;&nbsp;http://cashier.dj.dodoca.com/wxpay/feedback<br/>' .
        '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;告警通知URL&nbsp;&nbsp;&nbsp;&nbsp;http://cashier.dj.dodoca.com/wxpay/warning<br/>',
        'params' => [
            'app_id' => [
                'text' => 'AppID',
                'type' => 'text',
            ],
//            'app_secret' => [         # 暂时删掉 2015-10-29 备
//                'text' => 'AppSecret',
//                'type' => 'text',
//            ],
            'mchid' => [
                'text' => 'MChid(商户ID)',
                'type' => 'text',
                'required' => true
            ],
            'key' => [
                'text' => '商户支付秘钥(Key)',
                'type' => 'text',
                'required' => true
            ],
            'ordinary_mchid' => [
                'text' => 'MChid(商户ID)',
                'type' => 'text',
                'required' => false
            ],
            'ordinary_key' => [
                'text' => '商户支付秘钥(Key)',
                'type' => 'text',
                'required' => false
            ],            
        ],
        'pems' => [
            'apiclient_cert' => [
                'text' => 'apiclient_cert',
                'type' => 'text'
            ],
            'apiclient_key' => [
                'text' => 'apiclient_key',
                'type' => 'text'
            ],
            'rootca' => [
                'text' => 'rootca',
                'type' => 'text'
            ]
        ]
    ]
];

<?php
/**
 * create_time 2015-08-21
 * 
 * @author chenhao
 */
return [
    !defined('app_path') && define('app_path',dirname(dirname(__FILE__))),
    'wxpay' => ['apiclient_cert.pem','apiclient_key.pem','apiclient_cert.p12'],
    'alipay' => ['cacert.pem','apiclient_cert.pem','apiclient_key.pem','rootca.pem'],
];

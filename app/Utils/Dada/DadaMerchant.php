<?php
/**
 * Created by PhpStorm.
 * User: wangshiliang@dodoca.com
 * Date: 2018/7/24
 * Time: 13:29
 */

namespace App\Utils\Dada;

use App\Utils\Weixin\Http;

class DadaMerchant extends Dada
{

    public function __construct()
    {
    }

    /**
     * @name 创就商户
     * @param $mobile  联系人电话
     * @param $city 商户城市名称(如,上海)
     * @param $enterprise 企业全称
     * @param $address 企业地址
     * @param $contact 联系人姓名
     * @param $email 邮箱地址
     * @return array ["code"=>0 ,"msg"=>""  "result"=> "商户号" ]
     */
    public function add($mobile, $city, $enterprise, $address, $contact, $email){
        return $this->mxCurl('/merchantApi/merchant/add',[
            'mobile'             => $mobile,
            'city_name'          => $city,
            'enterprise_name'    => $enterprise,
            'enterprise_address' => $address,
            'contact_name'       => $contact,
            'contact_phone'      => $mobile,
            'email'              => $email
        ]);
    }


}
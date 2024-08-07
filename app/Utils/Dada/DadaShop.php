<?php
/**
 * Created by PhpStorm.
 * User: wangshiliang@dodoca.com
 * Date: 2018/7/31
 * Time: 14:52
 */

namespace App\Utils\Dada;


class DadaShop extends Dada
{

    /**
     * @name 新增门店
     * @param $name  门店名称
     * @param $business 业务类型(食品小吃-1,饮料-2,鲜花-3,文印票务-8,便利店-9,水果生鲜-13,同城电商-19, 医药-20,蛋糕-21,酒品-24,小商品市场-25,服装-26,汽修零配-27,数码-28,小龙虾-29, 其他-5)
     * @param $city_name 城市名称(如,上海)
     * @param $area 区域名称(如,浦东新区)
     * @param $address 门店地址
     * @param $lng  门店经度
     * @param $lat  门店纬度
     * @param $contact 联系人姓名
     * @param $phone 联系人电话
     * @param $shop 门店编码,可自定义,但必须唯一
     * @param $username 达达商家app账号
     * @param $password  达达商家app密码
     * @param $idCard = '' 联系人身份证
     * @return array ["code"=>0 ,"msg"=>""  "result"=> [
     *             "success"=>2,
     *             "successList"=>[{
     *                 "phone":"",
     *                 "business": 1,
     *                 "lng": 0.000,
     *                 "lat": 0.00,
     *                 "stationName":"",
     *                 "originShopId":"s01",
     *                 "contactName": "xxx",
     *                 "stationAddress": "",
     *                 "cityName": "上海",
     *                 "areaName": "浦东新区"
     *              }],
     *              "failedList" => []
     * ] ]
     */
    public function addShop($name, $business, $city_name, $area, $address, $lng, $lat, $contact, $phone, $shop, $username = '', $password = '', $idCard = ''){
        return $this->mxCurl('/api/shop/add',[[
            'station_name'       => $name,
            'business'           => $business,
            'city_name'          => $city_name,
            'area_name'          => $area,
            'station_address'    => $address,
            'lng'                => $lng,
            'lat'                => $lat,
            'contact_name'       => $contact,
            'phone'              => $phone,
            'origin_shop_id'     => $shop,
            'id_card'            => $idCard,
            'username'           => $username,
            'password'           => $password
        ]]);
    }

    /**
     * @name 编辑门店
     * @param $shop  门店编码
     * @param $data['name']  门店名称
     * @param $data['business'] 业务类型(食品小吃-1,饮料-2,鲜花-3,文印票务-8,便利店-9,水果生鲜-13,同城电商-19, 医药-20,蛋糕-21,酒品-24,小商品市场-25,服装-26,汽修零配-27,数码-28,小龙虾-29, 其他-5)
     * @param $data['city_name'] 城市名称(如,上海)
     * @param $data['area'] 区域名称(如,浦东新区)
     * @param $data['address'] 门店地址
     * @param $data['lng']  门店经度
     * @param $data['lat']  门店纬度
     * @param $data['contact'] 联系人姓名
     * @param $data['phone'] 联系人电话
     * @param $data['shop'] 门店编码,可自定义,但必须唯一
     * @param $data['status'] 	门店状态（1-门店激活，0-门店下线）
     * @return array ["code"=>0 ,"msg"=>""  ]
     */
    public function updateShop($shop, $data){
        $datas = ['origin_shop_id'=> $shop ];
        if(isset($data['name'])) $datas['station_name'] = $data['name'];
        if(isset($data['business'])) $datas['business'] = $data['business'];
        if(isset($data['city_name'])) $datas['city_name'] = $data['city_name'];
        if(isset($data['area'])) $datas['area_name'] = $data['area'];
        if(isset($data['address'])) $datas['station_address'] = $data['address'];
        if(isset($data['lng'])) $datas['lng'] = $data['lng'];
        if(isset($data['lat'])) $datas['lat'] = $data['lat'];
        if(isset($data['contact'])) $datas['contact_name'] = $data['contact'];
        if(isset($data['phone'])) $datas['phone'] = $data['phone'];
        if(isset($data['shop'])) $datas['new_shop_id'] = $data['shop'];
        if(isset($data['status'])) $datas['status'] = $data['status'];
        return $this->mxCurl('/api/shop/update',$datas);
    }

    /**
     * @name 门店详情
     * @param $shop  门店id
     * @return array ["code"=>0 ,"msg"=>"" ,"result"=>[
     *              "origin_shop_id"=>"shop",
     *              "station_name"=>"name",
     *              "business"=>"type",
     *              "city_name"=>"city",
     *              "area_name"=>"area",
     *              "station_address"=>"address",
     *              "lng"=>"lng",
     *              "lat"=>"lat",
     *              "contact_name" => "contact",
     *              "phone"=>"phone",
     *              "id_card"=>"idCard",
     *              "status"=>""
     * ] ]
     */
    public function getShop($shop){
        return $this->mxCurl('/api/shop/update',['origin_shop_id'=>$shop]);
    }
}
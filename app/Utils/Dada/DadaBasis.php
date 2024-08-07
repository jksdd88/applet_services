<?php
/**
 * Created by PhpStorm.
 * User: wangshiliang@dodoca.com
 * Date: 2018/7/31
 * Time: 15:37
 */

namespace App\Utils\Dada;


class DadaBasis extends Dada
{

    /**
     * @name 获取城市信息列表
     * @return array ["code"=>0 ,"msg"=>""  "result"=> ["cityName"=>"","cityCode"=>0] ]
     */
    public function cityList(){
        return $this->mxCurl('/api/cityCode/list',[ ]);
    }

    /**
     * @name 取消订单原因列表
     * @return array ["code"=>0 ,"msg"=>""  "result"=> ["id"=>"","reason"=>""] ]
     */
    public function reasonsList(){
        return $this->mxCurl('/api/order/cancel/reasons',[ ]);
    }

    /**
     * @name 投诉达达原因列表
     * @return array ["code"=>0 ,"msg"=>""  "result"=> ["id"=>"","reason"=>""] ]
     */
    public function complaintList(){
        return $this->mxCurl('/api/complaint/reasons',[ ]);
    }

    /**
     * @name 获取可追加骑手列表
     * @name $shop 达达门店编号
     * @return array ["code"=>0 ,"msg"=>""  "result"=> ["id"=>"","name"=>"", "city_id"=> ""] ]
     */
    public function transporterList($shop){
        return $this->mxCurl('/api/order/appoint/list/transporter',[ 'shop_no' => $shop ]);
    }

}
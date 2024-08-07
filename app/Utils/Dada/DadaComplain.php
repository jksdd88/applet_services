<?php
/**
 * Created by PhpStorm.
 * User: wangshiliang@dodoca.com
 * Date: 2018/7/31
 * Time: 14:49
 */

namespace App\Utils\Dada;


class DadaComplain extends Dada
{
    /**
     * @name 投诉
     * @param $order  第三方订单编号
     * @param $reason 投诉原因ID
     * @return array ["code"=>0 ,"msg"=>""  "result"=> "商户号" ]
     */
    public function put($order, $reason){
        return $this->mxCurl('/api/complaint/dada',[
            'order_id'           => $order,
            'reason_id'          => $reason
        ]);
    }

}
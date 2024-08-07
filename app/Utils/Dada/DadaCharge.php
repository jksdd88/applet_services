<?php
/**
 * Created by PhpStorm.
 * User: wangshiliang@dodoca.com
 * Date: 2018/7/31
 * Time: 14:51
 */

namespace App\Utils\Dada;


class DadaCharge extends Dada
{
    /**
     * @name 生成支付链接
     * @param $amount  充值金额（单位元，可以精确到分）
     * @param $category 生成链接适应场景（category有二种类型值：PC、H5）
     * @param $notify_url 支付成功后跳转的页面（支付宝在支付成功后可以跳转到某个指定的页面，微信支付不支持）
     * @return array ["code"=>0 ,"msg"=>""  "result"=> "url" ]
     */
    public function getPayUrl($amount, $category, $notify_url){
        return $this->mxCurl('/api/recharge',[
            'amount'           => $amount,
            'category'         => $category,
            'notify_url'       => $notify_url
        ]);
    }

    /**
     * @name 查询账户余额
     * @param $category 查询运费账户类型（1：运费账户；2：红包账户，3：所有），默认查询运费账户余额
     * @return array ["code"=>0 ,"msg"=>""  "result"=> ['deliverBalance','redPacketBalance'] ]
     */
    public function get($category){
        return $this->mxCurl('/api/balance/query',[ 'category'   => $category   ]);
    }

}
<?php
/**
 * 运费
 * @author 王禹
 * @package  App\Services;
 */
namespace App\Services;

use App\Models\GoodsRegionExclude;

class ShipmentService
{

    /**
     * @param $goods_list 商品列表
     * Array
    (
        [0] => Array
        (
            [id] => 1  //商品id
            [title] => 商品一
            [shipment_id] => 0 //运费模板id 未使用传0
            [postage] => 2.5 //单商品本身运费
            [quantity] => 10 //购买数量
        )

        [1] => Array
        (
            [id] => 2 //商品id
            [title] => 商品二
            [shipment_id] => 3 //运费模板id
            [shipment_data] => Array //运费模板数据
                (
                [valuation_type] => 2
                [start_standard] => 1
                [start_fee] => 10
                [add_standard] => 2
                [add_fee] => 1
                )
            [postage] => 2.5 //商品本身运费
            [quantity] => 100 //购买数量
            [weight] => 1 //商品重量KG
            [volume] => 1 //体积m³
        )
    )
     * @param $province 收货地址省份id
     * @param $city 收货地址城市id
     * @return array|int|string
     */
    static function getOrderShipmentFee($goods_list ,$province,$city){

        $shipment_fee = 0;  //运费金额
        $shipment_list = array();

        foreach($goods_list as $goods_data){


            if(empty($goods_data['id']) || empty($goods_data['quantity']))
            {
                return array('status' => false, 'error' => '部分商品不在配送范围');//数据异常
            }

            //判断该商品是否在配送范围
            if($regionExclude = GoodsRegionExclude::get_data_by_goodsid($goods_data['id']))
            {
                $region_ids = explode(',',$regionExclude['region_ids']);
                if(in_array($province,$region_ids) || in_array($city,$region_ids))
                {
                    return array('status' => false, 'error' => '商品“'.$goods_data['title'].'”不在配送范围');
                }
            }

            //计算商品统一运费
            if(isset($goods_data['shipment_id']) && $goods_data['shipment_id'] === 0)
            {
                $shipment_fee += number_format($goods_data['postage'] * $goods_data['quantity'],2,'.','');
                continue;
            }
            elseif(!empty($goods_data['shipment_id']) && is_int($goods_data['shipment_id']) && isset($goods_data['shipment_data']['valuation_type']))
            {
                //商品使用运费模板
                isset($shipment_list[$goods_data['shipment_id']]) ?:$shipment_list[$goods_data['shipment_id']] = $goods_data['shipment_data'];

                //计算相同运费模板 商品总件数 商品整合计算
                $quantity = &$shipment_list[$goods_data['shipment_id']]['quantity'];
                empty($goods_data['quantity']) ? : ( $quantity = empty($quantity) ? $goods_data['quantity'] : $quantity + $goods_data['quantity'] );
                unset($quantity);

                //计算总重量 商品整合计算
                $weight = &$shipment_list[$goods_data['shipment_id']]['weight'];
                $weight_sum = empty($goods_data['weight']) ? 0 : number_format(($goods_data['weight'] * $goods_data['quantity']), 3, '.', '');
                empty($goods_data['weight']) ? : ( $weight = empty($weight) ? $weight_sum : $weight + $weight_sum );
                unset($weight);

                //计算总体积 商品整合计算
                $volume =  &$shipment_list[$goods_data['shipment_id']]['volume'];
                $volume_sum = empty($goods_data['volume']) ? 0 : number_format(($goods_data['volume'] * $goods_data['quantity']), 3, '.', '');
                empty($goods_data['volume']) ? : ( $volume = empty($volume) ? $volume_sum : $volume + $volume_sum );
                unset($volume);

//                $shipment_goods[$goods_data['shipment_id']]['quantity'] = empty($shipment_goods[$goods_data['shipment_id']]['quantity']) ? $goods_data['quantity'] : $shipment_goods[$goods_data['shipment_id']]['quantity'] + $goods_data['quantity'];
            }
            else
            {
                return array('status' => false, 'error' => '部分商品不在配送范围！');//数据异常
            }
        }

        foreach($shipment_list as $shipment_data)
        {
            //必须设定增费标准才计算
            if(!empty($shipment_data['add_standard']) && !empty($shipment_data['add_fee'])){

                $quantity = 0;      //总商品量
                $add_amount = 0;    //商品增量费用

                switch($shipment_data['valuation_type'])
                {
                    case 1: //重量
                        $quantity = empty($shipment_data['weight']) ? 0 : $shipment_data['weight'] * 1000;
                        break;

                    case 2: //件数
                        $quantity = empty($shipment_data['quantity']) ? 0 : $shipment_data['quantity'];
                        break;

                    case 3: //体积
                        $quantity = empty($shipment_data['volume']) ? 0 : $shipment_data['volume'];
                        break;
                }

                if(!empty($shipment_data['start_standard']) && $quantity > $shipment_data['start_standard'])
                {
                    $start_standard = ceil( ( $quantity - $shipment_data['start_standard'] ) / $shipment_data['add_standard']);
                    $add_amount =  number_format($start_standard * $shipment_data['add_fee'], 2, '.', '');
                    unset($start_standard);
                }

            }

            if(isset($shipment_data['start_fee']))
            {
                $shipment_fee += empty($add_amount) ?  $shipment_data['start_fee'] : $shipment_data['start_fee'] + $add_amount;
                unset($add_amount);
            }else
            {
                return array('status' => false, 'error' => '部分商品不在配送范围！！');//数据异常
            }

        }

        return array('status' => true, 'shipment_fee' => $shipment_fee);
    }

}

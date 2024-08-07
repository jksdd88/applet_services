<?php 
/**
 * Created by PhpStorm.
 * User: yanghui
 * Date: 2017-09-31
 * Time: 15:20
 */
namespace App\Services;

use App\Facades\Member;
use App\Facades\Suppliers;

use App\Jobs\AppOrderNotice;
use App\Models\Delivery;
use App\Models\OrderInfo;
use App\Models\OrderGoods;
use App\Models\OrderGoodsExtend;
use App\Models\OrderGoodsUmp;
use App\Models\OrderPackage;
use App\Models\OrderRefund;
use App\Models\OrderRefundLog;
use App\Models\OrderStat;
use App\Models\OrderUmp;
use App\Models\SupplierOrderGoodsRe;
use App\Models\TuanInitiateJoin;
use Carbon\Carbon;
use App\Models\MerchantSetting;
use App\Models\MerchantMember;
use GuzzleHttp\Client;
use App\Models\SupplierOrderRe;
use App\Models\SupplierOrderRefundRe;
use App\Models\Product;
use App\Models\Goods;
use App\Models\SupplierGoods;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Request;
use App\Models\ReturnCashOrder;

//use App\Models\SupplierOrderRe;
use App\Models\OrderRefundApply;

class RefundService extends BaseService
{
    use DispatchesJobs;

    public function __construct(ShipmentService $shipmentService, ServiceAccountRechargeService $serviceAccountRechargeService, PiaoService $piaoService)
    {
		//$this->member_id = Member::id();
		$this->member_id = 6;
		//$this->merchant_id = Member::merchant_id();
		$this->merchant_id = 1;
        $this->shipmentService = $shipmentService;
        $this->serviceAccountRechargeService = $serviceAccountRechargeService;//wuxin+
        $this->piaoService = $piaoService;
    }
	
	//提交退款申请
    public function getRefund($params) {
		$order_id = isset($params['order_id']) ? $params['order_id'] : 0;
		$order_refund_id = isset($params['order_refund_id']) ? $params['order_refund_id'] : 0;
		$amount = isset($params['amount']) ? $params['amount'] : 0;
		$order_goods_id = isset($params['order_goods_id']) ? $params['order_goods_id'] : 0;
		$package_id = isset($params['package_id']) ? $params['package_id'] : 0;
		$refund_quantity = isset($params['refund_quantity']) ? $params['refund_quantity'] : 0;
		$shipment_fee = isset($params['shipment_fee']) ? $params['shipment_fee'] : 0;
		$images = isset($params['images']) ? json_decode($params['images'],true) : array();

		if(!$order_id || !$refund_quantity || !$order_goods_id) {
			return array('errcode' => '41001', 'errmsg' => '参数错误');
		}	

		if ($order_refund_id) { //再次申请退款
			$refundItem = OrderRefund::where(array('id' => $order_refund_id, 'merchant_id' => $this->merchant_id, 'shop_id' => $this->shop_id, 'member_id' => $this->member_id))->first();
			if (!$refundItem)
				return array('errcode' => '41002', 'errmsg' => '再次申请退款失败，无效申请记录');

			$package_id = $refundItem['package_id'];
			$reset_order_goods = OrderGoods::select('id')->where(array('order_id' => $refundItem['order_id'], 'member_id' => $this->member_id, 'goods_id' => $refundItem['goods_id'], 'spec_id' => $refundItem['spec_id']))->first();
			if (!$reset_order_goods)
				return array('errcode' => '41003', 'errmsg' => '再次申请退款失败，未知订单产品信息');

			$order_goods_id = $reset_order_goods['id'];
		} else {
            $order_goods_id = $params['order_goods_id'];
            $package_id = isset($params['package_id']) && $params['package_id'] ? $params['package_id'] : 0; //包裹ID
		}

		//订单商品
        if ($package_id) {
            $refundGoods = OrderGoods::select('order_goods.id', 'order_goods.order_id', 'order_goods.goods_id', 'order_goods.goods_name', 'order_goods.spec_id', 'order_goods.quantity as goodsQuantity', 'order_goods.shipped_quantity', 'order_goods.refund_quantity', 'order_goods.price', 'order_goods.pay_price', 'order_goods.postage', 'order_goods.shipment_id', 'order_goods.status', 'order_goods.refund_status', 'order_package_rs.quantity', 'order_goods.spec_id')
                ->join('order_package_rs', 'order_goods.id', '=', 'order_package.order_goods_id')
                ->where(array('order_goods.id' => $order_goods_id, 'order_package.id' => $package_id))
                ->first();  //order_package_rs.quantity 是此产品该包裹对应的数量
        } else {
            $refundGoods = OrderGoods::select('id', 'order_id', 'goods_id', 'goods_name', 'spec_id', 'quantity', 'shipped_quantity', 'refund_quantity', 'price', 'pay_price', 'postage', 'shipment_id', 'status', 'refund_status')
                ->where(array('id' => $order_goods_id, 'member_id' => $this->member_id))
                ->first();
            if ($refundGoods) {
                $refundGoods_quantity = isset($refundGoods['quantity']) ? $refundGoods['quantity'] : 0;
                $refundGoods['goodsQuantity'] = $refundGoods_quantity;
                $refundGoods['quantity'] = $refundGoods_quantity - $refundGoods['shipped_quantity']; //本产品未发货数量
            }
        }
		if (!$refundGoods)
			return array('errcode' => '41004', 'errmsg' => '订单商品不存在');

		$order = $this->_checkOrder($refundGoods['order_id']);
        if (isset($order['status']) && $order['status'] == false) {
			return array('errcode' => '41005', 'errmsg' => $order['message']);
        }

		//对应申请退款记录
        $orderRefund = OrderRefund::select('id', 'merchant_id', 'shop_id', 'order_id', 'member_id', 'goods_id', 'package_id', 'product_id', 'type', 'package_id', 'refund_quantity', 'status', 'shipment_fee')
            ->where(array('merchant_id' => $this->merchant_id, 'order_id' => $order['id'], 'member_id' => $this->member_id, 'goods_id' => $refundGoods['goods_id'], 'spec_id' => $refundGoods['spec_id'], 'package_id' => $package_id))
            ->get();
        $refund_quantity = 0;
        $refund_shipment_fee = 0;
        foreach ($orderRefund as $orK => $orV) {
            // -----------优化用户主动取消可退款,主动取消不增加已退款件数
            if ($orV['status'] != REFUND_CLOSE && $orV['status'] != REFUND_REFUSE && $orV['status'] != REFUND_CANCEL) {
                //统计退款数量； 非关闭、拒绝申请, 不可在退款，
                $refund_quantity += $orV['refund_quantity'];
                $refund_shipment_fee += $orV['shipment_fee'];
            }
        }
		//2017/6/14针对上面的代码在发货后只计算package_id大于0的运费，未发货前申请退款的运费未计算在内，造成申请退款页面，退货运费显示错误
        //统计未发货前退款的运费
        if ($package_id) {
            $unshipped_shipment_fee = OrderRefund::where(
                array(
                    'merchant_id' => $this->merchant_id,
                    'shop_id' => $this->shop_id,
                    'order_id' => $order['id'],
                    'member_id' => $this->member_id,
                    'goods_id' => $refundGoods['goods_id'],
                    'product_id' => $refundGoods['product_id'],
                    'package_id' => 0
                )
            )->where(function ($query) {
                $query->where('status', '!=', REFUND_CLOSE)
                    ->where('status', '!=', REFUND_REFUSE)
                    ->where('status', '!=', REFUND_CANCEL);
            })->sum('shipment_fee');

            $refund_shipment_fee = $refund_shipment_fee + $unshipped_shipment_fee;
        }

        // 总数量 - 退款数量 = 还可退几件
        $_rQuantity = $refundGoods['quantity'] > $refund_quantity ? $refundGoods['quantity'] - $refund_quantity : 0;

		//最大退款商品金额
		if ($refundGoods['goodsQuantity'] == $params['refund_quantity']) {
			$_rAmount = $refundGoods['pay_price'];
		} else {
			$_rAmount = ($refundGoods['pay_price'] / $refundGoods['goodsQuantity']) * $_rQuantity;
		}

        //退款金额增加优惠金额（如余额支付）
        $_rAmount = number_format($_rAmount, 2, '.', '');
		
		//判断退款总数量  总数量   已经退款总数量
        if ($params['refund_quantity'] && $params['refund_quantity'] > $_rQuantity) {
			return array('errcode' => '41006', 'errmsg' => '最多可退' . $_rQuantity . '件');
        }

		//一件可退多少钱
        $oneAmount = number_format(($_rAmount / $_rQuantity), 2, '.', '');
        if ($refundGoods['goodsQuantity'] == $params['refund_quantity']) {
            if ((float)$params['amount'] - sprintf('%.2f', $_rAmount) > 0) {
				return array('errcode' => '41007', 'errmsg' => '最多可退' . $params['refund_quantity'] * $oneAmount . '元');
            }
        } else if ((float)$params['amount'] - sprintf('%.2f', $params['refund_quantity'] * $oneAmount) > 0) {
			return array('errcode' => '41007', 'errmsg' => '最多可退' . $params['refund_quantity'] * $oneAmount . '元');
        }

		$_rShipment_fee = 0; //可退运费
        if ($order['shipment_fee'] > 0) {
            if ($refundGoods['shipment_id'] > 0) {
                $_rShipment_fee = $this->shipmentService->refundShippingFee($order, $refundGoods['goods_id'], $params['refund_quantity']);
            } elseif ($refundGoods['postage'] > 0) {
                //运费退款考虑修改运费 及 退款数量修改 @mwc 20170331
                $reset_shipment_fee = $this->shipmentService->get_order_canrefund_shipmentfee($order);//订单实际可退运费
                $reset_shipment_fee = $reset_shipment_fee - $refund_shipment_fee;//当前商品已退运费
                $overplus_quantity = $_rQuantity - $params['refund_quantity'];//退款剩余数量
                $overplus_shipment_fee = sprintf('%.2f', $refundGoods['postage'] * $overplus_quantity);//剩余数量运费
                $_rShipment_fee = (float)$reset_shipment_fee - $overplus_shipment_fee > 0 ? (float)$reset_shipment_fee - $overplus_shipment_fee : 0;
            }
        }

        $shipment_fee = isset($params['shipment_fee']) ? $params['shipment_fee'] : 0;
        //(运费为分的，判断无效)
        //使用高精确度数字相减
        if (bcsub($shipment_fee, $_rShipment_fee, 2) > 0.00) {
			return array('errcode' => '41008', 'errmsg' => '运费最多可退' . $_rShipment_fee . '元');
        }

		//计算退积分数
        $refundCredit = $this->getRefundCredit($order_goods_id, $params['refund_quantity']);



		return array('errcode' => '0', 'errmsg' => '申请退款成功');
	}

	private function _checkOrder($order_id)
    {
        $where['id'] = $order_id;
        //$order = Order::select('id', 'shop_id', 'member_id', 'shipment_fee', 'is_selffetch', 'is_sys', 'payment_code', 'payment_id', 'payment_name', 'order_sn', 'status', 'created_at', 'refund_status', 'nickname', 'order_type', 'type')->where($where)->first();
		$order = OrderInfo::select('id', 'member_id', 'shipment_fee', 'order_sn', 'status', 'created_time', 'refund_status', 'nickname', 'order_type','pay_type')->where($where)->first();
        //判断订单是否合法
        if (!$order)
            return array('status' => false, 'message' => '订单不存在');
        if ($order['member_id'] != $this->member_id) {
            return array('status' => false, 'message' => '非法订单');
        }
        if ($order['is_deleted'] != 0) {
            return array('status' => false, 'message' => '订单已删除');
        }
        return $order;
    }

	//计算退积分数
    public function getRefundCredit($order_goods_id, $quantity)
    {
        $refundCredit = 0;
        if ($order_goods_id && $quantity) {
            $client = new Client(['base_uri' => getBackendDomain(true), 'timeout' => 200, 'verify' => false]);
            $refundCredit = $client->post('credit/order_refund_credit.json', ['form_params' => [
                'order_goods_id' => $order_goods_id,
                'refund_quantity' => $quantity
            ]])->getBody()->getContents();
        }
        return $refundCredit ? intval($refundCredit) : 0;
    }
}

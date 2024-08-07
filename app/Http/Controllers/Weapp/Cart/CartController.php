<?php

namespace App\Http\Controllers\Weapp\Cart;

use App\Models\Cart;
use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Http\Requests\CartRequest;
use App\Models\Goods;
use App\Models\GoodsSpec;
use App\Services\CartService;
use App\Services\GoodsService;
use App\Services\MerchantService;

use App\Facades\Member;
use App\Utils\CacheKey;
use Illuminate\Support\Facades\Cache;

class CartController extends Controller
{

    public function __construct()
    {
        $this->config_err = config('err');
        $this->merchant_id = Member::merchant_id();
        $this->member_id = Member::id();
    }

    /**
     * 购物车列表
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $cart_list_res = CartService::getCartLists($this->merchant_id, $this->member_id);
        return $cart_list_res;
    }

    /**
     * 新增一条购物车记录
     * @param CartRequest $cartRequest
     * @param GoodsService $goodsService
     * @return array
     * @author: tangkang@dodoca.com
     */
    public function store(CartRequest $cartRequest, GoodsService $goodsService)
    {
		//验证版本权限是否过期
		$merchantInfo = MerchantService::getMerchantVersion($this->merchant_id);
		if(!$merchantInfo || !isset($merchantInfo['errcode'])) {
			return array('errcode'=>99901,'errmsg'=>'操作失败：获取商户数据失败');
		}
		if($merchantInfo['errcode']==0) {
			if($merchantInfo['data']['is_expired']==1) {
				return array('errcode'=>99901,'errmsg'=>'商户已关闭下单功能');
			}
		} else {
			return array('errcode'=>99901,'errmsg'=>'操作失败：'.$merchantInfo['errmsg']);
		}
		
        $param = [
            'merchant_id' => $this->merchant_id,
//            'member_id' => $this->member_id,
            'goods_id' => $cartRequest->get('goods_id'),
            'goods_spec_id' => $cartRequest->get('goods_spec_id', 0),
//            'date' => $cartRequest->get('date', 0),预约不加入购物车
        ];
        $quantity = $cartRequest->get('quantity');
        $goods_res = Goods::get_data_by_id($param['goods_id'], $this->merchant_id);

        if (empty($goods_res)) return $this->config_err['80004'];//商品不存在
        if (empty($goods_res->onsale)) return $this->config_err['80011'];//商品下架
        if ($goods_res->is_sku == 1 || $goods_res->is_sku == 2) {
            if (empty($param['goods_spec_id'])) return ['errcode' => 1, 'errmsg' => '获取商品规格失败'];//商品不存在
        }
        
        //虚拟商品不能加入购物车
        if($goods_res['goods_type'] == 1){
            return ['errcode' => 210002, 'errmsg' => '虚拟商品不能加入购物车'];
        }
        
        $stock_res = $goodsService->getGoodsStock($param);
        if ($stock_res['errcode'] != 0) return $stock_res;
        $stock = $stock_res['data'];
        if ($quantity > $stock) {
            return $this->config_err['80003'];//库存不足
        }
        //限购
        $cquota_res = $goodsService->getCquota($quantity, $goods_res->id, $this->member_id, $this->merchant_id);
        if ($cquota_res['errcode'] != 0) return $cquota_res;

        $key = CacheKey::get_cart_by_member_key($this->member_id, $this->merchant_id);
        $key_goods = CacheKey::get_cart_by_goods_key($param['goods_id'], $param['goods_spec_id']);

        $create_data = [
            'merchant_id' => $this->merchant_id,
            'member_id' => $this->member_id,
            'goods_id' => $cartRequest->get('goods_id'),
            'goods_spec_id' => $cartRequest->get('goods_spec_id'),
            'quantity' => intval($cartRequest->get('quantity', 1)),
            'created_time' => date('Y-m-d H:i:s'),
            'updated_time' => date('Y-m-d H:i:s'),
        ];


//        if ($goods_res->cquota > 0) {//限购
//
//        }

        if (Cache::has($key)) {
            $query_res = Cache::get($key);
            if (count($query_res) > 99) return ['errcode' => 1, 'errmsg' => '您的购物车已满\n您的购物车宝贝总数已满100件，建议您先去结算或清理。'];
            if (isset($query_res[$key_goods]) && !empty($query_res[$key_goods])) {//已有增加数量
                $create_data['quantity'] += intval($query_res[$key_goods]['quantity']);
            }
            $query_res[$key_goods] = $create_data;//有购物车列表，新增、修改一条
//            print_r($query_res);die;

            Cache::forever($key, $query_res);
        } else {
            Cache::forever($key, [$key_goods => $create_data]);
        }

        return $this->config_err['0'];
    }

    /**更新购物车商品(加减商品数量)
     * @param $key_goods 购物车id
     * @param CartRequest $cartRequest
     * @param GoodsService $goodsService
     * @return array
     * @author: tangkang@dodoca.com
     */
    public function update(CartRequest $cartRequest, $key_goods)
    {
        $param = $cartRequest->all();
        $param['key_goods'] = $key_goods;
        $param['merchant_id'] = $this->merchant_id;
        $param['member_id'] = $this->member_id;
        $update_res = CartService::update($param);
        if ($update_res['errcode'] != 0) return $update_res;
        $res = CartService::getCartLists($this->merchant_id, $this->member_id);
        return $res;
    }

    /**删除购物车
     * @param CartRequest $cartRequest
     * @return mixed
     * @author: tangkang@dodoca.com
     */
    public function destroy(CartRequest $cartRequest)
    {
        $param['ids'] = $cartRequest->get('ids', []);
        $param['merchant_id'] = $this->merchant_id;
        $param['member_id'] = $this->member_id;
        $update_res = CartService::delete($param);
        if ($update_res['errcode'] != 0) return $update_res;
        $res = CartService::getCartLists($this->merchant_id, $this->member_id);
        return $res;
    }

}

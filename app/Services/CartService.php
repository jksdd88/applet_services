<?php
/**
 * Created by PhpStorm.
 * User: tang
 * Date: 2017-09-15
 * Time: 9:27
 */
namespace App\Services;

use App\Models\Goods;
use App\Utils\CacheKey;
use Illuminate\Support\Facades\Cache;

class CartService
{
    /**
     * 给前端的购物车列表（调勇哥接口）
     * @param $merchant_id
     * @param $member_id
     * @return array
     * @author: tangkang@dodoca.com
     */
    public static function getCartLists($merchant_id, $member_id)
    {
        $vipcardService = new VipcardService();
        $discount_service = new DiscountService($vipcardService);
        $res = $discount_service->getCartGoodsDiscountInfo($merchant_id, $member_id);
        return $res;
    }

    /**
     * 获取用户购物车列表
     * @param array $param
     * @return array
     * @author: tangkang@dodoca.com
     */
    public static function getLists($param = array())
    {
        if (empty($param['member_id'])) return ['errcode' => 1, 'errmsg' => '获取用户信息失败'];
        if (empty($param['merchant_id'])) return ['errcode' => 1, 'errmsg' => '获取商户信息失败'];
        $key = CacheKey::get_cart_by_member_key($param['member_id'], $param['merchant_id']);
        $query_res = Cache::get($key);
        return ['errcode' => 0, 'errmsg' => '获取购物车列表成功', 'data' => $query_res];
    }

    /**
     * 更新购物车信息
     * @param array $param
     * @return array
     * @author: tangkang@dodoca.com
     */
    public static function update($param = array())
    {
        if (empty($param['member_id'])) return ['errcode' => 1, 'errmsg' => '获取用户信息失败'];
        if (empty($param['merchant_id'])) return ['errcode' => 1, 'errmsg' => '获取商户信息失败'];
        if (empty($param['key_goods'])) return ['errcode' => 1, 'errmsg' => '缺少购物车id参数'];
        if (isset($param['quantity']) && !is_int($param['quantity']) && $param['quantity'] > 0) return ['errcode' => 1, 'errmsg' => '购物车商品数量必须为整数'];
        $config_err = config('err');
        $key_goods = $param['key_goods'];
        $key = CacheKey::get_cart_by_member_key($param['member_id'], $param['merchant_id']);
        $query_res = Cache::get($key);

        $cart = isset($query_res[$key_goods]) ? $query_res[$key_goods] : null;
        if (empty($cart)) return ['errcode' => 1, 'errmsg' => '购物车商品不存在，请刷新购物车。'];

        $goods_res = Goods::get_data_by_id($cart['goods_id'], $param['merchant_id']);
        if (empty($goods_res)) return $config_err['80004'];//商品删除
        if (empty($goods_res->onsale)) return $config_err['80011'];//商品下架
        if ($goods_res->is_sku > 0) {//多规格商品
            $goods_spec_id = $param['goods_spec_id'];
            if (empty($goods_spec_id)) return $config_err['80002']; //直接更新规格,空则没修改规格，直接改原规格数量
            $query_res[$key_goods]['goods_spec_id'] = $goods_spec_id;
        }
        $param_stock = [
            'merchant_id' => $param['merchant_id'],
            'goods_id' => $cart['goods_id'],
            'goods_spec_id' => $cart['goods_spec_id'],
        ];
        $goodsService = new GoodsService();
        $stock_res = $goodsService->getGoodsStock($param_stock);
        if ($stock_res['errcode'] != 0) return $stock_res;
        $stock = $stock_res['data'];
        //修改购买数量
        if ($param['quantity'] > $stock) {
            return $config_err['80003'];//库存不足
        }

        //限购
        $cquota_res = $goodsService->getCquota($param['quantity'], $goods_res->id, $param['member_id'], $param['merchant_id']);
        if ($cquota_res['errcode'] != 0) return $cquota_res;

        $query_res[$key_goods]['quantity'] = $param['quantity'];

        $query_res[$key_goods]['updated_time'] = date('Y-m-d H:i:s');
        Cache::forever($key, $query_res);
        return ['errcode' => 0, 'errmsg' => '更新成功'];

    }

    /**
     * 删除购物车
     * @param array $param
     * @return array
     * @author: tangkang@dodoca.com
     */
    public static function delete($param = array())
    {
        if (empty($param['ids'])) return ['errcode' => 1, 'errmsg' => '请选择要删除的购物车商品'];
        if (empty($param['member_id'])) return ['errcode' => 1, 'errmsg' => '获取用户信息失败'];
        if (empty($param['merchant_id'])) return ['errcode' => 1, 'errmsg' => '获取商户信息失败'];
        $cart_ids = $param['ids'];
        $key = CacheKey::get_cart_by_member_key($param['member_id'], $param['merchant_id']);
        $query_res = Cache::get($key);
        foreach ($cart_ids as $id) {
            unset($query_res[$id]);
        }
        Cache::forever($key, $query_res);
        if (count($query_res) < 1) Cache::forget($key);
        return ['errcode' => '0', 'errmsg' => '删除购物车成功'];
    }
}
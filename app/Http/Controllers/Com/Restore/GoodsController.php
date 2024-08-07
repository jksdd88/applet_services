<?php
/**
 * Created by PhpStorm.
 * User: tang
 * Date: 2017-09-20
 * Time: 15:44
 */
namespace App\Http\Controllers\Com\Restore;

use App\Http\Controllers\Controller;
use App\Utils\CacheKey;
use Illuminate\Http\Request;
use App\Services\GoodsService;
use App\Models\Goods;
use App\Models\GoodsSpec;
use Illuminate\Support\Facades\Cache;

Class GoodsController extends Controller
{
    /**
     * 修复多规格商品总库存
     * @param $goods_id
     * @param Request $request
     * @param GoodsService $goodsService
     * @return array|string
     * @author: tangkang@dodoca.com
     */
    public function stock($goods_id, Request $request, GoodsService $goodsService)
    {
        $return = [];
        $merchant_id = $request->get('merchant_id');
        if(empty($merchant_id)) return '商户信息不能为空';
        //总库存修复
        $goods_stock = Goods::where('id', $goods_id)->where('merchant_id', $merchant_id)->value('stock');
        $return[] = '修改前库存：' . $goods_stock;

        $goods_spec_res = GoodsSpec::get_data_by_goods_id($goods_id, $merchant_id);
        $param = [
            'merchant_id' => $merchant_id,
            'goods_id' => $goods_id,
        ];
        $goods_real_stock = 0;
        foreach ($goods_spec_res as $goods_spec) {
            $param['goods_spec_id'] = $goods_spec->id;
            $goods_spec_stock = $goodsService->getGoodsStock($param);
            if ($goods_spec_stock['errcode'] == 0) {
                $goods_real_stock += intval($goods_spec_stock['data']);
            } else {
                $return[]='多规格商品总库存异常。goods_id-' . $goods_id . '-goods_spec:' . $goods_spec->id;
            }
        }
        $update_data=[
            'stock'=>$goods_real_stock
        ];
        Goods::update_data($goods_id,$merchant_id,$update_data);
        $goods_stock_after = Goods::where('id', $goods_id)->where('merchant_id', $merchant_id)->value('stock');
        $return[] = '修改后库存：' . $goods_stock_after;

        return $return;
    }

    /**
     * 清空所有商品缓存*（含规格的）
     * @author: tangkang@dodoca.com
     */
    public function flushGoods(Request $request){
        $goods_id=$request->get('goods_id');
        $merchant_id=$request->get('merchant_id');
        $goods_spec_res=GoodsSpec::where('goods_id',$goods_id)->get();
        //清商品
        $key = CacheKey::get_good_by_id_key($goods_id, $merchant_id);
        Cache::forget($key);
        echo '清除商品cache成功';
        //清标签组
        $tags_key = CacheKey::get_tags_goods_stock($goods_id, 0); //清除标签组
        Cache::tags($tags_key)->flush();
        echo '------清除成功</br>';
        echo '清除商品cache成功';

        //清规格
        foreach($goods_spec_res as $list){
            $key = CacheKey::get_goodspec_by_id_key($list->id, $merchant_id);
            Cache::forget($key);
            echo '------清除成功</br>';
            print_r($list);
        }
    }
}

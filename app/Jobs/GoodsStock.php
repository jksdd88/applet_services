<?php

namespace App\Jobs;

use App\Jobs\Job;
use App\Models\ApptStock;
use App\Models\Goods;
use App\Models\GoodsSpec;
use App\Services\GoodsService;
use App\Utils\CacheKey;
use App\Utils\CommonApi;
use Illuminate\Contracts\Bus\SelfHandling;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Cache;

class GoodsStock extends Job implements SelfHandling, ShouldQueue
{
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($param)
    {
//        $stock_num, $merchant_id, $goods_id, $goods_spec_id = 0, $date = 0
        $this->param = $param;
//        $this->param = [
//            'stock_num' => $stock_num,
//            'merchant_id' => $merchant_id,
//            'goods_id' => $goods_id,
//            'goods_spec_id' => $goods_spec_id,
//            'date' => $date,
//        ];
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $stock_num = $this->param['stock_num'];
        $merchant_id = $this->param['merchant_id'];
        $goods_id = $this->param['goods_id'];
        $goods_spec_id = $this->param['goods_spec_id'];
        try {
            if (empty($merchant_id) || empty($goods_id) || empty($stock_num)) {//记录异常
                throw new \Exception('库存队列参数错误。' . json_encode($this->param, JSON_UNESCAPED_UNICODE));
            }
            $goods_res = Goods::get_data_by_id($goods_id, $merchant_id);
            if (empty($goods_res)) {
                throw new \Exception('商品不存在。商品id：' . $goods_id . '，查询结果：' . json_encode($goods_res, JSON_UNESCAPED_UNICODE));
            }
            if ($goods_res->is_sku == 1) {//普通多规格
                if (empty($goods_spec_id)) throw new \Exception('规格id不能为空');
                $goods_spec_res = GoodsSpec::where(['merchant_id' => $merchant_id, 'id' => $goods_spec_id])->first(['goods_id']);
                if (empty($goods_res) || $goods_spec_res->goods_id != $goods_id) {
                    throw new \Exception('参数商品id与参数规格id不匹配。商品id：' . $goods_id . ',规格id：' . $goods_spec_id);
                }
                $query = GoodsSpec::where('merchant_id', $merchant_id)->where('id', $goods_spec_id);
                if ($stock_num > 0) {
                    $query->increment('stock', $stock_num);
                } else {
                    $query->where('stock', '>=', abs($stock_num))->decrement('stock', abs($stock_num));
                }
                //预约商品不存在总库存，无意义
            } elseif ($goods_res->is_sku == 2) {//预约商品不需要减
                self::setApptStock($this->param);
            }
            //操作goods表库存
            if ($goods_res->is_sku == 0 || $goods_res->is_sku == 1) {
                $queryGoods = Goods::where('merchant_id', $merchant_id)->where('id', $goods_id);
                if ($stock_num > 0) {
                    $queryGoods->increment('stock', $stock_num);
                } else {
                    $queryGoods->where('stock', '>=', abs($stock_num))->decrement('stock', abs($stock_num));
                }
                //清goods表单条缓存记录，非多规格库存已修改不需清（这里就是缓存操作后的队列）
                $key = CacheKey::get_good_by_id_key($goods_id, $merchant_id);
                Cache::forget($key);//update_data封装的会清所有标签组，清除后所有规格库存重置，故不用封装的update_data。这里只是把goods表单挑记录清空缓存
            }
        } catch (\Exception $e) {
            //记录异常
            $except = [
                'activity_id' => $goods_spec_id,
                'data_type' => 'goods_stock_job',
                'content' => 'app/Jobs/GoodsStock.php。' . $e->getMessage(),
            ];
            CommonApi::errlog($except);
        }
        return;
    }

    /**
     * 预约库存操作
     * @param $param
     * @author: tangkang@dodoca.com
     */
    public function setApptStock($param)
    {
        //非法字符串日期
        $data_temp = date('Y-m-d', strtotime($param['date']));
        if (empty($param) || empty($param['merchant_id']) || empty($param['goods_id']) || empty($param['goods_spec_id']) || empty($param['date']) || empty($param['stock_num']) || $data_temp == '1970-01-01') {
            throw new \Exception('预约库存队列参数错误' . json_encode($param, JSON_UNESCAPED_UNICODE));
        }
        $data = [
            'merchant_id' => $param['merchant_id'],
            'goods_id' => $param['goods_id'],
            'goods_spec_id' => $param['goods_spec_id'],
            'appt_date' => $param['date'],
        ];
        $appt_stock_res = ApptStock::where($data)->first(['stock', 'id']);
        $goods_spec_res = GoodsSpec::where(['merchant_id' => $param['merchant_id'], 'id' => $param['goods_spec_id']])->first(['goods_id', 'stock']);
        if (empty($appt_stock_res)) {//第一次售卖
            $data['stock'] = $goods_spec_res->stock - abs($param['stock_num']);
            ApptStock::create($data);
        } else {
            //初始库存10，A购买后，商家重新设置库存为5，B买家再购买，此时库存4，A、B都取消订单还库存，则库存变更为6了？
            if ($param['stock_num'] < 0 && (($appt_stock_res->stock - abs($param['stock_num'])) < 0)) {//减库存后小于零了
                return;
                throw new \Exception('不能再减了，小于0了。'
                    . $appt_stock_res->stock
                    . '，规格表库存：' . $goods_spec_res->stock
                    . ',参数：' . json_encode($param, JSON_UNESCAPED_UNICODE)
                );
            }
            if ($param['stock_num'] > 0 && $appt_stock_res->stock >= $goods_spec_res->stock) {//加库存后大于商家设置的最大库存了
                return;
                throw new \Exception('不能再加了，大于规格库存了。预约库存表库存：'
                    . $appt_stock_res->stock
                    . '，规格表库存：' . $goods_spec_res->stock
                    . '，参数：' . json_encode($param, JSON_UNESCAPED_UNICODE)
                );
            }
            $query = ApptStock::where($data);
            if ($param['stock_num'] > 0) {
                $query->increment('stock', $param['stock_num']);
            } else {
                $query->where('stock', '>=', abs($param['stock_num']))->decrement('stock', abs($param['stock_num']));
            }
        }
        return;
    }
}

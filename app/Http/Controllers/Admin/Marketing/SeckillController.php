<?php

namespace App\Http\Controllers\Admin\Marketing;

use App\Http\Controllers\Controller;
use App\Services\AloneActivityRecodeService;
use App\Services\GoodsService;
use App\Services\SeckillService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Models\Seckill;
use App\Models\Goods;
use App\Models\AloneActivityRecode;
use App\Models\SeckillInitiate;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Auth;
use App\Models\GoodsSpec;
use App\Models\WeixinQrcode;

class SeckillController extends Controller
{
    private $merchant_id;

    function __construct(Seckill $seckill, Request $request)
    {
        $this->model = $seckill;
        $this->params = $request->all();
        $this->merchant_id = Auth::user()->merchant_id;
    }

    //秒杀列表
    public function getSeckillList()
    {
        if (empty($this->merchant_id)) {
            return Response::json(['errcode' => 70001, 'errmsg' => '非法操作']);
        }
        $params = $this->params;
        $offset = isset($params['offset']) ? $params['offset'] : 0;
        $limit = isset($params['limit']) ? $params['limit'] : 10;
        $status = isset($params['status']) && is_numeric($params['status']) ? intval($params['status']) : 'all';
        $title = isset($params['title']) && $params['title'] ? $params['title'] : '';
        $id = isset($params['id']) && $params['id'] ? $params['id'] : '';
        $start_at = isset($params['start_at']) && $params['start_at'] ? $params['start_at'] : '';
        $end_at = isset($params['end_at']) && $params['end_at'] ? $params['end_at'] : '';
        //该商户的下的所有未删除的商品
        $goods_list = Goods::where('merchant_id', $this->merchant_id)->where('is_delete', 1)->lists('id');
        $query = $this->model
            ->select('id', 'goods_id', 'goods_title', 'goods_img', 'price', 'csale', 'end_time', 'start_time', 'presale_time', 'start_time', 'end_time')
            ->where('merchant_id', '=', $this->merchant_id)
            ->whereIn('goods_id', $goods_list);
        $today = date('Y-m-d H:i:s');
        if (is_numeric($status)) {
            $status = $status + 1;
            // $query->where('status',$status-1);
            switch ($status) {
                case 1://未开始   当前时间>预热时间
                    $query->where('presale_time', '>', $today)->where('end_time', '>', $today);
                    break;
                case 2://预热
                    $query->where('presale_time', '<=', $today)->where('start_time', '>', $today)->where('end_time', '>', $today);
                    break;
                case 3://开始
                    $query->where('start_time', '<=', $today)->where('end_time', '>', $today);
                    break;
                case 4://结束
                    $query->where('end_time', '<=', $today);
                    break;
                default:
                    break;
            }
        }

        if (false === empty($id)) {
            $query->where('id', 'like', '%' . $id . '%');
        }
        if (false === empty($title)) {
            $query->where('goods_title', 'like', '%' . $title . '%');
        }
        if (false === empty($start_at)) {
            $query->where('start_time', '>=', $start_at);
        }
        if (false === empty($end_at)) {
            $query->where('end_time', '<=', $end_at);
        }

        $count = $query->count();
        $lists = $query->orderBy('id', 'DESC')->skip($offset)->take($limit)->get();
        $lists = count($lists) > 0 ? $lists->toArray() : [];

        if ($lists) {
            foreach ($lists as $key => &$val) {
//                $lists[$key]['stock'] = Goods::where('id', '=', $val['goods_id'])->pluck('stock');
                $goods_res = Goods::get_data_by_id($val['goods_id'], $this->merchant_id);
                $lists[$key]['stock'] = $goods_res['stock'];
                $time = time();
                if ($time < strtotime($val['presale_time']) && $time < strtotime($val['end_time'])) {
                    $val['status'] = 0;      //未开始
                } else if ($time >= strtotime($val['presale_time']) && $time < strtotime($val['start_time']) && $time < strtotime($val['end_time'])) {
                    $val['status'] = 1;   //预热
                } else if ($time >= strtotime($val['start_time']) && $time < strtotime($val['end_time'])) {
                    $val['status'] = 2;       //开始
                } else if ($time >= strtotime($val['end_time'])) {
                    $val['status'] = 3;                                         //结束
                } else {
                    $val['status'] = 3;
                }
                //------------------------↓------------------------Qrcode start 
                //是否生成二维码 1 商品 2 活动 3 拼团，4秒杀
                //author :renruiqi@dodoca.com
                if (isset($this->params['appid']) && !empty(trim($this->params['appid']))) {
                    $appid_qr = trim($this->params['appid']);
                    $val['is_qrcode'] = count(WeixinQrcode::check($val['id'], 4, $appid_qr)) > 0 ? 1 : 2; //二维码是否生成 1:是 2:否
                }
                //------------------------↑------------------------Qrcodeend
            }
        }
        return Response::json(['errcode' => 0, '_count' => $count, 'data' => $lists]);
    }

    //添加秒杀
    public function postSeckill()
    {
        if (empty($this->merchant_id)) {
            return Response::json(['errcode' => 70001, 'errmsg' => '非法操作']);
        }
        $params = $this->params;
        $data = array(
            'merchant_id' => $this->merchant_id,
            'goods_id' => isset($params['goods_id']) && $params['goods_id'] ? intval($params['goods_id']) : 0,
            'goods_title' => isset($params['goods_title']) ? trim($params['goods_title']) : '',
            'goods_img' => isset($params['goods_img']) ? trim($params['goods_img']) : '',
            'price' => isset($params['price']) && $params['price'] ? floatval($params['price']) : 0.00,
            'price_title' => isset($params['price_title']) ? trim($params['price_title']) : '',
            'seckill_schedule_show' => isset($params['seckill_schedule_show']) ? intval($params['seckill_schedule_show']) : 0,
            'status' => isset($params['status']) ? intval($params['status']) : 0,
            'start_time' => isset($params['start_time']) && $params['start_time'] ? date('Y-m-d H:i:s', strtotime(trim($params['start_time']))) : '',
            'presale_time' => isset($params['presale_time']) && $params['presale_time'] ? date('Y-m-d H:i:s', strtotime(trim($params['presale_time']))) : '',
            'end_time' => isset($params['end_time']) && $params['end_time'] ? date('Y-m-d H:i:s', strtotime(trim($params['end_time']))) : '',
            'finished_at_show' => isset($params['finished_at_show']) ? intval($params['finished_at_show']) : 0,
			'cquota'	=>	isset($params['cquota']) ? intval($params['cquota']) : 0,
			'is_remind'	=>	isset($params['is_remind']) ? intval($params['is_remind']) : 0,
        );

        if (!$data['goods_id']) {
            return Response::json(['errcode' => 70002, 'errmsg' => '未指定商品']);
        }
        $goods_id = $data['goods_id'];
        $goods_stock_res = Goods::get_data_by_id($data['goods_id'], $this->merchant_id);

        $service_goodsservice = new GoodsService();
        $param['merchant_id'] = $goods_stock_res['merchant_id'];
        $param['goods_id'] = $goods_stock_res['id'];
        if ($goods_stock_res['is_sku'] == 1) {
            $goods_spec_res = GoodsSpec::get_data_by_goods_id($goods_stock_res['id'], $goods_stock_res['merchant_id']);
            if (empty($goods_spec_res)) return ['errcode' => 1, 'errmsg' => '多规格信息获取失败'];
            foreach ($goods_spec_res as $goods_spec) {
                $param['goods_spec_id'] = $goods_spec->id;
                $goods_spec_stock = $service_goodsservice->getGoodsStock($param);
                if ($goods_spec_stock['errcode'] == 0) {
                    $goods_stock_res['stock'] += intval($goods_spec_stock['data']);
                } else {
                    \Log::info('新建秒杀获取多规格库存失败：' . json_encode($goods_spec_stock));
                }
            }
        } else {
            $cache_goods = $service_goodsservice->getGoodsStock($param);
            if (isset($cache_goods['data'])) {
                $goods_stock_res['stock'] = $cache_goods['data'];
            } else {
                $goods_stock_res['stock'] = 0;
            }
        }
        if ($goods_stock_res['stock'] < 1) {
            return Response::json(['errcode' => 70003, 'errmsg' => '选择的商品无库存']);
        }

        $goods_info = Goods::get_data_by_id($goods_id, $this->merchant_id);
        if (!empty($goods_info) && $goods_info->is_delete == 1 && $goods_info->onsale == 1) {
            $AloneActivityRecodeService = new AloneActivityRecodeService();
            $alone_res = $AloneActivityRecodeService->getCreated([$goods_id], $this->merchant_id);
            if (!empty($alone_res) && !empty($alone_res[0])) {
                if ($alone_res[0]['act_type'] == 'seckill') {
                    return Response::json(['errcode' => 1, 'errmsg' => '已参加秒杀活动，请重新选择其它商品创建。']);
                } elseif ($alone_res[0]['act_type'] == 'tuan') {
                    return Response::json(['errcode' => 1, 'errmsg' => '已参加团购活动，请重新选择其它商品创建。']);
                } else {
                    return Response::json(['errcode' => 1, 'errmsg' => '已参加营销活动，请重新选择其它商品创建。']);
                }
            }
            //单规格
            if ($goods_info->is_sku == 0) {
                $min_price = $goods_info->price;
            } elseif ($goods_info->is_sku == 1) {
                //多规格最低价
                $min_price = GoodsSpec::where('goods_id', $goods_id)
                    ->where('is_delete', 1)
                    ->min('price');
            } elseif ($goods_info->is_sku == 2) {
                return Response::json(['errcode' => 70021, 'errmsg' => '多规格预约商品暂不支持秒杀']);
            }
        } else {
            return Response::json(['errcode' => 70022, 'errmsg' => '该商品已经下架']);
        }
        if ($min_price < $data['price']) {
            return Response::json(['errcode' => 70023, 'errmsg' => '秒杀价格必须小于或等于最低价']);
        }

        $data['start_time'] = $data['start_time'] != '' ? $data['start_time'] : date('Y-m-d H:i:s');     //开始时间默认值
        $data['presale_time'] = $data['presale_time'] != '' ? $data['presale_time'] : $data['start_time'];   //预热时间默认与开始时间同步
        $seckill_data = $this->model->create($data);
        if ($seckill_id = $seckill_data->id) {
            $recode = array(
                'merchant_id' => $this->merchant_id,
                'goods_id' => $data['goods_id'],
                'alone_id' => $seckill_id,
                'act_type' => 'seckill',
                'finish_time' => $data['end_time'],
                'start_time' => $data['presale_time'],
            );
            AloneActivityRecode::create($recode);
            return Response::json(['errcode' => 0, 'errmsg' => '添加成功']);
        } else {
            return Response::json(['errcode' => 70005, 'errmsg' => '活动添加失败']);
        }
    }

    //修改秒杀
    public function putSeckill($id)
    {
        $params = $this->params;
        $str_today = date('Y-m-d H:i:s');
        $start_time = isset($params['start_time']) && $params['start_time'] ? date('Y-m-d H:i:s', strtotime(trim($params['start_time']))) : $str_today;
        $end_time = isset($params['end_time']) && $params['end_time'] ? date('Y-m-d H:i:s', strtotime(trim($params['end_time']))) : $str_today;
        $cquota = isset($params['cquota']) ? (int)$params['cquota'] : 0;
        $is_remind = isset($params['is_remind']) ? (int)$params['is_remind'] : 0;
//        $presale_time = isset($params['presale_time']) && $params['presale_time'] ? date('Y-m-d H:i:s',strtotime(trim($params['presale_time']))) : '';

        if (empty($this->merchant_id)) {
            return Response::json(['errcode' => 70001, 'errmsg' => '非法操作']);
        }
        if (!$id) {
            return Response::json(['errcode' => 70002, 'errmsg' => '参数错误']);
        }

        if (isset($params['merchant_id'])) {
            unset($params['merchant_id']);
        }
        $SeckillService = new SeckillService();
        $seckill_res = $SeckillService->attachStatus($id, $this->merchant_id);
        if ($seckill_res['errcode'] != 0) return $seckill_res;
        $seckill_data = $seckill_res['data'];

        //未开始才可以修改
        if ($seckill_data['status'] != 2) {
            $data = array(
                'start_time' => $start_time,
                'end_time' => $end_time,
				'cquota'	=>	$cquota,
				'is_remind'	=>	$is_remind,
            );
            $goods_stock_res = Goods::get_data_by_id($seckill_data['goods_id'], $this->merchant_id);
            $service_goodsservice = new GoodsService();
            $param['merchant_id'] = $goods_stock_res['merchant_id'];
            $param['goods_id'] = $goods_stock_res['id'];

            if ($goods_stock_res['is_sku'] == 1) {
                $goods_spec_res = GoodsSpec::get_data_by_goods_id($goods_stock_res['id'], $goods_stock_res['merchant_id']);
                if (empty($goods_spec_res)) return ['errcode' => 1, 'errmsg' => '多规格信息获取失败'];
                foreach ($goods_spec_res as $goods_spec) {
                    $param['goods_spec_id'] = $goods_spec->id;
                    $goods_spec_stock = $service_goodsservice->getGoodsStock($param);
                    if ($goods_spec_stock['errcode'] == 0) {
                        $goods_stock_res['stock'] += intval($goods_spec_stock['data']);
                    } else {
                        \Log::info('新建秒杀获取多规格库存失败：' . json_encode($goods_spec_stock));
                    }
                }
            } else {
                $cache_goods = $service_goodsservice->getGoodsStock($param);
                if (isset($cache_goods['data'])) {
                    $goods_stock_res['stock'] = $cache_goods['data'];
                } else {
                    $goods_stock_res['stock'] = 0;
                }
            }

            if ($goods_stock_res['stock'] < 1) {
                return Response::json(['errcode' => 70003, 'errmsg' => '选择的商品无库存']);
            }
            $result = $this->model->update_data($id, $this->merchant_id, $data);//修改缓存
            if ($result) {
                $recode = array(
                    // 'start_time'  =>  $start_time,   
                    'finish_time' => $end_time,//结束时间同步
                );
                AloneActivityRecode::where(array('alone_id' => $id, 'merchant_id' => $this->merchant_id))->update($recode);
                return Response::json(['errcode' => 0, 'errmsg' => '保存成功']);
            } else {
                return Response::json(['errcode' => 70008, 'errmsg' => '活动保存失败']);
            }
        } else {
            return Response::json(['errcode' => 70009, 'errmsg' => '活动已开始无法修改']);
        }
    }

    //秒杀详细
    public function getSeckill($id)
    {
        if (empty($this->merchant_id)) {
            return Response::json(['errcode' => 70001, 'errmsg' => '非法操作']);
        }
        if (!$id) {
            return Response::json(['errcode' => 70002, 'errmsg' => '参数错误']);
        }

//        $seckill_data = $this->model->where(array('id'=>$id,'merchant_id' => $this->merchant_id))->first();
        $seckill_data = $this->model->get_data_by_id($id, $this->merchant_id);

        if (empty($seckill_data)) {
            return Response::json(['errcode' => 70006, 'errmsg' => '活动记录不存在']);
        }

        return Response::json(['errcode' => 0, 'data' => $seckill_data]);
    }

    //关闭活动-手动秒杀结束
    public function putFinishSeckill()
    {
        $params = $this->params;
        $id = isset($params['id']) ? intval($params['id']) : '';
        if (empty($this->merchant_id)) {
            return Response::json(['errcode' => 70001, 'errmsg' => '非法操作']);
        }
        if (!$id) {
            return Response::json(['errcode' => 70002, 'errmsg' => '参数错误']);
        }

//        $seckill_data = $this->model->where(array('id' => $id,'merchant_id' => $this->merchant_id))->first();
//        if(empty($seckill_data)){
//            return Response::json(['errcode'=>70006,'errmsg'=>'活动记录不存在']);
//        }
        $SeckillService = new SeckillService();
        $seckill_res = $SeckillService->attachStatus($id, $this->merchant_id);
        if ($seckill_res['errcode'] != 0) return $seckill_res;
        $seckill_data = $seckill_res['data'];

        $seckill_data->end_time = date('Y-m-d H:i:s');
        $seckill_data->status = 3;
        $result = $seckill_data->save();
        if ($result) {
            // $alone_data = AloneActivityRecode::where(array('alone_id' => $id,'act_type' => 'seckill', 'finish_time' => '0000-00-00 00:00:00'))->first();
            $alone_data = AloneActivityRecode::where('alone_id', $id)
                ->where('act_type', 'seckill')
                ->where('finish_time', '>', $seckill_data->end_time)
                ->first();

            if (!empty($alone_data)) {
                $alone_data->finish_time = $seckill_data->end_time;
                $finish_recode = $alone_data->save();
                if (!$finish_recode) {
                    return Response::json(['errcode' => 70010, 'errmsg' => '活动关闭失败']);
                }
            }
            return Response::json(['errcode' => 0, 'errmsg' => '活动关闭成功']);
        }
    }

    //检测商品是否正在参加其他活动
    public function getGoodsStatus($id)
    {
        if (empty($this->merchant_id)) {
            return Response::json(['errcode' => 70001, 'errmsg' => '非法操作']);
        }
        if (!$id) {
            return Response::json(['errcode' => 70002, 'errmsg' => '参数错误']);
        }
        $date = date('Y-m-d H:i:s');
        $alone_data = AloneActivityRecode::where('goods_id', $id)
            ->where('merchant_id', $this->merchant_id)
            ->where(function ($query) use ($date) {
                $query->where(function ($q_type) use ($date) {
                    $q_type->where('act_type', 'seckill')->where('finish_time', '>', $date);
                })->orWhere(function ($q_type) {
                    $q_type->where('act_type', 'tuan')->where('finish_time', '0000-00-00 00:00:00');
                });
            })
            ->first();
        if ($alone_data == null) {
            return Response::json(['errcode' => 0]);
        } else {
            return Response::json(['errcode' => 70004, 'errmsg' => '当前商品正在参加活动']);
        }
    }


}

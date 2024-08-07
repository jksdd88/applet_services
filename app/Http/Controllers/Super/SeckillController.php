<?php

namespace App\Http\Controllers\Super;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Seckill;
use App\Models\Goods;
use Illuminate\Support\Facades\Response;

class SeckillController extends Controller {
    function __construct(Seckill $seckill,Request $request) {
        $this->model  = $seckill;
        $this->params = $request->all();
    }

    //秒杀列表
    public function getSeckillList()
    {
        $params=$this->params;
        $offset   = isset($params['offset']) ? $params['offset'] : 0;
        $limit    = isset($params['limit']) ? $params['limit'] : 10;
        $status   = isset($params['status']) && is_numeric($params['status']) ? intval($params['status']) : 'all';
        $title = isset($params['title']) && $params['title'] ? $params['title'] : '';
        $id = isset($params['id']) && $params['id'] ? $params['id'] : '';
        $start_at = isset($params['start_at']) && $params['start_at'] ? $params['start_at'] : '';
        $end_at = isset($params['end_at']) && $params['end_at'] ? $params['end_at'] : '';
        $query = $this->model
                ->select('id', 'goods_id', 'goods_title', 'goods_img', 'price', 'csale', 'end_time','start_time','presale_time','start_time','end_time');
        $today = date('Y-m-d H:i:s');
        if(is_numeric($status)){
            $status=$status+1;
            // $query->where('status',$status-1);
            switch($status) {
                case 1://未开始   当前时间>预热时间
                    $query->where('presale_time','>',$today)->where('end_time','>',$today);
                    break;
                case 2://预热
                    $query->where('presale_time','<=',$today)->where('start_time','>',$today)->where('end_time','>',$today);
                    break;
                case 3://开始
                    $query->where('start_time','<=',$today)->where('end_time','>',$today);
                    break;
                case 4://结束
                    $query->where('end_time','<=',$today);
                    break;
                default:
                    break;
            }
        }

        if(false === empty($id)){
            $query->where('id','like','%'.$id.'%');
        }
        if(false === empty($title)){
            $query->where('goods_title','like','%'.$title.'%');
        }
        if(false === empty($start_at)){
            $query->where('start_time','>=',$start_at);
            $query->where('start_time','<=',$end_at);
        }
        if(false === empty($end_at)){
            $query->where('end_time','<=',$end_at);
            $query->where('end_time','>=',$start_at);
        }

        $count = $query->count();
        $lists = $query->orderBy('id', 'DESC')->skip($offset)->take($limit)->get();
        $lists = count($lists)>0 ? $lists->toArray() : [];

        if($lists){
            foreach($lists as $key => &$val){
                $lists[$key]['stock'] = Goods::where('id','=',$val['goods_id'])->pluck('stock');
                $time = time();
                if($time<strtotime($val['presale_time']) && $time<strtotime($val['end_time'])){
                    $val['status'] = 0;      //未开始
                }else if($time>=strtotime($val['presale_time']) && $time<strtotime($val['start_time']) && $time<strtotime($val['end_time'])){
                    $val['status'] = 1;   //预热
                }else if($time>=strtotime($val['start_time']) && $time<strtotime($val['end_time'])){
                    $val['status'] = 2;       //开始
                }else if($time>=strtotime($val['end_time'])){
                    $val['status'] = 3;                                         //结束
                }else{
                    $val['status'] = 3;  
                }
            }
        }
        return Response::json(['errcode'=>0,'_count'=>$count,'data'=>$lists]);
    }

}

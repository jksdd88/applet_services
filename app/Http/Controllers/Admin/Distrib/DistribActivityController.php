<?php

namespace App\Http\Controllers\Admin\Distrib;

use App\Models\DistribActivity;
use App\Models\DistribActivityRelation;
use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;

class DistribActivityController extends Controller
{
    function __construct(Request $request)
    {
        //$this->merchant_id = 2;
        //$this->user_id = 2;
        
        if (app()->isLocal()) {
            $this->merchant_id = 2;
            $this->user_id = 2;

        } else {
            $this->merchant_id = Auth::user()->merchant_id;
            $this->user_id = Auth::user()->id;
        }
    }

    /**
     * 推客活动素材列表
     * @param  $send_status     int     可选  发送类型（1：立即发送，2：定时发送）
     * @param  $keyword         string  可选  搜索标题关键字
     * @param  $page            int     可选  页码 (默认为1)
     * @param  $pagesize        int     可选  显示条数(默认为10)
     */
    public function index(Request $request)
    {
        $param = $request->all();
        $page = (empty($param['page']) || (int)$param['page'] <1) ? 1 : (int)$param['page'];
        $pagesize = (empty($param['pagesize']) || (int)$param['pagesize'] <1) ? 10 : (int)$param['pagesize'];
        
        $field = ["id", "title", "send_type", "send_time", "image", "text1", "text2", "text3", "created_time"];
        $query = DistribActivity::select($field)
                ->where('merchant_id', '=', $this->merchant_id)
                ->where('is_delete', '=', 1);
        
        if(isset($param['send_status']) && $param['send_status'] == 1) //已生效
        {
            
            $query->whereRaw('((`send_type` = 1 AND `created_time` <= NOW()) OR (`send_type` = 2 AND `send_time` <= NOW()))');
        }
        else if(isset($param['send_status']) && $param['send_status'] == 2) //未生效
        {
            $query->whereRaw('((`send_type` = 1 AND `created_time` > NOW()) OR (`send_type` = 2 AND `send_time` > NOW()))');
        }
        
        if(isset($param['keyword']) && $param['keyword'] != '')
        {
            $query->where('title', 'like', '%'.$param['keyword'].'%');
        }
        
        $data = [];
        $count = $query->count();
        if($count > 0)
        {
            $data = $query->offset(($page-1)*$pagesize)->limit($pagesize)->get()->toArray();
            if($data)
            {
                foreach($data as $key=>$val)
                {
                    if($val['send_type'] == 1)
                    {
                        $data[$key]['send_time'] = $val['created_time'];
                    }
                    $data[$key]['send_status'] = time() > strtotime($data[$key]['send_time']) ? '已生效' : '未生效';
                }
            }
        }

        return Response::json(['errcode' => 0, 'errmsg' => '获取数据成功', 'count' => $count, 'data' => $data]);
    }


    /**
     * 推客活动素材列表
     * @param  $id int 必选 活动id
     */
    public function getRow(Request $request)
    {
        $id = (int)$request->input('id', 0);
        if($id > 0)
        {
            $row = DistribActivity::get_data_by_id($id, $this->merchant_id);
            if(!$row)
            {
                return Response::json(['errcode'=>99001 , 'errmsg'=>'此活动不存在！']);//数据不存在
            } else {
                return Response::json(['errcode'=>0 , 'errmsg'=>'获取成功！', 'data' => $row]);
            }
        }
    }


    /**
     * 推客活动素材信息
     * @params id           int 可选 活动id
     * @params title        int 必填 标题
     * @params send_type    int 必填 发送类型（1：立即发送，2：定时发送）
     * @params send_time    int 可选 定时发送时间（send_type=2时有效）
     * @params image        int 必填 活动图片
     * @params text1        int 必填 底部文案1
     * @params text2        int 必填 底部文案2
     * @params text3        int 必填 活动文案
     */
    public function save(Request $request)
    {
        $id = $request->input('id', 0);
        $param = $request -> all();
        
        //验证
        $check  = self::checkDistribData($param);
        if($check['errcode'] != 0) return Response::json($check); //验证失败
        
        $data = $check['data'];
        
        //判定是否存在
        if($id > 0)
        {
            $is_exist = DistribActivity::get_data_by_id($id, $this->merchant_id);
            if(!$is_exist) return Response::json(['errcode'=>99001 , 'errmsg'=>'此活动不存在！']);//数据不存在
            
            $rs = DistribActivity::update_data($id, $this->merchant_id, $data);
            if($rs)
            {
                //删除关系表数据
                DistribActivityRelation::delete_datas($id, $this->merchant_id);
            }
        } else {
            $data['merchant_id'] = $this->merchant_id;
            $data['send_time'] = $data['send_type'] == 2 ? $data['send_time'] : date('Y-m-d H:i:s', time());
            DistribActivity::insert_data($data);
        }

        return  Response::json(['errcode'=>0 , 'errmsg'=>'设置已生效']);
    }


    /**
     * 数据验证
     * @params $goods_id  int 必填 商品id
     */
    static function checkDistribData($param)
    {
        $return_data = ['errcode'=>0, 'errmsg'=>""];
        
        if(!isset($param['title']) || $param['title'] == '' || mb_strlen($param['title']) > 40){
            $return_data['errcode'] = 99001;
            $return_data['errmsg'] = "参数非法";
        }
        
        if(!isset($param['send_type']) || (int)$param['send_type'] <= 0 || (int)$param['send_type'] > 2){
            $return_data['errcode'] = 99001;
            $return_data['errmsg'] = "参数非法";
        }
        
        if($param['send_type'] == 2 && 
            (!isset($param['send_time']) || $param['send_time'] == '')){
            $return_data['errcode'] = 99001;
            $return_data['errmsg'] = "参数非法";
        }
        
        if(!isset($param['image']) || $param['image'] == '' || mb_strlen($param['image']) > 255){
            $return_data['errcode'] = 99001;
            $return_data['errmsg'] = "参数非法";
        }
        
        if(!isset($param['text1']) || $param['text1'] == '' || mb_strlen($param['text1']) > 100){
            $return_data['errcode'] = 99001;
            $return_data['errmsg'] = "参数非法";
        }
        
        if(!isset($param['text2']) || $param['text2'] == '' || mb_strlen($param['text2']) > 200){
            $return_data['errcode'] = 99001;
            $return_data['errmsg'] = "参数非法";
        }
        
        $return_data['data'] = $return_data['errcode'] == 0 ? $param : '';
        return  $return_data;
    }



    /**
     * 删除活动素材信息
     * @params $id  int 必填 活动id
     */
    public function destroy($id)
    {
        DistribActivity::update_data($id, $this->merchant_id, ['is_delete'=>-1]);
        
        //删除关系表数据
        DistribActivityRelation::delete_datas($id, $this->merchant_id);
        
        return  Response::json(['errcode'=>0 , 'errmsg'=>'操作成功']);
    }
}

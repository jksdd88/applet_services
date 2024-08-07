<?php

namespace App\Http\Controllers\Admin\Form;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Services\FormFeedbackService;
use App\Services\FormService;
use Illuminate\Support\Facades\Response;


use App\Models\FormDailyView;
use App\Models\FormSum;
use Illuminate\Support\Facades\DB;

use Illuminate\Support\Facades\Auth;

class FormStatisticsController extends Controller
{

    public function __construct()
    {
       if (app()->isLocal()) {
            $this->merchant_id = 1;
        } else {
            $this->merchant_id = Auth::user()->merchant_id;
        }
    }

    /**
    *@param $form_id int 必选 表单id
    *@param $day_nums int 可选 查阅天数 默认5天
    *@param $start_time str 可选 开始时间 默认格式xxxx-xx-xx
    *@param $endt_time str 可选 结束时间 默认格式xxxx-xx-xx
    *@param $wxinfo_id int 可选 小程序id 
    *@return array
    *@author renruiqi@dodoca.com
    *
    */
    public function getFormStatistics(Request $request,FormFeedbackService $FormFeedbackService,$form_id){
        $param = $request->all();
        //天数和截止时间判定
        $end_time = date('Y-m-d'); 
        if(isset($param['day_nums']) && intval($param['day_nums'])>0){
            $search_num = intval($param['day_nums']) -1;
        }elseif(isset($param['start_time']) && isset($param['end_time'])){
            if(strtotime($param['start_time'])==false ||
                strtotime($param['end_time'])==false ||
                mb_strlen($param['start_time'])!=10 ||
                mb_strlen($param['end_time'])!=10 ||
                strtotime($param['end_time'])<strtotime($param['start_time'])
                ){
                return Response::json(['errcode'=>1,'errmsg'=>'参数格式错误','data'=>[]]);
            }
            $end_time = $param['end_time'];
            $search_num = (int)( (strtotime($param['end_time']) - strtotime($param['start_time']))/86400);
        }else{
            $search_num = 4;
        }
        for($i=0;$i<=$search_num;$i++){
            $day_time_str = date('Y-m-d',strtotime($end_time)-$i*86400);
             $query = FormSum::select('feedback_sum_day','view_sum_day','form_id','feedback_sum')
                ->where('form_id',$form_id)
                ->whereDate('day_time','=',$day_time_str)
                ->where('merchant_id',$this->merchant_id);
            if(isset($param['wxinfo_id']) && (int)$param['wxinfo_id']>0){
                $list = $query->where('wxinfo_id',intval($param['wxinfo_id']))->get();
            }else{
                $list = $query->get();
            }
            $data[$day_time_str]['feedback_sum_day'] =0;
            $data[$day_time_str]['view_sum_day'] =0;
            if(count($list)>0){
                foreach( $list as $v){
                    $data[$day_time_str]['feedback_sum_day'] += $v->feedback_sum_day;
                    $data[$day_time_str]['view_sum_day'] += $v->view_sum_day;
                    // $data[$day_time_str]['feedback_sum'] = $xxx;//单日总反馈数(需要查库) 
                }
            }
        }  

        $where = [
            'form_id'=>$form_id,
            'merchant_id'=>$this->merchant_id,
        ];
        if(isset($param['wxinfo_id']) && intval($param['wxinfo_id']) >0){
            $where['wxinfo_id']= (int)$param['wxinfo_id'];
        }
        $view_all = FormDailyView::where($where)->count();//总浏览数

        $res = $FormFeedbackService->getFeedbackTimes($where,$where['merchant_id']);
        if($res['errcode'] !==0){
            return Response::json(['errcode'=>1,'errmsg'=>'接口调用失败','data'=>[]]);
        }else{
            $feedback_all = $res['data']['total'];//总反馈数量
            $feedback_sum_today = $res['data']['total_today'];//今日反馈数量
        }
        if($end_time === date('Y-m-d')){//若结束时间等于当前时间则需实时查询
            $data[$end_time]['view_sum_day'] = FormDailyView::where($where)->whereDate('created_time','=',$end_time)->count();//今日浏览数
            $data[$end_time]['feedback_sum_day'] = $feedback_sum_today;//今日反馈
            // $data[$end_time]['feedback_sum'] = $xxx;//总反馈数(查库)
        }
        ksort($data);
        $data_new['data'] = $data;
        $data_new['feedback_all'] = $feedback_all;
        $data_new['view_all'] = $view_all;
        $data_new['feedback_sum_today'] = $feedback_sum_today;
        return Response::json(['errcode'=>0,'errmsg'=>'查询成功','data'=>$data_new]);
    }

    /**
    *查询当天浏览量+总量
    *@param $form_id int 必选 表单id
    *@param $merchant_id 必选 商家id
    *@param $wxinfo_id int 可选 小程序id
    *@return array
    *@author renruiqi@qq.com
    */
    public function getTodayViewNum($form_id,$merchant_id,$wxinfo_id=0)
    {
        if((int)$form_id<1 || (int)$merchant_id<1 ) return ['errcode'=>1,'errmsg'=>'参数格式错误'];
        $where = [
            'form_id'=>$form_id,
            'merchant_id'=>$merchant_id,
        ];
        if((int)$wxinfo_id>0){
            $where['wxinfo_id']= (int)$wxinfo_id;
        }

        $count['all'] = FormDailyView::where($where)->count();//总数
        $count['today'] = FormDailyView::where($where)->whereDate('created_time','=',date('Y-m-d'))->count();//今日数
        return ['errcode'=>0,'errmsg'=>'ok','data'=>$count];

    }

    //添加数据
    public function index(FormService $a){
        $res = $a->addFormViewNum(11,11,11,11);
        dd($res);
        addFormViewNum( $member_id, $form_id, $wxinfo_id, $merchant_id);
        //反馈表5000条数据
       /* for($i=2;$i<=5000;$i++){
            $data = [
                'merchant_id'=>1,
                'wxinfo_id'=>mt_rand(1,5),
                'form_id'=>mt_rand(1,5),
                'status'=>1,
                'component_values'=>'表单组件'.$i,
                'created_time' => date('Y-m-d H:i:s'),
                'updated_time' => date('Y-m-d H:i:s'),
            ];

            // $res = FormFeedback::insert($data);
        }*/

        //浏览表5000条数据 
         /*for($i=2;$i<=5000;$i++){
            $data = [
                'merchant_id'=>1,
                'wxinfo_id'=>mt_rand(1,5),
                'form_id'=>mt_rand(1,5),
                'member_id'=>$i,
                'created_time' => date('Y-m-d H:i:s'),
            ];

            $res = FormDailyView::insert($data);
        }*/
    }

}

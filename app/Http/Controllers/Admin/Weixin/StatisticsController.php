<?php
/**
 * Created by PhpStorm.
 * User: lizhenheng
 * Date: 2017-12-28
 * Time: 下午 04:36
 */
namespace App\Http\Controllers\Admin\Weixin;

use App\Http\Controllers\Controller;
use App\Models\XcxUserPortraitDaily;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use App\Models\XcxSurveyDaily;
use App\Models\XcxVisitDaily;
use App\Models\XcxVisitDistributionDaily;
use App\Models\XcxVisitPageDaily;
use App\Models\XcxVisitRetainDaily;
use Config;
use App\Models\OrderInfo;
use App\Models\OrderHourDaily;
use Illuminate\Support\Facades\Auth;


class StatisticsController extends Controller
{
    protected $request;
    protected $params;
    protected $today;
    protected $merchant_id;

    public function __construct(Request $request) {
        $this->request = $request;
        $this->params = $request->all();
        $this->today = date('Ymd');
        $this->merchant_id=Auth::user()->merchant_id;
//        $this->merchant_id=4;
    }

    //获取概况趋势
    public function getSurvey(){

        if (!empty($this->params['startdate'])) {  //创建时间

            $start_day = strtotime($this->params['startdate']);

        }else{

            $start_day = strtotime($this->today) - 7*3600*24;
        }

        if (!empty($this->params['enddate'])) {  //结束时间

            $end_day =  strtotime($this->params['enddate']);

        }else{

            $end_day = strtotime($this->today) - 3600*24;
        }

        $offset = isset($this->params['offset']) && $this->params['offset'] ? $this->params['offset'] : 0;
        $limit = isset($this->params['limit']) && $this->params['limit'] ? $this->params['limit'] : 20;
//        $merchant_id = isset($this->params['merchant_id']) && $this->params['merchant_id'] ? $this->params['merchant_id'] : 0;
        $appid = isset($this->params['appid']) && $this->params['appid'] ? $this->params['appid'] : '';

        $wheres = array(

            array('column' => 'day_time', 'value' => date("Ymd",$end_day), 'operator' => '<='),
            array('column' => 'day_time', 'value' => date("Ymd",$start_day), 'operator' => '>='),
            array('column' => 'merchant_id', 'value' => $this->merchant_id, 'operator' => '=')
        );
        if($appid){
            $wheres[]=array('column' => 'appid', 'value' => $appid, 'operator' => '=');
        }
        $fields="day_time,sum(visit_total) as visit_total,sum(share_pv) as share_pv,sum(share_uv) as share_uv";
        $query = XcxSurveyDaily::select(\DB::raw($fields));
        foreach($wheres as $where) {
            $query->where($where['column'], $where['operator'], $where['value']);
        }
        $query->groupBy("day_time");
        $list = $query->orderBy('day_time', 'asc')->get()->toArray();
        $result = array();
        if($list){
            foreach($list as $key=>$val){
                $result[$key]['day_time'] = date("Y-m-d",strtotime($val['day_time']));
                $result[$key]['visit_total'] = $val['visit_total'];
                $result[$key]['share_pv'] = $val['share_pv'];
                $result[$key]['share_uv'] = $val['share_uv'];
            }
        }else{
            $key=0;
            for($start_day;$start_day<=$end_day;$start_day=($start_day+ 3600*24)){
                $result[$key]['day_time'] = date("Y-m-d",$start_day);
                $result[$key]['visit_total'] = 0;
                $result[$key]['share_pv'] = 0;
                $result[$key]['share_uv'] = 0;
                $key++;
            }
        }

        $data['errcode'] = 0;
        $data['data'] = $result;

        return Response::json($data);

    }

    //获取访问趋势
    public function getVisit(){
        if (!empty($this->params['startdate'])) {  //创建时间

            $start_day = strtotime($this->params['startdate']);

        }else{

            $start_day = strtotime($this->today) - 7*3600*24;
        }

        if (!empty($this->params['enddate'])) {  //结束时间

            $end_day =  strtotime($this->params['enddate']);

        }else{

            $end_day = strtotime($this->today) - 3600*24;
        }

//        $merchant_id = isset($this->params['merchant_id']) && $this->params['merchant_id'] ? $this->params['merchant_id'] : 0;
        $appid = isset($this->params['appid']) && $this->params['appid'] ? $this->params['appid'] : '';

        $wheres = array(

            array('column' => 'day_time', 'value' => date("Ymd",$end_day), 'operator' => '<='),
            array('column' => 'day_time', 'value' => date("Ymd",$start_day), 'operator' => '>='),
            array('column' => 'merchant_id', 'value' => $this->merchant_id, 'operator' => '=')
        );
        if($appid){
            $wheres[]=array('column' => 'appid', 'value' => $appid, 'operator' => '=');
        }

        $fields="day_time,merchant_id,appid,session_cnt,visit_pv,visit_uv,visit_uv_new,stay_time_uv,stay_time_session,visit_depth";
        $query = XcxVisitDaily::select(\DB::raw($fields));
        foreach($wheres as $where) {
            $query->where($where['column'], $where['operator'], $where['value']);
        }
        $query->groupBy("day_time");
        $list = $query->orderBy('day_time', 'asc')->get()->toArray();
        $result = array();
        if($list){
            foreach ($list as $key=>$val){
                $result[$key]['day_time'] = date("Y-m-d",strtotime($val['day_time']));
                $result[$key]['session_cnt'] = $val['session_cnt'];
                $result[$key]['visit_pv'] = $val['visit_pv'];
                $result[$key]['visit_uv'] = $val['visit_uv'];
                $result[$key]['visit_uv_new'] = $val['visit_uv_new'];
                $result[$key]['stay_time_uv'] = $val['stay_time_uv'];
                $result[$key]['stay_time_session'] = $val['stay_time_session'];
                $result[$key]['visit_depth'] = $val['visit_depth'];
            }
        }else{
            $key=0;
            for($start_day;$start_day<=$end_day;$start_day=($start_day+ 3600*24)){
                $result[$key]['day_time'] = date("Y-m-d",$start_day);
                $result[$key]['session_cnt'] = 0;
                $result[$key]['visit_pv'] = 0;
                $result[$key]['visit_uv'] = 0;
                $result[$key]['visit_uv_new'] = 0;
                $result[$key]['stay_time_uv'] = 0;
                $result[$key]['stay_time_session'] = 0;
                $result[$key]['visit_depth'] = 0;
                $key++;
            }
        }
        $data['errcode'] = 0;

        $data['data'] = $result;
        return Response::json($data);
    }

    //获取访问分布
    public function getVisitDistribution(){
        if (!empty($this->params['startdate'])) {  //创建时间

            $start_day = strtotime($this->params['startdate']);

        }else{

            $start_day = strtotime($this->today) - 7*3600*24;
        }

        if (!empty($this->params['enddate'])) {  //结束时间

            $end_day =  strtotime($this->params['enddate']);

        }else{

            $end_day = strtotime($this->today) - 3600*24;
        }

//        $merchant_id = isset($this->params['merchant_id']) && $this->params['merchant_id'] ? $this->params['merchant_id'] : 0;
        $appid = isset($this->params['appid']) && $this->params['appid'] ? $this->params['appid'] : '';
        $type = isset($this->params['type']) && $this->params['type'] ? $this->params['type'] : 0;
        $wheres = array(

            array('column' => 'day_time', 'value' => date("Ymd",$end_day), 'operator' => '<='),
            array('column' => 'day_time', 'value' => date("Ymd",$start_day), 'operator' => '>='),
            array('column' => 'merchant_id', 'value' => $this->merchant_id, 'operator' => '=')
        );
        if($appid){
            $wheres[]=array('column' => 'appid', 'value' => $appid, 'operator' => '=');
        }
        if($type){
            $wheres[]=array('column' => 'type', 'value' => $type, 'operator' => '=');
        }
        $fields="day_time,merchant_id,appid,type,scene_id,sum(scene_value) as scene_value";
        $query = XcxVisitDistributionDaily::select(\DB::raw($fields));
        foreach($wheres as $where) {
            $query->where($where['column'], $where['operator'], $where['value']);
        }
        $query->groupBy("scene_id");
        $list = $query->orderBy('day_time', 'asc')->get();
        $result = array();
        $xcxstatics = Config::get('xcxstatics') ? Config::get('xcxstatics') : '';
        if($list){
            foreach ($list as $key=>$val){
                $result[$val['scene_id']]['scene_value'] = $val['scene_value'];
            }
        }
        $result_data=array();
        if($type){
            $key=0;
            $sort_result = array();
            foreach ($xcxstatics[$type] as $k=>$v){
                if(!isset($result[$k])){
                    $result_data[$key]['scene_name']=$v;
                    $result_data[$key]['scene_value']=0;
                }else{
                    $result_data[$key]['scene_name']=$v;
                    $result_data[$key]['scene_value']=$result[$k]['scene_value'];
                }
                $sort_result[]=$result_data[$key]['scene_value'];
                $key++;
            }
        }
        if($type==1){
            array_multisort($sort_result, SORT_DESC, $result_data);
            $result_data=array_slice($result_data,0,8);
        }

        $data['errcode'] = 0;

        $data['data'] = $result_data;
        return Response::json($data);
    }

    //获取访问页面
    public function getVisitPage(){
        if (!empty($this->params['startdate'])) {  //创建时间

            $start_day = strtotime($this->params['startdate']);

        }else{

            $start_day = strtotime($this->today) - 7*3600*24;
        }

        if (!empty($this->params['enddate'])) {  //结束时间

            $end_day =  strtotime($this->params['enddate']);

        }else{

            $end_day = strtotime($this->today) - 3600*24;
        }
        $offset = isset($this->params['offset']) && $this->params['offset'] ? $this->params['offset'] : 0;
        $limit = isset($this->params['limit']) && $this->params['limit'] ? $this->params['limit'] : 20;
//        $merchant_id = isset($this->params['merchant_id']) && $this->params['merchant_id'] ? $this->params['merchant_id'] : 0;
        $appid = isset($this->params['appid']) && $this->params['appid'] ? $this->params['appid'] : '';

        $wheres = array(

            array('column' => 'day_time', 'value' => date("Ymd",$end_day), 'operator' => '<='),
            array('column' => 'day_time', 'value' => date("Ymd",$start_day), 'operator' => '>='),
            array('column' => 'merchant_id', 'value' => $this->merchant_id, 'operator' => '=')
        );
        if($appid){
            $wheres[]=array('column' => 'appid', 'value' => $appid, 'operator' => '=');
        }
        $fields="day_time,merchant_id,appid,page_path,page_visit_pv,page_visit_uv,page_staytime_pv,entrypage_pv,exitpage_pv,page_share_pv,page_share_uv";

        $list=XcxVisitPageDaily::get_data_list($wheres,$fields,$offset,$limit);
        $count=XcxVisitPageDaily::get_data_count($wheres);
        $data['errcode'] = 0;
        $data['_count'] = $count['num'];
        $data['data'] = $list;
        return Response::json($data);
    }

    //获取访问留存
    public function getVisitRetain(){
        if (!empty($this->params['startdate'])) {  //创建时间

            $start_day = strtotime($this->params['startdate']);

        }else{

            $start_day = strtotime($this->today) - 7*3600*24;
        }

        if (!empty($this->params['enddate'])) {  //结束时间

            $end_day =  strtotime($this->params['enddate']);

        }else{

            $end_day = strtotime($this->today) - 3600*24;
        }
        $offset = isset($this->params['offset']) && $this->params['offset'] ? $this->params['offset'] : 0;
        $limit = isset($this->params['limit']) && $this->params['limit'] ? $this->params['limit'] : 20;
//        $merchant_id = isset($this->params['merchant_id']) && $this->params['merchant_id'] ? $this->params['merchant_id'] : 0;
        $appid = isset($this->params['appid']) && $this->params['appid'] ? $this->params['appid'] : '';

        $wheres = array(

            array('column' => 'day_time', 'value' => date("Ymd",$end_day), 'operator' => '<='),
            array('column' => 'day_time', 'value' => date("Ymd",$start_day), 'operator' => '>='),
            array('column' => 'merchant_id', 'value' => $this->merchant_id, 'operator' => '=')
        );
        if($appid){
            $wheres[]=array('column' => 'appid', 'value' => $appid, 'operator' => '=');
        }
        $fields="day_time,merchant_id,appid,type,visit_key,visit_value";

        $list=XcxVisitRetainDaily::get_data_list($wheres,$fields,$offset,$limit);
        $count=XcxVisitRetainDaily::get_data_count($wheres);
        $data['errcode'] = 0;
        $data['_count'] = $count['num'];
        $data['data'] = $list;
        return Response::json($data);
    }

    //用户画像
    public function getUserPortrait(){
        if (!empty($this->params['startdate'])) {  //创建时间

            $start_day = strtotime($this->params['startdate']);

        }else{

            $start_day = strtotime($this->today) - 1*3600*24;
        }

        if (!empty($this->params['enddate'])) {  //结束时间

            $end_day =  strtotime($this->params['enddate']);

        }else{

            $end_day = strtotime($this->today) - 3600*24;
        }

//        $merchant_id = isset($this->params['merchant_id']) && $this->params['merchant_id'] ? $this->params['merchant_id'] : 0;
        $appid = isset($this->params['appid']) && $this->params['appid'] ? $this->params['appid'] : '';
        $type = isset($this->params['type']) && $this->params['type'] ? $this->params['type'] : 0;
        $attribute = isset($this->params['attribute']) && $this->params['attribute'] ? $this->params['attribute'] : 0;
        $wheres = array(

            array('column' => 'day_time', 'value' => date("Ymd",$end_day), 'operator' => '<='),
            array('column' => 'day_time', 'value' => date("Ymd",$start_day), 'operator' => '>='),
            array('column' => 'merchant_id', 'value' => $this->merchant_id, 'operator' => '=')
        );
        if($appid){
            $wheres[]=array('column' => 'appid', 'value' => $appid, 'operator' => '=');
        }
        if($type){
            $wheres[]=array('column' => 'type', 'value' => $type, 'operator' => '=');
        }
        if($attribute){
            $wheres[]=array('column' => 'attribute', 'value' => $attribute, 'operator' => '=');
        }
        $fields="day_time,merchant_id,appid,type,attribute,attribute_id,attribute_name,sum(attribute_value) as attribute_value";
        $query = XcxUserPortraitDaily::select(\DB::raw($fields));
        foreach($wheres as $where) {
            $query->where($where['column'], $where['operator'], $where['value']);
        }
        $query->groupBy("type","attribute_id");
        $list = $query->orderBy('day_time', 'desc')->get()->toArray();
        $result = array();
        if($list){
            $sort_reslut = array();
            $sort_reslut_a = array();
            foreach ($list as $key=>$val){
                $result[$key]['type'] = $val['type'];
                $result[$key]['attribute'] = $val['attribute'];
                $result[$key]['attribute_id'] = $val['attribute_id'];
                if($attribute==1){
                    $result[$key]['attribute_name'] = str_replace("省","",$val['attribute_name']);
                }else{
                    $result[$key]['attribute_name'] = $val['attribute_name'];
                }
                $result[$key]['attribute_value'] = $val['attribute_value'];
                $sort_reslut[]=$val['attribute_value'];
                $sort_reslut_a[]=$val['attribute_id'];
            }
            if($attribute==1){
                array_multisort($sort_reslut, SORT_DESC, $result);
            }else{
                array_multisort($sort_reslut_a, SORT_ASC, $result);
            }
        }else{ //初始化数据
            if($attribute==3){
                $result[0]['type'] = $type;
                $result[0]['attribute'] = $attribute;
                $result[0]['attribute_id'] = 0;
                $result[0]['attribute_name'] = '未知';
                $result[0]['attribute_value'] = 0;
                $result[1]['type'] = $type;
                $result[1]['attribute'] = $attribute;
                $result[1]['attribute_id'] = 1;
                $result[1]['attribute_name'] = '男';
                $result[1]['attribute_value'] = 0;
                $result[2]['type'] = $type;
                $result[2]['attribute'] = $attribute;
                $result[2]['attribute_id'] = 2;
                $result[2]['attribute_name'] = '女';
                $result[2]['attribute_value'] = 0;
            }elseif($attribute==6){
                $result[0]['type'] = $type;
                $result[0]['attribute'] = $attribute;
                $result[0]['attribute_id'] = 0;
                $result[0]['attribute_name'] = '未知';
                $result[0]['attribute_value'] = 0;
                $result[1]['type'] = $type;
                $result[1]['attribute'] = $attribute;
                $result[1]['attribute_id'] = 1;
                $result[1]['attribute_name'] = '17岁以下';
                $result[1]['attribute_value'] = 0;
                $result[2]['type'] = $type;
                $result[2]['attribute'] = $attribute;
                $result[2]['attribute_id'] = 2;
                $result[2]['attribute_name'] = '18-24岁';
                $result[2]['attribute_value'] = 0;
                $result[3]['attribute'] = $attribute;
                $result[3]['attribute_id'] = 3;
                $result[3]['attribute_name'] = '25-29岁';
                $result[3]['attribute_value'] = 0;
                $result[4]['type'] = $type;
                $result[4]['attribute'] = $attribute;
                $result[4]['attribute_id'] = 4;
                $result[4]['attribute_name'] = '30-39岁';
                $result[4]['attribute_value'] = 0;
                $result[5]['type'] = $type;
                $result[5]['attribute'] = $attribute;
                $result[5]['attribute_id'] = 5;
                $result[5]['attribute_name'] = '40-49岁';
                $result[5]['attribute_value'] = 0;
                $result[6]['type'] = $type;
                $result[6]['attribute'] = $attribute;
                $result[6]['attribute_id'] = 6;
                $result[6]['attribute_name'] = '50岁以上';
                $result[6]['attribute_value'] = 0;

            }
        }

        $data['errcode'] = 0;

        $data['data'] = $result;
        if($result){
            $data['max_attribute_value'] = $result[0]['attribute_value'];
        }else{
            $data['max_attribute_value'] = 0;
        }


        return Response::json($data);
    }


    public function getOrderHour(){
        if (!empty($this->params['startdate'])) {  //创建时间
            $start_day = strtotime($this->params['startdate']);
        }else{
            $start_day = '';
        }
        if (!empty($this->params['enddate'])) {  //结束时间
            $end_day =  strtotime($this->params['enddate']);
        }else{
            $end_day = '';
        }
        $wheres = array(
            array('column' => 'merchant_id', 'value' => $this->merchant_id, 'operator' => '=')
        );
        if($start_day){
            $wheres[]=array('column' => 'created_time', 'value' => date("Y-m-d",$start_day), 'operator' => '>=');
        }
        if($end_day){
            $wheres[]=array('column' => 'created_time', 'value' => date("Y-m-d",$end_day), 'operator' => '<=');
        }
        $appid = isset($this->params['appid']) && $this->params['appid'] ? $this->params['appid'] : '';
        if($appid){
            $wheres[]=array('column' => 'appid', 'value' => $appid, 'operator' => '=');
        }
        $fields = 'DISTINCT order_sn,merchant_id,created_time';
        $query = OrderInfo::select(\DB::raw($fields));
        foreach($wheres as $where) {
            $query->where($where['column'], $where['operator'], $where['value']);
        }
        $query->orderBy('created_time','asc');
        $list = $query->get()->toArray();
        if($list) {
            $create_data = array();
            foreach ($list as $key => $val) {
                $hour = date("H", strtotime($val["created_time"]));
                $hour = (int)$hour;
                if (!isset($create_data[$hour])) {
                    $create_data[$hour] = 1;
                } else {
                    $create_data[$hour]++;
                }
            }
        }
        for($i=0;$i<=23;$i++){
            if(!isset($create_data[$i])){
                $create_data[$i]=0;
            }
        }
        $result=array();
        if($create_data){
            ksort($create_data);
            foreach($create_data as $key=>$val){
                $result[]=$val;
            }
        }

        $data['errcode'] = 0;

        $data['data'] = $result;

        return Response::json($data);
    }


}
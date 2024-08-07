<?php
/**
 * Created by PhpStorm.
 * User: wangshiliang@dodoca.com
 * Date: 2017/8/30
 * Time: 14:50
 * Desc: 微信统计数据同步
 */
namespace App\Services;

use App\Models\WeixinInfo;
use App\Models\XcxSurveyDaily;
use App\Models\XcxUserPortraitDaily;
use App\Models\XcxVisitDaily;
use App\Models\XcxVisitDistributionDaily;
use App\Models\XcxVisitPageDaily;
use App\Models\XcxVisitRetainDaily;
use App\Utils\Weixin\Statics;
use Mockery\Exception;

class WeixinDataService
{
    private $day ;
    private $weixinService;
    private $staticHttp;


    public function __construct()
    {
        $this->day = date("Ymd", strtotime("-1 day"));
        $this->weixinService = new WeixinService();
        $this->staticHttp = new Statics();
    }

    /**
     * @name 微信数据同步 执行
     * @return bool
     */
    public function readyGo($callback, $page = 0){
        $limit = 1000;
        $counter = 0;
        $applist = WeixinInfo:: query()->where(['auth'=>1,'status'=>1])->where('appid','!=','')->skip($page*$limit)->take($limit)->get(['id','merchant_id','appid'])->toArray();
        foreach ($applist as $key => $val) {
            $counter ++ ;
            //最近四天
            for($i = 1 ;$i < 5 ; $i ++){
                $this->day = date("Ymd", strtotime("-".$i." day"));
                try{
                    $response = $this->$callback($val);
                }catch (Exception $e){
                    $response =  true;
                }
                if($response !== true){ //接口请求报错
                    if((isset($response['errcode']) && in_array($response['errcode'],[48001,-1,41001])) || isset($response['list']) && empty( $response['list']) ){
                        //48001 没有借口权限
                        //-1 系统错误
                        //41001 accesstoken 有误
                        //list = [] 内容为空
                    }else{
                        $this->weixinService->setLog('WxAnalysis_'.$callback,['day_time'=>$this->day], $response,$val['merchant_id'],$val['appid']);
                    }
                }
            }
        }
        if($counter < $limit){
            return true;
        }else{
            return $this->readyGo($callback, ++$page);
        }

    }


    /**
     * @name 概况趋势  XcxSurveyDailyStatistics
     * @return bool
     */
    private function summaryTrend($val){
        $count = XcxSurveyDaily::query()->where(['day_time'=>$this->day,'merchant_id'=>$val['merchant_id'],'appid'=>$val['appid']])->count();
        if($count > 0){ //已存在 跳过
            return true;
        }
        $this->staticHttp->setAccessToken($this->weixinService->getAccessToken($val['appid']));
        $response = $this->staticHttp->getweanalysisappiddailysummarytrend( ['begin_date' => $this->day, 'end_date' => $this->day]);
        if(isset($response['errcode']) && $response['errcode'] != 0){//接口请求报错
            return $response;
        }
        if(!isset($response['list'][0])){
            return $response;
        }else{
            $response = $response['list'][0];
        }
        XcxSurveyDaily::insert([
            'day_time'    => $this->day,
            'merchant_id' => $val['merchant_id'],
            'appid'       => $val['appid'],
            'visit_total' => $response['visit_total'],
            'share_pv'    => $response['share_pv'],
            'share_uv'    => $response['share_uv'],
            'created_time'=> date("Y-m-d H:i:s")
        ]);
        return true;
    }
    private function summaryTrendData($val){
        XcxSurveyDaily::insert([
            'day_time'    => $this->day,
            'merchant_id' => $val['merchant_id'],
            'appid'       => $val['appid'],
            'visit_total' => 0,
            'share_pv'    => 0,
            'share_uv'    => 0,
            'created_time'=> date("Y-m-d H:i:s")
        ]);
    }

    /**
     * @name  用户画像  XcxUserPortraitDailyStatistics
     * @return bool
     */
    private function userPortrait($val){
        $count = XcxUserPortraitDaily::query()->where(['day_time'=>$this->day,'merchant_id'=>$val['merchant_id'],'appid'=>$val['appid']])->count();
        if($count > 0){ //已存在 跳过
            return true;
        }
        $day = substr($this->day,0,4).'-'.substr($this->day,4,2).'-'.substr($this->day,6,2);
        $this->staticHttp->setAccessToken($this->weixinService->getAccessToken($val['appid']));
        $response = $this->staticHttp->getweanalysisappiduserportrait( ['begin_date' => $day, 'end_date' => $day]);
        if(isset($response['errcode']) && $response['errcode'] != 0){
            return $response;
        }
        foreach ($response['visit_uv_new'] as $ks => $vs) {
            $attribute=0;
            switch ($ks){
                case 'province':$attribute=1;break;
                case 'city':$attribute=2;break;
                case 'genders':$attribute=3;break;
                case 'platforms':$attribute=4;break;
                case 'devices':$attribute=5;break;
                case 'ages':$attribute=6;break;
            }
            foreach ($vs as $k=>$v) {
                XcxUserPortraitDaily::insert([
                    'day_time'       => $this->day,
                    'merchant_id'    => $val['merchant_id'],
                    'appid'          => $val['appid'],
                    'type'           => 1,
                    'attribute'      => $attribute,
                    'attribute_id'   => isset($v['id'])?$v['id']:0,
                    'attribute_name' => $v['name'],
                    'attribute_value'=> $v['value'],
                    'created_time'   => date("Y-m-d H:i:s")
                ]);
            }
        }
        foreach ($response['visit_uv'] as $ks => $vs) {
            switch ($ks){
                case 'province':$attribute=1;break;
                case 'city':$attribute=2;break;
                case 'genders':$attribute=3;break;
                case 'platforms':$attribute=4;break;
                case 'devices':$attribute=5;break;
                case 'ages':$attribute=6;break;
            }
            foreach ($vs as $k=>$v) {
                XcxUserPortraitDaily::insert([
                    'day_time'       => $this->day,
                    'merchant_id'    => $val['merchant_id'],
                    'appid'          => $val['appid'],
                    'type'           => 2,
                    'attribute'      => $attribute,
                    'attribute_id'   => isset($v['id'])?$v['id']:0,
                    'attribute_name' => $v['name'],
                    'attribute_value'=> $v['value'],
                    'created_time'   => date("Y-m-d H:i:s")
                ]);
            }
        }
        return true;
    }
    private function userPortraitData($val){
        $data = [
            1 => ['id'=>'11','name'=>"上海"],
            2 => ['id'=>'11','name'=>"上海"],
            3 => ['id'=>'0','name'=>"未知"],
            4 => ['id'=>'1','name'=>"iPhone"],
            5 => ['id'=>'0','name'=>"未知"],
            6 => ['id'=>'1','name'=>'17岁以下']
        ];
        for($i = 1 ; $i <= 2 ; $i++ ){
            foreach ($data as $k => $v){
                XcxUserPortraitDaily::insert([
                    'day_time'       => $this->day,
                    'merchant_id'    => $val['merchant_id'],
                    'appid'          => $val['appid'],
                    'type'           => $i,
                    'attribute'      => $k,
                    'attribute_id'   => $v['id'],
                    'attribute_name' => $v['name'],
                    'attribute_value'=> 0,
                    'created_time'   => date("Y-m-d H:i:s")
                ]);
            }
        }
    }

    /**
     * @name  访问趋势  XcxVisitDailyStatistics
     * @return bool
     */
    private function visitTrend($val){
        $count = XcxVisitDaily::query()->where(['day_time'=>$this->day,'merchant_id'=>$val['merchant_id'],'appid'=>$val['appid']])->count();
        if($count > 0){ //已存在 跳过
            return true;
        }
        $this->staticHttp->setAccessToken($this->weixinService->getAccessToken($val['appid']));
        $response = $this->staticHttp->getweanalysisappiddailyvisittrend( ['begin_date' => $this->day, 'end_date' => $this->day]);
        if(isset($response['errcode']) && $response['errcode'] != 0){//接口请求报错
            return $response;
        }
        if(!isset($response['list'][0])){
            return $response;
        }
        $response = $response['list'][0];
        XcxVisitDaily::insert([
            'day_time'          => $this->day,
            'merchant_id'       => $val['merchant_id'],
            'appid'             => $val['appid'],
            'session_cnt'       => $response['session_cnt'],
            'visit_pv'          => $response['visit_pv'],
            'visit_uv'          => $response['visit_uv'],
            'visit_uv_new'      => $response['visit_uv_new'],
            'stay_time_uv'      => isset($response['stay_time_uv'])? round($response['stay_time_uv'],2) : 0,
            'stay_time_session' => isset($response['stay_time_session'])? round($response['stay_time_session'],2) : 0,
            'visit_depth'       => isset($response['visit_depth'])? round($response['visit_depth'],2) : 0,
            'created_time'      => date("Y-m-d H:i:s")
        ]);
        return true;
    }
    private function visitTrendData($val){
        XcxVisitDaily::insert([
            'day_time'          => $this->day,
            'merchant_id'       => $val['merchant_id'],
            'appid'             => $val['appid'],
            'session_cnt'       => 0,
            'visit_pv'          => 0,
            'visit_uv'          => 0,
            'visit_uv_new'      => 0,
            'stay_time_uv'      => 0,
            'stay_time_session' => 0,
            'visit_depth'       => 0,
            'created_time'      => date("Y-m-d H:i:s")
        ]);
    }

    /**
     * @name  访问分布  XcxVisitDistributionDailyStatistics
     * @return bool
     */
    private function visitDistribution($val){
        $count = XcxVisitDistributionDaily::query()->where(['day_time'=>$this->day,'merchant_id'=>$val['merchant_id'],'appid'=>$val['appid']])->count();
        if($count > 0){ //已存在 跳过
            return true;
        }
        $this->staticHttp->setAccessToken($this->weixinService->getAccessToken($val['appid']));
        $response = $this->staticHttp->getweanalysisappidvisitdistribution( ['begin_date' => $this->day, 'end_date' => $this->day]);
        if(isset($response['errcode']) && $response['errcode'] != 0){//接口请求报错
            return $response;
        }
        foreach ($response['list'] as $ks => $vs) {
            if (!isset($vs['item_list'])) {
                continue;
            }
            switch ($vs['index']){
                case 'access_source_session_cnt': $type = 1;break;
                case 'access_staytime_info': $type = 2;break;
                case 'access_depth_info': $type = 3;break;
                default : $type = 0;
            }
            foreach ($vs['item_list'] as $k => $v) {
                XcxVisitDistributionDaily::insert([
                    'day_time'     => $this->day,
                    'merchant_id'  => $val['merchant_id'],
                    'appid'        => $val['appid'],
                    'type'         => $type,
                    'scene_id'     => $v['key'],
                    'scene_value'  => $v['value'],
                    'created_time' => date("Y-m-d H:i:s"),
                ]);
            }
        }
        return true;
    }
    private function visitDistributionData($val){
        for($i = 1; $i <= 3 ; $i++ ){
            XcxVisitDistributionDaily::insert([
                'day_time'     => $this->day,
                'merchant_id'  => $val['merchant_id'],
                'appid'        => $val['appid'],
                'type'         => $i,
                'scene_id'     => 1,
                'scene_value'  => 1,
                'created_time' => date("Y-m-d H:i:s"),
            ]);
        }
    }

    /**
     * @name  访问页面 XcxVisitPageDailyStatistics
     * @return bool
     */
    private function visitPage($val){
        $count = XcxVisitPageDaily::query()->where(['day_time'=>$this->day,'merchant_id'=>$val['merchant_id'],'appid'=>$val['appid']])->count();
        if($count > 0){ //已存在 跳过
            return true;
        }
        $this->staticHttp->setAccessToken($this->weixinService->getAccessToken($val['appid']));
        $response = $this->staticHttp->getweanalysisappidvisitpage( ['begin_date' => $this->day, 'end_date' => $this->day]);
        if(isset($response['errcode']) && $response['errcode'] != 0){//接口请求报错
            return $response;
        }
        foreach ($response['list'] as $k => $v) {
            XcxVisitPageDaily::insert([
                'day_time' => $this->day,
                'merchant_id' => $val['merchant_id'],
                'appid' => $val['appid'],
                'page_path' => $v['page_path'],
                'page_visit_pv' => $v['page_visit_pv'],
                'page_visit_uv' => $v['page_visit_uv'],
                'page_staytime_pv' => $v['page_staytime_pv'],
                'entrypage_pv' => $v['entrypage_pv'],
                'exitpage_pv' => $v['exitpage_pv'],
                'page_share_pv' => $v['page_share_pv'],
                'page_share_uv' => $v['page_share_uv'],
                'created_time' => date("Y-m-d H:i:s")
            ]);
        }
        return true;
    }
    private function visitPageData($val){
        XcxVisitPageDaily::insert([
            'day_time' => $this->day,
            'merchant_id' => $val['merchant_id'],
            'appid' => $val['appid'],
            'page_path' => 'pages/decorate/decorate',
            'page_visit_pv' => 0,
            'page_visit_uv' => 0,
            'page_staytime_pv' => 0,
            'entrypage_pv' => 0,
            'exitpage_pv' => 0,
            'page_share_pv' => 0,
            'page_share_uv' => 0,
            'created_time' => date("Y-m-d H:i:s")
        ]);
    }

    /**
     * @name  访问留存 XcxVisitRetainDailyStatistics
     * @return bool
     */
    private function dailyRetain($val){
        $count = XcxVisitRetainDaily::query()->where(['day_time'=>$this->day,'merchant_id'=>$val['merchant_id'],'appid'=>$val['appid']])->count();
        if($count > 0){ //已存在 跳过
            return true;
        }
        $this->staticHttp->setAccessToken($this->weixinService->getAccessToken($val['appid']));
        $response = $this->staticHttp->getweanalysisappiddailyretaininfo( ['begin_date' => $this->day, 'end_date' => $this->day]);
        if(isset($response['errcode']) && $response['errcode'] != 0){//接口请求报错
            return $response;
        }
        foreach ($response['visit_uv_new'] as $k => $v) {
            XcxVisitRetainDaily::insert([
                'day_time' => $this->day,
                'merchant_id' => $val['merchant_id'],
                'appid' => $val['appid'],
                'type' => 1,
                'visit_key' => $v['key'],
                'visit_value' => $v['value'],
                'created_time' => date("Y-m-d H:i:s"),
            ]);
        }
        foreach ($response['visit_uv'] as $k => $v) {
            XcxVisitRetainDaily::insert([
                'day_time' => $this->day,
                'merchant_id' => $val['merchant_id'],
                'appid' => $val['appid'],
                'type' => 2,
                'visit_key' => $v['key'],
                'visit_value' => $v['value'],
                'created_time' => date("Y-m-d H:i:s"),
            ]);
        }
        return true;
    }
    private function dailyRetainData($val){
        for($i = 1; $i <= 2 ; $i ++ ){
            XcxVisitRetainDaily::insert([
                'day_time' => $this->day,
                'merchant_id' => $val['merchant_id'],
                'appid' => $val['appid'],
                'type' => 1,
                'visit_key' => 0,
                'visit_value' => 0,
                'created_time' => date("Y-m-d H:i:s"),
            ]);
        }
    }

}
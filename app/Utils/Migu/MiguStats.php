<?php
/**
 * Created by PhpStorm.
 * User: wangshiliang@dodoca.com
 * Date: 2018/4/19
 * Time: 14:17
 */

namespace App\Utils\Migu;

use App\Utils\Weixin\Http;

class MiguStats
{
    const URL = 'http://api.migucloud.com';

    private $uid;
    private $token;
    public $requestUrl;
    public $requestDate;

    public function __construct($uid,$token)
    {
        $this->uid = $uid;
        $this->token = $token;
    }


    /**
     * @name 直播实时在线人数
     * @param $type 1 直播 2 录播
     * @param $id   $type = 1  channelId值 $type = 2  vid值, 以“;”隔开,不传默认查所有
     * @param $beginTime =0 开始时间 1505984400 $time不存在有效
     * @param $endTime = 0 结束时间  1505984400 $time不存在有效
     * @param $time = 0  时间 1505984400000
     * @param $pageNum = 1 页码
     * @param $pageSize = 10 每页记录数
     * @param $particle=1  时间粒度 0 = 5分钟粒度；    1 = 1分钟粒度
     * @return array [
     *   'ret'=>'',
     *   'msg'=>'success',
     *   'result'=>[
     *     'pageNum'=>1,,
     *     'pageSize'=>10,
     *     'totalElements'=>1,
     *     'totalPages'=1,
     *     'content'=>[ 0=>[
     *         'channel'=>'','datas'=>[0=>['num'=>'在线人数','time'=>'']]]
     *     ]
     *   ]
     * ]
     */
    public function listUsersOnline($type, $id, $beginTime = 0, $endTime = 0,$time = 0, $pageNum = 1, $pageSize = 10, $particle = 1){
        $data = [];
        if(empty($id) || ($type != 1 && $type != 2)){
            return ['ret'=>'-1','msg'=>'id param error'];
        }
        if( $type == 1){
            $id = array_map(function($v){ return substr($v,-8); },explode(';',$id));
            $data['platform'] = 'LIVE';
            $data['channel'] = implode(';',$id);
        }else{
            $data['platform'] = 'VOD';
            $data['vid'] = $id;
        }
        if($beginTime > 0 &&  $endTime > 0){
            $data['beginTime'] = $beginTime*1000;
            $data['endTime'] = $endTime*1000;
        }elseif($time > 0){
            $data['time'] = $time ;
        }else {
            return ['ret'=>'-1','msg'=>'time param error'];
        }
        $data['pageNum'] = $pageNum < 1 ? 1 : $pageNum;
        $data['pageSize'] = $pageSize ;
        $data['type'] = $particle == 1 ? 1 : 0;
        return $this->mxCurl(static::URL.'/stats/biz/listUsersonline?uid='.$this->uid.'&token='.$this->token,json_encode($data));
    }

    /**
     * @name 播放次数
     * @param $vids 视频Id 多个视频id以“;”隔开，默认查询所有
     * @param $beginTime 开始时间
     * @param $endTime 结束时间
     * @param $order  排序  0是降序,1是升序
     * @param $pageNum = 1 页码
     * @param $pageSize = 1  每页记录
     * @return array [
     *   'ret'=>'',
     *   'msg'=>'success',
     *   'result'=>[
     *     'pageNum'=>1,,
     *     'pageSize'=>10,
     *     'totalElements'=>1,
     *     'totalPages'=1,
     *     'content'=>[
     *       'single'=>[
     *          0=>[ 'vid' => '', 'datas' => [ 0 => ['hit'=>'播放次数','time'=>''] ] ]
     *       ],
     *       'total'=>[
     *          'vids' =>'视频id'
     *         'total'=>'总播放次数'
     *         'average'=>'日均请求次数',
     *         'datas'=>[0=>['time'=>'','totalHit'=>'总播放次数']]
     *       ]
     *     ]
     *   ]
     * ]
     */
    public function listPlayTimes($vids, $beginTime, $endTime, $order = 1 , $pageNum=1, $pageSize = 10){
        if(empty($vids)){
            return ['ret'=>'-1','msg'=>'id param error'];
        }
        $data = ['platform'=>'VOD','vid'=>$vids , 'flag' => $order , 'pageNum' => $pageNum , 'pageSize' => $pageSize];
        if(!empty($beginTime)){
            $data['beginTime'] = $beginTime;
        }
        if(!empty($endTime)){
            $data['endTime'] = $endTime;
        }
        $data['pageNum'] = $pageNum < 1 ? 1 : $pageNum;
        $data['pageSize'] = $pageSize ;
        return $this->mxCurl(static::URL.'/stats/biz/listPlayTimes?uid='.$this->uid.'&token='.$this->token,json_encode($data));
    }

    /**
     * @name 流量
     * @param $type 1 直播 2 录播
     * @param $id   $type = 1  channelId值 $type = 2  vid值, 以“;”隔开,不传默认查所有
     * @param $beginTime =0 开始时间 1505984400 $time不存在有效
     * @param $endTime = 0 结束时间 1505984400 $time不存在有效
     * @param $pageNum = 1 页码
     * @param $pageSize = 10 每页记录数
     * @return array [
     *   'ret'=>'',
     *   'msg'=>'success',
     *   'result'=>[
     *     'pageNum'=>1,,
     *     'pageSize'=>10,
     *     'totalElements'=>1,
     *     'totalPages'=1,
     *     'content'=>[
     *       'single'=>[
     *          0=>[ 'vid' => '', 'datas' => [ 0 => ['flow'=>'播放流量','time'=>''] ] ]
     *       ],
     *       'total'=>[
     *          'vids' =>'视频id'
     *         'total'=>'总播放流量'
     *         'average'=>'日均请求次数',
     *         'datas'=>[0=>['time'=>'','totalFlow'=>'总播放次数']]
     *       ]
     *     ]
     *   ]
     * ]
     */
    public function listFlow($type, $id, $beginTime = 0, $endTime = 0, $pageNum = 1, $pageSize = 10){
        $data = [];
        if(empty($id)  || ($type != 1 && $type != 2) ){
            return ['ret'=>'-1','msg'=>'id param error'];
        }
        if( $type == 1){
            $id = array_map(function($v){ return substr($v,-8); },explode(';',$id));
            $data['platform'] = 'LIVE';
            $data['channel'] =  implode(';',$id);
        }else{
            $data['platform'] = 'VOD';
            $data['vid'] = $id;
        }
        if(!empty($beginTime)){
            $data['beginTime'] = $beginTime*1000;
        }
        if(!empty($endTime)){
            $data['endTime'] = $endTime*1000;
        }
        $data['pageNum'] = $pageNum < 1 ? 1 : $pageNum;
        $data['pageSize'] = $pageSize ;
        return $this->mxCurl(static::URL.'/stats/biz/listFlow?uid='.$this->uid.'&token='.$this->token,json_encode($data));
    }

    /**
     * @name 	带宽
     * @param $type 1 直播 2 录播
     * @param $id   $type = 1  channelId值 $type = 2  vid值, 以“;”隔开,不传默认查所有
     * @param $beginTime =0 开始时间 1505984400 $time不存在有效
     * @param $endTime = 0 结束时间 1505984400 $time不存在有效
     * @param $pageNum = 1 页码
     * @param $pageSize = 10 每页记录数
     * @return array [
     *   'ret'=>'',
     *   'msg'=>'success',
     *   'result'=>[
     *     'pageNum'=>1,,
     *     'pageSize'=>10,
     *     'totalElements'=>1,
     *     'totalPages'=1,
     *     'content'=>[
     *       'single'=>[
     *          0=>[ 'vid' => '', 'datas' => [ 0 => ['bandwidth'=>'播放带宽','time'=>''] ] ]
     *       ],
     *       'total'=>[
     *          'vids' =>'视频id'
     *         'total'=>'总播放流量'
     *         'average'=>'日均请求次数',
     *         'datas'=>[0=>['time'=>'','peakBandwidth'=>'总播放带宽']]
     *       ]
     *     ]
     *   ]
     * ]
     */
    public function listBandwidth($type, $id, $beginTime = 0, $endTime = 0, $pageNum = 1, $pageSize = 10){
        $data = [];
        if(empty($id)  || ($type != 1 && $type != 2) ){
            return ['ret'=>'-1','msg'=>'id param error'];
        }
        if( $type == 1){
            $id = array_map(function($v){ return substr($v,-8); },explode(';',$id));
            $data['platform'] = 'LIVE';
            $data['channel'] = implode(';',$id);
        }else{
            $data['platform'] = 'VOD';
            $data['vid'] = $id;
        }
        if(!empty($beginTime)){
            $data['beginTime'] = $beginTime*1000;
        }
        if(!empty($endTime)){
            $data['endTime'] = $endTime*1000;
        }
        $data['pageNum'] = $pageNum < 1 ? 1 : $pageNum;
        $data['pageSize'] = $pageSize ;
        return $this->mxCurl(static::URL.'/stats/biz/listBandwidth?uid='.$this->uid.'&token='.$this->token,json_encode($data));
    }

    /**
     * @name 	独立IP数
     * @param $type 1 直播 2 录播
     * @param $id   $type = 1  channelId值 $type = 2  vid值, 以“;”隔开,不传默认查所有
     * @param $beginTime =0 开始时间 1505984400 $time不存在有效
     * @param $endTime = 0 结束时间 1505984400 $time不存在有效
     * @param $pageNum = 1 页码
     * @param $pageSize = 10 每页记录数
     * @return array [
     *   'ret'=>'',
     *   'msg'=>'success',
     *   'result'=>[
     *     'pageNum'=>1,,
     *     'pageSize'=>10,
     *     'totalElements'=>1,
     *     'totalPages'=1,
     *     'content'=>[
     *              'channel' => '' , 'dhip'=>'数据集合', 'time' => ''
     *     ]
     *   ]
     * ]
     */
    public function listDhip($type, $id, $beginTime = 0, $endTime = 0, $pageNum = 1, $pageSize = 10){
        $data = [];
        if(empty($id)  || ($type != 1 && $type != 2) ){
            return ['ret'=>'-1','msg'=>'id param error'];
        }
        if( $type == 1){
            $id = array_map(function($v){ return substr($v,-8); },explode(';',$id));
            $data['platform'] = 'LIVE';
            $data['channel'] = implode(';',$id);
        }else{
            $data['platform'] = 'VOD';
            $data['vid'] = $id;
        }
        if(!empty($beginTime)){
            $data['beginTime'] = $beginTime*1000;
        }
        if(!empty($endTime)){
            $data['endTime'] = $endTime*1000;
        }
        $data['pageNum'] = $pageNum < 1 ? 1 : $pageNum;
        $data['pageSize'] = $pageSize ;
        return $this->mxCurl(static::URL.'/stats/biz/listDhip?uid='.$this->uid.'&token='.$this->token,json_encode($data));
    }

    /**
     * @name 	直播  最大在线观看人数
     * @param $channelId 值  以“;”隔开,不传默认查所有
     * @param $beginTime =0 开始时间 1505984400 $time不存在有效
     * @param $endTime = 0 结束时间 1505984400 $time不存在有效
     * @param $pageNum = 1 页码
     * @param $pageSize = 10 每页记录数
     * @return array [
     *   'ret'=>'',
     *   'msg'=>'success',
     *   'result'=>[
     *     'pageNum'=>1,,
     *     'pageSize'=>10,
     *     'totalElements'=>1,
     *     'totalPages'=1,
     *     'content'=>[
     *         0 => [ 'channel'=>'', 'num'=>'最大在线人数' ]
     *     ]
     *   ]
     * ]
     */
    public function getUseronlineMax( $channelId, $beginTime = 0, $endTime = 0, $pageNum = 1, $pageSize = 10){
        $data = [];
        if(empty($channelId) ){
            return ['ret'=>'-1','msg'=>'id param error'];
        }
        $id = array_map(function($v){ return substr($v,-8); },explode(';',$channelId));
        $data['channel'] = implode(';',$id);
        if(!empty($beginTime)){
            $data['beginTime'] = $beginTime*1000;
        }
        if(!empty($endTime)){
            $data['endTime'] = $endTime*1000;
        }
        $data['pageNum'] = $pageNum < 1 ? 1 : $pageNum;
        $data['pageSize'] = $pageSize ;
        return $this->mxCurl(static::URL.'/stats/biz/getUseronlineMax?uid='.$this->uid.'&token='.$this->token,json_encode($data));
    }

    /**
     * @name 转码时长
     * @param $type 1 直播 2 录播
     * @param $beginTime =0 开始时间 1505984400 $time不存在有效
     * @param $endTime = 0 结束时间 1505984400 $time不存在有效
     * @return array [
     *   'ret'=>'',
     *   'msg'=>'success',
     *   'result'=>[
     *     'uid'=>1,,
     *     'platform'=>10,
     *     'totalTime'=>1,
     *     'a1total'=1,
     *     'a2total'=1
     *   ]
     * ]
     */
    public function listTranferTime( $type, $beginTime = 0, $endTime = 0){
        $data = [];
        $data['platform'] = $type == 1 ? 'LIVE' : 'VOD';
        if(!empty($beginTime)){
            $data['beginTime'] = $beginTime*1000;
        }
        if(!empty($endTime)){
            $data['endTime'] = $endTime*1000;
        }
        return $this->mxCurl(static::URL.'/stats/biz/listTranferTime?uid='.$this->uid.'&token='.$this->token,json_encode($data));
    }

    /**
     * @name 2.1.8.	存储空间
     * @param $type 1 直播 2 录播
     * @param $beginTime =0 开始时间 1505984400 $time不存在有效
     * @param $endTime = 0 结束时间 150598440 $time不存在有效
     * @param $pageNum = 1 页码
     * @param $pageSize = 10 每页记录数
     * @return array [
     *   'ret'=>'',
     *   'msg'=>'success',
     *   'result'=>[
     *     'pageNum'=>1,,
     *     'pageSize'=>10,
     *     'totalElements'=>1,
     *     'totalPages'=1,
     *     'content'=>[
     *         0 => [ 'time'=>'', 'space'=>'存储大小' ]
     *     ]
     *   ]
     * ]
     */
    public function listStorage( $beginTime = 0, $endTime = 0, $pageNum = 1, $pageSize = 10){
        $data = [];
        if(empty($channelId) ){
            return ['ret'=>'-1','msg'=>'id param error'];
        }

        if(!empty($beginTime)){
            $data['beginTime'] = $beginTime*1000;
        }
        if(!empty($endTime)){
            $data['endTime'] = $endTime*1000;
        }
        $data['pageNum'] = $pageNum < 1 ? 1 : $pageNum;
        $data['pageSize'] = $pageSize ;
        return $this->mxCurl(static::URL.'/stats/biz/listStorage?uid='.$this->uid.'&token='.$this->token,json_encode($data));
    }

    private function mxCurl($url , $data , $is_post = true){
        $response = ( new Http())->mxCurl($url,$data,$is_post,['proxy'=>'false','timeout'=>20,'header'=>['Content-type: application/json;charset="utf-8"','Accept: application/json'] ]);
        $this->requestDate = $response['request']['data'];
        $this->requestUrl  = $response['request']['url']; 
        if($response['errcode'] == 0 ){
            return json_decode($response['data'],true) ;
        }else{
            $response['ret'] = '-1';
            return $response;
        }
    }

}
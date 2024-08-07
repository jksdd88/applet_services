<?php
/**
 * Created by PhpStorm.
 * User: wangshiliang@dodoca.com
 * Date: 2018/4/14
 * Time: 13:31
 */

namespace App\Utils\Migu;

use App\Utils\Weixin\Http;

class MiguLive
{
    const URL = 'http://api.migucloud.com';

    private $uid;
    private $token;

    public function __construct($uid,$token)
    {
        $this->uid = $uid;
        $this->token = $token;
    }

    /**
     * @name 创就直播
     * @param $title 直播名称 最大长度为128个字
     * @param $start 直播开始时间  时间戳
     * @param $end 直播时长 分钟
     * @param $subject 直播主题 最大长度为128个字
     * @param $desc 直播描述 最大长度为128个字
     * @param $image 默认封面
     * @param $clarity = '2' 画质 1 流畅; 2 标清; 3 高清; 4 超清; 5 音频 若表示需要转高清和超清两路，则$clarity="3,4"
     * @param $record =0  录制方式    0: 不录制;  1:录制
     * @param $demand =0  是否导入云点播 该字段仅在record非零下有效，      0: 否 1: 是
     * @param $transcode =0  是否在导入点播后自动进行离线转码 该字段仅在demand=1下有效，   0: 否;1: 是
     * @return array ["ret"=>"0"  result => [ "channelId"=> "20170405130908_EVETQIW5"] ]
     */
    public function createChannel($title, $start, $end, $subject, $desc, $image, $clarity = '2',  $record = 0 ,$demand = 0, $transcode = 0){
        $data = [
            "startTime"     => date('Y-m-d H:i:s',$start),
            "endTime"       => date('Y-m-d H:i:s',$end),
            "title"         => $title,
            "subject"       => $subject,
            "description"   => $desc,
            "liveType"      => 'push',//push: 推流直播; pull: 拉流直播
            "ingestSrc"     => "",//拉流直播源 仅当liveType为pull时有效
            "imgUrl"        => $image,
            "videoType"     => $clarity,
            "cameraNum"     => 1,//默认值1  1~4机位选择   (目前仅支持单机位)
            "record"        => $record == 1  ? 2 : 0,
            "snapshot"      => 0,//截图方式，默认为0。        0: 默认方式 1: AI截图（针对咪咕善跑业AI务）
            "demand"        => $demand ,
            "transcode"     => $transcode,
            "timeShift"     => 0,//是否支持时移           0: 否; 1: 是
            "playMode"      => 0 ,
            "delayTime"     => 0,//延时时间     该字段仅当playMode =1 时有效,单位min
            "lowDelay"      => 1,//是否开启低延时模式
            "cdnType"       => 0//CDN类型 0: 低延时CDN;1: 在线直播CDN;2: 咪咕LiveCDN
        ];
        return $this->mxCurl(static::URL.'/l2/live/createChannel?uid='.$this->uid.'&token='.$this->token,json_encode($data));
    }

    /**
     * @name 查询直播
     * @param $channelId 直播ID channelId
     * @return array ["ret"=>"0"  result => [ ... ]
     */
    public function getChannel($channelId){
        return $this->mxCurl(static::URL.'/l2/live/getChannel?uid='.$this->uid.'&token='.$this->token,['id'=>$channelId],false);
    }

    /**
     * @name 修改直播
     * @param $id  频道ID
     * @param $title 直播名称 最大长度为128个字
     * @param $start 直播开始时间  时间戳
     * @param $end 直播时长 分钟
     * @param $subject 直播主题 最大长度为128个字
     * @param $desc 直播描述 最大长度为128个字
     * @param $image 默认封面
     * @param $clarity = '2' 画质 1 流畅; 2 标清; 3 高清; 4 超清; 5 音频 若表示需要转高清和超清两路，则$clarity="3,4"
     * @param $record =0  录制方式     0: 不录制;  1:录制
     * @param $demand =0  是否导入云点播 该字段仅在record非零下有效，      0: 否 1: 是
     * @param $transcode =0  是否在导入点播后自动进行离线转码 该字段仅在demand=1下有效，   0: 否;1: 是
     * @return array ["ret"=>"0" ,"msg"=>"", result => null ]
     */
    public function updateChannel($id, $title, $start, $end, $subject, $desc, $image, $clarity = '2',  $record = 0 ,$demand = 0, $transcode = 0){
        $data = [
            'id'            => $id ,
            "startTime"     => date('Y-m-d H:i:s',$start),
            "endTime"       => date('Y-m-d H:i:s',$end),
            "title"         => $title,
            "subject"       => $subject,
            "description"   => $desc,
            "liveType"      => 'push',//push: 推流直播; pull: 拉流直播
            "ingestSrc"     => "",//拉流直播源 仅当liveType为pull时有效
            "imgUrl"        => $image,
            "videoType"     => $clarity,
            "cameraNum"     => 1,//默认值1  1~4机位选择   (目前仅支持单机位)
            "record"        => $record == 1  ? 2 : 0,
            "snapshot"      => 0,//截图方式，默认为0。        0: 默认方式 1: AI截图（针对咪咕善跑业AI务）
            "demand"        => $demand,
            "transcode"     => $transcode,
            "timeShift"     => 0,//是否支持时移           0: 否; 1: 是
            "playMode"      => 0 ,
            "delayTime"     => 0,//延时时间     该字段仅当playMode =1 时有效,单位min
            "lowDelay"      => 1,//是否开启低延时模式
            "cdnType"       => 0//CDN类型 0: 低延时CDN;1: 在线直播CDN;2: 咪咕LiveCDN
        ];
        return $this->mxCurl(static::URL.'/l2/live/updateChannel?uid='.$this->uid.'&token='.$this->token,json_encode($data));
    }

    /**
     * @name 删除直播
     * @param $channelId 直播ID channelId
     * @return array ["ret"=>"0" ,"msg"=>"", result => null ]
     */
    public function removeChannel($channelId){
        return $this->mxCurl(static::URL.'/l2/live/removeChannel?uid='.$this->uid.'&token='.$this->token,json_encode(['id'=>$channelId]));
    }

    /**
     * @name 关闭直播
     * @param $channelId 直播ID channelId
     * @return array ["ret"=>"0" ,"msg"=>"", result => null ]
     */
    public function closeChannel($channelId){
        return $this->mxCurl(static::URL.'/l2/ctrl/forbidChannel?uid='.$this->uid.'&token='.$this->token,['channelId'=>$channelId],false);
    }

    /**
     * @name 打开关闭的直播
     * @param $channelId 直播ID channelId
     * @return array ["ret"=>"0" ,"msg"=>"", result => null ]
     */
    public function openChannel($channelId){
        return $this->mxCurl(static::URL.'/l2/ctrl/resumeChannel?uid='.$this->uid.'&token='.$this->token,['channelId'=>$channelId],false);
    }

    /**
     * @name 获取直播列表
     * @param $where['title'] 直播名称
     * @param $where['subject'] 直播主题
     * @param $where['status'] 直播状态 0: 未开始;    1: 直播中;    2: 暂停;    3: 结束    若没有该字段表示查询全部结果
     * @param $where['liveType'] 当值为    push: 获取推流直播列表;    pull: 获取拉流直播列表;    没有该字段-获取全部直播列表
     * @param $where['startTime'] 查询起始时间，以频道创建时间为查询条件
     * @param $where['endTime'] 查询结束时间，以频道创建时间为查询条件
     * @param $where['ids'] 多个需要查询的直播ID通过英文逗号”,”拼接
     * @param $pageSize 每次取条目，默认10条
     * @param $pageNum  第一页值为1，默认第一页
     * @return array ["ret"=>"0"  result => [ 0 => ['totalElements'=>'用户直播总数','pageSize'=>'列表数量','pageNum'=>'页码','content'=>[ 'id','uid','randCode','imgUrl',....] ]]
     */
    public function listChannel($where = [], $pageSize = 15, $pageNum = 1){
        $where['pageSize'] = $pageSize;
        $where['pageNum'] = $pageNum;
        return $this->mxCurl(static::URL.'/l2/live/listChannel?uid='.$this->uid.'&token='.$this->token,json_encode($where));
    }

    /**
     * @name 获取直播推流地址
     * @param $channelId 直播ID
     * @return array ["ret"=>"0"  result => [ 'uid','channelId','imgUrl','cameraList' => [ 0 => ['status'=>'机位状态0未开始 1直播中 2暂停 3结束','camIndex'=>'机位编号','url'=>'推流地址']  ] ] ]
     */
    public function getPushUrl($channelId){
        return $this->mxCurl(static::URL.'/l2/addr/getPushUrl?uid='.$this->uid.'&token='.$this->token,['channel_id'=>$channelId],false);
    }

    /**
     * @name 获取播放地址
     * @param $channelId 直播ID
     * @return array ["ret"=>"0"  result => [ 'uid','channelId'=>'直播ID','imgUrl','cdnType','viewerNum','cameraList' => [  0 => ['camIndex'=>'机位id' 'transcodeList'=> [  0 => ['transIndex' => '转码id' ,'transType' => '转码类型' , 'urlFlv' => 'flv观看地址' , 'urlHls' =>'hls观看地址' , 'urlRtmp' => 'Rtmp观看地址' ] ] ] ]  ] ]
     */
    public function getPullUrl($channelId){
        return $this->mxCurl(static::URL.'/l2/addr/getPullUrl?uid='.$this->uid.'&token='.$this->token,['channel_id'=>$channelId],false);
    }

    /**
     * @name 拉流模式开始直播
     * @param $channelId 直播ID
     * @return array ["ret"=>"0"  "msg" => "" ]
     */
    public function startIngest($channelId){
        return $this->mxCurl(static::URL.'/l2/ingest/startIngest?uid='.$this->uid.'&token='.$this->token,['channelId'=>$channelId],false);
    }

    /**
     * @name 拉流模式停止直播
     * @param $channelId 直播ID
     * @return array ["ret"=>"0"  "msg" => "" ]
     */
    public function stopIngest($channelId){
        return $this->mxCurl(static::URL.'/l2/ingest/stopIngest?uid='.$this->uid.'&token='.$this->token,['channelId'=>$channelId],false);
    }

    /**
     * @name 删除截图
     * @param $channelId 直播ID
     * @param $path
     * @return array ["ret"=>"0" ,"msg"=>"", result => null ]
     */
    public function deleteSnapshot($channelId,$path=''){
        return $this->mxCurl(static::URL.'/l2/snapshot/deleteSnapshot?uid='.$this->uid.'&token='.$this->token,['channelId'=>$channelId,'path'=>$path],false);
    }

    private function mxCurl($url , $data , $is_post = true){
        $response = ( new Http())->mxCurl($url,$data,$is_post,['proxy'=>'false','timeout'=>20,'header'=>['Content-type: application/json;charset="utf-8"','Accept: application/json'] ]);
        if($response['errcode'] == 0 ){
            return json_decode($response['data'],true) ;
        }else{
            $response['ret'] = '-1';
            return $response;
        }
    }

}
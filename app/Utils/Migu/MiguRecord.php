<?php
/**
 * Created by PhpStorm.
 * User: wangshiliang@dodoca.com
 * Date: 2018/4/14
 * Time: 17:42
 */

namespace App\Utils\Migu;

use App\Utils\Weixin\Http;

class MiguRecord
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
     * @name 获取录制列表
     * @param $where['title'] 直播名称  对直播名称进行模糊搜索
     * @param $where['channelId'] 按照录制时间进行升序或者降序输出
     * @param $where['order']  直播ID 对直播ID进行模糊搜索 1: 升序 0: 降序
     * @param $pageSize = 15 每次取条目，默认10条
     * @param $pageNum = 1  第一页值为1，默认第一页
     * @return array [
     *     "ret"=>"0"
     *     "result" => [
     *         'pageNum' => '',
     *         'pageSize' => '',
     *         'totalElements'=>'',
     *         'content'=>[
     *             0 => [
     *                 'channelId',
     *                 'id'=>'唯一标识 用于录制删除和导入云点播',
     *                 'imgUrl'=>'封面地址',
     *                 'recordLength'=>'录制时长',
     *                 'recordStartTime'=>'录制起始时间',
     *                 'recordStopTime'=>'录制结束时间',
     *                 'recordUrl'=>'录制文件地址',
     *                 'status'=>'录制状态-1: 录制中 0: 已录制   1: 导入中    2: 已导入',
     *                 'subject'=>'主题',
     *                 'title'=>'标题',
     *                 'uid'=>'用户ID',
     *                 'updateTime'=>'',
     *                 'vid=>'点播 id 导入点播后会产生该字段',
     *             ]
     *         ]
     *    ]
     * ]
     */
    public function listRecord($where = [], $pageSize = 15, $pageNum = 1){
        $where['pageSize'] = $pageSize;
        $where['pageNum'] = $pageNum;
        return $this->mxCurl(static::URL.'/l2/record/listRecord?uid='.$this->uid.'&token='.$this->token,json_encode($where));
    }

    /**
     * @name 获取点播vid列表
     * @param $channelId 直播ID
     * @param $pageNum = 1  页码
     * @param $pageSize = 10 每次取条目，默认10条
     * @return array ["ret"=>"0"  result => [ 1,2,3 ]]
     */
    public function listVid($channelId, $pageNum = 1, $pageSize = 10){
        return $this->mxCurl(static::URL.'/l2/record/listVid?uid='.$this->uid.'&token='.$this->token,json_encode(['channelId'=>$channelId,'pageSize'=>$pageNum,'pageNum'=>$pageSize]));
    }

    /**
     * @name 直播录制删除
     * @param $id 录制ID
     * @return array ["ret"=>"0"  msg  => '' ]
     */
    public function removeRecord($id){
        return $this->mxCurl(static::URL.'/l2/record/removeRecord?uid='.$this->uid.'&token='.$this->token,json_encode(['id'=>$id]));
    }

    /**
     * @name 导入云点播
     * @param $ids 点播ID （多个录制文件合片时可通过英文逗号”,”进行分隔拼接）    HLS录制模式不支持合片操作
     * @param $filename 导入点播后文件名
     * @param $online = 0 是否上线 0: 否 1: 是
     * @param $transcode = 0  是否开启转码 0: 否    1: 是
     * @param $clarity = '2' 画质 1 流畅; 2 标清; 3 高清; 4 超清; 5 音频 若表示需要转高清和超清两路，则$clarity="3,4"
     * @param $tips 标签  若有多条标签，则用逗号隔开
     * @param $category 分类
     * @return array ["ret"=>"0"  msg  => '' ]
     */
    public function uploadVideo($ids, $filename, $online = 0, $transcode = 0, $clarity = '2'){
        $data = [
            'ids' => $ids,
            'filename' => $filename,
            'transcode' => $transcode,
            'videoType' => $clarity,
            'online' => $online
            ];
        return $this->mxCurl(static::URL.'/l2/record/uploadVideo?uid='.$this->uid.'&token='.$this->token,json_encode($data));
    }

    /**
     * @name 获取播放地址
     * @param $vid 视频ID
     * @param $vtype = '0' 码率类型
     * @param $source = '' 码率类型 source,字段不传时默认走cdn,source=api.migucloud.com时走源站播放
     * @return array ["ret"=>"0"  msg  => '' , 'result'=>[ 'publicFlag' =>'视频上下线状态' , 'desc'=>'上下线状态描述', 'title'=>'' ,'timestamp'=>'', 'list' => [ 0=>[ 'vtype'=>'码率类型','vurl' => '地址' ] ] ]  ]
     */
    public function getUrl($vid, $vtype = '0,1,2', $source = ''){
        return $this->mxCurl(static::URL.'/vod2/v1/getUrl?uid='.$this->uid.'&token='.$this->token,['vid'=>$vid],false);
    }

    /**
     * @name 获取播放地址 防盗链
     * @param $vid 视频ID
     * @param $vtype = '0' 码率类型
     * @return array ["ret"=>"0"  msg  => '' , 'result'=>[ 'publicFlag' =>'视频上下线状态' , 'desc'=>'上下线状态描述', 'title'=>'' ,'timestamp'=>'', 'list' => [ 0=>[ 'vtype'=>'码率类型','vurl' => '地址' ] ] ]  ]
     */
    public function getUrlVerify($vid, $vtype = '0,1,2'){
        return $this->mxCurl(static::URL.'/vod2/v1/getUrlVerify?uid='.$this->uid.'&token='.$this->token,['vid'=>$vid],false);
    }

    /**
     * @name 获取下载地址
     * @param $vid 视频ID
     * @return array ["ret"=>"0"  msg  => '' , 'result'=>'url'  ]
     */
    public function getDownloadUrl($vid){
        return $this->mxCurl(static::URL.'/vod2/v1/getDownloadUrl?uid='.$this->uid.'&token='.$this->token,['vid'=>$vid],false);
    }

    /**
     * @name 获取批量下载地址
     * @param $vids 视频ID 多个vid用英文 ,隔开，最多20个
     * @return array ["ret"=>"0"  msg  => '' , 'result'=>[  0=>['vid'=>'','downloadUrl'=>''] ]  ]
     */
    public function getDownloadUrlForVids($vids){
        return $this->mxCurl(static::URL.'/vod2/v1/getDownloadUrlForVids?uid='.$this->uid.'&token='.$this->token,['vids'=>$vids],false);
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
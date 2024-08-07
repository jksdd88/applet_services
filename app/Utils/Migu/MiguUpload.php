<?php
/**
 * Created by PhpStorm.
 * User: wangshiliang@dodoca.com
 * Date: 2018/7/11
 * Time: 9:45
 */

namespace App\Utils\Migu;

use App\Utils\Weixin\Http;

class MiguUpload
{
    const URL = 'https://api.migucloud.com/vodupload/';

    private $uid;
    private $token;
    private $cookie = '';
    public $url = '';

    public function __construct($uid,$token)
    {
        $this->uid = $uid;
        $this->token = $token;
        $this->url   = static::URL;
    }

    /**
     * @name 创建上传
     * @param $title 视频名称 最大长度为128个字
     * @param $filename 视频文件名称
     * @param $size 视频大小 单位字节 如1M文件则为1024*1024
     * @param $key  md5 cookie 值
     * @param $publish 上下线 1 上线 2 下线
     * @param $transcode  转码   0: 否;1: 是
     * @param $clarity  转码画质 “0,1,2,3,4”对应转码模版id:0-流畅1-标清2-高清3-超清4-原画质
     * @param $catalogId 分类id
     * @param $finishUrl 转码完成回调地址
     * @param $uploadUrl 上传状态回调地址
     * @param $transUrl 转码状态回调地址
     * @param $reviewUrl 审核状态回调地址
     * @param $ctrlUrl 播控状态回调地址
     * @return array [
     *              "ret"=>"0"
     *               result => [
     *                       "server_addr"=> "上传的地址",
     *                        "task_id" => '上传任务的唯一标示',
     *                        "vid" => '上传后视频的唯一标示',
     *                        "finished_present" => '已经上传部分的百分比',
     *                        "total_block" => '文件切片总数',
     *                        "blocksize" => '每个分片的大小',
     *                        "blocks" => '上传到了第几个分片',
     *               ]
     *          ]
     */
    public function create($title,$filename,$size,$key,$publish,$transcode,$clarity,$catalogId,$finishUrl,$uploadUrl,$transUrl,$reviewUrl,$ctrlUrl){
        $data = [
            'filename'      => $filename,
            'title'         => $title,
            'file_size'     => $size,
            'md5'           => $key,
            'public_flag'   => $publish == 1 ? 41 :42,
            'trans_flag'    => $transcode == 1 ? 0: 1,
            'trans_version' => $clarity,
            'catalog_id'    => $catalogId,
            //'tag'           => '',//文件标签,多标签间只用“，”连接
            //'desc'          => '',//文件描述
            'cbFinishUrl'  => $finishUrl,
            'cbUploadUrl'  => $uploadUrl,
            'cbTransUrl'   => $transUrl,
            'cbReviewUrl'  => $reviewUrl,
            'cbCtrlUrl'    => $ctrlUrl,
        ];
        return $this->mxCurl(static::URL.'create_task?user_id='.$this->uid.'&atoken='.$this->token, $data, false);

    }

    /**
     * @name 上传
     * @param $taskId 上传任务的唯一标示
     * @param $block 当前上传的是第几个分片就传几  如 1 代表当前是第一个分片
     * @param $key  md5 cookie 值
     * @return array [
     *              "ret"=>"0"
     *               result => [
     *                       "total_block"=> "该上传任务总计的分片数",
     *                        "current_block" => '当前完成的分片数',
     *                        "remaining_block" => '剩下未传的分片数',
     *                        "task_id" => '当前上传所属的任务id',
     *                        "uid" => '当前上传所属的用户uid',
     *               ]
     *          ]
     */
    public function upload($taskId,$block,$key){
        $data = [
            'task_id'       => $taskId,
            'block'         => $block,
            //'blocksize'   => 0, 单位字节 默认为2M 也就是            2097152
            //'md5sum'        => 0, 切片MD5值 如果需要对每个分片的MD5值进行校验才需要传，默认不校验
        ];
        return $this->mxCurl(static::URL.'file_content?user_id='.$this->uid.'&atoken='.$this->token,$data);
    }

    /**
     * @name 上传地址
     * @return string
     */
    public function getUploadUrl(){
        return static::URL.'file_content?user_id='.$this->uid;
    }
    /**
     * @name 上报状态
     * @param $taskId 上传任务的唯一标示
     * @param $sttaus 状态 0 上传完成 1上传失败 2取消上传
     * @param $key  md5 cookie 值
     * @return array [
     *              "ret"=>"0"
     *               result => [
     *                       "vid"=> "视频的唯一标示",
     *                        "md5" => '文件的md5值',
     *                        "uid" => '当前上传所属的用户uid',
     *               ]
     *          ]
     */
    public function report($taskId,$sttaus){
        $data = [
            'task_id'       => $taskId,
            'cmd'         => $sttaus,
        ];
        return $this->mxCurl(static::URL.'update_status?user_id='.$this->uid.'&atoken='.$this->token,$data,false);
    }

    private function mxCurl($url , $data , $is_post = true){
        $response = ( new Http())->mxCurl($url,$data,$is_post,['proxy'=>'false','timeout'=>20,'header'=>['Content-type: application/json;charset="utf-8"','Accept: application/json'] ]);
        if($response['errcode'] == 0 ){
            return json_decode($response['data'],true) ;
        }else{
            return $response;
        }
    }

}
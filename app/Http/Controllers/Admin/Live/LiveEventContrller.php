<?php

namespace App\Http\Controllers\Admin\Live;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\LiveEvent;
use App\Services\LiveService;


class LiveEventContrller extends Controller
{
    private $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    //@@@@ 上线清理 并切换事件地址
    public function index()
    {
        $action = $this->request->get('action');
        if($action == 'muxin20180525'){
            $LiveService = new LiveService();
            $LiveService->clearToken();
            $token = $LiveService->getToken();
            return 'ok:'.$token;
        }
        return 'hi';
    }


    public function play()
    {
        return '';
    }

    /**
     * uid
     * channelId
     * status 0: 未开始1: 直播中2: 暂停3: 结束
     */
    public function liveStatus(){
        $data = $this->request->all();
        $liveService = new LiveService();
        if($data['uid'] == $liveService->uid){
            LiveEvent::insert_data(['channel_id'=>$data['channelId'],'type'=>1,'status'=>$data['status'],'request'=>json_encode($data) ]);
            $liveService->liveStatus($data['channelId'],$data['status']);
            return ['ret'=>'0','msg'=>"",'data'=>json_encode($data)];
        }
        return ['ret'=>'0','msg'=>""];
    }

    /**
     * post
     * retcode 返回导入点播结果 0: 导入成功; 非0: 导入失败
     * msg 导入结果说明
     * channelId  直播ID
     * vid 录像完成后的点播ID
     * endTag  录制结束标识  true: 录制结束; false: 录制未结束
     * playUrl 播放地址
     * startTime 录制开始时间 毫秒级时间戳
     * endTime 录制结束时间  毫秒级时间戳
     */
    public function recordComplete(){
        $data = $this->request->all();
        if(isset($data['channelId'])){
            LiveEvent::insert_data(['channel_id'=>$data['channelId'],'type'=>2,'status'=>$data['endTag']?2:1,'request'=>json_encode($data) ]);
            $liveService = new LiveService();
            $liveService->vodData($data['channelId'],1,$data);
        }
        return ['ret'=>'0','msg'=>""];
    }

    /**
     * retcode 返回导入点播结果 0: 导入成功; 非0: 导入失败
     * msg 导入结果说明
     * channelId  直播ID
     * vid 录像完成后的点播ID 视频ID，点播唯一标识
     * startTime 录制开始时间 毫秒级时间戳
     * endTime 录制结束时间  毫秒级时间戳
     * list    输出结果集
     * list['vtype'] 视频码率 流畅, 标清, 高清, 超清, 原画
     * list['vspoturl'] 观看地址
     * list['vdownloadurl']  下载地址
     */
    public function record(){
        $data = $this->request->all();
        if(isset($data['channelId'])){
            LiveEvent::insert_data(['channel_id'=>$data['channelId'],'type'=>3,'status'=>2,'request'=>json_encode($data) ]);
            $liveService = new LiveService();
            $liveService->vodData($data['channelId'],2,$data);
        }
        return ['ret'=>'0','msg'=>""];
    }

    /**
     * uid  用户ID
     * userId 子用户ID  根据具体uid的业务自行约定规则透传
     * channelId  直播ID
     * address  截图索引文件URL
     * msg success 处理结果
     */
    public function imageStart(){
        $data = $this->request->all();
        $liveService = new LiveService();
        if($data['uid'] == $liveService->uid){
            LiveEvent::insert_data(['channel_id'=>$data['channelId'],'type'=>4,'status'=>1,'request'=>json_encode($data) ]);
        }
        return ['ret'=>'0','msg'=>""];
    }

    /**
     * uid  用户ID
     * userId 子用户ID  根据具体uid的业务自行约定规则透传
     * channelId  直播ID
     * address  截图索引文件URL
     * msg success 处理结果
     */
    public function imageEnd(){
        $data = $this->request->all();
        $liveService = new LiveService();
        if($data['uid'] == $liveService->uid){
            LiveEvent::insert_data(['channel_id'=>$data['channelId'],'type'=>5,'status'=>2,'request'=>json_encode($data) ]);
        }
        return ['ret'=>'0','msg'=>""];
    }

    /**
     * uid  用户ID
     * id  图片ID
     * path  图片URL
     * label 识别分类  0: 色情  1: 性感 2: 正常  -1: 图片不存在或者下载失败）
     * rate  疑似程度  介于0-1间的浮点数，识别为某分类的概率，概率越高，机器越肯定
     * review  是否需要人工复审
     */
    public function pornographicNotice(){
        $data = $this->request->all();
        $liveService = new LiveService();
        if($data['uid'] == $liveService->uid){
            LiveEvent::insert_data(['channel_id'=>$data['id'],'type'=>6,'status'=>$data['review']?2:1,'request'=>json_encode($data) ]);
        }
        return ['ret'=>'0','msg'=>""];
    }

    /**
     * uid  用户ID
     * id  图片ID
     * path  图片URL
     * label 识别分类  0: 非暴恐 1: 特定人物（恐怖分子头目） 2: 特殊着装（军警服、疑似恐怖分子着装）3: 特殊符号（阿拉伯文、藏独疆独旗帜等）  4: 武器/持武器者（枪支刀具）  5: 国家标识（国旗国徽等） 6: 血腥场景    7: 暴乱场景（游行、焚烧、打砸）   8: 战争场景（爆炸、大型作战武器
     * rate  疑似程度  介于0-1间的浮点数，识别为某分类的概率，概率越高，机器越肯定
     * review  是否需要人工复审
     */
    public function terrorismNotice(){
        $data = $this->request->all();
        $liveService = new LiveService();
        if($data['uid'] == $liveService->uid){
            LiveEvent::insert_data(['channel_id'=>$data['id'],'type'=>7,'status'=>$data['review']?2:1,'request'=>json_encode($data) ]);
        }
        return ['ret'=>'0','msg'=>""];
    }

    /**
     * uid  用户ID
     * id  图片ID
     * path  图片URL
     * label 识别分类  0: 政治人物       2: 非政治人物            3: 无人脸
     * rate  疑似程度  介于0-1间的浮点数，识别为某分类的概率，概率越高，机器越肯定
     * review  是否需要人工复审
     * faceId  与该人脸最相似人物的名称
     */
    public function politicalNotice(){
        $data = $this->request->all();
        $liveService = new LiveService();
        if($data['uid'] == $liveService->uid){
            LiveEvent::insert_data(['channel_id'=>$data['id'],'type'=>8,'status'=>$data['review']?2:1,'request'=>json_encode($data) ]);
        }
        return ['ret'=>'0','msg'=>""];
    }

    /**
     * uid  用户ID
     * streamName  图片ID 直播流名称 直播码与机位号组合而成，形如：ABCDEFGH_C0
     */
    public function ccnotifyNotice(){
        $data = $this->request->all();
        $liveService = new LiveService();
        if($data['uid'] == $liveService->uid){
            LiveEvent::insert_data(['channel_id'=>$data['streamName'],'type'=>9,'status'=>0,'request'=>json_encode($data) ]);
        }
        return ['ret'=>'0','msg'=>""];
    }

    /**
     * @name
     * @param  uid  用户ID
     * @param streamName  图片ID 直播流名称 直播码与机位号组合而成，形如：ABCDEFGH_C0
     */
    public function ccstaticNotice(){
        $data = $this->request->all();
        $liveService = new LiveService();
        if($data['uid'] == $liveService->uid){
            LiveEvent::insert_data(['channel_id'=>$data['streamName'],'type'=>9,'status'=>0,'request'=>json_encode($data) ]);
        }
        return ['ret'=>'0','msg'=>""];
    }

    /**
     * @name 上传转码完成
     */
    public function uploadFinish(){
        $data = $this->request->all();
        LiveEvent::insert_data(['channel_id'=>isset($data['vid'])?$data['vid']:0 ,'type'=>20,'status'=>isset($data['status'])?$data['status']:0,'request'=>json_encode($data) ]);
        if(isset($data['ret']) && $data['ret'] =='0' && isset($data['result']['vid'])){
            $liveService = new LiveService();
            $liveService->vodSuccess($data['result']['vid'],$data);
        }
        if(isset($data['status']) && isset($data['result']['vid']) ){
            $liveService = new LiveService();
            $liveService->vodStatus($data['result']['vid'],$data['status']);
        }
        return ['ret'=>'0','msg'=>'success','result'=>[ 'vid'=> isset($data['result']['vid'])?$data['result']['vid']:'' ] ];
    }

    /**
     * @name 上传
     */
    public function upload(){
        $data = $this->request->all();
        LiveEvent::insert_data(['channel_id'=>isset($data['vid'])?$data['vid']:0,'type'=>21,'status'=>isset($data['status'])?$data['status']:0,'request'=>json_encode($data) ]);
        return ['ret'=>'0','msg'=>'success','result'=>[  ] ];
    }

    /**
     * @name 上传转码
     */
    public function uploadTrans(){
        $data = $this->request->all();
        LiveEvent::insert_data(['channel_id'=>isset($data['vid'])?$data['vid']:0,'type'=>22,'status'=>isset($data['status'])?$data['status']:0,'request'=>json_encode($data) ]);
        return ['ret'=>'0','msg'=>'success','result'=>[  ] ];
    }

    /**
     * @name 上传审核
     */
    public function uploadReview(){
        $data = $this->request->all();
        LiveEvent::insert_data(['channel_id'=>isset($data['vid'])?$data['vid']:0,'type'=>23,'status'=>isset($data['status'])?$data['status']:0,'request'=>json_encode($data) ]);
        return ['ret'=>'0','msg'=>'success','result'=>[  ] ];
    }

    /**
     * @name 上传控制
     */
    public function uploadCtrl(){
        $data = $this->request->all();
        LiveEvent::insert_data(['channel_id'=>isset($data['vid'])?$data['vid']:0,'type'=>24,'status'=>isset($data['status'])?$data['status']:0,'request'=>json_encode($data) ]);
        return ['ret'=>'0','msg'=>'success','result'=>[  ] ];
    }

}

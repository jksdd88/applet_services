<?php
/**
 * Created by PhpStorm.
 * User: wangshiliang@dodoca.com
 * Date: 2018/4/14
 * Time: 16:05
 */

namespace App\Utils\Migu;

use App\Utils\Weixin\Http;

class MiguEvent
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
     * @name 添加事件
     * @param $url 该nofity的通知地址
     * @param $event 触发该nofity的事件类型
     * @param “publish”: 表示当直播流连接或断开的事件发生时，触发通知;
     * @param “rec”: 当录制完成时，触发通知;
     * @param “combine”: 当导入云点播并处理完成时，触发通知；
     * @param “snapshotStart”: 当截图开始时，触发通知;
     * @param “snapshotEnd”: 当截图结束时，触发通知；
     * @param “eroticism”: 直播涉黄时触发通知；
     * @param “violence”: 直播涉恐时，触发通知;
     * @param “politics”: 直播涉及政治敏感时，触发通知；
     * @param “alarm”: 央视播控告警时触发通知；
     * @param “forbid”：央视播控禁播时触发告警。
     * @return array ["ret"=>"0","msg"=> ""   ]
     */
    public function saveNotify($url, $event){
        return $this->mxCurl(static::URL.'/l2/notify/saveNotify?uid='.$this->uid.'&token='.$this->token,json_encode(['url'=>$url,'event'=>$event]));
    }

    /**
     * @name 移除事件
     * @param $event 触发该nofity的事件类型 同楼上
     * @return array ["ret"=>"0","msg"=> ""   ]
     */
    public function removeNotify( $event ){
        return $this->mxCurl(static::URL.'/l2/notify/removeNotify?uid='.$this->uid.'&token='.$this->token,json_encode(['event'=>$event]));
    }

    /**
     * @name 状态通知
     * @param $type
     * @return array ["ret"=>"0","msg"=> ""   ]
     */
    public function notify($type){
        if($type == 'liveStatus'){ //直播状态通知
            /**
             * post
             * uid
             * channelId
             * status 0: 未开始1: 直播中2: 暂停3: 结束
             */
        }else if($type == 'recordComplete'){//录像完成通知接口
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
        }else if($type == 'record'){//录像处理完成通知接口
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
        }else if($type == 'snapshotStart' || $type == 'snapshotEnd'){//录像处理完成通知接口
            /**
             * uid  用户ID
             * userId 子用户ID  根据具体uid的业务自行约定规则透传
             * channelId  直播ID
             * address  截图索引文件URL
             * msg success 处理结果
             */
        }else if($type == 'pornographic'){//涉黄
            /**
             * uid  用户ID
             * id  图片ID
             * path  图片URL
             * label 识别分类  0: 色情  1: 性感 2: 正常  -1: 图片不存在或者下载失败）
             * rate  疑似程度  介于0-1间的浮点数，识别为某分类的概率，概率越高，机器越肯定
             * review  是否需要人工复审
             */
        }else if($type == 'terrorism'){//涉恐
            /**
             * uid  用户ID
             * id  图片ID
             * path  图片URL
             * label 识别分类  0: 非暴恐 1: 特定人物（恐怖分子头目） 2: 特殊着装（军警服、疑似恐怖分子着装）3: 特殊符号（阿拉伯文、藏独疆独旗帜等）  4: 武器/持武器者（枪支刀具）  5: 国家标识（国旗国徽等） 6: 血腥场景    7: 暴乱场景（游行、焚烧、打砸）   8: 战争场景（爆炸、大型作战武器
             * rate  疑似程度  介于0-1间的浮点数，识别为某分类的概率，概率越高，机器越肯定
             * review  是否需要人工复审
             */
        }else if($type == 'political'){//政治敏感
            /**
             * uid  用户ID
             * id  图片ID
             * path  图片URL
             * label 识别分类  0: 政治人物       2: 非政治人物            3: 无人脸
             * rate  疑似程度  介于0-1间的浮点数，识别为某分类的概率，概率越高，机器越肯定
             * review  是否需要人工复审
             * faceId  与该人脸最相似人物的名称
             */
        }else if($type == 'ccnotify'){//央视播控告警通知
            /**
             * uid  用户ID
             * streamName  图片ID 直播流名称 直播码与机位号组合而成，形如：ABCDEFGH_C0
             */
        }else if($type == 'ccstatic'){//央视播控禁播通知
            /**
             * uid  用户ID
             * streamName  图片ID 直播流名称 直播码与机位号组合而成，形如：ABCDEFGH_C0
             */
        }
       return json_encode(['ret'=>'0','msg'=>'']);
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
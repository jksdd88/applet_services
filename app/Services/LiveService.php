<?php
/**
 * Created by PhpStorm.
 * User: wangshiliang@dodoca.com
 * Date: 2018/4/16
 * Time: 13:30
 */

namespace App\Services;

use App\Models\LiveCatalog;
use App\Models\LiveChannel;
use App\Models\LiveRecord;
use App\Models\LiveStats;
use App\Models\LiveInfo;
use App\Models\LiveEvent;
use App\Models\LiveUpload;
use App\Models\LiveGoods;
use App\Models\LiveRecordGoods;
use App\Models\MerchantSetting;
use App\Models\WeixinLog;
use App\Utils\CacheKey;
use App\Utils\Migu\MiguDianbo;
use App\Utils\Migu\MiguLogin;
use App\Utils\Migu\MiguLive;
use App\Utils\Migu\MiguRecord;
use App\Utils\Migu\MiguStats;
use App\Utils\Migu\MiguUpload;
use Cache;
use Config;
use Symfony\Component\HttpFoundation\File\UploadedFile;


class LiveService
{

    private $advance;
    private $delete_vod ;
    private $host;
    private $clarity = '3'; //直播画质: 0 原画质 1流畅; 2标清;3高清;4超清;5音频
    private $vodClarity = '2';// 上传画质:0-流畅1-标清2-高清3-超清4-原画质
    private $vodSuffix = ['mp4','3gp','rmvb','wmv','mkv','mov','f4v','avi','ts','hls','mpeg','mpg','flv'];//wma  aac wav flac ape ogg mp3
    private $vodMax  = 4096;//M = 4G
    public  $uid ;

    public function __construct()
    {
        $this->uid = Config::get('live.uid');
        $this->delete_vod = Config::get('live.del_vod');
        $this->advance = Config::get('live.advance');
        $this->host = Config::get('weixin.base_host');
    }

    /**
     * @name 获取咪咕token
     * @return string
     */
    public function getToken(){
        $atokenKey    = CacheKey::get_live_cache('atoken', $this->uid);
        $atoken       = Cache::get($atokenKey);
        if($atoken){
            return $atoken;
        }
        $ftokenKey    = CacheKey::get_live_cache('ftoken', $this->uid);
        $ftoken       = Cache::get($ftokenKey);
        $MiguLogin = new MiguLogin();
        if($ftoken){
            $login = $MiguLogin -> loginRefresh($this->uid,$ftoken);
        }else{
            $login = $MiguLogin -> login(Config::get('live.user'), Config::get('live.pwd'));
        }
        if(!isset($login['ret']) || $login['ret'] != '0'){
            WeixinLog::insert_data([
                'merchant_id'=>0,
                'value'=>'',
                'action'=>'Live_token',
                'request'=>json_encode(['uid'=>$this->uid,'ftoken'=>$ftoken?'':$ftoken,'user'=>Config::get('live.user'),'pwd'=>Config::get('live.pwd')]),
                'reponse'=>is_array($login)?json_encode($login):$login
            ]);
            return false;
        }
        $atoken_time = intval($login['result']['expired_time']/120);
        $ftoken_time = intval($login['result']['expired_time']/120 + 5);
        Cache::put($atokenKey,  $login['result']['atoken'],$atoken_time);
        Cache::put($ftokenKey, $login['result']['ftoken'],$ftoken_time);

        return $login['result']['atoken'];
    }

    /**
     * @name 清理咪咕token
     * @return void
     */
    public function clearToken(){
        Cache::forget(CacheKey::get_live_cache('atoken', $this->uid));
        Cache::forget(CacheKey::get_live_cache('ftoken', $this->uid));
    }

    //========================== 直播 ==========================
    /**
     * @name 创建直播室
     * @param $merchantId 商户id
     * @param $data['title'] 直播名称 最大长度为128个字
     * @param $data['start'] default now 直播开始时间  时间戳
     * @param $data['end'] default now +3600 直播时长 分钟
     * @param $data['max']default 100 最大观看人数
     * @param $data['image'] default '' 直播封面
     * @param $data['subject'] default title 直播主题 最大长度为128个字
     * @param $data['desc'] default title 直播描述 最大长度为128个字
     * @param $data['clarity'] default '1' 画质 1流畅; 2标清;3高清;4超清;5音频
     * @param $data['record'] default 0  录制方式     0: 不录制;  1:录制
     * @return array ['errcode'=>0,'errmsg'=>'ok', 'id'=>$id ]
     */
    public function createChannel($merchantId,$data){
        if($merchantId < 1 || empty($data['title'])){
            return ['errcode'=>1,'errmsg'=>'param null '];
        }
        $datas['title']        = $data['title'];
        $datas['start_time']   = !isset($data['start']) || empty($data['start']) ? $_SERVER['REQUEST_TIME'] : $data['start'];
        $datas['end_time']     = !isset($data['end']) || empty($data['end']) ? $_SERVER['REQUEST_TIME']+3600 : $data['end'];
        $datas['subject']      = !isset($data['subject']) || empty($data['subject']) ? $data['title'] : $data['subject'];
        $datas['description']  = !isset($data['desc']) || empty($data['desc']) ? $data['title'] : $data['desc'];
        $datas['img_url']      = !isset($data['image']) || empty($data['image']) ? '' : $data['image'];
        $datas['video_type']   = '0'; //!isset($data['clarity']) || empty($data['clarity']) ? $this->clarity : $data['clarity'];
        $datas['record']       = !isset($data['record']) || empty($data['record']) ? 0 : $data['record'];
        $datas['demand']       = $datas['record'];
        $datas['transcode']    = $datas['record'];

        $miguLive = new MiguLive($this->uid,$this->getToken());
        $info     = $miguLive -> createChannel($datas['title'],$datas['start_time'],$datas['end_time'],$datas['subject'],$datas['description'],$datas['img_url'],$datas['video_type'],$datas['record'],$datas['demand'],$datas['transcode']);
        if($info['ret'] != '0' ){
            return ['errcode'=>(int)$info['ret'],'errmsg'=>'createChannel: '.$info['msg']];
        }
        $push     = $miguLive -> getPushUrl($info['result']['channelId']);
        if($push['ret'] != '0' ){
            $miguLive->removeChannel($info['result']['channelId']);
            return ['errcode'=>(int)$push['ret'],'errmsg'=>'getPushUrl: '.$push['msg']];
        }
        $play     = $miguLive -> getPullUrl($info['result']['channelId']);
        if($play['ret'] != '0' ){
            $miguLive->removeChannel($info['result']['channelId']);
            return ['errcode'=>(int)$play['ret'],'errmsg'=>'getPushUrl: '.$play['msg']];
        }
        if($datas['start_time'] > ($_SERVER['REQUEST_TIME'] + $this->advance)){
            $close = $miguLive->closeChannel($info['result']['channelId']);
            if($close['ret'] != '0' ){
                $miguLive->removeChannel($info['result']['channelId']);
                return ['errcode'=>(int)$close['ret'],'errmsg'=>'close: '.$close['msg']];
            }
            $datas['status']      = -1 ;
        }else{
            $datas['status']      = 1 ;
        }
        $datas['merchant_id'] = $merchantId ;
        $datas['max_sum']     = !isset($data['max']) ||  $data['max'] < 100 ? 100 : $data['max'] ;
        $datas['channel_id']  = $info['result']['channelId'] ;
        $datas['push_src']    = $push['result']['cameraList'][0]['url'] ;
        $datas['play_rtmp']   = $play['result']['cameraList'][0]['transcodeList']['0']['urlRtmp'] ;
        $datas['play_flv']    = $play['result']['cameraList'][0]['transcodeList']['0']['urlFlv'] ;
        $datas['play_hls']    = $play['result']['cameraList'][0]['transcodeList']['0']['urlHls'] ;

        $id = LiveChannel::insert_data($datas);
        if(!$id){
            $miguLive->removeChannel($info['result']['channelId']);
            return ['errcode'=>2,'errmsg'=>'sys error'];
        }
        return ['errcode'=>0,'errmsg'=>'ok', 'id'=>$id ];
    }

    /**
     * @name 修改直播室
     * @param $id 直播id
     * @param $merchantId 商户id
     * @param $data['title'] 直播名称 最大长度为128个字
     * @param $data['start'] default now 直播开始时间  时间戳
     * @param $data['end'] default now +3600 直播时长 分钟
     * @param $data['max']default 1 最大观看人数 最小值10
     * @param $data['image'] default '' 直播封面
     * @param $data['subject'] default title 直播主题 最大长度为128个字
     * @param $data['desc'] default title 直播描述 最大长度为128个字
     * @param $data['clarity'] default '1' 画质 1流畅; 2标清;3高清;4超清;5音频 若表示需要转高清和超清两路，则videoType="3,4"
     * @param $data['record'] default 0  录制方式     0: 不录制;  1:录制
     * @return array ['errcode'=>0,'errmsg'=>'ok', 'id'=>$id ]
     */
    public function updateChannel($id,$merchantId,$data){
        if( $merchantId < 1 || $id < 1 ){
            return ['errcode'=>1,'errmsg'=>'param null '];
        }
        $info = LiveChannel::get_one('id',$id);
        if(!isset($info['id']) || $info['merchant_id'] != $merchantId){
            return ['errcode'=>1,'errmsg'=>'id  merchant_id error '];
        }
        if($info['end_time'] < $_SERVER['REQUEST_TIME']){
            return ['errcode'=>0,'errmsg'=>'ok', 'id'=>$id ];
        }
        if($info['start_time'] < $_SERVER['REQUEST_TIME'] &&  $info['end_time'] > $_SERVER['REQUEST_TIME'] && (isset($data['start']) || isset($data['end']) || isset($data['max']))){
            return ['errcode'=>2,'errmsg'=>'直播中不能修改起止时间和限制人数'];
        }
        $datas['title']        = !isset($data['title']) || empty($data['title']) ? $info['title'] : $data['title'];
        $datas['start_time']   = !isset($data['start']) || empty($data['start']) ? $info['start_time'] : $data['start'];
        $datas['end_time']     = !isset($data['end']) || empty($data['end']) ? $info['end_time'] : $data['end'];
        $datas['max_sum']      = !isset($data['max']) || empty($data['max'])  || $data['max'] < 1 ? $info['max_sum']  : $data['max'] ;
        $datas['img_url']      = !isset($data['image']) || empty($data['image']) ? $info['img_url'] : $data['image'];
        $datas['subject']      = !isset($data['subject']) || empty($data['subject']) ? $info['subject'] : $data['subject'];
        $datas['description']  = !isset($data['desc']) || empty($data['desc']) ? $info['description'] : $data['desc'];
        $datas['video_type']   = '0';//!isset($data['clarity']) || empty($data['clarity']) ? $info['video_type'] : $data['clarity'];
        $datas['record']       = !isset($data['record']) || empty($data['record']) ? $info['record'] : $data['record'];
        $datas['demand']       = $datas['record'];
        $datas['transcode']    = $datas['record'];
        $datas['status']  = 1;

        $miguLive = new MiguLive($this->uid,$this->getToken());
        //禁播 先打开
        if($info['status'] == -1 || $info['play_status'] == 3){
            $response = $miguLive->openChannel($info['channel_id']);
            if($response['ret'] != '0' ){
                return ['errcode'=>(int)$response['ret'],'errmsg'=>'updateChannel: OPEN '.$response['msg']];
            }
        }
        //未开始 暂停  可同步修改
        if($info['status'] ==1 && ($info['play_status'] == 2 || $info['play_status'] == 0)){
            $response     = $miguLive ->updateChannel($info['channel_id'],$datas['title'],$datas['start_time'],$datas['end_time'],$datas['subject'],$datas['description'],'',$datas['video_type'],$datas['record'],$datas['demand'],$datas['transcode']);
        }
        //时间未到  关闭
        if($datas['start_time'] > ($_SERVER['REQUEST_TIME'] + $this->advance)){
            $response = $miguLive->closeChannel($info['channel_id']);
            if($response['ret'] != '0' ){
                return ['errcode'=>(int)$response['ret'],'errmsg'=>'updateChannel: CLOSE '.$response['msg']];
            }
            $datas['status']  = -1;
        }
        LiveChannel::update_data('id',$id,$datas);
        return ['errcode'=>0,'errmsg'=>'ok', 'id'=>$id ];
    }

    /**
     * @name 删除直播
     * @param $id 直播id
     * @param $merchantId 商户id
     * @return array ['errcode'=>0,'errmsg'=>'ok']
     */
    public function deleteChannel($id, $merchantId){
        if( $merchantId < 1 || $id < 1 ){
            return ['errcode'=>1,'errmsg'=>'param null '];
        }
        $info = LiveChannel::get_one('id',$id);
        if(!isset($info['id']) || $info['merchant_id'] != $merchantId){
            return ['errcode'=>1,'errmsg'=>'id  merchant_id error '];
        }
        if($info['status'] != 2){
            //return ['errcode'=>1,'errmsg'=>'直播取消后才可以删除'];
        }
        $miguLive = new MiguLive($this->uid,$this->getToken());
        if($info['status'] == 1 || $info['play_status'] == 3){
            $response = $miguLive->openChannel($info['channel_id']);
            if($response['ret'] != '0' ){
                return ['errcode'=>(int)$response['ret'],'errmsg'=>'deleteChannel: OPEN '.$response['msg']];
            }
        }
        $response = $miguLive->removeChannel($info['channel_id']);
        if($response['ret'] != '0' ){
            return ['errcode'=>(int)$response['ret'],'errmsg'=>'deleteChannel: '.$response['msg']];
        }else{
            $response = $miguLive->closeChannel($info['channel_id']);
        }
        LiveChannel::update_data('id',$id,['is_delete' => -1 ]);
        return ['errcode'=>0,'errmsg'=>'ok'];
    }

    /**
     * @name 直播开关
     * @param $id 直播id
     * @param $merchantId 商户id
     * @param $status 1 开  -1 关
     * @return array ['errcode'=>0,'errmsg'=>'ok']
     */
    public function switchChannel($id, $merchantId, $status){
        if( $merchantId < 1 || $id < 1 ){
            return ['errcode'=>1,'errmsg'=>'param null '];
        }
        $info = LiveChannel::get_one('id',$id);
        if(!isset($info['id']) || $info['merchant_id'] != $merchantId || empty($info['channel_id'])){
            return ['errcode'=>1,'errmsg'=>'id  merchant_id error '];
        }
        $status = $status == 1 ? 1 : -1;
        if( ($status == 1 && $info['status'] == 1) || ($status == -1 && $info['status'] == -1) ){
            return ['errcode'=>0,'errmsg'=>'ok!'];
        }
        $miguLive = new MiguLive($this->uid,$this->getToken());
        if($status == 1){
            $response = $miguLive->openChannel($info['channel_id']);
        }else{
            $response = $miguLive->closeChannel($info['channel_id']);
        }
        if($response['ret'] != '0' ){
            return ['errcode'=>(int)$response['ret'],'errmsg'=>'switchChannel: '.$response['msg']];
        }
        LiveEvent::insert_data(['channel_id'=>$info['channel_id'],'type'=> $status == 1 ? 12 : 13,'status'=>$response['ret'],'request'=>json_encode($response) ]);
        LiveChannel::update_data('id',$id,['status' => ($status == 1 ? 1 : -1) ]);
        return ['errcode'=>0,'errmsg'=>'ok'];
    }

    /**
     * @name 直播续期
     * @param $id 直播id
     * @param $merchantId 商户id
     * @param $time 结束时间 YYYY-mm-dd HH:ii;SS
     * @return array ['errcode'=>0,'errmsg'=>'ok']
     */
    public function renewalChannel($id, $merchantId, $time){
        if( $merchantId < 1 || $id < 1 ){
            return ['errcode'=>1,'errmsg'=>'param null '];
        }
        $info = LiveChannel::get_one('id',$id);
        if(!isset($info['id']) || $info['merchant_id'] != $merchantId){
            return ['errcode'=>1,'errmsg'=>'id  merchant_id error '];
        }
        if($info['status'] != 1){
            return ['errcode'=>2,'errmsg'=>'直播已经关闭'];
        }
        $time = strtotime($time);
        if($time <= $info['end_time']){
            return ['errcode'=>3,'errmsg'=>'延期时间小于结束时间'];
        }
        $result = LiveChannel::update_data('id',$info['id'],['end_time'=>$time]);
        if(!$result){
            return ['errcode'=>4,'errmsg'=>'意外错误'];
        }
        return ['errcode'=>0,'errmsg'=>''];
    }


    /**
     * @name 直播信息
     * @param $id 直播id
     * @param $merchantId 商户id
     * @return array ['errcode'=>0,'errmsg'=>'ok','data'=>[
     *    id => '',
     *    title=>'标题',
     *    start=>'开始时间',
     *    end=>'结束时间',
     *    subject=>'主体',
     *    desc=>'描述',
     *    clarity=>'画质:1流畅; 2标清;3高清;4超清;5音频',
     *    record=>'录制:0 不录制 1录制',
     *    status=>'开关状态：-1 关闭 1开启',
     *    play_status=>'播放状态：0未开始 1 开始 2 暂停 3结束',
     *    max=>'最大并发人数' ,
     *    created_time => '创建时间'
     * ]];
     */
    public function getChannel($id, $merchantId){
        if( $merchantId < 1 || $id < 1 ){
            return ['errcode'=>1,'errmsg'=>'param null '];
        }
        $info = LiveChannel::get_one('id',$id);
        if(!isset($info['id']) || $info['merchant_id'] != $merchantId){
            return ['errcode'=>1,'errmsg'=>'id  merchant_id error '];
        }
        $data = [];
        $data['id']           =  $info['id'];
        $data['title']        =  $info['title'];
        $data['start']        =  $info['start_time'];
        $data['end']          =  $info['end_time'];
        $data['subject']      =  $info['subject'] ;
        $data['desc']         =  $info['description'];
        $data['clarity']      =  $info['video_type'];
        $data['record']       =  $info['record'] ;
        $data['status']       =  $info['status'] ;
        $data['play_status']  =  $info['play_status'] ;
        $data['max']          =  $info['max_sum'] ;
        $data['created_time']    =  $info['created_time'] ;
        return ['errcode'=>0,'errmsg'=>'ok','data'=>$data];
    }

    /**
     * @name 直播
     * @param $id 直播id
     * @param $merchantId 商户id
     * @return array [ 'errcode'=>0,'errmsg'=>'ok',data=>[
     *     push_url => '推流地址',
     *     play_url => '播放地址'，
     *
     *     play_rtmp => '播放地址 rtmp 格式',
     *     play_flv => '播放地址 flv 格式',
     * ] ]
     */
    public function playChannel($id, $merchantId){
        if( $merchantId < 1 || $id < 1 ){
            return ['errcode'=>1,'errmsg'=>'param null '];
        }
        $info = LiveChannel::get_one('id',$id);
        if(!isset($info['id']) || $info['merchant_id'] != $merchantId){
            return ['errcode'=>1,'errmsg'=>'id  merchant_id error '];
        }
        if($info['status'] != 1){
            return ['errcode'=>2,'errmsg'=>'channel close'];
        }
        //时间
        if($_SERVER['REQUEST_TIME'] < $info['start_time'] ||  $_SERVER['REQUEST_TIME'] > $info['end_time']){
            return ['errcode'=>3,'errmsg'=>'error overdue'];
        }
        //数量
        $migustats = new MiguStats($this->uid,$this->getToken());
        $maxlist = $migustats ->getUseronlineMax($info['channel_id'],$_SERVER['REQUEST_TIME']-1800,$_SERVER['REQUEST_TIME']);
        $max = [ 'num' => 0 , 'time' => 0];
        if(isset($maxlist['result']['content'][0]['datas'])){
            foreach ($maxlist['result']['content'][0]['datas'] as $k => $v) {
                if($v['time'] > $max['time']){
                    $max['num'] = $v['num'];
                }
            }
        }
        if($max['num'] > $info['max_sum']){
            return ['errcode'=>4,'errmsg'=>'gt max'];
        }
        //流量
        return ['errcode'=>0,'errmsg'=>'ok','data'=>[ 'push_url'=>$info['push_src'], 'play_url'=>$info['play_hls' ] ,'play_rtmp' => $info['play_rtmp' ] , 'play_flv' => $info['play_flv' ] ]];
    }

    //========================== 录播 ==========================

    /**
     * @name 录播列表
     * @param $merchantId 商户id
     * @return array  ['errcode'=>0,'errmsg'=>'ok','data'=>['count'=>1 'list'=>[
     *   id,
     *   merchant_id,
     *   lid => '直播id'
     *   length => '时长',
     *   play => '播放地址',
     *   download => '下载地址',
     *   status => '状态：-1: 录制中 0: 已录制 1: 导入中 2: 已导入',
     *   start_time => '开始时间',
     *  end_time => '结束时间'
     * ]] ]
     */
    public function getVodList( $merchantId, $page=1, $length=15){
        $list =  LiveRecord::select_data($merchantId,$page,$length);
        $count = LiveRecord::select_count($merchantId);
        return ['errcode'=>0,'errmsg'=>'ok','data'=>['list'=>$list,'count'=>$count] ];
    }

    /**
     * @name 录播列表
     * @param
     * @param $merchantId 商户id
     * @return array  ['errcode'=>0,'errmsg'=>'ok','data'=>[
     *   lid => '直播id'
     *   length => '时长',
     *   play => '播放地址',
     *   download => '下载地址',
     *   status => '状态：-1: 录制中 0: 已录制 1: 导入中 2: 已导入',
     *   start_time => '开始时间',
     *  end_time => '结束时间'
     * ] ]
     */
    public function getVod($id, $merchantId){
        $info = LiveRecord::get_one('id',$id);
        if(!isset($info['id']) || $info['merchant_id'] != $merchantId){
            return ['errcode'=>1,'errmsg'=>'id or merchantId is error' ];
        }
        return ['errcode'=>0,'errmsg'=>'ok','data'=>[
            'lid'=>$info['lid'],
            'title'=>$info['title'],
            'img_url'=>$info['img_url'],
            'length'=>$info['length'],
            'play'=>$info['play'],
            'download'=>$info['download'],
            'status'=>$info['status'],
            'publish_status' => $info['publish_status'],
            'start_time'=>$info['start_time'],
            'end_time'=>$info['start_time']
        ] ];
    }

    /**
     * @name 删除录播
     * @param $id 录播 id
     * @param $merchantId 商户id
     * @return array ['errcode'=>0,'errmsg'=>'']
     */
    public function deleteVod($id, $merchantId){
        $record = LiveRecord::get_one('id',$id);
        if(!isset($record['id']) || $record['id'] != $merchantId){
            return ['errcode'=>1,'errmsg'=>'id  merchant_id error'];
        }
        $liveRecord = new MiguDianbo($this->uid,$this->getToken());
        $response = $liveRecord -> deleteVideo($record['vid']);
        if($response['ret'] != '0'){
            return ['errcode'=>(int)$response['ret'],'errmsg'=>$response['msg']];
        }
        LiveRecord::update_data('id',$record['id'],['is_delete'=>-1]);
        return ['errcode'=>0,'errmsg'=>''];
    }

	/**
     * @name 点播视频上下线
     * @param $id  录像id
     * @param $status 1 上线 2 下线
     * @return  array
     */
	public function publishVideo($id, $status){
		$info = LiveRecord::get_one('id',$id);
		if(!isset($info['channel_id'])) {
			return ['errcode'=>1,'errmsg'=>'录播参数错误']; 
		}
        $status = $status==1 ? 1 : 2;
		if(($status == 2 && $info['publish_status'] ==2) || ($status == 1 && $info['publish_status'] ==1  )){
            return ['errcode'=>0,'errmsg'=>'操作成功'];
        }
		$liveVod = new MiguDianbo($this->uid,$this->getToken());
		$response = $liveVod->publishVideo($info['vid'],$status);
		if($response['ret'] != '0'){
            return ['errcode'=>(int)$response['ret'],'errmsg'=>$response['msg']];
        }
        $data = ['publish_status'=>$status];
        if($status == 1 ){
            $response = $liveVod -> getUrl($info['vid']);
            if($response['ret'] != '0' || !isset($response['result']['list'][0]['vurl']) || empty($response['result']['list'][0]['vurl'])){
                return ['errcode'=>2,'errmsg'=>'上线失败[P]'];
            }
            $data['play'] = $response['result']['list'][0]['vurl'];

            $response = $liveVod -> getDownloadUrl($info['vid']);
            if($response['ret'] != '0' || !isset($response['result']) || empty($response['result'])){
                return ['errcode'=>2,'errmsg'=>'上线失败[D]'];
            }
            $data['download'] = $response['result'];
        }
		$res = LiveRecord::update_data('id',$id,$data);
		if(!$res) {
			return ['errcode'=>2,'errmsg'=>'操作失败']; 
		}
		return ['errcode'=>0,'errmsg'=>'操作成功'];
	}

    /**
     * @name 录播播放地址
     * @param $id 录播id
     * @param $merchantId 商户id
     * @return int
     */
    public function getVodPlay($id,$merchantId){
        $info = LiveRecord::get_one('id',$id);
        if(!isset($info['id']) || $info['merchant_id'] != $merchantId){
            return ['errcode'=>1,'errmsg'=>'id  merchant_id error '];
        }
        if($info['status'] != 2){
            return ['errcode'=>2,'errmsg'=>'视频未就绪'];
        }
        if($info['publish_status'] != 1){
            return ['errcode'=>2,'errmsg'=>'视频已下线'];
        }
        if($info['expire_time'] <  $_SERVER['REQUEST_TIME']){
            return ['errcode'=>2,'errmsg'=>'视频已过期'];
        }

        $play = $info['play'];
        if($info['length'] < 7200){
            $liveVod = new MiguDianbo($this->uid,$this->getToken());
            $response = $liveVod->getUrlVerify($info['vid']);
            if($response['ret'] != '0'){
                return ['errcode'=>3,'errmsg'=>$response['msg']];
            }
            if(isset($response['result']['list'][0]['vurl'])){
                $play = $response['result']['list'][0]['vurl'];
            }
        }
        return ['errcode'=>0,'errmsg'=>'','play'=>$play];
    }

    //========================== 播放数据 ==========================
    /**
     * @name 在线人数
     * @param $type 类型 1 直播 2 录播
     * @param $id 直播id 或 录播id
     * @param $merchantId
     * @return array ['errcode'=>0,'errmsg'=>'','data'=>[ 'time'=>'时间','num'=>'数量' ]]
     */
	public function onlineStats($type,$id,$merchantId){
	    if(($type != 2 && $type != 1) ||  $id < 0 || $merchantId < 0 ){
            return ['errcode'=>1,'errmsg'=>'参数有误'];
        }
        if($type == 1){
            $info = LiveChannel::get_one('id',$id);
        }else{
            $info = LiveRecord::get_one('id',$id);
        }
        if( !isset($info['id']) ||  $info['merchant_id'] != $merchantId){
            return ['errcode'=>1,'errmsg'=>'参数有误'];
        }
        $miguStats = new MiguStats($this->uid,$this->getToken());
        $response = $miguStats -> listUsersOnline($type,( $type == 1 ? $info['channel_id'] : $info['vid'] ),$_SERVER['REQUEST_TIME']-1800,$_SERVER['REQUEST_TIME']);
        $list = isset($response['result']['content'][0]['datas'])?$response['result']['content'][0]['datas']:[]; // time num
        $data = ['time'=>0,'num'=>0];
        foreach ($list as $k => $v) {
            if($v['time'] > $data['time']){
                $data['time'] = $v['time'];
                $data['num'] = $v['num'];
            }
        }
        $data['time'] = intval($data['time']/1000);
        return ['errcode'=>0,'errmsg'=>'','data'=>$data];
    }

    //========================== 直播数据存储 ==========================

    /**
     * @name 获取直播数据
     * @param $type 数据类型:1 事实在线人数  3 流量 4 带宽 5独立ip数 6 最多在线人数（直播）
     * @param $channel_id
     * @param $merchant_id
     * @param $id
     * @param $length = 1800
     * @return int
     */
    public function statsLive($type, $channel_id, $merchant_id, $id, $length = 1800){
        $miguStats = new MiguStats($this->uid,$this->getToken());
        if($type == 1){
            $response = $miguStats -> listUsersOnline(1,$channel_id,$_SERVER['REQUEST_TIME']-$length,$_SERVER['REQUEST_TIME']);
            $list = isset($response['result']['content'][0]['datas'])?$response['result']['content'][0]['datas']:[]; // time num
            $valkey = 'num';
        }else if($type == 3){
            $response = $miguStats -> listFlow(1,$channel_id,$_SERVER['REQUEST_TIME']-$length,$_SERVER['REQUEST_TIME']);
            $list = isset($response['result']['content']['single'][0]['datas'])?$response['result']['content']['single'][0]['datas']:[]; // time flow
            $valkey = 'flow';
        }else if($type == 4){
            $response = $miguStats -> listBandwidth(1,$channel_id,$_SERVER['REQUEST_TIME']-$length,$_SERVER['REQUEST_TIME']);
            $list = isset($response['result']['content']['single'][0]['datas'])?$response['result']['content']['single'][0]['datas']:[];// time  bandwidth
            $valkey = 'bandwidth';
        }else if($type == 5){
            $response = $miguStats -> listDhip(1,$channel_id,$_SERVER['REQUEST_TIME']-$length,$_SERVER['REQUEST_TIME']);
            $list = isset($response['result']['content'])?$response['result']['content']:[];// time  dhip
            $valkey = 'dhip';
        }else if($type == 6){
            $response = $miguStats -> getUseronlineMax($channel_id,$_SERVER['REQUEST_TIME']-$length,$_SERVER['REQUEST_TIME']);
            $list = isset($response['result']['content'])?$response['result']['content']:[];// time  num
            $valkey = 'num';
        }else{
            return ['errcode'=>1,'errmsg'=>'type error'];
        }
        if(empty($list)){
            return ['errcode'=>1,'errmsg'=>'response null ','response'=>$response];
        }
        foreach ($list as $k => $v) {
            $v['time'] = isset($v['time'])?intval($v['time']/1000):0 ;
            $check = LiveStats::check_one($channel_id,$v['time'],$type);
            if(!isset($check['id'])){
                LiveStats::insert_data([
                    'merchant_id'=>$merchant_id,
                    'channel_id'=>$channel_id,
                    'sid'=>$id,
                    'type'=>1,
                    'value_type'=>$type,
                    'value' => $v[$valkey],
                    'value_time'=>$v['time']
                ]);
            }else if($check['value'] < $v[$valkey]){
                LiveStats::update_data('id',$check['id'],['value'=>$v[$valkey]]);
            }
        }
        return ['errcode'=>0,'errmsg'=>''];
    }

    /**
     * @name 获取录播数据
     * @param $type 数据类型:1 事实在线人数 2 播放次数 3 流量 4 带宽 5独立ip数
     * @param $vid
     * @param $channel_id
     * @param $merchant_id
     * @param $id
     * @param $length = 1800
     * @return int
     */
    public function statsRecord($type, $vid, $channel_id, $merchant_id, $id, $length = 180000){
        $miguStats = new MiguStats($this->uid,$this->getToken());
        if($type == 1){
            $response = $miguStats -> listUsersOnline(2,$vid,$_SERVER['REQUEST_TIME']-$length,$_SERVER['REQUEST_TIME']);
            $list = isset($response['result']['content'][0]['datas'])?$response['result']['content'][0]['datas']:[]; // time num
            $valkey = 'num';
        }else if($type == 2){
            $response = $miguStats -> listPlayTimes($vid,$_SERVER['REQUEST_TIME']-$length,$_SERVER['REQUEST_TIME']);
            $list = isset($response['result']['content']['single'][0]['datas'])?$response['result']['content']['single'][0]['datas']:[]; // time hit
            $valkey = 'hit';
        }else if($type == 3){
            $response = $miguStats -> listFlow(2,$vid,$_SERVER['REQUEST_TIME']-$length,$_SERVER['REQUEST_TIME']);
            $list = isset($response['result']['content']['single'][0]['datas'])?$response['result']['content']['single'][0]['datas']:[]; // time flow
            $valkey = 'flow';
        }else if($type == 4){
            $response = $miguStats -> listBandwidth(2,$vid,$_SERVER['REQUEST_TIME']-$length,$_SERVER['REQUEST_TIME']);
            $list = isset($response['result']['content']['single'][0]['datas'])?$response['result']['content']['single'][0]['datas']:[]; // time
            $valkey = 'bandwidth';
        }else if($type == 5){
            $response = $miguStats -> listDhip(2,$vid,$_SERVER['REQUEST_TIME']-$length,$_SERVER['REQUEST_TIME']);
            $list = isset($response['result']['content'])?$response['result']['content']:[];// time
            $valkey = 'dhip';
        }else{
            return ['errcode'=>1,'errmsg'=>'type error'];
        }
        if(empty($list)){
            return ['errcode'=>1,'errmsg'=>'response null ','response'=>$response];
        }

        foreach ($list as $k => $v) {
            $v['time'] = intval($v['time']/1000) ;
            $check = LiveStats::check_one($channel_id,$v['time'],$type,$vid);
            if(!isset($check['id'])){
                LiveStats::insert_data([
                    'merchant_id'=>$merchant_id,
                    'channel_id'=>$channel_id,
                    'sid'=>$id,
                    'vid'=>$vid,
                    'type'=>2,
                    'value_type'=>$type,
                    'value' => $v[$valkey],
                    'value_time'=>$v['time']
                ]);
            }else if($check['value'] < $v[$valkey]){
                LiveStats::update_data('id',$check['id'],['value'=>$v[$valkey]]);
            }
        }
        return ['errcode'=>0,'errmsg'=>''];
    }

    /**
     * @name 获取录播数据
     * @param $type 数据类型:7 转码时长 8存储空间
     * @param $types $type=7 1 直播 2 录播
     * @return int
     */
    public function statsOther($type,$types=1){
        $miguStats = new MiguStats($this->uid,$this->getToken());
        if($type == 7){
            $response = $miguStats -> listTranferTime($types,$_SERVER['REQUEST_TIME'],$_SERVER['REQUEST_TIME']-1800);
        }else{
            $response = $miguStats -> listStorage($_SERVER['REQUEST_TIME'],$_SERVER['REQUEST_TIME']-1800);
        }
        return ['errcode'=>0,'errmsg'=>'','data'=>$response['result']];
    }

    //========================== 上传视频 ==========================
    /**
     * @name 录像上传
     * @param $merchantId 商户id
     * @param $filename 文件名 包含后缀
     * @param $size 文件大小 字节
     * @param $time 文件最后修改时间 毫秒
     * @param $cookie_key 文件md5
     * @return array [ 'errcode'=>0,'errmsg'=>'','data'=>[  'id'=>'live_upload 表 ID',  'cookie_key'=>'断点上传cookie key', 'total'=>'总分块数' , 'block_current' => '当前上传到第几块',  'url'=>'上传地址', ]]
     */
    public function vodUpload( $merchantId, $filename, $size, $time, $cookie_key ){
        if($merchantId < 1 ||  empty($filename) || empty($size) || empty($time)){
            return ['errcode'=>1,'errmsg'=>'参数有误 '];
        }
        $suffix = explode('.',$filename);
        $suffix = end($suffix);
        $suffix = strtolower($suffix);
        if(!in_array($suffix,$this->vodSuffix)){
            return ['errcode'=>1,'errmsg'=>'不支持文件格式'];
        }
        $sizem = intval($size/1024/1024);
        if($sizem > $this->vodMax){
            return ['errcode'=>1,'errmsg'=>'视频文件过大'];
        }
        $migu = new MiguUpload($this->uid,$this->getToken());
        $datas['merchant_id'] = $merchantId;
        $datas['title']       = $filename;
        $datas['size']        = $size;
        $datas['cookie_key']  = $cookie_key;
        $datas['publish']     = 2;
        $datas['transcode']   = 1;
        $datas['clarity']     = $this->vodClarity.',4';
        $datas['catalog_id']  = $this->createCatalog($merchantId);
        $response = $migu -> create(
            $datas['title'],
            $datas['title'],
            $datas['size'],
            $datas['cookie_key'],
            $datas['publish'],
            $datas['transcode'],
            $datas['clarity'],
            $datas['catalog_id'],
            $this->host.'/migu/notice/uploadfinish.json',
            $this->host.'/migu/notice/uploadfinish.json',
            $this->host.'/migu/notice/uploadtrans.json',
            $this->host.'/migu/notice/uploadreview.json',
            $this->host.'/migu/notice/uploadctrl.json'
        );
        if(!isset($response['ret']) ||  $response['ret'] != '0' || !isset($response['result']['task_id'])){
            return ['errcode'=>1,'errmsg'=>'意外错误 ','response'=>$response];
        }
        $datas['task_id']        = $response['result']['task_id'];
        $datas['vid']            = $response['result']['vid'];
        $datas['progress']       = $response['result']['finished_present'];
        $datas['block_total']    = $response['result']['total_block'];
        $datas['block_size']     = $response['result']['blocksize'];
        $datas['block_current']  = $response['result']['blocks'][0];
        $id =  LiveUpload::insert_data($datas);
        return ['errcode'=>0,'errmsg'=>'创建上传任务成功','data'=>[
            'id'=>$id,
            'cookie_key'=>$cookie_key,
            'total'=>$datas['block_total'] ,
            'block_current' => $datas['block_current'],
            'url'=> $migu->getUploadUrl().'&task_id='.$datas['task_id'].'&block='
        ]];
    }

    /**
     * @name 录像上传状态上报
     * @param $id live_upload id
     * @param $status  0 上传完成 1上传失败 2取消上传
     * @return array [ 'errcode'=>0,'errmsg'=>'','data'=>[ 'id'=>'live_record 表id' ]]
     */
    public function  vodReport($id,$status){
        if($id < 1 || !in_array($status,[0,1,2])){
            return ['errcode'=>1,'errmsg'=>'param null '];
        }
        $info = LiveUpload::get_one('id',$id);
        if(!isset($info['id'])){
            return ['errcode'=>1,'errmsg'=>'id null '];
        }
        $migu = new MiguUpload($this->uid,$this->getToken());
        $response = $migu->report($info['task_id'],$status);
        if(!isset($response['ret']) || $response['ret'] != '0'){
            return ['errcode'=>1,'errmsg'=>'response error ', 'response'=>$response];
        }

        LiveUpload::update_data('id',$id,['status'=>$status]);
        if($status != 0){
            return ['errcode'=>0,'errmsg'=>''];
        }
        LiveRecord::insert_data([
            'merchant_id'=>$info['merchant_id'],
            'lid'=>$info['id'],
            'vid'=>$info['vid'],
            'title'=>$info['title'],
            'from_type'=>1,
            'size'=> round($info['size'] / 1024 /1024 ),
            'status' => 1 ,
            'publish_status' => $info['publish'] ,
            'expire_time'=> time() + 86400
        ]);
        return ['errcode'=>0,'errmsg'=>'','data'=>['id'=>0]];
    }
    /**
     * @name 录像上传状态
     * @param $vid
     * @param $status    22=>上传中 23=>上传完成 25=>上传取消  52 => 转码中 53=>转码完成
     * @return array [ 'errcode'=>0,'errmsg'=>'','data'=>[ 'id'=>'live_record 表id' ]]
     */
    public function vodStatus($vid,$status){
        $list = LiveUpload::query()->where(['vid'=>$vid,'is_delete'=>1])->get(['id','vid'])->toArray();
        if(empty($list)){
            return ['errcode'=>1,'errmsg'=>'vid error'];
        }
        //上传记录状态
        if($status == 22){
            $data =['status'=>3,'status_upload'=>0];
        }elseif($status == 23){
            $data =['status_upload'=>1];
        }elseif($status == 25){
            $data =['status_upload'=>2];
        }elseif($status == 52){
            $data =['status'=>4,'status_transcode'=>0];
        }elseif($status == 53){
            $data =['status'=>5,'status_transcode'=>1];
        }else{
            $data = [];
        }
        if(!empty($data)){
            foreach ($list as $k => $v){
                LiveUpload::update_data('id',$v['id'],$data);
            }
        }
        //录像状态
        if($status == 25){
            $list = LiveRecord::query()->where(['vid'=>$vid,'is_delete'=>1])->where('status','<',2)->get(['id','vid'])->toArray();
            foreach ($list as $k => $v) {
                LiveRecord::update_data('id',$v['id'],['status'=>3]);
            }
        }
        return ['errcode'=>0,'errmsg'=>''];
    }

    /**
     * @name 录像上传完成
     * @return array [ 'errcode'=>0,'errmsg'=>'','data'=>[ 'id'=>'live_record 表id' ]]
     */
    public function vodSuccess($vid,$data){
        $formatList = [];
        if(isset($data['result']['formatList'])){
            foreach ($data['result']['formatList'] as $k => $v){
                if($v['errCode'] == '0'){
                    $formatList[$v['templateId']] = $v['playUrl'];
                }
            }
        }
        if(empty($formatList)){
            return ['errcode'=>1,'errmsg'=>'formatList error'];
        }
        $list = LiveRecord::query()->where(['vid'=>$vid,'is_delete'=>1])->where('status','<',2)->get(['id','vid'])->toArray();
        if(empty($list)){
            return ['errcode'=>1,'errmsg'=>'vid error'];
        }
        $datas = [  'status' => 2 ,  'expire_time'=> time() + 86400  ];
        if(isset($formatList['2'])){
            $datas['play'] = $formatList['2'];
        }else{
            $datas['play'] = $formatList['4'];
        }
        $liveVod = new MiguDianbo($this->uid,$this->getToken());
        $response = $liveVod->getVideoList($vid);
        if($response['ret'] == '0'){
            $datas['space'] = $response['result'][0]['space'] ; // K
            $datas['length'] = $response['result'][0]['duration'];
            $datas['cover']   =  $response['result'][0]['cover_img'];
            $datas['trans_type']  = $response['result'][0]['trans_type_ids'];
        }
        foreach ($list as $k => $v) {
            LiveRecord::update_data('id',$v['id'],$datas);
        }
    }


    //========================== 直播事件 ==========================
    /**
     * @name 播放状态
     * @param $channelId 直播ID
     * @param $status 0: 未开始; 1: 直播中; 2: 暂停 ;3: 结束
     * @return void
     */
    public function liveStatus($channelId, $status){
        LiveChannel::update_data('channel_id',$channelId,['play_status'=>$status]);
    }

    /**
     * @name 录制信息添加
     * @param $channelId 直播ID
     * @param $type 1 录制 2 导入
     * @param $data
     * @return void
     */
    public function vodData($channelId, $type, $data){
        $info = LiveChannel::get_one('channel_id',$channelId);
        if(!isset($info['id'])){
            return ['errcode'=>1,'errmsg'=>'id error '];
        }
        $record = LiveRecord::get_one('vid',$data['vid']);
        if(!isset($record['id'])){
            $record['id'] = LiveRecord::insert_data([
                'merchant_id' => $info['merchant_id'],
                'channel_id' => $channelId,
                'lid' => $info['id'],
                'title' => $info['title'],
                'img_url' => $info['img_url'],
                'length' => intval(($data['endTime']-$data['startTime'])/1000),
                'status'=> -1,
                'play' => isset($data['playUrl'])?$data['playUrl']:'',
                'vid' => $data['vid'],
                'start_time' => intval($data['startTime']/1000),
                'end_time' => intval($data['endTime']/1000)
            ]);
            if(isset($record['id'])){
                $this->copyLiveSet($record['id']);//录播同步直播设置
            }
        }
        if( $type == 1){
            if( $data['endTag'] ){
                LiveRecord::update_data('id',$record['id'],[ 'status' => 0 ,'length'=> intval(($data['endTime']-$data['startTime'])/1000) ,'start_time'=>intval($data['startTime']/1000),'end_time'=> intval($data['endTime']/1000)]);
            }else{
                LiveRecord::update_data('id',$record['id'],[ 'status' => -1 ,'length'=> intval(($data['endTime']-$data['startTime'])/1000) ,'start_time'=>intval($data['startTime']/1000),'end_time'=> intval($data['endTime']/1000)]);
            }
        }elseif ( $type == 2 ){
            $datai = [
                'status' => 2 ,
                'publish_status' => 1 ,
                'play'   => !isset($data['list'][0]['vspoturl']) || empty($data['list'][0]['vspoturl']) ? '' : $data['list'][0]['vspoturl'],
                'download'   => !isset($data['list'][0]['vdownloadurl']) || empty($data['list'][0]['vdownloadurl']) ? '' : $data['list'][0]['vdownloadurl'],
                'length'=> intval(($data['endTime']-$data['startTime'])/1000) ,
                'start_time'=>intval($data['startTime']/1000),
                'end_time'=> intval($data['endTime']/1000),
                'expire_time'=> intval($data['endTime']/1000) + $this->delete_vod,
            ];

            $liveVod = new MiguDianbo($this->uid,$this->getToken());
            $response = $liveVod->getVideoList($data['vid']);
            if($response['ret'] == '0'){
                $datai['size']  = round($response['result'][0]['size']/ 1024 /1024 );// M
                $datai['space'] = $response['result'][0]['space'] ; // K
                $datai['length'] = $response['result'][0]['duration'];
                $datai['cover']   =  $response['result'][0]['cover_img'];
                $datai['trans_type']  = $response['result'][0]['trans_type_ids'];
            }
            $response = $liveVod->publishVideo($data['vid'],2);
            if($response['ret'] == '0'){
                $datai['publish_status'] = 2;
            }
            $count = LiveRecord::selectVodCount($info['id']);
            $datai['title'] = $info['title'].($count > 0 ?'（'.$count.'）':'');
            LiveRecord::update_data('id',$record['id'],$datai);
        }
    }


    //========================== 目录 ==========================
    /**
     * @name 创建视频目录
     * @param $merchantId 直播ID
     *  @param $parentName = '' 目录名称
     *  @param $parentId = '0' 父目录id
     * @return void
     */
    public function createCatalog($merchantId, $parentName = '', $parentId = '0'){
        $info = LiveCatalog::get_one('merchant_id',$merchantId);
        if(isset($info['id'])){
            return $info['catalog_id'];
        }
        $parentName = empty($parentName) ? 'merchant_'.$merchantId.'_'.$parentId : $parentName ;
        $migu = new MiguDianbo($this->uid,$this->getToken());
        $response = $migu->catalog_create($parentName, $parentId);
        if(!isset($response['ret']) ||  $response['ret'] != '0' || !isset($response['result']['catalogId'])){
            return '0';
        }
        LiveCatalog::insert_data(['merchant_id'=>$merchantId,'parent_id'=>$parentId,'catalog_id'=>$response['result']['catalogId'],'name'=>$parentName]);
        return $response['result']['catalogId'];
    }

    //========================== Console ==========================

    public function statsConsole(){
        $delay = 86400;
        $list = LiveChannel::selectFinishChannel();
        foreach ($list as $k => $v) {
            if(empty($v['channel_id'])){
                continue;
            }
            $this->statsLive(1, $v['channel_id'], $v['merchant_id'], $v['id'],$delay);
            $this->statsLive(3, $v['channel_id'], $v['merchant_id'], $v['id'],$delay);
            $this->statsLive(4, $v['channel_id'], $v['merchant_id'], $v['id'],$delay);
            $this->statsLive(5, $v['channel_id'], $v['merchant_id'], $v['id'],$delay);
            $this->statsLive(6, $v['channel_id'], $v['merchant_id'], $v['id'],$delay);
        }
        $list = LiveRecord::selectOnline();
        foreach ($list as $k => $v) {
            if(empty($v['vid'])){
                continue;
            }
            $this->statsLive(1, $v['vid'], $v['channel_id'], $v['merchant_id'], $v['id'],$delay);
            $this->statsLive(2, $v['vid'], $v['channel_id'], $v['merchant_id'], $v['id'],$delay);
            $this->statsLive(3, $v['vid'], $v['channel_id'], $v['merchant_id'], $v['id'],$delay);
            $this->statsLive(4, $v['vid'], $v['channel_id'], $v['merchant_id'], $v['id'],$delay);
            $this->statsLive(5, $v['vid'], $v['channel_id'], $v['merchant_id'], $v['id'],$delay);
        }
    }

    public function closeConsole(){
        $list = LiveChannel::selectFinishChannel();
        $miguLive = new MiguLive($this->uid,$this->getToken());
        foreach ($list as $k => $v) {
            if(empty($v['channel_id']) ||  $v['play_status'] == 3 ||  $v['status'] == -1){
                continue;
            }
            $response = $miguLive->closeChannel($v['channel_id']);
            if($response['ret'] == '0' || $response['ret'] == 0 ){
                LiveEvent::insert_data(['channel_id'=>$v['channel_id'],'type'=>11,'status'=>$response['ret'],'request'=>json_encode($response) ]);
                LiveChannel::update_data('id',$v['id'],['status' => -1 ]);
            }
        }
    }

    public function openConsole(){
        $list = LiveChannel::selectStartChannel($this->advance);
        $miguLive = new MiguLive($this->uid,$this->getToken());
        foreach ($list as $k => $v) {
            if(empty($v['channel_id']) || ($v['play_status'] != 3 && $v['status'] != -1)){
                continue;
            }
            $response = $miguLive->openChannel($v['channel_id']);
            if($response['ret'] == '0' || $response['ret'] == 0 ){
                LiveEvent::insert_data(['channel_id'=>$v['channel_id'],'type'=>10,'status'=>$response['ret'],'request'=>json_encode($response) ]);
                LiveChannel::update_data('id',$v['id'],['status' => 1 ]);
            }
        }
    }

    public function deleteRecordConsole(){
        $list = LiveRecord::selectFinishVod();
        $liveRecord = new MiguDianbo($this->uid,$this->getToken());
        foreach ($list as $k => $v) {
            $response = $liveRecord -> deleteVideo($v['vid']);
            if($response['ret'] == '0' || $response['ret'] == 0){
                LiveRecord::update_data('id',$v['id'],['is_delete'=>-1]);
            }
        }
    }

    public function maxChannelConsole(){
        $length = 43200;
        $list = LiveChannel::selectMaxChannel($length);
        $miguStats = new MiguStats($this->uid,$this->getToken());
        foreach ($list as $k => $v) {
            $response = $miguStats -> getUseronlineMax($v['channel_id'],$_SERVER['REQUEST_TIME']-($length * 2),$_SERVER['REQUEST_TIME']);
            $list = isset($response['result']['content'])?$response['result']['content']:[];// time  num
            $max = 0;
            foreach ($list as $ks => $vs) {
                if($vs['num'] > $max){
                    $max =  $vs['num'];
                }
            }
            if($max  >  $v['view_max']){
                LiveChannel::update_data('id',$v['id'],['view_max'=>$max]);
            }
        }
    }

    public function closePublishConsole(){

        $list = LiveRecord::selectClosePublish();
        $liveVod = new MiguDianbo($this->uid,$this->getToken());
        foreach ($list as $k => $v ) {
            $info = MerchantSetting::get_data_by_id($v['merchant_id']);
            if($info['live_record'] < 1 && strtotime($info['live_record_time']) < ($_SERVER['REQUEST_TIME'] - 1800)){
                $response = $liveVod->publishVideo($v['vid'],2);
                if($response['ret'] == '0'){
                    LiveRecord::update_data('id',$v['id'],['publish_status'=>2]);
                }
            }
        }
    }

    //========================== how ==========================
    public function getRealViewsNumber($type, $id, $merchant_id)
    {
        $views_number = 0;
        if($type == 1){ //直播
            $live_info = LiveInfo::get_data_by_id($id, $merchant_id);
            if($live_info){
                $number_cache_key = CacheKey::live_online_number($id);
                if(Cache::has($number_cache_key)){
                    $views_number = Cache::get($number_cache_key);
                }else{
                    $response = $this->onlineStats(1, $live_info['channel_id'], $merchant_id);
                    if($response && $response['errcode'] == 0){
                        $views_number = $response['data']['num'];
                    }
                }
            }
        }

        if($type == 2){ //录播
            $number_cache_key = CacheKey::record_online_number($id);
            if(Cache::has($number_cache_key)){
                $views_number = Cache::get($number_cache_key);
            }else{
                $response = $this->onlineStats(2, $id, $merchant_id);
                if($response && $response['errcode'] == 0){
                    $views_number = $response['data']['num'];
                }
            }
        }

        return $views_number;
    }
    
    //录播同步直播设置
    //$live_record_id 录播id
    public function copyLiveSet($live_record_id){
        
        $data = LiveRecord::get_one('id', $live_record_id);
        
        if(!$data){
            return false;
        }
        
        $merchant_id = $data['merchant_id'];
        
        $channel_id = $data['lid'];

        $live_info = LiveInfo::select('id', 'view_coupons', 'desc', 'type', 'range')
                            ->where('merchant_id', $merchant_id)
                            ->where('channel_id', $channel_id)
                            ->first();

        if($live_info){
            
            if($live_info['type'] == 1){
                //普通直播
                $live_record_data['type'] = $live_info['type'];
                $live_record_data['desc'] = $live_info['desc'];
                LiveRecord::update_data('id', $live_record_id, $live_record_data);
            }else{
                //电商直播
                $live_record_data['type'] = $live_info['type'];
                $live_record_data['view_coupons'] = $live_info['view_coupons'];
                $live_record_data['range'] = $live_info['range'];
                LiveRecord::update_data('id', $live_record_id, $live_record_data);
                
                if($live_info['range'] == 1){//自选商品
                    //关联商品
                    $livegoodslist = LiveGoods::get_data_by_liveid($live_info['id'], $merchant_id);
                    $ids = [];
                    if($livegoodslist) {
                        foreach($livegoodslist as $k=>$v) {
                            
                            $live_record_goods_data['merchant_id'] = $merchant_id;
                            $live_record_goods_data['live_record_id'] = $live_record_id;
                            $live_record_goods_data['goods_id'] = $v['goods_id'];
                            
                            LiveRecordGoods::insert_data($live_record_goods_data);
                        }
                    }
                }
                
            }
        }
        
    }
    
    
    
    
    
    

}
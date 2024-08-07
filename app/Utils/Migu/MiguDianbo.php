<?php
/**
 * User: yanghui@dodoca.com
 */

namespace App\Utils\Migu;

use App\Utils\Weixin\Http;

class MiguDianbo
{
    const URL = 'http://api.migucloud.com';

    private $uid;
    private $token;

    public function __construct($uid,$token)
    {
        $this->uid = $uid;
        $this->token = $token;
    }
	
	//ftp上传接口
    public function uploadByFtp()
	{
		$data = [
			'tasks'=>[
				[
					'priority'		=> 0, //优先级
					'inUri'			=> '/test002/2jBv9Idg5dRoQA6KvzwRKZ.mp4', //视频存放路径
					'transMp4'		=> 1, //mp4转码 0：启用；1：关闭
					'md5sum'		=> '7f6d5ca25b415a98606b21b5ed43f50b', //视频md5值
					'cbFinishUrl'	=> '', //回调url
					'zmOutList'		=> [
						[
							'templateId'	=> '1',
							'outFormat'		=> 'MP4',
							'logoId'		=> [0,1],
						],
						[
							'templateId'	=> '2',
							'outFormat'		=> 'MP4',
							'logoId'		=> [0,1],
						],
					],
				],
			],
		];
		return $this->mxCurl(static::URL.'/vod2/t0/uploadByFtp?uid='.$this->uid.'&token='.$this->token,json_encode($data));
	}
	
	//视频查询
	public function getVideoList($vids)
	{
		$vids = is_array($vids) ? $vids : [$vids];
		$data = [
			'vid' => implode(',',$vids),
		];
		return $this->mxCurl(static::URL.'/vod2/v0/getVideoList?uid='.$this->uid.'&token='.$this->token,json_encode($data));
	}

	/**
     * @name 视频高级查询
     * @param $vid string
	 * @param $title string 标题
	 * @param $catalog_id string 目录 0-所有目录
	 * @param $child string 0-只包括当前目录；1-包括当前目录和子目录
     * @param $tags String 视频标签 多个标签用“,”分割
	 * @param $public_flag Integer 1为上线(true)，2为下线
	 * @param $status Integer 
	 * @param $start_date 2017-11-17
	 * @param $end_date 2017-11-17
     */
	public function getVideoListForAdv($vid='',$title='',$catalog_id=0,$child=0,$tags='',$public_flag='',$status='',$start_date='',$end_date='',$page_number=1,$page_size=10)
	{
		$data = [
			'vid'			=> $vid,
			'title'			=> $title,
			'catalog_id'	=> $catalog_id,
			'child'			=> $child,
			'tags'			=> $tags,
			'public_flag'	=> $public_flag,
			'status'		=> $status,
			'start_date'	=> $start_date,
			'end_date'		=> $end_date,
			'page_number'	=> $page_number,
			'page_size'		=> $page_size,
		];

		return $this->mxCurl(static::URL.'/vod2/v0/getVideoListForAdv?uid='.$this->uid.'&token='.$this->token,json_encode($data));
	}

	//视频删除
	public function deleteVideo($vid)
	{
		$data = [
			'vid' => $vid,
		];
		return $this->mxCurl(static::URL.'/vod2/v0/deleteVideo?uid='.$this->uid.'&token='.$this->token,json_encode($data));
	}

	/**
     * @name 点播视频上下线
     * @param $vids string/array 
     * @param $status 1 上线 2下线
     */
	public function publishVideo($vids,$status)
	{
		$vids = is_array($vids) ? $vids : [$vids];
		$data = [
			'publishstatus'	=> $status==1 ? 41 : 42,
			'vid' => implode(',',$vids),
		];
		return $this->mxCurl(static::URL.'/vod2/v0/publishVideo?uid='.$this->uid.'&token='.$this->token,json_encode($data));
	}

	/**
     * @name 批量修改视频分类
     * @param $vid string
     * @param $catalog_id 分类id
	 * @param $catalog_name 分类名称
     */
	public function updateVideoForCatalog($vid,$catalog_id,$catalog_name)
	{
		$data = [
			'vid' => $vid,
			'catalog_id' => $catalog_id,
			'catalog_name' => $catalog_name,
		];
		return $this->mxCurl(static::URL.'/vod2/v0/updateVideoForCatalog?uid='.$this->uid.'&token='.$this->token,json_encode($data));
	}

	/**
     * @name 创建分类
     * @param $name		string	分类名称
     * @param $parentId	String	父分类ID，默认0（表示根分类）
     */
	public function catalog_create($name,$parentId)
	{
		$data = [
			'name' => $name,
			'parentId' => $parentId,
		];
		return $this->mxCurl(static::URL.'/vod2/v2/catalog_create?uid='.$this->uid.'&token='.$this->token,json_encode($data));
	}

	/**
     * @name 移动分类
     * @param $catalogId	String	分类ID
     * @param $parentId		String	父分类ID
     */
	public function catalog_move($catalogId,$parentId)
	{
		$data = [
			'catalogId' => $catalogId,
			'parentId' => $parentId,
		];
		return $this->mxCurl(static::URL.'/vod2/v2/catalog_move?uid='.$this->uid.'&token='.$this->token,json_encode($data));
	}

	/**
     * @name 修改分类名称
     * @param $catalogId	String	分类ID
     * @param $name			String	分类名称
     */
	public function catalog_edit($catalogId,$name)
	{
		$data = [
			'catalogId' => $catalogId,
			'name' => $name,
		];
		return $this->mxCurl(static::URL.'/vod2/v2/catalog_edit?uid='.$this->uid.'&token='.$this->token,json_encode($data));
	}

	/**
     * @name 删除分类
     * @param $catalogId	String	分类ID
     */
	public function catalog_delete($catalogId)
	{
		$data = [
			'catalogId' => $catalogId,
		];
		return $this->mxCurl(static::URL.'/vod2/v2/catalog_delete?uid='.$this->uid.'&token='.$this->token,json_encode($data));
	}

	/**
     * @name 分类树查询
     */
	public function catalog_query()
	{
		$data = [];
		return $this->mxCurl(static::URL.'/vod2/v2/catalog_query?uid='.$this->uid.'&token='.$this->token,$data,false);
	}

	/**
     * @name 获取CDN播放地址-无防盗链
	 * @param $vid		String	视频ID
	 * @param $vtype	String	码率类型	模板IDlist,多个模板以,间隔，如:0,1,2
	 * @param $source	String	源站标识	source,字段不传时默认走cdn,source=api.migucloud.com时走源站播放
     */
	public function getUrl($vid,$vtype='',$source='')
	{
		$data = [
			'vid' => $vid,
			'vtype'	=> $vtype,
			'source' => $source,
		];
		return $this->mxCurl(static::URL.'/vod2/v1/getUrl?uid='.$this->uid.'&token='.$this->token,$data,false);
	}

	/**
     * @name 获取CDN播放地址-防盗链
	 * @param $vid		String	视频ID
	 * @param $vtype	String	码率类型	模板IDlist,多个模板以,间隔，如:0,1,2
     */
	public function getUrlVerify($vid,$vtype='')
	{
		$data = [
			'vid' => $vid,
			'vtype'	=> $vtype,
		];
		return $this->mxCurl(static::URL.'/vod2/v1/getUrlVerify?uid='.$this->uid.'&token='.$this->token,$data,false);
	}

	/**
     * @name 获取CDN下载地址
	 * @param $vid		String	视频ID
	 * @param $vtype	String	码率类型	模板IDlist,多个模板以,间隔，如:0,1,2
     */
	public function getDownloadUrl($vid)
	{
		$data = [
			'vid' => $vid,
		];
		return $this->mxCurl(static::URL.'/vod2/v1/getDownloadUrl?uid='.$this->uid.'&token='.$this->token,$data,false);
	}

	/**
     * @name 获取CDN下载地址
	 * @param $vids		String	视频ID	多个vid用英文 ,隔开，最多20个
     */
	public function getDownloadUrlForVids($vids)
	{
		$vids = is_array($vids) ? $vids : [$vids];
		$data = [
			'vids' => implode(',',$vids),
		];
		return $this->mxCurl(static::URL.'/vod2/v1/getDownloadUrlForVids?uid='.$this->uid.'&token='.$this->token,$data,false);
	}

	/**
     * @name 创建转码任务
     * @param $vid			String	视频ID
	 * @param $priority		Integer	转码优先级		转码优先级：0-普通转码；1-热点；2-绿色通道
	 * @param $needCombine	boolean	是否需要合片	true:合片,默认;false:不合片
	 * @param $needTransMp4	boolean	是否需要Mp4转码	true:每个对应的模板对应增加Mp4格式的视频输出;默认为false
	 * @param $slices		boolean	视频剪辑分片	视频裁剪时间点设置,单位为秒 [{"start": 3,"end": 180},{"start": 240,"end": 360}]
	 * @param $transList	List	转码格式列表	[{"outFormat":"MP4","templateId":"27"}]
     */
	public function trans_create($vid,$priority=0,$needCombine=false,$needTransMp4=false,$slices=[],$transList=[])
	{
		$data = [
			'tasks'=>[
				[
					'needCombine'	=> $needCombine, 
					'needTransMp4'	=> $needTransMp4, 
					'priority'		=> $priority, 
					'slices'		=> $slices, //视频md5值
					'transList'		=> $transList, //回调url
					'vid'			=> $vid,
				],
			],
		];

		return $this->mxCurl(static::URL.'/vod2/t1/trans/create?uid='.$this->uid.'&token='.$this->token,json_encode($data));
	}

	/**
     * @name 创建转码任务
     * @param $vid	String/Array	视频ID
     */
	public function trans_cancel($vids)
	{
		$vids = is_array($vids) ? $vids : [$vids];
		$data = [
			'tasks'=>$vids,
		];

		return $this->mxCurl(static::URL.'/vod2/t1/trans/cancel?uid='.$this->uid.'&token='.$this->token,json_encode($data));
	}

	/**
     * @name 转码状态查询
	 * @param $vid		String	视频ID	
     */
	public function queryTransStatus($vid)
	{
		$data = [
			'vid' => $vid,
		];
		return $this->mxCurl(static::URL.'/vod2/t1/query/queryTransStatus?uid='.$this->uid.'&token='.$this->token,$data,false);
	}

	/**
     * @name 转码进度查询
	 * @param $vid		String	视频ID	
     */
	public function queryTransPercent($vid)
	{
		$data = [
			'vid' => $vid,
		];
		return $this->mxCurl(static::URL.'/vod2/t1/query/queryTransPercent?uid='.$this->uid.'&token='.$this->token,$data,false);
	}
	
	/**
     * @name 转码时长查询
	 * @param $vid		String	视频ID	
     */
	public function transTime($vid)
	{
		$data = [
			'vid' => $vid,
		];
		return $this->mxCurl(static::URL.'/vod2/t1/query/transTime?uid='.$this->uid.'&token='.$this->token,$data,false);
	}

	/**
     * @name 转码时长查询（根据uid查询总时长）
     */
	public function transSumTime($vid)
	{
		$data = [];
		return $this->mxCurl(static::URL.'/vod2/t1/query/transSumTime?uid='.$this->uid.'&token='.$this->token,$data,false);
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
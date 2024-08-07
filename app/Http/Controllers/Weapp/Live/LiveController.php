<?php

namespace App\Http\Controllers\Weapp\Live;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Response;
use App\Utils\CacheKey;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use App\Facades\Member;
use App\Models\LiveViewer;
use App\Models\LiveInfo;
use App\Models\LiveChannel;
use App\Models\LiveRecord;
use App\Models\LiveGoods;
use App\Models\LiveRecordGoods;
use App\Models\Goods;
use App\Models\Coupon;
use App\Models\CouponCode;
use App\Models\LiveRecordViewer;
use App\Models\MerchantSetting;
use App\Models\Member as MemberModel;
use App\Services\MerchantService;
use App\Services\LiveService;
use App\Models\WeixinInfo;
use App\Utils\CommonApi;
use App\Services\WeixinService;
use Qiniu\Auth;
use GuzzleHttp\Client;
/**
 * 直播
 *
 * @package default
 * @author 张长春、郭其凯
 **/
class LiveController extends Controller
{
	
	public function __construct(LiveService $liveService) {
		$this->member_id   = Member::id();
		$this->merchant_id = Member::merchant_id();

		$this->liveService = $liveService;
	}
	
	/**
	 * 进入直播间
	 *
	 * @return json
	 * @author 郭其凯
	 **/
    public function getLive(Request $request, $id)
    {
    	$data = LiveInfo::get_data_by_id($id, $this->merchant_id);

    	if(!$data){
    		return ['errcode' => 99001, 'errmsg' => '活动不存在'];
    	}

    	if($data->status == 2){
    		return ['errcode' => 310022, 'errmsg' => '直播已取消'];
    	}

    	$status = 0;
    	if(strtotime($data->start_time) > time()) {		//未开始
			$status = 1;
		} else if(strtotime($data->end_time) < time()) {	//已结束
			$status = 2;
		} else if($data->status == 1) {			//暂停中
			$status = 4;
		} else {
			$status = 3;			//直播中
		}

		//对正在直播中的进行人数限制判断
		$view_max = $data->view_max * 100; //最大观看人数
		if($status == 3){
			//人数缓存key
			$number_cache_key = CacheKey::live_online_number($id);
			$online_number    = Cache::get($number_cache_key);
		    if($online_number){
		    	if($online_number >= $view_max){
		    		//记录超出人数
		    		LiveInfo::increment_data($id, $this->merchant_id, 'exceeds_number', 1);
		    		return ['errcode' => 310020, 'errmsg' => '超过最大人次，拒绝进入'];
		    	}
		    }else{
		    	$api_online_number = 0;
		    	$response = $this->liveService->onlineStats(1, $data->channel_id, $this->merchant_id);
		    	if($response && $response['errcode'] == 0){
		    		$api_online_number = $response['data']['num'];
		    		if($api_online_number >= $view_max){
		    			//记录超出人数
		    			LiveInfo::increment_data($id, $this->merchant_id, 'exceeds_number', 1);
		    			return ['errcode' => 310020, 'errmsg' => '超过最大人次，拒绝进入'];
		    		}
		    	}
		    	Cache::put($number_cache_key, $api_online_number, 11);
		    }

		    //记录观看直播的会员ID
			$member_cache_key = CacheKey::live_online_member($id);
			$member_list      = Cache::get($member_cache_key);
			if($member_list){
				if(!in_array($this->member_id, $member_list)){
					array_push($member_list, $this->member_id);
			    	Cache::put($member_cache_key, $member_list, 11);
					Cache::increment($number_cache_key); //递增一个人次
				}
			}else{
				Cache::put($member_cache_key, [$this->member_id], 11);
				Cache::increment($number_cache_key); //递增一个人次
			}
			
			$result = $this->liveService->playChannel($data->channel_id, $this->merchant_id);
	    	if($result && $result['errcode'] == 0){
	    		$data->channel_info = $result['data'];
	    	}elseif($result['errcode'] == 4){
	    		$status = 5; //超过最大人次，拒绝进入
	    	}
	    }

	    $data->status = $status;
    	//参与直播商品数量
    	$goods_num = 0;
    	if($data->range == 1){ //自选商品
    		$goods_num = LiveGoods::where('merchant_id', $this->merchant_id)->where('live_id', $id)->where('is_delete', 1)->count();
    	}
    	if($data->range == 2){ //全店参与
    		$goods_num = Goods::where('merchant_id', $this->merchant_id)->where('onsale', 1)->where('is_delete', 1)->count();
    	}
    	$data->goods_num = $goods_num;

    	//参与直播优惠劵
    	$coupon = $this->getCoupon($id, $this->merchant_id, $this->member_id);
    	if($coupon && $coupon['errcode'] == 0){
    		$data->coupon_list = $coupon['data'];
    	}

    	$data->room_flag = encrypt(env('APP_ENV', 'production') . '_' . $id, 'E');

    	$member_info = MemberModel::get_data_by_id($this->member_id, $this->merchant_id);
    	$data->nickname = $member_info->name;
    	$data->avatar = $member_info->avatar;

    	return ['errcode' => 0, 'data' => $data];
	}

	/**
	 * 录播详情
	 *
	 * @return json
	 * @author 郭其凯
	 **/
	public function getRecord(Request $request, $id)
	{
		$data = LiveRecord::get_one('id', $id);

		if($data && $data['merchant_id'] == $this->merchant_id){
			if($data['publish_status'] == 1){
				//观看人数
				$number_cache_key = CacheKey::record_online_number($id);
				//记录录播观看人数
				$member_cache_key = CacheKey::record_online_member($id);

				$member_list = Cache::get($member_cache_key);
				if($member_list){
					if(!in_array($this->member_id, $member_list)){
						array_push($member_list, $this->member_id);
				    	Cache::put($member_cache_key, $member_list, 11);
				    	if(!Cache::has($number_cache_key)){
							Cache::put($number_cache_key, 1, 11);
						}else{
							Cache::increment($number_cache_key); //递增一个人次
						}
					}
				}else{
					Cache::put($member_cache_key, [$this->member_id], 11);
					if(!Cache::has($number_cache_key)){
						Cache::put($number_cache_key, 1, 11);
					}else{
						Cache::increment($number_cache_key);
					}
				}

				//删除下载地址
				if(isset($data['download'])){
					unset($data['download']);
				}

				//动态获取录播播放地址
				$getPlay = $this->liveService->getVodPlay($id, $this->merchant_id);
				if($getPlay['errcode'] == 0){
					$data['play'] = $getPlay['play'];
				}else if($getPlay['errcode'] != 3){
                    return ['errcode' => 310021, 'errmsg' => $getPlay['errmsg']];
                }

				$channel_id = $data['lid'];

					
                $live_info = LiveInfo::select('id', 'title', 'cover_img', 'is_buy', 'range')
                                    ->where('merchant_id', $this->merchant_id)
                                    ->where('channel_id', $channel_id)
                                    ->first();
                if($live_info){
                    $data['live_id'] = $live_info['id'];
                }else{
                    $data['live_id'] = 0;
                }
				
				
				
	    		//参与直播商品数量
		    	$goods_num = 0;
		    	if($data['range'] == 1){ //自选商品
		    		$goods_num = LiveRecordGoods::where('merchant_id', $this->merchant_id)->where('live_record_id', $data['id'])->where('is_delete', 1)->count();
		    	}

		    	if($data['range'] == 2){ //全店参与
		    		$goods_num = Goods::where('merchant_id', $this->merchant_id)->where('onsale', 1)->where('is_delete', 1)->count();
		    	}

		    	$data['goods_num'] = $goods_num;

		    	//参与直播优惠劵
		    	$coupon = $this->getCoupon($data['id'], $this->merchant_id, $this->member_id,2);
		    	if($coupon && $coupon['errcode'] == 0){
		    		$data['coupon_list'] = $coupon['data'];
		    	}
		    	
		    	
		    	$data['room_flag'] = encrypt(env('APP_ENV', 'production') . '_record_' . $id, 'E');
		    	
		    	$member_info = MemberModel::get_data_by_id($this->member_id, $this->merchant_id);
		    	$data['nickname'] = $member_info['name'];
		    	$data['avatar'] = $member_info['avatar'];
		    	

		    	return ['errcode' => 0, 'data' => $data];
			    
		    	
		    	
			}else{
				return ['errcode' => 310021, 'errmsg' => '视频已下架'];
			}
    	}else{
    		return ['errcode' => 99001, 'errmsg' => '视频不存在或已下架'];
    	}
	}

    /**
	 * 参与优惠劵
	 *
	 * @return array
	 * @author 郭其凯
	 * 
	 * $type，1->直播，2->录播
	 * $live_id，$type=1->直播id， $type=2->录播id
	 **/
    private function getCoupon($live_id, $merchant_id, $member_id, $type=1)
    {
        
        //判断类型
        if($type == 1){
            //直播
            $data = LiveInfo::get_data_by_id($live_id, $merchant_id);
        }else{
            //录播
            $data = LiveRecord::get_one('id', $live_id);
        }
        
    	

    	if(!$data){
    		return ['errcode' => 99001, 'errmsg' => '活动不存在'];
    	}

    	if(!empty($data['view_coupons'])){
    		$view_coupons = explode(',', $data['view_coupons']);

    		$query = Coupon::query();
			$query->select('id', 'card_color', 'name', 'coupon_sum', 'send_num', 'content_type', 'coupon_val', 'is_condition', 'condition_val', 'status', 'memo', 'time_type', 'effect_time', 'period_time', 'dt_validity_begin', 'dt_validity_end', 'get_type', 'get_num', 'rang_goods');
			$query->whereIn('id', $view_coupons);
			$query->where('merchant_id', $merchant_id);
			$query->where('is_close', 1);
			$query->where('is_delete', 1);
			$query->where(function ($query){
				$query->where('time_type', 0)->orWhere(function ($query){
					$query->where('time_type', 1)->where('dt_validity_end', '>', Carbon::now());
				});
			});
			$query->whereRaw('coupon_sum > send_num');
			$query->orderBy('time_type', 'desc')->orderBy('condition_val')->orderBy('coupon_val', 'desc')->orderBy('created_time', 'desc');
			$coupon = $query->get();

			foreach ($coupon as $key => $row) {
				$coupon_id = $row['id'];
				$coupon[$key]['show_status'] = 0;

				if($row['time_type'] == 1){
					$coupon[$key]['dt_validity_begin'] = date('Y.m.d', strtotime($row['dt_validity_begin']));
					$coupon[$key]['dt_validity_end']   = date('Y.m.d', strtotime($row['dt_validity_end']));
				}

				if($row['content_type'] == 2){
					$coupon[$key]['coupon_val'] = floatval($row['coupon_val']);
				}

				if($member_id){
					if($row['get_type'] == 2 && $row['get_num'] > 0){
						$member_code_wheres = [
							[
								'column'   => 'merchant_id',
								'operator' => '=',
								'value'    => $merchant_id
							],
							[
								'column'   => 'coupon_id',
								'operator' => '=',
								'value'    => $coupon_id
							],
							[
								'column'   => 'member_id',
								'operator' => '=',
								'value'    => $member_id
							],
							[
								'column'   => 'is_delete',
								'operator' => '=',
								'value'    => 1
							]
						];

						$member_code_quantity = CouponCode::get_data_count($member_code_wheres);

						if($member_code_quantity >= $row['get_num']){
							$coupon[$key]['show_status'] = 2;
						}
					}
				}
			}

			return ['errcode' => 0, 'data' => $coupon];
    	}
	}

	/**
	 * 参与商品
	 *
	 * @return json
	 * @author 郭其凯
	 * 
	 * $type，1->直播，2->录播
	 * $live_id，$type=1->直播id， $type=2->录播id
	 **/
    public function getGoods(Request $request)
    {
        $type = $request->input('type', 1);
        
		$live_id  = $request->live_id;
		$pagesize = $request->input('pagesize', 10);
		$page     = $request->input('page', 1);
		$offset   = ($page - 1) * $pagesize;
		
		if($type == 1){
		    //直播
		    $data = LiveInfo::get_data_by_id($live_id, $this->merchant_id);
		}else{
		    //录播
		    $data = LiveRecord::get_one('id', $live_id);
		}
    	

    	if(!$data){
    		return ['errcode' => 0, 'count' => 0, 'data' => []];
    	}

		$total = 0;
		$list  = [];
		if($data['range'] == 1){ //自选商品
		    
		    if($type == 1){
		        //直播
		        $live_goods = LiveGoods::get_data_by_liveid($live_id, $this->merchant_id);
		    }else{
		        //录播
		        $live_goods = LiveRecordGoods::get_data_by_live_record_id($live_id, $this->merchant_id);
		    }
			
			$goods_ids = [];
			if($live_goods){
				foreach($live_goods as $row){
					$goods_ids[] = $row['goods_id'];
				}
			}

			if($goods_ids){
				$query = Goods::query();
				$query->select('id', 'title', 'img', 'price', 'base_csale', 'csale');
				$query->whereIn('id', $goods_ids);
				$query->where('merchant_id', $this->merchant_id);
				$query->where('onsale', 1)->where('is_delete', 1);
				$total = $query->count();
				$list  = $query->orderBy('csale', 'desc')->skip($offset)->take($pagesize)->get();
			}
		}
		
		if($data['range'] == 2){ //全店参与
			$query = Goods::query();
			$query->select('id', 'title', 'img', 'price', 'base_csale', 'csale');
			$query->where('merchant_id', $this->merchant_id);
			$query->where('onsale', 1)->where('is_delete', 1);
			$total = $query->count();
			$list  = $query->orderBy('csale', 'desc')->skip($offset)->take($pagesize)->get();
		}

		return ['errcode' => 0, 'count' => $total, 'data' => $list];
	}

	/**
	 * 直播列表
	 *
	 * @param string $offset  偏移量
     * @param string $limit 每页数量
     *
	 * @return json
	 * @author 郭其凯
	 **/
    public function liveList(Request $request)
    {
    	$pagesize = $request->input('pagesize', 10);
		$page     = $request->input('page', 1);
		$offset   = ($page - 1) * $pagesize;

		$query = LiveInfo::query();
		$query->select('id', 'title', 'cover_img', 'status', 'virtual_sum', 'start_time', 'end_time','type');
		$query->where('merchant_id', $this->merchant_id);
		$query->where('status', '!=', 2);
		$query->where('is_delete', 1);
		$total = $query->count();
		$data  = $query->orderBy('id', 'desc')->skip($offset)->take($pagesize)->get();

		if($total){
			foreach($data as &$row){
				$status = 0;
                if(strtotime($row->start_time) > time()) { //未开始
                    $status = 1;
                } else if(strtotime($row->end_time) < time()) { //已结束
                    $status = 2;
                } else if($row->status == 1) { //暂停中
                    $status = 4;          
                } else { //直播中
                    $status = 3;            
                }
                
                if($status == 3){ //获取真实在线人数
                	$views_number = $this->liveService->getRealViewsNumber(1, $row->id, $this->merchant_id);
                }

                $row->status = $status;
				$row->views_number = isset($views_number) ? (int)$views_number : 0;
	    	}
		}
    	
    	return ['errcode' => 0, 'count' => $total, 'data' => $data];
	}

	/**
	 * 录播列表
	 *
	 * @param string $offset  偏移量
     * @param string $limit 每页数量
     *
	 * @return json
	 * @author 郭其凯
	 **/
    public function recordList(Request $request)
    {
    	$pagesize = $request->input('pagesize', 10);
		$page     = $request->input('page', 1);
		$offset   = ($page - 1) * $pagesize;

		$query = LiveRecord::query();
		$query->select('id', 'img_url', 'title' , 'type', 'virtual_sum');
		$query->where('merchant_id', $this->merchant_id);
		$query->where('lid', '>', 0);
		$query->where('status', 2);
		$query->where('publish_status', 1);
		$query->where('is_delete', 1);
		$count = $query->count();
		$data = $query->orderBy('id', 'desc')->skip($offset)->take($pagesize)->get();

		foreach($data as &$row){
			//获取真实在线人数
			$views_number = $this->liveService->getRealViewsNumber(2, $row->id, $this->merchant_id);
			$row->views_number = (int)$views_number;
		}

    	return ['errcode' => 0, 'count' => $count, 'data' => $data];
	}
	
	/**
     * 推送会员观看信息（直播，录播）
     *
	 * @auth zhangchangchun@dodoca.com
     * @param string $type 类型 1-直播观看，2-录播观看
	 * @param string $live_id 房间id
     *
     * @return \Illuminate\Http\Response
     */
	public function push(Request $request) {
		$type = isset($request['type']) ? (int)$request['type'] : 0;
		$live_id = isset($request['live_id']) ? (int)$request['live_id'] : 0;
		if(!$type || !in_array($type,array(1,2))) {
			return Response::json(array('errcode' => '310001', 'errmsg' => '缺少必要参数'));	
		}
		
		if($type==1) {	//直播观看
			$return_data = [
				'is_comment'	=>	0,	//是否开启评论：1 开启 0 关闭
				'is_buy'		=>	0,	//是否开启购买动态：1 开启 0 关闭
				'is_prohibit'	=>	0,	//是否禁言：1-禁言，0-正常
				'status'		=>	0,	//直播状态：1-未开始，2-已结束，3-直播中，4-暂停中
				'praise'		=>	0,	//点赞数
				'buy_info'		=>	'',	//购买动态
			];
			
			$data = LiveInfo::get_data_by_id($live_id, $this->merchant_id);
			if(!$data){
				return Response::json(array('errcode' => '99001', 'errmsg' => '直播不存在'));	
			}
			$return_data['is_comment'] = $data['is_comment'];
			$return_data['is_buy'] = $data['is_buy'];
			
			if(strtotime($data['start_time'])>time()) {		//未开始
				$return_data['status'] = 1;
			} else if(strtotime($data['end_time'])<time()) {	//已结束
				$return_data['status'] = 2;
			} else if($data['status']==1) {			//暂停中
				$return_data['status'] = 4;
			} else {
				$return_data['status'] = 3;			//直播中
			}
			
			//获取点赞数
			$praise_key = CacheKey::live_praise($live_id);
			$return_data['praise'] = (int)Cache::get($praise_key);
			if(!$return_data['praise']) {
				$return_data['praise'] = $data['praise'];
			}
						
			//获取购买动态
			if($data['is_buy']==1 && ($return_data['status']==3 || $return_data['status']==4)) {				
				
				//缓存随机会员
				$cachekey = CacheKey::buy_msg('memberlist_'.$live_id);
				$memberList = Cache::get($cachekey);
				if(!$memberList) {
					$memberList = MemberModel::select(['id','name','avatar'])->orderBy('id','desc')->skip(0)->take(500)->get()->toArray();
					Cache::put($cachekey, $memberList, 5);
				}
				
				//缓存商品
				$goods_list = [];
				$cachekey = CacheKey::buy_msg('goods_'.$live_id);
				if(Cache::get($cachekey)) {
					$goods_list = Cache::get($cachekey);
				}
				if(!$goods_list) {
					if($data['range']==1) {	//自选商品
						$live_goods = LiveGoods::get_data_by_liveid($live_id, $this->merchant_id);
						$goods_ids = [];
						if($live_goods){
							foreach($live_goods as $row){
								$goods_ids[] = $row['goods_id'];
							}
						}	
						if($goods_ids){
							$goods_list = Goods::select('id', 'title')->whereIn('id', $goods_ids)->where('merchant_id', $this->merchant_id)->where('onsale', 1)->where('is_delete', 1)->get()->toArray();
						}
					} else if($data['range']==2) {	//全店参与
						$goods_list = Goods::select('id', 'title')->orderBy('id','desc')->where('merchant_id', $this->merchant_id)->where('onsale', 1)->where('is_delete', 1)->skip(0)->take(100)->get()->toArray();
					}
					if($goods_list) {
						Cache::put($cachekey, $goods_list, 5);
					}
				}
				
				$msgList = [];
				$cachekey = CacheKey::buy_msg('buy_'.$live_id);
				if(Cache::get($cachekey)) {
					$msgList = Cache::get($cachekey);
				}
				if(!$msgList && $memberList && $goods_list) {
					for($i=0; $i<10; $i++) {
						$memberInfo = $memberList[rand(0,count($memberList)-1)];
						$goodsInfo = $goods_list[rand(0,count($goods_list)-1)];
						$str = mb_substr($memberInfo['name'],0,1).'** 购买了'.$goodsInfo['title'];
						if(!in_array($str,$msgList)) {
							$msgList[] = $str;
						}
					}		
				}
				$return_data['buy_info'] = $msgList;
				if($msgList) {
					Cache::put($cachekey, $msgList, 2);
				}
			}
									
			$viewInfo = LiveViewer::get_data_by_id($this->member_id,$live_id);
			
			if(strtotime($data['start_time'])<time() && strtotime($data['end_time'])>time()) {	//若活动未开始，已结束，不计算观看数据
				if(!$viewInfo) {
					$view_data = [
						'merchant_id'	=>	$this->merchant_id,
						'member_id'		=>	$this->member_id,
						'live_id'		=>	$live_id,
						'lastview_stime'=>	date("Y-m-d H:i:s",time()),
						'lastview_etime'=>	date("Y-m-d H:i:s",time()+60),
					];
					LiveViewer::insert_data($view_data);
					
					//更新参与人数
					$channelInfo = LiveChannel::get_one('id',$data['channel_id']);
					if($channelInfo) {
						LiveChannel::increment_data($data['channel_id'],$this->merchant_id,'view_sum',1);
					}
				} else {
					$return_data['is_prohibit'] = $viewInfo['is_prohibit'];
					$view_time = time()-strtotime($viewInfo['lastview_etime']);
					if($view_time>50) {	//大于50秒认为有效
						if($view_time>600) {	//大于10分钟表示重新进入的，不计算观看时间
							$view_data = [
								'lastview_etime'=>	date("Y-m-d H:i:s",time()),
							];
						} else {
							$view_data = [
								'lastview_etime'=>	date("Y-m-d H:i:s",time()),
								'view_time'		=>	$viewInfo['view_time'] + $view_time,
							];
						}
						LiveViewer::update_data($this->member_id,$live_id,$view_data);
					}
				}
			}
    		return ['errcode' => 0, 'errmsg' => '请求成功', 'data' => $return_data];
		} else if($type==2) {	//录播观看
			
			$data = LiveRecord::get_one('id', $live_id);
			if(!$data){
				return Response::json(array('errcode' => '99001', 'errmsg' => '录播不存在'));	
			}
			/*if($data['publish_status']==2) {
				return Response::json(array('errcode' => '99001', 'errmsg' => '录播已下线'));	
			}*/
			
			//处理观众信息
			$viewInfo = LiveRecordViewer::get_data_by_id($this->member_id,$live_id);
			if(!$viewInfo) {
				$view_data = [
					'merchant_id'	=>	$this->merchant_id,
					'member_id'		=>	$this->member_id,
					'record_id'		=>	$live_id,
				];
				LiveRecordViewer::insert_data($view_data);
				
				//更新参与人数
				LiveRecord::increment_data($live_id,$this->merchant_id,'view_sum',1);
			} else {
				$view_data = [
					'lastview_time'	=>	date("Y-m-d H:i:s",time()),
				];
				LiveRecordViewer::update_data($this->member_id,$live_id,$view_data);
			}
			
			$cachekey = CacheKey::live_viewer_by_memer_id($this->member_id,$live_id);
			$last_time = Cache::get($cachekey);
			$is_redu_money = 0;	//是否扣款
			if($last_time) {
				$view_time = time()-$last_time;
				
				$v_time = 1800;	//计费半小时
				//if(strstr($request->url(),'qa-applet.dodoca.com')) {
					//$v_time = 300;
				//}
				
				if($view_time>$v_time) {
					$is_redu_money = 1;
					Cache::put($cachekey, $last_time+1800, 60);
				}
			} else {
				$is_redu_money = 1;
				Cache::put($cachekey, time(), 60);
			}
			
			$is_can_view = 1;	//是否可以观看视频
			$merchatsInfo = MerchantSetting::get_data_by_id($this->merchant_id);
			if(!$merchatsInfo || $merchatsInfo['live_record']<1) {
				$is_can_view = 0;
				
				//更新商户设置表live_record_time
				//MerchantSetting::update_data($this->merchant_id,['live_record_time'=>date("Y-m-d H:i:s",time())]);
				
				//下线所有录播
				/*$rList = LiveRecord::select(['id','merchant_id'])->where(['merchant_id'=>$this->merchant_id,'publish_status'=>1,'is_delete'=>1])->get()->toArray();
				if($rList) {
					foreach($rList as $key => $item) {
						$result = $this->liveService->publishVideo($item['id'],2);
						
						记录日志
						CommonApi::wlog([
							custom'    	=>    	'live_down_'.$item['id'],
							merchant_id'   =>    	$item['merchant_id'],
							content'		=>		'require->'.json_encode($item,JSON_UNESCAPED_UNICODE).',result->'.json_encode($result,JSON_UNESCAPED_UNICODE),
						]);
					}
				}*/
			}
						
			if($is_can_view==1 && $is_redu_money==1) {	//发起录播扣款
				$chang_data = array(
					'merchant_id'	=>	$this->merchant_id,
					'ctype'			=>	2,
					'type'			=>	9,
					'type_id'		=>	$live_id,
					'sum'			=>	-1,
					'memo'			=>	"会员".($this->member_id+MEMBER_CONST)."观看消耗1个",
				);
				$result = MerchantService::changeLiveMoney($chang_data);
				if(!$result) {
					$is_can_view = 0;
				} else {
					LiveRecord::increment_data($live_id,$this->merchant_id,'consume',1);
					//若金额用完了，触发事件
					if($result && isset($result['balance']) && $result['balance']==0) {
						MerchantSetting::update_data($this->merchant_id,['live_record_time'=>date("Y-m-d H:i:s",time())]);
					}
				}
			}
			return Response::json(array('errcode' => '0', 'errmsg' => '请求成功', 'data' => ['is_can_view'=>$is_can_view]));
		}
		
	}
	
	/**
     * 直播点赞
     *
	 * @auth zhangchangchun@dodoca.com
	 * @param string $live_id 房间id
	 * @param sum 点赞数
     *
     * @return \Illuminate\Http\Response
     */
	public function praise(Request $request) {
		$live_id = isset($request['live_id']) ? (int)$request['live_id'] : 0;
		$sum = isset($request['sum']) ? (int)$request['sum'] : 1;
		$data = LiveInfo::get_data_by_id($live_id, $this->merchant_id);
		if(!$data){
			return Response::json(array('errcode' => '99001', 'errmsg' => '直播不存在'));	
    	}
		
		//获取点赞数
		$praise_key = CacheKey::live_praise($live_id);
		$praise = Cache::get($praise_key);
		if(!$praise) {
			$praise = $data['praise'];
		}
		
		//会员点赞
		//$viewer_praise_key = CacheKey::live_viewer_praise($this->member_id,$live_id);
		//$viewer_praise = Cache::get($viewer_praise_key);
		//if(!$viewer_praise) {
			LiveInfo::where(['id'=>$live_id])->increment('praise',$sum);
			$praise = Cache::increment($praise_key, $sum);
			//$praise = $praise+1;
			//Cache::put($viewer_praise_key, time(), 86400);
		//}
		return Response::json(array('errcode' => '0', 'errmsg' => '请求成功', 'data' => ['praise'=>$praise]));
		
	}

	 public function downQrcode(Request $request)
    {
        $member_id   = Member::id();
        $merchant_id = Member::merchant_id();
        $weapp_id    = Member::weapp_id();
        // $member_id   =1;
        // $merchant_id = 2;
        // $weapp_id    = 5;
        $live_id = isset($request['live_id']) ? (int)$request['live_id'] : 0;
        if(!$live_id) {
			return ['errcode'=>'80001','errmsg'=>'参数不全'];
		}
        $template = isset($request['template']) ? (int)$request['template'] : 1;

        $cacheKey = CacheKey::live_qrcode_card_key($merchant_id, $weapp_id, $member_id, $template, $live_id);
        $imgData  = Cache::tags('live_merchant_'.$merchant_id)->get($cacheKey);

        if(!$imgData){
            $wxinfo = WeixinInfo::get_one('id', $weapp_id, 1);
            $appid  = $wxinfo['appid'];

            $member_info = MemberModel::get_data_by_id($member_id, $merchant_id);

            //头像
            $avatar_img = $member_info['avatar']; 

            //用户名称
            $member_name = $member_info['name'];

            $live_info = LiveInfo::get_data_by_id($live_id,$merchant_id);
            //直播名称
            $live_name = $live_info['title'];
            //直播封面
            $live_img = env('QINIU_STATIC_DOMAIN').'/'.ltrim($live_info['cover_img']);
            $encrypt_member_id = encrypt($member_id, 'E');
            //二维码地址
            //$qrcode_img = $wxinfo['qrcode']; //默认小程序码
            $qrcode_img = env('QINIU_STATIC_DOMAIN').'/'.ltrim($wxinfo['qrcode'], '/'); //默认小程序码
            if($live_id ){
	        	if($live_info['type'] == 0){
	        		$create_qrcode = (new WeixinService())->qrcode($appid, 'pages/live/liveIndex/liveIndex?distrib_member_id='.$encrypt_member_id.'&id='.$live_id );
	        		//$create_qrcode = (new WeixinService())->qrcodeAll($appid,4, 'pages/live/liveIndex/liveIndex?distrib_member_id='.$encrypt_member_id.'&id='.$live_id );
		            if (isset($create_qrcode['errcode']) && $create_qrcode['errcode'] == 0) {
		                $qrcode_img = env('QINIU_STATIC_DOMAIN').'/'.ltrim($create_qrcode['url'], '/');
		            }
	        	}else{
	        		$create_qrcode = (new WeixinService())->qrcode($appid, 'pages/live/liveNormal/liveNormal?distrib_member_id='.$encrypt_member_id.'&id='.$live_id );
	        		//$create_qrcode = (new WeixinService())->qrcodeAll($appid,4,'pages/live/liveNormal/liveNormal?distrib_member_id='.$encrypt_member_id.'&id='.$live_id );
		            if (isset($create_qrcode['errcode']) && $create_qrcode['errcode'] == 0) {
		                $qrcode_img = env('QINIU_STATIC_DOMAIN').'/'.ltrim($create_qrcode['url'], '/');
		            }
	        	}
        	}
           
            $text2  = '邀请您来一起看直播';
            $text3  = '长按识别二维码';

            //风格一
            if($template == 1){


	            $width  = 642;
	            $height = 774;

	            

	            //创建画布
	            $canvas = new \Imagick();
                $canvas->newImage($width, $height, new \ImagickPixel('white'));
                $canvas->setImageFormat('png');

                //创建画布
	            $canvas_bg = new \Imagick();
                $canvas_bg->newImage(642, 184, new \ImagickPixel('rgb(255, 128, 0)'));
                $canvas_bg->setImageFormat('png');
                $canvas->compositeImage($canvas_bg, \Imagick::COMPOSITE_OVER, 0, 0);

                //头像白色背景
                $avatar_back = new \Imagick();
                $avatar_back->newImage(132, 132, 'tomato');
                $avatar_back->roundCorners(360, 360);
                $canvas->compositeImage($avatar_back, \Imagick::COMPOSITE_OVER, 28, 18);
                //头像
                $avatar = new \Imagick();
                $avatar->readImageBlob(file_get_contents($avatar_img));
                $avatar->thumbnailImage(122, 122, true);
                $avatar->roundCorners(360, 360);
                $canvas->compositeImage($avatar, \Imagick::COMPOSITE_OVER, 33, 23);
                //昵称
                $text1_draw = new \ImagickDraw();
                $text1_draw->setFont(realpath('font/Microsoft-Yahei.ttf'));
                $text1_draw->setFontSize(48);
                $text1_draw->setFillColor('white');
                $text1_font_metrics = $canvas->queryFontMetrics($text1_draw, $member_name);
                $canvas->annotateImage($text1_draw, 194, 80, 0, $member_name);
                //描述
                $text2_draw = new \ImagickDraw();
                $text2_draw->setFont(realpath('font/Microsoft-Yahei.ttf'));
                $text2_draw->setFontSize(32);
                $text2_draw->setFillColor('white');
                $font_metrics = $canvas->queryFontMetrics($text2_draw, $text2);
                $canvas->annotateImage($text2_draw, 194, 124, 0,$text2);

                //直播封面
                $live_img_draw = new \Imagick();
                $live_img_draw->readImageBlob(file_get_contents($live_img));
                $live_img_draw->thumbnailImage(642,360);
                $canvas->compositeImage($live_img_draw, \Imagick::COMPOSITE_OVER, 0, 180);


                //直播名称
                $live_name_draw = new \ImagickDraw();
                $live_name_draw->setFont(realpath('font/Microsoft-Yahei.ttf'));
                $live_name_draw->setFontSize(24);
                $live_name_draw->setFillColor('black');
                // $text1_font_metrics = $canvas->queryFontMetrics($text1_draw, $live_name);
                // $canvas->annotateImage($live_name_draw, 30, 670, 0, $live_name);
                //文字换行
                $desc_wrap = $this->autoWrap(24, 0, realpath('font/Microsoft-Yahei.ttf'), $live_name, 450);
                if($desc_wrap){
                    $text_y = 640;
                    foreach($desc_wrap as $str){
                        $font_metrics = $canvas->queryFontMetrics($live_name_draw, $str);
                        $canvas->annotateImage($live_name_draw, 50, $text_y, 0, $str);
                        $text_y += $font_metrics['textHeight'] + 3;
                    }
                }
            
                //二维码
                $qrcode = new \Imagick();
                $qrcode->readImageBlob(file_get_contents($qrcode_img));
                $qrcode->thumbnailImage(104, 104, true);
                $canvas->compositeImage($qrcode, \Imagick::COMPOSITE_OVER, 484, 580);

                $text3_draw = new \ImagickDraw();
                $text3_draw->setFont(realpath('font/Microsoft-Yahei.ttf'));
                $text3_draw->setFontSize(15);
                $text3_draw->setFillColor('gray');
                $font_metrics = $canvas->queryFontMetrics($text3_draw, $text3);
                $canvas->annotateImage($text3_draw, 480, 700, 0,$text3);
            }

            //风格二
            if($template == 2){

            	$width  = 446;
	            $height = 784;
	            $bg_img = 'https://s.dodoca.com/applet_weapp/images/live/new/share-style-1-bg.png';
	            //创建画布
	            $canvas = new \Imagick();
	            $canvas->readImageBlob(file_get_contents($bg_img));
	            $canvas->thumbnailImage($width, $height);
	            $canvas->setImageFormat('png');
	            $canvas->setCompressionQuality(100);
	            $canvas->enhanceImage();
	            //昵称
                $text1_draw = new \ImagickDraw();
                $text1_draw->setFont(realpath('font/Microsoft-Yahei.ttf'));
                $text1_draw->setFontSize(32);
                $text1_draw->setFillColor('black');
                $text1_font_metrics = $canvas->queryFontMetrics($text1_draw, $member_name);
                $canvas->annotateImage($text1_draw, 34, 70, 0, $member_name);
                //描述
                $text2_draw = new \ImagickDraw();
                $text2_draw->setFont(realpath('font/Microsoft-Yahei.ttf'));
                $text2_draw->setFontSize(20);
                $text2_draw->setFillColor('orange');
                $font_metrics = $canvas->queryFontMetrics($text2_draw, $text2);
                $canvas->annotateImage($text2_draw, 34, 100, 0,$text2);

	            //头像白色背景
                $avatar_back = new \Imagick();
                $avatar_back->newImage(74, 74, 'red');
                $avatar_back->roundCorners(360, 360);
                $canvas->compositeImage($avatar_back, \Imagick::COMPOSITE_OVER, 324, 28);
                //头像
                $avatar = new \Imagick();
                $avatar->readImageBlob(file_get_contents($avatar_img));
                $avatar->thumbnailImage(70, 70, true);
                $avatar->roundCorners(360, 360);
                $canvas->compositeImage($avatar, \Imagick::COMPOSITE_OVER, 326, 30);

				//直播封面
                $live_img_draw = new \Imagick();
                $live_img_draw->readImageBlob(file_get_contents($live_img));
                $live_img_draw->thumbnailImage(406,228);
                $canvas->compositeImage($live_img_draw, \Imagick::COMPOSITE_OVER, ($width-406)/2, 140);

                //直播名称
                $live_name_draw = new \ImagickDraw();
                $live_name_draw->setFont(realpath('font/Microsoft-Yahei.ttf'));
                $live_name_draw->setFontSize(24);
                $live_name_draw->setFillColor('black');
                
                //文字换行
                $desc_wrap = $this->autoWrap(28, 0, realpath('font/Microsoft-Yahei.ttf'), $live_name, 660);
                if($desc_wrap){
                    $text_y = 400;
                    foreach($desc_wrap as $str){
                        $font_metrics = $canvas->queryFontMetrics($live_name_draw, $str);
                        $canvas->annotateImage($live_name_draw, 20, $text_y, 0, $str);
                        $text_y += $font_metrics['textHeight'] + 3;
                    }
                }

                $text3_draw = new \ImagickDraw();
                $text3_draw->setFont(realpath('font/Microsoft-Yahei.ttf'));
                $text3_draw->setFontSize(15);
                $text3_draw->setFillColor('gray');
                $font_metrics = $canvas->queryFontMetrics($text3_draw, $text3);
                $canvas->annotateImage($text3_draw, 166, 470, 0,$text3);

                //二维码
                $qrcode = new \Imagick();
                $qrcode->readImageBlob(file_get_contents($qrcode_img));
                $qrcode->thumbnailImage(270, 270, true);
                $canvas->compositeImage($qrcode, \Imagick::COMPOSITE_OVER, 88, 500);     

            }

            //风格三
            if($template == 3){
            	$width  = 446;
	            $height = 754;
	            //默认数据
            	$bg_img = 'https://s.dodoca.com/applet_weapp/images/live/new/share-3-top.png';
	            //创建画布
	            $canvas = new \Imagick();
                $canvas->newImage($width, $height, new \ImagickPixel('rgba(255, 255, 255, 0)'));
                $canvas->setImageFormat('png');

	            //创建画布
	            $canvas_bg = new \Imagick();
	            $canvas_bg->readImageBlob(file_get_contents($bg_img));
	            $canvas_bg->thumbnailImage(446, 118);
	            $canvas_bg->setImageFormat('png');
	            $canvas_bg->setCompressionQuality(100);
	            $canvas_bg->enhanceImage();
	            $canvas->compositeImage($canvas_bg, \Imagick::COMPOSITE_OVER,  0, 0);
                
                //创建画布
	            $canvas_2 = new \Imagick();
                $canvas_2->newImage(446, 636, new \ImagickPixel('rgb(255, 255, 255)'));
                $canvas_2->setImageFormat('png');
                $canvas->compositeImage($canvas_2, \Imagick::COMPOSITE_OVER,  0, 118);

                //头像白色背景
                $avatar_back = new \Imagick();
                $avatar_back->newImage(84, 84, 'red');
                $avatar_back->roundCorners(360, 360);
                $canvas->compositeImage($avatar_back, \Imagick::COMPOSITE_OVER,  ($width-84)/2, 68);
                //头像
                $avatar = new \Imagick();
                $avatar->readImageBlob(file_get_contents($avatar_img));
                $avatar->thumbnailImage(80, 80, true);
                $avatar->roundCorners(360, 360);
                $canvas->compositeImage($avatar, \Imagick::COMPOSITE_OVER,  ($width-80)/2, 70);

                //昵称
                $text1_draw = new \ImagickDraw();
                $text1_draw->setFont(realpath('font/Microsoft-Yahei.ttf'));
                $text1_draw->setFontSize(24);
                $text1_draw->setFillColor('black');
                $text1_font_metrics = $canvas->queryFontMetrics($text1_draw, $member_name);
                $canvas->annotateImage($text1_draw, 170, 180, 0, $member_name);

                //二维码
                $qrcode = new \Imagick();
                $qrcode->readImageBlob(file_get_contents($qrcode_img));
                $qrcode->thumbnailImage(190, 190, true);
                $canvas->compositeImage($qrcode, \Imagick::COMPOSITE_OVER, ($width-190)/2, 200); 

                $text3_draw = new \ImagickDraw();
                $text3_draw->setFont(realpath('font/Microsoft-Yahei.ttf'));
                $text3_draw->setFontSize(15);
                $text3_draw->setFillColor('gray');
                $font_metrics = $canvas->queryFontMetrics($text3_draw, $text3);
                $canvas->annotateImage($text3_draw, 164, 410, 0,$text3);

                //直播名称
                $live_name_draw = new \ImagickDraw();
                $live_name_draw->setFont(realpath('font/Microsoft-Yahei.ttf'));
                $live_name_draw->setFontSize(24);
                $live_name_draw->setFillColor('black');
                //文字换行
                $desc_wrap = $this->autoWrap(24, 0, realpath('font/Microsoft-Yahei.ttf'), $live_name, 706);
                if($desc_wrap){
                    $text_y = 448;
                    foreach($desc_wrap as $str){
                        $font_metrics = $canvas->queryFontMetrics($live_name_draw, $str);
                        $canvas->annotateImage($live_name_draw, 20, $text_y, 0, $str);
                        $text_y += $font_metrics['textHeight'] + 3;
                    }
                }

                //直播封面
                $live_img_draw = new \Imagick();
                $live_img_draw->readImageBlob(file_get_contents($live_img));
                $live_img_draw->thumbnailImage(446,252);
                $canvas->compositeImage($live_img_draw, \Imagick::COMPOSITE_OVER, 0, 502);

            }

            // header('Content-type:image/png');
            // echo $canvas->getImageBlob();exit;
            $content = $canvas->getImageBlob();
            $canvas->clear(); //释放资源
            $content = base64_encode($content);
            // echo $content;exit;
            $imgData = $this->upload($content);
            Cache::tags('live_merchant_'.$merchant_id)->forever($cacheKey, $imgData);
        }

        if(!empty($imgData)){
            return ['errcode' => 0, 'data' => $imgData];
        }else{
            return ['errcode' => -1, 'errmsg' => '获取推广码失败'];
        }
    }

    //文字自动换行
    private function autoWrap($fontSize, $angle, $fontFile, $string, $width) 
    {
        $content = "";
        // 将字符串拆分成一个个单字 保存到数组 letter 中
        for ($i = 0; $i < mb_strlen($string); $i ++) {
            $letter[] = mb_substr($string, $i, 1);
        }

        foreach ($letter as $l) {
            $teststr = $content."".$l;
            $testbox = imagettfbbox($fontSize, $angle, $fontFile, $teststr);
            // 判断拼接后的字符串是否超过预设的宽度
            if (($testbox[2] > $width) && ($content !== "")) {
                $content .= "\r\n";
            }
            $content .= $l;
        }
       
        return explode("\r\n", $content);
    }

    /**
     * 上传图片源码到七牛
     * @return array
     * @author: tangkang@dodoca.com
     */
    private function upload($content)
    {
        $bucket    = env('QINIU_BUCKET');
        $accessKey = env('QINIU_ACCESS_KEY');
        $secretKey = env('QINIU_SECRET_KEY');

        $auth = new Auth($accessKey, $secretKey);
        $upToken = $auth->uploadToken($bucket);//获取上传所需的token

        $filename = $this->getRandChar().'.png';
        $key = date('Y').'/'.date('m').'/'.date('d').'/'.$filename;
        $base_key = \Qiniu\base64_urlSafeEncode($key);

        $client = new Client();
        $res = $client->request('POST', 'http://upload.qiniu.com/putb64/-1/key/'.$base_key, [
            'body' => $content,
            'headers' => [
                'Content-Type'  => 'image/png',
                'Authorization' => 'UpToken '.$upToken
            ]
        ]);

        $result = json_decode($res->getBody(), true);

        if(isset($result['key'])){
            return $result['key'];
        }
    }

    /**
     * 生成文件名称
     * @return array
     * @author: tangkang@dodoca.com
     */
    private function getRandChar(){
        $str = null;
        $strPol = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789abcdefghijklmnopqrstuvwxyz";
        $max = strlen($strPol)-1;

        for($i=0;$i<28;$i++){
            $str.=$strPol[rand(0,$max)];
        }
        return $str;
    }

    public function cardData(Request $request){
    	$member_id   = Member::id();
        $merchant_id = Member::merchant_id();
        $weapp_id    = Member::weapp_id();
    	// $member_id   =1;
     //    $merchant_id = 2;
     //    $weapp_id    = 5;
        $live_id  = $request->live_id;
        //$live_id = isset($request['live_id']) ? (int)$request['live_id'] : 1;
        $data = [];
        $member_info = MemberModel::get_data_by_id($member_id, $merchant_id);
        //头像
        $data['avatar_img'] = $member_info['avatar']; 
        //用户名称
        $data['member_name'] = $member_info['name'];

        $live_info = LiveInfo::get_data_by_id($live_id,$merchant_id);
        //直播名称
        $data['live_name'] = $live_info['title'];
        //直播封面
        $data['live_img'] = $live_info['cover_img'];

        $wxinfo = WeixinInfo::get_one('id', $weapp_id, 1);
        $appid  = $wxinfo['appid'];
        $encrypt_member_id = encrypt($member_id, 'E');
         //二维码地址
        $data['qrcode_img'] = env('QINIU_STATIC_DOMAIN').'/'.ltrim($wxinfo['qrcode'], '/'); //默认小程序码
        if($live_id ){
        	if($live_info['type'] == 0){
        		$create_qrcode = (new WeixinService())->qrcode($appid, 'pages/live/liveIndex/liveIndex?distrib_member_id='.$encrypt_member_id.'&id='.$live_id );
        		//$create_qrcode = (new WeixinService())->qrcodeAll($appid,4, 'pages/live/liveIndex/liveIndex?distrib_member_id='.$encrypt_member_id.'&id='.$live_id );
	            if (isset($create_qrcode['errcode']) && $create_qrcode['errcode'] == 0) {
	                $data['qrcode_img'] = env('QINIU_STATIC_DOMAIN').'/'.ltrim($create_qrcode['url'], '/');
	            }
        	}else{
        		$create_qrcode = (new WeixinService())->qrcode($appid, 'pages/live/liveNormal/liveNormal?distrib_member_id='.$encrypt_member_id.'&id='.$live_id );
        		//$create_qrcode = (new WeixinService())->qrcodeAll($appid,4, 'pages/live/liveNormal/liveNormal?distrib_member_id='.$encrypt_member_id.'&id='.$live_id );
	            if (isset($create_qrcode['errcode']) && $create_qrcode['errcode'] == 0) {
	                $data['qrcode_img'] = env('QINIU_STATIC_DOMAIN').'/'.ltrim($create_qrcode['url'], '/');
	            }
        	}
            
        }

        return Response::json(array('errcode' => '0', 'errmsg' => '请求成功', 'data' => $data));

    }
	
}

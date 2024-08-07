<?php

namespace App\Http\Controllers\Admin\Live;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\LiveChannel;
use App\Models\LiveInfo;
use App\Models\LiveGoods;
use App\Models\Merchant;
use App\Models\MerchantSetting;
use App\Models\LiveBalance;
use App\Models\LiveViewer;
use App\Models\OrderGoods;
use App\Models\OrderInfo;
use App\Models\Coupon;
use App\Models\Goods;
use App\Models\Member;
use App\Services\LiveService;

class LiveController extends Controller
{
    private $merchant_id;

    function __construct(Request $request)
    {
        $this->params = $request->all();  

		$this->merchant_id = Auth::user()->merchant_id;
		$this->user_id = Auth::user()->id;
		// $this->merchant_id = 1;
  //       $this->user_id = 2;
    }

	//创建直播
	public function save(Request $request)
	{
		$params = $request->all();
		
		$data['title'] = isset($params['title']) ? (string)$params['title'] : '';
		$data['cover_img'] = isset($params['cover_img']) ? (string)$params['cover_img'] : '';
		$data['start_time'] = isset($params['start_time']) ? (string)$params['start_time'] : '';
		$data['end_time'] = isset($params['end_time']) ? (string)$params['end_time'] : '';
		$data['view_max'] = isset($params['view_max']) ? (int)$params['view_max'] : 0;
		$data['consume'] = isset($params['consume']) ? (int)$params['consume'] : 0;
		$data['is_comment'] = isset($params['is_comment']) ? (int)$params['is_comment'] : 0;
		$data['is_buy'] = isset($params['is_buy']) ? (int)$params['is_buy'] : 0;
		$data['view_coupons'] = isset($params['view_coupons']) ? (string)$params['view_coupons'] : '';
		$goods = isset($params['goods']) ? (string)$params['goods'] : '';
		$data['range'] = isset($params['range']) ? (int)$params['range'] : 0;
		$data['virtual_sum'] = isset($params['virtual_sum']) ? (int)$params['virtual_sum'] : 0;
		$data['type'] = isset($params['type']) ? (int)$params['type'] : 0;
		$data['desc'] = isset($params['desc']) ? (string)$params['desc'] : '';
		$data['create_card'] = isset($params['create_card']) ? (int)$params['create_card'] : 0;

		if(!$data['title'] || !$data['cover_img'] || !$data['start_time'] || !$data['end_time'] || !$data['view_max'] || !$data['consume']) {
			return ['errcode'=>'80001','errmsg'=>'缺少参数']; 
		}

		if( $data['type'] == 1 ){//普通直播 直播描述必填 且无购买状态
			if(!$data['desc']){
				return ['errcode'=>'80001','errmsg'=>'缺少参数']; 
			}
			$data['is_buy'] == 0;
		}else{
			$data['desc'] = '';
		}

		//基本版18-22点不能创建直播
		$Merchant = Merchant::get_data_by_id($this->merchant_id);
		if($Merchant['version_id']==6) {
			$start_time = strtotime($data['start_time']);
			$end_time = strtotime($data['end_time']);
			$times = ['18','19','20','21'];
			if(in_array(date('H',$start_time),$times) || in_array(date('H',$end_time),$times)) {
				return ['errcode'=>'80001','errmsg'=>'18-22点不能创建直播']; 
			}
		}

		//开始时间不能大于结束时间
		if(strtotime($data['start_time']) > strtotime($data['end_time'])) {
			return ['errcode'=>'80001','errmsg'=>'开始时间不能大于结束时间']; 
		}
		
		//时长必须超过30分钟
		if(strtotime($data['end_time'])-strtotime($data['start_time'])<1800) {
			return ['errcode'=>'80001','errmsg'=>'时长必须超过30分钟']; 
		}

		//检查直播包余额
		$setting = MerchantSetting::get_data_by_id($this->merchant_id);
		if($setting['live_balance'] < $data['consume']) {
			return ['errcode'=>'80002','errmsg'=>'直播包余额不足']; 
		}
		
		$data['merchant_id'] = $this->merchant_id;

		//添加直播
		$LiveService = new LiveService();
		$live_data = [
			'title'		=> $data['title'],
			'start'		=> strtotime($data['start_time']),
			'end'		=> strtotime($data['end_time']),
			'max'		=> $data['view_max'],
			'clarity'	=> 2,
			'record'	=> 1,
			'demand'	=> 1,
			'image'		=> $data['cover_img'],
			'type'		=> $data['type'],
			'desc'		=> $data['desc'],
			'create_card'=> $data['create_card']
		];
		$res = $LiveService->createChannel($this->merchant_id,$live_data);
		if($res['errcode']!=0) {
			return $res; 
		}

		$data['channel_id'] = $res['id'];

		DB::beginTransaction();
		try {
			$id = LiveInfo::insert_data($data);
			if(!$id) {
				return ['errcode'=>'80004','errmsg'=>'创建直播失败']; 
			}

			if($goods) {
				$goods = explode(',',$goods);
				
				foreach($goods as $goods_id) {
					$goods_data = [
						'merchant_id' => $this->merchant_id,
						'live_id' => $id,
						'goods_id' => $goods_id,
					];
					LiveGoods::insert_data($goods_data);
				}
			}

			//扣除直播包余额
			MerchantSetting::decrement_data($setting['id'],$this->merchant_id,'live_balance',$data['consume']);

			$setting = MerchantSetting::get_data_by_id($this->merchant_id);
			$live_balance_data = [
				'merchant_id' => $this->merchant_id,
				'sum' => -$data['consume'],
				'balance' => $setting['live_balance'],
				'ctype' => 1,
				'type' => 8,
				'memo' => '直播创建消耗'.$data['consume'].'个',
			];
			LiveBalance::insert_data($live_balance_data);
			
			DB::commit();
		} catch (\Exception $e) {
			DB::rollBack();

			return ['errcode'=>'80005','errmsg'=>'操作失败']; 
		}

		return ['errcode'=>'0','errmsg'=>'操作成功']; 
	}

	//修改直播
	public function edit(Request $request)
	{
		$params = $request->all();
		
		$id = isset($params['id']) ? (int)$params['id'] : 0;
		$data['title'] = isset($params['title']) ? (string)$params['title'] : '';
		$data['cover_img'] = isset($params['cover_img']) ? (string)$params['cover_img'] : '';
		$data['start_time'] = isset($params['start_time']) ? (string)$params['start_time'] : '';
		$data['end_time'] = isset($params['end_time']) ? (string)$params['end_time'] : '';
		$data['view_max'] = isset($params['view_max']) ? (int)$params['view_max'] : 0;
		$data['consume'] = isset($params['consume']) ? (int)$params['consume'] : 0;
		$data['is_comment'] = isset($params['is_comment']) ? (int)$params['is_comment'] : 0;
		$data['is_buy'] = isset($params['is_buy']) ? (int)$params['is_buy'] : 0;
		$data['view_coupons'] = isset($params['view_coupons']) ? (string)$params['view_coupons'] : '';
		$goods = isset($params['goods']) ? (string)$params['goods'] : '';
		$data['range'] = isset($params['range']) ? (int)$params['range'] : 0;
		$data['virtual_sum'] = isset($params['virtual_sum']) ? (int)$params['virtual_sum'] : 0;

		$data['desc'] = isset($params['desc']) ? (string)$params['desc'] : '';
		$data['create_card'] = isset($params['create_card']) ? (int)$params['create_card'] : 0;

		if(!$id || !$data['title'] || !$data['cover_img']) {
			return ['errcode'=>'80001','errmsg'=>'缺少参数']; 
		}

		$info = LiveInfo::get_data_by_id($id,$this->merchant_id);

		if(!$info) {
			return ['errcode'=>'80001','errmsg'=>'直播不存在']; 
		}

		//检查不能编辑的状态
		/*
		if(in_array($info['status'],[])) {
			return ['errcode'=>'80001','errmsg'=>'当前状态无法编辑']; 
		}
		*/

		//已经开始不能编辑项移除
		$start_time = strtotime($info['start_time']);

		//只有未开始才能修改直播名称
		if($start_time<time() || $info['status']>0) {
			unset($data['title']);
		}

		if($start_time<time()) {
			unset($data['start_time']);
			unset($data['end_time']);
			unset($data['view_max']);
			unset($data['consume']);
		} else {
			//基本版18-22点不能创建直播
			$Merchant = Merchant::get_data_by_id($this->merchant_id);
			if($Merchant['version_id']==6) {
				$start_time = strtotime($data['start_time']);
				$end_time = strtotime($data['end_time']);
				$times = ['18','19','20','21'];
				if(in_array(date('H',$start_time),$times) || in_array(date('H',$end_time),$times)) {
					return ['errcode'=>'80001','errmsg'=>'18-22点不能创建直播']; 
				}
			}

			//开始时间不能大于结束时间
			if(strtotime($data['start_time']) > strtotime($data['end_time'])) {
				return ['errcode'=>'80001','errmsg'=>'开始时间不能大于结束时间']; 
			}

			//时长必须超过30分钟
			if(strtotime($data['end_time'])-strtotime($data['start_time'])<1800) {
				return ['errcode'=>'80001','errmsg'=>'时长必须超过30分钟']; 
			}
		}

		//修改直播接口
		$LiveService = new LiveService();
		if($start_time<time()) {
			$live_data = [
				'title'		=> isset($data['title']) ? $data['title'] : '',
				'clarity'	=> 2,
				'record'	=> 1,
				'demand'	=> 1,
				'image'		=> $data['cover_img'],
				'desc'	=> $data['desc'],
				'create_card'		=> $data['create_card']
			];
		} else {
			$live_data = [
				'title'		=> isset($data['title']) ? $data['title'] : '',
				'start'		=> strtotime($data['start_time']),
				'end'		=> strtotime($data['end_time']),
				'max'		=> $data['view_max'],
				'clarity'	=> 2,
				'record'	=> 1,
				'demand'	=> 1,
				'image'		=> $data['cover_img'],
				'desc'	=> $data['desc'],
				'create_card'		=> $data['create_card']
			];
		}
		if($info['channel_id']) {
			//只有未开始才能修改直播名称
			if($start_time<time() || $info['status']>0) {
				unset($live_data['title']);
			}

			$res = $LiveService->updateChannel($info['channel_id'],$this->merchant_id,$live_data);
			if($res['errcode']!=0) {
				return $res; 
			}
		}
		
		DB::beginTransaction();
		try {
			//如果消耗直播包数量发生变化 先归还之前扣除的 再扣除
			if(isset($data['consume']) && $data['consume']!=$info['consume']) {
				//归还直播包之前扣除余额
				$setting = MerchantSetting::get_data_by_id($this->merchant_id);
				MerchantSetting::increment_data($setting['id'],$this->merchant_id,'live_balance',$info['consume']);
			
				$setting = MerchantSetting::get_data_by_id($this->merchant_id);
				$live_balance_data = [
					'merchant_id' => $this->merchant_id,
					'sum' => $info['consume'],
					'balance' => $setting['live_balance'],
					'ctype' => 1,
					'type' => 2,
					'memo' => '直播修改归还'.$info['consume'].'个',
				];
				LiveBalance::insert_data($live_balance_data);

				//扣除直播包余额
				MerchantSetting::decrement_data($setting['id'],$this->merchant_id,'live_balance',$data['consume']);

				$setting = MerchantSetting::get_data_by_id($this->merchant_id);
				$live_balance_data = [
					'merchant_id' => $this->merchant_id,
					'sum' => -$data['consume'],
					'balance' => $setting['live_balance'],
					'ctype' => 1,
					'type' => 8,
					'memo' => '直播修改消耗'.$data['consume'].'个',
				];
				LiveBalance::insert_data($live_balance_data);
			}

			$res = LiveInfo::update_data($id,$this->merchant_id,$data);
			if(!$res) {
				return ['errcode'=>'80002','errmsg'=>'操作失败']; 
			}

			DB::commit();
		} catch (\Exception $e) {
			DB::rollBack();

			return ['errcode'=>'80005','errmsg'=>'操作失败']; 
		}



		$goods = $goods ? explode(',',$goods) : [];

		//查询现有的
		$list = LiveGoods::get_data_by_liveid($id, $this->merchant_id);
		$ids = [];
		if($list) {
			foreach($list as $v) {
				$ids[] = $v['goods_id'];
			}
		}

		//需要增加的
		$need_add = array_diff($goods,$ids);
		if($need_add) {
			foreach($need_add as $goods_id) {
				$goods_data = [
					'merchant_id' => $this->merchant_id,
					'live_id' => $id,
					'goods_id' => $goods_id,
				];
				LiveGoods::insert_data($goods_data);
			}
		}
		
		//需要删除的
		$need_del = array_diff($ids,$goods);
		if($need_del) {
			foreach($need_del as $goods_id) {
				LiveGoods::delete_data($id,$goods_id,$this->merchant_id);
			}
		}

		return ['errcode'=>'0','errmsg'=>'操作成功']; 
	}

	//直播信息
	public function info(Request $request)
	{
		$params = $request->all();

		$id = isset($params['id']) ? (int)$params['id'] : 0;

		if(!$id) {
			return ['errcode'=>'80001','errmsg'=>'缺少参数']; 
		}

		$info = LiveInfo::get_data_by_id($id,$this->merchant_id);
		
		if(!$info) {
			return ['errcode'=>'80002','errmsg'=>'直播不存在']; 
		}

		//频道信息
		$channel = LiveChannel::get_one('id',$info['channel_id']);
		$info['max_sum'] = isset($channel['max_sum']) ? $channel['max_sum'] : 0;
		$info['view_sum'] = isset($channel['view_sum']) ? $channel['view_sum'] : 0;
		//$info['view_max'] = isset($channel['view_max']) ? $channel['view_max'] : 0;
		$info['view_fengzhi'] = isset($channel['view_max']) ? $channel['view_max'] : 0;
		
		//优惠券
		$info['coupons'] = [];
		$view_coupons = explode(',',$info['view_coupons']);
		if(!empty($view_coupons)) {
			$Coupon = new Coupon();
			$query = $Coupon->select('id', 'name')->where(['is_delete' =>1,'merchant_id' =>$this->merchant_id]);
			$info['coupons'] = $query->whereIn('id',$view_coupons)->get()->toArray();
		}

		//已选商品
		$list = LiveGoods::get_data_by_liveid($id, $this->merchant_id);
		$ids = [];
		if($list) {
			foreach($list as $v) {
				$ids[] = $v['goods_id'];
			}
		}
		$info['goods'] = [];
		if(!empty($ids)) {
			$Goods = new Goods();
			$query = $Goods->select('id','title','price','stock','img')->where(['is_delete' =>1,'merchant_id' =>$this->merchant_id]);
			$info['goods'] = $query->whereIn('id',$ids)->get()->toArray();
		}


		return ['errcode'=>'0','data'=>$info]; 
	}

	//取消直播
	public function cancel(Request $request)
	{
		$params = $request->all();

		$id = isset($params['id']) ? (int)$params['id'] : 0;

		if(!$id) {
			return ['errcode'=>'80001','errmsg'=>'缺少参数']; 
		}

		$info = LiveInfo::get_data_by_id($id,$this->merchant_id);

		if(!$info) {
			return ['errcode'=>'80002','errmsg'=>'直播不存在']; 
		}

		//检查不能取消的状态
		if(in_array($info['status'],[1,2])) {
			return ['errcode'=>'80003','errmsg'=>'当前状态无法取消']; 
		}
		
		//已经开始不能取消
		$start_time = strtotime($info['start_time']);
		if($start_time<time()) {
			return ['errcode'=>'80004','errmsg'=>'直播已经开始无法取消']; 
		}

		DB::beginTransaction();
		try {
			$res = LiveInfo::update_data($id,$this->merchant_id,['status'=>2]);
			if(!$res) {
				return ['errcode'=>'80002','errmsg'=>'操作失败']; 
			}
			
			$res = LiveChannel::update_data('id',$info['channel_id'],['status'=>2]);
			if(!$res) {
				return ['errcode'=>'80003','errmsg'=>'操作失败']; 
			}

			//归还直播包余额
			$setting = MerchantSetting::get_data_by_id($this->merchant_id);
			MerchantSetting::increment_data($setting['id'],$this->merchant_id,'live_balance',$info['consume']);
		
			$setting = MerchantSetting::get_data_by_id($this->merchant_id);
			$live_balance_data = [
				'merchant_id' => $this->merchant_id,
				'sum' => $info['consume'],
				'balance' => $setting['live_balance'],
				'ctype' => 1,
				'type' => 2,
				'memo' => '直播取消归还'.$info['consume'].'个',
			];
			LiveBalance::insert_data($live_balance_data);
			
			DB::commit();
		} catch (\Exception $e) {
			DB::rollBack();

			return ['errcode'=>'80005','errmsg'=>'操作失败']; 
		}

		return ['errcode'=>'0','errmsg'=>'操作成功']; 
	}

	//删除直播
	public function del(Request $request)
	{
		$params = $request->all();

		$id = isset($params['id']) ? (int)$params['id'] : 0;

		if(!$id) {
			return ['errcode'=>'80001','errmsg'=>'缺少参数']; 
		}

		$info = LiveInfo::get_data_by_id($id,$this->merchant_id);

		if(!$info) {
			return ['errcode'=>'80002','errmsg'=>'直播不存在']; 
		}

		//检查不能删除的状态
		if($info['status']!=2) {
			return ['errcode'=>'80003','errmsg'=>'当前状态无法删除']; 
		}

		//接口
		$LiveService = new LiveService();
		$res = $LiveService->deleteChannel($info['channel_id'],$this->merchant_id);
		if($res['errcode']!=0) {
			return $res;
		}

		$res = LiveInfo::update_data($id,$this->merchant_id,['is_delete'=>-1]);
		if(!$res) {
			return ['errcode'=>'80002','errmsg'=>'操作失败']; 
		}

		return ['errcode'=>'0','errmsg'=>'操作成功']; 
	}

	//暂停直播
	public function stop(Request $request)
	{
		$params = $request->all();

		$id = isset($params['id']) ? (int)$params['id'] : 0;

		if(!$id) {
			return ['errcode'=>'80001','errmsg'=>'缺少参数']; 
		}

		$info = LiveInfo::get_data_by_id($id,$this->merchant_id);

		if(!$info) {
			return ['errcode'=>'80002','errmsg'=>'直播不存在']; 
		}

		//检查不能暂停的状态
		if(in_array($info['status'],[1,2])) {
			return ['errcode'=>'80003','errmsg'=>'当前状态无法暂停']; 
		}

		//只有在进行中的直播才能暂停
		$start_time = strtotime($info['start_time']);
		$end_time = strtotime($info['end_time']);
		if($start_time>time() || $end_time<time()) {
			return ['errcode'=>'80004','errmsg'=>'只有在进行中的直播才能暂停']; 
		}

		//接口
		$LiveService = new LiveService();
		$res = $LiveService->switchChannel($info['channel_id'],$this->merchant_id,-1);
		if($res['errcode']!=0) {
			return $res;
		}

		$res = LiveInfo::update_data($id,$this->merchant_id,['status'=>1]);
		if(!$res) {
			return ['errcode'=>'80002','errmsg'=>'操作失败']; 
		}

		return ['errcode'=>'0','errmsg'=>'操作成功']; 
	}
	
	//开始直播
	public function start(Request $request)
	{
		$params = $request->all();

		$id = isset($params['id']) ? (int)$params['id'] : 0;

		if(!$id) {
			return ['errcode'=>'80001','errmsg'=>'缺少参数']; 
		}

		$info = LiveInfo::get_data_by_id($id,$this->merchant_id);

		if(!$info) {
			return ['errcode'=>'80002','errmsg'=>'直播不存在']; 
		}

		//检查不能开始的状态
		if(in_array($info['status'],[2])) {
			return ['errcode'=>'80003','errmsg'=>'当前状态无法开始']; 
		}

		//只有在进行中的直播才能开始
		$start_time = strtotime($info['start_time']);
		$end_time = strtotime($info['end_time']);
		if($start_time>time() || $end_time<time()) {
			return ['errcode'=>'80004','errmsg'=>'只有在进行中的直播才能开始']; 
		}

		//接口
		$LiveService = new LiveService();
		$res = $LiveService->switchChannel($info['channel_id'],$this->merchant_id,1);
		if($res['errcode']!=0) {
			return $res;
		}

		$res = LiveInfo::update_data($id,$this->merchant_id,['status'=>0]);
		if(!$res) {
			return ['errcode'=>'80002','errmsg'=>'操作失败']; 
		}

		return ['errcode'=>'0','errmsg'=>'操作成功']; 
	}

	//续期
	public function renew(Request $request)
	{
		$params = $request->all();

		$id = isset($params['id']) ? (int)$params['id'] : 0;
		$consume = isset($params['consume']) ? (int)$params['consume'] : 0;
		$hour = isset($params['hour']) ? (int)$params['hour'] : 0;

		if(!$id || !$consume || !$hour) {
			return ['errcode'=>'80001','errmsg'=>'缺少参数']; 
		}

		$info = LiveInfo::get_data_by_id($id,$this->merchant_id);

		if(!$info) {
			return ['errcode'=>'80002','errmsg'=>'直播不存在']; 
		}

		//直播中才能续期
		$start_time = strtotime($info['start_time']);
		$end_time = strtotime($info['end_time']);
		if($start_time>time() || $end_time<time()) {
			//return ['errcode'=>'80003','errmsg'=>'只有在进行中的直播才能续期']; 
		}

		//检查直播包余额
		$setting = MerchantSetting::get_data_by_id($this->merchant_id);
		if($setting['live_balance'] < $consume) {
			return ['errcode'=>'80004','errmsg'=>'直播包余额不足']; 
		}

		DB::beginTransaction();
		try {
			//扣除直播包余额
			MerchantSetting::decrement_data($setting['id'],$this->merchant_id,'live_balance',$consume);

			$setting = MerchantSetting::get_data_by_id($this->merchant_id);
			$live_balance_data = [
				'merchant_id' => $this->merchant_id,
				'sum' => -$consume,
				'balance' => $setting['live_balance'],
				'ctype' => 1,
				'type' => 6,
				'memo' => '直播续期消耗'.$info['consume'].'个',
			];
			LiveBalance::insert_data($live_balance_data);

			//增加消耗直播包数量
			LiveInfo::increment_data($id,$this->merchant_id,'consume',$consume);

			//延长结束时间
			$data['end_time'] = date('Y-m-d H:i:s',strtotime($info['end_time'])+3600*$hour);
			$res = LiveInfo::update_data($id,$this->merchant_id,$data);
			
			$LiveService = new LiveService();
			$res = $LiveService->renewalChannel($info['channel_id'],$this->merchant_id,$data['end_time']);
			if($res['errcode']!=0) {
				return $res;
			}

			DB::commit();
		} catch (\Exception $e) {
			DB::rollBack();

			return ['errcode'=>'80005','errmsg'=>'操作失败']; 
		}

		return ['errcode'=>'0','errmsg'=>'操作成功']; 
	}

	//观众列表
	public function getuserlist(Request $request)
	{
		$params = $request->all();

		$page = isset($params['page']) ? (int)$params['page'] : 1;
		$pagesize = isset($params['pagesize']) ? (int)$params['pagesize'] : 10;
		$live_id = isset($params['live_id']) ? (int)$params['live_id'] : 0;
		$nickname = isset($params['nickname']) ? (string)$params['nickname'] : '';

		$where = ['merchant_id'=>$this->merchant_id];

		if($live_id) {
			$where['live_id'] = $live_id;
		}

		$query = LiveViewer::where($where);

		if($nickname) {
			$search_member = Member::where(['merchant_id'=>$this->merchant_id]);

			$search_member->where("name","like",'%'.$nickname.'%');
			$search_data = $search_member->get()->toArray();
			$member_ids = [];
			if($search_data) {
				foreach($search_data as $v) {
					$member_ids[] = $v['id'];
				}
			}
			
			$query->whereIn('member_id',$member_ids);
		}
		
		$count  = $query->count();

		$query->orderBy('created_time','desc');

		$data = $query->offset(($page-1)*$pagesize)->limit($pagesize)->get()->toArray();

		//读取直播信息
		if($data) {
			foreach($data as $v) {
				$live_ids[$v['live_id']] = $v['live_id'];
				$live_members[$v['member_id']] = $v['member_id'];
			}

			$members = Member::whereIn('id',$live_members)->get()->toArray();
			if($members) {
				foreach($members as $v) {
					$members[$v['id']] = $v;
				}
			}
			
			$lives = LiveInfo::whereIn('id',$live_ids)->get()->toArray();
			if($lives) {
				foreach($lives as $v) {
					$id_lives[$v['id']] = $v;
				}
			}

			foreach($data as $k=>$v) {
				$data[$k]['live_title'] = isset($id_lives[$v['live_id']]) ? $id_lives[$v['live_id']]['title'] : '';
				$data[$k]['nickname'] = isset($members[$v['member_id']]) ? $members[$v['member_id']]['name'] : '';
				$data[$k]['member_sn'] = MEMBER_CONST + $v['member_id'];
			}
		}

		return ['errcode' => 0, 'errmsg' => '获取数据成功', 'data' => $data, 'count'=> $count]; 
	}

	//禁言
	public function ban(Request $request)
	{
		$params = $request->all();

		$member_id = isset($params['member_id']) ? (int)$params['member_id'] : 0;
		$live_id = isset($params['live_id']) ? (int)$params['live_id'] : 0;
		$is_prohibit = isset($params['is_prohibit']) ? (int)$params['is_prohibit'] : 1;

		if(!$member_id || !$live_id) {
			return ['errcode'=>'80001','errmsg'=>'缺少参数']; 
		}

		$info = LiveViewer::get_data_by_id($member_id,$live_id);

		if(!$info) {
			return ['errcode'=>'80002','errmsg'=>'用户不存在']; 
		}

		$res = LiveViewer::update_data($member_id,$live_id,['is_prohibit'=>$is_prohibit]);
		if(!$res) {
			return ['errcode'=>'80003','errmsg'=>'操作失败'];
		}

		return ['errcode'=>'0','errmsg'=>'操作成功'];
	}

	//评论详情
	public function getcommentlist(Request $request)
	{
		
	}

	//购买详情
	public function getbuylist(Request $request)
	{
		$params = $request->all();

		$member_id = isset($params['member_id']) ? (int)$params['member_id'] : 0;

		if(!$member_id) {
			return ['errcode'=>'80001','errmsg'=>'参数不全'];
		}

		$query = OrderGoods::where(['merchant_id'=>$this->merchant_id,'member_id'=>$member_id]);

		$count  = $query->count();
		
		$data = $query->get()->toArray();

		return ['errcode' => 0, 'errmsg' => '获取数据成功', 'data' => $data, 'count'=> $count]; 
	}

	//直播列表
	public function getlist(Request $request)
	{
		$params = $request->all();

		$page = isset($params['page']) ? (int)$params['page'] : 1;
		$pagesize = isset($params['pagesize']) ? (int)$params['pagesize'] : 10;
		$title = isset($params['title']) ? (string)$params['title'] : '';
		$type = isset($params['type']) ? (int)$params['type'] : 0; //0分页 1全部
		$link = isset($params['link']) ? (int)$params['link'] : 0; //是否是挂接

		$where = ['merchant_id'=>$this->merchant_id,'is_delete'=>1];

		$query = LiveInfo::where($where);

		if($link) {
			$query->where("status","<>",2);
		}

		if($title) {
			$query->where("title","like",'%'.$title.'%');
		}

		$count  = $query->count();

		$query->orderBy('created_time','desc');
		
		if($type) {
			$data = $query->get()->toArray(); 
		} else {
			$data = $query->offset(($page-1)*$pagesize)->limit($pagesize)->get()->toArray();
		}

		//读取channel信息
		if($data) {
			foreach($data as $v) {
				$channel_ids[] = $v['channel_id'];
			}
			
			$channels = LiveChannel::whereIn('id',$channel_ids)->get()->toArray();
			if($channels) {
				foreach($channels as $v) {
					$id_channels[$v['id']] = $v;
				}
			}

			foreach($data as $k=>$v) {
				$data[$k]['view_num'] = isset($id_channels[$v['channel_id']]) ? $id_channels[$v['channel_id']]['view_sum'] : 0;
				$data[$k]['push_src'] = isset($id_channels[$v['channel_id']]) ? $id_channels[$v['channel_id']]['push_src'] : '';
			}
		}

		return ['errcode' => 0, 'errmsg' => '获取数据成功', 'data' => $data, 'count'=> $count]; 
	}

	//播放统计
	public function getplaylist(Request $request)
	{
		$params = $request->all();

		$page = isset($params['page']) ? (int)$params['page'] : 1;
		$pagesize = isset($params['pagesize']) ? (int)$params['pagesize'] : 10;
		$title = isset($params['title']) ? (string)$params['title'] : '';
		$start_time = isset($params['start_time']) ? (string)$params['start_time'] : '';
		$end_time = isset($params['end_time']) ? (string)$params['end_time'] : '';
		$time_type = isset($params['time_type']) ? (int)$params['time_type'] : 0; //1今天 2近七天 3近30天 4近半年

		$where = ['merchant_id'=>$this->merchant_id,'is_delete'=>1];

		$query = LiveChannel::where($where);
		
		//0未开始 1 直播中 2 暂停 3 结束
		if(isset($params['play_status']) && is_numeric($params['play_status'])) {
			$search = LiveInfo::where($where);
			$now = date('Y-m-d H:i:s');
			//0: 未开始; 1: 直播中; 2: 暂停 ;3: 结束 4: 已取消
			if($params['play_status']==2) { //暂停
				$search->where('status','=',1);
			} elseif($params['play_status']==4) { //已取消
				$search->where('status','=',2);
			} elseif($params['play_status']==0) { //未开始
				$search->where('status','=',0);
				$search->where('start_time','>',$now);
			} elseif($params['play_status']==1) { //直播中
				$search->where('status','=',0);
				$search->where('start_time','<',$now);
				$search->where('end_time','>',$now);
			} elseif($params['play_status']==3) { //结束
				$search->where('status','=',0);
				$search->where('end_time','<',$now);
			} 
	
			$live_data = $search->get()->toArray();
			$ids = [];
			foreach($live_data as $v) {
				$ids[] = $v['channel_id'];	
			}

			//print_r($ids);exit;
			
			$query->whereIn('id',$ids);
		}

		if($title) {
			$query->where("title","like",'%'.$title.'%');
		}

		if($time_type) {
			$end_time = date('Y-m-d H:i:s',strtotime(date('Y-m-d')) + 86400);
			switch($time_type) {
				case 1:
					$start_time = date('Y-m-d H:i:s',strtotime(date('Y-m-d')));
					break;
				case 2:
					$start_time = date('Y-m-d H:i:s',strtotime(date('Y-m-d'))-86400*7);
					break;
				case 3:
					$start_time = date('Y-m-d H:i:s',strtotime(date('Y-m-d'))-86400*30);
					break;
				case 4:
					$start_time = date('Y-m-d H:i:s',strtotime(date('Y-m-d'))-86400*182);
					break;
			}

			//开始时间
			if($start_time){
				$query->where('created_time','>=',$start_time);
			}
			//结束时间
			if($end_time){
				$query->where('created_time','<=',$end_time);
			}
		} else {
			//开始时间
			if($start_time){
				$query->where('created_time','>=',$start_time);
			}
			//结束时间
			if($end_time){
				$end_time = strtotime($end_time) + 86400;
				$query->where('created_time','<=',date('Y-m-d H:i:s',$end_time));
			}
		}

		$count  = $query->count();

		$query->orderBy('created_time','desc');

		$data = $query->offset(($page-1)*$pagesize)->limit($pagesize)->get()->toArray();

		//读取直播信息
		if($data) {
			foreach($data as $v) {
				$channel_ids[] = $v['id'];
			}
			
			$lives = LiveInfo::whereIn('channel_id',$channel_ids)->get()->toArray();
			if($lives) {
				foreach($lives as $v) {
					$id_lives[$v['channel_id']] = $v;
				}
			}

			foreach($data as $k=>$v) {
				//播放时长
				$time = $v['end_time'] - $v['start_time'];
				$data[$k]['timelen'] = $this->gettimelen($time);
				$data[$k]['start_time'] = date('Y-m-d H:i:s',$v['start_time']);
				$data[$k]['end_time'] = date('Y-m-d H:i:s',$v['end_time']);
				$data[$k]['consume'] = isset($id_lives[$v['id']]) ? $id_lives[$v['id']]['consume'] : 0;
				$data[$k]['live_status'] = isset($id_lives[$v['id']]) ? $id_lives[$v['id']]['status'] : 0;
				$data[$k]['live_start_time'] = isset($id_lives[$v['id']]) ? $id_lives[$v['id']]['start_time'] : 0;
				$data[$k]['live_end_time'] = isset($id_lives[$v['id']]) ? $id_lives[$v['id']]['end_time'] : 0;
				$now = time();
				if($data[$k]['live_status']==1) {
					$data[$k]['live_status_des'] = '已暂停';
				} elseif($data[$k]['live_status']==2) {
					$data[$k]['live_status_des'] = '已取消';
				} elseif(strtotime($data[$k]['live_start_time']) > $now) {
					$data[$k]['live_status_des'] = '未开始';
				} elseif(strtotime($data[$k]['live_start_time']) < $now && strtotime($data[$k]['live_end_time']) > $now) {
					$data[$k]['live_status_des'] = '直播中';
				} elseif(strtotime($data[$k]['live_end_time']) < $now) {
					$data[$k]['live_status_des'] = '已结束';
				}
			}
		}
		
		return ['errcode' => 0, 'errmsg' => '获取数据成功', 'data' => $data, 'count'=> $count]; 
	}

	/** 
	 * 计算剩余天时分。 
	 * $unixEndTime string 终止日期的Unix时间 
	 */
	public function gettimelen($time)  
	{    
		$days = 0;  
		if ($time >= 86400) { // 如果大于1天  
			$days = (int)($time / 86400);  
			$time = $time % 86400; // 计算天后剩余的毫秒数  
		}  
		  
		$xiaoshi = 0;  
		if ($time >= 3600) { // 如果大于1小时  
			$xiaoshi = (int)($time / 3600);  
			$time = $time % 3600; // 计算小时后剩余的毫秒数  
		}  
		  
		$fen = (int)($time / 60); // 剩下的毫秒数都算作分  
		
		$res = '';
		$res .= $days ? $days.'天' : '';
		$res .= $xiaoshi ? $xiaoshi.'小时' : '';
		$res .= $fen ? $fen.'分' : '';

		return $res;
	}  

	public function statistics(Request $request){
		$params = $request->all();
		$id = isset($params['id']) ? (int)$params['id'] : 0;
		if(!$id){
            return Response::json(['errcode'=>100031,'errmsg'=>'参数错误']);
        }
        $data['amount'] = 0;//商品售出总金额
	    $data['num'] = 0;//商品售出总件数
	    $data['order_people'] = 0;//下单人数
	    $data['pay_people'] = 0;//支付人数
	    $data['pay_num'] = 0;//支付件数
	    $data['pay_amount'] = 0;//支付金额
        $query = OrderInfo::select('order_goods.id','order_goods.goods_name','order_goods.created_time','order_goods.member_id','order_goods.quantity','order_goods.pay_price','order_info.nickname','order_info.pay_status');
        $query->where(['order_info.source'=>1,'order_info.source_id'=>$id,'order_info.merchant_id'=>$this->merchant_id]);
        $query->leftJoin('order_goods', 'order_info.id', '=', 'order_goods.order_id');
        $result = $query->get();
        if($result){
        	$result = $result -> toArray();
	        foreach ($result as $k => $v) {
	        	$data['amount'] += $v['pay_price'];
	        	$data['num'] += $v['quantity'];
	        	if($v['pay_status'] == 1){
	        		$data['pay_num'] += $v['quantity'];
	        		$data['pay_amount'] += $v['pay_price'];
	        	}
	        }
	    }
        // $num = \DB::select('select count(*) as num from(select count(*) as zs FROM order_info WHERE source=1 AND source_id=:id AND merchant_id = :merchant_id  GROUP BY member_id) as o',[':merchant_id'=>$this->merchant_id,':id'=>$id]);
        // $data['order_people'] = $num[0]->num;
        // $pay_num = \DB::select('select count(*) as num from(select count(*) as zs FROM order_info WHERE pay_status =1 AND source=1 AND source_id=:id AND merchant_id = :merchant_id GROUP BY member_id) as o',[':merchant_id'=>$this->merchant_id,':id'=>$id]);
        // $data['pay_people'] = $pay_num[0]->num;
        $data['order_people'] = OrderInfo::where(['source'=>1,'source_id'=>$id,'merchant_id'=>$this->merchant_id])->count();
        $data['pay_people'] = OrderInfo::where(['source'=>1,'source_id'=>$id,'merchant_id'=>$this->merchant_id,'pay_status'=>1])->count();
        $results = OrderInfo::select('order_goods.goods_id','order_goods.goods_name',DB::raw('sum(order_goods.quantity) as num'))->where(['order_info.source'=>1,'order_info.source_id'=>$id,'order_info.merchant_id'=>$this->merchant_id])->leftJoin('order_goods', 'order_info.id', '=', 'order_goods.order_id')->groupby('order_goods.goods_id')->get()->toArray();
        $data['sales'] = $results;
	    return ['errcode' => 0, 'errmsg' => '获取数据成功', 'data' => $data]; 
	}

	public function orderdata(Request $request){
		$params = $request->all();
		$id = isset($params['id']) ? (int)$params['id'] : 1;
		if(!$id){
            return Response::json(['errcode'=>100031,'errmsg'=>'参数错误']);
        }
        $page = isset($params['page']) ? (int)$params['page'] : 1;
		$pagesize = isset($params['pagesize']) ? (int)$params['pagesize'] : 10;

        $query = OrderInfo::select('order_goods.id','order_goods.goods_name','order_goods.created_time','order_goods.member_id','order_goods.quantity','order_goods.pay_price','order_info.nickname','order_info.pay_status');
        $query->where(['order_info.source'=>1,'order_info.source_id'=>$id,'order_info.merchant_id'=>$this->merchant_id]);
        $query->leftJoin('order_goods', 'order_info.id', '=', 'order_goods.order_id');
        $count = $query->count();
     	$query->orderBy('created_time','desc');
		$data = $query->offset(($page-1)*$pagesize)->limit($pagesize)->get()->toArray();
	    return ['errcode' => 0, 'errmsg' => '获取数据成功', 'data' => $data, 'count'=> $count]; 

	}


}

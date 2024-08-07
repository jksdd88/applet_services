<?php

namespace App\Http\Controllers\Admin\Live;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\LiveRecord;
use App\Models\MerchantSetting;
use App\Models\LiveBalance;
use App\Services\LiveService;
use App\Models\Coupon;
use App\Models\LiveRecordGoods;
use App\Models\Goods;

class RecordController extends Controller
{
    private $merchant_id;

    function __construct(Request $request)
    {
        $this->params = $request->all();  
		//$this->merchant_id = 2;
        //$this->user_id = 2;
		$this->merchant_id = Auth::user()->merchant_id;
		$this->user_id = Auth::user()->id;
    }

	//录播列表
	public function getrecordlist(Request $request)
	{
		$params = $request->all();

		$page = isset($params['page']) ? (int)$params['page'] : 1;
		$pagesize = isset($params['pagesize']) ? (int)$params['pagesize'] : 10;
		$title = isset($params['title']) ? (string)$params['title'] : '';
		$start_time = isset($params['start_time']) ? (string)$params['start_time'] : '';
		$end_time = isset($params['end_time']) ? (string)$params['end_time'] : '';
		$time_type = isset($params['time_type']) ? (int)$params['time_type'] : 0; //1今天 2近七天 3近30天 4近半年
		$status = isset($params['status']) ? (int)$params['status'] : 0; //0全部 1 上线 2下线 3 失效

		$where = ['merchant_id'=>$this->merchant_id,'is_delete'=>1];

		$query = LiveRecord::where($where)->where('status','>',0);

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

		if($status==1) {
			$query->where(['publish_status'=>1]);
			$query->where('status','=',2);
		} elseif($status==2) {
			$query->where(['publish_status'=>2]);
		} elseif($status==3) {
			$query->where('expire_time','<',time());
		}

		$count  = $query->count();

		$query->orderBy('created_time','desc');

		$data = $query->offset(($page-1)*$pagesize)->limit($pagesize)->get()->toArray();

		foreach($data as $k=>$v) {
			//剩余时间
			$time = $v['end_time'] - $v['start_time'];
			$data[$k]['timelen'] = $this->gettimelen($time);
			$v['expire_time'] = $v['expire_time'] ? $v['expire_time'] : $v['end_time']+86400*5;
			$lefttime = $this->lefttime($v['expire_time']);
			$data[$k]['lefttime'] = $lefttime ? $lefttime : '已失效';
			$data[$k]['start_time'] = date('Y-m-d H:i:s',$v['start_time']);
			$data[$k]['end_time'] = date('Y-m-d H:i:s',$v['end_time']);
			$data[$k]['download'] = '';
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

	/** 
	 * 计算剩余天时分。 
	 * $unixEndTime string 终止日期的Unix时间 
	 */
	public function lefttime($unixEndTime=0)  
	{  
		if ($unixEndTime <= time()) { // 如果过了活动终止日期  
			return '已失效';  
		}  
		  
		// 使用当前日期时间到活动截至日期时间的毫秒数来计算剩余天时分  
		$time = $unixEndTime - time();  
		  
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

	//删除录播
	public function del(Request $request)
	{
		$params = $request->all();

		$id = isset($params['id']) ? (int)$params['id'] : 0;

		if(!$id) {
			return ['errcode'=>'80001','errmsg'=>'缺少参数']; 
		}

		$info = LiveRecord::get_one('id',$id);

		if(!$info) {
			return ['errcode'=>'80002','errmsg'=>'录播不存在']; 
		}

		//接口
		if($info['rid']) {
			$LiveService = new LiveService();
			$res = $LiveService->deleteVod($id,$this->merchant_id);
			if($res['errcode']!=0) {
				return $res;
			}
		}

		$res = LiveRecord::update_data('id',$id,['is_delete'=>-1]);
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
		$consume = isset($params['consume']) ? $params['consume'] : 0;
		$month = isset($params['month']) ? (int)$params['month'] : 0;

		if(!$id || !$consume || !$month) {
			return ['errcode'=>'80001','errmsg'=>'缺少参数']; 
		}

		$info = LiveRecord::get_one('id',$id);

		if(!$info) {
			return ['errcode'=>'80002','errmsg'=>'录播不存在']; 
		}

		//检查录播包余额
		$setting = MerchantSetting::get_data_by_id($this->merchant_id);
		if($setting['live_store'] < $consume) {
			return ['errcode'=>'80004','errmsg'=>'直播云存储余额不足']; 
		}

		DB::beginTransaction();
		try {
			//扣除直播包余额
			MerchantSetting::decrement_data($setting['id'],$this->merchant_id,'live_store',$consume);

			$setting = MerchantSetting::get_data_by_id($this->merchant_id);
			$live_balance_data = [
				'merchant_id' => $this->merchant_id,
				'sum' => -$consume,
				'balance' => $setting['live_store'],
				'ctype' => 3,
				'type' => 7,
				'memo' => '录播续费云存储消耗'.$consume.'G',
			];
			LiveBalance::insert_data($live_balance_data);

			//增加消耗存储包数量
			LiveRecord::increment_data($id,$this->merchant_id,'consume',$consume);

			//增加录播的过期时间
			$addtime = $info['expire_time'] ? $month*30*86400 : $info['end_time']+$month*30*86400;
			LiveRecord::increment_data($id,$this->merchant_id,'expire_time',$addtime);

			DB::commit();
		} catch (\Exception $e) {
			DB::rollBack();

			return ['errcode'=>'80005','errmsg'=>'操作失败']; 
		}

		return ['errcode'=>'0','errmsg'=>'操作成功']; 
	}

	//点播视频上下线
	public function publish(Request $request) {
		$params = $request->all();

		$id = isset($params['id']) ? (int)$params['id'] : 0;
		$status = isset($params['status']) ? (int)$params['status'] : 0;

		if(!$id || !$status) {
			return ['errcode'=>'80001','errmsg'=>'缺少参数']; 
		}
		
		//查询录播余额
		if($status==1) {
			//检查录播包余额
			$setting = MerchantSetting::get_data_by_id($this->merchant_id);
			if($setting['live_record'] == 0) {
				return ['errcode'=>'80004','errmsg'=>'录播人次余额不足']; 
			}
		}

		$LiveService = new LiveService();

		return $LiveService->publishVideo($id,$status);
	}
	
	
	
	
	
	
	
	//录播信息
	public function info($id,Request $request)
	{
	    $params = $request->all();
	
	    $id = isset($id) ? (int)$id : 0;
	
	    if(!$id) {
	        return ['errcode'=>'80001','errmsg'=>'缺少参数'];
	    }
	   
	    //录播信息
	    $info = LiveRecord::get_one('id', $id);
	
	    if(!$info) {
	        return ['errcode'=>'80002','errmsg'=>'录播不存在'];
	    }
	    
	
	    //优惠券
	    $info['coupons'] = [];
	    $view_coupons = explode(',',$info['view_coupons']);
	    if(!empty($view_coupons)) {
	        $Coupon = new Coupon();
	        $query = $Coupon->select('id', 'name')->where(['is_delete' =>1,'merchant_id' =>$this->merchant_id]);
	        $info['coupons'] = $query->whereIn('id',$view_coupons)->get()->toArray();
	    }
	    
	
	    //已选商品
	    $list = LiveRecordGoods::get_data_by_live_record_id($id, $this->merchant_id);
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
	
	
	    return ['errcode'=>'0','errmsg'=>'获取录播信息成功','data'=>$info];
	}
	
	
	//修改录播
	public function edit($id,Request $request)
	{
	    $params = $request->all();
	
	    $id = isset($id) ? (int)$id : 0;
	    
	    //录播信息
	    $live_record_info = LiveRecord::get_one('id', $id);
	    
	    if(!$live_record_info) {
	        return ['errcode'=>'80001','errmsg'=>'录播信息不存在'];
	    }
	    
	    
	    
	    if($live_record_info['from_type'] == 0){
	        //视频来源：录制，不能修改视频类型
	        $data['type'] = isset($live_record_info['type']) ? (int)$live_record_info['type'] : 0; //视频类型：0->电商，1->普通
	    }else{
	        //视频来源：上传
	        $data['type'] = isset($params['type']) ? (int)$params['type'] : 0; //视频类型：0->电商，1->普通
	    }
	    
	    
	    $data['title'] = isset($params['title']) ? (string)$params['title'] : '';  //视频名称
	    $data['img_url'] = isset($params['img_url']) ? (string)$params['img_url'] : '';  //视频封面
	    $data['is_comment'] = isset($params['is_comment']) ? (int)$params['is_comment'] : 0;   //开启评论 1 开启 0 关闭
	    $data['virtual_sum'] = isset($params['virtual_sum']) ? (int)$params['virtual_sum'] : 0;    //虚拟观看人数
	    
	    
	    
	    if($data['type'] == 0){
	        //视频类型：电商
	        $data['view_coupons'] = isset($params['view_coupons']) ? (string)$params['view_coupons'] : ''; //送优惠券id集合
	        $goods = isset($params['goods']) ? (string)$params['goods'] : '';
	        $data['range'] = isset($params['range']) ? (int)$params['range'] : 2;  //关联商品 1 自选商品 2全店参与
	    }else{
	        //视频类型：普通
	        $data['desc'] = isset($params['desc']) ? (string)$params['desc'] : ''; //视频简介
	    }
	    
	
	    $res = LiveRecord::update_data('id', $id, $data);
	    if(!$res) {
	        return ['errcode'=>'80002','errmsg'=>'操作失败'];
	    }
	    
	
	
	
	    $goods = isset($goods) ? explode(',',$goods) : [];
	
	    
	    if($goods){
	        //查询现有的
	        $list = LiveRecordGoods::get_data_by_live_record_id($id, $this->merchant_id);
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
	                    'live_record_id' => $id,
	                    'goods_id' => $goods_id,
	                ];
	                LiveRecordGoods::insert_data($goods_data);
	            }
	        }
	        
	        //需要删除的
	        $need_del = array_diff($ids,$goods);
	        if($need_del) {
	            foreach($need_del as $goods_id) {
	                LiveRecordGoods::delete_data($id,$goods_id,$this->merchant_id);
	            }
	        }
	    }
	    
	    
	
	    return ['errcode'=>'0','errmsg'=>'操作成功'];
	}
	
	
	
	
	//录像上传
	public function upRecord(Request $request){
	    
	    //参数
	    $params = $request->all();
	    
	    $merchant_id = $this->merchant_id;//商户id
	    
	    if(!$merchant_id){
	        return Response::json(['errcode' => 99001,'errmsg' => '商户ID不存在']);
	    }
	    
	    //文件名 包含后缀
	    $filename = isset($params['filename']) ? $params['filename'] : 'record_'.time().mt_rand(10,99);
	    
	    if(!$filename){
	        return Response::json(['errcode' => 99001,'errmsg' => '缺少文件名参数']);
	    }
	    
	    //文件大小 字节
	    $size = isset($params['size']) ? $params['size'] : 0;
	    
	    if(!$size){
	        return Response::json(['errcode' => 99001,'errmsg' => '缺少文件大小参数']);
	    }

	    //文件最后修改时间 毫秒
	    $time = isset($params['time']) ? $params['time'] : '';
	    
	    if(!$time){
	        return Response::json(['errcode' => 99001,'errmsg' => '缺少文件最后修改时间参数']);
	    }

        //文件MD5值
        $key = isset($params['key']) ? $params['key'] : 0;
        if(!$key){
            return Response::json(['errcode' => 99001,'errmsg' => '缺少文件key参数']);
        }
	    
	    //调用接口
	    $LiveService = new LiveService();
	    $rs = $LiveService->vodUpload($merchant_id, $filename, $size, $time, $key);
	    
	    if($rs['errcode'] == 0){
	        return Response::json(['errcode' => 0,'errmsg' => '操作成功','data' => $rs['data']]);
	    }else{
	        if(isset($rs['response'])){
	            return Response::json(['errcode' => 99001,'errmsg' => $rs['errmsg'],'response'=>$rs['response']]);
	        }else{
	            return Response::json(['errcode' => 99001,'errmsg' => $rs['errmsg']]);
	        }
	    }
	    
	}
	
	
	//录像上传状态上报
	public function reportRecord(Request $request){
	     
	    //参数
	    $params = $request->all();
	     
	    $merchant_id = $this->merchant_id;//商户id
	     
	    if(!$merchant_id){
	        return Response::json(['errcode' => 99001,'errmsg' => '商户ID不存在']);
	    }
	    
	    //live_upload id
	    $id = isset($params['id']) ? (int)$params['id'] : 0;
	    
	    if(!$id){
	        return Response::json(['errcode' => 99001,'errmsg' => '缺少live_upload id参数']);
	    }
	    
	    //$status  0 上传完成 1上传失败 2取消上传
	    $status = isset($params['status']) ? (int)$params['status'] : 2;
	    
	    
	    //调用接口
	    $LiveService = new LiveService();
	    $rs = $LiveService->vodReport($id, $status);
	     
	    
	    
	    if($rs['errcode'] == 0){
	        return Response::json(['errcode' => 0,'errmsg' => '操作成功']);
	    }else{
	        if(isset($rs['response'])){
	            return Response::json(['errcode' => 99001,'errmsg' => $rs['errmsg'],'response'=>$rs['response']]);
	        }else{
	            return Response::json(['errcode' => 99001,'errmsg' => $rs['errmsg']]);
	        }
	    }
	}
	
	
	
	//录播同步直播数据
	public function recordGetLive(Request $request){
	
	    //参数
	    $params = $request->all();
	
	    $merchant_id = $this->merchant_id;//商户id
	
	    if(!$merchant_id){
	        return Response::json(['errcode' => 99001,'errmsg' => '商户ID不存在']);
	    }
	     
	    //录播id
	    $id = isset($params['id']) ? (int)$params['id'] : 0;
	     
	    if(!$id){
	        return Response::json(['errcode' => 99001,'errmsg' => '缺少录播id']);
	    }
	    
	    //调用接口
	    $LiveService = new LiveService();
	    $rs = $LiveService->copyLiveSet($id);
	    
	    return Response::json(['errcode' => 0,'errmsg' => '操作成功']);
	    
	}
}

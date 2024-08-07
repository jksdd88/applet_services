<?php

namespace App\Http\Controllers\Admin\Distrib;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\MemberBalanceDetail;
use App\Models\Member;
use App\Models\WeixinInfo;
use App\Utils\CacheKey;
use App\Services\WeixinPayService;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Config;

class DistribCashController extends Controller
{
    private $merchant_id;

    function __construct(Request $request)
    {
        $this->params = $request->all();  
		
		$this->merchant_id = Auth::user()->merchant_id;
        $this->user_id = Auth::user()->id; 
		//$this->merchant_id = 2;
        //$this->user_id = 1;
    }

	/**
     * 获取提现申请列表
     * @param $page				int		可选	页码 (可选,默认为1)
     * @param $pagesize			int		可选	显示条数(可选,默认为10)
     * @param $type				int		必填	1->提现到微信零钱  2->提现到支付宝/银行卡
     * @param $status			int		可选	提现状态 1->待处理  2->提现中 3->商家拒绝 4->提现成功 5->余额不足（微信零钱返回）
     * @param $wxinfo_id		int		可选	来源小程序
     * @param $account_number	string	可选	银行卡号
	 * @param $alipay			string	可选	支付宝账号
    */
	public function getTakecashList(Request $request)
	{
		set_time_limit(0); //防止导出数据量大时程序超时
		$params = $request->all();

		$page = isset($params['page']) ? (int)$params['page'] : 1;
		$pagesize = isset($params['pagesize']) ? (int)$params['pagesize'] : 10;
		$type = isset($params['type']) ? (int)$params['type'] : 0;
		$status = isset($params['status']) ? (int)$params['status'] : 0;
		$wxinfo_id = isset($params['wxinfo_id']) ? (int)$params['wxinfo_id'] : 0;
		$account_number = isset($params['account_number']) ? (string)$params['account_number'] : '';
		$alipay = isset($params['alipay']) ? (string)$params['alipay'] : '';
		$is_down = isset($params['is_down']) ? (int)$params['is_down'] : 0;

		if(!$type) {
			return ['errcode'=>'70001','errmsg'=>'缺少参数'];
		}

		$takecash_status = config('config.takecash_status');
		$takecash_types = [1 => '提现到微信零钱', 2 => '提现到支付宝', 3 => '提现到银行卡'];

		$where['merchant_id'] = $this->merchant_id;
		if($status) {
			$where['status'] = $status;
		}

		if($wxinfo_id) {
			$where['wxinfo_id'] = $wxinfo_id;
		}

		if($type==1) {
			$where['type'] = $type;
		}

		if($account_number) {
			$where['account_number'] = $account_number;
		}

		if($alipay) {
			$where['alipay'] = $alipay;
		}

		$query = MemberBalanceDetail::where($where);

		if($type==2) {
			$query->whereIn('type',[2,3]);
		}
		
		/*
		if($account_number) {
			$query->where("account_number","like",'%'.$account_number.'%');
		}

		if($alipay) {
			$query->where("alipay","like",'%'.$alipay.'%');
		}
		*/

		//开始时间
        if(isset($params['start_time'])){
			$start_time = strtotime($params['start_time']);
            $query->whereDate('created_time','>=',$params['start_time']);
        }
        //结束时间
        if(isset($params['end_time'])){
			$end_time = strtotime($params['end_time']) + 86400;
            $query->whereDate('created_time','<=',$params['end_time']);
        }

		$query->orderBy('created_time','desc');
		
		//下载数据
		if($is_down){
			$filename = '推客提现申请列表'.date('Y-m-d').'.csv';
            header('Content-Type: application/vnd.ms-excel');
            header('Content-Disposition: attachment;filename="'.$filename.'"');
            header('Cache-Control: max-age=0');
            //打开PHP文件句柄，php://output表示直接输出到浏览器
            $fp = fopen('php://output', 'a');
            //表格列表
            if($type == 2){
            	$head = ['申请推客', '提现小程序', '姓名', '手机号', '提现方式', '提现订单号', '提现金额', '状态', '申请时间', '处理时间', '失败原因', '备注', '支付宝', '银行卡', '开户行'];
            }else{
            	$head = ['申请推客', '提现小程序', '姓名', '手机号', '提现方式', '提现订单号', '提现金额', '状态', '申请时间', '处理时间', '失败原因', '备注'];
            }
            
            foreach($head as $key => $val){
                $head[$key] = @iconv('UTF-8', 'GBK//IGNORE', $val);
            }
            fputcsv($fp, $head);

			$query->chunk(100, function($list) use($fp, $type, $takecash_types, $takecash_status) {
				foreach($list as $value){
					$member = Member::get_data_by_id($value['member_id'], $this->merchant_id);
					$wxinfo = WeixinInfo::get_one_all('id', $value['wxinfo_id']);
					$row = [ 
						'nick_name'    => filterEmoji($member['name']), //申请推客
						'weapp'        => $wxinfo['nick_name'], //提现小程序
						'name'         => $value['name'], //推客姓名
						'mobile'       => $value['mobile'], //手机号
						'type'         => $takecash_types[$value['type']], //提现方式
						'order_nu'     => $value['order_no'], //提现订单号
						'amount'       => $value['amount'], //提现金额
						'status_des'   => $value['status'] ? $takecash_status[$value['status']] : '', //状态
						'created_time' => $value['created_time'], //申请时间
						'handle_time'  => $value['handle_time'], //处理时间
						'fail_reason'  => $value['fail_reason'], //失败原因
						'remark'       => $value['remark'] //备注
	                ];
	                if($type == 2) {
						$row['alipay']           = $value['alipay'];
						$row['account_number']   = $value['account_number'];
						$row['branch_bank_name'] = $value['branch_bank_name'];
	                }

	                $column = array();
                    foreach($row as $k => $v){
                        $column[$k] = @iconv('UTF-8', 'GBK//IGNORE', $v);
                    }
                    fputcsv($fp, $column);
				}	
			});
			exit;
		}
		
		$data['count']  = $query->count();
		$data['data'] = $query->offset(($page-1)*$pagesize)->limit($pagesize)->get();

		
		$list = [];
		if($data['count']>0){
			$data['data'] = $data['data']->toArray();
			foreach($data['data'] as $k=>$v){
				//微信昵称
                $member_info = Member::get_data_by_id($v['member_id'], $this->merchant_id);
				$list[$k]['id'] = $v['id'];
				$list[$k]['nick_name'] =  $member_info ? $member_info['name'] : '--';
				$wxinfo = WeixinInfo::get_one_all('id',$v['wxinfo_id']);
				if($wxinfo['status']==-1) {
					$list[$k]['wx_nick_name'] =  $wxinfo['nick_name'].'(已删除)';
				} else {
					$list[$k]['wx_nick_name'] =  $wxinfo['nick_name'];
				}
				$list[$k]['mobile'] = $v['mobile'];
				$list[$k]['name'] = $v['name'];
				$list[$k]['type'] = $takecash_types[$v['type']];
				$list[$k]['order_no'] = $v['order_no'];
				$list[$k]['amount'] = $v['amount'];
				$list[$k]['status'] = $v['status'];
				$list[$k]['status_des'] = $v['status'] ? $takecash_status[$v['status']] : '';
				if($v['status']==TAKECASH_FAIL) {
					//$list[$k]['status_des'] = '提现中';
				}
				$list[$k]['created_time'] = $v['created_time'];
				$list[$k]['handle_time'] = $v['handle_time'];
				$list[$k]['fail_reason'] = $v['fail_reason'];
				$list[$k]['remark'] = $v['remark'];
				$list[$k]['alipay'] = $v['alipay'];
				$list[$k]['account_number'] = $v['account_number'];
				$list[$k]['branch_bank_name'] = $v['branch_bank_name'];
				$list[$k]['member_sn'] = MEMBER_CONST + $v['member_id'];
			}
		}

		return ['errcode' => 0, 'errmsg' => '获取数据成功'.$this->merchant_id, 'data' => $list, 'count'=> $data['count']]; 
	}

	/**
     * 同意提现到微信零钱
     * @param $id				int		必填	主键id
    */
	public function takecashAgree(Request $request)
	{
		$params = $request->all();

		$ids = isset($params['id']) ? $params['id'] : [];

		if(!$ids) {
			return ['errcode'=>'70001','errmsg'=>'缺少参数'];
		}

		if(is_array($ids)){
			$count = count($ids);
			$success = 0;
			foreach($ids as $id){
				$detail = MemberBalanceDetail::get_data_by_id($id, $this->merchant_id);
				if($detail && $detail['type'] == 1 && ($detail['status'] == TAKECASH_AWAIT || $detail['status'] == TAKECASH_FAIL)) {
					//查询appid
					$wxinfo = WeixinInfo::get_one_all('id', $detail['wxinfo_id']);
					//调用接口
					$data = [
						'merchant_id' => $this->merchant_id,
						'no'          => $detail['order_no'],
						'appid'       => $wxinfo['appid'],
						'member_id'   => $detail['member_id'],
						'amount'      => $detail['amount'],
						'cid'         => $detail['id'],
						'type'        => 1
					];

					$WeixinPayService = new WeixinPayService();
					$res = $WeixinPayService->transfersSubmit($data);
					if($res['errcode'] == 0) {
						$success++;
						MemberBalanceDetail::update_data($id, $this->merchant_id, ['handle_time' => date('Y-m-d H:i:s'), 'status' => TAKECASH_SUBMIT]);
					}
				}
			}
			return ['errcode' => 0, 'errmsg' => '此次共执行'.$count.'条记录，成功'.$success.'条'];
		}else{
			$id = $ids;
			$detail = MemberBalanceDetail::get_data_by_id($id, $this->merchant_id);
			if(!$detail) {
				return ['errcode' => '70002','errmsg' => '没有提现申请记录'];
			}

			if($detail['type'] != 1) {
				return ['errcode' => '70003','errmsg' => '提现申请类型必须为提现到微信零钱'];
			}

			if($detail['status'] != TAKECASH_AWAIT && $detail['status'] != TAKECASH_FAIL) {
				return ['errcode' => '70004', 'errmsg' => '当前申请状态不允许此操作'];
			}

			//查询appid
			$wxinfo = WeixinInfo::get_one_all('id', $detail['wxinfo_id']);

			//调用接口
			$data = [
				'merchant_id' => $this->merchant_id,
				'no'          => $detail['order_no'],
				'appid'       => $wxinfo['appid'],
				'member_id'   => $detail['member_id'],
				'amount'      => $detail['amount'],
				'cid'         => $detail['id'],
				'type'        => 1
			];
			
			$WeixinPayService = new WeixinPayService();
			$res = $WeixinPayService->transfersSubmit($data);
			if($res['errcode'] != 0) {
				return ['errcode' => '70005', 'errmsg' => $res['errmsg']];
			}

			$res = MemberBalanceDetail::update_data($id, $this->merchant_id, ['handle_time' => date('Y-m-d H:i:s'), 'status' => TAKECASH_SUBMIT]);
			if(!$res) {
				return ['errcode' => '70006', 'errmsg' => '操作失败'];
			}

			return ['errcode' => 0, 'errmsg' => '操作成功'];
		}
	}

	/**
     * 拒绝提现
     * @param $id				int		必填	主键id
	 * @param $remark			string	必填	备注，拒绝理由
    */
	public function takecashDisagree(Request $request)
	{
		$params = $request->all();

		$id = isset($params['id']) ? (int)$params['id'] : 0;
		$remark = isset($params['remark']) ? (string)$params['remark'] : '';

		if(!$id || !$remark) {
			return ['errcode'=>'70001','errmsg'=>'缺少参数'];
		}

		$detail = MemberBalanceDetail::get_data_by_id($id,$this->merchant_id);
		if(!$detail) {
			return ['errcode'=>'70002','errmsg'=>'没有提现申请记录'];
		}

		if($detail['status']!= TAKECASH_AWAIT && $detail['status']!= TAKECASH_FAIL) {
			return ['errcode'=>'70004','errmsg'=>'当前申请状态不允许此操作'];
		}

		//当前用户信息
		$pre_member_info = Member::get_data_by_id($detail['member_id'], $this->merchant_id);

		//需要退钱 开始事务
		DB::beginTransaction();
		try {
			//归还到用户余额
			Member::increment_data($detail['member_id'],$this->merchant_id,'balance',$detail['amount']);

			$final_member_info = Member::get_data_by_id($detail['member_id'], $this->merchant_id);

			if($detail['amount']>0 && $final_member_info['balance']==$pre_member_info['balance']) {
				return ['errcode' => '70006', 'errmsg' => '提现失败'];
			}

			//记录金额变动
			$data = [
				'merchant_id'		=> $detail['merchant_id'],
				'member_id'			=> $detail['member_id'],
				'wxinfo_id'			=> $detail['wxinfo_id'],
				'order_no'			=> $detail['order_no'],
				'amount'			=> $detail['amount'],
				'pre_amount'		=> $pre_member_info['balance'],
				'final_amount'		=> $final_member_info['balance'],
				'status'			=> TAKECASH_REFUSE,
				'type'				=> 5,
				'name'				=> $detail['name'],
				'mobile'			=> $detail['mobile'],
				'alipay'			=> $detail['alipay'],
				'account_number'	=> $detail['account_number'],
				'branch_bank_name'	=> $detail['branch_bank_name'],
				'remark'			=> $remark,
			];
			MemberBalanceDetail::insert_data($data);

			MemberBalanceDetail::update_data($id,$this->merchant_id,['handle_time'=>date('Y-m-d H:i:s'),'status'=>TAKECASH_REFUSE,'remark'=>$remark]);

			DB::commit();
		} catch (\Exception $e) {
			DB::rollBack();

			//失败去除member缓存 
			$key = CacheKey::get_member_by_id_key($detail['member_id'], $this->merchant_id);
			Cache::forget($key);

            return ['errcode' => '70005', 'errmsg' => '操作失败'];
        }

		return ['errcode' => 0, 'errmsg' => '操作成功'];
	}
	
	/**
     * 线下打款确认
     * @param $id				int		必填	主键id
    */
	public function takecashConfirm(Request $request)
	{
		$params = $request->all();

		$id = isset($params['id']) ? (int)$params['id'] : 0;

		if(!$id) {
			return ['errcode'=>'70001','errmsg'=>'缺少参数'];
		}

		$detail = MemberBalanceDetail::get_data_by_id($id,$this->merchant_id);
		if(!$detail) {
			return ['errcode'=>'70002','errmsg'=>'没有提现申请记录'];
		}

		if($detail['type']!=2 && $detail['type']!=3) {
			return ['errcode'=>'70003','errmsg'=>'提现申请类型必须为支付宝/银行卡'];
		}

		if($detail['status']!= TAKECASH_AWAIT) {
			return ['errcode'=>'70004','errmsg'=>'当前申请状态不允许此操作'];
		}

		$res = MemberBalanceDetail::update_data($id,$this->merchant_id,['handle_time'=>date('Y-m-d H:i:s'),'status'=>TAKECASH_SUCCESS]);
		if(!$res) {
			return ['errcode' => '70005', 'errmsg' => '操作失败'];
		}

		return ['errcode' => 0, 'errmsg' => '操作成功'];
	}

    
}

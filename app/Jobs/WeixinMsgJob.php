<?php
/**
 * 消息模板队列
 * @author zhangchangchun@dodoca.com
 * $order_id 订单id
 */
namespace App\Jobs;

use App\Jobs\Job;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Bus\SelfHandling;
use Illuminate\Contracts\Queue\ShouldQueue;

use App\Models\OrderInfo;
use App\Models\OrderGoods;
use App\Models\OrderAppt;
use App\Models\Member;
use App\Models\Store;
use App\Models\Fightgroup;
use App\Models\FightgroupJoin;
use App\Models\FightgroupLaunch;
use App\Models\MemberCard;
use App\Models\OrderPackage;
use App\Models\OrderPackageItem;
use App\Models\Merchant;
use App\Models\UserLog;
use App\Models\DistribPartner;
use App\Services\WeixinMsgService;

use App\Utils\CommonApi;

class WeixinMsgJob extends Job implements SelfHandling, ShouldQueue
{
    /**
     * Create a new job instance.
     * data = [
	 		'order_id'		=>	1,
			'merchant_id'	=>	1,
			'type'			=>	1,	//消息模板类型
	 	];
     * @return void
     */
    public function __construct($data=[])
    {
		$this->data        = $data;
		$this->type        = isset($data['type']) ? $data['type'] : 0;
		$this->order_id    = isset($data['order_id']) ? $data['order_id'] : 0;
		$this->merchant_id = isset($data['merchant_id']) ? $data['merchant_id'] : 0;
		$this->member_id   = isset($data['member_id']) ? $data['member_id'] : 0;
		$this->delivery_id   = isset($data['delivery_id']) ? $data['delivery_id'] : 0;	//物流id（发货用）
		$this->goods_title = isset($data['goods_title']) ? $data['goods_title'] : 0;	//预约服务:服务名称
        $this->appt_string = isset($data['appt_string']) ? $data['appt_string'] : 0;	//预约服务:时间
        $this->store_name  = isset($data['store_name']) ? $data['store_name'] : 0;	    //预约服务:店铺名称
        $this->store_id    = isset($data['store_id']) ? $data['store_id'] : 0;	    //预约服务:店铺id

		if($this->order_id && $this->merchant_id) {
			$this->orderInfo = OrderInfo::get_data_by_id($this->order_id,$this->merchant_id);
		}
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(WeixinMsgService $WeixinMsgService)
    {
        switch($this->type) {
			case 'topay':	//待付款提醒
				if($this->orderInfo && !in_array($this->orderInfo['order_type'],[ORDER_SALEPAY,ORDER_KNOWLEDGE])) {
					$msgData = [
						'merchant_id'	=>	$this->orderInfo['merchant_id'],
						'member_id'		=>	$this->orderInfo['member_id'],
						'appid'			=>	$this->orderInfo['appid'],
						'orderid'		=>	$this->orderInfo['id'],
						'no'			=> 	$this->orderInfo['order_sn'],
						'name'			=> 	$this->orderInfo['order_title'],
						'status' 		=> 	'待支付',
						'price'		 	=> 	$this->orderInfo['amount'],
						'tip' 			=> 	"请在".$this->orderInfo['expire_at']."之前完成支付",
					];
					$result = $WeixinMsgService->payPending($msgData);
					//记录日志	
					CommonApi::wlog([
						'custom'    	=>    	'wxmsg_topay_'.$this->orderInfo['id'],
						'merchant_id'   =>    	$this->orderInfo['merchant_id'],
						'member_id'     =>    	$this->orderInfo['member_id'],
						'content'		=>		'require->'.json_encode($msgData,JSON_UNESCAPED_UNICODE).',result->'.json_encode($result,JSON_UNESCAPED_UNICODE),
					]);
				}
				break;
			case 'paysuccess':	//订单支付成功通知
				if($this->orderInfo) {
					$result = $msgData = [];
					if($this->orderInfo['order_type'] == ORDER_APPOINT){//预约订单
						$appInfo = OrderAppt::select(['appt_string','hexiao_code','appt_date','store_id','merchant_id'])->where(['order_id'=>$this->order_id])->first();
						if($appInfo) {
							$storeInfo = Store::get_data_by_id($appInfo['store_id'],$appInfo['merchant_id']);
							$storeAddr = '';
							if($storeInfo) {
								$storeAddr = $storeInfo['province_name'].$storeInfo['city_name'].$storeInfo['district_name'].$storeInfo['address'];
							}
							$msgData = [
								'merchant_id'	=>	$this->orderInfo['merchant_id'],
								'member_id'		=>	$this->orderInfo['member_id'],
								'appid'			=>	$this->orderInfo['appid'],
								'orderid'		=>	$this->orderInfo['id'],
								'no'			=> 	$this->orderInfo['order_sn'],
								'name'			=> 	$this->orderInfo['order_title'],
								'time'			=>	$appInfo['appt_string'],
								'remark' 		=> 	"您的预约号码为".$appInfo['hexiao_code']."，请在 ".$appInfo['appt_date']." 到达 ".$storeAddr." 享受服务。",
							];
							$result = $WeixinMsgService->bespeak($msgData);
						}
					} else {
						$msgData = [
							'merchant_id'	=>	$this->orderInfo['merchant_id'],
							'member_id'		=>	$this->orderInfo['member_id'],
							'appid'			=>	$this->orderInfo['appid'],
							'orderid'		=>	$this->orderInfo['id'],
							'no'			=> 	$this->orderInfo['order_sn'],
							'name'			=> 	$this->orderInfo['order_type']==ORDER_SALEPAY ? "买单" : $this->orderInfo['order_title'],
							'price'		 	=> 	$this->orderInfo['amount'],
							'time'			=>	$this->orderInfo['pay_time'],
							'tip' 			=> 	$this->orderInfo['pay_type']==ORDER_PAY_WEIXIN ? "微信支付" : "货到付款",
						];
						$result = $WeixinMsgService->PaySuccess($msgData);
					}
					//记录日志	
					CommonApi::wlog([
						'custom'    	=>    	'wxmsg_paysuccess_'.$this->orderInfo['id'],
						'merchant_id'   =>    	$this->orderInfo['merchant_id'],
						'member_id'     =>    	$this->orderInfo['member_id'],
						'content'		=>		'require->'.json_encode($msgData,JSON_UNESCAPED_UNICODE).',result->'.json_encode($result,JSON_UNESCAPED_UNICODE),
					]);
				}
				break;
			case 'upgrade': //会员升级
				$member_id = $this->member_id;
				$member = Member::where('id', $member_id)->first();
				if($member){

					$member_card_id = $member['member_card_id'];

					if($member_card_id){
						$member_card = MemberCard::where('id', $member_card_id)->first();

						$msgData = [
							'merchant_id' => $member['merchant_id'],
							'member_id'   => $member_id,
							'current'     => $member_card['card_name']
						];

						$level = '享受'.($member_card['discount'] * 10).'折优惠';
						if($member_card['is_postage_free'] == 1){
							$level = $level.'，并包邮';
						}

						$msgData['level'] = $level;

						$validity_time = '';
						if($member_card['card_type'] == 1){
							$validity_time = '满足条件自动升级';
						}elseif($member_card['card_type'] == 2){
							$validity_time = $member['member_card_overtime'];
						}

						$msgData['validity_time'] = $validity_time;
					}else{
						$msgData = [
							'merchant_id'   => $member['merchant_id'],
							'member_id'     => $member_id,
							'current'       => '默认会员',
							'validity_time' => '满足条件自动升级',
							'level'         => '无任何优惠'
						];
					}
					
					$result = $WeixinMsgService->upgrade($msgData);
					//记录日志	
					CommonApi::wlog([
						'custom'    	=>    	'wxmsg_upgrade_'.$member_id,
						'merchant_id'   =>    	$this->merchant_id,
						'member_id'     =>    	$this->member_id,
						'content'		=>		'require->'.json_encode($msgData,JSON_UNESCAPED_UNICODE).',result->'.json_encode($result,JSON_UNESCAPED_UNICODE),
					]);
				}
				break;
			case 'fightgroup':	//拼团成功
			    if($this->orderInfo) {
			        
			        //拼团参与表信息
			        $fightgroup_join_info = FightgroupJoin::select('id','fightgroup_id','launch_id','tuan_price')->where('order_id','=',$this->orderInfo['id'])->where('merchant_id','=',$this->orderInfo['merchant_id'])->first();
			        
			        //拼团主表信息
                    $fightgroup_info = Fightgroup::get_data_by_id($fightgroup_join_info['fightgroup_id'], $this->orderInfo['merchant_id']);
			        
			        //拼团发起表信息
                    $fightgroup_launch_info = FightgroupLaunch::get_data_by_id($fightgroup_join_info['launch_id'], $this->orderInfo['merchant_id']);
			        
			        
			        $msgData = [
			            'merchant_id'	=>	$this->orderInfo['merchant_id'],
			            'member_id'		=>	$this->orderInfo['member_id'],
			            'appid'			=>	$this->orderInfo['appid'],
			            'orderid'		=>	$this->orderInfo['id'],
			            'name'			=> 	$fightgroup_info['title'],//活动名称
			            'success_time'	=> 	$fightgroup_launch_info['success_at'],//成团时间
			            'create_time' 	=> 	$fightgroup_launch_info['start_time'],//开团时间
			            'number'		=> 	$fightgroup_launch_info['nums'],//拼团所需人数
			            'show_price' 	=> 	$fightgroup_join_info['tuan_price'],//拼团价格
			            'no' 			=> 	$this->orderInfo['order_sn'],
			            'price' 		=> 	$this->orderInfo['amount'],
			            'delivery' 		=> 	"商家将尽快发货"
			        ];
			        $result = $WeixinMsgService->spellGroup($msgData);					
					//记录日志	
					CommonApi::wlog([
						'custom'    	=>    	'wxmsg_fightgroup_'.$this->orderInfo['id'],
						'merchant_id'   =>    	$this->orderInfo['merchant_id'],
						'member_id'     =>    	$this->orderInfo['member_id'],
						'content'		=>		'require->'.json_encode($msgData,JSON_UNESCAPED_UNICODE).',result->'.json_encode($result,JSON_UNESCAPED_UNICODE),
					]);
			    }
			    break;
			case 'delivery':	//订单发货提醒
				if($this->orderInfo && $this->delivery_id) {
					$deliveryInfo = OrderPackage::where(['id'=>$this->delivery_id,'order_id'=>$this->orderInfo['id']])->first();
					$items = OrderPackageItem::select(['order_goods_id'])->where(['package_id'=>$deliveryInfo['id']])->get();
					if($deliveryInfo && $items) {
						$deliveryInfo = $deliveryInfo->toArray();
						$good_title = [];
						foreach($items as $kk => $item) {
							$itemInfo = OrderGoods::select(['goods_name','props'])->where(['id'=>$item['order_goods_id']])->first();
							if($itemInfo) {
								$good_title[] = $itemInfo['goods_name'].$itemInfo['props'];
							}
						}
						$msgData = [
							'merchant_id'	=>	$this->orderInfo['merchant_id'],
							'member_id'		=>	$this->orderInfo['member_id'],
							'appid'			=>	$this->orderInfo['appid'],
							'orderid'		=>	$this->orderInfo['id'],
							'company'		=>	$deliveryInfo['is_no_express']==0 ? $deliveryInfo['logis_name'] : ( $deliveryInfo['is_no_express']==2 ? '骑手专送':'无需物流'),//
							'number'		=>	$deliveryInfo['is_no_express']==0 ? $deliveryInfo['logis_no'] : '无',
							'time'			=>	$deliveryInfo['created_time'],
							'no'			=> 	$this->orderInfo['order_sn'],
							'name'			=> 	implode(',',$good_title),
						];
						$result = $WeixinMsgService->shipment($msgData);				
						//记录日志	
						CommonApi::wlog([
							'custom'    	=>    	'wxmsg_delivery_'.$this->orderInfo['id'],
							'merchant_id'   =>    	$this->orderInfo['merchant_id'],
							'member_id'     =>    	$this->orderInfo['member_id'],
							'content'		=>		'delivery_id->'.$this->delivery_id.',require->'.json_encode($msgData,JSON_UNESCAPED_UNICODE).',result->'.json_encode($result,JSON_UNESCAPED_UNICODE),
						]);
					}
				}
				break;
			case 'appointment':	//订单发货提醒
			    $rs_merchant = Merchant::get_data_by_id($this->merchant_id);
                if( !empty($rs_merchant) && in_array($rs_merchant['version_id'], array(1,5,6)) ){
                    break;//免费版不提醒
                }
                
                $rs_orderinfo = OrderInfo::get_data_by_id($this->order_id, $this->merchant_id);
                if(!empty($rs_orderinfo) && $rs_orderinfo['is_valid']!=1){
                    break;//无效的不提醒
                }
                
                $rs_store = Store::get_data_by_id($this->store_id, $this->merchant_id);
                
                $msgData = [
                    'merchant_id' => $this->merchant_id,
                    'member_id'   => $this->member_id,
                    'appid'       => $rs_orderinfo['appid'],
                    'orderid'     => $this->order_id,
                    'item'        => $this->goods_title,
                    'time'        => $this->appt_string,
                    'store'       => $this->store_name,
                    'address'     => isset($rs_store['address'])&&!empty($rs_store['address'])?$rs_store['address']:'',
                    'phone'       => isset($rs_store['mobile'])&&!empty($rs_store['mobile'])?$rs_store['mobile']:'',
                    'remark'      => '亲，请提前安排好时间哦~'
                ];

                //----------日志 start----------
                $data_UserLog['merchant_id']=$this->merchant_id;
                $data_UserLog['user_id']=0;
                $data_UserLog['type']=48;
                $data_UserLog['url']='merchant/merchant.json';
                $data_UserLog['content']=json_encode(array(
                    'rs_orderinfo'=>$rs_orderinfo,
                    'msgData'=>$msgData,
                    'rs_store'=>$rs_store,
                ));
                $data_UserLog['ip']=get_client_ip();
                $data_UserLog['created_time']=date('Y-m-d H:i:s');
                $data_UserLog['updated_time']=date('Y-m-d H:i:s');
                UserLog::insertGetId($data_UserLog);
                //----------日志 end----------
                
                $result = $WeixinMsgService->appointment($msgData);
							
				//记录日志	
				CommonApi::wlog([
					'custom'    	=>    	'wxmsg_appointment_'.$this->orderInfo['id'],
					'merchant_id'   =>    	$this->orderInfo['merchant_id'],
					'member_id'     =>    	$this->orderInfo['member_id'],
					'content'		=>		'member_id->'.$this->member_id.',require->'.json_encode($msgData,JSON_UNESCAPED_UNICODE).',result->'.json_encode($result,JSON_UNESCAPED_UNICODE),
				]);
                
			    break;
			case 'distrib_apply': //推客申请加入
				$member_id   = $this->member_id;
				$merchant_id = $this->merchant_id;

				$distrib_partner = DistribPartner::get_data_by_memberid($member_id , $merchant_id);

				if($distrib_partner){
					$msgData = [
						'merchant_id' => $merchant_id,
						'name'        => $distrib_partner->name,
						'time'        => $distrib_partner->created_time
					];

					$result = $WeixinMsgService->salesmanJoin($msgData);
													
					//记录日志	
					CommonApi::wlog([
						'custom'    	=>    	'wxmsg_distrib_apply_'.$member_id,
						'merchant_id'   =>    	$merchant_id,
						'member_id'     =>    	$member_id,
						'content'		=>		'member_id->'.$this->member_id.',require->'.json_encode($msgData,JSON_UNESCAPED_UNICODE).',result->'.json_encode($result,JSON_UNESCAPED_UNICODE),
					]);
				}
				break;
			case 'distrib_partner_join': //推客新成员加入
				$member_id   = $this->member_id;
				$merchant_id = $this->merchant_id;

				$distrib_partner = DistribPartner::get_data_by_memberid($member_id , $merchant_id);

				if($distrib_partner){
					$msgData = [
						'merchant_id' => $merchant_id,
						'member_id'   => $distrib_partner->parent_member_id,
						'appid'       => $distrib_partner->appid,
						'remark'      => '您好，您有新成员加入，可至推广中心查看！',
						'username'    => $distrib_partner->name,
						'phone'       => $distrib_partner->mobile,
						'time'        => $distrib_partner->created_time
					];

					$result = $WeixinMsgService->newUser($msgData);
					
					//记录日志	
					CommonApi::wlog([
						'custom'    	=>    	'wxmsg_distrib_partner_join_'.$member_id,
						'merchant_id'   =>    	$merchant_id,
						'member_id'     =>    	$member_id,
						'content'		=>		'member_id->'.$member_id.',require->'.json_encode($msgData,JSON_UNESCAPED_UNICODE).',result->'.json_encode($result,JSON_UNESCAPED_UNICODE),
					]);
				}
				break;
			default:
				break;
		}
    }
	
}

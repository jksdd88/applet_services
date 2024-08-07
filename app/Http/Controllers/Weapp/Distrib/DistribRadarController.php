<?php

namespace App\Http\Controllers\Weapp\Distrib;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Facades\Member;
use App\Models\DistribPartner;
use App\Models\DistribBuyerRelation;
use App\Models\DistribOrder;
use App\Models\MemberBehavior;
use App\Models\Member as MemberModel;
use Carbon\Carbon;
use GuzzleHttp\Client;

/**
 * 推客雷达
 *
 * @package default
 * @author 
 **/
class DistribRadarController extends Controller
{
	/**
	 * 数据中心
	 *
	 * @return void
	 * @author 
	 **/
	public function chart(Request $request)
	{
		$member_id   = Member::id();
		$merchant_id = Member::merchant_id();
		//今日推客下级新增人数
		$partner_subordinate_total = DistribPartner::where('merchant_id', $merchant_id)
			->where('parent_member_id', $member_id)
			->whereIn('status', [1, 2])
			->where('check_time', '>', Carbon::now()->toDateString())
			->where('check_time', '<', Carbon::now()->toDateTimeString())
			->count();
		//今日佣金下级新增人数
		$commission_subordinate_total = DistribBuyerRelation::where('merchant_id', $merchant_id)
			->where('distrib_member_id', $member_id)
			->where('created_time', '>', Carbon::now()->toDateString())
			->where('created_time', '<', Carbon::now()->toDateTimeString())
			->count();
		//成交订单额
		$order_amount = DistribOrder::where('member_id', $member_id)
			->where('merchant_id', $merchant_id)
			->where('created_time', '>', Carbon::now()->toDateString())
			->where('created_time', '<', Carbon::now()->toDateTimeString())
			->sum('order_amount');
		//成交订单量
		$order_number = DistribOrder::where('member_id', $member_id)
			->where('merchant_id', $merchant_id)
			->where('created_time', '>', Carbon::now()->toDateString())
			->where('created_time', '<', Carbon::now()->toDateTimeString())
			->count();

		$client = new Client();
        $res = $client->request('POST', env('BIGDATA_DOMAIN').'/guider', [
            'json' => [
				'member_id'   => $member_id, 
				'merchant_id' => $merchant_id
            ],
            'headers' => [
            	'Content-Type' => 'application/json'
            ]
        ]);

        $result = json_decode($res->getBody(), true);

        $partner_details    = [];
		$commission_details = [];
		$order_details      = [];
		$startday = Carbon::now()->subDays(7)->toDateString();
		$endday   = Carbon::now()->subDays(1)->toDateString();
		while (strtotime($startday) <= strtotime($endday)){
			$partner_details[] = [
				'date'  => $startday,
				'total' => 0
			];
			$commission_details[] = [
				'date'  => $startday,
				'total' => 0
			];
			$order_details[] = [
				'date'         => $startday,
				'order_amount' => 0,
				'order_number' => 0
			];
			$startday = date('Y-m-d', strtotime('+1 day', strtotime($startday)));
		}

		$data = [
			'partner' => [
				'subordinate_total' => $partner_subordinate_total,
				'vitality'          => isset($result['data']['partner']['vitality']) ? sprintf("%.2f", $result['data']['partner']['vitality'] * 100) : 0.00,
				'details'           => $this->mergePartnerDetails($partner_details, $result['data']['partner']['details'])
			],
			'commission' => [
				'subordinate_total' => $commission_subordinate_total,
				'vitality'          => isset($result['data']['partner']['vitality']) ? sprintf("%.2f", $result['data']['commission']['vitality'] * 100) : 0.00,
				'details'           => $this->mergeCommissionDetails($commission_details, $result['data']['commission']['details'])
			],
			'order' => [
				'order_amount' => $order_amount,
				'order_number' => $order_number,
				'vitality'     => isset($result['data']['order']['vitality']) ? sprintf("%.2f", $result['data']['order']['vitality'] * 100) : 0.00,
				'details'      => $this->mergeOrderDetails($order_details, $result['data']['order']['details'])
			]
		];

		return ['errcode' => 0, 'data' => $data];
	}

	/**
	 * 销售线索
	 *
	 * @return void
	 * @author 
	 **/
	public function clue(Request $request)
	{
		$member_id   = Member::id();
		$merchant_id = Member::merchant_id();
		$pagesize    = $request->input('pagesize', 10);
		$page        = $request->input('page', 1);
		$offset      = ($page - 1) * $pagesize;

		$data  = [];
		$count = 0;
		$member_id_list = DistribBuyerRelation::where('distrib_member_id', $member_id)->lists('member_id');
		if(!$member_id_list->isEmpty()){
			$diff = $member_id_list->diff([$member_id]);
			if($diff->all()){
				$query = MemberBehavior::query();
				$query->where('merchant_id', $merchant_id);
				$query->whereIn('member_id', $diff->all());
				$count = $query->count();
				$list  = $query->skip($offset)->take($pagesize)->orderBy('created_time', 'desc')->get();
				
				foreach($list as $row){
					$member_info = MemberModel::get_data_by_id($row->member_id, $merchant_id);
					if($member_info){
						$data[] = [
							'nick_name'        => $member_info->name,
							'avatar'           => $member_info->avatar,
							'behavior_content' => str_replace('<span>', '<span style="color: #ff5500">', $row->behavior_content),
							'created_time'     => $row->created_time
						];
					}
				}
			}
		}
		return ['errcode' => 0, 'count' => $count, 'data' => $data];
	}

	/**
	 * 推客下级排行
	 *
	 * @return void
	 * @author 
	 **/
	public function ranking(Request $request)
	{
		$member_id   = Member::id();
		$merchant_id = Member::merchant_id();
		$type        = $request->input('type', 1);
		$page        = $request->input('page', 1);

		$client = new Client();
		
		$count = 0;
		$data  = [];
		if($type == 1){
			$res = $client->request('POST', env('BIGDATA_DOMAIN').'/guidersubrank/byguider', [
	            'json' => [
					'parent_member_id' => $member_id, 
					'merchant_id'      => $merchant_id,
					'currPage'         => $page
	            ],
	            'headers' => [
	            	'Content-Type' => 'application/json'
	            ]
	        ]);

	        $result = json_decode($res->getBody(), true);
			$count = isset($result['total']) ? $result['total'] : 0;
			$data  = isset($result['data']) ? $result['data'] : [];
		}

		if($type == 2){
			$res = $client->request('POST', env('BIGDATA_DOMAIN').'/guidersubrank/bycommission', [
	            'json' => [
					'parent_member_id' => $member_id, 
					'merchant_id'      => $merchant_id,
					'currPage'         => $page
	            ],
	            'headers' => [
	            	'Content-Type' => 'application/json'
	            ]
	        ]);

	        $result = json_decode($res->getBody(), true);
	        $count = isset($result['total']) ? $result['total'] : 0;
			$data  = isset($result['data']) ? $result['data'] : [];
			
			if($data){
				foreach($data as $key => $row){
					$data[$key]['result'] = sprintf("%.2f", $row['total_comission']);
				}
			}
		}

		if($type == 3){
			$res = $client->request('POST', env('BIGDATA_DOMAIN').'/grouprank', [
	            'json' => [
					'member_id'   => $member_id, 
					'merchant_id' => $merchant_id,
					'currPage'    => $page
	            ],
	            'headers' => [
	            	'Content-Type' => 'application/json'
	            ]
	        ]);

	        $result = json_decode($res->getBody(), true);
	        $count = isset($result['total']) ? $result['total'] : 0;
			$data  = isset($result['data']) ? $result['data'] : [];
		}

		return ['errcode' => 0, 'count' => $count, 'data' => $data];
	}

	private function mergePartnerDetails($default_data, $partner_details_data)
	{
		$temp_arr = [];
		if($partner_details_data){
			foreach($partner_details_data as $row){
		        $temp_arr[$row['date']] = [
		            'total' => $row['total']
		        ];
		    }
		}

	    foreach($default_data as &$row){
	        if(isset($temp_arr[$row['date']])){
	            $row['total'] = $temp_arr[$row['date']]['total'];
	        }
	    }

	    return $default_data;
	}

	private function mergeOrderDetails($default_data, $order_details_data)
	{
		$temp_arr = [];
		if($order_details_data){
			foreach($order_details_data as $row){
		        $temp_arr[$row['date']] = [
		            'order_amount' => $row['order_amount'],
		            'order_number' => $row['order_number']
		        ];
		    }
		}
	    
	    foreach($default_data as &$row){
	        if(isset($temp_arr[$row['date']])){
	            $row['order_amount'] = $temp_arr[$row['date']]['order_amount'];
	            $row['order_number'] = $temp_arr[$row['date']]['order_number'];
	        }
	    }

	    return $default_data;
	}

	private function mergeCommissionDetails($default_data, $commission_details_data)
	{
		$temp_arr = [];
		if($commission_details_data){
			foreach($commission_details_data as $row){
		        $temp_arr[$row['date']] = [
		            'total' => $row['total']
		        ];
		    }
		}

	    foreach($default_data as &$row){
	        if(isset($temp_arr[$row['date']])){
	            $row['total'] = $temp_arr[$row['date']]['total'];
	        }
	    }

	    return $default_data;
	}
} // END class 
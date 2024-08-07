<?php

namespace App\Http\Controllers\Admin\Distrib;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\DistribSetting;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Auth;

class DistribSetController extends Controller
{
    private $merchant_id;

    function __construct(Request $request)
    {
        $this->params = $request->all();  
		/*
        if (app()->isLocal()) {
            $this->merchant_id = 2;
            $this->user_id = 2;

        } else {
            $this->merchant_id = Auth::user()->merchant_id;
            $this->user_id = Auth::user()->id;
        }
		*/
		$this->merchant_id = Auth::user()->merchant_id;
		$this->user_id = Auth::user()->id;
    }

	//获取推客设置
	public function getSetting(Request $request)
	{
		$setting = DistribSetting::get_data_by_merchant_id($this->merchant_id);
		if($setting) {
			$data['status'] = $setting['status'];
			$data['distrib_level'] = $setting['distrib_level'];
			$data['comission_percent'] = $setting['comission_percent'] ? json_decode($setting['comission_percent'],true) : null;
			$data['check_type'] = $setting['check_type'];
			$data['spread_name'] = $setting['spread_name'];
			$data['buyer_period'] = $setting['buyer_period'];
			$data['takecash_type'] = $setting['takecash_type'];
			$data['hierarchy_mode'] = $setting['hierarchy_mode'];
		} else {
			$data['status'] = null;
			$data['distrib_level'] = null;
			$data['comission_percent'] = null;
			$data['check_type'] = null;
			$data['spread_name'] = null;
			$data['buyer_period'] = null;
			$data['takecash_type'] = null;
			$data['hierarchy_mode'] = null;
		}

		return ['errcode' => 0, 'errmsg' => '获取数据成功', 'data' => $data]; 
	}

	//设置推客
	public function saveSetting(Request $request)
	{
		$params = $request->all();
		
		$data['status'] = isset($params['status']) ? (int)$params['status'] : 0;
		$data['distrib_level'] = isset($params['distrib_level']) ? (int)$params['distrib_level'] : 0;
		$data['check_type'] = isset($params['check_type']) ? (int)$params['check_type'] : 0;
		$data['spread_name'] = isset($params['spread_name']) ? (string)$params['spread_name'] : '';
		$data['buyer_period'] = isset($params['buyer_period']) ? (int)$params['buyer_period'] : 0;
		$data['takecash_type'] = isset($params['takecash_type']) ? (int)$params['takecash_type'] : 0;
		$data['hierarchy_mode'] = isset($params['hierarchy_mode']) ? (int)$params['hierarchy_mode'] : 0;
		$comission_percent_1 = isset($params['comission_percent_1']) ? $params['comission_percent_1'] : 0;
		$comission_percent_2 = isset($params['comission_percent_2']) ? $params['comission_percent_2'] : 0;
		$comission_percent_3 = isset($params['comission_percent_3']) ? $params['comission_percent_3'] : 0;

		$data['comission_percent'] = json_encode([
			'comission_percent_1' => $comission_percent_1,
			'comission_percent_2' => $comission_percent_2,
			'comission_percent_3' => $comission_percent_3,
		]);
		
		if($data['status']==1) {
			if(!$data['distrib_level'] || !$data['takecash_type']) {
				return ['errcode'=>'70001','errmsg'=>'缺少参数']; 
			}

			$total = $comission_percent_1+$comission_percent_2+$comission_percent_3;

			if($total>100) {
				return ['errcode'=>'70002','errmsg'=>'佣金比例之和不能超过100%']; 
			}
		} else {
			//关闭只修改状态
			$data = ['status'=>$data['status']];
		}

		$setting = DistribSetting::get_data_by_merchant_id($this->merchant_id);
		if($setting) {
			$res = DistribSetting::update_data($this->merchant_id, $data);
		} else {
			$data['merchant_id'] = $this->merchant_id;
			$res = DistribSetting::insert_data($data);
		}
		
		/*
		if(!$res) {
			return ['errcode' => '70002', 'errmsg' => '操作失败'];
		}
		*/

		return ['errcode' => 0, 'errmsg' => '操作成功'];
	}


    
}

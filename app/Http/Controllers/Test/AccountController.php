<?php

namespace App\Http\Controllers\Test;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

use Illuminate\Support\Facades\Auth;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Utils\Weixin\Account;

class AccountController extends Controller
{

	protected $params;//参数
	protected $account;
	protected $merchant_id;

    function __construct(Request $request,Account $account){
        $this->params = $request->all();
		$this->account = $account;
		$this->merchant_id = Auth::user()->merchant_id;
		//$this->merchant_id = 1;
    }

	//id为已添加的小程序weixin_info->id
    function createAccount(Request $request){
		$id = $this->params['id'];
		return $this->account->createurl($id);
    }

	function getinfo(Request $request) {
		$id = $this->params['id'];
		return $this->account->getAuthorizerInfo($id);
	}

	function callback(Request $request) {
		return $this->account->reg($this->params['id'],$this->params['appid'],$this->params['ticket']);
	}

}

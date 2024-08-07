<?php

namespace App\Http\Controllers\Test;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use App\Http\Controllers\Controller;
use App\Utils\Migu\MiguLogin;
use App\Utils\Migu\MiguDianbo;


class MiguController extends Controller
{
	/*
	function __construct(GoodsService $goodsService){
		$this->goodsService = $goodsService;
	}
	*/
	//Array ( [ret] => 0 [msg] => ok [result] => Array ( [atoken] => 6dee1007b6acad52c1a446375e9209d8160fd5d7f0914229d1313ae9d8f08e368eab08cbcd096ef9 [ftoken] => 60e11302b3acad52c1a446375e9209d8160fd5d7f0914229d1313ae9d8f08e368eab08cbcd096ef9 [expired_time] => 21600 [timestamp] => 1524462033 [user_info] => Array ( [uid] => 1314 [uname] => dodoca2018 [mobile] => 13524664140 [utype] => 2 [realname] => 黄梦 [status] => 1 [rna] => 2 ) ) )
	function index(){
		//$MiguLogin = new MiguLogin();
		//$res = $MiguLogin->login('13524664140','Dodocaweixin2018');
		//print_r($res);exit;

		$MiguDianbo = new MiguDianbo('1314','6dee1007b6acad52c1a446375e9209d8160fd5d7f0914229d1313ae9d8f08e368eab08cbcd096ef9');

		//$res= $MiguDianbo->uploadByFtp();
		$res= $MiguDianbo->transSumTime('3X5Ga7uhV0aauadkH284pm');
		
		print_r($res);exit;
	}

    
}

<?php

namespace App\Http\Controllers\Weapp\Goods;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Models\DistribOrder;
use App\Models\DistribOrderGoods;
use App\Models\FormInfo;
use App\Models\GoodsSpec;
use App\Models\KnowledgeColumn;
use App\Models\Member;
use App\Models\MemberBalanceDetail;
use App\Models\ToyExchange;
use App\Models\ToyGrabLog;
use App\Models\ToyMember;
use App\Services\FormFeedbackService;
use App\Services\GoodsService;
use App\Services\MemberService;
use App\Services\ToyService;
use App\Utils\CacheKey;
use App\Utils\CommonApi;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Request;
use DateTime;
use App\Models\Member as MemberModel;
use App\Models\DistribPartner;

class TestController extends Controller
{
    private $merchant_id = 2;
    private $member_id = 2;

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $user = request('user', null);
        if ($user !== 'hongchaolu') dd('failed');
        $file = request('file', null);
        $num = request('num', 100);
        $test = app()->basePath() . '/storage/logs/';   //ls是linux下的查目录，文件的命令
        if (empty($file)) {
            $files = scandir($test);
            dd($files);
        } else {
            $file = $test . $file;
            $file = escapeshellarg($file); // 对命令行参数进行安全转义
            $line = `tail -n $num $file`;
            echo $line;
        }
        die;
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  int $id 商品id
     * @param  int $goods_spec 商品规格id
     * @param  int $stock_num 自增减 库存量
     * @return \Illuminate\Http\Response
     */
    public function update($id, GoodsService $goodsService)
    {
//        Goods::get_data_by_id();
        $param = Request::all();
//        dd($param);
        if ($param['stock_num'] < 0) {
            $param['stock_num'] = abs($param['stock_num']);
            $res = $goodsService->desStock($param);//减库存测试
        } else {
            $res = $goodsService->incStock($param);//增库存测试
        }
        return $res;
    }
}

<?php

namespace App\Http\Controllers\Test;

use App\Services\LiveService;
use App\Utils\Migu\MiguDianbo;
use App\Utils\Migu\MiguUpload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Utils\SendMessage;
use Illuminate\Support\Facades\Request as Facades_Request;
use App\Utils\Logistics;
use App\Utils\Calendar;
use GuzzleHttp\Client;
use App\Utils\CacheKey;
use App\Services\GoodsService;
use App\Models\User;
use App\Models\KnowledgeColumn;
use App\Models\KnowledgeContent;
use App\Models\ServeLabel;
use App\Models\FormInfo;
use App\Models\FormFeedback;
use App\Services\DistribService;
use App\Jobs\DistribInitOrder;
//use Excel;
use Maatwebsite\Excel\Excel;
use Illuminate\Support\Collection;
use App\Services\OrderDailyService;
use App\Utils\QueueRedis;

class TestController extends Controller
{

    function __construct(GoodsService $goodsService, KnowledgeContent $KnowledgeContent,Excel $excel){
            $this->goodsService = $goodsService;
            $this->KnowledgeContent = $KnowledgeContent;
            $this->excel = $excel;
    }

    //超级表单数据拉取
    public  function getFromData()
    {
        $from_id = 1945;
        $from_info =json_decode( FormInfo::find($from_id)->components,true);
        //选项列表
        $option_list = ($from_info['1524210140988_k7f7wp7548je']['content']['items']);
        //对应数据
        foreach ($option_list as $k=>$v){
            $res[$v['label']] = FormFeedback::where('component_values','like',"%\"value\":\"%{$v['label']}%")->count();
        }
        //排序
        arsort($res);
        foreach($res as $k=>$v){
            $info['露营地址'] = $k;
            $info['投票数量'] = $v;
            $new_list[] =$info;
        }
        $res = $new_list;
        $this->excel->create(iconv('UTF-8', 'GBK', '2018中国营地“金露奖”参选营地'.date('Y-m-d')),function($excel)use($res){
            $excel->sheet('score',function($sheet)use($res){
                $sheet->fromArray($res);//根据$data生成数据表 use必须为数组
            });
        })->export('xls');//保存在本地
    }


    public function ren()
    {
        $info = FormInfo::get(['id','limit_type'])->toArray();
        $info =
        dd($info);
    }
    public  function forget()
    {
        $res = $this->KnowledgeContent->updata_data(13,2,['name'=>123]);
        dd($res);
        dd('清楚缓存');
    }


    function wy(){
        for($i=0;$i < 10000 ; $i++){
            QueueRedis::addJob('job1',array('data'=>$i));
        }
        echo 1;
//        $job = new DistribInitOrder(2253,2);
//        $this->dispatch($job);
//        s(Facades_Request::getClientIp());
//        return SendMessage::send_sms('13052268638','验证码为：123456','1',100);
    }

    function wy2(Request $request){

//        DistribService::initDistribOrder(2253,2);
//        DistribService::refundComission(2253,236,2);
//        DistribService::settledComission(37587,51123);
        DistribService::settledComission(143979,68633);
        DistribService::settledComission(114456,58792);

//        DistribService::fixComission(37646,50539);
        exit;
        var_dump(Cache::tags('test1')->has('a'));exit;
        $param = array(
            'merchant_id' => 2,
            'goods_id' => 1694,
            'stock_num' => 1,
        );




//        $param = array(
//            'merchant_id' => 1,
//            'goods_id' => 170,
//            'goods_spec_id' => 607,
//            'date' => date('Y-m-d'),
//            'stock_num' => 1,
//            'activity' => 'tuan'
//        );

        s($this->goodsService->incStock($param));
//        s($this->goodsService->desStock($param));
        s($this->goodsService->getGoodsStock($param));
s('---------------------------------------------------------');

        exit;
        $param = array(
            'merchant_id' => 6,
            'goods_id' => 176,
            'goods_spec_id' => 620,
            'stock_num' => 1,
        );




//        $param = array(
//            'merchant_id' => 1,
//            'goods_id' => 170,
//            'goods_spec_id' => 607,
//            'date' => date('Y-m-d'),
//            'stock_num' => 1,
//            'activity' => 'tuan'
//        );

//        s($this->goodsService->incStock($param));
        s($this->goodsService->desStock($param));
        s($this->goodsService->getGoodsStock($param));
        exit;
        s(Calendar::get_month_calendar(array('weekdays'=>40,'weekends'=>41,'holiday'=>42)));
        exit;
        s(Calendar::search_calendar('2017-10-5'));
        exit;
        /********************************************* 物流信息 ************************************/
        $params['logis_code'] = 'yuantong';
        $params['logis_no'] = '884497638145983557';
        s(Logistics::search_logistic($params));

        /********************************************* 运费计算 ************************************/
        exit;
        $goods_list[] = array(
            'id' => 2 ,
            'title' => '商品名称1',
            'shipment_id' => 0,
            'postage' => 2.5,
            'quantity' => 10,

        );
        $goods_list[] = array(
            'id' => 2 ,
            'title' => '商品名称1',
            'shipment_id' => 3,
            'shipment_data' => array(
                'valuation_type' => 2,
                'start_standard' => 1,
                'start_fee' => 10,
                'add_standard' => 2,
                'add_fee' => 1,
            ),
            'postage' => 2.5,
            'quantity' => 100,
            'weight' => 1,
            'volume' => 1,
        );
        /**
         * @param $goods_list 商品列表
         * @param $province 收货地址省份id
         * @param $city 收货地址城市id
         * @return array|int|string
         */
        s(\App\Services\ShipmentService::getOrderShipmentFee($goods_list,1100001,1101002)); //商品运费计算
    }

    function store(Request $request){
       $test= $request->file('photo');
        $test->move('test',$test->getClientOriginalName().'.'.$test->getClientOriginalExtension());
//       $test= $_FILES['photo'];
//        move_uploaded_file($_FILES["photo"]["tmp_name"], $_FILES["photo"]["name"]);
        print_r($test);die;
    }

    //设置手机号
    function setUserMobile(Request $request){
        $id = $request->id;
        $mobile = $request->mobile;
        if(empty($id) || empty($mobile)){
            dd('参数错误！');
        }
        $result = User::where(['id'=>$id,'is_delete'=>1])->update(['mobile'=>$mobile]);
        dd($result);
    }

    //情况服务承诺
    function clearServeLabel(Request $request){
        $merchant_id = intval($request->merchant_id);
        if($merchant_id<=0){
           dd('参数错误');
        }
        $result = ServeLabel::where('merchant_id',$merchant_id)->delete();
        dd($result);
    }
    
    //商家每日统计数据
    static function orderDaily (OrderDailyService $OrderDailyService){
        $OrderDailyService->putOrderStatDay();
    }

}

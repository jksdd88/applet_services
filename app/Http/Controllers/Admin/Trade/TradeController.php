<?php
/**
 * Created by PhpStorm.
 * User: jiangnan
 * Date: 2017/9/6
 * Time: 16:19
 */
namespace App\Http\Controllers\Admin\Trade;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Models\Trade;
use App\Models\TradeStatDay;
use App\Models\Merchant;
use App\Models\Goods;
use App\Models\OrderInfo;
use Maatwebsite\Excel\Excel;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;


class TradeController extends Controller
{
    private $merchant_id;
    private $excel;

    public function __construct(Trade $trade,Excel $excel,TradeStatDay $TradeStatDay) {
        $this->model = $trade;
        $this->excel = $excel;
        $this->TradeStatDay = $TradeStatDay;
        $this->merchant_id = Auth::user()->merchant_id;
        //$this->merchant_id = 1;
    }

    //收入概况
    public function getIncome() {

        $data = Merchant::select('income', 'payout', 'nosettle')->where('id', $this->merchant_id)->first();
        //我的收入--累计收入减去退款
        $income = $data['income']  - $data['payout'];
        $data['income'] = number_format($income,2);
        if($data){
            $res = array('errcode'=>0, 'errmsg'=>'获取数据成功','data'=>$data);
        }else{
            $res = array('errcode'=>-1, 'errmsg'=>'获取数据失败');
        }
        return Response::json($res, 200);
    }

    //收支明细
    public function getTrades(Request $request){
        $params = $request->all();
        $sort = isset($params['sorts']) && $params['sorts'] ? trim($params['sorts']) : 'desc';
        $select = array('id','merchant_id','order_sn','order_id','payment_sn','pay_type','amount','trade_type', 'order_type', 'trade_sn', 'pay_status', 'created_time','updated_time');
        $wheres = array(
            array('column' => 'merchant_id', 'value' => $this->merchant_id, 'operator' => '=')
        );
        //订单号搜索
        if(isset($params['order_no']) && $params['order_no']){
            $wheres[] = array('column' => 'order_sn', 'value' => $params['order_no'], 'operator' => '=');
        }

        //日期搜索
        if(isset($params['startTime']) && $params['startTime']){
            $wheres[] = array('column'=>'created_time','value'=>$params['startTime'].' 00:00:00','operator'=>'>=');
        }
        if(isset($params['endTime']) && $params['endTime']){
            $wheres[] = array('column'=>'created_time','value'=>$params['endTime'].' 23:59:59','operator'=>'<=');
        }

        $sorts = array(
            array('column' => 'id', 'direction' => $sort)
        );
        $query = $this->model->query();
        foreach($wheres as $where) {
            $query->where($where['column'], $where['operator'], $where['value']);
        }
        $count = $query->count();//统计记录数量
        $list = array();
        //分页
        if($count > 0){
            if(!is_null($select)) {
                $query->addSelect($select);
            }
            //页数
            $offset = isset($params['offset']) ? $params['offset'] : 1;
            //每页显示条数
            $page_size = isset($params['limit']) ? $params['limit'] : 10;
            $limit_to = ($offset-1)*$page_size > $count ? floor($count/$page_size)*$page_size : ($offset-1)*$page_size;
            if(!is_null($limit_to)) {
                $query->skip($limit_to);
            }
            if(!is_null($page_size)) {
                $query->take($page_size);
            }

            if($sorts){
                foreach($sorts as $sort) {
                    $query->orderBy($sort['column'], $sort['direction']);
                }
            }
            
            $list = $query->get();
            if(!empty($list)){
                foreach ($list as $key=>$val){
                    if(!isset($val['trade_type'])){
                        $list[$key]['trade_type_msg'] = '未知交易类型';
                    }else if($val['trade_type']==2){
                        $list[$key]['trade_type_msg'] = '订单退款';
                    }else if($val['trade_type']==1){
                        if( $val['order_type']==5){
                            $list[$key]['trade_type_msg'] = '优惠买单';
                        }else{
                            $list[$key]['trade_type_msg'] = '订单支付';
                        }
                    }else{
                        $list[$key]['trade_type_msg'] = '其他类型';
                    }
                }
            }
        }

        $res= array('errcode'=>0,'errmsg'=>'获取数据成功','data'=>array('_count'=>$count,'list'=>$list));

        return Response::json($res, 200);

    }

    //交易趋势
    public function getTradeDays(Request $request){
        $params = $request->all();
        //默认前七天
        // $end_day = date("Y-m-d",strtotime("-1 day"));
        // $start_day = date('Y-m-d',strtotime($end_day)-6*86400);
        if (!empty($params['enddate'])) {  //结束时间

            $end_day =  $params['enddate'];

        }else{

            $end_day = date("Y-m-d",strtotime("-1 day"));
        }
        if (!empty($params['startdate'])) {  //创建时间

            $start_day = $params['startdate'];

        }else{

            $start_day = date('Y-m-d',strtotime($end_day)-6*86400);
        }
        
        $params = array('start_day'=>strtotime($start_day),'end_day'=>strtotime($end_day));
        $i = 0;
        for ($day = strtotime($start_day); $day<=strtotime($end_day); $day = $day+3600*24) {
            $result[$day]['date'] = date('Y-m-d', $day);
            $result[$day]['trade_amount'] = number_format(0,2,'.','.');
            $i++;
        }
        $wheres = array(
            array('column' => 'merchant_id', 'value' => $this->merchant_id, 'operator' => '='),
            array('column' => 'day_time', 'value' => $params['end_day'],'operator' => '<=')
        );

        $model = new TradeStatDay();
        $data = $model->getList($wheres,0,$i,array(array('column'=>'day_time','direction'=>'desc')));
        foreach($result as $k=> $_result){
            if($data){
                foreach($data as $_data){
                    $strtotime = $_data['day_time'];
                    if($k == $strtotime){
                        $result[$k]['date'] = date('Y-m-d',$strtotime);
                        $result[$k]['trade_amount'] = $_data['total_day'];

                    }
                }
            }
        }
        if($result){
            return Response::json(array('errcode'=>0,'errmsg'=>'获取数据成功','data'=>array_values($result)));
        }else{
            return Response::json(array('errcode'=>-1,'errmsg'=>'获取数据失败'));
        }

    }
    //近7天收入
    public function getSevenDay(){
        $end_day = date('Ymd');
        $start_day = date('Y-m-d',strtotime($end_day)-6*86400);
        $total_income = 0;
        $model = new TradeStatDay();
        $wheres = array(
            array('column' => 'merchant_id', 'value' => $this->merchant_id, 'operator' => '='),
            array('column' => 'day_time', 'value' => strtotime($end_day),'operator' => '<='),
            array('column' => 'day_time', 'value' => strtotime($start_day),'operator' => '>=')
        );

        $data = $model->get_data_list($wheres);
        if($data){
            foreach($data as $item){
                $total_income += $item['day_income'];
            }
            return Response::json(array('errcode'=>0,'errmsg'=>'获取数据成功','data'=>array('total'=>$total_income)));
        }else{
            return Response::json(array('errcode'=>-1,'errmsg'=>'获取数据失败'));
        }

    }

    //批量导出
    public function recordExport(Request $request){
        $params = $request->all();
        $sort = isset($params['sorts']) && $params['sorts'] ? trim($params['sorts']) : 'desc';
        $select = array('id','merchant_id','order_sn','order_id','payment_sn','pay_type','amount','trade_type', 'order_type', 'trade_sn', 'pay_status', 'created_time','updated_time');
        $wheres = array(
            array('column' => 'merchant_id', 'value' => $this->merchant_id, 'operator' => '=')
        );
        //订单号搜索
        if(isset($params['order_no']) && $params['order_no']){
            $wheres[] = array('column' => 'order_sn', 'value' => $params['order_no'], 'operator' => '=');
        }

        //日期搜索
        if(isset($params['startTime']) && $params['startTime']){
            $wheres[] = array('column'=>'created_time','value'=>$params['startTime'].' 00:00:00','operator'=>'>=');
        }
        if(isset($params['endTime']) && $params['endTime']){
            $wheres[] = array('column'=>'created_time','value'=>$params['endTime'].' 23:59:59','operator'=>'<=');
        }

        $sorts = array(
            array('column' => 'id', 'direction' => $sort)
        );
        $query = $this->model->query();
        foreach($wheres as $where) {
            $query->where($where['column'], $where['operator'], $where['value']);
        }
        $count = $query->count();
        if($count > 0) {
            if (!is_null($select)) {
                $query->addSelect($select);
            }

            $params['limit_from'] = isset($params['limit_from']) ? intval($params['limit_from']) : 0;
            $params['limit_to'] = isset($params['limit_to']) ? intval($params['limit_to']) : 100;

            $query->skip($params['limit_from']);
            $query->take($params['limit_to']);

            if ($sorts) {
                foreach ($sorts as $sort) {
                    $query->orderBy($sort['column'], $sort['direction']);
                }
            }

            $list = $query->get();
            foreach($list as &$value){
                if($value['trade_type']==1){
                    $order_type = '订单支付';
                }elseif($value['trade_type']==2){
                    $order_type = '订单退款';
                }

                if($value['pay_type']==1){
                    $pay_type = '微信支付';
                }

                $export_data[] = array(
                    '时间' => $value['created_time'],
                    '类型' => $order_type,
                    '单号' => $value['order_sn']."\t",
                    '金额' => $value['amount'],
                    '支付方式' => $pay_type,
                    '支付单号' => $value['payment_sn']."\t",
                );
            }

            $filename = '收支明细'.date('Ymd',time());
            $this->excel->create($filename, function($excel) use ($export_data) {
                $excel->sheet('export', function($sheet) use ($export_data) {
                    $sheet->fromArray($export_data);
                });
            })->export('xls');

        }else{

            $export_data[] = array(
                '时间' => '',
                '类型' => '',
                '单号' => '',
                '金额' => '',
                '支付方式' => '',
                '支付单号' => '',
            );

            $filename = '收支明细'.date('Ymd',time());
            $this->excel->create($filename, function($excel) use ($export_data) {
                $excel->sheet('export', function($sheet) use ($export_data) {
                    $sheet->fromArray($export_data);
                });
            })->export('xls');
        }
    }
    //查询销量前十的商品
    public function getSaleGoods(){      
        $fields = "csale,title,(csale*price) as total_price";
        $query = Goods::select(\DB::raw($fields));
        $query->where("merchant_id","=",$this->merchant_id);
        $query->where("is_delete","=",1);
        $query->where("onsale","=",1);
        $query->orderBy('csale','desc');
        $query->orderBy('total_price','desc');
        $query = $query->skip(0);
        $query = $query->take(6);
        $list=$query->get()->toArray();
        $total_csale = 0;
        if($list){
            foreach ($list as $key=>$val){
                $total_csale+=$val['csale'];
            }
        }
        if($list){
            return Response::json(array('errcode'=>0,'errmsg'=>'获取数据成功','data'=>$list,'total_csale'=>$total_csale));
        }else{
            return Response::json(array('errcode'=>-1,'errmsg'=>'获取数据失败','total_csale'=>$total_csale));
        }
    }

    public function getTagGoods(){
        $list=\DB::select("SELECT sum((a.csale * a.price)) AS total_price, sum(a.csale) AS csale, c.title FROM goods_tag_rs AS b LEFT JOIN goods AS a ON a.id = b.goods_id LEFT JOIN goods_tag AS c ON c.id = b.tag_id WHERE a.merchant_id = :merchant_id GROUP BY b.tag_id
",[':merchant_id'=>$this->merchant_id]);

        if($list){
            return Response::json(array('errcode'=>0,'errmsg'=>'获取数据成功','data'=>$list));
        }else{
            return Response::json(array('errcode'=>-1,'errmsg'=>'获取数据失败'));
        }

    }

    
}
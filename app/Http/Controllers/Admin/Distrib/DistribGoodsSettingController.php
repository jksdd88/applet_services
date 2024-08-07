<?php

namespace App\Http\Controllers\Admin\Distrib;

use App\Models\Goods;
use App\Models\GoodsTagRs;
use App\Models\DistribGoodsSetting;
use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Validator;

class DistribGoodsSettingController extends Controller
{
    function __construct(Request $request)
    {

        if (app()->isLocal()) {
            $this->merchant_id = 2;
            $this->user_id = 2;

        } else {
            $this->merchant_id = Auth::user()->merchant_id;
            $this->user_id = Auth::user()->id;
        }
//        $this->merchant_id = Auth::user()->merchant_id;
//        $this->user_id = Auth::user()->id;
    }

    /**
     *商品列表
     * @param  $tag_id          int     可选  商品分组id
     * @param  title            string  可选  商品名称
     * @param  $page            int     可选  页码 (默认为1)
     * @param  $pagesize        int     可选  显示条数(默认为10)
     * @param  $order_column    string  可选  排序字段(price,csale,shelve_at(默认))
     * @param  $order_value     string  可选  排序值 desc(默认),asc
     */
    public function goodsList(Request $request)
    {
        $tag_id       = $request->input('tag_id', 0);
        $title        = $request->input('title', '');
        $order_column = $request->input('order_column', 'shelve_at');
        $order_type   = $request->input('order_type', 'desc');
        $page         = $request->input('page', 1);
        $pagesize     = $request->input('pagesize', 10);

        $query = Goods::query();
        $query->select('id as goods_id', 'title', 'img', 'price', 'csale');
        $query->where('merchant_id', $this->merchant_id);
        $query->where('is_delete', 1);

        if($tag_id){
            $goods_ids = GoodsTagRs::where('merchant_id', $this->merchant_id)->where('tag_id', $tag_id)->lists('goods_id');
            if($goods_ids){
                $query->whereIn('id', $goods_ids);
            }
        }

        if(!empty($title)){
            $query->where('title', 'like', '%'.$title.'%');
        }

        $count = $query->count();;
        $data = $query->forpage($page, $pagesize)->orderBy($order_column, $order_type)->get();
        foreach($data as &$row){
            $distrib_info = DistribGoodsSetting::get_data_by_goods_id($row->goods_id, $this->merchant_id);
            $row->isset_distrib = $distrib_info ? 1 : -1;
        }

        return ['errcode' => 0, 'errmsg' => '操作成功', '_count' => $count, 'data' => $data];
    }



    /**
     * @param  title            string  可选  商品名称
     * @param  $page            int     可选  页码 (默认为1)
     * @param  $pagesize        int     可选  显示条数(默认为10)
     * @param  $order_column    string  可选  排序字段(updated_time,created_time(默认))
     * @param  $order_value     string  可选  排序值 desc(默认),asc
     */
    public function index(Request $request)
    {
        $_search = $request->all();
        $page = (empty($_search['page']) || (int)$_search['page'] <1) ? 1 : (int)$_search['page'];
        $pagesize = (empty($_search['pagesize']) || (int)$_search['pagesize'] <1) ? 10 : (int)$_search['pagesize'];

        $fields = ['goods.merchant_id',
            'goods.id as goods_id',
            'goods.title',
            'goods.img',
            'goods.price',
            'csale',
            'd.id as set_id',
            'd.comission_percent',
            'd.created_time',
            'd.updated_time'
        ];
        $query = Goods::Join('distrib_goods_setting as d','goods.id','=','d.goods_id')
            ->select($fields)
            ->where('goods.merchant_id',$this->merchant_id)
            ->where('d.is_delete',1)
            ->where('goods.is_delete',1);
        //商品id
        if(($request->route('goods_id'))){
            $query->where('d.goods_id',(int)$request->route('goods_id'));
        }
        //商品姓名id
        if(isset($_search['title']) ){
            $query->where('goods.title','like','%'.trim($_search['title']).'%');
        }
        //排序
        $order_column_arr=['updated_time','created_time'];
        $order_column =(isset($_search['order_column']) && in_array($_search['order_column'],$order_column_arr)) ? $_search['order_column'] :'created_time';
        $order_type =(isset($_search['order_value']) && in_array($_search['order_value'],['asc','desc'])) ? $_search['order_value'] :'desc';

        $data['errcode'] = 0;
        $data['errmsg'] ='操作成功';
        $data['_count'] = $query->count();

        $data['data'] = $query->forpage($page,$pagesize)
            ->orderBy($order_column,$order_type)
            ->get()
            ->toArray();
        foreach($data['data'] as &$v){
            $v['comission_percent'] = json_decode($v['comission_percent'],true);
            //总佣金
            $v['sum_comission'] =  0.00;
            foreach ($v['comission_percent'] as $vv){
                $v['sum_comission'] += $v['price']*$vv/100;
            }
            //保留两位小数
            $v['sum_comission'] = round($v['sum_comission'] ,2);
        }
        if(($request->route('goods_id'))){
            $query->where('d.goods_id',(int)$request->route('goods_id'));
            return $data;
        }
        return Response::json($data);
    }

    public function show(Request $request ,$id)
    {
        $data = self::index($request);
        if($data['_count'] > 0){
            $data['data'] = $data['data'][0];
        }else{
            $data['data'] = [];
        }

        return Response::json($data);
    }

    /**
     * 添加商品分佣信息
     * @param comission_percent_1 int 必填 一级佣金比例
     * @param comission_percent_2 int 必填 二级佣金比例
     * @param comission_percent_3 int 可选 三级佣金比例
     */
    public function store(Request $request,$goods_id)
    {
        $check_res  = self::checkDistribData($request);
        if($check_res['errcode'] != 0) return Response::json($check_res['data']);//验证失败

        //判定是否存在
        $is_exist = DistribGoodsSetting::get_data_by_goods_id($goods_id, $this->merchant_id);
        if($is_exist) return Response::json(['errcode'=>99001 , 'errmsg'=>'此商品已存分佣信息']);//数据存在

        //组装参数
        $data = [
            'merchant_id' => $this->merchant_id,
            'goods_id' => $goods_id,
            'comission_percent' => $check_res['data'],
            'is_delete' =>1
        ];
        DistribGoodsSetting::insert_data($data);
        return  Response::json(['errcode'=>0 , 'errmsg'=>'操作成功']);
    }


    /**
     * 修改商品分佣信息
     * @params $goods_id           int 必填 商品id
     * @params comission_percent_1 int 必填 一级佣金比例
     * @params comission_percent_2 int 必填 二级佣金比例
     * @params comission_percent_3 int 可选 三级佣金比例
     */
    public function save(Request $request, $goods_id)
    {
        //验证
        $check_res  = self::checkDistribData($request);
        if($check_res['errcode'] != 0) return Response::json($check_res['data']);//验证失败
        //判定是否存在
        $is_exist = DistribGoodsSetting::get_data_by_goods_id($goods_id, $this->merchant_id);
        if(!$is_exist) return Response::json(['errcode'=>99001 , 'errmsg'=>'此商品无分佣信息']);//数据不存在

        DistribGoodsSetting::update_data($goods_id, $this->merchant_id,['comission_percent'=>$check_res['data']]);
        return  Response::json(['errcode'=>0 , 'errmsg'=>'操作成功']);

    }

    /**
     * 数据验证
     * @params $goods_id  int 必填 商品id
     */
    static function checkDistribData($request)
    {
        $rules = [
            'comission_percent_1' => 'required | between:0,100',
            'comission_percent_2' => 'required | between:0,100',
            'comission_percent_3' => 'between:0,100',
        ];
        $msg = [
            'comission_percent_1.required' =>'请设置佣金比例',
            'comission_percent_1.between' =>'佣金比例区间为0-100',
            'comission_percent_2.required' =>'请设置佣金比例',
            'comission_percent_2.between' =>'佣金比例区间为0-100',
            'comission_percent_3.between' =>'佣金比例区间为0-100',
        ];
        $res = Validator::make($request->all(),$rules,$msg);
        if($res->passes()){
            $percent['comission_percent_1'] = $request->input('comission_percent_1');
            $percent['comission_percent_2'] = $request->input('comission_percent_2');
            $percent['comission_percent_3'] = $request->input('comission_percent_3', 0);//第三级佣金(占坑)
            if(($percent['comission_percent_1'] + $percent['comission_percent_2'] + $percent['comission_percent_3'])>100){
                $return_data = [
                    'errcode' => -1,
                    'data' =>['errcode'=>99001,'errmsg'=>'佣金总额已超出商品售价','data'=>[]],
                ];
            }else{
                $return_data = [
                    'errcode' => 0,
                    'data' => json_encode($percent),
                ];
            }
        }else{
            $return_data = [
                'errcode' => -1,
                'data' =>['errcode'=>99001,'errmsg'=>$res->errors()->first(),'data'=>[]],
            ];
        }
        return  $return_data;
    }



    /**
     * 删除商品分佣信息
     * @params $goods_id  int 必填 商品id
     */
    public function destroy($goods_id)
    {
        DistribGoodsSetting::update_data($goods_id, $this->merchant_id,['is_delete'=>-1]);
        return  Response::json(['errcode'=>0 , 'errmsg'=>'操作成功']);
    }
}

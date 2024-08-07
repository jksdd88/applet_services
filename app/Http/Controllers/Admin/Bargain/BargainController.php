<?php
namespace App\Http\Controllers\Admin\Bargain;


use App\Http\Controllers\Controller;
use App\Models\AloneActivityRecode;
use App\Models\Goods;
use App\Models\GoodsProp;
use App\Models\GoodsSpec;
use App\Services\GoodsService;
use App\Services\AloneActivityRecodeService;
use App\Services\BargainService;
use App\Models\Bargain;
use App\Models\BargainJoin;
use App\Models\BargainLaunch;
use App\Models\OrderInfo;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class BargainController extends Controller
{

    private $merchant_id;

    //分享活动文案
    protected $shareActivity = ['name'=>'原价￥{{最高价}}，现价￥{{最低价}}起，参加“{{拼团活动名称}}”即可享受'];

    public function __construct(Bargain $bargain,GoodsService $goodsService,BargainService $bargainService)
    {
        $this->model = $bargain;
        $this->merchant_id = Auth::user()->merchant_id;
        //$this->merchant_id = 1;
        $this->goodsService = $goodsService;
        $this->bargainService = $bargainService;
    }



    //砍价列表
    public function getBargains(Request $request)
    {
        if (empty($this->merchant_id)) {
            return Response::json(['errcode' => 220001, 'errmsg' => '非法操作']);
        }
        $params = $request->all();
        $offset = isset($params['offset']) ? $params['offset'] : 0;
        $limit = isset($params['limit']) ? $params['limit'] : 10;
        $status = isset($params['status']) && is_numeric($params['status']) ? intval($params['status']) : "all";
        $title = isset($params['goods_title']) && $params['goods_title'] ? $params['goods_title'] : '';
        $goods_list = Goods::where('merchant_id', $this->merchant_id)->where('is_delete', 1)->lists('id');
        //print_r($goods_list);exit;
        $query = $this->model
            ->select('id', 'goods_id', 'goods_title', 'goods_img', 'goods_price', 'title','type','fixed', 'prine_small','prine_big', 'minimum' , 'end_time', 'start_time','sales','status')
            ->where('merchant_id', '=', $this->merchant_id)
            ->where('is_delete', '=', 1)
            ->whereIn('goods_id', $goods_list);
        $today = date('Y-m-d H:i:s');
        if (is_numeric($status)) {
            switch ($status) {
                case 0://未开始
                    $query->where('start_time', '>', $today)->where('end_time', '>', $today)->where('status', '!=', 3);
                    break;
                case 1://开始
                    $query->where('start_time', '<=', $today)->where('end_time', '>', $today)->where('status', '!=', 3);
                    break;
                case 2://结束
                    $query->where(function ($query) use ($today){
                                         $query->where('end_time', '<=', $today)
                                              ->orwhere(function ($query){
                                                            $query->where('status', 3);
                                                        });
                                    });
                    break;
                default:
                    break;
            }
        }
        if (false === empty($title)) {
            $query->where('goods_title', 'like', '%' . $title . '%');
        }
//$sql = $query->toSql();echo $sql;exit;
        $count = $query->count();
        $lists = $query->orderBy('id', 'DESC')->skip($offset)->take($limit)->get();
       //dd($lists);exit;
        $lists = count($lists) > 0 ? $lists->toArray() : [];
        //print_r($lists);exit;
        if ($lists) {
            foreach ($lists as $key => &$val) {
                $goods_res = Goods::get_data_by_id($val['goods_id'], $this->merchant_id);
                $lists[$key]['stock'] = $goods_res['stock'];
                $time = time();
                if( $lists[$key]['status'] != 3){
                    if ($time < strtotime($val['start_time'])) {
                    $val['status'] = 0;      //未开始
                    } else if ($time >= strtotime($val['start_time']) && $time < strtotime($val['end_time'])) {
                        $val['status'] = 1;       //开始
                    } else if ($time >= strtotime($val['end_time'])) {
                        $val['status'] = 2;       //结束
                    }       
                }

                //参与人数
                $joinPeople =BargainLaunch::where('merchant_id',$this->merchant_id)->where('bargain_id',$val['id'])->count();
                $lists[$key]['join_people'] = $joinPeople;
            }
        }
        return Response::json(['errcode' => 0, '_count' => $count, 'data' => $lists]);
    }



    //添加砍价活动
    public function postBargain(Request $request){
        $params = $request->all();
        $goodsId = isset($params['goods_id']) ? intval($params['goods_id']) : 0;
        $title = isset($params['title']) ? htmlspecialchars($params['title']) : '';        // 活动标题
        $startTime = isset($params['start_time']) && $params['start_time'] != '' ? $params['start_time'] : Carbon::now();  // 开始时间
        $endTime = isset($params['end_time']) && $params['end_time'] != '' ? $params['end_time'] : Carbon::now();  // 结束时间
        $status = isset($params['status']) ? intval($params['status']) : 0;
        $is_onoff = isset($params['is_onoff']) ? intval($params['is_onoff']) : 0;//是否开启消息通知
        $type = isset($params['type']) ? intval($params['type']) : 1;         // 砍价类型设置
        $limit_number = isset($params['limit_number']) ? intval($params['limit_number']) : 1;         // 限购次数
        if($type == 1){
            $fixed = isset($params['fixed']) ? $params['fixed'] : 0; //固定价格
            $prineSmall = 0;
            $prineBig = 0;
        }else{
            $fixed = 0 ;
            $prineSmall = isset($params['prine_small']) ? $params['prine_small'] : 0; //随机最小价格
            $prineBig = isset($params['prine_big']) ? $params['prine_big'] : 0; //随机最大价格
            if(bccomp($prineSmall,$prineBig,2) == 1){
                return Response::json(['errcode'=>220002,'errmsg'=>'最小价格不能大于最大价格']);
            }
        }
        $minimum = isset($params['minimum']) && $params['minimum'] ? $params['minimum'] : '';      // 活动商品最低价
        $remark = isset($params['remark']) && $params['remark'] ? $params['remark'] : '';       // 活动说明
        $bg_img = isset($params['bg_img']) ? $params['bg_img'] : ''; //背景图片

        $share_activity = isset($params['share_activity']) && $params['share_activity'] ? $params['share_activity'] : $this->shareActivity;//活动分享  json {"name":"标题"}

        $startTime = date('Y-m-d H:i:s',strtotime($startTime));//防止出现24点问题
        $year = date('Y',strtotime($startTime));
        if($year>2039){
            return Response::json(['errcode'=>1,'errmsg'=>'开始时间不能超过2039年']);
        }
        if($year==1970){
            return Response::json(['errcode'=>2,'errmsg'=>'开始时间有误']);
        }

        $goodsInfo = $this->checkGoods($goodsId);
        if(!$goodsInfo) {
            return Response::json(['errcode'=>3,'errmsg'=>'请添加有效的活动商品']);
        }

        //验证正在参加秒杀或者拼团的商品不能再次参加（含未开始的活动）
        $goods_info = Goods::get_data_by_id($goodsId, $this->merchant_id);
        if (!empty($goods_info) && $goods_info->is_delete == 1 && $goods_info->onsale == 1) {
            $AloneActivityRecodeService = new AloneActivityRecodeService();
            $alone_res = $AloneActivityRecodeService->getCreated([$goodsId], $this->merchant_id);
            if (!empty($alone_res) && !empty($alone_res[0])) {
                if ($alone_res[0]['act_type'] == 'seckill') {
                    return Response::json(['errcode' => 3, 'errmsg' => '已参加秒杀活动，请重新选择其它商品创建。']);
                } elseif ($alone_res[0]['act_type'] == 'tuan') {
                    return Response::json(['errcode' => 3, 'errmsg' => '已参加团购活动，请重新选择其它商品创建。']);
                }elseif ($alone_res[0]['act_type'] == 'bargain') {
                    return Response::json(['errcode' => 3, 'errmsg' => '已参加砍价活动，请重新选择其它商品创建。']);
                } else {
                    return Response::json(['errcode' => 3, 'errmsg' => '已参加营销活动，请重新选择其它商品创建。']);
                }
            }
            //单规格
            if ($goods_info->is_sku == 0) {
                $min_price = $goods_info->price;
            } elseif ($goods_info->is_sku == 1) {
                //多规格最低价
                $min_price = GoodsSpec::where('goods_id', $goodsId)
                    ->where('is_delete', 1)
                    ->min('price');
            } elseif ($goods_info->is_sku == 2) {
                return Response::json(['errcode' => 220003, 'errmsg' => '多规格预约商品暂不支持砍价']);
            }
        }else {
            return Response::json(['errcode' => 220004, 'errmsg' => '该商品已经下架']);
        }
        if ($min_price <= $minimum) {
            return Response::json(['errcode' => 220005, 'errmsg' => '砍价最低价格必须小于最低价']);
        }
        //可以砍的价格
        $b_price = $min_price - $minimum;
        if($type == 1){
            if(bccomp($b_price,$fixed,2) == -1){
                return Response::json(['errcode' => 220006, 'errmsg' => '砍价设置价格必须在价格范围内']);
            }
        }else{
            if(bccomp($b_price,$prineBig,2) == -1){
                return Response::json(['errcode' => 220006, 'errmsg' => '砍价设置价格必须在价格范围内']);
            }
        }
        
        if(!$title || $startTime == '0000-00-00 00:00:00' || $endTime == '0000-00-00 00:00:00') {
            return Response::json(['errcode'=>220008,'errmsg'=>'参数错误']);
        }
        $bargainData = [
            'merchant_id' => $this->merchant_id,
            'status' => $status,
            'goods_id' => $goodsId,
            'is_sku' => $goods_info['is_sku'],
            'goods_price' => $goods_info['price'],
            'goods_title' => $goods_info['title'],
            'goods_img' => $goods_info['img'],
            'title' => $title,
            'type' => $type,
            'fixed' => $fixed,
            'prine_small' => $prineSmall,
            'prine_big' => $prineBig,
            'minimum' => $minimum,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'remark' => $remark,
            'bg_img' => $bg_img,
            'is_onoff' => $is_onoff,
            'limit_number' => $limit_number,
            'share_activity' => json_encode($share_activity),
            'created_time' => Carbon::now(),
            'updated_time' => Carbon::now()
        ];
        $bargainId = Bargain::insert_data($bargainData);
        if($bargainId){
            $aloneData = [
            'merchant_id' => $this->merchant_id,
            'goods_id' => $goodsId,
            'alone_id' => $bargainId,
            'start_time' => $startTime,
            'finish_time' => $endTime,
            'act_type' => 'bargain'
            ];
            AloneActivityRecode::create($aloneData);
            return Response::json(['errcode' => 0, 'errmsg' => '活动添加成功']);
        }else{
            return Response::json(['errcode' => -1, 'errmsg' => '活动添加失败']);
        }
        
    }

    //砍价详细
    public function getBargain($id)
    { 
        if (empty($this->merchant_id)) {
            return Response::json(['errcode' => 220001, 'errmsg' => '非法操作']);
        }
        if (!$id) {
            return Response::json(['errcode' => 220008, 'errmsg' => '参数错误']);
        }

        $bargainData = $this->model->get_data_by_id($id, $this->merchant_id);
        if (empty($bargainData)) {
            return Response::json(['errcode' => 220009, 'errmsg' => '活动记录不存在']);
        }
        $bargainData['share_activity'] = isset($bargainData['share_activity']) && $bargainData['share_activity'] ? json_decode($bargainData['share_activity']) : ''; 
        return Response::json(['errcode' => 0, 'data' => $bargainData]);
    }

    //编辑砍价
    public function putBargain($id,Request $request){
        $params = $request->all();
        $goodsId = isset($params['goods_id']) ? intval($params['goods_id']) : 0;
        $title = isset($params['title']) ? htmlspecialchars($params['title']) : '';        // 活动标题
        $startTime = isset($params['start_time']) && $params['start_time'] != '' ? $params['start_time'] : Carbon::now();  // 开始时间
        $endTime = isset($params['end_time']) && $params['end_time'] != '' ? $params['end_time'] : Carbon::now();  // 结束时间
        $status = isset($params['status']) ? intval($params['status']) : 0;
        $is_onoff = isset($params['is_onoff']) ? intval($params['is_onoff']) : 0;//是否开启消息通知
        $type = isset($params['type']) ? intval($params['type']) : 1;         // 砍价类型设置
        $limit_number = isset($params['limit_number']) ? intval($params['limit_number']) : 1;         // 限购次数
        if($type == 1){
            $fixed = isset($params['fixed']) ? $params['fixed'] : 0; //固定价格
            $prineSmall = 0;
            $prineBig = 0;
        }else{
            $fixed = 0 ;
            $prineSmall = isset($params['prine_small']) ? $params['prine_small'] : 0; //随机最小价格
            $prineBig = isset($params['prine_big']) ? $params['prine_big'] : 0; //随机最大价格
            if(bccomp($prineSmall,$prineBig,2) == 1){
                return Response::json(['errcode'=>220002,'errmsg'=>'最小价格不能大于最大价格']);
            }
        }
        $minimum = isset($params['minimum']) && $params['minimum'] ? $params['minimum'] : '';      // 活动商品最低价
        $remark = isset($params['remark']) && $params['remark'] ? $params['remark'] : '';       // 活动说明
        $bg_img = isset($params['bg_img']) ? $params['bg_img'] : ''; //背景图片

        $share_activity = isset($params['share_activity']) && $params['share_activity'] ? $params['share_activity'] : $this->shareActivity;//活动分享  json {"name":"标题"}
        if($type == 1){
            if(!$title || !$fixed) {
                return Response::json(['errcode' => 220008, 'errmsg' => '参数错误']);
            }
        }else{
            if(!$title || !$prineSmall || !$prineBig) {
                return Response::json(['errcode' => 220008, 'errmsg' => '参数错误']);
            }
        }
        $goods_info = Goods::get_data_by_id($goodsId, $this->merchant_id);
        if($goods_info->is_sku == 0){
            $min_price = $goods_info->price;
        } elseif ($goods_info->is_sku == 1) {
            //多规格最低价
            $min_price = GoodsSpec::where('goods_id', $goodsId)
            ->where('is_delete', 1)
            ->min('price');
        }
        if ($min_price <= $minimum) {
            return Response::json(['errcode' => 220005, 'errmsg' => '砍价最低价格必须小于最低价']);
        }
        //可以砍的价格
        $b_price = $min_price - $minimum;
        if($type == 1){
            if(bccomp($b_price,$fixed,2) == -1){
                return Response::json(['errcode' => 220006, 'errmsg' => '砍价设置价格必须在价格范围内']);
            }
        }else{
            if(bccomp($b_price,$prineBig,2) == -1){
                return Response::json(['errcode' => 220006, 'errmsg' => '砍价设置价格必须在价格范围内']);
            }
        }
        $bargainInfo = Bargain::where(['id'=>$id, 'merchant_id'=>$this->merchant_id,'is_delete' => 1])->first();
        if($bargainInfo['goods_id'] != $goodsId) {
            return Response::json(['errcode' => 220010, 'errmsg' => '参数错误，编辑失败']);
        }
        $time = time();
        if ($time > strtotime($bargainInfo['start_time'])) {
            return Response::json(['errcode' => 220011, 'errmsg' => '活动开始后无法修改']); 
        }else{
            $bargainData = [
            'merchant_id' => $this->merchant_id,
            'status' => $status,
            'goods_id' => $goodsId,
            'title' => $title,
            'type' => $type,
            'fixed' => $fixed,
            'prine_small' => $prineSmall,
            'prine_big' => $prineBig,
            'minimum' => $minimum,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'remark' => $remark,
            'bg_img' => $bg_img,
            'is_onoff' => $is_onoff,
            'limit_number' => $limit_number,
            'share_activity' => json_encode($share_activity),
            'created_time' => Carbon::now(),
            'updated_time' => Carbon::now()
            ];
           $result = $this->model->update_data($id, $this->merchant_id, $bargainData);
           if ($result) {
                $recode = array( 
                    'start_time' => $startTime,//开始时间同步
                    'finish_time' => $endTime,//结束时间同步
                );
                AloneActivityRecode::where(array('alone_id' => $id, 'merchant_id' => $this->merchant_id))->update($recode);
                return Response::json(['errcode' => 0, 'errmsg' => '活动编辑成功']);
            } else {
                return Response::json(['errcode' => 220010, 'errmsg' => '活动编辑失败']);
            }
        }  
    }

    //删除砍价
    public function deleteBargain($id)
    {
        if (empty($this->merchant_id)) {
            return Response::json(['errcode' => 220001, 'errmsg' => '非法操作']);
        }
        if (!$id) {
            return Response::json(['errcode' => 220008, 'errmsg' => '参数错误']);
        }
        $res = $this->model->where(['id'=>$id, 'merchant_id'=>$this->merchant_id,'is_delete' => 1])->first();
        if (!$res) return array('error' => 220012, 'msg' => '砍价活动不存在');
        $time = time();
        if ($time < strtotime($res['start_time'])) {//活动未开始
            $data['is_delete'] = -1;
            $data['end_time'] = date('Y-m-d H:i:s');
            $result = $this->model->update_data($id, $this->merchant_id, $data);
            if($result){
                $recode = array( 
                    'finish_time' => date('Y-m-d H:i:s'),//结束时间同步
                );
                AloneActivityRecode::where(array('alone_id' => $id, 'merchant_id' => $this->merchant_id))->update($recode);
                return Response::json(['errcode' => 0, 'errmsg' => '活动删除成功']);
            }
        }else{
            return Response::json(['errcode'=>-1,'errmsg'=>'活动开始后不能删除']);
        }
    }

 
    //手动结束砍价
    public function putFinishBargain(Request $request)
    {
        $params = $request->all();
        if (empty($this->merchant_id)) {
            return Response::json(['errcode' => 220001, 'errmsg' => '非法操作']);
        }
        $data = [];
        $data['id'] = isset($params['id']) ? $params['id'] : 0;//活动id
        $data['goods_id']    = isset($params['goods_id']) ? intval($params['goods_id']) : 0;
        $data['merchant_id'] = $this->merchant_id;
        if(empty($data['id']) || empty($data['goods_id']) || empty($data['merchant_id'])){
            return Response::json(['errcode'=>220008,'errmsg'=>'参数错误']);
        }
        $result = $this->bargainService->ActionClose($data);
        return $result;
    }

    //砍价数据统计
    public function getStatis(Request $request)
    {
        $params = $request->all();
        $offset = isset($params['offset']) ? intval($params['offset']) : 0;
        $limit = isset($params['limit']) ? intval($params['limit']) : 10; 
        $bargain_id = isset($params['bargain_id']) ? intval($params['bargain_id']) : 0;
        $order_status = isset($params['order_status']) ? $params['order_status'] :'all';
        if (empty($this->merchant_id)) {
            return Response::json(['errcode' => 220001, 'errmsg' => '非法操作']);
        }
        if (!$bargain_id) {
            return Response::json(['errcode' => 220008, 'errmsg' => '参数错误']);
        }
        $query = BargainLaunch::select('bargain_launch.id','bargain_launch.nickname','bargain_launch.created_time','bargain_launch.original_price','bargain_launch.bargain_price','order_info.status','bargain_launch.order_id','bargain_launch.order_sn')->where(['bargain_launch.bargain_id'=>$bargain_id, 'bargain_launch.merchant_id'=>$this->merchant_id]);
        $query->leftJoin('order_info', 'order_info.id', '=', 'bargain_launch.order_id');
        switch($order_status) {
            case 1: //未下单
                $query->where('bargain_launch.order_id',0);
                break;
            case 2: //待支付
                $query->whereIn('order_info.status',[ORDER_SUBMIT,ORDER_TOPAY,ORDER_SUBMITTED]);
                break;
            case 3: //已支付
                $query->whereIn('order_info.status',[ORDER_SEND,ORDER_FORPICKUP,ORDER_TOSEND,ORDER_SUCCESS]);
                break;
            case 4: //已关闭 
                $query->whereIn('order_info.status',[ORDER_AUTO_CANCELED,ORDER_BUYERS_CANCELED,ORDER_MERCHANT_CANCEL,ORDER_REFUND_CANCEL]);
                break;
            default:
                break;
        }
        //$sql = $query->toSql();echo $sql;exit;
        $count = $query->count();
        $res = $query->orderBy('bargain_launch.id', 'DESC')->skip($offset)->take($limit)->get();
        if(!empty($res)){
            $res = $res->toArray();
        }else{
            return Response::json(['errcode' => 220012, 'errmsg' => '暂无数据']);
        }
        foreach ($res as $k => $v) {
            $goods_title = $this->model->where(['id'=>$bargain_id, 'merchant_id'=>$this->merchant_id])->pluck('goods_title');
            $res[$k]['goods_title'] = $goods_title;
            switch($v['status']) {
                case ORDER_AUTO_CANCELED:
                case ORDER_BUYERS_CANCELED:
                case ORDER_MERCHANT_CANCEL:
                case ORDER_REFUND_CANCEL:
                    $res[$k]['order_status'] = 4;
                    break;
                case ORDER_SUBMITTED:
                case ORDER_SUBMIT:
                case ORDER_TOPAY:             
                    $res[$k]['order_status'] = 2;
                    break;
                case ORDER_TOSEND:
                case ORDER_FORPICKUP:
                case ORDER_SEND:
                case ORDER_SUCCESS:
                    $res[$k]['order_status'] = 3;
                    break;
                default:
                    $res[$k]['order_status'] = 1;
                    break;
            }
        }
        return Response::json(['errcode' => 0, '_count' => $count, 'data' => $res]);
    }



    //校验活动商品
    private function checkGoods($goodsId) 
    {
        $goodsRes = Goods::select('id','title','img', 'price', 'is_sku', 'cquota')
            ->where(['id' => $goodsId, 'merchant_id' => $this->merchant_id,'onsale' => 1, 'is_delete' => 1])
            ->first();
        if(!$goodsRes) {
            return false;
        }
        return $goodsRes;
    }

     /**
     * 商品规格
     */
    public function getGoodsProp($goodsId){
        $result = $this->getGoodsProps($goodsId);
        if(isset($result['errcode'])){
            return Response::json($result);
        }else{
            return Response::json(['errcode'=>0,'data'=>$result]);
        }
    }

    //商品规格
    private function getGoodsProps($goodsId) {
        $goodsId = isset($goodsId) ? intval($goodsId) : 0;
        $goodsInfo = Goods::select('id', 'goods_cat_id', 'img', 'price', 'is_sku', 'stock')
            ->where(['id' => $goodsId, 'merchant_id' => $this->merchant_id])
            ->first();

        if(empty($goodsInfo)) {
            return ['errcode' => 1, 'errmsg' => '商品不存在'];
        }

        if($goodsInfo['is_sku'] == 0) {
            return $goodsInfo;
        }
        $props = Prop::where(['goods_cat_id'=>$goodsInfo['goods_cat_id'], 'prop_type'=>1,'is_delete'=>1])
            ->whereIn('merchant_id', [$this->merchant_id, 0])
            ->get();

        $products = GoodsSpec::select('id', 'price', 'spec_sn', 'props', 'props_str', 'img', 'barcode', 'stock')
            ->where('goods_id', $goodsId)
            ->where('is_delete', 1)
            ->get();

        $productProps = [];
        foreach($products as $_plist) {
            if($_plist['props']) {
                $propsArr = explode(';', $_plist['props']);
                if(is_array($propsArr) && !empty($propsArr)) {
                    foreach($propsArr as $_tprop) {
                        $bprop = explode(':', $_tprop);
                        if(isset($bprop[0])) {
                            $productProps[] = $bprop[0];
                        }
                    }
                }
            }
        }

        $productProps = array_unique($productProps);

        foreach($props as $key => $list) {
            if(!in_array($list['id'], $productProps)) {
                unset($props[$key]);
            }
        }

        $goodsProps = [];
        foreach ($props as $_prop) {
            if ($_prop['prop_type'] == 1) {
                if (!isset($goodsProps[$_prop['id']])) {
                    $propItem = [];
                    $goodsProp = GoodsProp::select('prop_vid', 'prop_value')
                        ->where('prop_id', $_prop['id'])
                        ->where('goods_id', $goodsId)
                        ->where('is_delete',1)
                        ->get();
                    $propItem['id'] = $_prop['id'];
                    $propItem['name'] = $_prop['title'];
                    $propItem['listorder'] = $_prop['listorder'];
                    $propItem['gps'] = $goodsProp;
                    $goodsProps[$_prop['id']] = $propItem;
                }
            }
        }

        $data = [
            'is_sku' => $goodsInfo['is_sku'],
            'goods_props' => $goodsProps,
            'products' => $products,
        ];

        return $data;

    }


}

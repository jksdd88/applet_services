<?php
namespace App\Http\Controllers\Super;


use App\Http\Controllers\Controller;
use App\Models\Fightgroup;
use App\Models\FightgroupItem;
use App\Models\FightgroupJoin;
use App\Models\FightgroupLadder;
use App\Models\FightgroupLaunch;
use App\Models\FightgroupStock;
use App\Models\OrderRefund;
use App\Models\Shop;
use App\Services\FightgroupService;
use App\Services\GoodsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class TuanController extends Controller
{

    private $merchant_id;

    protected $initiate_status = array(
        PIN_INIT_SUBMIT   => '开团未支付',
        PIN_INIT_ACTIVE  => '拼团中',
        PIN_INIT_SUCCESS  => '成功',
        PIN_INIT_FAIL  => '失败(团长未支付)',
        PIN_INIT_FAIL_STOCK  => '失败(库存不足)',
        PIN_INIT_FAIL_END  => '失败',
        PIN_INIT_FAIL_MERCHANT=>'失败(商家终止)',
        PIN_INIT_FAIL_NOTWORKING=>'开团失败(超卖)'
    );

    protected $initiate_join_status = array(
        PIN_JOIN_SUBMIT => '待支付',
        PIN_JOIN_PAID => '支付成功',
        PIN_JOIN_FAIL => '失败(超时未支付)',
        PIN_JOIN_FAIL_STOCK => '失败(库存不足)',
        PIN_JOIN_FAIL_END => '未成团',
        PIN_JOIN_FAIL_EXCEED => '参团失败(名额已满)',
        PIN_JOIN_SUCCESS => '参团成功',
        PIN_JOIN_FAIL_MERCHANT => '失败(手动)',
        PIN_JOIN_FAIL_NOTWORKING => '失败(超卖)'
    );

    protected $ladder_status = array(
        PIN_LADDER_SUBMIT => '未开始',
        PIN_LADDER_ACTIVE => '拼团中',
        PIN_LADDER_END => '已结束',
        PIN_LADDER_STOCK_END => '已结束(库存不足)',
        PIN_LADDER_MERCHANT_END => '已结束(手动)'
    );

    public function __construct(FightgroupService $fightgroupService,GoodsService $goodsService)
    {
        $this->fightgroupService = $fightgroupService;
        $this->goodsService = $goodsService;
    }

    /**
     * 拼团列表
     */
    public function getFightgroups(Request $request){
        $params = $request->all();
        $title       = isset($params['title']) ? $params['title'] : '';//活动名称
        $goods_title = isset($params['goods_title']) ? $params['goods_title'] : '';//商品名称
        $offset   = isset($params['offset']) ? $params['offset'] : 0;
        $limit    = isset($params['limit']) ? $params['limit'] : 10;
        $sn_id    = isset($params['fightgroup_id']) ? $params['fightgroup_id'] : '';//编号
        $status   = isset($params['status']) ? $params['status'] : 0;//0全部 1未开始 2活动中 3已结束
        $query = Fightgroup::where('id','>',0);
        //按编号搜索
        if($sn_id){
            $query->where('id',$sn_id);
        }
        //未开始
        if($status==1){
            $query->where('status',PIN_SUBMIT);
        }
        //活动中
        if($status==2){
            $query->where('status',PIN_ACTIVE);
        }
        //已结束
        if($status==3){
            $query->whereIn('status',[PIN_TIME_END,PIN_MERCHANT_END]);
        }

        //按活动名称搜索
        if($title){
            $query->where('title','like',"%$title%");
        }
        //按商品名称搜索
        if($goods_title){
            $query->where('goods_title','like',"%$goods_title%");
        }
        $data['_count'] = $query->count();
        $data['data']   = array();
        if($data['_count']){
            $data['data'] = $query->orderBy('id', 'desc')->skip($offset)->take($limit)->get();
            if($data['data']){
                foreach($data['data'] as &$v){
                    $tuanStock = FightgroupStock::where('fightgroup_id',$v['id'])->get()->toArray();
                    $total_stock = $surplus_num = 0;
                    if($tuanStock){
                        foreach($tuanStock as $ks=>$vs){
                            $total_stock+=$vs['total_stock'];
                            $surplus_num+=$vs['stock'];
                        }
                    }
                    $v['total_stock'] = $total_stock;//总库存
                    $v['surplus_num'] = $surplus_num;//剩余库存
                    $ladder = FightgroupLadder::where('fightgroup_id',$v['id'])->get();
                    if($ladder){
                        foreach($ladder as &$vs){
                            $vs['status_msg'] = $this->ladder_status[$vs['status']];
                        }
                    }
                    $v['ladder'] = $ladder;
                    $v['shop_name'] = Shop::where('merchant_id',$v['merchant_id'])->value('name');
                }
            }
        }
        return Response::json(['errcode'=>0,'_count'=>$data['_count'],'data'=>$data['data']]);
    }

    /**
     * 开团列表
     */
    public function getLaunchs($tuanid,Request $request){
        $data = $request->all();
        // 检索参数
        $offset      = isset($data['offset']) ? $data['offset'] : 0;
        $limit       = isset($data['limit']) ? $data['limit'] : 10;
        $status      = isset($data['status']) && is_numeric($data['status']) ? intval($data['status']) : 'all';
        //所需人数(几人团)
        $nums    = isset($data['nums']) ? $data['nums'] : '';

        $startDate   = isset($data['startDate']) ? trim($data['startDate']) : '';
        $endDate     = isset($data['endDate']) ? trim($data['endDate']).' 23:59:59' : date('Y-m-d',time()).' 23:59:59';


        // 搜索条件
        $wheres = array(
            array('column' => 'fightgroup_id', 'value' => $tuanid, 'operator' => '=')
        );

        //拼团状态
        if (is_numeric($status)) {
            if($status==0){//开团待支付
                $condition = array(
                    array('column' => 'status', 'value' => PIN_INIT_SUBMIT, 'operator' => '=')
                );
            }else if($status==1){//拼团中
                $condition = array(
                    array('column' => 'status', 'value' => PIN_INIT_ACTIVE, 'operator' => '=')
                );
            }else if($status==2){//成功
                $condition = array(
                    array('column' => 'status', 'value' => PIN_INIT_SUCCESS, 'operator' => '>=')
                );
            }else if($status==3){//失败
                $condition = array(
                    array('column' => 'status', 'value' => PIN_INIT_FAIL, 'operator' => '>='),
                    array('column' => 'status', 'value' => PIN_INIT_FAIL_MERCHANT, 'operator' => '<=')
                );
            }
            $wheres = array_merge($condition, $wheres);
        }
        //几人团
        if ($nums && $nums!="all"){
            $condition = array(array('column' => 'nums', 'value' => $nums, 'operator' => '='));
            $wheres = array_merge($condition, $wheres);
        }

        //团长昵称
        $nickname = isset($data['nickname']) ? trim($data['nickname']) : '';
        if($nickname){
            $condition = array(array('column' => 'nickname', 'value' => $nickname, 'operator' => '='));
            $wheres = array_merge($condition, $wheres);
        }
        //开团时间
        if($startDate && $endDate){
            $condition = array(
                array('column' => 'created_time', 'value' => $startDate, 'operator' => '>='),
                array('column' => 'created_time', 'value' => $endDate, 'operator' => '<=')
            );
            $wheres = array_merge($condition, $wheres);
        }

        //根据订单号搜索
        $order_sn = isset($data['order_sn']) && $data['order_sn'] ? trim($data['order_sn']) : '';
        if($order_sn){
            $launch_id = FightgroupJoin::where('order_sn',$order_sn)->value('launch_id');
            $condition = array(array('column' => 'id', 'value' => $launch_id, 'operator' => '='));
            $wheres = array_merge($condition, $wheres);
        }

        $query = FightgroupLaunch::select('*');

        foreach ($wheres as $value) {
            $query->where($value['column'], $value['operator'], $value['value']);
        }
        $count = $query->count();

        $initiates = array();

        if($count > 0){
            $initiates = $query->orderBy('id', 'DESC')->skip($offset)->take($limit)->get();
            foreach($initiates as $k => $i){
                $ladder = FightgroupLadder::where('id',$i['ladder_id'])->first();
                //拼团需要人数
                $initiates[$k]['cpeoples'] = $ladder['cpeoples'];
                //所需时间
                $initiates[$k]['expire_in'] = $ladder['expire_in'];
                $initiates[$k]['is_edit_time'] = in_array($i['status'],[PIN_INIT_SUBMIT,PIN_INIT_ACTIVE]) ? 1 : 0;//团长开团未支付和拼团中允许修改时间
                //拼团状态
                $initiates[$k]['status_name'] = $this->initiate_status[$i['status']];
            }
        }
        return Response::json(['errcode' => 0, '_count' => $count,'data'=>$initiates]);
    }

    /**
     * 参团列表
     */
    public function getJoins($launch_id,Request $request){
        $params = $request->all();
        if(!$launch_id){
            return Response::json(['errcode'=>1,'errmsg'=>'非法请求']);
        }
        $order_sn = isset($params['order_sn']) && $params['order_sn'] ? $params['order_sn'] : '';//订单号
        $nickname = isset($params['nickname']) && $params['nickname'] ? $params['nickname'] : '';//参与人昵称
        $offset   = isset($params['offset']) ? $params['offset'] : 0;
        $limit    = isset($params['limit']) ? $params['limit'] : 10;
        $data = array('_count'=>0,'data'=>[]);
        $query = FightgroupJoin::select('id','avatar','nickname','num','item_id','pay_time','order_id','order_sn','status','is_captain')->where('launch_id',$launch_id);
        //按订单号搜索
        if($order_sn){
            $query->where('order_sn',$order_sn);
        }
        //按参与人昵称搜索
        if($nickname){
            $query->where('nickname',$nickname);
        }
        $data['_count'] = $query->count();
        if($data['_count']){
            $data['data'] = $query->skip($offset)->take($limit)->get();
            foreach($data['data'] as &$v){
                $itemInfo = FightgroupItem::where('id',$v['item_id'])->first();
                $v['props_str'] = isset($itemInfo['props_str']) ? $itemInfo['props_str'] : '单规格';
                $v['price'] = isset($itemInfo['ladder_price']) ? $itemInfo['ladder_price'] : 0;//拼团单价
                $v['total_price'] = $v['price']*$v['num'];//拼团总价
                $v['status_str'] = $this->initiate_join_status[$v['status']];
                if($v['pay_at']=="0000-00-00 00:00:00"){
                    $v['pay_at'] = "--";
                }

                //已经退款
                if($v['is_refund']>0){
                    $v['refund_id'] = OrderRefund::where('order_id',$v['order_id'])->pluck('id');
                }
            }
        }
        return Response::json(['errcode'=>0,'_count'=>$data['_count'],'data'=>$data['data']]);
    }
}

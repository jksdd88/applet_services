<?php

namespace App\Http\Controllers\Weapp\Knowledge;

use App\Models\KnowledgeColumn;
use App\Models\KnowledgeContent;
use App\Models\OrderKnowledge;
use App\Services\BuyService;
use App\Services\KnowledgeService;
use App\Utils\CommonApi;
use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Facades\Member;
use Illuminate\Support\Facades\DB;
use function Qiniu\json_decode;

class KnowledgeOrderController extends Controller
{
    protected $merchant_id;
    protected $member_id;
    protected $weapp_id;

    public function __construct()
    {
        $this->member_id = Member::id();
        $this->merchant_id = Member::merchant_id();
        $this->weapp_id = Member::weapp_id();
    }

    /**
     *我购买的内容
     * @author renruiqi@dodoca.com
     */
    public function my_contents(Request $request)
    {
        $param = $request->all();
        $page = empty($param['page']) ? 1 : $param['page'];
        $pagesize = (empty($param['pagesize']) ? 10 : $param['pagesize']);
        $query = OrderKnowledge::Join('knowledge_content as c', 'order_knowledge.knowledge_id', '=', 'c.id')
            ->where('order_knowledge.member_id', $this->member_id)
            ->where('order_knowledge.merchant_id', $this->merchant_id)
            ->where('order_knowledge.k_type', 2)//2为内容
            ->where('order_knowledge.pay_status', OrderKnowledge::PAY_STATUS_SUCCESS)//已經付款
            ->where('c.merchant_id', $this->merchant_id);
            //->where('c.is_delete', 1)
        $data['_count'] = $query->count();
        $data['lists'] = $query->forPage($page, $pagesize)
            ->orderBy('order_knowledge.created_time', 'desc')
            ->get([
                'c.id',
                'c.merchant_id',
                'c.name',
                'c.summary',
                'c.img',
                'c.csale',
                'c.base_csale',
                'c.status',
                'c.type',
                'order_knowledge.paid_time as created_time',//付款时间
                'c.is_delete'
            ]);
        if ($data['_count']) {
            $data['lists'] = $data['lists']->toArray();
            foreach ($data['lists'] as &$v) {
                $v['csale'] += $v['base_csale'];
                $v['img'] = json_decode($v['img']);
                unset($v['base_csale']);
            }
        }
        return ['errcode' => 0, 'errmsg' => '操作成功', 'data' => $data];
    }

    /**
     *我购买的专栏
     * @author renruiqi@dodoca.com
     */
    public function my_columns(Request $request)
    {
        $param = $request->all();
        $page = empty($param['page']) ? 1 : $param['page'];
        $pagesize = (empty($param['pagesize']) ? 10 : $param['pagesize']);
        $query = OrderKnowledge::Join('knowledge_column as c', 'order_knowledge.knowledge_id', '=', 'c.id')
            ->where('order_knowledge.member_id', $this->member_id)
            ->where('order_knowledge.merchant_id', $this->merchant_id)
            ->where('order_knowledge.k_type', 1)//1为专栏
            ->where('order_knowledge.pay_status', OrderKnowledge::PAY_STATUS_SUCCESS)//已經付款
            ->where('c.merchant_id', $this->merchant_id);
            //->where('c.is_delete', 1)
        $data['_count'] = $query->count();
        $data['lists'] = $query->forPage($page, $pagesize)
            ->orderBy('order_knowledge.created_time', 'desc')
            ->get([
                'c.id',
                'c.merchant_id',
                'c.name',
                'c.summary',
                'c.img',
                'c.csale',
                'c.base_csale',
                'c.status',
                'c.period_number',//更新期数
                'order_knowledge.paid_time as created_time',//付款时间
                'c.is_delete'
            ]);
        if ($data['_count']) {
            $data['lists'] = $data['lists']->toArray();
            foreach ($data['lists'] as &$v) {
                $v['csale'] += $v['base_csale'];
                $v['img'] = json_decode($v['img']);
                unset($v['base_csale']);
            }
        }
        return ['errcode' => 0, 'errmsg' => '操作成功', 'data' => $data];
    }

    /**
     * Store a newly created resource in storage.下单购买
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     * @author: tangkang@dodoca.com
     */
    public function store(Requests\Weapp\OrderKnowledgeRequest $request)
    {
        $param_re = $request->all();
        $param = $request->getData($param_re, $this->merchant_id);
        if ($param['errcode'] != 0) return $param;
        if (KnowledgeService::ifPaid($param['data']['knowledge_id'], $param['data']['k_type'], $this->member_id, $this->merchant_id)) {
            return ['errcode' => 1, 'errmsg' => '请不要重复购买'];
        }
        if (empty(Member::appid())) return ['errcode' => 1, 'errmsg' => '小程序参数缺失'];
        $buyService = new BuyService();
        DB::beginTransaction();
        try {
            $data = [
                'merchant_id' => $this->merchant_id,  //商户id
                'member_id' => $this->member_id,  //会员id
                'order_type' => ORDER_KNOWLEDGE,  //订单类型---预约下单
                'amount' => $param['data']['price'],
                'name' => $param['data']['name'],
                'img' => $param['data']['img'],
            ];
            $buy_res = $buyService->createorder($data);
            if ($buy_res['errcode'] != 0) throw new \Exception(json_encode($buy_res, JSON_UNESCAPED_UNICODE));
            $k_data = [
                'merchant_id' => $this->merchant_id,  //商户id
                'appid' => Member::appid(),//获取小程序appid
                'member_id' => $this->member_id,  //会员id
                'order_id' => $buy_res['order_id'],
                'order_sn' => $buy_res['order_sn'],
                'k_type' => $param['data']['k_type'],
                'col_content_type' => $param['data']['type'],
                'knowledge_id' => $param['data']['knowledge_id'],
                'name' => $param['data']['name'],
                'price' => $param['data']['price'],
                'img' => $param['data']['img'],
            ];
            if ($param['data']['price'] == '0.00') {
                $buy_res['ispay'] = 1;
                $k_data['pay_status'] = OrderKnowledge::PAY_STATUS_SUCCESS;
                $k_data['paid_time'] = date('Y-m-d H:i:s');
                //免费：增销量
                if ($param['data']['k_type'] == OrderKnowledge::K_TYPE_COLUMN) {
                    KnowledgeColumn::incCsale($param['data']['knowledge_id'], $this->merchant_id);
                } else {
                    KnowledgeContent::incCsale($param['data']['knowledge_id'], $this->merchant_id);
                }
            } else {
                $buy_res['ispay'] = 0;
                $k_data['pay_status'] = OrderKnowledge::PAY_STATUS_UNPAID;
            }
            $res_k = OrderKnowledge::insert_data($k_data);
            if (empty($res_k)) throw new \Exception('OrderKnowledge插入失败，$k_data：' . json_encode($k_data));
            DB::commit();

            $buy_res['errmsg'] = '订阅成功';
            return $buy_res;
        } catch (\Exception $e) {
            DB::rollBack();
            $except = [
                'activity_id' => $param_re['object_id'],
                'data_type' => 'order_knowledge',
                'content' => '知识付费购买失败.file：' . $e->getFile() . '，line:' . $e->getLine() . ',msg:' . $e->getMessage() . '，trace：' . $e->getTraceAsString(),
            ];
            CommonApi::errlog($except);
            return ['errcode' => 1, 'errmsg' => json_decode($e->getMessage())->errmsg];
        }
    }
}

<?php

namespace App\Services;

use App\Models\KnowledgeColumn;
use App\Models\KnowledgeContent;
use App\Models\KnowledgeContentColumnRel;
use App\Models\OrderKnowledge;
use App\Utils\CommonApi;


/**
 * Created by PhpStorm.
 * User: tang
 * Date: 2018-04-09
 * Time: 10:41
 */
class KnowledgeService
{
    /**
     * 栏目列表
     * @param $param
     * @param $merchant_id
     * @return mixed
     * @author: tangkang@dodoca.com
     */
    static function listsColumn($param, $merchant_id)
    {
        $query = KnowledgeColumn::where('merchant_id', $merchant_id);
        if (!(int)($member_id = empty($param['member_id']))) {
            $query->where('member_id', $member_id);
        }
        if (!empty($param['status']) && ($status = (int)$param['status']) && in_array($status, [KnowledgeColumn::STATUS_ONSHELVE, KnowledgeColumn::STATUS_UNSHELVE])) {
            $query->where('status', $status);
        }
        if (!empty($param['name']) && ($name = $param['name'])) {
            $query->where('name', 'like', "%$name%");
        }
        $sort = 'desc';
        if (!empty($param['sort']) && in_array($param['sort'], ['asc', 'desc'])) {
            $sort = $param['sort'];
        }
        $orderBy = 'created_time';
        if (!empty($param['orderby']) && in_array($param['orderby'], ['csale', 'created_time', 'price'])) {
            $orderBy = $param['orderby'];
        }
        $query->where('is_delete', 1);
        $data['count'] = $query->count();
        $page = empty($param['page']) ? 1 : $param['page'];
        $pagesize = (empty($param['pagesize']) ? 10 : $param['pagesize']);
        $fields = [
            'id',
            'name',
            'summary',
            'img',
            'price',
            'period_number',
            'status',
            'created_time'
        ];
        if (!empty($param['weapp'])) {
            $fields[] = \Db::Raw('(csale+base_csale) as csale');
        } else {
            $fields[] = 'csale';
            $fields[] = 'base_csale';
        }
        $data['lists'] = $query->orderBy($orderBy, $sort)->forPage($page, $pagesize)->get($fields);
        if (!$data['lists']->isEmpty()) {
            $data['lists'] = $data['lists']->toArray();
        }
        return $data;
    }

    /**
     * 内容列表
     * @param $param
     * @param $merchant_id
     * @return mixed
     * @author: tangkang@dodoca.com
     */
    static function listsContent($param, $merchant_id)
    {
        $query = KnowledgeContent::where('merchant_id', $merchant_id);
        if (!($member_id = (int)empty($param['member_id']))) {
            $query->where('member_id', $member_id);
        }
        if (!empty($param['status']) && ($status = (int)$param['status']) && in_array($status, [KnowledgeContent::STATUS_ONSHELVE, KnowledgeContent::STATUS_UNSHELVE])) {
            $query->where('status', $status);
        }

        if (!empty($param['name'])) {
            $query->where('name', 'like', '%' . $param['name'] . '%');
        }
        if ((!empty($param['type'])) && ($type = (int)$param['type']) && in_array($type, [KnowledgeContent::TYPE_ARTICLE, KnowledgeContent::TYPE_AUDIO, KnowledgeContent::TYPE_VIDEO])) {
            $query->where('type', $type);
        }
        $sort = 'desc';
        if (!empty($param['sort']) && in_array($param['sort'], ['asc', 'desc'])) {
            $sort = $param['sort'];
        }
        $orderBy = 'created_time';
        if (!empty($param['orderby']) && in_array($param['orderby'], ['csale', 'created_time', 'price'])) {
            $orderBy = $param['orderby'];
        }

        $query->where('is_delete', 1);
        $data['count'] = $query->count();
        $page = empty($param['page']) ? 1 : $param['page'];
        $pagesize = empty($param['pagesize']) ? 10 : $param['pagesize'];
        $fields = [
            'id',
            'name',
            'summary',
            'img',
            'price',
            'created_time',
            'type',
            'status'
        ];
        if (!empty($param['weapp'])) {
            $fields[] = \Db::Raw('(csale+base_csale) as csale');
        } else {
            $fields[] = 'csale';
            $fields[] = 'base_csale';
        }
        $data['lists'] = $query->orderBy($orderBy, $sort)->forPage($page, $pagesize)->get($fields);
        if (!$data['lists']->isEmpty()) {
            $data['lists'] = $data['lists']->toArray();
        }
        return $data;
    }

    /**
     * 根据栏目id获取栏目内容列表
     * @param $id
     * @param $merchant_id
     * @param $param
     * @return int
     * @author tangkang@dodoca.com
     */
    static function get_content_by_id($id, $merchant_id, $param)
    {
        if (!$id || !is_numeric($id)) return 0;
        if (!$merchant_id || !is_numeric($merchant_id)) return 0;
        $query = KnowledgeContentColumnRel::where('knowledge_content_column_rel.merchant_id', $merchant_id)
            ->where('knowledge_content_column_rel.column_id', $id)
            ->where('knowledge_content_column_rel.is_delete', 1)
            ->leftJoin('knowledge_content', 'knowledge_content.id', '=', 'knowledge_content_column_rel.content_id')
            ->where('knowledge_content.merchant_id', $merchant_id)
            ->where('knowledge_content.is_delete', 1);
        if (!empty($param['status'])) {
            if (is_numeric($param['status']) && in_array($param['status'], [KnowledgeContent::STATUS_ONSHELVE, KnowledgeContent::STATUS_UNSHELVE])) {
                $query->where('knowledge_content.status', $param['status']);
            }
        }
        if (!empty($param['type']) && in_array($param['type'], [1, 2, 3])) {
            $query->where('knowledge_content.type', $param['type']);

        }
        if (!empty($param['name'])) {
            $query->where('knowledge_content.name', 'like', '%' . $param['name'] . '%');
        }
        $data['count'] = $query->count();
        $page = empty($param['page']) ? 1 : $param['page'];
        $pagesize = empty($param['pagesize']) ? 10 : $param['pagesize'];
        $fields = [
            'knowledge_content.id',
            'knowledge_content.name',
            'knowledge_content.summary',
            'knowledge_content.img',
            'knowledge_content.price',
            'knowledge_content.csale',
            'knowledge_content.base_csale',
            'knowledge_content.type',
            'knowledge_content.created_time',
            'knowledge_content.status',
        ];
        if (!empty($param['weapp'])) {
            $fields[] = \Db::Raw('(knowledge_content.csale+knowledge_content.base_csale) as csale');
        } else {
            $fields[] = 'knowledge_content.csale';
            $fields[] = 'knowledge_content.base_csale';
        }
        $data['lists'] = $query->forPage($page, $pagesize)->get($fields);
        if (!$data['lists']->isEmpty()) {
            $data['lists'] = $data['lists']->toArray();
        }
        return $data;
    }


    /**
     * 根据专栏ids获取专栏列表
     * @param $ids  array ids 数组  必选
     * @param $merchant_id
     * @param $param
     * @return int
     * @author renruiqi@dodoca.com
     */
    static function get_content_by_ids($merchant_id, $ids, $is_weapp = null)
    {
        if (!is_array($ids)) return ['errcode' => 99001, 'errmsg' => '参数错误'];
        $data = [];
        if (in_array(-1, $ids)) $data[] = ['id' => -1, 'name' => '所有内容'];
        if (in_array(-2, $ids)) $data[] = ['id' => -2, 'name' => '专栏'];
        foreach ($ids as $v) {
            $query = KnowledgeColumn::select('id', 'name');
            if ($is_weapp === 1) {
                $query->where('status', KnowledgeColumn::STATUS_ONSHELVE);//小程序端排除下架栏目
            }
            $child_data = $query->where('merchant_id', $merchant_id)
                ->select('id', 'name')
                ->where('is_delete', 1)
                ->find($v);
            if ($child_data) {
                $data[] = $child_data->toArray();

            }
        }
        return $data;
    }


    static function get_show_csale($data)
    {
        foreach ($data as $k => &$v) {
            $v['real_csale'] = $v['csale'] + $v['base_csale'];
            unset($v['csale'], $v['base_csale']); //释放变量
        }
        return $data;
    }

    /**
     * 是否购买了知识
     * @param $k_id
     * @param $k_type $k_id的类型（专栏 or 内容）
     * @param $member_id
     * @param $merchant_id
     * @return bool 1已购买，0未购买
     * @author: tangkang@dodoca.com
     */
    static function ifPaid($k_id, $k_type, $member_id, $merchant_id)
    {
        $res = OrderKnowledge::get_data_by_k_member_mercahnt_id($k_id, $k_type, $member_id, $merchant_id);
        if (empty($res)) {
            if ($k_type == OrderKnowledge::K_TYPE_COLUMN) return 0;
            $columns = KnowledgeContentColumnRel::get_lists_by_content_id($k_id, $merchant_id);
            if (!empty($columns) && !empty($columns['lists'])) {
                foreach ($columns['lists'] as $list) {
                    $res = OrderKnowledge::get_data_by_k_member_mercahnt_id($list['id'], OrderKnowledge::K_TYPE_COLUMN, $member_id, $merchant_id);
                    if ($res) {
                        return 1;
                    }
                }
            }
            return 0;
        } else {
            return 1;
        }
    }


    /**
     * 根据内容id获取所属栏目列表
     * @param $id //内容id
     * @param $merchant_id
     * @param $param
     * @return int
     * @author renruiqi@dodoca.com
     */
    static function get_column_by_content_id($id, $merchant_id, $param)
    {
        if (!$id || !is_numeric($id)) return 0;
        if (!$merchant_id || !is_numeric($merchant_id)) return 0;
        $query = KnowledgeContentColumnRel::where('knowledge_content_column_rel.merchant_id', $merchant_id)
            ->where('knowledge_content_column_rel.is_delete', 1)
            ->where('knowledge_content_column_rel.content_id', $id)
            ->Join('knowledge_column', 'knowledge_column.id', '=', 'knowledge_content_column_rel.column_id')
            ->where('knowledge_column.merchant_id', $merchant_id)
            ->where('knowledge_column.is_delete', 1);

        //截断
        if (!empty($param['status'])) {
            if (is_numeric($param['status']) && in_array($param['status'], [KnowledgeColumn::STATUS_ONSHELVE, KnowledgeColumn::STATUS_UNSHELVE])) {
                $query->where('knowledge_column.status', $param['status']);
            }
        }

        if (!empty($param['name'])) {
            $query->where('knowledge_column.name', 'like', '%' . $param['name'] . '%');
        }
        $data['count'] = $query->count();
        $page = empty($param['page']) ? 1 : $param['page'];
        $pagesize = empty($param['pagesize']) ? 10 : $param['pagesize'];
        $data['lists'] = $query->forPage($page, $pagesize)->get([
            'knowledge_column.id',
            'knowledge_column.name',
            'knowledge_column.summary',
            'knowledge_column.period_number',
            'knowledge_column.img',
            'knowledge_column.price',
            'knowledge_column.csale',
            'knowledge_column.base_csale',
            'knowledge_column.created_time',
            'knowledge_column.status',
        ]);
        if (!$data['lists']->isEmpty()) {
            $data['lists'] = $data['lists']->toArray();
        }
        return $data;
    }

    /**
     * 付款成功回调
     * @param $order
     * @return array
     * @author: tangkang@dodoca.com
     */
    static function postOrderPaid($order)
    {
        try {
            $order_k_res = OrderKnowledge::get_data_by_order_id($order['id'], $order['merchant_id']);
            if ($order_k_res['price'] == '0.00') return;//0元不走了
            if (empty($order_k_res) || empty($order_k_res['knowledge_id']) || empty($order_k_res['merchant_id'])) {
                throw new \Exception('知识付费订单查询失败，订单参数：' . json_encode($order) . '，查询结果：' . json_encode($order_k_res));
            }
            $update_data = [
                'paid_time' => date('Y-m-d H:i:s'),
                'pay_status' => OrderKnowledge::PAY_STATUS_SUCCESS,
            ];
            $res = OrderKnowledge::update_data($order['id'], $order['merchant_id'], $update_data);
            if (empty($res)) throw new \Exception('知识付费订单支付成功状态更新失败');
            if ($order_k_res['k_type'] == OrderKnowledge::K_TYPE_COLUMN) {
                $inc_res = KnowledgeColumn::incCsale($order_k_res['knowledge_id'], $order_k_res['merchant_id']);
            } elseif ($order_k_res['k_type'] == OrderKnowledge::K_TYPE_CONTENT) {
                $inc_res = KnowledgeContent::incCsale($order_k_res['knowledge_id'], $order_k_res['merchant_id']);
            } else {
                throw new \Exception('知识付费类型非法，参数：' . json_encode($order_k_res));
            }
            if (empty($inc_res)) throw new \Exception('知识付费自增销量失败，返回结果$inc_res：' . $inc_res . '，参数：' . json_encode($order_k_res));
        } catch (\Exception $e) {
            //记录异常
            $except = [
                'activity_id' => isset($order['id']) ? $order['id'] : '0',
                'data_type' => 'order_knowledge',
                'content' => '知识付费订单付款成功状态信息保存异常。line:' . $e->getLine() . '，msg：' . $e->getMessage() . json_encode($order, JSON_UNESCAPED_UNICODE),
            ];
            CommonApi::errlog($except);
            return ['errcode' => 1, 'errmsg' => '预约商品付款信息保存异常：' . $e->getMessage()];
        }
    }
}
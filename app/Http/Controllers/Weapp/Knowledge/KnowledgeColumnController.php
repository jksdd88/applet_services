<?php

namespace App\Http\Controllers\Weapp\Knowledge;

use App\Models\KnowledgeColumn;
use App\Models\KnowledgeContent;
use App\Models\OrderKnowledge;
use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Services\KnowledgeService;
use App\Facades\Member;


class KnowledgeColumnController extends Controller
{
    private $merchant_id;
    private $member_id;

    public function __construct()
    {
        $this->merchant_id = Member::merchant_id();         //商户id
        $this->member_id = Member::id();
    }

    /**
     * 查询所有专栏
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $param = request()->all();
        $param['status'] = KnowledgeColumn::STATUS_ONSHELVE;
        $param['weapp'] = 1;//前台、小程序端调用
        //获取商户id,获取商户id
        $data = KnowledgeService::listsColumn($param, $this->merchant_id);
        if (!empty($data['lists'])) {
            foreach ($data['lists'] as &$column) {
                $column['img'] = json_decode($column['img']);
            }
        }
        return ['errcode' => 0, 'errmsg' => '操作成功', 'data' => $data];
    }

    /**
     *
     *根据专栏id查询所有内容
     * @author renruiqi@dodoaca.com
     */
    public function contents(Request $request)
    {
        $param = $request->all();
        $param['status'] = KnowledgeContent::STATUS_ONSHELVE;
        $column_id = $request->input('column_id', null);

        if (!$column_id) return ['errcode' => 99001, 'errmsg' => '参数错误', 'data' => []];

        //获取商户id,获取商户id
        $data = KnowledgeService::get_content_by_id((int)$column_id, $this->merchant_id, $param);
        $data['if_paid'] = KnowledgeService::ifPaid($column_id, OrderKnowledge::K_TYPE_COLUMN, $this->member_id, $this->merchant_id);
        if (!empty($data['lists'])) {
            foreach ($data['lists'] as &$list) {
                $list['img'] = json_decode($list['img']);
//                if ($data['if_paid'] || KnowledgeService::ifPaid($list['id'], OrderKnowledge::K_TYPE_CONTENT, $this->member_id, $this->merchant_id)) {
                if ($data['if_paid']) {
                    unset($list['price']);
                }
                $list['csale'] = $list['csale'] + $list['base_csale'];
                unset($list['base_csale']);
                $list['if_paid'] = $data['if_paid'];
            }
        }
        return ['errcode' => 0, 'errmsg' => '操作成功', 'data' => $data];
    }

    /**
     *
     *根据专栏数组 查询所有专栏
     * @author renruiqi@dodoaca.com
     */

    public function get_columns_by_ids(Request $request)
    {
        $ids = $request->input('ids', []);
        $data = KnowledgeService::get_content_by_ids($this->merchant_id, $ids, 1);
        return ['errcode' => 0, 'errmsg' => '操作成功', 'data' => $data];
    }

    /**
     *
     *根据专栏数组 查询所有专栏
     * @author renruiqi@dodoaca.com
     */

    public function get_columns_by_ids_new(Request $request)
    {
        $ids = $request->input('ids', []);
        $merchant_id = $request->input('merchant_id', null);
        if(count($ids)<1 || !$merchant_id )return ['errcode' => 99001, 'errmsg' => '请传入正确参数', 'data' => []];
        $data = KnowledgeService::get_content_by_ids($merchant_id, $ids, 1);
        return ['errcode' => 0, 'errmsg' => '操作成功', 'data' => $data];
    }
    /**
     * Display the specified resource.专栏详情
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $column = KnowledgeColumn::get_data_by_id($id, $this->merchant_id);
        if (!empty($column)) {
            $column['img'] = json_decode($column['img']);
            if ($column['is_delete'] !== 1) return ['errcode' => 0, 'errmsg' => '【专栏已下架】', 'data' => ['is_del' => -1]];
            $column['csale'] = $column['csale'] + $column['base_csale'];
            unset($column['base_csale']);
            $column['if_paid'] = KnowledgeService::ifPaid($id, OrderKnowledge::K_TYPE_COLUMN, $this->member_id, $this->merchant_id);
            if ($column['if_paid']) {
                unset($column['price']);
            } else {
                if ($column['status'] == KnowledgeColumn::STATUS_UNSHELVE) return ['errcode' => 0, 'errmsg' => '专栏已下架', 'data' => ['is_del' => -1]];
            }
        }
        return ['errcode' => 0, 'errmsg' => '操作成功', 'data' => $column];
    }
}

<?php

namespace App\Http\Controllers\Admin\Knowledge;

use App\Models\KnowledgeColumn;
use App\Models\KnowledgeContent;
use App\Models\KnowledgeContentColumnRel;
use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Services\KnowledgeService;
use Illuminate\Support\Facades\Schema;

class KnowledgeColumnController extends Controller
{
    protected $merchant_id;

    public function __construct()
    {
        // $this->merchant_id = Auth::user()->merchant_id;
        if (app()->isLocal()) {
            $this->merchant_id = 2;
        } else {
            $this->merchant_id = Auth::user()->merchant_id;
        }

    }

    /**
     * Display a listing of the resource.专栏列表
     *
     * @return \Illuminate\Http\Response
     * @author: tangkang@dodoca.com
     */
    public function index()
    {
        if (!empty($status = (int)request('status', 0))) {
            $param['status'] = $status;
        }
        if (!empty($page = (int)request('page', 0))) {
            $param['page'] = $page;
        }
        if (!empty($pagesize = (int)request('pagesize', 0))) {
            $param['pagesize'] = $pagesize;
        }
        if (!(empty($name = request('name', '')))) {
            $param['name'] = $name;
        }
        $data = KnowledgeService::listsColumn($param, $this->merchant_id);
        return ['errcode' => 0, 'errmsg' => '', 'data' => $data];
    }

    /**
     * Store a newly created resource in storage.新增专栏
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     * @author: tangkang@dodoca.com
     */
    public function store(Requests\Admin\KnowledgeColumnRequest $request)
    {
        $param = $request->all();
        $validate_res = $request->validate_param($param);
        if ($validate_res['errcode'] != 0) {
            return $validate_res;
        } else {
            $param = $validate_res['data'];
        }
        if (KnowledgeColumn::whereMerchantId($this->merchant_id)->whereName($param['name'])->whereIsDelete(1)->first(['id'])) {
            return ['errcode' => 1, 'errmsg' => '名称重复'];
        }
        $fields = Schema::getColumnListing((new KnowledgeColumn())->getTable());
        $data = [];
        foreach ($fields as $field) {
            if (isset($param[$field])) {
                $data[$field] = $param[$field];
            }
        }
        $data['merchant_id'] = $this->merchant_id;
        $res = KnowledgeColumn::insert_data($data);
        if (!empty($res)) {
            return ['errcode' => 0, 'errmsg' => '新增成功'];
        } else {
            return ['errcode' => 1, 'errmsg' => '新增失败'];
        }
    }

    /**
     * Display the specified resource.专栏详情
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     * @author: tangkang@dodoca.com
     */
    public function show($id)
    {
        $data = KnowledgeColumn::get_data_by_id($id, $this->merchant_id);
        return ['errcode' => 0, 'errmsg' => '', 'data' => $data];
    }

    /**
     * 专栏内容清单
     * @param $column_id
     * @return array
     * @author: tangkang@dodoca.com
     */
    public function showContentLists($column_id)
    {
        if (!empty($type = (int)request('type'))) {
            $param['status'] = $type;
        }
        if (empty($page = (int)request('page'))) {
            $param['page'] = $page;
        }
        if (empty($perPage = (int)request('perPage'))) {
            $param['perPage'] = $perPage;
        }
        if (!empty($name = request('name'))) {
            $param['name'] = $name;
        }
        $data = KnowledgeColumn::get_content_by_id($column_id, $this->merchant_id, $param);//接口丢失?
        return ['errcode' => 0, 'errmsg' => '', 'data' => $data];
    }

    /**
     * Update the specified resource in storage.更新专栏
     *
     * @param  \Illuminate\Http\Request $request
     * @param  int $id
     * @return \Illuminate\Http\Response
     * @author: tangkang@dodoca.com
     */
    public function update(Requests\Admin\KnowledgeColumnRequest $request, $id)
    {
        $param = $request->all();
        $validate_res = $request->validate_param($param);
        if ($validate_res['errcode'] != 0) {
            return $validate_res;
        } else {
            $param = $validate_res['data'];
        }
        if (KnowledgeColumn::whereMerchantId($this->merchant_id)->where('id', '<>', $id)->whereName($param['name'])->whereIsDelete(1)->first(['id'])) {
            return ['errcode' => 1, 'errmsg' => '名称重复'];
        }
        $fields = Schema::getColumnListing((new KnowledgeColumn())->getTable());
        $data = [];
        foreach ($fields as $field) {
            if (isset($param[$field])) {
                $data[$field] = $param[$field];
            }
        }
        unset($data['id'], $data['merchant_id'], $data['csale'], $data['period_number']);
        $res = KnowledgeColumn::updata_data($id, $this->merchant_id, $data);
        if (!empty($res)) {
            return ['errcode' => 0, 'errmsg' => '更新成功'];
        } else {
            return ['errcode' => 1, 'errmsg' => '更新失败'];
        }
    }

    /**
     * 上下架等
     * @param Request $request
     * @param $id
     * @return array
     * @author: tangkang@dodoca.com
     */
    public function update_field(Request $request, $id)
    {
        $param = $request->all();
        if (empty($param['status'] = (int)$param['status'])) {
            return ['errcode' => 1, 'errmsg' => '上下架参数必填'];
        } else {
            if (!in_array($param['status'], [KnowledgeColumn::STATUS_ONSHELVE, KnowledgeColumn::STATUS_UNSHELVE])) {
                return ['errcode' => 1, 'errmsg' => '上下架参数错误'];
            }
            $data['status'] = $param['status'];
        }

//        $fields = Schema::getColumnListing((new KnowledgeContent())->getTable());
//        $data = [];
//        $fields_fill = ['status'];
//        foreach ($fields as $field) {
//            if (in_array($field, $fields_fill) && isset($param[$field])) {
//                $data[$field] = $param[$field];
//            }
//        }
//        if (empty($param)) return ['errcode' => 1, 'errmsg' => '更新数据不能为空'];
        $res = KnowledgeColumn::updata_data($id, $this->merchant_id, $data);
        if (!empty($res)) {
            return ['errcode' => 0, 'errmsg' => '更新成功'];
        } else {
            return ['errcode' => 1, 'errmsg' => '更新失败'];
        }
    }

    /**
     * 添加内容到专栏
     * @param Request $request
     * @param $column_id
     * @return array
     * @author: tangkang@dodoca.com
     */
    public function store_rel(Request $request, $column_id)
    {
        $content_ids = $request->get('content_ids', 0);
        if (empty($content_ids)) {
            return ['errcode' => 1, 'errmsg' => '请选择要添加的内容'];
        }
        $content_ids = explode(',', $content_ids);
        $data['merchant_id'] = $this->merchant_id;
        $data['column_id'] = $column_id;
        $res = true;
        $num_success = 0;
        foreach ($content_ids as $content_id) {
            if (empty($content_id) || !is_numeric($content_id)) {
                \Log::info('添加内容到专栏：内容id参数非法，' . $content_ids . '，$column_id:' . $column_id);
                continue;
            }
            if (KnowledgeContentColumnRel::get_one_by_content_column_id($content_id, $column_id, $this->merchant_id)) continue;
            $data['content_id'] = $content_id;
            $res = KnowledgeContentColumnRel::insert_data($data);
            if (empty($res)) {
                \Log::info('添加内容到专栏失败，$content_id：' . $content_id . '，$column_id:' . $column_id);
            }
            $content_info  = KnowledgeContent::get_data_by_id($content_id, $this->merchant_id);
            if($content_info && $content_info['status'] == 1 ){ //状态为上架的数量
                $num_success++;
            }
        }
        KnowledgeColumn::incPeriodNumber($column_id, $this->merchant_id, $num_success);

        if (!empty($res)) {
            return ['errcode' => 0, 'errmsg' => '添加成功'];
        } else {
            return ['errcode' => 1, 'errmsg' => '添加失败'];
        }
    }

    /**
     * 移除专栏内容
     * @param Request $request
     * @param $column_id
     * @return array
     * @author: tangkang@dodoca.com
     */
    public function destroy_rel(Request $request, $column_id)
    {
        $content_ids = $request->get('content_ids', 0);
        if (empty($content_ids)) {
            return ['errcode' => 1, 'errmsg' => '请选择要移除的内容'];
        }
        $data['merchant_id'] = $this->merchant_id;
        $data['column_id'] = $column_id;
        $content_ids = explode(',', $content_ids);
        $num_success = 0;
        foreach ($content_ids as $content_id) {
            KnowledgeContentColumnRel::destroy_data($column_id, $content_id, $this->merchant_id);
            $content_info  = KnowledgeContent::get_data_by_id($content_id, $this->merchant_id);
            if($content_info && $content_info['status'] == 1 ){ //状态为上架的数量
                $num_success++;
            }
        }
        KnowledgeColumn::decPeriodNumber($column_id, $this->merchant_id, $num_success);
//        if (!empty($res)) {
        return ['errcode' => 0, 'errmsg' => '移除成功'];
//        } else {
//            return ['errcode' => 1, 'errmsg' => '移除失败'];
//        }
    }

    /**
     * Remove the specified resource from storage.删除专栏
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     * @author: tangkang@dodoca.com
     */
    public function destroy($id)
    {
        $res = KnowledgeColumn::delete_data($id, $this->merchant_id);
        KnowledgeContentColumnRel::delete_data($id, 'column_id', $this->merchant_id);
        if (empty($res)) {
            return ['errcode' => 1, 'errmsg' => '删除失败'];
        } else {
            return ['errcode' => 0, 'errmsg' => '删除成功'];
        }
    }


    //查询专栏id查询该专栏下的所属内容
    public function get_content_by_id(Request $request)
    {
        $id = $request->input('id', 0);
        $data = KnowledgeService::get_content_by_id($id, $this->merchant_id, $request->all());
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
        $data = KnowledgeService::get_content_by_ids($this->merchant_id, $ids);
        return ['errcode' => 1, 'errmag' => '操作成功', 'data' => $data];
    }

    public function two_array_transfer_img($data)
    {
        if (count($data) < 1) return [];
        foreach ($data as &$v) {
            $v['img'] = json_decode($v['img']);
        }
        return $data;
    }

}

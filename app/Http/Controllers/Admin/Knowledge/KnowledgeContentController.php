<?php

namespace App\Http\Controllers\Admin\Knowledge;

use App\Models\KnowledgeColumn;
use App\Models\KnowledgeContentColumnRel;
use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Models\KnowledgeContent;
use App\Services\KnowledgeService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use \Milon\Barcode\DNS2D;

class KnowledgeContentController extends Controller
{
    protected $merchant_id;

    public function __construct()
    {
        if (app()->isLocal()) {
            $this->merchant_id = 2;
        } else {
            $this->merchant_id = Auth::user()->merchant_id;
        }
    }

    /**
     * 获取预览链接二维码
     * @return array
     * @author: renruiqi@dodoca.com
     */
    public function qrcode(Request $request)
    {
        $content_id = request('content_id', 0);
        if (empty($content_id)) return ['errcode' => 1, 'msg' => '内容ID参数错误'];
        $url = ENV('APP_URL') . '/wap/content?id=' . $content_id . '&merchant_id=' . $this->merchant_id;
        $qrcode = 'data:image/png;base64,' . DNS2D::getBarcodePNG($url, "QRCODE", "10", "10");
        return ['errcode' => 0, 'data' => $qrcode];
    }

    /**
     * Display a listing of the resource.内容列表
     *
     * @return \Illuminate\Http\Response
     * @author: tangkang@dodoca.com
     */
    public function index()
    {
        $param = [];
        if (!empty($status = (int)request('status'))) {
            $param['status'] = $status;
        }
        if (!empty($page = (int)request('page'))) {
            $param['page'] = $page;
        }
        if (!empty($pagesize = (int)request('pagesize'))) {
            $param['pagesize'] = $pagesize;
        }
        if (!empty($name = request('name'))) {
            $param['name'] = $name;
        }
        if (!empty($type = request('type'))) {
            $param['type'] = $type;
        }
        $data = KnowledgeService::listsContent($param, $this->merchant_id);

        //所属专栏
        if (count($data['lists'])) {
            foreach ($data['lists'] as &$list) {
                $list['columns'] = KnowledgeContentColumnRel::get_lists_by_content_id($list['id'], $this->merchant_id);
            }
        }

        return ['errcode' => 0, 'errmsg' => '', 'data' => $data];
    }

    /**
     * Store a newly created resource in storage. 新增内容
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     * @author: tangkang@dodoca.com
     */
    public function store(Requests\Admin\KnowledgeContentRequest $request)
    {
        $param = $request->all();
        $validate_res = $request->validate_param($param);
        if ($validate_res['errcode'] != 0) {
            return $validate_res;
        } else {
            $param = $validate_res['data'];
        }
        if (KnowledgeContent::whereMerchantId($this->merchant_id)->whereName($param['name'])->whereIsDelete(1)->first(['id'])) {
            return ['errcode' => 1, 'errmsg' => '名称重复'];
        }
        $fields = Schema::getColumnListing((new KnowledgeContent())->getTable());
        $data = [];
        foreach ($fields as $field) {
            if (isset($param[$field])) {
                $data[$field] = $param[$field];
            }
        }
        unset($data['csale'], $data['is_delete']);
        if ($data['type'] == KnowledgeContent::TYPE_ARTICLE) unset($data['video_url']);
        $data['merchant_id'] = $this->merchant_id;
        $res = KnowledgeContent::insert_data($data);
        if (!empty($res)) {
            return ['errcode' => 0, 'errmsg' => '新增成功'];
        } else {
            return ['errcode' => 1, 'errmsg' => '新增失败'];
        }
    }

    /**
     * Display the specified resource.内容详情
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     * @author: tangkang@dodoca.com
     */
    public function show($id)
    {
        $data = KnowledgeContent::get_data_by_id($id, $this->merchant_id);
        return ['errcode' => 0, 'errmsg' => '', 'data' => $data];
    }

    /**
     * Update the specified resource in storage.更新内容
     *
     * @param  \Illuminate\Http\Request $request
     * @param  int $id
     * @return \Illuminate\Http\Response
     * @author: tangkang@dodoca.com
     */
    public function update(Requests\Admin\KnowledgeContentRequest $request, $id)
    {
        $param = $request->all();
        $validate_res = $request->validate_param($param);
        if ($validate_res['errcode'] != 0) {
            return $validate_res;
        } else {
            $param = $validate_res['data'];
        }
        $con_res = KnowledgeContent::get_data_by_id($id, $this->merchant_id);
        if (empty($con_res)) return ['errcode' => 1, 'errmsg' => '内容数据获取失败'];
        if (!empty($param['type']) && $con_res['type'] != $param['type']) return ['errcode' => 1, 'errmsg' => '内容类型不可修改'];
        if (KnowledgeContent::whereMerchantId($this->merchant_id)->where('id', '<>', $id)->whereName($param['name'])->whereIsDelete(1)->first(['id'])) {
            return ['errcode' => 1, 'errmsg' => '名称重复'];
        }
        $fields = Schema::getColumnListing((new KnowledgeContent())->getTable());
        $data = [];
        foreach ($fields as $field) {
            if (isset($param[$field])) {
                $data[$field] = $param[$field];
            }
        }
        if ($param['type'] == KnowledgeContent::TYPE_ARTICLE) unset($data['video_url']);
        unset($param['id'], $param['merchant_id'], $param['csale'], $param['type']);
        $res = KnowledgeContent::updata_data($id, $this->merchant_id, $data);
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
            if (!in_array($param['status'], [KnowledgeContent::STATUS_ONSHELVE, KnowledgeContent::STATUS_UNSHELVE])) {
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
        //如果状态相同则直接跳出
        $column_info  = KnowledgeContent::get_data_by_id($id, $this->merchant_id);
        if(isset($column_info) && $param['status'] == $column_info['status'] ){
            return ['errcode' => 0, 'errmsg' => '更新成功'];
        }
        $res = KnowledgeContent::updata_data($id, $this->merchant_id, $data);
        //该内容的所有专栏更新期数都要加一or减一
        $rel_data = KnowledgeContentColumnRel::where('merchant_id',$this->merchant_id)
                ->where('is_delete',1)
                ->where('content_id',$id)
                ->get();
        if($rel_data->count()){
            foreach($rel_data->toArray() as $v){
                if($param['status'] == KnowledgeContent::STATUS_ONSHELVE){//内容上架
                    KnowledgeColumn::incPeriodNumber($v['column_id'], $this->merchant_id);//自增期数
                }else{
                    KnowledgeColumn::decPeriodNumber($v['column_id'], $this->merchant_id);//自减期数
                }
            }
        }

/*        $action = $param['status'] ==KnowledgeContent::STATUS_ONSHELVE ? '+': '-';
        $updata_number = KnowledgeColumn::Join('knowledge_content_column_rel as rel','knowledge_column.id','=','rel.content_id')
            ->where('rel.content_id',$id);
        if($param['status'] == KnowledgeContent::STATUS_ONSHELVE){//内容上架
            $updata_number->increment('knowledge_column.period_number');
        }else{
            $updata_number->decrement('knowledge_column.period_number');
        }*/



        if (!empty($res)) {
            return ['errcode' => 0, 'errmsg' => '更新成功'];
        } else {
            return ['errcode' => 1, 'errmsg' => '更新失败'];
        }
    }

    /**
     * Remove the specified resource from storage.删除内容
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     * @author: tangkang@dodoca.com
     */
    public function destroy($id)
    {
        $content_info  = KnowledgeContent::get_data_by_id($id, $this->merchant_id);
        if($content_info && $content_info['status'] == 1 ){ //状态为上架
            //该内容的所有专栏更新期数都要减一
            $rel_data = KnowledgeContentColumnRel::where('merchant_id',$this->merchant_id)
                ->where('is_delete',1)
                ->where('content_id',$id)
                ->get();
            if($rel_data->count()){
                foreach($rel_data->toArray() as $v){
                    KnowledgeColumn::decPeriodNumber($v['column_id'], $this->merchant_id);//自减期数
                }
            }
        }
        $res = KnowledgeContent::delete_data($id, $this->merchant_id);

        KnowledgeContentColumnRel::delete_data($id, 'content_id', $this->merchant_id);//清除关联关系
        if (empty($res)) {
            return ['errcode' => 1, 'errmsg' => '删除失败'];
        } else {
            return ['errcode' => 0, 'errmsg' => '删除成功'];
        }
    }
}

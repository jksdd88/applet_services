<?php

namespace App\Http\Controllers\Admin\Form;

use App\Models\FormInfo;
use App\Services\FormFeedbackService;
use App\Services\FormService;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\Admin\FormRequest;
use Illuminate\Support\Facades\Schema;
use App\Services\DesignService;
use \Milon\Barcode\DNS2D;
use App\Models\FormTemplate;

class FormController extends Controller
{
    public function __construct()
    {
        if (app()->isLocal()) {
            $this->merchant_id = 2;
        } else {
            $this->merchant_id = Auth::user()->merchant_id;
        }
    }

    /**
     * 列表
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $FormService = new FormService();
        $form_service_res = $FormService->getLists(request()->all(), $this->merchant_id);
        return $form_service_res;
    }

    /**
     * 表单开启、暂停、删除
     * @return array
     * @author: tangkang@dodoca.com
     */
    public function updateField()
    {
        $request = request()->all();
        if (empty($request['id'])) return ['errcode' => 1, 'errmsg' => '缺少表单id参数'];
        if (!empty($request['is_delete']) && $request['is_delete'] == -1) {//删除
            $form_info_res = FormInfo::delete_data($request['id'], $this->merchant_id);
        } elseif (!empty($request['status']) && in_array($request['status'], [1, 2])) {//开启、暂停
            $form_info_res = FormInfo::update_data($request['id'], $this->merchant_id, ['status' => $request['status']]);
        } else {
            return ['errcode' => 1, 'errmsg' => '获取参数非法'];
        }
        if (empty($form_info_res)) {
            return ['errcode' => 1, 'errmsg' => '操作失败'];
        } else {
            return ['errcode' => 0, 'errmsg' => '操作成功'];
        }
    }

    /**
     * 新增表单
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(FormRequest $request)
    {
        $param = $request->all();
        $validate_res = $request->validateForm();
        if ($validate_res['errcode'] != 0) return $validate_res;
        $name_res = FormInfo::where('merchant_id', $this->merchant_id)
            ->where('name', $param['name'])->where('is_delete', 1)->count();
        if (!empty($name_res)) return ['errcode' => 1, 'errmsg' => '已存在该表单名称，请重新命名。'];
        $fields = Schema::getColumnListing('form_info');
        foreach ($fields as $field) {
            if ($field != 'id' && isset($param[$field])) {
                if ($field == 'components' || $field == 'submit_btn') {
                    $createData[$field] = json_encode($param[$field], JSON_UNESCAPED_UNICODE);
                } else {
                    $createData[$field] = $param[$field];
                }
            }
        }
        $createData['merchant_id'] = $this->merchant_id;
        $createData['status'] = 1;
        $createData['is_template'] = 2;
        $createData['is_delete'] = 1;
        //检查表单类型是否正确
//        $FormService_res = call_user_func([new FormService(), 'checkLimitType'], $createData, $this->merchant_id);
        $FormService = new FormService();
        $FormService_res = $FormService->checkLimitType($createData, $this->merchant_id);
        if ($FormService_res['errcode'] != 0) return $FormService_res;

        $form_info_res = FormInfo::insertGetId($createData);
        if (empty($form_info_res)) return ['errcode' => 1, 'errmsg' => '新增失败'];
        if (!empty($param['form_template_id']) && (int)$param['form_template_id'] > 0) {
            FormTemplate::where('id', $param['form_template_id'])->limit(1)->increment('use_count');
        }
        return ['errcode' => 0, 'errmsg' => '新增成功', 'data' => $form_info_res];
    }

    /**
     * 更新表单
     * @param FormRequest $request
     * @param $id
     * @return array
     * @author: tangkang@dodoca.com
     */
    public function update(FormRequest $request, $id)
    {
        $param = $request->all();
        $validate_res = $request->validateForm();
        if ($validate_res['errcode'] != 0) return $validate_res;
        $form_info_data = FormInfo::get_data_by_id($id, $this->merchant_id);
        if (empty($form_info_data)) return ['errcode' => 1, 'errmsg' => '查询表单数据失败'];
        $name_res = FormInfo::where('merchant_id', $this->merchant_id)
            ->where('name', $param['name'])->where('id', '<>', $id)->where('is_delete', 1)->count();
        if (!empty($name_res)) return ['errcode' => 1, 'errmsg' => '已存在该表单名称，请重新命名。'];
        $fields = Schema::getColumnListing('form_info');
        foreach ($fields as $field) {
            if ($field != 'id' && isset($param[$field])) {
                if ($field == 'components' || $field == 'submit_btn') {
                    $createData[$field] = json_encode($param[$field], JSON_UNESCAPED_UNICODE);
                } else {
                    $createData[$field] = $param[$field];
                }
            }
        }
        $createData['status'] = 1;
        $FormFeedbackService = new FormFeedbackService();
        $FormService_res = $FormFeedbackService->getTotalFeedbackTimes(['form_id' => $id, ''], $this->merchant_id);//所有member_id的总反馈量
        if ($FormService_res['errcode'] != 0) return $FormService_res;
        if (!empty($createData['feedback_maximum']) && $createData['feedback_maximum'] <= $FormService_res['data']) {
            $createData['status'] = 5;
        }
        if (!empty($createData)) {
            $update_data_tem = array_merge($form_info_data->toArray(), $createData);
            //检查类型
            $FormService = new FormService();
            $FormService_res = $FormService->checkLimitType($update_data_tem, $this->merchant_id);
            if ($FormService_res['errcode'] != 0) return $FormService_res;
        }
        //暂停的始终暂停
        if ($param['status'] == 2) {
            $createData['status'] = 2;
        }
        unset($createData['merchant_id']);
        $form_info_res = FormInfo::update_data($id, $this->merchant_id, $createData);
        if (empty($form_info_res)) return ['errcode' => 1, 'errmsg' => '更新失败'];
        return ['errcode' => 0, 'errmsg' => '更新成功', 'data' => $id];
    }

    /**
     * 表单详情
     * Display the specified resource.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function show($id, DesignService $designService)
    {
        $FormService = new FormService();
        $form_service_res = $FormService->getDetail(['form_id' => $id], $this->merchant_id, $designService);
        return $form_service_res;
    }

    /**
     * 获取预览链接二维码
     * @return array
     * @author: tangkang@dodoca.com
     */
    public function qrcode()
    {
        $form_id = request('form_id', 0);
        if (empty($form_id)) return ['errcode' => 1, 'msg' => '表单ID参数错误'];
        $url = ENV('APP_URL') . '/wap/form?id=' . $form_id . '&merchant_id=' . $this->merchant_id;
        $qrcode = 'data:image/png;base64,' . DNS2D::getBarcodePNG($url, "QRCODE", "10", "10");
        return ['errcode' => 0, 'data' => $qrcode];
    }
}

<?php

namespace App\Http\Controllers\Weapp\Form;

use App\Services\DesignService;
use App\Services\FormFeedbackService;
use App\Services\FormService;
use App\Http\Controllers\Controller;
use App\Facades\Member;

class FormController extends Controller
{
    private $member_id;
    private $merchant_id;
    private $weapp_id;

    public function __construct()
    {
        if (app()->isLocal()) {
            $this->merchant_id = 2;         //商户id
            $this->member_id = 12;         //会员id
            $this->weapp_id = 2;         //会员id
        } else {
            $this->merchant_id = Member::merchant_id();         //商户id
            $this->member_id = Member::id();         //会员id
            $this->weapp_id = Member::weapp_id();         //会员id
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
        $param = request()->all();
        if (empty($this->member_id)) return ['errcode' => 1, 'errmsg' => '获取会员id失败'];
        $param['member_id'] = $this->member_id;
        $form_service_res = $FormService->getLists($param, $this->merchant_id);
        return $form_service_res;
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
        $FormFeedbackService = new FormFeedbackService();
        $param = request()->all();
        $param['form_id'] = $id;
        $param['member_id'] = $this->member_id;
        $form_service_res = $FormService->getDetail($param, $this->merchant_id, $designService);
        if (empty($form_service_res) || !isset($form_service_res['errcode'])) return ['errcode' => 1, 'errmsg' => '表单数据查询失败'];
        if ($form_service_res['errcode'] != 0) return $form_service_res;
        if ($form_service_res['errcode'] == 0 && $form_service_res['data']['is_delete'] == -1) {
            return ['errcode' => 1, 'errmsg' => '表单已关闭'];
        }
        $FormFeedbackService_res = $FormFeedbackService->getFeedbackTimes($param, $this->merchant_id);
        if (empty($FormFeedbackService_res) || $FormFeedbackService_res['errcode'] != 0) return $FormFeedbackService_res;
        $form_service_res['member_feedback_times'] = $FormFeedbackService_res['data'];
        $FormService->addFormViewNum($this->member_id, $id, $this->weapp_id, $this->merchant_id);
        return $form_service_res;
    }
}

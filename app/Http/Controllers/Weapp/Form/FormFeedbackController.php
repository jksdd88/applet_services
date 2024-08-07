<?php

namespace App\Http\Controllers\Weapp\Form;

use App\Models\FormFeedback;
use App\Models\FormInfo;
use App\Services\DesignService;
use App\Services\FormFeedbackService;
use App\Http\Controllers\Controller;
use App\Facades\Member;
use App\Services\WeixinMsgService;
use Illuminate\Support\Facades\Schema;

class FormFeedbackController extends Controller
{
    private $member_id;
    private $merchant_id;

    public function __construct()
    {
        if (app()->isLocal()) {
            $this->merchant_id = 2;         //商户id
            $this->member_id = 12;         //会员id
            $this->weapp_id = 2;         //会员id
            $this->appid = 2;         //appid
        } else {
            $this->merchant_id = Member::merchant_id();         //商户id
            $this->member_id = Member::id();         //会员id
            $this->weapp_id = Member::weapp_id();         //会员id
            $this->appid = Member::appid();         //appid
        }
    }

    /**
     * 反馈记录列表
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $param = request()->all();
        if (empty($this->member_id)) return ['errcode' => 1, 'errmsg' => '获取会员id失败'];
        $param['member_id'] = $this->member_id;
        $FormService = new FormFeedbackService();
        $form_service_res = $FormService->getLists($param, $this->merchant_id);
        return $form_service_res;
    }

    /**
     * 提交反馈
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store()
    {
        $data = request()->all();
        $data = self::validateFeedbackData($data);
        if ($data['errcode'] != 0) return $data;
        $data = $data['data'];
        $member_res = Member::get();
        if (app()->isLocal()) {
            $member_res['name'] = 'test';
        } else {
            if (empty($member_res) || empty($member_res['name'])) return ['errcode' => 1, 'errmsg' => '获取会员昵称失败'];
        }
        $create_data = [
            'merchant_id' => $this->merchant_id,
            'member_id' => $this->member_id,
            'nickname' => $member_res['name'],
            'wxinfo_id' => $this->weapp_id,
            'form_id' => $data['form_id'],
            'status' => $data['status'],
            'component_values' => json_encode($data['component_values'], JSON_UNESCAPED_UNICODE),
            'is_delete' => 1,
            'feedback_check' => $data['forminfo_res']['feedback_check'],
        ];
        $feedback_res = FormFeedback::insertGetId($create_data);
        if (empty($feedback_res)) return ['errcode' => 1, 'errmsg' => '反馈失败'];

        $WeixinMsgService = new WeixinMsgService();
        $msg_param = [
            'merchant_id' => $this->merchant_id,
            'member_id' => $this->member_id,
            'appid' => $this->appid,
            'id' => $data['form_id'],
            'feedback_id' => $feedback_res,
            'content' => $data['forminfo_res']['name'] . '已提交成功！',
            'time' => date('Y-m-d H:i:s'),
        ];
        $WeixinMsgService->submit($msg_param);
        return ['errcode' => 0, 'errmsg' => '操作成功'];
    }

    //更新
    public function update($id)
    {
        $data = request()->all();
        $data = self::validateFeedbackData($data, true);
        if ($data['errcode'] != 0) return $data;
        $data = $data['data'];
        $update_data = [
            'component_values' => json_encode($data['component_values'], JSON_UNESCAPED_UNICODE),
        ];
        $FormFeedback_res = FormFeedback::get_data_by_id($id, $this->merchant_id);
        if ($FormFeedback_res['status'] == 2 || $FormFeedback_res['status'] == 4) {
            return ['errcode' => 1, 'errmsg' => '反馈记录已不可修改'];
        }
        $res = FormFeedback::update_data($id, $this->merchant_id, $update_data);
        if ($res !== false) {
            return ['errcode' => 0, 'errmsg' => '操作成功'];
        } else {
            return ['errcode' => 1, 'errmsg' => '操作失败'];
        }
    }

    /**
     * 反馈提交验证
     * @param $data
     * @return array
     * @author: tangkang@dodoca.com
     */
    public function validateFeedbackData($data, $update = false)
    {
        if (empty($data['form_id'])) return ['errcode' => 1, 'errmsg' => '缺少表单id参数'];
        if (empty($data['component_values'])) return ['errcode' => 1, 'errmsg' => '请填写信息后再提交'];

        $FormFeedbackService = new FormFeedbackService();
        $timesParam = [
            'member_id' => $this->member_id,
            'form_id' => $data['form_id'],
        ];
        $forminfo_res = FormInfo::get_data_by_id($data['form_id'], $this->merchant_id);
        //判断表单是否可反馈（检查表单是否正常 && 反馈次数限制 && 反馈上限）
        if (!$update) {//新增
            $times_res = $FormFeedbackService->canFeedback($timesParam, $this->merchant_id);
            if ($times_res['errcode'] != 0) return $times_res;
        } else {
            if (empty($forminfo_res)) return ['errcode' => 1, 'errmsg' => '表单不存在'];
            if ($forminfo_res['is_delete'] == -1) return ['errcode' => 1, 'errmsg' => '表单已删除，无法提交反馈'];
            if ($forminfo_res['status'] == 3) {//暂停
                return ['errcode' => 1, 'errmsg' => '已结束'];
            }
            if ($forminfo_res['period_type'] == 2) {//有效期。1：永久有效，2：期限内
                if ($forminfo_res['start_time'] > date('Y-m-d H:i:s')) return ['errcode' => 1, 'errmsg' => '未开始'];
                if ($forminfo_res['end_time'] <= date('Y-m-d H:i:s')) return ['errcode' => 1, 'errmsg' => '已结束'];
            }
        }
        //兼容修改前
        $data['forminfo_res'] = $forminfo_res;
        if (empty($data['forminfo_res']) || $data['forminfo_res']['is_delete'] == -1) return ['errcode' => 1, 'errmsg' => '表单已关闭'];
        if ($data['forminfo_res']['feedback_check'] == 2) {//需要审核
            $data['status'] = 3;//待审核
        } elseif ($data['forminfo_res']['feedback_check'] == 1) {
            $data['status'] = 1;//提交成功
        } else {
            $data['status'] = 0;//异常
        }
        $flag = false;
        foreach ($data['component_values'] as $key => $value) {
            if ($value['value'] != '') {
                $flag = true;
                break;
            }
        }
        if (!$flag) return ['errcode' => 1, 'errmsg' => '请填写信息后再提交'];
        return ['errcode' => 0, 'errmsg' => 'ok', 'data' => $data];
    }

    /**
     * 反馈详情（查看用，用户提交时的表单信息）
     * Display the specified resource.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function show($id, DesignService $designService)
    {
        $FormFeedbackService = new FormFeedbackService();
        $param = request()->all();
        $param['id'] = $id;
        $param['member_id'] = $this->member_id;
        $form_service_res = $FormFeedbackService->getDetail($param, $this->merchant_id, $designService);
        return $form_service_res;
    }

    /**
     * 反馈详情（编辑用，最新表单信息）
     * Display the specified resource.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function showEdit($id, DesignService $designService)
    {
        $FormFeedbackService = new FormFeedbackService();
        $param = request()->all();
        $param['id'] = $id;
        $param['member_id'] = $this->member_id;
        $form_service_res = $FormFeedbackService->getDetail($param, $this->merchant_id, $designService);
        return $form_service_res;
    }

    /**
     * 根据表单id获取会员反馈次数
     * @return array
     * @author: tangkang@dodoca.com
     */
    public function getTimes()
    {
        $FormFeedbackService = new FormFeedbackService();
        $param = request()->all();
        $param['member_id'] = $this->member_id;
        if (empty($param['member_id'])) return ['errcode' => 1, 'errmsg' => '会员id参数错误'];
        $FormFeedbackService_res = $FormFeedbackService->getFeedbackTimes($param, $this->merchant_id);
        return $FormFeedbackService_res;
    }
}

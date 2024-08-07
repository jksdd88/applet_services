<?php

namespace App\Http\Controllers\Admin\Form;

use App\Models\FormFeedback;
use App\Models\FormInfo;
use App\Services\DesignService;
use App\Services\FormFeedbackService;
use Illuminate\Http\Request;
use App\Http\Requests;
use App\Http\Controllers\Controller;
use Maatwebsite\Excel\Excel;
use Illuminate\Support\Facades\Auth;

class FormFeedbackController extends Controller
{
    private $merchant_id;

    public function __construct()
    {
        if (app()->isLocal()) {
            $this->merchant_id = 2;
        } else {
            $this->merchant_id = Auth::user()->merchant_id;
        }
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $request_data = request()->all();
        $FormFeedbackService = new FormFeedbackService();
        $form_feedback_res = $FormFeedbackService->getLists($request_data, $this->merchant_id);
        return $form_feedback_res;
    }

    /**
     * Display the specified resource.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request, $id, DesignService $designService)
    {
        $FormFeedbackService = new FormFeedbackService();
        $param = $request->all();
        $param['id'] = $id;
        $form_feedback_res = $FormFeedbackService->getDetail($param, $this->merchant_id, $designService);
//        if (!empty($form_feedback_res) && $form_feedback_res['errcode'] == 0) {
//            $form_feedback_res['data']['components'] = $form_feedback_res['data']['feedback']['component_values'];
//        }
        return $form_feedback_res;
    }

    /**
     * 备注、取消、审核通过
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {

        $status = $request->get('status', 0);
        $remark = $request->get('remark', 0);
        if (!in_array($status, [2, 4]) && empty($remark)) {
            return ['errcode' => 1, 'errmsg' => '参数错误'];

        }
        if (in_array($status, [2, 4])) {
            $FormFeedback_res = FormFeedback::get_data_by_id($id,$this->merchant_id);
            if($FormFeedback_res['status'] == 2){
                return ['errcode' => 1, 'errmsg' => '反馈记录为拒绝状态，不可再修改状态'];
            }
            $data['status'] = $status;
        }
        if (!empty($request->get('remark', 0))) {
            $data['remark'] = $remark;
        }
        $res = FormFeedback::update_data($id, $this->merchant_id, $data);
        if (empty($res)) return ['errcode' => 1, 'errmsg' => '操作失败'];
        return ['errcode' => 0, 'errmsg' => '操作成功'];
    }

    /**
     * 反馈记录下载
     * @param Excel $excel
     * @author: tangkang@dodoca.com
     * @throws \Maatwebsite\Excel\Exceptions\LaravelExcelException
     */
    public function download(Excel $excel)
    {
        //编码
        $param = request()->all();
        $FormFeedbackService = new FormFeedbackService();
        $form_feedback_res = $FormFeedbackService->downloadLists($param, $this->merchant_id);
        if (empty($form_feedback_res) || $form_feedback_res['errcode'] != 0) return $form_feedback_res;
        $download_data = $form_feedback_res['data'];
        if (empty($download_data)) $download_data[] = ['提交人' => '', '提交时间' => '', '小程序' => '', '备注' => ''];
        if (empty($param['form_id'])) return ['errcode' => 1, 'errmsg' => '缺少表单id参数'];
        $form_info_res = FormInfo::get_data_by_id($param['form_id'], $this->merchant_id);
        $file_name = $form_info_res['name'] . '反馈记录' . date('Y/m/d');
        $callback = function ($excel) use ($download_data) {
            $excel->sheet('export', function ($sheet) use ($download_data) {
                $sheet->fromArray($download_data);
            });
        };
        $excel->create($file_name, $callback)->export('xls');
    }
}

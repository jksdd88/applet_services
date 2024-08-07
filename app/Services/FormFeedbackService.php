<?php
/**
 * Created by PhpStorm.
 * User: tang
 * Date: 2017-12-06
 * Time: 9:32
 */

namespace App\Services;

use App\Models\FormFeedback;
use App\Models\FormInfo;
use App\Models\WeixinInfo;
use App\Utils\CommonApi;

class FormFeedbackService
{
    /**
     * 反馈列表
     * @param $request_data
     * @param $merchant_id
     * @return array
     * @author: tangkang@dodoca.com
     */
    public function getLists($request_data, $merchant_id)
    {
        if (!(($query = self::getListQuery($request_data, $merchant_id)) instanceof \Illuminate\Database\Eloquent\Builder)) return $query;

        $pagesize = empty($request_data['pagesize']) ? 10 : $request_data['pagesize'];
        $page = empty($request_data['page']) ? 1 : $request_data['page'];

        $data['_count'] = $query->count();
        $fields = [
            'id',
            'nickname',
            'wxinfo_id',
            'form_id',
            'status',
            'created_time',
            'updated_time',
            'feedback_check',
        ];
        $data['lists'] = $query->forPage($page, $pagesize)->latest('created_time')->get($fields);
        foreach ($data['lists'] as &$list) {
            $forminfo_res = FormInfo::get_data_by_id($list['form_id'], $merchant_id);
//            $list['forminfo']=$forminfo_res;
            $list['form_name'] = empty($forminfo_res) ? 'Error' : $forminfo_res['name'];
            $weixin_info_res = WeixinInfo::check_one_id($list['wxinfo_id']);
            $list['wxinfo_name'] = empty($weixin_info_res) ? '-' : $weixin_info_res['nick_name'];
        }
        return ['errcode' => 0, 'errmsg' => 'OK', 'data' => $data];
    }

    /**
     * 下载反馈列表
     * @return array
     * @author: tangkang@dodoca.com
     */
    public function downloadLists($request_data, $merchant_id)
    {
        if (!(($query = self::getListQuery($request_data, $merchant_id)) instanceof \Illuminate\Database\Eloquent\Builder)) return $query;
        $fields = [
            'nickname',
            'wxinfo_id',
            'remark',
            'created_time',
            'component_values',
        ];
        $feedback_datas = $query->latest('created_time')->get($fields);
        if ($feedback_datas->isEmpty()) return ['errcode' => 0, 'errmsg' => 'OK', 'data' => []];
        $data = [];
        $form_info = FormInfo::get_data_by_id($request_data['form_id'], $merchant_id);
        $components = json_decode($form_info['components'], true);
        foreach ($feedback_datas as $k => $value) {
            $weixin_info_res = WeixinInfo::get_one('id', $value['wxinfo_id'], 1);
            $value['nickname'] = (string)$value['nickname'];
            $value['nickname'] = @iconv("utf-8", "gbk", $value['nickname']);
            $value['nickname'] = @iconv("gbk", "utf-8", $value['nickname']);
            $data[$k]['提交人'] = $value['nickname'];
            $data[$k]['提交时间'] = $value['created_time'];
            $data[$k]['小程序'] = $weixin_info_res['nick_name'];
            $data[$k]['备注'] = $value['remark'];
            $component_values = json_decode($value['component_values'], true);
            foreach ($components as $key => $component) {
                if (isset($component['module_type']) && $component['module_type'] == 'design') {
                    unset($feedback_datas[$k]);
                    continue;//非表单组件不显示
                }
                if (!isset($component['content']['title'])) continue;

                if (isset($component_values[$key])) {
                    $tem_value = isset($component_values[$key]['value']) ? $component_values[$key]['value'] : '';
                    if (isset($component['type']) && $component['type'] == 'imageUpload') {
                        $img_domai = env('QINIU_STATIC_DOMAIN');
                        if(!empty($tem_value)){
                            foreach ($tem_value as &$value) {
                                $value = $img_domai . '/' . $value;
                            }
                        }
                        $tem_value = implode("\r\n", $tem_value);
                    }
                    $data[$k][$component['content']['title']] = $tem_value;
                } else {
                    $data[$k][$component['content']['title']] = '';
                }
            }
        }
//        dd($data);
        return ['errcode' => 0, 'errmsg' => 'OK', 'data' => $data];
    }

    /**
     * 列表参数验证、查询条件
     * @param $request_data
     * @param $merchant_id
     * @return array
     * @author: tangkang@dodoca.com
     */
    public function getListQuery($request_data, $merchant_id)
    {
        if (empty($merchant_id)) return ['errcode' => 1, 'errmsg' => '缺少商户id参数'];

        $where = [
            'merchant_id' => $merchant_id,
            'is_delete' => 1,
        ];
        if (isset($request_data['form_id']) && empty($request_data['form_id'])) {
            return ['errcode' => 1, 'errmsg' => '表单id参数错误'];
        }
        if (!empty($request_data['form_id'])) $where['form_id'] = $request_data['form_id'];

        if (isset($request_data['member_id'])) $where['member_id'] = $request_data['member_id'];
        if (!empty($request_data['wxinfo_id'])) $where['wxinfo_id'] = $request_data['wxinfo_id'];

        $query = FormFeedback::where($where);

        if (isset($request_data['created_time'])) {
            if (count($request_data['created_time']) != 2) return ['errcode' => 1, 'errmsg' => '请选择有效期范围的起止时间'];
            if ($request_data['created_time'][0] == $request_data['created_time'][1]) {
                $query->whereDate('created_time', '=', $request_data['created_time'][0]);
            } else {
                $query->whereDate('created_time', '>=', $request_data['created_time'][0])
                    ->whereDate('created_time', '<=', $request_data['created_time'][1]);
            }
        }
        if (!empty($request_data['nickname'])) $query->where('nickname', 'like', '%' . $request_data['nickname'] . '%');
        if (!empty($request_data['status'])) $query->where('status', $request_data['status']);

        return $query;
    }

    /**
     * 反馈详情
     * @param $param
     * @param $merchant_id
     * @return array
     * @author: tangkang@dodoca.com
     */
    public function getDetail($param, $merchant_id, $designService)
    {
        if (empty($merchant_id)) return ['errcode' => 1, 'errmsg' => '缺少商户id参数'];
        if (empty($param['id'])) return ['errcode' => 1, 'errmsg' => '缺少反馈记录id参数'];
        if (empty($param['form_id'])) return ['errcode' => 1, 'errmsg' => '缺少表单id参数'];
        $FormService = new FormService();
        $form_service_res = $FormService->getDetail(['form_id' => $param['form_id']], $merchant_id, $designService);
        if (empty($form_service_res) || $form_service_res['errcode'] != 0) return $form_service_res;
        $data = $form_service_res['data'];
        $form_feedback_res = FormFeedback::get_data_by_id($param['id'], $merchant_id);
        if (empty($form_feedback_res) || $form_feedback_res['form_id'] != $param['form_id']) return ['errcode' => 1, 'errmsg' => '无反馈详情数据'];
        if (isset($param['member_id']) && $form_feedback_res['member_id'] != $param['member_id']) return ['errcode' => 1, 'errmsg' => '用户未反馈数据'];
        $form_feedback_res['component_values'] = json_decode($form_feedback_res['component_values'], true);
        $tem = [];
        foreach ($form_feedback_res['component_values'] as $key => $value) {
            if (isset($value['module_type']) && $value['module_type'] != 'design') {
                $tem[$key] = $value;
            }
        }
        $form_feedback_res['component_values'] = $tem;
        $data['feedback'] = $form_feedback_res;
        return ['errcode' => 0, 'errmsg' => 'OK', 'data' => $data];
    }

    /**
     * 用户是否可反馈
     * @param $param
     * @param $merchant_id
     * @return array
     * @author: tangkang@dodoca.com
     */
    public function canFeedback($param, $merchant_id)
    {
        $times_res = self::getFeedbackTimes($param, $merchant_id);
        if ($times_res['errcode'] != 0) return $times_res;
        $forminfo_res = FormInfo::get_data_by_id($param['form_id'], $merchant_id);
        if (empty($forminfo_res)) return ['errcode' => 1, 'errmsg' => '表单不存在'];
        if ($forminfo_res['is_delete'] == -1) return ['errcode' => 1, 'errmsg' => '表单已删除，无法提交反馈'];
        if ($forminfo_res['status'] == 3) {//暂停
            return ['errcode' => 1, 'errmsg' => '已结束'];
        }
        if ($forminfo_res['period_type'] == 2) {//有效期。1：永久有效，2：期限内
            if ($forminfo_res['start_time'] > date('Y-m-d H:i:s')) return ['errcode' => 1, 'errmsg' => '未开始'];
            if ($forminfo_res['end_time'] <= date('Y-m-d H:i:s')) return ['errcode' => 1, 'errmsg' => '已结束'];
        }
        //类型检查
        $FormService = new FormService();
        $check_limit_res = $FormService->checkLimitType($forminfo_res, $merchant_id);
        if ($check_limit_res['errcode'] != 0) return $check_limit_res;

        if ($forminfo_res['limit_type'] != 1) {//限提交次数类型。1：每用户不限制，2：限制累计和每天，3：限制累计，4：限制每天
            if ($forminfo_res['limit_type'] == 2 || $forminfo_res['limit_type'] == 3) {
                if (!empty($forminfo_res['limit_maximum']) && $times_res['data']['total'] >= $forminfo_res['limit_maximum']) {
                    $msg = sprintf('您提交次数已达上限%s次', $forminfo_res['limit_maximum']);
                }
            }
            if ($forminfo_res['limit_type'] == 2 || $forminfo_res['limit_type'] == 4) {
                if (!empty($forminfo_res['limit_maximum_day']) && $times_res['data']['total_today'] >= $forminfo_res['limit_maximum_day']) {
                    $msg = sprintf('您今日提交次数已达上限%s次', $forminfo_res['limit_maximum_day']);
                }
            }
            if (isset($msg)) return ['errcode' => 1, 'errmsg' => $msg];
        }
        //反馈上限
        if (!empty($forminfo_res['feedback_maximum'])) {
            //获取所有用户、小程序环境提交的反馈总量
            unset($param['member_id'], $param['wxinfo_id']);
            $total_feedback_res = self::getTotalFeedbackTimes($param, $merchant_id);
            if ($total_feedback_res['errcode'] != 0) return $total_feedback_res;
            if ($total_feedback_res['data'] >= $forminfo_res['feedback_maximum']) {
                if ($forminfo_res['status'] != 5) {
                    FormInfo::update_data($param['form_id'], $merchant_id, ['status' => 5]);
                }
                return ['errcode' => 1, 'errmsg' => '已达反馈总数上限'];//反馈总反馈次数
            }
        } else {
            if (!isset($total_feedback_res['data'])) $total_feedback_res['data'] = 10000000;//无反馈上线
        }
        return ['errcode' => 0, 'errmsg' => 'OK', 'data' => $total_feedback_res['data']];
    }


    /**
     * 获取表单已反馈次数
     * @param $param
     * @param $merchant_id
     * @return array
     * @author: tangkang@dodoca.com
     */
    public function getFeedbackTimes($param, $merchant_id)
    {
        $today_res = self::getTodayFeedbackTimes($param, $merchant_id);
        $total_res = self::getTotalFeedbackTimes($param, $merchant_id);
        if (empty($total_res) || empty($today_res) || $total_res['errcode'] != 0 || $today_res['errcode'] != 0) {
            //记录异常
            $except = [
                'activity_id' => 0,
                'data_type' => 'getFeedbackTimes',
                'content' => '获取表单已反馈次数异常。今日返回值：' . json_encode($today_res, JSON_UNESCAPED_UNICODE) .
                    '。总计返回值：' . json_encode($total_res, JSON_UNESCAPED_UNICODE) .
                    '。参数：' . json_encode($param, JSON_UNESCAPED_UNICODE),
            ];
            CommonApi::errlog($except);
            if ($total_res['errcode'] != 0) return $total_res;
            if ($total_res['errcode'] != 0) return $today_res;
        }
        $times['total'] = $total_res['data'];
        $times['total_today'] = $today_res['data'];
        return ['errcode' => 0, 'errmsg' => 'OK', 'data' => $times];
    }

    /**
     * 获取今日反馈数量
     * @param $param
     * @param $merchant_id
     * @return array
     * @author: tangkang@dodoca.com
     */
    public function getTodayFeedbackTimes($param, $merchant_id)
    {
        if (empty($merchant_id)) return ['errcode' => 1, 'errmsg' => '缺少商户id参数'];
        if (empty($param['form_id'])) return ['errcode' => 1, 'errmsg' => '缺少表单id参数'];
        $where = [
            'merchant_id' => $merchant_id,
            'form_id' => $param['form_id'],
            'is_delete' => 1,
        ];
        if (isset($param['member_id']) && !empty($param['member_id'])) {
            $where['member_id'] = $param['member_id'];
        }
        if (isset($param['wxinfo_id']) && !empty($param['wxinfo_id'])) {
            $where['wxinfo_id'] = $param['wxinfo_id'];
        }
        $times = FormFeedback::where($where)->whereDate('created_time', '=', date('Y-m-d'))->count();
        return ['errcode' => 0, 'errmsg' => 'OK', 'data' => $times];
    }

    /**
     * 获取总反馈数量
     * @param $param
     * @param $merchant_id
     * @return array
     * @author: tangkang@dodoca.com
     */
    public function getTotalFeedbackTimes($param, $merchant_id)
    {
        if (empty($merchant_id)) return ['errcode' => 1, 'errmsg' => '缺少商户id参数'];
        if (empty($param['form_id'])) return ['errcode' => 1, 'errmsg' => '缺少表单id参数'];
        $where = [
            'merchant_id' => $merchant_id,
            'form_id' => $param['form_id'],
            'is_delete' => 1,
        ];
        if (isset($param['member_id']) && !empty($param['member_id'])) {
            $where['member_id'] = $param['member_id'];
        }
        if (isset($param['wxinfo_id']) && !empty($param['wxinfo_id'])) {
            $where['wxinfo_id'] = $param['wxinfo_id'];
        }
        $times = FormFeedback::where($where)->count();
        return ['errcode' => 0, 'errmsg' => 'OK', 'data' => $times];
    }


    /**
     *查询某日反馈+总量
     * @param $form_id int 必选 表单id
     * @param $merchant_id 必选 商家id
     * @param $wxinfo_id int 可选 小程序id
     * @param $deadline str 可选 截止时间(闭区间,包含今天) 格式xxxx-xx-xx
     * @return array
     * @author renruiqi@qq.com
     */
    public function getTodayFeedbackNum($form_id, $merchant_id, $wxinfo_id = 0, $deadline = null)
    {
        if ((int)$form_id < 1 || (int)$merchant_id < 1) return ['errcode' => 1, 'errmsg' => '参数格式错误'];
        $where = [
            'form_id' => $form_id,
            'merchant_id' => $merchant_id,
            'is_delete' => 1,
        ];
        if ((int)$wxinfo_id > 0) {
            $where['wxinfo_id'] = (int)$wxinfo_id;
        }
        if (isset($deadline) && (strtotime($deadline))) {
            $deadline_time = date('Y-m-d', strtotime($deadline));
        } else {
            $deadline_time = date('Y-m-d');
        }
        $count['all'] = FormFeedback::where($where)->whereDate('created_time', '<=', $deadline_time)->count();//总数
        $count['today'] = FormFeedback::where($where)->whereDate('created_time', '=', $deadline_time)->count();//今日数
        return ['errocde' => 0, 'errmsg' => 'ok', 'data' => $count];
    }
}

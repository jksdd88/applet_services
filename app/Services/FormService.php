<?php
/**
 * Created by PhpStorm.
 * User: tang
 * Date: 2017-12-06
 * Time: 9:32
 */

namespace App\Services;

use App\Models\FormCate;
use App\Models\FormInfo;
use App\Models\FormDailyView;
use App\Models\WeixinInfo;
use App\Utils\CommonApi;
use Illuminate\Support\Facades\Schema;
use App\Services\DesignService;

class FormService
{
    /**
     * 表单列表
     * @param $param
     * @return array
     * @author: tangkang@dodoca.com
     */
    public function getLists($param, $merchant_id)
    {
        if (empty($param)) return ['errcode' => 1, 'errmsg' => '参数不能为空'];
        if (empty($merchant_id)) return ['errcode' => 1, 'errmsg' => '商户ID不能为空'];
        $pagesize = empty($param['pagesize']) ? 10 : $param['pagesize'];
        $page = empty($param['page']) ? 1 : $param['page'];
        //条件
        $whereParam = [
            'merchant_id' => $merchant_id,
            'is_template' => 2,
            'is_delete' => 1,
        ];
        if (!empty($param['cate_id'])) {
            $whereParam['cate_id'] = $param['cate_id'];
        }
        $query = FormInfo::where($whereParam);
        $date_str = date('Y-m-d H:i:s');
        if (!empty($param['name'])) $query->where('name', 'like', '%' . $param['name'] . '%');
        if (!empty($param['status']) && (int)$param['status'] > 0) {
            $res_data = $this->searchStatus((int)$param['status'], $query);
            if ($res_data['errcode'] == 0) $query = $res_data['data'];
        }

        if (!empty($param['start_time']) && !empty($param['start_time'][0]) && !empty($param['start_time'][1])) {
            if ($param['start_time'][0] <= $param['start_time'][1]) {
                $query->where(function ($query_time) use ($param) {
                    $query_time->where('period_type', 1)->orWhere(function ($q) use ($param) {
                        $q->where('period_type', 2)->whereBetween('start_time', $param['start_time']);
                    });
                });
            } else {
                return ['errcode' => 1, 'errmsg' => '请重新选择时间范围'];
            }
        }

        //结果
        $data['_count'] = $query->count();
        if (!empty($data['_count']) && $data['_count'] < 1) {
            $data['lists'] = [];
            return ['errcode' => 0, 'errmsg' => 'OK', 'data' => $data];
        }
        $query->forPage($page, $pagesize)->latest('id');
        if (!isset($param['fields']) || empty($param['fields'])) $param['fields'] = ['*'];
        $fields_res = Schema::getColumnListing('form_info');
        foreach ($param['fields'] as $field) {
            if (!empty($field) && in_array($field, $fields_res)) {
                $fields[] = $field;
            }
        }
        if (empty($fields)) {
            $fields = [
                'id',
                'merchant_id',
                'cate_id',
                'name',
                'status',
                'period_type',
                'start_time',
                'end_time',
                'limit_type',
                'limit_maximum',
                'limit_maximum_day',
                'feedback_maximum',
                'created_time',
                'updated_time',
            ];
        }
        $data['lists'] = $query->get($fields);
        if (empty($data['lists'])) return ['errcode' => 1, 'errmsg' => '查询失败'];
        $data['lists'] = $data['lists']->toArray();
        $FormFeedbackService = new FormFeedbackService();

        foreach ($data['lists'] as &$list) {
            //分类名称
            if ($list['cate_id'] === 0) {
                $list['cate_name'] = '-';   //无分类默认显示
            } else {
                $FormCate_res = FormCate::get_data_by_id($list['cate_id'], $merchant_id);
                if (!empty($FormCate_res)) {
                    $list['cate_name'] = $FormCate_res['name'];
                } else {
                    $list['cate_name'] = 'Error';
                }
            }

            $FormService_res = $FormFeedbackService->getTotalFeedbackTimes(['form_id' => $list['id']], $merchant_id);
            if ($FormService_res['errcode'] != 0) {
                $list['feedback_times'] = 'Error';
            } else {
                $list['feedback_times'] = $FormService_res['data'];
            }

            //表单状态值
            $list['status'] = $this->setStatus($list);

        }
        $data['server_time'] = date('Y-m-d H:i:s');
        return ['errcode' => 0, 'errmsg' => 'OK', 'data' => $data];
    }

    /**
     * 表单详情
     * @param $form_id
     * @param $merchant_id
     * @return array
     * @author: tangkang@dodoca.com
     */
    public function getDetail($param, $merchant_id, DesignService $designService)
    {
        $form_id = $param['form_id'];
        if (empty($form_id)) return ['errcode' => 1, 'errmsg' => '缺少表单id参数'];
        $from_info_res = FormInfo::get_data_by_id($form_id, $merchant_id);
        if (empty($from_info_res)) return ['errcode' => 1, 'errmsg' => '表单数据查询失败'];
        //状态status赋值
        $from_info_res['status'] = self::setStatus($from_info_res);//给表单赋状态值
        if ($from_info_res['status'] == 0) return ['errcode' => 1, 'errmsg' => '表单状态异常'];
        $from_info_res['submit_btn'] = json_decode($from_info_res['submit_btn'], true);
        $from_info_res['components'] = json_decode($from_info_res['components'], true);
        $from_info_res['components'] = $designService->handleComponent($merchant_id, $from_info_res['components']);
        $form_cate_res = FormCate::get_data_by_id($from_info_res['cate_id'], $merchant_id);
        if (!empty($form_cate_res)) {
            $from_info_res['cate_name'] = $form_cate_res['name'];
        } else {
            $from_info_res['cate_name'] = 'Error';
        }
        $from_info_res['server_time'] = date('Y-m-d H:i:s');
        $FormFeedbackService = new FormFeedbackService();
        unset($param['member_id']);
        $FormService_res = $FormFeedbackService->getTotalFeedbackTimes($param, $merchant_id);//所有member_id的总反馈量

        if ($FormService_res['errcode'] != 0) return $FormService_res;
        $from_info_res['feedback_times'] = $FormService_res['data'];
        if (!empty($from_info_res['feedback_maximum']) && $from_info_res['feedback_times'] >= $from_info_res['feedback_maximum']) {
            if($from_info_res['status'] != 5){
                FormInfo::update_data($param['form_id'], $merchant_id, ['status'=>5]);
            }
            $from_info_res['status'] = 5;
        }
        return ['errcode' => 0, 'errmsg' => 'OK', 'data' => $from_info_res];
    }

    /**
     * 给表单赋状态值
     * @param $form
     * @return int|string
     * @author: tangkang@dodoca.com
     */
    public function setStatus($form)
    {
        if (!is_array($form)) {
            if (empty($form->toArray())) return 0;
        }
        if ($form['status'] == 5) return 5;//关闭状态优先级最高
        $date_time = date('Y-m-d H:i:s');
        if ($form['period_type'] == 1) {//永久有效
            if ($form['status'] == 2) {
                return 2;//暂停
            } elseif ($form['status'] == 1) {
                return 4;//进行中
            }
        } elseif ($form['period_type'] == 2) {//期限内
            if ($form['start_time'] <= $date_time) {//开始区间 当前>开始
                if ($form['end_time'] > $date_time) {
                    if ($form['status'] == 2) {
                        return 2;//暂停
                    } elseif ($form['status'] == 1) {
                        return 4;//进行中
                    }
                } elseif ($form['end_time'] <= $date_time) {//结束区间 当前>结束    
                    return 5;//已结束
                }
            } else {
                return 3;//未开始
            }
        }
        return 0;
    }

    /**
     * 检查限提交次数类型是否正常
     * @param $forminfo_res 表单单条记录
     * @param $merchant_id
     * @return array
     * @author: tangkang@dodoca.com
     */
    public function checkLimitType($forminfo_res, $merchant_id)
    {
        if ($forminfo_res['limit_type'] != 1) {//限提交次数类型。1：每用户不限制，2：限制累计和每天，3：限制累计，4：限制每天
            if ($forminfo_res['limit_type'] == 2 || $forminfo_res['limit_type'] == 3) {
//                if ($forminfo_res['limit_maximum'] == 0) {
//                    $msg = '表单限提交次数类型错误，该类型下limit_maximum必须大于0';
//                }
            } elseif ($forminfo_res['limit_type'] == 2 || $forminfo_res['limit_type'] == 4) {
                if ($forminfo_res['limit_maximum_day'] == 0) {
                    $msg = '表单限提交次数类型错误，该类型下limit_maximum_day必须大于0';
                }
            }
            if (isset($msg)) {
                //记录异常
                if (empty($forminfo_res['form_id'])) $forminfo_res['form_id'] = 0;
                $except = [
                    'activity_id' => 0,
                    'data_type' => 'checkLimit',
                    'content' => $msg . '，请检查表单类型。form_ID：' . $forminfo_res['form_id'] . ',表单信息：' . json_encode($forminfo_res, JSON_UNESCAPED_UNICODE),
                ];
                CommonApi::errlog($except);
                return ['errcode' => 1, 'errmsg' => $msg];
            }
        }
        return ['errcode' => 0, 'errmsg' => 'OK'];
    }

    /**
     * 表单阅读数统计(加一)
     * @param $form_id
     * @param $merchant_id
     * @param $member_id
     * @param $wxinfo_id
     * @return array
     * @author: renruiqi@dodoca.com
     */
    public function addFormViewNum($member_id, $form_id, $wxinfo_id, $merchant_id)
    {
        if (!$member_id || !$form_id || !$wxinfo_id || !$merchant_id) {
            return ['errcode' => 0, 'errmsg' => '参数格式错误'];
        }
        $data = [
            'member_id' => $member_id,
            'form_id' => $form_id,
            'wxinfo_id' => $wxinfo_id,
            'merchant_id' => $merchant_id,
        ];
        FormDailyView::insert_data($data);
        return ['errcode' => 0, 'errmsg' => 'OK'];
    }

    public function searchStatus($status, $query)
    {
        if (!is_object($query)) return ['errcode' => 1, 'errmsg' => '参数格式错误'];
        $date_str = date('Y-m-d H:i:s');
        //表单状态判定  2:已暂停 3:未开始 4:已开始 5:已结束
        switch ((int)$status) {
            case 3:
                //状态为未开始  当前时间小于开始时间
                $query->where('period_type', 2)->where('status','<>',5)->whereDate('start_time', '>', $date_str);
                break;
            case 4:
                //状态为已开始  status=1 and (period_type=1 or (period_type =2 andstart_time>= $date_str and end_time<$date_str) )
                $query->where('status', 1)->where(function ($query_one) {
                    $query_one->where('period_type', 1)
                        ->orWhere(function ($query_two) {
                            $query_two->whereDate('start_time', '<=', date('Y-m-d H:i:s'))
                                ->whereDate('end_time', '>', date('Y-m-d H:i:s'))
                                ->where('period_type', 2);
                        });
                });
                break;
            case 2:
                //状态为暂停  status=2 and (period_type=1 or (period_type =2 and start_time>= $date_str and end_time<$date_str))
                $query->where('status', 2)->where(function ($query_one) {
                    $query_one->where('period_type', 1)
                        ->orWhere(function ($query_two) {
                            $query_two->whereDate('start_time', '<=', date('Y-m-d H:i:s'))
                                ->whereDate('end_time', '>', date('Y-m-d H:i:s'))
                                ->where('period_type', 2);
                        });
                });

                break;
            case 5:
                //结束
                // $query->where('period_type', 2)->whereDate('end_time', '<=', $date_str);
                $query->where(function($query_one){
                    $query_one->where('period_type', 2)
                    ->whereDate('end_time', '<=', date('Y-m-d H:i:s'))
                    ->Orwhere('status',5);
                });
                break;
            default:
                break;
        }
        return ['errcode' => 0, 'errmsg' => 'ok', 'data' => $query];
    }


}

<?php

namespace App\Http\Controllers\Admin\Credit;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Models\CreditDetail;
use App\Models\Member;
use App\Models\Shop;
use App\Models\MerchantSetting;
use App\Models\MerchantMonthCreditType;
use App\Models\MerchantDayCredit;
use App\Models\MerchantMember;
use Maatwebsite\Excel\Excel;
use App\Models\CreditRule;
use App\Models\Merchant;
use Illuminate\Support\Facades\Response;
use App\Services\CreditService;
use Illuminate\Support\Facades\DB;

class CreditController extends Controller {

    private $merchant_id;
    protected $CreditDetail; //积分详情
    protected $version;

    public function __construct(CreditDetail $CreditDetail, MerchantSetting $merchantSetting, Excel $excel, CreditRule $creditRule, CreditService $CreditService, Member $Member, Merchant $merchant) {
        $this->merchant_id = Auth::user()->merchant_id;
        //$this->merchant_id = 2;
        $this->excel = $excel;
        $this->model = $merchantSetting;
        $this->CreditDetail = $CreditDetail;
        $this->CreditRule = $creditRule;
        $this->CreditService = $CreditService;
        $this->Member = $Member;

        $merchant_info = $merchant->get_data_by_id($this->merchant_id);
        if ($merchant_info && in_array($merchant_info['version_id'], [1, 5, 6])) {
            $data['errcode'] = 20015;
            $data['errmsg'] = '您正在使用的版本尚未开通此功能，如需开通请联系您的销售顾问';
            return Response::json($data);
        }
    }

    /**
     * 积分列表
     */
    public function getCredits(Request $request) {
        $param = $request->all();
        $user = Auth::user();
        $merchant_id = 0;
        if (isset($user->id)) {
            $merchant_id = $user->id;
        }
        $merchant_id = 2;
        $wheres = array(
            array('column' => 'merchant_id', 'value' => $this->merchant_id, 'operator' => '='),
        );

        if (isset($param['member_id']) && $param['member_id']) {
            $wheres[] = array('column' => 'member_id', 'value' => $param['member_id'], 'operator' => '=');
        }

        //会员账号
        if (isset($param['user_id']) && $param['user_id']) {
            $member_id = $param['user_id'] - MEMBER_CONST;
            $wheres[] = array('column' => 'member_id', 'value' => $member_id, 'operator' => '=');
        }
        //手机号
        if (isset($param['phone_num']) && $param['phone_num']) {
             $mobiletmp = Member::where('mobile', $param['phone_num'])->where('merchant_id', $this->merchant_id)->pluck('id');
            // $phone[] = array('column' => 'mobile', 'value' => $param['phone_num'], 'operator' => '=');
            // $mobiletmp = $this->Member->get_data_list($phone, "id");
            $wheres[] = array('column' => 'member_id', 'value' => $mobiletmp, 'operator' => '=');
        }

        //时间范围(开始时间)
        if (isset($param['startDate']) && $param['startDate']) {
            $wheres[] = array('column' => 'created_time', 'value' => date('Y-m-d 00:00:00', strtotime($param['startDate'])), 'operator' => '>=');
        }
        //时间范围(结束时间)
        if (isset($param['endDate']) && $param['endDate']) {
            $wheres[] = array('column' => 'created_time', 'value' => date('Y-m-d 23:59:59', strtotime($param['endDate'])), 'operator' => '<=');
        }
        //积分类型
        if (isset($param['creditStyle']) && $param['creditStyle']) {
            if ($param['creditStyle'] == 1) {
                $wheres[] = array('column' => 'credit', 'value' => 0, 'operator' => '>');
            } else if ($param['creditStyle'] == 2) {
                $wheres[] = array('column' => 'credit', 'value' => 0, 'operator' => '<');
            }
        }
        //明细
        if (isset($param['detail']) && $param['detail']) {
            $wheres[] = array('column' => 'memo', 'value' => '%' . $param['detail'] . '%', 'operator' => 'like');
        }
        $result = array();

        $result['_count'] = $this->CreditDetail->get_data_count($wheres);
        $result['data'] = array();
        if ($result['_count'] > 0) {
            //排序
            $column = (isset($param['column']) && $param['column']) ? $param['column'] : 'id';
            if ($column == 'time') {
                $column = 'created_time';
            } else if ($column == 'change') {
                $column = 'credit';
            } elseif ($column == 'final') {
                $column = 'final_credit';
            }
            //echo $column;
            $direction = (isset($param['direction']) && $param['direction']) ? $param['direction'] : 'desc';

            //导出条数控制
            if (isset($param['export']) && $param['export'] == "1") {
                $limit_from = isset($param['limit_from']) ? intval($param['limit_from'])-1 : 0;
                $limit_to = isset($param['limit_to']) ? intval($param['limit_to']) : 500;

                $param['limit'] = $limit_to;
                $param['offset'] = $limit_from;
            }

            //分页信息

            $offset = isset($param['offset']) ? $param['offset'] : 10;
            $limit = isset($param['limit']) ? $param['limit'] : 10;
            $fields = 'id,merchant_id,nickname,member_id,pre_credit,credit,final_credit,type,memo,
            created_time';
            $result['data'] = $this->CreditDetail->get_data_list($wheres, $fields, $offset, $limit);
            //取手机号

            foreach ($result['data'] as &$data) {
                $data['user_id'] = $data['member_id'] + MEMBER_CONST;
                $data['mobile'] = Member::where('id', $data['member_id'])->pluck('mobile');
            }
        }
        if (isset($param['export']) && $param['export'] == "1") {
            $this->export($result['data']);
            exit;
        }

        return $result;
    }

    private function export($record_data) {
        if ($record_data) {
            foreach ($record_data as $record_per) {
                $record_per['nickname'] = str_replace('=', '', $record_per['nickname']);
                $record_per['final_credit'] = str_replace('=', '0', $record_per['final_credit']);
                $export_data[] = array(
                    '会员账号' => $record_per['member_id'] + MEMBER_CONST,
                    '昵称' => $record_per['nickname'],
                    '手机号' => $record_per['mobile'],
                    '变动值' => $record_per['credit'],
                    '变动后' => $record_per['final_credit'],
                    '时间' => $record_per['created_time'],
                    '明细' => $record_per['memo'],
                );
//                $export_data[] = array(
//                    '会员账号' => iconv('UTF-8',mb_detect_encoding($record_per['member_id'] + MEMBER_CONST, array('ASCII','UTF-8','GB2312','GBK','BIG5')),$record_per['member_id'] + MEMBER_CONST),
//                    '昵称' => iconv('UTF-8',mb_detect_encoding($record_per['nickname'], array('ASCII','UTF-8','GB2312','GBK','BIG5')),$record_per['nickname']),
//                    '手机号' => iconv('UTF-8',mb_detect_encoding($record_per['mobile'], array('ASCII','UTF-8','GB2312','GBK','BIG5')),$record_per['mobile']),
//                    '变动值' => iconv('UTF-8',mb_detect_encoding($record_per['credit'], array('ASCII','UTF-8','GB2312','GBK','BIG5')),$record_per['credit']),
//                    '变动后' => iconv('UTF-8',mb_detect_encoding($record_per['final_credit'], array('ASCII','UTF-8','GB2312','GBK','BIG5')),$record_per['final_credit']),
//                    '时间' => iconv('UTF-8',mb_detect_encoding($record_per['created_time'], array('ASCII','UTF-8','GB2312','GBK','BIG5')),$record_per['created_time']),
//                    '明细' => iconv('UTF-8',mb_detect_encoding($record_per['memo'], array('ASCII','UTF-8','GB2312','GBK','BIG5')),$record_per['memo'])
//                );
            }
        }else{
            $export_data[] = array(
            '会员账号' => '',
            '昵称' => '',
            '手机号' => '',
            '变动值' => '',
            '变动后' => '',
            '时间' => '',
            '明细' => ''
            );
        }
        $filename = '积分纪录' . date('YmdHis', time());
        $this->excel->create($filename, function($excel) use ($export_data) {
            $excel->sheet('export', function($sheet) use ($export_data) {
                $sheet->fromArray($export_data);
            });
        })->export('xls');

        unset($export_data);
    }

    public function postCreditRule(Request $request) {
        $param = $request->all();
        $user = Auth::user();
        $merchant_id = 0;
        if (isset($user->id)) {
            $merchant_id = $user->id;
        }

        $credit = 0;
        $type = '';

        foreach ($param['check'] as $k => $check) {

            switch ($k) {
                case 'phoneCheck':
                    $credit = $param['phoneCredit'];
                    $type = 1;
                    break;
                case 'confirmCheck':
                    $credit = $param['confirmCredit'];
                    $type = 3;
                    break;
                case 'shareCheck':
                    $credit = $param['shareCredit'];
                    $type = 4;
                    break;
            }

            //如果是满送
            if ($k == 'moneyCheck') {
                if ($param['moneyCredit']) {
                    CreditRule::where('type', '=', 2)->where('merchant_id', '=', $this->merchant_id)->delete();
                    foreach ($param['moneyCredit'] as $moneyCredit) {
                        $data = array(
                            'merchant_id' => $this->merchant_id,
                            'credit' => $moneyCredit['credit'],
                            'type' => 2,
                            'enabled' => $check,
                            'condition' => $moneyCredit['amount'],
                        );
                        $result = $this->CreditRule->insert_data($data);
                        //$result = CreditRule::insert_data($data);
                    }
                }
            } else {
                $where = array(
                    array('column' => 'merchant_id', 'value' => $this->merchant_id, 'operator' => '='),
                    array('column' => 'type', 'value' => $type, 'operator' => '=')
                );
                $result = $this->CreditRule->get_data_list($where);
                if (isset($result[0])) {
                    $result[0]["credit"] = $credit;
                    $result[0]["enabled"] = $credit <= 0 ? 0 : $check;
                    // $result = CreditRule::update_data($result[0]["id"], $this->merchant_id, $result[0]);
                    $result = $this->CreditRule->update_data($result[0]["id"], $this->merchant_id, $result[0]);
                } else {
                    $data = array(
                        'merchant_id' => $this->merchant_id,
                        'credit' => $credit,
                        'type' => $type,
                        'condition' => '',
                        'enabled' => $credit <= 0 ? 0 : 1
                    );
                    $result = $this->CreditRule->insert_data($data);
                    //$result = CreditRule::insert_data($data);
                }
            }
        }
        if (!isset($result->id)) {
            return Response::json(array('errcode' => 0, 'errcode' => '操作成功'));
        } else {
            return Response::json(array('errcode' => 10001, 'errcode' => '操作失败'));
        }
    }

    public function Setting(Request $request) {
        $data = $request->all();
		$udata = [
			'credit_rule'	=>	isset($data['credit_rule']) ? (int)$data['credit_rule'] : 0,
		];
		
		$result = MerchantSetting::update_data($this->merchant_id,$udata);
        if ($result) {
            return Response::json(array('success' => true));
        } else {
            return Response::json(array('error' => '失败', 'type' => 'setting', 'code' => '1000001'));
        }
    }
	
    //获取积分设置
    public function getSetting() {
        $result = MerchantSetting::get_data_by_id($this->merchant_id);        
        return $result;
    }
	
    public function getCreditSurvey() {
        $data = [];
        //积分渠道
        $data['sendData'] = MerchantMonthCreditType::where('merchant_id', $this->merchant_id)
                        ->where('credit', '>', 0)
                        ->whereIn('type', [1, 2, 3, 5])
                        ->select(DB::raw('sum(credit) as value'), 'type')
                        ->groupBy('type')
                        ->get()->toArray();
        foreach ($data['sendData'] as &$v) {
            $v['name'] = $this->typeToName($v['type']);
            unset($v['type']);
        }
        $data['sendCredit'] = MerchantMonthCreditType::where('merchant_id', $this->merchant_id)->whereIn('type', [1, 2, 3, 5])->sum('credit'); //总发放积分
        $data['expendCredit'] = MerchantMonthCreditType::where('merchant_id', $this->merchant_id)->whereIn('type', [1, 2, 3, 5])->sum('expend_credit'); //总消耗积分
        //积分按日显示
        $list = MerchantDayCredit::where('merchant_id', $this->merchant_id)->select('created_time', 'credit as sendCredit', 'expend_credit as spendCredit')->orderBy('created_time', 'desc')->take(7)->get()->toArray();
        foreach ($list as &$vs) {
            $vs['time'] = $vs['created_time'];
            unset($vs['created_time']);
        }
        //重新排序
        $created_atSort = [];
        foreach ($list as &$vsort) {
            $created_atSort[] = $vsort['time'];
        }
        array_multisort($created_atSort, SORT_ASC, $list);
        //rsort($list);
        $data['data'] = $list;
        return Response::json(array('errcode' => 0, 'data' => $data));
    }

    //送积分渠道
    public function typeToName($type) {
        switch ($type) {
            case 50:
                $name = '推荐关注';
                break;
            case 51:
                $name = '推荐推客';
                break;
            case 1:
                $name = '完善手机';
                break;
            case 2:
                $name = '下单送积分';
                break;
            case 3:
                $name = '主动确认收货';
                break;
            case 4:
                //$name = '晒单';
                $name = '积分抵扣';
                break;
            case 5:
                $name = '手动送积分'; //下单扣积分
                break;
            case 6:
                $name = '取消订单退积分'; //下单扣积分
                break;
            case 7:
                $name = '订单退款退积分'; //下单扣积分
                break;
            case 55:
                $name = '推文推客兑换';
                break;
            case 100:
                $name = '手动送积分';
                break;
            default:
                $name = '未知渠道';
        }
        return $name;
    }

    /**
     * 送积分
     * @param MerchantMemberService $merchantMemberService
     * @return mixed
     */
    public function putMemberCredit(Request $request) {
        $param = $request->all();
        $member_ids = $param['member_ids'];
        $credit = $param['credit'];
        if (strlen($credit) > 10) {
            return ['errcode' => 300062, 'errmsg' => '最多支持十位数字'];
        }

        $reason = isset($param['reason']) ? $param['reason'] : "";
        $memo = $credit < 0 ? '商家手动减' : '商家手动送';
        if ($credit == 0) {
            return ['errcode' => 30002, 'errmsg' => '修改积分不能为零'];
        }
        foreach ($member_ids as $id) {
            $array = array("give_credit" => $credit, "memo" => $memo . $credit . "积分(原因:" . $reason . ")");
            $res = $this->CreditService->giveCredit($this->merchant_id, $id, 5, $array);
        }
        return $res;
    }

    //积分获取
    public function getCreditRules() {
        $wheres = array(
            array('column' => 'merchant_id', 'value' => $this->merchant_id, 'operator' => '='),
        );

        $result['data'] = $this->CreditRule->get_data_list($wheres);
        $rules = array(
            'phoneCredit' => 0,
            'confirmCredit' => 0,
            'shareCredit' => 0,
            'moneyCredit' => array(),
            'check' => array(
                'phoneCheck' => 0,
                'confirmCheck' => 0,
                'shareCheck' => 0,
                'moneyCheck' => 0
            )
        );

        $moneyCredit = array();
        foreach ($result['data'] as &$data) {
            //完善手机
            if ($data["type"] == 1) {
                $rules['phoneCredit'] = $data["credit"];
                $rules['check']['phoneCheck'] = $data["enabled"];
            }
            //满送积分
            if ($data["type"] == 2) {
                $rules['check']['moneyCheck'] = $data["enabled"];
                $moneyCredit['amount'] = $data["condition"];
                $moneyCredit['credit'] = $data["credit"];
                $moneyCredit['id'] = $data["id"];
                $rules['moneyCredit'][] = $moneyCredit;
            }
            //主动确认收货
            if ($data["type"] == 3) {
                $rules['confirmCredit'] = $data["credit"];
                $rules['check']['confirmCheck'] = $data["enabled"];
            }
            //晒单分享
            if ($data["type"] == 4) {
                $rules['shareCredit'] = $data["credit"];
                $rules['check']['shareCheck'] = $data["enabled"];
            }
        }
        return $rules;
    }

}

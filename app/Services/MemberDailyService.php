<?php

namespace App\Services;

use App\Models\MemberDaily;
use App\Models\Member;
use Illuminate\Support\Facades\Auth;

/*
 * 会员统计信息service
 * shangyazhao@dodoca.com
 */
class MemberDailyService {

    protected $today = '';
    protected $model = '';

    function __construct(MemberDaily $memberDaily) {
        $this->model = $memberDaily;
        $this->today = date('Ymd');
    }

    public function getMemberStatDays($merchant_id,$limit_day = 7) {
        if($merchant_id==0){
            return;
        }
        $result = array();
        $end_day = strtotime($this->today);
        $start_day = $end_day - $limit_day * 3600 * 24;

        for ($day = $start_day; $day < $end_day; $day = $day + 3600 * 24) {
            $result[$day]['date'] = date('Y-m-d', $day);            
            $result[$day]['cmember'] = 0;
        }
        $wheres = array(
            array('column' => 'merchant_id', 'value' => $merchant_id, 'operator' => '='),
            array('column' => 'day_time', 'value' => $this->today, 'operator' => '<')
        );
        $data = $this->model->get_data_list($wheres,'*',0, $limit_day);        
        foreach ($result as $k => $_result) {
            if ($data) {
                foreach ($data as $_data) {
                    $strtotime = strtotime($_data['day_time']);
                    if ($k == $strtotime) {
                        $result[$k]['date'] = date('Y-m-d', $strtotime);
                        //$result[$k]['cturnover'] = $_data['add_trade_num'];                        
                        $result[$k]['cmember'] = $_data['add_num'];
                    }
                }
            }
        }
        return array_values($result);
    }
    
    //写入统计库信息
    public static function putMemberStatDay() {
        $yestoday = date("Ymd", strtotime("-1 day"));
        $today = date("Ymd");
        //查询商户
        $merchants = \DB::select("select id from merchant");
        if (!empty($merchants)) {
            $merchants = json_decode(json_encode($merchants), TRUE);
        }
        $model = new MemberDaily();
        $member=new Member();
        foreach ($merchants as $key => $value) {  //遍历商户写入统计库
            $wheres=array();
            $merchant_id = $value['id'];
            $wheres[] = array('column' => 'merchant_id', 'value' => $merchant_id, 'operator' => '=');
            $num_member = $member->get_data_count($wheres); //会员总数
            $wheres[] = array('column' => 'purchased_count', 'value' => 0, 'operator' => '>');
            $num_trade_member = $member->get_data_count($wheres); //成交会员总数数
            $wheres = array();
            $wheres[] = array('column' => 'merchant_id', 'value' => $merchant_id, 'operator' => '=');
            $wheres[] = array('column' => 'created_time', 'value' => date("Y-m-d"), 'operator' => '<');
            $wheres[] = array('column' => 'created_time', 'value' => date("Y-m-d", strtotime("-1 day")), 'operator' => '>=');
            $num_today = $member->get_data_count($wheres); //昨日新增会员数        
            $wheres[] = array('column' => 'purchased_count', 'value' => 1, 'operator' => '=');
            $num_yestoday = $member->get_data_count($wheres); //昨日新增成交会员数
            $data = $model->where(array('merchant_id' => $merchant_id, 'day_time' => $yestoday))->first();
            
            $putdata = array('merchant_id' => $merchant_id, 'add_num' =>$num_today, 'add_trade_num' => $num_yestoday, 'total' => $num_member, 'trade_total' => $num_trade_member, 'day_time' => $yestoday);
            if ($data) {                
                $where[] = array('column' => 'day_time', 'value' => $yestoday, 'operator' => '=');
                $where[] = array('column' => 'merchant_id', 'value' => $merchant_id, 'operator' => '=');
                $result = $model->update_data_by_where($yestoday, $merchant_id, $where, $putdata);
            } else {
               $result = $model->insert_data($putdata);
            }
        }
    }

}

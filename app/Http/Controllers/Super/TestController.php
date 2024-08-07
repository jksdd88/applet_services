<?php

/*
 * 会员模块后台
 * shangyazhao@dodoca.com
 *
 */

namespace App\Http\Controllers\Super;

use App\Http\Controllers\Controller;
use App\Models\IndustryDaily;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Auth;
use App\Models\Bargain;
use App\Models\BargainJoin;
use App\Models\BargainLaunch;
use App\Models\AloneActivityRecode;
use App\Models\Merchant;
use App\Models\DesignTemplatePage;
use App\Models\DesignTemplate;

class TestController extends Controller {


    public function __construct(Request $request) {
        $this->request = $request;
        $this->params = $request->all();
        $this->today = date('Y-m-d H:i:s');
    }

    public function ActionClose(){

        $data['merchant_id'] =  $this->request['merchant_id'];

        $data['id'] =  $this->request['id'];

        $data['goods_id'] =  $this->request['goods_id'];


        $this->ActionClose1($data);
        /*$this->getTradeDaily();*/
    }

    /**
     * 結束砍價活動
     *
     * @param string $merchant_id  商户ID
     * @param string $id  活動ID
     * @param string $goods_id  商品ID
     *
     * @return \Illuminate\Http\Response
     */

    public function ActionClose1($param){

        $merchant_id = isset($param['merchant_id']) ? intval($param['merchant_id']) : 0;

        $action_id   = isset($param['id']) ? intval($param['id']) : 0;

        $goods_id    = isset($param['goods_id']) ? intval($param['goods_id']) : 0;

        if($merchant_id && $merchant_id>0 && $action_id && $action_id>0 && $goods_id && $goods_id>0){      //商家手动结束

            $action = Bargain::get_data_by_id($action_id,$merchant_id);

            if(!$action){

                return ['errcode' => 10004, 'errmsg' => '活动不存在'];

            }
            if($action['status'] == -1){

                return ['errcode' => 10004, 'errmsg' => '活动不存在'];
            }

            $data['status'] = 3;

            $update_result = Bargain::update_data($action_id,$merchant_id,$data);

            $launch_data['status'] = 3;

            $all_launch_actions = BargainLaunch::where(array('bargain_id'=>$action_id,'status'=>1))->update($launch_data);    //结束所有当前的关联发起活动

            //更新私有活动记录表
            $activity_recode = [
                'finish_time' => date('Y-m-d H:i:s')
            ];

            $activity_update = AloneActivityRecode::where(array('merchant_id'=>$merchant_id,'goods_id'=>$goods_id,'alone_id'=>$action_id,'act_type'=>'bargain'))->update($activity_recode);

            if($update_result){

                return ['errcode' => 0, 'errmsg' => '活动已结束'];
            }

        }else{            //活动到期自动结束

            $fields = 'id,merchant_id,goods_id,status,is_delete,start_time,end_time,created_time,updated_time';

            $all_actions = Bargain::select(\DB::raw($fields))->where(array('is_delete'=>1,'status'=>1))->get();   //查询所有正在活动中的砍价活动

            foreach($all_actions as $key=>$value){

                if(strtotime($value['end_time']) <= strtotime($this->today)){

                    $data['status'] = 2;

                    $auto_update_result = Bargain::update_data($value['id'],$value['merchant_id'],$data);    //结束当前砍价活动

                    $launch_data['status'] = 3;

                    $all_launch_actions = BargainLaunch::where(array('bargain_id'=>$value['id'],'status'=>1))->update($launch_data);    //结束所有当前的关联发起活动

                    //更新私有活动记录表
                    $activity_recode = [
                        'finish_time' => date('Y-m-d H:i:s')
                    ];

                    $activity_update = AloneActivityRecode::where(array('merchant_id'=>$value['merchant_id'],'goods_id'=>$value['goods_id'],'alone_id'=>$value['id'],'act_type'=>'bargain'))->update($activity_recode);

                }else{

                    continue;
                }

            }

        }

    }


    /**
     * 結束砍價发起活动
     *
     * @return \Illuminate\Http\Response
     */

    public function LaunchClose(){

        $fields = 'id,merchant_id,member_id,bargain_id,status,start_time,end_time,created_time,updated_time';

        $all_actions = BargainLaunch::select(\DB::raw($fields))->where('status','=',1)->get();   //查询所有正在活动中的砍价活动

        foreach ($all_actions as $key=>$value){

            $bargain = Bargain::select('status')->where(array('id'=>$value['bargain_id'],'merchant_id'=>$value['merchant_id']))->get();

            if($bargain[0]['status'] == 2 || $bargain[0]['status'] == 3 || (strtotime($value['end_time']) < strtotime($this->today))){     //主活动到期或者当前活动到期

                $data['status'] = 3;

                BargainLaunch::update_data($value['id'],$value['merchant_id'],$value['bargain_id'],$data);

            }else{

                continue;
            }
        }
    }

    public function AutoStartAction(){

        $fields = 'id,merchant_id,goods_id,status,is_delete,start_time,end_time';

        $all_actions = Bargain::select(\DB::raw($fields))->where(array('is_delete'=>1,'status'=>0))->get();   //查询所有有效的未开始的砍价活动

        foreach($all_actions as $key=>$value){

            if(strtotime($value['start_time']) <= strtotime($this->today)  && strtotime($this->today) < strtotime($value['end_time'])){   //当前时间大于等于开始时间

                $data['status'] = 1;

                $auto_update_result = Bargain::update_data($value['id'],$value['merchant_id'],$data);    //开启当前砍价活动

            }else{

                continue;
            }

        }

    }


    public function getTradeDaily(){

        $daily_gmv_trade_fields = 'merchant.id ,sum(t2.amount) as amount';

        $daily_gmv_trade = Merchant::select(\DB::raw($daily_gmv_trade_fields))->where('merchant.status','<>',-1)
                                   // ->where('t2.created_time','>=',$todayStart)
                                    //->where('t2.created_time','<=',$todayEnd)
                                    ->leftJoin('trade as t2','merchant.id','=','t2.merchant_id')->groupby('merchant.id')->get();

        $daily_income_fields = 'merchant.id ,sum(t2.amount) as amount';

        $daily_income = Merchant::select(\DB::raw($daily_income_fields))->where('merchant.status','<>',-1)
                                    ->where('t2.pay_status','=',1)
                                   // ->where('t2.created_time','>=',$todayStart)
                                    //->where('t2.created_time','<=',$todayEnd)
                                    ->leftJoin('trade as t2','merchant.id','=','t2.merchant_id')->groupby('merchant.id')->get();

        $daily_daily_trades_fields = 'merchant.id ,sum(t2.amount) as amount';

        $daily_daily_trades = Merchant::select(\DB::raw($daily_daily_trades_fields))->where('merchant.status','<>',-1)
                                ->where('t2.pay_status','=',1)
                                ->where('t2.pay_type','=',1)
                                // ->where('t2.created_time','>=',$todayStart)
                                //->where('t2.created_time','<=',$todayEnd)
                                ->leftJoin('trade as t2','merchant.id','=','t2.merchant_id')->groupby('merchant.id')->get();

        /*//统计当日GMV
        $daily_gmv_trade = \DB::select('SELECT t1.id, sum(t2.amount) AS amount FROM
                                    (
                                        SELECT
                                            id
                                        FROM
                                            merchant
                                        WHERE
                                            status <> -1
                                        GROUP BY
                                            id
                                    )t1
                                    LEFT JOIN trade AS t2 ON t1.id = t2.merchant_id
                                    GROUP BY
                                    t1. id');
        //统计当日收入
        $daily_income = \DB::select('SELECT t1.id, sum(t2.amount) AS amount FROM
                                    (
                                        SELECT
                                            id
                                        FROM
                                            merchant
                                        WHERE
                                            status <> -1
                                        GROUP BY
                                            id
                                    )t1
                                    LEFT JOIN trade AS t2 ON t1.id = t2.merchant_id where 
                                    GROUP BY
                                    t1. id');*/

    }


    public function getInitNum(){

        $data['edit_num'] = intval($this->getNum());

        $template = DesignTemplate::where('edit_num',0)->update($data);

        $template_page = DesignTemplatePage::where('edit_num',0)->update($data);

        if($template && $template_page){

            echo "Success";exit;
        }

    }


    private function getNum(){
        $current_time = time();
        $start_time = strtotime('2018-02-06 20:00:00');
        return floor(($current_time - $start_time) / 200) ;
    }


}

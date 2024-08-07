<?php
/**
 * Created by lizhenheng.
 * User: Administrator
 * Date: 2018-02-22
 * Time: 上午 10:29
 */
namespace App\Http\Controllers\Admin\Printer;


use App\Http\Controllers\Controller;

use App\Utils\PrinterFc;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Config;
use App\Models\Printer;
use App\Models\PrintContent;
use Illuminate\Support\Facades\Auth;
use App\Services\OrderPrintService;


class PrinterController extends Controller{
    protected $request;
    protected $params;
    protected $merchant_id;

    public function __construct(Request $request) {
        $this->request = $request;
        $this->params = $request->all();
        $this->merchant_id=Auth::user()->merchant_id;
//        $this->merchant_id = 2;
    }

    /**
     * 获取小票机列表
     */
    public function getList(){
        $offset = isset($this->params['offset']) && $this->params['offset'] ? $this->params['offset'] : 0;
        $limit = isset($this->params['limit']) && $this->params['limit'] ? $this->params['limit'] : 10;
        $wheres = array(
            array('column' => 'is_delete', 'value' => 1, 'operator' => '='),
            array('column' => 'merchant_id', 'value' => $this->merchant_id, 'operator' => '=')
        );

        $list=Printer::get_data_list($wheres,"*",$offset,$limit);
        if($list){
            foreach ($list as $key=>$val){
                if($val['print_model']==2){
                    $list[$key]['print_model_name'] = '风驰';
                }
                $where = array(
                    array('column' => 'printer_id', 'value' => $val['id'], 'operator' => '='),
                    array('column' => 'merchant_id', 'value' => $this->merchant_id, 'operator' => '=')
                );
                $content = PrintContent::get_data_list($where,"*");
                $content_name = "";
                if($content){
                    foreach ($content as $k=>$v){
                        switch ($v['order_type'])
                        {
                            case 1:$content_name?$content_name=$content_name.",配送订单":$content_name="配送订单";break;
                            case 2:$content_name?$content_name=$content_name.",自提订单":$content_name="自提订单";break;
                            case 3:$content_name?$content_name=$content_name.",服务订单":$content_name="服务订单";break;
                            case 4:$content_name?$content_name=$content_name.",优惠买单":$content_name="优惠买单";break;
                        }
                    }
                }
                $list[$key]['print_content'] = $content_name;
            }
        }
        $count=Printer::get_data_count($wheres);
        $data['errcode'] = 0;
        $data['data'] = $list;
        $data['_count'] = $count['num'];
        return Response::json($data);
    }

    /**
     * 保存或修改数据
     */
    public function saveData(){
        $id = isset($this->params['id']) && $this->params['id'] ? $this->params['id'] : 0;
        $print_name = isset($this->params['print_name']) && $this->params['print_name'] ? $this->params['print_name'] : '';
        $print_model = isset($this->params['print_model']) && $this->params['print_model'] ? $this->params['print_model'] : 0;
        $print_num = isset($this->params['print_num']) && $this->params['print_num'] ? $this->params['print_num'] : '';
        $fc_user = isset($this->params['fc_user']) && $this->params['fc_user'] ? $this->params['fc_user'] : 0;
        $fc_password = isset($this->params['fc_password']) && $this->params['fc_password'] ? $this->params['fc_password'] : "";


        $print_couplet = isset($this->params['print_couplet']) && $this->params['print_couplet'] ? $this->params['print_couplet'] : 0;
        $print_content = isset($this->params['print_content']) && $this->params['print_content'] ? $this->params['print_content'] : "";

        $data = array();
        $data["merchant_id"] = $this->merchant_id;
        $data["print_name"] = $print_name;
        $data["print_model"] = $print_model;
        $data["fc_user"] = $fc_user;
        $data["fc_password"] = $fc_password;
        $data["print_num"] = $print_num;
        $data["print_couplet"] = $print_couplet;

        $one_where = array(
            array('column' => 'print_num', 'value' => $print_num, 'operator' => '='),
            array('column' => 'is_delete', 'value' => 1, 'operator' => '='),
            array('column' => 'merchant_id', 'value' => $this->merchant_id, 'operator' => '=')
        );
        if($id){
            $one_where[] = array('column' => 'id', 'value' => $id, 'operator' => '<>');
        }
        $isprint = Printer::get_one_data($one_where);
        if($isprint){
            $res['errcode'] = 1;

            $res['errmsg'] = '打印机编号已存在';
            return Response :: json($res);
        }

        if($id){
            if($data["print_num"] && $data["fc_user"] && $data["fc_password"]) {
                $Message['unm'] = $data["fc_user"]; //fc账号
                $Message['dno'] = $data["print_num"]; //打印机编号
                $Message['api_key'] = $data["fc_password"];
                $result = PrinterFc::queryState($Message);
                $result = json_decode($result);
                if(is_numeric($result) && $result!=0){
                    $printer_error = Config::get('printererror') ? Config::get('printererror') : '';
                    $result = PrinterFc::setFcprint($Message);
                    if(is_numeric($result) && $result!=0){
                        if($printer_error){
                            $result = $printer_error[$result];
                            $res['errcode'] = 1;
                            $res['errmsg'] =$result;
                            return Response :: json($res);
                        }
                    }
                }
                $wheres = array(
                    array('column' => 'id', 'value' => $id, 'operator' => '='),
                    array('column' => 'merchant_id', 'value' => $this->merchant_id, 'operator' => '=')
                );
                Printer::update_data_by_where($wheres,$data);
            }else{
                $res['errcode'] = 1;
                $res['errmsg'] = "缺少参数";
                return Response:: json($res);
            }
        }else{
            if($data["print_num"] && $data["fc_user"] && $data["fc_password"]){
                $Message['unm'] = $data["fc_user"]; //fc账号
                $Message['dno'] = $data["print_num"]; //打印机编号
                $Message['api_key'] = $data["fc_password"];
                $result = PrinterFc::queryState($Message);
                $result = json_decode($result);
                if(is_numeric($result) && $result!=0) {
                    $result = PrinterFc::setFcprint($Message);
                    $result = json_decode($result);
                    if (is_numeric($result) && $result != 0) {
                        $printer_error = Config::get('printererror') ? Config::get('printererror') : '';
                        if ($printer_error) {
                            $result = $printer_error[$result];
                            $res['errcode'] = 1;
                            $res['errmsg'] = $result;
                            return Response:: json($res);
                        }
                    }else{
                        $isprint = Printer::get_one_data($one_where);
                        if($isprint){
                            $res['errcode'] = 1;

                            $res['errmsg'] = '打印机编号已存在';
                            return Response :: json($res);
                        }
                        $data["is_delete"] = 1;
                        $id=Printer::insert_data($data);
                    }
                }else{
                    $isprint = Printer::get_one_data($one_where);
                    if($isprint){
                        $res['errcode'] = 1;

                        $res['errmsg'] = '打印机编号已存在';
                        return Response :: json($res);
                    }
                    $data["is_delete"] = 1;
                    $id=Printer::insert_data($data);
                }
            }else{
                $res['errcode'] = 1;
                $res['errmsg'] = "缺少参数";
                return Response:: json($res);
            }


        }

        $where = array(
            array('column' => 'printer_id', 'value' => $id, 'operator' => '='),
        );
        PrintContent::delete_data_by_where($where);
        if($print_content){
            $print_content_all = $print_content;
            foreach ($print_content_all as $key=>$val){
                if($val['order_type']!=0 && ($val['order_finish'] || $val['order_status'])){
                    $create_data = array();
                    $create_data['printer_id'] = $id;
                    $create_data['merchant_id'] = $this->merchant_id;
                    $create_data['order_type'] = isset($val['order_type'])?$val['order_type']:0;
                    $create_data['order_finish'] = isset($val['order_finish'])?$val['order_finish']:0;
                    $create_data['order_status'] = isset($val['order_status'])?$val['order_status']:0;
                    PrintContent::insert_data($create_data);
                }
            }
        }


        $res['errcode'] = 0;
        $res['errmsg'] ='';
        $res['data'] = '';
        return Response :: json($res);
    }

    public function getDetail(){
        $id = isset($this->params['id']) && $this->params['id'] ? $this->params['id'] : 0;
        if($id){
            $wheres = array(
                array('column' => 'id', 'value' => $id, 'operator' => '='),
                array('column' => 'merchant_id', 'value' => $this->merchant_id, 'operator' => '=')
            );
            $data = Printer::get_one_data($wheres,"*");
            $where = array(
                array('column' => 'printer_id', 'value' => $id, 'operator' => '='),
                array('column' => 'merchant_id', 'value' => $this->merchant_id, 'operator' => '=')
            );
            $list = PrintContent::get_data_list($where);
            $data['print_content']=$list;
        }
        $res['errcode'] = 0;

        $res['data'] = $data;
        return Response :: json($res);

    }

    /**
     * 修改状态1关闭2开启
     */
    public function editStatus(){
        $id = isset($this->params['id']) && $this->params['id'] ? $this->params['id'] : 0;
        $status = isset($this->params['status']) && $this->params['status'] ? $this->params['status'] : 0;
        if($id){
            $wheres = array(
                array('column' => 'id', 'value' => $id, 'operator' => '='),
                array('column' => 'merchant_id', 'value' => $this->merchant_id, 'operator' => '=')
            );
            Printer::update_data_by_where($wheres,array("status"=>$status));
        }
        $res['errcode'] = 0;

        $res['data'] = '';
        return Response :: json($res);
    }

    /**
     * 删除数据-1->已删除  1->正常
     */
    public function setDelete(){
        $id = isset($this->params['id']) && $this->params['id'] ? $this->params['id'] : 0;
        if($id){
            $wheres = array(
                array('column' => 'id', 'value' => $id, 'operator' => '='),
                array('column' => 'merchant_id', 'value' => $this->merchant_id, 'operator' => '=')
            );
            $data = Printer::get_one_data($wheres,"*");
            if($data["print_num"] && $data["fc_user"] && $data["fc_password"]){
                $Message['unm'] = $data["fc_user"]; //fc账号
                $Message['dno'] = $data["print_num"]; //打印机编号
                $Message['api_key'] = $data["fc_password"];
                $result = PrinterFc::deleteFcprint($Message);
                /*$result = json_decode($result);
                if(is_numeric($result) && $result!=0){
                    $printer_error = Config::get('printererror') ? Config::get('printererror') : '';
                    $result = $printer_error[$result];
                    $res['errcode'] = 1;
                    $res['errmsg'] = $result;
                    return Response :: json($res);
                }*/
            }


            $wheres = array(
                array('column' => 'id', 'value' => $id, 'operator' => '='),
                array('column' => 'merchant_id', 'value' => $this->merchant_id, 'operator' => '=')
            );
            Printer::update_data_by_where($wheres,array("is_delete"=>-1));
        }
        $res['errcode'] = 0;
        $res['errmsg'] ='';
        $res['data'] = '';
        return Response :: json($res);
    }

    public function getDdd(){
        $order_id = isset($this->params['order_id']) && $this->params['order_id'] ? $this->params['order_id'] : 0;
//        $type = isset($this->params['type']) && $this->params['type'] ? $this->params['type'] : 0;
        if($order_id){
            $orderPrint = new OrderPrintService();
            $result = $orderPrint->printOrder($order_id,$this->merchant_id);
        }



        $str = "点点客";
//        $content= "|1点点客|4点点客|6点点客|7点点客|8点点客";
//        $content.= "|4|D".$this->formatStr($str,7)."\\n";
//        $content.= "|6|D".$this->formatStr($str,7)."\\n";
//        $content.= "|7|D".$this->formatStr($str,7)."\\n";
        /*$content= "|7|D".$this->formatStr($str,7);
        $content.="|5订单状态：支付完成\\n";
        $content.="|5下单时间：2018-05-21 00:00:00\\n";
        $content.="|5订单编号：1234567984455\\n";
        $content.="|6|C************************";*/
//var_dump($content);exit;
        /*$content="\\n|6|C------------------------";
        $content.="\\n|5实付金额              ￥5.00";
        $content.="\\n|6|C************************";*/

        /*$aa = array();
        $aa['api_key'] = "t0t2l8t446tp8628";
        $aa['unm'] = "dodoca";
        $aa['dno'] = "670511102";
        $aa['mode'] = "1"; //key
        $aa['content'] = $result;

        $result = PrinterFc::sendFreeMessage($aa);
//        $result=PrinterFc::setFcprint($aa);
//        $result = PrinterFc::queryState($aa);
//        $result = PrinterFc::deleteFcprint($aa);
        $result = json_decode($result);
        if(is_numeric($result) && $result!=0){
            $printer_error = Config::get('printererror') ? Config::get('printererror') : '';
            $result = $printer_error[$result];
        }*/
        $res['errcode'] = 0;
        $res['data'] = $result;
        return Response::json($res, 200);
    }

}

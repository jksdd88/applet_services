<?php
/**
 * Created by PhpStorm.
 * User: lizhenheng
 * Date: 2018-03-13
 * Time: 上午 11:20
 */
namespace App\Services;

use App\Utils\PrinterFc;
use App\Models\OrderInfo;
use App\Models\OrderGoods;
use App\Models\OrderUmp;
use App\Models\Store;
use App\Models\OrderAddr;
use App\Models\OrderAppt;
use App\Models\OrderSelffetch;
use App\Models\Merchant;
use App\Models\Printer;
use App\Models\PrintContent;
use App\Models\PrintLog;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OrderPrintService
{
    protected $merchant_id;

    protected $orderStatus = array(
        ORDER_AUTO_CANCELED => '已关闭',
        ORDER_BUYERS_CANCELED => '用户取消',
        ORDER_MERCHANT_CANCEL => '商家取消',
        ORDER_REFUND_CANCEL => '已关闭',
        ORDER_SUBMIT => '待付款',
        ORDER_TOPAY => '待付款',
        ORDER_SEND => '待收货',
        ORDER_SUCCESS => '已完成',
    );
    public function __construct()
    {
//        $this->merchant_id = Auth::user()->merchant_id;
//        $this->merchant_id = 2;
    }

    /**
     * @param $order_id
     * @param $merchant_id
     * @param int $type
     * @return array
     * 打印需要的数据
     */
    public function printOrder($order_id,$merchant_id){
        if($order_id){
            $type = 0;
            //订单详情
            $temporderInfo = OrderInfo::select('*')->where("id",$order_id)->where("merchant_id",$merchant_id)->first();
            if($temporderInfo['order_type']==4){
                $type = 3;//预约订单
            }elseif($temporderInfo['order_type']==5){
                $type = 4;//优惠买单
            }elseif ($temporderInfo['order_type'] !=4 && $temporderInfo['order_type'] !=5 && $temporderInfo['delivery_type']==2){
                $type = 2;//自提订单
            }elseif($temporderInfo['order_type'] !=4 && $temporderInfo['order_type'] !=5 && ($temporderInfo['delivery_type']==1 || $temporderInfo['delivery_type']==3)){
                $type = 1;//配送订单（物流配送、同城配送）
            }

            $merchant = Merchant::get_data_by_id($merchant_id);

            //订单商品表
            $tempGoodsData =  OrderGoods::select("id","goods_name","price","pay_price","props","quantity")->where("order_id",$order_id)->get()->toArray();
            // 订单优惠信息
            $ordersUmp = OrderUmp::select('*')->where('order_id', $order_id)->where('amount', '!=', '0.00')->get()->toArray();

            $detail = array();
            if($type == 1){
                //配送订单(配送地址)
                $OrderAddrData =  OrderAddr::select("*")->where("order_id",$order_id)->first();
                $addr_array = array();
                if(!empty($OrderAddrData)){
                    $addr_array['mobile'] = $OrderAddrData["mobile"];
                    $addr_array['consignee'] = $OrderAddrData["consignee"];
                    $addr_array['address'] = $OrderAddrData["province_name"].$OrderAddrData["city_name"].$OrderAddrData["district_name"].$OrderAddrData["address"];
                }
                $detail = $addr_array;
            }elseif ($type == 2){ //包含提货人信息
                //自提订单(自提门店地址)
                $store = Store::get_data_by_id($temporderInfo['store_id'],$merchant_id);
                $addr_array = array();
                if(!empty($store)){
                    $addr_array['mobile'] = $temporderInfo["member_mobile"];
                    $addr_array['consignee'] = $temporderInfo["member_name"];
                    $addr_array['address'] = $store["name"];
                }
                $detail = $addr_array;
            }elseif ($type == 3){
                //订单类型:服务订单
                $tempOrderAppt = OrderAppt::select("*")->where("order_id",$order_id)->first();
                $addr_array = array();
                if(!empty($tempOrderAppt)){
                    $addr_array['time'] = $tempOrderAppt["appt_string"];
                    $addr_array['mobile'] = $tempOrderAppt["customer_mobile"];
                    $addr_array['consignee'] = $tempOrderAppt["customer"];
                    $addr_array['address'] = $tempOrderAppt["store_name"];
                    $addr_array['appt_staff_nickname'] = $tempOrderAppt["appt_staff_nickname"];
                }
                $detail = $addr_array;
            }elseif ($type == 4){
                //优惠买单
                $store = Store::get_data_by_id($temporderInfo['store_id'],$merchant_id);
                $addr_array = array();
                if(!empty($store)){
                    $addr_array['address'] = $store["name"];
                }else{
                    $addr_array['address'] = "--";
                }
                $detail = $addr_array;
            }
        }
        $result = array();
        $result['merchant'] =  isset($merchant)?$merchant:array();
        $result['orderinfo'] =  isset($temporderInfo)?$temporderInfo:array();
        $result['ordergoods'] = isset($tempGoodsData)?$tempGoodsData:array();
        $result['orderump'] = isset($ordersUmp)?$ordersUmp:array();
        $result['detail'] = isset($detail)?$detail:array();
        $content = $this->setContent($result,$type);

        //是否打印
        $hexiao_status = 0;
        if($type == 2){
            $hexiao_status = OrderSelffetch::select("hexiao_status")->where(["order_id"=>$order_id,'merchant_id'=>$merchant_id])->first();
        }elseif ($type == 3){
            $hexiao_status = OrderAppt::select('hexiao_status')->where(["order_id"=>$order_id,'merchant_id'=>$merchant_id])->first();
        }
        //1要打印0不打印
        $print = Printer::select("*")
            ->where('status','=',1)
            ->where('is_delete','=',1)
//            ->where('print_num','=',$print_num)
            ->where('merchant_id','=',$merchant_id)
            ->get()->toArray();
        if($print){
            foreach ($print as $key=>$val){
                $isprint = 0;
                $print_content = PrintContent::select("*")
                    ->where('printer_id','=',$val['id'])
                    ->where('order_type','=',$type)
                    ->first();
                if($temporderInfo['pay_status']==1 && $print_content['order_finish']==1){ //支付完成
                    $isprint = 1;
                }
                if($type == 1){
                    if($temporderInfo['pay_status']==1 && $temporderInfo['status']==10){ //已发货
                        if($print_content['order_status']==1) {	//开启发货打印
							$isprint = 1;
						} else {
							$isprint = 0;
						}
                    }
                }elseif ($type == 2 || $type == 3){
                    if($hexiao_status['hexiao_status']==1){ //核销
						if($print_content['order_status']==2) {	//开启打印（核销后）
                       		$isprint = 1;
						} else {
							$isprint = 0;
						}
                    }
                }

                if($isprint == 0){
                    continue; //不符合打印条件
                }else{
                    //开始打印
//                    $Printer = new PrinterFc();
                    if($val['print_couplet']>0 && $val['print_num'] && $val['fc_user'] && $val['fc_password']){
                        $get_array = array();
                        $get_array['dno'] = $val['print_num']; //设备编号
                        $get_array['unm'] = $val['fc_user']; //账号
                        $get_array['api_key'] = $val['fc_password']; //key
                        $get_array['mode'] = "0"; //key
                        $get_array['content'] = $content; //key

                        for($val['print_couplet'];$val['print_couplet']>0;$val['print_couplet']--){
                            $ress = PrinterFc::sendFreeMessage($get_array); //打印
                            //记录打印日志
                            $log_data = array();
                            $log_data['merchant_id'] = $merchant_id;
                            $log_data['order_id'] = $order_id;
                            $log_data['print_num'] = $val['print_num'];
                            $log_data['order_detail'] = json_encode($result);
                            $log_data['print_content'] = $content;
                            $log_data['result'] = $ress;
                            if($ress==0){
                                $log_data['status'] = 1;
                            }else{
                                $log_data['status'] = 2;
                            }

                            PrintLog::insert_data($log_data);
                        }
                    }
                }
            }
        }
//        exit;
        return 1;

    }

    public  function setContent($data,$type){
        $content="";
        if($data['orderinfo']){
            $order_status = config("config.order_status");
            $content.= "|7|D".$this->formatStr("* ".$data['merchant']['company']." *",7);
            if($type==3){
                if($data['orderinfo']['status'] == 10){ //已支付
                    $new_status = "买家支付，待核销 ";
                }elseif($data['orderinfo']['status'] == 11){ //已完成
                    $new_status = "已完成";
                }
            }else{
                $new_status = $order_status[$data['orderinfo']['status']];
            }
            $content.="\\n|5订单状态：".$new_status;
            $content.="\\n|5下单时间：".$data['orderinfo']['created_time'];
            $content.="\\n|5订单编号：".$data['orderinfo']['order_sn'];
            $content.="\\n|6|C************************";
            $goods_total_money = 0;
            //购买商品
            if($data['ordergoods']){
                foreach ($data['ordergoods'] as $key=>$val){
                    if($type==3){
                        if($data['detail']['appt_staff_nickname']){
                            $val['props'] = $data['detail']['appt_staff_nickname'];
                        }else{
                            $val['props'] = '';
                        }
                    }
                    if($type!=4){
                        if($val['props']){
                            $content.="\\n|5".$val['goods_name']."     ".$val['props']."    x".$val['quantity']."   ￥".($val['price']*$val['quantity']);
                        }else{
                            $content.="\\n|5".$val['goods_name']."         x".$val['quantity']."   ￥".($val['price']*$val['quantity']);
                        }
                    }
                    $goods_total_money += ($val['price']*$val['quantity']);
                }
            }
            if($type!=4) {
                $content .= "\\n|6|C------------------------";
            }
            if($type==3){
                $content.="\\n|5总价                  ￥".$goods_total_money;
            }elseif($type==4){
                $content.="\\n|5订单总额              ￥".$goods_total_money;
                $content .= "\\n|6|C------------------------";
            }else{
                $content.="\\n|5商品总价              ￥".$goods_total_money;
            }
            if($type==1){
                $content.="\\n|5运费                  ￥".$data['orderinfo']['shipment_fee'];
            }

            //优惠信息
            if($data['orderump']){
                $order_ump_ump_type = config("varconfig.order_ump_ump_type");
                foreach ($data['orderump'] as $key=>$val){
                    $ump_name = $order_ump_ump_type[$val['ump_type']];
                    if($val['ump_type']==4){
                        if(strlen($ump_name)<=6){
                            $content.="\\n|5".$ump_name."                 -￥".abs($val['shipment_fee']);
                        }else{
                            $content.="\\n|5".$ump_name."              -￥".abs($val['shipment_fee']);
                        }
                    }else{
                        if(strlen($ump_name)<=6){
                            $content.="\\n|5".$ump_name."                 -￥".abs($val['amount']);
                        }else{
                            $content.="\\n|5".$ump_name."              -￥".abs($val['amount']);
                        }
//                        $content.="\\n|6".$ump_name."               -￥".abs($val['amount']);
                    }
                }
            }
            $content.="\\n|6|C------------------------";
            $content.="\\n|7|D实付金额       ￥".$data['orderinfo']['amount'];
            $content.="\\n|6|C************************";

            //提货地址
            if($data['detail']){
                if($type==1){ //配送地址
                    $content.="\\n|7|D".$data['detail']['address'];
                }elseif ($type==2){ //自提地址
                    $content.="\\n|7|D提货门店:".$data['detail']['address']."\\n";
                }elseif($type==3){ //服务订单
                    $content.="\\n|7|D服务时间:".$data['detail']['time'];
                    $content.="\\n|7|D预约门店:".$data['detail']['address']."\\n";
                }elseif ($type==4){
                    $content.="\\n|7|D收款门店:".$data['detail']['address'];
                }
                if($type!=4){
                    $content.="\\n|7|D".$data['detail']['consignee'];
                    $content.="\\n|7|D".$data['detail']['mobile'];
                    $content.="\\n|7|D买家留言:".$data['orderinfo']['memo'];
                }
            }
            $content.="\\n\\n|5谢谢惠顾，欢迎下次光临！";
//            $content.="@CTn";

        }
        $content = $this->make_semiangle($content);
        return $content;
    }

    /**
     * 处理字符串居中排列
     * @param $str
     * @param $fontType 字体大小
     */
    private function formatStr($str,$fontType){
        $long = 0;
        $len = strlen($str);
        //算出前面的字符数 用空格填充 集中

        switch ($fontType){
            case 5:
                if($len <= 20)
                {
                    $nStr = str_pad($str,26," ",STR_PAD_BOTH);
                }else{
                    $long = 48-$len;
                    $nStr = str_pad($str,$long," ",STR_PAD_BOTH);
                }
                break;
            case 6:
                $nStr = str_pad($str,26," ",STR_PAD_BOTH);

                break;
            case 7:

                if($len < 13){
                    $nStr = str_pad($str,26," ",STR_PAD_BOTH);
                }

                if($len >= 13 && $len < 17){
                    $nStr = str_pad($str,27," ",STR_PAD_BOTH);
                }

                if($len >= 17 && $len < 21){
                    $nStr = str_pad($str,29," ",STR_PAD_BOTH);
                }

                if($len >= 21 && $len < 27){
                    $nStr = str_pad($str,30," ",STR_PAD_BOTH);
                }

                if($len >= 27){
                    $nStr = $str;
                }
                break;
        }


        return $nStr;
    }

    /**
     * 处理字符串居中排列
     * @param $str
     * @param $fontType 字体大小
     */
    private function spaceStr($leftstr,$long=27){
        $leftlen = strlen($leftstr);
        if($leftlen<27){
            $nStr = str_pad($leftstr,$long," ",STR_PAD_RIGHT);
        }else{
            $nStr = $leftstr;
        }

        return $nStr;
    }


    /**--------------------------------------------------------**
     *                         小票样式说明
     *                        ------------
     *
     *在文字前加“|1”、“|4”（字体大小：18点）、“|5”（字体大小：24点）、“|6”（字体大小：32点）、“|7”（字体大小：48点）
     * 、“|8”（字体大小：64点），在二维码数据前加“|2”，在条码的数据前加“|3”  。
     * content 内容为前面内容相加（如：|1 文字内容文字内容文字内容 |2 二维码内容 |3 条码内容）;
     * 1.3.2 版增加粗体：|5对应的粗体为|C ;|6对应的粗体为|D;
     * \n换行  \n\n空一行
     *---------------------------------------------------------**
     */

    /**
     * @param $content
     * @return string
     * 字符替换
     */
    private function make_semiangle($content) {
        $arr = array('０' => '0', '１' => '1', '２' => '2', '３' => '3', '４' => '4',
            '５' => '5', '６' => '6', '７' => '7', '８' => '8', '９' => '9',
            'Ａ' => 'A', 'Ｂ' => 'B', 'Ｃ' => 'C', 'Ｄ' => 'D', 'Ｅ' => 'E',
            'Ｆ' => 'F', 'Ｇ' => 'G', 'Ｈ' => 'H', 'Ｉ' => 'I', 'Ｊ' => 'J',
            'Ｋ' => 'K', 'Ｌ' => 'L', 'Ｍ' => 'M', 'Ｎ' => 'N', 'Ｏ' => 'O',
            'Ｐ' => 'P', 'Ｑ' => 'Q', 'Ｒ' => 'R', 'Ｓ' => 'S', 'Ｔ' => 'T',
            'Ｕ' => 'U', 'Ｖ' => 'V', 'Ｗ' => 'W', 'Ｘ' => 'X', 'Ｙ' => 'Y',
            'Ｚ' => 'Z', 'ａ' => 'a', 'ｂ' => 'b', 'ｃ' => 'c', 'ｄ' => 'd',
            'ｅ' => 'e', 'ｆ' => 'f', 'ｇ' => 'g', 'ｈ' => 'h', 'ｉ' => 'i',
            'ｊ' => 'j', 'ｋ' => 'k', 'ｌ' => 'l', 'ｍ' => 'm', 'ｎ' => 'n',
            'ｏ' => 'o', 'ｐ' => 'p', 'ｑ' => 'q', 'ｒ' => 'r', 'ｓ' => 's',
            'ｔ' => 't', 'ｕ' => 'u', 'ｖ' => 'v', 'ｗ' => 'w', 'ｘ' => 'x',
            'ｙ' => 'y', 'ｚ' => 'z',
            '（' => '(', '）' => ')', '〔' => '[', '〕' => ']', '【' => '[',
            '】' => ']', '〖' => '[', '〗' => ']', '“' => '[', '”' => ']',
            '‘' => '[', '’' => ']', '｛' => '{', '｝' => '}', '《' => '<',
            '》' => '>',
            '％' => '%', '＋' => '+', '—' => '-', '－' => '-', '～' => '~',
            '：' => ':', '。' => '.', '、' => ',', '，' => '.', '、' => '.',
            '；' => ',', '？' => '?', '！' => '!', '…' => '-', '‖' => '|',
            '”' => '"', '’' => '`', '‘' => '`', '｜' => '|', '〃' => '"',
            '　' => ' ', '＆' => '&', '＃' => '#', '＾' => '^', '＊' => '*',
            '｜' => '|', '／' => '/', '／' => '/', '￥' => '￥', '｀' => '`', '　' => ' '
        );
        return strtr($content, $arr);
    }


}
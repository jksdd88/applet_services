<?php

namespace App\Http\Controllers\Weapp;

use Illuminate\Http\Request;
use Illuminate\Foundation\Bus\DispatchesJobs;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Services\CouponService;
use App\Models\Member as MemberModel;
use App\Models\MemberCard;
use App\Models\MemberUpdateLog;
use Captcha;
use Cache;
use Mail;
use App\Utils\CacheKey;
use App\Models\OrderInfo;
use App\Models\OrderGoods;
use App\Services\GoodsService;
use App\Services\BuyService;
use App\Services\VipcardService;
use App\Services\MemberService;
use App\Jobs\TestJob;
use App\Facades\Member;
use Carbon\Carbon;
use \Milon\Barcode\DNS1D;
use \Milon\Barcode\DNS2D;
use App\Models\GoodsCat;
use App\Models\Goods;
use App\Models\Prop;
use App\Models\PropValue;
use App\Models\User;
use App\Models\Coupon;
use App\Models\CouponCode;
use App\Models\DistribPartner;
use App\Models\DistribBuyerRelation;
use App\Models\DistribMemberFirstRecord;
use App\Utils\Encrypt;
use GuzzleHttp\Client;
use Image;
use App\Utils\CommonApi;
use App\Services\MerchantService;
use App\Models\LiveInfo;
use App\Services\LiveService;
use App\Models\DistribOrder;
use App\Models\WeixinInfo;
use App\Models\DataExportTask as DataExportTaskModel;


class TestController extends Controller
{
	use DispatchesJobs;
	
	public function __construct(CouponService $CouponService,GoodsService $GoodsService,OrderInfo $OrderInfo,BuyService $BuyService)
    {
        $this->CouponService = $CouponService;
		$this->GoodsService = $GoodsService;
		$this->OrderInfo = $OrderInfo;
		$this->BuyService = $BuyService;
    }

    public function editDistribRelation(Request $request)
    {
		$member_account        = $request->member_account;
		$parent_member_account = $request->parent_member_account;
		$merchant_id           = $request->merchant_id;
		if(!$member_account || !$parent_member_account || !$merchant_id){
			echo '参数不全';
			exit;
		}

		$member_id        = $member_account - MEMBER_CONST;
		$parent_member_id = $parent_member_account - MEMBER_CONST;

		if(!$member_id){
			echo '会员ID不对';
			exit;
		}
		if(!$parent_member_id){
			echo '上级推客会员ID不对';
			exit;
		}

		//上级推客
		$parent_distrib = DistribPartner::get_data_by_memberid($parent_member_id, $merchant_id);
		if(!$parent_distrib){
			echo '上级推客不存在';
			exit;
		}
		//当前推客
		$distrib = DistribPartner::get_data_by_memberid($member_id, $merchant_id);
		if(!$distrib){
			echo '当前推客不存在';
			exit;
		}

    	if($parent_distrib && $distrib){
    		//更新推客的上级
    		DistribPartner::update_data($member_id, $merchant_id, ['parent_member_id' => $parent_member_id]);
    		//更新佣金归属关系
    		DistribBuyerRelation::update_data($member_id, $merchant_id, ['distrib_member_id' => $parent_member_id]);
    		//增加上级推客的下级人数
    		DistribPartner::increment_data($parent_member_id, $merchant_id, 'team_size', 1);

    		echo '修改成功';
    	}
    }

    public function updateGoodsCat(Request $request)
    {
    	$parent_id = $request->parent_id;
    	$goods_cats = GoodsCat::where('parent_id', $parent_id)->where('child_count', 0)->get();

    	foreach ($goods_cats as $cat) {
    		$prop_arr = ['预约时段', '预约日期', '预约门店', '预约人员'];
    		foreach($prop_arr as $prop_title){
    			$prop_exist = Prop::where('goods_cat_id', $cat->id)->where('title', $prop_title)->first();
    			if(!$prop_exist){
    				$data = [
						'merchant_id'  => 0,
						'goods_cat_id' => $cat->id,
						'title'        => $prop_title,
						'prop_type'    => 1,
						'is_delete'    => 1
    				];

    				Prop::create($data);	
    			}
    		}
    	}

    	$goods_cats = GoodsCat::where('parent_id', $parent_id)->where('child_count', '>', 0)->get();

    	foreach ($goods_cats as $cat) {
    		$three_cat = GoodsCat::where('parent_id', $cat->id)->get();
    		foreach($three_cat as $t_cat){
    			$prop_arr = ['预约时段', '预约日期', '预约门店', '预约人员'];
	    		foreach($prop_arr as $prop_title){
	    			$prop_exist = Prop::where('goods_cat_id', $t_cat->id)->where('title', $prop_title)->first();
	    			if(!$prop_exist){
	    				$data = [
							'merchant_id'  => 0,
							'goods_cat_id' => $t_cat->id,
							'title'        => $prop_title,
							'prop_type'    => 1,
							'is_delete'    => 1
	    				];

	    				Prop::create($data);
	    			}
	    		}
    		}
    	}

    	Prop::where('merchant_id', 0)->where('title', '预约时段')->update(['is_edit' => 1]);
    	Prop::where('merchant_id', 0)->where('title', '预约门店')->update(['prop_value_type' => 1, 'is_edit' => 0]);
    	Prop::where('merchant_id', 0)->where('title', '预约人员')->update(['prop_value_type' => 2, 'is_edit' => 0]);
    	Prop::where('merchant_id', 0)->where('title', '预约日期')->update(['is_edit' => 0]);


    	$props = Prop::where('merchant_id', 0)->where('title', ['预约日期'])->get();

    	foreach($props as $prop){
    		$prop_value_arr = ['周一至周五', '周六至周日', '法定节假日'];

			foreach($prop_value_arr as $prop_value_title){
				$exist = PropValue::where('prop_id', $prop->id)->where('title', $prop_value_title)->first();
				if(!$exist){
					$data = [
						'merchant_id' => 0,
						'prop_id'     => $prop->id,
						'title'       => $prop_value_title,
						'prop_type'   => 1,
						'is_delete'   => 1
					];

					PropValue::create($data);
				}
			}
    	}
    }

    public function soldout()
    {
    	Goods::where('goods_cat_id', 4190)->update([
			'goods_cat_id' => 4204,
			'onsale'       => 0
    	]);

    	Goods::where('goods_cat_id', 4191)->update([
			'goods_cat_id' => 4207,
			'onsale'       => 0
    	]);

    	Goods::where('goods_cat_id', 4192)->update([
			'goods_cat_id' => 4211,
			'onsale'       => 0
    	]);

    	Goods::where('goods_cat_id', 4193)->update([
			'onsale'       => 0
    	]);

    	Goods::where('goods_cat_id', 4194)->update([
			'onsale'       => 0
    	]);
    }

    /**
     * 同步优惠劵发送数量
     */
    public function syncCouponSendNum(Request $request)
    {
    	ini_set('max_execution_time', '0');
    	$lists = Coupon::get();
    	foreach($lists as $row){
    		$coupon_id = $row->id;
    		$send_num = CouponCode::where('coupon_id', $coupon_id)->where('is_delete', 1)->count();
    		Coupon::where('id', $coupon_id)->update(['send_num' => $send_num]);
    	}
    }

    /**
     * 订单列表
     */
    public function index(MemberService $MemberService, Request $request)
    {
    	$id = $request->id;
    	$task = DataExportTaskModel::where('id', $id)->where('status', 0)->first();
        $task_id     = $task->id;
        $merchant_id = $task->merchant_id;
        $condition   = $task->condition;
        $status      = $task->status;

        if($status === 0){
            DataExportTaskModel::where('id', $task_id)->update(['status' => 1]);
            $condition = json_decode($condition, true);
            extract($condition);
            $query = DistribPartner::query();

            $query->where('merchant_id', $merchant_id);

            if($wxinfo_id){
                $query->where('wxinfo_id', $wxinfo_id);
            }

            if($member_id){
                $query->where('member_id', $member_id - MEMBER_CONST);
            }
             //手机号
            if($mobile){
                $query->where('mobile', 'like', $mobile.'%');
            }
            //推客姓名
            if($name){
                $query->where('name', 'like', '%'.$name.'%');
            }
            //状态
            if($status){
                $query->where('status', $status);
            }
            //推荐人姓名
            if($referrer_name){
                //从会员表中查询
                $parent_member_id = Member::where('merchant_id', $merchant_id)->where('name', 'like', '%'.$referrer_name.'%')->lists('id');
                if($parent_member_id){
                    $query->whereIn('parent_member_id', $parent_member_id);
                }
            }
            //开始时间
            if($start_time && $end_time){
                $query->where('check_time', '>=', $start_time)->where('check_time', '<=', $end_time.' 23:59:59');
            }

            $query->whereIn('status', [1, 2]); //推客列表 正常+禁用

            $query->orderBy('created_time', 'desc');

            $filepath = storage_path('app').DIRECTORY_SEPARATOR.'distrib_info_'.$merchant_id.'_'.time().'.csv';
            $file     = fopen($filepath, "w");

            $head = ['推客信息', '会员账号', '未结算佣金', '已结算佣金', '推广订单总额', '推荐人(会员号)', '推客下级人数', '佣金下级人数', '姓名', '手机号', '加入时间', '来源小程序', '状态'];
            foreach($head as $key => $val){
                $head[$key] = @iconv('UTF-8', 'GBK//IGNORE', $val);
            }
            fputcsv($file, $head);

            $status_arr = ['待处理','正常','禁用','已拒绝'];

            $query->chunk(500, function($list) use ($file, $merchant_id, $status_arr) {
                foreach($list as $value){
                    //推客昵称
                    $member = Member::get_data_by_id($value['member_id'], $merchant_id);
                    //上级推客信息
                    $parent_info = '';
                    if($value['parent_member_id']){
                        $higher_member = Member::get_data_by_id($value['parent_member_id'], $merchant_id);
                        $account       = $value['parent_member_id'] + MEMBER_CONST;
                        $parent_info   = filterEmoji($higher_member['name'] . '(' .$account .')');
                    }
                    //小程序信息
                    $wxinfo = WeixinInfo::check_one_id($value['wxinfo_id']);
                    //推广订单额
                    $order_amount = DistribOrder::where('member_id', $value['member_id'])->sum('order_amount');
                    //下级人数
                    $team_size = DistribPartner::where('merchant_id', $merchant_id)->where('parent_member_id', $value['member_id'])->whereIn('status', [1, 2])->count();
                    //佣金下级人数
                    $commission_num = DistribBuyerRelation::where('merchant_id', $merchant_id)->where('distrib_member_id', $value['member_id'])->count();

                    $row = [
                        'nick_name'        => filterEmoji($member['name']), //推客呢称
                        'account'          => $value['member_id'] + MEMBER_CONST, //推客会员帐号
                        'expect_comission' => $value['expect_comission'], //未结算佣金
                        'total_comission'  => $value['total_comission'], //已结算佣金
                        'order_amount'     => $order_amount, //推广订单总额
                        'parent_info'      => $parent_info, //推荐人(会员号)
                        'team_size'        => $team_size ? $team_size : 0, //下级人数
                        'commission_num'   => $commission_num ? $commission_num : 0, //佣金下级人数
                        'name'             => $value['name'], //姓名
                        'mobile'           => $value['mobile'], //手机号
                        'check_time'       => $value['check_time'], //加入时间
                        'weapp'            => $wxinfo['nick_name'], //来源小程序
                        'status'           => $status_arr[$value['status']] //推客状态
                    ];

                    foreach($row as $k => $v){
                        $row[$k] = @iconv('UTF-8', 'GBK//IGNORE', $v);
                    }

                    fputcsv($file, $row);
                }
            });

            fclose($file);
            DataExportTaskModel::where('id', $task_id)->update(['status' => 2, 'filepath' => $filepath]);
        }
    }

    private function getRandChar(){
        $str = null;
        $strPol = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789abcdefghijklmnopqrstuvwxyz";
        $max = strlen($strPol)-1;

        for($i=0;$i<28;$i++){
            $str.=$strPol[rand(0,$max)];
        }
        return $str;
    }

    //文字自动换行
    private function autoWrap($fontSize, $angle, $fontFile, $string, $width) 
    {
        $content = "";
        // 将字符串拆分成一个个单字 保存到数组 letter 中
        for ($i = 0; $i < mb_strlen($string); $i ++) {
            $letter[] = mb_substr($string, $i, 1);
        }

        foreach ($letter as $l) {
            $teststr = $content."".$l;
            $testbox = imagettfbbox($fontSize, $angle, $fontFile, $teststr);
            // 判断拼接后的字符串是否超过预设的宽度
            if (($testbox[2] > $width) && ($content !== "")) {
                $content .= "\r\n";
            }
            $content .= $l;
        }
       
        return explode("\r\n", $content);
    }

    //自动计算图片大小
    private function autoZoom($picWidth, $picHeight, $maxWidth, $maxHeight)
    {
        if(($maxWidth && $picWidth > $maxWidth) || ($maxHeight && $picHeight > $maxHeight))
        {
            if($maxWidth && $picWidth > $maxWidth)
            {
                $widthRatio = $maxWidth / $picWidth;
                $resizeWidthTag = true;
            }

            if($maxHeight && $picHeight > $maxHeight)
            {
                $heightRatio = $maxHeight / $picHeight;
                $resizeHeightTag = true;
            }

            if($resizeWidthTag && $resizeHeightTag)
            {
                if($widthRatio < $heightRatio)
                    $ratio = $widthRatio;
                else
                    $ratio = $heightRatio;
            }

            if($resizeWidthTag && !$resizeHeightTag)
                $ratio = $widthRatio;
            if($resizeHeightTag && !$resizeWidthTag)
                $ratio = $heightRatio;

            $newWidth  = $picWidth * $ratio;
            $newHeight = $picHeight * $ratio;

            return ['newWidth' => $newWidth, 'newHeight' => $newHeight];
        }else{
            return ['newWidth' => $maxWidth, 'newHeight' => $maxHeight];
        }
    }

    /**
     * 导出帐号到单点登录
     */
    public function exportUserSql()
    {
    	ini_set('max_execution_time', '0');
    	$lists = User::select('id', 'username', 'mobile', 'is_admin')->where('is_delete', 1)->where('username', '<>', '')->where('mobile', '<>', '')->orderBy('id', 'asc')->get();

		foreach($lists as $row){
			$app_env    = env('APP_ENV');
			$username   = $row->username;
			$mobile     = $row->mobile;
			$data_id    = $row->id;
			$data_level = $row->is_admin == 1 ? 1 : 2;

			$sql = "INSERT INTO account (`type`,`app_env`,`username`,`mobile`,`data_id`,`data_level`) VALUES ('1','".$app_env."','".$username."','".$mobile."','".$data_id."','".$data_level."');";
			echo $sql;
			echo '<br>';
		}
    }
	
	/**
     * zcctest
     */
    public function zcctest($id,$type=1,Request $request)
    {
		$all = $request->all();
		if($type==1) {	//测试扣库存
			/*$odata = [
				'memo'	=>	'12',
			];
			$r = $this->OrderInfo->update_data(430,2,$odata);
			print_r($r);*/
			/*$order = OrderInfo::where(['id'=>$id])->first();
			$ordergoods = OrderGoods::select(['id','quantity','stock_type','goods_id','spec_id'])->where(['order_id'=>$order['id']])->get();
			if($ordergoods) {
				foreach($ordergoods as $key => $ginfo) {
					if($ginfo['stock_type']==0) {	//付款减库存
						$stkdata = [
							'merchant_id'	=>	$order['merchant_id'],
							'stock_num'		=>	$ginfo['quantity'],
							'goods_id'		=>	$ginfo['goods_id'],
							'goods_spec_id'	=>	$ginfo['spec_id'],
						];
						if($order['order_type']==ORDER_FIGHTGROUP) {	//拼团订单
							$stkdata['activity'] = 'tuan';
						}
						if($order['order_type']==ORDER_APPOINT) {	//预约订单
							$oppt = OrderAppt::select(['id','iappt_date'])->where(['order_id'=>$order['id']])->first();
							if($oppt) {
								$stkdata['date'] = $oppt['iappt_date'];
							}
						}
						print_r($stkdata);
						$stkresult = $this->GoodsService->desStock($stkdata);print_r($stkresult);
						if($stkresult && isset($stkresult['errcode']) && $stkresult['errcode']==0) {
							
						} else {
							
						}
					}
				}
			}*/
		} else if($type==2) {	//测试退款
			/*$data = [
				'merchant_id'	=>	2,		//商户id
				'order_id'		=>	151,	//订单id
				'apply_type'	=>	2,		//退款类型，备注config/varconfig.php
				'refund_id'		=>	0,		//退款表order_refund表id（全单退不传该参数）
			];
			$BuyService = new BuyService;
			$rs = $BuyService->orderrefund($data);
			echo '<pre>';
			print_r($rs);*/
		} else if($type==3) {	//测试队列
			$all = $request->all();
			$str = isset($all['s']) ? $all['s'] : date("Y-m-d H:i:s");
			$job = new TestJob($str);
            $jobs = $this->dispatch($job);
			print_r($jobs);
		} else if($type==4) {	//测试url
			//$hexiao_code = 'data:image/png;base64,'.DNS1D::getBarcodePNG('1258555555551', "CODABAR","2","80");
			//echo '<img src="'.$hexiao_code.'" />';
			
			$hexiao_code = 'data:image/png;base64,'.DNS2D::getBarcodePNG('1258555555551', "QRCODE","10","10");
			echo '<img src="'.$hexiao_code.'" />';
			
		} else if($type==5) {	//查询订单
			$order_id = isset($all['order_id']) ? $all['order_id'] : 0;
			$merchant_id = isset($all['merchant_id']) ? $all['merchant_id'] : 0;
			if($merchant_id && $order_id) {
				$order_info = OrderInfo::get_data_by_id($order_id, $merchant_id);
				echo "<pre>";
				print_r($order_info);
			} else {
				die('err');
			}
		} else if($type==6) {	//测试优惠买单
			/*$data = array(
				'merchant_id'	=>	2,	//商户id
				'member_id'		=>	67,	//会员id
				'order_type'	=>	ORDER_SALEPAY,	//订单类型
				'amount'		=>	200,	//订单金额（适合无商品处理）
				'store_id'		=>	2,	//门店id（适合无商品处理）
				'coupon_id'		=>	286,	//券id（适合无商品处理）
				'is_credit'		=>	1,	//是否使用积分（1-使用，0-不使用）
			);
			$result = $this->BuyService->createorder($data);
			return $result;*/
		} else if($type==7) {	//写日志测试
			$data = [
				'custom'    	=>    	'test',    	//标识字段数据
			 	'merchant_id'   =>    	'1',    	//商户id
			 	'member_id'     =>    	'2',    	//会员id
			 	'content'		=>		'测试测试',		//日志内容
			];
			$r = CommonApi::wlog($data);
			print_r($r);
		} else if($type==8) {
			$chang_data = array(
				'merchant_id'	=>	1,
				'ctype'			=>	2,
				'type'			=>	1,
				'sum'			=>	1,
				'memo'			=>	"会员观看消耗1个",
			);
			$result = MerchantService::changeLiveMoney($chang_data);
			echo $result;
		}
    }
	
}

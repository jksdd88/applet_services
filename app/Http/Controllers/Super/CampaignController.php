<?php
/**
 * 广告投放推广
 * User: ailiya@dodoca.com
 * Date: 2018/7/31
 * Time: 14:51
 */
namespace App\Http\Controllers\Super;

use App\Models\Campaign;
use App\Models\CampaignAdcreative;
use App\Models\CampaignAdgroup;
use App\Models\CampaignAdvertiser;
use App\Models\CampaignWxIndustry;
use App\Models\Region;
use App\Models\WeixinInfo;
use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Session;
use Config;

class CampaignController extends Controller
{
    
    function __construct()
    {
        $this->merchant_id = 0;
        $this->super_user_id=Session::get('super_user.id');
        
        //行业分类
        $this->education = Config::get('weixinad.education');//学历枚举
        $this->user_os = Config::get('weixinad.user_os');//操作系统枚举
    }

    /**
     * 广告推广素材列表
     * @param  $campaign_type   int     可选  广告类别（2：公众号，3：朋友圈）
     * @param  $keyword         string  可选  搜索标题关键字
     */
    public function index(Request $request)
    {
        $param = $request->all();
        $page = (empty($param['page']) || (int)$param['page'] <1) ? 1 : (int)$param['page'];
        $pagesize = (empty($param['pagesize']) || (int)$param['pagesize'] <1) ? 10 : (int)$param['pagesize'];
        $agent_id = (empty($param['agent_id']) || (int)$param['agent_id'] < 0) ? 0 : (int)$param['agent_id'];
        
        $merchant_company = isset($param['merchant_company']) && $param['merchant_company'] != '' ? $param['merchant_company'] : '';//商户公司名
        $agent_name = isset($param['agent_name']) && $param['agent_name'] != '' ? $param['agent_name'] : '';//代理商名
        $campaign_name = isset($param['campaign_name']) && $param['campaign_name'] != '' ? $param['campaign_name'] : '';//投放计划名
        $account_name = isset($param['account_name']) && $param['account_name'] != '' ? $param['account_name'] : '';//公众账号名
        
        //连表查询
        $query = Campaign::join('campaign_adgroup', function($join){
            $join->on('campaign.id', '=', 'campaign_adgroup.campaign_id');
        });
        $query->join('campaign_advertiser', function($join){
            $join->on('campaign_advertiser.id', '=', 'campaign.advertiser_id');
        });
        $query->join('merchant', function($join)use($merchant_company){
            $join->on('merchant.id', '=', 'campaign.merchant_id');
            if($merchant_company != '')
            {
                $join->where('merchant.company', 'like', '%'.$merchant_company.'%');
            }
        });
        
        //广告推广计划
        $field = 'campaign.id, campaign.merchant_id, campaign.advertiser_id,campaign.campaign_name, campaign.campaign_type'.
               ', campaign.agent_name, campaign.created_time, campaign.configured_status';
        //广告内容/广告组
        $field.= ', campaign_adgroup.adgroup_name, campaign_adgroup.begin_date'.
               ', campaign_adgroup.end_date, campaign_adgroup.daily_budget, campaign_adgroup.bid_amount';
        //广告资质
        $field.= ', campaign_advertiser.account_name';
        //商户表
        $field.= ', merchant.company';
               
        
        $query->select(\DB::raw($field))
                ->where('campaign.is_delete', '=', 1);
        if($agent_id > 0)
        {
            $query->where('campaign.agent_id', '=', $agent_id);
        }
        
        if(isset($param['campaign_type']) && $param['campaign_type'] > 1 && $param['campaign_type'] < 4) //推广计划类型(1: 普通展示广告-不使用，2: 微信公众号广告，3: 微信朋友圈广告)
        {
            $query->where('campaign.campaign_type', '=', $param['campaign_type']);
        }
        if(isset($param['campaign_name']) && $param['campaign_name'] != '')
        {
            $query->where('campaign.campaign_name', 'like', '%'.$campaign_name.'%');
        } 
        if(isset($param['agent_name']) && $param['agent_name'] != '')
        {
            $query->where('campaign.agent_name', 'like', '%'.$agent_name.'%');
        } 
        if(isset($param['account_name']) && $param['account_name'] != '')
        {
            $query->where('campaign_advertiser.account_name', 'like', '%'.$account_name.'%');
        } 
        
        $data = [];
        $count = $query->count();
        if($count > 0)
        {
            $data = $query->orderBy('id','desc')->offset(($page-1)*$pagesize)->limit($pagesize)->get()->toArray();
            if($data)
            {
                foreach($data as $key=>$val)
                {
                    $advertiser = CampaignAdvertiser::select(["account_name"])->where('id', $val['advertiser_id'])->first();
                    $data[$key]['campaign_date'] = $val['begin_date']." - ".$val['end_date'];//投放时间段
                    $data[$key]['daily_budget'] = $val['daily_budget']/100;//日限额
                    $data[$key]['bid_amount'] = $val['bid_amount']/100;//出价
                    $data[$key]['account_name'] = $advertiser->account_name;//公众号名称
                    $data[$key]['campaign_type'] = $val['campaign_type'] == 2 ? "公众号" : "朋友圈";//广告类别/推广计划类型
                }
            }
        }

        return Response::json(['errcode' => 0, 'errmsg' => '获取数据成功', 'count' => $count, 'data' => $data]);
    }


    /**
     * 获取广告信息
     * @param  $id int 必选 广告推广计划ID
     * @param  $merchant_id int 必选 商家ID
     */
    public function getRow(Request $request)
    {
        $id = $request->input('id', 0);
        $this->merchant_id = $request->input('merchant_id', 0);
        $row = [];
        if($id > 0)
        {
            $row = Campaign::get_data_by_id($id, $this->merchant_id);
            if(!$row)
            {
                return Response::json(['errcode'=>99001 , 'errmsg'=>'信息不存在！']);//数据不存在
            }
            else
            {
                //获取其他表信息
                //资质表
                $advertiser = CampaignAdvertiser::get_data_by_id($row['advertiser_id'], $this->merchant_id);
                $row['corporation_name'] = $advertiser->corporation_name;//企业名称
                $row['qualification_type'] = $advertiser['qualification_type'];//主体资质类型（1：三证合一，2：非三证合一）
                $row['certification_image'] = $advertiser['certification_image'];//营业执照/三合一证照片
                $row['institutional_framework_code_image'] = $advertiser['institutional_framework_code_image'];//组织机构代码照片
                $row['tax_registration_certificate_image'] = $advertiser['tax_registration_certificate_image'];//税务登记证书
                $industry_ids = explode('-', $advertiser['industry']);//行业
                $row['industry1'] = isset($industry_ids[0]) ? $industry_ids[0] : 0;
                $row['industry2'] = isset($industry_ids[1]) ? $industry_ids[1] : 0;
                $row['industry3'] = isset($industry_ids[2]) ? $industry_ids[2] : 0;
                $row['industry_qualification_image'] = $advertiser['industry_qualification_image'];//行业资质
                $row['identity_card_front_image'] = $advertiser['identity_card_front_image'];//身份证正面照
                $row['identity_card_back_image'] = $advertiser['identity_card_back_image'];//身份证背面照
                $row['name'] = $advertiser['name']; //联系人
                $row['phone'] = $advertiser['phone']; //联系人电话
                $row['appid'] = $advertiser['appid']; //公众号appid
                $row['account_name'] = $advertiser['account_name']; //公众号名称
                
                
                //广告组
                $adgroup = CampaignAdgroup::get_data_by_campaign_id($id, $this->merchant_id);
                $row['adgroup_name'] = $adgroup['adgroup_name']; //广告名称
                //$row['site_set'] = $adgroup['site_set']; //投放地点集合 SITE_SET_WECHAT
                $row['begin_date'] = $adgroup['begin_date'];
                $row['end_date'] = $adgroup['end_date'];
                $row['campaign_date'] = $adgroup['begin_date'].' - '.$adgroup['end_date'];
                $row['time_slot_start'] = $adgroup['time_slot_start'];
                $row['time_slot_end'] = $adgroup['time_slot_end'];
                $row['origin'] = $adgroup['origin'];//地区 多选[110000,110100]
                $row['age_bracket'] = $adgroup['age_bracket'];//年龄间隔（0:不限，1：1~17岁，2：18~23岁，3：24~30岁，4：31~40岁，5：41~50岁，6：51~65岁，7：66~127岁） 
                $row['gender'] = $adgroup['gender']; //性别
                $row['interest'] = $adgroup['interest']; //兴趣
                $row['education'] = $adgroup['education']; //学历
                $row['education_str'] = $this->education[$adgroup['education']]['weixinad_title']; //学历
                $row['user_os'] = $adgroup['user_os'];   //操作系统
                $row['user_os_str'] = $this->user_os[$adgroup['user_os']]['weixinad_title'];   //操作系统
                $row['daily_budget'] = $adgroup['daily_budget']/100; //日限额
                $row['bid_amount'] = $adgroup['bid_amount']/100; //广告出价
                $row['product_refs_id'] = $adgroup['product_refs_id'];//门店id
                
                
                //广告创意
                $adcreative = CampaignAdcreative::get_data_by_campaign_id($id, $this->merchant_id);
                $row['adcreative_name'] = $adcreative['adcreative_name']; //广告创意名称
                $row['title'] = $adcreative['title']; //图文标题
                $row['ad_type'] = $adcreative['ad_type']; //广告样式（1：图文，2：图片）
                $row['description'] = $adcreative['description']; //图文描述
                $row['image'] = $adcreative['image']; //元素图片（相对路径）
                $domain = env('QINIU_STATIC_DOMAIN');//获取图片域名
                $row['image_org'] = $domain.'/'.$adcreative['image']; //元素图片绝对路径
                $row['show_image_status'] = $adcreative['show_image_status']; //图文展示方式（1：纯图，2：图文）仅朋友圈可用
                $row['link_type'] = $adcreative['link_type']; //点击跳转类型（1：已绑小程序，2：自定义链接）
                $row['link'] = $adcreative['link']; //手动录入链接
                
                $row['link_name_type'] = $adcreative['link_name_type']; //链接名称类型（1：查看详情，2：领取优惠券，3：预约活动，4：进入小程序）
                $row['mini_program_type'] = $adcreative['mini_program_type'];//小程序关联类型（1：关联小程序，2：自定义输入）
                $row['mini_program_id'] = $adcreative['mini_program_id']; //小程序id  ?  选择用户的小程序
                $row['mini_program_path'] = $adcreative['mini_program_path']; //小程序路径  ?  选择用户的小程序自动生成小程序路径
                $row['share_title'] = $adcreative['share_title'];//分享标题
                $row['share_description'] = $adcreative['share_description'];//分享描述
                if($row['ad_location'] == 1) {
                    $row['show_image'] = 'https://s.dodoca.com/applet_mch/images/agent/01.jpg';
                } else if($row['ad_location'] == 2) {
                    $row['show_image'] = 'https://s.dodoca.com/applet_mch/images/agent/04.jpg';
                } else if($row['ad_location'] == 3) {
                    if($row['ad_type'] == 1)
                    {
                        $row['show_image'] = 'https://s.dodoca.com/applet_mch/images/agent/02.jpg';
                    } else {
                        $row['show_image'] = 'https://s.dodoca.com/applet_mch/images/agent/03.jpg';
                    }
                }
            }
        }
        return Response::json(['errcode' => 0, 'errmsg' => '获取数据成功', 'data' => $row]);
    }

    /**
     * 删除广告信息
     * @param  $id int 必选 广告推广计划ID
     * @param  $merchant_id int 必选 商家ID
     */
    public function deleteRow(Request $request)
    {
        $id = $request->input('id', 0);
        $this->merchant_id = $request->input('merchant_id', 0);
        $return = ['errcode' => 99001, 'errmsg' => '删除失败'];
        
        if($id > 0 && $this->merchant_id > 0)
        {
            $row = Campaign::get_data_by_id($id, $this->merchant_id);
            //没有代理商id的才能删除
            if($row && $row['agent_id'] == 0)
            {
                $data['is_delete'] = -1;
                $rs = Campaign::update_data($id, $this->merchant_id, $data);
                $rs = CampaignAdvertiser::update_data($row['advertiser_id'], $this->merchant_id, $data);
                $rs = CampaignAdgroup::update_data($id, $this->merchant_id, $data);
                $rs = CampaignAdcreative::update_data($id, $this->merchant_id, $data);
                $return['errcode'] = 0;
                $return['errmsg'] = '删除成功';
            } else {
                $return['errmsg'] = '代理商关联数据不能删除';
            }
        }
        return Response::json($return);
    }
    
    /**
     * 下载
     */
    public function explodefile(Request $request)
    {
        $param = $request->all();
        
        $id = isset($param['id']) && (int)$param['id'] > 0 ? (int)$param['id'] : 0;
        $this->merchant_id = isset($param['merchant_id']) && (int)$param['merchant_id'] > 0 ? (int)$param['merchant_id'] : 0;
        if($id > 0 && $this->merchant_id > 0)
            {
            //连表查询
            $query = Campaign::join('campaign_adgroup', function($join) {
                $join->on('campaign.id', '=', 'campaign_adgroup.campaign_id');
            });
            
            $field = 'campaign.id, campaign.merchant_id, campaign.advertiser_id, campaign.ad_location, campaign.campaign_name, campaign.campaign_type, campaign.product_type'.
                   ', campaign.created_time, campaign.configured_status, campaign.agent_id, campaign.agent_name'.
                   ', campaign_adgroup.adgroup_name, campaign_adgroup.begin_date, campaign_adgroup.end_date, campaign_adgroup.time_slot_start'.
                   ', campaign_adgroup.time_slot_start, campaign_adgroup.time_slot_end, campaign_adgroup.origin, campaign_adgroup.age_bracket, campaign_adgroup.gender'.
                   ', campaign_adgroup.interest, campaign_adgroup.education, campaign_adgroup.user_os, campaign_adgroup.daily_budget'.
                   ', campaign_adgroup.bid_amount, campaign_adgroup.bid_amount, campaign_adgroup.product_refs_id';
            
            $query->select(\DB::raw($field))
                    ->where('campaign.id', '=', $id)
                    ->where('campaign.merchant_id', '=', $this->merchant_id)
                    ->where('campaign.is_delete', '=', 1);
            
            if(isset($param['campaign_type']) && $param['campaign_type'] > 1 && $param['campaign_type'] < 4) //推广计划类型(1: 普通展示广告-不使用，2: 微信公众号广告，3: 微信朋友圈广告)
            {
                $query->where('campaign_type', '=', $param['campaign_type']);
            }
            
            $filename = '广告投放信息'.date('Ymd',time()).'.csv';
            header('Content-Type: application/vnd.ms-excel');
            header('Content-Disposition: attachment;filename="'.$filename.'"');
            header('Cache-Control: max-age=0');
            
            //打开PHP文件句柄，php://output表示直接输出到浏览器
            
            
            $fp = fopen('php://output', 'a');
            //表格列表
            $head = [
                '企业名称', '主体资质类型', '三合一证照片', '营业执照',  '组织机构代码照片', '税务登记证书', 
                '身份证正面照', '身份证背面照', '行业', '行业资质照片', '广告资质照片', '管理员姓名', 
                '管理员联系方式',  '公众号APPID', '公众号名称',
                
                '投放计划名称', '广告位', '推广计划类型', '推广目标', '客户设置的状态开关',
                
                '广告内容名称', '投放开始日期', '投放结束日期', '投放开始时间段', '投放结束时间段', 
                '地区', '年龄间隔', '性别', '兴趣', '学历', '操作系统', '日限额(元)', '广告出价(元)', '门店ID',
                
                '广告创意名称', '广告样式', '标题', '描述', '元素图片', '点击跳转类型', '手动录入链接', '接名称类型', 
                '小程序关联类型', '小程序id', '小程序路径', '分享标题', '分享描述'
            ];
            foreach($head as $key => $val){
                $head[$key] = @iconv('UTF-8', 'GBK//IGNORE', $val);
            }
            fputcsv($fp, $head);
            
            //获取配置数据
            $arr['domain'] = env('QINIU_STATIC_DOMAIN');// 七牛图片域名
            $arr['ad_location'] = Config::get('weixinad.ad_location');//广告位
            $arr['campaign_type'] = Config::get('weixinad.campaign_type');//推广计划类型
            $arr['product_type'] = Config::get('weixinad.product_type');//推广目标
            $arr['configured_status'] = Config::get('weixinad.configured_status');//客户设置的状态开关
            $arr['age'] = Config::get('weixinad.age');//年龄
            $arr['user_os'] = Config::get('weixinad.user_os');//用户操作系统
            $arr['interest'] = Config::get('weixinad.interest');//兴趣
            $arr['education'] = Config::get('weixinad.education');//学历
            $arr['gender'] = Config::get('weixinad.gender');//性别
            $arr['link_name_type'] = Config::get('weixinad.link_name_type');//链接名称类型
            //导出订单
            $query->chunk(100, function($list) use ($fp, $arr) {
                foreach($list as $value) {
                    //获取广告投放资质
                    $department_str = '';
                    $advertiser = CampaignAdvertiser::get_data_by_id($value['advertiser_id'], $this->merchant_id);
                    $industry = [];
                    
                    //获取行业
                    if($advertiser)
                    {
                        $industry_arr = explode('-', $advertiser['industry']);
                        foreach($industry_arr as $val)
                        {
                            if($val > 0)
                            {
                                $rs = CampaignWxIndustry::get_data_by_id($val, $this->merchant_id);
                                $industry[] = $rs->name;
                            }
                        }
                    }
                    //s($arr['domain']);exit;
                    $row = array(
                        //广告投放资质
                        'corporation_name'                  => $advertiser['corporation_name'],//企业名称
                        'qualification_type'                => $advertiser['qualification_type'] == 1 ? '三证合一' : '非三证合一',//主体资质类型
                        'certification_image'               => $advertiser['qualification_type'] == 1 ? $arr['domain'].'/'.$advertiser['certification_image'] : '',//三合一证照片
                        'certification_image2'              => $advertiser['qualification_type'] == 2 ? $arr['domain'].'/'.$advertiser['certification_image'] : '',//营业执照
                        'institutional_framework_code_image'=> $advertiser['institutional_framework_code_image'] == '' ? '' : $arr['domain'].'/'.$advertiser['institutional_framework_code_image'],//组织机构代码照片
                        'tax_registration_certificate_image'=> $advertiser['tax_registration_certificate_image'] == '' ? '' : $arr['domain'].'/'.$advertiser['tax_registration_certificate_image'],//税务登记证书
                        'identity_card_front_image'         => $arr['domain'].'/'.$advertiser['identity_card_front_image'],//身份证正面照
                        'identity_card_back_image'          => $arr['domain'].'/'.$advertiser['identity_card_back_image'],//身份证背面照
                        'industry'                          => count($industry) > 0 ? implode('-', $industry) : '',//行业
                        'industry_qualification_image'      => $advertiser['industry_qualification_image'] == '' ? '' : $arr['domain'].'/'.$advertiser['industry_qualification_image'],//行业资质照片
                        'ad_qualification_image'            => $advertiser['ad_qualification_image'] == '' ? '' : $arr['domain'].'/'.$advertiser['ad_qualification_image'],//广告资质照片
                        'name'                              => $advertiser['name'],//管理员姓名
                        'phone'                             => $advertiser['phone'],//管理员联系方式
                        'appid'                             => $advertiser['appid'],//公众号APPID
                        'account_name'                      => $advertiser['account_name'],//公众号名称
                    );
                    
                    //投放计划
                    $row['campaign_name'] = $value['campaign_name'];//投放计划名称
                    $row['ad_location'] = $arr['ad_location'][$value['ad_location']];//广告位（1：朋友圈，2：公众号顶部，3：公众号底部）
                    $row['campaign_type'] = $arr['campaign_type'][$value['campaign_type']]['weixinad_title'];//推广计划类型
                    $row['product_type'] = isset($arr['product_type'][$value['product_type']]) ? $arr['product_type'][$value['product_type']]['weixinad_title'] : '';//推广目标
                    $row['configured_status'] = isset($arr['configured_status'][$value['configured_status']]) ? 
                        $arr['configured_status'][$value['configured_status']]['weixinad_title'] : '';//客户设置的状态开关（1：有效，2：暂停）

                    
                    //广告内容
                    //获取地区
                    //s($value['origin']);exit;
                    $origin_arr = json_decode($value['origin'], true);
                    $origin = [];
                    if(is_array($origin_arr) && count($origin_arr) > 0)
                    {
                        foreach($origin_arr as $val)
                        {
                            $region_rs = Region::get_title_by_id_cache($val);
                            if($region_rs)
                            {
                                $origin[] = $region_rs;
                            }
                        }
                    }
                    $row['adgroup_name'] = $value['adgroup_name'];//广告组名称
                    $row['begin_date'] = $value['begin_date'];//投放开始日期
                    $row['end_date'] = $value['end_date'];//投放结束日期
                    $row['time_slot_start'] = $value['time_slot_start'];//投放开始时间段
                    $row['time_slot_end'] = $value['time_slot_end'];//投放结束时间段
                    $row['origin'] = empty($origin) ? '-' : implode(', ', $origin);//地区
                    $row['age_bracket'] = isset($arr['age'][$value['age_bracket']]) ? $arr['age'][$value['age_bracket']]['weixinad_title'] : '不限';//年龄
                    $row['gender'] = isset($arr['gender'][$value['gender']]) ? $arr['gender'][$value['gender']]['weixinad_title'] : '不限';//性别
                    $row['interest'] = isset($arr['interest'][$value['interest']]) ? $arr['interest'][$value['interest']]['weixinad_title'] : '不限';//兴趣
                    $row['education'] = isset($arr['education'][$value['education']]) ? $arr['education'][$value['education']]['weixinad_title'] : '不限';//学历
                    $row['user_os'] = isset($arr['user_os'][$value['user_os']]) ? $arr['user_os'][$value['user_os']]['weixinad_title'] : '不限';//操作系统
                    $row['daily_budget'] = $value['daily_budget']/100;//日限额
                    $row['bid_amount'] = $value['bid_amount']/100;//广告出价
                    $row['product_refs_id'] = $value['product_refs_id'];//门店ID
                    
                    
                    //广告创意
                    $adcreative = CampaignAdcreative :: get_data_by_campaign_id($value['id'], $value['merchant_id']);
                    $ad_type_str = '-';
                    //广告样式（1：图文，2：图片）
                    if ($adcreative['ad_type'] == 1)
                    {
                        $ad_type_str = '图文';
                    }
                    else
                    {
                        $ad_type_str = '图片';
                    }
                    $row['adcreative_name'] = $adcreative['adcreative_name'];//创意名称
                    $row['ad_type'] = $ad_type_str;//广告样式（1：图文，2：图片）
                    $row['title'] = $adcreative['title'];//标题（1-14字符）
                    $row['description'] = $adcreative['description'];//描述（1-28字符）
                    $row['image'] = $adcreative['image'] == '' ? '' : $arr['domain'].'/'.$adcreative['image'];//元素图片
                    $row['link_type'] = $adcreative['link_type'] == 1 ? '已绑小程序' : '自定义链接';//点击跳转类型（1：已绑小程序，2：自定义链接）
                    $row['link'] = $adcreative['link'];//手动录入链接
                    $row['link_name_type'] = isset($arr['link_name_type'][$adcreative['link_name_type']]) ? $arr['link_name_type'][$adcreative['link_name_type']]['weixinad_title'] : '';//链接名称类型（1：查看详情，2：领取优惠券，3：预约活动，4：进入小程序）
                    $row['mini_program_type'] = $adcreative['mini_program_type'] == 1 ? '关联小程序' : '自定义输入/空白';//小程序关联类型（1：关联小程序，2：自定义输入）
                    $row['mini_program_id'] = $adcreative['mini_program_id'];//小程序id
                    $row['mini_program_path'] = $adcreative['mini_program_path'];//小程序路径
                    $row['share_title'] = $adcreative['share_title'];//分享标题
                    $row['share_description'] = $adcreative['share_description'];//分享描述
                    
                    //s($row);exit;
                    $column = array();
                    foreach($row as $k => $v){
                        $column[$k] = @iconv('UTF-8', 'GBK//IGNORE', $v);
                    }
                    fputcsv($fp, $column);
                }
            });
            exit;
        }
    }

}

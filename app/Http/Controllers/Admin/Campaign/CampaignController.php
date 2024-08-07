<?php
/**
 * 广告投放推广
 * User: ailiya@dodoca.com
 * Date: 2018/7/31
 * Time: 14:51
 */
namespace App\Http\Controllers\Admin\Campaign;

use App\Models\Campaign;
use App\Models\CampaignAdcreative;
use App\Models\CampaignAdgroup;
use App\Models\CampaignAdvertiser;
use App\Models\CampaignWxIndustry;
use App\Models\Industry;
use App\Models\Region;
use App\Models\WeixinInfo;
use Illuminate\Http\Request;
use App\Utils\Weixin\Http;

use App\Services\WeixinService;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;
use Config;

class CampaignController extends Controller
{
    
    function __construct(Request $request)
    {
        //$this->merchant_id = 2;
        //$this->user_id = 2;
        
        if (app()->isLocal()) {
            $this->merchant_id = 2;
            $this->user_id = 2;

        } else {
            $this->merchant_id = Auth::user()->merchant_id;
            $this->user_id = Auth::user()->id;
        }
        //行业分类
        //$data_ImporveInfo['industry'] =Industry::get_title_by_id($request['industry_one']).'-'.Industry::get_title_by_id($request['industry_two']).'-'.Industry::get_title_by_id($request['industry_three']);
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
        
        //连表查询
        $query = Campaign::join('campaign_adgroup', function($join) {
            $join->on('campaign.id', '=', 'campaign_adgroup.campaign_id');
        });
        
        $field = 'campaign.id, campaign.advertiser_id,campaign.campaign_name, campaign.campaign_type, campaign.created_time'.
               ', campaign.configured_status, campaign_adgroup.adgroup_name, campaign_adgroup.begin_date, campaign_adgroup.end_date'.
               ', campaign_adgroup.daily_budget, campaign_adgroup.bid_amount';
        
        $query->select(\DB::raw($field))
                ->where('campaign.merchant_id', '=', $this->merchant_id)
                ->where('campaign.is_delete', '=', 1);
        
        if(isset($param['campaign_type']) && $param['campaign_type'] > 1 && $param['campaign_type'] < 4) //推广计划类型(1: 普通展示广告-不使用，2: 微信公众号广告，3: 微信朋友圈广告)
        {
            $query->where('campaign.campaign_type', '=', $param['campaign_type']);
        }
        
        $keyword = isset($param['keyword']) && $param['keyword'] != '' ? $param['keyword'] : '';
        if($keyword != '')
        {
            $query->where(function($query)use($keyword){

                $query->where('campaign.campaign_name', 'like', '%'.$keyword.'%')
                      ->orwhere('campaign_adgroup.adgroup_name', 'like', '%'.$keyword.'%');
            });
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
     * @param  $id int 必选 活动id
     */
    public function getRow(Request $request)
    {
        $id = (int)$request->input('id', 0);
        if($id > 0)
        {
            $row = Campaign::get_data_by_id($id, $this->merchant_id);
            if(!$row)
            {
                return Response::json(['errcode'=>99001 , 'errmsg'=>'广告信息不存在！']);//数据不存在
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
                $row['image'] = $adcreative['image']; //元素图片（路径）
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
                                
                return Response::json(['errcode'=>0 , 'errmsg'=>'获取成功！', 'data' => $row]);
            }
        }
    }


    /**
     * 更新广告信息
     */
    public function save(Request $request)
    {
        //$id = $request->input('id', 0);
        $param = $request -> all();
        
        //验证
        $check  = self::checkDistribData($param);
        if($check['errcode'] != 0) return Response::json($check); //验证失败
        
        $data = self::getDatas($param);//获取数据
        
        $data['advertiser']['merchant_id'] = $this->merchant_id;
        $data['campaign']['merchant_id'] = $this->merchant_id;
        $data['adgroup']['merchant_id'] = $this->merchant_id;
        $data['adcreative']['merchant_id'] = $this->merchant_id;
        
        $advertiser = CampaignAdvertiser::insert_data($data['advertiser']);
        if($advertiser)
        {
            //调用接口获取代理商id
            $curl_data['merchant_id'] = $this->merchant_id;
            if(env('APP_ENV')=='production' ){
                $curl_rt = $this->curl('http://www.dodoca.com/useradd/xcxagentinfo',$curl_data,'POST');//正式
            }else{
                $curl_rt = $this->curl('http://twww.dodoca.com/useradd/xcxagentinfo',$curl_data,'POST');//测试
            }
            $curl_rt = $curl_rt ? json_decode($curl_rt, true) : ['errcode' => 10009];
            if($curl_rt['errcode'] == 0)
            {
                if($curl_rt['data']['if_ad'] == 1)
                {
                    $data['campaign']['agent_id'] = $curl_rt['data']['agentid'];
                    $data['campaign']['agent_name'] = $curl_rt['data']['agent_company'];
                }
            }
            
            $data['campaign']['advertiser_id'] = $advertiser; //资质id
            $campaign = Campaign::insert_data($data['campaign']);
            if($campaign)
            {
                $data['adgroup']['campaign_id'] = $campaign; //推广id
                $data['adcreative']['campaign_id'] = $campaign; //推广id
                CampaignAdgroup::insert_data($data['adgroup']); //广告组
                CampaignAdcreative::insert_data($data['adcreative']); //广告创意
            } else {
                //可做删除推广表记录
            }
        } else {
            //可做删除资质表记录
        }

        return  Response::json(['errcode'=>0 , 'errmsg'=>'设置已生效']);
    }
    
    
    /**
     * 获取菜单数据
     * @param type 枚举类型 “education”：学历，
     *                      “user_os”：操作系统，
     *                      “interest”：兴趣，
     *                      “age”：年龄，
     *                      “industry”：行业
     */
    public function get_menu(Request $request)
    {
        $type = $request->input('type', '');
        if($type == '')
        {
            return  Response::json(['errcode'=>99001 , 'errmsg'=>'请求不存在']);
        }
        
        $data = [];
        switch($type)
        {
            case 'education':
                $rs = Config::get('weixinad.education');
                if($rs)
                {
                    foreach($rs as $key => $value)
                    {
                        $data[$key] = $value['weixinad_title'];
                    }
                }
                break;
            case 'user_os':
                $rs = Config::get('weixinad.user_os');
                if($rs)
                {
                    foreach($rs as $key => $value)
                    {
                        $data[$key] = $value['weixinad_title'];
                    }
                }
                break;
            case 'interest':
                $rs = Config::get('weixinad.interest');
                if($rs)
                {
                    foreach($rs as $key => $value)
                    {
                        $data[$key] = $value['weixinad_title'];
                    }
                }
                break;
            case 'age':
                $rs = Config::get('weixinad.age');
                if($rs)
                {
                    foreach($rs as $key => $value)
                    {
                        $data[$key] = $value['weixinad_title'];
                    }
                }
                break;
            case 'industry':
                //获取行业列表
                $industry_rs = CampaignWxIndustry::select(\DB::raw('wx_industry_id as id, name, pid, level'))
                             ->get()->toArray();
                $data = $industry_rs;
                break;
        }
        return  Response::json(['errcode'=>0 , 'errmsg'=>'', 'data'=>$data]);
    }
    
    
    /**
     * 获取已绑定的小程序id和链接
     */
    public function get_applet()
    {
        $data = [];
        //获取平台的小程序id
        $rs = WeixinInfo::select('id', 'nick_name', 'appid', 'user_name')
            ->where('merchant_id', $this->merchant_id)
            ->where('user_name', '!=', '')
            ->where('status', 1)->get()->toArray();
        if($rs)
        {
            foreach($rs as $key => $value)
            {
                $data[$key]['id'] = $value['id'];
                $data[$key]['name'] = $value['nick_name'];
            }
        }
        return Response::json(['errcode'=>0 , 'errmsg'=>'成功', 'data'=>$data]);
    }
    
    /**
     * 获取已绑定的公众号门店列表
     */
    public function get_account(Request $request)
    {
        $param = $request->all();
        $page = (empty($param['page']) || (int)$param['page'] <1) ? 1 : (int)$param['page'];
        $pagesize = (empty($param['pagesize']) || (int)$param['pagesize'] <1) ? 10 : (int)$param['pagesize'];
        $data = [];
        $count = 0;
        
        $rs = WeixinInfo::select('appid')
               ->where(['merchant_id'=>$this->merchant_id, 'type'=>2, 'status'=>1])
               ->first();
        if($rs)
        {
            $appid = $rs['appid'];
            $WeixinService = new WeixinService();
            $token = $WeixinService->getAccessToken($appid);
            if($token)
            {
                //查询公众号门店列表
                $curl_data['begin'] = ($page-1)*$pagesize;
                $curl_data['limit'] = $pagesize;
                $url = 'https://api.weixin.qq.com/cgi-bin/poi/getpoilist?access_token='.$token;
                $response = ( new Http())->mxCurl($url, json_encode($curl_data));
                if(isset($response['errcode']) && $response['errcode'] == 0)
                {
                    $list = json_decode($response['data'], true);
                    if(isset($list['errcode']) && $list['errcode'] == 0) {
                        $count = $list['total_count'];
                        foreach($list['business_list'] as $key => $val)
                        {
                            $data[$key]['id'] = $val['base_info']['poi_id']; //poi_id 门店id
                            $data[$key]['name'] = $val['base_info']['business_name'].($val['base_info']['branch_name'] != '' ? '-'.$val['base_info']['branch_name'] : '');
                        }
                    }
                }
            }
        }
        return Response::json(['errcode'=>0 , 'errmsg'=>'获取成功！', 'data' => $data, 'count' => $count]);
    }
    
    
    /**
     * 下载
     */
    public function explodefile(Request $request)
    {
        $param = $request->all();
        
        $id = isset($param['id']) && (int)$param['id'] > 0 ? (int)$param['id'] : 0;
        if($id > 0)
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
                    if(count($origin_arr) > 0)
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
                    $row['origin'] = implode(', ', $origin);//地区
                    $row['age_bracket'] = isset($arr['age'][$value['age_bracket']]) ? $arr['age'][$value['age_bracket']]['weixinad_title'] : '不限';//年龄
                    $row['gender'] = isset($arr['gender'][$value['gender']]) ? $arr['gender'][$value['gender']]['weixinad_title'] : '不限';//性别
                    $row['interest'] = isset($arr['interest'][$value['interest']]) ? $arr['interest'][$value['interest']]['weixinad_title'] : '不限';//兴趣
                    $row['education'] = isset($arr['education'][$value['education']]) ? $arr['education'][$value['education']]['weixinad_title'] : '不限';//学历
                    $row['user_os'] = isset($arr['user_os'][$value['user_os']]) ? $arr['user_os'][$value['user_os']]['weixinad_title'] : '不限';//操作系统
                    $row['daily_budget'] = $value['daily_budget']/100;//日限额
                    $row['bid_amount'] = $value['bid_amount']/100;//广告出价
                    $row['product_refs_id'] = $value['product_refs_id'];//门店ID
                    
                    
                    //广告创意
                    $adcreative = CampaignAdcreative :: get_data_by_campaign_id($value['id'], $this->merchant_id);
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
    
    //-------------------------------------------------------------static
        
    /**
     * 获取所有插入表数据
     * @params $goods_id  int 必填 商品id
     */
    static function getDatas($param)
    {
        //广告资质
        $advertiser['corporation_name'] = $param['corporation_name'];//企业名称
        $advertiser['qualification_type'] = $param['qualification_type'];//主体资质类型（1：三证合一，2：非三证合一）
        $advertiser['certification_image'] = $param['certification_image'];//三合一证照片/营业执照
        $advertiser['institutional_framework_code_image'] = isset($param['institutional_framework_code_image']) ? 
                                                            $param['institutional_framework_code_image'] : '';//组织机构代码照片
        $advertiser['tax_registration_certificate_image'] = $param['tax_registration_certificate_image'] ? 
                                                            $param['tax_registration_certificate_image'] : '';//税务登记证书
        $advertiser['identity_card_front_image'] = $param['identity_card_front_image'];//身份证正面照
        $advertiser['identity_card_back_image'] = $param['identity_card_back_image'];//身份证背面照
        $advertiser['industry'] = $param['industry'];//行业（层级之间用“-”隔开 : 1-2-3）微信需转换为“;”间隔
        $advertiser['industry_qualification_image'] = $param['industry_qualification_image'];//行业资质照片
        $advertiser['name'] = $param['name'];//管理员姓名
        $advertiser['phone'] = $param['phone'];//管理员联系方式
        $advertiser['appid'] = $param['appid'];//公众号appid
        $advertiser['account_name'] = $param['account_name'];//公众号名称
        $advertiser['is_delete'] = 1;
        
        
        
        //投放计划表
        $campaign['campaign_name'] = $param['campaign_name'];//计划名称
        $campaign['ad_location'] = $param['ad_location'];//广告位（1：朋友圈，2：公众号顶部，3：公众号底部）
        $campaign['campaign_type'] = $param['campaign_type'];//推广计划类型(1: 普通展示广告-不使用，2: 微信公众号广告，3: 微信朋友圈广告)
        $campaign['product_type'] = $param['product_type'];//推广目标/标的物类型( 1:品牌推广, 2:商品推广, 3:门店推广) 
        $campaign['daily_budget'] = $param['daily_budget']*100;//日消耗限额，微信朋友圈不可使用！5,000 分-400,000,000 分之间（ 50 元-4,000,000 元）
        $campaign['is_delete'] = 1;
        
        
        $origin = isset($param['origin']) ? explode(',', $param['origin']) : '';
        
        //广告内容
        $adgroup['adgroup_name'] = $param['adgroup_name'];//广告组名称
        $adgroup['site_set'] = 'SITE_SET_WECHAT';//投放地点集合（写死：["SITE_SET_WECHAT"]）
        $adgroup['begin_date'] = $param['begin_date'];//投放开始日期
        $adgroup['end_date'] = $param['end_date'];//投放结束日期
        $adgroup['time_slot_start'] = $param['time_slot_start'];//投放开始时间段
        $adgroup['time_slot_end'] = $param['time_slot_end'];//投放结束时间段
        $adgroup['origin'] = is_array($origin) > 0 ? json_encode($origin) : '';//地区数组
        $adgroup['age_bracket'] = isset($param['age_bracket']) ? $param['age_bracket'] : 0;//年龄间隔（0:不限，1：1~17岁，2：18~23岁，3：24~30岁，4：31~40岁，5：41~50岁，6：51~65岁，7：66~127岁） 
        $adgroup['gender'] = isset($param['gender']) ? $param['gender'] : 0;//性别（0：不限，1：男，2：女）
        $adgroup['interest'] = isset($param['interest']) ? $param['interest'] : 0;//兴趣（值：1-16,21,25 ）config:weixinad:interest健对应
        $adgroup['education'] = isset($param['education']) ? $param['education'] : 0;//学历（0：未知，1：博士，2：硕士，3：本科，4：专科，5：高中，6：初中，7：小学）
        $adgroup['user_os'] = isset($param['user_os']) ? $param['user_os'] : 0;//操作系统（0：不限，1：IOS，2：安卓系统）
        $adgroup['daily_budget'] = $param['daily_budget']*100;//日限额（仅限微信朋友圈：100,000 – 1,000,000,000分之间）
        $adgroup['bid_amount'] = $param['bid_amount']*100;//广告出价（10-200,000分之间）
        $adgroup['billing_event'] = 1;//计费类型（1:点击量）config:weixinad:billing_event
        $adgroup['optimization_goal'] = 1;//广告优化目标类型（1:点击量）config:weixinad:optimization_goal
        $adgroup['product_refs_id'] = isset($param['product_refs_id']) ? $param['product_refs_id'] : '';//门店ID
        $adgroup['is_delete'] = 1;
        
        
        
        //广告创意
        $adcreative['adcreative_name'] = $param['adcreative_name'];//创意名称
        $adcreative['ad_type'] = (int)$param['ad_type'];//广告样式（1：图文，2：图片）
        $adcreative['title'] = isset($param['title']) ? $param['title'] : '';//标题（1-14字符）
        $adcreative['description'] = isset($param['description']) ? $param['description'] : '';//描述（1-28字符）
        $adcreative['image'] = $param['image'];//元素图片
        $adcreative['show_image_status'] = isset($param['show_image_status']) ? $param['show_image_status'] : 0;//图文展示方式（1：纯图，2：图文）仅朋友圈可用
        $adcreative['link_type'] = (int)$param['link_type'];//点击跳转类型（1：已绑小程序，2：自定义链接）
        $adcreative['link_name_type'] = $param['link_name_type'];//链接名称类型（1：查看详情，2：领取优惠券，3：预约活动，4：进入小程序）
        $adcreative['mini_program_type'] = $param['mini_program_type'];//小程序关联类型（1：关联小程序，2：自定义输入）
        if($param['mini_program_type'] == 1 && isset($param['applet_id']) && $param['applet_id'] > 0)
        {
            $wxinfo = WeixinInfo::check_one_id($param['applet_id']);// 我们平台小程序id
            $adcreative['mini_program_id'] = $wxinfo['user_name'];//小程序id（小程序原始ID）
            $adcreative['mini_program_path'] = '/pages/decorate/decorate';//已绑定小程序路径
        }
        else
        {
            $adcreative['mini_program_id'] = $param['mini_program_id'];//小程序id（小程序原始ID）
            $adcreative['mini_program_path'] = $param['mini_program_path'];//小程序路径
        }
        $adcreative['share_title'] = isset($param['share_title']) ? $param['share_title'] : '';//分享标题
        $adcreative['share_description'] = isset($param['share_description']) ? $param['share_description'] : '';//分享描述
        $adcreative['is_delete'] = 1;
        
        
        
        
        return ['advertiser' => $advertiser, 'campaign' => $campaign, 'adgroup' => $adgroup, 'adcreative' => $adcreative];
    }


    /**
     * 数据验证
     */
    static function checkDistribData($param)
    {
        $return_data = ['errcode'=>0, 'errmsg'=>""];
        //推广计划
        //计划名称
        if(!isset($param['campaign_name']) || $param['campaign_name'] == '' || mb_strlen($param['campaign_name']) > 40){
            $return_data['errcode'] = 99001;
            $return_data['errmsg'] = "参数非法";
            $return_data['errfield'] = "campaign_name";
        }
        //推广计划类型
        if(!isset($param['campaign_type']) || (int)$param['campaign_type'] <= 0 || (int)$param['campaign_type'] > 3){
            $return_data['errcode'] = 99001;
            $return_data['errmsg'] = "参数非法";
            $return_data['errfield'] = "campaign_type";
        }
        //推广目标/标的物类型
        if(!isset($param['product_type']) || (int)$param['product_type'] <= 0 || (int)$param['product_type'] > 3){
            $return_data['errcode'] = 99001;
            $return_data['errmsg'] = "参数非法";
            $return_data['errfield'] = "product_type";
        }
        //广告位
        if(!isset($param['ad_location']) || (int)$param['ad_location'] <= 0 || (int)$param['ad_location'] > 3){
            $return_data['errcode'] = 99001;
            $return_data['errmsg'] = "参数非法";
            $return_data['errfield'] = "ad_location";
        }
        
        //广告内容/广告组
        //广告内容名
        if(!isset($param['adgroup_name']) || $param['adgroup_name'] == '' || mb_strlen($param['adgroup_name']) > 40){
            $return_data['errcode'] = 99001;
            $return_data['errmsg'] = "参数非法";
            $return_data['errfield'] = "adgroup_name";
        }
        //投放开始时间
        if(!isset($param['begin_date']) || $param['begin_date'] == ''){
            $return_data['errcode'] = 99001;
            $return_data['errmsg'] = "参数非法";
            $return_data['errfield'] = "begin_date";
        }
        //投放结束时间
        if(!isset($param['end_date']) || $param['end_date'] == ''){
            $return_data['errcode'] = 99001;
            $return_data['errmsg'] = "参数非法";
            $return_data['errfield'] = "end_date";
        }
        //出价
        if(!isset($param['bid_amount']) || $param['bid_amount'] < 0.1 || $param['bid_amount'] > 2000){
            $return_data['errcode'] = 99001;
            $return_data['errmsg'] = "参数非法";
            $return_data['errfield'] = "bid_amount";
        }
        
        
        
        //广告创意
        //广告样式（1：图文，2：图片）
        if(!isset($param['ad_type']) || $param['ad_type'] < 0 || $param['ad_type'] > 2){
            $return_data['errcode'] = 99001;
            $return_data['errmsg'] = "参数非法";
            $return_data['errfield'] = "ad_type";
        }
        //图片
        if(!isset($param['image']) || $param['image'] == '' || mb_strlen($param['image']) > 1000){
            $return_data['errcode'] = 99001;
            $return_data['errmsg'] = "参数非法";
            $return_data['errfield'] = "image";
        }
        if($param['ad_type'] == 1)
        {
            if(!isset($param['title']) || $param['title'] == '' || mb_strlen($param['title']) > 1000){
                $return_data['errcode'] = 99001;
                $return_data['errmsg'] = "参数非法";
                $return_data['errfield'] = "title";
            }
            if(!isset($param['description']) || $param['description'] == '' || mb_strlen($param['description']) > 1000){
                $return_data['errcode'] = 99001;
                $return_data['errmsg'] = "参数非法";
                $return_data['errfield'] = "description";
            }
        }
        
        if($param['link_type'] == 2)
        {
            //小程序id = 小程序appid integer
            if(!isset($param['mini_program_id']) || $param['mini_program_id'] == '' || mb_strlen($param['mini_program_id']) > 255){
                $return_data['errcode'] = 99001;
                $return_data['errmsg'] = "参数非法";
            $return_data['errfield'] = "mini_program_id";
            }
            if($param['link_type'] == 2 && 
            (!isset($param['mini_program_path']) || $param['mini_program_path'] == '' || mb_strlen($param['mini_program_path']) > 1000)){
                $return_data['errcode'] = 99001;
                $return_data['errmsg'] = "参数非法";
            $return_data['errfield'] = "mini_program_path";
            }
            if($param['link_type'] == 1 && 
            (!isset($param['link_name_type']) || $param['link_name_type'] <= 0 || mb_strlen($param['link_name_type']) > 4)) {
                $return_data['errcode'] = 99001;
                $return_data['errmsg'] = "参数非法";
            $return_data['errfield'] = "link_name_type";
            }
        }
        
        return  $return_data;
    }
    
    
    /**
     * curl
     */
    public static function curl($url,$data='',$method='GET') {
        $headers = array(
            'App-Key: 9d2ae8uaf83y3b45',
        );
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL,$url);
        curl_setopt($ch, CURLOPT_TIMEOUT,10);
        if(strtoupper($method)=='PUT'){
            $method='POST';
            $data['_method']='PUT';
        }else if(strtoupper($method)=='DELETE'){
            $method='POST';
            $data['_method']='DELETE';
        }
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); //不验证SSL接口 本地打开,服务器上注释掉 
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $cont = curl_exec($ch);
        if (curl_errno($ch)) {	//抓取异常
            return json_encode(array('state'=>'-1','cont'=>curl_error($ch),'url'=>$url,'errno'=>curl_errno($ch)));
        }
        curl_close($ch);
        return $cont;
    
    }

}

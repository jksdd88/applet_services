<?php
/**
 * Created by PhpStorm.
 * User: lizhenheng
 * Date: 2017-11-20
 * Time: 上午 10:23
 */
namespace App\Http\Controllers\Super;


use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Response;
use App\Models\Merchant;
use App\Models\WeixinOpen;
use App\Models\WeixinInfo;
use App\Models\WeixinTemplate;
use App\Models\OperateRewardDetail;
use App\Models\Region;
use App\Models\User;
use App\Models\UserLog;
use Illuminate\Http\Request;


class MerchantController extends Controller
{
    protected $request;
    protected $params;

    public function __construct(Request $request) {
        $this->request = $request;
        $this->params = $request->all();
    }

    public function merchantList(){
        $offset = isset($this->params['offset']) && $this->params['offset'] ? $this->params['offset'] : 0;
        $limit = isset($this->params['limit']) && $this->params['limit'] ? $this->params['limit'] : 20;
        $query=Merchant::select('merchant.id','merchant.company','merchant.country','merchant.province','merchant.city','merchant.district',
            'merchant.address','merchant.contact','merchant.mobile','merchant.logo','merchant.type','merchant.income','merchant.payout','merchant.nosettle','merchant.industry_sign','merchant.version_id'
            ,'merchant.source','merchant.status','merchant.is_demo','merchant.expire_time','merchant.created_time','merchant.updated_time','user.username')->leftJoin("user","user.merchant_id","=","merchant.id");
        if (!empty($this->params['id'])) {  //商户号
            $query->where('merchant.id','=',$this->params['id']);
        }

        if (!empty($this->params['mobile'])) {
            $query->where('merchant.mobile', "like", '%' . $this->params['mobile'] . '%');
        }

        if (!empty($this->params['company'])) {  //公司名
            $query->where('merchant.company','like','%'.$this->params['company'].'%');
        }
        if (!empty($this->params['startTime'])) {  //创建时间
            $query->where('merchant.created_time','>=',$this->params['startTime']);
        }
        if (!empty($this->params['endTime'])) {  //结束时间
            $query->where('merchant.created_time','<=',$this->params['endTime']);
        }
//        $query->where('merchant.status','<>',-1);

        if (!empty($this->params['username'])) {  //用户名查询
            $query->where( 'user.username', "like", '%' . $this->params['username'] . '%');
        }

        $count = $query->count();
        $query->skip($offset);
        $query->take($limit);
        $query->orderBy('merchant.id','desc');
        $list = $query->get();
        if($list){
            foreach ($list as $key=>$val){
                $country_title=Region::get_data_by_id($val["country"]);
                $list[$key]["country_title"]=$country_title["title"];
                $province_title=Region::get_data_by_id($val["province"]);
                $list[$key]["province_title"]=$province_title["title"];
                $city_title=Region::get_data_by_id($val["city"]);
                $list[$key]["city_title"]=$city_title['title'];
                $district_title=Region::get_data_by_id($val["district"]);
                $list[$key]["district_title"]=$district_title['title'];
                $weixincount = WeixinInfo::count_app_data($val["id"]);
                $open = WeixinOpen::list_data('merchant_id',$val["id"]);
                if($weixincount==0 && $open){
                    $list[$key]["is_open"]=1;
                }else{
                    $list[$key]["is_open"]=0;
                }
            }
        }

        $data['errcode'] = 0;
        $data['_count'] = $count;
        $data['data'] = $list;
        return Response :: json($data);
    }

    public function weixinInfo(){
        $offset = isset($this->params['offset']) && $this->params['offset'] ? $this->params['offset'] : 0;
        $limit = isset($this->params['limit']) && $this->params['limit'] ? $this->params['limit'] : 20;
        $merchant_id = isset($this->params['merchant_id']) && $this->params['merchant_id'] ? $this->params['merchant_id'] : 0;
        if($merchant_id){
            $query=WeixinInfo::select('*');
            $query->where('merchant_id','=',$merchant_id);
            $query->where('status','=',1);
            $query->where('type','=',1);
            $count = $query->count();
            $query->skip($offset);
            $query->take($limit);
            $query->orderBy('id','desc');
            $list = $query->get();
        }
        $data['errcode'] = 0;
        $data['_count'] = $count;
        $data['data'] = $list;
        return Response :: json($data);
    }

    //刷新二维码
    public function rQrcode(){
        $merchant_id = isset($this->params['merchant_id']) && $this->params['merchant_id'] ? $this->params['merchant_id'] : 0;
        $appid = isset($this->params['appid']) && $this->params['appid'] ? $this->params['appid'] : '';
        $user='root';
        $password='493ceb07fa8a2d1a0a18a8a67c644322';

        $apiUrl="https://".$_SERVER['SERVER_NAME'].'/weixin/operation/rQrcode.json';
        $data['merchant_id']=$merchant_id;
        $data['appid']=$appid;
        $data['user']=$user;
        $data['password']=$password;
//        $proxy = empty(env('PROXY_IP')) ? null : ['proxy' => env('PROXY_IP'). ':' . env('PROXY_PORT')];

        $result = mxCurl($apiUrl, $data, false, null);
        return $result;
    }
    //小程序版本升级
    public function version(){
        $user='root';
        $password='492wdc07fa8a2d1a0a18a8a67c644322';

        $apiUrl="https://".$_SERVER['SERVER_NAME'].'/weixin/operation/version.json';
        $data['user']=$user;
        $data['password']=$password;
//        $proxy = empty(env('PROXY_IP')) ? null : ['proxy' => env('PROXY_IP'). ':' . env('PROXY_PORT')];

        $result = mxCurl($apiUrl, $data, false, null);
        return $result;
    }

    /**
     * 获取商户变动详情
     *  Author:zhangyu1@dodoca.com
     */
    public function getMerchantLog(Request $request){

        $merchant_id =  $request->id;

        $offset = isset($this->params['offset']) ? intval($this->params['offset']) : 0;

        $limit = isset($this->params['limit']) ? intval($this->params['limit']) : 10;

        $query = UserLog::select('user_log.type','user_log.merchant_id','user_log.user_id','user_log.created_time','user_log.ip','merchant.company')->leftJoin("merchant","user_log.merchant_id","=","merchant.id");

        $query->where('user_log.merchant_id','=',$merchant_id);

        if(!empty($this->params['type'])){     //记录类型查询

            $query->where('user_log.type','=',$this->params['type']);
        }

        $count = $query->count();

        $query->skip($offset);

        $query->take($limit);

        $query->orderBy('user_log.created_time','desc');

        $list = $query->get();

        $detail = config('userlog');    //配置文件

        foreach ($list as $key=>$val){

            foreach ($detail as $k=>$v){

                if($val['type'] == $k){

                    $list[$key]['type'] = $v;
                }
            }
        }

       foreach($detail as $k=>$v){

            $log_type[$k]['value'] = $k;

            $log_type[$k]['label'] = $v;
       }

        $data['errcode'] = 0;

        $data['errmsg'] = '商家记录列表 查询成功';

        $data['_count'] = $count;

        $data['data'] = $list;

        $data['log_type'] = array_values($log_type);

        return Response :: json($data);
    }

    /**
     *  邀请好友开店排行
     *  Author:zhangyu1@dodoca.com
     *  2018-03-12
     *
     */
    public function InviteList(){

        $offset = isset($this->params['offset']) ? intval($this->params['offset']) : 0;

        $limit = isset($this->params['limit']) ? intval($this->params['limit']) : 10;

        $query = Merchant::select("merchant.id","merchant.company","merchant.contact","merchant.mobile","merchant.status","merchant.referee_merchant_id","merchant.version_id","merchant.source","b.company as referee_company")->leftJoin("merchant as b","b.id","=","merchant.referee_merchant_id");

        $query->where('merchant.status','<>',-1);

        $invite_merchants = Merchant::select('referee_merchant_id')->where('merchant.status','<>',-1)->where('referee_merchant_id','<>',0)->get()->toArray();

        $merchant_type = isset($this->params['type']) ? intval($this->params['type']) : 0;

        if($merchant_type == 1){     //受邀商家

            $query->where('merchant.referee_merchant_id','<>',0);

        }elseif($merchant_type == 2){   //发起邀请商家

            $query->whereIn('merchant.id',$invite_merchants);
        }

        $query->where(function ($query_2) use ($invite_merchants){

            $query_2->where('merchant.referee_merchant_id','<>',0)->orWhere(function ($query_3)  use($invite_merchants){

                $query_3->whereIn('merchant.id',$invite_merchants);
            });
        });

        if (!empty($this->params['company'])) {  //公司名

            $company = $this->params['company'];

            $query->where(function ($query_1) use ($company) {

                $query_1->where('merchant.company','like','%'.$company.'%')->orWhere('b.company','like','%'.$company.'%');
            });

        }

        if (!empty($this->params['mobile'])) {  //手机号码

            $query->where('merchant.mobile','=',$this->params['mobile']);

        }

        $count = $query->count();

       /* $query->skip($offset);

        $query->take($limit);*/

        $query->orderBy('merchant.id','desc');

        $list = $query->get();

        if($list){

            foreach($list as $key=>$value){

                $all_merchants = Merchant::select("id")->where('referee_merchant_id','=',$value['id'])->get();   //查询所有被邀请的商家ID

                $list[$key]['replenish'] = 0;   //注册并完善商家数

                $list[$key]['empower'] = 0;    //授权成功商家数

                $list[$key]['release'] = 0;    //发布成功后商家数

                $list[$key]['goods_num'] = 0;   //获得商品商家数

                if($all_merchants){

                    foreach ($all_merchants as $k=>$v){

                        $replenist = Merchant::select("id")->where('company','<>','')->where('source','<>',10)->where('id',$v['id'])->get();     //查询是否完善信息

                        if(!empty($replenist)){

                            $list[$key]['replenish'] = $list[$key]['replenish'] + 1;
                        }

                        $empower = WeixinInfo::select(\DB::raw('count(DISTINCT appid) as num'))->where('appid','<>','')->where(array('merchant_id'=>$v['id'],'type'=>1))->get();      //查询是否授权

                        if(!empty($empower)){

                            $list[$key]['empower'] = $list[$key]['empower'] + $empower[0]['num'];
                        }

                        $release = WeixinTemplate::select(\DB::raw('count(DISTINCT appid) as num'))->where(array('release'=>1,'merchant_id'=>$v['id']))->get();      //查询是否发布成功

                        if(!empty($release)){

                            $list[$key]['release'] = $list[$key]['release'] + $release[0]['num'];
                        }
                    }

                    $reward = OperateRewardDetail::select(\DB::raw('sum(reward) as reward'))->where('referee_merchant_id',$value['id'])->get();

                    if(!empty($reward)){

                        $list[$key]['goods_num'] = $list[$key]['goods_num'] + $reward[0]['reward'];
                    }

                }
            }

            $temp =array();

            for($i=0;$i<count($list);$i++){

                for($j=0;$j<count($list)-$i-1;$j++){

                    if($list[$j]['replenish']<$list[$j+1]['replenish']){

                        $temp=$list[$j]; $list[$j]=$list[$j+1]; $list[$j+1]=$temp;
                    }
                }
            }
            $list = json_decode($list);

            $list = array_slice($list,$offset,$limit);

        }

        $data['errcode'] = 0;

        $data['_count'] = $count;

        $data['data'] = $list;

        return Response :: json($data);
    }

}
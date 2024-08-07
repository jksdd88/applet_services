<?php
/**
 * Created by PhpStorm.
 * User: zhangyu1@dodoca.com
 * Date: 2018/3/16
 * Time: 10:47
 */
namespace App\Http\Controllers\Super;

use App\Http\Controllers\Controller;
use App\Models\Merchant;
use App\Models\User;
use App\Models\WeixinInfo;
use App\Models\WeixinTemplate;
use Illuminate\Support\Facades\Response;
use Illuminate\Http\Request;
use Config;

Class WeappUpdateController extends Controller{

    protected $request;
    protected $params;

    public function __construct(Request $request) {
        $this->request = $request;
        $this->params = $request->all();
    }

    /**
     *  上线新版本(调用王世亮接口)
     *  zhangyu1@dodoca.com
     *
     */
    public function OnlineVersion(){

        $user='root';    //用户名

        $password='492wdc07fa8a2d1a0a18a8a67c644322';    //密码

        //$apiUrl="https://applet.dodoca.com/weixin/operation/version.json";     //调用 接口

        $apiUrl="https://".$_SERVER['SERVER_NAME'].'/weixin/operation/version.json';     //调用接口

        $data['user']=$user;

        $data['password']=$password;

        $result = mxCurl($apiUrl, $data, false, null);

        return $result;
    }


    /**
     *  版本列表(调用王世亮接口)
     *
     *  zhangyu1@dodoca.com
     */
    public function VersionList(){

        $user='root';    //用户名

        $password='492wdc07fa8a2d1a0a18a82wd8tf6aq2';    //密码

        //$apiUrl="https://applet.dodoca.com/weixin/operation/getver.json";     //调用接口

        $apiUrl="https://".$_SERVER['SERVER_NAME'].'/weixin/operation/getver.json';     //调用接口

        $data['user']=$user;

        $data['password']=$password;

        $result = mxCurl($apiUrl, $data, false, null);

        return $result;
    }

    /**
     *  全网发布(调用王世亮接口)
     *
     *  zhangyu1@dodoca.com
     */
    public function PublishNet(){

        $user='root';    //用户名

        $password='492wdc07fa8a2d1a0a18a8a67c644322';    //密码

        $id = $this->params['id'];

        //$apiUrl="https://applet.dodoca.com/weixin/operation/onlinever.json";     //调用接口

        $apiUrl="https://".$_SERVER['SERVER_NAME'].'/weixin/operation/onlinever.json';     //调用接口

        $data['user']=$user;

        $data['password']=$password;

        $data['id'] = $id;

        $result = mxCurl($apiUrl, $data, false, null);

        return $result;
    }


    /**
     *  手动全网升级小程序版本(调用王世亮接口)
     *
     *  zhangyu1@dodoca.com
     */
    public function ManualUpdate(){

        $user='root';    //用户名

        $password='493ceb07fa8a2d1a0a18a8a67c644311';    //密码

        //$apiUrl="https://applet.dodoca.com/weixin/operation/upgrade.json";     //调用接口

        $apiUrl="https://".$_SERVER['SERVER_NAME'].'/weixin/operation/upgrade.json';     //调用接口

        $data['user']=$user;

        $data['password']=$password;

        $result = mxCurl($apiUrl, $data, false, null);

        return $result;
    }

    /**
     *  手动验证小程序升级状态(调用王世亮接口)
     *
     *  zhangyu1@dodoca.com
     */
    public function ManualVerify(){

        $user='root';    //用户名

        $password='391fff20b7806d9828a7cbc145d2a5b7';    //密码

        //$apiUrl="https://applet.dodoca.com/weixin/operation/verify.json";     //调用接口

        $apiUrl="https://".$_SERVER['SERVER_NAME'].'/weixin/operation/verify.json';     //调用接口

        $data['user']=$user;

        $data['password']=$password;

        $result = mxCurl($apiUrl, $data, false, null);

        return $result;
    }

    public function applist(){
        $company = $this->request->get('company','');//商户登录名
        $version = $this->request->get('version','');//
        $industry = $this->request->get('industry','');
        $name    = $this->request->get('name','');//小程序名称
        $start = $this->request->get('start','');//授权时间
        $end   = $this->request->get('end','');//
        $appid = $this->request->get('appid','');//小程序appid
        $ghid  = $this->request->get('ghid',''); //小程序原始id
        $page  = $this->request->get('page',1);//
        $leng  = $this->request->get('length',15);


        $query = WeixinInfo::query();
        if(!empty($start) ){
            $query ->where('auth_time','>',$start);
        }
        if(!empty($end) ){
            $query ->where('auth_time','<',$end);
        }
        if(!empty($name) ){
            $query ->where('nick_name','like','%'.$name.'%');
        }
        if(!empty($appid)){
            $query ->where('appid','=',$appid);
        }
        if(!empty($ghid) ){
            $query ->where('user_name','=',$ghid);
        }
        $query ->where('status','=',1);
        $query ->where('appid','!=','');

        if(!empty($company) || ($version != -1 && $version != '')  || ($industry != -1 && $industry != '') ){
            $query->whereExists(function ($merchantQuery )use($company, $version, $industry){
                $merchantQuery ->select(['id'])->from('merchant');
                if(!empty($company)){
                    $merchantQuery->where('company','like','%'.$company.'%');
                }
                if($version != -1 && $version != ''){
                    $merchantQuery->where('version_id','=',$version);
                }
                if($industry != -1 && $industry != ''){
                    $merchantQuery->where('industry_sign','=',$industry);
                }
                $merchantQuery->whereRaw('merchant.id = weixin_info.merchant_id');
            });
        }
        $count   =  $query ->count();
        $query ->skip(($page-1)* $leng)->take($leng)-> orderBy('id', 'DESC');
        $list = $query->get(['id','merchant_id','appid','type','auth','tpl_type','nick_name','head_img','qrcode','user_name','signature','principal_name','auth_time'])->toArray();

        $industrylist = Config::get('industrysign');
        foreach ($industrylist as $k => $v) {
            $industrylist[$k] = ['id'=>$k,'name'=>$v['name']];
        }
        $versionlist  = Config::get('version');
        foreach ($versionlist as $k => $v) {
            if($k == 1){
                $versionlist[$k] = ['id'=>$k,'name'=>$v['name']."（旧）"];
            }else{
                $versionlist[$k] = ['id'=>$k,'name'=>$v['name']];
            }
        }
        $host  = Config::get('weixin.qn_host');
        foreach ($list as $k=> $v) {
            $list[$k]['qrcode'] = $v['qrcode'] == '' ? '' : $host.$v['qrcode'];
            $merchant = Merchant::get_data_by_id($v['merchant_id']);
            $list[$k]['version']  = isset( $versionlist[$merchant['version_id']]['name'] )?$versionlist[$merchant['version_id']]['name'] : $merchant['version_id'];
            $list[$k]['industry'] = isset( $industrylist[$merchant['industry_sign']]['name'] )?$industrylist[$merchant['industry_sign']]['name'] : $merchant['industry_sign'];
            $list[$k]['company']  = $merchant['company'];

        }
        foreach ($industrylist as $k => $v ) {
            $industrylistx[] = $v;
        }
        foreach ($versionlist as $k => $v) {
            $versionlistx[] = $v;
        }
        return ['errcode'=>0, 'errmsg'=>'ok', 'data'=>[ 'list'=>$list, 'count' => $count, 'industry_list' => $industrylistx, 'version_list' => $versionlistx]];
    }

}
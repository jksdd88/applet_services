<?php
namespace App\Http\Controllers\Weapp\SuperCase;

use Illuminate\Http\Request;
use App\Http\Requests;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\SuperCase;
use Illuminate\Support\Facades\Session;
use Config;
use App\Facades\Member;
use App\Services\SuperCaseService;


class SuperCaseController extends Controller
{
    private $fontFile = "font/Microsoft-Yahei.ttf"; //字体文件，微软雅黑

    //行业分类列表
    public function getIndustry(Request $request)
    {
        $params = $request->all();
        $industrycat = isset($params['industrycat']) ? $params['industrycat'] : '';
        if($industrycat == 'all' ) {
            $industry = Config::get('industrycat') ? Config::get('industrycat') : '';
        }else{
            $industry = Config::get('industrycat') ? Config::get('industrycat') : '';
            foreach ($industry as $k => $v) {//过滤没有案例的分类
                $where = ['is_delete'=>1,'onshow'=>1,'industry'=>$v['id']];
                $count = SuperCase::where($where)->count();
                if(!$count){
                    unset($industry[$k]);
                } 
            }
            $industry =array_values($industry);
        }
        return ['errcode' => 0, 'errmsg' => '获取数据成功', 'data' => $industry];
    }


    //案例列表
    public function getCases(Request $request)
    {
        $params = $request->all();
        $page = isset($params['page']) ? (int)$params['page'] : 1;
        $pagesize = isset($params['pagesize']) ? (int)$params['pagesize'] : 10;
        $industry = isset($params['industry']) ? (int)$params['industry'] : 0;
        $where = ['is_delete'=>1,'onshow'=>1,'industry'=>$industry];
        $query = SuperCase::where($where);
        $count  = $query->count();
        $query->orderBy('sort','asc')->orderBy('created_time','desc');
        $data = $query->select('id','xcxcode','xcxname','cardimg')->offset(($page-1)*$pagesize)->limit($pagesize)->get()->toArray();
        return ['errcode' => 0, 'errmsg' => '获取数据成功', 'data' => $data, 'count'=> $count];        
    }



    //添加案例
    public function postCase(Request $request){
        $params = $request->all();
        $name = isset($params['name']) && $params['name']  ? $params['name'] : ''; 
        $mobile = isset($params['mobile']) && $params['mobile'] ? $params['mobile'] : ''; 
        $xcxname = isset($params['xcxname']) && $params['xcxname']  ? $params['xcxname'] : ''; 
        $version = isset($params['version']) && $params['version'] ? intval($params['version']) : 0; 
        $industry = isset($params['industry']) ? intval($params['industry']) : 0;
        $xcxcode = isset($params['xcxcode']) ? htmlspecialchars($params['xcxcode']) : '';
        $sort = isset($params['sort']) ? htmlspecialchars($params['sort']) : '';
        $source = 1;
        if(!$xcxname){
             return Response::json(['errcode' => 320001, 'errmsg' => '小程序名称不能为空']);
        }
        if(!$version){
             return Response::json(['errcode' => 320002, 'errmsg' => '小程序版本不能为空']);
        }
        if(!$industry){
             return Response::json(['errcode' => 320003, 'errmsg' => '小程序行业分类不能为空']);
        }
        if(!$xcxcode){
             return Response::json(['errcode' => 320004, 'errmsg' => '小程序码不能为空']);
        }
         //小程序名称是否存在
        $exist =  SuperCase::where(['is_delete'=>1,'xcxname'=>$xcxname])->first();
        if($exist){
            return Response::json(['errcode' => 320005, 'errmsg' => '小程序名称已存在']);
        }
        $img = (new SuperCaseService())->caseCard($params);
        $cardimg = $img['data'];
        $CaseData = [   
            'name' => $name,
            'mobile' => $mobile,     
            'xcxname' => $xcxname,
            'version' => $version,
            'industry' => $industry,
            'xcxcode' => $xcxcode,
            'source' => $source,
            'sort' => $sort,
            'cardimg' => $cardimg,
            'created_time' => Carbon::now(),
            'updated_time' => Carbon::now()
        ];     
        $result = SuperCase::insert_data($CaseData);
        if($result){
            return Response::json(['errcode' => 0, 'errmsg' => '案例保存成功']);
        }else{
            return Response::json(['errcode' => -1, 'errmsg' => '案例添加失败']);
        }

    }

    //案例详细
    public function getCase($id)
    { 
        if (!$id) {
            return Response::json(['errcode' => 99001, 'errmsg' => '参数错误']);
        }
        $caseData = SuperCase::get_data_by_id($id,'cardimg');
        if(!$caseData){
             return Response::json(['errcode' => 320008, 'errmsg' => '小程序案例不存在']);
        }
        return Response::json(['errcode' => 0, 'data' => $caseData]);
    }

    
}

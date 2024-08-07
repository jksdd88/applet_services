<?php
namespace App\Http\Controllers\Super;


use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\SuperCase;
use Illuminate\Support\Facades\Session;
use App\Services\SuperCaseService;

class CaseController extends Controller
{

    private $super_user_id;

    public function __construct()
    {
        $this->super_user_id=Session::get('super_user.id');
    }



    //案例列表
    public function getCases(Request $request)
    {
        $params = $request->all();
        $page = isset($params['page']) ? (int)$params['page'] : 1;
        $pagesize = isset($params['pagesize']) ? (int)$params['pagesize'] : 10;
        $name = isset($params['name']) ? (string)$params['name'] : '';
        $time = isset($params['time']) ? (string)$params['time'] : '';
        $version = isset($params['version']) ? (int)$params['version'] : 0; //小程序版本  1免费版 2普通版 3 标准版
        $industry = isset($params['industry']) ? (int)$params['industry'] : 0; 
        $onshow = isset($params['onshow']) ? $params['onshow'] : 'all';
        $where = ['is_delete'=>1];

        $query = SuperCase::where($where);

        if($name) {
            $query->where("xcxname","like",'%'.$name.'%');
        }
        if($version) {
            $query->where("version",$version);
        }
        if($industry) {
            $query->where("industry",$industry);
        }
        if(is_numeric($onshow)) {
            $query->where("onshow",$onshow);
        }
        
        //开始时间
        if($time){
            $query->where('created_time','>=',$time.' 00:00:00')->where('created_time','<=',$time.' 23:59:59');
        }

        $count  = $query->count();

        $query->orderBy('sort','asc')->orderBy('created_time','desc');

        $data = $query->offset(($page-1)*$pagesize)->limit($pagesize)->get()->toArray();

        return ['errcode' => 0, 'errmsg' => '获取数据成功', 'data' => $data, 'count'=> $count];        
    }



    //添加案例
    public function putCase(Request $request){
        $params = $request->all();
        $id = isset($params['id']) && $params['id'] ? intval($params['id']) : 0;   
        $xcxname = isset($params['xcxname']) && $params['xcxname']  ? $params['xcxname'] : ''; 
        $version = isset($params['version']) && $params['version'] ? intval($params['version']) : 0; 
        $industry = isset($params['industry']) ? intval($params['industry']) : 0;
        $xcxcode = isset($params['xcxcode']) ? htmlspecialchars($params['xcxcode']) : '';
        $sort = isset($params['sort']) ? htmlspecialchars($params['sort']) : '';
        $source = 2;
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
        $img = (new SuperCaseService())->caseCard($params);
        $cardimg = $img['data'];
        $CaseData = [         
            'super_user_id' => $this->super_user_id,
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
        if($id){    
            //小程序名称是否存在
            $exist =  SuperCase::where(['is_delete'=>1,'xcxname'=>$xcxname])->where('id','!=',$id)->first();
            if($exist){
                return Response::json(['errcode' => 320005, 'errmsg' => '小程序名称已存在']);
            }
            $existdata = SuperCase::super_get_data_by_id($id);
            if(!$existdata){
                return Response::json(['errcode' => 99001, 'errmsg' => '参数错误']);
            }
            if($existdata['onshow'] == 1){
                return Response::json(['errcode' => 320006, 'errmsg' => '上架案例不允许修改']);
            }
            $result = SuperCase::super_update_data($id,$CaseData);

        }else{
            //小程序名称是否存在
            $exist =  SuperCase::where(['is_delete'=>1,'xcxname'=>$xcxname])->first();
            if($exist){
                return Response::json(['errcode' => 320005, 'errmsg' => '小程序名称已存在']);
            }
            $result = SuperCase::insert_data($CaseData);
        }
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
        $caseData = SuperCase::super_get_data_by_id($id);
        if(!$caseData){
             return Response::json(['errcode' => 320008, 'errmsg' => '小程序案例不存在']);
        }
        return Response::json(['errcode' => 0, 'data' => $caseData]);
    }


    //删除案例
    public function deleteCase($id)
    {
        if (!$id) {
            return Response::json(['errcode' => 99001, 'errmsg' => '参数错误']);
        }
        $res = SuperCase::super_get_data_by_id($id);
        if (!$res){
             return Response::json(['errcode' => 320008, 'errmsg' => '小程序案例不存在']);
        }
        //上架产品不可以删除
        $existdata = SuperCase::super_get_data_by_id($id);
        if($existdata['onshow'] == 1){
            return Response::json(['errcode' => 320009, 'errmsg' => '上架案例不允许删除']);
        }
        $data['is_delete'] = -1;
        $data['super_user_id'] = $this->super_user_id;
        $result = SuperCase::super_update_data($id,$data);
        if($result){
            return Response::json(['errcode' => 0, 'errmsg' => '小程序案例删除成功']);
        }else{
            return Response::json(['errcode' => -1, 'errmsg' => '小程序案例删除失败']);
        }
    }

    //上下架案例
    public function postOnshow(Request $request)
    {
        $params = $request->all();
        $id = isset($params['id']) && $params['id'] ? intval($params['id']) : 0;
        if (!$id) {
            return Response::json(['errcode' => 99001, 'errmsg' => '参数错误']);
        }
        $type = isset($params['type']) && $params['type'] ? intval($params['type']) : 0;
        if (!in_array($type, array(1, 2))) {
            return Response::json(['errcode' => 99001, 'errmsg' => '参数类型错误']);
        }
        $update_data['super_user_id'] = $this->super_user_id;
        if ($type == 1) {// 上架
            $update_data['onshow'] = 1;
        } else {//下架
            $update_data['onshow'] = 0;
        }
        $result = SuperCase::super_update_data($id,$update_data);
        if($result){
            return Response::json(['errcode' => 0, 'errmsg' => '操作成功']);
        }else{
            return Response::json(['errcode' => -1, 'errmsg' => '操作失败']);
        }
    }

     //导入
    public function postCaseCsv(){
        $file = $_FILES['file'];
        //$file = 'D:\20180704.csv';
        $valid = $this->checkCsvValid($file);
        if($valid !== true) {
             return $valid;
        }
        $s = file_get_contents($file); //读取文件到变量
        $s = iconv('GBK', 'UTF-8', $s);
        $s = str_replace('  ', '', $s);//去空格
        $results = $this->strGetcsv($s);
        if(is_array($results)){
            array_shift($results);
            foreach($results as $_row) {
                if($_row){
                    $data = [];
                    $data['xcxname'] = $_row[0];
                    $data['version'] = $_row[2];
                    $data['industry'] = $_row[3];
                    $data['xcxcode'] = $_row[1];
                    $img = (new SuperCaseService())->caseCard($data);
                    $cardimg = $img['data'];
                    $CaseData = [         
                        'xcxname' => $_row[0],
                        'version' => $_row[2],
                        'industry' => $_row[3],
                        'xcxcode' => $_row[1],
                        'source' => 2,
                        'sort' => 100,
                        'cardimg' => $cardimg,
                        'created_time' => Carbon::now(),
                        'updated_time' => Carbon::now()
                    ];
                     SuperCase::insert_data($CaseData);
                }
            }
            
        }
         return ['errcode'=>0, 'errmsg'=>'导入完成'];
    }

    private function checkCsvValid($file)
    {
        $mimes = array('application/vnd.ms-excel','application/octet-stream','text/plain','text/csv','text/tsv');

        if(isset($file['error']) && $file['error'] > 0) {
            return ['errcode'=>1, 'errmsg'=>$file['error']];
        }else if(!in_array($file['type'],$mimes)){
            return ['errcode'=>1, 'errmsg'=>'请上传csv文件'];
        } else if($file['size'] > 1024*1024) {
            return ['errcode'=>1, 'errmsg'=>'上传的CSV文件必须小于1MB'];
        }
        return true;
    }

    private function strGetcsv($string, $delimiter=',', $enclosure='"') {
        $fp = fopen('php://temp/', 'r+');
        fputs($fp, $string);
        rewind($fp);
        $r = [];
        while ($t = fgetcsv($fp, strlen($string), $delimiter, $enclosure)) {
            $r[] = $t;
        }
        if (count($r) == 1) {
            return current($r);
        }
        return $r;
    }


}

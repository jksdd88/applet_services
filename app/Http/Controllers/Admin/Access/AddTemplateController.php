<?php
/**
    *超级表单模板功能操作
    *@author renruiqi@dodoca.com
*/
namespace App\Http\Controllers\Admin\Access;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;


use Illuminate\Support\Facades\Auth;
use App\Models\Merchant;
use App\Models\FormCate;
use App\Models\FormTemplate;
use App\Models\FormInfo;
use Illuminate\Support\Facades\Response;
use App\Services\FormService;
class AddTemplateController extends Controller
{
    
     public function __construct()
     {  
        if (app()->isLocal()) {
            $this->merchant_id = 2;
        } else {
            $this->merchant_id = Auth::user()->merchant_id;
        }
     }
    //即将添加为模板的表单列表
    public function getFormList(Request $request)
    {
        if(empty($_GET['name']) || $_GET['name'] != '123qwe'){
            dd('非法操作');
            return redirect('manage/');
        }

        //查询表单
        $list = FormInfo::select('id','name','created_time','is_delete','is_template')
                ->whereIn('merchant_id',[0,$this->merchant_id])
                // ->where('is_template',2)
                ->where('is_delete',1)
                ->orderBy('is_template','desc')
                ->orderBy('id','desc')
                ->get();
        // dd($list);
        if(count($list) == 0){
            dd('请添加表单');
        }else{
            $list  = $list->toArray();
        }
        return  view('admin.form.form_list',['data'=>$list]);

    }

    //添加模板页面
    public function getFormOne(Request $request)
    {
        $form_id = (int)$request->form_id ;
        //判断该表是否是当前用户所拥有
        $is_ok =  FormInfo::whereIn('merchant_id',[0,$this->merchant_id])->find($form_id);
        if(!$is_ok) return redirect('manage/');
        //查询模板分类
        // $template_cate = $
        $template_cate = config('form_temp_type');
        // array_shift($template_cate);
        return  view('admin.form.get_form_one',['form_id'=>$form_id,'template_cate'=>$template_cate]);
    }

    //验证模板数据
    public function addFormlate(Request $request)
    {
       
        //验证表id
        $form_id = (int)$request->form_id ;
        $is_ok =  FormInfo::whereIn('merchant_id',[0,$this->merchant_id])->find($form_id);
        // if(!$is_ok) return redirect('manage/');
        if(!$is_ok) dd('该表单不属于本商户');
        //验证模板名称
        $name = trim($request->form_name);
        if($name=='' ||mb_strlen($name)>20){
            return '<script type="text/javascript">history.go(-1); alert("模板名称为1-20");</script>';
        }
        //验证图片地址
        $img_url = trim($request->form_img);
        if(mb_strlen($img_url)<= 40 ||mb_strlen($img_url)>= 44){
            return '<script type="text/javascript">history.go(-1); alert("图片地址格式错误")</script>';
        }
        //验证分类
        if(count($request->form_template)==0){
            return '<script type="text/javascript">history.go(-1); alert("分类必选")</script>';
        }
        $data['use_count'] = isset($request->use_count) ? intval($request->use_count) : 0 ;
        $data['type'] = ','.implode($request->form_template,',').',';
        $data['form_id'] = $form_id;
        $data['merchant_id'] = 0;
        $data['name'] = $name;
        $data['image'] = $img_url;
        $data['created_time'] = date('Y-m-d H:i:s');
        $data['updated_time'] = date('Y-m-d H:i:s');
        $data['is_delete'] = 1;
        //清除该表单原始数据
        FormTemplate::where('form_id',$data['form_id'])->delete();

        $res = FormTemplate::insert($data);
        if($res){
            //对表单数据进行修改
            FormInfo::whereIn('merchant_id',[0,$this->merchant_id])->where('id',$form_id)->update(['is_template'=>1,'merchant_id'=>0]);
            return '<script type="text/javascript"> alert("操作成功"); location.href="'.url('admin/formtemplate/form_list?name=123qwe').'";</script>';
        }else{
            return '<script type="text/javascript"> history.go(-1);alert("操作失败"); </script>';

        }

    }

    //修改模板
    public function TempLateOne(Request $request)
    {

        $form_id = $request->form_id;
        $data = FormTemplate::where('form_id',$form_id)->where('is_delete',1)->orderBy('id','desc')->first();
        if($data ==null){
            //数据为空
            $res = FormTemplate::where('form_id',$form_id)->update(['is_delete'=>-1]);
            FormInfo::where('id',$form_id)->update(['is_template'=>2]);

            return '<script type="text/javascript"> alert("该模板已经被删除");location.href="'.url('admin/formtemplate/form_list?name=123qwe').'"; </script>';
        }


        $data = $data->toArray();
        $data['image_url'] = env('QINIU_STATIC_DOMAIN').'/'.$data['image'];
        // dd(substr_count($data['type'],','.'1'.','));
        $template_cate = config('form_temp_type');
        // array_shift($template_cate);
        return  view('admin.form.get_template_one',['data'=>$data,'template_cate'=>$template_cate]);

    }

    //验证修改模板时候提交的数据
    public function checkTempLate(Request $request)
    {
        $form_id = (int)$request->form_id ;
            $is_ok =  FormInfo::whereIn('merchant_id',[0,$this->merchant_id])->find($form_id);    //表单不存在则为非法操作
            if(!$is_ok) dd('操作非法');
            //验证模板名称
            $name = trim($request->form_name);
            if($name=='' ||mb_strlen($name)>20){
                return '<script type="text/javascript">history.go(-1); alert("模板名称为1-20");</script>';
            }
            //验证图片地址
            $img_url = trim($request->form_img);
            if(mb_strlen($img_url)<= 40 || mb_strlen($img_url)>= 44){
                return '<script type="text/javascript">history.go(-1); alert("图片地址格式错误")</script>';
            }
            //验证分类
            if(count($request->form_template)==0){
                return '<script type="text/javascript">history.go(-1); alert("分类必选")</script>';
            }

            //删除模板则将form_info中is_template 改为1
            if($request->is_delete == -1){
                $data['is_delete'] = -1;
                FormInfo::where('id',$form_id)->update(['is_template'=>2]);
            }
            $data['type'] = ','.implode($request->form_template,',').',';
            $data['form_id'] = $form_id;
            $data['name'] = $name;
            $data['image'] = $img_url;
            $data['use_count'] = isset($request->use_count) ? intval($request->use_count) : 0 ;
            $data['updated_time'] = date('Y-m-d H:i:s');
            $res =  FormTemplate::where('form_id',$form_id)
                                ->where('is_delete',1)
                                ->update($data);
            return '<script type="text/javascript"> alert("操作成功"); location.href="'.url('admin/formtemplate/form_list?name=123qwe').'";</script>';
    }
    /*

     Route::get('formtemplate/form_list','Form\AddTemplateController@getFormList');//表单列表
    */

}

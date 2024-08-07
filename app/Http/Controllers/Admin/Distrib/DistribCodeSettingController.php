<?php

namespace App\Http\Controllers\Admin\Distrib;

use App\Models\DistribCodeSetting;
use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Validator;

class DistribCodeSettingController extends Controller
{
    function __construct(Request $request)
    {
        if (app()->isLocal()) {
            $this->merchant_id = 2;
        } else {
            $this->merchant_id = Auth::user()->merchant_id;
        }
    }
    
    /**
     * 获取推客码设置
     */
    public function get_row()
    {
        //判定是否存在
        $row = DistribCodeSetting::get_data_by_merchantid($this->merchant_id);
        
        return  Response::json(['errcode'=>0 , 'errmsg' => '操作成功', 'data' => $row]);
    }

    /**
     * 保存推客码设置
     * @param  $template          int     必选  推客码模板
     * @param  $background_img    int     必选  背景图
     * @param  $text1             int     必选  文案1
     * @param  $text2             string  必选  文案2
     */
    public function save(Request $request)
    {
        $data['template'] = (int)$request->input('template', 0);//模板
        $data['background_img'] = $request->input('background_img', '');//背景图
        $data['text1'] = $request->input('text1', '');//文案1
        $data['text2'] = $request->input('text2', '');//文案2
        
        //验证
        $check_res  = self::checkDistribCodeSetting($data);
        if($check_res['errcode'] != 0) return Response::json($check_res);//验证失败
        
        //判定是否存在
        $row = DistribCodeSetting::get_data_by_merchantid($this->merchant_id);
        if($row)
        {
            DistribCodeSetting::update_data($this->merchant_id, $data);
        } else {
            $data['merchant_id'] = $this->merchant_id;
            DistribCodeSetting::insert_data($data);
        }
        return  Response::json(['errcode'=>0 , 'errmsg'=>'设置已生效']);
    }
    
    
    /**
     * 验证推客码设置
     * @param  $request['template']          int     必选  推客码模板
     * @param  $request['background_img']    int     必选  背景图
     * @param  $request['text1']             int     必选  文案1
     * @param  $request['text2']             string  必选  文案2
     */
    private function checkDistribCodeSetting($request)
    {
        $check_res = ['errcode'=>0,'errmsg'=>""];
        
        if((int)$request['template'] < 1 || (int)$request['template'] > 4) //4种模板（1：模板1，2：模板2，3：模板3，4：模板4）
        {
            $check_res = ['errcode'=>99001,'errmsg'=>"参数非法"];
        }
        
        if($request['background_img'] == '')
        {
            $check_res = ['errcode'=>99001,'errmsg'=>"参数非法"];
        }
        
        if($request['text1'] == '')
        {
            $check_res = ['errcode'=>99001,'errmsg'=>"参数非法"];
        }
        
        if($request['text2'] == '')
        {
            $check_res = ['errcode'=>99001,'errmsg'=>"参数非法"];
        }
        
        if($request['text1'] == '')
        {
            $check_res = ['errcode'=>99001,'errmsg'=>"参数非法"];
        }
        
        if($request['text2'] == '')
        {
            $check_res = ['errcode'=>99001,'errmsg'=>"参数非法"];
        }
        
        if(mb_strlen($request['text1']) > 100)
        {
            $check_res = ['errcode'=>99001,'errmsg'=>"图片文案第一行，字数超过长度限制"];
        }
        
        if(mb_strlen($request['text2']) > 200)
        {
            $check_res = ['errcode'=>99001,'errmsg'=>"图片文案第二行，字数超过长度限制"];
        }
        
        return $check_res;
    }
}

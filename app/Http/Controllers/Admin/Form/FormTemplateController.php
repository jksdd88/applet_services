<?php

namespace App\Http\Controllers\Admin\Form;

use App\Models\FormTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Models\Merchant;

class FormTemplateController extends Controller
{
    /**
     * 表单模版列表
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $type = request()->get('type', 0);
        $pagesize = request()->get('pagesize', 10);
        $page = request()->get('page', 1);
        $where = [
            'merchant_id' => 0,
            'is_delete' => 1,
        ];
        $query = FormTemplate::where($where);
        // if (!empty($type)) $where['type'] = $type;
        if (!empty($type) && $type>0) {
            $query->whereRaw(" FIND_IN_SET(".$type.",type) ");
        }
        
        //免费版（新），基础版 不可使用预设模板
        $rs_merchant = Merchant::get_data_by_id(Auth::user()->merchant_id);
        if( in_array($rs_merchant['version_id'], array(5,6)) ){
            $query->whereRaw(" 1=0 ");
        }
        
        $data['_count'] = $query->count();
        $query->forPage($page, $pagesize)->latest('created_time');
        $data['lists'] = $query->get();
        if($data['_count']>0){
            foreach($data['lists'] as &$v){
                $v->image = env('QINIU_STATIC_DOMAIN').'/'.$v->image;
            }
        }
        return ['errcode' => 0, 'errmsg' => 'OK', 'data' => $data];
    }

    /**
     * 表单模版分类
     * @author: tangkang@dodoca.com
     */
    public function getTemplate()
    {
        $data['lists'] = config('form_temp_type');
        $data['_count'] = count($data['lists']);
        return ['errcode' => 0, 'errmsg' => 'OK', 'data' => $data];
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}

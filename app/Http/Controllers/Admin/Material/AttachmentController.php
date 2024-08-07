<?php

namespace App\Http\Controllers\Admin\Material;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Services\SuperAttachmentService;
use Illuminate\Support\Facades\Response;


class AttachmentController extends Controller
{
    public function __construct(SuperAttachmentService $superattachmentservice) {
        $this->service = $superattachmentservice;
    }
    /**
    *素材查询
    *@author renruiqi@qq.com
    *@param  $file_name strint 可选 要搜索的文件名
    *@param  $page int 可选 页码
    *@param  $page_size int 可选 每页显示条目数
    *@param  $group_id int 可选 分组id
    *@param  $scene_type 可选 适用场景 0为普通 1为素材库
    */
    public function index(Request $request){
        $request['scene_type'] = 1; //添加限定条件
        $service_rt = $this->service->getAttachments($request);
        return Response::json($service_rt);
    }
}

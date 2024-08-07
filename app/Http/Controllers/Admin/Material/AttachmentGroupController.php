<?php

namespace App\Http\Controllers\Admin\Material;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Services\SuperAttachmentGroupService;
use Illuminate\Support\Facades\Response;


class AttachmentGroupController extends Controller
{
    public function __construct(SuperAttachmentGroupService $superattachmentgroupservice){
        $this->service = $superattachmentgroupservice;
    }

    /**
    *@author renruiqi@qq.com
    *@param pid int 可选 父级分类id 默认0
    *@param type int 可选 类型   0普通1素材默认0
    */
    public function index(Request $request){
        $request['type'] =1;
        $service_rt = $this->service->getGroups($request->all());
        return Response::json($service_rt);
    }
}

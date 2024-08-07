<?php
/**
* 用于ui在后天添加素材
*@author renruiqi@dodoca.com
*/
namespace App\Http\Controllers\Admin\Access;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Models\Material;

class MaterialController extends Controller
{


    public function addBlade()
    {
        //查询素材分组
        $material_tag = config('material_tag');
        return view('admin.material.add',['material_tag'=>$material_tag]);
    }

    /**
    *添加素材
    */
    public function add(Request $request)
    {
//数据验证
        //ids
        $type = ',';
        if($request->type[0] ==0 ){
            //全选
            $material_tag = config('material_tag');
            array_shift($material_tag);
            foreach($material_tag as $k=>$v){
                $type .= $v['id'].',';
            }
        }else{
            foreach($request->type as $k=>$v){
                $type .= $v.',';
            }
        }
//添加
        $res = Material::insert(['type'=>$type]);
        dd($res);
    }



    //
    public function check_url()
    {
        $res = url();

    }
}

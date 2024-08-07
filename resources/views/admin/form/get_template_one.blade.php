<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>修改模板</title>
</head>
<style type="text/css">
    table{ border-collapse:collapse;}
    td,th,input{
        border : 1px solid red;
        font-size:23px;
    }
    p{
        font-size:13px;
    }
</style>
<body>
<h1>设置模板</h1>
    <table>
    <form action="{{url('admin/formtemplate/check_template')}}" method="get">
        <tr>
            <td width="100">表单id</td>
            <td width="650">{{$data['form_id']}}<input type="hidden" name="form_id" value="{{$data['form_id']}}"></td>
        </tr>
        <tr>
            <td width="100">模板名称</td>
            <td width="650"><input type="text" name="form_name" value="{{$data['name']}}"></td>
        </tr>
        <tr>
            <td>模板原图</td>
            <td><img src="{{$data['image_url']}}" alt="" width=100 height=100></td>
        </tr>
        <tr>
            <td width="100">模板图片 </td>
            <td width="650"><p >*参考格式:2017/12/18/Fv74U_cfjUVu0ZLipHVQ-BWdts3v.jpg</p><input type="text" name="form_img" value="{{$data['image']}}" style="width:520px">
            </td>
        </tr>
        <tr>
            <td width="100">使用人数</td>
            <td width="650"><input type="text" name="use_count" value="{{$data['use_count']}}"></td>
        </tr>
        <tr>
            <td width="100">模板分类</td>
            <td width="650">
                @foreach($template_cate as $k=>$v)
                    @if($k > 0) <!--去除选项:'全部'-->
                        @if(substr_count($data['type'],",{$k},")>0)
                            <label > {{$v}}<input type="checkbox" name='form_template[]' value="{{$k}}" id="checkbox{{$k}} " checked></label><br/>
                        @else
                            <label > {{$v}}<input type="checkbox" name='form_template[]' value="{{$k}}" id="checkbox{{$k}}" ></label><br/>
                        @endif
                    @endif
                @endforeach
            </td>
        </tr>
        <tr>
            <td width="100">是否删除</td>
            <td width="650">
                @if($data['is_delete'] == 1)
                    <input type="radio" name="is_delete" value="1" checked>保留模板
                    <input  type="radio" name="is_delete" value="-1">删除模板
                @else
                    <input type="radio" name="is_delete" value="1" >保留模板
                    <input  type="radio" name="is_delete" value="-1" checked>删除模板
                @endif
            </td>
        </tr>
        <tr>
            <td colspan=2 > <input type="submit"></td>
        </tr>
    </form>
    </table>
        
</body>
</html>
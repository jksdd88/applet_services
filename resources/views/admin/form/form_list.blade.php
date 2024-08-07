<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>添加模板</title>
</head>
<style type="text/css">

.main table{ border-collapse:collapse;}
/*.main table{ border:solid 1px #000;}*/
td,th{
    border : 1px solid red;
    font-size:23px;
}
/*table {margin: 0 auto;}*/
</style>
<body>
    <h1>模板添加页面</h1>

    <div class="main" >
        <table >
            <tr>
                <th width=100>表单id</th> 
                <th width=200>名称</th>
                <th width=300>创建时间</th>
                <th width=100>模板</th>
                <th width=150>操作</th>
            </tr>
            @foreach($data as $v)
            <tr>
                <td>{{$v['id']}}</td>
                <td>{{$v['name']}}</td>
                <td>{{$v['created_time']}}</td>
                    
                @if($v['is_template'] == 2)
                    <td>否</td>
                    <td><a href="{{url('admin/formtemplate/form_one')}}?form_id={{$v['id']}}">设为模板</a></td>
                @else
                    <td>是</td>
                    <td><a href="{{url('admin/formtemplate/template_one')}}?form_id={{$v['id']}}">--编辑该模板</a></td>
                @endif
            </tr>
            @endforeach
            <tr></tr>

        </table>
    </div>
</body>
</html>
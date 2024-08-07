<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>add</title>
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
    <!--引入js-->
    <script type="text/javascript"></script>
    <script src="{{ URL::asset('/jquery-2.1.4.min.js') }}"></script>


</head>
<body>
    <h1>添加素材</h1>
    <table>
        <form action="/admin/material/add" method='post' id='add_material'>
        <?php  csrf_token();?>
            <tr>
                <th>名称</th>
                <td><input type="text" name='name'></td>
            </tr>
            <tr>
                <th>图片地址</th>
                <td><input type="text" name='image'></td>
            </tr>
            <tr>
                <th>所属分类</th>
                <td>
                    @foreach($material_tag as $k=>$v)
                    <label><input type="checkbox" name='type[]' value='{{$v["id"]}}' id='check_{{$v["id"]}}' class='check_btn'>{{$v['name']}}</label></br>
                    
                    @endforeach
                </td>
            </tr>
            <tr>
                <td colspan='2'><input type="button" value='提交' id='submit_btn'></td>
            </tr>
        </form>
    </table>
    <script type="text/javascript">
    $('#check_0').on('click',function(){
        var status = this.checked;
        if(this.checked == true){
            //全不选
            $('.check_btn').prop("checked", true);
        }else{
            $('.check_btn').prop("checked", false);
        }
    });

    $('#submit_btn').on('click',function(){
        //验证名称
        //验证图片地址
        //验证分类
        if($(".check_btn:checked").size()==0){
            alert('请选择素材分类');
            return;
        }

        $('#add_material').submit();
    });
    </script>
</body>
</html>
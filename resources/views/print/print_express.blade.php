<!DOCTYPE html>
<html>
<head>
    <title>订单打印</title>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="">
    <meta name="author" content="WEIBA.INC">
    <script type="text/javascript" src="https://s.dodoca.com/comm/js/jquery-2.1.4.min.js"></script>
    <link rel="stylesheet" type="text/css" href="https://s.dodoca.com/applet_mch/css/print.css" media="print">
    <link rel="stylesheet" type="text/css" href="https://s.dodoca.com/applet_mch/css/print-preview.css" media="screen">
    <style type="text/css" media="screen">
        .express{ overflow:hidden; position:relative; width:{{ $waybill_tpl['imgWidth'] }}px; height:{{ $waybill_tpl['imgHeight'] }}px;}
        .express img{ overflow:hidden; position:relative; width:{{ $waybill_tpl['imgWidth'] }}px; height:{{ $waybill_tpl['imgHeight'] }}px;opacity:0.3;filter:alpha(opacity=20);}
        .express .field{position:absolute;word-wrap:break-word;font-size:{{ $waybill_tpl['size'] }}px;color:#00f;}
        {{ $waybill_tpl['screen'] }}
        .check-shipcode-list{}
        td,th{text-align: center;}
        tr:hover{background-color: #cacaca}
    </style>
    <style type="text/css" media="print">
        .pageNext{page-break-after: always;}
        .express{ overflow:hidden; position:relative; width:{{ $waybill_tpl['width'] }}mm; height:{{ $waybill_tpl['height'] }}mm;}
        .express img {display: none;width:{{ $waybill_tpl['height'] }}mm; height:{{ $waybill_tpl['height'] }}mm;}
        .express .field{position:absolute;word-wrap:break-word;font-size:{{ $waybill_tpl['size'] }}px;color:#0000ff;font-family:"黑体"}
        .check-shipcode-list{display: none}
        {{ $waybill_tpl['print'] }}
    </style>
</head>
<body>
<div id="main">
    <?php if(!$orders): ?>
        <h2>订单已发货或者不在代发货状态 </h2>
        <hr/>
        <button id="printBtn" style="display: none">确认打印</button>
        <button id="sendBtn" style="display: none">确认发货</button>
        <button id="closeWindow">关闭窗口</button>
    <?php else: ?>
        <?php foreach($orders as $order){ ?>
            <div class="express">
                <img src="https://msxcx.wrcdn.com/<?php echo $waybill_tpl['img'] ?>">
                <?php foreach($waybill_tpl['print_item_arr'] as $tpl){ ?>
                    <?php if($tpl['show'] == 1): ?>
                        <?php
                        if(is_array($order[$tpl['id']])){
                            $result = $dot = '';
                            foreach($order[$tpl['id']] as $_result){
                                $result .= $dot.$_result;
                                $dot = '<br/>';
                            }
                            ?>
                            <div class="field css-<?php echo $tpl['id']; ?>"><?php echo $result; ?></div>
                        <?php }else{?>
                            <div class="field css-<?php echo $tpl['id']; ?>"><?php echo $order[$tpl['id']]; ?></div>
                        <?php }?>
                    <?php endif; ?>
                <?php } ?>
            </div>
            <div class="pageNext"></div>
        <?php } ?>
        <div class="buttons">
            <button id="printBtn">确认打印</button>
            <button id="sendBtn">确认发货</button>
            <button id="closeWindow" style="display: none">关闭窗口</button>
            <p>真实打印不会显示快递单底色 </p>
        </div>
    <?php endif; ?>
</div>
<div class="check-shipcode-list" style="width: 500px;padding: 10px;border: 1px solid #cacaca;bottom: 80px;right: 30px;position: fixed;display: none;background-color: #fff;">
    <div class="">
        <h4>发货确认</h4>
    </div>
    <div>
        <table id="chang_form" style="border-collapse: collapse;border-spacing: 0;width:100%;border:none;margin-top: 15px;margin-bottom: 15px;">
            <thead>
            <tr><th style="width: 120px;">订单号</th><th style="width: 120px;">快递</th><th>快递单号</th></tr>
            </thead>
            <tbody>
            <?php foreach($orders as $order){ ?>
                <tr>
                    <td><?php echo $order['order_sn']?><input type="hidden" name="order_id" value="<?php echo $order['id']?>" /></td>
                    <td><?php echo $waybill_tpl['express_company']?></td>
                    <td><input type="text" name="logis_no" value="<?php echo $order['shipCode']?>"></td></tr>
            <?php } ?>

            </tbody>
        </table>
    </div>
    <div>
        <input type="hidden" id="waybill_tpl_id" name="waybill_tpl" value="<?php echo $waybill_tpl['id']?>">
        <input type="hidden" id="logis_name" name="logis_name" value="<?php echo $waybill_tpl['express_company']?>">
        <input type="hidden" id="logis_code" name="logis_code" value="<?php echo $waybill_tpl['logis_code']?>">
        <button id="save">保存</button><button id="closeSave">关闭</button>
    </div>
</div>
<script type="text/javascript">

    document.getElementById("printBtn").onclick = function(){
        window.print();
        document.printForm.submit();
    };
    /*$(document).ajaxSend(function(e, xhr, options){
        xhr.setRequestHeader('app-key', _global.app_key);
        xhr.setRequestHeader('access-token', _global.access_token);
//xhr.setRequestHeader('access-token', 'da3eb16070dc9262a2c0719fe0b680f1');
    });
    */
    $(function(){
        $("#sendBtn").click(function(){
            $(".check-shipcode-list").show();
        });

        $("#save").click(function(){
            if(confirm('确认发货？')) {
                var $tr = $("#chang_form tbody tr");
                var len = $tr.length;
                var arr = [];
                for (var i = 0; i < len; i++) {
                    text = 'order_id:' + $tr.eq(i).children('td').eq(0).find('input[name="order_id"]').val()
                        + ',logis_no:' + $tr.eq(i).find('input[name="logis_no"]').val()
                        + ',logis_name:' + $('#logis_name').val()
                        + ',logis_code:' + $('#logis_code').val()
                        + ',waybill_tpl_id:' + $('#waybill_tpl_id').val();
                    arr.push(text);
                }

                $.ajax({
                    url: '/admin/order/batchShipment.json',
                    data: {'data': arr},
                    type: 'POST',
                    dataType: 'json',
                    success: function (data) {
                        if (data.errcode == 0) {
                            alert(data.errmsg);
                            $(".check-shipcode-list").hide();
                            $("#sendBtn").hide();
                            $("#closeWindow").show();
                        } else {
                            alert(data.errmsg);
                        }
                    }
                });
            }
        });

        $("#closeSave").click(function(){
            $(".check-shipcode-list").hide();
        });

        $("#closeWindow").click(function(){
            window.close();
        });
    })
</script>

</body>
</html>
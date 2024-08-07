<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <title>发货单打印</title>
    <meta charset="utf-8">
    <script type="text/javascript">
        function btnPrintClick(){
            window.print();
        }
        function btnCloseClick(){
            window.close();
        }
    </script>
    <style type="text/css" media="screen">
        * {box-sizing: border-box;}
        ul {width: 100%;}
        ul,li{margin: 0;padding: 0;line-height: 1.4;}
        .col-0{width:10em; }
        .col-1{width:auto; }
        .col-2{width:12em; }
        .col-3{width:4em; }
        .col-4{width:5em; }
        .col-5{width:5em; }
        .clearfix:after {visibility: hidden;display: block;font-size: 0;content: " ";clear: both;height: 0;}
        .clearfix { display: inline-table; }
        * html .clearfix { height: 1%; }
        .clearfix { display: block; }
    </style>
    <style type="text/css" media="print">
        * {box-sizing: border-box;}
        ul {width: 100%;}
        ul,li{margin: 0;padding: 0;line-height: 1.4;}
        .col-0{width:10em; }
        .col-1{width:auto; }
        .col-2{width:12em; }
        .col-3{width:4em; }
        .col-4{width:5em; }
        .col-5{width:5em; }
        .pageNext{page-break-after: always;}
        #btnPrint,#btnClose{display: none;}
        .clearfix:after {visibility: hidden;display: block;font-size: 0;content: " ";clear: both;height: 0;}
        .clearfix { display: inline-table; }
        * html .clearfix { height: 1%; }
        .clearfix { display: block; }
    </style>

</head>
<body style="width: 70em;display: block;margin: 0 auto;">
<?php
foreach($orders as $order){

?>
<div class="clearfix"><div style="width: 100%;">
        <div>
            <div>
                <div style="MARGIN-RIGHT: 20px">
                    <p><span></span></p><h3 style="FONT-WEIGHT: normal;max-width:300px;min-width: 100px;float: left;margin: 0 "><?php echo $shop['name'];?></h3>
                    <p></p></div>

                <ul style="list-style: none;float: left;">
                    <li style="float: left;"><span>生成日期： </span><?php echo $order['created_at'];?></li>
                    <li style="float: left;margin-left:20px;"><span>订单号： </span><?php echo $order['order_sn'];?></li>
                    <li style="float: left;margin-left:20px;"><span>支付方式： </span><?php echo $order['payment_name'];?></li>
                </ul>

                <br></div><tr"></tr"></div><tr"></tr"><tr"></tr"><tr"></tr"><table style="margin:20px 0;text-align: left;width:100%;border: 1px solid #000;border-collapse: collapse;border-spacing: 0">
            <colgroup>
                <col class="col-0">
                <col class="col-1">
                <col class="col-2">
                <col class="col-3">
                <col class="col-4">
                <col class="col-5">
            </colgroup>
            <thead>
            <tr><th style="border-bottom: 1px solid #858585;color: #000;padding: 0.4em 0.4em 0.4em 1.1em;text-align: left;">货号</th><th style="border-bottom: 1px solid #858585;color: #000;padding: 0.4em 0.4em 0.4em 1.1em;text-align: left;">商品名称</th><th style="border-bottom: 1px solid #858585;color: #000;padding: 0.4em 0.4em 0.4em 1.1em;text-align: left;">规格</th><th style="border-bottom: 1px solid #858585;color: #000;padding: 0.4em 0.4em 0.4em 1.1em;text-align: left;">数量</th><th style="border-bottom: 1px solid #858585;color: #000;padding: 0.4em 0.4em 0.4em 1.1em;text-align: left;">单价</th><th style="border-bottom: 1px solid #858585;color: #000;padding: 0.4em 0.4em 0.4em 1.1em;text-align: left;">总价</th><th style="border-bottom: 1px solid #858585;color: #000;padding: 0.4em 0.4em 0.4em 1.1em;text-align: left;">要求留言</th><th style="border-bottom: 1px solid #858585;color: #000;padding: 0.4em 0.4em 0.4em 1.1em;text-align: left;">维权信息</th>
            </tr>
            </thead>
            <tbody>

            <?php
            foreach($order['goods'] as $goods){
            ?>
            <tr>
                <td style="border-bottom: 1px solid #858585;padding: 0.5em 0.8em;word-break: break-all;"><?php echo $goods['goods_sn'] ? $goods['goods_sn'] : '';?></td>
                <td style="border-bottom: 1px solid #858585;padding: 0.5em 0.8em;word-break: break-all;"><?php echo $goods['goods_name'];?></td>
                <td style="border-bottom: 1px solid #858585;padding: 0.5em 0.8em;word-break: break-all;"><?php echo $goods['props'];?> </td><td style="border-bottom: 1px solid #858585;padding: 0.5em 0.8em;word-break: break-all;"><?php echo $goods['quantity'];?></td>
                <td style="border-bottom: 1px solid #858585;padding: 0.5em 0.8em;word-break: break-all;"><?php echo $goods['price'];?></td>
                <td style="border-bottom: 1px solid #858585;padding: 0.5em 0.8em;word-break: break-all;"><?php echo $goods['pay_price'];?></td>
                <?php
                    if(false === empty($goods['collect_fields'])){
                ?>
                <td style="border-bottom: 1px solid #858585;padding: 0.5em 0.8em;word-break: break-all;"><?php if(is_array($goods['collect_fields'])){foreach($goods['collect_fields'] as $gv){ echo isset($gv['name']) ? $gv['name'].':&nbsp;'.$gv['value'] : '' .'<br />'; }} ?></td>
                <?php
                    }else{
                    echo '<td style="border-bottom: 1px solid #858585;padding: 0.5em 0.8em;word-break: break-all;"></td>';
                }
                ?>
                 <td style="border-bottom: 1px solid #858585;padding: 0.5em 0.8em;word-break: break-all;">
                 <?php
                 if (isset($goods['refund_info'])) {
                    $refundInfo = $goods['refund_info'];
                 switch ($refundInfo['type'])
                {
                 case 0:
                  $type ='退款不退货';
                  break;
                 case 1:
                  $type = "退款退货";
                  break;
                 }
                switch ($refundInfo['status'])
                {
                 case 10:
                  $status ='申请退款中';
                  break;
                 case 11:
                  $status = "再次申请退款中";
                  break;
                 case 12:
                  $status = "系统申请退款中";
                  break;
                  case 20:
                  $status = "商家同意退款，等待买家处理";
                  break;
                  case 21:
                  $status = "拒绝退款";
                  break;
                  case 22:
                  $status = "买家已发货，等待卖家收货";
                  break;
                  case 30:
                  $status = "第三方退款中";
                  break;
                  case 31:
                  $status = "已经退款，退款完成";
                  break;
                  case 40:
                  $status = "已经关闭";
                  break;
                  case 41:
                  $status = "用户取消退款";
                  break;
                }
                  echo "[{$type} {$status} 数量{$refundInfo['refund_quantity']}件]";
              }else{echo "";}
                 ?></td>
            </tr>
            <?php
            }
            ?>

            </tbody>
        </table>
    </div>
    <div style="width:100%;font-size:12px;">
        <ul class="clearfix" style="list-style: none;width:63em;margin-left:0; font-size:1.5em">
            <li style="float: left;"><span>商品总价：</span><?php echo $order['goods_amount'];?></li>
            <li style="float: left;margin-left:10px;"><span>优惠金额：</span><?php //echo $order['ump_amount'];?></li>
            <li style="float: left;margin-left:10px;"><span>运费： </span><?php echo $order['shipment_fee'];?> </li>
            <li style="float: left;margin-left:10px;"><span>实付金额：</span><?php echo $order['amount'];?> </li>
        </ul>

        <?php if($order['is_selffetch'] == 1){?>
        <ul style="list-style: none;float:left;margin-left:0; font-size:1.5em">
            <li style="float: left;width: 100%;"><span>提货地址：</span><?php echo $order['selffetch_store'];?></li>
            <li style="float: left;"><span>提货人：</span><?php echo $order['selffetch_addressee_name'];?></li>
            <li style="float: left;margin-left: 10px;"><span>联系电话： </span><?php echo $order['selffetch_addressee_phone'];?> </li>
        </ul>
        <?php
        }
        else
        {
        ?>
        <ul class="clearfix" style="list-style: none;margin-left:0; font-size:1.5em">
            <?php if($order['ump_detail']){ ?><li style="float: left;"><span>折扣方式： </span><?php echo $order['ump_detail'];?> </li><br /><?php } ?>
            <li style="float: left;"><span>收货人：</span><?php echo $order['consignee'];?></li>
            <li style="float: left;margin-left: 10px;"><span>联系电话： </span><?php echo $order['mobile'];?> </li>
            <li style="float: left;width: 100%;"><span>收货地址：</span><?php echo $order['country_name'].$order['province_name'].$order['city_name'].$order['district_name'].$order['address'];?> </li>
        </ul>
        <?php
        }
        ?>
        <?php
        if(false === empty($order['memo']) || false === empty($order['order_fields'])){
        ?>
        <ul class="clearfix" style="list-style: none;margin-left:0; font-size:1.5em">
            <?php
            if(false === empty($order['memo'])){
            ?>
            <li style="float: left;"><span>买家留言：</span><?php echo $order['memo'];?></li><br />
            <?php
            }
            ?>
            <?php
            if(false === empty($order['order_fields'])){
            ?>
            <li style="float: left;">
                <span style="float: left">订单留言：</span>
                <ul class="clearfix" style="float:left;width: 500px">
                    <?php
                        foreach($order['order_fields'] as $v){
                    ?>
                    <li style="float: left;width: 100%;list-style-type:none"><span><?php echo $v['name']; ?>:&nbsp;</span><?php echo $v['value']; ?></li><br />
                    <?php
                        }
                    ?>
                </ul>
            </li>
            <?php
            }
            ?>
        </ul>
        <?php
        }
        ?>

    </div><div style="width:1024px;float:left;height:40px;"></div></div><div style="width: 100%;float:left;text-align: center;margin-bottom: 50px;">
    <input id="btnPrint" style="width: 80px;" value="打印" type="button" onclick="btnPrintClick()">
    <input id="btnClose" style="width: 80px;" value="关闭窗口" type="button" onclick="btnCloseClick()">
</div>
<?php
}
?>


</body>

</html>

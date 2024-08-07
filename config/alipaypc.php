<?php
return [
    //应用ID,您的APPID。
    'app_id' => "2017101309287983",
    
    //商户私钥
    'merchant_private_key' => "MIIEpAIBAAKCAQEAsD48IW5QcuSF1dxjWU/KJKB5pPNqOM8eXMq1R0rvEgOfsRgXSFA2DAR7c9vlGMljPhBlPZrl1LpsNqqqJXhMA0H6Iby4DOx8KnpgIuo8fvc4Lv4JwiyUjKSJBJRycEDT4j7Bq9m8ec2yH6C8NpfSrrfiCcBFj2JC+YvUXx9CnTSYSBJvLw3++kai7gdCJ3kPMZV9lHHSe9WN/DIzpVmYnH5CZoN1INtpy1ysXukf2b5hveQ3Xh8Hg3Ch4V5fztijRjeeFczndQ9PWuLFlLGisQ78oI0fmjr0xBYOXjJcVY+E0ljHMasGfQoZbgUTbmulGj+LoN5UR2jgdZK5F+w3OwIDAQABAoIBAF5CetB6eoZoWHgf7fa3aOqb9VNWaIpHo/qG49tkZWaiD4ec1d70H2PgBdLaWbYfB3gLNspzDNbwea3nKybtJuJbKBdhIqKu3F2vo7koxAA34pGnhrqWM0DhQvLzHh2RXoFThSuPQXF3pPurcN5V5vVRHZCPh+R+7kkfEw9o3azLrzGkuwBRexCsgxVZWrUxmytOW5u6E5RqqLvqq3+TZgqE72aXleJORJibD976yNKF8nJy4qWaqGDAsWg8jdL+RqcygTStqIOgT6YXciEIIIPhyzidcHQx4iaYJ6DbE0PcJenGX7XDhMuHyuFagfoct22La5iOtQaUdQ1FebgQHckCgYEA6RYZ5c0T+qcccyY5uIVaGr6j5NNIgmMoUj5oDlm5uNZulIAvaH/X1n/2E0DYiYQmMLCFzn81khTsTymwkImFNy2wJmlZGqpyuSA7ePLFDd3h2luJLAnbhEZxxmAr9jNT7WRqIsPR7GkqgRqeJ6QUAOIOx2if6mVeUKVNeKKYjDUCgYEAwZGa9SmH0lZs4MNsv9Rh3kLcKT/LU82UMgQZTo1I9qvTpNi+X06XW6Yky83uhkV6H2sYEUvm6O2ztx0n8rJgNqiuFhEAqfcxwwE7SL5VXNtl4AelOQCa/aUSZYkS7H/TcXc0qoTyo3zumdvF2BaDqBapG4rPLMOU36E3cq54w68CgYEAiWEYZISKCQsjzo6yKJqYb/j7GmyZaRhOdKMJq6OgvlvMk0Q2LQ5kxa1n+RMYTX0REVOJmhsKFQ619TaqNZaIaOxJzWn8NaZOteRUiUX1dOXZL10SLV2l/4GRn42he5vnFJ1BnTnzabbWcX+hxdWEYLzcXxXAY2PZwgib78VNyh0CgYA0/v909ezoyp6+nuKsVqKA7r9GT+AETrmvQ+4F0qrSVlL4xBrDD0pjXkaewf/3JRh9d528RpKu3T2h+cqRKQMsk9wt0HPlFPe70x9/GBVY+fyXbKBwzdihb0ttHy1eMNUcMK3rrcCcwDy3RTOSqP9cyu97yfJU6CtfWs1KibgoowKBgQChewiviOdmsBbQg/t+0mZhMhpkYLGxDvcmvuUNxpRx1Bor9IMv7Dxf5MWuOHYt174ZjUYu4AnyX4RGcBklsnGxqeF0N19+QxC72vg6KEG+Yawsjq8kQq02C5PRtRgAiLhPYGP2U+k2t8donOLTIymfa4rI6Vgq+gRtzikSE+kbSg==",
    
    //异步通知地址
    'notify_url' => env('PAY_URL').'/com/cashier/alipcnotify',
    
    //同步跳转
    'return_url' => env('PAY_URL').'/com/cashier/alipcreturn',
    
    //编码格式
    'charset' => "UTF-8",
    
    //签名方式
    'sign_type' => "RSA2",
    
    //支付宝网关
    'gatewayUrl' => "https://openapi.alipay.com/gateway.do",
    
    //支付宝公钥,查看地址：https://openhome.alipay.com/platform/keyManage.htm 对应APPID下的支付宝公钥。
    'alipay_public_key' => "MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEArNYpbyywRQ2hIVeN9Dy520Q+FvxlWzU0Xndc8nnkVZRlvqDkbBEwps5bUshvAc4/Y2XjVxyEmmOtbZZdxOM8jJQaql5/N1KgJskOTB90WGXRcotvyVUjelT/S6EBg3ICLqq2OqqGevW6LdvvtX7i44Q4M8X+ouQKf4/oA+xNvFaa7gLfKmWNbWCC54i4XfPXG/GpedJzCZabGdENW5sGf9Hf4nD151PzDoaYos2J5vaDkr7BDBaRuQWu1KNc2vkmcrFd8PKgQFIcXk+z8y2mWUJJpOV0pxyDqKLOH1vlBfNtheUSMAjqekZWVMGot7KPTZT6804Eg0edEOAYuUlEJwIDAQAB",
];
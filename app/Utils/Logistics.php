<?php
/**
 * @ 物流信息
 * @author wangyu
 * @time 2017-09-8
 */
namespace App\Utils;
use GuzzleHttp\Client;
use App\Utils\CacheKey;
use Illuminate\Support\Facades\Cache;

class Logistics
{

    /**
     * @param array $params
     * $params['logis_code'] = '物流公司代码';
     * $params['logis_no'] = '快递单号';
     *
     * @return array|mixed 失败返回 {"result":false,"returnCode":"501","message":"POLL:重复订阅"}
     * 失败返回{"result":false,"returnCode":"700","message":"POLL:不支持的快递公司：shunfen"}
     * 成功返回{"result":true,"returnCode":"200","message":"提交成功"}
     */
    static function search_logistic($params = array())
    {

        //物流公司代码
        $logis_code = isset($params['logis_code']) ? $params['logis_code'] : '';
        //快递单号
        $logis_no = isset($params['logis_no']) ? $params['logis_no'] : '';


        $data = array(
            "logis_code" => $logis_code,
            "logis_no" => $logis_no
        );

        try {

            if (!$logis_code || !$logis_no)
            {
                return array('result' => false, 'message' => '数据异常');
            }

            $search_logistic_key = CacheKey::get_logistic_ket($logis_code,$logis_no);
            $client_result = Cache::get($search_logistic_key);

            if(empty($client_result))
            {
                $data['source_ip'] = get_client_ip();
                $data['source_app'] = 3;//表示来源商城
                $logisticsClient = new Client(['base_uri' => env('LOGISTICS_SERVER'), 'timeout' => 2, 'verify' => false]);

                $api_url = '/api/kuaidaoyun/search' . '?' . http_build_query($data);

                $client_result = json_decode($logisticsClient->get($api_url)->getBody(), true);

                if($client_result['status'] == false && !empty($client_result['code']) && $client_result['code'] < 0){
                    Cache::put($search_logistic_key,$client_result,15);
                }
            }

            return $client_result;

        } catch (\Exception $e)
        {
            
        }
    }

}
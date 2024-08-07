<?php
/**
 * Created by PhpStorm.
 * User: wangshiliang@dodoca.com
 * Date: 2017/8/30
 * Time: 14:50
 */
namespace App\Services;

use App\Models\ExpressMerchant;
use App\Utils\Dada\DadaMerchant;
use App\Utils\Dada\DadaShop;
use App\Utils\Dada\DadaBasis;
use App\Utils\CacheKey;
use Cache;
use Config;

class ExpressService
{
    public $app_key;
    public $app_secret;

    public function __construct()
    {
        $this->app_key  = Config::get('express.app_key');
        $this->app_secret = Config::get('express.app_secret');
    }

    public function getCallbackUrl(){
        return Config::get('weixin.base_host').'/dada/order/callback';
    }
    public function priceCallbackUrl(){
        return Config::get('weixin.base_host').'/admin/express/order/cache.json';
    }

    public function checkAuth(){
        return ['errcode'=>0,'errmsg'=>''];
    }

    public function getDadaMerchant($merchant_id){
        $cachekey = CacheKey::get_dada_express($merchant_id,'dada_merchant_id');
        $dada_merchant_id = Cache::get($cachekey);
        if($dada_merchant_id)
        {
            return $dada_merchant_id;
        }
        $dada_id = ExpressMerchant::query()->where([ 'merchant_id' => $merchant_id , 'is_delete' => 1 , 'status' => 1 ])->value('dada_id');
        if($dada_id){
            Cache::put($cachekey,$dada_id,30);
        }else{
            $dada_id = '';
        }
        return $dada_id;
    }

    public function getEnv($merchant_id = 0){
        $env = Config::get('express.env');
        $test = Config::get('express.test_merchant_id');
        return ( $env == 'production' && !in_array($merchant_id,$test) ) || ($env == 'release' && $merchant_id == 6) || ($env == 'develop-applet' && $merchant_id == 1) ? false : true;
    }

    public function getCity($merchant_id){
        $cachekey = CacheKey::get_dada_express('city','imdada');
        $result = Cache::get($cachekey);
        if($result)
        {
            return $result;
        }
        $dada = (new DadaBasis()) ->setConfig($this->app_key,$this->app_secret, $this->getDadaMerchant($merchant_id),$this->getEnv($merchant_id));
        $response = $dada->cityList();
        if(isset($response['code']) && $response['code'] == 0 && isset($response['result'])){
            Cache::put($cachekey,$response['result'],30);
        }else{
            $response['result'] = [];
        }
        return  $response['result'];
    }

    public function getCancel($merchant_id){
        $cachekey = CacheKey::get_dada_express('cancel','imdada');
        $result = Cache::get($cachekey);
        if($result)
        {
            return $result;
        }
        $dada = (new DadaBasis()) ->setConfig($this->app_key,$this->app_secret, $this->getDadaMerchant($merchant_id),$this->getEnv($merchant_id));
        $response = $dada->reasonsList();
        if(isset($response['code']) && $response['code'] == 0 && isset($response['result'])){
            Cache::put($cachekey,$response['result'],30);
        }else{
            $response['result'] = [];
        }
        return  $response['result'];
    }

    public function getComplaint($merchant_id){
        $cachekey = CacheKey::get_dada_express('complaint','imdada');
        $result = Cache::get($cachekey);
        if($result)
        {
            return $result;
        }
        $dada = (new DadaBasis()) ->setConfig($this->app_key,$this->app_secret, $this->getDadaMerchant($merchant_id),$this->getEnv($merchant_id));
        $response = $dada->complaintList();
        if(isset($response['code']) && $response['code'] == 0 && isset($response['result'])){
            Cache::put($cachekey,$response['result'],30);
        }else{
            $response['result'] = [];
        }
        return  $response['result'];

    }

    public function updateLocation(){

    }

}
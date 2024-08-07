<?php

namespace App\Http\Controllers\Admin\Region;

use App\Http\Controllers\Controller;
use App\Models\Region;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Cache;
use App\Utils\CacheKey;

class RegionController extends Controller
{

    public function __construct(Region $region)
    {
        $this->model            = $region;
    }

    /**
     * 获取地区二维数据
     *
     * @return Response
     */
    public function getDtree()
    {
        return Response::json($this->getRegionDtree());
    }

    public function getRegion()
    {
        return Response::json($this->getRegionAll());
    }

    public function getSubRegion()
    {
        return Response::json($this->getSubRegionTree(Request::all()));
    }
    
    
    
    public function getRegionDtree()
    {
        $search_shop_regions_key = CacheKey::get_region_key('shop_regions');
        $region = Cache::get($search_shop_regions_key);
        //Cache::forget('shop_regions');
        //dd($region);
        if(!$region)
        {
            $regions = $this->model->select('id','title','parent_id','code','listorder')->orderBy('listorder')->get();
            foreach ($regions as $_region) {
                $return[$_region->parent_id][] = $_region;
            }
            $region =  $return;
    
            $merchant_id = 377;//isset(Auth::user()->merchant_id) ?  Auth::user()->merchant_id :'';
            $merchant_region = '{"default_region":{"id":1,"title":"\u4e2d\u56fd"},"region":[{"id":1,"title":"\u4e2d\u56fd"}]}';//$this->merchantSetting->where('merchant_id','=',$merchant_id)->pluck('region');
            //dd($merchant_id);
            $region_json = json_decode($merchant_region,true);
        
            $is_china = false;
            $region_in   = array();
        
            $region_json['region'] = isset($region_json['region']) ? $region_json['region'] : json_decode('{"region":{"id":1,"title":"\u4e2d\u56fd"}}',true);
        
            if(isset($region_json['region']))
            {
                foreach($region_json['region'] as $val )
                {
                    if($val['id']==1)
                    {
                        $is_china = true;
                    }
                    else
                    {
                        $region_in[] = $val['id'];
                    }
        
                }
        
                if(!$is_china)
                {
                    for($i=0;$i<=33;$i++)
                    {
                        unset($region[1][$i]);
                    }
                    $region[1] = array_values($region[1]);
                }
        
                $region_out = array();
                if(count($region_in) && isset($region[900000]))
                {
                    foreach($region[900000] as $k=>$v)
                    {
                        if(!in_array($v['id'],$region_in))
                        {
                            unset($region[900000][$k]);
                        }
                        else
                        {
                            $region_out[]= $v;
                        }
                    }
                    $region[900000]  = $region_out;
                }
                else
                {
                    if(isset($region[1][34]))
                    {
                        unset($region[1][34]);
                    }
                }
            }
            Cache::forever($search_shop_regions_key,$region);
        }
        return $region;
    }
    
    public function getSubRegionTree($request)
    {
        if(isset($request['clearAll']) && $request['clearAll'])
        {
            $search_shop_regionAll_key = CacheKey::get_region_key('shop_regionAll');
            $search_shop_regions_key = CacheKey::get_region_key('shop_regions');
            Cache::forget($search_shop_regionAll_key);
            Cache::forget($search_shop_regions_key);
        }
    
        $merchant_id = 377;//isset(Auth::user()->merchant_id) ?  Auth::user()->merchant_id :'';
        $parent_id = isset($request['parent_id']) ? $request['parent_id'] :0;
        $data['data']   =  $this->model->where('id','=',1)->orWhere('parent_id','=',900000)->orderBy('listorder')->get()->toArray();
        $data['status'] = true;
    
        $region = '{"default_region":{"id":1,"title":"\u4e2d\u56fd"},"region":[{"id":1,"title":"\u4e2d\u56fd"}]}';//$this->merchantSetting->where('merchant_id','=',$merchant_id)->pluck('region');
        if($region)
        {
            $data['region'] = json_decode($region,true);
        }
        else
        {
            $data['region'] = json_decode('{"default_region":{"id":1,"title":"\u4e2d\u56fd"},"region":[{"id":1,"title":"\u4e2d\u56fd"}]}',true);
        }
    
        return $data;
    }
    
    public function getRegionAll()
    {
        $search_shop_regions_key = CacheKey::get_region_key('shop_regions');
        
        if(Cache::get($search_shop_regions_key))
        {
            return Cache::get($search_shop_regions_key);
        }else{
            $regions = $this->model->orderBy('listorder')->get();
            foreach ($regions as $key=>$_region) {
                $return[$_region['id']] = $_region;
            }
            Cache::forever($search_shop_regions_key,$return);
            return $return;
        }
    }
}

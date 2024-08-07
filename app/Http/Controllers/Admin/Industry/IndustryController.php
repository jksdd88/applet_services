<?php

namespace App\Http\Controllers\Admin\Industry;

use App\Http\Controllers\Controller;
use App\Models\Industry;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Cache;
use App\Utils\CacheKey;

class IndustryController extends Controller
{

    public function __construct(Industry $industry)
    {
        $this->model            = $industry;
    }

    /**
     * 获取行业分类二维数据
     *
     * @return Response
     */
    public function getDtree()
    {
        return Response::json($this->getIndustryDtree());
    }

    public function getIndustry()
    {
        return Response::json($this->getIndustryAll());
    }

    public function getSubIndustry()
    {
        return Response::json($this->getSubIndustryTree(Request::all()));
    }
    
    
    
    public function getIndustryDtree()
    {
        //dd('a');
        $search_data_industry_key = CacheKey::get_industry_key('data_industry');
        //Cache::forget($search_data_industry_key);
        if(Cache::get($search_data_industry_key))
        {
            $industry =  Cache::get($search_data_industry_key);
        }else{
            //dd('b');
            $industry = $this->model->select('id','title','parent_id','listorder')->orderBy('listorder')->get();
            foreach ($industry as $_industry) {
                $return[$_industry->parent_id][] = $_industry;
            }
            Cache::forever($search_data_industry_key,$return);
            $industry =  $return;
        }
    
        return $industry;
    }
    
    public function getSubIndustryTree($request)
    {
        if(isset($request['clearAll']) && $request['clearAll'])
        {
            $search_data_industryAll_key = CacheKey::get_industry_key('data_industryAll');
            $search_data_industry_key = CacheKey::get_industry_key('data_industry');
            Cache::forget($search_data_industryAll_key);
            Cache::forget($search_data_industry_key);
        }
    
        return $data;
    }
    
    public function getIndustryAll()
    {
        $search_data_industry_key = CacheKey::get_industry_key('data_industry');
        
        if(Cache::get($search_data_industry_key))
        {
            return Cache::get($search_data_industry_key);
        }else{
            $industry = $this->model->orderBy('listorder')->get();
            foreach ($industry as $key=>$_industry) {
                $return[$_industry['id']] = $_industry;
            }
            Cache::forever($search_data_industry_key,$return);
            return $return;
        }
    }
}

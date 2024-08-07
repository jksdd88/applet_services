<?php

namespace App\Http\Controllers\Admin\User;

use App\Services\PrivService;
use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;

class PrivController extends Controller
{
    public function getPrivs(PrivService $privService)
    {
        return $privService->getPrivs();
    }

    public function getDogPrivs(PrivService $privService){
        return $privService->getDogPrivs();
    }
}

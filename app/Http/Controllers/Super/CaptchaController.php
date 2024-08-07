<?php namespace App\Http\Controllers\Super;

use App\Http\Controllers\Controller;

class CaptchaController extends Controller {

    public function index(){
        echo captcha_img();
    }
}

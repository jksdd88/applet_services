<?php namespace App\Http\Controllers;


class CaptchaController extends Controller {

    public function index(){
        //echo '<img src="'.Captcha::create('default').'">';
        s(session('captcha'));
        exit;

        echo captcha_img();
    }
}

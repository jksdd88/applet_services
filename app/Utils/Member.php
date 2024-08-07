<?php
namespace App\Utils;

use Illuminate\Support\Facades\Session;

class Member {

    public function get()
    {
        if(Session::get('member')) {
            return Session::get('member');
        }
        return false;
    }

    public function id()
    {
        if(Session::get('member.id')) {
            return Session::get('member.id');
        }
        return false;
    }

    public function merchant_id()
    {
        if(Session::get('member.merchant_id')) {
            return Session::get('member.merchant_id');
        }
        return false;
    }

    public function weapp_id()
    {
        if(Session::get('member.weapp_id')) {
            return Session::get('member.weapp_id');
        }
        return false;
    }

    public function appid()
    {
        if(Session::get('member.appid')) {
            return Session::get('member.appid');
        }
        return false;
    }

    public function set($value)
    {
        Session::put('member', $value);
    }

    public function setField($field, $value)
    {
        Session::put('member.'.$field, $value);
    }
}
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;

abstract class Request extends FormRequest
{
    /**
     * 重写返回状态码-200，重写返回错误码、错误信息
     * Get the proper failed validation response for the request.
     *
     * @param  array  $errors
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function response(array $errors)
    {
        $resp=['errcode'=>1,'errmsg'=>current(current($errors))];
        return new JsonResponse($resp, 200);
    }
}

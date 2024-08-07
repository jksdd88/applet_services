<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that should not be reported.
     *
     * @var array
     */
    protected $dontReport = [
        HttpException::class,
        ModelNotFoundException::class,
    ];

    /**
     * Report or log an exception.
     *
     * This is a great spot to send exceptions to Sentry, Bugsnag, etc.
     *
     * @param  \Exception  $e
     * @return void
     */
    public function report(Exception $e)
    {
        return parent::report($e);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Exception  $e
     * @return \Illuminate\Http\Response
     */
    public function render($request, Exception $e)
    {
		if(Cookie::get('xcxdebug')) {
            $debug = Cookie::get('xcxdebug');
        } else {
            $debug = Request::input('xcxdebug');
        }
		
        if(!$debug || $debug != 1) {
			if ($e instanceof ModelNotFoundException) {
				$e = new NotFoundHttpException($e->getMessage(), $e);
			}
			return parent::render($request, $e);
        }
		
        if($debug == 1) {
            Config::set('app.debug', true);
            return parent::render($request, $e)->withCookie('xcxdebug', 1, 5);
        }
        return parent::render($request, $e);
    }
}

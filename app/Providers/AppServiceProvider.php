<?php

namespace App\Providers;

use DB;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        DB::listen(function($sql, $bindings, $time) {
            if (env('APP_ENV', 'production') == 'local') {
                foreach ($bindings as $index => $param) {
                    if ($param instanceof \DateTime) {
                        $bindings[$index] = $param->format('Y-m-d H:i:s');
                    }
                }
                $sql = str_replace("?", "'%s'", $sql);
                array_unshift($bindings, $sql);
                // dd($params);
                \Log::info('SQL语句输出----->'.call_user_func_array('sprintf', $bindings));
            }
        });
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }
}

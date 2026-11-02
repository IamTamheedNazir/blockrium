<?php

namespace App\Http\Middleware;

use Closure;
use Session;
class checklogin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if(empty(Session::get('ad_id'))){
            return redirect('/ad_maticlegend');
        }
		
        return $next($request);
    }
}



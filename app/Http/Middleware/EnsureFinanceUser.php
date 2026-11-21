<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFinanceUser
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && strtolower($user->role) === 'pptk') {
            if ($request->expectsJson()) {
                abort(403, 'Akses dibatasi. Modul BKU & Pembukuan khusus untuk Pengelola Keuangan (Admin/Bendahara).');
            }

            return redirect('/pagu')->with('warning', 'Modul BKU & Pembukuan khusus untuk Pengelola Keuangan (Admin/Bendahara). Anda dialihkan ke Portal Pagu PPTK.');
        }

        return $next($request);
    }
}

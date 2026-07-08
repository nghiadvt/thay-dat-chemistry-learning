<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

class StudentJoinController extends Controller
{
    public function show(?string $pin = null): Response
    {
        if ($pin !== null) {
            $pin = preg_replace('/\D/', '', $pin);
            if (strlen($pin) !== 6) {
                abort(404);
            }
        }

        $path = public_path('app/index.html');
        if (! is_readable($path)) {
            abort(503, 'Student app chưa được mount tại public/app/.');
        }

        $html = file_get_contents($path);
        $baseHref = rtrim(url('/app'), '/').'/';
        $baseTag = '<base href="'.e($baseHref).'">';

        if (stripos($html, '<base ') === false) {
            $html = preg_replace('/<head>/i', '<head>'.$baseTag, $html, 1);
        }

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
        ]);
    }

    public function legacyIndex(): RedirectResponse
    {
        $pin = preg_replace('/\D/', '', (string) request('pin', ''));
        if (strlen($pin) === 6) {
            return redirect('/join/'.$pin);
        }

        return redirect('/join');
    }
}

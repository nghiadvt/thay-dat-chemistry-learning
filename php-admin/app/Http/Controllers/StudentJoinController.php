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

        // QR deep-link: inject PIN so student.js skips Join PIN even if pathname is rewritten by <base>.
        if ($pin !== null && strlen($pin) === 6) {
            $boot = '<script>window.HTD_JOIN_PIN='.json_encode($pin).';</script>';
            $html = preg_replace('/<\/head>/i', $boot.'</head>', $html, 1);
        }

        // Cache-bust student assets so phone always gets latest join/avatar fixes.
        $assetMap = [
            'js/config.js' => public_path('app/js/config.js'),
            'js/api.js' => public_path('app/js/api.js'),
            'js/socket.js' => public_path('app/js/socket.js'),
            'js/game-adapter.js' => public_path('app/js/game-adapter.js'),
            'js/backend-bridge.js' => public_path('app/js/backend-bridge.js'),
            'js/shared.js' => public_path('app/js/shared.js'),
            'js/equation-ui.js' => public_path('app/js/equation-ui.js'),
            'js/keyboard-runtime.js' => public_path('app/js/keyboard-runtime.js'),
            'js/student.js' => public_path('app/js/student.js'),
            'css/shared.css' => public_path('app/css/shared.css'),
            'css/student.css' => public_path('app/css/student.css'),
            'css/keyboard-runtime.css' => public_path('app/css/keyboard-runtime.css'),
        ];
        foreach ($assetMap as $rel => $abs) {
            $v = is_readable($abs) ? (string) filemtime($abs) : (string) time();
            $html = str_replace(
                'href="'.$rel.'"',
                'href="'.$rel.'?v='.$v.'"',
                $html
            );
            $html = str_replace(
                'src="'.$rel.'"',
                'src="'.$rel.'?v='.$v.'"',
                $html
            );
        }

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
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

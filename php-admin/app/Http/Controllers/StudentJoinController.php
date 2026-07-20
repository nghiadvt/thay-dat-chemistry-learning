<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

class StudentJoinController extends Controller
{
    public function home(): Response
    {
        return $this->serveStudentApp(['entry' => 'home']);
    }

    public function show(?string $pin = null): Response
    {
        $normalizedPin = null;
        if ($pin !== null) {
            $normalizedPin = preg_replace('/\D/', '', $pin);
            if (strlen($normalizedPin) !== 6) {
                abort(404);
            }
        }

        return $this->serveStudentApp([
            'entry' => 'join',
            'pin' => $normalizedPin,
        ]);
    }

    public function legacyIndex(): RedirectResponse
    {
        $pin = preg_replace('/\D/', '', (string) request('pin', ''));
        if (strlen($pin) === 6) {
            return redirect('/join/'.$pin);
        }

        return redirect('/home');
    }

    /**
     * @param  array{entry?: string, pin?: string|null}  $options
     */
    private function serveStudentApp(array $options = []): Response
    {
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

        $bootScripts = '';
        $entry = $options['entry'] ?? 'home';
        if (in_array($entry, ['home', 'join'], true)) {
            $bootScripts .= '<script>window.HTD_ENTRY_SCREEN='.json_encode($entry).';</script>';
        }

        $pin = $options['pin'] ?? null;
        if ($pin !== null && strlen($pin) === 6) {
            $bootScripts .= '<script>window.HTD_JOIN_PIN='.json_encode($pin).';</script>';
        }

        if ($bootScripts !== '') {
            $html = preg_replace('/<\/head>/i', $bootScripts.'</head>', $html, 1);
        }

        $assetMap = [
            'js/config.js' => public_path('app/js/config.js'),
            'js/api.js' => public_path('app/js/api.js'),
            'js/socket.js' => public_path('app/js/socket.js'),
            'js/game-adapter.js' => public_path('app/js/game-adapter.js'),
            'js/backend-bridge.js' => public_path('app/js/backend-bridge.js'),
            'js/shared.js' => public_path('app/js/shared.js'),
            'js/equation-ui.js' => public_path('app/js/equation-ui.js'),
            'js/keyboard-runtime.js' => public_path('app/js/keyboard-runtime.js'),
            'js/sound.js' => public_path('app/js/sound.js'),
            'js/fx.js' => public_path('app/js/fx.js'),
            'js/elements-data.js' => public_path('app/js/elements-data.js'),
            'js/elements-module.js' => public_path('app/js/elements-module.js'),
            'js/quiz-module.js' => public_path('app/js/quiz-module.js'),
            'js/balance-data.js' => public_path('app/js/balance-data.js'),
            'js/balance-module.js' => public_path('app/js/balance-module.js'),
            'js/student-theme.js' => public_path('app/js/student-theme.js'),
            'js/student-account.js' => public_path('app/js/student-account.js'),
            'js/student-entitlements.js' => public_path('app/js/student-entitlements.js'),
            'js/student.js' => public_path('app/js/student.js'),
            'css/shared.css' => public_path('app/css/shared.css'),
            'css/student.css' => public_path('app/css/student.css'),
            'css/animations.css' => public_path('app/css/animations.css'),
            'css/elements.css' => public_path('app/css/elements.css'),
            'css/duck-race-student.css' => public_path('app/css/duck-race-student.css'),
            'css/student-themes.css' => public_path('app/css/student-themes.css'),
            'css/keyboard-runtime.css' => public_path('app/css/keyboard-runtime.css'),
            'css/quiz.css' => public_path('app/css/quiz.css'),
            'css/balance.css' => public_path('app/css/balance.css'),
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
}

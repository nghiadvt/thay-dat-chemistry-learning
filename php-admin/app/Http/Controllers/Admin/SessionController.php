<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\GameSession;
use App\Models\Quiz;
use App\Models\User;
use App\Services\GamePlayModeResolver;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use App\Services\PinGenerator;
use App\Services\RedisRoomService;
use App\Services\SessionQrService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SessionController extends Controller
{
    public function __construct(
        private PinGenerator $pinGenerator,
        private RedisRoomService $redisRoomService,
        private SessionQrService $sessionQrService,
    ) {}

    public function index(Request $request): View
    {
        $query = GameSession::query()
            ->with(['game:id,name', 'quiz:id,name', 'host:id,name']);

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', '%'.$search.'%')
                    ->orWhere('pin', 'like', '%'.$search.'%');
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->has('is_active') && $request->input('is_active') !== '') {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->filled('game_id')) {
            $query->where('game_id', $request->integer('game_id'));
        }

        if ($request->filled('host_id')) {
            $query->where('host_id', $request->integer('host_id'));
        }

        if ($request->filled('created_from')) {
            $query->whereDate('created_at', '>=', $request->date('created_from'));
        }

        if ($request->filled('created_to')) {
            $query->whereDate('created_at', '<=', $request->date('created_to'));
        }

        $sessions = $query
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        $games = Game::query()->orderBy('name')->get(['id', 'name']);
        $hosts = User::query()
            ->whereIn('id', GameSession::query()->whereNotNull('host_id')->distinct()->pluck('host_id'))
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('admin.sessions.index', compact('sessions', 'games', 'hosts', 'search'));
    }

    public function create(): View
    {
        $games = Game::query()->orderBy('name')->get();

        $quizzes = Quiz::query()
            ->with('game:id,name')
            ->withCount(['questions as active_questions_count' => fn ($q) => $q->where('is_active', true)])
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('admin.sessions.create', compact('games', 'quizzes'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'quiz_id' => ['required', 'integer', Rule::exists('quizzes', 'id')->where('is_active', true)],
        ]);

        $quiz = Quiz::with('game')->findOrFail($validated['quiz_id']);

        if ($quiz->questions()->where('is_active', true)->count() === 0) {
            return back()->with('error', 'Quiz chưa có câu hỏi active. Thêm câu hỏi trước khi tạo phòng.');
        }

        $pin = $this->pinGenerator->generateUniquePin();
        $playMode = app(GamePlayModeResolver::class)->forGame($quiz->game);

        $session = GameSession::create([
            'pin' => $pin,
            'name' => $validated['name'],
            'host_id' => Auth::id(),
            'game_id' => $quiz->game_id,
            'quiz_id' => $quiz->id,
            'play_mode_slug' => $playMode['play_mode_slug'],
            'mode_config' => $playMode['mode_config'],
            'status' => 'waiting',
            'is_active' => true,
        ]);

        $this->redisRoomService->createWaitingRoom(
            $pin,
            $quiz->game_id,
            $quiz->id,
            $playMode['play_mode_slug'],
            $playMode['mode_config'],
        );

        try {
            $this->sessionQrService->ensureQr($session);
        } catch (\Throwable) {
            // Phòng vẫn dùng được; QR sẽ tạo lại khi mở trang host
        }

        return redirect()->route('admin.sessions.index')
            ->with('success', "Đã tạo phòng «{$session->name}» — PIN {$pin}.");
    }

    public function edit(GameSession $session): View
    {
        $session->load(['game', 'quiz', 'host']);

        $games = Game::query()->orderBy('name')->get();

        $quizzes = Quiz::query()
            ->with('game:id,name')
            ->withCount(['questions as active_questions_count' => fn ($q) => $q->where('is_active', true)])
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        // Keep current quiz in the dropdown even if it was deactivated after create.
        if ($session->quiz_id && ! $quizzes->contains('id', $session->quiz_id)) {
            $current = Quiz::query()
                ->with('game:id,name')
                ->withCount(['questions as active_questions_count' => fn ($q) => $q->where('is_active', true)])
                ->find($session->quiz_id);
            if ($current) {
                $quizzes->prepend($current);
            }
        }

        $canChangeQuiz = $session->status === 'waiting';

        return view('admin.sessions.edit', compact('session', 'games', 'quizzes', 'canChangeQuiz'));
    }

    public function update(Request $request, GameSession $session): RedirectResponse
    {
        $canChangeQuiz = $session->status === 'waiting';

        $rules = [
            'name' => ['required', 'string', 'max:255'],
        ];

        if ($canChangeQuiz) {
            $rules['quiz_id'] = ['required', 'integer', Rule::exists('quizzes', 'id')->where('is_active', true)];
        }

        $validated = $request->validate($rules);

        $session->name = $validated['name'];

        if ($canChangeQuiz) {
            $quiz = Quiz::with('game')->findOrFail($validated['quiz_id']);

            if ($quiz->questions()->where('is_active', true)->count() === 0) {
                return back()->with('error', 'Quiz chưa có câu hỏi active. Chọn quiz khác.')->withInput();
            }

            $quizChanged = (int) $session->quiz_id !== (int) $quiz->id;

            $session->quiz_id = $quiz->id;
            $session->game_id = $quiz->game_id;

            $playMode = app(GamePlayModeResolver::class)->forGame($quiz->game);
            $session->play_mode_slug = $playMode['play_mode_slug'];
            $session->mode_config = $playMode['mode_config'];

            if ($quizChanged && $session->is_active) {
                $this->redisRoomService->createWaitingRoom(
                    $session->pin,
                    $quiz->game_id,
                    $quiz->id,
                    $playMode['play_mode_slug'],
                    $playMode['mode_config'],
                );
            }
        }

        $session->save();

        return redirect()->route('admin.sessions.index')
            ->with('success', "Đã cập nhật phòng «{$session->name}» (PIN {$session->pin}).");
    }

    public function show(GameSession $session): View
    {
        $session = $this->syncSessionStatusFromRedis($session);
        $session->load(['game.playMode', 'quiz', 'host']);

        $joinUrl = $this->sessionQrService->joinUrl($session);
        // Always a scannable QR for /join/{pin} — never the mock qr-login.png asset.
        $qrUrl = $this->sessionQrService->displayQrUrl($session, $joinUrl);

        return view('admin.sessions.show', compact('session', 'joinUrl', 'qrUrl'));
    }

    public function close(GameSession $session): RedirectResponse
    {
        $session->update(['is_active' => false]);
        $this->redisRoomService->purgeRoom($session->pin);

        return redirect()
            ->route('admin.sessions.index')
            ->with('success', "Đã kết thúc phòng «{$session->name}» — PIN {$session->pin} đã tắt.");
    }

    public function regeneratePin(GameSession $session): RedirectResponse
    {
        if ($session->status === 'playing') {
            return back()->with('error', 'Không thể đổi PIN khi game đang chơi. Kết thúc trò chơi hoặc phòng trước.');
        }

        $oldPin = $session->pin;
        $newPin = $this->pinGenerator->generateUniquePin($oldPin);

        $session->loadMissing('game.playMode');
        $playMode = app(GamePlayModeResolver::class)->forGame($session->game);
        $playModeSlug = $session->play_mode_slug ?: $playMode['play_mode_slug'];
        $modeConfig = $session->mode_config ?: $playMode['mode_config'];

        $this->redisRoomService->migrateRoomPin(
            $oldPin,
            $newPin,
            (int) $session->game_id,
            $session->quiz_id ? (int) $session->quiz_id : null,
            $playModeSlug,
            $modeConfig,
        );

        if ($session->status === 'ended') {
            $session->update([
                'pin' => $newPin,
                'status' => 'waiting',
                'started_at' => null,
                'ended_at' => null,
            ]);
        } else {
            $session->update(['pin' => $newPin]);
        }

        Storage::disk('public')->delete([
            "sessions/{$oldPin}.png",
            "sessions/{$oldPin}.joinurl",
        ]);

        try {
            $this->sessionQrService->ensureQr($session->fresh());
        } catch (\Throwable) {
            // QR CDN fallback on next page load
        }

        return back()->with('success', "Đã đổi PIN phòng «{$session->name}»: {$oldPin} → {$newPin}. QR đã cập nhật.");
    }

    private function syncSessionStatusFromRedis(GameSession $session): GameSession
    {
        $redis = Redis::connection('rooms');
        $key = "room:{$session->pin}";

        if (! $redis->exists($key)) {
            if ($session->status === 'playing') {
                $session->update([
                    'status' => 'ended',
                    'ended_at' => $session->ended_at ?? now(),
                ]);
            }

            return $session->fresh();
        }

        $redisStatus = $redis->hGet($key, 'status');
        if (! $redisStatus || $redisStatus === $session->status) {
            return $session;
        }

        $updates = ['status' => $redisStatus];
        if ($redisStatus === 'ended' && ! $session->ended_at) {
            $updates['ended_at'] = now();
        }
        if ($redisStatus === 'playing' && ! $session->started_at) {
            $updates['started_at'] = now();
        }

        $session->update($updates);

        return $session->fresh();
    }

    public function toggleActive(GameSession $session): RedirectResponse
    {
        $session->update(['is_active' => ! $session->is_active]);

        if (! $session->is_active) {
            $this->redisRoomService->purgeRoom($session->pin);
        } elseif ($session->status === 'waiting') {
            $this->redisRoomService->createWaitingRoom($session->pin, $session->game_id, $session->quiz_id);
        } elseif ($session->status === 'ended') {
            $this->redisRoomService->resetRoomForReplay($session->pin, $session->game_id, $session->quiz_id);
        }

        return back()->with('success', $session->is_active ? 'Đã bật phòng.' : 'Đã tắt phòng — học sinh không thể tham gia.');
    }

    public function reset(GameSession $session): RedirectResponse
    {
        if ($session->status !== 'ended') {
            return back()->with('error', 'Chỉ có thể chơi lại phòng đã kết thúc (ended).');
        }

        if (! $session->is_active) {
            return back()->with('error', 'Bật phòng trước khi chơi lại.');
        }

        if (! $session->quiz_id) {
            return back()->with('error', 'Phòng không gắn quiz — không thể reset.');
        }

        $session->update([
            'status' => 'waiting',
            'started_at' => null,
            'ended_at' => null,
        ]);

        $this->redisRoomService->resetRoomForReplay($session->pin, $session->game_id, $session->quiz_id);

        return redirect()
            ->route('admin.sessions.show', $session)
            ->with('success', "Đã reset phòng «{$session->name}» — PIN {$session->pin} sẵn sàng chơi lại.");
    }

    public function destroy(GameSession $session): RedirectResponse
    {
        $label = $session->name ?? $session->pin;

        if ($error = $this->deleteSession($session)) {
            return back()->with('error', $error);
        }

        return redirect()
            ->route('admin.sessions.index')
            ->with('success', "Đã xóa phòng «{$label}».");
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', Rule::exists('game_sessions', 'id')],
        ]);

        $sessions = GameSession::query()
            ->whereIn('id', $validated['ids'])
            ->get();

        $deleted = 0;
        $skipped = [];

        foreach ($sessions as $session) {
            $label = $session->name ?? $session->pin;
            if ($error = $this->deleteSession($session)) {
                $skipped[] = $label;

                continue;
            }
            $deleted++;
        }

        if ($deleted === 0) {
            return back()->with('error', $skipped
                ? 'Không xóa được phòng nào. Phòng đang chơi không thể xóa.'
                : 'Không có phòng nào được xóa.');
        }

        $message = "Đã xóa {$deleted} phòng.";
        if ($skipped !== []) {
            $message .= ' Bỏ qua '.count($skipped).' phòng đang chơi: '.implode(', ', $skipped).'.';
        }

        return redirect()
            ->route('admin.sessions.index')
            ->with($skipped !== [] && $deleted > 0 ? 'warning' : 'success', $message);
    }

    /**
     * @return string|null Error message when delete is blocked; null on success.
     */
    private function deleteSession(GameSession $session): ?string
    {
        if ($session->status === 'playing') {
            $label = $session->name ?? $session->pin;

            return "Không thể xóa phòng «{$label}» — game đang chơi.";
        }

        $pin = $session->pin;

        $this->redisRoomService->purgeRoom($pin);

        $paths = array_values(array_filter([
            "sessions/{$pin}.png",
            "sessions/{$pin}.joinurl",
            $session->qr_path,
        ]));

        if ($paths !== []) {
            Storage::disk('public')->delete($paths);
        }

        $session->delete();

        return null;
    }
}

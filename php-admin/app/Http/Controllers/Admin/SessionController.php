<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\GameSession;
use App\Models\Quiz;
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

    public function index(): View
    {
        $sessions = GameSession::query()
            ->with(['game:id,name', 'quiz:id,name', 'host:id,name'])
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('admin.sessions.index', compact('sessions'));
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

        $session = GameSession::create([
            'pin' => $pin,
            'name' => $validated['name'],
            'host_id' => Auth::id(),
            'game_id' => $quiz->game_id,
            'quiz_id' => $quiz->id,
            'status' => 'waiting',
            'is_active' => true,
        ]);

        $this->redisRoomService->createWaitingRoom($pin, $quiz->game_id, $quiz->id);

        try {
            $this->sessionQrService->ensureQr($session);
        } catch (\Throwable) {
            // Phòng vẫn dùng được; QR sẽ tạo lại khi mở trang host
        }

        return redirect()->route('admin.sessions.index')
            ->with('success', "Đã tạo phòng «{$session->name}» — PIN {$pin}.");
    }

    public function show(GameSession $session): View
    {
        $session->load(['game', 'quiz', 'host']);

        $joinUrl = $this->sessionQrService->joinUrl($session);

        try {
            $this->sessionQrService->ensureQr($session, $joinUrl);
            $session->refresh();
        } catch (\Throwable) {
            // Hiển thị placeholder nếu không tạo được QR
        }

        $qrUrl = $session->qr_url;

        return view('admin.sessions.show', compact('session', 'joinUrl', 'qrUrl'));
    }

    public function toggleActive(GameSession $session): RedirectResponse
    {
        $session->update(['is_active' => ! $session->is_active]);

        if (! $session->is_active) {
            $this->redisRoomService->destroyRoom($session->pin);
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
}

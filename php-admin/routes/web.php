<?php

use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\GameController as AdminGameController;
use App\Http\Controllers\Admin\KeyboardController as AdminKeyboardController;
use App\Http\Controllers\Admin\QuestionController as AdminQuestionController;
use App\Http\Controllers\Admin\QuizController as AdminQuizController;
use App\Http\Controllers\Admin\ReportController as AdminReportController;
use App\Http\Controllers\Admin\SessionController as AdminSessionController;
use App\Http\Controllers\Api\GameController;
use App\Http\Controllers\Api\GameSessionController;
use App\Http\Controllers\Api\KeyboardController;
use App\Http\Controllers\Api\QuestionController;
use App\Http\Controllers\Api\QuestionImageController;
use App\Http\Controllers\Api\QuizController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\RoomController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\StudentJoinController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('manifest.webmanifest', function () {
    $path = public_path('app/manifest.webmanifest');
    abort_unless(is_readable($path), 404);

    return response()->file($path, [
        'Content-Type' => 'application/manifest+json; charset=UTF-8',
        'Cache-Control' => 'no-cache',
    ]);
});

Route::get('sw.js', function () {
    $path = public_path('app/sw.js');
    abort_unless(is_readable($path), 404);

    return response()->file($path, [
        'Content-Type' => 'application/javascript; charset=UTF-8',
        'Service-Worker-Allowed' => '/',
        'Cache-Control' => 'no-cache',
    ]);
});

Route::get('join', [StudentJoinController::class, 'show'])->name('student.join');
Route::get('join/{pin}', [StudentJoinController::class, 'show'])
    ->where('pin', '[0-9]{6}')
    ->name('student.join.pin');
Route::get('app/index.html', [StudentJoinController::class, 'legacyIndex']);

Route::get('api/rooms/{pin}', [RoomController::class, 'show']);

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    Route::redirect('/dashboard', '/admin')->name('dashboard');

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

        Route::resource('keyboards', AdminKeyboardController::class)->except(['show', 'update']);
        Route::get('keyboards/{keyboard}/editor', [AdminKeyboardController::class, 'editor'])->name('keyboards.editor');
        Route::resource('games', AdminGameController::class)->except(['show']);
        Route::resource('quizzes', AdminQuizController::class);
        Route::patch('quizzes/{quiz}/active', [AdminQuizController::class, 'toggleActive'])->name('quizzes.toggle-active');

        Route::get('quizzes/{quiz}/questions/create', [AdminQuestionController::class, 'create'])->name('questions.create');
        Route::post('quizzes/{quiz}/questions', [AdminQuestionController::class, 'store'])->name('questions.store');
        Route::get('quizzes/{quiz}/questions/{question}/edit', [AdminQuestionController::class, 'edit'])->name('questions.edit');
        Route::put('quizzes/{quiz}/questions/{question}', [AdminQuestionController::class, 'update'])->name('questions.update');
        Route::patch('quizzes/{quiz}/questions/{question}/active', [AdminQuestionController::class, 'toggleActive'])->name('questions.toggle-active');
        Route::delete('quizzes/{quiz}/questions/{question}', [AdminQuestionController::class, 'destroy'])->name('questions.destroy');

        Route::get('sessions', [AdminSessionController::class, 'index'])->name('sessions.index');
        Route::get('sessions/create', [AdminSessionController::class, 'create'])->name('sessions.create');
        Route::post('sessions', [AdminSessionController::class, 'store'])->name('sessions.store');
        Route::get('sessions/{session}/edit', [AdminSessionController::class, 'edit'])->name('sessions.edit');
        Route::put('sessions/{session}', [AdminSessionController::class, 'update'])->name('sessions.update');
        Route::get('sessions/{session}', [AdminSessionController::class, 'show'])->name('sessions.show');
        Route::post('sessions/{session}/reset', [AdminSessionController::class, 'reset'])->name('sessions.reset');
        Route::patch('sessions/{session}/active', [AdminSessionController::class, 'toggleActive'])->name('sessions.toggle-active');

        Route::get('reports', [AdminReportController::class, 'index'])->name('reports.index');
        Route::get('reports/{session}', [AdminReportController::class, 'show'])->name('reports.show');
        Route::get('reports/{session}/export', [AdminReportController::class, 'export'])->name('reports.export');
    });

    Route::prefix('api')->group(function () {
        Route::apiResource('keyboards', KeyboardController::class);
        Route::post('keyboards/{keyboard}/preview', [KeyboardController::class, 'uploadPreview'])->name('keyboards.preview');
        Route::apiResource('games', GameController::class);
        Route::apiResource('quizzes', QuizController::class);

        Route::post('question-content-images', [QuestionImageController::class, 'store'])->name('question-images.store');

        Route::get('quizzes/{quiz}/questions', [QuestionController::class, 'index']);
        Route::post('quizzes/{quiz}/questions', [QuestionController::class, 'store']);
        Route::get('quizzes/{quiz}/questions/{question}', [QuestionController::class, 'show']);
        Route::put('quizzes/{quiz}/questions/{question}', [QuestionController::class, 'update']);
        Route::patch('quizzes/{quiz}/questions/{question}', [QuestionController::class, 'update']);
        Route::delete('quizzes/{quiz}/questions/{question}', [QuestionController::class, 'destroy']);

        Route::post('game-sessions', [GameSessionController::class, 'store']);
        Route::get('game-sessions/{session}', [GameSessionController::class, 'show']);
        Route::post('game-sessions/{session}/reset', [GameSessionController::class, 'reset']);

        Route::get('reports/sessions', [ReportController::class, 'sessions']);
        Route::get('reports/sessions/{session}', [ReportController::class, 'sessionDetail']);
        Route::get('reports/students/aggregate', [ReportController::class, 'studentAggregate']);
    });
});

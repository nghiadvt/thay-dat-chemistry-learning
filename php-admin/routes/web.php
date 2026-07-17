<?php

use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\FeedbackController as AdminFeedbackController;
use App\Http\Controllers\Admin\GameController as AdminGameController;
use App\Http\Controllers\Admin\ImageCropperController as AdminImageCropperController;
use App\Http\Controllers\Admin\KeyboardController as AdminKeyboardController;
use App\Http\Controllers\Admin\QuestionBankController as AdminQuestionBankController;
use App\Http\Controllers\Admin\QuestionController as AdminQuestionController;
use App\Http\Controllers\Admin\QuizController as AdminQuizController;
use App\Http\Controllers\Admin\ReportController as AdminReportController;
use App\Http\Controllers\Admin\SessionController as AdminSessionController;
use App\Http\Controllers\Admin\TagController as AdminTagController;
use App\Http\Controllers\Api\GameController;
use App\Http\Controllers\Api\GameSessionController;
use App\Http\Controllers\Api\ImageCropRegionController;
use App\Http\Controllers\Api\ImageCropSourceController;
use App\Http\Controllers\Api\KeyboardController;
use App\Http\Controllers\Api\PracticeController;
use App\Http\Controllers\Api\QuestionController;
use App\Http\Controllers\Api\QuestionImageController;
use App\Http\Controllers\Api\QuizController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\RoomController;
use App\Http\Controllers\Api\SiteFeedbackController;
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

Route::get('home', [StudentJoinController::class, 'home'])->name('student.home');

Route::get('join', [StudentJoinController::class, 'show'])->name('student.join');
Route::get('join/{pin}', [StudentJoinController::class, 'show'])
    ->where('pin', '[0-9]{6}')
    ->name('student.join.pin');
Route::get('app/index.html', [StudentJoinController::class, 'legacyIndex']);

Route::get('api/rooms/{pin}', [RoomController::class, 'show']);

// Ôn trắc nghiệm (học sinh, không cần đăng nhập)
Route::get('api/practice/topics', [PracticeController::class, 'topics']);
Route::get('api/practice/questions', [PracticeController::class, 'questions']);

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    Route::redirect('/dashboard', '/admin')->name('dashboard');

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');
        Route::get('dashboard/export', [AdminDashboardController::class, 'export'])->name('dashboard.export');
        Route::view('appearance', 'admin.appearance.index')->name('appearance');
        Route::get('image-cropper', [AdminImageCropperController::class, 'index'])->name('image-cropper.index');
        Route::get('image-cropper/create', [AdminImageCropperController::class, 'create'])->name('image-cropper.create');
        Route::get('image-cropper/{imageCropper}/edit', [AdminImageCropperController::class, 'edit'])->name('image-cropper.edit');
        Route::patch('image-cropper/{imageCropper}/groups', [AdminImageCropperController::class, 'updateGroups'])->name('image-cropper.update-groups');
        Route::delete('image-cropper/{imageCropper}', [AdminImageCropperController::class, 'destroy'])->name('image-cropper.destroy');

        Route::resource('keyboards', AdminKeyboardController::class)->except(['show', 'update']);
        Route::get('keyboards/{keyboard}/editor', [AdminKeyboardController::class, 'editor'])->name('keyboards.editor');
        Route::get('games/battle-arena-demo', [AdminGameController::class, 'battleDemo'])->name('games.battle-demo');
        Route::get('games/dragon-hunt-demo', [AdminGameController::class, 'dragonDemo'])->name('games.dragon-demo');
        Route::resource('games', AdminGameController::class)->except(['show']);
        Route::get('quizzes/export-csv', [AdminQuizController::class, 'exportCsv'])->name('quizzes.export-csv');
        Route::get('quizzes/import-template', [AdminQuizController::class, 'importTemplate'])->name('quizzes.import-template');
        Route::post('quizzes/import-csv', [AdminQuizController::class, 'importCsv'])->name('quizzes.import-csv');
        Route::resource('quizzes', AdminQuizController::class);
        Route::patch('quizzes/{quiz}/active', [AdminQuizController::class, 'toggleActive'])->name('quizzes.toggle-active');

        Route::get('question-bank/export-csv', [AdminQuestionBankController::class, 'exportCsv'])->name('question-bank.export-csv');
        Route::get('question-bank/import-template', [AdminQuestionBankController::class, 'importTemplate'])->name('question-bank.import-template');
        Route::post('question-bank/import-csv', [AdminQuestionBankController::class, 'importCsv'])->name('question-bank.import-csv');
        Route::get('question-bank/search', [AdminQuestionBankController::class, 'search'])->name('question-bank.search');
        Route::patch('question-bank/bulk-tags', [AdminQuestionBankController::class, 'bulkUpdateTags'])->name('question-bank.bulk-tags');
        Route::patch('question-bank/{question_bank}/tags', [AdminQuestionBankController::class, 'updateTags'])->name('question-bank.update-tags');
        Route::resource('question-bank', AdminQuestionBankController::class)->except(['show']);

        Route::get('tags', [AdminTagController::class, 'index'])->name('tags.index');
        Route::post('tags', [AdminTagController::class, 'store'])->name('tags.store');
        Route::patch('tags/{tag}', [AdminTagController::class, 'update'])->name('tags.update');
        Route::get('image-crop-groups', [AdminTagController::class, 'indexGroups'])->name('image-crop-groups.index');
        Route::post('image-crop-groups', [AdminTagController::class, 'storeGroup'])->name('image-crop-groups.store');

        Route::get('quizzes/{quiz}/questions/create', [AdminQuestionController::class, 'create'])->name('questions.create');
        Route::post('quizzes/{quiz}/questions', [AdminQuestionController::class, 'store'])->name('questions.store');
        Route::post('quizzes/{quiz}/questions/from-bank', [AdminQuestionController::class, 'fromBank'])->name('questions.from-bank');
        Route::patch('quizzes/{quiz}/questions/reorder', [AdminQuestionController::class, 'reorder'])->name('questions.reorder');
        Route::patch('quizzes/{quiz}/questions/bulk', [AdminQuestionController::class, 'bulkUpdate'])->name('questions.bulk');
        Route::patch('quizzes/{quiz}/questions/{question}/tags', [AdminQuestionController::class, 'updateTags'])->name('questions.update-tags');
        Route::get('quizzes/{quiz}/questions/{question}/edit', [AdminQuestionController::class, 'edit'])->name('questions.edit');
        Route::put('quizzes/{quiz}/questions/{question}', [AdminQuestionController::class, 'update'])->name('questions.update');
        Route::patch('quizzes/{quiz}/questions/{question}/active', [AdminQuestionController::class, 'toggleActive'])->name('questions.toggle-active');
        Route::delete('quizzes/{quiz}/questions/{question}', [AdminQuestionController::class, 'destroy'])->name('questions.destroy');

        Route::get('sessions', [AdminSessionController::class, 'index'])->name('sessions.index');
        Route::get('sessions/create', [AdminSessionController::class, 'create'])->name('sessions.create');
        Route::post('sessions', [AdminSessionController::class, 'store'])->name('sessions.store');
        Route::post('sessions/bulk-destroy', [AdminSessionController::class, 'bulkDestroy'])->name('sessions.bulk-destroy');
        Route::get('sessions/{session}/edit', [AdminSessionController::class, 'edit'])->name('sessions.edit');
        Route::put('sessions/{session}', [AdminSessionController::class, 'update'])->name('sessions.update');
        Route::delete('sessions/{session}', [AdminSessionController::class, 'destroy'])->name('sessions.destroy');
        Route::get('sessions/{session}', [AdminSessionController::class, 'show'])->name('sessions.show');
        Route::post('sessions/{session}/reset', [AdminSessionController::class, 'reset'])->name('sessions.reset');
        Route::post('sessions/{session}/close', [AdminSessionController::class, 'close'])->name('sessions.close');
        Route::post('sessions/{session}/regenerate-pin', [AdminSessionController::class, 'regeneratePin'])->name('sessions.regenerate-pin');
        Route::patch('sessions/{session}/active', [AdminSessionController::class, 'toggleActive'])->name('sessions.toggle-active');

        Route::get('reports', [AdminReportController::class, 'index'])->name('reports.index');
        Route::get('reports/{session}', [AdminReportController::class, 'show'])->name('reports.show');
        Route::get('reports/{session}/export', [AdminReportController::class, 'export'])->name('reports.export');

        Route::get('feedback', [AdminFeedbackController::class, 'index'])->name('feedback.index');
        Route::get('feedback/{feedback}', [AdminFeedbackController::class, 'show'])->name('feedback.show');
        Route::patch('feedback/{feedback}/status', [AdminFeedbackController::class, 'updateStatus'])->name('feedback.update-status');
    });

    Route::prefix('api')->group(function () {
        Route::apiResource('keyboards', KeyboardController::class);
        Route::post('keyboards/{keyboard}/preview', [KeyboardController::class, 'uploadPreview'])->name('keyboards.preview');
        Route::apiResource('games', GameController::class);
        Route::apiResource('quizzes', QuizController::class);

        Route::post('question-content-images', [QuestionImageController::class, 'store'])->name('question-images.store');
        Route::post('image-crop-sources', [ImageCropSourceController::class, 'store'])->name('image-crop-sources.store');
        Route::post('image-crop-sources/{source}/regions', [ImageCropRegionController::class, 'sync'])->name('image-crop-sources.regions.sync');
        Route::delete('image-crop-sources/{source}/regions/{region}', [ImageCropRegionController::class, 'destroy'])->name('image-crop-sources.regions.destroy');

        Route::post('site-feedback', [SiteFeedbackController::class, 'store'])->name('site-feedback.store');

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

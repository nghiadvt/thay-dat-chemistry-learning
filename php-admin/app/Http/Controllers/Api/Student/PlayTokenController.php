<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Services\StudentPlayToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Cấp token ngắn hạn để student app chứng minh danh tính với ws-server khi vào
 * phòng chơi — nhờ đó kết quả được gắn đúng tài khoản học sinh.
 */
class PlayTokenController extends Controller
{
    public function __construct(
        private StudentPlayToken $tokens,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $student = $request->user('student');

        return $this->jsonSuccess([
            'play_token' => $this->tokens->issue($student),
            'expires_in' => StudentPlayToken::TTL_SECONDS,
        ]);
    }
}

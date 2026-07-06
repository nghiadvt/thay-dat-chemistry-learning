@extends('layouts.admin')

@section('title', 'Báo cáo PIN '.$session->pin)
@section('page-title', 'Chi tiết báo cáo')

@section('content')
<div class="page-header">
    <div>
        <h2>Session PIN {{ $session->pin }}</h2>
        <p style="margin:4px 0 0;color:#6b7280;">
            {{ $session->game?->name }} · Kết thúc {{ $session->ended_at?->format('d/m/Y H:i') }}
        </p>
    </div>
    <div class="actions">
        <a href="{{ route('admin.reports.export', $session) }}" class="btn btn-primary">Tải CSV</a>
        <a href="{{ route('admin.reports.index') }}" class="btn btn-secondary">← Danh sách</a>
    </div>
</div>

<div class="card">
    <h3>Bảng xếp hạng ({{ $session->results->count() }} học sinh)</h3>
    @if ($session->results->isEmpty())
        <div class="empty-state">Chưa có kết quả.</div>
    @else
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Hạng</th>
                    <th>Học sinh</th>
                    <th>Điểm</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($session->results as $result)
                <tr>
                    <td>{{ $result->rank }}</td>
                    <td><strong>{{ $result->student_name }}</strong></td>
                    <td>{{ $result->score }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>

@if ($session->answers->isNotEmpty())
<div class="card">
    <h3>Chi tiết câu trả lời ({{ $session->answers->count() }})</h3>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Học sinh</th>
                    <th>Câu hỏi</th>
                    <th>Loại</th>
                    <th>Đúng</th>
                    <th>Điểm</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($session->answers as $answer)
                <tr>
                    <td>{{ $answer->student_name }}</td>
                    <td>{{ Str::limit(strip_tags($answer->question?->content ?? ''), 50) }}</td>
                    <td><code>{{ $answer->question?->answer_type }}</code></td>
                    <td>{{ $answer->is_correct ? '✓' : '✗' }}</td>
                    <td>{{ $answer->score_earned }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif
@endsection

@extends('layouts.admin')

@section('title', 'Bàn phím — Hóa Thầy Đạt')
@section('page-title', 'Bàn phím')

@section('content')
<div class="page-header">
    <h2>Danh sách bàn phím</h2>
    <a href="{{ route('admin.keyboards.create') }}" class="btn btn-primary">+ Tạo bàn phím</a>
</div>

<div class="card">
    @if ($keyboards->isEmpty())
        <div class="empty-state">Chưa có bàn phím. <a href="{{ route('admin.keyboards.create') }}">Tạo mới</a></div>
    @else
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Tên</th>
                    <th>Môn</th>
                    <th>Số hàng</th>
                    <th>Quiz dùng</th>
                    <th>Cập nhật</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($keyboards as $keyboard)
                <tr>
                    <td><strong>{{ $keyboard->name }}</strong></td>
                    <td>{{ $keyboard->subject }}</td>
                    <td>{{ count($keyboard->config['rows'] ?? []) }}</td>
                    <td>{{ $keyboard->quizzes_count }}</td>
                    <td>{{ $keyboard->updated_at?->format('d/m/Y H:i') }}</td>
                    <td class="actions">
                        <a href="{{ route('admin.keyboards.edit', $keyboard) }}" class="btn btn-secondary btn-sm">Sửa</a>
                        <form method="POST" action="{{ route('admin.keyboards.destroy', $keyboard) }}" onsubmit="return confirm('Xóa bàn phím này?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-danger btn-sm">Xóa</button>
                        </form>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>
@endsection

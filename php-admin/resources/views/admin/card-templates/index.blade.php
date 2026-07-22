@extends('layouts.admin')

@section('title', 'Mẫu thẻ của tôi — Hóa Thầy Đạt')

@section('content')
<div class="page-header">
    <div class="page-header__text">
        <h2>Mẫu thẻ của tôi</h2>
        <p class="page-header__meta">Thiết kế phiếu tài khoản in cho học sinh — tái sử dụng cho nhiều lớp.</p>
    </div>
    <a href="{{ route('admin.card-templates.create') }}" class="btn btn-primary">+ Thiết kế thẻ mới</a>
</div>

<div class="card admin-list-card">
    @if ($templates->isEmpty())
        <p style="padding:1.5rem;margin:0;color:#64748b">Chưa có mẫu nào. Bấm «Thiết kế thẻ mới» để bắt đầu.</p>
    @else
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Tên mẫu</th>
                    <th>Mặt</th>
                    <th>Khung (mm)</th>
                    <th>Cập nhật</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($templates as $tpl)
                    <tr>
                        <td><strong>{{ $tpl->name }}</strong></td>
                        <td>{{ $tpl->sides === 2 ? '2 mặt' : '1 mặt' }}</td>
                        <td>{{ $tpl->frame_width_mm }} × {{ $tpl->frame_height_mm }}</td>
                        <td>{{ $tpl->updated_at?->format('d/m/Y H:i') }}</td>
                        <td class="table-actions">
                            <a href="{{ route('admin.card-templates.edit', $tpl) }}" class="btn btn-sm">Sửa</a>
                            <a href="{{ route('admin.card-templates.preview', $tpl) }}" class="btn btn-sm" target="_blank" rel="noopener">Xem trước</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
@endsection

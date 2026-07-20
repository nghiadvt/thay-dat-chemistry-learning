@extends('layouts.admin')

@section('title', 'Nhóm — Hóa Thầy Đạt')

@php
    $scopeLabels = \App\Models\Group::SCOPE_LABELS;
    $currentLabel = $scopeLabels[$scope] ?? $scope;
    $backRoute = match ($scope) {
        \App\Models\Group::SCOPE_QUESTION_BANK => route('admin.question-bank.index'),
        \App\Models\Group::SCOPE_SESSION => route('admin.sessions.index'),
        default => route('admin.quizzes.index'),
    };
@endphp

@section('content')
<div class="page-header">
    <div class="page-header__text">
        <h2>Nhóm {{ mb_strtolower($currentLabel) }}</h2>
        <p class="page-subtitle">Nhóm giúp gom các mục lại để dễ tìm và lọc. Mỗi mục thuộc tối đa một nhóm.</p>
    </div>
    <a href="{{ $backRoute }}" class="btn btn-secondary">← {{ $currentLabel }}</a>
</div>

<nav class="group-scope-tabs" aria-label="Chọn loại nhóm">
    @foreach ($scopeLabels as $value => $label)
        <a href="{{ route('admin.groups.index', ['scope' => $value]) }}"
           class="{{ $scope === $value ? 'active' : '' }}">{{ $label }}</a>
    @endforeach
</nav>

<div class="card" style="margin-bottom:16px">
    <h3 style="margin-top:0">Tạo nhóm mới</h3>
    <form method="POST" action="{{ route('admin.groups.store') }}" class="group-row-form">
        @csrf
        <input type="hidden" name="scope" value="{{ $scope }}">
        <input type="text" name="name" placeholder="VD: Chương 1 — Nguyên tử" required maxlength="100" value="{{ old('name') }}">
        <input type="color" name="color" value="{{ old('color', \App\Models\Group::nextDefaultColor($scope)) }}" aria-label="Màu nhóm">
        <input type="number" name="sort_order" min="0" value="{{ old('sort_order', 0) }}" aria-label="Thứ tự" title="Thứ tự hiển thị">
        <button type="submit" class="btn btn-primary">Tạo nhóm</button>
    </form>
    @error('name')<div class="field-error">{{ $message }}</div>@enderror
</div>

<div class="card admin-list-card">
    @if ($groups->isEmpty())
        <div class="empty-state">Chưa có nhóm nào cho {{ mb_strtolower($currentLabel) }}.</div>
    @else
        <div class="table-wrap admin-list-table-wrap">
            <table class="data-table admin-list-table">
                <thead>
                    <tr>
                        <th>Nhóm</th>
                        <th>Số mục</th>
                        <th>Hành động</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($groups as $group)
                        <tr>
                            <td>
                                <form method="POST" action="{{ route('admin.groups.update', $group) }}" class="group-row-form">
                                    @csrf @method('PUT')
                                    <input type="text" name="name" value="{{ $group->name }}" required maxlength="100">
                                    <input type="color" name="color" value="{{ $group->color }}" aria-label="Màu nhóm">
                                    <input type="number" name="sort_order" min="0" value="{{ $group->sort_order }}" aria-label="Thứ tự">
                                    <button type="submit" class="btn btn-secondary btn-sm">Lưu</button>
                                </form>
                            </td>
                            <td>{{ $counts[$group->id] ?? 0 }}</td>
                            <td class="actions-cell">
                                <form method="POST" action="{{ route('admin.groups.destroy', $group) }}" style="display:inline"
                                      data-confirm="Xóa nhóm «{{ $group->name }}»? Các mục trong nhóm sẽ chuyển về «Chưa phân nhóm», không bị xóa."
                                      data-confirm-danger="1">
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

@extends('layouts.admin')

@section('title', ($game ? 'Sửa' : 'Tạo').' game — Hóa Thầy Đạt')
@section('page-title', $game ? 'Sửa game' : 'Tạo game')

@section('content')
<div class="page-header">
    <h2>{{ $game ? 'Sửa: '.$game->name : 'Tạo game mới' }}</h2>
    <a href="{{ route('admin.games.index') }}" class="btn btn-secondary">← Quay lại</a>
</div>

<div class="card">
    <form method="POST" action="{{ $game ? route('admin.games.update', $game) : route('admin.games.store') }}">
        @csrf
        @if ($game) @method('PUT') @endif

        <div class="form-group">
            <label for="name">Tên game *</label>
            <input type="text" id="name" name="name" value="{{ old('name', $game?->name) }}" required>
            @error('name')<div class="field-error">{{ $message }}</div>@enderror
        </div>

        <div class="form-group">
            <label for="description">Mô tả</label>
            <textarea id="description" name="description" rows="4">{{ old('description', $game?->description) }}</textarea>
            @error('description')<div class="field-error">{{ $message }}</div>@enderror
        </div>

        <button type="submit" class="btn btn-primary">{{ $game ? 'Cập nhật' : 'Tạo game' }}</button>
    </form>
</div>
@endsection

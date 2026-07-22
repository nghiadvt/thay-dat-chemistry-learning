@extends('layouts.admin')

@section('title', 'Sửa phiên bản — '.$preset->name)

@push('head')
<link rel="stylesheet" href="@vasset('css/admin-periodic.css')">
@endpush

@section('content')
<div class="page-header">
    <div>
        <a href="{{ route('admin.periodic.index') }}" class="page-header__back">← Tất cả phiên bản</a>
        <h2 data-preset-name>{{ $preset->name }}</h2>
        <p class="page-header__sub">Click một ô để chỉnh. Mọi thay đổi lưu vào bản nháp — bấm <strong>Xuất bản</strong> mới tới học sinh.</p>
    </div>
    <span class="pw-savestate" data-savestate>Đã lưu nháp</span>
</div>

<div class="pw-toolbar">
    <div class="pw-viewtabs" role="tablist" aria-label="Chế độ xem">
        <button type="button" data-view="edit" class="is-active">Chế độ sửa</button>
        <button type="button" data-view="normal">Xem như HS thường</button>
        <button type="button" data-view="pro">Xem như HS Pro</button>
    </div>
    <div class="pw-bulk" data-bulk hidden>
        <span class="pw-selcount"><span data-selcount>0</span> ô đã chọn</span>
        <select data-bulk-group>
            <option value="">Chọn theo nhóm…</option>
        </select>
        <button type="button" class="btn btn-sm" data-bulk="lit-on">Bật sáng</button>
        <button type="button" class="btn btn-sm" data-bulk="lit-off">Tắt sáng</button>
        <button type="button" class="btn btn-sm" data-bulk="pro-on">Đặt Pro</button>
        <button type="button" class="btn btn-sm" data-bulk="pro-off">Bỏ Pro</button>
        <button type="button" class="btn btn-sm" data-bulk="show">Hiện</button>
        <button type="button" class="btn btn-sm" data-bulk="hide">Ẩn</button>
        <button type="button" class="btn btn-sm" data-bulk-clear>Bỏ chọn</button>
    </div>
    <button type="button" class="btn btn-secondary btn-sm" data-toggle-multi>Chọn nhiều ô</button>
</div>

<div class="pw-layout">
    <div class="pw-stage" data-stage>
        <div id="pwGrid"></div>
    </div>

    <aside class="pw-side">
        <div class="pw-panel">
            <h3>Thông tin phiên bản</h3>
            <div class="pw-field">
                <label>Tên phiên bản</label>
                <input type="text" data-name-input value="{{ $preset->name }}" maxlength="120">
            </div>
        </div>

        <div class="pw-panel">
            <h3>Nhóm nguyên tố</h3>
            <p class="page-header__sub" style="margin:0 0 8px">Sửa tên/màu ở đây — chú giải cũng hiện ngay trên bảng (giống học sinh).</p>
            <div class="pw-legend" data-legend></div>
            <button type="button" class="btn btn-sm" data-cat-add style="margin-top:10px">+ Thêm nhóm</button>
        </div>
    </aside>
</div>

<div class="pw-publishbar">
    <span class="pw-publishbar__warn" data-dirty-note @if(!$preset->has_unpublished_changes) hidden @endif>
        ⚠ Có thay đổi chưa xuất bản — học sinh vẫn thấy bản đã xuất bản trước đó.
    </span>
    <span class="pw-savestate" data-savestate>Đã lưu nháp</span>
    <div class="pw-publishbar__spacer"></div>
    <span class="pw-publishbar__warn">Xuất bản sẽ thay đổi ngay cho học sinh đang dùng.</span>
    <form method="POST" action="{{ route('admin.periodic.publish', $preset) }}"
          data-confirm="Xuất bản «{{ $preset->name }}»? Học sinh sẽ thấy phiên bản này ngay.">
        @csrf
        <button type="submit" class="btn btn-primary">🚀 Xuất bản cho học sinh</button>
    </form>
</div>

{{-- Popover chỉnh 1 ô --}}
<div class="pw-pop" data-pop hidden>
    <div class="pw-pop__head">
        <span class="pw-pop__sym" data-pop-sym></span>
        <div>
            <div data-pop-name style="font-weight:600"></div>
            <div data-pop-z style="font-size:.78rem;color:#6b7280"></div>
        </div>
        <button type="button" style="margin-left:auto;border:none;background:transparent;cursor:pointer;font-size:1.1rem" data-pop-close aria-label="Đóng">×</button>
    </div>

    <div class="pw-pop__row"><label>Sáng (active)</label>@include('admin.partials.toggle-switch', ['name' => null, 'label' => 'Sáng'])</div>
    <div class="pw-pop__row"><label>Hiện cho học sinh</label>@include('admin.partials.toggle-switch', ['name' => null, 'label' => 'Hiện'])</div>
    <div class="pw-pop__row"><label>Yêu cầu bản Pro</label>@include('admin.partials.toggle-switch', ['name' => null, 'label' => 'Pro'])</div>

    <details style="margin-top:8px">
        <summary style="cursor:pointer;font-size:.85rem">Sửa thông tin gốc &amp; âm thanh</summary>
        <p class="pw-pop__hint">Đổi thông tin gốc áp dụng cho MỌI phiên bản.</p>
        <div class="pw-field"><label>Tên tiếng Việt</label><input type="text" data-el-name-vi></div>
        <div class="pw-field"><label>Tên tiếng Anh (IUPAC)</label><input type="text" data-el-name-en></div>
        <div class="pw-field"><label>Khối lượng</label><input type="number" step="0.0001" data-el-mass></div>
        <div class="pw-field"><label>Nhóm</label><select data-el-cat></select></div>
        <div class="pw-field"><label>Thứ tự xuất hiện</label><input type="number" min="0" data-el-order></div>
        <div class="pw-field">
            <label>Âm thanh (mp3/m4a…) — thiếu thì đọc bằng máy</label>
            <input type="file" accept="audio/*" data-el-sound>
            <div data-el-sound-state style="font-size:.78rem;color:#6b7280"></div>
        </div>
        <button type="button" class="btn btn-sm btn-primary" data-el-save style="margin-top:8px">Lưu thông tin gốc</button>
    </details>
</div>

<script>
  window.__PERIODIC_BOOT__ = @json($boot);
</script>
@endsection

@push('scripts')
<script src="@vasset('js/periodic-layout.js')"></script>
<script src="@vasset('js/periodic-grid.js')"></script>
<script src="@vasset('js/periodic-workspace.js')"></script>
@endpush

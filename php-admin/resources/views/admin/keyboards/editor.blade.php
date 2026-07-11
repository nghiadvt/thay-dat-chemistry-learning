@extends('layouts.admin')

@section('title', 'Chỉnh bàn phím — '.$keyboard->name)
@section('body-class', 'admin-body--editor-tool')
@section('content-class', 'admin-content--tool')

@push('head')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800&display=swap" rel="stylesheet">
@php
    $kbeCss = public_path('htd-admin/css/keyboard-editor.css');
    $kbeShared = public_path('htd-admin/css/shared.css');
    $kbeV = file_exists($kbeCss) ? filemtime($kbeCss) : time();
@endphp
<link rel="stylesheet" href="@vasset('htd-admin/css/shared.css')">
<link rel="stylesheet" href="{{ asset('htd-admin/css/keyboard-editor.css') }}?v={{ $kbeV }}">
@endpush

@section('content')
<div class="kbe-app" id="adminKeyboardEditor">
    @include('admin.keyboards._editor-body')
</div>
@endsection

@push('scripts')
<script>
window.ADMIN_BOOT = {
    keyboard: @json($keyboardBoot),
    apiBase: @json(url('/')),
    wsUrl: @json(config('services.ws.url')),
};
</script>
<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
@php
    $kbeJs = public_path('htd-admin/js/keyboard-editor.js');
    $kbeJsV = file_exists($kbeJs) ? filemtime($kbeJs) : $kbeV;
    $kbeBoot = public_path('htd-admin/js/admin-boot.js');
    $kbeApi = public_path('htd-admin/js/api.js');
    $kbeInit = public_path('htd-admin/js/admin-keyboard-init.js');
@endphp
<script src="@vasset('htd-admin/js/admin-boot.js')"></script>
<script src="@vasset('htd-admin/js/api.js')"></script>
<script src="{{ asset('htd-admin/js/keyboard-editor.js') }}?v={{ $kbeJsV }}"></script>
<script src="@vasset('htd-admin/js/admin-keyboard-init.js')"></script>
@endpush

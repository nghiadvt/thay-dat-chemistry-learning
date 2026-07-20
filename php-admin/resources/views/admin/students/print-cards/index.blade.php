@extends('layouts.admin')

@section('title', 'In phiếu tài khoản — '.$class->name)

@php
    $cardsPerSheet = collect($templates)->mapWithKeys(fn ($tpl, $key) => [$key => $tpl['cardsPerSheet']]);
@endphp

@section('content')
<div class="stu-hero">
    <div class="stu-hero__text">
        <a class="stu-hero__back" href="{{ route('admin.students.classes.show', $class) }}">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M19 12H5m6 6-6-6 6-6"/></svg>
            {{ $class->name }}
        </a>
        <h2>In phiếu tài khoản</h2>
        <p class="stu-hero__meta"><strong>{{ $students->count() }}</strong> học sinh sẽ được in</p>
    </div>
</div>

<form method="POST" action="{{ route('admin.students.print-cards.export', $class) }}" id="printCardsForm" class="print-cards">
    @csrf

    <div class="print-cards__layout">
        <div class="print-cards__templates" role="radiogroup" aria-label="Chọn mẫu in">
            @foreach ($templates as $key => $tpl)
                <label class="template-card {{ $key === $defaultTemplate ? 'is-selected' : '' }}">
                    <input type="radio" name="template" value="{{ $key }}"
                           @checked($key === $defaultTemplate) data-template-radio>
                    <span class="template-card__head">
                        <span class="template-card__name">{{ $tpl['name'] }}</span>
                        <span class="template-card__badge">{{ $tpl['cardsPerSheet'] }} thẻ/trang</span>
                    </span>
                    <p class="template-card__desc">{{ $tpl['description'] }}</p>
                </label>
            @endforeach
        </div>

        <div class="print-cards__preview">
            <div class="print-cards__preview-head">
                <strong>Xem trước</strong>
                <span id="printCardsSheetInfo" aria-live="polite"></span>
            </div>
            <iframe id="printCardsFrame" class="print-cards__frame" title="Xem trước mẫu in phiếu tài khoản"></iframe>
        </div>
    </div>

    <div class="print-cards__actions">
        <a class="btn" href="{{ route('admin.students.classes.show', $class) }}">Hủy</a>
        <button type="submit" class="btn btn-primary" id="printCardsSubmit">Tạo &amp; tải file ZIP</button>
    </div>
</form>

@push('scripts')
<script>
(function () {
    const previewBase = @json(route('admin.students.print-cards.preview', $class));
    const cardsPerSheet = @json($cardsPerSheet);
    const totalStudents = {{ $students->count() }};

    const frame = document.getElementById('printCardsFrame');
    const sheetInfo = document.getElementById('printCardsSheetInfo');
    const radios = document.querySelectorAll('[data-template-radio]');
    const form = document.getElementById('printCardsForm');
    const submitBtn = document.getElementById('printCardsSubmit');

    function selectTemplate(key) {
        radios.forEach((r) => r.closest('.template-card').classList.toggle('is-selected', r.value === key));
        frame.src = previewBase + '?template=' + encodeURIComponent(key);

        const perSheet = cardsPerSheet[key] || 1;
        const sheets = Math.ceil(totalStudents / perSheet);
        sheetInfo.textContent = totalStudents + ' học sinh · sẽ tạo ' + sheets + ' file PDF, gộp vào 1 file zip';
    }

    radios.forEach((radio) => radio.addEventListener('change', () => selectTemplate(radio.value)));

    const initial = document.querySelector('[data-template-radio]:checked');
    if (initial) selectTemplate(initial.value);

    form.addEventListener('submit', () => {
        submitBtn.disabled = true;
        submitBtn.textContent = 'Đang tạo file…';
        // Form submit thường (không phải AJAX) nên trình duyệt tự tải file zip;
        // mở lại nút sau vài giây để giáo viên in lại được nếu cần.
        setTimeout(() => {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Tạo & tải file ZIP';
        }, 4000);
    });
})();
</script>
@endpush
@endsection

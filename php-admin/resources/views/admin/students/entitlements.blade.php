@extends('layouts.admin')

@section('title', 'Quyền truy cập — '.$student->display_name)
@section('page-title', 'Quyền truy cập')

@php
    $accessLabels = ['none' => 'Đang khóa', 'free' => 'Dùng thử', 'full' => 'Full (Pro)'];
    $sourceLabels = ['default' => 'mặc định hệ thống', 'class' => 'cấp cho cả lớp', 'student' => 'cấp riêng cho học sinh'];
    $featureIcons = ['elements' => '🧪', 'balance' => '⚖️', 'quiz' => '📝', 'duck_race' => '🦆'];

    $hue = crc32($student->display_name.$student->id) % 360;
    $initials = (function (string $name) {
        $words = preg_split('/\s+/', trim($name)) ?: [];
        $letters = array_map(fn ($w) => mb_substr($w, 0, 1), array_slice($words, -2));
        return mb_strtoupper(implode('', $letters) ?: '?');
    })($student->display_name);

    // Diễn giải scope thành chữ thay vì JSON: dùng label khai báo trong FeatureRegistry.
    $scopeText = function (?array $scope, string $featureKey) use ($features) {
        if (empty($scope)) {
            return null;
        }
        $fields = $features[$featureKey]['scope_fields'] ?? [];
        // Chỉ diễn giải các trường có nhãn khai báo — key kỹ thuật (vd enabled) không đưa ra UI.
        return collect($scope)->filter(fn ($v, $k) => isset($fields[$k]))->map(function ($value, $key) use ($fields) {
            $label = $fields[$key]['label'];
            if ($value === null) {
                $value = 'không giới hạn';
            } elseif (is_array($value)) {
                $value = implode(', ', $value);
            } elseif (is_bool($value)) {
                $value = $value ? 'bật' : 'tắt';
            }
            return $label.': '.$value;
        })->implode(' · ');
    };
@endphp

@section('content')
<div class="stu-hero" style="--hue: {{ $hue }}">
    <span class="stu-hero__tile">{{ $initials }}</span>
    <div class="stu-hero__text">
        <a class="stu-hero__back" href="{{ $student->class_id ? route('admin.students.classes.show', $student->class_id) : route('admin.students.index') }}">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M19 12H5m6 6-6-6 6-6"/></svg>
            {{ $student->studentClass?->name ? 'Lớp '.$student->studentClass->name : 'Các lớp' }}
        </a>
        <h2>{{ $student->display_name }}</h2>
        <p class="stu-hero__meta">
            <code style="font-family:var(--stu-mono)">{{ $student->username }}</code>
            · Quyền dùng các tính năng — cấp mới sẽ đè lên quyền cũ, quyền riêng đè lên quyền lớp
        </p>
    </div>
</div>

{{-- Mỗi tính năng một thẻ: trạng thái hiện tại + form cấp quyền tại chỗ --}}
<div class="ent-grid">
    @foreach ($features as $key => $feature)
        @php
            $entry = $resolved[$key] ?? null;
            $level = $entry['access_level'] ?? 'free';
            $scopeLine = $entry ? $scopeText($entry['scope'], $key) : null;
        @endphp
        <div class="ent-card" style="--i: {{ $loop->index }}">
            <div class="ent-card__head">
                <span class="ent-card__icon" aria-hidden="true">{{ $featureIcons[$key] ?? '🎮' }}</span>
                <div class="ent-card__title">
                    <h3>{{ $feature['name'] }}</h3>
                    <p class="ent-card__desc">{{ $feature['description'] }}</p>
                </div>
                <span class="ent-badge ent-badge--{{ $level }}">{{ $accessLabels[$level] ?? $level }}</span>
            </div>

            @if ($entry)
                <p class="ent-card__meta">
                    Nguồn: <strong>{{ $sourceLabels[$entry['source']] ?? $entry['source'] }}</strong>
                    @if ($entry['expires_at'] !== null)
                        · hết hạn {{ $entry['expires_at']->format('d/m/Y') }}
                        (<strong>còn {{ $entry['days_remaining'] }} ngày</strong>)
                    @elseif ($level === 'full')
                        · <strong>vĩnh viễn</strong>
                    @endif
                </p>
                @if ($scopeLine)
                    <div class="ent-card__scope">{{ $scopeLine }}</div>
                @endif
            @endif

            <details>
                <summary>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 5v14m-7-7h14"/></svg>
                    Cấp quyền mới
                </summary>
                <form method="POST" action="{{ route('admin.students.entitlements.store', $student) }}" class="ent-form">
                    @csrf
                    <input type="hidden" name="feature_key" value="{{ $key }}">

                    <div>
                        <span class="ent-form__label">Mức truy cập</span>
                        <div class="stu-pills stu-pills--danger">
                            <label><input type="radio" name="access_level" value="full" checked><span>Full (Pro)</span></label>
                            <label><input type="radio" name="access_level" value="free"><span>Dùng thử</span></label>
                            <label><input type="radio" name="access_level" value="none"><span>Khóa hẳn</span></label>
                        </div>
                    </div>

                    <div>
                        <span class="ent-form__label">Thời hạn</span>
                        <div class="stu-pills" data-duration>
                            <label><input type="radio" name="duration" value="permanent" checked><span>Vĩnh viễn</span></label>
                            <label><input type="radio" name="duration" value="days"><span>Có thời hạn</span></label>
                            <span class="ent-form__days">
                                <input type="number" name="days" min="1" max="3650" placeholder="7" disabled aria-label="Số ngày">
                                <small style="color:var(--stu-dim)">ngày</small>
                            </span>
                        </div>
                    </div>

                    @if ($feature['scope_fields'] !== [])
                        <details class="ent-advanced">
                            <summary>Tùy chỉnh phạm vi (để trống = theo mức đã chọn)</summary>
                            <div>
                                @foreach ($feature['scope_fields'] as $scopeKey => $field)
                                    <label>
                                        {{ $field['label'] }}
                                        @if ($field['type'] === 'int')
                                            <input type="number" name="scope[{{ $scopeKey }}]"
                                                   @isset($field['min']) min="{{ $field['min'] }}" @endisset>
                                        @else
                                            <select name="scope[{{ $scopeKey }}][]" multiple size="3">
                                                @foreach ($field['options'] ?? [] as $option)
                                                    <option value="{{ $option }}">{{ $option }}</option>
                                                @endforeach
                                            </select>
                                            <small>Giữ Ctrl để chọn nhiều</small>
                                        @endif
                                    </label>
                                @endforeach
                            </div>
                        </details>
                    @endif

                    <div class="ent-form__actions">
                        <button type="submit" class="btn btn-primary btn-sm">Cấp cho học sinh này</button>
                        @if ($student->class_id)
                            <button type="submit" class="btn btn-sm"
                                    formaction="{{ route('admin.students.entitlements.class', $student->class_id) }}">
                                Cấp cho cả lớp {{ $student->studentClass?->name }}
                            </button>
                        @endif
                    </div>
                </form>
            </details>
        </div>
    @endforeach
</div>

<div class="stu-panel">
    <p class="stu-section-title">Lịch sử cấp quyền
        <small>gồm cả quyền cấp cho lớp {{ $student->studentClass?->name ?? '' }} · dòng mờ = đã hết hiệu lực</small>
    </p>
    @if ($grants->isEmpty())
        <div class="stu-empty" style="padding:28px 16px">
            <div class="stu-empty__icon">🔓</div>
            <h3>Chưa cấp quyền nào</h3>
            <p>Học sinh đang dùng mức mặc định của từng tính năng.</p>
        </div>
    @else
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Tính năng</th><th>Mức</th><th>Áp cho</th>
                    <th>Hiệu lực</th><th>Người cấp</th><th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($grants as $grant)
                    @php $grantScope = $scopeText($grant->scope, $grant->feature_key); @endphp
                    <tr @class(['ent-history-row--inactive' => ! $grant->isEffective()])>
                        <td>
                            <strong>{{ \App\Support\FeatureRegistry::label($grant->feature_key) }}</strong>
                            @if ($grantScope)
                                <div style="font-size:.78rem;color:var(--stu-dim)">{{ $grantScope }}</div>
                            @endif
                        </td>
                        <td>
                            <span class="ent-badge ent-badge--{{ $grant->access_level }}">{{ $accessLabels[$grant->access_level] ?? $grant->access_level }}</span>
                        </td>
                        <td>
                            @if ($grant->student_id)
                                <span class="ent-target-chip">Học sinh này</span>
                            @else
                                <span class="ent-target-chip ent-target-chip--class">Cả lớp</span>
                            @endif
                        </td>
                        <td>
                            @if ($grant->revoked_at)
                                Đã thu hồi {{ $grant->revoked_at->format('d/m/Y') }}
                            @elseif ($grant->expires_at)
                                Đến {{ $grant->expires_at->format('d/m/Y') }}
                                @if ($grant->isEffective())
                                    <strong>(còn {{ $grant->daysRemaining() }} ngày)</strong>
                                @else
                                    (đã hết hạn)
                                @endif
                            @else
                                Vĩnh viễn
                            @endif
                        </td>
                        <td>{{ $grant->grantedBy?->name ?? '—' }}</td>
                        <td class="row-actions">
                            @if ($grant->isEffective())
                                <form method="POST" action="{{ route('admin.students.entitlements.revoke', $grant) }}"
                                      onsubmit="return confirm('Thu hồi quyền này?')">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-danger">Thu hồi</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

@push('scripts')
<script>
// Ô "số ngày" chỉ bật khi chọn "Có thời hạn"; chọn lại "Vĩnh viễn" thì xóa và khóa.
document.querySelectorAll('[data-duration]').forEach((group) => {
    const daysInput = group.querySelector('[name="days"]');
    group.querySelectorAll('[name="duration"]').forEach((radio) => {
        radio.addEventListener('change', () => {
            const withDays = group.querySelector('[name="duration"]:checked').value === 'days';
            daysInput.disabled = !withDays;
            if (withDays) daysInput.focus(); else daysInput.value = '';
        });
    });
});
</script>
@endpush
@endsection

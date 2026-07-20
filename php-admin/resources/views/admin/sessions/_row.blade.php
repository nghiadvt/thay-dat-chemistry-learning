{{-- Một hàng phòng chơi. Dùng cả ở bảng phẳng lẫn khi tải dần nội dung nhóm. --}}
@php
    $statusLabels = $statusLabels ?? \App\Support\StatusLabels::SESSION;

    $rowActions = [];
    if ($session->quiz_id) {
        $rowActions[] = ['key' => 'trial', 'label' => 'Chơi thử'];
    }
    if ($session->status !== 'ended') {
        $rowActions[] = ['key' => 'host', 'label' => 'Vào phòng chơi'];
    }
    if ($session->status === 'ended' && $session->is_active) {
        $rowActions[] = ['key' => 'replay', 'label' => 'Chơi lại'];
    }
    if ($session->status !== 'playing') {
        $rowActions[] = ['key' => 'delete', 'label' => 'Xóa phòng', 'danger' => true];
    }
@endphp
<tr class="{{ $session->is_active ? '' : 'row-inactive' }}" data-session-id="{{ $session->id }}">
    <td class="sessions-col-select" data-col="select">
        <input
            type="checkbox"
            class="sessions-row-check"
            value="{{ $session->id }}"
            aria-label="Chọn phòng {{ $session->name ?? $session->pin }}"
            @disabled($session->status === 'playing')
        >
    </td>
    <td data-col="name">
        <span class="session-name-cell">{{ $session->name ?? 'Phòng '.$session->pin }}</span>
    </td>
    <td data-col="group">
        @include('admin.partials.group-chip', [
            'group' => $session->group,
            'link' => $session->group ? route('admin.sessions.index', ['group_id' => $session->group_id]) : null,
        ])
    </td>
    <td data-col="pin">
        <span class="session-pin-badge">{{ $session->pin }}</span>
    </td>
    <td data-col="quiz" title="{{ $session->quiz?->name }}">{{ $session->quiz?->name ?? '—' }}</td>
    <td data-col="game" title="{{ $session->gameName() }}">{{ $session->gameName() ?? '—' }}</td>
    <td data-col="host">{{ $session->host?->name ?? '—' }}</td>
    <td data-col="status">
        <span class="badge badge-{{ $session->status }}">
            {{ $statusLabels[$session->status] ?? $session->status }}
        </span>
    </td>
    <td data-col="active">
        @include('admin.partials.toggle-switch', [
            'formAction' => route('admin.sessions.toggle-active', $session),
            'checked' => $session->is_active,
            'submitOnChange' => true,
            'label' => 'Bật / tắt phòng',
        ])
    </td>
    <td data-col="created" class="session-created-cell">{{ $session->created_at?->format('d/m/Y H:i') }}</td>
    <td data-col="actions" class="actions-cell">
        <div class="row-actions-group">
            <a href="{{ route('admin.sessions.edit', $session) }}" class="btn btn-secondary btn-sm">Chi tiết</a>
            @if (!empty($rowActions))
                @include('admin.partials.row-action-menu', [
                    'menuLabel' => 'Khác',
                    'actions' => $rowActions,
                    'dataAttrs' => [
                        'host-url' => route('admin.sessions.show', $session),
                        'reset-url' => route('admin.sessions.reset', $session),
                        'delete-url' => route('admin.sessions.destroy', $session),
                        'quiz-id' => $session->quiz_id,
                        'quiz-name' => $session->quiz?->name,
                        'session-pin' => $session->pin,
                        'session-name' => $session->name ?? ('Phòng '.$session->pin),
                    ],
                ])
            @endif
        </div>
    </td>
</tr>

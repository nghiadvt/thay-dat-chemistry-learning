@props([
    'name' => null,
    'checked' => false,
    'submitOnChange' => false,
    'formAction' => null,
    'label' => 'Bật / tắt',
])

@if ($formAction)
<form method="POST" action="{{ $formAction }}" class="admin-toggle-form">
    @csrf
    @method('PATCH')
@endif

<label class="admin-toggle" aria-label="{{ $label }}">
    <input
        type="checkbox"
        class="admin-toggle-input"
        @if ($name) name="{{ $name }}" value="1" @endif
        @checked($checked)
        @if ($submitOnChange && $formAction) onchange="this.form.submit()" @endif
    >
    <span class="admin-toggle-track" aria-hidden="true"></span>
</label>

@if ($formAction)
</form>
@endif

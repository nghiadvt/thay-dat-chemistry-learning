@php
    $selectedKeyboardId = $selectedKeyboardId ?? old('keyboard_id');
    $keyboardPreviewMap = $keyboards->mapWithKeys(fn ($keyboard) => [
        (string) $keyboard->id => [
            'name' => $keyboard->name,
            'preview_url' => $keyboard->preview_url,
            'editor_url' => route('admin.keyboards.editor', $keyboard),
        ],
    ]);
@endphp

<div class="form-group keyboard-select-with-preview">
    <label for="keyboard_id">Bàn phím *</label>
    <select id="keyboard_id" name="keyboard_id" required>
        <option value="">— Chọn bàn phím —</option>
        @foreach ($keyboards as $keyboard)
            <option value="{{ $keyboard->id }}" @selected((string) $selectedKeyboardId === (string) $keyboard->id)>{{ $keyboard->name }}</option>
        @endforeach
    </select>
    @error('keyboard_id')<div class="field-error">{{ $message }}</div>@enderror

    <div id="kbSelectPreview" class="kb-select-preview" hidden aria-live="polite"></div>
    <script type="application/json" id="kbSelectPreviewData">@json($keyboardPreviewMap)</script>
</div>

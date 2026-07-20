{{--
    Ô chọn nhóm dùng chung cho form tạo/sửa (mode="form") và panel bộ lọc (mode="filter").
    Ở chế độ form có thêm nút tạo nhanh nhóm mới — tên nhập vào gửi qua `new_group_name`
    và được controller ưu tiên hơn `group_id`.

    Biến: $groups, $selected, $mode, $label, $scope (chỉ dùng cho link quản lý)
--}}
@php
    $mode = $mode ?? 'form';
    $selectId = $id ?? 'group-select-'.uniqid();
    $selectedValue = (string) ($selected ?? '');
    $labelText = $label ?? 'Nhóm';
    $isFilter = $mode === 'filter';
@endphp

<div class="group-select" data-group-select>
    <label for="{{ $selectId }}">{{ $labelText }}</label>
    @if ($isFilter)
        <div class="group-select__control">
            <span class="tag-select-dot" data-group-dot hidden></span>
            <select id="{{ $selectId }}"
                    name="group_id"
                    class="list-filter-control"
                    data-group-select-input>
                <option value="">Tất cả nhóm</option>
                <option value="none" @selected($selectedValue === 'none')>Chưa phân nhóm</option>
                @foreach ($groups as $group)
                    <option value="{{ $group->id }}" data-color="{{ $group->color }}" @selected($selectedValue === (string) $group->id)>{{ $group->name }}</option>
                @endforeach
            </select>
        </div>
    @else
        <select id="{{ $selectId }}"
                name="group_id"
                data-group-select-input>
            <option value="">— Chưa phân nhóm —</option>
            @foreach ($groups as $group)
                <option value="{{ $group->id }}" @selected($selectedValue === (string) $group->id)>{{ $group->name }}</option>
            @endforeach
        </select>
    @endif

    @unless ($isFilter)
        <div class="group-select__new" data-group-new hidden>
            <input type="text"
                   name="new_group_name"
                   maxlength="100"
                   placeholder="Tên nhóm mới…"
                   data-group-new-input
                   disabled>
            <button type="button" class="btn btn-secondary btn-sm" data-group-new-cancel>Hủy</button>
        </div>
        <button type="button" class="group-select__add" data-group-new-toggle>+ Nhóm mới</button>
        @error('new_group_name')<div class="field-error">{{ $message }}</div>@enderror
        @error('group_id')<div class="field-error">{{ $message }}</div>@enderror
    @endunless
</div>

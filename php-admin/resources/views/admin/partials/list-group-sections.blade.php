{{--
    Thân bảng dạng nhóm: mục «Thao tác gần đây» mở sẵn, các nhóm còn lại gập lại
    và chỉ tải nội dung khi người dùng bấm mở (xem public/js/grouped-list.js).

    Biến bắt buộc:
      $sections   list<array{key,label,count,color,text_color}>
      $recent     Collection các record thao tác gần nhất
      $rowView    tên view render 1 hàng, ví dụ 'admin.quizzes._row'
      $rowVar     tên biến truyền vào rowView, ví dụ 'quiz'
      $rowsUrl    endpoint tải dần nội dung nhóm
      $colspan    số cột của bảng
    Tùy chọn:
      $rowExtra   mảng biến phụ truyền thêm cho rowView
      $emptyText  chữ hiện khi nhóm rỗng
--}}
@php
    $rowExtra = $rowExtra ?? [];
    $emptyText = $emptyText ?? 'Nhóm này chưa có mục nào.';
@endphp

@if ($recent->isNotEmpty())
    <tbody class="list-group-section" data-group-section data-section-key="recent">
        <tr class="list-group-head">
            <td colspan="{{ $colspan }}">
                <button type="button" class="list-group-toggle" data-group-toggle aria-expanded="true">
                    <span class="list-group-caret" aria-hidden="true"></span>
                    <span class="list-group-name">Thao tác gần đây</span>
                    <span class="list-group-count">{{ $recent->count() }}</span>
                </button>
            </td>
        </tr>
        @foreach ($recent as $record)
            @include($rowView, array_merge($rowExtra, [$rowVar => $record]))
        @endforeach
    </tbody>
@endif

@foreach ($sections as $section)
    <tbody class="list-group-section is-collapsed"
           data-group-section
           data-section-key="{{ $section['key'] }}"
           data-rows-url="{{ $rowsUrl }}"
           data-total="{{ $section['count'] }}"
           data-loaded="0"
           data-empty-text="{{ $emptyText }}"
           data-colspan="{{ $colspan }}">
        <tr class="list-group-head">
            <td colspan="{{ $colspan }}">
                <button type="button" class="list-group-toggle" data-group-toggle aria-expanded="false">
                    <span class="list-group-caret" aria-hidden="true"></span>
                    @if ($section['color'])
                        <span class="list-group-dot" style="background:{{ $section['color'] }}"></span>
                    @endif
                    <span class="list-group-name">{{ $section['label'] }}</span>
                    <span class="list-group-count">{{ $section['count'] }}</span>
                </button>
            </td>
        </tr>
    </tbody>
@endforeach

@if ($recent->isEmpty() && $sections === [])
    <tbody>
        <tr><td colspan="{{ $colspan }}" class="list-group-empty">Chưa có dữ liệu.</td></tr>
    </tbody>
@endif

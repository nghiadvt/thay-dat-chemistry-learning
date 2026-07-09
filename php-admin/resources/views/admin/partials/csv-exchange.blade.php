@php
    /** @var string $tableId */
    /** @var string $exportUrl */
    /** @var string $templateUrl */
    /** @var string $importUrl */
    /** @var array<string, array<string, mixed>> $registry */
    /** @var array<string, mixed>|null $preserveQuery */
    $preserveQuery = $preserveQuery ?? [];
    $requiredCols = collect($registry)->filter(fn ($col) => ($col['importable'] ?? false) && ($col['required'] ?? false));
    $optionalCols = collect($registry)->filter(fn ($col) => ($col['importable'] ?? false) && ! ($col['required'] ?? false));
@endphp
<div
    class="csv-exchange"
    data-csv-exchange
    data-table-id="{{ $tableId }}"
    data-export-url="{{ $exportUrl }}"
    data-template-url="{{ $templateUrl }}"
    data-import-url="{{ $importUrl }}"
    data-preserve-query='@json($preserveQuery)'
>
    <button
        type="button"
        class="csv-exchange__trigger"
        data-csv-exchange-toggle
        aria-haspopup="menu"
        aria-expanded="false"
    >
        <span>CSV</span>
        <svg viewBox="0 0 20 20" width="16" height="16" aria-hidden="true"><path fill="currentColor" d="M5.5 7.5 10 12l4.5-4.5H5.5z"/></svg>
    </button>
    <div class="csv-exchange__panel" data-csv-exchange-panel hidden>
        <button type="button" class="csv-exchange__action" data-csv-action="export">Xuất CSV</button>
        <button type="button" class="csv-exchange__action" data-csv-action="template">Tải file mẫu</button>
        <button type="button" class="csv-exchange__action" data-csv-action="import">Nhập CSV…</button>
    </div>

    <div class="csv-exchange-modal" data-csv-import-modal hidden aria-hidden="true">
        <div class="csv-exchange-modal__backdrop" data-csv-import-close></div>
        <div class="csv-exchange-modal__dialog" role="dialog" aria-labelledby="csvImportTitle">
            <header class="csv-exchange-modal__header">
                <h3 id="csvImportTitle">Nhập CSV</h3>
                <button type="button" class="csv-exchange-modal__close" data-csv-import-close aria-label="Đóng">×</button>
            </header>
            <form method="POST" action="{{ $importUrl }}" enctype="multipart/form-data" class="csv-exchange-modal__body">
                @csrf
                <p class="csv-exchange-modal__lead">
                    File CSV UTF-8. Chỉ một số cột là bắt buộc — các cột khác có thể bỏ trống hoặc không có trong file.
                    Tải <button type="button" class="csv-exchange-modal__link" data-csv-action="template">file mẫu</button> để xem định dạng.
                </p>

                @if ($requiredCols->isNotEmpty())
                    <div class="csv-exchange-modal__section">
                        <h4>Cột bắt buộc</h4>
                        <ul class="csv-exchange-modal__cols">
                            @foreach ($requiredCols as $key => $col)
                                <li>
                                    <strong>{{ $col['label'] }}</strong>
                                    @if (! empty($col['hint']))
                                        <span class="csv-exchange-modal__hint">{{ $col['hint'] }}</span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if ($optionalCols->isNotEmpty())
                    <div class="csv-exchange-modal__section">
                        <h4>Cột tùy chọn</h4>
                        <ul class="csv-exchange-modal__cols csv-exchange-modal__cols--optional">
                            @foreach ($optionalCols as $key => $col)
                                <li>
                                    <strong>{{ $col['label'] }}</strong>
                                    @if (! empty($col['hint']))
                                        <span class="csv-exchange-modal__hint">{{ $col['hint'] }}</span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="form-group">
                    <label for="csvFileInput">Chọn file CSV</label>
                    <input type="file" id="csvFileInput" name="csv_file" accept=".csv,text/csv,text/plain" required>
                </div>

                <footer class="csv-exchange-modal__footer">
                    <button type="button" class="btn btn-secondary" data-csv-import-close>Hủy</button>
                    <button type="submit" class="btn btn-primary">Nhập</button>
                </footer>
            </form>
        </div>
    </div>
</div>

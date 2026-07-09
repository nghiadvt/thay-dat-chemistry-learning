<?php

namespace App\Support;

final class AdminListCsvRegistry
{
    /**
     * @return array<string, array{label: string, required: bool, exportable: bool, importable: bool, template: bool, hint?: string}>
     */
    public static function quiz(): array
    {
        return [
            'name' => [
                'label' => 'Tên',
                'required' => true,
                'exportable' => true,
                'importable' => true,
                'template' => true,
                'hint' => 'Tên quiz (bắt buộc)',
            ],
            'tags' => [
                'label' => 'Chủ đề',
                'required' => false,
                'exportable' => true,
                'importable' => true,
                'template' => true,
                'hint' => 'Nhiều chủ đề, phân cách dấu phẩy',
            ],
            'game' => [
                'label' => 'Game',
                'required' => true,
                'exportable' => true,
                'importable' => true,
                'template' => true,
                'hint' => 'Tên game có sẵn trong hệ thống (bắt buộc)',
            ],
            'keyboard' => [
                'label' => 'Bàn phím',
                'required' => true,
                'exportable' => true,
                'importable' => true,
                'template' => true,
                'hint' => 'Tên bàn phím có sẵn (bắt buộc)',
            ],
            'grade' => [
                'label' => 'Lớp',
                'required' => false,
                'exportable' => true,
                'importable' => true,
                'template' => true,
                'hint' => 'VD: 10, 11',
            ],
            'questions' => [
                'label' => 'Câu hỏi',
                'required' => false,
                'exportable' => true,
                'importable' => false,
                'template' => false,
                'hint' => 'Chỉ xuất — số câu hỏi trong quiz',
            ],
            'active' => [
                'label' => 'Bật',
                'required' => false,
                'exportable' => true,
                'importable' => true,
                'template' => true,
                'hint' => '1/0 hoặc Có/Không (mặc định Có)',
            ],
        ];
    }

    /**
     * @return array<string, array{label: string, required: bool, exportable: bool, importable: bool, template: bool, hint?: string}>
     */
    public static function questionBank(): array
    {
        return [
            'type' => [
                'label' => 'Loại',
                'required' => true,
                'exportable' => true,
                'importable' => true,
                'template' => true,
                'hint' => 'mc, essay, structured hoặc Trắc nghiệm/Tự luận/Phương trình (bắt buộc)',
            ],
            'tags' => [
                'label' => 'Chủ đề',
                'required' => false,
                'exportable' => true,
                'importable' => true,
                'template' => true,
                'hint' => 'Nhiều chủ đề, phân cách dấu phẩy',
            ],
            'content' => [
                'label' => 'Nội dung',
                'required' => true,
                'exportable' => true,
                'importable' => true,
                'template' => true,
                'hint' => 'Đề câu hỏi — text hoặc HTML (bắt buộc)',
            ],
            'points' => [
                'label' => 'Điểm',
                'required' => false,
                'exportable' => true,
                'importable' => true,
                'template' => true,
                'hint' => '1–100 (mặc định 1)',
            ],
            'time' => [
                'label' => 'Thời gian',
                'required' => false,
                'exportable' => true,
                'importable' => true,
                'template' => true,
                'hint' => 'Giây (mặc định 30)',
            ],
            'options' => [
                'label' => 'Đáp án MC',
                'required' => false,
                'exportable' => false,
                'importable' => true,
                'template' => true,
                'hint' => 'Chỉ cần khi Loại=mc — phân cách | (VD: A|B|C|D)',
            ],
            'correct_index' => [
                'label' => 'Đáp án đúng MC',
                'required' => false,
                'exportable' => false,
                'importable' => true,
                'template' => true,
                'hint' => 'Chỉ cần khi Loại=mc — index 0-based (VD: 0 = đáp án đầu)',
            ],
            'correct_answer' => [
                'label' => 'Đáp án mẫu',
                'required' => false,
                'exportable' => false,
                'importable' => true,
                'template' => true,
                'hint' => 'Chỉ cần khi Loại=essay',
            ],
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $registry
     * @return list<string>
     */
    public static function requiredImportKeys(array $registry): array
    {
        return collect($registry)
            ->filter(fn (array $col) => $col['importable'] && $col['required'])
            ->keys()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, array<string, mixed>>  $registry
     * @return list<string>
     */
    public static function defaultExportKeys(array $registry): array
    {
        return collect($registry)
            ->filter(fn (array $col) => $col['exportable'])
            ->keys()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, array<string, mixed>>  $registry
     * @return list<string>
     */
    public static function templateKeys(array $registry, array $visibleColumns): array
    {
        $visible = array_values(array_filter($visibleColumns, fn ($key) => isset($registry[$key])));

        $extra = collect($registry)
            ->filter(fn (array $col) => $col['template'] && ! $col['exportable'] && $col['importable'])
            ->keys();

        return array_values(array_unique([...$visible, ...$extra->all()]));
    }

    /**
     * @param  array<string, array<string, mixed>>  $registry
     * @return array<string, string>
     */
    public static function headerLabels(array $registry, array $columnKeys): array
    {
        $headers = [];
        foreach ($columnKeys as $key) {
            if (isset($registry[$key])) {
                $headers[$key] = $registry[$key]['label'];
            }
        }

        return $headers;
    }

    /**
     * @param  array<string, array<string, mixed>>  $registry
     * @param  list<string>  $rawHeaders
     * @return array{keys: list<string>, missing_required: list<string>}
     */
    public static function mapImportHeaders(array $registry, array $rawHeaders): array
    {
        $lookup = [];
        foreach ($registry as $key => $col) {
            if (! $col['importable']) {
                continue;
            }
            $lookup[mb_strtolower($key)] = $key;
            $lookup[mb_strtolower($col['label'])] = $key;
        }

        $keys = [];
        foreach ($rawHeaders as $header) {
            $normalized = mb_strtolower(trim($header));
            if ($normalized === '') {
                continue;
            }
            if (isset($lookup[$normalized])) {
                $keys[] = $lookup[$normalized];
            }
        }

        $keys = array_values(array_unique($keys));
        $missing = array_values(array_filter(
            self::requiredImportKeys($registry),
            fn (string $required) => ! in_array($required, $keys, true),
        ));

        return ['keys' => $keys, 'missing_required' => $missing];
    }
}

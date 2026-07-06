<?php

namespace App\Services;

class KeyboardValidator
{
    private const MAX_UNITS = 10;

    private const DEFAULT_SMART_CONTEXT = [
        'after_element' => 'subscript',
        'after_plus' => 'coefficient',
    ];

    /**
     * Normalize config: merge defaults, strip editor-only fields.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public function normalizeConfig(array $config): array
    {
        $defaults = [
            'keySize' => 'M',
            'fontSize' => 'M',
            'textColor' => '#000000',
            'background' => '#FFFFFF',
            'border' => '#D0D0D0',
        ];

        $normalized = [
            'schema_version' => $config['schema_version'] ?? 1,
            'defaults' => array_merge($defaults, $config['defaults'] ?? []),
            'rows' => $config['rows'] ?? [],
            'smart_context' => array_merge(
                self::DEFAULT_SMART_CONTEXT,
                $config['smart_context'] ?? []
            ),
        ];

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return list<string>
     */
    public function validate(array $config): array
    {
        $config = $this->normalizeConfig($config);
        $issues = [];
        $rows = $config['rows'] ?? [];
        $hasDelete = false;
        $hasSpace = false;
        $hasSend = false;
        $lastIndex = count($rows) - 1;

        foreach ($rows as $index => $row) {
            if (! empty($row['hidden'])) {
                continue;
            }

            $units = $this->rowUnits($row);
            $rowName = $row['name'] ?? "Hàng #{$index}";

            if ($units > self::MAX_UNITS) {
                $issues[] = "Hàng \"{$rowName}\" vượt ".self::MAX_UNITS." units ({$units})";
            }

            $keys = $row['keys'] ?? [];
            if (count($keys) === 0 && empty($row['isSpaceRow'])) {
                $issues[] = "Hàng \"{$rowName}\" đang trống";
            }

            foreach ($keys as $key) {
                $type = $key['type'] ?? 'normal';
                if ($type === 'delete') {
                    $hasDelete = true;
                }
                if ($type === 'space') {
                    $hasSpace = true;
                }
                if ($type === 'send') {
                    $hasSend = true;
                }
                if ($type === 'normal' && empty($key['text'])) {
                    $issues[] = "Phím trống ở hàng \"{$rowName}\"";
                }
            }

            if ($index < $lastIndex && ! empty($row['isSpaceRow'])) {
                $issues[] = 'Hàng Space phải ở cuối';
            }
        }

        if (! $hasDelete) {
            $issues[] = 'Thiếu phím Delete';
        }
        if (! $hasSpace) {
            $issues[] = 'Thiếu phím Space';
        }
        if (! $hasSend) {
            $issues[] = 'Thiếu phím Send';
        }

        return $issues;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function rowUnits(array $row): int
    {
        $units = 0;
        foreach ($row['keys'] ?? [] as $key) {
            $units += (int) ($key['width'] ?? 1);
        }

        return $units;
    }
}

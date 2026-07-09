<?php

namespace App\Http\Controllers\Admin\Concerns;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

trait HandlesAdminCsv
{
    /**
     * @return list<string>|null
     */
    protected function csvColumnKeys(Request $request): ?array
    {
        $columns = $request->input('columns', []);
        if (is_string($columns)) {
            $columns = array_values(array_filter(array_map('trim', explode(',', $columns))));
        }

        return is_array($columns) && $columns !== [] ? $columns : null;
    }

    /**
     * @param  array{imported: int, errors: list<string>}  $result
     */
    protected function csvImportResponse(array $result, string $route, string $label): RedirectResponse
    {
        if ($result['imported'] === 0 && $result['errors'] !== []) {
            return redirect()->route($route)->with('error', $result['errors'][0]);
        }

        $message = "Đã import {$result['imported']} {$label}.";
        if ($result['errors'] !== []) {
            $preview = implode(' · ', array_slice($result['errors'], 0, 3));
            if (count($result['errors']) > 3) {
                $preview .= ' · …';
            }

            return redirect()->route($route)->with('warning', $message.' Một số dòng lỗi: '.$preview);
        }

        return redirect()->route($route)->with('success', $message);
    }
}

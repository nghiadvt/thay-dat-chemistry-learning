<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PracticeGrade;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PracticeGradeController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);

        $sortOrder = (int) PracticeGrade::query()->max('sort_order') + 1;

        PracticeGrade::create([
            'name' => $validated['name'],
            'slug' => PracticeGrade::uniqueSlug($validated['name']),
            'sort_order' => $sortOrder,
        ]);

        return back()->with('success', 'Đã thêm khối.');
    }

    public function update(Request $request, PracticeGrade $practiceGrade): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);

        $practiceGrade->update([
            'name' => $validated['name'],
            'slug' => PracticeGrade::uniqueSlug($validated['name'], $practiceGrade),
        ]);

        return back()->with('success', 'Đã cập nhật khối.');
    }

    public function destroy(PracticeGrade $practiceGrade): RedirectResponse
    {
        if ($practiceGrade->topics()->exists()) {
            return back()->with('error', 'Khối vẫn còn chủ đề. Hãy xóa hoặc chuyển chủ đề trước khi xóa khối.');
        }

        $practiceGrade->delete();

        return back()->with('success', 'Đã xóa khối.');
    }
}

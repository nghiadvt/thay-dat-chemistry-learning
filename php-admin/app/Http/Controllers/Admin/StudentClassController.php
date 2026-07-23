<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StudentClass;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StudentClassController extends Controller
{
    /**
     * Trang danh sách lớp giờ chính là trang "Học sinh" — giữ route cũ
     * để link/bookmark cũ không chết.
     */
    public function index(): RedirectResponse
    {
        return redirect()->route('admin.students.index');
    }

    /**
     * Trang lớp: mọi thao tác với học sinh của một lớp gom về đây.
     */
    public function show(Request $request, StudentClass $class): View
    {
        $this->assertOwned($request, $class);

        $students = $class->students()
            ->with('latestLockLog')
            ->orderBy('display_name')
            ->orderBy('username')
            ->get();

        return view('admin.students.class-show', compact('class', 'students'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'grade' => ['nullable', 'string', 'max:20'],
        ]);

        $class = StudentClass::create($validated + ['teacher_id' => $request->user()->id]);

        // Đưa thẳng vào trang lớp mới để thêm học sinh luôn.
        return redirect()->route('admin.students.classes.show', $class)
            ->with('success', 'Đã tạo lớp '.$class->name.'. Thêm học sinh cho lớp ở đây.');
    }

    public function update(Request $request, StudentClass $class): RedirectResponse
    {
        $this->assertOwned($request, $class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'grade' => ['nullable', 'string', 'max:20'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $class->update($validated);

        return back()->with('success', 'Đã cập nhật lớp.');
    }

    public function toggleActive(Request $request, StudentClass $class): RedirectResponse
    {
        $this->assertOwned($request, $class);

        $class->update(['is_active' => ! $class->is_active]);

        return back()->with('success', $class->is_active ? 'Đã bật hoạt động cho lớp.' : 'Đã tắt hoạt động của lớp.');
    }

    public function destroy(Request $request, StudentClass $class): RedirectResponse
    {
        $this->assertOwned($request, $class);

        if ($class->students()->exists()) {
            return back()->with('error', 'Lớp vẫn còn học sinh. Hãy chuyển lớp cho học sinh trước khi xóa.');
        }

        $class->delete();

        return redirect()->route('admin.students.index')->with('success', 'Đã xóa lớp.');
    }

    private function assertOwned(Request $request, StudentClass $class): void
    {
        abort_unless(
            $request->user()->isAdmin() || $class->teacher_id === $request->user()->id,
            403,
            'Bạn không quản lý lớp này.'
        );
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PracticeGrade;
use App\Models\PracticeTopic;
use App\Models\StudentClass;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PracticeTopicController extends Controller
{
    public function index(Request $request): View
    {
        $grades = PracticeGrade::query()->ordered()->get();

        $activeGrade = $grades->firstWhere('id', (int) $request->query('grade')) ?? $grades->first();

        $topics = $activeGrade
            ? PracticeTopic::query()
                ->where('practice_grade_id', $activeGrade->id)
                ->ordered()
                ->with(['quizzes' => fn ($q) => $q->withCount('questionBankItems')->with('studentClasses')])
                ->get()
            : collect();

        return view('admin.practice.index', [
            'grades' => $grades,
            'activeGrade' => $activeGrade,
            'topics' => $topics,
            'classes' => StudentClass::query()->orderBy('name')->get(),
            'openTopicId' => (int) $request->query('open_topic'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateTopic($request);

        $sortOrder = (int) PracticeTopic::query()
            ->where('practice_grade_id', $validated['practice_grade_id'])
            ->max('sort_order') + 1;

        $topic = PracticeTopic::create([
            'practice_grade_id' => $validated['practice_grade_id'],
            'name' => $validated['name'],
            'slug' => PracticeTopic::uniqueSlug($validated['name'], $validated['practice_grade_id']),
            'sort_order' => $sortOrder,
        ]);

        return $this->backToTopic($topic, 'Đã tạo chủ đề «'.$topic->name.'». Thêm bài trắc nghiệm bên dưới.');
    }

    public function update(Request $request, PracticeTopic $practiceTopic): RedirectResponse
    {
        $validated = $this->validateTopic($request);

        $practiceTopic->update([
            'practice_grade_id' => $validated['practice_grade_id'],
            'name' => $validated['name'],
            'slug' => PracticeTopic::uniqueSlug($validated['name'], $validated['practice_grade_id'], $practiceTopic),
        ]);

        return $this->backToTopic($practiceTopic, 'Đã cập nhật chủ đề.');
    }

    public function destroy(PracticeTopic $practiceTopic): RedirectResponse
    {
        if ($practiceTopic->quizzes()->exists()) {
            return back()->with('error', 'Chủ đề vẫn còn bài trắc nghiệm. Hãy xóa các bài trước khi xóa chủ đề.');
        }

        $gradeId = $practiceTopic->practice_grade_id;
        $practiceTopic->delete();

        return redirect()->route('admin.practice.index', ['grade' => $gradeId])
            ->with('success', 'Đã xóa chủ đề.');
    }

    public function applyClass(Request $request, PracticeTopic $practiceTopic): RedirectResponse
    {
        $validated = $request->validate([
            'class_id' => ['required', 'integer', 'exists:student_classes,id'],
        ]);

        $classId = (int) $validated['class_id'];
        $quizzes = $practiceTopic->quizzes()->get();

        foreach ($quizzes as $quiz) {
            $quiz->studentClasses()->syncWithoutDetaching([$classId]);
        }

        $class = StudentClass::find($classId);

        return $this->backToTopic(
            $practiceTopic,
            "Đã thêm lớp «{$class?->name}» vào {$quizzes->count()} bài trắc nghiệm."
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function validateTopic(Request $request): array
    {
        return $request->validate([
            'practice_grade_id' => ['required', 'integer', 'exists:practice_grades,id'],
            'name' => ['required', 'string', 'max:150'],
        ]);
    }

    /**
     * Quay về trang danh sách với đúng khối đang xem và chủ đề vẫn mở,
     * để các thao tác chỉnh sửa nhanh trong accordion không bị mất ngữ cảnh.
     */
    private function backToTopic(PracticeTopic $topic, string $message): RedirectResponse
    {
        return redirect()->route('admin.practice.index', [
            'grade' => $topic->practice_grade_id,
            'open_topic' => $topic->id,
        ])->with('success', $message);
    }
}

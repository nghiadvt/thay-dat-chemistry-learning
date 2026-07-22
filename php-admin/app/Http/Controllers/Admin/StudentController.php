<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\StudentClass;
use App\Services\StudentCredentials;
use App\Services\StudentPasswordService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StudentController extends Controller
{
    public function __construct(
        private StudentCredentials $credentials,
        private StudentPasswordService $passwords,
    ) {}

    /**
     * Trang chính "Học sinh": chỉ hiển thị danh sách lớp.
     * Mọi thao tác với học sinh nằm trong trang của từng lớp.
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        $classes = StudentClass::query()
            ->visibleTo($user)
            ->withCount('students')
            ->orderBy('name')
            ->get();

        // Tìm nhanh một học sinh khi không nhớ em đó ở lớp nào.
        $search = trim((string) $request->input('q', ''));
        $results = null;
        if ($search !== '') {
            $results = Student::query()
                ->visibleTo($user)
                ->with('studentClass')
                ->where(function ($builder) use ($search) {
                    $builder->where('display_name', 'like', '%'.$search.'%')
                        ->orWhere('username', 'like', '%'.$search.'%')
                        ->orWhere('student_code', 'like', '%'.$search.'%');
                })
                ->orderBy('display_name')
                ->limit(50)
                ->get();
        }

        $unassigned = Student::query()
            ->visibleTo($user)
            ->whereNull('class_id')
            ->orderBy('display_name')
            ->get();

        return view('admin.students.index', compact('classes', 'search', 'results', 'unassigned'));
    }

    public function create(Request $request): View
    {
        $classes = StudentClass::query()->visibleTo($request->user())->orderBy('name')->get();

        return view('admin.students.create', compact('classes'));
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'display_name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:190'],
            'username' => ['required', 'string', 'max:64', 'alpha_dash', Rule::unique('students', 'username')],
            'class_id' => ['nullable', Rule::exists('student_classes', 'id')],
            'password' => ['nullable', 'string', 'min:6', 'max:64'],
        ]);

        $this->assertClassOwned($request, $validated['class_id'] ?? null);

        $plain = ($validated['password'] ?? null) ?: $this->credentials->generatePassword();

        $student = DB::transaction(function () use ($validated, $user) {
            return Student::create([
                'teacher_id' => $user->id,
                'class_id' => $validated['class_id'] ?? null,
                'student_code' => $this->credentials->generateStudentCode(),
                'username' => $validated['username'],
                'display_name' => $validated['display_name'],
                'email' => $validated['email'] ?? null,
                // Mật khẩu thật được đặt ngay sau bằng StudentPasswordService để
                // hash và bản mã luôn khớp nhau; giá trị này chỉ là chỗ giữ chỗ.
                'password' => Str::random(32),
            ]);
        });

        $this->passwords->apply($student, $plain, $user, 'apply', $request->ip());

        // Có lớp thì quay về trang lớp, không thì về danh sách lớp.
        $destination = $student->class_id
            ? route('admin.students.classes.show', $student->class_id)
            : route('admin.students.index');

        return redirect($destination)
            ->with('success', 'Đã tạo học sinh.')
            ->with('generated_credentials', [
                ['username' => $student->username, 'code' => $student->student_code, 'password' => $plain],
            ]);
    }

    public function edit(Request $request, Student $student): View
    {
        $this->assertOwned($request, $student);

        $classes = StudentClass::query()->visibleTo($request->user())->orderBy('name')->get();

        return view('admin.students.edit', compact('student', 'classes'));
    }

    /**
     * Lưu ý: student_code KHÔNG nằm trong danh sách sửa được — đổi nó sẽ làm
     * bản mã mật khẩu đã lưu không giải mã được nữa.
     */
    public function update(Request $request, Student $student): RedirectResponse
    {
        $this->assertOwned($request, $student);

        $validated = $request->validate([
            'display_name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:190'],
            'username' => ['required', 'string', 'max:64', 'alpha_dash', Rule::unique('students', 'username')->ignore($student->id)],
            'class_id' => ['nullable', Rule::exists('student_classes', 'id')],
            'status' => ['required', Rule::in(['active', 'locked', 'disabled'])],
        ]);

        $this->assertClassOwned($request, $validated['class_id'] ?? null);

        // Mở khóa thì phải xóa bộ đếm sai, nếu không học sinh sẽ bị khóa lại ngay.
        if ($validated['status'] === 'active' && $student->status !== 'active') {
            $student->failed_attempts = 0;
            $student->locked_at = null;
        }

        $student->fill($validated)->save();

        $destination = $student->class_id
            ? route('admin.students.classes.show', $student->class_id)
            : route('admin.students.index');

        return redirect($destination)->with('success', 'Đã cập nhật học sinh.');
    }

    public function destroy(Request $request, Student $student): RedirectResponse
    {
        $this->assertOwned($request, $student);

        // Xóa mềm để giữ lịch sử thống kê của học sinh.
        $student->delete();

        $destination = $student->class_id
            ? route('admin.students.classes.show', $student->class_id)
            : route('admin.students.index');

        return redirect($destination)->with('success', 'Đã xóa học sinh (có thể khôi phục).');
    }

    /**
     * Tạo hàng loạt tài khoản cho một lớp.
     *
     * Hai cách dùng: dán danh sách tên (mỗi dòng một học sinh) hoặc chỉ nhập
     * số lượng. Có danh sách tên thì số lượng bị bỏ qua.
     */
    public function bulkGenerate(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'class_id' => ['required', Rule::exists('student_classes', 'id')],
            'names' => ['nullable', 'string', 'max:10000'],
            'quantity' => ['required_without:names', 'nullable', 'integer', 'min:1', 'max:60'],
            'username_prefix' => ['nullable', 'string', 'max:32', 'alpha_dash'],
            'display_prefix' => ['nullable', 'string', 'max:60'],
        ]);

        $class = StudentClass::query()->visibleTo($user)->findOrFail($validated['class_id']);

        $names = collect(preg_split('/\r\n|\r|\n/', (string) ($validated['names'] ?? '')))
            ->map(fn (string $line) => trim($line))
            ->filter()
            ->map(fn (string $name) => Str::limit($name, 100, ''))
            ->values();

        if ($names->count() > 60) {
            return back()->with('error', 'Mỗi lần chỉ tạo được tối đa 60 học sinh. Hãy chia danh sách thành nhiều lần.');
        }

        $quantity = $names->isNotEmpty() ? $names->count() : (int) $validated['quantity'];

        $usernamePrefix = ($validated['username_prefix'] ?? null) ?: (Str::slug($class->name) ?: 'hs');
        $displayPrefix = ($validated['display_prefix'] ?? null) ?: $class->name;

        $startIndex = Student::withTrashed()->where('class_id', $class->id)->count() + 1;
        $created = [];

        DB::transaction(function () use ($quantity, $names, $class, $user, $usernamePrefix, $displayPrefix, $startIndex, &$created, $request) {
            for ($i = 0; $i < $quantity; $i++) {
                $number = $startIndex + $i;

                $student = Student::create([
                    'teacher_id' => $user->id,
                    'class_id' => $class->id,
                    'student_code' => $this->credentials->generateStudentCode(),
                    'username' => $this->credentials->generateUsername($usernamePrefix, $number),
                    'display_name' => $names->get($i) ?? $displayPrefix.' - '.$number,
                    'password' => Str::random(32),
                ]);

                $plain = $this->credentials->generatePassword();
                $this->passwords->apply($student, $plain, $user, 'apply', $request->ip());

                $created[] = [
                    'display_name' => $student->display_name,
                    'username' => $student->username,
                    'code' => $student->student_code,
                    'password' => $plain,
                ];
            }
        });

        return redirect()->route('admin.students.classes.show', $class)
            ->with('success', 'Đã tạo '.count($created).' tài khoản học sinh.')
            ->with('generated_credentials', $created);
    }

    public function resetPassword(Request $request, Student $student): RedirectResponse
    {
        $this->assertOwned($request, $student);

        $plain = $this->passwords->reset($student, $request->user(), $request->ip());

        return back()
            ->with('success', 'Đã đặt lại mật khẩu.')
            ->with('generated_credentials', [
                ['username' => $student->username, 'code' => $student->student_code, 'password' => $plain],
            ]);
    }

    public function unlock(Request $request, Student $student): RedirectResponse
    {
        $this->assertOwned($request, $student);

        $student->forceFill([
            'status' => 'active',
            'failed_attempts' => 0,
            'locked_at' => null,
        ])->save();

        return back()->with('success', 'Đã mở khóa tài khoản học sinh.');
    }

    /**
     * Phiếu tài khoản để in và phát cho học sinh.
     */
    public function credentialsSheet(Request $request, StudentClass $class): View
    {
        $this->assertClassOwned($request, $class->id);

        $students = $class->students()->orderBy('username')->get()
            ->map(function (Student $student) use ($request) {
                return [
                    'student' => $student,
                    'password' => rescue(
                        fn () => $this->passwords->reveal($student, $request->user(), $request->ip()),
                        '(không đọc được)',
                        false
                    ),
                ];
            });

        return view('admin.students.credentials-sheet', compact('class', 'students'));
    }

    private function assertOwned(Request $request, Student $student): void
    {
        abort_unless(
            $request->user()->isAdmin() || $student->teacher_id === $request->user()->id,
            403,
            'Bạn không quản lý học sinh này.'
        );
    }

    private function assertClassOwned(Request $request, int|string|null $classId): void
    {
        if ($classId === null) {
            return;
        }

        abort_unless(
            StudentClass::query()->visibleTo($request->user())->whereKey($classId)->exists(),
            403,
            'Bạn không quản lý lớp này.'
        );
    }
}

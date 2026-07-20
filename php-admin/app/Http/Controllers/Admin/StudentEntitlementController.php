<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\StudentClass;
use App\Models\StudentEntitlement;
use App\Services\EntitlementResolver;
use App\Support\FeatureRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StudentEntitlementController extends Controller
{
    public function __construct(
        private EntitlementResolver $resolver,
    ) {}

    public function index(Request $request, Student $student): View
    {
        $this->assertOwned($request, $student);

        $resolved = $this->resolver->all($student);

        $grants = StudentEntitlement::query()
            ->with('grantedBy:id,name')
            ->where(function ($query) use ($student) {
                $query->where('student_id', $student->id);
                if ($student->class_id !== null) {
                    $query->orWhere('class_id', $student->class_id);
                }
            })
            ->latest('id')
            ->get();

        $features = FeatureRegistry::all();

        return view('admin.students.entitlements', compact('student', 'resolved', 'grants', 'features'));
    }

    public function store(Request $request, Student $student): RedirectResponse
    {
        $this->assertOwned($request, $student);

        $validated = $this->validateGrant($request);

        StudentEntitlement::create($validated + [
            'student_id' => $student->id,
            'granted_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Đã cấp quyền cho học sinh.');
    }

    /**
     * Cấp một lần cho cả lớp — chỉ tạo 1 dòng, học sinh mới vào lớp sau đó cũng
     * được hưởng ngay mà không cần cấp lại.
     */
    public function storeForClass(Request $request, StudentClass $class): RedirectResponse
    {
        abort_unless(
            $request->user()->isAdmin() || $class->teacher_id === $request->user()->id,
            403,
            'Bạn không quản lý lớp này.'
        );

        $validated = $this->validateGrant($request);

        StudentEntitlement::create($validated + [
            'class_id' => $class->id,
            'granted_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Đã cấp quyền cho cả lớp '.$class->name.'.');
    }

    public function revoke(Request $request, StudentEntitlement $entitlement): RedirectResponse
    {
        $this->assertGrantOwned($request, $entitlement);

        $entitlement->forceFill(['revoked_at' => now()])->save();

        return back()->with('success', 'Đã thu hồi quyền.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateGrant(Request $request): array
    {
        $validated = $request->validate([
            'feature_key' => ['required', Rule::in(FeatureRegistry::keys())],
            'access_level' => ['required', Rule::in([
                FeatureRegistry::ACCESS_NONE,
                FeatureRegistry::ACCESS_FREE,
                FeatureRegistry::ACCESS_FULL,
            ])],
            'duration' => ['required', Rule::in(['permanent', 'days'])],
            'days' => ['nullable', 'integer', 'min:1', 'max:3650', 'required_if:duration,days'],
            'scope' => ['nullable', 'array'],
        ]);

        return [
            'feature_key' => $validated['feature_key'],
            'access_level' => $validated['access_level'],
            'scope' => $this->normalizeScope($validated['scope'] ?? []),
            'starts_at' => now(),
            'expires_at' => $validated['duration'] === 'days'
                ? now()->addDays((int) $validated['days'])
                : null,
        ];
    }

    /**
     * Bỏ các ô để trống để chúng không ghi đè phạm vi mặc định bằng chuỗi rỗng.
     *
     * @param  array<string, mixed>  $scope
     * @return array<string, mixed>|null
     */
    private function normalizeScope(array $scope): ?array
    {
        $clean = [];

        foreach ($scope as $key => $value) {
            if ($value === '' || $value === null || $value === []) {
                continue;
            }

            $clean[$key] = is_numeric($value) ? (int) $value : $value;
        }

        return $clean === [] ? null : $clean;
    }

    private function assertOwned(Request $request, Student $student): void
    {
        abort_unless(
            $request->user()->isAdmin() || $student->teacher_id === $request->user()->id,
            403,
            'Bạn không quản lý học sinh này.'
        );
    }

    private function assertGrantOwned(Request $request, StudentEntitlement $entitlement): void
    {
        if ($request->user()->isAdmin()) {
            return;
        }

        $ownerId = $entitlement->student_id !== null
            ? Student::withTrashed()->whereKey($entitlement->student_id)->value('teacher_id')
            : StudentClass::withTrashed()->whereKey($entitlement->class_id)->value('teacher_id');

        abort_unless($ownerId === $request->user()->id, 403, 'Bạn không quản lý quyền này.');
    }
}

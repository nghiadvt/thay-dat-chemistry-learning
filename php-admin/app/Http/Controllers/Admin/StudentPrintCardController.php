<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\StudentClass;
use App\Services\StudentPasswordService;
use App\Support\PrintCardTemplates;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

/**
 * "In phiếu tài khoản" theo mẫu dựng sẵn.
 *
 * Luồng: chọn lớp → chọn 1 trong các mẫu ở PrintCardTemplates (xem trước
 * ngay trên trang) → bấm tạo file thì mỗi trang (4/6/8 thẻ tuỳ mẫu) được
 * render thành 1 file PDF riêng, gộp tất cả vào 1 file zip để tải về —
 * kể cả khi lớp chỉ đủ một trang, vẫn tải zip cho nhất quán.
 */
class StudentPrintCardController extends Controller
{
    public function __construct(private StudentPasswordService $passwords) {}

    public function index(Request $request, StudentClass $class): View|RedirectResponse
    {
        $this->assertOwned($request, $class);

        $students = $class->students()->orderBy('username')->get();

        if ($students->isEmpty()) {
            return redirect()->route('admin.students.classes.show', $class)
                ->with('error', 'Lớp chưa có học sinh để in phiếu.');
        }

        $templates = PrintCardTemplates::all();
        $defaultTemplate = array_key_first($templates);

        return view('admin.students.print-cards.index', compact('class', 'students', 'templates', 'defaultTemplate'));
    }

    /**
     * Xem trước 1 trang của mẫu đã chọn, dùng đúng học sinh thật đầu danh
     * sách — để giáo viên thấy được layout thật trước khi tải cả lớp.
     */
    public function preview(Request $request, StudentClass $class): Response
    {
        $this->assertOwned($request, $class);

        $template = $this->resolveTemplate($request);

        $students = $class->students()->orderBy('username')->take($template['cardsPerSheet'])->get();

        $html = view('admin.students.print-cards.sheet', [
            'template' => $template,
            'class' => $class,
            'rows' => $this->revealRows($students, $request),
        ])->render();

        return response($html)->header('Content-Type', 'text/html; charset=UTF-8');
    }

    /**
     * Tạo PDF cho từng trang (mỗi trang = cardsPerSheet thẻ) rồi nén hết
     * vào 1 file zip để tải về.
     */
    public function export(Request $request, StudentClass $class): BinaryFileResponse
    {
        $this->assertOwned($request, $class);

        $template = $this->resolveTemplate($request);

        $students = $class->students()->orderBy('username')->get();

        abort_if($students->isEmpty(), 422, 'Lớp chưa có học sinh để in phiếu.');

        $rows = $this->revealRows($students, $request);

        $workDir = storage_path('app/private/tmp/print-cards/'.Str::uuid());
        File::ensureDirectoryExists($workDir);

        $zipName = 'phieu-tai-khoan-'.Str::slug($class->name).'-'.now()->format('Ymd-His').'.zip';
        $zipPath = storage_path('app/private/tmp/print-cards/'.Str::uuid().'.zip');

        try {
            $sheetNumber = 0;
            $pdfPaths = [];

            foreach ($rows->chunk($template['cardsPerSheet']) as $chunk) {
                $sheetNumber++;

                $html = view('admin.students.print-cards.sheet', [
                    'template' => $template,
                    'class' => $class,
                    'rows' => $chunk,
                ])->render();

                $pdfPath = $workDir.'/'.sprintf('phieu-%02d.pdf', $sheetNumber);
                Pdf::loadHTML($html)->setPaper('a4', 'portrait')->save($pdfPath);
                $pdfPaths[] = $pdfPath;
            }

            $zip = new ZipArchive();
            abort_unless($zip->open($zipPath, ZipArchive::CREATE) === true, 500, 'Không tạo được file zip.');
            foreach ($pdfPaths as $pdfPath) {
                $zip->addFile($pdfPath, basename($pdfPath));
            }
            $zip->close();
        } finally {
            File::deleteDirectory($workDir);
        }

        return response()->download($zipPath, $zipName)->deleteFileAfterSend(true);
    }

    /**
     * Ghép danh sách học sinh với mật khẩu đọc được (giải mã) để đưa vào
     * mẫu in. Học sinh nào giải mã lỗi thì hiện dòng báo lỗi thay vì chặn
     * cả lượt in.
     */
    private function revealRows(Collection $students, Request $request): Collection
    {
        return $students->map(function (Student $student) use ($request) {
            return [
                'student' => $student,
                'password' => rescue(
                    fn () => $this->passwords->reveal($student, $request->user(), $request->ip()),
                    '(không đọc được)',
                    false
                ),
            ];
        });
    }

    private function resolveTemplate(Request $request): array
    {
        $validated = $request->validate([
            'template' => ['required', 'string', Rule::in(PrintCardTemplates::keys())],
        ]);

        return PrintCardTemplates::find($validated['template']);
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

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CardTemplate;
use App\Models\Student;
use App\Models\StudentClass;
use App\Services\CardTemplateStorageService;
use App\Services\StudentPasswordService;
use App\Support\CardFonts;
use App\Support\CardTemplateA4Layout;
use App\Support\PrintCardTemplates;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

/**
 * "In phiếu tài khoản" — mẫu built-in hoặc mẫu tùy chỉnh (card_templates).
 */
class StudentPrintCardController extends Controller
{
    public function __construct(
        private StudentPasswordService $passwords,
        private CardTemplateStorageService $cardStorage,
    ) {}

    public function index(Request $request, StudentClass $class): View|RedirectResponse
    {
        $this->assertOwned($request, $class);

        $students = $class->students()->orderBy('username')->get();

        if ($students->isEmpty()) {
            return redirect()->route('admin.students.classes.show', $class)
                ->with('error', 'Lớp chưa có học sinh để in phiếu.');
        }

        $templates = $this->buildTemplateCatalog($request);

        $defaultTemplate = array_key_first($templates);

        return view('admin.students.print-cards.index', compact('class', 'students', 'templates', 'defaultTemplate'));
    }

    public function preview(Request $request, StudentClass $class): Response
    {
        $this->assertOwned($request, $class);

        $source = $this->resolveSource($request);

        if ($source['type'] === 'builtin') {
            $template = $source['template'];
            $students = $class->students()->orderBy('username')->take($template['cardsPerSheet'])->get();

            $html = view('admin.students.print-cards.sheet', [
                'template' => $template,
                'class' => $class,
                'rows' => $this->revealRows($students, $request),
            ])->render();

            return response($html)->header('Content-Type', 'text/html; charset=UTF-8');
        }

        /** @var CardTemplate $cardTemplate */
        $cardTemplate = $source['cardTemplate'];
        $a4 = $source['a4'];
        $students = $class->students()->orderBy('username')->take($a4['perSheet'])->get();
        $rows = $this->printRows($students, $class, $request);

        $html = $this->renderCustomSheet($cardTemplate, $class, $rows, 'front', $a4);

        return response($html)->header('Content-Type', 'text/html; charset=UTF-8');
    }

    public function export(Request $request, StudentClass $class): BinaryFileResponse
    {
        $this->assertOwned($request, $class);

        $source = $this->resolveSource($request);
        $students = $class->students()->orderBy('username')->get();

        abort_if($students->isEmpty(), 422, 'Lớp chưa có học sinh để in phiếu.');

        $workDir = storage_path('app/private/tmp/print-cards/'.Str::uuid());
        File::ensureDirectoryExists($workDir);

        $zipName = 'phieu-tai-khoan-'.Str::slug($class->name).'-'.now()->format('Ymd-His').'.zip';
        $zipPath = storage_path('app/private/tmp/print-cards/'.Str::uuid().'.zip');

        try {
            $pdfPaths = $source['type'] === 'builtin'
                ? $this->exportBuiltin($source['template'], $class, $students, $request, $workDir)
                : $this->exportCustom($source['cardTemplate'], $source['a4'], $class, $students, $request, $workDir);

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
     * @return array<string, array<string, mixed>>
     */
    private function buildTemplateCatalog(Request $request): array
    {
        $templates = [];

        foreach (PrintCardTemplates::all() as $key => $tpl) {
            $sourceKey = 'builtin:'.$key;
            $templates[$sourceKey] = array_merge($tpl, [
                'sourceKey' => $sourceKey,
                'kind' => 'builtin',
            ]);
        }

        $customList = CardTemplate::query()
            ->visibleTo($request->user())
            ->orderBy('name')
            ->get();

        foreach ($customList as $cardTemplate) {
            $a4 = CardTemplateA4Layout::computeForTemplate($cardTemplate);
            $sourceKey = 'custom:'.$cardTemplate->id;
            $templates[$sourceKey] = [
                'sourceKey' => $sourceKey,
                'kind' => 'custom',
                'key' => $sourceKey,
                'name' => $cardTemplate->name,
                'description' => 'Mẫu thiết kế của bạn · '.($cardTemplate->sides === 2 ? '2 mặt (ZIP gồm trang trước + sau)' : '1 mặt'),
                'cardsPerSheet' => $a4['perSheet'],
                'sides' => $cardTemplate->sides,
                'twoSided' => (int) $cardTemplate->sides === 2,
            ];
        }

        return $templates;
    }

    /**
     * @return array{type: 'builtin', template: array<string, mixed>}|array{type: 'custom', cardTemplate: CardTemplate, a4: array<string, mixed>}
     */
    private function resolveSource(Request $request): array
    {
        $validated = $request->validate([
            'template' => ['required', 'string', 'max:64'],
        ]);

        $value = $validated['template'];

        if (str_starts_with($value, 'builtin:')) {
            $key = substr($value, 8);
            $template = PrintCardTemplates::find($key);
            abort_if($template === null, 422, 'Mẫu built-in không hợp lệ.');

            return ['type' => 'builtin', 'template' => $template];
        }

        if (str_starts_with($value, 'custom:')) {
            $id = (int) substr($value, 7);
            abort_if($id < 1, 422, 'Mẫu tùy chỉnh không hợp lệ.');

            $cardTemplate = CardTemplate::query()
                ->visibleTo($request->user())
                ->findOrFail($id);

            return [
                'type' => 'custom',
                'cardTemplate' => $cardTemplate,
                'a4' => CardTemplateA4Layout::computeForTemplate($cardTemplate),
            ];
        }

        // Tương thích mẫu cũ (modern/classic/minimal không prefix)
        if ($legacy = PrintCardTemplates::find($value)) {
            return ['type' => 'builtin', 'template' => $legacy];
        }

        throw ValidationException::withMessages([
            'template' => 'Mẫu in không hợp lệ.',
        ]);
    }

    /**
     * @return list<string> đường dẫn PDF tạm
     */
    private function exportBuiltin(
        array $template,
        StudentClass $class,
        Collection $students,
        Request $request,
        string $workDir,
    ): array {
        $rows = $this->revealRows($students, $request);
        $pdfPaths = [];
        $sheetNumber = 0;

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

        return $pdfPaths;
    }

    /**
     * @return list<string>
     */
    private function exportCustom(
        CardTemplate $cardTemplate,
        array $a4,
        StudentClass $class,
        Collection $students,
        Request $request,
        string $workDir,
    ): array {
        $rows = $this->printRows($students, $request);
        $pdfPaths = [];
        $sides = ['front'];
        if ((int) $cardTemplate->sides === 2) {
            $sides[] = 'back';
        }

        foreach ($sides as $side) {
            $sheetNumber = 0;
            $prefix = $side === 'back' ? 'sau' : 'truoc';

            foreach ($rows->chunk($a4['perSheet']) as $chunk) {
                $sheetNumber++;
                $html = $this->renderCustomSheet($cardTemplate, $class, $chunk->values()->all(), $side, $a4);
                $pdfPath = $workDir.'/'.sprintf('phieu-%s-%02d.pdf', $prefix, $sheetNumber);
                Pdf::loadHTML($html)->setPaper('a4', 'portrait')->save($pdfPath);
                $pdfPaths[] = $pdfPath;
            }
        }

        return $pdfPaths;
    }

    /**
     * @param  list<array{data: array<string, mixed>}>  $rows
     */
    private function renderCustomSheet(
        CardTemplate $cardTemplate,
        StudentClass $class,
        array $rows,
        string $side,
        array $a4,
    ): string {
        $layout = $cardTemplate->layout ?? CardTemplate::defaultLayout();
        $sideLayout = $layout[$side] ?? ['imageLayers' => [], 'elements' => []];
        $elements = $sideLayout['elements'] ?? [];

        $fontKeys = collect($elements)->pluck('fontFamily')->filter()->unique()->values()->all();

        $bakedPath = $side === 'back' ? $cardTemplate->back_baked_path : $cardTemplate->front_baked_path;

        return view('admin.card-templates.sheet', [
            'cardTemplate' => $cardTemplate,
            'class' => $class,
            'rows' => $rows,
            'side' => $side,
            'a4' => $a4,
            'elements' => $elements,
            'imageDataUri' => $this->cardStorage->toDataUri($bakedPath),
            'fontCss' => CardFonts::dompdfFontFaceCss($fontKeys),
        ])->render();
    }

    /**
     * @return Collection<int, array{student: Student, password: string}>
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

    /**
     * @return list<array{data: array<string, mixed>}>
     */
    private function printRows(Collection $students, StudentClass $class, Request $request): array
    {
        $teacher = $request->user();

        return $students->map(function (Student $student) use ($class, $request, $teacher) {
            $password = rescue(
                fn () => $this->passwords->reveal($student, $request->user(), $request->ip()),
                '(không đọc được)',
                false
            );

            return [
                'data' => CardFonts::dataForPrint($student, $class, $password, $teacher),
            ];
        })->values()->all();
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

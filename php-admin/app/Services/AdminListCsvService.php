<?php

namespace App\Services;

use App\Models\Game;
use App\Models\Keyboard;
use App\Models\QuestionBankItem;
use App\Models\Quiz;
use App\Models\Tag;
use App\Support\AdminListCsvRegistry;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminListCsvService
{
    public function __construct(
        private QuestionValidator $questionValidator,
        private QuizTagService $quizTagService,
        private QuestionBankTagService $questionBankTagService,
    ) {}

    /**
     * @param  iterable<int, mixed>  $rows
     * @param  list<string>  $columnKeys
     */
    public function streamExport(string $type, iterable $rows, array $columnKeys, string $filename): StreamedResponse
    {
        $registry = $this->registry($type);
        $headers = AdminListCsvRegistry::headerLabels($registry, $columnKeys);

        return response()->streamDownload(function () use ($rows, $columnKeys, $headers, $type) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($out, array_values($headers));

            foreach ($rows as $row) {
                $line = [];
                foreach ($columnKeys as $key) {
                    $line[] = $this->exportValue($type, $key, $row);
                }
                fputcsv($out, $line);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @param  list<string>  $columnKeys
     */
    public function streamTemplate(string $type, array $columnKeys, string $filename): StreamedResponse
    {
        $registry = $this->registry($type);
        $headers = AdminListCsvRegistry::headerLabels($registry, $columnKeys);
        $examples = $this->exampleRows($type, $columnKeys);

        return response()->streamDownload(function () use ($headers, $examples, $columnKeys) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($out, array_values($headers));

            foreach ($examples as $example) {
                $line = [];
                foreach ($columnKeys as $key) {
                    $line[] = $example[$key] ?? '';
                }
                fputcsv($out, $line);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return array{imported: int, errors: list<string>}
     */
    public function importQuiz(UploadedFile $file, array $columnKeys): array
    {
        $registry = AdminListCsvRegistry::quiz();
        $parsed = $this->parseFile($file, $registry, $columnKeys);
        if ($parsed['fatal']) {
            return ['imported' => 0, 'errors' => [$parsed['fatal']]];
        }

        $imported = 0;
        $errors = [];

        foreach ($parsed['rows'] as $index => $row) {
            $line = $index + 2;
            try {
                $this->importQuizRow($row);
                $imported++;
            } catch (\Throwable $e) {
                $errors[] = "Dòng {$line}: ".$e->getMessage();
            }
        }

        return ['imported' => $imported, 'errors' => $errors];
    }

    /**
     * @return array{imported: int, errors: list<string>}
     */
    public function importQuestionBank(UploadedFile $file, array $columnKeys): array
    {
        $registry = AdminListCsvRegistry::questionBank();
        $parsed = $this->parseFile($file, $registry, $columnKeys);
        if ($parsed['fatal']) {
            return ['imported' => 0, 'errors' => [$parsed['fatal']]];
        }

        $imported = 0;
        $errors = [];

        foreach ($parsed['rows'] as $index => $row) {
            $line = $index + 2;
            try {
                $this->importQuestionBankRow($row);
                $imported++;
            } catch (\Throwable $e) {
                $errors[] = "Dòng {$line}: ".$e->getMessage();
            }
        }

        return ['imported' => $imported, 'errors' => $errors];
    }

    /**
     * @param  list<string>  $requestedColumns
     * @return array{fatal: ?string, rows: list<array<string, string>>}
     */
    private function parseFile(UploadedFile $file, array $registry, array $requestedColumns): array
    {
        $handle = fopen($file->getRealPath(), 'r');
        if ($handle === false) {
            return ['fatal' => 'Không đọc được file CSV.', 'rows' => []];
        }

        $firstLine = fgets($handle);
        if ($firstLine === false) {
            fclose($handle);

            return ['fatal' => 'File CSV trống.', 'rows' => []];
        }

        $firstLine = preg_replace('/^\xEF\xBB\xBF/', '', $firstLine) ?? $firstLine;
        $rawHeaders = str_getcsv($firstLine);
        $mapped = AdminListCsvRegistry::mapImportHeaders($registry, $rawHeaders);

        if ($mapped['missing_required'] !== []) {
            fclose($handle);
            $labels = collect($mapped['missing_required'])
                ->map(fn ($key) => $registry[$key]['label'] ?? $key)
                ->implode(', ');

            return ['fatal' => "Thiếu cột bắt buộc: {$labels}. Tải file mẫu để xem định dạng.", 'rows' => []];
        }

        $keys = $mapped['keys'];
        $rows = [];

        while (($data = fgetcsv($handle)) !== false) {
            if ($this->isEmptyRow($data)) {
                continue;
            }

            $row = [];
            foreach ($keys as $i => $key) {
                $row[$key] = trim((string) ($data[$i] ?? ''));
            }
            $rows[] = $row;
        }

        fclose($handle);

        if ($rows === []) {
            return ['fatal' => 'Không có dòng dữ liệu nào trong file.', 'rows' => []];
        }

        return ['fatal' => null, 'rows' => $rows];
    }

    /**
     * @param  array<string, string>  $row
     */
    private function importQuizRow(array $row): void
    {
        $name = trim($row['name'] ?? '');
        if ($name === '') {
            throw new \InvalidArgumentException('Thiếu tên quiz.');
        }

        $gameName = trim($row['game'] ?? '');
        $keyboardName = trim($row['keyboard'] ?? '');
        if ($gameName === '' || $keyboardName === '') {
            throw new \InvalidArgumentException('Thiếu Game hoặc Bàn phím.');
        }

        $game = Game::query()->where('name', $gameName)->first();
        if (! $game) {
            throw new \InvalidArgumentException("Không tìm thấy game «{$gameName}».");
        }

        $keyboard = Keyboard::query()->where('name', $keyboardName)->first();
        if (! $keyboard) {
            throw new \InvalidArgumentException("Không tìm thấy bàn phím «{$keyboardName}».");
        }

        $quiz = Quiz::create([
            'game_id' => $game->id,
            'keyboard_id' => $keyboard->id,
            'name' => $name,
            'grade' => ($row['grade'] ?? '') !== '' ? $row['grade'] : null,
            'sort_order' => 0,
            'is_active' => $this->parseBoolean($row['active'] ?? '1'),
        ]);

        if (($row['tags'] ?? '') !== '') {
            $this->quizTagService->syncFromInput($quiz, $row['tags']);
        }
    }

    /**
     * @param  array<string, string>  $row
     */
    private function importQuestionBankRow(array $row): void
    {
        $content = trim($row['content'] ?? '');
        if ($content === '') {
            throw new \InvalidArgumentException('Thiếu nội dung câu hỏi.');
        }

        $answerType = $this->parseAnswerType($row['type'] ?? '');
        if ($answerType === '') {
            throw new \InvalidArgumentException('Thiếu hoặc sai loại câu (mc/essay/structured).');
        }

        $data = [
            'content' => $content,
            'answer_type' => $answerType,
            'points' => ($row['points'] ?? '') !== '' ? (int) $row['points'] : 1,
            'time_limit_seconds' => ($row['time'] ?? '') !== '' ? (int) $row['time'] : 30,
        ];

        if ($answerType === 'mc') {
            $optionsRaw = trim($row['options'] ?? '');
            if ($optionsRaw === '') {
                throw new \InvalidArgumentException('Câu trắc nghiệm cần cột «Đáp án MC».');
            }
            $options = array_values(array_filter(array_map('trim', preg_split('/\|/', $optionsRaw) ?: [])));
            $data['options'] = $options;
            $data['correct_index'] = ($row['correct_index'] ?? '') !== '' ? (int) $row['correct_index'] : 0;
        }

        if ($answerType === 'essay') {
            $sample = trim($row['correct_answer'] ?? '');
            if ($sample === '') {
                throw new \InvalidArgumentException('Câu tự luận cần cột «Đáp án mẫu».');
            }
            $data['correct_answer_normalized'] = $sample;
        }

        if ($answerType === 'structured') {
            throw new \InvalidArgumentException('Loại Phương trình chưa hỗ trợ import CSV — tạo trong form.');
        }

        $prepared = $this->questionValidator->validateAndPrepare($data);

        $item = QuestionBankItem::create([
            ...$prepared,
            'is_active' => true,
        ]);

        if (($row['tags'] ?? '') !== '') {
            $this->syncTagsFromInput($item, $row['tags']);
        }
    }

    private function syncTagsFromInput(QuestionBankItem $item, string $input): void
    {
        $names = collect(preg_split('/[,;]+/', $input) ?: [])
            ->map(fn ($name) => trim((string) $name))
            ->filter()
            ->unique(fn ($name) => mb_strtolower($name));

        $ids = $names->map(fn (string $name) => Tag::findOrCreateFromName($name)->id)->all();
        $this->questionBankTagService->syncFromIds($item, $ids);
    }

    private function exportValue(string $type, string $key, mixed $row): string
    {
        if ($type === 'quiz' && $row instanceof Quiz) {
            return match ($key) {
                'name' => $row->name,
                'tags' => $row->tags->pluck('name')->implode(', '),
                'game' => $row->game?->name ?? '',
                'keyboard' => $row->keyboard?->name ?? '',
                'grade' => $row->grade ?? '',
                'questions' => (string) ($row->questions_count ?? $row->questions()->count()),
                'active' => $row->is_active ? '1' : '0',
                default => '',
            };
        }

        if ($type === 'question_bank' && $row instanceof QuestionBankItem) {
            return match ($key) {
                'type' => $this->answerTypeLabel($row->answer_type, $row->input_mode),
                'tags' => $row->tags->pluck('name')->implode(', '),
                'content' => strip_tags($row->content),
                'points' => (string) $row->points,
                'time' => (string) $row->time_limit_seconds,
                default => '',
            };
        }

        return '';
    }

    private function answerTypeLabel(string $type, ?string $inputMode): string
    {
        if ($type === 'mc') {
            return 'Trắc nghiệm';
        }
        if ($type === 'essay') {
            return 'Tự luận';
        }

        return match ($inputMode) {
            'balance' => 'Cân bằng hệ số',
            'blank' => 'Điền chỗ thiếu',
            'blank_balance' => 'Cân bằng + điền',
            'product' => 'Điền sản phẩm',
            default => 'Phương trình',
        };
    }

    private function parseAnswerType(string $value): string
    {
        $normalized = mb_strtolower(trim($value));

        return match ($normalized) {
            'mc', 'trắc nghiệm', 'trac nghiem' => 'mc',
            'essay', 'tự luận', 'tu luan' => 'essay',
            'structured', 'phương trình', 'phuong trinh' => 'structured',
            default => '',
        };
    }

    private function parseBoolean(string $value): bool
    {
        $normalized = mb_strtolower(trim($value));

        return in_array($normalized, ['0', 'false', 'không', 'khong', 'off', 'no'], true) ? false : true;
    }

    /**
     * @param  list<string|null>  $data
     */
    private function isEmptyRow(array $data): bool
    {
        return collect($data)->every(fn ($cell) => trim((string) $cell) === '');
    }

    /**
     * @param  list<string>  $columnKeys
     * @return list<array<string, string>>
     */
    private function exampleRows(string $type, array $columnKeys): array
    {
        if ($type === 'quiz') {
            return [[
                'name' => 'Quiz mẫu — Hóa vô cơ',
                'tags' => 'Hóa 10, Ôn tập',
                'game' => 'Ôn tập học kỳ 1',
                'keyboard' => 'Bàn phím Hóa vô cơ',
                'grade' => '10',
                'active' => '1',
            ]];
        }

        return [
            [
                'type' => 'Trắc nghiệm',
                'tags' => 'Hóa 10',
                'content' => 'Nguyên tố nào có số hiệu nguyên tử bằng 6?',
                'points' => '1',
                'time' => '30',
                'options' => 'Cacbon|Silic|Natri|Magie',
                'correct_index' => '0',
            ],
            [
                'type' => 'Tự luận',
                'tags' => 'Hóa 11',
                'content' => 'Viết công thức cấu tạo của etanol.',
                'points' => '2',
                'time' => '45',
                'correct_answer' => 'C2H5OH',
            ],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function registry(string $type): array
    {
        return match ($type) {
            'quiz' => AdminListCsvRegistry::quiz(),
            'question_bank' => AdminListCsvRegistry::questionBank(),
            default => throw new \InvalidArgumentException('Unknown CSV type'),
        };
    }

    /**
     * @param  list<string>|null  $requested
     * @return list<string>
     */
    public function resolveExportColumns(string $type, ?array $requested): array
    {
        $registry = $this->registry($type);
        $allowed = collect($registry)->filter(fn ($col) => $col['exportable'])->keys()->all();

        if ($requested === null || $requested === []) {
            return AdminListCsvRegistry::defaultExportKeys($registry);
        }

        $resolved = array_values(array_intersect($requested, $allowed));

        return $resolved !== [] ? $resolved : AdminListCsvRegistry::defaultExportKeys($registry);
    }

    /**
     * @param  list<string>|null  $requested
     * @return list<string>
     */
    public function resolveTemplateColumns(string $type, ?array $requested): array
    {
        $registry = $this->registry($type);
        $visible = $this->resolveExportColumns($type, $requested);

        return AdminListCsvRegistry::templateKeys($registry, $visible);
    }
}

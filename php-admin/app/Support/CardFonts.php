<?php

namespace App\Support;

/**
 * Registry font dùng cho in thẻ tài khoản (dompdf + editor WYSIWYG).
 *
 * File .ttf nằm tại resources/fonts/card/. dompdf nạp qua @font-face file://;
 * editor nạp qua URL public /htd-admin/fonts/card/.
 */
class CardFonts
{
    /** @var list<array{key: string, label: string, family: string, files: array{regular: string, bold?: string, italic?: string}, supportsVietnamese: bool}>|null */
    private static ?array $registry = null;

    /**
     * @return list<array{key: string, label: string, family: string, files: array{regular: string, bold?: string, italic?: string}, supportsVietnamese: bool}>
     */
    public static function all(): array
    {
        if (self::$registry !== null) {
            return self::$registry;
        }

        self::$registry = [
            [
                'key' => 'be-vietnam-pro',
                'label' => 'Be Vietnam Pro',
                'family' => 'Be Vietnam Pro',
                'files' => [
                    'regular' => 'BeVietnamPro-Regular.ttf',
                    'bold' => 'BeVietnamPro-Bold.ttf',
                    'italic' => 'BeVietnamPro-Italic.ttf',
                ],
                'supportsVietnamese' => true,
            ],
            [
                'key' => 'inter',
                'label' => 'Inter',
                'family' => 'Inter',
                'files' => [
                    'regular' => 'Inter-Regular.ttf',
                    'bold' => 'Inter-Bold.ttf',
                ],
                'supportsVietnamese' => true,
            ],
            [
                'key' => 'roboto',
                'label' => 'Roboto',
                'family' => 'Roboto',
                'files' => [
                    'regular' => 'Roboto-Regular.ttf',
                    'bold' => 'Roboto-Bold.ttf',
                ],
                'supportsVietnamese' => true,
            ],
            [
                'key' => 'montserrat',
                'label' => 'Montserrat',
                'family' => 'Montserrat',
                'files' => [
                    'regular' => 'Montserrat-Regular.ttf',
                    'bold' => 'Montserrat-Bold.ttf',
                ],
                'supportsVietnamese' => true,
            ],
            [
                'key' => 'nunito',
                'label' => 'Nunito',
                'family' => 'Nunito',
                'files' => [
                    'regular' => 'Nunito-Regular.ttf',
                    'bold' => 'Nunito-Bold.ttf',
                ],
                'supportsVietnamese' => true,
            ],
            [
                'key' => 'merriweather',
                'label' => 'Merriweather',
                'family' => 'Merriweather',
                'files' => [
                    'regular' => 'Merriweather-Regular.ttf',
                    'bold' => 'Merriweather-Bold.ttf',
                ],
                'supportsVietnamese' => true,
            ],
            [
                'key' => 'playfair-display',
                'label' => 'Playfair Display',
                'family' => 'Playfair Display',
                'files' => [
                    'regular' => 'PlayfairDisplay-Regular.ttf',
                    'bold' => 'PlayfairDisplay-Bold.ttf',
                ],
                'supportsVietnamese' => true,
            ],
            [
                'key' => 'lobster',
                'label' => 'Lobster',
                'family' => 'Lobster',
                'files' => [
                    'regular' => 'Lobster-Regular.ttf',
                ],
                'supportsVietnamese' => false,
            ],
            [
                'key' => 'pacifico',
                'label' => 'Pacifico',
                'family' => 'Pacifico',
                'files' => [
                    'regular' => 'Pacifico-Regular.ttf',
                ],
                'supportsVietnamese' => false,
            ],
            [
                'key' => 'dancing-script',
                'label' => 'Dancing Script',
                'family' => 'Dancing Script',
                'files' => [
                    'regular' => 'DancingScript-Regular.ttf',
                    'bold' => 'DancingScript-Bold.ttf',
                ],
                'supportsVietnamese' => false,
            ],
        ];

        return self::$registry;
    }

    /**
     * @return array{key: string, label: string, family: string, files: array{regular: string, bold?: string, italic?: string}, supportsVietnamese: bool}|null
     */
    public static function find(string $key): ?array
    {
        foreach (self::all() as $font) {
            if ($font['key'] === $key) {
                return $font;
            }
        }

        return null;
    }

    public static function fontDir(): string
    {
        return resource_path('fonts/card');
    }

    public static function publicBaseUrl(): string
    {
        return asset('htd-admin/fonts/card');
    }

    /**
     * Sinh @font-face cho dompdf (file:// absolute path).
     *
     * @param  list<string>  $keys
     */
    public static function dompdfFontFaceCss(array $keys): string
    {
        $css = '';
        $seen = [];

        foreach ($keys as $key) {
            $font = self::find($key);
            if (! $font) {
                continue;
            }

            foreach ($font['files'] as $variant => $filename) {
                $abs = self::fontDir().'/'.$filename;
                if (! is_file($abs)) {
                    continue;
                }

                $faceKey = $font['family'].'|'.$variant;
                if (isset($seen[$faceKey])) {
                    continue;
                }
                $seen[$faceKey] = true;

                $weight = match ($variant) {
                    'bold' => '700',
                    default => '400',
                };
                $style = $variant === 'italic' ? 'italic' : 'normal';

                $css .= "@font-face{font-family:'{$font['family']}';src:url('file://{$abs}');font-weight:{$weight};font-style:{$style};}\n";
            }
        }

        return $css;
    }

    /**
     * Sinh @font-face cho editor web (URL public).
     */
    public static function editorFontFaceCss(): string
    {
        $css = '';
        $seen = [];

        foreach (self::all() as $font) {
            foreach ($font['files'] as $variant => $filename) {
                $path = self::fontDir().'/'.$filename;
                if (! is_file($path)) {
                    continue;
                }

                $faceKey = $font['family'].'|'.$variant;
                if (isset($seen[$faceKey])) {
                    continue;
                }
                $seen[$faceKey] = true;

                $weight = match ($variant) {
                    'bold' => '700',
                    default => '400',
                };
                $style = $variant === 'italic' ? 'italic' : 'normal';
                $url = self::publicBaseUrl().'/'.$filename;

                $css .= "@font-face{font-family:'{$font['family']}';src:url('{$url}');font-weight:{$weight};font-style:{$style};}\n";
            }
        }

        return $css;
    }

    /**
     * Danh sách chip bind dữ liệu (nhãn thân thiện, không lộ tên cột kỹ thuật).
     *
     * @return list<array{key: string, label: string, binding: string}>
     */
    public static function dataBindings(): array
    {
        return [
            ['key' => 'display_name', 'label' => 'Tên', 'binding' => 'student.display_name'],
            ['key' => 'username', 'label' => 'Tài khoản', 'binding' => 'student.username'],
            ['key' => 'password', 'label' => 'Mật khẩu', 'binding' => 'student.password'],
            ['key' => 'student_code', 'label' => 'Mã HS', 'binding' => 'student.student_code'],
            ['key' => 'email', 'label' => 'Email', 'binding' => 'student.email'],
            ['key' => 'class_name', 'label' => 'Lớp', 'binding' => 'class.name'],
            ['key' => 'class_grade', 'label' => 'Khối', 'binding' => 'class.grade'],
            ['key' => 'teacher_name', 'label' => 'Giáo viên', 'binding' => 'teacher.name'],
            ['key' => 'static', 'label' => 'Text tự nhập', 'binding' => 'static'],
        ];
    }

    /**
     * Dữ liệu mẫu cho xem trước trong editor.
     *
     * @return array<string, mixed>
     */
    public static function sampleData(): array
    {
        return [
            'student' => [
                'display_name' => 'Nguyễn Văn An',
                'username' => 'an10a1',
                'password' => 'Hoa123!',
                'student_code' => 'HS-AB1234',
                'email' => 'an@example.com',
            ],
            'class' => [
                'name' => '10A1',
                'grade' => '10',
            ],
            'teacher' => [
                'name' => 'Thầy Đạt',
            ],
        ];
    }

    /**
     * Dữ liệu in từ học sinh / lớp / giáo viên thật.
     *
     * @return array<string, mixed>
     */
    public static function dataForPrint(
        \App\Models\Student $student,
        \App\Models\StudentClass $class,
        string $password,
        \App\Models\User $teacher,
    ): array {
        return [
            'student' => [
                'display_name' => $student->display_name,
                'username' => $student->username,
                'password' => $password,
                'student_code' => $student->student_code,
                'email' => $student->email ?? '',
            ],
            'class' => [
                'name' => $class->name,
                'grade' => (string) ($class->grade ?? ''),
            ],
            'teacher' => [
                'name' => $teacher->name,
            ],
        ];
    }

    /**
     * Resolve giá trị hiển thị từ binding + dữ liệu thật.
     *
     * @param  array<string, mixed>  $data
     */
    public static function resolveBinding(string $binding, array $data, ?string $fallback = ''): string
    {
        if ($binding === 'static') {
            return (string) ($fallback ?? '');
        }

        [$entity, $field] = array_pad(explode('.', $binding, 2), 2, null);
        if (! $entity || ! $field) {
            return (string) ($fallback ?? '');
        }

        $value = $data[$entity][$field] ?? null;

        return $value !== null && $value !== '' ? (string) $value : (string) ($fallback ?? '');
    }
}

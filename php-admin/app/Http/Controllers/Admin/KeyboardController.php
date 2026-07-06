<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Keyboard;
use App\Services\KeyboardValidator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class KeyboardController extends Controller
{
    public function __construct(
        private KeyboardValidator $validator,
    ) {}

    public function index(): View
    {
        $keyboards = Keyboard::query()
            ->withCount('quizzes')
            ->orderBy('name')
            ->get();

        return view('admin.keyboards.index', compact('keyboards'));
    }

    public function create(): View
    {
        return view('admin.keyboards.form', [
            'keyboard' => null,
            'configJson' => json_encode($this->defaultConfig(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'subject' => ['nullable', 'string', 'max:64'],
            'config_json' => ['required', 'string'],
        ]);

        $config = $this->parseConfig($validated['config_json']);
        if ($config === null) {
            return back()->withInput()->withErrors(['config_json' => 'JSON không hợp lệ.']);
        }

        $config = $this->validator->normalizeConfig($config);
        $issues = $this->validator->validate($config);
        if ($issues !== []) {
            return back()->withInput()->withErrors(['config_json' => implode(' ', $issues)]);
        }

        Keyboard::create([
            'name' => $validated['name'],
            'subject' => $validated['subject'] ?? 'chemistry',
            'config' => $config,
        ]);

        return redirect()->route('admin.keyboards.index')
            ->with('success', 'Đã tạo bàn phím.');
    }

    public function edit(Keyboard $keyboard): View
    {
        return view('admin.keyboards.form', [
            'keyboard' => $keyboard,
            'configJson' => json_encode($keyboard->config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        ]);
    }

    public function update(Request $request, Keyboard $keyboard): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'subject' => ['nullable', 'string', 'max:64'],
            'config_json' => ['required', 'string'],
        ]);

        $config = $this->parseConfig($validated['config_json']);
        if ($config === null) {
            return back()->withInput()->withErrors(['config_json' => 'JSON không hợp lệ.']);
        }

        $config = $this->validator->normalizeConfig($config);
        $issues = $this->validator->validate($config);
        if ($issues !== []) {
            return back()->withInput()->withErrors(['config_json' => implode(' ', $issues)]);
        }

        $keyboard->update([
            'name' => $validated['name'],
            'subject' => $validated['subject'] ?? 'chemistry',
            'config' => $config,
        ]);

        return redirect()->route('admin.keyboards.index')
            ->with('success', 'Đã cập nhật bàn phím.');
    }

    public function destroy(Keyboard $keyboard): RedirectResponse
    {
        if ($keyboard->quizzes()->exists()) {
            return back()->with('error', 'Không thể xóa bàn phím đang được quiz sử dụng.');
        }

        $keyboard->delete();

        return redirect()->route('admin.keyboards.index')
            ->with('success', 'Đã xóa bàn phím.');
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultConfig(): array
    {
        return [
            'schema_version' => 1,
            'defaults' => [
                'keySize' => 'M',
                'fontSize' => 'M',
                'textColor' => '#000000',
                'background' => '#FFFFFF',
                'border' => '#D0D0D0',
            ],
            'rows' => [
                [
                    'id' => 'row-numbers',
                    'name' => 'Numbers',
                    'height' => 'M',
                    'padding' => 2,
                    'spacing' => 4,
                    'background' => '#F5F5F5',
                    'border' => '#E0E0E0',
                    'alignment' => 'center',
                    'hidden' => false,
                    'locked' => false,
                    'isSpaceRow' => false,
                    'keys' => array_map(fn ($d) => $this->key($d), ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9']),
                ],
                [
                    'id' => 'row-symbols',
                    'name' => 'Symbols',
                    'height' => 'M',
                    'padding' => 2,
                    'spacing' => 4,
                    'background' => '#F5F5F5',
                    'border' => '#E0E0E0',
                    'alignment' => 'center',
                    'hidden' => false,
                    'locked' => false,
                    'isSpaceRow' => false,
                    'keys' => array_merge(
                        array_map(fn ($d) => $this->key($d), ['(', ')', '+', '-', '=']),
                        [['id' => 'key-del', 'text' => '⌫', 'value' => 'BACKSPACE', 'width' => 2, 'type' => 'delete', 'background' => '#FFFFFF', 'color' => '#000000', 'border' => '#D0D0D0', 'radius' => 6, 'fontSize' => 'M', 'keySize' => 'M', 'tooltip' => '', 'disabled' => false]]
                    ),
                ],
                [
                    'id' => 'row-elements',
                    'name' => 'Elements',
                    'height' => 'M',
                    'padding' => 2,
                    'spacing' => 4,
                    'background' => '#F5F5F5',
                    'border' => '#E0E0E0',
                    'alignment' => 'center',
                    'hidden' => false,
                    'locked' => false,
                    'isSpaceRow' => false,
                    'keys' => array_map(fn ($d) => $this->key($d), ['H', 'O', 'C', 'N', 'Cl', 'Na', 'K', 'Ca']),
                ],
                [
                    'id' => 'row-space',
                    'name' => 'Space',
                    'height' => 'M',
                    'padding' => 2,
                    'spacing' => 4,
                    'background' => '#F5F5F5',
                    'border' => '#E0E0E0',
                    'alignment' => 'center',
                    'hidden' => false,
                    'locked' => true,
                    'isSpaceRow' => true,
                    'keys' => [
                        ['id' => 'key-space', 'text' => 'Space', 'value' => ' ', 'width' => 7, 'type' => 'space', 'background' => '#FFFFFF', 'color' => '#000000', 'border' => '#D0D0D0', 'radius' => 6, 'fontSize' => 'M', 'keySize' => 'M', 'tooltip' => '', 'disabled' => false],
                        ['id' => 'key-send', 'text' => 'Gửi', 'value' => 'SEND', 'width' => 3, 'type' => 'send', 'background' => '#2D46D6', 'color' => '#FFFFFF', 'border' => '#2D46D6', 'radius' => 6, 'fontSize' => 'M', 'keySize' => 'M', 'tooltip' => '', 'disabled' => false],
                    ],
                ],
            ],
            'smart_context' => [
                'after_element' => 'subscript',
                'after_plus' => 'coefficient',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function key(string $text): array
    {
        return [
            'id' => 'key-'.strtolower(preg_replace('/[^a-z0-9]/i', '', $text) ?: 'x'),
            'text' => $text,
            'value' => $text,
            'width' => 1,
            'type' => 'normal',
            'background' => '#FFFFFF',
            'color' => '#000000',
            'border' => '#D0D0D0',
            'radius' => 6,
            'fontSize' => 'M',
            'keySize' => 'M',
            'tooltip' => '',
            'disabled' => false,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parseConfig(string $json): ?array
    {
        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : null;
    }
}

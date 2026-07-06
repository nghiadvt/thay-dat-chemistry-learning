<?php

namespace Tests\Support;

class KeyboardTestConfig
{
    /**
     * Minimal valid keyboard config (matches admin default layout).
     *
     * @return array<string, mixed>
     */
    public static function minimalValid(): array
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
                    'name' => 'Numbers',
                    'keys' => array_map(
                        fn (string $digit) => self::normalKey($digit),
                        ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9']
                    ),
                ],
                [
                    'name' => 'Symbols',
                    'keys' => [
                        ...array_map(fn (string $t) => self::normalKey($t), ['(', ')', '+', '-', '=']),
                        self::deleteKey(),
                    ],
                ],
                [
                    'name' => 'Space',
                    'isSpaceRow' => true,
                    'keys' => [
                        self::spaceKey(),
                        self::sendKey(),
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
    public static function normalKey(string $text): array
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
     * @return array<string, mixed>
     */
    public static function deleteKey(): array
    {
        return [
            'id' => 'key-del',
            'text' => '⌫',
            'value' => 'BACKSPACE',
            'width' => 2,
            'type' => 'delete',
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
     * @return array<string, mixed>
     */
    public static function spaceKey(): array
    {
        return [
            'id' => 'key-space',
            'text' => 'Space',
            'value' => ' ',
            'width' => 7,
            'type' => 'space',
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
     * @return array<string, mixed>
     */
    public static function sendKey(): array
    {
        return [
            'id' => 'key-send',
            'text' => 'Gửi',
            'value' => 'SEND',
            'width' => 3,
            'type' => 'send',
            'background' => '#2D46D6',
            'color' => '#FFFFFF',
            'border' => '#2D46D6',
            'radius' => 6,
            'fontSize' => 'M',
            'keySize' => 'M',
            'tooltip' => '',
            'disabled' => false,
        ];
    }
}

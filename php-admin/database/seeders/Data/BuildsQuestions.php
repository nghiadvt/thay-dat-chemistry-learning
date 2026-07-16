<?php

namespace Database\Seeders\Data;

trait BuildsQuestions
{
    /**
     * Câu trắc nghiệm. $options: đáp án đúng đứng ĐẦU, sẽ được xoay vị trí theo $seed.
     *
     * @param  list<string>  $options
     * @return array<string, mixed>
     */
    private static function mc(string $content, array $options, int $seed, string $topic, ?string $explanation = null): array
    {
        [$shuffled, $correctIndex] = self::rotate($options, $seed);

        return [
            'content' => '<p>'.$content.'</p>',
            'answer_type' => 'mc',
            'options' => $shuffled,
            'correct_index' => $correctIndex,
            'correct_answer_normalized' => null,
            'explanation' => $explanation ? '<p>'.$explanation.'</p>' : null,
            'time_limit_seconds' => 20,
            'points' => 1,
            'topic' => $topic,
        ];
    }

    /**
     * Câu tự luận (nhập công thức / phương trình).
     *
     * @return array<string, mixed>
     */
    private static function essay(string $content, string $answer, string $topic, ?string $explanation = null): array
    {
        return [
            'content' => '<p>'.$content.'</p>',
            'answer_type' => 'essay',
            'options' => null,
            'correct_index' => null,
            'correct_answer_normalized' => $answer,
            'explanation' => $explanation ? '<p>'.$explanation.'</p>' : null,
            'time_limit_seconds' => 30,
            'points' => 1,
            'topic' => $topic,
        ];
    }

    /**
     * Xoay đáp án đúng (phần tử đầu) tới vị trí xác định bởi $seed.
     *
     * @param  list<string>  $optionsCorrectFirst
     * @return array{0: list<string>, 1: int}
     */
    private static function rotate(array $optionsCorrectFirst, int $seed): array
    {
        $correct = array_shift($optionsCorrectFirst);
        $pos = $seed % (count($optionsCorrectFirst) + 1);
        array_splice($optionsCorrectFirst, $pos, 0, [$correct]);

        return [array_values($optionsCorrectFirst), $pos];
    }

    /**
     * Câu hỏi khối lượng mol với 3 phương án nhiễu M-2, M+2, M+16.
     */
    private static function molarMass(string $formulaHtml, float $mass, int $seed, string $topic): array
    {
        $fmt = fn (float $m): string => str_replace('.', ',', rtrim(rtrim(number_format($m, 1, '.', ''), '0'), '.'));

        return self::mc(
            'Khối lượng mol của <strong>'.$formulaHtml.'</strong> là bao nhiêu g/mol?',
            [$fmt($mass), $fmt($mass - 2), $fmt($mass + 2), $fmt($mass + 16)],
            $seed,
            $topic
        );
    }

    /**
     * Câu hỏi ký hiệu hóa học: tên nguyên tố -> ký hiệu, nhiễu lấy từ pool.
     *
     * @param  list<string>  $pool  các ký hiệu khác để làm phương án nhiễu
     */
    private static function symbol(string $nameVn, string $symbol, array $pool, int $seed, string $topic): array
    {
        $distractors = array_values(array_diff($pool, [$symbol]));
        $picked = [];
        for ($i = 0; $i < 3; $i++) {
            $picked[] = $distractors[($seed * 3 + $i) % count($distractors)];
        }

        return self::mc(
            'Nguyên tố <strong>'.$nameVn.'</strong> có ký hiệu hóa học là gì?',
            array_merge([$symbol], array_unique($picked)),
            $seed,
            $topic
        );
    }

    /**
     * Cấu hình electron cho Z <= 20 (không cần phân lớp d).
     */
    private static function electronConfig(int $z): string
    {
        $orbitals = [['1s', 2], ['2s', 2], ['2p', 6], ['3s', 2], ['3p', 6], ['4s', 2]];
        $parts = [];
        foreach ($orbitals as [$name, $cap]) {
            if ($z <= 0) {
                break;
            }
            $take = min($z, $cap);
            $parts[] = $name.$take;
            $z -= $take;
        }

        return implode(' ', $parts);
    }

    private static function configQuestion(string $nameVn, int $z, int $seed, string $topic): array
    {
        $correct = self::electronConfig($z);
        $distractors = array_values(array_unique(array_diff(
            [self::electronConfig($z - 1), self::electronConfig($z + 1), self::electronConfig($z + 2)],
            [$correct]
        )));

        return self::mc(
            'Cấu hình electron của nguyên tử <strong>'.$nameVn.'</strong> (Z = '.$z.') là:',
            array_merge([$correct], $distractors),
            $seed,
            $topic
        );
    }
}

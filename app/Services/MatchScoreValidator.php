<?php

namespace App\Services;

use App\Models\CategoryScoringRule;
use DomainException;

class MatchScoreValidator
{
    public function validate(
        CategoryScoringRule $rule,
        mixed $scoreA,
        mixed $scoreB,
    ): array {
        $scoreA = $this->parseScore($scoreA, 'Pasangan A');
        $scoreB = $this->parseScore($scoreB, 'Pasangan B');

        $gameCount = $rule->game_count;

        if ($gameCount === null || $gameCount < 1) {
            throw new DomainException(
                'Jumlah game pada aturan skor belum valid.'
            );
        }

        switch ($rule->format) {
            case 'race_to':
                $valid = max($scoreA, $scoreB) === $gameCount
                    && min($scoreA, $scoreB) < $gameCount;

                if (! $valid) {
                    throw new DomainException(
                        "Race to {$gameCount}: satu pasangan harus "
                        . "mencapai {$gameCount} game dan lawannya "
                        . "harus di bawah {$gameCount}."
                    );
                }

                break;

            case 'total_of':
                if ($scoreA + $scoreB !== $gameCount) {
                    throw new DomainException(
                        "Total of {$gameCount}: jumlah skor kedua "
                        . "pasangan harus tepat {$gameCount}."
                    );
                }

                break;

            default:
                throw new DomainException(
                    'Format skor tidak dikenali.'
                );
        }

        if ($scoreA === $scoreB) {
            throw new DomainException(
                'Hasil seri belum didukung karena aturan '
                . 'penyelesaiannya belum ditetapkan.'
            );
        }

        return [
            'score_a' => $scoreA,
            'score_b' => $scoreB,
            'winner_side' => $scoreA > $scoreB ? 'A' : 'B',
            'diff_a' => $scoreA - $scoreB,
            'diff_b' => $scoreB - $scoreA,
        ];
    }

    private function parseScore(mixed $value, string $label): int
    {
        if (is_int($value) && $value >= 0 && $value <= 65535) {
            return $value;
        }

        // Input form dapat berupa string angka, termasuk "0".
        if (
            is_string($value) &&
            preg_match('/^[0-9]{1,5}$/D', $value) === 1 &&
            (int) $value <= 65535
        ) {
            return (int) $value;
        }

        throw new DomainException(
            "Skor {$label} harus berupa bilangan bulat "
            . 'antara 0 dan 65535.'
        );
    }
}
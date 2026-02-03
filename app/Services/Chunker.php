<?php

namespace App\Services;

class Chunker
{
    public function chunk(string $text, int $size, int $overlap): array
    {
        $text = trim(preg_replace('/\s+/', ' ', $text) ?? '');
        if ($text === '') {
            return [];
        }

        $chunks = [];
        $length = strlen($text);
        $step = max(1, $size - $overlap);

        for ($offset = 0; $offset < $length; $offset += $step) {
            $chunk = substr($text, $offset, $size);
            if ($chunk === '' || $chunk === false) {
                break;
            }
            $chunks[] = $chunk;
        }

        return $chunks;
    }
}

<?php

namespace TeamTNT\TNTSearch;

class TNTFuzzyMatch
{
    public function norm(array $vec)
    {
        $norm = 0;
        $components = count($vec);

        for ($i = 0; $i < $components; $i++) {
            $norm += $vec[$i] * $vec[$i];
        }

        return sqrt($norm);
    }

    public function dot(array $vec1, array $vec2)
    {
        $prod = 0;
        $components = count($vec1);

        for ($i = 0; $i < $components; $i++) {
            $prod += ($vec1[$i] * $vec2[$i]);
        }

        return $prod;
    }

    public function wordToVector(string $word)
    {
        $alphabet = "aAbBcCčČćĆdDđĐeEfFgGhHiIjJkKlLmMnNoOpPqQrRsSšŠtTvVuUwWxXyYzZžŽ1234567890'+ /";

        $result = [];
        foreach (str_split($word) as $w) {
            $result[] = strpos($alphabet, $w) + 1000000;
        }
        return $result;
    }

    public function angleBetweenVectors(array $a, array $b)
    {
        $denominator = ($this->norm($a) * $this->norm($b));

        if ($denominator == 0) {
            return 0;
        }

        return $this->dot($a, $b) / $denominator;
    }

    public function hasCommonSubsequence(string $pattern, string $str)
    {
        $pattern = mb_strtolower($pattern);
        $str = mb_strtolower($str);

        $j = 0;
        $patternLength = strlen($pattern);
        $strLength = strlen($str);

        for ($i = 0; $i < $strLength && $j < $patternLength; $i++) {
            if ($pattern[$j] == $str[$i]) {
                $j++;
            }
        }

        return ($j == $patternLength);
    }

    public function makeVectorSameLength(array $str, array $pattern)
    {
        $j = 0;
        $max = max(count($pattern), count($str));
        $b = [];

        for ($i = 0; $i < $max && $j < $max; $i++) {
            if (isset($pattern[$j]) && isset($str[$i]) && $pattern[$j] == $str[$i]) {
                $j++;
                $b[] = $str[$i];
            } else {
                $b[] = 0;
            }
        }

        return $b;
    }

    public function fuzzyMatchFromFile(string $pattern, string $path)
    {
        $res = [];
        $lines = fopen($path, "r");
        if ($lines) {
            while (!feof($lines)) {
                $line = rtrim(fgets($lines, 4096));
                if ($this->hasCommonSubsequence($pattern, $line)) {
                    $res[] = $line;
                }
            }
            fclose($lines);
        }

        $patternVector = $this->wordToVector($pattern);

        $sorted = [];
        foreach ($res as $caseSensitiveWord) {
            $word = mb_strtolower(trim($caseSensitiveWord));
            $wordVector = $this->wordToVector($word);
            $normalizedPatternVector = $this->makeVectorSameLength($wordVector, $patternVector);

            $angle = $this->angleBetweenVectors($wordVector, $normalizedPatternVector);

            if (strpos($word, $pattern) !== false) {
                $angle += 0.2;
            }
            $sorted[$caseSensitiveWord] = $angle;
        }

        arsort($sorted);
        return $sorted;
    }

    public function fuzzyMatch(string $pattern, array $items)
    {
        $res = [];

        foreach ($items as $item) {
            if ($this->hasCommonSubsequence($pattern, $item)) {
                $res[] = $item;
            }
        }

        $patternVector = $this->wordToVector($pattern);

        $sorted = [];
        foreach ($res as $word) {
            $word = trim($word);
            $wordVector = $this->wordToVector($word);
            $normalizedPatternVector = $this->makeVectorSameLength($wordVector, $patternVector);

            $angle = $this->angleBetweenVectors($wordVector, $normalizedPatternVector);

            if (strpos($word, $pattern) !== false) {
                $angle += 0.2;
            }

            $sorted[$word] = $angle;
        }

        arsort($sorted);

        return $sorted;
    }
}

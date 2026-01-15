<?php

namespace App\Services\Document;

class NumberToWordsUkrainian
{
    private const ONES = [
        '', 'один', 'два', 'три', 'чотири', 'п\'ять', 'шість', 'сім', 'вісім', 'дев\'ять'
    ];

    private const TEENS = [
        'десять', 'одинадцять', 'дванадцять', 'тринадцять', 'чотирнадцять',
        'п\'ятнадцять', 'шістнадцять', 'сімнадцять', 'вісімнадцять', 'дев\'ятнадцять'
    ];

    private const TENS = [
        '', '', 'двадцять', 'тридцять', 'сорок', 'п\'ятдесят', 'шістдесят',
        'сімдесят', 'вісімдесят', 'дев\'яносто'
    ];

    private const SCALES = [
        ['', 'тисяч', 'мільйонів', 'мільярдів', 'трильйонів']
    ];

    public static function convert(float $number): string
    {
        $number = (int) $number;

        if ($number === 0) {
            return 'нуль';
        }

        if ($number < 0) {
            return 'мінус ' . self::convert(abs($number));
        }

        $parts = [];
        $scale = 0;

        while ($number > 0 && $scale < count(self::SCALES[0])) {
            $part = $number % 1000;

            if ($part !== 0) {
                $words = self::convertGroup($part);

                if ($scale > 0) {
                    $words .= ' ' . self::getScaleName($scale, $part);
                }

                array_unshift($parts, $words);
            }

            $number = intdiv($number, 1000);
            $scale++;
        }

        return implode(' ', $parts);
    }

    private static function convertGroup(int $number): string
    {
        $parts = [];

        $hundreds = intdiv($number, 100);
        if ($hundreds > 0) {
            $parts[] = self::convertHundreds($hundreds);
        }

        $remainder = $number % 100;
        if ($remainder >= 10 && $remainder < 20) {
            $parts[] = self::TEENS[$remainder - 10];
        } else {
            $tens = intdiv($remainder, 10);
            $ones = $remainder % 10;

            if ($tens > 0) {
                $parts[] = self::TENS[$tens];
            }

            if ($ones > 0) {
                $parts[] = self::ONES[$ones];
            }
        }

        return implode(' ', $parts);
    }

    private static function convertHundreds(int $number): string
    {
        $hundreds = [
            '', 'сто', 'двісті', 'триста', 'чотирьохсот', 'п\'ятисот',
            'шістисот', 'сімисот', 'вісімисот', 'дев\'ятисот'
        ];

        return $hundreds[$number];
    }

    private static function getScaleName(int $scale, int $number): string
    {
        $scales = [
            1 => ['тисяча', 'тисячі', 'тисяч'],
            2 => ['мільйон', 'мільйони', 'мільйонів'],
            3 => ['мільярд', 'мільярди', 'мільярдів'],
            4 => ['трильйон', 'трильйони', 'трильйонів'],
        ];

        if (!isset($scales[$scale])) {
            return '';
        }

        $endingIndex = 2;
        if ($number % 10 === 1 && $number % 100 !== 11) {
            $endingIndex = 0;
        } elseif (in_array($number % 10, [2, 3, 4]) && !in_array($number % 100, [12, 13, 14])) {
            $endingIndex = 1;
        }

        return $scales[$scale][$endingIndex];
    }
}

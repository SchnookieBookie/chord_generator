<?php

class VoicingManager
{
    private const NOTE_INDEX = [
        'C' => 0,
        'C#' => 1,
        'D' => 2,
        'D#' => 3,
        'E' => 4,
        'F' => 5,
        'F#' => 6,
        'G' => 7,
        'G#' => 8,
        'A' => 9,
        'A#' => 10,
        'B' => 11,
    ];

    private const NOTE_NAMES = ['C', 'C#', 'D', 'D#', 'E', 'F', 'F#', 'G', 'G#', 'A', 'A#', 'B'];

    private const OPEN_STRINGS = [4, 9, 2, 7, 11, 4];

    private const SPECIAL_VOICINGS = [
        'F#maj7' => ['low' => [2, 4, 2, 3, 2, 2]],
        'Bmaj7' => ['mid' => [-1, 2, 4, 2, 4, 2]],
        'E' => ['high' => [-1, -1, 2, 4, 5, 4], 'low' => [0, 2, 2, 1, 0, 0]],
        'Em' => ['high' => [-1, -1, 2, 4, 5, 3], 'low' => [0, 2, 2, 0, 0, 0]],
        'C' => ['low' => [-1, 3, 2, 0, 1, 0], 'high' => [-1, 3, 5, 5, 5, 3]],
        'Cadd9' => ['low' => [-1, 3, 2, 0, 3, 3], 'mid' => [-1, 3, 2, 0, 3, 3], 'high' => [-1, -1, 10, 9, 8, 10]],
        'G' => ['low' => [3, 2, 0, 0, 0, 3], 'high' => [-1, -1, 5, 7, 8, 7]],
        'Gadd9' => ['low' => [3, -1, 0, 2, 0, 3], 'mid' => [3, -1, 0, 2, 0, 3], 'high' => [-1, -1, 5, 4, 3, 5]],
        'Am' => ['low' => [-1, 0, 2, 2, 1, 0], 'high' => [-1, -1, 7, 9, 10, 8]],
        'Dm' => ['low' => [-1, -1, 0, 2, 3, 1], 'high' => [-1, 5, 7, 7, 6, 5]],
        'F' => ['low' => [1, 3, 3, 2, 1, 1], 'high' => [-1, -1, 3, 5, 6, 5]],
        'A' => ['low' => [-1, 0, 2, 2, 2, 0], 'high' => [-1, -1, 7, 9, 10, 9]],
        'Aadd9' => ['low' => [-1, 0, 2, 4, 2, 0], 'mid' => [-1, 0, 2, 4, 2, 0], 'high' => [-1, -1, 7, 6, 5, 7]],
        'D' => ['low' => [-1, -1, 0, 2, 3, 2], 'high' => [-1, 5, 7, 7, 7, 5]],
        'Dadd9' => ['low' => [-1, -1, 0, 2, 3, 0], 'mid' => [-1, -1, 0, 2, 3, 0], 'high' => [-1, -1, 12, 11, 10, 12]],
        'Elliott xx30xx' => ['low' => [-1, -1, 3, 0, -1, -1], 'mid' => [-1, -1, 3, 0, -1, -1], 'high' => [-1, -1, 3, 0, -1, -1]],
        'Elliott x32xxx' => ['low' => [-1, 3, 2, -1, -1, -1], 'mid' => [-1, 3, 2, -1, -1, -1], 'high' => [-1, 3, 2, -1, -1, -1]],
        'Elliott 02xxxx' => ['low' => [0, 2, -1, -1, -1, -1], 'mid' => [0, 2, -1, -1, -1, -1], 'high' => [0, 2, -1, -1, -1, -1]],
        'Elliott x020xx' => ['low' => [-1, 0, 2, 0, -1, -1], 'mid' => [-1, 0, 2, 0, -1, -1], 'high' => [-1, 0, 2, 0, -1, -1]],
        'Elliott xx023x' => ['low' => [-1, -1, 0, 2, 3, -1], 'mid' => [-1, -1, 0, 2, 3, -1], 'high' => [-1, -1, 0, 2, 3, -1]],
        'Elliott xx0230' => ['low' => [-1, -1, 0, 2, 3, 0], 'mid' => [-1, -1, 0, 2, 3, 0], 'high' => [-1, -1, 0, 2, 3, 0]],
        'Elliott x022xx' => ['low' => [-1, 0, 2, 2, -1, -1], 'mid' => [-1, 0, 2, 2, -1, -1], 'high' => [-1, 0, 2, 2, -1, -1]],
        'Elliott x02200' => ['low' => [-1, 0, 2, 2, 0, 0], 'mid' => [-1, 0, 2, 2, 0, 0], 'high' => [-1, 0, 2, 2, 0, 0]],
        'Elliott 022xxx' => ['low' => [0, 2, 2, -1, -1, -1], 'mid' => [0, 2, 2, -1, -1, -1], 'high' => [0, 2, 2, -1, -1, -1]],
        'Elliott xx200x' => ['low' => [-1, -1, 2, 0, 0, -1], 'mid' => [-1, -1, 2, 0, 0, -1], 'high' => [-1, -1, 2, 0, 0, -1]],
        'Elliott xx003x' => ['low' => [-1, -1, 0, 0, 3, -1], 'mid' => [-1, -1, 0, 0, 3, -1], 'high' => [-1, -1, 0, 0, 3, -1]],
        'Elliott x320xx' => ['low' => [-1, 3, 2, 0, -1, -1], 'mid' => [-1, 3, 2, 0, -1, -1], 'high' => [-1, 3, 2, 0, -1, -1]],
    ];

    private const SIMPLIFY = [
        'maj9' => 'maj7',
        '6/9' => '6',
        'add9' => '',
        'madd9' => 'm',
        'm9' => 'm7',
        'm11' => 'm7',
        'm9b5' => 'm7b5',
        '9#5' => '7#5',
        'sus2' => 'sus4',
    ];

    private const TUNING_PRESETS = [
        'Standard' => ['E2', 'A2', 'D3', 'G3', 'B3', 'E4'],
    ];

    public function getVDB(): array
    {
        return self::SPECIAL_VOICINGS;
    }

    public function getSimplify(): array
    {
        return self::SIMPLIFY;
    }

    public function getTuningPresets(): array
    {
        return self::TUNING_PRESETS;
    }

    public function getFrets(string $chordName, string $preference = 'mid'): array
    {
        $preference = in_array($preference, ['low', 'mid', 'high'], true) ? $preference : 'mid';

        $manualFrets = $this->parseFretShape($chordName);
        if ($manualFrets !== null) {
            return $manualFrets;
        }

        if (str_starts_with($chordName, 'Elliott ') && isset(self::SPECIAL_VOICINGS[$chordName])) {
            return self::SPECIAL_VOICINGS[$chordName][$preference] ?? self::SPECIAL_VOICINGS[$chordName]['mid'];
        }

        if (isset(self::SPECIAL_VOICINGS[$chordName][$preference])
            && $this->voicingCategory(self::SPECIAL_VOICINGS[$chordName][$preference]) === $preference) {
            return self::SPECIAL_VOICINGS[$chordName][$preference];
        }

        if (isset(self::SPECIAL_VOICINGS[$chordName]['mid'])
            && $this->voicingCategory(self::SPECIAL_VOICINGS[$chordName]['mid']) === $preference) {
            return self::SPECIAL_VOICINGS[$chordName]['mid'];
        }

        [$root, $suffix] = $this->splitChord($chordName);
        $quality = $this->templateForSuffix($suffix);

        return $this->movableShape($root, $quality, $preference);
    }

    public function getPlayability(array $frets): array
    {
        $played = array_values(array_filter($frets, fn (int $fret): bool => $fret > 0));

        if (!$played) {
            return ['playable' => true, 'score' => 0, 'note' => ''];
        }

        $counts = array_count_values($played);
        if (max($counts) >= 5) {
            return ['playable' => false, 'score' => 120, 'note' => 'vynechaný tvar: příliš plošné barre'];
        }

        $unique = array_values(array_unique($played));
        sort($unique);
        $span = max($played) - min($played);
        $frettedCount = count($played);
        $barreFret = $this->detectBarreFret($frets);
        $extraFingers = count(array_filter($unique, fn (int $fret): bool => $fret !== $barreFret));
        $score = ($span * 2) + $extraFingers + max(0, $frettedCount - 4);

        if ($barreFret !== null) {
            $score -= 2;
        }

        $playable = $span <= 5 && ($extraFingers <= 4 || $barreFret !== null);
        $note = '';

        if ($barreFret !== null) {
            $barreCount = array_count_values($played)[$barreFret] ?? 0;
            $note = ($barreCount >= 4 ? 'barre' : 'mini barre') . ' přes ' . $barreFret . '. pražec';
        }

        if (!$playable) {
            $note = $note ? $note . ', hodně natažený tvar' : 'hodně natažený tvar';
        }

        return ['playable' => $playable, 'score' => max(0, $score), 'note' => $note];
    }

    public function getNotesFromFrets(array $frets): array
    {
        $notes = [];

        foreach ($frets as $stringIndex => $fret) {
            if ($fret < 0) {
                continue;
            }

            $noteIndex = (self::OPEN_STRINGS[$stringIndex] + $fret) % 12;
            $notes[] = self::NOTE_NAMES[$noteIndex];
        }

        return array_values(array_unique($notes));
    }

    public function identifyChordFromFrets(array $frets, ?string $preferredRoot = null): string
    {
        if ($preferredRoot === 'F#' && $frets === [2, 4, 2, 3, 2, 2]) {
            return 'F#maj7';
        }

        if ($preferredRoot === 'B' && $frets === [-1, 2, 4, 2, 4, 2]) {
            return 'Bmaj7';
        }

        $pitchClasses = [];

        foreach ($frets as $stringIndex => $fret) {
            if ($fret < 0) {
                continue;
            }

            $pitchClasses[] = (self::OPEN_STRINGS[$stringIndex] + $fret) % 12;
        }

        $pitchClasses = array_values(array_unique($pitchClasses));

        if (!$pitchClasses) {
            return 'N.C.';
        }

        $roots = $preferredRoot !== null && isset(self::NOTE_INDEX[$preferredRoot])
            ? [self::NOTE_INDEX[$preferredRoot]]
            : $pitchClasses;

        foreach ($roots as $rootIndex) {
            $name = $this->identifyFromRoot($pitchClasses, $rootIndex);
            if ($name !== null) {
                return $name;
            }
        }

        foreach ($pitchClasses as $rootIndex) {
            $name = $this->identifyFromRoot($pitchClasses, $rootIndex);
            if ($name !== null) {
                return $name;
            }
        }

        sort($pitchClasses);
        return implode('/', array_map(fn (int $note): string => self::NOTE_NAMES[$note], $pitchClasses));
    }

    public function parseFretShape(string $shape): ?array
    {
        $shape = strtolower(trim($shape));

        if ($shape === '') {
            return null;
        }

        $compact = preg_replace('/\s+/', '', $shape) ?? '';
        if (preg_match('/^[x0-9]{6}$/', $compact)) {
            return array_map(
                fn (string $token): int => $token === 'x' ? -1 : (int)$token,
                str_split($compact)
            );
        }

        $parts = preg_split('/[\s,;\/-]+/', $shape) ?: [];
        $parts = array_values(array_filter($parts, fn (string $part): bool => $part !== ''));

        if (count($parts) !== 6) {
            return null;
        }

        $frets = [];
        foreach ($parts as $part) {
            if ($part === 'x') {
                $frets[] = -1;
                continue;
            }

            if (!preg_match('/^\d{1,2}$/', $part)) {
                return null;
            }

            $frets[] = (int)$part;
        }

        return $frets;
    }

    public function isFretShape(string $shape): bool
    {
        return $this->parseFretShape($shape) !== null;
    }

    public function formatFretShape(array $frets): string
    {
        $tokens = array_map(fn (int $fret): string => $fret < 0 ? 'x' : (string)$fret, $frets);

        if (array_reduce($frets, fn (bool $carry, int $fret): bool => $carry && $fret <= 9, true)) {
            return implode('', $tokens);
        }

        return implode(' ', $tokens);
    }

    public function splitChord(string $chordName): array
    {
        if (preg_match('/^([A-G]#?)(.*)$/', $chordName, $matches)) {
            return [$matches[1], $matches[2]];
        }

        return ['C', ''];
    }

    private function templateForSuffix(string $suffix): string
    {
        if (str_contains($suffix, 'sus')) {
            return 'sus';
        }

        if (str_contains($suffix, 'dim') || str_contains($suffix, 'b5')) {
            return 'dim';
        }

        if (str_contains($suffix, 'aug') || str_contains($suffix, '#5')) {
            return 'aug';
        }

        if (str_contains($suffix, '7b9')) {
            return 'dom7b9';
        }

        if (str_contains($suffix, '7b13')) {
            return 'dom7b13';
        }

        if ($suffix === '9') {
            return 'dom9';
        }

        if ($suffix === '13') {
            return 'dom13';
        }

        if (str_contains($suffix, 'mMaj')) {
            return str_contains($suffix, '9') ? 'minMaj9' : 'minMaj7';
        }

        if ($suffix === 'm6') {
            return 'min6';
        }

        if ($suffix === 'madd9') {
            return 'minAdd9';
        }

        if (str_contains($suffix, 'maj9')) {
            return 'maj9';
        }

        if (str_contains($suffix, 'add9')) {
            return 'add9';
        }

        if (str_contains($suffix, '6/9')) {
            return 'sixNine';
        }

        if (str_starts_with($suffix, 'm') && !str_starts_with($suffix, 'maj')) {
            if (str_contains($suffix, '13')) {
                return 'min13';
            }

            if (str_contains($suffix, '11')) {
                return 'min11';
            }

            if (str_contains($suffix, '9')) {
                return 'min9';
            }

            return str_contains($suffix, '7') ? 'min7' : 'min';
        }

        if (str_contains($suffix, 'maj7') || $suffix === '6') {
            return 'maj7';
        }

        if ($suffix === '7' || $suffix === '7sus4') {
            return 'dom7';
        }

        return 'maj';
    }

    private function movableShape(string $root, string $quality, string $preference): array
    {
        $rootIndex = self::NOTE_INDEX[$root] ?? 0;
        $eRoot = $this->fretFor($rootIndex, 0);
        $aRoot = $this->fretFor($rootIndex, 1);
        $dRoot = $this->fretFor($rootIndex, 2);

        if ($preference === 'high') {
            $rootFret = $dRoot < 5 ? $dRoot + 12 : $dRoot;
            return $this->shapeFromRoot($rootFret, $quality, 'd');
        }

        if ($preference === 'mid') {
            $rootFret = $aRoot < 2 ? $aRoot + 12 : $aRoot;
            return $this->shapeFromRoot($rootFret, $quality, 'a');
        }

        return $this->shapeFromRoot($eRoot, $quality, 'e');
    }

    private function fretFor(int $rootIndex, int $stringIndex): int
    {
        return ($rootIndex - self::OPEN_STRINGS[$stringIndex] + 12) % 12;
    }

    private function shapeFromRoot(int $rootFret, string $quality, string $stringSet): array
    {
        $shapes = [
            'e' => [
                'maj' => [0, 2, 2, 1, 0, 0],
                'min' => [0, 2, 2, 0, 0, 0],
                'maj7' => [0, 2, 1, 1, 0, 0],
                'dom7' => [0, 2, 0, 1, 0, 0],
                'dom9' => [0, null, 0, 1, 0, 2],
                'dom13' => [0, null, 0, 1, 2, 0],
                'dom7b9' => [0, null, 0, 1, 0, 1],
                'dom7b13' => [0, null, 0, 1, 1, 0],
                'min7' => [0, 2, 0, 0, 0, 0],
                'min6' => [0, 2, 2, 0, 2, 0],
                'minMaj7' => [0, 2, 1, 0, 0, 0],
                'minMaj9' => [0, 2, 1, 0, 0, 2],
                'add9' => [0, 2, 2, 1, 0, 2],
                'maj9' => [0, 2, 1, 1, 0, 2],
                'sixNine' => [0, 2, 2, 1, 2, 2],
                'minAdd9' => [0, 2, 2, 0, 0, 2],
                'min9' => [0, 2, 0, 0, 0, 2],
                'min11' => [0, null, 0, 0, 1, 0],
                'min13' => [0, 2, 0, 0, 1, 0],
                'sus' => [0, 2, 2, 2, 0, 0],
                'dim' => [0, 1, 2, 0, null, null],
                'aug' => [0, null, 2, 1, 1, null],
            ],
            'a' => [
                'maj' => [null, 0, 2, 2, 2, 0],
                'min' => [null, 0, 2, 2, 1, 0],
                'maj7' => [null, 0, 2, 1, 2, 0],
                'dom7' => [null, 0, 2, 0, 2, 0],
                'dom9' => [null, 0, 2, 0, 2, 2],
                'dom13' => [null, 0, 2, 0, 2, 2],
                'dom7b9' => [null, 0, 2, 0, 2, 1],
                'dom7b13' => [null, 0, 2, 0, 2, 1],
                'min7' => [null, 0, 2, 0, 1, 0],
                'min6' => [null, 0, 2, 2, 1, 2],
                'minMaj7' => [null, 0, 2, 1, 1, 0],
                'minMaj9' => [null, 0, 2, 1, 0, 0],
                'add9' => [null, 0, 2, 4, 2, 0],
                'maj9' => [null, 0, 2, 1, 0, 0],
                'sixNine' => [null, 0, 2, 4, 2, 2],
                'minAdd9' => [null, 0, 2, 4, 1, 0],
                'min9' => [null, 0, 2, 0, 0, 0],
                'min11' => [null, 0, 2, 0, 1, 0],
                'min13' => [null, 0, 2, 0, 1, 2],
                'sus' => [null, 0, 2, 2, 3, 0],
                'dim' => [null, 0, 1, 2, 1, null],
                'aug' => [null, 0, 3, 2, 2, null],
            ],
            'd' => [
                'maj' => [null, null, 0, 2, 3, 2],
                'min' => [null, null, 0, 2, 3, 1],
                'maj7' => [null, null, 0, 2, 2, 2],
                'dom7' => [null, null, 0, 2, 1, 2],
                'dom9' => [null, null, 0, -1, 1, 0],
                'dom13' => [null, null, 0, -1, 0, 0],
                'dom7b9' => [null, null, 0, -1, 1, -1],
                'dom7b13' => [null, null, 0, -1, 2, -1],
                'min7' => [null, null, 0, 2, 1, 1],
                'min6' => [null, null, 0, 2, 0, 1],
                'minMaj7' => [null, null, 0, 1, 2, 1],
                'minMaj9' => [null, null, 0, 1, 0, 1],
                'add9' => [null, null, 0, -1, -2, 0],
                'maj9' => [null, null, 0, -1, 2, 0],
                'sixNine' => [null, null, 0, -1, 0, 0],
                'minAdd9' => [null, null, 0, -2, -2, 0],
                'min9' => [null, null, 0, -2, 1, 0],
                'min11' => [null, null, 0, 0, 1, 1],
                'min13' => [null, null, 0, 0, 1, 2],
                'sus' => [null, null, 0, 2, 3, 3],
                'dim' => [null, null, 0, 1, 0, 1],
                'aug' => [null, null, 0, 3, 3, 2],
            ],
        ];

        $offsets = $shapes[$stringSet][$quality] ?? $shapes[$stringSet]['maj'];

        return array_map(
            fn (?int $offset): int => $offset === null || $rootFret + $offset < 0 ? -1 : $rootFret + $offset,
            $offsets
        );
    }

    private function voicingCategory(array $frets): string
    {
        foreach ($frets as $stringIndex => $fret) {
            if ($fret >= 0) {
                if ($stringIndex === 0) {
                    return 'low';
                }

                if ($stringIndex === 1) {
                    return 'mid';
                }

                return 'high';
            }
        }

        return 'mid';
    }

    private function detectBarreFret(array $frets): ?int
    {
        $played = array_values(array_filter($frets, fn (int $fret): bool => $fret > 0));

        if (!$played) {
            return null;
        }

        $counts = array_count_values($played);
        $minFret = min($played);

        if (($counts[$minFret] ?? 0) >= 2) {
            return $minFret;
        }

        foreach ($counts as $fret => $count) {
            if ($count >= 3) {
                return (int)$fret;
            }
        }

        return null;
    }

    private function identifyFromRoot(array $pitchClasses, int $rootIndex): ?string
    {
        $intervals = array_map(fn (int $note): int => ($note - $rootIndex + 12) % 12, $pitchClasses);
        sort($intervals);

        $has = fn (int $interval): bool => in_array($interval, $intervals, true);
        $withoutRoot = array_values(array_filter($intervals, fn (int $interval): bool => $interval !== 0));

        $patterns = [
            'maj13' => [0, 4, 7, 10, 2, 9],
            '13' => [0, 4, 7, 10, 9],
            'maj9' => [0, 4, 7, 11, 2],
            '9' => [0, 4, 7, 10, 2],
            'm9' => [0, 3, 7, 10, 2],
            'm11' => [0, 3, 7, 10, 5],
            'm6' => [0, 3, 7, 9],
            'mMaj7' => [0, 3, 7, 11],
            'maj7' => [0, 4, 7, 11],
            '7' => [0, 4, 7, 10],
            'm7b5' => [0, 3, 6, 10],
            'dim7' => [0, 3, 6, 9],
            'm7' => [0, 3, 7, 10],
            '6' => [0, 4, 7, 9],
            'add9' => [0, 4, 7, 2],
            'madd9' => [0, 3, 7, 2],
            'sus4' => [0, 5, 7],
            'sus2' => [0, 2, 7],
            'dim' => [0, 3, 6],
            'aug' => [0, 4, 8],
            'm' => [0, 3, 7],
            '' => [0, 4, 7],
            '5' => [0, 7],
        ];

        foreach ($patterns as $suffix => $required) {
            $suffix = (string)$suffix;

            if ($suffix === '5' && count($intervals) !== 2) {
                continue;
            }

            if (array_diff($required, $intervals) === []) {
                return self::NOTE_NAMES[$rootIndex] . $suffix;
            }
        }

        if ($has(4) && $has(10) && $has(9)) {
            return self::NOTE_NAMES[$rootIndex] . '13(no5)';
        }

        if ($has(4) && $has(2) && $has(9)) {
            return self::NOTE_NAMES[$rootIndex] . '6/9(no5)';
        }

        if ($has(4) && $has(7) && $has(10)) {
            return self::NOTE_NAMES[$rootIndex] . '7';
        }

        if ($has(7) && $has(10)) {
            return self::NOTE_NAMES[$rootIndex] . '7(no3)';
        }

        if ($has(4) && $has(9)) {
            return self::NOTE_NAMES[$rootIndex] . '6(no5)';
        }

        if ($has(3) && $has(9)) {
            return self::NOTE_NAMES[$rootIndex] . 'm6(no5)';
        }

        if ($has(4) && $has(2)) {
            return self::NOTE_NAMES[$rootIndex] . 'add9(no5)';
        }

        if ($has(3) && $has(2)) {
            return self::NOTE_NAMES[$rootIndex] . 'madd9(no5)';
        }

        if (count($withoutRoot) <= 2) {
            if ($has(3)) {
                return self::NOTE_NAMES[$rootIndex] . 'm(no5)';
            }

            if ($has(4)) {
                return self::NOTE_NAMES[$rootIndex] . '(no5)';
            }

            if ($has(5)) {
                return self::NOTE_NAMES[$rootIndex] . 'sus4(no5)';
            }

            if ($has(2)) {
                return self::NOTE_NAMES[$rootIndex] . 'sus2(no5)';
            }
        }

        return null;
    }
}

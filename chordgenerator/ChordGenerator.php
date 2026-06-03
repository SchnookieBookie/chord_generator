<?php

class ChordGenerator
{
    private MusicTheory $musicTheory;

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

    private const OPEN_STRINGS = [4, 9, 2, 7, 11, 4];

    private const ELLIOTT_FRAGMENTS = [
        'Elliott xx30xx' => [-1, -1, 3, 0, -1, -1],
        'Elliott x32xxx' => [-1, 3, 2, -1, -1, -1],
        'Elliott 02xxxx' => [0, 2, -1, -1, -1, -1],
        'Elliott x020xx' => [-1, 0, 2, 0, -1, -1],
        'Elliott xx023x' => [-1, -1, 0, 2, 3, -1],
        'Elliott xx0230' => [-1, -1, 0, 2, 3, 0],
        'Elliott x022xx' => [-1, 0, 2, 2, -1, -1],
        'Elliott x02200' => [-1, 0, 2, 2, 0, 0],
        'Elliott 022xxx' => [0, 2, 2, -1, -1, -1],
        'Elliott xx200x' => [-1, -1, 2, 0, 0, -1],
        'Elliott xx003x' => [-1, -1, 0, 0, 3, -1],
        'Elliott x320xx' => [-1, 3, 2, 0, -1, -1],
    ];

    public function __construct(MusicTheory $musicTheory)
    {
        $this->musicTheory = $musicTheory;
    }

    public function generateProgression(array $opts): array
    {
        $key = $opts['key'] ?? 'C';
        $modes = array_values(array_filter($opts['modes'] ?? ['major']));
        $length = max(1, (int)($opts['length'] ?? 6));
        $allowRepeats = !empty($opts['allowRepeats']);
        $resolveHome = !empty($opts['resolveHome']);
        $startHome = !empty($opts['startHome']);
        $avoidChromaticNeighbors = !empty($opts['avoidChromaticNeighbors']);
        $chordTypes = $opts['chordTypes'] ?? ['triad', 'seventh', 'extended'];
        $borrowPositions = $this->parsePositions($opts['borrowPositions'] ?? '', $length);
        $borrowCount = max(0, min($length, (int)($opts['borrowCount'] ?? 0)));

        while (count($borrowPositions) < $borrowCount) {
            $candidate = random_int(1, $length);
            if (!in_array($candidate, $borrowPositions, true)) {
                $borrowPositions[] = $candidate;
            }
        }

        sort($borrowPositions);

        $difficulty = (string)($opts['difficulty'] ?? 'beginner');
        $primaryMode = $this->normalizePrimaryMode($modes[0] ?? 'major', $difficulty);
        $activeModes = array_values(array_unique(array_merge([$primaryMode], array_slice($modes, 1))));
        $homeRoot = $this->musicTheory->normalizeNote((string)$key);
        $palette = $this->buildPalette($key, $activeModes);
        $borrowKey = (string)($opts['borrowKey'] ?? $key);
        $borrowMode = (string)($opts['borrowMode'] ?? '');
        $borrowPalette = $this->buildBorrowPalette($key, $primaryMode, $modes, $borrowKey, $borrowMode);
        $allowAlteredColor = $borrowCount > 0 || count($borrowPositions) > 0;
        $progression = [];

        for ($slot = 1; $slot <= $length; $slot++) {
            $isLastHome = $resolveHome && $slot === $length;
            $mustStartHome = $startHome && $slot === 1;
            $isBorrowed = in_array($slot, $borrowPositions, true);
            $forcedRoot = ($isLastHome || $mustStartHome) ? $homeRoot : null;
            $slotPalette = $isBorrowed ? $borrowPalette : $palette;
            $source = $slotPalette;

            if ($isLastHome || $mustStartHome) {
                $source = array_values(array_filter(
                    $palette,
                    fn (array $candidate): bool => $candidate['degree'] === 1 && $candidate['mode'] === $primaryMode
                )) ?: $palette;

            } else {
                $source = $this->filterContextualCandidates($source, $homeRoot, $slot, $length, $startHome, $resolveHome, $avoidChromaticNeighbors, $allowAlteredColor);
                if (in_array('elliott', $chordTypes, true)) {
                    $source = array_merge($source, $this->buildElliottFragments($slot, (string)$key, $primaryMode));
                }
            }

            $source = $this->filterHomeRootCandidates($source, $homeRoot, $slot, $length, $startHome, $resolveHome);
            $previous = $progression[count($progression) - 1] ?? null;
            $selectionForcedRoot = $forcedRoot;
            $cycleFallbackSource = $source;
            $safeSlotPalette = $this->filterHomeRootCandidates($slotPalette, $homeRoot, $slot, $length, $startHome, $resolveHome);
            $source = $this->filterRepeatedCandidates($source, $progression, $allowRepeats, $safeSlotPalette, $forcedRoot);

            if (!$source) {
                if (!$allowRepeats) {
                    $source = $cycleFallbackSource;
                }

                if (!$source && $forcedRoot !== null && !$allowRepeats) {
                    $fallbackBase = ($isLastHome || $mustStartHome) ? $palette : $slotPalette;
                    $source = $this->filterContextualCandidates($fallbackBase, $homeRoot, $slot, $length, $startHome, $resolveHome, $avoidChromaticNeighbors, $allowAlteredColor);
                    $source = $this->filterHomeRootCandidates($source, $homeRoot, $slot, $length, $startHome, $resolveHome);
                    $source = $this->filterRepeatedCandidates($source, $progression, $allowRepeats, $source, null);
                    $selectionForcedRoot = null;
                }

                if (!$source) {
                    break;
                }
            }

            if ($avoidChromaticNeighbors && $previous) {
                $neighborSafeSource = $this->filterChromaticNeighborCandidates($source, $previous);

                if ($neighborSafeSource) {
                    $source = $neighborSafeSource;
                } else {
                    $fallbackBase = ($isLastHome || $mustStartHome) ? $palette : $slotPalette;
                    $fallbackSource = $this->filterContextualCandidates($fallbackBase, $homeRoot, $slot, $length, $startHome, $resolveHome, $avoidChromaticNeighbors, $allowAlteredColor);
                    $fallbackSource = $this->filterHomeRootCandidates($fallbackSource, $homeRoot, $slot, $length, $startHome, $resolveHome);
                    $fallbackSource = $this->filterRepeatedCandidates($fallbackSource, $progression, $allowRepeats, $fallbackBase, null);
                    $source = $this->filterChromaticNeighborCandidates($fallbackSource, $previous);
                    $selectionForcedRoot = null;

                    if (!$source) {
                        $safePalette = $this->filterHomeRootCandidates($palette, $homeRoot, $slot, $length, $startHome, $resolveHome);
                        $unusedSlotPalette = $this->filterRepeatedCandidates($safeSlotPalette, $progression, $allowRepeats, [], null);
                        $unusedPalette = $this->filterRepeatedCandidates($safePalette, $progression, $allowRepeats, [], null);
                        $source = $this->filterChromaticNeighborCandidates($unusedSlotPalette, $previous)
                            ?: $unusedSlotPalette
                            ?: $this->filterChromaticNeighborCandidates($unusedPalette, $previous)
                            ?: $unusedPalette
                            ?: $this->filterChromaticNeighborCandidates($safeSlotPalette ?: $safePalette, $previous);
                    }
                }

                if (!$source) {
                    break;
                }
            }

            $usedNames = array_map(fn (array $chord): string => (string)$chord['name'], $progression);
            $candidateSource = $source;
            $candidate = null;
            $suffix = '';
            $name = '';
            $attempts = max(1, count($candidateSource));

            for ($attempt = 0; $attempt < $attempts; $attempt++) {
                $candidate = $this->pickWeighted($candidateSource, $previous, $progression, $allowRepeats, $avoidChromaticNeighbors, $slot, $length, $resolveHome, $selectionForcedRoot);

                if (!$this->isHomeRootAllowed($candidate, $homeRoot, $slot, $length, $startHome, $resolveHome)) {
                    $candidateSource = $this->filterHomeRootCandidates($candidateSource, $homeRoot, $slot, $length, $startHome, $resolveHome);
                    if (!$candidateSource) {
                        break;
                    }

                    $selectionForcedRoot = null;
                    continue;
                }

                $suffix = ($candidate['chordKind'] ?? '') === 'elliott'
                    ? ''
                    : $this->pickSuffix($candidate['chordKind'] ?? $candidate['quality'], $candidate['degree'], $slot, $length, $chordTypes, $difficulty, $allowRepeats ? [] : $usedNames, (string)$candidate['root']);
                if ($isLastHome && $this->musicTheory->normalizeNote((string)$candidate['root']) === $homeRoot) {
                    $suffix = $this->forcedHomeSuffix($primaryMode, $suffix);
                }
                $name = $candidate['name'] ?? ($candidate['root'] . $suffix);

                if ($allowRepeats || !in_array($name, $usedNames, true)) {
                    break;
                }

                $candidateSource = $this->removeChordNameCandidate($candidateSource, $candidate, $chordTypes, $difficulty, $slot, $length, $primaryMode, $isLastHome, $homeRoot, $usedNames);
                if (!$candidateSource) {
                    break;
                }
            }

            if (!$candidate) {
                break;
            }

            if (!$this->isHomeRootAllowed($candidate, $homeRoot, $slot, $length, $startHome, $resolveHome)) {
                $source = $this->filterHomeRootCandidates($source, $homeRoot, $slot, $length, $startHome, $resolveHome);
                if (!$source) {
                    break;
                }

                $candidate = $this->pickWeighted($source, $previous, $progression, $allowRepeats, $avoidChromaticNeighbors, $slot, $length, $resolveHome, null);
                $suffix = ($candidate['chordKind'] ?? '') === 'elliott'
                    ? ''
                    : $this->pickSuffix($candidate['chordKind'] ?? $candidate['quality'], $candidate['degree'], $slot, $length, $chordTypes, $difficulty, $allowRepeats ? [] : $usedNames, (string)$candidate['root']);
                if ($isLastHome && $this->musicTheory->normalizeNote((string)$candidate['root']) === $homeRoot) {
                    $suffix = $this->forcedHomeSuffix($primaryMode, $suffix);
                }
                $name = $candidate['name'] ?? ($candidate['root'] . $suffix);
            }

            $progression[] = [
                'root' => $candidate['root'],
                'suffix' => $suffix,
                'name' => $name,
                'degree' => $candidate['degree'],
                'roman' => $candidate['roman'],
                'quality' => $candidate['quality'],
                'chordKind' => $candidate['chordKind'] ?? $candidate['quality'],
                'mode' => $candidate['mode'],
                'borrowed' => $isBorrowed,
            ];
        }

        if ($resolveHome && $progression) {
            $last = $progression[count($progression) - 1];
            $usedRoots = array_map(fn (array $chord): string => $this->repeatSignature($chord), $progression);
            $homeSignature = 'root:' . $homeRoot;

            if (count($progression) < $length
                && $this->musicTheory->normalizeNote((string)$last['root']) !== $homeRoot
                && ($allowRepeats || !in_array($homeSignature, $usedRoots, true))) {
                $homeCandidates = array_values(array_filter(
                    $palette,
                    fn (array $candidate): bool => $candidate['degree'] === 1 && $candidate['mode'] === $primaryMode
                ));
                $homeCandidate = $homeCandidates[0] ?? null;

                if ($homeCandidate && (!$avoidChromaticNeighbors || !$this->isChromaticNeighborMove($last, $homeCandidate))) {
                    $suffix = $this->forcedHomeSuffix(
                        $primaryMode,
                        $this->pickSuffix($homeCandidate['chordKind'] ?? $homeCandidate['quality'], 1, $length, $length, $chordTypes, $difficulty)
                    );
                    $progression[] = [
                        'root' => $homeCandidate['root'],
                        'suffix' => $suffix,
                        'name' => $homeCandidate['root'] . $suffix,
                        'degree' => 1,
                        'roman' => $homeCandidate['roman'],
                        'quality' => $homeCandidate['quality'],
                        'chordKind' => $homeCandidate['chordKind'] ?? $homeCandidate['quality'],
                        'mode' => $homeCandidate['mode'],
                        'borrowed' => false,
                    ];
                }
            }
        }

        return [
            'progression' => $progression,
            'usedModes' => array_values(array_unique(array_column($progression, 'mode'))),
            'borrowPositions' => $borrowPositions,
        ];
    }

    private function buildPalette(string $key, array $modes): array
    {
        $palette = [];

        foreach ($modes as $rank => $mode) {
            foreach ($this->musicTheory->getDiatonicChords($key, $mode) as $chord) {
                $chord['modeRank'] = $rank;
                $palette[] = $chord;
            }
        }

        return $palette;
    }

    private function normalizePrimaryMode(string $mode, string $difficulty): string
    {
        if ($difficulty === 'beginner' && in_array($mode, ['harmonic_minor', 'melodic_minor'], true)) {
            return 'minor';
        }

        return $mode;
    }

    private function buildBorrowPalette(string $key, string $primaryMode, array $selectedModes, string $borrowKey, string $borrowMode): array
    {
        if ($borrowMode !== '') {
            $palette = $this->buildPalette($borrowKey, [$borrowMode]);
            $primarySignatures = array_map(
                fn (array $chord): string => $chord['root'] . ':' . $chord['quality'],
                $this->musicTheory->getDiatonicChords($key, $primaryMode)
            );

            return array_values(array_filter(
                $palette,
                fn (array $chord): bool => !in_array($chord['root'] . ':' . $chord['quality'], $primarySignatures, true)
            )) ?: $palette;
        }

        $modes = array_keys($this->musicTheory->getModes());
        $borrowModes = array_values(array_unique(array_merge($selectedModes, $modes)));
        $primarySignatures = array_map(
            fn (array $chord): string => $chord['root'] . ':' . $chord['quality'],
            $this->musicTheory->getDiatonicChords($key, $primaryMode)
        );
        $palette = [];

        foreach ($borrowModes as $mode) {
            foreach ($this->musicTheory->getDiatonicChords($key, $mode) as $chord) {
                $signature = $chord['root'] . ':' . $chord['quality'];

                if ($mode !== $primaryMode && !in_array($signature, $primarySignatures, true)) {
                    $chord['mode'] = $mode;
                    $palette[] = $chord;
                }
            }
        }

        return $palette ?: $this->buildPalette($key, [$primaryMode]);
    }

    private function filterRepeatedCandidates(array $source, array $progression, bool $allowRepeats, array $fallbackPalette = [], ?string $forcedRoot = null): array
    {
        if (!$progression) {
            return $source;
        }

        $blocked = $this->blockedRepeatSignatures($progression, $allowRepeats);
        $filtered = array_values(array_filter(
            $source,
            fn (array $candidate): bool => !in_array($this->repeatSignature($candidate), $blocked, true)
        ));

        if ($filtered) {
            return $filtered;
        }

        if ($fallbackPalette) {
            $fallback = array_values(array_filter(
                $fallbackPalette,
                fn (array $candidate): bool => !in_array($this->repeatSignature($candidate), $blocked, true)
            ));

            if ($fallback) {
                return $fallback;
            }
        }

        return [];
    }

    private function blockedRepeatSignatures(array $progression, bool $allowRepeats): array
    {
        $relevant = $allowRepeats ? array_slice($progression, -2) : $progression;

        return array_map(fn (array $chord): string => $this->repeatSignature($chord), $relevant);
    }

    private function isForcedRoot(array $candidate, ?string $forcedRoot): bool
    {
        return $forcedRoot !== null
            && $this->musicTheory->normalizeNote((string)($candidate['root'] ?? '')) === $forcedRoot;
    }

    private function hasUnusedRoot(array $source, array $progression): bool
    {
        $used = array_map(fn (array $chord): string => $this->repeatSignature($chord), $progression);

        foreach ($source as $candidate) {
            if (!in_array($this->repeatSignature($candidate), $used, true)) {
                return true;
            }
        }

        return false;
    }

    private function hasAllowedNeighbor(array $source, array $previous): bool
    {
        foreach ($source as $candidate) {
            if (!$this->isForbiddenMove($previous, $candidate, true)) {
                return true;
            }
        }

        return false;
    }

    private function filterChromaticNeighborCandidates(array $source, array $previous): array
    {
        return array_values(array_filter(
            $source,
            fn (array $candidate): bool => !$this->isChromaticNeighborMove($previous, $candidate)
        ));
    }

    private function filterHomeRootCandidates(array $source, string $homeRoot, int $slot, int $length, bool $startHome, bool $resolveHome): array
    {
        if (($startHome && $slot === 1) || ($resolveHome && $slot === $length)) {
            return $source;
        }

        return array_values(array_filter(
            $source,
            fn (array $candidate): bool => $this->isHomeRootAllowed($candidate, $homeRoot, $slot, $length, $startHome, $resolveHome)
        ));
    }

    private function isHomeRootAllowed(array $candidate, string $homeRoot, int $slot, int $length, bool $startHome, bool $resolveHome): bool
    {
        if (!isset($candidate['root']) || $candidate['root'] === '') {
            return true;
        }

        if ($this->musicTheory->normalizeNote((string)$candidate['root']) !== $homeRoot) {
            return true;
        }

        return ($startHome && $slot === 1) || ($resolveHome && $slot === $length);
    }

    private function removeChordNameCandidate(array $source, array $rejected, array $chordTypes, string $difficulty, int $slot, int $length, string $primaryMode, bool $isLastHome, string $homeRoot, array $usedNames): array
    {
        return array_values(array_filter(
            $source,
            function (array $candidate) use ($rejected, $chordTypes, $difficulty, $slot, $length, $primaryMode, $isLastHome, $homeRoot, $usedNames): bool {
                if ($this->repeatSignature($candidate) !== $this->repeatSignature($rejected)
                    || ($candidate['chordKind'] ?? $candidate['quality']) !== ($rejected['chordKind'] ?? $rejected['quality'])
                    || ($candidate['mode'] ?? '') !== ($rejected['mode'] ?? '')) {
                    return true;
                }

                if (($candidate['chordKind'] ?? '') === 'elliott') {
                    return !in_array((string)($candidate['name'] ?? ''), $usedNames, true);
                }

                foreach ($this->suffixChoices($candidate['chordKind'] ?? $candidate['quality'], (int)$candidate['degree'], $slot, $length, $chordTypes, $difficulty) as $suffix) {
                    if ($isLastHome && $this->musicTheory->normalizeNote((string)$candidate['root']) === $homeRoot) {
                        $suffix = $this->forcedHomeSuffix($primaryMode, $suffix);
                    }

                    if (!in_array((string)$candidate['root'] . $suffix, $usedNames, true)) {
                        return true;
                    }
                }

                return false;
            }
        ));
    }

    private function repeatSignature(array $chord): string
    {
        if (($chord['chordKind'] ?? '') === 'elliott') {
            return 'elliott:' . (string)($chord['name'] ?? '');
        }

        $root = (string)($chord['root'] ?? '');

        if ($root !== '') {
            return 'root:' . $this->musicTheory->normalizeNote($root);
        }

        return 'name:' . (string)($chord['name'] ?? '');
    }

    private function buildElliottFragments(int $slot, string $key, string $mode): array
    {
        $scalePitchClasses = array_map(
            fn (string $note): int => self::NOTE_INDEX[$this->musicTheory->normalizeNote($note)],
            $this->musicTheory->getScale($key, $mode)
        );
        $fragments = [];

        foreach (self::ELLIOTT_FRAGMENTS as $name => $frets) {
            if (!$this->fragmentFitsScale($frets, $scalePitchClasses)) {
                continue;
            }

            $fragments[] = [
                'root' => '',
                'suffix' => '',
                'name' => $name,
                'degree' => 0,
                'roman' => 'fragment',
                'quality' => 'fragment',
                'chordKind' => 'elliott',
                'mode' => 'elliott',
                'borrowed' => false,
                'fragmentWeight' => $slot === 1 ? 6 : 12 + (count($fragments) % 2),
            ];
        }

        return $fragments;
    }

    private function fragmentFitsScale(array $frets, array $scalePitchClasses): bool
    {
        foreach ($frets as $stringIndex => $fret) {
            if ($fret < 0) {
                continue;
            }

            $pitchClass = (self::OPEN_STRINGS[$stringIndex] + $fret) % 12;
            if (!in_array($pitchClass, $scalePitchClasses, true)) {
                return false;
            }
        }

        return true;
    }

    private function filterContextualCandidates(array $source, string $homeRoot, int $slot, int $length, bool $startHome, bool $resolveHome, bool $avoidChromaticNeighbors, bool $allowAlteredColor): array
    {
        $filtered = array_values(array_filter(
            $source,
            function (array $candidate) use ($homeRoot, $slot, $length, $startHome, $resolveHome, $avoidChromaticNeighbors, $allowAlteredColor): bool {
                $kind = $candidate['chordKind'] ?? $candidate['quality'];
                $root = $this->musicTheory->normalizeNote((string)$candidate['root']);

                if (!$allowAlteredColor && $kind === 'altered') {
                    return false;
                }

                if ($root === $homeRoot) {
                    $allowedAsStart = $startHome && $slot === 1;
                    $allowedAsEnd = $resolveHome && $slot === $length;

                    if (!$allowedAsStart && !$allowedAsEnd) {
                        return false;
                    }
                }

                if (in_array($kind, ['dim', 'halfdim'], true)) {
                    return in_array((int)$candidate['degree'], [2, 7], true);
                }

                return true;
            }
        ));

        if ($filtered) {
            return $filtered;
        }

        return array_values(array_filter(
            $source,
            fn (array $candidate): bool => !in_array($candidate['chordKind'] ?? $candidate['quality'], ['dim', 'halfdim', 'altered'], true)
        )) ?: $source;
    }

    private function pickWeighted(array $palette, ?array $previous, array $progression, bool $allowRepeats, bool $avoidChromaticNeighbors, int $slot, int $length, bool $resolveHome, ?string $forcedRoot): array
    {
        $weighted = [];
        $total = 0;
        $blocked = $this->blockedRepeatSignatures($progression, $allowRepeats);

        foreach ($palette as $candidate) {
            if (in_array($this->repeatSignature($candidate), $blocked, true)) {
                continue;
            }

            if ($previous && $avoidChromaticNeighbors && $this->isChromaticNeighborMove($previous, $candidate)) {
                continue;
            }

            if ($previous && !$this->isForcedRoot($candidate, $forcedRoot) && $this->isForbiddenMove($previous, $candidate, $avoidChromaticNeighbors)) {
                continue;
            }

            $weight = 10;
            $kind = $candidate['chordKind'] ?? $candidate['quality'];

            if ($kind === 'elliott') {
                $weight = (int)($candidate['fragmentWeight'] ?? 10);
            }

            if ($previous && $kind !== 'elliott' && ($previous['chordKind'] ?? '') !== 'elliott') {
                $distance = $this->musicTheory->fifthDistance($previous['root'], $candidate['root']);
                $semitones = $this->musicTheory->semitoneDistance($previous['root'], $candidate['root']);
                $weight += max(0, 20 - ($distance * 4));

                if ($this->musicTheory->isFifthResolution($previous['root'], $candidate['root'])) {
                    $weight += 34;
                }

                $weight += $this->functionalMoveWeight((int)$previous['degree'], (int)$candidate['degree']);

                if (!$avoidChromaticNeighbors && $semitones <= 2) {
                    $weight += 8;
                } elseif ($avoidChromaticNeighbors && $semitones === 2) {
                    $weight += 4;
                } elseif ($semitones === 6) {
                    $weight -= 12;
                }
            }

            if ($kind === 'elliott') {
                $total += $weight;
                $weighted[] = ['weight' => $weight, 'chord' => $candidate];
                continue;
            }

            if (in_array($candidate['degree'], [2, 4, 5, 6, 7], true)) {
                $weight += 7;
            }

            $rank = (int)($candidate['modeRank'] ?? 0);
            if ($rank === 0) {
                $weight += 8;
            } elseif ($rank === 1) {
                $weight += 1;
            } else {
                $weight -= 3;
            }

            if ($kind === 'dom' || $kind === 'domAltered' || $kind === 'domLydian') {
                $weight += 5;
            }

            if ($slot === $length - 1 && (int)$candidate['degree'] === 7) {
                $weight -= 30;
            }

            if ($resolveHome && $slot === $length - 1) {
                if ((int)$candidate['degree'] === 5) {
                    $weight += 42;
                } elseif ((int)$candidate['degree'] === 2) {
                    $weight += 22;
                } elseif ((int)$candidate['degree'] === 7) {
                    $weight -= 15;
                }
            } elseif ($slot === $length - 1 && in_array($candidate['degree'], [2, 5], true)) {
                $weight += 14;
            }

            $weight = max(1, $weight);
            $total += $weight;
            $weighted[] = ['weight' => $weight, 'chord' => $candidate];
        }

        if (!$weighted) {
            if ($previous) {
                $fallback = array_values(array_filter(
                    $palette,
                    fn (array $candidate): bool => !in_array($this->repeatSignature($candidate), $blocked, true)
                        && (!$avoidChromaticNeighbors || !$this->isChromaticNeighborMove($previous, $candidate))
                        && ($this->isForcedRoot($candidate, $forcedRoot) || !$this->isForbiddenMove($previous, $candidate, $avoidChromaticNeighbors))
                ));

                if ($fallback) {
                    return $fallback[array_rand($fallback)];
                }
            }

            $nonBlocked = array_values(array_filter(
                $palette,
                fn (array $candidate): bool => !in_array($this->repeatSignature($candidate), $blocked, true)
            ));

            return ($nonBlocked ?: $palette)[array_rand($nonBlocked ?: $palette)];
        }

        $roll = random_int(1, $total);
        $cursor = 0;

        foreach ($weighted as $entry) {
            $cursor += $entry['weight'];
            if ($roll <= $cursor) {
                return $entry['chord'];
            }
        }

        return $weighted[count($weighted) - 1]['chord'];
    }

    private function isChromaticNeighborMove(array $previous, array $candidate): bool
    {
        if (!isset($previous['root'], $candidate['root'])) {
            return false;
        }

        return $this->musicTheory->semitoneDistance((string)$previous['root'], (string)$candidate['root']) === 1;
    }

    private function isForbiddenMove(array $previous, array $candidate, bool $avoidChromaticNeighbors): bool
    {
        $from = (int)$previous['degree'];
        $to = (int)$candidate['degree'];
        $toKind = $candidate['chordKind'] ?? $candidate['quality'];

        if ($avoidChromaticNeighbors && $this->isChromaticNeighborMove($previous, $candidate)) {
            return true;
        }

        if ($from === 5 && $to === 7) {
            return true;
        }

        if (in_array($toKind, ['dim', 'halfdim'], true) && !in_array($to, [2, 7], true)) {
            return true;
        }

        return false;
    }

    private function functionalMoveWeight(int $from, int $to): int
    {
        $strong = [
            1 => [4 => 20, 6 => 18, 2 => 14, 5 => 12],
            2 => [5 => 36, 7 => 10],
            3 => [6 => 26, 4 => 12],
            4 => [5 => 26, 1 => 18, 2 => 10],
            5 => [1 => 42, 6 => 18],
            6 => [2 => 28, 4 => 18, 5 => 8],
            7 => [1 => 36, 3 => 10],
        ];

        if (isset($strong[$from][$to])) {
            return $strong[$from][$to];
        }

        if (abs($from - $to) === 1) {
            return 5;
        }

        if ($from === 5 && in_array($to, [3, 7], true)) {
            return -35;
        }

        if ($from === 1 && $to === 7) {
            return -22;
        }

        return -6;
    }

    private function forcedHomeSuffix(string $primaryMode, string $generatedSuffix): string
    {
        if ($primaryMode === 'major') {
            return '';
        }

        if (in_array($primaryMode, ['harmonic_minor', 'melodic_minor'], true)) {
            return 'm';
        }

        return $generatedSuffix;
    }

    private function pickSuffix(string $chordKind, int $degree, int $slot, int $length, array $allowedTypes, string $difficulty, array $usedNames = [], string $root = ''): string
    {
        $choices = $this->suffixChoices($chordKind, $degree, $slot, $length, $allowedTypes, $difficulty);

        if ($root !== '' && $usedNames) {
            $unused = array_values(array_filter(
                $choices,
                fn (string $suffix): bool => !in_array($root . $suffix, $usedNames, true)
            ));

            if ($unused) {
                $choices = $unused;
            }
        }

        return $choices[array_rand($choices)];
    }

    private function suffixChoices(string $chordKind, int $degree, int $slot, int $length, array $allowedTypes, string $difficulty): array
    {
        if ($difficulty === 'beginner') {
            $allowedTypes = array_values(array_intersect($allowedTypes, ['triad', 'seventh']));
            if (!$allowedTypes) {
                $allowedTypes = ['triad'];
            }
        }

        $library = [
            'maj' => [
                'triad' => [''],
                'seventh' => ['maj7', '6'],
                'extended' => ['add9', 'maj9', '6/9'],
                'color' => ['sus2', 'sus4'],
            ],
            'majLydian' => [
                'triad' => [''],
                'seventh' => ['maj7', '6'],
                'extended' => ['maj9', '6/9'],
                'color' => ['sus2'],
            ],
            'min' => [
                'triad' => ['m'],
                'seventh' => ['m7'],
                'extended' => ['m9', 'madd9'],
                'color' => ['sus2'],
            ],
            'dom' => [
                'triad' => [''],
                'seventh' => ['7'],
                'extended' => ['9', '13'],
                'color' => ['sus4', '7sus4'],
            ],
            'domLydian' => [
                'triad' => [''],
                'seventh' => ['7'],
                'extended' => ['9', '13'],
                'color' => ['sus4'],
            ],
            'domAltered' => [
                'triad' => [''],
                'seventh' => ['7'],
                'extended' => ['7b9', '7b13'],
                'color' => ['7sus4'],
            ],
            'halfdim' => [
                'triad' => ['dim'],
                'seventh' => ['m7b5'],
                'extended' => ['m9b5'],
                'color' => ['m7b5'],
            ],
            'dim' => [
                'triad' => ['dim'],
                'seventh' => ['dim7'],
                'extended' => ['dim7'],
                'color' => ['dim7'],
            ],
            'aug' => [
                'triad' => ['aug'],
                'seventh' => ['aug'],
                'extended' => ['aug'],
                'color' => ['aug'],
            ],
            'augMaj' => [
                'triad' => ['aug'],
                'seventh' => ['aug'],
                'extended' => ['aug'],
                'color' => ['aug'],
            ],
            'minMaj' => [
                'triad' => ['m'],
                'seventh' => ['m'],
                'extended' => ['madd9'],
                'color' => ['m'],
            ],
            'altered' => [
                'triad' => ['dim'],
                'seventh' => ['7b5'],
                'extended' => ['7b9', '7b13'],
                'color' => ['7b5'],
            ],
        ];

        $choices = [];
        foreach ($allowedTypes as $type) {
            $choices = array_merge($choices, $library[$chordKind][$type] ?? []);
        }

        if (!$choices) {
            $choices = $library[$chordKind]['triad'] ?? [''];
        }

        if ($slot === $length && $degree === 1) {
            $stable = [
                'maj' => ['', 'maj7', '6'],
                'majLydian' => ['', 'maj7'],
                'min' => ['m'],
                'minMaj' => ['m'],
                'dom' => ['7', '9'],
            ];
            $preferred = array_values(array_intersect($stable[$chordKind] ?? $choices, $choices));
            if ($preferred) {
                $choices = $preferred;
            }
        }

        return $choices;
    }

    private function parsePositions(string $value, int $length): array
    {
        $value = trim($value);
        $positions = [];

        if (preg_match('/^\d{2,}$/', $value)) {
            foreach (str_split($value) as $part) {
                $position = (int)$part;
                if ($position >= 1 && $position <= $length && !in_array($position, $positions, true)) {
                    $positions[] = $position;
                }
            }

            return $positions;
        }

        foreach (preg_split('/[^0-9]+/', $value) ?: [] as $part) {
            if ($part === '') {
                continue;
            }

            $position = (int)$part;
            if ($position >= 1 && $position <= $length && !in_array($position, $positions, true)) {
                $positions[] = $position;
            }
        }

        return $positions;
    }
}

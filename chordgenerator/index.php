<?php
require_once __DIR__ . '/MusicTheory.php';
require_once __DIR__ . '/ChordGenerator.php';
require_once __DIR__ . '/VoicingManager.php';
require_once __DIR__ . '/DiagramRenderer.php';
require_once __DIR__ . '/AudioPlayer.php';

$musicTheory = new MusicTheory();
$voicingManager = new VoicingManager();
$diagramRenderer = new DiagramRenderer($voicingManager);
$chordGenerator = new ChordGenerator($musicTheory);
$audioPlayer = new AudioPlayer();
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

function post_value(string $key, mixed $default): mixed
{
    return $_POST[$key] ?? $default;
}

function is_checked(string $key, string $value = '1'): string
{
    $posted = $_POST[$key] ?? null;

    if (is_array($posted)) {
        return in_array($value, $posted, true) ? 'checked' : '';
    }

    return (string)$posted === $value ? 'checked' : '';
}

function is_selected(string $key, string $value, mixed $default = null): string
{
    $posted = $_POST[$key] ?? $default;

    if (is_array($posted)) {
        return in_array($value, $posted, true) ? 'selected' : '';
    }

    return (string)$posted === $value ? 'selected' : '';
}

function parse_positions(string $value, int $length): array
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

function normalize_chord_name(string $name): string
{
    $name = preg_replace('/\s+/', '', trim($name)) ?? '';

    if (!preg_match('/^([A-Ga-g])(#?)(.*)$/', $name, $matches)) {
        return '';
    }

    return strtoupper($matches[1]) . $matches[2] . $matches[3];
}

function quality_from_suffix(string $suffix): string
{
    if (str_contains($suffix, 'dim') || str_contains($suffix, 'b5')) {
        return 'dim';
    }

    if (str_contains($suffix, 'aug') || str_contains($suffix, '#5')) {
        return 'aug';
    }

    if (str_starts_with($suffix, 'm') && !str_starts_with($suffix, 'maj')) {
        return 'min';
    }

    return 'maj';
}

function chord_kind_from_suffix(string $suffix): string
{
    if (str_contains($suffix, 'dim7')) {
        return 'dim';
    }

    if (str_contains($suffix, 'b5')) {
        return 'halfdim';
    }

    if (str_contains($suffix, '7b9') || str_contains($suffix, '7b13')) {
        return 'domAltered';
    }

    if ($suffix === '7' || $suffix === '9' || $suffix === '13' || str_contains($suffix, 'sus')) {
        return 'dom';
    }

    if (str_contains($suffix, 'mMaj')) {
        return 'minMaj';
    }

    return quality_from_suffix($suffix);
}

function chord_from_name(string $name, array $fallback, VoicingManager $voicingManager): array
{
    $manualFrets = $voicingManager->parseFretShape($name);
    if ($manualFrets !== null) {
        $identifiedName = $voicingManager->identifyChordFromFrets($manualFrets);

        return [
            'root' => '',
            'suffix' => '',
            'name' => $voicingManager->formatFretShape($manualFrets),
            'degree' => (int)($fallback['degree'] ?? 1),
            'roman' => (string)($fallback['roman'] ?? ''),
            'quality' => 'manual',
            'chordKind' => 'manual',
            'mode' => (string)($fallback['mode'] ?? 'major'),
            'borrowed' => !empty($fallback['borrowed']),
            'manualLabel' => $identifiedName,
        ];
    }

    $normalized = normalize_chord_name($name);

    if ($normalized === '') {
        return $fallback;
    }

    [$root, $suffix] = $voicingManager->splitChord($normalized);

    return [
        'root' => $root,
        'suffix' => $suffix,
        'name' => $root . $suffix,
        'degree' => (int)($fallback['degree'] ?? 1),
        'roman' => (string)($fallback['roman'] ?? ''),
        'quality' => quality_from_suffix($suffix),
        'chordKind' => chord_kind_from_suffix($suffix),
        'mode' => (string)($fallback['mode'] ?? 'major'),
        'borrowed' => !empty($fallback['borrowed']),
    ];
}

function normalize_primary_mode_for_ui(string $mode, string $difficulty): string
{
    if ($difficulty === 'beginner' && in_array($mode, ['harmonic_minor', 'melodic_minor'], true)) {
        return 'minor';
    }

    return $mode;
}

function mode_slots(string $mode1, string $mode2, string $mode3, string $difficulty): array
{
    return [
        normalize_primary_mode_for_ui($mode1, $difficulty),
        $mode2,
        $mode3,
    ];
}

function normalize_edit_value(string $value): string
{
    return strtolower((string)(preg_replace('/\s+/', '', trim($value)) ?? ''));
}

function replacement_suffix(string $chordKind, string $oldSuffix): string
{
    $wants13 = str_contains($oldSuffix, '13');
    $wants11 = str_contains($oldSuffix, '11');
    $wants9 = str_contains($oldSuffix, '9');
    $wants7 = str_contains($oldSuffix, '7') || $wants9 || $wants11 || $wants13;

    return match ($chordKind) {
        'min' => $wants13 ? 'm13' : ($wants11 ? 'm11' : ($wants9 ? 'm9' : ($wants7 ? 'm7' : 'm'))),
        'maj', 'majLydian' => $wants9 ? 'maj9' : ($wants7 ? 'maj7' : ''),
        'dom', 'domLydian' => $wants13 ? '13' : ($wants9 ? '9' : '7'),
        'halfdim' => 'm7b5',
        'dim' => str_contains($oldSuffix, 'dim7') ? 'dim7' : 'dim',
        'aug', 'augMaj' => str_contains($oldSuffix, 'maj7') ? 'maj7#5' : 'aug',
        'minMaj' => $wants9 ? 'mMaj9' : 'mMaj7',
        'domAltered', 'altered' => str_contains($oldSuffix, '13') ? '7b13' : '7b9',
        default => '',
    };
}

function replacement_chord_for_mode(array $fallback, string $newMode, string $key, MusicTheory $musicTheory): array
{
    $degree = max(1, min(7, (int)($fallback['degree'] ?? 1)));
    $chords = $musicTheory->getDiatonicChords($key, $newMode);
    $candidate = $chords[$degree - 1] ?? ($chords[0] ?? null);

    if ($candidate === null) {
        return $fallback;
    }

    $suffix = replacement_suffix((string)($candidate['chordKind'] ?? $candidate['quality']), (string)($fallback['suffix'] ?? ''));

    return [
        'root' => $candidate['root'],
        'suffix' => $suffix,
        'name' => $candidate['root'] . $suffix,
        'degree' => $candidate['degree'],
        'roman' => $candidate['roman'],
        'quality' => $candidate['quality'],
        'chordKind' => $candidate['chordKind'] ?? $candidate['quality'],
        'mode' => $newMode,
        'borrowed' => !empty($fallback['borrowed']),
    ];
}

function replacement_mode_for_changed_slot(string $oldMode, array $previousModeSlots, array $currentModeSlots): ?string
{
    foreach ($previousModeSlots as $slot => $previousMode) {
        if ($previousMode === '' || $previousMode !== $oldMode) {
            continue;
        }

        $currentMode = (string)($currentModeSlots[$slot] ?? '');
        if ($currentMode !== '' && $currentMode !== $previousMode) {
            return $currentMode;
        }
    }

    return null;
}

function restore_progression_from_post(
    VoicingManager $voicingManager,
    MusicTheory $musicTheory,
    array $currentModeSlots,
    string $key,
    bool $applyModeChanges
): ?array
{
    $names = $_POST['chordName'] ?? null;

    if (!is_array($names) || !$names) {
        return null;
    }

    $edited = $_POST['editedChord'] ?? [];
    $displayed = $_POST['displayedChord'] ?? [];
    $previousModeSlots = $_POST['previousModeSlot'] ?? [];
    $previousModeSlots = is_array($previousModeSlots) ? array_values(array_pad(array_slice($previousModeSlots, 0, 3), 3, '')) : ['', '', ''];
    $progression = [];

    foreach ($names as $index => $name) {
        $fallback = [
            'root' => (string)($_POST['chordRoot'][$index] ?? 'C'),
            'suffix' => (string)($_POST['chordSuffix'][$index] ?? ''),
            'name' => (string)$name,
            'degree' => (int)($_POST['chordDegree'][$index] ?? 1),
            'roman' => (string)($_POST['chordRoman'][$index] ?? ''),
            'quality' => (string)($_POST['chordQuality'][$index] ?? 'maj'),
            'chordKind' => (string)($_POST['chordKind'][$index] ?? ($_POST['chordQuality'][$index] ?? 'maj')),
            'mode' => (string)($_POST['chordMode'][$index] ?? 'major'),
            'borrowed' => (string)($_POST['chordBorrowed'][$index] ?? '0') === '1',
        ];

        $editedValue = (string)($edited[$index] ?? $name);
        $displayedValue = (string)($displayed[$index] ?? $name);
        $wasUserEdited = normalize_edit_value($editedValue) !== normalize_edit_value((string)$name)
            && normalize_edit_value($editedValue) !== normalize_edit_value($displayedValue);
        $newMode = $applyModeChanges && !$wasUserEdited
            ? replacement_mode_for_changed_slot($fallback['mode'], $previousModeSlots, $currentModeSlots)
            : null;

        $progression[] = $newMode !== null
            ? replacement_chord_for_mode($fallback, $newMode, $key, $musicTheory)
            : chord_from_name($editedValue, $fallback, $voicingManager);
    }

    return [
        'progression' => $progression,
        'usedModes' => array_values(array_unique(array_column($progression, 'mode'))),
        'borrowPositions' => [],
    ];
}

function voicing_center(array $frets): float
{
    $played = array_values(array_filter($frets, fn (int $fret): bool => $fret > 0));

    if (!$played) {
        return 0;
    }

    return array_sum($played) / count($played);
}

function voicing_span(array $frets): int
{
    $played = array_values(array_filter($frets, fn (int $fret): bool => $fret > 0));

    if (!$played) {
        return 0;
    }

    return max($played) - min($played);
}

function choose_voicing(
    string $chordName,
    int $position,
    string $difficulty,
    array $highPositions,
    array $lowPositions,
    VoicingManager $voicingManager,
    ?float &$previousCenter
): string {
    $profiles = [
        'beginner' => ['allowed' => ['low', 'mid'], 'jump' => 12, 'height' => 2.4, 'span' => 8, 'cap' => 7, 'jumpLimit' => 5],
        'expert' => ['allowed' => ['low', 'mid', 'high'], 'jump' => 7, 'height' => 1.1, 'span' => 4, 'cap' => 12, 'jumpLimit' => 7],
        'mustaine' => ['allowed' => ['low', 'mid', 'high'], 'jump' => 2, 'height' => 0.2, 'span' => 1, 'cap' => 24, 'jumpLimit' => 9],
    ];
    $profile = $profiles[$difficulty] ?? $profiles['beginner'];

    if (in_array($position, $highPositions, true)) {
        $frets = $voicingManager->getFrets($chordName, 'high');
        $center = voicing_center($frets);

        if ($previousCenter === null || abs($center - $previousCenter) <= $profile['jumpLimit']) {
            $previousCenter = $center;
            return 'high';
        }
    }

    if (in_array($position, $lowPositions, true)) {
        $frets = $voicingManager->getFrets($chordName, 'low');
        $center = voicing_center($frets);

        if ($previousCenter === null || abs($center - $previousCenter) <= $profile['jumpLimit']) {
            $previousCenter = $center;
            return 'low';
        }
    }

    $best = 'mid';
    $bestScore = INF;
    $closest = 'mid';
    $closestScore = INF;
    $candidates = $profile['allowed'];

    foreach ($candidates as $candidate) {
        $frets = $voicingManager->getFrets($chordName, $candidate);
        $playability = $voicingManager->getPlayability($frets);
        $played = array_values(array_filter($frets, fn (int $fret): bool => $fret > 0));

        if (!$played) {
            continue;
        }

        $center = voicing_center($frets);
        $maxFret = max($played);
        $jump = $previousCenter === null ? 0 : abs($center - $previousCenter);
        $score = $center * $profile['height'];
        $score += voicing_span($frets) * $profile['span'];
        $score += $playability['score'] * 6;

        $score += $jump * $profile['jump'];

        if (!$playability['playable']) {
            $score += 80;
        }

        if ($maxFret > $profile['cap']) {
            $score += ($maxFret - $profile['cap']) * 14;
        }

        if ($jump < $closestScore) {
            $closest = $candidate;
            $closestScore = $jump;
        }

        if ($jump <= $profile['jumpLimit'] && $score < $bestScore) {
            $best = $candidate;
            $bestScore = $score;
        }
    }

    if ($bestScore === INF) {
        $best = $closest;
    }

    $previousCenter = voicing_center($voicingManager->getFrets($chordName, $best));
    return $best;
}

function voicing_profile(string $difficulty): array
{
    $profiles = [
        'beginner' => ['allowed' => ['low', 'mid'], 'jump' => 12, 'height' => 2.4, 'span' => 8, 'cap' => 7, 'jumpLimit' => 5],
        'expert' => ['allowed' => ['low', 'mid', 'high'], 'jump' => 7, 'height' => 1.1, 'span' => 4, 'cap' => 12, 'jumpLimit' => 7],
        'mustaine' => ['allowed' => ['low', 'mid', 'high'], 'jump' => 2, 'height' => 0.2, 'span' => 1, 'cap' => 24, 'jumpLimit' => 9],
    ];

    return $profiles[$difficulty] ?? $profiles['beginner'];
}

function displayed_repeat_signature(string $name): string
{
    if (str_starts_with($name, 'Elliott ')) {
        return 'elliott:' . $name;
    }

    if (preg_match('/^([A-G]#?)/', $name, $matches)) {
        return 'root:' . $matches[1];
    }

    return 'name:' . $name;
}

function voicing_options(
    string $chordName,
    int $position,
    string $difficulty,
    array $highPositions,
    array $lowPositions,
    VoicingManager $voicingManager
): array {
    $profile = voicing_profile($difficulty);
    $preferences = array_values(array_unique(array_merge($profile['allowed'], ['low', 'mid', 'high'])));

    if (in_array($position, $highPositions, true)) {
        $preferences = ['high'];
    } elseif (in_array($position, $lowPositions, true)) {
        $preferences = ['low'];
    }

    $options = [];
    foreach ($preferences as $preference) {
        $frets = $voicingManager->getFrets($chordName, $preference);
        [$preferredRoot] = $voicingManager->splitChord($chordName);
        $preferredRoot = str_starts_with($chordName, 'Elliott ') ? null : $preferredRoot;
        $identifiedName = $voicingManager->identifyChordFromFrets($frets, $preferredRoot);
        $playability = $voicingManager->getPlayability($frets);
        $played = array_values(array_filter($frets, fn (int $fret): bool => $fret > 0));

        if (!$played) {
            continue;
        }

        $center = voicing_center($frets);
        $maxFret = max($played);
        $score = $center * $profile['height'];
        $score += voicing_span($frets) * $profile['span'];
        $score += $playability['score'] * 6;

        if (!$playability['playable']) {
            $score += 80;
        }

        if ($maxFret > $profile['cap']) {
            $score += ($maxFret - $profile['cap']) * 14;
        }

        $options[] = [
            'preference' => $preference,
            'center' => $center,
            'score' => $score,
            'repeatSignature' => displayed_repeat_signature($identifiedName),
        ];
    }

    return $options ?: [['preference' => 'mid', 'center' => 0, 'score' => INF]];
}

function plan_voicings(
    array $progression,
    string $difficulty,
    array $highPositions,
    array $midPositions,
    array $lowPositions,
    VoicingManager $voicingManager
): array {
    $profile = voicing_profile($difficulty);
    $layers = [];

    foreach ($progression as $index => $chord) {
        $position = $index + 1;
        if (in_array($position, $midPositions, true)) {
            $layers[] = [[
                'preference' => 'mid',
                'center' => voicing_center($voicingManager->getFrets((string)$chord['name'], 'mid')),
                'score' => 0,
                'repeatSignature' => displayed_repeat_signature($voicingManager->identifyChordFromFrets(
                    $voicingManager->getFrets((string)$chord['name'], 'mid'),
                    str_starts_with((string)$chord['name'], 'Elliott ') ? null : (string)$chord['root']
                )),
            ]];
            continue;
        }

        $layers[] = voicing_options((string)$chord['name'], $position, $difficulty, $highPositions, $lowPositions, $voicingManager);
    }

    $states = [];
    foreach ($layers[0] ?? [] as $optionIndex => $option) {
        $states[$optionIndex] = [
            'score' => $option['score'],
            'previous' => null,
            'recent' => [$option['repeatSignature'] ?? ''],
        ];
    }
    $history = [$states];

    for ($layerIndex = 1; $layerIndex < count($layers); $layerIndex++) {
        $nextStates = [];

        foreach ($layers[$layerIndex] as $optionIndex => $option) {
            $bestScore = INF;
            $bestPrevious = null;

            foreach ($states as $previousIndex => $state) {
                $previous = $layers[$layerIndex - 1][$previousIndex];
                $jump = abs($option['center'] - $previous['center']);
                $signature = $option['repeatSignature'] ?? '';

                if ($signature !== '' && in_array($signature, $state['recent'] ?? [], true)) {
                    continue;
                }

                if ($jump > $profile['jumpLimit']) {
                    continue;
                }

                $score = $state['score'] + $option['score'] + ($jump * $profile['jump']);
                if ($score < $bestScore) {
                    $bestScore = $score;
                    $bestPrevious = $previousIndex;
                }
            }

            if ($bestPrevious !== null) {
                $recent = $states[$bestPrevious]['recent'] ?? [];
                $recent[] = $option['repeatSignature'] ?? '';
                $nextStates[$optionIndex] = [
                    'score' => $bestScore,
                    'previous' => $bestPrevious,
                    'recent' => array_slice(array_values(array_filter($recent)), -2),
                ];
            }
        }

        if (!$nextStates) {
            $previousCenter = $layers[$layerIndex - 1][array_key_first($states)]['center'] ?? null;
            $fallback = 'mid';
            $fallbackJump = INF;

            foreach ($layers[$layerIndex] as $option) {
                $jump = $previousCenter === null ? 0 : abs($option['center'] - $previousCenter);
                if ($jump < $fallbackJump) {
                    $fallback = $option['preference'];
                    $fallbackJump = $jump;
                }
            }

            return array_pad(
                array_map(fn (array $layer): string => $layer[0]['preference'] ?? 'mid', array_slice($layers, 0, $layerIndex)),
                count($layers),
                $fallback
            );
        }

        $states = $nextStates;
        $history[$layerIndex] = $states;
    }

    if (!$states) {
        return array_map(fn (array $layer): string => $layer[0]['preference'] ?? 'mid', $layers);
    }

    $bestFinal = array_key_first($states);
    foreach ($states as $optionIndex => $state) {
        if ($state['score'] < $states[$bestFinal]['score']) {
            $bestFinal = $optionIndex;
        }
    }

    $planned = array_fill(0, count($layers), 'mid');
    for ($layerIndex = count($layers) - 1; $layerIndex >= 0; $layerIndex--) {
        $planned[$layerIndex] = $layers[$layerIndex][$bestFinal]['preference'];
        $bestFinal = $history[$layerIndex][$bestFinal]['previous'] ?? 0;
    }

    return $planned;
}

function voicing_label(string $voicing): string
{
    return [
        'low' => 'nízko',
        'mid' => 'střed',
        'high' => 'vysoko',
    ][$voicing] ?? $voicing;
}

function shift_voicing(string $voicing, string $direction): string
{
    $order = ['low', 'mid', 'high'];
    $index = array_search($voicing, $order, true);

    if ($index === false) {
        $index = 1;
    }

    $index += $direction === 'down' ? -1 : 1;
    $index = max(0, min(count($order) - 1, $index));

    return $order[$index];
}

function scale_summaries(string $key, array $modeSlots, MusicTheory $musicTheory): array
{
    $labels = ['Primární', 'Sekundární', 'Terciální'];
    $summaries = [];

    foreach ($modeSlots as $slot => $mode) {
        if ($mode === '') {
            continue;
        }

        $summaries[] = [
            'slot' => $slot,
            'label' => $labels[$slot] ?? 'Škála',
            'mode' => $mode,
            'modeLabel' => $musicTheory->getModeLabel($mode),
            'notes' => $musicTheory->getScale($key, $mode),
        ];
    }

    return $summaries;
}

function note_scale_class(string $note, array $scaleSummaries): string
{
    $slots = [];

    foreach ($scaleSummaries as $summary) {
        if (in_array($note, $summary['notes'], true)) {
            $slots[] = (int)$summary['slot'];
        }
    }

    if (count($slots) >= 3) {
        return 'scale-note-all';
    }

    if (count($slots) === 2) {
        return 'scale-note-two';
    }

    return match ($slots[0] ?? -1) {
        0 => 'scale-note-primary',
        1 => 'scale-note-secondary',
        2 => 'scale-note-tertiary',
        default => 'scale-note-muted',
    };
}

function fretboard_rows(string $key, array $scaleSummaries, MusicTheory $musicTheory): array
{
    $strings = [
        ['label' => 'E', 'note' => 'E'],
        ['label' => 'B', 'note' => 'B'],
        ['label' => 'G', 'note' => 'G'],
        ['label' => 'D', 'note' => 'D'],
        ['label' => 'A', 'note' => 'A'],
        ['label' => 'E', 'note' => 'E'],
    ];
    $rows = [];

    foreach ($strings as $string) {
        $frets = [];
        for ($fret = 0; $fret <= 21; $fret++) {
            $note = $musicTheory->transpose($string['note'], $fret);
            $isInScale = false;

            foreach ($scaleSummaries as $summary) {
                if (in_array($note, $summary['notes'], true)) {
                    $isInScale = true;
                    break;
                }
            }

            $frets[] = [
                'fret' => $fret,
                'note' => $note,
                'class' => $isInScale ? note_scale_class($note, $scaleSummaries) : 'scale-note-muted',
                'active' => $isInScale,
                'root' => $note === $musicTheory->normalizeNote($key),
            ];
        }

        $rows[] = [
            'label' => $string['label'],
            'frets' => $frets,
        ];
    }

    return $rows;
}

$length = max(1, (int)post_value('length', 6));
$mode1 = (string)post_value('mode1', 'major');
$mode2 = (string)post_value('mode2', '');
$mode3 = (string)post_value('mode3', '');
$availableModes = array_keys($musicTheory->getModes());
if (!in_array($mode1, $availableModes, true)) {
    $mode1 = 'major';
}
if ($mode2 !== '' && !in_array($mode2, $availableModes, true)) {
    $mode2 = '';
}
if ($mode3 !== '' && !in_array($mode3, $availableModes, true)) {
    $mode3 = '';
}
$selectedModes = array_values(array_filter([$mode1, $mode2, $mode3]));

$selectedChordTypes = post_value('chordTypes', ['triad', 'seventh', 'extended']);
if (!is_array($selectedChordTypes) || !$selectedChordTypes) {
    $selectedChordTypes = ['triad'];
}

$difficulty = (string)post_value('difficulty', 'beginner');
if (!in_array($difficulty, ['beginner', 'expert', 'mustaine'], true)) {
    $difficulty = 'beginner';
}
$currentModeSlots = mode_slots($mode1, $mode2, $mode3, $difficulty);

$borrowMode = (string)post_value('borrowMode', '');
if ($borrowMode !== '' && !in_array($borrowMode, $availableModes, true)) {
    $borrowMode = '';
}

$options = [
    'key' => post_value('key', 'C'),
    'modes' => array_slice($selectedModes, 0, 3),
    'length' => $length,
    'allowRepeats' => isset($_POST['allowRepeats']),
    'avoidChromaticNeighbors' => isset($_POST['avoidChromaticNeighbors']) || $requestMethod !== 'POST',
    'startHome' => isset($_POST['startHome']),
    'resolveHome' => isset($_POST['resolveHome']) || $requestMethod !== 'POST',
    'chordTypes' => $selectedChordTypes,
    'difficulty' => $difficulty,
    'borrowCount' => post_value('borrowCount', 0),
    'borrowPositions' => post_value('borrowPositions', ''),
    'borrowKey' => post_value('borrowKey', post_value('key', 'C')),
    'borrowMode' => $borrowMode,
];

$action = (string)post_value('action', 'generate');
if (post_value('voicingAction', '') !== '') {
    $action = 'update_progression';
}
$applyModeChanges = $action === 'update_progression' && post_value('voicingAction', '') === '';
$restored = $action === 'update_progression'
    ? restore_progression_from_post($voicingManager, $musicTheory, $currentModeSlots, (string)$options['key'], $applyModeChanges)
    : null;
$result = $restored ?? $chordGenerator->generateProgression($options);
$length = count($result['progression']);
$highPositionsValue = (string)post_value('highPositions', '');
$midPositionsValue = (string)post_value('midPositions', '');
$lowPositionsValue = (string)post_value('lowPositions', '');
$highPositions = parse_positions($highPositionsValue, $length);
$midPositions = parse_positions($midPositionsValue, $length);
$lowPositions = parse_positions($lowPositionsValue, $length);
$difficultyLabels = [
    'beginner' => 'Začátečník',
    'expert' => 'Znalec',
    'mustaine' => 'Dave Mustaine',
];
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ChordForge</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <main class="app">
        <section class="workspace">
            <form class="controls" method="post">
                <div class="brand">
                    <div class="logo">ChordForge</div>
                    <div class="tagline">Generátor muzikálnějších kytarových postupů</div>
                </div>

                <div class="panel">
                    <h2>Tónina a stupnice</h2>
                    <label>
                        Tónina
                        <select name="key">
                            <?php foreach ($musicTheory->getNotes() as $note): ?>
                                <option value="<?= htmlspecialchars($note) ?>" <?= is_selected('key', $note, 'C') ?>><?= htmlspecialchars($note) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>
                        Stupnice v jedné sekvenci
                        <select name="mode1">
                            <?php foreach ($musicTheory->getModes() as $mode => $data): ?>
                                <option value="<?= htmlspecialchars($mode) ?>" <?= $mode1 === $mode ? 'selected' : '' ?>><?= htmlspecialchars($data['label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>
                        Sekundarni dominantni stupnice
                        <select name="mode2">
                            <option value="" <?= $mode2 === '' ? 'selected' : '' ?>>Nepoužít</option>
                            <?php foreach ($musicTheory->getModes() as $mode => $data): ?>
                                <option value="<?= htmlspecialchars($mode) ?>" <?= $mode2 === $mode ? 'selected' : '' ?>><?= htmlspecialchars($data['label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>
                        Treti dominantni stupnice
                        <select name="mode3">
                            <option value="" <?= $mode3 === '' ? 'selected' : '' ?>>Nepoužít</option>
                            <?php foreach ($musicTheory->getModes() as $mode => $data): ?>
                                <option value="<?= htmlspecialchars($mode) ?>" <?= $mode3 === $mode ? 'selected' : '' ?>><?= htmlspecialchars($data['label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>

                <div class="panel">
                    <h2>Generování</h2>
                    <label>
                        Počet akordů
                        <input type="number" name="length" min="1" value="<?= htmlspecialchars((string)$length) ?>">
                    </label>
                    <label>
                        Obtížnost
                        <select name="difficulty">
                            <?php foreach ($difficultyLabels as $value => $label): ?>
                                <option value="<?= htmlspecialchars($value) ?>" <?= is_selected('difficulty', $value, 'beginner') ?>><?= htmlspecialchars($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <div class="switch-row">
                        <label><input type="checkbox" name="allowRepeats" <?= isset($_POST['allowRepeats']) ? 'checked' : '' ?>> Povolit opakování</label>
                    <label><input type="checkbox" name="avoidChromaticNeighbors" <?= isset($_POST['avoidChromaticNeighbors']) || $requestMethod !== 'POST' ? 'checked' : '' ?>> Zakázat půltónové sousedy</label>
                    <label><input type="checkbox" name="startHome" <?= isset($_POST['startHome']) ? 'checked' : '' ?>> Začít doma</label>
                    <label><input type="checkbox" name="resolveHome" <?= isset($_POST['resolveHome']) || $requestMethod !== 'POST' ? 'checked' : '' ?>> Skončit doma</label>
                </div>

                    <div class="checkbox-grid">
                        <label><input type="checkbox" name="chordTypes[]" value="triad" <?= is_checked('chordTypes', 'triad') ?: ($requestMethod !== 'POST' ? 'checked' : '') ?>> Triády</label>
                    <label><input type="checkbox" name="chordTypes[]" value="seventh" <?= is_checked('chordTypes', 'seventh') ?: ($requestMethod !== 'POST' ? 'checked' : '') ?>> Septakordy</label>
                    <label><input type="checkbox" name="chordTypes[]" value="extended" <?= is_checked('chordTypes', 'extended') ?: ($requestMethod !== 'POST' ? 'checked' : '') ?>> Rozšířené akordy</label>
                    <label><input type="checkbox" name="chordTypes[]" value="color" <?= is_checked('chordTypes', 'color') ?>> Sus a barevné akordy</label>
                    <label><input type="checkbox" name="chordTypes[]" value="elliott" <?= is_checked('chordTypes', 'elliott') ?>> Elliott poloakordy</label>
                </div>
                </div>

                <div class="panel">
                    <h2>Voicingy a barvy</h2>
                    <label>
                        Vynutit vysoké voicingy na pozicích
                        <input type="text" name="highPositions" value="<?= htmlspecialchars($highPositionsValue) ?>" placeholder="napr. 1 3 5 nebo 135">
                    </label>
                    <label>
                        Vynutit střední voicingy na pozicích
                        <input type="text" name="midPositions" value="<?= htmlspecialchars($midPositionsValue) ?>" placeholder="napr. 2 4 nebo 24">
                    </label>
                    <label>
                        Vynutit nízké voicingy na pozicích
                        <input type="text" name="lowPositions" value="<?= htmlspecialchars($lowPositionsValue) ?>" placeholder="napr. 2 4 6 nebo 246">
                    </label>
                    <label>
                        Počet akordů mimo domácí barvu
                        <input type="number" name="borrowCount" min="0" value="<?= htmlspecialchars((string)post_value('borrowCount', 0)) ?>">
                    </label>
                    <label>
                        Přesné pozice mimo tóninu
                        <input type="text" name="borrowPositions" value="<?= htmlspecialchars((string)post_value('borrowPositions', '')) ?>" placeholder="napr. 4 7 nebo 47">
                    </label>
                    <label>
                        Klíč akordů mimo barvu
                        <select name="borrowKey">
                            <?php foreach ($musicTheory->getNotes() as $note): ?>
                                <option value="<?= htmlspecialchars($note) ?>" <?= is_selected('borrowKey', $note, (string)$options['key']) ?>><?= htmlspecialchars($note) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>
                        Mód akordů mimo barvu
                        <select name="borrowMode">
                            <option value="" <?= $borrowMode === '' ? 'selected' : '' ?>>Automaticky</option>
                            <?php foreach ($musicTheory->getModes() as $mode => $data): ?>
                                <option value="<?= htmlspecialchars($mode) ?>" <?= $borrowMode === $mode ? 'selected' : '' ?>><?= htmlspecialchars($data['label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>

                <button class="generate-btn" type="submit" name="action" value="generate">Generovat postup</button>

                <section class="results">
                    <div class="summary">
                        <div>
                            <span class="eyebrow">Výsledek</span>
                            <h1><?= htmlspecialchars((string)$options['key']) ?> · <?= htmlspecialchars(implode(' + ', array_map(fn ($mode) => $musicTheory->getModeLabel($mode), $options['modes']))) ?></h1>
                        </div>
                        <div class="used-modes">
                            <span><?= htmlspecialchars($difficultyLabels[$difficulty]) ?></span>
                            <?php foreach ($result['usedModes'] as $mode): ?>
                                <span><?= htmlspecialchars($musicTheory->getModeLabel($mode)) ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <?php
                        $plannedVoicings = plan_voicings($result['progression'], $difficulty, $highPositions, $midPositions, $lowPositions, $voicingManager);
                        $postedVoicings = $_POST['selectedVoicing'] ?? [];
                        if ($action === 'update_progression' && is_array($postedVoicings)) {
                            foreach ($postedVoicings as $voicingIndex => $postedVoicing) {
                                if (isset($plannedVoicings[$voicingIndex]) && in_array($postedVoicing, ['low', 'mid', 'high'], true)) {
                                    $plannedVoicings[$voicingIndex] = $postedVoicing;
                                }
                            }
                        }

                        $voicingAction = (string)post_value('voicingAction', '');
                        if (preg_match('/^(\d+):(down|up)$/', $voicingAction, $voicingMatches)) {
                            $voicingIndex = (int)$voicingMatches[1];
                            if (isset($plannedVoicings[$voicingIndex])) {
                                $plannedVoicings[$voicingIndex] = shift_voicing($plannedVoicings[$voicingIndex], $voicingMatches[2]);
                            }
                        }

                        $displayNames = [];

                        foreach ($result['progression'] as $index => $chord) {
                            $voicing = $plannedVoicings[$index] ?? 'mid';
                            $frets = $voicingManager->getFrets((string)$chord['name'], $voicing);
                            $preferredRoot = str_starts_with((string)$chord['name'], 'Elliott ') || $voicingManager->isFretShape((string)$chord['name']) ? null : (string)$chord['root'];
                            $displayNames[$index] = $voicingManager->identifyChordFromFrets($frets, $preferredRoot);
                        }
                    ?>

                    <div class="progression-line">
                        <?php foreach ($result['progression'] as $index => $chord): ?>
                            <span class="<?= $chord['borrowed'] ? 'borrowed' : '' ?>"><?= htmlspecialchars($displayNames[$index] ?? $chord['name']) ?></span>
                        <?php endforeach; ?>
                    </div>

                    <div class="chord-grid">
                        <?php foreach ($result['progression'] as $index => $chord): ?>
                            <?php
                                $position = $index + 1;
                                $voicing = $plannedVoicings[$index] ?? 'mid';
                                $diagram = $diagramRenderer->drawDiagram($chord['name'], $voicing, $chord['borrowed']);
                                $shownName = $diagram['identifiedName'] ?? ($displayNames[$index] ?? $chord['name']);
                            ?>
                            <article class="chord-card <?= $chord['borrowed'] ? 'is-borrowed' : '' ?>">
                                <input type="hidden" name="chordRoot[]" value="<?= htmlspecialchars($chord['root']) ?>">
                                <input type="hidden" name="chordSuffix[]" value="<?= htmlspecialchars($chord['suffix']) ?>">
                                <input type="hidden" name="chordName[]" value="<?= htmlspecialchars($chord['name']) ?>">
                                <input type="hidden" name="chordDegree[]" value="<?= htmlspecialchars((string)$chord['degree']) ?>">
                                <input type="hidden" name="chordRoman[]" value="<?= htmlspecialchars($chord['roman']) ?>">
                                <input type="hidden" name="chordQuality[]" value="<?= htmlspecialchars($chord['quality']) ?>">
                                <input type="hidden" name="chordKind[]" value="<?= htmlspecialchars($chord['chordKind'] ?? $chord['quality']) ?>">
                                <input type="hidden" name="chordMode[]" value="<?= htmlspecialchars($chord['mode']) ?>">
                                <input type="hidden" name="chordBorrowed[]" value="<?= $chord['borrowed'] ? '1' : '0' ?>">
                                <input type="hidden" name="selectedVoicing[]" value="<?= htmlspecialchars($voicing) ?>">
                                <input type="hidden" name="displayedChord[]" value="<?= htmlspecialchars($shownName) ?>">

                                <div class="card-top">
                                    <div>
                                        <div class="position"><?= $position ?>.</div>
                                        <h3><?= htmlspecialchars($shownName) ?></h3>
                                    </div>
                                    <div class="voicing-controls" aria-label="Voicing">
                                        <button type="submit" name="voicingAction" value="<?= $index ?>:down" title="Nizsi voicing" <?= $voicing === 'low' ? 'disabled' : '' ?>>&larr;</button>
                                        <span class="voicing-pill"><?= htmlspecialchars(voicing_label($voicing)) ?></span>
                                        <button type="submit" name="voicingAction" value="<?= $index ?>:up" title="Vyssi voicing" <?= $voicing === 'high' ? 'disabled' : '' ?>>&rarr;</button>
                                    </div>
                                </div>
                                <?= $diagram['svg'] ?>
                                <label class="chord-edit">
                                    Akord / prazce
                                    <input type="text" name="editedChord[]" value="<?= htmlspecialchars($voicingManager->isFretShape((string)$chord['name']) ? (string)$chord['name'] : $shownName) ?>" autocomplete="off">
                                </label>
                                <div class="meta">
                                    <span><?= htmlspecialchars($chord['roman']) ?></span>
                                    <span><?= htmlspecialchars($musicTheory->getModeLabel($chord['mode'])) ?></span>
                                    <span><?= htmlspecialchars(implode(' ', $diagram['notes'] ?? [])) ?></span>
                                    <span><?= htmlspecialchars($diagram['voicingStr']) ?></span>
                                    <?php if ($diagram['fingeringNote'] !== ''): ?>
                                        <span><?= htmlspecialchars($diagram['fingeringNote']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>

                    <button class="update-btn" type="submit" name="action" value="update_progression">Překreslit upravené akordy</button>
                    <?php foreach ($currentModeSlots as $modeSlot): ?>
                        <input type="hidden" name="previousModeSlot[]" value="<?= htmlspecialchars($modeSlot) ?>">
                    <?php endforeach; ?>

                    <?php
                        $scaleSummaries = scale_summaries((string)$options['key'], $currentModeSlots, $musicTheory);
                    ?>
                    <?php if ($scaleSummaries): ?>
                        <section class="scale-overview" aria-label="Použité škály">
                            <div class="scale-legend" aria-label="Legenda barev">
                                <span><i class="scale-note-primary"></i> primární</span>
                                <span><i class="scale-note-secondary"></i> sekundární</span>
                                <span><i class="scale-note-tertiary"></i> terciální</span>
                                <span><i class="scale-note-two"></i> průnik dvou</span>
                                <span><i class="scale-note-all"></i> průnik tří</span>
                            </div>
                            <div class="scale-list">
                                <?php foreach ($scaleSummaries as $summary): ?>
                                    <div class="scale-row scale-row-<?= (int)$summary['slot'] + 1 ?>">
                                        <span><?= htmlspecialchars($summary['label']) ?></span>
                                        <strong><?= htmlspecialchars($summary['modeLabel']) ?></strong>
                                        <span><?= htmlspecialchars(implode(' ', $summary['notes'])) ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="scale-fretboard" aria-label="Noty škál na hmatníku">
                                <div class="fret-numbers" aria-hidden="true">
                                    <span></span>
                                    <?php for ($fret = 0; $fret <= 21; $fret++): ?>
                                        <span><?= $fret ?></span>
                                    <?php endfor; ?>
                                </div>
                                <div class="fretboard-strings">
                                    <?php foreach (fretboard_rows((string)$options['key'], $scaleSummaries, $musicTheory) as $row): ?>
                                        <div class="fretboard-row">
                                            <span class="string-name"><?= htmlspecialchars($row['label']) ?></span>
                                            <?php foreach ($row['frets'] as $fretNote): ?>
                                                <span class="fret-cell <?= $fretNote['fret'] === 0 ? 'is-open' : '' ?>">
                                                    <?php if ($fretNote['active']): ?>
                                                        <span class="fret-note <?= htmlspecialchars($fretNote['class']) ?> <?= $fretNote['root'] ? 'is-root' : '' ?>">
                                                            <?= htmlspecialchars($fretNote['note']) ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </section>
                    <?php endif; ?>
                </section>
            </form>
        </section>
    </main>
    <script src="assets/script.js"></script>
</body>
</html>

<?php

class MusicTheory
{
    private const NOTES = ['C', 'C#', 'D', 'D#', 'E', 'F', 'F#', 'G', 'G#', 'A', 'A#', 'B'];

    private const MODES = [
        'major' => [
            'label' => 'Jónská / dur',
            'intervals' => [0, 2, 4, 5, 7, 9, 11],
            'qualities' => ['maj', 'min', 'min', 'maj', 'maj', 'min', 'dim'],
            'chordKinds' => ['maj', 'min', 'min', 'maj', 'dom', 'min', 'halfdim'],
        ],
        'minor' => [
            'label' => 'Aiolská / přirozená moll',
            'intervals' => [0, 2, 3, 5, 7, 8, 10],
            'qualities' => ['min', 'dim', 'maj', 'min', 'min', 'maj', 'maj'],
            'chordKinds' => ['min', 'halfdim', 'maj', 'min', 'min', 'maj', 'dom'],
        ],
        'dorian' => [
            'label' => 'Dórská',
            'intervals' => [0, 2, 3, 5, 7, 9, 10],
            'qualities' => ['min', 'min', 'maj', 'maj', 'min', 'dim', 'maj'],
            'chordKinds' => ['min', 'min', 'maj', 'dom', 'min', 'halfdim', 'maj'],
        ],
        'phrygian' => [
            'label' => 'Frygická',
            'intervals' => [0, 1, 3, 5, 7, 8, 10],
            'qualities' => ['min', 'maj', 'maj', 'min', 'dim', 'maj', 'min'],
            'chordKinds' => ['min', 'maj', 'dom', 'min', 'halfdim', 'maj', 'min'],
        ],
        'mixolydian' => [
            'label' => 'Mixolydická',
            'intervals' => [0, 2, 4, 5, 7, 9, 10],
            'qualities' => ['maj', 'min', 'dim', 'maj', 'min', 'min', 'maj'],
            'chordKinds' => ['dom', 'min', 'halfdim', 'maj', 'min', 'min', 'maj'],
        ],
        'lydian' => [
            'label' => 'Lydická',
            'intervals' => [0, 2, 4, 6, 7, 9, 11],
            'qualities' => ['maj', 'maj', 'min', 'dim', 'maj', 'min', 'min'],
            'chordKinds' => ['majLydian', 'domLydian', 'min', 'halfdim', 'maj', 'min', 'min'],
        ],
        'harmonic_minor' => [
            'label' => 'Harmonická moll',
            'intervals' => [0, 2, 3, 5, 7, 8, 11],
            'qualities' => ['min', 'dim', 'aug', 'min', 'maj', 'maj', 'dim'],
            'chordKinds' => ['minMaj', 'halfdim', 'augMaj', 'min', 'domAltered', 'maj', 'dim'],
        ],
        'melodic_minor' => [
            'label' => 'Melodická moll',
            'intervals' => [0, 2, 3, 5, 7, 9, 11],
            'qualities' => ['min', 'min', 'aug', 'maj', 'maj', 'dim', 'dim'],
            'chordKinds' => ['minMaj', 'min', 'augMaj', 'domLydian', 'domAltered', 'halfdim', 'altered'],
        ],
    ];

    private const ROMAN = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII'];

    public function getNotes(): array
    {
        return self::NOTES;
    }

    public function getModes(): array
    {
        return self::MODES;
    }

    public function getModeLabel(string $mode): string
    {
        if ($mode === 'elliott') {
            return 'Elliott fragment';
        }

        return self::MODES[$mode]['label'] ?? $mode;
    }

    public function normalizeNote(string $note): string
    {
        $aliases = [
            'Db' => 'C#',
            'Eb' => 'D#',
            'Gb' => 'F#',
            'Ab' => 'G#',
            'Bb' => 'A#',
        ];

        return $aliases[$note] ?? $note;
    }

    public function transpose(string $note, int $semitones): string
    {
        $note = $this->normalizeNote($note);
        $index = array_search($note, self::NOTES, true);

        if ($index === false) {
            $index = 0;
        }

        return self::NOTES[($index + $semitones + 1200) % 12];
    }

    public function getScale(string $key, string $mode): array
    {
        $key = $this->normalizeNote($key);
        $modeData = self::MODES[$mode] ?? self::MODES['major'];

        return array_map(
            fn (int $interval): string => $this->transpose($key, $interval),
            $modeData['intervals']
        );
    }

    public function getDiatonicChords(string $key, string $mode): array
    {
        $modeData = self::MODES[$mode] ?? self::MODES['major'];
        $scale = $this->getScale($key, $mode);
        $chords = [];

        foreach ($scale as $index => $root) {
            $quality = $modeData['qualities'][$index];
            $chordKind = $modeData['chordKinds'][$index] ?? $quality;
            $roman = self::ROMAN[$index];

            if ($quality === 'min' || $quality === 'dim') {
                $roman = strtolower($roman);
            }

            if ($quality === 'dim') {
                $roman .= 'o';
            } elseif ($quality === 'aug') {
                $roman .= '+';
            }

            $chords[] = [
                'root' => $root,
                'degree' => $index + 1,
                'quality' => $quality,
                'chordKind' => $chordKind,
                'roman' => $roman,
                'mode' => $mode,
            ];
        }

        return $chords;
    }

    public function fifthDistance(string $from, string $to): int
    {
        $from = $this->normalizeNote($from);
        $to = $this->normalizeNote($to);
        $steps = 0;
        $cursor = $from;

        while ($steps < 12) {
            if ($cursor === $to) {
                return min($steps, 12 - $steps);
            }

            $cursor = $this->transpose($cursor, 7);
            $steps++;
        }

        return 6;
    }

    public function isFifthResolution(string $from, string $to): bool
    {
        return $this->transpose($from, -5) === $this->normalizeNote($to)
            || $this->transpose($from, 7) === $this->normalizeNote($to);
    }

    public function semitoneDistance(string $from, string $to): int
    {
        $from = $this->normalizeNote($from);
        $to = $this->normalizeNote($to);
        $fromIndex = array_search($from, self::NOTES, true);
        $toIndex = array_search($to, self::NOTES, true);

        if ($fromIndex === false || $toIndex === false) {
            return 0;
        }

        $distance = abs($toIndex - $fromIndex);
        return min($distance, 12 - $distance);
    }
}

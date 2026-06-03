<?php

class DiagramRenderer
{
    private VoicingManager $voicingManager;

    public function __construct(VoicingManager $voicingManager)
    {
        $this->voicingManager = $voicingManager;
    }

    public function drawDiagram(string $chordName, string $voicingPreference, bool $borrowed = false): array
    {
        $frets = $this->voicingManager->getFrets($chordName, $voicingPreference);
        [$preferredRoot] = $this->voicingManager->splitChord($chordName);
        $preferredRoot = str_starts_with($chordName, 'Elliott ') || $this->voicingManager->isFretShape($chordName) ? null : $preferredRoot;
        $identifiedName = $this->voicingManager->identifyChordFromFrets($frets, $preferredRoot);
        $playability = $this->voicingManager->getPlayability($frets);
        $playedFrets = array_values(array_filter($frets, fn (int $fret): bool => $fret > 0));
        $baseFret = $playedFrets ? max(1, min($playedFrets)) : 1;

        if ($baseFret <= 3) {
            $baseFret = 1;
        }

        $accent = $borrowed ? '#b65cff' : '#d69124';
        $svg = '<svg class="diagram-svg" viewBox="0 0 112 138" role="img" aria-label="' . htmlspecialchars($identifiedName) . '">';
        $svg .= '<text x="56" y="14" text-anchor="middle" class="diagram-title">' . htmlspecialchars($identifiedName) . '</text>';

        for ($string = 0; $string < 6; $string++) {
            $x = 18 + ($string * 15);
            $svg .= '<line x1="' . $x . '" y1="30" x2="' . $x . '" y2="116" class="string-line" />';
        }

        for ($fret = 0; $fret < 6; $fret++) {
            $y = 30 + ($fret * 17);
            $class = $fret === 0 && $baseFret === 1 ? 'nut-line' : 'fret-line';
            $svg .= '<line x1="18" y1="' . $y . '" x2="93" y2="' . $y . '" class="' . $class . '" />';
        }

        if ($baseFret > 1) {
            $svg .= '<text x="100" y="48" class="fret-label">' . $baseFret . 'fr</text>';
        }

        foreach ($frets as $string => $fret) {
            $x = 18 + ($string * 15);

            if ($fret < 0) {
                $svg .= '<text x="' . $x . '" y="127" text-anchor="middle" class="mute-mark">x</text>';
                continue;
            }

            if ($fret === 0) {
                $svg .= '<circle cx="' . $x . '" cy="125" r="4" class="open-mark" />';
                continue;
            }

            $displayFret = $fret - $baseFret + 1;
            $y = 30 + ($displayFret * 17) - 8;
            $svg .= '<circle cx="' . $x . '" cy="' . $y . '" r="6.5" fill="' . $accent . '" class="finger-dot" />';
        }

        $svg .= '</svg>';

        return [
            'svg' => $svg,
            'identifiedName' => $identifiedName,
            'notes' => $this->voicingManager->getNotesFromFrets($frets),
            'voicingStr' => implode(' ', array_map(fn (int $fret): string => $fret < 0 ? 'x' : (string)$fret, $frets)),
            'fingeringNote' => $playability['note'],
            'playable' => $playability['playable'],
        ];
    }
}

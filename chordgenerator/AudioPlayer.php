<?php

class AudioPlayer
{
    private const RPRESETS = [
        '4/4 straight' => [1, 0, 0, 0, 1, 0, 0, 0],
        '4/4 push' => [1, 0, 1, 0, 0, 1, 0, 0],
        '6/8 pulse' => [1, 0, 0, 1, 0, 0],
    ];

    public function getRhythmPresets(): array
    {
        return self::RPRESETS;
    }
}

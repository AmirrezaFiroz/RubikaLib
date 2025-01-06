<?php

declare(strict_types=1);

namespace RubikaLib\Interfaces;

final class MusicFile
{
    public function __construct(
        public readonly string $file_name = 'default',
        public readonly string $singer = 'default'
    ) {}
}

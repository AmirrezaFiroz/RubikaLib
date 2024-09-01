<?php

declare(strict_types=1);

namespace RubikaLib\Interfaces;

final class Gif
{
    public function __construct(
        public readonly string $fileId,
        public readonly int $dcID,
        public readonly string $access_hash_rec,
        public readonly string $file_name,
        public readonly int $width,
        public readonly int $height,
        public readonly int $time,
        public readonly int $size,
        public readonly string $thumb_inline = ''
    ) {}
}

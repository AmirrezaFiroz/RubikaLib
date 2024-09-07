<?php

declare(strict_types=1);

namespace RubikaLib\Helpers;

use Generator;

/**
 * library Optimal functions
 */
final class Optimal
{
    /**
     * Get File Chunks As \Generator Funciton
     *
     * @param string $url
     * @return Generator file chunks (262 KB)
     */
    public static function getFile(string $url): Generator
    {
        $fileName = basename($url);
        $file = fopen($fileName, 'w+');
        $handle = fopen($url, 'r', false);
        if ($handle) {
            while (!feof($handle)) {
                $chunk = fread($handle, 262143);
                fwrite($file, $chunk);
                yield $chunk;
            }
            fclose($handle);
        }
        fclose($file);
    }
}

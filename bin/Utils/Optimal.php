<?php

declare(strict_types=1);

namespace RubikaLib\Utils;

use Generator;

/**
 * library tool functions
 */
final class Optimal
{
    /**
     * get file chuncks as Generator funciton
     *
     * @param string $url
     * @param string $useragent
     * @return Generator
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

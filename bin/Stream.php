<?php

declare(strict_types=1);

namespace RubikaLib;

use RubikaLib\Interfaces\MainSettings;

/**
 * Streaming Part Of Library
 */
final class Stream
{
    public function __construct(
        private Session $session,
        private Requests $req,
        private MainSettings $settings
    ) {}

    /**
     * Start New Live Stream
     *
     * @param string $guid chat guid
     * @param string $title live title
     * @param string $thumb thumbnail photo (if its empty ---> bin/video.png will be placed)
     * @return array [$stream-key, $stream-link, $live-id]
     */
    public function StartNewLiveStream(string $guid, string $title, string $thumb = ''): array
    {
        $d = $this->req->sendRequest('sendLive', [
            'object_guid' => $guid,
            'title' => $title,
            'device_type' => 'Software',
            'thumb_inline' => $thumb == '' ? base64_encode(file_get_contents('video.png')) : $thumb,
            'rnd' => (string)mt_rand(10000000, 999999999)
        ], $this->session)['data'];

        preg_match('/Stream url:\s*(.+)\s*Stream key:\s*(.+)/', $d['publish_text'], $matches);
        return [
            $matches[2],
            $matches[1],
            $d['message_update']['message']['live_data']['live_id']
        ];
    }

    /**
     * Stop Live Stream
     *
     * @param string $live_id
     * @return array API result
     */
    public function stopLive(string $live_id): array
    {
        return $this->req->sendRequest('stopLive', [
            'live_id' => $live_id
        ], $this->session)['data'];
    }
}

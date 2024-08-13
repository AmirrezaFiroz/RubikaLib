<?php

declare(strict_types=1);

namespace RubikaLib;

use Generator;
use RubikaLib\interfaces\MainSettings;
use RubikaLib\Logger;

/**
 * special Exception class
 */
final class Requests
{
    private string $getdcmessURL = 'https://getdcmess.iranlms.ir/';
    /**
     * all DC URLs
     *
     * @var array|null
     */
    public ?array $links;
    private ?Cryption $crypto;
    private ?string $re_auth;

    public function __construct(
        /**
         * useragent for request sending
         *
         * @var string
         */
        public string $useragent,
        public readonly string $auth,
        private MainSettings $mainSettings,
        string $private_key = ''
    ) {
        $this->crypto = new Cryption($this->auth, $private_key);
        $this->re_auth = $auth != '' ? cryption::re_auth($this->auth) : '';

        if (file_exists("lib/api-links.json")) {
            $this->links = json_decode(file_get_contents("lib/api-links.json"), true);
        } else {
            !is_dir("lib") ? mkdir("lib") : null;
            file_put_contents("lib/api-links.json", json_encode($this->getdcmess()));
        }
    }

    /**
     * get DCs from API and save them
     *
     * @return array an array of DC URLs
     */
    public function getdcmess(): array
    {
        $data = [
            'api_version' => '4',
            'client' => json_encode([
                'app_name' => 'Main',
                'app_version' => '4.4.15',
                'lang_code' => 'fa',
                'package' => 'web.rubika.ir',
                'platform' => 'Web'
            ]),
            'method' => 'getDCs'
        ];

        $ch = curl_init($this->getdcmessURL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        $res = curl_exec($ch);
        curl_close($ch);
        if (curl_error($ch)) {
            throw new Logger('connection error: ' . curl_error($ch));
        } else {
            $res = json_decode($res, true);

            if ($res['status'] == 'OK') {
                $res['data']['default_rubino_urls'] = [
                    "https://rubino1.iranlms.ir/"
                ];
                $res['data']['default_wallet_urls'] = [
                    "https://wallet1.iranlms.ir/"
                ];
                $this->links = $res['data'];

                return $res['data'];
            } else {
                throw new Logger('there is an error in getting DC URLs', data: $res);
            }
        }
    }

    /**
     * send request to API
     *
     * @param string $method
     * @param array $data
     * @param boolean $tmp_session
     * @throws Logger if there is an error in result
     * @return array API result
     */
    public function making_request(string $method, array $data, Session $session, bool $tmp_session = false): array
    {
        $data = json_encode([
            'method' => $method,
            'input' => $data,
            'client' => [
                'app_name' => "Main",
                'app_version' => "4.4.15",
                'platform' => "Web",
                'package' => "web.rubika.ir",
                'lang_code' => "fa"
            ]
        ]);
        $data1 = $this->crypto->enc($data);
        $data = [
            'api_version' => '6',
            'data_enc' => $data1,
            ($tmp_session ? 'tmp_session' : 'auth') => (!$tmp_session ? $this->re_auth : $this->auth)
        ];

        if (!$tmp_session) {
            $data['sign'] = $this->crypto->generateSign($data1);
        }

        $default_api_urls = $this->links['default_api_urls'];
        $url = $default_api_urls[mt_rand(0, count($default_api_urls) - 1)];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Host: ' . str_replace('https://', '', $url),
            'User-Agent: ' . $this->useragent,
            'Accept: application/json, text/plain, */*',
            'Content-Type: text/plain',
            'Origin: https://web.rubika.ir',
            'Connection: keep-alive',
            'Referer: https://web.rubika.ir/',
        ]);
        curl_setopt($ch, CURLOPT_USERAGENT, $this->useragent);

        $res = curl_exec($ch);
        curl_close($ch);

        if (curl_error($ch)) {
            throw new Logger('connection error: ' . curl_error($ch));
        } else {
            $res = json_decode($this->crypto->dec(json_decode($res, true)['data_enc']), true);

            // INVALID_AUTH
            // NOT_REGISTERED
            // INVALID_INPUT
            // TOO_REQUESTS

            if (in_array($res['status'], ['SendPassKey', 'OK'])) {
                return $res;
            } else {
                if (in_array($res['status_det'], ['NOT_REGISTERED'])) {
                    $session->terminate();
                }

                if (isset($res['client_show_message'])) {
                    throw new Logger('there is an error in result: ' . json_encode(['status' => $res['status'], 'method' => $method, 'message' => $res['client_show_message']['link']['alert_data']['message'], 'status_det' => @$res['status_det']]), data: $res);
                }

                throw new Logger('there is an error in result: ' . json_encode(['status' => $res['status'], 'method' => $method, 'status_det' => @$res['status_det']]), data: $res);
            }
        }
    }

    /**
     * download a file from CND
     *
     * @param string $access_hash_rec
     * @param string $file_id
     * @param integer $DC
     * @return string|Generator|false false if file not found, string if (MainSettings)->optimal is false or Generator if it is true
     */
    public function downloadFile(string $access_hash_rec, string $file_id, int $DC): string|Generator|false
    {
        $storage_url = $this->links['storages'][(string)$DC];
        $start_index = 0;
        $chunk_size = 262144;
        $buffer = '';
        $total_length = 0;

        $ch = curl_init($storage_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, '');
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_USERAGENT, $this->useragent);
        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "auth: {$this->crypto->auth}",
            "access-hash-rec: $access_hash_rec",
            "file-id: $file_id",
            'Host: ' . parse_url($storage_url, PHP_URL_HOST),
            "User-Agent: {$this->useragent}",
            "start-index: $start_index",
            "last-index: " . ($start_index + $chunk_size - 1),
        ]);

        $res = curl_exec($ch);
        curl_close($ch);
        if (curl_error($ch)) {
            throw new Logger('connection error: ' . curl_error($ch));
        }

        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $headers = substr($res, 0, $header_size);

        if (strpos($headers, 'total_length:') !== false) {
            preg_match('/total_length: (\d+)/', $headers, $matches);
            $total_length = (int)$matches[1];
        }

        while (strlen($buffer) < $total_length) {
            $ch = curl_init($storage_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, '');
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_USERAGENT, $this->useragent);
            curl_setopt($ch, CURLOPT_HEADER, 1);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "auth: {$this->crypto->auth}",
                "access-hash-rec: $access_hash_rec",
                "file-id: $file_id",
                'Host: ' . parse_url($storage_url, PHP_URL_HOST),
                "User-Agent: {$this->useragent}",
                "start-index: $start_index",
                "last-index: " . ($start_index + $chunk_size - 1),
            ]);

            $res = curl_exec($ch);
            if (curl_error($ch)) {
                curl_close($ch);
                throw new Logger('connection error: ' . curl_error($ch));
            }

            $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $body = substr($res, $header_size);
            curl_close($ch);

            if ($body == 'error') {
                return false;
                break;
            }
            if ($this->mainSettings->Optimal) {
                yield $body;
            }

            $buffer .= $body;
            $start_index += $chunk_size;
        }

        if (!$this->mainSettings->Optimal) {
            return $buffer;
        }
    }

    /**
     * upload a file to server
     *
     * @param string $path file path in directorry
     * @param string $file_id
     * @param string $access_hash_send
     * @param string $url
     * @return array API result
     */
    public function uploadFile(string $path, string $file_id, string $access_hash_send, string $url): array
    {
        $fileContent = file_get_contents($path);
        $total_parts = ceil(strlen($fileContent) / 131072);

        for ($part = 1; $part <= $total_parts; $part++) {
            echo "$part/$total_parts\n";
            $start_index = ($part - 1) * 131072;
            $last_index = min($start_index + 131072, strlen($fileContent));
            $chunk_data = substr($fileContent, $start_index, $last_index - $start_index);

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $chunk_data);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "auth: {$this->auth}",
                "access-hash-send: $access_hash_send",
                "file-id: $file_id",
                'chunk-size: ' . strlen($chunk_data),
                "part-number: $part",
                "total-part: $total_parts"
            ]);

            $res = curl_exec($ch);
            curl_close($ch);

            if (curl_error($ch)) {
                throw new Logger('connection error: ' . curl_error($ch));
            }

            if ($part == $total_parts) {
                return json_decode($res, true);
            }
        }
    }
}

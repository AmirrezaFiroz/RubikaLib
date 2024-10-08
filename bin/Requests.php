<?php

declare(strict_types=1);

namespace RubikaLib;

use Generator;
use RubikaLib\Enums\AppType;
use RubikaLib\Interfaces\MainSettings;

/**
 * special Exception class
 */
final class Requests
{
    /**
     * all DC URLs
     *
     * @var array
     */
    public ?array $links;
    private ?string $getDCMessURL;
    private ?Cryption $crypto;
    private ?string $re_auth;
    private ?string $k;
    private ?string $url;

    /**
     * @param string $useragent useragent for request sending
     * @param string $auth
     * @param MainSettings $MainSettings
     * @param string $private_key
     */
    public function __construct(
        public readonly string $useragent,
        private string $auth,
        private MainSettings $MainSettings,
        string $private_key = ''
    ) {
        $this->k = md5(sha1(Cryption::GenerateRandom_tmp_session(5)));
        $this->getDCMessURL = ($MainSettings->AppType == AppType::Shad) ? 'https://shgetDCMess.iranlms.ir/' : 'https://getDCMess.iranlms.ir/';
        $this->crypto = new Cryption($this->auth, $private_key);
        $this->re_auth = $auth != '' ? cryption::re_auth($this->auth) : '';

        if (file_exists("{$MainSettings->Base}api-links-shad.json") or file_exists("{$MainSettings->Base}api-links-rubika.json")) {
            $this->links = json_decode(file_get_contents(($MainSettings->AppType == AppType::Shad) ? "{$MainSettings->Base}api-links-shad.json" : "{$MainSettings->Base}api-links-rubika.json"), true);
        } else {
            !is_dir($MainSettings->Base) ? mkdir($MainSettings->Base) : null;
            file_put_contents(($MainSettings->AppType == AppType::Shad) ? "{$MainSettings->Base}api-links-shad.json" : "{$MainSettings->Base}api-links-rubika.json", json_encode($this->getDCMess()));
        }

        $default_api_urls = $this->links['default_api_urls'];
        $this->url = $default_api_urls[mt_rand(0, count($default_api_urls) - 1)];
    }

    /**
     * get DCs from API and save them
     *
     * @return array an array of DC URLs
     */
    public function getDCMess(): array
    {
        $data = [
            'api_version' => '4',
            'client' => json_encode([
                'app_name' => 'Main',
                'app_version' => ($this->MainSettings->AppType == AppType::Shad) ? '4.5.0' : '4.4.15',
                'lang_code' => 'fa',
                'package' => ($this->MainSettings->AppType == AppType::Shad) ? 'web.shad.ir' : 'web.rubika.ir',
                'platform' => 'Web'
            ]),
            'method' => 'getDCs'
        ];

        $ch = curl_init($this->getDCMessURL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        $res = curl_exec($ch);
        curl_close($ch);
        if (curl_error($ch)) {
            throw new Failure('connection error: ' . curl_error($ch));
        } else {
            $res = json_decode($res, true);

            if ($res['status'] == 'OK') {
                // $res['data']['default_rubino_urls'] = [
                //     "https://rubino1.iranlms.ir/"
                // ];
                // $res['data']['default_wallet_urls'] = [
                //     "https://wallet1.iranlms.ir/"
                // ];
                $this->links = $res['data'];

                return $res['data'];
            } else {
                throw new Failure('there is an error in getting DC URLs', data: $res);
            }
        }
    }

    /**
     * send request to API
     *
     * @param string $method
     * @param array $data
     * @param boolean $tmp_session
     * @throws Failure if there is an error in result {
     *      INVALID_AUTH
     *      NOT_REGISTERED
     *      INVALID_INPUT
     *      TOO_REQUESTS
     *  }
     * @return array API result
     */
    public function SendRequest(string $method, array $data, Session $session, bool $tmp_session = false): array
    {
        // TODO
        /*if (isset($session->data['private_key'])) {
            if (is_array($session->data['date'])) {
                $gen_time = $session->data['date']['generated'];
            } elseif (is_int($session->data['date'])) {
                $gen_time = $session->data['date'];
            }

            if ((time() - $gen_time) >= 86400) {
                $session->ChangeData('user', $this->SendRequest('getUserInfo', ['user_guid' => $session->data['user']['user_guid']], $session)['data']['user']);
                $this->links = json_decode(file_get_contents("{$this->MainSettings->Base}api-links.json"), true);
                $this->MainSettings->KeepUpdated ? file_put_contents(($this->MainSettings->AppType == AppType::Shad) ? "{$this->MainSettings->Base}api-links-shad.json" : "{$this->MainSettings->Base}api-links-rubika.json", json_encode($this->getDCMess())) : null;
            }
        }*/

        $data = json_encode([
            'method' => $method,
            'input' => $data,
            'client' => [
                'app_name' => 'Main',
                'app_version' => ($this->MainSettings->AppType == AppType::Shad) ? '4.5.0' : '4.4.15',
                'lang_code' => 'fa',
                'package' => ($this->MainSettings->AppType == AppType::Shad) ? 'web.shad.ir' : 'web.rubika.ir',
                'platform' => 'Web'
            ]
        ]);

        $data1 = $this->crypto->Close($data);
        $data = [
            'api_version' => '6',
            'data_enc' => $data1,
            ($tmp_session ? 'tmp_session' : 'auth') => (!$tmp_session ? $this->re_auth : $this->auth)
        ];

        if (!$tmp_session) {
            $data['sign'] = $this->crypto->GenerateSign($data1);
        }

        $ch = curl_init($this->url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Host: ' . str_replace('https://', '', $this->url),
            'User-Agent: ' . $this->useragent,
            'Accept: application/json, text/plain, */*',
            'Content-Type: text/plain',
            'Origin: https://web.' . ($this->MainSettings->AppType == AppType::Shad ? 'shad' : 'rubika') . '.ir',
            'Connection: keep-alive',
            'Referer: https://web.' . ($this->MainSettings->AppType == AppType::Shad ? 'shad' : 'rubika') . '.ir/',
        ]);
        curl_setopt($ch, CURLOPT_USERAGENT, $this->useragent);

        $res = curl_exec($ch);
        curl_close($ch);

        if (curl_error($ch)) {
            throw new Failure('connection error: ' . curl_error($ch));
        } else {
            $b = json_decode($res, true);
            if (!isset($b['data_enc'])) {
                throw new Failure('there is an error in result: ' . json_encode($b));
            }
            $res = json_decode($this->crypto->Open($b['data_enc']), true);

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
                    throw new Failure('there is an error in result: ' . json_encode(['status' => $res['status'], 'method' => $method, 'message' => $res['client_show_message']['link']['alert_data']['message'], 'status_det' => @$res['status_det']]), data: $res);
                }

                throw new Failure('there is an error in result: ' . json_encode(['status' => $res['status'], 'method' => $method, 'status_det' => @$res['status_det']]), data: $res);
            }
        }
    }

    /**
     * Download A File From CND
     *
     * @param string $access_hash_rec
     * @param string $file_id
     * @param integer $DC
     * @return array|Generator|false false if file not found, string if (MainSettings)->optimal is off or Generator if it is on
     */
    public function DownloadFileFromAPI(string $access_hash_rec, string $file_id, int $DC, int $start_index = 0): array|Generator|false
    {
        $storage_url = $this->links['storages'][(string)$DC];
        $chunk_size = 262144;
        if ($start_index != 0) $start_index += $chunk_size;
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
            throw new Failure('connection error: ' . curl_error($ch));
        }
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $headers = substr($res, 0, $header_size);

        if (strpos($headers, 'total_length:') !== false) {
            preg_match('/total_length: (\d+)/', $headers, $matches);
            $total_length = (int)$matches[1];
        }

        $percent = round(($start_index / $total_length) * 100);
        $this->showProgress((int)$percent);

        while ($start_index < $total_length) {
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
                throw new Failure('connection error: ' . curl_error($ch));
            }
            $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $body = substr($res, $header_size);
            curl_close($ch);
            if ($body == 'error') {
                if ($this->MainSettings->Optimal) {
                    yield false;
                } else {
                    return false;
                }
                break;
            }

            $percent = $total_length > $chunk_size ? round(($start_index / $total_length) * 100) : 100;
            $this->showProgress((int)$percent);

            if ($this->MainSettings->Optimal) {
                yield [$body, $start_index];
            }
            if (!$this->MainSettings->Optimal) {
                $buffer .= $body;
            }

            $start_index += $chunk_size;
        }

        if (!$this->MainSettings->Optimal) {
            return $buffer;
        }
    }

    public function getPartOfSessionKey(): array
    {
        return [Cryption::Encode($this->auth, $this->k), $this->k];
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
    public function SendFileToAPI(string $path, string $file_id, string $access_hash_send, string $url): array
    {
        $chunkSize = 131072; // 128 KB
        $fileHandle = fopen($path, 'rb');
        $fileSize = filesize($path);
        $totalParts = ceil($fileSize / $chunkSize);

        for ($part = 1; $part <= $totalParts; $part++) {
            $chunkData = fread($fileHandle, $chunkSize);
            $actualChunkSize = strlen($chunkData);

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $chunkData);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "auth: {$this->auth}",
                "access-hash-send: $access_hash_send",
                "file-id: $file_id",
                "chunk-size: $actualChunkSize",
                "part-number: $part",
                "total-part: $totalParts"
            ]);

            $res = curl_exec($ch);

            if (curl_error($ch)) {
                fclose($fileHandle);
                throw new Failure('connection error: ' . curl_error($ch));
            }

            curl_close($ch);

            $percent = round(($part / $totalParts) * 100);
            $this->showProgress((int)$percent);

            if ($part == $totalParts) {
                fclose($fileHandle);
                echo PHP_EOL; // یک خط جدید در انتها
                return json_decode($res, true);
            }
        }
    }

    /**
     * Show Progress bar
     *
     * @param integer $percent
     * @return void
     */
    private function showProgress(int $percent): void
    {
        if (!$this->MainSettings->ShowProgressBar) return;

        $bar = str_repeat("=", $percent - ($percent != 0 ? 1 : 0)) . ($percent != 0 ? '>' : '') . str_repeat(" ", (100 - $percent));
        echo "\rUploading... : [{$bar}] {$percent}%";
        flush();
    }
}

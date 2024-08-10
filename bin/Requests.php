<?php

declare(strict_types=1);

namespace RubikaLib;

use RubikaLib\Logger;
use RubikaLib\Utils\userAgent;

/**
 * special Exception class
 */
final class Requests
{
    private string $getdcmessURL = 'https://getdcmess.iranlms.ir/';
    public ?array $links;
    private ?Cryption $crypto;
    private ?string $re_auth;

    public function __construct(
        public string $useragent,
        public string $auth,
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
}

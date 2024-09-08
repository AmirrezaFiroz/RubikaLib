<?php

declare(strict_types=1);

namespace RubikaLib;

use Ratchet\Client\WebSocket;
use React\EventLoop\Loop;
use RubikaLib\Utils\Tools;
use RubikaLib\Enums\ChatActivities;
use RubikaLib\Interfaces\{
    MainSettings,
    Runner
};

/**
 * class for working with API
 */
final class Main
{
    private ?Runner $Runner;
    private ?int $phone_number;
    private ?Requests $req;
    private ?Session $session;
    private ?Cryption $crypto;

    public static $VERSION = '2.0.0';

    public ?Folders $Folders;
    public ?Account $Account;
    public ?Messages $Messages;
    public ?Contacts $Contacts;
    public ?Chats $Chats;

    /**
     * @param integer $phone_number 989123456789 or 9123456789 or leave empty to get in CLI
     * @param string $app_name (it just need in login)
     * @param MainSettings $settings
     */
    public function __construct(
        int $phone_number = 0,
        string $app_name = '',
        private MainSettings $settings = new MainSettings
    ) {
        if (isset($_SERVER['SERVER_PROTOCOL'])) {
            if (!file_exists($settings->Base . 'sessions.rub') or !isset(json_decode(Cryption::Decode(file_get_contents($settings->Base . 'sessions.rub'), $settings->Base), true)[basename($_SERVER['SCRIPT_FILENAME'])])) {
?>
                <!DOCTYPE html>
                <?php
                if (!in_array(strlen((string)$phone_number), [10, 12]) && count($_POST) == 0) {
                ?>
                    <form method="post">
                        <span style="color: green;">Enter Phone Number:</span>
                        <br><br>
                        <input type="text" name="phone" placeholder="9123456789" required minlength="10" maxlength="12"> <button type="submit">Go</button>
                    </form>
                    <?php
                    exit;
                }

                if (!in_array(strlen((string)$phone_number), [10, 12]) && count($_POST) != 0) {
                    if (isset($_POST['phone'])) {
                        if (in_array(strlen((string)((int)$_POST['phone'])), [10, 12])) {
                            $this->phone_number = Tools::ReplaceTruePhoneNumber((int)$_POST['phone']);

                            $d = file_exists($settings->Base . 'sessions.rub') ? json_decode(Cryption::Decode(file_get_contents($settings->Base . 'sessions.rub'), $settings->Base), true) : array();
                            @$d[basename($_SERVER['SCRIPT_FILENAME'])] = $this->phone_number;
                            file_put_contents($settings->Base . 'sessions.rub', Cryption::Encode(json_encode($d), $settings->Base));

                            if (!Session::is_session($this->phone_number, $settings->AppType)) {
                                $this->req = new Requests($_SERVER['HTTP_USER_AGENT'], $settings->tmp_session, MainSettings: $settings);
                                $this->session = new Session($this->phone_number, $settings->tmp_session, $settings->Base, $settings);
                                $this->session
                                    ->ChangeData('useragent', $this->req->useragent)
                                    ->ChangeData('step', 'setup');

                                $send_code = $this->sendCode();

                                if ($send_code['status'] == 'SendPassKey') {
                    ?>
                                    <form method="post">
                                        <span style="color: yellow;">Enter Password:</span>
                                        <br><br>
                                        <input type="text" name="PassKey" placeholder="<?= $send_code['hint_pass_key'] ?>" required> <button type="submit">Go</button>
                                    </form>
                                <?php
                                    $this->session
                                        ->ChangeData('step', 'getPassKey')
                                        ->ChangeData('hint_pass_key', $send_code['hint_pass_key']);
                                    exit;
                                } else {
                                ?>
                                    <form method="post">
                                        <span style="color: green;">Enter Code:</span>
                                        <br><br>
                                        <input type="text" name="code" placeholder="<?= $send_code['code_digits_count'] ?>-digits" required minlength="<?= $send_code['code_digits_count'] ?>" maxlength="<?= $send_code['code_digits_count'] ?>"> <button type="submit">Go</button>
                                    </form>
                                    <?php
                                    $this->session
                                        ->ChangeData('step', 'getCode')
                                        ->ChangeData('phone_code_hash', $send_code['phone_code_hash'])
                                        ->ChangeData('code_digits_count', $send_code['code_digits_count']);
                                    exit;
                                }
                            } else {
                                $this->session = new Session($this->phone_number, workDir: $settings->Base, settings: $settings);
                                $this->req = new Requests(
                                    auth: Cryption::Decode($this->session->getPartOfSessionKey()[0], $this->session->getPartOfSessionKey()[1]),
                                    private_key: $this->session->data['private_key'] ?? '',
                                    useragent: $this->session->data['useragent'],
                                    MainSettings: $settings
                                );

                                switch ($this->session->data['step']) {
                                    case 'getCode':
                                    ?>
                                        <form method="post">
                                            <span style="color: green;">Enter Code:</span>
                                            <br><br>
                                            <input type="text" name="code" placeholder="<?= $this->session->data['code_digits_count'] ?>-digits" required> <button type="submit">Go</button>
                                        </form>
                                    <?php
                                        exit;
                                        break;
                                    case 'getPassKey':
                                    ?>
                                        <form method="post">
                                            <span style="color: yellow;">Enter Password:</span>
                                            <br><br>
                                            <input type="text" name="PassKey" placeholder="<?= $this->session->data['hint_pass_key'] ?>" required> <button type="submit">Go</button>
                                        </form>
                                        <?php
                                        exit;
                                        break;
                                    case 'setup':
                                        $send_code = $this->sendCode();

                                        if ($send_code['status'] == 'SendPassKey') {
                                        ?>
                                            <form method="post">
                                                <span style="color: yellow;">Enter Password:</span>
                                                <br><br>
                                                <input type="text" name="PassKey" placeholder="<?= $send_code['hint_pass_key'] ?>" required> <button type="submit">Go</button>
                                            </form>
                                        <?php
                                            $this->session
                                                ->ChangeData('step', 'getPassKey')
                                                ->ChangeData('hint_pass_key', $send_code['hint_pass_key']);
                                            exit;
                                        } else {
                                        ?>
                                            <form method="post">
                                                <span style="color: green;">Enter Code:</span>
                                                <br><br>
                                                <input type="text" name="code" placeholder="<?= $send_code['code_digits_count'] ?>-digits" required minlength="<?= $send_code['code_digits_count'] ?>" maxlength="<?= $send_code['code_digits_count'] ?>"> <button type="submit">Go</button>
                                            </form>
                            <?php
                                            $this->session
                                                ->ChangeData('step', 'getCode')
                                                ->ChangeData('phone_code_hash', $send_code['phone_code_hash'])
                                                ->ChangeData('code_digits_count', $send_code['code_digits_count']);
                                            exit;
                                        }
                                        exit;
                                        break;
                                }
                            }
                        } else {
                            ?>
                            <span style="color: red;">Invalid Phone</span>
                            <br>
                            <span style="color: green;">Enter Phone:</span>
                            <br><br>
                            <form method="post">
                                <input type="text" name="phone" placeholder="9123456789" required minlength="10" maxlength="12"> <button type="submit">Go</button>
                            </form>
                        <?php
                            exit;
                        }
                    }
                }
            } elseif (isset(json_decode(Cryption::Decode(file_get_contents($settings->Base . 'sessions.rub'), $settings->Base), true)[basename($_SERVER['SCRIPT_FILENAME'])])) {
                $this->phone_number = Tools::ReplaceTruePhoneNumber(json_decode(Cryption::Decode(file_get_contents($settings->Base . 'sessions.rub'), $settings->Base), true)[basename($_SERVER['SCRIPT_FILENAME'])]);
                $this->session = new Session($this->phone_number, workDir: $settings->Base, settings: $settings);

                if (isset($_POST['PassKey']) && $this->session->data['step'] == 'getPassKey') {
                    $this->req = new Requests($_SERVER['HTTP_USER_AGENT'], Cryption::Decode($this->session->getPartOfSessionKey()[0], $this->session->getPartOfSessionKey()[1]), MainSettings: $settings);

                    $send_code = $this->sendCode($_POST['PassKey']);

                    if ($send_code['status'] == 'OK') {
                        $this->session
                            ->ChangeData('step', 'getCode')
                            ->ChangeData('phone_code_hash', $send_code['phone_code_hash'])
                            ->ChangeData('code_digits_count', $send_code['code_digits_count']);
                        ?>
                        <form method="post">
                            <span style="color: green;">Enter Code:</span>
                            <br><br>
                            <input type="text" name="code" placeholder="<?= $send_code['code_digits_count'] ?>-digits" required> <button type="submit">Go</button>
                        </form>
                    <?php
                        $this->session->ChangeData('step', 'getCode');
                        exit;
                    }
                } elseif (isset($_POST['code']) && $this->session->data['step'] == 'getCode') {
                    if (strlen((string)((int)$_POST['code'])) == $this->session->data['code_digits_count']) {
                        $this->req = new Requests($_SERVER['HTTP_USER_AGENT'], Cryption::Decode($this->session->getPartOfSessionKey()[0], $this->session->getPartOfSessionKey()[1]), MainSettings: $settings);

                        list($signIn, $private_key) = $this->signIn($this->session->data['phone_code_hash'], (int)$_POST['code']);

                        $auth = Cryption::Decrypt_RSAEncodedAuth($private_key, $signIn['auth']);
                        unset($signIn['user']['online_time']);
                        $this->session->ReGenerateSession();
                        $this->session
                            ->ChangeData('auth', $auth)
                            ->ChangeData('user', $signIn['user'])
                            ->ChangeData('private_key', $private_key)
                            ->ChangeData('useragent', $this->req->useragent)
                            ->setAuth($auth);
                        $this->req = new Requests(auth: $auth, private_key: $private_key, useragent: $this->req->useragent, MainSettings: $settings);

                        $this->RegisterDevice($app_name);
                    } else {
                    ?>
                        <form method="post">
                            <span style="color: red;">Invalid Code!</span>
                            <br>
                            <span style="color: green;">Enter Code:</span>
                            <br><br>
                            <input type="text" name="code" placeholder="<?= $this->session->data['code_digits_count'] ?>-digits" required> <button type="submit">Go</button>
                        </form>
                        <?php
                        exit;
                    }
                } else {
                    switch ($this->session->data['step']) {
                        case 'getCode':
                        ?>
                            <form method="post">
                                <span style="color: green;">Enter Code:</span>
                                <br><br>
                                <input type="text" name="code" placeholder="<?= $this->session->data['code_digits_count'] ?>-digits" required> <button type="submit">Go</button>
                            </form>
                        <?php
                            exit;
                            break;
                        case 'getPassKey':
                        ?>
                            <form method="post">
                                <span style="color: yellow;">Enter Password:</span>
                                <br><br>
                                <input type="text" name="PassKey" placeholder="<?= $this->session->data['hint_pass_key'] ?>" required> <button type="submit">Go</button>
                            </form>
                            <?php
                            exit;
                            break;
                        case 'setup':
                            $send_code = $this->sendCode();

                            if ($send_code['status'] == 'SendPassKey') {
                            ?>
                                <form method="post">
                                    <span style="color: yellow;">Enter Password:</span>
                                    <br><br>
                                    <input type="text" name="PassKey" placeholder="<?= $send_code['hint_pass_key'] ?>" required> <button type="submit">Go</button>
                                </form>
                            <?php
                                $this->session
                                    ->ChangeData('step', 'getPassKey')
                                    ->ChangeData('hint_pass_key', $send_code['hint_pass_key']);
                                exit;
                            } else {
                            ?>
                                <form method="post">
                                    <span style="color: green;">Enter Code:</span>
                                    <br><br>
                                    <input type="text" name="code" placeholder="<?= $send_code['code_digits_count'] ?>-digits" required minlength="<?= $send_code['code_digits_count'] ?>" maxlength="<?= $send_code['code_digits_count'] ?>"> <button type="submit">Go</button>
                                </form>
                <?php
                                $this->session
                                    ->ChangeData('step', 'getCode')
                                    ->ChangeData('phone_code_hash', $send_code['phone_code_hash'])
                                    ->ChangeData('code_digits_count', $send_code['code_digits_count']);
                                exit;
                            }
                            exit;
                            break;
                    }
                }
            }

            if (!isset($this->phone_number)) {
                ?>
                <!DOCTYPE html>
                <form method="post">
                    <span style="color: green;">Enter Phone Number:</span>
                    <br><br>
                    <input type="text" name="phone" placeholder="9123456789" required minlength="10" maxlength="12"> <button type="submit">Go</button>
                </form>
<?php
                exit;
            }

            $this->session = new Session($this->phone_number, workDir: $settings->Base, settings: $settings);
            $this->req = new Requests(
                auth: Cryption::Decode($this->session->getPartOfSessionKey()[0], $this->session->getPartOfSessionKey()[1]),
                private_key: $this->session->data['private_key'] ?? '',
                useragent: $_SERVER['HTTP_USER_AGENT'],
                MainSettings: $settings
            );

            $this->Account = new Account($this->session, $this->req, $settings);
            $this->Chats = new Chats($this->session, $this->req, $settings);
            $this->session->ChangeData('user', $this->Chats->getChatInfo($this->Account->getMySelf()['user_guid'])['user']);
            $this->Folders = new Folders($this->req, $this->session, $this);
            $this->crypto = new Cryption(Cryption::Decode($this->session->getPartOfSessionKey()[0], $this->session->getPartOfSessionKey()[1]), $this->session->data['private_key']);
            $this->Messages = new Messages($this->session, $this->req, $settings);
            $this->Contacts = new Contacts($this->session, $this->req);
        } else {
            while (!in_array(strlen((string)$phone_number), [10, 12]) && !isset(json_decode(Cryption::Decode(file_get_contents($settings->Base . 'sessions.rub'), $settings->Base), true)[basename($_SERVER['SCRIPT_FILENAME'])])) {
                $phone_number = (int)readline("Enter Phone Number: ");

                if (in_array(strlen((string)$phone_number), [10, 12])) {
                    $d = file_exists($settings->Base . 'sessions.rub') ? json_decode(Cryption::Decode(file_get_contents($settings->Base . 'sessions.rub'), $settings->Base), true) : array();
                    @$d[basename($_SERVER['SCRIPT_FILENAME'])] = $phone_number;
                    file_put_contents($settings->Base . 'sessions.rub', Cryption::Encode(json_encode($d), $settings->Base));
                }
            }

            $this->phone_number = Tools::ReplaceTruePhoneNumber($phone_number);

            if (!Session::is_session($this->phone_number, $settings->AppType)) {
                $this->req = new Requests($settings->UserAgent, $settings->tmp_session, MainSettings: $settings);
                $this->session = new Session($this->phone_number, $settings->tmp_session, $settings->Base, settings: $settings);
                $this->session
                    ->ChangeData('useragent', $this->req->useragent)
                    ->ChangeData('step', 'setup');
                $send_code = $this->sendCode();

                if ($send_code['status'] == 'SendPassKey') {
                    while (true) {
                        $pass_key = readline("enter your passkey ({$send_code['hint_pass_key']}) : ");
                        $send_code = $this->sendCode($pass_key);

                        if ($send_code['status'] == 'OK') {
                            break;
                        } else {
                            echo "Invalid!\n";
                        }
                    }
                }

                $this->session
                    ->ChangeData('step', 'getCode')
                    ->ChangeData('phone_code_hash', $send_code['phone_code_hash'])
                    ->ChangeData('code_digits_count', $send_code['code_digits_count']);

                list($signIn, $private_key) = [[], ''];
                while (true) {
                    $code = (int)readline("enter code ({$send_code['code_digits_count']}-digits) : ");

                    if (strlen((string)$code) == $send_code['code_digits_count']) {
                        list($signIn, $private_key) = $this->signIn($send_code['phone_code_hash'], $code);
                        break;
                    } else {
                        echo "its isn't {$send_code['code_digits_count']}-digits!\n";
                    }
                }

                $auth = Cryption::Decrypt_RSAEncodedAuth($private_key, $signIn['auth']);
                unset($signIn['user']['online_time']);
                $this->session->ReGenerateSession();
                $this->session
                    ->ChangeData('auth', $auth)
                    ->ChangeData('user', $signIn['user'])
                    ->ChangeData('private_key', $private_key)
                    ->ChangeData('useragent', $this->req->useragent)
                    ->setAuth($auth);
                $this->req = new Requests(auth: $auth, private_key: $private_key, useragent: $this->req->useragent, MainSettings: $settings);

                $this->RegisterDevice($app_name);
            } else {
                $this->session = new Session($this->phone_number, workDir: $settings->Base, settings: $settings);
                $this->req = new Requests(
                    auth: Cryption::Decode($this->session->getPartOfSessionKey()[0], $this->session->getPartOfSessionKey()[1]),
                    private_key: $this->session->data['private_key'] ?? '',
                    useragent: $this->session->data['useragent'],
                    MainSettings: $settings
                );

                switch ($this->session->data['step']) {
                    case 'getCode':
                        list($signIn, $private_key) = [[], ''];
                        while (1) {
                            $code = (int)readline("enter code ({$this->session->data['code_digits_count']}-digits) : ");

                            if (strlen((string)$code) == $this->session->data['code_digits_count']) {
                                list($signIn, $private_key) = $this->signIn($this->session->data['phone_code_hash'], $code);
                                break;
                            }
                        }

                        $auth = Cryption::Decrypt_RSAEncodedAuth($private_key, $signIn['auth']);
                        unset($signIn['online_time']);
                        $this->session->ReGenerateSession();
                        $this->session
                            ->ChangeData('auth', $auth)
                            ->ChangeData('user', $signIn['user'])
                            ->ChangeData('private_key', $private_key)
                            ->ChangeData('useragent', $this->req->useragent)
                            ->setAuth($auth);
                        $this->req = new Requests(auth: $auth, private_key: $private_key, useragent: $this->req->useragent, MainSettings: $settings);

                        $this->RegisterDevice($app_name);
                        break;
                    case 'getPassKey':
                        while (true) {
                            $pass_key = readline("enter your passkey ({$this->session->data['hint_pass_key']}) : ");
                            $send_code = $this->sendCode($pass_key);

                            if ($send_code['status'] == 'OK') {
                                break;
                            }
                        }

                        list($signIn, $private_key) = [[], ''];
                        while (1) {
                            $code = (int)readline("enter code ({$this->session->data['code_digits_count']}-digits) : ");

                            if (strlen((string)$code) == $this->session->data['code_digits_count']) {
                                list($signIn, $private_key) = $this->signIn($this->session->data['phone_code_hash'], $code);
                                break;
                            }
                        }

                        $auth = Cryption::Decrypt_RSAEncodedAuth($private_key, $signIn['auth']);
                        unset($signIn['online_time']);
                        $this->session->ReGenerateSession();
                        $this->session
                            ->ChangeData('auth', $auth)
                            ->ChangeData('user', $signIn['user'])
                            ->ChangeData('private_key', $private_key)
                            ->ChangeData('useragent', $this->req->useragent)
                            ->setAuth($auth);
                        $this->req = new Requests(auth: $auth, private_key: $private_key, useragent: $this->req->useragent, MainSettings: $settings);

                        $this->RegisterDevice($app_name);
                        break;
                    case 'setup':
                        $send_code = $this->sendCode();

                        if ($send_code['status'] == 'SendPassKey') {
                            while (true) {
                                $pass_key = readline("enter your passkey ({$send_code['hint_pass_key']}) : ");
                                $send_code = $this->sendCode($pass_key);

                                if ($send_code['status'] == 'OK') {
                                    break;
                                } else {
                                    echo "Invalid!\n";
                                }
                            }
                        }

                        $this->session
                            ->ChangeData('step', 'getCode')
                            ->ChangeData('phone_code_hash', $send_code['phone_code_hash'])
                            ->ChangeData('code_digits_count', $send_code['code_digits_count']);

                        list($signIn, $private_key) = [[], ''];
                        while (true) {
                            $code = (int)readline("enter code ({$send_code['code_digits_count']}-digits) : ");

                            if (strlen((string)$code) == $send_code['code_digits_count']) {
                                list($signIn, $private_key) = $this->signIn($send_code['phone_code_hash'], $code);
                                break;
                            }
                        }

                        $auth = Cryption::Decrypt_RSAEncodedAuth($private_key, $signIn['auth']);
                        unset($signIn['user']['online_time']);
                        $this->session->ReGenerateSession();
                        $this->session
                            ->ChangeData('auth', $auth)
                            ->ChangeData('user', $signIn['user'])
                            ->ChangeData('private_key', $private_key)
                            ->ChangeData('useragent', $this->req->useragent)
                            ->setAuth($auth);
                        $this->req = new Requests(auth: $auth, private_key: $private_key, useragent: $this->req->useragent, MainSettings: $settings);

                        $this->RegisterDevice($app_name);
                        break;
                }
            }

            $this->Account = new Account($this->session, $this->req, $settings);
            $this->Chats = new Chats($this->session, $this->req, $settings);
            $this->session->ChangeData('user', $this->Chats->getChatInfo($this->Account->getMySelf()['user_guid'])['user']);
            $this->Folders = new Folders($this->req, $this->session, $this);
            $this->crypto = new Cryption(Cryption::Decode($this->req->getPartOfSessionKey()[0], $this->req->getPartOfSessionKey()[1]), $this->session->data['private_key']);
            $this->Messages = new Messages($this->session, $this->req, $settings);
            $this->Contacts = new Contacts($this->session, $this->req);
        }
    }

    /**
     * send login code to phone number
     *
     * @param string $pass_key if account have password
     * @throws Failure INVALID_INPUT
     * @return array API result
     */
    private function sendCode(string $pass_key = ''): array
    {
        $d = [
            'phone_number' => (string)$this->phone_number,
            'send_type' => 'SMS'
        ];
        if ($pass_key != '') {
            $d['pass_key'] = $pass_key;
        }

        $r = $this->req->SendRequest('sendCode', $d, $this->session, true)['data'];

        if (!in_array($r['status'], ['OK', 'SendPassKey'])) {
            throw new Failure('there is an error in result: ' . json_encode(['status' => 'OK', 'status_det' => $r['status']]));
        } else {
            return $r;
        }
    }

    /**
     * sign in to API
     *
     * @param string $phone_code_hash
     * @param integer $code
     * @throws Failure CodeIsExpired, CodeIsInvalid, CodeIsUsed
     * @return array [API result, private_key]
     */
    private function signIn(string $phone_code_hash, int $code): array
    {
        list($publicKey, $privateKey) = cryption::Generate_RSAkey();

        $r = $this->req->SendRequest('signIn', [
            "phone_number" => (string)$this->phone_number,
            "phone_code_hash" => $phone_code_hash,
            "phone_code" => $code,
            "public_key" => $publicKey
        ], $this->session, true)['data'];

        if ($r['status'] != 'OK') {
            throw new Failure('there is an error in result: ' . json_encode(['status' => 'OK', 'status_det' => $r['status']]));
        } else {
            return [$r, $privateKey];
        }
    }

    /**
     * register login data as a device
     *
     * @param string $app_name it will be showen in device_model (max length = 10)
     * @return array API result
     */
    private function RegisterDevice(string $app_name = ''): array
    {
        if (strlen($app_name) != 0 && $app_name > 10) {
            $app_name = substr($app_name, 0, 10);
        }

        $d = [
            'token_type' => 'Web',
            'token' => '',
            'app_version' => 'WB_4.4.15',
            'lang_code' => 'fa',
            'system_version' => Tools::getOSbyUserAgent($this->req->useragent),
            'device_model' => ($app_name != '' ? "Rubika-lib($app_name) " . self::$VERSION : 'Rubika-lib ' . self::$VERSION),
            'device_hash' => Tools::GenerateDeviceHash($this->req->useragent)
        ];
        $r = $this->req->SendRequest('registerDevice', $d, $this->session);

        if ($r['status'] != 'OK') {
            throw new Failure('there is an error in result: ' . json_encode(['status' => 'OK', 'status_det' => $r['status']]));
        } else {
            return $r;
        }
    }



    // ======================================================= Main class methods ===================================================================



    /**
     * set Runner class for getting updates
     *
     * @param Runner $class Runner Object
     * @return self
     */
    public function proccess(Runner $class): self
    {
        $this->Runner = $class;
        $class->onStart($this->Account->getMySelf());

        return $this;
    }

    /**
     * connect to socket and get updates
     *
     * @return void
     */
    public function RunAndLoop(): void
    {
        if (is_null($this->Runner)) throw new Failure("App Runner Class Isn't Set");

        $this->Chats->getChatsUpdates();

        $loop = Loop::get();

        $default_sockets = $this->req->links['default_sockets'];
        $link = $default_sockets[mt_rand(0, count($default_sockets) - 1)];

        $link = "wss://nsocket9.iranlms.ir:80/";

        \Ratchet\Client\connect($link)->then(
            function (WebSocket $conn) use ($loop) {
                $conn->on('message', function ($msg) {
                    $rawData = $msg->getPayload();
                    $cleanData = trim($rawData, " \t\n\r\0\x0B\"");
                    $h = json_decode($cleanData, true);
                    if (isset($h['data_enc'])) {
                        $update = json_decode($this->crypto->Open($h['data_enc']), true);
                        if (isset($update['show_activities'])) {
                            $update = $update['show_activities'][0];
                            $this->Runner->onAction(match ($update['type']) {
                                'Typing' => ChatActivities::Typing,
                                'Recording' => ChatActivities::Recording,
                                'Uploading' => ChatActivities::Uploading
                            }, $update['object_guid'], $update['user_activity_guid'], $this);
                        } else {
                            $this->Runner->onMessage($update, $this);
                        }
                    }
                });

                $conn->send(json_encode([
                    'api_version' => '6',
                    'auth' => Cryption::Decode($this->req->getPartOfSessionKey()[0], $this->req->getPartOfSessionKey()[1]),
                    'data' => '',
                    'method' => 'handShake'
                ]));

                $loop->addPeriodicTimer(30, function () use ($conn) {
                    $conn->send('{}');
                });
            },
            function ($e) {
                throw new Failure("error on socket connection: {$e->getMessage()}");
            }
        );

        $loop->run();
    }
}

<?php

declare(strict_types=1);

namespace RubikaLib;

use RubikaLib\Helpers\Optimal;
use RubikaLib\interfaces\MainSettings;

/**
 * account object
 */
final class Account
{
    public function __construct(
        private Session $session,
        private Requests $req,
        private MainSettings $settings
    ) {}

    /**
     * get Session Data
     *
     * @return array user data of session
     */
    public function getMySelf(): array
    {
        return $this->session->data['user'];
    }

    /**
     * Terminate This Session
     *
     * @return array API result
     */
    public function logout(): array
    {
        $this->session->terminate();
        return $this->req->SendRequest('logout', array(), $this->session)['data'];
    }

    /**
     * get Account Sessions List
     *
     * @return array API result
     */
    public function getMySessions(): array
    {
        return $this->req->SendRequest('getMySessions', array(), $this->session)['data'];
    }

    /**
     * Terminate a Session
     *
     * @param string $session_key session key
     * @throws Failure on error
     * @return array API result
     */
    public function TerminateSession(string $session_key): array
    {
        if (mb_strlen($session_key) != 64) throw new Failure('session key must be 64 characters');

        return $this->req->SendRequest('terminateSession', [
            'session_key' => $session_key
        ], $this->session)['data'];
    }

    /**
     * Set Account Username
     *
     * @param string $newUserName example: @rubika_lib or rubika_lib
     * @return array API result
     */
    public function ChangeUsername(string $newUserName): array
    {
        $d = $this->req->SendRequest('updateUsername', [
            'username' => str_replace('@', '', $newUserName)
        ], $this->session)['data'];

        if ($d['status'] == 'OK') {
            $this->session->changeData('user', $d['user']);
        }

        return $d;
    }

    /**
     * Edit Account Info
     *
     * @param string $first_name new first name (if want to change)
     * @param string $last_name new last name (if want to change)
     * @param string $bio new bio (if want to change)
     * @throws Failure on error
     * @return array API result
     */
    public function EditProfile(string $first_name = '', string $last_name = '', string $bio = ''): array
    {
        $d = [
            'updated_parameters' => []
        ];

        if ($first_name != '') {
            $d['first_name'] = mb_substr($first_name, 0, 32);
            $d['updated_parameters'][] = 'first_name';
        }
        if ($last_name != '') {
            $d['last_name'] = mb_substr($last_name, 0, 32);
            $d['updated_parameters'][] = 'last_name';
        }
        if ($bio != '') {
            $d['bio'] = $bio;
            $d['updated_parameters'][] = 'bio';
        }

        if ($first_name == '' && $last_name == '' && $bio == '') throw new Failure('edit what??');

        $d = $this->req->SendRequest('updateProfile', $d, $this->session)['data'];

        if (isset($d['chat_update'])) {
            $this->session->changeData('user', $d['user']);
        }

        return $d;
    }

    /**
     * Request Delete Account
     *
     * @return array API result
     */
    public function RequestDeleteAccount(): array
    {
        return $this->req->SendRequest('requestDeleteAccount', array(), $this->session)['data'];
    }

    /**
     * Upload New Profile Picture
     *
     * @param string $file_path must be a picture (png/jpg/jpeg)
     * @param bool $isLink
     * @return array API result
     */
    public function UploadNewProfileAvatar(string $file_path, bool $isLink): array
    {
        $fn = basename($file_path);
        if ($isLink) {
            if (!$this->settings->Optimal) {
                file_put_contents("{$this->settings->Base}$fn", file_get_contents($file_path));
            } else {
                $f = fopen("{$this->settings->Base}$fn", 'a');
                foreach (Optimal::getFile($file_path, $this->settings->UserAgent) as $part) {
                    fwrite($f, $part);
                }
                fclose($f);
            }

            $file_path = "{$this->settings->Base}$fn";
        }

        list($file_id, $dc_id, $access_hash_rec) = $this->sendFileToAPI($file_path);

        return $this->req->SendRequest('uploadAvatar', [
            'thumbnail_file_id' => $file_id,
            'main_file_id' => $file_id
        ], $this->session)['data'];
    }

    /**
     * Delete Profile Picture
     *
     * @param string $avatar_id
     * @return void API result
     */
    public function DeleteMyAvatar(string $avatar_id): array
    {
        return $this->req->SendRequest('deleteAvatar', [
            'object_guid' => $this->getMySelf()['user_guid'],
            'avatar_id' => $avatar_id
        ], $this->session)['data'];
    }

    /**
     * check login password
     *
     * @param string $password
     * @return array API result
     */
    private function checkTwoStepPasscode(string $password): array
    {
        return $this->req->SendRequest('checkTwoStepPasscode', [
            'password' => $password
        ], $this->session)['data'];
    }

    // TODO
    /**
     * change account verify email
     *
     * @param string $password
     * @param string $email
     * @throws Failure if not correct email address
     * @return array API result
     */
    private function requestRecoveryEmail(string $password, string $email): array
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new Failure('not valid email address');

        return $this->req->SendRequest('requestRecoveryEmail', [
            'password' => $password
        ], $this->session)['data'];
    }

    /**
     * verify email code
     *
     * @param string $password
     * @param integer $code
     * @return array API result
     */
    private function verifyRecoveryEmail(string $password, int $code): array
    {
        return $this->req->SendRequest('verifyRecoveryEmail', [
            'password' => $password,
            'code' => (string)$code
        ], $this->session)['data'];
    }

    // TODO
    /**
     * chnage account password
     *
     * @param string $current_password
     * @param string $new_password
     * @param string $hint
     * @return array API result
     */
    private function changePassword(string $current_password, string $new_password, string $hint = 'password hint'): array
    {
        return $this->req->SendRequest('changePassword', [
            'password' => $current_password,
            'new_password' => $new_password,
            'hint' => $hint
        ], $this->session)['data'];
    }

    /**
     * turn off account passwrod
     *
     * @return array API result
     */
    public function turnOffTwoStep(): array
    {
        return $this->req->SendRequest('turnOffTwoStep', array(), $this->session)['data'];
    }

    /**
     * upload file to API
     *
     * @param string $path file path or link
     * @return array [$file_id, $dc_id, $access_hash_rec]
     */
    private function sendFileToAPI(string $path): array
    {
        $fn = basename($path);
        $ex = explode('.', $fn);
        $data = $this->RequestSendFile($fn, filesize($path), $ex[count($ex) - 1]);

        return [$data['id'], $data['dc_id'], $this->req->SendFileToAPI($path, $data['id'], $data['access_hash_send'], $data['upload_url'])['data']['access_hash_rec']];
    }

    /**
     * it will use to upload file
     *
     * @param string $file_name
     * @param integer $size
     * @param string $mime
     * @return array API result
     */
    private function RequestSendFile(string $file_name, int $size, string $mime): array
    {
        return $this->req->SendRequest('requestSendFile', [
            'file_name' => $file_name,
            'size' => $size,
            'mime' => $mime
        ], $this->session)['data'];
    }
}

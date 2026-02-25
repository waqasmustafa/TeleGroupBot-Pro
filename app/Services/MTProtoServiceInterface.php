<?php

namespace App\Services;

interface MTProtoServiceInterface
{
    public function setAccount($account);
    public function setSessionFile($session_file);
    public function login($phone, $api_id, $api_hash, $session_name = null, $proxy = null);
    public function completeLogin($code);
    public function complete2FA($password);
    public function sendMessage($toId, $message);
    public function getMessages($limit = 20);
    public function logout();
    public function stop();
    public function startListener($account);
}

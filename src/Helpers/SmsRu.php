<?php
namespace App\Helpers;

class SmsRu
{
    private string $apiId;

    public function __construct(string $apiId)
    {
        $this->apiId = $apiId;
    }

    public function send(string $phone, string $message): bool
    {
        $query = http_build_query([
            'api_id' => $this->apiId,
            'to'     => $phone,
            'msg'    => $message,
            'json'   => 1,
        ]);

        $response = @file_get_contents('https://sms.ru/sms/send?' . $query);
        if ($response === false) {
            return false;
        }
        $data = json_decode($response, true);
        return isset($data['status']) && $data['status'] === 'OK';
    }
}

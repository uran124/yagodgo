<?php
namespace App\Helpers;

class MailSender
{
    private string $from;

    public function __construct(string $from)
    {
        $this->from = $from;
    }

    public function send(string $to, string $subject, string $message): bool
    {
        $headers = "From: {$this->from}\r\n" .
                   "Content-Type: text/plain; charset=utf-8";
        return mail($to, $subject, $message, $headers);
    }
}

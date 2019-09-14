<?php
namespace App\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class Mailer
{
    private $mail;

    public function __construct($mail_settings)
    {
        date_default_timezone_get('Asia/Jakarta');

        $this->mail = new PHPMailer;
        $this->mail->isSMTP();
        // $this->mail->SMTPDebug = 2;
        $this->mail->Debugoutput = 'html';
        // $this->mail->Host = 'smtp.mailtrap.io';
        // $this->mail->Port = 2525;
        $this->mail->Host = 'smtp.hostinger.co.id';
        $this->mail->Port = 587;
        $this->mail->SMTPAuth = true;

        $this->setSender(
            $mail_settings['username'],
            $mail_settings['password'],
            $mail_settings['display_name'],
            $mail_settings['display_email'],
            $mail_settings['reply_name'],
            $mail_settings['reply_email']
        );
    }

    public function setSender($email, $password, $display_name, $display_email, $reply_name, $reply_email)
    {
        $this->mail->Username = $email;
        $this->mail->Password = $password;
        $this->mail->setFrom($display_email, $display_name);
        $this->mail->addReplyTo($reply_email, $reply_name);
    }

    public function sendEmail($to_email, $subject, $message, $template_name = '')
    {
        // $this->mail->addAddress($to_email, $to_name);
        $this->mail->addAddress($to_email);
        $this->mail->Subject = $subject;
        // $this->mail->msgHTML(file_get_contents($template_name));
        $this->mail->msgHTML($message);
        $this->mail->AltBody = $message;

        $send_email = $this->mail->send();
        if (!$send_email) {
            return $this->mail->ErrorInfo;
        } else {
            return $send_email;
        }
    }
}

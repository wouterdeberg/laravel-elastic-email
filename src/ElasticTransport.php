<?php


namespace Flux\LaravelElasticEmail;

use GuzzleHttp\ClientInterface;
use Illuminate\Mail\Transport\Transport;
use Illuminate\Support\Facades\Storage;
use Swift_Mime_SimpleMessage;

class ElasticTransport extends Transport
{
    protected $client;
    protected $key;
    protected $account;
    protected $url = "https://api.elasticemail.com/v2/email/send";

    public function __construct(ClientInterface $client, $key, $account)
    {
        $this->client = $client;
        $this->key = $key;
        $this->account = $account;
    }

    public function send(Swift_Mime_SimpleMessage $message, &$failedRecipients = null)
    {
        $this->beforeSendPerformed($message);

        $data = [
            'apikey' => $this->key,
            'account' => $this->account,
            'msgTo' => $this->getEmailAddresses($message),
            'msgCC' => $this->getEmailAddresses($message, 'getCc'),
            'msgBcc' => $this->getEmailAddresses($message, 'getBcc'),
            'msgFrom' => $this->getFromAddress($message)['email'],
            'msgFromName' => $this->getFromAddress($message)['name'],
            'from' => $this->getFromAddress($message)['email'],
            'fromName' => $this->getFromAddress($message)['name'],
            'to' => $this->getEmailAddresses($message),
            'subject' => $message->getSubject(),
            'bodyHtml' => $message->getBody(),
            'bodyText' => $this->getText($message),
            'isTransactional' => true,
        ];

        $attachments = $message->getChildren();
        $attachmentCount = $this->checkAttachmentCount($attachments);

        if ($attachmentCount > 0) {
            $data = $this->attach($attachments, $data);
        }
        $ch = curl_init();

        curl_setopt_array($ch, array(
            CURLOPT_URL => $this->url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_SSL_VERIFYPEER => false
        ));

        $result = curl_exec($ch);
        curl_close($ch);

        if ($attachmentCount > 0) {
            $this->deleteTempAttachmentFiles($data, $attachmentCount);
        }

        return $result;
    }

    protected function getEmailAddresses(Swift_Mime_SimpleMessage $message, $method = 'getTo')
    {
        $data = call_user_func([$message, $method]);

        if (is_array($data)) {
            return implode(',', array_keys($data));
        }
        return '';
    }

    protected function getFromAddress(Swift_Mime_SimpleMessage $message)
    {
        return [
            'email' => array_keys($message->getFrom())[0],
            'name' => array_values($message->getFrom())[0],
        ];
    }

    protected function getText(Swift_Mime_SimpleMessage $message)
    {
        $text = null;

        foreach ($message->getChildren() as $child) {
            if ($child->getContentType() == 'text/plain') {
                $text = $child->getBody();
            }
        }

        return $text;
    }

    public function checkAttachmentCount($attachments)
    {
        $count = 0;
        foreach ($attachments AS $attachment) {
            if ($attachment instanceof \Swift_Attachment) {
                $count++;
            }
        }
        return $count;
    }

    public function attach($attachments, $data)
    {
        if (is_array($attachments) && count($attachments) > 0) {
            $i = 1;
            foreach ($attachments AS $attachment) {
                if ($attachment instanceof \Swift_Attachment) {
                    $attachedFile = $attachment->getBody();
                    $fileName = $attachment->getFilename();
                    $ext = pathinfo($fileName, PATHINFO_EXTENSION);
                    $tempName = uniqid() . '.' . $ext;
                    Storage::put($tempName, $attachedFile);
                    $type = $attachment->getContentType();
                    $attachedFilePath = storage_path($tempName);
                    $data['file_' . $i] = new \CURLFile($attachedFilePath, $type, $fileName);
                    $i++;
                }
            }
        }

        return $data;
    }

    protected function deleteTempAttachmentFiles($data, $count)
    {
        for ($i = 1; $i <= $count; $i++) {
            $file = $data['file_' . $i]->name;
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }
}

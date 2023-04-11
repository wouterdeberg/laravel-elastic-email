<?php


namespace FlexFlux\LaravelElasticEmail;

use Symfony\Component\Mime\Email;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\MessageConverter;
use Symfony\Component\Mailer\Transport\AbstractTransport;

class ElasticTransport extends AbstractTransport
{
    protected $key;
    protected $url = "https://api.elasticemail.com/v2/email/send";

    public function __construct($key)
    {
        parent::__construct();

        $this->key = $key;
    }

    public function __toString(): string
    {
        return 'elasticemail';
    }

     /**
     * {@inheritdoc}
     */
    public function doSend(SentMessage $message) : void
    {
        $email = MessageConverter::toEmail($message->getOriginalMessage());

        $data = [
            'apikey' => $this->key,
            'msgTo' => $this->getEmailAddresses($email),
            'msgCC' => $this->getEmailAddresses($email, 'getCc'),
            'msgBcc' => $this->getEmailAddresses($email, 'getBcc'),
            'msgFrom' => $email->getFrom()[0]->getAddress(),
            'msgFromName' => $email->getFrom()[0]->getName(),
            'from' => $email->getFrom()[0]->getAddress(),
            'fromName' => $email->getFrom()[0]->getName(),
            'replyTo' => $this->getEmailAddresses($email, 'getReplyTo'),
            'to' => $this->getEmailAddresses($email),
            'subject' => $email->getSubject(),
            'bodyHtml' => $email->getHtmlBody(),
            'bodyText' => $email->getTextBody(),
            'isTransactional' => $email->getHeaders()->getHeaderBody('x-metadata-transactional') ? true : false,
            'replyTo' => $this->getEmailAddresses($email, 'getReplyTo'),
        ];

        $attachments = $email->getAttachments();
        $attachmentCount = count($attachments);

        if ($attachmentCount > 0) {
            $data = $this->attach($attachments, $data);
        }

        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $this->url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        curl_exec($ch);
        curl_close($ch);

        if ($attachmentCount > 0) {
            $this->deleteTempAttachmentFiles($data, $attachmentCount);
        }
    }

    /**
     * Add attachments to post data array.
     * @param $attachments
     * @param $data
     * @return mixed
     */

    public function attach($attachments, $data)
    {
        if (is_array($attachments) && count($attachments) > 0) {
            $i = 1;
            foreach ($attachments as $attachment) {
                if ($attachment instanceof DataPart) {
                    $attachedFile = $attachment->getBody();
                    $fileName = $attachment->getPreparedHeaders()->getHeaderParameter('Content-Disposition', 'filename');
                    $ext = pathinfo($fileName, PATHINFO_EXTENSION);
                    $tempName = uniqid().'.'.$ext;
                    Storage::put($tempName, $attachedFile);
                    $type = $attachment->getMediaType().'/'.$attachment->getMediaSubtype();
                    $attachedFilePath = storage_path($tempName);
                    $data['file_'.$i] = new \CurlFile($attachedFilePath, $type, $fileName);
                    $i++;
                }
            }
        }

        return $data;
    }

    /**
     * Retrieve requested emailaddresses from email.
     * @param Email $email
     * @param string $method
     * @return string
     */

    protected function getEmailAddresses(Email $email, $method = 'getTo')
    {
        $data = call_user_func([$email, $method]);

        $addresses = [];
        if (is_array($data)) {
            foreach ($data as $address) {
                $addresses[] = $address->getAddress();
            }
        }

        return implode(',', $addresses);
    }

    /**
     * Delete temporary attachment files.
     * @param $data
     * @param $count
     */

    protected function deleteTempAttachmentFiles($data, $count) : void
    {
        for ($i = 1; $i <= $count; $i++) {
            $file = $data['file_'.$i]->name;
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }
}

<?php

namespace FlexFlux\LaravelElasticEmail;

use Symfony\Component\Mime\Email;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\MessageConverter;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mailer\Exception\TransportException;

class ElasticTransport extends AbstractTransport
{
    protected $key;
    protected $url = 'https://api.elasticemail.com/v2/email/send';

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

        $response = curl_exec($ch);
        $curlErrno = curl_errno($ch);
        $curlError = curl_error($ch);
        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        if ($attachmentCount > 0) {
            $this->deleteTempAttachmentFiles($data, $attachmentCount);
        }

        if ($curlErrno !== 0) {
            throw new TransportException(sprintf('Elastic Email request failed: %s', $curlError));
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new TransportException(sprintf(
                'Elastic Email returned HTTP %d: %s',
                $statusCode,
                is_string($response) ? $response : '(no response body)'
            ));
        }

        $decoded = is_string($response) ? json_decode($response, true) : null;

        if (
            is_array($decoded) &&
            array_key_exists('success', $decoded) &&
            $decoded['success'] === false
        ) {
            throw new TransportException(sprintf(
                'Elastic Email reported failure: %s',
                $decoded['error'] ?? 'unknown error'
            ));
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

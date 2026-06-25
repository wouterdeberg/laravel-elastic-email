<?php

namespace FlexFlux\LaravelElasticEmail;

use Symfony\Component\Mime\Email;
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

        $from = $email->getFrom();
        if (empty($from)) {
            throw new TransportException('Elastic Email: email has no From address.');
        }

        $data = [
            'apikey' => $this->key,
            'msgTo' => $this->getEmailAddresses($email),
            'msgCC' => $this->getEmailAddresses($email, 'getCc'),
            'msgBcc' => $this->getEmailAddresses($email, 'getBcc'),
            'msgFrom' => $from[0]->getAddress(),
            'msgFromName' => $from[0]->getName(),
            'from' => $from[0]->getAddress(),
            'fromName' => $from[0]->getName(),
            'replyTo' => $this->getEmailAddresses($email, 'getReplyTo'),
            'to' => $this->getEmailAddresses($email),
            'subject' => $email->getSubject(),
            'bodyHtml' => $email->getHtmlBody(),
            'bodyText' => $email->getTextBody(),
            'isTransactional' => $email->getHeaders()->getHeaderBody('x-metadata-transactional') ? true : false,
        ];

        $attachments = $email->getAttachments();
        $tempFiles = [];

        if (count($attachments) > 0) {
            $data = $this->attach($attachments, $data, $tempFiles);
        }

        $result = $this->executeCurl($data);

        $this->deleteTempFiles($tempFiles);

        if ($result['errno'] !== 0) {
            throw new TransportException(sprintf('Elastic Email request failed: %s', $result['error']));
        }

        if ($result['statusCode'] < 200 || $result['statusCode'] >= 300) {
            throw new TransportException(sprintf(
                'Elastic Email returned HTTP %d: %s',
                $result['statusCode'],
                is_string($result['response']) ? $result['response'] : '(no response body)'
            ));
        }

        $decoded = is_string($result['response']) ? json_decode($result['response'], true) : null;

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
     * Execute the cURL request and return a result array.
     * Extracted into a protected method so tests can override it.
     *
     * @param array<string,mixed> $postData
     * @return array{response: string|false, errno: int, error: string, statusCode: int}
     */
    protected function executeCurl(array $postData): array
    {
        $ch = curl_init();

        if ($ch === false) {
            throw new TransportException('Elastic Email: failed to initialise cURL.');
        }

        curl_setopt_array($ch, [
            CURLOPT_URL => $this->url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postData,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
        ]);

        $response   = curl_exec($ch);
        $errno      = curl_errno($ch);
        $error      = curl_error($ch);
        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        return compact('response', 'errno', 'error', 'statusCode');
    }

    /**
     * Add attachments to post data array.
     * @param $attachments
     * @param $data
     * @param array $tempFiles Populated with absolute paths of temp files for later cleanup.
     * @return mixed
     */
    public function attach($attachments, $data, array &$tempFiles = [])
    {
        if (is_array($attachments) && count($attachments) > 0) {
            $i = 1;
            foreach ($attachments as $attachment) {
                if ($attachment instanceof DataPart) {
                    $fileName = $attachment->getPreparedHeaders()->getHeaderParameter('Content-Disposition', 'filename') ?? '';
                    $ext = pathinfo($fileName, PATHINFO_EXTENSION);
                    $placeholder = tempnam(sys_get_temp_dir(), 'elastic_');
                    if ($placeholder === false) {
                        throw new TransportException('Elastic Email: could not create temporary file for attachment.');
                    }
                    $tempPath = $ext !== '' ? $placeholder . '.' . $ext : $placeholder;
                    file_put_contents($tempPath, $attachment->getBody());
                    $type = $attachment->getMediaType().'/'.$attachment->getMediaSubtype();
                    $data['file_'.$i] = new \CurlFile($tempPath, $type, $fileName);
                    $tempFiles[] = $placeholder;
                    if ($tempPath !== $placeholder) {
                        $tempFiles[] = $tempPath;
                    }
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
     * @param array $tempFiles Absolute paths returned by attach()
     */
    protected function deleteTempFiles(array $tempFiles): void
    {
        foreach ($tempFiles as $path) {
            if (file_exists($path)) {
                unlink($path);
            }
        }
    }
}

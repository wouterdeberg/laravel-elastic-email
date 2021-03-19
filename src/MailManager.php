<?php


namespace Flux\LaravelElasticEmail;

use Illuminate\Mail\MailManager as LaravelMailManager;

class MailManager extends LaravelMailManager
{
    public function createElasticemailTransport()
    {
        $config = $this->app['config']->get('mail.mailers.elastic_email', []);
        return new ElasticTransport(
            $this->guzzle($config),
            $config['key'],
            $config['account'],
        );
    }
}

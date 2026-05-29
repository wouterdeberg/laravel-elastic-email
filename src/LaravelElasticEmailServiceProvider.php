<?php

namespace FlexFlux\LaravelElasticEmail;

use Illuminate\Mail\MailManager;
use Illuminate\Support\ServiceProvider;

class LaravelElasticEmailServiceProvider extends ServiceProvider
{
    public function register()
    {
        $app = $this->app;
        $this->app->afterResolving(MailManager::class, function (MailManager $manager) use ($app) {
            $manager->extend('elastic_email', function () use ($app) {
                $config = $app['config']->get('mail.mailers.elastic_email', []);

                return new ElasticTransport(
                    $config['key'],
                );
            });
        });
    }
}

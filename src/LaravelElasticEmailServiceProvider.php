<?php

namespace FlexFlux\LaravelElasticEmail;

use Illuminate\Mail\MailManager;
use Illuminate\Support\ServiceProvider;

class LaravelElasticEmailServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->afterResolving(MailManager::class, function (MailManager $manager) {
            $manager->extend('elastic_email', function () {
                $config = $this->app['config']->get('mail.mailers.elastic_email', []);

                return new ElasticTransport(
                    $config['key'],
                );
            });
        });
    }
}

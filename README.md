# FLUX - Laravel Elastic Email

Laravel Elastic Email is a wrapper for Elastic Email.
You can send e-mails in your project just like you usually do with Laravel's native mailers, the package makes sure the e-mails are send via the Elastic Email API using your Elastic Email account.



## Requires
Laravel version 8.12 or higher.

### Installation ###

* Step 1: Install package via composer.

```bash
composer require flux/laravel-elastic-email
```

* Step 2: Add your account and API keys to your **.env file**.
```
ELASTIC_ACCOUNT=<Your public account key>
ELASTIC_KEY=<Your API key>
```

* Step 3: Update **MAIL_MAILER** with 'elastic_email' in your **.env file**.
```
MAIL_MAILER=elastic_email
```

* Step 4: Add this new mailer to your **config/mail.php*** file.
```php
'mailers' => [
    ...
    'elastic_email' => [
        'transport' => 'elasticemail',
        'key' => env('ELASTIC_KEY'),
        'account' => env('ELASTIC_ACCOUNT')
    ],  
    ...
],
```

* Step 5: In your **config/app.php** file go to your providers array and comment out Laravel's default MailServiceProvider and add the following package provider:
```php
'providers' => [
    /*
     * Laravel Framework Service Providers...
     */
    ...
//    Illuminate\Mail\MailServiceProvider::class,
      \Flux\LaravelElasticEmail\LaravelElasticEmailServiceProvider::class,
    ...
],
```

### Usage ###

Read Laravels documentation on how to send E-mails with the Laravel Framework.

https://laravel.com/docs/8.x/mail
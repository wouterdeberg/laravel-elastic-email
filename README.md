# FlexFlux - Laravel Elastic Email

Laravel Elastic Email is a wrapper for Elastic Email.
You can send e-mails in your project just like you usually do with Laravel's native mailers, the package makes sure the e-mails are send via the Elastic Email API using your Elastic Email account.

## Requires
V1 - Laravel version 8.12 or higher.
V2 - Laravel version 9.0 or higher.

### Installation ###

* Step 1: Install package via composer.

```bash
composer require flexflux/laravel-elastic-email
```

* Step 2: Add your account and API keys to your **.env file**.
```
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
        'transport' => 'elastic_email',
        'key' => env('ELASTIC_KEY')
    ],  
    ...
],
```

* Step 5: In your **config/app.php** file go to your providers array and add the following package provider:
```php
'providers' => [
    /*
     * Laravel Framework Service Providers...
     */
    ...
      \FlexFlux\LaravelElasticEmail\LaravelElasticEmailServiceProvider::class,
    ...
],
```

* Step 5b: If you're using V1: comment out Laravel's default MailServiceProvider in the **config/app.php**.

### Usage ###

Read Laravels documentation on how to send E-mails with the Laravel Framework.

https://laravel.com/docs/8.x/mail

### Upgrade Guide from V1 to V2 ###
Upgrade guide from V1 to V2:
- Turn the MailServiceProvider back on in your app.php config file.
- Remove your account ID environment variable.
- Update your elastic_email mailer attribute in your mail.php configuration.

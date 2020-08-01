<?php

require_once(__DIR__ . '/view.php');

$configfile_content = file_get_contents(__DIR__ . '/config/config.json');
$config = json_decode($configfile_content, true);

// Configuration
Flight::set('flight.views.path', __DIR__ . '/views');
Flight::set('flight.views.extension', '.twig');
Flight::set('app.config', $config);

// Configure template engine
Flight::register('view', 'TwigView', array(
    Flight::get('flight.views.path'),
    Flight::get('flight.views.extension')
));

Flight::view()->set('config', Flight::get('app.config'));

// Routes
Flight::route('/mail/config-v1.1.xml', function() {
    $email = Flight::request()->query['emailaddress'];

    Flight::response()->header('Content-Type', 'application/xml');
    Flight::render('autoconfig.xml', array(
        'email' => empty($email) ? '%EMAILADDRESS%' : $email
    ));
});

Flight::route('/autodiscover/autodiscover.xml', function() {
    $email = NULL;

    if (Flight::request()->method == 'POST') {
        $content_type = Flight::request()->type;

        if ($content_type != 'application/xml' && $content_type != 'text/xml') {
            Flight::halt(400);
            return;
        }

        $body = Flight::request()->getBody();
        $xml = simplexml_load_string($body);

        if (empty($xml->Request)) {
            Flight::halt(400);
            return;
        }

        if (
            empty($xml->Request->AcceptableResponseSchema)
            || (
                $xml->Request->AcceptableResponseSchema != 'http://schemas.microsoft.com/exchange/autodiscover/outlook/responseschema/2006a'
                && $xml->Request->AcceptableResponseSchema != 'https://schemas.microsoft.com/exchange/autodiscover/outlook/responseschema/2006a'
            )
        ) {
            error_log('Missing acceptable response schema: ' . $xml->Request->AcceptableResponseSchema);
            Flight::halt(400);
            return;
        }

        if (empty($xml->Request->EMailAddress)) {
            Flight::halt(400);
            return;
        }

        $email = $xml->Request->EMailAddress;

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Flight::halt(400);
            return;
        }
    }

    Flight::response()->header('Content-Type', 'application/xml');
    Flight::render('autodiscover.xml', array(
        'email' => $email
    ));
});

Flight::route('/email.mobileconfig', function() {
    $email = Flight::request()->query['email'];

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        Flight::halt(400);
        return;
    }

    $config = Flight::get('app.config');

    $payload_uuid = md5($email);
    $payload_identifier = implode('.', array_reverse(explode('.', "mobileconfig.{$config['domain']}")));

    $payload_mail_uuid = md5("{$email}/mail");
    $payload_mail_identifier = "{$payload_identifier}.mail";

    $filename = "{$config['domain']}.mobileconfig";

    Flight::response()->header('Content-Type', 'application/x-apple-aspen-config; charset=utf-8');
    Flight::response()->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
    Flight::render('mobileconfig.xml', array(
        'payload_uuid' => $payload_uuid,
        'payload_identifier' => $payload_identifier,
        'payload_mail_uuid' => $payload_mail_uuid,
        'payload_mail_identifier' => $payload_mail_identifier,
        'email' => $email
    ));
});

Flight::start();
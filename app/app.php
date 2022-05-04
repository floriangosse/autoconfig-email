<?php

use Ramsey\Uuid\Uuid;

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
                // TODO: Replace with str_ends_with($xml->Request->AcceptableResponseSchema, '://schemas.microsoft.com/exchange/autodiscover/outlook/responseschema/2006a')
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
    $config = Flight::get('app.config');
    $domain = $config['domain'];

    $request = Flight::request();
    $response = Flight::response();
    $local_part = $request->data['local_part'];
    $incoming_mail_server_username_local_part = $request->data['incoming_mail_server_username_local_part'];
    $outgoing_mail_server_username_local_part = $request->data['outgoing_mail_server_username_local_part'];

    // Collect errors if it's a POST request
    $errors = array();
    if ($request->method == 'POST') {
        $email = format_email(
            array(
                'local_part' => $local_part,
                'domain' => $domain
            )
        );

        if (empty($local_part) || !is_valid_email($email)) {
            array_push($errors, 'Invalid email address');
        }

        if (!empty($incoming_mail_server_username_local_part)) {
            $incoming_mail_server_username = format_email(
                array(
                    'local_part' => $incoming_mail_server_username_local_part,
                    'domain' => $domain
                )
            );

            if (!is_valid_email($incoming_mail_server_username)) {
                array_push($errors, 'Invalid incoming server username');
            }
        }

        if (!empty($outgoing_mail_server_username_local_part)) {
            $outgoing_mail_server_username = format_email(
                array(
                    'local_part' => $incoming_mail_server_username_local_part,
                    'domain' => $domain
                )
            );

            if (!is_valid_email($outgoing_mail_server_username)) {
                array_push($errors, 'Invalid outgoing server username');
            }
        }
    }

    // Render the form if it's a GET request or a POST request with errors
    if (
        $request->method == 'GET' ||
        (
            $request->method == 'POST' &&
            count($errors) != 0
        )
    ) {
        $advanced = $request->query['advanced'] == '1';
        $email_from_query = $request->query['email'];

        // In case of a valid email address in the query params we set this as the form value
        if (
            $request->method == 'GET' &&
            !empty($email_from_query) &&
            is_valid_email($email_from_query)
        ) {
            $email_obj_from_query = parse_email($email_from_query);

            if ($domain == $email_obj_from_query->domain) {
                $local_part = $email_obj_from_query->local_part;
            }
        }

        $action = parse_url($request->url, PHP_URL_PATH) . ($advanced ? '?advanced=1' : '');

        $basic_options_url = '/email.mobileconfig';
        $advanced_options_url = '/email.mobileconfig?advanced=1';

        Flight::render('mobileconfig.html', array(
            'advanced' => $advanced,
            'basic_options_url' => $basic_options_url,
            'advanced_options_url' => $advanced_options_url,
            'domain' => $domain,
            'action' => $action,
            'values' => array(
                'local_part' => $local_part,
                'incoming_mail_server_username_local_part' => $incoming_mail_server_username_local_part ?: '',
                'outgoing_mail_server_username_local_part' => $outgoing_mail_server_username_local_part ?: ''
            ),
            'errors' => $errors
        ));


        if (count($errors) > 0) {
            $response->status(400);
        }

        return;
    }

    $sanitized_local_part = sanitize_local_part($local_part);

    $email = format_email(
        array(
            'local_part' => $local_part,
            'domain' => $domain
        )
    );
    $incoming_mail_server_username = empty($incoming_mail_server_username_local_part)
        ? $email
        : format_email(
            array(
                'local_part' => $incoming_mail_server_username_local_part,
                'domain' => $domain
            )
        );
    $outgoing_mail_server_username = empty($outgoing_mail_server_username_local_part)
        ? $email
        : format_email(
            array(
                'local_part' => $outgoing_mail_server_username_local_part,
                'domain' => $domain
            )
        );

    $uuid_namespace = Uuid::uuid5(Uuid::NAMESPACE_DNS, $domain);

    $payload_uuid = Uuid::uuid5($uuid_namespace, $local_part);
    $payload_identifier = implode('.', array_reverse(explode('.', "mobileconfig.{$local_part}.{$domain}")));

    $payload_mail_uuid = Uuid::uuid5($uuid_namespace, "{$local_part}/mail");
    $payload_mail_identifier = "{$payload_identifier}.mail";

    $filename = "{$sanitized_local_part}@{$domain}.mobileconfig";

    $response->header('Content-Type', 'application/x-apple-aspen-config; charset=utf-8');
    // $response->header('Content-Type', 'text/plain; charset=utf-8');
    // $response->header('Content-Type', 'application/xml; charset=utf-8');
    $response->header('Content-Disposition', "attachment; filename=\"{$filename}\"");

    Flight::render('mobileconfig.xml', array(
        'payload_uuid' => $payload_uuid->toString(),
        'payload_identifier' => $payload_identifier,
        'payload_mail_uuid' => $payload_mail_uuid->toString(),
        'payload_mail_identifier' => $payload_mail_identifier,
        'email' => $email,
        'incoming_mail_server_username' => $incoming_mail_server_username,
        'outgoing_mail_server_username' => $outgoing_mail_server_username
    ));
});

function is_valid_email($email) {
    return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
}

function parse_email($email) {
    return (object) array(
        'local_part' => substr($email, 0, strpos($email, '@')),
        'domain' => substr($email, strpos($email, '@') + 1)
    );
}

function format_email($obj) {
    $obj = (object) $obj;
    return "{$obj->local_part}@{$obj->domain}";
}

function sanitize_local_part($local_part) {
    return str_replace(str_split("!#$%&'*+-/=?^_`{|}~"), "_", $local_part);
}

Flight::start();
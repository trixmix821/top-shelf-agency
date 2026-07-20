<?php

declare(strict_types=1);

require_once __DIR__ . '/lib.php';

const GROWTH_AUDIT_MAX_BODY_BYTES = 32768;

$requestId = bin2hex(random_bytes(8));

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-Request-ID: ' . $requestId);

/** @param array<string,mixed> $body */
function growth_audit_respond(int $status, array $body): never
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}

/** @return array<string,mixed> */
function growth_audit_read_json(): array
{
    $contentType = strtolower(trim(explode(';', (string) ($_SERVER['CONTENT_TYPE'] ?? ''))[0]));
    if ($contentType !== 'application/json') {
        throw new GrowthAuditValidationException(['request' => 'Content-Type must be application/json.']);
    }

    $contentLength = isset($_SERVER['CONTENT_LENGTH']) ? (int) $_SERVER['CONTENT_LENGTH'] : 0;
    if ($contentLength > GROWTH_AUDIT_MAX_BODY_BYTES) {
        throw new GrowthAuditValidationException(['request' => 'The request is too large.']);
    }

    $raw = file_get_contents('php://input', false, null, 0, GROWTH_AUDIT_MAX_BODY_BYTES + 1);
    if (!is_string($raw) || $raw === '' || strlen($raw) > GROWTH_AUDIT_MAX_BODY_BYTES) {
        throw new GrowthAuditValidationException(['request' => 'A valid JSON request is required.']);
    }
    try {
        $decoded = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        throw new GrowthAuditValidationException(['request' => 'A valid JSON request is required.']);
    }
    if (!is_array($decoded)) {
        throw new GrowthAuditValidationException(['request' => 'A valid JSON object is required.']);
    }
    return $decoded;
}

function growth_audit_client_ip(): string
{
    $ip = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
    return filter_var($ip, FILTER_VALIDATE_IP) !== false ? $ip : 'unknown';
}

function growth_audit_log(string $requestId, Throwable $error): void
{
    error_log(sprintf(
        '[growth-audit] request=%s type=%s message=%s',
        $requestId,
        get_class($error),
        preg_replace('/[\r\n]+/', ' ', $error->getMessage())
    ));
}

try {
    $config = GrowthAuditConfig::fromEnvironment();
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

    if ($method === 'GET') {
        $token = (new GrowthAuditBotToken($config))->issue();
        growth_audit_respond(200, ['ok' => true, 'token' => $token]);
    }

    if ($method !== 'POST') {
        header('Allow: GET, POST');
        growth_audit_respond(405, ['ok' => false, 'message' => 'Method not allowed.']);
    }

    $origin = rtrim(trim((string) ($_SERVER['HTTP_ORIGIN'] ?? '')), '/');
    if ($origin === '' || !hash_equals($config->allowedOrigin, $origin)) {
        growth_audit_respond(403, ['ok' => false, 'message' => 'The request could not be verified.']);
    }

    $rateLimiter = new GrowthAuditFileRateLimiter($config);
    $rateLimiter->consume(growth_audit_client_ip());
    $payload = growth_audit_read_json();

    if (trim((string) ($payload['company_fax'] ?? '')) !== '') {
        growth_audit_respond(200, ['ok' => true, 'redirect' => '/thank-you/']);
    }

    $submissionToken = is_string($payload['submission_token'] ?? null)
        ? (string) $payload['submission_token']
        : '';
    (new GrowthAuditBotToken($config))->verify($submissionToken);
    unset($payload['submission_token'], $payload['company_fax']);

    $transport = new GrowthAuditCurlTransport($config->ghlToken, $config->requestTimeoutSeconds);
    $client = new GrowthAuditHighLevelApiClient($config, $transport);
    $service = new GrowthAuditService(
        $config,
        new GrowthAuditValidator(),
        $client,
        new GrowthAuditFileLock($config)
    );
    $service->submit($payload);

    growth_audit_respond(200, ['ok' => true, 'redirect' => '/thank-you/']);
} catch (GrowthAuditValidationException $error) {
    growth_audit_respond(422, [
        'ok' => false,
        'message' => 'Please review the highlighted fields and try again.',
        'fields' => $error->errors,
    ]);
} catch (GrowthAuditRateLimitException $error) {
    header('Retry-After: ' . $error->retryAfter);
    growth_audit_respond(429, [
        'ok' => false,
        'message' => 'Too many requests. Please wait and try again.',
    ]);
} catch (GrowthAuditTokenException $error) {
    growth_audit_respond(403, [
        'ok' => false,
        'message' => 'The request could not be verified. Refresh the page and try again.',
    ]);
} catch (GrowthAuditConfigException $error) {
    growth_audit_log($requestId, $error);
    growth_audit_respond(503, [
        'ok' => false,
        'message' => 'The audit form is temporarily unavailable. Please email hello@topshelfagency.biz.',
    ]);
} catch (GrowthAuditUpstreamException $error) {
    growth_audit_log($requestId, $error);
    growth_audit_respond(502, [
        'ok' => false,
        'message' => 'We could not send your request. Try again or email hello@topshelfagency.biz.',
    ]);
} catch (Throwable $error) {
    growth_audit_log($requestId, $error);
    growth_audit_respond(500, [
        'ok' => false,
        'message' => 'We could not send your request. Try again or email hello@topshelfagency.biz.',
    ]);
}

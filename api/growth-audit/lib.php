<?php

declare(strict_types=1);

final class GrowthAuditConfigException extends RuntimeException
{
}

final class GrowthAuditValidationException extends RuntimeException
{
    /** @var array<string,string> */
    public array $errors;

    /** @param array<string,string> $errors */
    public function __construct(array $errors)
    {
        parent::__construct('Growth Audit validation failed');
        $this->errors = $errors;
    }
}

final class GrowthAuditUpstreamException extends RuntimeException
{
}

final class GrowthAuditRateLimitException extends RuntimeException
{
    public int $retryAfter;

    public function __construct(int $retryAfter)
    {
        parent::__construct('Growth Audit rate limit exceeded');
        $this->retryAfter = max(1, $retryAfter);
    }
}

final class GrowthAuditTokenException extends RuntimeException
{
}

final class GrowthAuditConfig
{
    public string $appSecret;
    public string $allowedOrigin;
    public string $storageDir;
    public int $rateLimitMax;
    public int $rateLimitWindowSeconds;
    public int $tokenMinAgeSeconds;
    public int $tokenTtlSeconds;
    public int $requestTimeoutSeconds;
    public string $ghlToken;
    public string $ghlLocationId;
    public string $ghlPipelineId;
    public string $ghlPipelineStageId;
    public string $tradeFieldKey;
    public string $teamSizeFieldKey;
    public string $bestContactTimeFieldKey;
    public string $concernFieldKey;
    public string $consentFieldKey;

    /** @param array<string,mixed> $values */
    public static function fromArray(array $values): self
    {
        $required = [
            'GROWTH_AUDIT_APP_SECRET',
            'GROWTH_AUDIT_ALLOWED_ORIGIN',
            'GROWTH_AUDIT_STORAGE_DIR',
            'GHL_PRIVATE_INTEGRATION_TOKEN',
            'GHL_LOCATION_ID',
            'GHL_PIPELINE_ID',
            'GHL_PIPELINE_STAGE_ID',
            'GHL_CF_TRADE_KEY',
            'GHL_CF_TEAM_SIZE_KEY',
            'GHL_CF_BEST_CONTACT_TIME_KEY',
            'GHL_CF_CONCERN_KEY',
            'GHL_CF_CONSENT_KEY',
        ];

        $missing = [];
        foreach ($required as $name) {
            if (!isset($values[$name]) || trim((string) $values[$name]) === '') {
                $missing[] = $name;
            }
        }
        if ($missing !== []) {
            throw new GrowthAuditConfigException(
                'Missing environment variables: ' . implode(', ', $missing)
            );
        }

        $config = new self();
        $config->appSecret = trim((string) $values['GROWTH_AUDIT_APP_SECRET']);
        if (strlen($config->appSecret) < 32) {
            throw new GrowthAuditConfigException('GROWTH_AUDIT_APP_SECRET must be at least 32 characters');
        }

        $config->allowedOrigin = rtrim(trim((string) $values['GROWTH_AUDIT_ALLOWED_ORIGIN']), '/');
        self::validateOrigin($config->allowedOrigin);

        $config->storageDir = trim((string) $values['GROWTH_AUDIT_STORAGE_DIR']);
        self::prepareStorageDirectory($config->storageDir);

        $config->rateLimitMax = self::integerValue($values, 'GROWTH_AUDIT_RATE_LIMIT_MAX', 5, 1, 100);
        $config->rateLimitWindowSeconds = self::integerValue($values, 'GROWTH_AUDIT_RATE_LIMIT_WINDOW_SECONDS', 900, 60, 86400);
        $config->tokenMinAgeSeconds = self::integerValue($values, 'GROWTH_AUDIT_TOKEN_MIN_AGE_SECONDS', 2, 0, 60);
        $config->tokenTtlSeconds = self::integerValue($values, 'GROWTH_AUDIT_TOKEN_TTL_SECONDS', 7200, 60, 86400);
        $config->requestTimeoutSeconds = self::integerValue($values, 'GHL_REQUEST_TIMEOUT_SECONDS', 15, 3, 60);

        $config->ghlToken = trim((string) $values['GHL_PRIVATE_INTEGRATION_TOKEN']);
        $config->ghlLocationId = self::identifier($values['GHL_LOCATION_ID'], 'GHL_LOCATION_ID');
        $config->ghlPipelineId = self::identifier($values['GHL_PIPELINE_ID'], 'GHL_PIPELINE_ID');
        $config->ghlPipelineStageId = self::identifier($values['GHL_PIPELINE_STAGE_ID'], 'GHL_PIPELINE_STAGE_ID');
        $config->tradeFieldKey = self::fieldKey($values['GHL_CF_TRADE_KEY'], 'GHL_CF_TRADE_KEY');
        $config->teamSizeFieldKey = self::fieldKey($values['GHL_CF_TEAM_SIZE_KEY'], 'GHL_CF_TEAM_SIZE_KEY');
        $config->bestContactTimeFieldKey = self::fieldKey($values['GHL_CF_BEST_CONTACT_TIME_KEY'], 'GHL_CF_BEST_CONTACT_TIME_KEY');
        $config->concernFieldKey = self::fieldKey($values['GHL_CF_CONCERN_KEY'], 'GHL_CF_CONCERN_KEY');
        $config->consentFieldKey = self::fieldKey($values['GHL_CF_CONSENT_KEY'], 'GHL_CF_CONSENT_KEY');

        return $config;
    }

    public static function fromEnvironment(): self
    {
        $names = [
            'GROWTH_AUDIT_APP_SECRET',
            'GROWTH_AUDIT_ALLOWED_ORIGIN',
            'GROWTH_AUDIT_STORAGE_DIR',
            'GROWTH_AUDIT_RATE_LIMIT_MAX',
            'GROWTH_AUDIT_RATE_LIMIT_WINDOW_SECONDS',
            'GROWTH_AUDIT_TOKEN_MIN_AGE_SECONDS',
            'GROWTH_AUDIT_TOKEN_TTL_SECONDS',
            'GHL_REQUEST_TIMEOUT_SECONDS',
            'GHL_PRIVATE_INTEGRATION_TOKEN',
            'GHL_LOCATION_ID',
            'GHL_PIPELINE_ID',
            'GHL_PIPELINE_STAGE_ID',
            'GHL_CF_TRADE_KEY',
            'GHL_CF_TEAM_SIZE_KEY',
            'GHL_CF_BEST_CONTACT_TIME_KEY',
            'GHL_CF_CONCERN_KEY',
            'GHL_CF_CONSENT_KEY',
        ];
        $values = [];
        foreach ($names as $name) {
            $value = getenv($name);
            if ($value !== false) {
                $values[$name] = $value;
            }
        }
        return self::fromArray($values);
    }

    private static function validateOrigin(string $origin): void
    {
        $parts = parse_url($origin);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            throw new GrowthAuditConfigException('GROWTH_AUDIT_ALLOWED_ORIGIN must be an absolute origin');
        }
        $scheme = strtolower((string) $parts['scheme']);
        $host = strtolower((string) $parts['host']);
        $local = in_array($host, ['localhost', '127.0.0.1', '::1'], true);
        if ($scheme !== 'https' && !($local && $scheme === 'http')) {
            throw new GrowthAuditConfigException('GROWTH_AUDIT_ALLOWED_ORIGIN must use HTTPS');
        }
        if (isset($parts['path']) && $parts['path'] !== '') {
            throw new GrowthAuditConfigException('GROWTH_AUDIT_ALLOWED_ORIGIN must not contain a path');
        }
    }

    private static function prepareStorageDirectory(string $directory): void
    {
        if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new GrowthAuditConfigException('GROWTH_AUDIT_STORAGE_DIR is not writable');
        }
        if (!is_writable($directory)) {
            throw new GrowthAuditConfigException('GROWTH_AUDIT_STORAGE_DIR is not writable');
        }

        $publicRoot = realpath(dirname(__DIR__, 2));
        $storageRoot = realpath($directory);
        if ($publicRoot !== false && $storageRoot !== false) {
            $public = rtrim(str_replace('\\', '/', strtolower($publicRoot)), '/') . '/';
            $storage = rtrim(str_replace('\\', '/', strtolower($storageRoot)), '/') . '/';
            if (strpos($storage, $public) === 0) {
                throw new GrowthAuditConfigException('GROWTH_AUDIT_STORAGE_DIR must be outside the public web root');
            }
        }
    }

    /** @param array<string,mixed> $values */
    private static function integerValue(array $values, string $name, int $default, int $min, int $max): int
    {
        $raw = isset($values[$name]) && trim((string) $values[$name]) !== '' ? (string) $values[$name] : (string) $default;
        if (!preg_match('/^\d+$/', $raw)) {
            throw new GrowthAuditConfigException($name . ' must be an integer');
        }
        $value = (int) $raw;
        if ($value < $min || $value > $max) {
            throw new GrowthAuditConfigException($name . ' is outside the allowed range');
        }
        return $value;
    }

    private static function identifier(mixed $value, string $name): string
    {
        $identifier = trim((string) $value);
        if (!preg_match('/^[A-Za-z0-9_-]{4,128}$/', $identifier)) {
            throw new GrowthAuditConfigException($name . ' has an invalid format');
        }
        return $identifier;
    }

    private static function fieldKey(mixed $value, string $name): string
    {
        $key = trim((string) $value);
        if (!preg_match('/^[A-Za-z0-9_.-]{2,160}$/', $key)) {
            throw new GrowthAuditConfigException($name . ' has an invalid format');
        }
        return $key;
    }
}

final class GrowthAuditValidator
{
    /** @param array<string,mixed> $payload @return array<string,mixed> */
    public function validate(array $payload): array
    {
        $errors = [];
        $data = [];

        $data['full_name'] = $this->requiredText($payload, 'full_name', 120, $errors);
        $data['business_name'] = $this->requiredText($payload, 'business_name', 160, $errors);
        $data['email'] = $this->email($payload, $errors);
        $data['phone'] = $this->phone($payload, $errors);
        $data['website'] = $this->website($payload, $errors);
        $data['business_town'] = $this->requiredText($payload, 'business_town', 120, $errors);
        $data['trade'] = $this->enum($payload, 'trade', ['Plumbing', 'Electrical', 'Other'], $errors);
        $data['team_size'] = $this->enum($payload, 'team_size', ['1', '2–5', '6–10', '11+'], $errors);
        $data['best_time'] = $this->requiredText($payload, 'best_time', 120, $errors);
        $data['concern'] = $this->requiredText($payload, 'concern', 2000, $errors, true);

        $consent = $payload['consent'] ?? null;
        $data['consent'] = $consent === true || in_array(strtolower(trim((string) $consent)), ['1', 'true', 'yes', 'on'], true);
        if (!$data['consent']) {
            $errors['consent'] = 'Explicit consent is required.';
        }

        foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term'] as $field) {
            $data[$field] = $this->optionalText($payload, $field, 200, $errors);
        }
        foreach (['landing_page', 'first_touch_url'] as $field) {
            $data[$field] = $this->optionalUrl($payload, $field, $errors);
        }

        if ($errors !== []) {
            throw new GrowthAuditValidationException($errors);
        }

        return $data;
    }

    /** @param array<string,mixed> $payload @param array<string,string> $errors */
    private function requiredText(array $payload, string $field, int $max, array &$errors, bool $multiline = false): string
    {
        $value = $this->cleanText($payload[$field] ?? null, $multiline);
        if ($value === '') {
            $errors[$field] = 'This field is required.';
        } elseif ($this->length($value) > $max) {
            $errors[$field] = 'This field is too long.';
        }
        return $value;
    }

    /** @param array<string,mixed> $payload @param array<string,string> $errors */
    private function optionalText(array $payload, string $field, int $max, array &$errors): string
    {
        $value = $this->cleanText($payload[$field] ?? null, false);
        if ($this->length($value) > $max) {
            $errors[$field] = 'This field is too long.';
        }
        return $value;
    }

    private function cleanText(mixed $value, bool $multiline): string
    {
        if (!is_string($value) && !is_numeric($value)) {
            return '';
        }
        $text = trim((string) $value);
        if ($text === '' || preg_match('//u', $text) !== 1) {
            return '';
        }
        $text = strip_tags($text);
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text) ?? '';
        if ($multiline) {
            $text = preg_replace('/[ \t]+/u', ' ', $text) ?? '';
            $text = preg_replace('/\n{3,}/u', "\n\n", $text) ?? '';
        } else {
            $text = preg_replace('/\s+/u', ' ', $text) ?? '';
        }
        return trim($text);
    }

    private function length(string $value): int
    {
        if ($value === '') {
            return 0;
        }
        $count = preg_match_all('/./us', $value, $matches);
        return $count === false ? PHP_INT_MAX : $count;
    }

    /** @param array<string,mixed> $payload @param array<string,string> $errors */
    private function email(array $payload, array &$errors): string
    {
        $email = strtolower($this->cleanText($payload['email'] ?? null, false));
        if ($email === '' || strlen($email) > 254 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'Enter a valid email address.';
        }
        return $email;
    }

    /** @param array<string,mixed> $payload @param array<string,string> $errors */
    private function phone(array $payload, array &$errors): string
    {
        $raw = $this->cleanText($payload['phone'] ?? null, false);
        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        if (strlen($digits) === 10) {
            return '+1' . $digits;
        }
        if (strlen($digits) === 11 && str_starts_with($digits, '1')) {
            return '+' . $digits;
        }
        if (str_starts_with($raw, '+') && strlen($digits) >= 8 && strlen($digits) <= 15) {
            return '+' . $digits;
        }
        $errors['phone'] = 'Enter a valid phone number.';
        return $raw;
    }

    /** @param array<string,mixed> $payload @param array<string,string> $errors */
    private function website(array $payload, array &$errors): string
    {
        $url = $this->cleanText($payload['website'] ?? null, false);
        if ($url === '') {
            return '';
        }
        if (strlen($url) > 2048 || filter_var($url, FILTER_VALIDATE_URL) === false) {
            $errors['website'] = 'Enter a complete http or https website URL.';
            return $url;
        }
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        if (!in_array($scheme, ['http', 'https'], true) || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            $errors['website'] = 'Enter a complete http or https website URL.';
        }
        return $url;
    }

    /** @param array<string,mixed> $payload @param array<string,string> $errors */
    private function optionalUrl(array $payload, string $field, array &$errors): string
    {
        $url = $this->cleanText($payload[$field] ?? null, false);
        if ($url === '') {
            return '';
        }
        if (strlen($url) > 2048 || filter_var($url, FILTER_VALIDATE_URL) === false) {
            $errors[$field] = 'Invalid attribution URL.';
        }
        return $url;
    }

    /** @param array<string,mixed> $payload @param string[] $allowed @param array<string,string> $errors */
    private function enum(array $payload, string $field, array $allowed, array &$errors): string
    {
        $value = $this->cleanText($payload[$field] ?? null, false);
        if (!in_array($value, $allowed, true)) {
            $errors[$field] = 'Choose a valid option.';
        }
        return $value;
    }
}

interface GrowthAuditHttpTransport
{
    /** @param array<string,string> $query @param array<string,mixed>|null $body @return array<string,mixed> */
    public function request(string $method, string $path, array $query = [], ?array $body = null): array;
}

final class GrowthAuditCurlTransport implements GrowthAuditHttpTransport
{
    private string $token;
    private int $timeout;

    public function __construct(string $token, int $timeout)
    {
        $this->token = $token;
        $this->timeout = $timeout;
    }

    public function request(string $method, string $path, array $query = [], ?array $body = null): array
    {
        if (!function_exists('curl_init')) {
            throw new GrowthAuditConfigException('The PHP cURL extension is required');
        }
        $url = 'https://services.leadconnectorhq.com' . $path;
        if ($query !== []) {
            $url .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }

        $handle = curl_init($url);
        if ($handle === false) {
            throw new GrowthAuditUpstreamException('Could not initialize the upstream request');
        }

        $headers = [
            'Accept: application/json',
            'Authorization: Bearer ' . $this->token,
            'Version: v3',
            'User-Agent: TopShelfGrowthAudit/1.0',
        ];
        $options = [
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => min(5, $this->timeout),
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ];
        if ($body !== null) {
            $encoded = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            $headers[] = 'Content-Type: application/json';
            $options[CURLOPT_POSTFIELDS] = $encoded;
        }
        $options[CURLOPT_HTTPHEADER] = $headers;
        curl_setopt_array($handle, $options);

        $raw = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $curlError = curl_error($handle);
        curl_close($handle);

        if ($raw === false) {
            throw new GrowthAuditUpstreamException('HighLevel request transport failure: ' . $curlError);
        }
        if (strlen((string) $raw) > 1048576) {
            throw new GrowthAuditUpstreamException('HighLevel response exceeded the size limit');
        }

        try {
            $decoded = trim((string) $raw) === '' ? [] : json_decode((string) $raw, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new GrowthAuditUpstreamException('HighLevel returned an invalid JSON response', 0, $exception);
        }
        if (!is_array($decoded)) {
            throw new GrowthAuditUpstreamException('HighLevel returned an unexpected response');
        }
        if ($status < 200 || $status >= 300) {
            throw new GrowthAuditUpstreamException('HighLevel request failed with HTTP ' . $status . ' at ' . $path);
        }
        return $decoded;
    }
}

interface GrowthAuditHighLevelClient
{
    /** @param array<string,mixed> $contact */
    public function upsertContact(array $contact): string;

    /** @return array<string,mixed>|null */
    public function findOpportunity(string $contactId): ?array;

    public function createOpportunity(string $contactId, string $name): string;

    public function addSubmittedTag(string $contactId): void;
}

final class GrowthAuditHighLevelApiClient implements GrowthAuditHighLevelClient
{
    private GrowthAuditConfig $config;
    private GrowthAuditHttpTransport $transport;

    public function __construct(GrowthAuditConfig $config, GrowthAuditHttpTransport $transport)
    {
        $this->config = $config;
        $this->transport = $transport;
    }

    public function upsertContact(array $contact): string
    {
        $response = $this->transport->request('POST', '/contacts/upsert', [], $contact);
        $id = $response['contact']['id'] ?? $response['id'] ?? null;
        if (!is_string($id) || $id === '') {
            throw new GrowthAuditUpstreamException('HighLevel contact upsert did not return a contact ID');
        }
        return $id;
    }

    public function findOpportunity(string $contactId): ?array
    {
        $response = $this->transport->request('GET', '/opportunities/search', [
            'locationId' => $this->config->ghlLocationId,
            'pipelineId' => $this->config->ghlPipelineId,
            'contactId' => $contactId,
            'status' => 'all',
            'limit' => '100',
        ]);

        $opportunities = null;
        if (isset($response['opportunities']) && is_array($response['opportunities'])) {
            $opportunities = $response['opportunities'];
        } elseif (isset($response['data']['opportunities']) && is_array($response['data']['opportunities'])) {
            $opportunities = $response['data']['opportunities'];
        } elseif (isset($response['data']) && is_array($response['data']) && array_is_list($response['data'])) {
            $opportunities = $response['data'];
        }
        if ($opportunities === null) {
            throw new GrowthAuditUpstreamException('HighLevel opportunity search returned an unexpected response');
        }

        foreach ($opportunities as $opportunity) {
            if (!is_array($opportunity)) {
                continue;
            }
            if (($opportunity['contactId'] ?? null) === $contactId &&
                ($opportunity['pipelineId'] ?? null) === $this->config->ghlPipelineId) {
                return $opportunity;
            }
        }
        return null;
    }

    public function createOpportunity(string $contactId, string $name): string
    {
        $response = $this->transport->request('POST', '/opportunities/', [], [
            'pipelineId' => $this->config->ghlPipelineId,
            'locationId' => $this->config->ghlLocationId,
            'pipelineStageId' => $this->config->ghlPipelineStageId,
            'contactId' => $contactId,
            'name' => $name,
            'status' => 'open',
        ]);
        $id = $response['opportunity']['id'] ?? $response['id'] ?? null;
        if (!is_string($id) || $id === '') {
            throw new GrowthAuditUpstreamException('HighLevel opportunity creation did not return an opportunity ID');
        }
        return $id;
    }

    public function addSubmittedTag(string $contactId): void
    {
        $this->transport->request(
            'POST',
            '/contacts/' . rawurlencode($contactId) . '/tags',
            [],
            ['tags' => ['growth-audit-submitted']]
        );
    }
}

final class GrowthAuditFileLock
{
    private GrowthAuditConfig $config;

    public function __construct(GrowthAuditConfig $config)
    {
        $this->config = $config;
    }

    /** @template T @param callable():T $callback @return T */
    public function synchronized(string $key, callable $callback): mixed
    {
        $hash = hash_hmac('sha256', $key, $this->config->appSecret);
        $path = rtrim($this->config->storageDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'lock-' . $hash;
        $handle = @fopen($path, 'c+');
        if ($handle === false) {
            throw new GrowthAuditConfigException('Could not open the Growth Audit lock file');
        }
        try {
            if (!flock($handle, LOCK_EX)) {
                throw new GrowthAuditConfigException('Could not acquire the Growth Audit lock');
            }
            return $callback();
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}

final class GrowthAuditFileRateLimiter
{
    private GrowthAuditConfig $config;

    public function __construct(GrowthAuditConfig $config)
    {
        $this->config = $config;
    }

    public function consume(string $clientIp, ?int $now = null): void
    {
        $now = $now ?? time();
        $key = hash_hmac('sha256', $clientIp, $this->config->appSecret);
        $path = rtrim($this->config->storageDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'rate-' . $key . '.json';
        $handle = @fopen($path, 'c+');
        if ($handle === false) {
            throw new GrowthAuditConfigException('Could not open the rate-limit store');
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new GrowthAuditConfigException('Could not lock the rate-limit store');
            }
            rewind($handle);
            $raw = stream_get_contents($handle);
            $timestamps = [];
            if (is_string($raw) && trim($raw) !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    foreach ($decoded as $timestamp) {
                        if (is_int($timestamp) && $timestamp > $now - $this->config->rateLimitWindowSeconds) {
                            $timestamps[] = $timestamp;
                        }
                    }
                }
            }
            if (count($timestamps) >= $this->config->rateLimitMax) {
                $oldest = min($timestamps);
                throw new GrowthAuditRateLimitException(
                    $this->config->rateLimitWindowSeconds - ($now - $oldest)
                );
            }
            $timestamps[] = $now;
            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, json_encode($timestamps, JSON_THROW_ON_ERROR));
            fflush($handle);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}

final class GrowthAuditBotToken
{
    private GrowthAuditConfig $config;

    public function __construct(GrowthAuditConfig $config)
    {
        $this->config = $config;
    }

    public function issue(?int $now = null): string
    {
        $timestamp = $now ?? time();
        $payload = $timestamp . '.' . self::base64UrlEncode(random_bytes(16));
        $signature = hash_hmac('sha256', $payload, $this->config->appSecret, true);
        return self::base64UrlEncode($payload) . '.' . self::base64UrlEncode($signature);
    }

    public function verify(string $token, ?int $now = null): void
    {
        $now = $now ?? time();
        $parts = explode('.', $token);
        if (count($parts) !== 2) {
            throw new GrowthAuditTokenException('Invalid submission token');
        }
        $payload = self::base64UrlDecode($parts[0]);
        $signature = self::base64UrlDecode($parts[1]);
        if ($payload === null || $signature === null) {
            throw new GrowthAuditTokenException('Invalid submission token');
        }
        $expected = hash_hmac('sha256', $payload, $this->config->appSecret, true);
        if (!hash_equals($expected, $signature)) {
            throw new GrowthAuditTokenException('Invalid submission token');
        }
        $payloadParts = explode('.', $payload, 2);
        if (count($payloadParts) !== 2 || !ctype_digit($payloadParts[0])) {
            throw new GrowthAuditTokenException('Invalid submission token');
        }
        $issued = (int) $payloadParts[0];
        $age = $now - $issued;
        if ($age < $this->config->tokenMinAgeSeconds || $age > $this->config->tokenTtlSeconds) {
            throw new GrowthAuditTokenException('Expired or premature submission token');
        }
    }

    private static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $value): ?string
    {
        $padding = strlen($value) % 4;
        if ($padding !== 0) {
            $value .= str_repeat('=', 4 - $padding);
        }
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);
        return $decoded === false ? null : $decoded;
    }
}

final class GrowthAuditService
{
    private GrowthAuditConfig $config;
    private GrowthAuditValidator $validator;
    private GrowthAuditHighLevelClient $client;
    private GrowthAuditFileLock $lock;

    public function __construct(
        GrowthAuditConfig $config,
        GrowthAuditValidator $validator,
        GrowthAuditHighLevelClient $client,
        GrowthAuditFileLock $lock
    ) {
        $this->config = $config;
        $this->validator = $validator;
        $this->client = $client;
        $this->lock = $lock;
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    public function submit(array $payload, ?DateTimeImmutable $submittedAt = null): array
    {
        $data = $this->validator->validate($payload);
        $submittedAt = $submittedAt ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $timestamp = $submittedAt->setTimezone(new DateTimeZone('UTC'))->format(DateTimeInterface::ATOM);
        [$firstName, $lastName] = $this->splitName((string) $data['full_name']);

        $contact = [
            'locationId' => $this->config->ghlLocationId,
            'name' => $data['full_name'],
            'firstName' => $firstName,
            'lastName' => $lastName,
            'companyName' => $data['business_name'],
            'phone' => $data['phone'],
            'email' => $data['email'],
            'city' => $data['business_town'],
            'source' => 'Website Growth Audit',
            'createNewIfDuplicateAllowed' => false,
            'customFields' => [
                ['key' => $this->config->tradeFieldKey, 'fieldValue' => $data['trade']],
                ['key' => $this->config->teamSizeFieldKey, 'fieldValue' => $data['team_size']],
                ['key' => $this->config->bestContactTimeFieldKey, 'fieldValue' => $data['best_time']],
                ['key' => $this->config->concernFieldKey, 'fieldValue' => $data['concern']],
                ['key' => $this->config->consentFieldKey, 'fieldValue' => 'Granted at ' . $timestamp . ' | form growth-audit-2026-07-17'],
            ],
        ];
        if ($data['website'] !== '') {
            $contact['website'] = $data['website'];
        }
        if ($data['landing_page'] !== '') {
            $attribution = ['url' => $data['landing_page']];
            $map = [
                'utm_campaign' => 'campaign',
                'utm_source' => 'utmSource',
                'utm_medium' => 'utmMedium',
                'utm_content' => 'utmContent',
            ];
            foreach ($map as $source => $target) {
                if ($data[$source] !== '') {
                    $attribution[$target] = $data[$source];
                }
            }
            $contact['attributionSource'] = $attribution;
        }

        $contactId = $this->client->upsertContact($contact);
        $opportunityResult = $this->lock->synchronized(
            $contactId . '|' . $this->config->ghlPipelineId,
            function () use ($contactId, $data): array {
                $existing = $this->client->findOpportunity($contactId);
                if ($existing !== null) {
                    $id = $existing['id'] ?? null;
                    if (!is_string($id) || $id === '') {
                        throw new GrowthAuditUpstreamException('Existing opportunity did not include an ID');
                    }
                    return ['id' => $id, 'duplicate' => true];
                }
                $name = $data['business_name'] . ' - Growth Audit';
                return ['id' => $this->client->createOpportunity($contactId, $name), 'duplicate' => false];
            }
        );

        $this->client->addSubmittedTag($contactId);

        return [
            'contactId' => $contactId,
            'opportunityId' => $opportunityResult['id'],
            'duplicate' => $opportunityResult['duplicate'],
        ];
    }

    /** @return array{0:string,1:string} */
    private function splitName(string $name): array
    {
        $parts = preg_split('/\s+/u', trim($name)) ?: [];
        $first = array_shift($parts) ?? '';
        return [$first, implode(' ', $parts)];
    }
}

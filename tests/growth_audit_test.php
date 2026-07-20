<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/api/growth-audit/lib.php';

final class FakeGrowthAuditHighLevelClient implements GrowthAuditHighLevelClient
{
    /** @var string[] */
    public array $calls = [];
    /** @var array<string,mixed>|null */
    public ?array $existingOpportunity = null;
    public ?string $failOn = null;
    /** @var array<string,mixed> */
    public array $lastContact = [];

    public function upsertContact(array $contact): string
    {
        $this->calls[] = 'upsert-contact';
        $this->lastContact = $contact;
        $this->maybeFail('upsert-contact');
        return 'contact_12345';
    }

    public function findOpportunity(string $contactId): ?array
    {
        $this->calls[] = 'find-opportunity';
        $this->maybeFail('find-opportunity');
        return $this->existingOpportunity;
    }

    public function createOpportunity(string $contactId, string $name): string
    {
        $this->calls[] = 'create-opportunity';
        $this->maybeFail('create-opportunity');
        return 'opportunity_12345';
    }

    public function addSubmittedTag(string $contactId): void
    {
        $this->calls[] = 'add-tag';
        $this->maybeFail('add-tag');
    }

    private function maybeFail(string $operation): void
    {
        if ($this->failOn === $operation) {
            throw new GrowthAuditUpstreamException('Simulated HighLevel API failure');
        }
    }
}

/** @return array<string,mixed> */
function test_config_values(string $storageDir): array
{
    return [
        'GROWTH_AUDIT_APP_SECRET' => str_repeat('s', 48),
        'GROWTH_AUDIT_ALLOWED_ORIGIN' => 'http://127.0.0.1:8080',
        'GROWTH_AUDIT_STORAGE_DIR' => $storageDir,
        'GROWTH_AUDIT_RATE_LIMIT_MAX' => '2',
        'GROWTH_AUDIT_RATE_LIMIT_WINDOW_SECONDS' => '60',
        'GROWTH_AUDIT_TOKEN_MIN_AGE_SECONDS' => '0',
        'GROWTH_AUDIT_TOKEN_TTL_SECONDS' => '3600',
        'GHL_REQUEST_TIMEOUT_SECONDS' => '5',
        'GHL_PRIVATE_INTEGRATION_TOKEN' => 'test-token-not-real',
        'GHL_LOCATION_ID' => 'location_12345',
        'GHL_PIPELINE_ID' => 'pipeline_12345',
        'GHL_PIPELINE_STAGE_ID' => 'stage_12345',
        'GHL_CF_TRADE_KEY' => 'contact.growth_audit_trade',
        'GHL_CF_TEAM_SIZE_KEY' => 'contact.growth_audit_team_size',
        'GHL_CF_BEST_CONTACT_TIME_KEY' => 'contact.growth_audit_best_contact_time',
        'GHL_CF_CONCERN_KEY' => 'contact.growth_audit_concern',
        'GHL_CF_CONSENT_KEY' => 'contact.growth_audit_consent',
    ];
}

/** @return array<string,mixed> */
function valid_payload(): array
{
    return [
        'full_name' => '  Jane   Contractor  ',
        'business_name' => 'North Jersey Electric',
        'phone' => '(201) 555-0184',
        'email' => 'JANE@example.com',
        'website' => 'https://example.com',
        'business_town' => 'Fort Lee',
        'trade' => 'Electrical',
        'team_size' => '2–5',
        'best_time' => 'Weekdays after 3 PM',
        'concern' => '<b>Missed calls</b> and slow estimate follow-up',
        'consent' => 'on',
        'utm_source' => 'google',
        'utm_medium' => 'cpc',
        'utm_campaign' => 'contractor audit',
        'utm_content' => 'bronze button',
        'utm_term' => 'electrician marketing',
        'landing_page' => 'https://topshelfagency.biz/growth-audit/',
        'first_touch_url' => 'https://topshelfagency.biz/',
    ];
}

function assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function assert_same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message . '\nExpected: ' . var_export($expected, true) . '\nActual: ' . var_export($actual, true)
        );
    }
}

function reflect_private_static(string $class, string $method): ReflectionMethod
{
    $reflection = new ReflectionMethod($class, $method);
    $reflection->setAccessible(true);
    return $reflection;
}

function remove_test_directory(string $directory): void
{
    if (!is_dir($directory)) {
        return;
    }
    foreach (scandir($directory) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $path = $directory . DIRECTORY_SEPARATOR . $entry;
        is_dir($path) ? remove_test_directory($path) : @unlink($path);
    }
    @rmdir($directory);
}

$storageDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'topshelf-growth-audit-tests-' . bin2hex(random_bytes(5));
$tests = [];

$tests['success'] = function () use ($storageDir): void {
    $config = GrowthAuditConfig::fromArray(test_config_values($storageDir));
    $client = new FakeGrowthAuditHighLevelClient();
    $service = new GrowthAuditService($config, new GrowthAuditValidator(), $client, new GrowthAuditFileLock($config));
    $result = $service->submit(valid_payload(), new DateTimeImmutable('2026-07-17T12:00:00+00:00'));

    assert_same(false, $result['duplicate'], 'A first submission should create an opportunity.');
    assert_same(
        ['upsert-contact', 'find-opportunity', 'create-opportunity', 'add-tag'],
        $client->calls,
        'The tag must only be added after contact and opportunity operations succeed.'
    );
    assert_same('+12015550184', $client->lastContact['phone'], 'Phone should be normalized.');
    assert_same('jane@example.com', $client->lastContact['email'], 'Email should be normalized.');
    assert_same('Jane Contractor', $client->lastContact['name'], 'Name whitespace should be normalized.');
    assert_same('Missed calls and slow estimate follow-up', $client->lastContact['customFields'][3]['fieldValue'], 'Concern HTML should be removed.');
    assert_true(str_contains($client->lastContact['customFields'][4]['fieldValue'], 'Granted at 2026-07-17T12:00:00+00:00'), 'Consent timestamp should be mapped.');
};

$tests['validation failure'] = function () use ($storageDir): void {
    $config = GrowthAuditConfig::fromArray(test_config_values($storageDir));
    $client = new FakeGrowthAuditHighLevelClient();
    $service = new GrowthAuditService($config, new GrowthAuditValidator(), $client, new GrowthAuditFileLock($config));
    $payload = valid_payload();
    $payload['email'] = 'not-an-email';
    $payload['consent'] = false;

    try {
        $service->submit($payload);
        throw new RuntimeException('Expected validation to fail.');
    } catch (GrowthAuditValidationException $error) {
        assert_true(isset($error->errors['email']), 'Email validation error should be returned.');
        assert_true(isset($error->errors['consent']), 'Consent validation error should be returned.');
        assert_same([], $client->calls, 'Validation must happen before any HighLevel API call.');
    }
};

$tests['duplicate submission'] = function () use ($storageDir): void {
    $config = GrowthAuditConfig::fromArray(test_config_values($storageDir));
    $client = new FakeGrowthAuditHighLevelClient();
    $client->existingOpportunity = [
        'id' => 'existing_opportunity',
        'contactId' => 'contact_12345',
        'pipelineId' => 'pipeline_12345',
    ];
    $service = new GrowthAuditService($config, new GrowthAuditValidator(), $client, new GrowthAuditFileLock($config));
    $result = $service->submit(valid_payload());

    assert_same(true, $result['duplicate'], 'An existing contact/pipeline opportunity should be reused.');
    assert_same('existing_opportunity', $result['opportunityId'], 'Existing opportunity ID should be retained.');
    assert_same(
        ['upsert-contact', 'find-opportunity', 'add-tag'],
        $client->calls,
        'A repeated submission must not call create opportunity.'
    );
};

$tests['missing configuration'] = function () use ($storageDir): void {
    $values = test_config_values($storageDir);
    unset($values['GHL_PIPELINE_ID']);
    try {
        GrowthAuditConfig::fromArray($values);
        throw new RuntimeException('Expected missing configuration to fail.');
    } catch (GrowthAuditConfigException $error) {
        assert_true(str_contains($error->getMessage(), 'GHL_PIPELINE_ID'), 'Missing variable name should be reported to server logs.');
    }
};

$tests['HighLevel API failure'] = function () use ($storageDir): void {
    $config = GrowthAuditConfig::fromArray(test_config_values($storageDir));
    $client = new FakeGrowthAuditHighLevelClient();
    $client->failOn = 'find-opportunity';
    $service = new GrowthAuditService($config, new GrowthAuditValidator(), $client, new GrowthAuditFileLock($config));
    try {
        $service->submit(valid_payload());
        throw new RuntimeException('Expected the simulated HighLevel failure to propagate.');
    } catch (GrowthAuditUpstreamException $error) {
        assert_same(
            ['upsert-contact', 'find-opportunity'],
            $client->calls,
            'No opportunity should be created and no tag should be applied after an API failure.'
        );
    }
};

$tests['rate limiting'] = function () use ($storageDir): void {
    $config = GrowthAuditConfig::fromArray(test_config_values($storageDir));
    $limiter = new GrowthAuditFileRateLimiter($config);
    $limiter->consume('203.0.113.7', 1000);
    $limiter->consume('203.0.113.7', 1001);
    try {
        $limiter->consume('203.0.113.7', 1002);
        throw new RuntimeException('Expected the third request to be rate limited.');
    } catch (GrowthAuditRateLimitException $error) {
        assert_true($error->retryAfter > 0, 'Rate limit should include a retry interval.');
    }
};

$tests['signed bot token'] = function () use ($storageDir): void {
    $config = GrowthAuditConfig::fromArray(test_config_values($storageDir));
    $tokens = new GrowthAuditBotToken($config);
    $token = $tokens->issue(1000);
    $tokens->verify($token, 1001);
    try {
        $tokens->verify($token . 'tampered', 1001);
        throw new RuntimeException('Expected a tampered token to fail.');
    } catch (GrowthAuditTokenException $error) {
        assert_true(true, 'Tampered token rejected.');
    }
};

$tests['environment fallback: env vars take full precedence'] = function () use ($storageDir): void {
    $values = test_config_values($storageDir);
    foreach ($values as $name => $value) {
        putenv($name . '=' . $value);
    }
    try {
        $config = GrowthAuditConfig::fromEnvironment();
        assert_same($values['GHL_LOCATION_ID'], $config->ghlLocationId, 'fromEnvironment should read GHL_LOCATION_ID from getenv().');
        assert_same($values['GROWTH_AUDIT_ALLOWED_ORIGIN'], $config->allowedOrigin, 'fromEnvironment should read the allowed origin from getenv().');
    } finally {
        foreach (array_keys($values) as $name) {
            putenv($name);
        }
    }
};

$tests['environment fallback: missing vars fail closed when no private file exists'] = function () use ($storageDir): void {
    $values = test_config_values($storageDir);
    unset($values['GHL_PIPELINE_ID']);
    foreach ($values as $name => $value) {
        putenv($name . '=' . $value);
    }
    try {
        GrowthAuditConfig::fromEnvironment();
        throw new RuntimeException('Expected missing configuration to fail when no private config file exists.');
    } catch (GrowthAuditConfigException $error) {
        assert_true(str_contains($error->getMessage(), 'GHL_PIPELINE_ID'), 'Missing variable name should be reported.');
    } finally {
        foreach (array_keys($values) as $name) {
            putenv($name);
        }
        putenv('GHL_PIPELINE_ID');
    }
};

$tests['private config file: merges only missing names, ignores present ones'] = function (): void {
    $fixtureDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'growth-audit-private-fixture-' . bin2hex(random_bytes(5));
    mkdir($fixtureDir, 0700, true);
    $fixturePath = $fixtureDir . DIRECTORY_SEPARATOR . 'config.php';
    file_put_contents($fixturePath, "<?php\nreturn [\n" .
        "    'GHL_PIPELINE_ID' => 'from-file-pipeline',\n" .
        "    'GHL_PIPELINE_STAGE_ID' => 'from-file-stage',\n" .
        "    'GHL_LOCATION_ID' => 'should-not-be-used',\n" .
        "];\n");

    try {
        $method = reflect_private_static(GrowthAuditConfig::class, 'loadPrivateConfigFile');
        $result = $method->invoke(null, $fixturePath, ['GHL_PIPELINE_ID', 'GHL_PIPELINE_STAGE_ID']);
        assert_same(
            ['GHL_PIPELINE_ID' => 'from-file-pipeline', 'GHL_PIPELINE_STAGE_ID' => 'from-file-stage'],
            $result,
            'Only the requested missing names should be returned.'
        );
        assert_true(!isset($result['GHL_LOCATION_ID']), 'Names not in the missing list must never be pulled from the private file.');
    } finally {
        remove_test_directory($fixtureDir);
    }
};

$tests['private config file: absent file returns no values'] = function (): void {
    $method = reflect_private_static(GrowthAuditConfig::class, 'loadPrivateConfigFile');
    $missingPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'growth-audit-private-fixture-absent-' . bin2hex(random_bytes(5)) . DIRECTORY_SEPARATOR . 'config.php';
    $result = $method->invoke(null, $missingPath, ['GHL_PIPELINE_ID']);
    assert_same([], $result, 'A missing private config file should yield no values, preserving fail-closed behavior.');
};

$tests['private config file: malformed contents throw'] = function (): void {
    $fixtureDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'growth-audit-private-fixture-' . bin2hex(random_bytes(5));
    mkdir($fixtureDir, 0700, true);
    $fixturePath = $fixtureDir . DIRECTORY_SEPARATOR . 'config.php';
    file_put_contents($fixturePath, "<?php\nreturn 'not-an-array';\n");

    try {
        $method = reflect_private_static(GrowthAuditConfig::class, 'loadPrivateConfigFile');
        try {
            $method->invoke(null, $fixturePath, ['GHL_PIPELINE_ID']);
            throw new RuntimeException('Expected a non-array private config file to be rejected.');
        } catch (GrowthAuditConfigException $error) {
            assert_true(true, 'Malformed private config file correctly rejected.');
        }
    } finally {
        remove_test_directory($fixtureDir);
    }
};

$failures = [];
try {
    foreach ($tests as $name => $test) {
        try {
            $test();
            echo "PASS: {$name}\n";
        } catch (Throwable $error) {
            $failures[] = $name . ': ' . $error->getMessage();
            echo "FAIL: {$name}\n";
        }
    }
} finally {
    remove_test_directory($storageDir);
}

if ($failures !== []) {
    fwrite(STDERR, implode("\n", $failures) . "\n");
    exit(1);
}

echo 'All ' . count($tests) . " Growth Audit tests passed.\n";

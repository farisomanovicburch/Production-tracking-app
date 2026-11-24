<?php
require_once __DIR__ . '/ApiTestCase.php';

// What Swagger UI shows about tokens must match what ProtectedRoutesTest proves the server does: a lock on every
// route that needs a token, a documented 401 for it, and no lock on the three routes anybody may call.
final class SwaggerSecurityTest extends ApiTestCase
{
    // GET / is registered in index.php, not in rest/routes, so it cannot be read out of the routes files.
    private const PUBLIC_OPERATIONS = ['get /', 'post /auth/login', 'post /auth/register'];

    /** "@id:[0-9]+" in a Flight pattern is "{id}" in an OpenAPI path */
    private function operationOf(array $spec, string $method, string $pattern): ?array
    {
        $template = preg_replace('/@(\w+)(:\[[^\]]+\]\+?)?/', '{$1}', $pattern);
        return $spec['paths'][$template][strtolower($method)] ?? null;
    }

    // An operation's own "security" replaces the global one, and "security: []" switches the lock off.
    private function isLocked(array $spec, array $operation): bool
    {
        $requirements = $operation['security'] ?? $spec['security'] ?? [];
        return array_filter($requirements, fn (array $requirement) => array_key_exists('bearerAuth', $requirement)) !== [];
    }

    // A response may be the shared "$ref": "#/components/responses/Unauthorized": follow it to the real answer.
    private function schemaOf(array $spec, array $response): ?string
    {
        if (isset($response['$ref'])) {
            $response = $spec['components']['responses'][basename($response['$ref'])] ?? [];
        }
        return $response['content']['application/json']['schema']['$ref'] ?? null;
    }

    public function testEveryRouteThatNeedsATokenShowsTheLockAndDocuments401(): void
    {
        $spec = $this->openApiDocument();
        $routes = array_merge(self::protectedRoutePatterns(), [['GET', '/auth/me']]);
        $this->assertGreaterThan(60, count($routes), 'The route files were not read: this test would prove nothing.');

        $problems = [];
        foreach ($routes as [$method, $pattern]) {
            $operation = $this->operationOf($spec, $method, $pattern);
            if ($operation === null) {
                $problems[] = "$method $pattern is not documented (or the {parameter} name differs from the route's)";
                continue;
            }
            if (!$this->isLocked($spec, $operation)) {
                $problems[] = "$method $pattern has no bearerAuth security";
            }
            if (!isset($operation['responses']['401'])) {
                $problems[] = "$method $pattern does not document 401";
            } elseif ($this->schemaOf($spec, $operation['responses']['401']) !== '#/components/schemas/Error') {
                $problems[] = "$method $pattern documents 401 without the Error schema";
            }
        }

        $this->assertSame([], $problems);
    }

    // Together with the test above this means the lock cannot be forgotten on a new path and cannot leak onto a public one.
    public function testOnlyTheThreePublicOperationsAreWithoutTheLock(): void
    {
        $spec = $this->openApiDocument();

        $unlocked = [];
        foreach ($spec['paths'] as $path => $operations) {
            foreach ($operations as $method => $operation) {
                if (!$this->isLocked($spec, $operation)) {
                    $unlocked[] = "$method $path";
                }
            }
        }

        $this->assertEqualsCanonicalizing(self::PUBLIC_OPERATIONS, $unlocked);
    }
}

<?php
require_once __DIR__ . '/../../utils/exceptions.php';

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

// The error handler answers with getStatus(), so each class carrying the right code IS the status code the client gets.
final class ExceptionsTest extends TestCase
{
    public static function exceptionsAndTheirStatus(): array
    {
        return [
            'invalid input or broken rule' => [ValidationException::class, 400],
            'not logged in'                => [UnauthorizedException::class, 401],
            'not allowed'                  => [ForbiddenException::class, 403],
            'entity does not exist'        => [NotFoundException::class, 404],
            'external API unavailable'     => [ExternalServiceException::class, 503],
        ];
    }

    #[DataProvider('exceptionsAndTheirStatus')]
    public function testEveryExceptionCarriesItsHttpStatusAndMessage(string $class, int $status): void
    {
        $exception = new $class('Something is wrong.');

        $this->assertInstanceOf(HttpException::class, $exception);
        $this->assertSame($status, $exception->getStatus());
        $this->assertSame('Something is wrong.', $exception->getMessage());
    }
}

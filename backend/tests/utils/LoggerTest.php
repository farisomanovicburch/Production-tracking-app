<?php
require_once __DIR__ . '/../../utils/Logger.php';

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LoggerTest extends TestCase
{
    private string $folder;

    // Every test logs into a folder that does not exist yet, so "the logger creates the folder" is the normal case.
    protected function setUp(): void
    {
        $this->folder = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'logger-test-' . bin2hex(random_bytes(6));
        Logger::setDirectory($this->folder . DIRECTORY_SEPARATOR . 'logs');
    }

    protected function tearDown(): void
    {
        Logger::setDirectory(null);
        $this->removeFolder($this->folder);
    }

    private function removeFolder(string $path): void
    {
        if (is_file($path)) {
            unlink($path);
            return;
        }
        if (!is_dir($path)) {
            return;
        }
        foreach (scandir($path) as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                $this->removeFolder($path . DIRECTORY_SEPARATOR . $entry);
            }
        }
        rmdir($path);
    }

    private function logFile(string $name): string
    {
        return $this->folder . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . $name;
    }

    private function lines(string $name): array
    {
        return file($this->logFile($name), FILE_IGNORE_NEW_LINES);
    }

    public function testInfoWritesOneLineWithTimeAndLevelToInfoLog(): void
    {
        Logger::info('LOGIN user=2 role=operator');

        $this->assertCount(1, $this->lines('info.log'));
        $this->assertMatchesRegularExpression(
            '/^\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\] INFO LOGIN user=2 role=operator$/',
            $this->lines('info.log')[0]
        );
    }

    public function testErrorWritesOneLineWithTimeAndLevelToErrorLog(): void
    {
        Logger::error('LOGIN FAILED email=a@b.ba');

        $this->assertMatchesRegularExpression(
            '/^\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\] ERROR LOGIN FAILED email=a@b\.ba$/',
            $this->lines('error.log')[0]
        );
    }

    public function testInfoNeverWritesToErrorLogAndErrorNeverToInfoLog(): void
    {
        Logger::info('one');
        $this->assertFileDoesNotExist($this->logFile('error.log'));

        Logger::error('two');
        $this->assertCount(1, $this->lines('info.log'));
    }

    public function testLinesAreAppendedInTheOrderTheyWereLogged(): void
    {
        Logger::info('first');
        Logger::info('second');

        $lines = $this->lines('info.log');
        $this->assertCount(2, $lines);
        $this->assertStringEndsWith('INFO first', $lines[0]);
        $this->assertStringEndsWith('INFO second', $lines[1]);
    }

    public function testItCreatesTheLogFolderOnAFreshClone(): void
    {
        $this->assertDirectoryDoesNotExist($this->folder);

        Logger::info('first line ever');

        $this->assertFileExists($this->logFile('info.log'));
    }

    public function testALineBreakInAMessageCannotForgeASecondLogLine(): void
    {
        // The message can contain text a user typed, such as the email of a failed login.
        Logger::error("LOGIN FAILED email=x\n[2026-01-01 00:00:00] INFO LOGIN user=1 role=admin");

        $this->assertCount(1, $this->lines('error.log'));
    }

    public static function secrets(): array
    {
        return [
            'password in key=value form' => ['LOGIN password=hunter2 user=2', 'hunter2'],
            'password in JSON' => ['body {"email":"a@b.ba","password":"hunter2"}', 'hunter2'],
            'a password field with a longer name' => ['new_password=hunter2', 'hunter2'],
            'password hash' => ['password_hash=$2y$10$abcdefghijklmnop', '$2y$10$abcdefghijklmnop'],
            'token in key=value form' => ['token=abc123secret', 'abc123secret'],
            'Authorization header' => ['Authorization: Bearer aaa.bbb.ccc', 'aaa.bbb.ccc'],
            'a bearer token without a header name' => ['sent Bearer aaa.bbb.ccc here', 'aaa.bbb.ccc'],
            'a bare JWT' => ['rejected eyJhbGciOiJIUzI1NiJ9.eyJ1c2VyIjoyfQ.c2lnbmF0dXJl', 'eyJhbGciOiJIUzI1NiJ9.eyJ1c2VyIjoyfQ.c2lnbmF0dXJl'],
        ];
    }

    #[DataProvider('secrets')]
    public function testPasswordsAndTokensNeverReachTheLog(string $message, string $secret): void
    {
        Logger::info($message);
        Logger::error($message);

        foreach (['info.log', 'error.log'] as $file) {
            $content = file_get_contents($this->logFile($file));
            $this->assertStringNotContainsString($secret, $content);
            $this->assertStringContainsString('[hidden]', $content);
        }
    }

    public function testOrdinaryWordsAreNotHidden(): void
    {
        Logger::info('GET /production-runs 200 user=2 role=operator 14ms');

        $this->assertStringEndsWith('INFO GET /production-runs 200 user=2 role=operator 14ms', $this->lines('info.log')[0]);
    }

    public function testLoggingNeverThrowsWhenTheFolderCannotBeCreated(): void
    {
        // A file where the folder should be: mkdir fails, as it would on a read-only deployment.
        file_put_contents($this->folder, 'a file, not a folder');
        mkdir($this->folder . '-other');
        Logger::setDirectory($this->folder . DIRECTORY_SEPARATOR . 'logs');
        $previous = ini_set('error_log', $this->folder . '-other' . DIRECTORY_SEPARATOR . 'php.log');

        try {
            Logger::error('this must not take the request down');
            $this->addToAssertionCount(1);
        } finally {
            ini_set('error_log', (string) $previous);
            $this->removeFolder($this->folder . '-other');
        }
    }

    // How a test hands a log folder to a server running in another process, where setDirectory() cannot reach.
    public function testTheFolderCanBeChosenWithTheLogDirectoryEnvironmentVariable(): void
    {
        Logger::setDirectory(null);
        putenv('LOG_DIRECTORY=' . $this->folder . DIRECTORY_SEPARATOR . 'from-environment');

        try {
            Logger::info('chosen by the environment');
            $this->assertFileExists($this->folder . DIRECTORY_SEPARATOR . 'from-environment' . DIRECTORY_SEPARATOR . 'info.log');
        } finally {
            putenv('LOG_DIRECTORY');
        }
    }

    public function testTheDefaultFolderIsBackendLogs(): void
    {
        Logger::setDirectory(null);

        // backend/logs is what .gitignore ignores; a log written anywhere else would be committed by accident.
        $this->assertSame(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'logs', Logger::directory());
    }
}

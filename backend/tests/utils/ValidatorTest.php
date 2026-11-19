<?php
require_once __DIR__ . '/../../utils/Validator.php';

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ValidatorTest extends TestCase
{
    private function assertRejected(callable $check, string $message): void
    {
        try {
            $check();
            $this->fail("Accepted, expected: $message");
        } catch (ValidationException $exception) {
            $this->assertSame($message, $exception->getMessage());
            $this->assertSame(400, $exception->getStatus());
        }
    }

    /* ---------- required ---------- */

    public function testRequiredAcceptsPresentValuesIncludingZeroAndFalse(): void
    {
        // 0 kg and is_active = false are real answers, not missing ones.
        Validator::required(['name' => 'PE', 'stock_qty' => 0, 'is_active' => false], ['name', 'stock_qty', 'is_active']);
        $this->addToAssertionCount(1);
    }

    public static function missingValues(): array
    {
        return ['absent' => [[]], 'null' => [['name' => null]], 'empty' => [['name' => '']], 'only spaces' => [['name' => '   ']]];
    }

    #[DataProvider('missingValues')]
    public function testRequiredRejectsAMissingValue(array $data): void
    {
        $this->assertRejected(fn () => Validator::required($data, ['name']), "Field 'name' is required.");
    }

    /* ---------- length ---------- */

    public function testLengthCountsCharactersNotBytes(): void
    {
        Validator::length('Čelik', 'name', 1, 5);   // 5 characters, 6 bytes
        $this->addToAssertionCount(1);
    }

    public function testLengthRejectsTooLongText(): void
    {
        $this->assertRejected(
            fn () => Validator::length('ABCDEF', 'code', 1, 5),
            "Field 'code' must be between 1 and 5 characters long."
        );
    }

    /* ---------- integer ---------- */

    public function testIntegerAcceptsAWholeNumberAsTextFromAForm(): void
    {
        Validator::integer('5', 'machine_id', 1);
        $this->addToAssertionCount(1);
    }

    public static function notWholeNumbers(): array
    {
        return ['decimal' => ['4.5'], 'text' => ['five'], 'digits then text' => ['5abc'], 'boolean' => [true]];
    }

    #[DataProvider('notWholeNumbers')]
    public function testIntegerRejectsAnythingThatIsNotAWholeNumber(mixed $value): void
    {
        $this->assertRejected(fn () => Validator::integer($value, 'machine_id'), "Field 'machine_id' must be a whole number.");
    }

    public function testIntegerRejectsAValueBelowTheMinimum(): void
    {
        $this->assertRejected(fn () => Validator::integer(0, 'machine_id', 1), "Field 'machine_id' must be at least 1.");
    }

    public function testIntegerRejectsAValueAboveTheMaximum(): void
    {
        $this->assertRejected(fn () => Validator::integer(17, 'days', 1, 16), "Field 'days' must be at most 16.");
    }

    /* ---------- decimal ---------- */

    public function testDecimalAcceptsNumbersAndNumericText(): void
    {
        Validator::decimal(12.5, 'quantity_kg', 0);
        Validator::decimal('0.001', 'quantity_kg', 0);
        $this->addToAssertionCount(1);
    }

    public static function notDecimals(): array
    {
        return ['text' => ['ten'], 'comma as separator' => ['2,5'], 'exponent' => ['1e3'], 'boolean' => [true]];
    }

    #[DataProvider('notDecimals')]
    public function testDecimalRejectsAnythingThatIsNotAPlainNumber(mixed $value): void
    {
        $this->assertRejected(fn () => Validator::decimal($value, 'quantity_kg'), "Field 'quantity_kg' must be a number.");
    }

    public function testDecimalRejectsAValueBelowTheMinimum(): void
    {
        $this->assertRejected(fn () => Validator::decimal('-0.5', 'stock_qty', 0), "Field 'stock_qty' must be at least 0.");
    }

    public function testDecimalRejectsAValueAboveTheMaximum(): void
    {
        $this->assertRejected(fn () => Validator::decimal('100.01', 'percentage', 0, 100), "Field 'percentage' must be at most 100.");
    }

    /* ---------- oneOf ---------- */

    public function testOneOfAcceptsAnAllowedValue(): void
    {
        Validator::oneOf('extrusion', 'machine_type', ['extrusion', 'conversion']);
        $this->addToAssertionCount(1);
    }

    public function testOneOfRejectsAnyOtherValue(): void
    {
        $this->assertRejected(
            fn () => Validator::oneOf('printing', 'machine_type', ['extrusion', 'conversion']),
            "Field 'machine_type' must be one of: extrusion, conversion."
        );
    }

    /* ---------- email ---------- */

    public function testEmailAcceptsAnEmailAddress(): void
    {
        Validator::email('operator@example.com');
        $this->addToAssertionCount(1);
    }

    public function testEmailRejectsTextThatIsNotAnEmailAddress(): void
    {
        $this->assertRejected(fn () => Validator::email('operator.example.com'), "Field 'email' must be a valid email address.");
    }

    /* ---------- datetime ---------- */

    public function testDatetimeAcceptsTheDatabaseFormat(): void
    {
        Validator::datetime('2026-10-05 06:00:00', 'started_at');
        $this->addToAssertionCount(1);
    }

    public static function notDatetimes(): array
    {
        return [
            'date only'           => ['2026-10-05'],
            'impossible date'     => ['2026-02-30 06:00:00'],
            'impossible hour'     => ['2026-10-05 25:00:00'],
            'not normalised yet'  => ['2026-10-05T06:00'],
        ];
    }

    #[DataProvider('notDatetimes')]
    public function testDatetimeRejectsAnythingElse(string $value): void
    {
        $this->assertRejected(
            fn () => Validator::datetime($value, 'started_at'),
            "Field 'started_at' must be a date and time (YYYY-MM-DD HH:MM:SS)."
        );
    }

    /* ---------- password ---------- */

    public function testPasswordAcceptsEightCharactersWithALetterAndANumber(): void
    {
        Validator::password('admin123');
        $this->addToAssertionCount(1);
    }

    public static function weakPasswords(): array
    {
        return ['too short' => ['abc123'], 'no number' => ['password'], 'no letter' => ['12345678']];
    }

    #[DataProvider('weakPasswords')]
    public function testPasswordRejectsAWeakPassword(string $password): void
    {
        $this->assertRejected(
            fn () => Validator::password($password),
            "Field 'password' must have at least 8 characters, including a letter and a number."
        );
    }
}

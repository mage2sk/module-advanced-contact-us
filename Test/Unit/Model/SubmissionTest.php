<?php
declare(strict_types=1);

namespace Panth\AdvancedContactUs\Test\Unit\Model;

use Panth\AdvancedContactUs\Model\Submission;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SubmissionTest extends TestCase
{
    public static function statusProvider(): array
    {
        return [
            [0, 0], ['0', 0], [1, 1], ['1', 1], ['2', 2], [' 2 ', 2],
            [3, null], ['3', null], ['-1', null], ['1.5', null], ['replied', null], ['', null], [null, null],
            [[2], null], [2.0, null], [true, null],
        ];
    }

    #[DataProvider('statusProvider')]
    public function testNormaliseStatusAcceptsOnlyKnownStatuses($value, ?int $expected): void
    {
        $this->assertSame($expected, Submission::normaliseStatus($value));
    }

    public function testEveryStatusHasALabel(): void
    {
        $this->assertSame(
            [Submission::STATUS_NEW => 'New', Submission::STATUS_READ => 'Read', Submission::STATUS_REPLIED => 'Replied'],
            Submission::STATUS_LABELS
        );
    }
}

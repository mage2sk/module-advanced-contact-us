<?php
declare(strict_types=1);

namespace Panth\AdvancedContactUs\Test\Unit\Ui\Component\Listing;

use Magento\Framework\Api\Filter;
use Magento\Framework\Data\Collection;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Panth\AdvancedContactUs\Ui\Component\Listing\LikeFulltextFilter;
use PHPUnit\Framework\TestCase;

class LikeFulltextFilterTest extends TestCase
{
    private array $where = [];

    private function collection(): AbstractDb
    {
        $this->where = [];

        $connection = $this->createStub(AdapterInterface::class);
        $connection->method('quoteIdentifier')->willReturnCallback(static fn($id) => '`' . $id . '`');
        $connection->method('quoteInto')->willReturnCallback(
            static fn($text, $value) => str_replace('?', "'" . $value . "'", $text)
        );

        $select = $this->createStub(Select::class);
        $select->method('where')->willReturnCallback(function ($condition) use (&$select) {
            $this->where[] = $condition;
            return $select;
        });

        $collection = $this->createStub(AbstractDb::class);
        $collection->method('getConnection')->willReturn($connection);
        $collection->method('getSelect')->willReturn($select);

        return $collection;
    }

    private function filter($value): Filter
    {
        $filter = $this->createStub(Filter::class);
        $filter->method('getValue')->willReturn($value);
        return $filter;
    }

    public function testSearchesEveryConfiguredColumnWithOr(): void
    {
        $filter = new LikeFulltextFilter(['name', 'email', 42, 'message']);
        $filter->apply($this->collection(), $this->filter('  ann  '));

        $this->assertSame(
            ["`name` LIKE '%ann%' OR `email` LIKE '%ann%' OR `message` LIKE '%ann%'"],
            $this->where
        );
    }

    public function testLikeWildcardsInTheSearchAreEscaped(): void
    {
        (new LikeFulltextFilter(['name']))->apply($this->collection(), $this->filter('50%_off\\'));

        $this->assertSame(["`name` LIKE '%50\\%\\_off\\\\%'"], $this->where);
    }

    public function testSearchTermIsCappedAt200Characters(): void
    {
        (new LikeFulltextFilter(['name']))->apply($this->collection(), $this->filter(str_repeat('x', 300)));

        $this->assertSame(["`name` LIKE '%" . str_repeat('x', 200) . "%'"], $this->where);
    }

    public function testBlankOrNonScalarValuesAddNoCondition(): void
    {
        $filter = new LikeFulltextFilter(['name']);
        $collection = $this->collection();

        $filter->apply($collection, $this->filter('   '));
        $filter->apply($collection, $this->filter(['a']));
        $filter->apply($collection, $this->filter(null));

        $this->assertSame([], $this->where);
    }

    public function testNoColumnsMeansNoCondition(): void
    {
        $collection = $this->collection();
        (new LikeFulltextFilter([1, null]))->apply($collection, $this->filter('ann'));

        $this->assertSame([], $this->where);
    }

    public function testNonDatabaseCollectionsAreIgnored(): void
    {
        $plain = $this->createStub(Collection::class);
        (new LikeFulltextFilter(['name']))->apply($plain, $this->filter('ann'));

        $this->assertSame([], $this->where);
    }
}

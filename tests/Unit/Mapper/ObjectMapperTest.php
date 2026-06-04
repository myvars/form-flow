<?php

declare(strict_types=1);

namespace MyVars\FormFlow\Tests\Unit\Mapper;

use MyVars\FormFlow\Mapper\ObjectMapper;
use MyVars\FormFlow\Tests\Fixtures\SampleEntity;
use PHPUnit\Framework\TestCase;
use Symfony\Component\ObjectMapper\ObjectMapperInterface;

final class ObjectMapperTest extends TestCase
{
    public function testReturnsDataUnchangedWhenAlreadyTargetType(): void
    {
        $inner = $this->createMock(ObjectMapperInterface::class);
        $inner->expects($this->never())->method('map');

        $mapper = new ObjectMapper($inner, SampleEntity::class);
        $existing = new SampleEntity('kept');

        self::assertSame($existing, $mapper($existing));
    }

    public function testDelegatesToInnerMapperOtherwise(): void
    {
        $mapped = new SampleEntity('mapped');
        $source = (object) ['name' => 'raw'];

        $inner = $this->createMock(ObjectMapperInterface::class);
        $inner->expects($this->once())
            ->method('map')
            ->with($source, SampleEntity::class)
            ->willReturn($mapped);

        $mapper = new ObjectMapper($inner, SampleEntity::class);

        self::assertSame($mapped, $mapper($source));
    }
}

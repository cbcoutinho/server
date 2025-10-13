<?php

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Test;

use OC\AppFramework\Utility\TimeFactory;
use OC\SnowflakeId;
use OC\SnowflakeIdGenerator;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\ICacheFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * @package Test
 */
class SnowflakeIdGeneratorTest extends TestCase {
	private ITimeFactory|MockObject $timeFactory;
	private ICacheFactory|MockObject $cacheFactory;

	public function setUp(): void {
		$this->timeFactory = $this->createMock(ITimeFactory::class);
		$this->cacheFactory = $this->createMock(ICacheFactory::class);
	}

	public function testGenerator(): void {
		$generator = new SnowflakeIdGenerator(new TimeFactory(), $this->cacheFactory);
		$snowflakeId = ($generator)();
		$this->assertGreaterThan(0x100000000, $snowflakeId);
		if (PHP_INT_SIZE === 8) {
			$this->assertIsInt($snowflakeId);
		} else {
			$this->assertIsString($snowflakeId);
		}
	}

	#[DataProvider('provideSnowflakeData')]
	public function testGeneratorWithFixedTime(string $date, int $expectedSeconds, int $expectedMilliseconds): void {
		$dt = new \DateTimeImmutable($date);
		$this->timeFactory->method('now')->willReturn($dt);
		$generator = new SnowflakeIdGenerator($this->timeFactory, $this->cacheFactory);
		$snowflakeId = new SnowflakeId(($generator)());
		printf("TS: %d %d\n", $expectedSeconds, $snowflakeId->seconds());
		printf("MS: %d %d\n", $expectedMilliseconds, $snowflakeId->milliseconds());

		$this->assertEquals($expectedSeconds, $snowflakeId->seconds());
		$this->assertEquals($expectedMilliseconds, $snowflakeId->milliseconds());
	}

	public static function provideSnowflakeData(): array {
		return  [
			['2025-10-01 00:00:00.000000', 0, 0],
			['2039-12-31 23:59:59.999999', 449711999, 999],
			['2027-08-06 03:08:30.000975', 58244910, 0],
			['2030-06-21 12:59:33.100875', 149000373, 100],
			['2086-06-21 12:59:33.010875', 1916225973, 10],
		];
	}
}

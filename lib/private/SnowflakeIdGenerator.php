<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace OC;

use OCP\AppFramework\Utility\ITimeFactory;
use OCP\ICacheFactory;

/**
 * Nextcloud Snowflake ID generator
 *
 * Generates unique ID for database
 *
 * @since 33.0.0
 */
class SnowflakeIdGenerator {
	public function __construct(
		private readonly ITimeFactory $timeFactory,
		private readonly ICacheFactory $cacheFactory,
	) {
	}

	public function __invoke(): int|string {
		// Time related
		[$seconds, $milliseconds] = $this->getCurrentTime();

		$serverId = $this->getServerId() & 0x1FF; // Keep 9 bits
		$isCli = (int)$this->isCli(); // 1 bit
		$sequenceId = $this->getSequenceId($seconds, $milliseconds, $serverId); //  12 bits
		if ($sequenceId > 0xFFF) {
			// Throttle a bit, wait for next millisecond
			usleep(1000);
			return $this();
		}

		if (PHP_INT_SIZE === 8) {
			$firstHalf = $seconds & 0x7FFFFFFF;
			$secondHalf = (($milliseconds & 0x3FF) << 22) | ($serverId << 13) | ($isCli << 12) | $sequenceId;
			printf("\n%08x%08x\n", $firstHalf, $secondHalf);
			return $firstHalf << 32 | $secondHalf;
		}

		// Fallback for 32 bits systems
		printf("\nSec: %s\n", $seconds);
		$firstQuarter = ($seconds >> 16) & 0x7FFF;
		$secondQuarter = $seconds & 0xFFFF;
		$thirdQuarter = ($milliseconds & 0x3FF) << 6 | ($serverId >> 3) & 0x3F;
		$fourthQuarter = ($serverId & 0x7) << 13 | ($isCli & 0x1) << 12 | $sequenceId & 0xFFF;
		printf("Hex: %04x%04x%04x%04x\n", $firstQuarter, $secondQuarter, $thirdQuarter, $fourthQuarter);

		return $this->convertToDecimal($firstQuarter, $secondQuarter, $thirdQuarter, $fourthQuarter);
	}

	/**
	 * Mostly copied from Symfony:
	 * https://github.com/symfony/symfony/blob/v7.3.4/src/Symfony/Component/Uid/BinaryUtil.php#L49
	 */
	private function convertToDecimal(int ... $bytes): string {
		$base = 10;
		$digits = '';

		while ($count = \count($bytes)) {
			$quotient = [];
			$remainder = 0;

			for ($i = 0; $i !== $count; ++$i) {
				$carry = $bytes[$i] + ($remainder << (PHP_INT_SIZE === 8 ? 16 : 8));
				$digit = intdiv($carry, $base);
				$remainder = $carry % $base;

				if ($digit || $quotient) {
					$quotient[] = $digit;
				}
			}

			$digits = $remainder . $digits;
			$bytes = $quotient;
		}

		return $digits;
	}

	private function getCurrentTime(): array {
		$time = $this->timeFactory->now();
		return [
			$time->getTimestamp() - SnowflakeId::TS_OFFSET,
			(int)$time->format('v'),
		];
	}

	private function getServerId(): int {
		return crc32(gethostname() ?: random_bytes(8));
	}

	private function isCli(): bool {
		return PHP_SAPI === 'cli';
	}

	private function getSequenceId(int $seconds, int $milliseconds, int $serverId): int {
		$key = 'seq:' . $seconds . ':' . $milliseconds;
		// Use APCu as fastest local cache, but not shared between processes in CLI
		if (!$this->isCli()) {
			$sequenceId = apcu_inc($key, ttl: 1);
			if ($sequenceId === false) {
				throw new \Exception('Failed to generate SnowflakeId with APCu');
			}

			return $sequenceId;
		}

		// Following lock can be shared between servers, add $serverId in $key
		$key .= ':' . $serverId;

		if ($this->cacheFactory->isAvailable()) {
			$cache = $this->cacheFactory->createLocking('sequence');
			// Inc doesn't allow to give TTL so try to add first
			if ($cache->add($key, 0, 1)) {
				return 0;
			}
			$sequence = $cache->inc($key);
			if (is_int($sequence)) {
				return $sequence;
			}

			throw new \Exception('Unable to generate sequence ID with locking cache');

		}

		// If all failed, just return a random number
		return random_int(0, 0xFFF - 1);
	}
}

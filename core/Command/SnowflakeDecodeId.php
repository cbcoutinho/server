<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-only
 */
namespace OC\Core\Command;

use OC\SnowflakeId;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SnowflakeDecodeId extends Base {
	protected function configure(): void {
		parent::configure();

		$this
			->setName('decode-snowflake')
			->setDescription('Decode Snowflake IDs used by Nextcloud')
			->addArgument('snowflake-id', InputArgument::REQUIRED, 'Nextcloud Snowflake ID to decode');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$snowflakeId = $input->getArgument('snowflake-id');
		$snowflakeId = new SnowflakeId($snowflakeId);

		$rows = [
			['Snowflake ID', $snowflakeId->numeric()],
			['Seconds', $snowflakeId->seconds()],
			['Milliseconds', $snowflakeId->milliseconds()],
			['Created from CLI', $snowflakeId->isCli() ? 'yes' : 'no'],
			['Server ID', $snowflakeId->serverId()],
			['Sequence ID', $snowflakeId->sequenceId()],
			['Creation timestamp', $snowflakeId->createdAt()],
			['Creation date', date('Y-m-d H:i:s', (int)$snowflakeId->createdAt()) . '.' . $snowflakeId->milliseconds()],
		];

		$table = new Table($output);
		$table->setRows($rows);
		$table->render();

		return Base::SUCCESS;
	}
}

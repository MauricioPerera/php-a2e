<?php

declare(strict_types=1);

namespace PHPA2E\Operation;

use PHPA2E\Executor\DataModel;

interface OperationInterface
{
    /**
     * @return string Operation type name (e.g., "ApiCall", "FilterData")
     */
    public function type(): string;

    /**
     * Execute the operation.
     *
     * @param array $config Operation configuration from the workflow
     * @param DataModel $data Shared workflow data model
     * @return array Result of the operation
     */
    public function execute(array $config, DataModel $data): array;
}

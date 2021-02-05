<?php

/**
 * (c) FSi sp. z o.o <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FSi\Component\DataIndexer;

interface DataIndexerInterface
{
    /**
     * @param mixed $data
     * @return mixed
     */
    public function getIndex($data): string;

    /**
     * @param string $index
     * @return mixed
     */
    public function getData(string $index);

    public function getDataSlice(array $indexes): array;

    public function getSeparator(): string;

    /**
     * Check if data can be indexed by DataIndexer.
     *
     * @param mixed $data
     * @return void
     */
    public function validateData($data): void;
}

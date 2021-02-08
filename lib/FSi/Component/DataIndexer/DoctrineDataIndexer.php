<?php

/**
 * (c) FSi sp. z o.o <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FSi\Component\DataIndexer;

use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Persistence\ObjectRepository;
use Symfony\Component\PropertyAccess\PropertyAccess;
use FSi\Component\DataIndexer\Exception\InvalidArgumentException;
use FSi\Component\DataIndexer\Exception\RuntimeException;

class DoctrineDataIndexer implements DataIndexerInterface
{
    /**
     * @var string
     */
    protected $separator = "|";

    /**
     * @var ObjectManager
     */
    protected $manager;

    /**
     * @var string
     */
    protected $class;

    /**
     * @param ManagerRegistry $registry
     * @param string $class
     * @throws Exception\InvalidArgumentException
     * @throws Exception\RuntimeException
     */
    public function __construct(ManagerRegistry $registry, string $class)
    {
        $this->manager = $this->tryToGetObjectManager($registry, $class);
        $this->class = $this->tryToGetRootClass($class);
    }

    public function getIndex($data): string
    {
        $this->validateData($data);

        return $this->joinIndexParts($this->getIndexParts($data));
    }

    public function getData(string $index)
    {
        return $this->tryToFindEntity($this->buildSearchCriteria($index));
    }

    public function getDataSlice(array $indexes): array
    {
        return $this->getRepository()->findBy($this->buildMultipleSearchCriteria($indexes));
    }

    public function validateData($data): void
    {
        if (false === is_object($data)) {
            throw new InvalidArgumentException("DoctrineDataIndexer can index only objects.");
        }

        if (false === $data instanceof $this->class) {
            throw new InvalidArgumentException(sprintf(
                'DoctrineDataIndexer expects data as instance of "%s" instead of "%s".',
                $this->class,
                get_class($data)
            ));
        }
    }

    public function getSeparator(): string
    {
        return $this->separator;
    }

    public function getClass(): string
    {
        return $this->class;
    }

    /**
     * Returns an array of identifier field names for self::$class.
     *
     * @return array
     */
    private function getIdentifierFieldNames(): array
    {
        return $this->manager
            ->getClassMetadata($this->class)
            ->getIdentifierFieldNames();
    }

    /**
     * @param ManagerRegistry $registry
     * @param string $class
     * @return ObjectManager
     * @throws Exception\InvalidArgumentException
     */
    private function tryToGetObjectManager(ManagerRegistry $registry, string $class): ObjectManager
    {
        $manager = $registry->getManagerForClass($class);

        if (null === $manager) {
            throw new InvalidArgumentException(sprintf(
                'ManagerRegistry doesn\'t have manager for class "%s".',
                $class
            ));
        }

        return $manager;
    }

    private function tryToGetRootClass(string $class): string
    {
        $classMetadata = $this->manager->getClassMetadata($class);

        if (false === $classMetadata instanceof ClassMetadataInfo) {
            throw new RuntimeException("Only Doctrine ORM is supported at the moment");
        }

        if (true === $classMetadata->isMappedSuperclass) {
            throw new RuntimeException('DoctrineDataIndexer can\'t be created for mapped super class.');
        }

        return $classMetadata->rootEntityName;
    }

    /**
     * @param mixed $object
     * @return array
     */
    private function getIndexParts($object): array
    {
        $identifiers = $this->getIdentifierFieldNames();

        $accessor = PropertyAccess::createPropertyAccessor();
        return array_map(
            static function ($identifier) use ($object, $accessor) {
                return $accessor->getValue($object, $identifier);
            },
            $identifiers
        );
    }

    private function joinIndexParts(array $indexes): string
    {
        return implode($this->separator, $indexes);
    }

    private function splitIndex(string $index, int $identifiersCount): array
    {
        $indexParts = explode($this->getSeparator(), $index);
        if (count($indexParts) !== $identifiersCount) {
            throw new RuntimeException(
                "Can't split index into parts. Maybe you should consider using different separator?"
            );
        }

        return $indexParts;
    }

    private function buildMultipleSearchCriteria(array $indexes): array
    {
        $multipleSearchCriteria = array();
        foreach ($indexes as $index) {
            foreach ($this->buildSearchCriteria($index) as $identifier => $indexPart) {
                if (false === array_key_exists($identifier, $multipleSearchCriteria)) {
                    $multipleSearchCriteria[$identifier] = array();
                }

                $multipleSearchCriteria[$identifier][] = $indexPart;
            }
        }
        return $multipleSearchCriteria;
    }

    private function buildSearchCriteria(string $index): array
    {
        $identifiers = $this->getIdentifierFieldNames();
        $indexParts = $this->splitIndex($index, count($identifiers));

        return array_combine($identifiers, $indexParts);
    }

    /**
     * @param array $searchCriteria
     * @return object
     * @throws Exception\RuntimeException
     */
    private function tryToFindEntity(array $searchCriteria)
    {
        $entity = $this->getRepository()->findOneBy($searchCriteria);

        if (null === $entity) {
            throw new RuntimeException(
                'Can\'t find any entity using the following search criteria: "' . implode(", ", $searchCriteria) . '"'
            );
        }

        return $entity;
    }

    private function getRepository(): ObjectRepository
    {
        return $this->manager->getRepository($this->class);
    }
}

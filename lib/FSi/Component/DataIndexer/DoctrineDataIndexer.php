<?php

/**
 * (c) FSi sp. z o.o <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FSi\Component\DataIndexer;

use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\PropertyAccess\PropertyAccess;
use FSi\Component\DataIndexer\Exception\InvalidArgumentException;
use FSi\Component\DataIndexer\Exception\RuntimeException;
use Doctrine\Common\Persistence\ManagerRegistry;

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
     * @param $class
     * @throws Exception\InvalidArgumentException
     * @throws Exception\RuntimeException
     */
    public function __construct(ManagerRegistry $registry, $class)
    {
        $this->manager = $registry->getManagerForClass($class);

        if (!isset($this->manager)) {
            throw new InvalidArgumentException(sprintf(
                'ManagerRegistry doesn\'t have manager for class "%s".',
                $class
            ));
        }

        $classMetadata = $this->manager->getClassMetadata($class);
        if ($classMetadata->isMappedSuperclass) {
            throw new RuntimeException('DoctrineDataIndexer can\'t be created for mapped super class.');
        }

        $this->class = $classMetadata->rootEntityName;
    }

    /**
     * {@inheritdoc}
     */
    public function getIndex($data)
    {
        $this->validateData($data);

        $metadataFactory = $this->manager->getMetadataFactory();
        $metadata = $metadataFactory->getMetadataFor($this->class);

        // We can assume, that there are always some identifiers, since otherwise Doctrine would throw an exception.
        $identifiers = $metadata->getIdentifierFieldNames();

        $accessor = PropertyAccess::createPropertyAccessor();
        $indexes = array();
        foreach ($identifiers as $identifier) {
            $indexes[] = $accessor->getValue($data, $identifier);
        }

        return implode($this->separator, $indexes);
    }

    /**
     * {@inheritdoc}
     */
    public function getData($index)
    {
        $identifiers = $this->getIdentifierFieldNames();
        $searchCriteria = array();
        if (count($identifiers) > 1) {
            $indexParts = explode($this->getSeparator(), $index);
            if (count($indexParts) != count($identifiers)) {
                throw new RuntimeException("Can't split index into parts. Maybe you should consider using different separator?");
            }

            reset($indexParts);
            foreach ($identifiers as $identifier) {
                $searchCriteria[$identifier] = current($indexParts);
                next($indexParts);
            }
        } else {
            $searchCriteria[current($identifiers)] = $index;
        }

        $entity = $this->manager->getRepository($this->class)->findOneBy($searchCriteria);

        if (!isset($entity)) {
            throw new RuntimeException('Can\'t find any entity using the following search criteria: "' . implode(", ", $searchCriteria)  . '"');
        }

        return $entity;
    }

    /**
     * {@inheritdoc}
     */
    public function getDataSlice($indexes)
    {
        if (!is_array($indexes) && (!$indexes instanceof \Traversable && !$indexes instanceof \Countable) ) {
            throw new InvalidArgumentException('Indexes are not traversable.');
        }

        $identifiers = $this->getIdentifierFieldNames();
        $searchCriteria = array();

        if (count($identifiers) > 1) {
            foreach ($indexes as $index) {
                $indexParts = explode($this->getSeparator(), $index);

                if (count($indexParts) != count($identifiers)) {
                    throw new RuntimeException("Can't split index into parts. Maybe you should consider using different separator?");
                }

                reset($indexParts);
                foreach ($identifiers as $identifier) {
                    if (!isset($searchCriteria[$identifier])) {
                        $searchCriteria[$identifier] = array();
                    }

                    $searchCriteria[$identifier][] = current($indexParts);
                    next($indexParts);
                }
            }
        } else {
            $searchCriteria[current($identifiers)] = (array) $indexes;
        }

        $entities = $this->manager->getRepository($this->class)->findBy($searchCriteria);

        return $entities;
    }

    /**
     * {@inheritdoc}
     */
    public function validateData($data)
    {
        if (!is_object($data)) {
            throw new InvalidArgumentException("DoctrineDataIndexer can index only objects.");
        }

        if (!is_a($data, $this->class)) {
            throw new InvalidArgumentException(sprintf(
                'DoctrineDataIndexer expects data as instance of "%s" instead of "%s".',
                $this->class,
                get_class($data)
            ));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getSeparator()
    {
        return $this->separator;
    }

    /**
     * Get class idexer is constructed for.
     *
     * @return string
     */
    public function getClass()
    {
        return $this->class;
    }

    /**
     * Returns an array of identifier field names for self::$class.
     *
     * @return array
     */
    private function getIdentifierFieldNames()
    {
        $metadataFactory = $this->manager->getMetadataFactory();
        $metadata = $metadataFactory->getMetadataFor($this->class);

        return $metadata->getIdentifierFieldNames();
    }
}

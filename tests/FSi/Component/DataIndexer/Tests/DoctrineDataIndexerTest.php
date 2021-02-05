<?php

/**
 * (c) FSi sp. z o.o <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FSi\Component\DataIndexer\Tests;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\CachedReader;
use Doctrine\Common\Cache\ArrayCache;
use Doctrine\Common\EventManager;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\Mapping\DefaultQuoteStrategy;
use Doctrine\ORM\Mapping\Driver\AnnotationDriver;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Repository\DefaultRepositoryFactory;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use FSi\Component\DataIndexer\DoctrineDataIndexer;
use FSi\Component\DataIndexer\Exception\InvalidArgumentException;
use FSi\Component\DataIndexer\Exception\RuntimeException;
use FSi\Component\DataIndexer\Tests\Fixtures\DeciduousTree;
use FSi\Component\DataIndexer\Tests\Fixtures\Monocycle;
use FSi\Component\DataIndexer\Tests\Fixtures\News;
use FSi\Component\DataIndexer\Tests\Fixtures\Oak;
use FSi\Component\DataIndexer\Tests\Fixtures\Plant;
use FSi\Component\DataIndexer\Tests\Fixtures\Post;
use FSi\Component\DataIndexer\Tests\Fixtures\Car;
use FSi\Component\DataIndexer\Tests\Fixtures\Bike;
use FSi\Component\DataIndexer\Tests\Fixtures\Tree;
use FSi\Component\DataIndexer\Tests\Fixtures\Vehicle;
use PHPUnit\Framework\TestCase;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\Mapping\ClassMetadataFactory;
use Doctrine\ORM\EntityRepository;

class DoctrineDataIndexerTest extends TestCase
{
    /**
     * @var EntityManager
     */
    protected $em;

    protected function setUp(): void
    {
        $connectionParams = [
            'driver'    => 'pdo_sqlite',
            'memory'    => true,
        ];

        $evm = new EventManager();
        $config = $this->getMockAnnotatedConfig();
        $conn = DriverManager::getConnection($connectionParams, $config, $evm);
        $em = EntityManager::create($conn, $config, $evm);
        $schema = array_map(static function($class) use ($em) {
            return $em->getClassMetadata($class);
        }, [News::class, Post::class]);

        $schemaTool = new SchemaTool($em);
        $schemaTool->dropSchema($schema);
        $schemaTool->updateSchema($schema, true);

        $this->em = $em;
    }

    public function testDataIndexerWithInvalidClass(): void
    {
        $managerRegistry = $this->createMock(ManagerRegistry::class);
        $managerRegistry->expects(self::any())
            ->method('getManagerForClass')
            ->willReturn(null);

        $class = "\\FSi\\Component\\DataIndexer\\DataIndexer";

        $this->expectException(InvalidArgumentException::class);
        new DoctrineDataIndexer($managerRegistry, $class);
    }

    public function testGetIndexWithSimpleKey(): void
    {
        $dataIndexer = new DoctrineDataIndexer($this->getManagerRegistry(), News::class);

        $news = new News("foo");

        self::assertSame($dataIndexer->getIndex($news), "foo");
    }

    public function testGetIndexWithCompositeKey(): void
    {
        $dataIndexer = new DoctrineDataIndexer($this->getManagerRegistry(), Post::class);

        $news = new Post("foo", "bar");

        self::assertSame($dataIndexer->getIndex($news), "foo" . $dataIndexer->getSeparator() . "bar");
    }

    public function testGetDataWithSimpleKey(): void
    {
        $dataIndexer = new DoctrineDataIndexer($this->getManagerRegistry(), News::class);

        $news = new News('foo');
        $this->em->persist($news);
        $this->em->flush();
        $this->em->clear();

        $news = $dataIndexer->getData("foo");

        self::assertSame($news->getId(), "foo");
    }

    public function testGetDataWithCompositeKey(): void
    {
        $dataIndexer = new DoctrineDataIndexer($this->getManagerRegistry(), Post::class);

        $post = new Post('foo', 'bar');
        $this->em->persist($post);
        $this->em->flush();
        $this->em->clear();

        $post = $dataIndexer->getData("foo|bar");

        self::assertSame($post->getIdFirstPart(), "foo");
        self::assertSame($post->getIdSecondPart(), "bar");
    }

    public function testGetDataWithCompositeKeyAndSeparatorInID(): void
    {
        $dataIndexer = new DoctrineDataIndexer($this->getManagerRegistry(), Post::class);

        $this->expectException(RuntimeException::class);
        $dataIndexer->getData("foo||bar");
    }

    public function testGetDataSliceWithSimpleKey(): void
    {
        $dataIndexer = new DoctrineDataIndexer($this->getManagerRegistry(), News::class);

        $news1 = new News('foo');
        $news2 = new News('bar');
        $this->em->persist($news1);
        $this->em->persist($news2);
        $this->em->flush();
        $this->em->clear();

        $news = $dataIndexer->getDataSlice(array("foo", "bar"));

        self::assertSame([$news[0]->getId(), $news[1]->getId()], ["bar", "foo"]);
    }

    public function testGetDataSliceWithCompositeKey(): void
    {
        $dataIndexer = new DoctrineDataIndexer($this->getManagerRegistry(), Post::class);

        $post1 = new Post('foo', 'foo1');
        $post2 = new Post('bar', 'bar1');
        $this->em->persist($post1);
        $this->em->persist($post2);
        $this->em->flush();
        $this->em->clear();

        $news = $dataIndexer->getDataSlice(["foo|foo1", "bar|bar1"]);

        self::assertSame([
            $news[0]->getIdFirstPart() . '|' . $news[0]->getIdSecondPart(),
            $news[1]->getIdFirstPart() . '|' . $news[1]->getIdSecondPart(),
        ], ["bar|bar1", "foo|foo1"]);
    }

    public function testGetIndexWithSubclass(): void
    {
        $dataIndexer = new DoctrineDataIndexer($this->getManagerRegistry(), Vehicle::class);

        // Creating subclasses of News
        $car = new Car('foo');
        $bike = new Bike('bar');

        self::assertSame($dataIndexer->getIndex($car), 'foo');
        self::assertSame($dataIndexer->getIndex($bike), 'bar');
        self::assertSame(Vehicle::class, $dataIndexer->getClass());
    }

    /**
     * For simple entity indexer must be set that class.
     */
    public function testCreateWithSimpleEntity(): void
    {
        $dataIndexer = new DoctrineDataIndexer($this->getManagerRegistry(), News::class);
        self::assertSame(News::class, $dataIndexer->getClass());
    }

    /**
     * For entity that extends other entity, indexer must set its parent.
     */
    public function testCreateWithSubclass(): void
    {
        $dataIndexer = new DoctrineDataIndexer($this->getManagerRegistry(), Car::class);
        self::assertSame(Vehicle::class, $dataIndexer->getClass());
    }

    /**
     * For few levels of inheritance indexer must set its highest parent.
     */
    public function testCreateWithSubclasses(): void
    {
        $dataIndexer = new DoctrineDataIndexer($this->getManagerRegistry(), Monocycle::class);
        self::assertSame(Vehicle::class, $dataIndexer->getClass());
    }

    /**
     * For entity that is on top of inheritance tree indexer must set given class.
     */
    public function testCreateWithEntityThatOtherInheritsFrom(): void
    {
        $dataIndexer = new DoctrineDataIndexer($this->getManagerRegistry(), Vehicle::class);
        self::assertSame(Vehicle::class, $dataIndexer->getClass());
    }

    public function testCreateWithMappedSuperClass(): void
    {
        $this->expectException(RuntimeException::class);
        new DoctrineDataIndexer($this->getManagerRegistry(), Plant::class);
    }

    /**
     * For entity that inherits from mapped super class indexer must be set to
     * the same class that was created.
     */
    public function testCreateWithEntityThatInheritsFromMappedSuperClass(): void
    {
        $dataIndexer = new DoctrineDataIndexer($this->getManagerRegistry(), Tree::class);
        self::assertSame(Tree::class, $dataIndexer->getClass());
    }

    public function testSecondLevelOfInheritanceFromMappedSuperClass(): void
    {
        $dataIndexer = new DoctrineDataIndexer($this->getManagerRegistry(), DeciduousTree::class);
        self::assertSame(Tree::class, $dataIndexer->getClass());
    }

    public function testThirdLevelOfInheritanceFromMappedSuperClass(): void
    {
        $dataIndexer = new DoctrineDataIndexer($this->getManagerRegistry(), Oak::class);
        self::assertSame(Tree::class, $dataIndexer->getClass());
    }

    protected function getManagerRegistry(): ManagerRegistry
    {
        $managerRegistry = $this->createMock(ManagerRegistry::class);
        $managerRegistry->method('getManagerForClass')->willReturn($this->em);

        return $managerRegistry;
    }

    protected function getMockAnnotatedConfig(): Configuration
    {
        $config = $this->createMock(Configuration::class);
        $config->method('getProxyDir')->willReturn(TESTS_TEMP_DIR);

        $config->expects(self::once())->method('getProxyNamespace')->willReturn('Proxy');
        $config->expects(self::once())->method('getAutoGenerateProxyClasses')->willReturn(true);
        $config->expects(self::once())->method('getClassMetadataFactoryName')->willReturn(ClassMetadataFactory::class);
        $config->method('getQuoteStrategy')->willReturn(new DefaultQuoteStrategy());

        $reader = new AnnotationReader();
        $reader = new CachedReader($reader, new ArrayCache());

        $config->method('getMetadataDriverImpl')->willReturn(new AnnotationDriver($reader, __DIR__));
        $config->method('getDefaultRepositoryClassName')->willReturn(EntityRepository::class);
        $config->method('getRepositoryFactory')->willReturn(new DefaultRepositoryFactory());

        return $config;
    }
}

<?php

/**
 * (c) FSi sp. z o.o <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FSi\Component\DataIndexer\Tests;

use Doctrine\Common\EventManager;
use Doctrine\ORM\Mapping\Driver\AnnotationDriver;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Repository\DefaultRepositoryFactory;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use FSi\Component\DataIndexer\DoctrineDataIndexer;
use FSi\Component\DataIndexer\Exception\InvalidArgumentException;
use FSi\Component\DataIndexer\Exception\RuntimeException;
use FSi\Component\DataIndexer\Tests\Fixtures\News;
use FSi\Component\DataIndexer\Tests\Fixtures\Post;
use FSi\Component\DataIndexer\Tests\Fixtures\Car;
use FSi\Component\DataIndexer\Tests\Fixtures\Bike;
use PHPUnit\Framework\TestCase;
use Doctrine\ORM\Configuration;

class DoctrineDataIndexerTest extends TestCase
{
    /**
     * Namespace for fixtures.
     */
    const FIXTURES = 'FSi\\Component\\DataIndexer\\Tests\\Fixtures\\';

    /**
     * @var EntityManager
     */
    protected $em;

    protected function setUp(): void
    {
        $connectionParams = array(
            'driver'    => 'pdo_sqlite',
            'memory'    => true,
        );

        $evm = new EventManager();
        $config = $this->getMockAnnotatedConfig();
        $conn = \Doctrine\DBAL\DriverManager::getConnection($connectionParams, $config, $evm);
        $em = EntityManager::create($conn, $config, $evm);
        $schema = array_map(function($class) use ($em) {
            return $em->getClassMetadata($class);
        }, array(
            self::FIXTURES . 'News',
            self::FIXTURES . 'Post',
        ));

        $schemaTool = new SchemaTool($em);
        $schemaTool->dropSchema($schema);
        $schemaTool->updateSchema($schema, true);

        $this->em = $em;
    }

    public function testDataIndexerWithInvalidClass(): void
    {
        $managerRegistry = $this->createMock(ManagerRegistry::class);
        $managerRegistry->expects($this->any())
            ->method('getManagerForClass')
            ->will($this->returnValue(null));

        $class = "\\FSi\\Component\\DataIndexer\\DataIndexer";

        $this->expectException(InvalidArgumentException::class);
        new DoctrineDataIndexer($managerRegistry, $class);
    }

    public function testGetIndexWithSimpleKey(): void
    {
        $class = "FSi\\Component\\DataIndexer\\Tests\\Fixtures\\News";
        $dataIndexer = new DoctrineDataIndexer($this->getManagerRegistry(), $class);

        $news = new News("foo");

        $this->assertSame($dataIndexer->getIndex($news), "foo");
    }

    public function testGetIndexWithCompositeKey(): void
    {
        $class = "FSi\\Component\\DataIndexer\\Tests\\Fixtures\\Post";
        $dataIndexer = new DoctrineDataIndexer($this->getManagerRegistry(), $class);

        $news = new Post("foo", "bar");

        $this->assertSame($dataIndexer->getIndex($news), "foo" . $dataIndexer->getSeparator() . "bar");
    }

    public function testGetDataWithSimpleKey(): void
    {
        $class = "FSi\\Component\\DataIndexer\\Tests\\Fixtures\\News";
        $dataIndexer = new DoctrineDataIndexer($this->getManagerRegistry(), $class);

        $news = new News('foo');
        $this->em->persist($news);
        $this->em->flush();
        $this->em->clear();

        $news = $dataIndexer->getData("foo");

        $this->assertSame($news->getId(), "foo");
    }

    public function testGetDataWithCompositeKey(): void
    {
        $class = "FSi\\Component\\DataIndexer\\Tests\\Fixtures\\Post";
        $dataIndexer = new DoctrineDataIndexer($this->getManagerRegistry(), $class);

        $post = new Post('foo', 'bar');
        $this->em->persist($post);
        $this->em->flush();
        $this->em->clear();

        $post = $dataIndexer->getData("foo|bar");

        $this->assertSame($post->getIdFirstPart(), "foo");
        $this->assertSame($post->getIdSecondPart(), "bar");
    }

    public function testGetDataWithCompositeKeyAndSeparatorInID(): void
    {
        $class = "FSi\\Component\\DataIndexer\\Tests\\Fixtures\\Post";
        $dataIndexer = new DoctrineDataIndexer($this->getManagerRegistry(), $class);

        $this->expectException(RuntimeException::class);
        $dataIndexer->getData("foo||bar");
    }

    public function testGetDataSliceWithSimpleKey(): void
    {
        $class = "FSi\\Component\\DataIndexer\\Tests\\Fixtures\\News";
        $dataIndexer = new DoctrineDataIndexer($this->getManagerRegistry(), $class);

        $news1 = new News('foo');
        $news2 = new News('bar');
        $this->em->persist($news1);
        $this->em->persist($news2);
        $this->em->flush();
        $this->em->clear();

        $news = $dataIndexer->getDataSlice(array("foo", "bar"));

        $this->assertSame(array(
            $news[0]->getId(),
            $news[1]->getId()
        ), array("bar", "foo"));
    }

    public function testGetDataSliceWithCompositeKey(): void
    {
        $class = "FSi\\Component\\DataIndexer\\Tests\\Fixtures\\Post";
        $dataIndexer = new DoctrineDataIndexer($this->getManagerRegistry(), $class);

        $post1 = new Post('foo', 'foo1');
        $post2 = new Post('bar', 'bar1');
        $this->em->persist($post1);
        $this->em->persist($post2);
        $this->em->flush();
        $this->em->clear();

        $news = $dataIndexer->getDataSlice(array("foo|foo1", "bar|bar1"));

        $this->assertSame(array(
            $news[0]->getIdFirstPart() . '|' . $news[0]->getIdSecondPart(),
            $news[1]->getIdFirstPart() . '|' . $news[1]->getIdSecondPart(),
        ), array("bar|bar1", "foo|foo1"));
    }

    public function testGetIndexWithSubclass(): void
    {
        $class = self::FIXTURES . 'Vehicle';
        $dataIndexer = new DoctrineDataIndexer($this->getManagerRegistry(), $class);

        // Creating subclasses of News
        $car = new Car('foo');
        $bike = new Bike('bar');

        $this->assertSame($dataIndexer->getIndex($car), 'foo');
        $this->assertSame($dataIndexer->getIndex($bike), 'bar');
        $this->assertSame($class, $dataIndexer->getClass());
    }

    /**
     * For simple entity indexer must be set that class.
     */
    public function testCreateWithSimpleEntity(): void
    {
        $class = self::FIXTURES . 'News';
        $dataIndexer = new DoctrineDataIndexer($this->getManagerRegistry(), $class);
        $this->assertSame($class, $dataIndexer->getClass());
    }

    /**
     * For entity that extends other entity, indexer must set its parent.
     */
    public function testCreateWithSubclass(): void
    {
        $class = self::FIXTURES . 'Car';
        $dataIndexer = new DoctrineDataIndexer($this->getManagerRegistry(), $class);
        $this->assertSame(self::FIXTURES . 'Vehicle', $dataIndexer->getClass());
    }

    /**
     * For few levels of inheritance indexer must set its highest parent.
     */
    public function testCreateWithSubclasses(): void
    {
        $class = self::FIXTURES . 'Monocycle';
        $dataIndexer = new DoctrineDataIndexer($this->getManagerRegistry(), $class);
        $this->assertSame(self::FIXTURES . 'Vehicle', $dataIndexer->getClass());
    }

    /**
     * For entity that is on top of inheritance tree indexer must set given class.
     */
    public function testCreateWithEntityThatOtherInheritsFrom(): void
    {
        $class = self::FIXTURES . 'Vehicle';
        $dataIndexer = new DoctrineDataIndexer($this->getManagerRegistry(), $class);
        $this->assertSame($class, $dataIndexer->getClass());
    }

    public function testCreateWithMappedSuperClass(): void
    {
        $this->expectException(RuntimeException::class);
        $class = self::FIXTURES . 'Plant';
        new DoctrineDataIndexer($this->getManagerRegistry(), $class);
    }

    /**
     * For entity that inherits from mapped super class indexer must be set to
     * the same class that was created.
     */
    public function testCreateWithEntityThatInheritsFromMappedSuperClass(): void
    {
        $class = self::FIXTURES . 'Tree';
        $dataIndexer = new DoctrineDataIndexer($this->getManagerRegistry(), $class);
        $this->assertSame($class, $dataIndexer->getClass());
    }

    public function testSecondLevelOfInheritanceFromMappedSuperClass(): void
    {
        $class = self::FIXTURES . 'DeciduousTree';
        $dataIndexer = new DoctrineDataIndexer($this->getManagerRegistry(), $class);
        $this->assertSame(self::FIXTURES . 'Tree', $dataIndexer->getClass());
    }

    public function testThirdLevelOfInheritanceFromMappedSuperClass(): void
    {
        $class = self::FIXTURES . 'Oak';
        $dataIndexer = new DoctrineDataIndexer($this->getManagerRegistry(), $class);
        $this->assertSame(self::FIXTURES . 'Tree', $dataIndexer->getClass());
    }

    protected function getManagerRegistry()
    {
        $managerRegistry = $this->createMock(ManagerRegistry::class);
        $managerRegistry->expects($this->any())
            ->method('getManagerForClass')
            ->will($this->returnValue($this->em));

        return $managerRegistry;
    }

    protected function getMockAnnotatedConfig()
    {
        $config = $this->createMock(Configuration::class);
        $config->expects($this->once())
            ->method('getProxyDir')
            ->will($this->returnValue(TESTS_TEMP_DIR));

        $config->expects($this->once())
            ->method('getProxyNamespace')
            ->will($this->returnValue('Proxy'));

        $config->expects($this->once())
            ->method('getAutoGenerateProxyClasses')
            ->will($this->returnValue(true));

        $config->expects($this->once())
            ->method('getClassMetadataFactoryName')
            ->will($this->returnValue('Doctrine\\ORM\\Mapping\\ClassMetadataFactory'));

        $config->expects($this->any())
            ->method('getQuoteStrategy')
            ->will($this->returnValue(new \Doctrine\ORM\Mapping\DefaultQuoteStrategy()));


        $reader = new \Doctrine\Common\Annotations\AnnotationReader();
        $reader = new \Doctrine\Common\Annotations\CachedReader($reader, new \Doctrine\Common\Cache\ArrayCache());

        $config->expects($this->any())
            ->method('getMetadataDriverImpl')
            ->will($this->returnValue(new AnnotationDriver($reader, __DIR__)));

        $config->expects($this->any())
            ->method('getDefaultRepositoryClassName')
            ->will($this->returnValue('Doctrine\\ORM\\EntityRepository'));

        $config->expects($this->any())
            ->method('getRepositoryFactory')
            ->will($this->returnValue(new DefaultRepositoryFactory()));

        return $config;
    }
}

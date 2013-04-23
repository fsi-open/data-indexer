<?php

/**
 * (c) Fabryka Stron Internetowych sp. z o.o <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FSi\Component\DataIndexer\Tests;

use FSi\Component\DataIndexer\DoctrineDataIndexer;
use FSi\Component\DataIndexer\Tests\Fixtures\Category;
use FSi\Component\DataIndexer\Tests\Fixtures\News;
use FSi\Component\DataIndexer\Tests\Fixtures\Post;

class DoctrineDataIndexerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @expectedException \FSi\Component\DataIndexer\Exception\InvalidArgumentException
     */
    public function testDataIndexerWithInvalidClass()
    {
        $managerRegistry = $this->getMock("Doctrine\\Common\\Persistence\\ManagerRegistry");
        $managerRegistry->expects($this->any())
            ->method('getManagerForClass')
            ->will($this->returnValue(null));

        $class = "\\FSi\\Component\\DataIndexer\\DataIndexer";

        $dataIndexer = new DoctrineDataIndexer($managerRegistry, $class);
    }

    public function testGetIndexWithSimpleKey()
    {
        $class = "FSi\\Component\\DataIndexer\\Tests\\Fixtures\\News";
        $dataIndexer = new DoctrineDataIndexer($this->getManagerRegistry(), $class);

        $news = new News("foo");

        $this->assertSame($dataIndexer->getIndex($news), "foo");
    }

    public function testGetIndexWithCompositeKey()
    {
        $class = "FSi\\Component\\DataIndexer\\Tests\\Fixtures\\Post";
        $dataIndexer = new DoctrineDataIndexer($this->getManagerRegistry(), $class);

        $news = new Post("foo", "bar");

        $this->assertSame($dataIndexer->getIndex($news), "foo" . $dataIndexer->getSeparator() . "bar");
    }

    /**
     * @expectedException \FSi\Component\DataIndexer\Exception\RuntimeException
     */
    public function testGetIndexForClassWithoutIdentifiersInClassMetadata()
    {
        $class = "FSi\\Component\\DataIndexer\\Tests\\Fixtures\\Category";
        $dataIndexer = new DoctrineDataIndexer($this->getManagerRegistry(), $class);

        $news = new Category("foo", "bar");

        $dataIndexer->getIndex($news);
    }

    public function testGetDataWithSimpleKey()
    {
        $class = "FSi\\Component\\DataIndexer\\Tests\\Fixtures\\News";
        $dataIndexer = new DoctrineDataIndexer($this->getManagerRegistry(), $class);

        $news = $dataIndexer->getData("foo");

        $this->assertSame($news->getId(), "foo");
    }

    public function testGetDataWithCompositeKey()
    {
        $class = "FSi\\Component\\DataIndexer\\Tests\\Fixtures\\Post";
        $dataIndexer = new DoctrineDataIndexer($this->getManagerRegistry(), $class);

        $post = $dataIndexer->getData("foo|bar");

        $this->assertSame($post->getIdFirstPart(), "foo");
        $this->assertSame($post->getIdSecondPart(), "bar");
    }

    /**
     * @expectedException \FSi\Component\DataIndexer\Exception\RuntimeException
     */
    public function testGetDataWithCompositeKeyAndSeparatorInID()
    {
        $class = "FSi\\Component\\DataIndexer\\Tests\\Fixtures\\Post";
        $dataIndexer = new DoctrineDataIndexer($this->getManagerRegistry(), $class);

        $dataIndexer->getData("foo||bar");
    }

    public function testGetDataSliceWithSimpleKey()
    {
        $class = "FSi\\Component\\DataIndexer\\Tests\\Fixtures\\News";
        $dataIndexer = new DoctrineDataIndexer($this->getManagerRegistry(), $class);

        $news = $dataIndexer->getDataSlice(array("foo", "bar"));

        $this->assertSame(array(
            $news[0]->getId(),
            $news[1]->getId()
        ), array("foo", "bar"));
    }

    public function testGetDataSliceWithCompositeKey()
    {
        $class = "FSi\\Component\\DataIndexer\\Tests\\Fixtures\\Post";
        $dataIndexer = new DoctrineDataIndexer($this->getManagerRegistry(), $class);

        $news = $dataIndexer->getDataSlice(array("foo|foo1", "bar|bar1"));

        $this->assertSame(array(
            $news[0]->getIdFirstPart() . '|' . $news[0]->getIdSecondPart(),
            $news[1]->getIdFirstPart() . '|' . $news[1]->getIdSecondPart(),
        ), array("foo|foo1", "bar|bar1"));
    }

    protected function getManagerRegistry()
    {
        $self = $this;

        $managerRegistry = $this->getMock("Doctrine\\Common\\Persistence\\ManagerRegistry");
        $managerRegistry->expects($this->any())
            ->method('getManagerForClass')
            ->will($this->returnCallback(function() use ($self){

                $manager = $self->getMock("Doctrine\\Common\\Persistence\\ObjectManager");
                $manager->expects($self->any())
                    ->method('getMetadataFactory')
                    ->will($self->returnCallback(function() use ($self) {
                        $metadataFactory = $self->getMock("Doctrine\\Common\\Persistence\\Mapping\\ClassMetadataFactory");

                        $metadataFactory->expects($self->any())
                            ->method('getMetadataFor')
                            ->will($self->returnCallback(function($class) use ($self) {
                                switch ($class) {
                                    case "FSi\\Component\\DataIndexer\\Tests\\Fixtures\\News" :
                                        $metadata = $self->getMock('Doctrine\\Common\\Persistence\\Mapping\\ClassMetadata');
                                        $metadata->expects($self->any())
                                            ->method('getIdentifierFieldNames')
                                            ->will($self->returnValue(array(
                                                'id'
                                            )));
                                        break;
                                    case "FSi\\Component\\DataIndexer\\Tests\\Fixtures\\Post":
                                        $metadata = $self->getMock('Doctrine\\Common\\Persistence\\Mapping\\ClassMetadata');
                                        $metadata->expects($self->any())
                                            ->method('getIdentifierFieldNames')
                                            ->will($self->returnValue(array(
                                                'id_first_part',
                                                'id_second_part'
                                            )));
                                        break;
                                    case "FSi\\Component\\DataIndexer\\Tests\\Fixtures\\Category":
                                        $metadata = $self->getMock('Doctrine\\Common\\Persistence\\Mapping\\ClassMetadata');
                                        $metadata->expects($self->any())
                                            ->method('getIdentifierFieldNames')
                                            ->will($self->returnValue(array()));
                                        break;
                                }

                                return $metadata;
                            }));

                        return $metadataFactory;
                    }));

                $manager->expects($self->any())
                    ->method('getRepository')
                    ->will($self->returnCallback(function() use ($self) {
                        $repository = $self->getMock("Doctrine\\Common\\Persistence\\ObjectRepository");

                        $repository->expects($self->any())
                            ->method('findOneBy')
                            ->will($self->returnCallback(function($criteria) use ($self) {
                                if ($criteria == array('id' => "foo")) {
                                    return new News("foo");
                                }
                                if ($criteria == array('id_first_part' => "foo", "id_second_part" => "bar")) {
                                    return new Post("foo", "bar");
                                }
                            }));

                        $repository->expects($self->any())
                            ->method('findBy')
                            ->will($self->returnCallback(function($criteria) use ($self) {
                                if ($criteria == array('id' => array("foo", "bar"))) {
                                    return array(
                                        new News("foo"),
                                        new News("bar")
                                    );
                                }

                                if ($criteria == array('id_first_part' => array('foo', 'bar'), 'id_second_part' => array('foo1', 'bar1'))) {
                                    return array(
                                        new Post("foo", "foo1"),
                                        new Post("bar", "bar1")
                                    );
                                }
                            }));

                        return $repository;
                    }));

                return $manager;
            }));

        return $managerRegistry;
    }
}
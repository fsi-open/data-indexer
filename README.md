# FSi DataIndexer Component #

This component is created to provide one simple object indexing strategy for FSi ``DataSource`` and ``DataGrid`` components.

## Installation ##

Add ``fsi/data-indexer`` to composer.json

```
{
    ...

    "require": {
        "fsi/data-indexer" : "^1.0@dev",
    }

    ...
}
```

## Usage ##

```php
$dataIndexer = new DoctrineDataIndexer($this->getDoctrine(), "DemoBundle:News");
$news = News("this_is_id");

$index = $dataIndexer->getIndex($news);
// value in $index "this_is_id"

$entity = $dataIndexer->getData($index);
// $entity value is a News object with id "this_is_id"

```

*DoctrineDataIndexer* handle single and composite keys

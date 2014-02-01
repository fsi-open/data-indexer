<?php

/**
 * (c) FSi sp. z o.o <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FSi\Component\DataIndexer\Tests\Fixtures;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 */
class Post
{
    /**
     * @ORM\Column(type="string")
     * @ORM\Id
     */
    protected $id_first_part;

    /**
     * @ORM\Column(type="string")
     * @ORM\Id
     */
    protected $id_second_part;

    public function __construct($id_first_part, $id_second_part)
    {
        $this->id_first_part = $id_first_part;
        $this->id_second_part = $id_second_part;
    }

    public function getIdFirstPart()
    {
        return $this->id_first_part;
    }

    public function getIdSecondPart()
    {
        return $this->id_second_part;
    }
}

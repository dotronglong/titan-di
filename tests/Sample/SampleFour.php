<?php namespace Titan\Tests\DI\Sample;

class SampleFour
{
    private $attr1;

    /**
     * SampleOne constructor.
     * @param $attr1
     * @param $attr2
     */
    public function __construct(array $attr1)
    {
        $this->attr1 = $attr1;
    }

    /**
     * @return mixed
     */
    public function getAttr1()
    {
        return $this->attr1;
    }

    /**
     * @param mixed $attr1
     */
    public function setAttr1($attr1)
    {
        $this->attr1 = $attr1;
    }
}

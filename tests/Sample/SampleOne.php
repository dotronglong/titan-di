<?php namespace Titan\Tests\DI\Sample;

class SampleOne
{
    private $attr1;
    private $attr2;
    private $sampleTwo;

    /**
     * SampleOne constructor.
     * @param $attr1
     * @param $attr2
     */
    public function __construct($attr1, SampleTwo $sampleTwo, $attr2 = 10)
    {
        $this->attr1     = $attr1;
        $this->attr2     = $attr2;
        $this->sampleTwo = $sampleTwo;
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

    /**
     * @return mixed
     */
    public function getAttr2()
    {
        return $this->attr2;
    }

    /**
     * @param mixed $attr2
     */
    public function setAttr2($attr2)
    {
        $this->attr2 = $attr2;
    }

    /**
     * @return mixed
     */
    public function getSampleTwo()
    {
        return $this->sampleTwo;
    }

    /**
     * @param mixed $sampleTwo
     */
    public function setSampleTwo($sampleTwo)
    {
        $this->sampleTwo = $sampleTwo;
    }
}

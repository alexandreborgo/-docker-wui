<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Container
 *
 * @ORM\Table(name="container")
 * @ORM\Entity(repositoryClass="AppBundle\Repository\ContainerRepository")
 */
class Container
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var string
     *
     * @ORM\Column(name="containerId", type="string", length=255)
     */
    private $containerId;

    /**
     * @var int
     *
     * @ORM\Column(name="statut", type="integer")
     */
    private $statut;

    /**
     * @var int
     *
     * @ORM\Column(name="hostp", type="integer")
     */
    private $hostp;

    /**
     * @var int
     *
     * @ORM\Column(name="guestp", type="integer")
     */
    private $guestp;

    /**
     * @var string
     *
     * @ORM\Column(name="name", type="string", length=255, unique=true)
     */
    private $name;

    /**
     * @var string
     *
     * @ORM\Column(name="isrunning", type="string", length=255)
     */
    private $isrunning;

    /**
     * @var int
     *
     * @ORM\Column(name="owner", type="integer")
     */
    private $owner;

    /**
     * @ORM\ManyToOne(targetEntity="Image", inversedBy="containers")
     * @ORM\JoinColumn(name="image", referencedColumnName="id")
     *
     */
    private $image;

    /**
     * @var string
     *
     * @ORM\Column(name="comment", type="string", length=1000)
     */
    private $comment;


    /**
     * Get id
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Get statut
     *
     * @return int
     */
    public function getStatut()
    {
        return $this->statut;
    }

    /**
     * Set statut
     *
     * @param integer $statut
     *
     * @return Container
     */
    public function setStatut($statut)
    {
        $this->statut = $statut;

        return $this;
    }

    /**
     * Get hostp
     *
     * @return int
     */
    public function getHostP()
    {
        return $this->hostp;
    }

    /**
     * Set hostp
     *
     * @param integer $hostp
     *
     * @return Container
     */
    public function setHostP($hostp)
    {
        $this->hostp = $hostp;

        return $this;
    }

    /**
     * Get guestp
     *
     * @return int
     */
    public function getGuestP()
    {
        return $this->guestp;
    }

    /**
     * Set guestp
     *
     * @param integer $guestp
     *
     * @return Container
     */
    public function setGuestP($guestp)
    {
        $this->guestp = $guestp;

        return $this;
    }

    /**
     * Set containerId
     *
     * @param integer $containerId
     *
     * @return Container
     */
    public function setContainerId($containerId)
    {
        $this->containerId = $containerId;

        return $this;
    }

    /**
     * Get containerId
     *
     * @return int
     */
    public function getContainerId()
    {
        return $this->containerId;
    }

    /**
     * Set name
     *
     * @param string $name
     *
     * @return Container
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get name
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set isrunning
     *
     * @param string $isrunning
     *
     * @return Container
     */
    public function setIsrunning($isrunning)
    {
        $this->isrunning = $isrunning;

        return $this;
    }

    /**
     * Get isrunning
     *
     * @return string
     */
    public function getIsrunning()
    {
        return $this->isrunning;
    }

    /**
     * Set owner
     *
     * @param integer $owner
     *
     * @return Container
     */
    public function setOwner($owner)
    {
        $this->owner = $owner;

        return $this;
    }

    /**
     * Get owner
     *
     * @return int
     */
    public function getOwner()
    {
        return $this->owner;
    }

    /**
     * Set image
     *
     * @param integer $image
     *
     * @return Container
     */
    public function setImage($image)
    {
        $this->image = $image;

        return $this;
    }

    /**
     * Get image
     *
     * @return integer
     */
    public function getImage()
    {
        return $this->image;
    }

    /**
     * Set comment
     *
     * @param string $comment
     *
     * @return Container
     */
    public function setComment($comment)
    {
        $this->comment = $comment;

        return $this;
    }

    /**
     * Get comment
     *
     * @return string
     */
    public function getComment()
    {
        return $this->comment;
    }
}


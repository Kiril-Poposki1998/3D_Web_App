<?php

namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\HasLifecycleCallbacks
 */
class Purchase
{
    use Timestamp;

    /**
     * @ORM\Id
     * @ORM\ManyToOne(targetEntity="User", inversedBy="purchases")
     * @ORM\JoinColumn(name="user_id", referencedColumnName="id", nullable=false)
     */
    protected $user;

    /**
     * @ORM\Id()
     * @ORM\ManyToOne(targetEntity="Model", inversedBy="purchases")
     * @ORM\JoinColumn(name="model_id", referencedColumnName="id", nullable=false)
     */
    protected $model;

    /**
     * @ORM\Column(type="float", nullable="true")
     */
    private $rating = 0;

    /**
     * @param $user
     * @param $model
     */
    public function __construct($user, $model)
    {
        $this->user = $user;
        $this->model = $model;
        $this->rating = 0;
    }

    /**
     * @return float
     */
    public function getRating(): float
    {
        return $this->rating;
    }

    /**
     * @param float $rating
     */
    public function setRating(float $rating): void
    {
        $this->rating = $rating;
    }

    /**
     * @return mixed
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * @param mixed $user
     */
    public function setUser($user): void
    {
        $this->user = $user;
    }

    /**
     * @return mixed
     */
    public function getModel()
    {
        return $this->model;
    }

    /**
     * @param mixed $model
     */
    public function setModel($model): void
    {
        $this->model = $model;
    }
}
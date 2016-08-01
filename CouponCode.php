<?php
/**
 *
 * Copyright (C) 2016 Bashe Development
 *
 * All rights reserved.
 *
 * Created on : Aug 01, 2016, 23:26
 * Author     : Joe Bashe <joe@bashedev.com>
 *
 */

namespace bashedev\CouponCodeGenerator;


/**
 * CouponCode
 *
 * @author Joe Bashe <joe@bashedev.com>
 */
class CouponCode
{

    /**
     * @var string
     */
    private $code;

    /**
     * @var float
     */
    private $discount;

    /**
     * @var string
     */
    private $discountTarget;

    /**
     * @var mixed
     */
    private $customerId = null;

    /**
     *
     * @return string
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * Get discount
     *
     * @return float
     */
    public function getDiscount()
    {
        return $this->discount;
    }

    /**
     * Get discount_target
     *
     * @return string
     */
    public function getDiscountTarget()
    {
        return $this->discountTarget;
    }

    public function getCustomerId()
    {
        return $this->customerId;
    }

    /**
     *
     * @param string $code
     *
     * @return $this
     */
    public function setCode($code)
    {
        $this->code = $code;

        return $this;
    }

    /**
     *
     * @param float $discount
     *
     * @return $this
     */
    public function setDiscount($discount)
    {
        $this->discount = $discount;

        return $this;
    }

    /**
     *
     * @param string $discountTarget
     *
     * @return $this
     */
    public function setDiscountTarget($discountTarget)
    {
        $this->discountTarget = $discountTarget;

        return $this;
    }

    /**
     *
     * @param mixed $customerId
     *
     * @return $this
     */
    public function setCustomerId($customerId)
    {
        $this->customerId = $customerId;

        return $this;
    }

}

#!/usr/bin/env php
<?php

use bashedev\CouponCodeGenerator\CouponCode;

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
 * CouponCodeGenerator
 *
 * @author Joe Bashe <joe@bashedev.com>
 */

class CouponCodeGenerator
{

    const COUPON_CODE_LENGTH = 9;  // not including spacer
    const COUPON_CODE_GROUP_SIZE = 3;
    const COUPON_CODE_SPACER = '-';
    /**
     *
     * @var array All possible characters to be used in each position of access code
     */
    private $characterSet = [];

    /**
     *
     * @var int Variable to store the character set length
     */
    private $characterSetLength = 0;

    /**
     *
     * @var array Existing invitation Codes in database
     */
    private $existingCodes = [];

    /**
     *
     * @var int max numeric (ordinal) value of digit: ($this->characterSetLength - 1)
     */
    private $maxDigitValue = 0;

    /**
     *
     * @var int The maximum increment that should be used based upon possible quantity of permutations and quantity of
     *          codes desired.
     */
    private $maxIncrement = 0;

    /**
     *
     * @var int The maximum exponent of the increment in terms of base {$this->characterSetLength}
     */
    private $maxExponent = 0;

    /**
     *
     * @var string Output type (write "file" or "db")
     */
    private $output = 'file';

    /**
     *
     * @var array Keeps track of current position in character set arrays
     */
    private $positionCounter = [];

    /**
     *
     * @var int Quantity of digits in code
     */
    private $qtyDigits = 0;

    /**
     *
     * @var int Quantity of possible permutations
     */
    private $qtyPermutations = 0;

    /**
     *
     * @var int Quantity of codes to generate
     */
    private $qtyToGenerate = 0;

    /**
     *
     * @var array
     */
    private $randomCharacterSets = [];

    /**
     * Top level function to generate invite codes
     *
     * @param int    $qtyToGenerate Quantity of invite codes to generate
     * @param string $output Output type to generate (write file or db)
     * @param string $filename
     * @param float  $discount Discount percentage expressed as decimal, e.g. 0.50 for 50%
     */
    public function generateCouponCodes($qtyToGenerate, $output = 'file', $filename = 'new-codes.csv', $discount = null)
    {
        $this->output = $output;
        $this->qtyToGenerate = $qtyToGenerate;
        $this->init();

        if ($output === 'file') {
            $qtyCodes = $this->createInvitations($discount, $filename);
        } else {
            $qtyCodes = $this->createInvitations($discount);
        }

        $sparsity = (1 - ($qtyCodes / $this->qtyPermutations)) * 100;
        printf(
            "Generated %s access codes from a total set of %s (%s%% sparsity)\n",
            number_format($qtyCodes),
            number_format($this->qtyPermutations),
            number_format($sparsity, 6)
        );
    }

    /**
     *
     * @param array $increment
     *
     * @throws \Exception
     */
    private function addIncrement($increment)
    {
        $carry = false;
        foreach ($increment as $exponent => $multiplier) {
            if (array_key_exists($exponent, $this->positionCounter)) {
                $currentValue = $this->positionCounter[$exponent];
            } else {
                throw new \Exception("Exponent ($exponent) doesn't exist: \n".print_r($this->positionCounter, true));
            }
            if ($carry === false) {
                $sum = $currentValue + $multiplier;
            } else {
                $sum = $currentValue + $carry + $multiplier;
                $carry = false;
            }

            if ($sum < $this->characterSetLength) {
                // fits in bounds, continue adding the next digit
                $this->positionCounter[$exponent] = $sum;
            } else { // carry
                $sum -= $this->characterSetLength;
                $carry = 1;
                $this->positionCounter[$exponent] = $sum;
            }
        }
    }

    /**
     *
     * @param int $number
     *
     * @return array
     */
    private function baseConvert($number)
    {
        // now descend thru powers until number is converted
        $power = $this->maxExponent;
        $converted = array_fill(0, $power, 0);
        $carry = $number;
        while ($carry > 0 && $power >= 0) {
            $multiplier = pow($this->characterSetLength, $power);
            for ($ordinal = $this->maxDigitValue; $ordinal > 0; $ordinal--) {
                $difference = $carry - ($ordinal * $multiplier);
                if ($difference < 0) { // took too much, try a lower ordinal
                    continue;
                }
                // took away something, good. mark it and set the carry to the difference, then continue on to the next
                // power
                $converted[$power] = $ordinal;
                $carry = $difference;
                break;
            }
            $power--;
        }

        return $converted;
    }

    /**
     *
     * @param string $code
     * @param float  $discount
     *
     * @return CouponCode
     */
    private function constructCouponCode($code, $discount)
    {
        $couponCode = new CouponCode();
        $couponCode->setCode($code);
        $couponCode->setCustomerId(0);  // Customer ID = 0 => (not set)
        $couponCode->setDiscount($discount);
        $couponCode->setDiscountTarget('RETAIL');

        return $couponCode;
    }

    /**
     *
     * @param float  $discount
     * @param string $filename
     *
     * @return int
     */
    private function createInvitations($discount = null, $filename = null)
    {
        $codes = [];
        $qtyCodes = 0;
        while ($qtyCodes < $this->qtyToGenerate) {
            $code = $this->getCode();
            if (in_array($code, $this->existingCodes)) { // skip existing code
                continue;
            }
            if ($this->output === 'file') { // generate csv file
                $codes[] = $code;
            } else { // save to db
                // save to db, e.g. $this->entityManager->persist($this->constructCouponCode($code, $discount));
            }
            $qtyCodes++;
        }
        if ($this->output === 'file') {
            $this->writeFile($codes, $filename);
            $this->existingCodes = array_merge($this->existingCodes, $codes);
        } else { // write db
            // write db, e.g. $this->entityManager->flush();
        }

        return $qtyCodes;
    }

    /**
     * Creates the randomized character sets to be used to generate permutations
     */
    private function createRandomCharacterSets()
    {
        // build an array of randomized character arrays
        for ($i = 0; $i < self::COUPON_CODE_LENGTH; $i++) {
            shuffle($this->characterSet);
            $this->randomCharacterSets[] = $this->characterSet;
        }
        shuffle($this->randomCharacterSets);
    }

    /**
     * @param string $permutation
     *
     * @return string
     */
    private function formatPermutation($permutation)
    {
        return implode(self::COUPON_CODE_SPACER, str_split($permutation, self::COUPON_CODE_GROUP_SIZE));
    }

    /**
     * Increment the positional counter (i.e. $this->pos) and get a single code.
     *
     * @return string Coupon code
     */
    private function getCode()
    {
        $this->incrementPermutationCounterRandomly();

        $permutation = $this->getCurrentPermutation();

        return $this->formatPermutation($permutation);
    }

    /**
     * Builds an invitation code out of the current permutation
     *
     * @return string Gets the current permutation (a.k.a code) by iterating through the $this->pos position-tracking
     *                array over the $this->randomCharacterSets array
     */
    private function getCurrentPermutation()
    {
        $code = '';
        foreach ($this->positionCounter as $col => $row) {
            $code = $this->randomCharacterSets[$col][$row].$code;
        }

        return $code;
    }

    /**
     * Increment the permutation counter by random quantity
     *
     * @throws \Exception
     */
    private function incrementPermutationCounterRandomly()
    {
        $increment = rand(1, $this->maxIncrement);
        $convertedIncrement = $this->baseConvert($increment);
        $this->addIncrement($convertedIncrement);
    }

    /**
     * Initialize class members
     */
    private function init()
    {
        // define alphanumeric character set, minus easily confusable letters and numbers (0, 1, I, O)
        $this->characterSet = array_merge(range('A', 'H'), range('J', 'N'), range('P', 'Z'), range(2, 9));
        $this->characterSetLength = count($this->characterSet);
        $this->maxDigitValue = $this->characterSetLength - 1;
        $this->qtyDigits = self::COUPON_CODE_LENGTH;
        $this->qtyPermutations = pow($this->characterSetLength, $this->qtyDigits);
        $this->maxIncrement = round($this->qtyPermutations / $this->qtyToGenerate);
        $this->positionCounter = array_fill(0, $this->qtyDigits, 0);
        // find max power
        while (pow($this->characterSetLength, $this->maxExponent) < $this->maxIncrement) {
            $this->maxExponent++;
        }
        $this->createRandomCharacterSets();
        $this->loadExistingCodes();
    }

    /**
     * Load existing codes into class variable
     */
    private function loadExistingCodes()
    {
        // write code to load any existing coupon codes to ensure no duplicates are created, e.g.:

        //$existingCoupons = $this->entityManager->getRepository('AppBundle:CouponCode')->findAll();
        //$this->existingCodes = array_map(
        //    function (CouponCode $value) {
        //        return $value->getCode();
        //    },
        //    $existingCoupons
        //);

        $filename = 'existing-codes.csv';
        if (file_exists($filename)) {
            $this->existingCodes = explode("\n", file_get_contents($filename));
        }
    }

    /**
     * Shuffles the array of codes and writes them to a CSV file (one per line).
     *
     * @param array  $codes
     * @param string $filename
     */
    private function writeFile(array $codes, $filename)
    {
        shuffle($codes);
        file_put_contents(
            $filename,
            array_reduce(
                $codes,
                function ($carry, $item) {
                    return $carry."$item\n";
                }
            )
        );
    }


}

$ccg = new CouponCodeGenerator();
$filename = 'coupon_codes/codes.csv';
$ccg->generateCouponCodes(1000, 'file', $filename);

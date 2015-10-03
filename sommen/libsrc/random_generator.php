<?php

if (!defined("EDUCATION_WEBSITE"))
{
   die("Don't waste your time trying to access this file");
}




/*
// The new cross-platform portable and reproducable random generator is
// derived from the randomc package by Agner Fog
//
//   http://www.agner.org/random/
*/
/**************Derived from MOTHER.CPP ****************** AgF 2007-08-01 *
*  'Mother-of-All' random number generator                               *
*                                                                        *
*  This is a multiply-with-carry type of random number generator         *
*  invented by George Marsaglia.  The algorithm is:                      *
*  S = 2111111111*X[n-4] + 1492*X[n-3] + 1776*X[n-2] + 5115*X[n-1] + C   *
*  X[n] = S modulo 2^32                                                  *
*  C = floor(S / 2^32)                                                   *
*                                                                        *
*  Note:                                                                 *
*  This implementation uses 64-bit integers for intermediate             *
*  calculations. Works only on compilers that support 64-bit integers.   *
*                                                                        *
*  1999 - 2007 A. Fog.                                                  *
* GNU General Public License www.gnu.org/copyleft/gpl.html               *
*************************************************************************/
class RandomGen
{
    protected $rand_store_0 = 0;
    protected $rand_store_1 = 0;
    protected $rand_store_2 = 0;
    protected $rand_store_3 = 0;
    protected $rand_store_4 = 0;
    protected $rand_init_done = 0;

    public function __construct($seed)
    {
        $this->rand_init($seed);
    }

    public function __destruct()
    {
    }

    // this function initializes the random number generator:
    public function rand_init($seed)
    {
    /*
        $s = gmp_init($seed);

        // make random numbers and put them into the buffer
        $s = gmp_and(gmp_sub(gmp_mul($s,  29943829), 1), "0xFFFFFFFF");
        $rand_store_0 = $s;
        $s = gmp_and(gmp_sub(gmp_mul($s,  29943829), 1), "0xFFFFFFFF");
        $rand_store_1 = $s;
        $s = gmp_and(gmp_sub(gmp_mul($s,  29943829), 1), "0xFFFFFFFF");
        $rand_store_2 = $s;
        $s = gmp_and(gmp_sub(gmp_mul($s,  29943829), 1), "0xFFFFFFFF");
        $rand_store_3 = $s;
        $s = gmp_and(gmp_sub(gmp_mul($s,  29943829), 1), "0xFFFFFFFF");
        $rand_store_4 = $s;

        // randomize some more
        for ($i = 0; $i < 19; $i++)
        {
            rand32();
        }
        $rand_init_done = 1;
    */
        $s = $seed;

        bcscale(0);
        // make random numbers and put them into the buffer
        $s = bcmod(bcsub(bcmul($s,  29943829), 1), "4294967296");
        $this->rand_store_0 = $s;
        $s = bcmod(bcsub(bcmul($s,  29943829), 1), "4294967296");
        $this->rand_store_1 = $s;
        $s = bcmod(bcsub(bcmul($s,  29943829), 1), "4294967296");
        $this->rand_store_2 = $s;
        $s = bcmod(bcsub(bcmul($s,  29943829), 1), "4294967296");
        $this->rand_store_3 = $s;
        $s = bcmod(bcsub(bcmul($s,  29943829), 1), "4294967296");
        $this->rand_store_4 = $s;

        // randomize some more
        for ($i = 0; $i < 19; $i++)
        {
            $this->rand32();
        }
        $this->rand_init_done = 1;
    }

    // Output random bits: this code is an implementation of one of Marsaglia's random generators
    public function rand32()
    {
    /*
        $sum = gmp_add(gmp_add(gmp_add(gmp_add(gmp_mul(2111111111, $rand_store_3),
                                              gmp_mul(1492, $rand_store_2)),
                                      gmp_mul(1776, $rand_store_1)),
                              gmp_mul(5115, $rand_store_0)),
                      $rand_store_4);
        $rand_store_3 = $rand_store_2;
        $rand_store_2 = $rand_store_1;
        $rand_store_1 = $rand_store_0;
        $rand_store_4 = gmp_and(gmp_div_q($sum, "0x100000000"), "0xFFFFFFFF");     // Carry
        $rand_store_0 = gmp_and($sum, "0xFFFFFFFF");             // Low 32 bits of sum
        return $rand_store_0;
    */
        bcscale(0);
        $sum = bcadd(bcadd(bcadd(bcadd(bcmul(2111111111, $this->rand_store_3),
                                       bcmul(1492, $this->rand_store_2)),
                                 bcmul(1776, $this->rand_store_1)),
                           bcmul(5115, $this->rand_store_0)),
                     $this->rand_store_4);
        $this->rand_store_3 = $this->rand_store_2;
        $this->rand_store_2 = $this->rand_store_1;
        $this->rand_store_1 = $this->rand_store_0;
        $this->rand_store_4 = bcmod(bcdiv($sum, "4294967296"), "4294967296");     // Carry
        $this->rand_store_0 = bcmod($sum, "4294967296");             // Low 32 bits of sum
        return $this->rand_store_0;
    }



    // returns a random number between 0 and 1: flat distribution
    public function frand()
    {
    /*
        return ((float)gmp_strval(gmp_and(rand32(), 0x7FFFFFFF))) / 2147483648.0;
    */
        $v = $this->rand32();
        bcscale(17);
        $v = bcdiv($v, "4294967296");
        return ((float)$v);
    }
    
    // returns a random number between 0 and 1: distribution favors higher values; 1st attempt at close-to-triangle distribution
    public function frand_triangle()
    {
        $v = $this->frand();
        //     =POWER(LOG(1+1000*B1),2)/9.00260465329917
        $v = log10(1.0 + 1000.0 * $v);
        $v = ($v * $v) / 9.00260465329917; // 9.00260465329917 == power(log10(1+1000), 2)
        return $v;
    }
}





?>


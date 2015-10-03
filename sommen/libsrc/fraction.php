<?php

if (!defined("EDUCATION_WEBSITE"))
{
   die("Don't waste your time trying to access this file");
}



/// <summary>
/// <para>Code according to info found here: http://mathforum.org/library/drmath/view/51886.html</para>
/// 
/// <para>
/// Date: 06/29/98 at 13:12:44</para>
/// <para>
/// From: Doctor Peterson</para>
/// <para>
/// Subject: Re: Decimal To Fraction Conversion</para>
/// 
/// <para>
/// The algorithm I am about to show you has an interesting history. I 
/// recently had a discussion with a teacher in England who had a 
/// challenging problem he had given his students, and wanted to know what 
/// others would do to solve it. The problem was to find the fraction 
/// whose decimal value he gave them, which is essentially identical to 
/// your problem! I wasn't familiar with a standard way to do it, but 
/// solved it by a vaguely remembered Diophantine method. Then, my 
/// curiosity piqued, and I searched the Web for information on the 
/// problem and didn't find it mentioned in terms of finding the fraction 
/// for an actual decimal, but as a way to approximate an irrational by a 
/// fraction, where the continued fraction method was used. </para>
/// 
/// <para>
/// I wrote to the teacher, and he responded with a method a student of 
/// his had come up with, which uses what amounts to a binary search 
/// technique. I recognized that this produced the same sequence of 
/// approximations that continued fractions gave, and was able to 
/// determine that it is really equivalent, and that it is known to some 
/// mathematicians (or at least math historians). </para>
/// 
/// <para>
/// After your request made me realize that this other method would be 
/// easier to program, I thought of an addition to make it more efficient, 
/// which to my knowledge is entirely new. So we're either on the cutting 
/// edge of computer technology or reinventing the wheel, I'm not sure 
/// which!</para>
/// 
/// <para>
/// Here's the method, with a partial explanation for how it works:</para>
/// 
/// <para>
/// We want to approximate a value m (given as a decimal) between 0 and 1, 
/// by a fraction Y/X. Think of fractions as vectors (denominator, 
/// numerator), so that the slope of the vector is the value of the 
/// fraction. We are then looking for a lattice vector (X, Y) whose slope 
/// is as close as possible to m. This picture illustrates the goal, and 
/// shows that, given two vectors A and B on opposite sides of the desired 
/// slope, their sum A + B = C is a new vector whose slope is between the 
/// two, allowing us to narrow our search:</para>
/// 
/// <code>
/// num
/// ^
/// |
/// +  +  +  +  +  +  +  +  +  +  +
/// |
/// +  +  +  +  +  +  +  +  +  +  +
/// |                                  slope m=0.7
/// +  +  +  +  +  +  +  +  +  +  +   /
/// |                               /
/// +  +  +  +  +  +  +  +  +  +  D &lt;--- solution
/// |                           /
/// +  +  +  +  +  +  +  +  + /+  +
/// |                       /
/// +  +  +  +  +  +  +  C/ +  +  +
/// |                   /
/// +  +  +  +  +  + /+  +  +  +  +
/// |              /
/// +  +  +  +  B/ +  +  +  +  +  +
/// |          /
/// +  +  + /A  +  +  +  +  +  +  +
/// |     /
/// +  +/ +  +  +  +  +  +  +  +  +
/// | /
/// +--+--+--+--+--+--+--+--+--+--+--&gt; denom
/// </code>
/// 
/// <para>
/// Here we start knowing the goal is between A = (3,2) and B = (4,3), and 
/// formed a new vector C = A + B. We test the slope of C and find that 
/// the desired slope m is between A and C, so we continue the search 
/// between A and C. We add A and C to get a new vector D = A + 2*B, which 
/// in this case is exactly right and gives us the answer.</para>
/// 
/// <para>
/// Given the vectors A and B, with slope(A) &lt; m &lt; slope(B), 
/// we can find consecutive integers M and N such that 
/// slope(A + M*B) &lt; x &lt; slope(A + N*B) in this way:</para>
/// 
/// <para>
/// If A = (b, a) and B = (d, c), with a/b &lt; m &lt; c/d, solve</para>
/// 
/// <code>
///     a + x*c
///     ------- = m
///     b + x*d
/// </code>
/// 
/// <para>
/// to give</para>
/// 
/// <code>
///         b*m - a
///     x = -------
///         c - d*m
/// </code>
/// 
/// <para>
/// If this is an integer (or close enough to an integer to consider it 
/// so), then A + x*B is our answer. Otherwise, we round it down and up to 
/// get integer multipliers M and N respectively, from which new lower and 
/// upper bounds A' = A + M*B and B' = A + N*B can be obtained. Repeat the 
/// process until the slopes of the two vectors are close enough for the 
/// desired accuracy. The process can be started with vectors (0,1), with 
/// slope 0, and (1,1), with slope 1. Surprisingly, this process produces 
/// exactly what continued fractions produce, and therefore it will 
/// terminate at the desired fraction (in lowest terms, as far as I can 
/// tell) if there is one, or when it is correct within the accuracy of 
/// the original data.</para>
/// 
/// <para>
/// For example, for the slope 0.7 shown in the picture above, we get 
/// these approximations:</para>
/// 
/// <para>
/// Step 1: A = 0/1, B = 1/1 (a = 0, b = 1, c = 1, d = 1)</para>
/// 
/// <code>
///         1 * 0.7 - 0   0.7
///     x = ----------- = --- = 2.3333
///         1 - 1 * 0.7   0.3
/// 
///     M = 2: lower bound A' = (0 + 2*1) / (1 + 2*1) = 2 / 3
///     N = 3: upper bound B' = (0 + 3*1) / (1 + 3*1) = 3 / 4
/// </code>
/// 
/// <para>
/// Step 2: A = 2/3, B = 3/4 (a = 2, b = 3, c = 3, d = 4)</para>
/// 
/// <code>
///         3 * 0.7 - 2   0.1
///     x = ----------- = --- = 0.5
///         3 - 4 * 0.7   0.2
/// 
///     M = 0: lower bound A' = (2 + 0*3) / (3 + 0*4) = 2 / 3
///     N = 1: upper bound B' = (2 + 1*3) / (3 + 1*4) = 5 / 7
/// </code>
/// 
/// <para>
/// Step 3: A = 2/3, B = 5/7 (a = 2, b = 3, c = 5, d = 7)</para>
/// 
/// <code>
///         3 * 0.7 - 2   0.1
///     x = ----------- = --- = 1
///         5 - 7 * 0.7   0.1
/// 
///     N = 1: exact value A' = B' = (2 + 1*5) / (3 + 1*7) = 7 / 10
/// </code>
/// 
/// <para>
/// which of course is obviously right.</para>
/// 
/// <para>
/// In most cases you will never get an exact integer, because of rounding 
/// errors, but can stop when one of the two fractions is equal to the 
/// goal to the given accuracy.</para>
/// 
/// <para>
/// [...]Just to keep you up to date, I tried out my newly invented algorithm 
/// and realized it lacked one or two things. Specifically, to make it 
/// work right, you have to alternate directions, first adding A + N*B and 
/// then N*A + B. I tested my program for all fractions with up to three 
/// digits in numerator and denominator, then started playing with the 
/// problem that affects you, namely how to handle imprecision in the 
/// input. I haven't yet worked out the best way to allow for error, but 
/// here is my C++ function (a member function in a Fraction class 
/// implemented as { short num; short denom; } ) in case you need to go to 
/// this algorithm.
/// </para>
/// 
/// <para>[Edit [i_a]: tested a few stop criteria and precision settings;
/// found that you can easily allow the algorithm to use the full integer
/// value span: worst case iteration count was 21 - for very large prime
/// numbers in the denominator and a precision set at double.Epsilon.
/// Part of the code was stripped, then reinvented as I was working on a 
/// proof for this system. For one, the reason to 'flip' the A/B treatment
/// (i.e. the 'i&1' odd/even branch) is this: the factor N, which will
/// be applied to the vector addition A + N*B is (1) an integer number to
/// ensure the resulting vector (i.e. fraction) is rational, and (2) is
/// determined by calculating the difference in direction between A and B.
/// When the target vector direction is very close to A, the difference
/// in *direction* (sort of an 'angle') is tiny, resulting in a tiny N
/// value. Because the value is rounded down, A will not change. B will,
/// but the number of iterations necessary to arrive at the final result
/// increase significantly when the 'odd/even' processing is not included.
/// Basically, odd/even processing ensures that once every second iteration
/// there will be a major change in direction for any target vector M.]
/// </para>
/// 
/// <para>[Edit [i_a]: further testing finds the empirical maximum
/// precision to be ~ 1.0E-13, IFF you use the new high/low precision
/// checks (simpler, faster) in the code (old checks have been commented out).
/// Higher precision values cause the code to produce very huge fractions
/// which clearly show the effect of limited floating point accuracy.
/// Nevetheless, this is an impressive result.
/// 
/// I also changed the loop: no more odd/even processing but now we're
/// looking for the biggest effect (i.e. change in direction) during EVERY
/// iteration: see the new x1:x2 comparison in the code below.
/// This will lead to a further reduction in the maximum number of iterations
/// but I haven't checked that number now. Should be less than 21,
/// I hope. ;-) ]
/// </para>
/// </summary>
//
// comments above from my C# implementation for meGUI; ported here to PHP, where
// one MUST expect less accuracy (as we work with floats)
//
class Fraction extends DebugReporting
{
    public $num;
    public $denom;

    public function __construct($n, $d)
    {
        $this->num = $n;
        $this->denom = $d;
    }

    public static function toFract($val)
    {
        return self::toFractWithPrecision($val, 1.0E-13);
    }

    public static function toFractWithPrecision($val, $Precision)
    {
        if (DebugReporting::$dbg >= 2) printf("<p>Fraction: val = %s, precision = %s</p>\n", $val, $Precision);
            
        // find nearest fraction
        $intPart = (int)$val;
        $val -= (float)$intPart;
        $lowest_acceptable_denom = 1.0 / $Precision;

        $low = new Fraction(0, 1);           // "A" = 0/1 (a/b)
        $high = new Fraction(1, 1);          // "B" = 1/1 (c/d)
        $ans = $high;

        if (DebugReporting::$dbg >= 2) printf("<p>Fraction: val = %s, precision = %s, intpart = %s</p>\n", $val, $Precision, $intPart);
            
        for (;;)
        {
            //Debug.Assert(low.Val <= val);
            //Debug.Assert(high.Val >= val);

            //         b*m - a
            //     x = -------
            //         c - d*m
            $testLow = $low->denom * $val - $low->num;
            $testHigh = $high->num - $high->denom * $val;
            
            if (DebugReporting::$dbg >= 2) 
            {
                printf("<p>Fraction: testlow = %s (fraction: %d/%d), testhigh = %s (fraction: %d/%d) - %d</p>\n", 
                        $testLow, $low->num, $low->denom, $testHigh, $high->num, $high->denom, $lowest_acceptable_denom);
            }
            
            if ($testHigh <= $testLow && ($high->denom <= $lowest_acceptable_denom))
            {
                $ans = $high;
            }
            else if ($low->denom <= $lowest_acceptable_denom)
            {
                $ans = $low;
            }
            
            // test for match:
            // 
            // m - a/b < precision
            //
            // ==>
            //
            // b * m - a < b * precision
            //
            // which is happening here: check both the current A and B fractions.
            //if (testHigh < high.denom * Precision)
            if ($testHigh < $Precision) // [i_a] speed improvement; this is even better for irrational 'val'
            {
                break; // high is answer
            }
            //if (testLow < low.denom * Precision)
            if ($testLow < $Precision) // [i_a] speed improvement; this is even better for irrational 'val'
            {
                // low is answer
                $high = $low;
                break;
            }

            $x1 = $testHigh / $testLow;
            $x2 = $testLow / $testHigh;

            if (DebugReporting::$dbg >= 2) printf("<p>Fraction: x1 = %s, x2 = %s, fraction = %s/%s</p>\n", $x1, $x2, $high->num, $high->denom);
            
            // always choose the path where we find the largest change in direction:
            if ($x1 > $x2)
            {
                //double x1 = testHigh / testLow;
                // safety checks: are we going to be out of integer bounds?
                if (($x1 + 1.0) * $low->denom + $high->denom >= 2147483647.0)
                {
                    break;
                }

                $n = (int)$x1;    // lower bound for m
                //int m = n + 1;    // upper bound for m

                //     a + x*c
                //     ------- = m
                //     b + x*d
                $h_num = $n * $low->num + $high->num;
                $h_denom = $n * $low->denom + $high->denom;

                //ulong l_num = m * low.num + high.num;
                //ulong l_denom = m * low.denom + high.denom;
                $l_num = $h_num + $low->num;
                $l_denom = $h_denom + $low->denom;

                if (DebugReporting::$dbg >= 2) printf("<p>Fraction: x1 LT x2: n = %s, h: %s/%s, l: %s/%s</p>\n", $n, $h_num, $h_denom, $l_num, $l_denom);
                
                $low->num = $l_num;
                $low->denom = $l_denom;
                $high->num = $h_num;
                $high->denom = $h_denom;
            }
            else
            {
                //double x2 = testLow / testHigh;
                // safety checks: are we going to be out of integer bounds?
                if ($low->denom + ($x2 + 1.0) * $high->denom >= 2147483647.0)
                {
                    break;
                }

                $n = (int)$x2;    // lower bound for m
                //ulong m = n + 1;    // upper bound for m

                //     a + x*c
                //     ------- = m
                //     b + x*d
                $l_num = $low->num + $n * $high->num;
                $l_denom = $low->denom + $n * $high->denom;

                //ulong h_num = low.num + m * high.num;
                //ulong h_denom = low.denom + m * high.denom;
                $h_num = $l_num + $high->num;
                $h_denom = $l_denom + $high->denom;

                if (DebugReporting::$dbg >= 2) printf("<p>Fraction: x1 LT x2: n = %s, h: %s/%s, l: %s/%s</p>\n", $n, $h_num, $h_denom, $l_num, $l_denom);
                
                $high->num = $h_num;
                $high->denom = $h_denom;
                $low->num = $l_num;
                $low->denom = $l_denom;
            }
            //Debug.Assert(low.Val <= val);
            //Debug.Assert(high.Val >= val);
        }

        // $high->num += $high->denom * $intPart;
        $ans->num += $ans->denom * $intPart;

        if (DebugReporting::$dbg >= 2)
        {
            printf("<p>Fraction: DONE for %f at precision %f: high = %s/%s, intpart = %s\n", $val, $Precision, $high->num, $high->denom, $intPart);
            printf("answer = %s/%s</p>\n", $ans->num, $ans->denom);
        }
        
        return $ans; // $high;
    }

    public static function Test()
    {
        $vut = 0.1;
        $ret = Fraction::toFract($vut);
        if (!(abs($vut - $ret->Val()) < 1.0E-9)) { printf("<p>test 1 failed!</p>\n"); }
        $vut = 0.99999997;
        $ret = Fraction::toFract($vut);
        if (!(abs($vut - $ret->Val()) < 1.0E-9)) { printf("<p>test 2 failed!</p>\n"); }
        $vut = (0x40000000 - 1.0) / (0x40000000 + 1.0);
        $ret = Fraction::toFract($vut);
        if (!(abs($vut - $ret->Val()) < 1.0E-9)) { printf("<p>test 3 failed!</p>\n"); }
        $vut = 1.0 / 3.0;
        $ret = Fraction::toFract($vut);
        if (!(abs($vut - $ret->Val()) < 1.0E-9)) { printf("<p>test 4 failed!</p>\n"); }
        $vut = 1.0 / (0x40000000 - 1.0);
        $ret = Fraction::toFract($vut);
        if (!(abs($vut - $ret->Val()) < 1.0E-9)) { printf("<p>test 5 failed!</p>\n"); }
        $vut = 320.0 / 240.0;
        $ret = Fraction::toFract($vut);
        if (!(abs($vut - $ret->Val()) < 1.0E-9)) { printf("<p>test 6 failed!</p>\n"); }
        $vut = 6.0 / 7.0;
        $ret = Fraction::toFract($vut);
        if (!(abs($vut - $ret->Val()) < 1.0E-9)) { printf("<p>test 7 failed!</p>\n"); }
        $vut = 320.0 / 241.0;
        $ret = Fraction::toFract($vut);
        if (!(abs($vut - $ret->Val()) < 1.0E-9)) { printf("<p>test 8 failed!</p>\n"); }
        $vut = 720.0 / 577.0;
        $ret = Fraction::toFract($vut);
        if (!(abs($vut - $ret->Val()) < 1.0E-9)) { printf("<p>test 9 failed!</p>\n"); }
        $vut = 2971.0 / 3511.0;
        $ret = Fraction::toFract($vut);
        if (!(abs($vut - $ret->Val()) < 1.0E-9)) { printf("<p>test 10 failed!</p>\n"); }
        $vut = 3041.0 / 7639.0;
        $ret = Fraction::toFract($vut);
        if (!(abs($vut - $ret->Val()) < 1.0E-9)) { printf("<p>test 11 failed!</p>\n"); }
        $vut = 1.0 / sqrt(2.0);
        $ret = Fraction::toFract($vut);
        if (!(abs($vut - $ret->Val()) < 1.0E-9)) { printf("<p>test 12 failed!</p>\n"); }
        $vut = 3.1415279 /* PI */;
        $ret = Fraction::toFract($vut);
        if (!(abs($vut - $ret->Val()) < 1.0E-9)) { printf("<p>test 13 failed!</p>\n"); }
    }

    public function Val()
    {
        if ($this->denom != 0)
        {
            return ((float)$this->num) / ((float)$this->denom);
        }
        else
        {
            return 1.0E24;
        }
    }
}

?>

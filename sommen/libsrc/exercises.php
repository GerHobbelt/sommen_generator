<?php

if (!defined("EDUCATION_WEBSITE"))
{
   die("Don't waste your time trying to access this file");
}




function FltEquals($a, $b, $precision)
{
    $v = $a - $b;
    return ($v >= -1.0E-9 && $v <= 1.0E-9); // 1.0E-9 is assumed to be 'epsilon'
}



// produces, stores and displays one single calculation.
class Exercise extends DebugReporting
{
    // inputs
    protected $oc;
    protected $st;
    protected $dotpos;  // when style is '.', determine which operand is to be 'dotted'.
    protected $in_lb;
    protected $in_ub;
    protected $out_lb;
    protected $out_ub;
    protected $oc_lb;
    protected $oc_ub;
    protected $frac_prec;
    protected $rnd;
    
    // derived values
    protected $in_range;
    protected $out_range;
    
    // results
    protected $vals; // array of left side values (operands) + right side value (answer value)
    protected $disp_vals; // vals[], but now for display
    
    public function __construct($style_def, $rnd, $operator_count)
    {
        $this->rnd = $rnd;
        $this->st = $style_def;
        
        $this->oc = $operator_count;
        $this->oc_lb = $operator_count;
        $this->oc_ub = $operator_count;

        foreach($this->st as $st)
        {
            if (!strncmp($st, "operand_count=", 14))
            {
                $this->oc = (int)substr($st, 14);
                if ($this->oc < 1)
                {
                    throw new FatalException(sprintf("Invalid operand count %s", substr($st, 14)));
                }
            }
            else if (!strncmp($st, "oc_lb=", 6))
            {
                $this->oc_lb = (int)substr($st, 6);
                if ($this->oc_lb < 1)
                {
                    throw new FatalException(sprintf("Invalid operand lower bound count %s", substr($st, 14)));
                }
            }
            else if (!strncmp($st, "oc_ub=", 6))
            {
                $this->oc_ub = (int)substr($st, 6);
                if ($this->oc_ub < 1)
                {
                    throw new FatalException(sprintf("Invalid operand upper bound count %s", substr($st, 14)));
                }
            }
		}

		if ($this->oc_ub < $this->oc_lb)
		{
			throw new FatalException(sprintf("Invalid operand bound set: upper bound %s must be equal or larger than lower bounds %s", 
						$this->oc_ub, $this->oc_lb));
		}
		
		// redetermine operator count:
        $this->oc = $this->oc_lb + (int)($this->rnd->frand() * ($this->oc_ub - $this->oc_lb + 1));
        if (DebugReporting::$dbg >= 3)
		{
			printf("<p>Operator count: %s (lower bound: %s, upper bound: %s)</p>\n", 
					$this->oc, $this->oc_lb, $this->oc_ub);
		}
		
		// and now, to make things easier for the rest of the code, we 'convert' operator count to argument count by adding 1
		$this->oc++;
		
        $this->in_lb = array($this->oc + 1);
        $this->in_ub = array($this->oc + 1);
        $this->out_lb = 1;
        $this->out_ub = 100;
        $this->frac_prec = 0.1;
        
        $pos_l = 0;
        $pos_h = $this->oc; // span left hand (and right hand too, which is ignored...)
        for ($k = $pos_l; $k <= $pos_h; $k++)
        {
            $this->in_lb[$k] = 1;
            $this->in_ub[$k] = 100;
        }

        foreach($this->st as $st)
        {
            if (!strncmp($st, "operand_count=", 14))
            {
                $this->oc = (int)substr($st, 14);
                if ($this->oc < 1)
                {
                    throw new FatalException(sprintf("Invalid operand count %s", substr($st, 14)));
                }
                $pos_l = 0;
                $pos_h = $this->oc; // span left hand (and right hand too, which is ignored...)
            }
            else if (!strcmp($st, "p1"))
            {
                // next filters apply to first lvalue only!
                $pos_l = 0;
                $pos_h = 0;
            }
            else if (!strcmp($st, "p2"))
            {
                // next filters apply to second (and subsequent) lvalues only!
                $pos_l = 1;
                $pos_h = $this->oc - 1;
            }
			else if ($st[0] == 'p' && FALSE !== strpos("3456789", substr($st, 1)) && strlen($st) == 2)
			{
				// next filters apply to Nth (and subsequent) lvalues only!
				$pos_l = ((int)substr($st, 1)) - 1;
				$pos_h = $this->oc - 1;
			}
            else if (!strcmp($st, "pO"))
            {
                // next filters apply to answer (right hand) lvalue only!
                $pos_l = $this->oc;
                $pos_h = $this->oc;
            }
            else if (!strcmp($st, "px"))
            {
                // next filters apply to ANY lvalues!
                $pos_l = 0;
                $pos_h = $this->oc - 1;
            }
            else if (!strncmp($st, "left_lower_bound=", 17))
            {
                $v = (float)substr($st, 17);
                for ($k = $pos_l; $k <= $pos_h; $k++)
                {
                    $this->in_lb[$k] = $v;
                }
            }
            else if (!strncmp($st, "left_upper_bound=", 17))
            {
                $v = (float)substr($st, 17);
                for ($k = $pos_l; $k <= $pos_h; $k++)
                {
                    $this->in_ub[$k] = $v;
                }
            }
            else if (!strncmp($st, "right_lower_bound=", 18))
            {
                $this->out_lb = (float)substr($st, 18);
            }
            else if (!strncmp($st, "right_upper_bound=", 18))
            {
                $this->out_ub = (float)substr($st, 18);
            }
            else if (!strncmp($st, "decimal_tolerance=", 18))
            {
                $this->frac_prec = (float)substr($st, 18);
                if ($this->frac_prec <= 0)
                {
                    throw new FatalException(sprintf("Invalid fraction/decimal tolerance %s", substr($st, 18)));
                }
            }
            else if (!strncmp($st, "lh_low=", 7))
            {
                $v = (float)substr($st, 7);
                for ($k = $pos_l; $k <= $pos_h; $k++)
                {
                    $this->in_lb[$k] = $v;
                }
            }
            else if (!strncmp($st, "lh_high=", 8))
            {
                $v = (float)substr($st, 8);
                for ($k = $pos_l; $k <= $pos_h; $k++)
                {
                    $this->in_ub[$k] = $v;
                }
            }
            else if (!strncmp($st, "rh_low=", 7))
            {
                $this->out_lb = (float)substr($st, 7);
            }
            else if (!strncmp($st, "rh_high=", 8))
            {
                $this->out_ub = (float)substr($st, 8);
            }
        }
            
        $this->vals = array($this->oc + 1);
        $this->disp_vals = array($this->oc + 1);
        $this->in_range = array($this->oc + 1);
        for ($i = 0; $i < $this->oc + 1; $i++)
        {
            $this->disp_vals[$i] = true;
        
            $this->in_range[$i] = $this->in_ub[$i] - $this->in_lb[$i] + 1;
            if ($this->in_range[$i] <= 0)
            {
                throw new FatalException(sprintf("Invalid left hand value range bounds %s .. %s for position %d", $this->in_lb[$i], $this->in_ub[$i], $i));
            }
        }

        $this->dotpos = 0;
        
        // derived values
        $this->out_range = $this->out_ub - $this->out_lb + 1;
        if ($this->out_range <= 0)
        {
            throw new FatalException(sprintf("Invalid right hand value range bounds %s .. %s", $this->out_lb, $this->out_ub));
        }
        
        $this->ExtraConfigChecks();
    }

    public function __destruct()
    {
    }

	public function RequiresMathMLForShow()
	{
		return FALSE;
	}
	
    public function ExtraConfigChecks()
    {
    }    
    
    public function Produce()
    {
        throw new Exception("unknown operation: cannot generate a proper exercise. Use a derived class to do so.");
    }    
    
    public function Compare($v)
    {
        $ret = ($v->oc - $this->oc);
        if ($ret != 0)
        {
            if (DebugReporting::$dbg >= 4) printf("<p>oc not equal: %s != %s</p>\n", $v->oc, $this->oc);
            return $ret;
        }
        for ($i = 0; $i <= $this->oc; $i++)
        {
            $ret = strcmp($v->GetOperator4Show($i, 0), $this->GetOperator4Show($i, 0));
            if ($ret != 0)
            {
                if (DebugReporting::$dbg >= 4) printf("<p>Operators [%s] not equal: %s != %s</p>\n", $i, $v->GetOperator4Show($i, 0), $this->GetOperator4Show($i, 0));
                return $ret;
            }
        }
        for ($i = 0; $i <= $this->oc; $i++)
        {
            $ret = $v->vals[$i] - $this->vals[$i];
            if (!FltEquals($ret, 0, $this->frac_prec))
            {
                if (DebugReporting::$dbg >= 4) printf("<p>value [%s] not equal: %s != %s</p>\n", $i, $v->vals[$i], $this->vals[$i]);
                return $ret;
            }
        }
        return $ret;
    }    
    
    public function PrepareDisplay()
    {
        for ($i = 0; $i < $this->oc + 1; $i++)
        {
            $this->disp_vals[$i] = true;
        }
        
        $reg_found = false;
        foreach($this->st as $st)
        {
            switch ($st)
            {
            default:
                break;
                
            case "reg":
                break;
                
            case "dot":
                // dot exercise: replace arbitrary left side value:
                $this->dotpos = (int)($this->rnd->frand() * $this->oc);
                $this->disp_vals[$this->dotpos] = false;
                $reg_found = true;
                break;
            }
        }
        // do it like this, because "reg" MAY not be specified at all and nor may "dot": assume "reg" to be default behaviour.
        if (!$reg_found)
        {
            // clean up last item (right side answer value)
            $this->dotpos = $this->oc;
            $this->disp_vals[$this->oc] = false;
        }
    }
    
    public function GetOperator4Show($idx, $marker)
    {
        return "???";
    }
    
    public function Getvalue4Show($idx, $marker)
    {
        if (isset($this->vals[$idx]))
        {
            return sprintf("%s", $this->vals[$idx]);
        }
        else
        {
            return sprintf("???");
        }
    }
    
    public function Getvalue4ShowFilter($idx, $marker)
    {
        if ($this->disp_vals[$idx] || $marker == $this->oc)
        {
            return $this->Getvalue4Show($idx, $marker);
        }
        else if ($idx == $this->oc)
        {
            return "";
        }
        else
        {
            return ".";
        }
    }
    
    public function Show($n, $idx)
    {
        if ($idx != $this->oc)
        {
            printf("%s", $this->Getvalue4ShowFilter(0, $idx));
            for ($i = 1; $i < $this->oc; $i++)
            {
                printf(" %s %s", $this->GetOperator4Show($i, $idx), $this->Getvalue4ShowFilter($i, $idx));
            }
            printf(" = %s", $this->Getvalue4ShowFilter($this->oc, $idx));
        }
        else
        {
            printf("%s", $this->Getvalue4ShowFilter($this->oc, $idx));
        }
    }
    
    public function ShowExercise($n)
    {
        if (strlen($n) > 0)
        {
            printf("<td class=\"sum_index\"><p>%s.</p></td>", $n + 1);
        }
        printf("<td class=\"sum\">\n<p>");
        $this->Show($n, 0);
        printf("</p>\n</td>\n");
    }
    
    public function ShowAnswer($n)
    {
        if (strlen($n) > 0)
        {
            printf("<td class=\"answer_index\"><p>%s.</p></td>", $n + 1);
        }
        printf("<td class=\"answer\">\n<p>");
        $this->Show($n, $this->oc);
        printf("</p>\n</td>\n");
    }
}







//
// we use this approach now: just generate the in-range numbers, execute the addition/multiplication/whatever
// and see if it falls within the specified output bounds. (Before we had something that was 'smarter' for addition
// but it didn't work well when some of the more 'particular' style filters were to be applied.
//
// If no success, give up.
//
// NOTE: that 'retry when failed' mechanism will be placed OUTSIDE this class: outer systems can better determine how and when
// they wish to retry failed attempts at generating a calculation!
//
// Why not do something smarter like making it work in one go by shuffling the numbers a bit when not correct at first try?
// because we work with integer math, we cannot generate all numbers in the range. (Can you say prime numbers? what if the 
// lower in-bound = 2? Then prime numbers above 2 cannot be reached. Ever. This is just one example.)
//
class AddExercise extends Exercise
{
    public function QuantizeValue($val, $idx)
    {
		$this->QuantizeExtraPrep($idx);
		
        $val = $this->RoundValue4Quantize($val);
		
		$pos_l = 0;
		$pos_h = $this->oc; // span left hand AND right hand item!
		
		foreach($this->st as $st)
		{
			switch ($st)
			{
			default:
				if (!strncmp($st, "step=", 5))
				{
					// calculus values may only be values, rounded to a given factor.
					$factor = (float)substr($st, 5);
					if (DebugReporting::$dbg >= 2)
					{
						printf("<p>integer step: factor = %s, ret= %s, idx = %d, pos_l = %d, pos_h = %d\n", 
								$factor, $val, $idx, $pos_l, $pos_h);
					}
					
					if ($idx >= $pos_l && $idx <= $pos_h)
					{
						$val = ((int)($val / $factor)) * $factor;
						if (DebugReporting::$dbg >= 2)
						{
							printf("<p>integer step: corrected ret: %s, factor = %s, idx = %d\n", 
									$val, $factor, $idx);
						}
						$val = $this->RoundValue4Quantize($val);
					}
				}
				else if ($st[0] == 'T')
				{
					// value may only be one of a specified set
					//
					// originally only intended for [tables of] multiplication, but now usable anywhere.
					
					if ($idx >= $pos_l && $idx <= $pos_h)
					{
						// allow only particular tables of multiplication: set following the T!
						// since the multiplication tables may go BEYOND 10 while we only allow multipliers up to 10,
						// we cannot do this by 'filtering' the currently generated material.
						// Instead, we just pick the table of choice from the list and apply that to lvalue[0].
						//
						// Of course, the answer must be recalculated too!
						$multipliers = explode(',', substr($st, 1));
						$diff = 4000000000;
						foreach($multipliers as $m)
						{
							$delta = abs($val - (int)$m);
							if ($delta < $diff)
							{
								$val = (int)$m;
								$diff = $delta;
							}
						}
						if (DebugReporting::$dbg >= 2)
						{
							printf("<p>T table quatization: delta: %s, idx = %d, val: %s\n", 
									$diff, $idx, $val);
						}
					}
				}
				else if ($st[0] == 'p' && FALSE !== strpos("3456789", substr($st, 1)) && strlen($st) == 2)
				{
					// next filters apply to Nth (and subsequent) lvalues only!
					$pos_l = ((int)substr($st, 1)) - 1;
					$pos_h = $this->oc - 1;
				}
				else
				{
					$val = $this->QuantizeExtra($val, $idx, $st, $pos_l, $pos_h);
				}
				break;
		
			case "p1":
				// next filters apply to first lvalue only!
				$pos_l = 0;
				$pos_h = 0;
				break;
				
			case "p2":
				// next filters apply to second (and subsequent) lvalues only!
				$pos_l = 1;
				$pos_h = $this->oc - 1;
				break;
		
			case "pO":
				// next filters apply to answer (right hand) lvalue only!
				$pos_l = $this->oc;
				$pos_h = $this->oc;
				break;
		
			case "px":
				// next filters apply to ANY lvalues!
				$pos_l = 0;
				$pos_h = $this->oc - 1;
				break;
			}
		}
					
		return $val;
    }

    protected function RoundValue4Quantize($val)
    {
		return ((int)floor($val));
	}
	
    protected function QuantizeExtraPrep($idx)
    {
		// nada
	}
	
    protected function QuantizeExtra($val, $idx, $st, $pos_l, $pos_h)
    {
		// nada
		return $val;
	}
	
    protected function RestrictToDecades($val, $random_base_value, $pos_l, $pos_h, $idx, $zero_ok)
    {
        // calculus values may only be powers of ten: do this by stripping the units off
        $val = (int)floor($val);
        $val -= ($val % 10);
        if (!$zero_ok && $val == 0)
        {
            // make sure we 'reverse' the result to have a VERY high probability we do not end up
            // with the very same $val as we just got above: lb-ub, then subtract from top to get in range:
            $range = (int)($this->in_lb[$idx] / 10) - (int)($this->in_ub[$idx] / 10) + 1;
            $val = (int)($random_base_value * $range);
            $val *= 10;
            $val = 10 * (int)($this->in_ub[$idx] / 10) - $val;
            if ($val > $this->in_ub[$idx])
            {
                throw new Exception("value not acceptable: restricted to decades; zero not OK so rescale, but found to be beyond upper bound then");
            }
            else if ($val < $this->in_lb[$idx])
            {
                throw new Exception("value not acceptable: restricted to decades; zero not OK so rescale, but found to be below lower bound then");
            }
            else if (!$zero_ok && $val == 0)
            {
                // range may be negative to zero or positive: ZERO is positioned in the middle of the range then; no can do except fail.
                throw new Exception("value not acceptable: restricted to decades; zero not OK so rescale, but found to be zero again then");
            }
        }
        return $val;
    }


    protected function RestrictToUnits($val, $random_base_value, $pos_l, $pos_h, $idx, $zero_ok, $scale)
    {
        // calculus values may only be units: do this by stripping off the decades and above
        $unit = (int)floor($val);
        $unit -= ((int)($unit / 10)) * 10;
        if (!$zero_ok && $unit == 0)
        {
            throw new Exception("value not acceptable: restricted to units: zero not OK so rescale, but found to be zero again then");
        }
        return $unit;
    }

    protected function PredictLLHValue($ret, $val, $dest, $idx, $zero_ok)
    {
        // correct $ret to ensure the sum $val will become the sum $dest
        $diff = $dest - $val;
        if (DebugReporting::$dbg >= 2)
        {
            printf("<p>Predict LLH Value: ret = %s, diff = %s, new_ret = %s, val = %s, dest = %s, idx = %d, zero_ok = %d\n", 
                    $ret, $diff, ($ret + $diff), $val, $dest, $idx, $zero_ok);
        }
        $ret += $diff;
        return $ret;
    }

    protected function DetermineValueExtra($val, $random_base_value, $idx, $st, $zero_ok, $pos_l, $pos_h)
    {
        // nada; ignore
        return $val;
    }

    public function DetermineValue($random_base_value, $idx)
    {
        // apply scale, then quantize.
        if ($idx < $this->oc)
        {
            // lvalue:
            $ret = $random_base_value * $this->in_range[$idx] + $this->in_lb[$idx];
        }
        else
        {
            // rvalue:
            $ret = $random_base_value * $this->out_range + $this->out_lb;
        }
        if (DebugReporting::$dbg >= 4) 
        {
            printf("<p>ret = %s @ idx = %s (base = %s, range = %s, lb = %s)\n", $ret, $idx, $random_base_value, $this->in_range[$idx], $this->in_lb[$idx]);
        }
        $ret = $this->QuantizeValue($ret, $idx);
        if (DebugReporting::$dbg >= 4) 
        {
            printf("quantized: %s</p>\n", $ret);
        }
        
        $units_rescale = 10; // default units rescale is 10 (or 9 when 'zero' is not acceptable), but this 'scale' (range) may be reduced when using no10wrap: see there.

        $state = 1;    // 1: init, 0: rerun and do averything again EXCEPT no10wrap, 2: detected no10wrap was handled before, don't do that anymore
        do
        {
            if (DebugReporting::$dbg >= 2 && $state != 1)
            {
                printf("<p>no10wrap: result B: %s (rescale: %s)\n", $ret, $units_rescale);
            }
            $zero_ok = false;
            
            // $units_rescale = 10; // default units rescale is 10 (or 9 when 'zero' is not acceptable), but this 'scale' (range) may be reduced when using no10wrap: see there.
            $pos_l = 0;
            $pos_h = $this->oc; // span left hand AND right hand item!
            foreach($this->st as $st)
            {
                if (DebugReporting::$dbg >= 2 && $state != 1)
                {
                    printf("<p>no10wrap: result C: %s (rescale: %s) [%s]\n", $ret, $units_rescale, $st);
                }
                switch ($st)
                {
                default:
                    if (!strncmp($st, "step=", 5))
                    {
                        // calculus values may only be integer values, rounded to a given factor.
                        //
                        // Note that ALL float and fraction classes will need this too, where you can force 'nice'
                        // answers by using 'step=1' for the answer item ('pO'),
                        $factor = (float)substr($st, 5);
                        if (DebugReporting::$dbg >= 2)
                        {
                            printf("<p>step: factor = %s, ret= %s, idx = %d, zero_ok = %d, pos_l = %d, pos_h = %d\n", 
                                    $factor, $ret, $idx, $zero_ok, $pos_l, $pos_h);
                        }
                        
                        if ($idx >= $pos_l && $idx <= $pos_h)
                        {
                            $ret = ((int)($ret / $factor)) * $factor;
                            if (DebugReporting::$dbg >= 2)
                            {
                                printf("<p>step: corrected ret: %s, factor = %s, idx = %d, zero_ok = %d\n", 
                                        $ret, $factor, $idx, $zero_ok);
                            }
                            $ret = $this->QuantizeValue($ret, $idx);
                        }
                        else if ($idx + 1 == $this->oc && $this->oc >= $pos_l && $this->oc <= $pos_h)
                        {
                            // when we put this restriction on the ANSWER, we might help ourselves by
                            // reducing the number of rejects by 'fitting' the last left hand term to
                            // produce the proper result...
                            $this->vals[$idx] = $ret;
                            $this->CalculateAnswer($idx + 1);
                            $val = $this->vals[$idx + 1];
                            $dest = (int)($val / $factor) * $factor;
                            if (DebugReporting::$dbg >= 2)
                            {
                                printf("<p>step: corrected answer: %s, factor = %s, answer = %s, idx = %d, zero_ok = %d\n", 
                                        $dest, $factor, $val, $idx, $zero_ok);
                            }
							$dest = $this->QuantizeValue($dest, $idx + 1);
                            if (!FltEquals($val, $dest, $this->frac_prec))
                            {
                                $ret = $this->PredictLLHValue($ret, $val, $dest, $idx, $zero_ok);
                                $ret = $this->QuantizeValue($ret, $idx);
                            }
                        }
                    }
					else if ($st[0] == 'T')
					{
					    // value may only be one of a specified set
						//
						// originally only intended for [tables of] multiplication, but now usable anywhere.
						
						if ($idx >= $pos_l && $idx <= $pos_h)
						{
							// allow only particular tables of multiplication: set following the T!
							// since the multiplication tables may go BEYOND 10 while we only allow multipliers up to 10,
							// we cannot do this by 'filtering' the currently generated material.
							// Instead, we just pick the table of choice from the list and apply that to lvalue[0].
							//
							// Of course, the answer must be recalculated too!
							$multipliers = explode(',', substr($st, 1));
							$m_idx = (int)(count($multipliers) * $random_base_value);
							foreach($multipliers as $m)
							{
								if ($m_idx-- == 0)
								{
									$ret = (int)$m;
									break;
								}
							}
                            if (DebugReporting::$dbg >= 2)
                            {
                                printf("<p>T table: answer: %s, choice = %s, idx = %d, zero_ok = %d\n", 
                                        $ret, $m_idx, $idx, $zero_ok);
                            }

							if ($idx + 1 == $this->oc)
							{
								// when we put this restriction on the calculation, we might help ourselves by
								// reducing the number of rejects by 'fitting' the last left hand term to
								// produce the proper result...
								$this->vals[$idx] = $ret;
								$this->CalculateAnswer($idx + 1);
								$val = $this->vals[$idx + 1];
								if ($val < $this->out_lb || $val > $this->out_ub)
								{
									$opt_ret = array(count($multipliers) + 1);
									$opt_idx = 0;
									
									foreach($multipliers as $m)
									{
										$this->vals[$idx] = $m;
										$this->CalculateAnswer($idx + 1);
										$opt_val = $this->vals[$idx + 1];
										if ($opt_val >= $this->out_lb && $opt_val <= $this->out_ub)
										{
											$opt_ret[$opt_idx++] = $m;
										}
									}
									
									if (DebugReporting::$dbg >= 2)
									{
										printf("<p>T table: original answer out of range: number of viable terms: %s, orig. answer = %s, idx = %d, zero_ok = %d, pos_l = %s, pos_h = %d, oc = %d\n", 
												$opt_idx, $val, $idx, $zero_ok, $pos_l, $pos_h, $this->oc);
									}
									
									if ($opt_idx > 0)
									{
										$opt_idx = (int)($this->rnd->frand_triangle() * $opt_idx);
										$ret = $opt_ret[$opt_idx];

										if (DebugReporting::$dbg >= 2)
										{
											printf("<p>T table: original answer out of range: picked viable term %d\n", 
													$opt_idx);
										}
									}
								}
							}
						}
					}
					else if ($st[0] == 'p' && FALSE !== strpos("3456789", substr($st, 1)) && strlen($st) == 2)
					{
						// next filters apply to Nth (and subsequent) lvalues only!
						$pos_l = ((int)substr($st, 1)) - 1;
						$pos_h = $this->oc - 1;
					}
                    else
                    {
                        $ret = $this->DetermineValueExtra($ret, $random_base_value, $idx, $st, $zero_ok, $pos_l, $pos_h);
                    }
                    break;
                    
                case "0ok":
                    // subsequent filters: zero value result is OK
                    if ($idx >= $pos_l && $idx <= $pos_h)
                    {
                        if (!$zero_ok && $units_rescale >= 9)
                        {
                            $units_rescale = 10; 
                        }
                        $zero_ok = true;
                    }
                    break;
                    
                case "0notok":
                    // subsequent filters: zero value result is not OK
                    if ($idx >= $pos_l && $idx <= $pos_h)
                    {
                        if ($zero_ok && $units_rescale >= 9)
                        {
                            $units_rescale = 9; 
                        }
                        $zero_ok = false;
                        // defer zero value check till we're out of this loop: we may want to rescale due to 'units' or 'tens' options too!
                    }
                    break;
                    
                case "1notok":
                    // subsequent filters: '1' value result is not OK
                    if ($idx >= $pos_l && $idx <= $pos_h)
                    {
						$val = $this->vals[$idx];
						
						if (FltEquals($val, 1, $this->frac_prec))
						{
							throw new Exception(sprintf("value not acceptable: answer (%s @ index %d) must NOT be ONE", 
									$this->vals[$idx], $idx));
						}
					}
                    break;
                    
                case "p1":
                    // next filters apply to first lvalue only!
                    $pos_l = 0;
                    $pos_h = 0;
                    break;
                    
                case "p2":
                    // next filters apply to second (and subsequent) lvalues only!
                    $pos_l = 1;
                    $pos_h = $this->oc - 1;
                    break;
            
                case "pO":
                    // next filters apply to answer (right hand) lvalue only!
                    $pos_l = $this->oc;
                    $pos_h = $this->oc;
                    break;
            
                case "px":
                    // next filters apply to ANY lvalues!
                    $pos_l = 0;
                    $pos_h = $this->oc - 1;
                    break;
            
                case "tens":
                    // calculus values may only be powers of ten: do this by stripping the units off
                    if ($idx + 1 == $this->oc && $this->oc >= $pos_l && $this->oc <= $pos_h)
                    {
                        // when we put this restriction on the ANSWER, we might help ourselves by
                        // reducing the number of rejects by 'fitting' the last left hand term to
                        // produce the proper result...
                        $factor = 10;
                        
                        $this->vals[$idx] = $ret;
                        $this->CalculateAnswer($idx + 1);
                        $val = $this->vals[$idx + 1];
                        $dest = (int)($val / $factor) * $factor;
                        if (DebugReporting::$dbg >= 2)
                        {
                            printf("<p>TENS: corrected answer: %s, factor = %s, answer = %s, idx = %d, zero_ok = %d, pos_l = %s, pos_h = %d, oc = %d\n", 
                                    $dest, $factor, $val, $idx, $zero_ok, $pos_l, $pos_h, $this->oc);
                        }
						$dest = $this->QuantizeValue($dest, $idx + 1);
                        if (!FltEquals($val, $dest, $this->frac_prec))
                        {
                            $ret = $this->PredictLLHValue($ret, $val, $dest, $idx, $zero_ok);
                            $ret = $this->QuantizeValue($ret, $idx);
                        }
                    }
                    
					if ($idx >= $pos_l && $idx <= $pos_h)
                    {
                        $ret = $this->RestrictToDecades($ret, $random_base_value, $pos_l, $pos_h, $idx, $zero_ok);
                    }
                    break;
                    
                case "units":
                    // lvalues may not contain decades: regenerate a unit value:
                    if ($idx >= $pos_l && $idx <= $pos_h)
                    {
                        $ret = $this->RestrictToUnits($ret, $random_base_value, $pos_l, $pos_h, $idx, $zero_ok, $units_rescale);
                    }
                    break;
                    
                case "10orU":
                    // lvalues may not contain decades OR may only be decades: decide which way to go by random choice
                    if ($idx >= $pos_l && $idx <= $pos_h)
                    {
                        $choice = $this->GetRandom($idx);
                        if ($choice >= 0.5)
                        {
                            $ret = $this->RestrictToDecades($ret, $random_base_value, $pos_l, $pos_h, $idx, $zero_ok);
                        }
                        else
                        {
                            $ret = $this->RestrictToUnits($ret, $random_base_value, $pos_l, $pos_h, $idx, $zero_ok, $units_rescale);
                        }
                    }
                    break;
                    
                case "no10wrap":
                    // calculus value may not wrap/overflow to another decade; brute force by filtering: reject anything that does 'overflow'.
                    //
                    // We cannot/will not handle that one here... though we might be able to so some prep work to reduce the
                    // number of failures dramatically:
                    if ($idx >= $pos_l && $idx <= $pos_h)
                    {
                        if ($state != 1)
                        {   
                            // reset flag set by previous round in here
                            
                            $state = 2;  // stop outer loop
                        }
                        else
                        {
                            $sum = 0;
                            $been_pos = 0;
                            for ($i = 0; $i < $idx; $i++)
                            {
                                $unit = $this->vals[$i] % 10;
                                $sum += $unit;
                                if ($sum > 0)
                                {
                                    $been_pos = 1;
                                }
                                else if ($sum < 0)
                                {
                                    $been_pos = -1;
                                }
                            }
                            
                            if ($been_pos == 1)
                            {
                                if ($ret >= 0)
                                {
                                    $do_correct = (($ret % 10) + $sum > 10);
                                }
                                else
                                {
                                    $do_correct = (($ret % 10) + $sum < 0);
                                }
                            }
                            else if ($been_pos == -1)
                            {
                                if ($ret >= 0)
                                {
                                    $do_correct = (($ret % 10) + $sum > 0);
                                }
                                else
                                {
                                    $do_correct = (($ret % 10) + $sum < -10);
                                }
                            }
                            else
                            {
                                $do_correct = TRUE; // FALSE; -- set this to FALSE if you accept stuff like 10 - 7 = 3 as 'nowrap' as well...
                            }
                            
                            if (DebugReporting::$dbg >= 2) 
                            {
                                printf("<p>no10wrap: rescale units? %s (mod10 = %s / sum=%s) == %s\n", $ret, $ret % 10, $sum, ($do_correct ? "YES" : "NO"));
                            }
                            
                            if ($do_correct)
                            {
                                // to prevent weird quantization effects, recalc the value from scratch and rerun the whole check cycle!
                                $ret = $random_base_value * $this->in_range[$idx] + $this->in_lb[$idx];
                                
                                // now this is what's really left for us to occupy: adjust our units range
                                $range = ($sum >= 0 ? ($ret >= 0 ? $sum : 10 - $sum) : ($ret >= 0 ? 10 + $sum : $sum));
                                
                                
                                $decades = (int)($ret / 10);
                                $units = $ret - $decades * 10;
                                
                                // special nudge to make sure the 'boundary solutions' happen more often:
                                // when this is the last LH term, use a larger scale and CLIP to bounds:
                                if ($idx == $this->oc - 1)
                                {
                                    $this_range = $range * 1.4;
                                    if ($this_range > 10)
                                    {
                                        $this_range = 10;
                                    }
                                }
                                else
                                {
                                    $this_range = $range;
                                }
                                
                                // now rescale units to fit the new (reduced) range:
                                $units *= $this_range;
                                $units /= 10;
                                
                                // second bit for the 'nudging' above: clip to bounds!
                                if ($sum > 0)
                                {
                                    if ($units > 0 && $units + $sum > 10)
                                    {
                                        $units = 10 - $sum;
                                    }
                                    else if ($units < 0 && $units + $sum < 0)
                                    {
                                        $units = -$sum;
                                    }
                                }
                                else if ($sum < 0)
                                {
                                    if ($units > 0 && $units + $sum > 0)
                                    {
                                        $units = -$sum;
                                    }
                                    else if ($units < 0 && $units + $sum < -10)
                                    {
                                        $units = -10 - $sum;
                                    }
                                }
                                
                                $ret = $units + $decades * 10;
                                $ret = $this->QuantizeValue($ret, $idx);

                                $units_rescale = $range;
                                
                                if (DebugReporting::$dbg >= 2)
                                {
                                    printf("<p>no10wrap: RESCALED: %s (units:%s / decades: %s / sum:%s / range:%s / range[%d]:%s)\n", $ret, $units, $decades, $sum, $range, $idx, $this_range);
                                }
                                
                                $state = 0;
                                
                                break 2;       // break out of the case and the foreach...
                            }
                        }
                    }
                    break;

                case "10wrap":
                    // calculus value MUST wrap/overflow to another decade; brute force by filtering: reject anything that does not 'overflow'.
                    //
                    // We cannot/will not handle that one here... though we might be able to so some prep work to reduce the
                    // number of failures dramatically:
                    if ($idx >= $pos_l && $idx <= $pos_h)
                    {
                        if ($state != 1)
                        {   
                            // reset flag set by previous round in here
                            
                            $state = 2;  // stop outer loop
                        }
                        else
                        {
                            $sum = 0;
                            $been_pos = 0;
                            for ($i = 0; $i < $idx; $i++)
                            {
                                $unit = $this->vals[$i] % 10;
                                $sum += $unit;
                                if ($sum > 0)
                                {
                                    $been_pos = 1;
                                }
                                else if ($sum < 0)
                                {
                                    $been_pos = -1;
                                }
                            }
                            
                            if ($been_pos == 1)
                            {
                                if ($ret >= 0)
                                {
                                    $do_correct = (($ret % 10) + $sum > 10);
                                }
                                else
                                {
                                    $do_correct = (($ret % 10) + $sum < 0);
                                }
                            }
                            else if ($been_pos == -1)
                            {
                                if ($ret >= 0)
                                {
                                    $do_correct = (($ret % 10) + $sum > 0);
                                }
                                else
                                {
                                    $do_correct = (($ret % 10) + $sum < -10);
                                }
                            }
                            else
                            {
                                $do_correct = TRUE; // FALSE; -- set this to FALSE if you accept stuff like 10 - 7 = 3 as 'nowrap' as well...
                            }
                            
                            if (DebugReporting::$dbg >= 2) 
                            {
                                printf("<p>10wrap: rescale units? %s (mod10 = %s / sum=%s) == %s\n", $ret, $ret % 10, $sum, (!$do_correct ? "YES" : "NO"));
                            }
                            
                            if (!$do_correct)
                            {
                                // to prevent weird quantization effects, recalc the value from scratch and rerun the whole check cycle!
                                $ret = $random_base_value * $this->in_range[$idx] + $this->in_lb[$idx];
                                
                                // now this is what's really left for us to occupy: adjust our units range (plus the minimum offset to create a carry!)
                                $range = ($sum >= 0 ? ($ret >= 0 ? $sum : 10 - $sum) : ($ret >= 0 ? 10 + $sum : $sum));
                                
                                
                                if ($sum >= 0)
                                {
                                    if ($ret >= 0)
                                    {
                                        $offset = 11 - $sum;
                                        $range = 10 - $offset; 
                                    }
                                    else
                                    {
                                        $offset = -1 - $sum;
                                        $range = 10 + $offset; 
                                    }
                                }
                                else
                                {
                                    if ($ret < 0)
                                    {
                                        $offset = -11 - $sum;
                                        $range = 10 + $offset; 
                                    }
                                    else
                                    {
                                        $offset = 1 - $sum;
                                        $range = 10 - $offset; 
                                    }
                                }
                                
                                
                                $decades = (int)($ret / 10);
                                $units = $ret - $decades * 10;
                                
                                // now rescale units to fit the new (reduced) range:
                                $units *= $range;
                                $units /= 10;
                                $units += $offset;
                                
                                if (DebugReporting::$dbg >= 2)
                                {
                                    printf("<p>10wrap: RESCALED PRE: %s (units:%s / decades: %s / sum:%s / range:%s / offset:%s)\n", $ret, $units, $decades, $sum, $range, $offset);
                                }

                                // second bit for the 'nudging' above: clip to outside bounds!
                                if ($sum > 0)
                                {
                                    if ($units > 0 && $units + $sum <= 10)
                                    {
                                        $units = 11 - $sum;
                                        $units %= 10;
                                    }
                                    else if ($units < 0 && $units + $sum >= 0)
                                    {
                                        $units = -1 - $sum;
                                        $units %= 10;
                                    }
                                }
                                else if ($sum < 0)
                                {
                                    if ($units > 0 && $units + $sum <= 0)
                                    {
                                        $units = 1 - $sum;
                                        $units %= 10;
                                    }
                                    else if ($units < 0 && $units + $sum >= -10)
                                    {
                                        $units = -11 - $sum;
                                        $units %= 10;
                                    }
                                }
                                
                                $ret = $units + $decades * 10;
                                $ret = $this->QuantizeValue($ret, $idx);

                                // $units_rescale = $range;
                                if ($zero_ok)
                                {
                                    $units_range = 10; 
                                }
                                else
                                {
                                    $units_range = 9; 
                                }
                                
                                if (DebugReporting::$dbg >= 2)
                                {
                                    printf("<p>10wrap: RESCALED: %s (units:%s / decades: %s / sum:%s / range:%s / offset:%s)\n", $ret, $units, $decades, $sum, $range, $offset);
                                }
                                
                                $state = 0;
                                
                                break 2;       // break out of the case and the foreach...
                            }
                        }
                    }
                    break;
                }
                if (DebugReporting::$dbg >= 2 && $state != 1)
                {
                    printf("<p>no10wrap: result D: %s (rescale: %s)\n", $ret, $units_rescale);
                }
            }
            if (DebugReporting::$dbg >= 2 && $state != 1)
            {
                printf("<p>no10wrap: result A: %s (rescale: %s)\n", $ret, $units_rescale);
            }
        } while ($state == 0);
    
        // check for non-allowed zero value now (deferred)
        if (!$zero_ok)
        {
            if (FltEquals($ret, 0, $this->frac_prec))
            {
                throw new Exception("value not acceptable: zero is not OK");
            }
        }
        
        if (DebugReporting::$dbg >= 2 && $state != 1)
        {
            printf("<p>no10wrap: result: %s (rescale: %s)\n", $ret, $units_rescale);
        }
        return $ret;
    }
    
    public function GetRandom($idx)
    {
        $rv = $this->rnd->frand();
        
        return $rv;
        
    }
    public function GenerateValue($idx)
    {
        $rv = $this->GetRandom($idx);

        return $this->DetermineValue($rv, $idx);
    }
    
    protected function RestrictExerciseExtra($st, $pos_l, $pos_h)
    {
        // nada
    }

    protected function RestrictExercisePost()
    {
        // when we put tight restrictions on the ANSWER, we might help ourselves by
        // reducing the number of rejects by 'fitting' the last left hand term to
        // produce the proper result...
        $this->CalculateAnswer($this->oc);
        $val = $this->vals[$this->oc];
        if (DebugReporting::$dbg >= 2)
        {
            printf("<p>Restrict Exercise Post: out_range = %s, lower bound: %s, upper bound: %s, value: %s\n", 
                    $this->out_range, $this->out_lb, $this->out_ub, $val);
        }
		
        if ($val < $this->out_lb - $this->frac_prec || $val > $this->out_ub + $this->frac_prec)
        {
            $ret = $this->vals[$this->oc - 1];
			$rv = $this->GetRandom($this->oc - 1);
            $dest = $this->out_lb;
			if ($this->out_range > 1)
			{
				// keep it (slightly) backwards compatible: only guestimate a new result value when the range is > 1
				$dest += $rv * $this->out_range;
			}
            $dest = $this->QuantizeValue($dest, $this->oc);
            if (!FltEquals($val, $dest, $this->frac_prec))
            {
                // tweak our last left hand value!
                $ret = $this->PredictLLHValue($ret, $val, $dest, $this->oc - 1, true);
                $ret = $this->QuantizeValue($ret, $this->oc - 1);
                $this->vals[$this->oc - 1] = $ret;
            }
        }
    }

    public function RestrictExercise()
    {
        $pos_l = 0;
        $pos_h = $this->oc; // span left hand AND right hand item!

        // ADD-specific reordering of left values?
        foreach($this->st as $st)
        {
            switch ($st)
            {
            default:
				if ($st[0] == 'p' && FALSE !== strpos("3456789", substr($st, 1)) && strlen($st) == 2)
				{
					// next filters apply to Nth (and subsequent) lvalues only!
					$pos_l = ((int)substr($st, 1)) - 1;
					$pos_h = $this->oc - 1;
				}
				else
				{
					$this->RestrictExerciseExtra($st, $pos_l, $pos_h);
				}
                break;
    
            case "p1":
                // next filters apply to first lvalue only!
                $pos_l = 0;
                $pos_h = 0;
                break;
                
            case "p2":
                // next filters apply to second (and subsequent) lvalues only!
                $pos_l = 1;
                $pos_h = $this->oc - 1;
                break;
        
            case "pO":
                // next filters apply to answer (right hand) lvalue only!
                $pos_l = $this->oc;
                $pos_h = $this->oc;
                break;
        
            case "px":
                // next filters apply to ANY lvalues!
                $pos_l = 0;
                $pos_h = $this->oc - 1;
                break;
        
            // WARNING: subtract and divide operations are NOT commutative, so 'reordering' their terms
            // will change the outcome! However, we do not check for that, as we moved the reordering
            // in the 'RestrictExercise()' section of the code so as to allow the subsequent 'CalculateAnswer()'
            // plus validations in 'ApplyPostFilter()' to catch this type of change, which VERY probably
            // leads to an out-of-bounds conditions there.
            //
            // This saves us some extra specific checks, just to make sure the user did not specify an inherently
            // INVALID option set for this particular 'style': 'asc' and 'desc' are not suitable for sub and div
            // operations -- nor their derivatives.
            case "desc":
                // order left values from high to low...
                for ($i = $pos_l; $i < $pos_h - 1; $i++)
                {
                    for ($j = i + 1; $j < $pos_h; $j++)
                    {
                        if ($this->vals[$j] > $this->vals[$i])
                        {
                            $v = $this->vals[$j];
                            $this->vals[$j] = $this->vals[$i];
                            $this->vals[$i] = $v;
                        }
                    }
                }
                break;
                
            case "asc":
                // order left values from low to high...
                for ($i = $pos_l; $i < $pos_h - 1; $i++)
                {
                    for ($j = i + 1; $j < $pos_h; $j++)
                    {
                        if ($this->vals[$j] < $this->vals[$i])
                        {
                            $v = $this->vals[$j];
                            $this->vals[$j] = $this->vals[$i];
                            $this->vals[$i] = $v;
                        }
                    }
                }
                break;
            }
        }

        $this->RestrictExercisePost();
    }
    
    protected function ApplyPostFilterExtra($st, $pos_l, $pos_h, $check_in_range)
    {
        // nada; ignore
    }
    
    public function ApplyPostFilter()
    {
        $pos_l = 0;
        $pos_h = $this->oc; // span left hand AND right hand item!
        
		$check_in_range = array($this->oc + 1);
		for ($idx = 0; $idx <= $this->oc; $idx++)
		{
			$check_in_range[$idx] = 1;
		}
		
        // specific reordering of left values? Any other special demands?
        foreach($this->st as $st)
        {
            switch ($st)
            {
            default:
                if (!strncmp($st, "step=", 5))
                {
                    // calculus values may only be integer values, rounded to a given factor.
                    $factor = (float)substr($st, 5);

                    for ($idx = $pos_l; $idx <= $pos_h; $idx++)
                    {
                        // $val = $this->vals[$idx] % $factor;
                        $v = $this->vals[$idx];
                        $iv = ((int)($v / $factor)) * $factor;
                        
                        if (!FltEquals($iv, $v, $this->frac_prec))
                        {
                            throw new Exception(sprintf("value not acceptable: answer (%s @ index %d) must be value rounded to factor %s (e.g. %s)", 
                                    $v, $idx, $factor, $iv));
                        }
                    }
                }
				else if ($st[0] == 'T')
				{
					for ($idx = $pos_l; $idx <= $pos_h; $idx++)
					{
						$check_in_range[$idx] = 0;
					}
					
					// allow only particular tables of multiplication: set following the T!
					// since the multiplication tables may go BEYOND 10 while we only allow multipliers up to 10,
					// we cannot do this by 'filtering' the currently generated material.
					// Instead, we just pick the table of choice from the list and apply that to lvalue[0].
					//
					// Of course, the answer must be recalculated too!
					$multipliers = explode(',', substr($st, 1));
                    for ($idx = $pos_l; $idx <= $pos_h; $idx++)
                    {
                        // $val = $this->vals[$idx] % $factor;
                        $v = $this->vals[$idx];
						$hit = 0;
						foreach($multipliers as $m)
						{
							if (FltEquals($m, $v, $this->frac_prec))
							{
								$hit = 1;
								break;
							}
						}
                        if (!$hit)
                        {
                            throw new Exception(sprintf("value not acceptable: answer (%s @ index %d) must be value from the series %s", 
                                    $v, $idx, substr($st, 1)));
                        }
                    }
				}
				else if ($st[0] == 'p' && FALSE !== strpos("3456789", substr($st, 1)) && strlen($st) == 2)
				{
					// next filters apply to Nth (and subsequent) lvalues only!
					$pos_l = ((int)substr($st, 1)) - 1;
					$pos_h = $this->oc - 1;
				}
                else
                {
   					$this->ApplyPostFilterExtra($st, $pos_l, $pos_h, $check_in_range);
                }
                break;
        
            case "p1":
                // next filters apply to first lvalue only!
                $pos_l = 0;
                $pos_h = 0;
                break;
                
            case "p2":
                // next filters apply to second (and subsequent) lvalues only!
                $pos_l = 1;
                $pos_h = $this->oc - 1;
                break;
        
            case "pO":
                // next filters apply to answer (right hand) lvalue only!
                $pos_l = $this->oc;
                $pos_h = $this->oc;
                break;
        
            case "px":
                // next filters apply to ANY lvalues!
                $pos_l = 0;
                $pos_h = $this->oc - 1;
                break;
        
            case "0ok":
                // zero value result is OK
                break;
                
            case "1ok":
                // '1' value result is OK
                break;
                
            case "0notok":
                // zero value result is not OK
                for ($idx = $pos_l; $idx <= $pos_h; $idx++)
                {
                    $val = $this->vals[$idx];
                    
                    if (FltEquals($val, 0, $this->frac_prec))
                    {
                        throw new Exception(sprintf("value not acceptable: answer (%s @ index %d) must NOT be ZERO", 
                                $this->vals[$idx], $idx));
                    }
                }
                break;
                
            case "1notok":
                // '1' value result is not OK
                for ($idx = $pos_l; $idx <= $pos_h; $idx++)
                {
                    $val = $this->vals[$idx];
                    
                    if (FltEquals($val, 1, $this->frac_prec))
                    {
                        throw new Exception(sprintf("value not acceptable: answer (%s @ index %d) must NOT be ONE", 
                                $this->vals[$idx], $idx));
                    }
                }
                break;
                
            case "tens":
				//for ($idx = $pos_l; $idx <= $pos_h; $idx++)
				//{
				//	$check_in_range[$idx] = 0;
				//}
				
                // calculus values may only be powers of ten
                for ($idx = $pos_l; $idx <= $pos_h; $idx++)
                {
                    $val = $this->vals[$idx];
                    $unit = ($val % 10);
                    $decades = $val - $unit;

                    if (!FltEquals($unit, 0, $this->frac_prec))
                    {
                        throw new Exception(sprintf("value not acceptable: answer (%s @ index %d) must be a rounded decade (e.g. %s)", 
                                $this->vals[$idx], $idx, $decades));
                    }
                }
                break;
                
            case "units":
				for ($idx = $pos_l; $idx <= $pos_h; $idx++)
				{
					$check_in_range[$idx] = 0;
				}
				
                // lvalues may not contain decades
                for ($idx = $pos_l; $idx <= $pos_h; $idx++)
                {
                    $val = $this->vals[$idx];
                    // $unit = ($val % 10);
                    // $decades = $val - $unit;

                    if ($val >= 10 || $val <= -10)
                    {
                        throw new Exception(sprintf("value not acceptable: answer (%s @ index %d) must be a unit value (i.e. in the EXCLUSIVE range -10 .. +10)", 
                                $this->vals[$idx], $idx));
                    }
                }
                break;
                
            case "10orU":
				//for ($idx = $pos_l; $idx <= $pos_h; $idx++)
				//{
				//	$check_in_range[$idx] = 0;
				//}
				
                // lvalues may not contain decades OR may only be decades: decide which way to go by random choice
                for ($idx = $pos_l; $idx <= $pos_h; $idx++)
                {
                    $val = $this->vals[$idx];
                    
                    if ($val >= 10 || $val <= -10)
                    {
                        $val %= 10;
                        
                        if (!FltEquals($val, 0, $this->frac_prec))
                        {
                            throw new Exception(sprintf("value not acceptable: answer (%s @ index %d) must be a rounded decade (e.g. %s) OR a unit value (i.e. in the EXCLUSIVE range -10 .. +10)", 
                                    $this->vals[$idx], $idx,
                                    ((int)($this->vals[$idx] / 10)) * 10));
                        }
                    }
					
					if ($val > -10 && $val < 10)
					{
						$check_in_range[$idx] = 0;
                        
                        if ($val < 0 && $this->in_lb[$idx] >= 0)
                        {
                            throw new Exception(sprintf("value not acceptable: tens-or-unit may not be a negative unit value %s as the lower bound %s is positive @ index %d", 
                                    $this->vals[$idx], $this->in_lb[$idx], $idx));
                        }
                        else if ($val > 0 && $this->in_ub[$idx] <= 0)
                        {
                            throw new Exception(sprintf("value not acceptable: tens-or-unit may not be a positive unit value %s as the upper bound %s is negative @ index %d", 
                                    $this->vals[$idx], $this->in_ub[$idx], $idx));
                        }
					}
                }
                break;
                
            case "no10wrap":
                // calculus value may not wrap/overflow to another decade; brute force by filtering: reject anything that does 'overflow'.
                $sum = 0;
                $been_pos = 0;
                $is_wrapped = false;
                for ($i = 0; $i < $this->oc; $i++)
                {
                    $unit = ($this->vals[$i] % 10);
                    $sum += $unit;
                    if ($been_pos > 0 && ($sum < 0 || $sum > 10))
                    {
                        $is_wrapped = true;
                    }
                    else if ($been_pos < 0 && ($sum > 0 || $sum < -10))
                    {
                        $is_wrapped = true;
                    }
                    
                    if ($sum > 0)
                    {
                        $been_pos = 1;
                    }
                    else if ($sum < 0)
                    {
                        $been_pos = -1;
                    }
                }
                if ($is_wrapped)
                {
                    throw new Exception(sprintf("value not acceptable: sum of units (%s) is not allowed to surpass a decade (10)", $sum));
                }
                break;
        
            case "10wrap":
                // calculus value MUST wrap/overflow to another decade; brute force by filtering: reject anything that does NOT 'overflow'.
                $sum = 0;
                $been_pos = 0;
                $is_wrapped = false;
                for ($i = 0; $i < $this->oc; $i++)
                {
                    $unit = ($this->vals[$i] % 10);
                    $sum += $unit;
                    if ($been_pos > 0 && ($sum < 0 || $sum > 10))
                    {
                        $is_wrapped = true;
                    }
                    else if ($been_pos < 0 && ($sum > 0 || $sum < -10))
                    {
                        $is_wrapped = true;
                    }
                    
                    if ($sum > 0)
                    {
                        $been_pos = 1;
                    }
                    else if ($sum < 0)
                    {
                        $been_pos = -1;
                    }
                }
                if (!$is_wrapped)
                {
                    throw new Exception(sprintf("value not acceptable: sum of units (%s) MUST surpass a decade (10)", $sum));
                }
                break;
            }
        }
        
		for ($idx = 0; $idx < $this->oc; $idx++)
		{
			if ($check_in_range[$idx])
			{
				if ($this->vals[$idx] < $this->in_lb[$idx])
				{
					throw new Exception(sprintf("Exercise rejected due to left hand value %s at position %d being below bounds %s.",
										$this->vals[$idx], $idx, $this->in_lb[$idx]));
				}
				else if ($this->vals[$idx] > $this->in_ub[$idx])
				{
					throw new Exception(sprintf("Exercise rejected due to left hand value %s at position %d being beyond above bounds %s.",
										$this->vals[$idx], $idx, $this->in_ub[$idx]));
				}
			}
		}
		
        // and some final sanity checking
		if ($check_in_range[$this->oc])
		{
			if ($this->vals[$this->oc] < $this->out_lb)
			{
				throw new Exception("Exercise rejected due to right hand value below bounds.");
			}
			else if ($this->vals[$this->oc] > $this->out_ub)
			{
				throw new Exception("Exercise rejected due to right hand value beyond (above) bounds.");
			}
		}
		
		if (DebugReporting::$dbg >= 4)
		{
		    printf("<p>CheckInRange: ");
			for ($idx = 0; $idx <= $this->oc; $idx++)
			{
				printf("[%d]:%s \n", $idx, ($check_in_range[$idx] ? "Y" : "n"));
			}
		}
    }
    
    public function CalculateAnswer($up_to_idx)
    {
        $sum = 0;
        for ($i = 0; $i < $up_to_idx; $i++)
        {
            $sum += $this->vals[$i];
        }
        $this->vals[$up_to_idx] = $sum;
    }
    
    public function Produce()
    {
        for ($i = 0; $i < $this->oc; $i++)
        {
            $this->vals[$i] = $this->GenerateValue($i);
        }
        // apply custom filters first
        $this->RestrictExercise();
        $this->CalculateAnswer($this->oc);
        $this->ApplyPostFilter();
    }

    public function GetOperator4Show($idx, $marker)
    {
        return "+";
    }
}






class SubExercise extends AddExercise
{
    public function ApplyPostFilter()
    {
        parent::ApplyPostFilter();

        // subtraction is addition, but just the other way around, so we only need to SWAP input [0] and answer!
        $a = $this->vals[$this->oc];
        $this->vals[$this->oc] = $this->vals[0];
        $this->vals[0] = $a;
            
/*
            // swap new answer with any of lvalues [1..n] so they get a chance at being the answer too.
            // don't care if lvalues are ordered or not; as long as we do not swap back [0] we're fine.
            $pos = (int)($this->rnd->frand() * $this->oc);
            if ($pos > 0)
            {
                $v = $this->vals[$pos];
                $this->vals[$pos] = $this->vals[$this->oc];
                $this->vals[$this->oc] = $v;
            }
*/
    }

    public function GetOperator4Show($idx, $marker)
    {
        return "&minus;";
    }
}







class MultExercise extends AddExercise
{
    public function CalculateAnswer($up_to_idx)
    {
        $sum = 1;
        for ($i = 0; $i < $up_to_idx; $i++)
        {
            $sum *= $this->vals[$i];
        }
        $this->vals[$up_to_idx] = $sum;
    }
    
    protected function PredictLLHValue($ret, $val, $dest, $idx, $zero_ok)
    {
        // correct $ret to improve our chances that the sum $val will become the sum $dest.
        //
        // Note that this also depends largely on the value of the other (previous) left hand terms,
        // so this is a long shot, in reality.
        if (!FltEquals($val, 0, $this->frac_prec))
        {
            $diff = $dest / $val;
            $ret *= $diff;
        }
        return $ret;
    }

    public function GetOperator4Show($idx, $marker)
    {
        return "&times;";
    }
}







// DIVIDE *only* supports 2-operand left-side operations, i.e. NO chains of divisions!
class DivExercise extends MultExercise
{
    public function ExtraConfigChecks()
    {
        if ($this->oc != 2)
        {
            throw new Exception("DIVIDE does not support exercises with more than two operands at the left side");
        }
    }    

    public function ApplyPostFilter()
    {
        parent::ApplyPostFilter();

        // sanity check: cannot divide by zero:
        if (FltEquals($this->vals[1], 0, $this->frac_prec))
        {
            throw new Exception("value not acceptable: divisor may not be ZERO as that would be an illegal calculation");
        }
            
        // division is multiplication, but just the other way around, so we only need to SWAP inputs and answer!
        //
        // a * b = c ==>  b = c / a ==>  c / a = b ==> rotate left all values in there.
        // a * b = c ==>  a = c / b ==>  c / b = a ==> swap [0] and [rval]
        //
        // don't care if lvalues are ordered or not: always take the second case here:

        // second case: swap [0] and [rval]
        $v = $this->vals[0];
        $this->vals[0] = $this->vals[2];
        $this->vals[2] = $v;

/*
        // first case: rotate:
        $v = $this->vals[0];
        $this->vals[0] = $this->vals[2];
        $this->vals[2] = $this->vals[1];
        $this->vals[1] = $v;
*/
    }

    public function GetOperator4Show($idx, $marker)
    {
        return ":";
    }
}



class DivWithRemainderExercise extends DivExercise
{
    // this one is quite blunt: it takes a division without remainder, than adds a 'faked' remainder after the fact.
    //
    // Be aware that a division with remainder requires some special attention in the Show() section too as there are
    // now TWO operands on the right side: the result after division AND the remainder!

    public function ExtraConfigChecks()
    {
        if ($this->oc != 2)
        {
            throw new Exception("DIVIDE WITH REMAINDER does not support exercises with more than two operands at the left side");
        }
    }    

    public function ApplyPostFilter()
    {
        parent::ApplyPostFilter();

        // range of the remainder is (of course) (0..divisor-1); keep negative divisors in mind: remainder is always positive!
        $rv = $this->GetRandom($this->oc + 1);
        $rem_range = abs($this->vals[1]);
        $rem_offset = 0;
    
        // do we also accept the 'zero' remainder? Depends on our styles set!
        foreach($this->st as $st)
        {
            switch ($st)
            {
            default:
                break;
            
            case "norem0":
                // no!
                $rem_range -= 1;
                $rem_offset = 1;
                break;
            }
        }
        
        if ($rem_range <= 0)
        {
            throw new Exception("value not acceptable: cannot produce a remainder for a division by 1");
        }
        
        $rem = (int)($rv * $rem_range + $rem_offset);

        // correct calculation.
        $this->vals[$this->oc + 1] = $rem;
        $this->vals[0] += $rem;
        
        if ($this->vals[0] < $this->out_lb)
        {
            throw new Exception("value not acceptable: first left hand value is below lower OUTPUT bound (division swaps bounds for first LH item)");
        }
        else if ($this->vals[0] > $this->out_ub)
        {
            throw new Exception("value not acceptable: first left hand value is beyond (above) upper OUTPUT bound (division swaps bounds for first LH item)");
        }
    }

    public function Getvalue4Show($idx, $marker)
    {
        if ($idx == $this->oc)
        {
            // print result WITH REMAINDER
            return sprintf("%s (rem: %s)", $this->vals[$idx], $this->vals[$idx + 1]);
        }
        else
        {
            return parent::GetValue4Show($idx, $marker);
        }
    }
}
    




    
class ModuloExercise extends DivWithRemainderExercise
{
    public function Getvalue4Show($idx, $marker)
    {
        if ($idx == $this->oc)
        {
            // print result *AS* REMAINDER
            return sprintf("%s", $this->vals[$idx], $this->vals[$idx + 1]);
        }
        else
        {
            return parent::GetValue4Show($idx, $marker);
        }
    }

    public function GetOperator4Show($idx, $marker)
    {
        return "mod";
    }
}







class PowerExercise extends AddExercise
{
    public function CalculateAnswer($up_to_idx)
    {
		$sum = $this->vals[0];
		for ($i = 1; $i < $up_to_idx; $i++)
		{
			$sum = pow($sum, $this->vals[$i]);
		}
		$this->vals[$up_to_idx] = $sum;
    }
    
    protected function PredictLLHValue($ret, $val, $dest, $idx, $zero_ok)
    {
        // correct $ret to improve our chances that the sum $val will become the sum $dest.
        //
        // Note that this also depends largely on the value of the other (previous) left hand terms,
        // so this is a long shot, in reality.
		if ($dest > 0 && $val > 0 && log($val) != 0)
		{
			$diff = log($dest) / log($val);
			if (DebugReporting::$dbg >= 2)
			{
				printf("<p>POWER: result prediction adjustment: orig: %s, desired: %s (rescale: %s)\n", $val, $dest, $diff);
			}
			$ret *= $diff;
			// adjust the value to stay in range anyhow:
			if ($ret < $this->in_lb[$idx])
			{
				if (DebugReporting::$dbg >= 2)
				{
					printf("<p>POWER: result prediction POST adjustment: orig: %s, desired: %s, lower bound: %s\n", $val, $dest, $this->in_lb[$idx]);
				}
				$ret = $this->in_lb[$idx];
			}
			else if ($ret > $this->in_ub[$idx])
			{
				if (DebugReporting::$dbg >= 2)
				{
					printf("<p>POWER: result prediction POST adjustment: orig: %s, desired: %s, upper bound: %s\n", $val, $dest, $this->in_ub[$idx]);
				}
				$ret = $this->in_ub[$idx];
			}
        }
        return $ret;
    }

    public function ExtraConfigChecks()
    {
        //if ($this->oc != 2)
        //{
        //    throw new Exception("POWER does not support exercises with more than two operands at the left side");
        //}
    }    
    
    public function GetOperator4Show($idx, $marker)
    {
        return "^";
    }

	// override the render routine as powers need some extra HTML fu to show up properly
    public function Show($n, $idx)
    {
        if ($idx == 0)
        {
            printf("%s", $this->Getvalue4ShowFilter(0, $idx));
            for ($i = 1; $i < $this->oc; $i++)
            {
                printf("<sup>%s", $this->Getvalue4ShowFilter($i, $idx));
            }
            for ($i = 1; $i < $this->oc; $i++)
            {
                printf("</sup>");
            }
            printf(" = %s", $this->Getvalue4ShowFilter($this->oc, $idx));
        }
        else
        {
            parent::Show($n, $idx);
        }
    }
}







class RootExercise extends PowerExercise
{
	public function RequiresMathMLForShow()
	{
		return TRUE;
	}
	
    public function ExtraConfigChecks()
    {
        if ($this->oc != 2)
        {
            throw new Exception("ROOT does not support exercises with more than two operands at the left side");
        }
    }    
    
    protected function RestrictExercisePost()
    {
		// when we display the roots in the classic (non-power) form, roots to the zero-th or one-th power are
		// not allowed as those would look, ah, unusual, in that format.
        if (FltEquals($this->vals[1], 0, $this->frac_prec) || FltEquals($this->vals[1], 1, $this->frac_prec))
        {
			// try the first possible 'sensible' power then; we'd fail otherwise anyway.
			$this->vals[1] = 2;
		}
	}
	
    public function ApplyPostFilter()
    {
        parent::ApplyPostFilter();

		// when we display the roots in the classic (non-power) form, roots to the zero-th or one-th power are
		// not allowed as those would look, ah, unusual, in that format.
        if (FltEquals($this->vals[1], 0, $this->frac_prec) || FltEquals($this->vals[1], 1, $this->frac_prec))
        {
            throw new Exception(sprintf("value %s not acceptable: power may not be ZERO or ONE as that would be an 'extremely curious' root calculation",
								$this->vals[1]));
        }
        if (FltEquals($this->vals[0], 0, $this->frac_prec))
        {
            throw new Exception(sprintf("value %s not acceptable: value-to-calculate-root-for may not be ZERO as that would be an illegal root calculation",
								$this->vals[0]));
        }
            
        // a root is the reciprocal of a power: but just the other way around, so we only need to SWAP inputs and answer!
        $v = $this->vals[0];
        $this->vals[0] = $this->vals[2];
        $this->vals[2] = $v;
    }

    public function GetOperator4Show($idx, $marker)
    {
        return "RT";
    }

	// override the render routine as powers need some extra HTML fu to show up properly
    public function Show($n, $idx)
    {
        if ($idx != $this->oc)
        {
			// <math xmlns="http://www.w3.org/1998/Math/MathML">
            printf("\\(");
			
			if (FltEquals($this->vals[1], 2, $this->frac_prec))
			{
				printf("\\sqrt{%s}", 		
						$this->Getvalue4ShowFilter(0, $idx));
			}
			else
			{
				printf("\\sqrt[%s]{%s}", 		
						$this->Getvalue4ShowFilter(1, $idx),
						$this->Getvalue4ShowFilter(0, $idx));
			}
			printf(" = %s\\)", 
				   $this->Getvalue4ShowFilter($this->oc, $idx));
        }
        else
        {
			printf("%s", 
				   $this->Getvalue4ShowFilter($this->oc, $idx));
        }
    }

	// override the render routine as powers need some extra HTML fu to show up properly
    public function ShowMathML($n, $idx)
    {
        if ($idx != $this->oc)
        {
			// <math xmlns="http://www.w3.org/1998/Math/MathML">
            printf( "<math xmlns=\"http://www.w3.org/1998/Math/MathML\" mode=\"inline\">\n  <mrow>\n");
			
			if (FltEquals($this->vals[1], 2, $this->frac_prec))
			{
				printf(  "    <msqrt>\n"
						."	    <mrow>\n"
						."        <mn>%s</mn>\n"
						." 	    </mrow>\n"
						."    </msqrt>\n", 		
						$this->Getvalue4ShowFilter(0, $idx));
			}
			else
			{
				printf(  "    <mroot>\n"
						."	    <mrow>\n"
						."        <mn>%s</mn>\n"
						." 	    </mrow>\n"
						."      <mn>%s</mn>\n"
						."    </mroot>\n", 		
						$this->Getvalue4ShowFilter(0, $idx),
						$this->Getvalue4ShowFilter(1, $idx));
			}
			printf( "    <mo>=</mo>\n"
				   ."    <mn>%s</mn>\n"
				   ."  </mrow>\n"
				   ."</math>\n", 
				   $this->Getvalue4ShowFilter($this->oc, $idx));
        }
        else
        {
			printf("%s", 
				   $this->Getvalue4ShowFilter($this->oc, $idx));
        }
    }
}







class FloatAddExercise extends AddExercise
{
    protected function RoundValue4Quantize($val)
    {
        $ret = floor($val / $this->frac_prec) * $this->frac_prec;

        return $ret;
    }

    protected function ApplyPostFilterExtra($st, $pos_l, $pos_h, $check_in_range)
    {
        switch ($st)
        {
        default:
            break;
            
        case "nofrac1":
            // we do not accept a divisor of '1'!
            for ($idx = $pos_l; $idx <= $pos_h; $idx++)
            {
                $val = $this->vals[$idx];
                
                if (FltEquals((int)$val, $val, $this->frac_prec))
                {
                    throw new Exception(sprintf("value not acceptable: answer (%s @ index %d) must be non-integer value (i.e. [decimal] fraction denominator larger than 1)", 
                            $this->vals[$idx], $idx));
                }
            }
            break;
        }
    }
}

class FloatSubExercise extends SubExercise
{
    protected function RoundValue4Quantize($val)
    {
        $ret = floor($val / $this->frac_prec) * $this->frac_prec;

        return $ret;
    }

    protected function ApplyPostFilterExtra($st, $pos_l, $pos_h, $check_in_range)
    {
        switch ($st)
        {
        default:
            break;
            
        case "nofrac1":
            // we do not accept a divisor of '1'!
            for ($idx = $pos_l; $idx <= $pos_h; $idx++)
            {
                $val = $this->vals[$idx];
                
                if (FltEquals((int)$val, $val, $this->frac_prec))
                {
                    throw new Exception(sprintf("value not acceptable: answer (%s @ index %d) must be non-integer value (i.e. [decimal] fraction denominator larger than 1)", 
                            $this->vals[$idx], $idx));
                }
            }
            break;
        }
    }
}


class FloatMultExercise extends MultExercise
{
    protected function RoundValue4Quantize($val)
    {
        $ret = floor($val / $this->frac_prec) * $this->frac_prec;

        return $ret;
    }

    protected function ApplyPostFilterExtra($st, $pos_l, $pos_h, $check_in_range)
    {
        switch ($st)
        {
        default:
            break;
            
        case "nofrac1":
            // we do not accept a divisor of '1'!
            for ($idx = $pos_l; $idx <= $pos_h; $idx++)
            {
                $val = $this->vals[$idx];
                $val -= (int)$val;
                
                if (FltEquals((int)$val, $val, $this->frac_prec))
                {
                    throw new Exception(sprintf("value not acceptable: answer (%s @ index %d) must be non-integer value (i.e. [decimal] fraction denominator larger than 1)", 
                            $this->vals[$idx], $idx));
                }
            }
            break;
        }
    }
}


class FloatDivExercise extends DivExercise
{
    protected function RoundValue4Quantize($val)
    {
        $ret = floor($val / $this->frac_prec) * $this->frac_prec;

        return $ret;
    }

    protected function ApplyPostFilterExtra($st, $pos_l, $pos_h, $check_in_range)
    {
        switch ($st)
        {
        default:
            break;
            
        case "nofrac1":
            // we do not accept a divisor of '1'!
            for ($idx = $pos_l; $idx <= $pos_h; $idx++)
            {
                $val = $this->vals[$idx];
                $val -= (int)$val;
                
                if (FltEquals((int)$val, $val, $this->frac_prec))
                {
                    throw new Exception(sprintf("value not acceptable: answer (%s @ index %d) must be non-integer value (i.e. [decimal] fraction denominator larger than 1)", 
                            $this->vals[$idx], $idx));
                }
            }
            break;
        }
    }
}









// big chunk of code common to all fraction classes. Due to the lack of support of multiple inheritance
// and my laziness to solve this in a OO-clean way, here's a global function to help them out:

function Fraction_Getvalue4Show($idx, $marker, $val, $oc, $style_arr, $first_denom, $frac_prec)
{
    $ret = Fraction::toFract($val); // convert with highest possible precision NOW

    $split_frac = FALSE;
    $redux = FALSE;

    $pos_l = 0;
    $pos_h = $oc; // span left hand AND right hand item!
    
    foreach($style_arr as $st)
    {
        switch ($st)
        {
        default:
			if ($st[0] == 'p' && FALSE !== strpos("3456789", substr($st, 1)) && strlen($st) == 2)
			{
				// next filters apply to Nth (and subsequent) lvalues only!
				$pos_l = ((int)substr($st, 1)) - 1;
				$pos_h = $oc - 1;
			}
            break;
    
        case "p1":
            // next filters apply to first lvalue only!
            $pos_l = 0;
            $pos_h = 0;
            break;
            
        case "p2":
            // next filters apply to second (and subsequent) lvalues only!
            $pos_l = 1;
            $pos_h = $oc - 1;
            break;
    
        case "pO":
            // next filters apply to answer (right hand) lvalue only!
            $pos_l = $oc;
            $pos_h = $oc;
            break;
    
        case "px":
            // next filters apply to ANY lvalues!
            $pos_l = 0;
            $pos_h = $oc - 1;
            break;
    
        case "redux":
            // the produced fraction MUST be reducible
            if ($idx >= $pos_l && $idx <= $pos_h)
            {
                $redux = TRUE;
            }
            break;
            
        case "split_frac":
            if ($idx >= $pos_l && $idx <= $pos_h)
            {
                $split_frac = TRUE;
            }
            break;
        }
    }

    if ($redux)
    {
        if ($ret->denom != $first_denom)
        {
            $m = $first_denom / $ret->denom;
            
            if (FltEquals((int)$m, $m, $frac_prec))
            {
                $ret->denom *= $m;
                $ret->num *= $m;
            }
        }
    }
    
    if ($ret->denom == 1)
    {
        return sprintf("%s", $ret->num);
    }
    else
    {
        $iv = 0;
        if ($split_frac)
        {
            $iv = (int)$val;
            $ret->num -= $iv * $ret->denom;
            if ($iv < 0)
            {
                $ret->num = -$ret->num;
            }
        }
        if ($iv != 0)
        {
            if ($marker >= $oc && $oc > 1)
            {
                return sprintf("%s %s/%s (= %s/%s)", $iv, $ret->num, $ret->denom, $iv * $ret->denom + $ret->num, $ret->denom);
            }
            else
            {
                return sprintf("%s %s/%s", $iv, $ret->num, $ret->denom);
            }
        }
        else
        {
            return sprintf("%s/%s", $ret->num, $ret->denom);
        }
    }
}





function CheckPrimeMax($val, $prime_max, $frac_prec)
{
    $val = abs($val);
    do
    {
        $d = FALSE;
        for ($i = 2; $i <= $prime_max; $i++)
        {
            $v = $val / $i;
            $iv = (int)$v;
            if (FltEquals($iv, $v, $frac_prec))
            {
                $d = TRUE;
                $val = $iv;
                break;
            }
        }
    } while ($d && $val > $prime_max);
    
    return ($val <= $prime_max);
}
    
    
function CalcGCD($a, $b)
{
    $a = abs($a);
    $b = abs($b);
    while ($b != 0)
    {
        $r = $a % $b;
        $a = $b;
        $b = $r;
    }
    return $a;
}
    
    
function Fraction_QuantizeValue($val, $idx, $frac_prec, &$first_denom, $style_arr, $rnd, $oc)
{
    $ret = Fraction::toFractWithPrecision($val, $frac_prec);
    
    $nofrac1 = FALSE;
    $redux = FALSE;
    
    $prime_max = 0;
    
    if (DebugReporting::$dbg >= 4) printf("fraction = %s/%s (@ prec=%s)\n", $ret->num, $ret->denom, $frac_prec);
    
    $pos_l = 0;
    $pos_h = $oc; // span left hand AND right hand item!
    
    foreach($style_arr as $st)
    {
        switch ($st)
        {
        default:
            if (!strncmp($st, "prime_max=", 10))
            {
                $prime_max = (int)substr($st, 10);
            }
            else if (!strncmp($st, "step=", 5))
            {
                // calculus values may only be values, rounded to a given factor.
                $factor = (float)substr($st, 5);
                if (DebugReporting::$dbg >= 2)
                {
                    printf("<p>fraction step: factor = %s, ret= %s, idx = %d, zero_ok = %d, pos_l = %d, pos_h = %d\n", 
                            $factor, $val, $idx, $zero_ok, $pos_l, $pos_h);
                }
                
                if ($idx >= $pos_l && $idx <= $pos_h)
                {
                    $val = ((int)($val / $factor)) * $factor;
                    if (DebugReporting::$dbg >= 2)
                    {
                        printf("<p>fraction step: corrected ret: %s, factor = %s, idx = %d, zero_ok = %d\n", 
                                $val, $factor, $idx, $zero_ok);
                    }
                    $ret = Fraction::toFractWithPrecision($val, $frac_prec);
                }
            }
			else if ($st[0] == 'T')
			{
				// value may only be one of a specified set
				//
				// originally only intended for [tables of] multiplication, but now usable anywhere.
				
				if ($idx >= $pos_l && $idx <= $pos_h)
				{
					// allow only particular tables of multiplication: set following the T!
					// since the multiplication tables may go BEYOND 10 while we only allow multipliers up to 10,
					// we cannot do this by 'filtering' the currently generated material.
					// Instead, we just pick the table of choice from the list and apply that to lvalue[0].
					//
					// Of course, the answer must be recalculated too!
					$multipliers = explode(',', substr($st, 1));
					$diff = 4000000000;
					$val = $ret->Val();
					foreach($multipliers as $m)
					{
						$delta = abs($val - (int)$m);
						if ($delta < $diff)
						{
							$val = (int)$m;
							$ret->num = $val * $ret->denom;
							$diff = $delta;
						}
					}
					if (DebugReporting::$dbg >= 2)
					{
						printf("<p>T table quatization: delta: %s, idx = %d, val: %s\n", 
								$diff, $idx, $val);
					}
				}
			}
			else if ($st[0] == 'p' && FALSE !== strpos("3456789", substr($st, 1)) && strlen($st) == 2)
			{
				// next filters apply to Nth (and subsequent) lvalues only!
				$pos_l = ((int)substr($st, 1)) - 1;
				$pos_h = $oc - 1;
			}
            break;
    
        case "p1":
            // next filters apply to first lvalue only!
            $pos_l = 0;
            $pos_h = 0;
            break;
            
        case "p2":
            // next filters apply to second (and subsequent) lvalues only!
            $pos_l = 1;
            $pos_h = $oc - 1;
            break;
    
        case "pO":
            // next filters apply to answer (right hand) lvalue only!
            $pos_l = $oc;
            $pos_h = $oc;
            break;
    
        case "px":
            // next filters apply to ANY lvalues!
            $pos_l = 0;
            $pos_h = $oc - 1;
            break;
    
        case "nofrac1":
            // we do not accept a divisor of '1'!
            if ($idx >= $pos_l && $idx <= $pos_h)
            {
                $nofrac1 = TRUE;
            }
            break;
            
        case "redux":
            // the produced fraction MUST be reducible
            if ($idx >= $pos_l && $idx <= $pos_h)
            {
                $redux = TRUE;
            }
            break;
            
        case "1denom":
            // only permit one (common) denominator: take the denom of lvalue[0] and apply to all
            if (!$first_denom || $idx == 0)
            {
                $first_denom = $ret->denom;
            }
            else if ($ret->denom != $first_denom)
            {
                // ensure the /value/ of the fraction, which gets its denominator patched, stays close to what it was:
                $rescale = $first_denom / $ret->denom;
                $ret->num = (int)($rescale * $ret->num + 0.5);
                
                $ret->denom = $first_denom;
            }
            break;
        
        case "1denom_M":
            // only permit one (common) denominator: take the denom of lvalue[0] and apply to all
            //
            // same as '1denom' apart from the fact that it also accepts 'non-reduced' fractions with the
            // same denominator, i.e. if denominator has been set to be '8', the fraction '1/2' is okay
            // too as it can be expressed in 8ths: '4/8'.
            //
            // It also picks the denominator at random, while '1denom' just picks the denominator of the 
            // first term.
            if (!$first_denom || $idx == 0)
            {
                // pick a 'common denominator' at random and apply that one to each term as it comes along.
                $m_range = (int)(1.0 / ($frac_prec * $ret->denom));
                
                $m = (int)($d_range * $rnd->frand()) + 1;
                $d = $ret->denom * $m;
                
                if ($prime_max > 0 && !CheckPrimeMax($d, $prime_max, $frac_prec))
                {
                    $d = $ret->denom;
                }
                
                $first_denom = $d;
            }

            $factor = $first_denom / $ret->denom;
            // is our fraction expressible as a $first_denom fraction? if not: tweak!
            if (!FltEquals((int)$factor, $factor, $frac_prec))
            {
                // ensure the /value/ of the fraction, which gets its denominator patched, stays close to what it was:
                $ret->num = (int)($factor * $ret->num + 0.5);
                
                $ret->denom = $first_denom;
            }
            break;
        }
    }
                
    if ($prime_max > 0 && !CheckPrimeMax($ret->denom, $prime_max, $frac_prec))
    {
        if (DebugReporting::$dbg >= 1) printf("<p>boom at checkprimemax (%s/%s @ %s)\n", $ret->num, $ret->denom, $prime_max);
    
        throw new Exception(sprintf("value not acceptable: fraction denominator %s is contains primes beyond the maximum allowed %s", $ret->denom, $prime_max));
    }
    
    if ($redux)
    {
        // ensure the fraction is reducible!
        $gcd = CalcGCD($ret->num, $ret->denom);
        if ($gcd <= 1)
        {
            $m = 1;
            if (!$first_denom || $idx == 0)
            {
                $m_range = (int)(1.0 / ($frac_prec * $ret->denom)) - 1;
                
                $m = 2 + (int)($m_range * $rnd->frand());
                
                $first_denom = $m * $ret->denom;
            }
            if ($m <= 1)
            {
                if (DebugReporting::$dbg >= 1) printf("<p>boom at GCD (%s/%s @ %s / first=%s / idx = %s)\n", $ret->num, $ret->denom, $m, $first_denom, $idx);
    
                throw new Exception(sprintf("value not acceptable: fraction %s/%s is not expandible nor reducible", $ret->num, $ret->denom));
            }
        }
    }        

    return $ret->Val();
}







class FractionAddExercise extends FloatAddExercise
{
    protected $first_denom;
    
    public function ExtraConfigChecks()
    {
        if ($this->frac_prec >= 1 || $this->frac_prec < 0)
        {
            throw new FatalException(sprintf("Cannot produce fractions when decimal tolerance (%f) is <= 0 or > 1)", $this->frac_prec));
        }        
    }    
    
    public function QuantizeValue($val, $idx)
    {
        return Fraction_QuantizeValue($val, $idx, $this->frac_prec, $this->first_denom, $this->st, $this->rnd, $this->oc);
    }

    public function Getvalue4Show($idx, $marker)
    {
        return Fraction_Getvalue4Show($idx, $marker, $this->vals[$idx], $this->oc, $this->st, $this->first_denom, $this->frac_prec);
    }
}

class FractionSubExercise extends FloatSubExercise
{
    protected $first_denom;
    
    public function ExtraConfigChecks()
    {
        if ($this->frac_prec >= 1 || $this->frac_prec < 0)
        {
            throw new FatalException(sprintf("Cannot produce fractions when decimal tolerance (%f) is <= 0 or > 1)", $this->frac_prec));
        }        
    }    
    
    public function QuantizeValue($val, $idx)
    {
        return Fraction_QuantizeValue($val, $idx, $this->frac_prec, $this->first_denom, $this->st, $this->rnd, $this->oc);
    }

    public function Getvalue4Show($idx, $marker)
    {
        return Fraction_Getvalue4Show($idx, $marker, $this->vals[$idx], $this->oc, $this->st, $this->first_denom, $this->frac_prec);
    }
}

class FractionMultExercise extends FloatMultExercise
{
    protected $first_denom;
    
    public function ExtraConfigChecks()
    {
        if ($this->frac_prec >= 1 || $this->frac_prec < 0)
        {
            throw new FatalException(sprintf("Cannot produce fractions when decimal tolerance (%f) is <= 0 or > 1)", $this->frac_prec));
        }        
    }    
    
    public function QuantizeValue($val, $idx)
    {
        return Fraction_QuantizeValue($val, $idx, $this->frac_prec, $this->first_denom, $this->st, $this->rnd, $this->oc);
    }

    public function Getvalue4Show($idx, $marker)
    {
        return Fraction_Getvalue4Show($idx, $marker, $this->vals[$idx], $this->oc, $this->st, $this->first_denom, $this->frac_prec);
    }
}

class FractionDivExercise extends FloatDivExercise
{
    protected $first_denom;
    
    public function ExtraConfigChecks()
    {
        if ($this->frac_prec >= 1 || $this->frac_prec < 0)
        {
            throw new FatalException(sprintf("Cannot produce fractions when decimal tolerance (%f) is <= 0 or > 1)", $this->frac_prec));
        }        
    }    
    
    public function QuantizeValue($val, $idx)
    {
        return Fraction_QuantizeValue($val, $idx, $this->frac_prec, $this->first_denom, $this->st, $this->rnd, $this->oc);
    }

    public function Getvalue4Show($idx, $marker)
    {
        return Fraction_Getvalue4Show($idx, $marker, $this->vals[$idx], $this->oc, $this->st, $this->first_denom, $this->frac_prec);
    }
}





?>


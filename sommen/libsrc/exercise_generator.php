<?php

if (!defined("EDUCATION_WEBSITE"))
{
   die("Don't waste your time trying to access this file");
}


require("libsrc/random_generator.php");
require("libsrc/fraction.php");
require("libsrc/exercises.php");





class FatalException extends Exception
{
}



class ExerciseGenerator extends DebugReporting
{
    protected $rnd;
    protected $exes;
    protected $ex_cnt;
    protected $operator_count;
    protected $shuffled_repeat_count;
    protected $disp_cols;
    protected $chapter;
    protected $chapter_format;
    protected $show_ex;
	protected $needs_mathML;
    protected $show_ans;
    protected $show_stats;
    protected $show_command_copy;
    protected $try_count4stats;
    protected $success_count4stats;
    protected $repeat_count4stats;
    protected $dupe_count4stats;
                    
    protected $try_single_limit;

    protected $styles;
    protected $raw_styles;
    protected $style_lines;
    protected $styles_count;
    protected $style_failures;
    protected $failed_styles;
    protected $failed_styles_count;

    protected $style_lines_collective;

    protected $description;
    protected $language;

    public function __construct($config_data)
    {
		$this->needs_mathML = FALSE;
        $this->rnd = new RandomGen(1);
        $this->exes = array();
        $this->ex_cnt = 64;
        $this->operator_count = 1;
        $this->disp_cols = 4;
        $this->chapter = 666;
        $this->chapter_format = "%d";
        $this->show_ex = TRUE;
        $this->show_ans = TRUE;
        $this->show_stats = TRUE;
        $this->show_command_copy = TRUE;
        $this->shuffled_repeat_count = 0;
        $this->try_count4stats = 0;
        $this->success_count4stats = 0;
        $this->repeat_count4stats = 0;
        $this->dupe_count4stats = 0;
                    
        $this->try_single_limit = 40;
        
        $this->styles = array();
        $this->raw_styles = array();
        $this->style_lines = array();
        $this->styles_count = 0;

        $this->style_failures = array();
        $this->failed_styles = array();
        $this->failed_styles_count = 0;
        
        $this->description = array();
        $this->language = "en";
        $this->description["en"] = "";
     
        $this->Configure($config_data);
    }

    public function __destruct()
    {
    }

    
    public function DoesShowNeedMathJax()
    {
        return $this->needs_mathML && count($this->exes) > 0;
    }
    
    public function IsShowExercisesEnabled()
    {
        return $this->show_ex && count($this->exes) > 0;
    }
    
    public function IsShowAnswersEnabled()
    {
        return $this->show_ans && count($this->exes) > 0;
    }
    
    public function IsShowStatisticsEnabled()
    {
        return $this->show_stats;
    }
    
    public function IsShowCommandEnabled()
    {
        return $this->show_command_copy;
    }
    
    public function Configure($config_data)
    {
/*
        $seed = (int)("0x" . substr(hash("crc32", $config_data), 1, 7));
        if ($seed == 0)
        {
            $seed = 42;
        }
        $this->rnd->rand_init($seed);
*/
        
        // split config into lines; compress/unify those lines that need this
        $lines = explode("\n", $config_data);
        $line_count = count($lines);
        $template_definition_start_idx = 0;
        for ($i = 0; $i < $line_count; $i++)
        {
            $l = trim($lines[$i]);
            
            if (strlen($l) > 0 && $l[0] != '#')
            {
                // clean up the config data:
                $l = preg_replace("/\s\s+/", " ", $l);  // reduce whitespace to single elements each.

                $l = str_replace(" =", "=", $l);  
                $l = str_replace("= ", "=", $l);  
                $l = str_replace(": ", ":", $l);  
                $l = str_replace(" :", ":", $l);  
            }

            $lines[$i] = $l;
            
            if ($l == "description:" || preg_match("/^description.[a-zA-Z-]+:/", $l))
            {
                // this command should not have its multiline content 'treated' in any way!
                $ended = FALSE;
                $descr_start_idx = $i;
                for ($i++; $i < $line_count; $i++)
                {
                    $l = trim($lines[$i]);
                    
                    if ($l == "+")
                    {
                        $lines[$i] = $l;
                        $ended = TRUE;
                        break;
                    }
                    if ($l == "=")
                    {
                        $lines[$i] = $l;
                        $ended = TRUE;
                        break;
                    }
                    // keep this line as is...
                }
                if (!$ended)
                {
                    throw new FatalException(sprintf("A decscription started at line %d is not properly terminated!", $descr_start_idx));
                }
            }
            else if (!strncmp($l, "styles_def_start:", 17) && $template_definition_start_idx == 0)
            {
                $template_definition_start_idx = $i;
            }
        }
        // or don't we have any styles defined in here?
        if ($template_definition_start_idx == 0)
        {
            $template_definition_start_idx = count($lines);
        }
        
        // now we are here, we've 'treated' all lines like we should AND we know where the first series of template definitions starts too!
        
        // defaults are assumed set; override defaults by items in the config
        //$this->description["en"] = "";
        //$this->styles = array();
        //$this->raw_styles = array();
        //$this->style_lines = array();
        $this->style_lines_collective = array();

        for ($i = 0; $i < count($lines); $i++)
        {
            $l = trim($lines[$i]);
            if (strlen($l) == 0 || $l[0] == '#')
                continue;
                
            if ($l == "description:" || preg_match("/^description.[a-zA-Z-]+:/", $l))
            {
                $desc = "";
                $lang = substr($l, 12, strlen($l) - 12 - 1);
                if (!isset($this->description[$lang]))
                {
                    $this->description[$lang] = "";
                }
                
                for ($i++; $i < count($lines); $i++)
                {
                    if (trim($lines[$i]) == "+")
                        break;
                    if (trim($lines[$i]) == "=")
                    {
                        // this description informs us we should ditch the already existing bits of description:
                        $this->description[$lang] = "";
                        break;
                    }
                    $desc .= $lines[$i] . "\n";
                }
                
                $this->description[$lang] .= $desc . "\n";
            }
            else if (!strncmp($l, "language:", 9))
            {
                $lang = substr($l, 9);
                if (strlen($lang) > 0)
                {
                    $this->language = $lang;
                }
                else
                {
                    throw new Exception(sprintf("Cannot decode language in config line %d: '%s'", $i, $l));
                }
            }
            else if (!strncmp($l, "debug:", 6))
            {
                $d = (int)substr($l, 6);
                if ($d >= 0)
                {
                    DebugReporting::$dbg = $d;

                    if (DebugReporting::$dbg >= 5)
                    {
                        printf("<p>config = </p><pre>%s</pre>\n", $config_data);
                    }
                }
                else
                {
                    throw new Exception(sprintf("Cannot decode debug level in config line %d: '%s'", $i, $l));
                }
            }
            else if (!strncmp($l, "chapter:", 8))
            {
                $ch = (int)substr($l, 8);
                if ($ch > 0)
                {
                    $this->chapter = $ch;
                }
                else
                {
                    throw new Exception(sprintf("Cannot decode chapter number in config line %d: '%s'", $i, $l));
                }
            }
            else if (!strncmp($l, "chapter_format:", 15))
            {
                $cf = substr($l, 15);
                if (strlen($cf) > 0)
                {
                    $this->chapter_format = $cf;
                }
                else
                {
                    throw new Exception(sprintf("Cannot decode chapter format string in config line %d: '%s'", $i, $l));
                }
            }
            else if (!strncmp($l, "preset:", 7))
            {
                // we need to replace preset request with 'the real thing'.
                //
                // as we want to support nested presets, PLUS we wish later lines to have precedence
                // over earlier items, we'll need to 'shift' the array of lines down when inserting the
                // preset data...

                $preset_key = substr($l, 7);
                for ($j = $template_definition_start_idx; $j < count($lines); $j++)
                {
                    if ($lines[$j] == $preset_key)
                    {
                        $j++;
                        break;
                    }
                }
                //  now find the style definition terminator '---'
                for ($k = $j; $k < count($lines); $k++)
                {
                    if ($lines[$k] == "---")
                    {
                        break;
                    }
                }
                if ($j > count($lines))
                {        
                    throw new FatalException(sprintf("Cannot find preset '%s'.", $preset_key));
                }
                $sl = array_slice($lines, $j, $k - $j);
                if (DebugReporting::$dbg >= 3)
                {
                    printf("<p>snippet for style '%s' @ index: %d, len: %d</p><pre>\n", $preset_key, $j, $k - $j);
                    var_dump($sl);
                    printf("</pre>\n");
                }
                
                array_splice($lines, $i + 1, 0, $sl);
            }
            else if (!strncmp($l, "styles_def_start:", 17))
            {
                // mark the start of a preset section: skip until end marker.
                //
                // Note that preset definition sections cannot be nested!
                //
                $start_pos = $i + 1;
                for ($i++; $i < count($lines); $i++)
                {
                    if (!strncmp($lines[$i], "styles_def_end:", 15))
                    {
                        break;
                    }
                }
                
                $this->style_lines_collective = array_merge($this->style_lines_collective, array_slice($lines, $start_pos, $i - $start_pos));
            }
            else if (!strncmp($l, "exercise_count:", 15))
            {
                $ec = (int)substr($l, 15);
                if ($ec > 0)
                {
                    $this->ex_cnt = $ec;
                }
                else
                {
                    throw new Exception(sprintf("Cannot decode exercise count in config line %d: '%s'", $i, $l));
                }
            }
            else if (!strncmp($l, "operator_count:", 15))
            {
                $ec = (int)substr($l, 15);
                if ($ec > 0)
                {
                    $this->operator_count = $ec;
                }
                else
                {
                    throw new Exception(sprintf("Cannot decode operator count in config line %d: '%s'", $i, $l));
                }
            }
            else if (!strncmp($l, "display_columns:", 16))
            {
                $cl = (int)substr($l, 16);
                if ($cl > 0)
                {
                    $this->disp_cols = $cl;
                }
                else
                {
                    throw new Exception(sprintf("Cannot decode display columns count in config line %d: '%s'", $i, $l));
                }
            }
            else if (!strncmp($l, "display_exercises:", 18))
            {
                $de = (int)substr($l, 18);
                $this->show_ex = !!$de;
            }
            else if (!strncmp($l, "display_answers:", 16))
            {
                $da = (int)substr($l, 16);
                $this->show_ans = !!$da;
            }
            else if (!strncmp($l, "display_statistics:", 19))
            {
                $da = (int)substr($l, 19);
                $this->show_stats = !!$da;
            }
            else if (!strncmp($l, "display_command_copy:", 21))
            {
                $da = (int)substr($l, 21);
                $this->show_command_copy = !!$da;
            }
            else if (!strncmp($l, "shuffled_repeat_count:", 22))
            {
                $rc = (int)substr($l, 22);
                if ($rc >= 0)
                {
                    $this->shuffled_repeat_count = $rc;
                }
                else
                {
                    throw new Exception(sprintf("Cannot decode shuffled repeat count in config line %d: '%s'", $i, $l));
                }
            }
            else if (!strncmp($l, "try_single_limit:", 17))
            {
                $tsl = (int)substr($l, 17);
                if ($tsl >= 0)
                {
                    $this->try_single_limit = $tsl;
                }
                else
                {
                    throw new Exception(sprintf("Cannot decode 'try single limit' number in config line %d: '%s'", $i, $l));
                }
            }
            else 
            {
                // assume this is a 'style line': treat it as such
                $st = explode(" ", $l);
                $idx = count($this->styles);
                $this->styles[$idx] = $st;
                $this->raw_styles[$idx] = $l;
                $this->style_lines[$idx] = $i;
                if (DebugReporting::$dbg >= 2) printf("<p>Added style %d: %s</p>\n", $idx, $l);
            }
        }
        
        $this->styles_count = count($this->styles);
        if (DebugReporting::$dbg >= 2) printf("<p>style count = %d</p>\n", $this->styles_count);
        
        // configure random generator to ensure reproducable results.
        // do NOT use the fancy hash scheme above: we don't care what config we're running as long
        // as each chapter WITHIN THAT CONFIG produces different results. And THAT's what the
        // random generator init is for!
        $seed = (int)$this->chapter;
        $this->rnd->rand_init($seed);

        
        // as we sometimes will find duplicates or otherwise failed generated exercises, we try more times 
        // than absolutely necessary.
        //
        // Heuristic: we start out and try the first ($this->try_single_limit) times, if no dice, then quit completely.
        // otherwise, try subsequent stuff a few 100 times each.
        $try_count = $this->ex_cnt * $this->try_single_limit;     // <-- not very useful at this setting now. To Be Re-evaluated...
        $try_lenience_factor = 2;
        $first_try_count = $this->try_single_limit * $this->styles_count * $try_lenience_factor;
        $this->style_failures = array();
        $this->failed_styles = array();
        $this->failed_styles_count = 0;
        for ($i = 0; $i < $this->styles_count; $i++)
        {
            $this->failed_styles[$i] = FALSE;
            $this->style_failures[$i] = 0;
        }
        if (DebugReporting::$dbg >= 2) 
        {
            printf("<p>try_count = %d / first_try_count = %d</p>\n", $try_count, $first_try_count);
        }
        for ($i = 0; $i < $try_count; $i++)
        {
            if ($i >= $first_try_count || $this->failed_styles_count >= $this->styles_count)
            {
                // no dice! Nothing worked out while trying to create the first/next exercise, so 
                // we're PRETTY sure the subsequent exercises won't succeed either...
                if (DebugReporting::$dbg >= 2) 
                {
                    printf("<p>BREAK: try_count = %d / i = %d / first_try_count = %d / failed_styles_count = %d / styles_count = %d</p>\n", $try_count, $i, $first_try_count, $this->failed_styles_count, $this->styles_count);
                }
                break;
            }
            
            // try to create one more exercise for this chapter.
            if ($this->styles_count > 1)
            {
                $st_idx = (int)($this->rnd->frand() * ($this->styles_count - $this->failed_styles_count));
                
                // determine which of the 'still performing well' styles should be picked:
                for ($j = 0; $j < $this->styles_count; $j++)
                {
                    if ($this->failed_styles[$j])
                        continue;
                    if ($st_idx-- == 0)
                    {
                        $st_idx = $j;
                        break;
                    }
                }
            }
            else
            {
                $st_idx = 0;
            }
            $st = $this->styles[$st_idx];

            if (DebugReporting::$dbg >= 2) 
            {
                printf("<p>exe count = %d, i = %d, idx = %d</p>\n", count($this->exes), $i, $st_idx);
                print_r($st);
            }
            
            try
            {
                switch ($st[0])
                {
                // integers
                case "add":
                    $ex = new AddExercise($st, $this->rnd, $this->operator_count);
                    break;
                
                case "sub":
                    $ex = new SubExercise($st, $this->rnd, $this->operator_count);
                    break;
                
                case "mul":
                    $ex = new MultExercise($st, $this->rnd, $this->operator_count);
                    break;
                
                case "div":
                    $ex = new DivExercise($st, $this->rnd, $this->operator_count);
                    break;
                
                case "divmod":
                    $ex = new DivWithRemainderExercise($st, $this->rnd, $this->operator_count);
                    break;

                case "mod":
                    $ex = new ModuloExercise($st, $this->rnd, $this->operator_count);
                    break;

                case "pow":
                    $ex = new PowerExercise($st, $this->rnd, $this->operator_count);
                    break;

                case "root": // roots (square root, etc.) are the reciprocal of powers (= 1 / pow)
                    $ex = new RootExercise($st, $this->rnd, $this->operator_count);
                    break;

                // fractions
                case "fradd":
                    $ex = new FractionAddExercise($st, $this->rnd, $this->operator_count);
                    break;
                
                case "frsub":
                    $ex = new FractionSubExercise($st, $this->rnd, $this->operator_count);
                    break;
                
                case "frmul":
                    $ex = new FractionMultExercise($st, $this->rnd, $this->operator_count);
                    break;
                
                case "frdiv":
                    $ex = new FractionDivExercise($st, $this->rnd, $this->operator_count);
                    break;

                case "frpow":
                    $ex = new FractionPowerExercise($st, $this->rnd, $this->operator_count);
                    break;

                case "frroot": // roots (square root, etc.) are the reciprocal of powers (= 1 / pow)
                    $ex = new FractionRootExercise($st, $this->rnd, $this->operator_count);
                    break;
                
                // floating point (decimal fractions)
                case "fpadd":
                    $ex = new FloatAddExercise($st, $this->rnd, $this->operator_count);
                    break;
                
                case "fpsub":
                    $ex = new FloatSubExercise($st, $this->rnd, $this->operator_count);
                    break;
                
                case "fpmul":
                    $ex = new FloatMultExercise($st, $this->rnd, $this->operator_count);
                    break;
                
                case "fpdiv":
                    $ex = new FloatDivExercise($st, $this->rnd, $this->operator_count);
                    break;

                case "fppow":
                    $ex = new FloatPowerExercise($st, $this->rnd, $this->operator_count);
                    break;

                case "fproot": // roots (square root, etc.) are the reciprocal of powers (= 1 / pow)
                    $ex = new FloatRootExercise($st, $this->rnd, $this->operator_count);
                    break;
                
                // unidentified
                default:
                    throw new FatalException(sprintf("Unidentified/unsupported base style '%s' in style '%s' at line %d", 
                        $st[0], $this->raw_styles[$st_idx], $this->style_lines[$st_idx]));
                    break;
                }

				$this->needs_mathML |= $ex->RequiresMathMLForShow();
				
                // generate an exercise now:
                $ex->Produce();

                // successful attempt at creating an exercise!
                //
                // now make sure the same bloody exercise is not in this list already:
                $found = 0;
                for ($j = 0; $j < count($this->exes); $j++)
                {
                    if ($this->exes[$j]->Compare($ex) == 0)
                    {
                        if (DebugReporting::$dbg >= 2) 
                        {
                            printf("<p>match found: %d = attempt(%d)</p><table><tr>\n", $j, $i);
                            $ex->ShowExercise("");
                            $this->exes[$j]->ShowExercise("");
                            printf("</tr></table>\n");
                        }
                        $found = 1;
                        break;
                    }
                }
                if (!$found)
                {
                    $this->exes[count($this->exes)] = $ex;
                    
                    if (DebugReporting::$dbg >= 2)
                    {
                        printf("<p>PUNCHED: #%d\n", count($this->exes));

                        printf("<table><tr>\n");
                        $ex->ShowExercise("");
                        printf("</tr></table>\n"); 
                    }

                    // when we've got all the exercises we want, quit:
                    if (count($this->exes) == $this->ex_cnt)
                        break;
                        
                    // update the try restriction so we get another 1K attempts at the next round:
                    $first_try_count = $this->try_single_limit * ($this->styles_count - $this->failed_styles_count) * $try_lenience_factor + $i;
                    
                    // reset per-style failure trackers too:
                    for ($j = 0; $j < $this->styles_count; $j++)
                    {
                        $this->style_failures[$j] = 0;
                    }

                    if (DebugReporting::$dbg >= 2) 
                    {
                        printf("<p>RESET for next round: try_count = %d / i = %d / first_try_count = %d / failed_styles_count = %d / styles_count = %d</p>\n", $try_count, $i, $first_try_count, $this->failed_styles_count, $this->styles_count);
                    }
                }
                else
                {
                    $this->dupe_count4stats++;
                    
                    if (DebugReporting::$dbg >= 1) 
                    {
                        printf("<p>Exercise attempt(%d) rejected as it is a duplicate.</p>\n", $i);
                        if (isset($ex))
                        {
                            printf("<table><tr>\n");
                            $ex->ShowExercise("");
                            printf("</tr></table>\n"); 
                        }
                    }
                }
            }
            catch (FatalException $e)
            {
                printf("<p>Exercise attempt(%d) failed. Error: '%s' for style '%s' at line '%d'</p>\n", 
                        $i + 1, $e->getMessage(), $this->raw_styles[$st_idx], $this->style_lines[$st_idx]);
                die();
            }
            catch (Exception $e)
            {
                if (DebugReporting::$dbg >= 1) 
                {
                    printf("<p>Exercise attempt(%d) failed. Error: '%s' for style '%s' at line '%d'</p>\n", 
                            $i + 1, $e->getMessage(), $this->raw_styles[$st_idx], $this->style_lines[$st_idx]);
                    if (isset($ex))
                    {
                        printf("<table><tr>\n");
                        $ex->ShowExercise("");
                        printf("</tr></table>\n"); 
                    }
                }
                
                // update failure counts for this style, so we can see which styles are 'bad' and should be
                // avoided in subsequent runs...
                $this->style_failures[$st_idx]++;
                if ($this->style_failures[$st_idx] >= $this->try_single_limit)
                {
                    // this one is out for the duration
                    $this->failed_styles[$st_idx] = TRUE;
                    $this->failed_styles_count++;

                    if (DebugReporting::$dbg >= 1) 
                    {
                        printf("<h2>Blocked style %d due to excessive failures (%d/%d) (i: %d, max: %d) (%d / %d)</h2>\n", 
                            $st_idx, $this->style_failures[$st_idx], $this->try_single_limit, $i, $first_try_count, $this->failed_styles_count, $this->styles_count);
                    }
                }
            }
        }
        
        $this->try_count4stats = $i;
        $this->success_count4stats = count($this->exes);
        if (count($this->exes) == 0) 
        {
            printf("<p>This tool cannot generate calculus exercises with these settings. Please check your parameters.</p>\n");
        }
        else if (DebugReporting::$dbg >= 1) 
        {
            printf("<p class=\"SmallPrint\">%d attempts were needed to produce %d exercises in this chapter.</p>\n", $this->try_count4stats, $this->success_count4stats);
        }
        
        // do we need to duplicate exercises to fill the required lot?
        if ($this->shuffled_repeat_count > 0 && count($this->exes) < $this->ex_cnt && count($this->exes) >= 20)
        {
            // make sure each exercise is repeated before we do another 'shuffled repeat' round: no use wasting exercises.
            // hence the 'hit[] array in here: keeps track which exercises were copied and who's remaining...
            //
            // Also make sure you don't duplicate the last exercise in there as the new first; though the chance this
            // happens is low, except maybe when very few exercises have been created. In those circumstances, do NOT
            // repeat at all, because then it's way too easy to see the exercises occur multiple times: see the '20' 
            // heuristic check up there in the conditional...
            $count = count($this->exes);
            $hit = array($count);
            $left = 0;
            $margin = 0;
            for ($i = -1; $i < $this->shuffled_repeat_count && count($this->exes) < $this->ex_cnt; )
            {
                if ($left == 0)
                {
                    for ($j = 0; $j < $count; $j++)
                    {
                        $hit[$j] = FALSE;
                    }
                    $left = $count;
                    $margin = (int)($count / 2);
                    $i++;
                }
                $rv = (int)($this->rnd->frand() * $left);
                for ($j = 0; $j < $count; $j++)
                {
                    if (!$hit[$j])
                    {
                        if ($rv-- <= 0)
                        {
                            // see if this exercise is identical to any of the previous $margin ones...
                            // we need to do it this way, explicitly checking the exercises stored in the
                            // preceding bins as we have the same 'should not duplicate nearby exercises'
                            // issue when starting another 'shuffled repeat' cycle - assuming 
                            // shuffled repeat count > 1 of course. :-)
                            $maxpos = count($this->exes);
                            $found = FALSE;
                            for ($k = $maxpos - $margin; $k < $maxpos; $k++)
                            {
                                if (!$this->exes[$k]->Compare($this->exes[$j]))
                                {
                                    // identical: forget this one!
                                    $found = TRUE;
                                    break;
                                }
                            }
                            if (!$found)
                            {
                                $hit[$j] = TRUE;
                                $left--;
                                $margin--;
                                
                                $this->exes[count($this->exes)] = $this->exes[$j];
                            }
                            break;
                        }
                    }
                }
            }
        }
        
        $this->repeat_count4stats = count($this->exes) - $this->success_count4stats;

        return 0;
    }

    
    public function ShowStatistics()
    {
        if ($this->IsShowStatisticsEnabled() || $this->IsShowCommandEnabled())
        {
            printf("<h1>" . $this->Translate("Calculus Exercises; Chapter") . " " . $this->chapter_format . "</h1>\n", $this->chapter);
            printf("<p class=\"CenteredFinePrint\">%s</p>\n", $this->ShowDescription());
        }

        if ($this->IsShowStatisticsEnabled())
        {
            printf("<p class=\"CenteredFinePrint\">%d attempts were needed to produce %d exercises in this chapter, which contains %s additional replicas. Further info: dupes: %d (%.0f%%), other rejects: %d (%.0f%%), success: %d (%.0f%%)</p>\n", 
                    $this->try_count4stats, $this->success_count4stats, 
                    ($this->repeat_count4stats 
                     ? sprintf("%d (%.0f%%)", $this->repeat_count4stats, 100.0 * ((float)$this->repeat_count4stats) / (0.0001 + (float)$this->success_count4stats)) 
                     : "no"),
                    $this->dupe_count4stats, 100.0 * ((float)$this->dupe_count4stats) / (0.0001 + (float)$this->try_count4stats),
                    $this->try_count4stats - $this->dupe_count4stats - $this->success_count4stats, 
                    100.0 * ((float)($this->try_count4stats - $this->dupe_count4stats - $this->success_count4stats)) / (0.0001 + (float)$this->try_count4stats),
                    $this->success_count4stats, 100.0 * ((float)$this->success_count4stats) / (0.0001 + (float)$this->try_count4stats)
                    );
        }
        
        if ($this->IsShowCommandEnabled())
        {
            printf("<h1 class=\"CenteredFinePrint\">Your instructions to re-create the above:</h1>\n");
            printf("<pre class=\"FinePrint\">\n");
            
            printf("# options / global parameters\n");
            printf("description:\n%s\n=\n", htmlentities($this->ShowDescription()));
            printf("debug: %d\n", DebugReporting::$dbg);
            printf("language: %s\n", $this->language);
            printf("chapter: %d\n", $this->chapter);
            printf("chapter_format: %s\n", $this->chapter_format);
            printf("exercise_count: %d\n", $this->ex_cnt);
            printf("display_columns: %d\n", $this->disp_cols);
            printf("display_exercises: %d\n", $this->show_ex);
            printf("display_answers: %d\n", $this->show_ans);
            printf("display_statistics: %d\n", $this->show_stats);
            printf("display_command_copy: %d\n", $this->show_command_copy);
            printf("shuffled_repeat_count: %d\n", $this->shuffled_repeat_count);
            printf("try_single_limit: %d\n", $this->try_single_limit);
            printf("operator_count: %d\n", $this->operator_count);
            printf("\n");
            printf("# styles:\n");
            
            for ($i = 0; $i < count($this->styles); $i++)
            {
                $st = $this->styles[$i];
                for ($j = 0; $j < count($st); $j++)
                {
                    printf("%s ", $st[$j]);
                }
                printf("\n");
            }
            printf("</pre>\n");
        }
    }
    
    
    public function Show()
    {
        if ($this->IsShowExercisesEnabled())
        {
            printf("<h1>" . $this->Translate("Calculus Exercises; Chapter") . " " . $this->chapter_format . "</h1>\n", $this->chapter);
            printf("<p class=\"CenteredFinePrint\">%s</p>\n", $this->ShowDescription());

            printf("<table class=\"sums\">\n");
            printf("<tr class=\"sums_header\">\n");
            for ($i = 0; $i < $this->disp_cols; $i++)
            {
                printf("<th class=\"sum_index\"><p>#</p></th><th class=\"sum_header\"><p>" . $this->Translate("sum") . "</p></th>\n");
            }
            printf("</tr>\n");
            
            // round up to get size for each of N columns:
            $sum_cnt = count($this->exes);
            $max_cnt = (int)(($sum_cnt + $this->disp_cols - 1) / $this->disp_cols);
            $dangling_items = $sum_cnt % $this->disp_cols;   // number of filled slots at partial-empty bottom row
            if ($dangling_items == 0)
            {
                $dangling_items = $this->disp_cols;
            }
            
            for ($i = 0; $i < $max_cnt; $i++)
            {
                printf("<tr class=\"%s\">\n", ($i % 2 == 0 ? "sum_line_odd" : "sum_line_even"));
                
                $adder = $dangling_items;
                for ($cols = 0; $cols < $this->disp_cols; $cols++)
                {
                    $idx = $i + $max_cnt * $cols;
                    if ($adder-- <= 0)
                    {
                        $idx -= $cols - $dangling_items;  // correct index for dangling items at the bottom row: not $max_xnt but $max_cnt-1 then!
                        // also make sure items are not printed in dangling positions, while also printed at the next top row:
                        if ($i == $max_cnt - 1)
                        {
                            $idx = $sum_cnt + 1;
                        }
                    }
                    if ($idx < $sum_cnt)
                    {
                        $this->exes[$idx]->PrepareDisplay();
                        $this->exes[$idx]->ShowExercise($idx);
                    }
                    else
                    {
                        printf("<td class=\"sum_index\"><p>&nbsp;</p></td><td class=\"sum\"><p>&nbsp;</p></td>\n");
                    }
                }
                printf("</tr>\n");
            }
            printf("</table>\n");
        }
    }
        
    public function ShowAnswers()
    {
        if ($this->IsShowAnswersEnabled())
        {
            printf("<h1>" . $this->Translate("Calculus Exercises: Answers for Chapter") . " " . $this->chapter_format . "</h1>\n", $this->chapter);
            printf("<p class=\"CenteredFinePrint\">%s</p>\n", $this->ShowDescription());

            printf("<table class=\"answers\">\n");
            printf("<tr class=\"answers_header\">\n");
            for ($i = 0; $i < $this->disp_cols; $i++)
            {
                printf("<th class=\"answer_index\"><p>#</p></th><th class=\"answer_header\"><p>" . $this->Translate("answer") . "</p></th>\n");
            }
            printf("</tr>\n");
            
            // round up to get size for each of N columns:
            $sum_cnt = count($this->exes);
            $max_cnt = (int)(($sum_cnt + $this->disp_cols - 1) / $this->disp_cols);
            $dangling_items = $sum_cnt % $this->disp_cols;   // number of filled slots at partial-empty bottom row
            if ($dangling_items == 0)
            {
                $dangling_items = $this->disp_cols;
            }
            
            for ($i = 0; $i < $max_cnt; $i++)
            {
                printf("<tr class=\"%s\">\n", ($i % 2 == 0 ? "answer_line_odd" : "answer_line_even"));
                
                $adder = $dangling_items;
                for ($cols = 0; $cols < $this->disp_cols; $cols++)
                {
                    $idx = $i + $max_cnt * $cols;
                    if ($adder-- <= 0)
                    {
                        $idx -= $cols - $dangling_items;  // correct index for dangling items at the bottom row: not $max_xnt but $max_cnt-1 then!
                        // also make sure items are not printed in dangling positions, while also printed at the next top row:
                        if ($i == $max_cnt - 1)
                        {
                            $idx = $sum_cnt + 1;
                        }
                    }
                    if ($idx < $sum_cnt)
                    {
                        $this->exes[$idx]->PrepareDisplay();
                        $this->exes[$idx]->ShowAnswer($idx);
                    }
                    else
                    {
                        printf("<td class=\"answer_index\"><p>&nbsp;</p></td><td class=\"answer\"><p>&nbsp;</p></td>\n");
                    }
                }
                printf("</tr>\n");
            }
            printf("</table>\n");
        }
    }

    protected function ShowDescription()
    {
        if (isset($this->description[$this->language]))
        {
            $descr = $this->description[$this->language];
        }
        else
        {
            $descr = $this->description["en"];
        }
        $descr = str_replace(array("\r\n", "\n", "\r"), "\n", trim($descr));
        $descr = preg_replace('/[ \t][ \t]+/', ' ', $descr);
        $descr = preg_replace('/\n\n+/', "\n<br>&amp;<br>\n", $descr);
        return $descr;
    }
    
    public function ShowStylesAsSelectOptions()
    {
        $line_count = count($this->style_lines_collective);

        $v = "???";
        
        for ($i = 0; $i < $line_count; $i++)
        {
            $l = trim($this->style_lines_collective[$i]);
            if (strlen($l) == 0 || $l[0] == '#')
                continue;
                
            if ($l == "description:" || $l == ("description." . $this->language . ":"))
            {
                $descr = "";
                
                for ($i++; $i < $line_count; $i++)
                {
                    $l = trim($this->style_lines_collective[$i]);
                    
                    if ($l == "+")
                    {
                        break;
                    }
                    if ($l == "=")
                    {
                        break;
                    }

                    $descr .= " " . $l;
                }
                
                //  now find the style definition terminator '---'
                for ($i++; $i < $line_count; $i++)
                {
                    if ($this->style_lines_collective[$i] == "---")
                    {
                        break;
                    }
                }
                
                printf("<option value=\"%s\">\n  %s\n</option>\n", $v, strip_tags($descr));
            }
            else
            {
                $v = $l;
            }
        }
    }

    public function GetLanguage()
    {
        return $this->language;
    }
    
    protected function Translate($msg)
    {
        switch ($this->language)
        {
        case "nl":
            switch ($msg)
            {
            case "Calculus Exercises; Chapter":
                return "Rekenen; Oefening";
                
            case "Calculus Exercises: Answers for Chapter":
                return "Rekenen; antwoorden voor oefening";
                
            case "sum":
                return "som";
                
            case "answer":
                return "antwoord";
                
            default:
                break;
            }
            break;
        
        default:
            break;
        }
        
        return $msg;
    }
}


?>

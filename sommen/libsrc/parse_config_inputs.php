<?php

if (!defined("EDUCATION_WEBSITE"))
{
   die("Don't waste your time trying to access this file");
}





if (DebugReporting::$dbg >= 3)
{
    foreach($_REQUEST as $k => $v)
    {
        printf("<p>_REQUEST['%s'] = '%s'</p>\n", $k, $v);
        if (is_array($v))
        {
            printf("<p>ARRAY:<br />\n");
            foreach($v as $k2 => $v2)
            {
                printf("['%s'] = '%s'<br />\n", $k2, $v2);
            }
            printf("</p>\n");
        }
    }


    foreach($_FILES as $k => $v)
    {
        printf("<p>_FILES['%s'] = '%s'</p>\n", $k, $v);
        if (is_array($v))
        {
            printf("<p>ARRAY:<br />\n");
            foreach($v as $k2 => $v2)
            {
                printf("['%s'] = '%s'<br />\n", $k2, $v2);
            }
            printf("</p>\n");
        }
    }


    if (isset($_FILES['ConfigFile']))
    {
        $fileName = $_FILES['ConfigFile']['name'];
        $tmpName  = $_FILES['ConfigFile']['tmp_name'];
        $fileSize = $_FILES['ConfigFile']['size'];
        $fileType = $_FILES['ConfigFile']['type'];
    }
    else
    {
        $fileName = "";
        $tmpName  = "";
        $fileSize = "";
        $fileType = "";
    }
    
    printf("<p>filename='%s' / '%s' / '%s' / '%s'</p>\n", $fileName, $tmpName, $fileSize, $fileType);

    if(!get_magic_quotes_gpc())
    {
        printf("<p>GPC: filename='%s'</p>\n", $fileName);
    } 
    else 
    {
        if (isset($_REQUEST['uploaded_filenamepath']))
        {
            printf("<p>non-GPC: filename='%s'</p>\n", $_REQUEST['uploaded_filenamepath']);
        }
    }


    $fp = fopen($tmpName, 'r');
    if ($fp != 0)
    {
        $content = fread($fp, $fileSize);
        fclose($fp);
    }
    else
    {
        $content = "bogus\n";
    }


    printf("<p>file content:</p><pre>%s</pre>\n", $content);



    // print_r(hash_algos());


    printf("<p>hash = '%s'</p>\n", hash("crc32", $content));




    printf("<p>Test fractions ...</p>\n");
    Fraction::Test();
}





// collect configuration: load presets, append custom file content. Feed that combined mess to the 'cleaner' and then the generator.
$config = "";

if(isset($_REQUEST['language']))
{
    $language = strip_tags($_REQUEST['language']);
    if (strlen($language) > 0)
    {
        $config .= sprintf("language: %s\n", $language);
    }
}
if(isset($_REQUEST['chapter']))
{
    $chapter = (int)$_REQUEST['chapter'];
    $config .= sprintf("chapter: %d\n", $chapter);
}

if (isset($_REQUEST['ex_cnt']))
{
    $ex_cnt = (int)$_REQUEST['ex_cnt'];
    $config .= sprintf("exercise_count: %d\n", $ex_cnt);
}

if (isset($_REQUEST['op_cnt']))
{
    $op_cnt = (int)$_REQUEST['op_cnt'];
    $config .= sprintf("operator_count: %d\n", $op_cnt);
}

if (isset($_REQUEST['cols']))
{
    $cols = (int)$_REQUEST['cols'];
    $config .= sprintf("display_columns: %d\n", $cols);
}

if (isset($_REQUEST['show_ex']))
{
    $show_ex = (int)$_REQUEST['show_ex'];
    $config .= sprintf("display_exercises: %d\n", $show_ex);
}

if (isset($_REQUEST['show_ans']))
{
    $show_ans = (int)$_REQUEST['show_ans'];
    $config .= sprintf("display_answers: %d\n", $show_ans);
}

if (isset($_REQUEST['show_stats']))
{
    $show_stats = (int)$_REQUEST['show_stats'];
    $config .= sprintf("display_statistics: %d\n", $show_stats);
}

if (isset($_REQUEST['show_cmd']))
{
    $show_cmd = (int)$_REQUEST['show_cmd'];
    $config .= sprintf("display_command_copy: %d\n", $show_cmd);
}

$preset_filepath = "presets.txt";
$fp = fopen($preset_filepath, "r");
if ($fp !== FALSE)
{
    $preset_data = fread($fp, filesize($preset_filepath));
    fclose($fp);
    $preset_data = str_replace("\r", "", $preset_data);  // ditch CR from any CRLF sequences...
}
else
{
    printf("<p>WARNING: cannot load preset file '%s'. Aborting!</p>\n", $preset_filepath);
    die();
}

if (isset($_REQUEST['PresetCfg']))
{
    $presets_coll = $_REQUEST['PresetCfg'];
    
    foreach($presets_coll as $k)
    {
        $k = strip_tags(trim($k));
        if (strlen($k) > 0)
        {
            $config .= sprintf("preset: %s\n", $k);
        }
    }
}

// if file is uploaded, load that one into config as well...
if (isset($_FILES['ConfigFile']))
{
    $fileName = strip_tags($_FILES['ConfigFile']['name']);
    $tmpName  = strip_tags($_FILES['ConfigFile']['tmp_name']);
    $fileSize = strip_tags($_FILES['ConfigFile']['size']);
    $fileType = strip_tags($_FILES['ConfigFile']['type']);

    if (DebugReporting::$dbg >= 3)
    {
        printf("<p>filename='%s' / '%s' / '%s' / '%s'</p>\n", $fileName, $tmpName, $fileSize, $fileType);

        if(!get_magic_quotes_gpc())
        {
            printf("<p>GPC: filename='%s'</p>\n", $fileName);
        } 
        else 
        {
            if (isset($_REQUEST['uploaded_filenamepath']))
            {
                printf("<p>non-GPC: filename='%s'</p>\n", $_REQUEST['uploaded_filenamepath']);
            }
        }
    }

    if (strlen($tmpName) > 0)
    {
        $fp = fopen($tmpName, 'r');
        if ($fp !== FALSE)
        {
            $config .= strip_tags(fread($fp, $fileSize));
            $config .= "\n";   // just to be sure...
            fclose($fp);
        }
        else
        {
            printf("<p>WARNING: cannot open temporary file '%s' which stores the uploaded config file data for file '%s'. Aborting! (%s/%s)</p>\n", $tmpName, $fileName, $fileSize, $fileType);
            die();
        }
    }
}

if (isset($_REQUEST['CustomConfigText']))
{
    $config .= strip_tags($_REQUEST['CustomConfigText']);
    $config .= "\n";   // just to be sure...
}



if (DebugReporting::$dbg >= 5)
{
    printf("<p>config = </p><pre>%s</pre>\n", $config);
}


// append the styles template data to the config so it is self-contained:
$config = "# do not allow our own presets to be overriden!\nstyles_def_start:\n" . $preset_data . "\nstyles_def_end:\n\n" . $config;

if (DebugReporting::$dbg >= 6)
{
    printf("<p>config + presets = </p><pre>%s</pre>\n", $config);
}

?>


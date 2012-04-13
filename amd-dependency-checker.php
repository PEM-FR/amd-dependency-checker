<?php

/**
 * @author PEM
 * Disclaimer : This file is ugly, incomplete, basic...
 * and I know all of that already.
 * If you don't like it don't use it or wait for a cleaner version if any
 * kthxbye :)
 */

// Args should be written like that :
// php amd-dependency-checker.php path=some/path recursive verbose unused
// path=string default to current path
// recursive if not set then false
// verbose if not set then false
// unused if not set then false

// first we ask for a path

$args = $_SERVER["argv"];

$commands = checkInput($args);

if (null != $commands) {
    // now we are ready to start working
    // let's have fun finding all the files to parse
    $files = array();
    getFiles($commands['path'], $files, $commands['recursive']);

    // foreach file we should check if it is an amd one, if so,
    // get the dependency list, and check the code.
    foreach ($files as $file) {
        $handle = fopen($file, 'r');
        $content = fread($handle, filesize($file));
        // check if AMD type
        if (strpos($content, 'define([') !== false) {
            // it's AMD, now let's get the dependencies
            $nbResults = preg_match('/define\(\[([^\]]*)\]/', $content, $tmpDeps);
            $deps = array();
            // a string containing the dependencies of the file
            if ($nbResults > 0) {
                // deps is a string... for now
                $deps = $tmpDeps[1];
                $foundMissing = false;
                if (!!$commands["verbose"]) {
                    echo '-- scanning ' . $file . ' --' . "\n";
                }
                // now let's look for 'new' and 'adopt('
                $nbResults = preg_match_all(
                    '/\.adopt\([^a-zA-Z]*([a-zA-Z\._]+)/',
                    $content,
                    $tmpAdopts
                );
                $adopts = array();
                if ($nbResults > 0) {
                    $adopts = $tmpAdopts[1];
                    // remove duplicates
                    $adopts = array_unique($adopts);
                    // now let's check if they are in the dependencies
                    foreach ($adopts as $adopt) {
                        $conv = str_replace(".", "/", $adopt);
                        if (strpos($deps, $conv) === false) {
                            $foundMissing = true;
                            echo 'in ' . $file . ' => ' . $conv . "\n";
                        }
                    }
                    if (!$foundMissing && !!$commands["verbose"]) {
                        echo '...no this.adopt(*) missing' . "\n";
                    }
                }

                // scanning for news
                $nbResults = preg_match_all(
                    '/new[^a-zA-Z_]+([a-zA-Z_]+\.[a-zA-Z\._]+)/',
                    $content,
                    $tmpNews
                );
                $foundNewMissing = false;
                $news = array();
                if ($nbResults > 0) {
                    $news = $tmpNews[1];
                    // remove duplicates
                    $news = array_unique($news);
                    // now let's check if they are in the dependencies
                    foreach ($news as $new) {
                        $conv = str_replace(".", "/", $new);
                        if (strpos($deps, $conv) === false) {
                            $foundMissing = $foundNewMissing = true;
                            echo 'in ' . $file . ' => ' . $conv . "\n";
                        }
                    }
                    if (!$foundNewMissing && !!$commands["verbose"]) {
                        echo '...no new * missing' . "\n";
                    }
                }

                // scanning 4 suspicious cases of strings like "my.widget.Here"
                $nbResults = preg_match_all(
                    '/["\']{1,1}([a-zA-Z_]+\.[a-zA-Z\._]+)["\']{1,1}/',
                    $content,
                    $tmpSuspicious
                );
                $foundNewMissing = false;
                $suspicious = array();
                if ($nbResults > 0) {
                    $suspicious = $tmpSuspicious[1];
                    // remove duplicates
                    $suspicious = array_unique($suspicious);
                    // now let's check if they are in the dependencies
                    foreach ($suspicious as $suspect) {
                        $conv = str_replace(".", "/", $suspect);
                        if (strpos($deps, $conv) === false) {
                            $foundMissing = $foundNewMissing = true;
                            echo 'in ' . $file . ' ~> ' . $conv . "\n";
                        }
                    }
                    if (!$foundNewMissing && !!$commands["verbose"]) {
                        echo '...no suspicious string found' . "\n";
                    }
                }

                if (!$foundMissing && !!$commands["verbose"]) {
                    echo 'everything SEEMS to be in order' . "\n";
                } elseif (!!$commands["verbose"]) {
                    echo '-- done --' . "\n\n";
                }

                if (!!$commands['unused']) {
                    // now we'll try to identify deps that are no longer in use
                    // we have $suspicious, $news and $adopts
                    $dependencies = explode(',', $deps);
                    $dependencies = array_map('cleanDependencies', $dependencies);
                    // we will do some cascading
                    // first we check the adopts
                    $diff = array_diff($dependencies, $adopts);
                    if (count($diff) > 0) {
                        $diff2 = array_diff($diff, $news);
                        if (count($diff2) > 0) {
                            $diff3 = array_diff($diff2, $suspicious);
                            if (count($diff3) > 0) {
                                foreach ($diff3 as $dif) {
                                    echo 'in ' . $file . ' <= ' . $dif . "\n";
                                }
                            } else {
                                foreach ($diff2 as $dif) {
                                    echo 'in ' . $file . ' <= ' . $dif . "\n";
                                }
                            }
                        } else {
                            foreach ($diff as $dif) {
                                echo 'in ' . $file . ' <= ' . $dif . "\n";
                            }
                        }
                    }
                }
            }

        }
        fclose($handle);
        echo "\n";
    }
}

function cleanDependencies($dep)
{
    $dep = trim($dep);
    $dep = substr($dep, 1, (strlen($dep)-2));
    $dep = str_replace('/', '.', $dep);
    return $dep;
}

function checkInput($args)
{
    $needHelp = array_search("help", $args);

    if ($needHelp !== false) {
        $cmd = (isset($args[($needHelp - 1)]))
            ? $args[($needHelp - 1)]
            : $args[0];
        $pathHelp = "\t\033[1;32m" . 'path=' . "\033[1;36m" . 'string' .
            "\033[0;37m\t" .
            'the relative path where to look for files, ex: /path/to/*.js' .
            "\n";
        $recHelp = "\t\033[1;32m" . 'recursive' . "\033[1;36m" .
            "\033[0;37m\t" .
            'do you want the parser to loop in recursive directories' . "\n";
        $verboseHelp = "\t\033[1;32m" . 'verbose' . "\033[1;36m" .
            "\033[0;37m\t\t" . 'display more information' . "\n";
        $unusedHelp = "\t\033[1;32m" . 'unused' . "\033[1;36m" .
            "\033[0;37m\t\t" . 'will attempt to detect unused dependencies ' .
            "[EXPERIMENTAL]\n";
        switch ($cmd) {
            case 'path':
                echo $pathHelp;
                break;
            case 'recursive':
                echo $recHelp;
                break;
            case 'verbose':
                echo $verboseHelp;
                break;
            case 'unused':
                echo $unusedHelp;
                break;
            default :
                echo 'ADC Command list :' . "\n";
                echo $pathHelp . $recHelp . $verboseHelp . $unusedHelp .
                    "\033[0;37m\n";
        }
        return null;
    }

    $firstVal = array_shift($args);
    $commands = array();

    foreach($args as $command) {
        $keyVal = explode('=', $command);
        // if the =value isn't set, it means it's a boolean and that we want it
        // hence true
        $commands[$keyVal[0]] = ((isset($keyVal[1])) ? $keyVal[1] : true);
    }

    if (!isset($commands["verbose"])) {
        // by default verbose is set to false
        $commands["verbose"] = false;
    }

    if (!isset($commands["path"])) {
        // by default we use current directory and warn the user
        $commands["path"] = "/";
        if (!!$commands["verbose"]) {
            echo "\t\033[1;31m" .
                'Warning : no path specified, current path will be used' .
                "\033[0;37m\n";
        }
    }

    if (!isset($commands["recursive"])) {
        // by default we use current directory and warn the user
        $commands["recursive"] = false;
        if (!!$commands["verbose"]) {
            echo "\t\033[1;31m" .
                'Warning : recursive option undefined, false by default' .
                "\033[0;37m\n";
        }
    }

    if (!isset($commands["unused"])) {
        // by default we use current directory and warn the user
        $commands["unused"] = false;
        if (!!$commands["verbose"]) {
            echo "\t\033[1;31m" .
                'Warning : unused option undefined, false by default' .
                "\033[0;37m\n";
        }
    }
    return $commands;
}

function getFiles($path, &$files, $recursive = false)
{
    if ($recursive) {
        $lookup = substr($path, strrpos($path, "/"));
        $dirPath = substr($path, 0, strrpos($path, "/") + 1) . "*";
        $dirs = glob($dirPath, GLOB_ONLYDIR);
        foreach ($dirs as $dir) {
            if ($dir . "/*" != $dirPath) {
                getFiles($dir . $lookup, $files, $recursive);
            }
        }
    }
    $result = glob($path);
    if ($result !== false) {
        $files = array_merge($files, $result);
    }
}
?>
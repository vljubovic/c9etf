<?php


// Class for reverse reconstructing various versions of file from history

require_once(dirname(__FILE__) . "/../lib/config.php");
require_once(dirname(__FILE__) . "/../lib/webidelib.php");

class Reconstruct
{
    private static $REPLACE_LIMIT = 6; // Maximum number of lines remaining in replace event e.g. hello world
    private static $DEBUG = false;
    
    private $incgoto = false;
    private $stats = null;
    private $file = null;
    private $username, $filename;
    private $lastEvent = 0, $totalEvents = 0, $lastLineAffected = -1, $firstLineAffected = -1;
    private $codeReplace = [], $codeReplaceMatch = [];
    
    public function __construct ($username, $include_goto = false)
        {
            $this->incgoto = $include_goto;
            $this->username = $username;
        }
    public function GetFile () {return $this->file;}
    public function GetStats () {return $this->stats;}
    public function GetTotalEvents () {return $this->totalEvents;}
    public function GetCodeReplaceEvents () {return array ( $this->codeReplace, $this->codeReplaceMatch ); }
    public function GetLastLineAffected () {return $this->lastLineAffected; }
    public function GetFirstLineAffected () {return $this->firstLineAffected; }
    private function UpdateAffectedLines($line) {
        if ($this->firstLineAffected == -1 || $this->firstLineAffected > $line)
            $this->firstLineAffected = $line;
        if ($this->lastLineAffected == -1 || $this->lastLineAffected < $line)
            $this->lastLineAffected = $line;
    }
    public function ReadStats() 
        {
            global $conf_stats_path;
            $userdata = setup_paths($this->username);
            $username_efn = self::escape_filename($this->username);
            $stat_file = $conf_stats_path . "/" . $userdata['efn'] . ".stats";
            $stats = $stats_goto = null;
            
            if (!file_exists($stat_file))
                throw new Exception("Stats file is missing!", 8);
            
            eval(file_get_contents($stat_file));
            $this->stats = $stats;
            if (empty($stats)) $this->stats = $stats_goto;
            if ($this->stats == NULL) {
                $this->stats = array(
                    "global_events" => array(),
                    "last_update_rev" => 0
                );
            }
            // Stats file can reference other files to be included
            if(!$this->incgoto) return;
            foreach ($this->stats as $key => $value)
                if (is_array($value) && array_key_exists("goto", $value)) 
                    {
                        $goto_path = $stat_file = $conf_stats_path . "/" . $value['goto'];
                        $stats_goto = null;
                        eval(file_get_contents($goto_path));
                        if ($stats_goto == null) continue;
                        foreach($stats_goto as $ks => $vs)
                            $this->stats[$ks] = $vs;
                        $stats_goto = null;
                    }
            return $this->stats;
        }

    // Remove events not related to current version of file 
    // (e.g. before last code replace event)
    public static function GetTotalTime ($stats, $path, $deadline)
        {
            if (!array_key_exists('events', $stats) && array_key_exists($path, $stats))
                {
                    $stats[$path] = Reconstruct::GetTotalTime($stats[$path], $path, $deadline);
                    return $stats;
                }
                
            $time = $lastevent = 0;
            $time_limit = 60;
            foreach($stats['events'] as $event)
                {
                    if ($event['time'] > $deadline) break;
                    if ($lastevent != 0) {
                        $dtime = $event['time'] - $lastevent['time'];
                        if ($dtime > $time_limit) $dtime = $time_limit;
                        $time += $dtime;
                    }
                    $lastevent = $event;    
                }
            return $time;
        }

    // Remove all events not within given interval (
    public static function StatsDeadline ($stats, $path, $deadline, $start=0) 
        {
            if (!array_key_exists('events', $stats) && array_key_exists($path, $stats))
                {
                    $stats[$path] = Reconstruct::StatsDeadline($stats[$path], $path, $deadline);
                    return $stats;
                }
            if (!array_key_exists('events', $stats))
                {
                    foreach ($stats as $filename => &$fstats)
                        $fstats = Reconstruct::StatsDeadline($fstats, $path, $deadline);
                    return $stats;
                }
            
            end($stats['events']);
            $end = key($stats['events']);
            for ($i=0; $i<$end; $i++) {
                if (!array_key_exists($i, $stats)) continue;
                if ($stats[$i]['time'] > $deadline) unset($stats[$i]);
                if ($stats[$i]['time'] < $start) unset($stats[$i]);
            }
            return $stats;
        }

    // Remove events not related to current version of file 
    // (e.g. before last code replace event)
    public function GetRelevantStats () 
        {
            $stats = $this->stats[$this->filename];
            
            // Find index of 'created' event
            $idxCreated = $offset = 0;
            for (; $idxCreated < $this->totalEvents; $idxCreated++)
                if ($stats['events'][$idxCreated]['text'] == "created")
                    break;
            
            foreach($this->codeReplace as $idx => $code)
                {
                    if (array_key_exists($idx, $this->codeReplaceMatch))
                        {
                            $this->lastEvent = 0;
                            $this->ReconstructFileForward("+$idx");
                            $before = $this->file;
                            
                            $afterIdx = $this->codeReplaceMatch[$idx];
                            $this->ReconstructFileForward("+" . ($afterIdx+1));
                            $after = $this->file;
                            
                            unset($stats['events'][$afterIdx]['diff']['remove_lines']);
                            unset($stats['events'][$afterIdx]['diff']['add_lines']);
                            unset($stats['events'][$afterIdx]['diff']['change']);
                            
                            // Cleanup diff
                            foreach($before as $key1 => $line1) {
                                foreach($after as $key2 => $line2) {
                                    if ($line1 == $line2) {
                                        unset($before[$key1]);
                                        unset($after[$key2]);
                                        break;
                                    }
                                }
                            }
                            foreach($before as $key1 => &$line1) chop($line1);
                            foreach($after as $key2 => &$line2) chop($line2);
                            
                            if (!empty($before)) $stats['events'][$afterIdx]['diff']['remove_lines'] = $before;
                            if (!empty($after)) $stats['events'][$afterIdx]['diff']['add_lines'] = $after;
                            
                            array_splice($stats['events'], $idx, $afterIdx-$idx);
                        }
                    else
                        {
                            $idx = $idx + 2;
                            $time = $stats['events'][$idx - $offset]['time'];
                            $this->lastEvent = 0;
                            $this->ReconstructFileForward("+$idx");
                            $file = join("", $this->file);
                            $idx -= $offset;
                            
                            array_splice($stats['events'], $idxCreated+1, $idx-1);
                            $offset = $idx-$idxCreated-1;
                            $stats['events'][$idxCreated]['time'] = $time;
                            $stats['events'][$idxCreated]['content'] = $file;
                        }
                }
            
            return $stats;
        }
    public function TryReconstruct($path, $timestamp)
        {
            if (intval($timestamp) < 100 && $timestamp[0] != "+") $timestamp = strtotime($timestamp);
            //list($s, $this->file) = self::reconstruct_file($this->username, $realpath, $c9path, $timestamp);
            // return $s;
            $this->filename = $path;
            if ($this->stats[$this->filename]['stats_version'] == 2)
                return Reconstruct::ReconstructFileForward ($timestamp);
            else
                return Reconstruct::ReconstructFileForwardHack ($timestamp);
        }

    private static function  escape_filename($raw) {
        return preg_replace('/[^A-Za-z0-9_\-]/', '_', $raw);
    }
    private function reconstruct_file($username, $realpath, $c9path, $timestamp) 
        {
            $status = 0;
            if (!array_key_exists($c9path, $this->stats))
                return [-1, ""]; #nema ga u logovima

            if (!file_exists($realpath)) {
                $status = 1;
                $work_file = array();
            } else
                $work_file = file($realpath);

            $file_log = $this->stats[$c9path]['events'];
            $evtcount = count($file_log);
            
            $end = 0;
            if ($timestamp[0] == "+") $end = $evtcount-intval($timestamp);

            // We reconstruct the file backwards from its current state
            for ($i = $evtcount-1; $i >= $end; $i--) {
                //print "$i,";
                if ($file_log[$i]['time'] < $timestamp) break;
                if ($i < -$timestamp) break;
                if ($file_log[$i]['text'] != "modified") continue;

                if (array_key_exists("change", $file_log[$i]['diff']))
                    foreach($file_log[$i]['diff']['change'] as $lineno => $text) {
                        // Editing last line - special case!
                        if ($lineno-1 == count($work_file)) $lineno--;
                        // Since php arrays are associative, we must initialize missing members in correct order
                        if ($lineno-1 > count($work_file)) {
                            if ($lineno == 2) $lineno=1;
                            else {
                                for ($j=count($work_file); $j<$lineno; $j++)
                                    $work_file[$j] = "\n";
                            }
                        }
                        $work_file[$lineno-1] = $text . "\n";
                    }
                if (array_key_exists("add_lines", $file_log[$i]['diff'])) {
                    $offset=1;
                    foreach($file_log[$i]['diff']['add_lines'] as $lineno => $text) {
                        if ($offset == 0 && $lineno == 0) $offset=1;
                        if ($lineno-$offset > count($work_file))
                            for ($j=count($work_file); $j<$lineno-$offset+1; $j++)
                                $work_file[$j] = "\n";
                        array_splice($work_file, $lineno-$offset, 1);
                        $offset++;
                    }
                }
                if (array_key_exists("remove_lines", $file_log[$i]['diff'])) {
                    $offset=-1;
                    foreach($file_log[$i]['diff']['remove_lines'] as $lineno => $text) {
                        if ($lineno+$offset > count($work_file))
                            for ($j=count($work_file); $j<$lineno+$offset+1; $j++)
                                $work_file[$j] = "\n";
                        if ($text == "false" || $text === false) $text = "";
                        array_splice($work_file, $lineno+$offset, 0, $text . "\n");
                    }
                }
            }

            return [$status, join ("", $work_file)];
        }

    public function ReconstructFileForward ($timestamp) 
        {
            if (!array_key_exists($this->filename, $this->stats))
                return false; #nema ga u logovima
            
            $file_log = $this->stats[$this->filename]['events'];
            $this->totalEvents = count($file_log);
            $offset = 0;
            
            $end = $this->totalEvents;
            if ($timestamp[0] == "+") $end = intval($timestamp);
            
            // We will reconstruct the file forwards from initial create
            for ($i=$this->lastEvent; $i<$end; $i++) 
                {
                    if (!array_key_exists($i, $file_log)) continue;
                    if (self::$DEBUG) print "Event: $i\n";
                    if ($timestamp[0] != "+" && $file_log[$i]['time'] > $timestamp) break;
                    if ($i < -$timestamp) break;
                    $this->firstLineAffected = $this->lastLineAffected = -1;
                    
                    if ($file_log[$i]['text'] == "created" && array_key_exists('content', $file_log[$i])) 
                        {
                            $this->file = explode("\n", $file_log[$i]['content']);
                            foreach($this->file as &$line) $line .= "\n";
                            $this->firstLineAffected = 0;
                            $this->lastLineAffected = count($this->file)-1;
                        }
                    
                    if ($file_log[$i]['text'] != "modified") continue;
                    
                    if (array_key_exists("change", $file_log[$i]['diff']))
                            foreach($file_log[$i]['diff']['change'] as $lineno => $text) 
                                {
                                    // Since php arrays are associative, we must initialize missing members in correct order
                                    if ($lineno-1 > count($this->file)) 
                                        for ($j=count($this->file); $j<$lineno; $j++) 
                                            {
                                                 $this->file[$j] = "\n";
                                                 $this->UpdateAffectedLines( $j );
                                            }
                                    $this->file[$lineno-1] = $text . "\n";
                                    $this->UpdateAffectedLines( $lineno - 1 );
                                }
                    
                    $hasRemove = array_key_exists("remove_lines", $file_log[$i]['diff']);
                    $hasAdd = array_key_exists("add_lines", $file_log[$i]['diff']);
                    
                    // Detect code-replace events
                    $isCodeReplace = false;
                    $removeCount = 0;
                    if ($hasRemove) $removeCount = count($file_log[$i]['diff']['remove_lines']);

                    if (count($this->file) - $removeCount < self::$REPLACE_LIMIT )
                        {
                            if ($removeCount > 5) $this->codeReplace[$i-1] = $this->file;
                            $isCodeReplace = true;
                            if (self::$DEBUG) print "CodeReplace: removed $removeCount from ".count($this->file)."\n";
                        }
                    
                    if (array_key_exists("remove_lines", $file_log[$i]['diff'])) 
                        {
                            $to_remove = [];
                            foreach($file_log[$i]['diff']['remove_lines'] as $lineno => $text) 
                                {
                                    if ($lineno < count($this->file))
                                        {
                                            if (self::$DEBUG) 
                                                if ($this->file[$lineno-1] == $text . "\n")
                                                    print "Izbacujem liniju $lineno (ok)\n";
                                                else
                                                    print "Izbacujem liniju $lineno (nije ok)\n - Treba: $text\n - Glasi: " . $this->file[$lineno-1] . "\n";
                                            $to_remove[] = $lineno;
                                        }
                                    else {
                                        if (self::$DEBUG) 
                                            print "Izbacujem liniju $lineno (nije ok)  > " . count($this->file) . "\n";
                                    }
                                }
                            $offset = 1;
                            $this->UpdateAffectedLines( $to_remove[0] );
                            foreach($to_remove as $line)
                                array_splice($this->file, $line - $offset++, 1);
                        }
                    
                    if (array_key_exists("add_lines", $file_log[$i]['diff'])) 
                            foreach($file_log[$i]['diff']['add_lines'] as $lineno => $text) 
                                {
                                    if ($lineno >= count($this->file))
                                        for ($j = count($this->file); $j < $lineno-1; $j++)
                                            $this->file[$j] = "";
                                    array_splice($this->file, $lineno-1, 0, $text . "\n");
                                    $this->UpdateAffectedLines( $lineno - 1 );
                                }
                            
                    // Check if we are infact reverting to an older version (a.k.a accidental delete / reformat events)
                    if ($isCodeReplace)
                        {
                            foreach($this->codeReplace as $eventId => $code) {
                                $linesDiff = Reconstruct::EquivalentCode($code, $this->file);
                                if (self::$DEBUG) print "isCodeReplace Reverted ".$linesDiff." lines, eventid $eventId i $i\n";
                                if ($linesDiff < 3) {
                                    if ($eventId == $i-1)
                                        unset($this->codeReplace[$eventId]); // Reformat event
                                    else
                                        $this->codeReplaceMatch[$eventId] = $i;
                                }
                            }
                        }
                }
            
            $this->lastEvent = $end;
            return true;
        }

    public function ReconstructFileForwardHack ($timestamp) 
        {
            if (!array_key_exists($this->filename, $this->stats))
                return false; #nema ga u logovima
            
            $file_log = $this->stats[$this->filename]['events'];
            $this->totalEvents = count($file_log);
            $offset = 0;
            
            $end = $this->totalEvents;
            if ($timestamp[0] == "+") $end = intval($timestamp);
            
            // We will reconstruct the file forwards from initial create
            for ($i=$this->lastEvent; $i<$end; $i++) 
                {
                    if (!array_key_exists($i, $file_log)) continue;
                    //if (self::$DEBUG) print "Event: $i\n";
                    if ($timestamp[0] != "+" && $file_log[$i]['time'] > $timestamp) break;
                    if ($i < -$timestamp) break;
                    $this->firstLineAffected = $this->lastLineAffected = -1;
                    
                    if ($file_log[$i]['text'] == "created" && array_key_exists('content', $file_log[$i])) 
                        {
                            $this->file = explode("\n", $file_log[$i]['content']);
                            foreach($this->file as &$line) $line .= "\n";
                            $this->firstLineAffected = 0;
                            $this->lastLineAffected = count($this->file) - 1;
                        }
                    
                    if ($file_log[$i]['text'] != "modified") continue;
                    
                    if (array_key_exists("change", $file_log[$i]['diff']))
                            foreach($file_log[$i]['diff']['change'] as $lineno => $text) 
                                {
                                    // Editing last line - special case!
                                    if ($lineno-1 == count($this->file)) $lineno--;
                                    // Since php arrays are associative, we must initialize missing members in correct order
                                    if ($lineno-1 > count($this->file)) 
                                        {
                                            if ($lineno == 2) $lineno=1;
                                            else {
                                                    for ($j=count($this->file); $j<$lineno; $j++)
                                                            $this->file[$j] = "\n";
                                            }
                                        }
                                    $this->file[$lineno-1] = $text . "\n";
                                    $this->UpdateAffectedLines( $lineno - 1 );
                                }
                    
                    $hasRemove = array_key_exists("remove_lines", $file_log[$i]['diff']);
                    $hasAdd = array_key_exists("add_lines", $file_log[$i]['diff']);
                    
                    // Detect code-replace events
                    $isCodeReplace = false;
                    $removeCount = 0;
                    if ($hasRemove) $removeCount = count($file_log[$i]['diff']['remove_lines']);

                    if (count($this->file) - $removeCount < self::$REPLACE_LIMIT )
                        {
                            if ($removeCount > 5) $this->codeReplace[$i-1] = $this->file;
                            $isCodeReplace = true;
                            if (self::$DEBUG) print "CodeReplace: removed $removeCount from ".count($this->file)."\n";
                        }
                    
                    // Create a combined sorted array
                    $lines = $retry_delete = array();
                    if ($hasRemove)
                        foreach($file_log[$i]['diff']['remove_lines'] as $lineno => $text)
                            $lines[$lineno][] = "-".$text;
                    if ($hasAdd)
                        foreach($file_log[$i]['diff']['add_lines'] as $lineno => $text)
                            $lines[$lineno][] = "+".$text;
                    
                    ksort($lines);
                    
                    $offset = -1; $lineRemoved=-1; $retryOffset = 0;
                    foreach($lines as $lineno => $spec) {
                        foreach($spec as $entry) {
                            $text = substr($entry,1) . "\n";
                            
                            if ($entry[0] == '-' && $lineRemoved == $lineno-1) $offset--; // Contiguous removal
                            
                            if ($entry[0] == '-' && $lineno+$offset < count($this->file) && $this->file[$lineno+$offset] == $text) {
                                if (self::$DEBUG) print "Izbacujem liniju $lineno (ok) - offset $offset\n";
                                array_splice($this->file, $lineno+$offset, 1);
                                $lineRemoved=$lineno;
                                
                                // Ako je izbačena pretposljednja linija u fajlu, a posljednja je prazna, trebalo je i nju izbaciti (bug u svn logu)
                                if ($lineno+$offset == count($this->file)-1 && $this->file[$lineno+$offset] == "\n")
                                    array_splice($this->file, $lineno+$offset, 1);

                            } else if ($entry[0] == '-') {
                                if (self::$DEBUG) print "Izbacivanje nije ok ($lineno treba biti '".chop($text)."' a glasi '".chop($this->file[$lineno+$offset])."') - offset $offset\n";
                                if (array_key_exists($lineno+$offset-1, $this->file) && $this->file[$lineno+$offset-1] == $text) {
                                    if (self::$DEBUG) print "Korigujem -1\n";
                                    $offset--;
                                    array_splice($this->file, $lineno+$offset, 1);
                                }
                                else if (array_key_exists($lineno+$offset+1, $this->file) &&  $this->file[$lineno+$offset+1] == $text) {
                                    if (self::$DEBUG) print "Korigujem +1\n";
                                    $offset++;
                                    array_splice($this->file, $lineno+$offset, 1);
                                }
                                else {
                                    if ($lineRemoved == $lineno-1) {
                                       if (self::$DEBUG) print "Svejedno izbacujem jer je contiguous\n"; 
                                       array_splice($this->file, $lineno+$offset, 1);
                                       $lineRemoved=$lineno;
                                    }
                                    $retry_delete[] = array($lineno, $text);
                                    $retryOffset = $offset;
                                }
                            } else {
                                if ($lineno+$offset < 0) $offset = -$lineno;
                                if (self::$DEBUG) print "Ubacujem liniju $lineno ('".chop($text)."')\n";
                                if (empty($this->file)) $this->file = [];
                                array_splice($this->file, $lineno+$offset, 0, $text);
                                $lineRemoved=-1;
                            }
                                    
                            $this->UpdateAffectedLines( $lineno + $offset );
                        }
                    }
                    
                    // Now retry failed deletes
                    // But also!
                    /*$offset = $retryOffset;
                    foreach($retry_delete as $key => $entry) {
                        $lineno=$entry[0]; $text = $entry[1];
                        if (array_key_exists($lineno+$offset, $this->file) && $this->file[$lineno+$offset] == $text) {
                            if (self::$DEBUG) print "Ponavljam $lineno ('" . chop($text) . "') bez korekcije\n";
                            unset($retry_delete[$key]);
                        } else {
                            if (self::$DEBUG) print "Brisem liniju $lineno iako ('" . chop($text) . "') nije jednako ('" . chop($this->file[$lineno+$offset]) . "')\n";
                        }
                        array_splice($this->file, $lineno+$offset, 1);
                        $offset--;
                    }*/
                    
                    $offset = $retryOffset;
                    foreach($retry_delete as $entry) {
                        $lineno=$entry[0]; $text = $entry[1];
                        $newoffset = 0;
                        while(true) {
                            $try = $lineno+$offset+$newoffset;
                            if (array_key_exists($try, $this->file) && $this->file[$try] == $text) {
                                $offset += $newoffset;
                                if (self::$DEBUG) print "Ponavljam $lineno ('" . chop($text) . "'), korigujem $offset\n";
                                array_splice($this->file, $lineno+$offset, 1);
                                $offset--; // We just deleted a line
                                break;
                            }
                            $try = $lineno+$offset-$newoffset;
                            if (array_key_exists($try, $this->file) && $this->file[$try] == $text) {
                                $offset -= $newoffset;
                                if (self::$DEBUG) print "Ponavljam $lineno ('" . chop($text) . "'), korigujem $offset\n";
                                array_splice($this->file, $lineno+$offset, 1);
                                $offset--; // We just deleted a line
                                break;
                            }
                            $newoffset++;
                            if ($lineno+$offset-$newoffset < 0 && $lineno+$offset+$newoffset >= count($this->file)) {
                                if (self::$DEBUG) print "Tekst $lineno ('" . chop($text) . "') nije pronađen nigdje!\n";
                                break;
                            }
                        }
                    }
                    
                    // Is file empty now?
                    if ($isCodeReplace && count($this->file) == 2 && empty(chop($this->file[1])))
                        array_splice($this->file, 1, 1);
                            
                    // Check if we are infact reverting to an older version (a.k.a accidental delete / reformat events)
                    if ($isCodeReplace)
                        {
                            foreach($this->codeReplace as $eventId => $code) {
                                $linesDiff = Reconstruct::EquivalentCode($code, $this->file);
                                if (self::$DEBUG) print "isCodeReplace Vraćen broj ".$linesDiff." eventid $eventId i $i\n";
                                if ($linesDiff < 3) {
                                    if ($eventId == $i-1)
                                        unset($this->codeReplace[$eventId]); // Reformat event
                                    else
                                        $this->codeReplaceMatch[$eventId] = $i;
                                }
                            }
                        }
                      
                }
            
            $this->lastEvent = $end;
            return true;
        }

    // Check if two blocks of code are equivalent in case of reformat
    public static function EquivalentCode ($code1, $code2)
        {
            $code1 = Reconstruct::CleanupCode($code1);
            $code2 = Reconstruct::CleanupCode($code2);
            foreach($code1 as $key1 => $line1) 
                {
                    foreach($code2 as $key2 => $line2)
                        if ($line1 == $line2)
                            {
                                unset($code1[$key1]);
                                unset($code2[$key2]);
                                break;
                            }
                    
                }
            return count($code1) + count($code2);
        }

    // Cleanup a block of code to detect reformat event
    public static function CleanupCode ($code)
        {
            foreach($code as $key => &$line) 
                {
                    $line = Reconstruct::CleanupLineReformat($line);
                    if (empty($line)) unset($code[$key]);
                }
            return $code;
        }

    // Cleanup string line to detect reformat event
    private static function CleanupLineReformat ($txt)
        {
            $txt = str_replace("{", "", $txt);
            $txt = str_replace("}", "", $txt);
            $txt = preg_replace("/\s+/", " ", $txt);
            $txt = preg_replace("/(\w) (\W)/", "$1$2", $txt);
            $txt = preg_replace("/(\W) (\w)/", "$1$2", $txt);
            $txt = preg_replace("/(\W) (\W)/", "$1$2", $txt);
            return trim($txt);
        }
}

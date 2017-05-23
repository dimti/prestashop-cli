<?php

class psUpdate extends stdClass {

    static $update;

    public function decode($var) {        
        if (psOut::$base64) {
            $var=base64_decode($var);
        } else {
	    $var=self::stripslashes($var);
	}
	if (psOut::$htmlescape) {
            $var=htmlspecialchars_decode($var);
        }
	return($var);
    }

    public function stripslashes($str) {
        $ret=$str;
        return(stripcslashes($str));
    }

    public function create($fstr) {
        $update = Array();
        if ($fstr) {
            if (!is_array($fstr)) {
                $fstr = Array($fstr);
            }
        } else {
            return(false);
        }
        foreach ($fstr as $f) {
            if (preg_match("/(\w*)\+=(.*)/", $f)) {
                preg_match("/(\w*)\+=(.*)/", $f, $farr);
                $update["plus"][$farr[1]] = $farr[2];
            } elseif (preg_match("/(\w*)\*=(.*)/", $f)) {
                preg_match("/(\w*)\*=(.*)/", $f, $farr);
                $update["multiply"][$farr[1]] = $farr[2];
            } elseif (preg_match("/(\w*)\.=(.*)/", $f)) {
                preg_match("/(\w*)\.=(.*)/", $f, $farr);
                $update["rconcat"][$farr[1]] = self::decode($farr[2]);
            } elseif (preg_match("/(\w*)=\.(.*)/", $f)) {
                preg_match("/(\w*)=\.(.*)/", $f, $farr);
                $update["lconcat"][$farr[1]] = self::decode($farr[2]);
            } elseif (preg_match("/(\w*)\~=(.*)/", $f)) {
                preg_match("/(\w*)\~=(.*)/", $f, $farr);
                $update["regexp"][$farr[1]] = self::decode($farr[2]);
            } elseif (preg_match("/(\w*)=(.*)/", $f)) {
                preg_match("/(\w*)=(.*)/", $f, $farr);
                $update["set"][$farr[1]] = self::decode($farr[2]);
            } else {
                psOut::error("Bad update syntax!");
            }
        }
        self::$update = $update;
        return($update);
    }

    public function update($obj) {
        $name = $obj->getName();
        foreach (self::$update as $op => $list) {
            foreach ($list as $prop => $val) {
                if (isset($obj->$prop->language)) {
                    switch ($op) {
                        case "set":
                            self::updateLanguageObj($obj->$prop, $prop, $val);
                            $chg[] = $prop;
                            break;
                        case "lconcat":
                            $old=(string) psGet::getLanguageObj($obj->$prop,$prop);
                            self::updateLanguageObj($obj->$prop, $prop, "$val"."$old");
                            $chg[] = $prop;
                            break;
                        case "rconcat":
                            $old=(string) psGet::getLanguageObj($obj->$prop,$prop);
                            self::updateLanguageObj($obj->$prop, $prop, "$old"."$val")  ;
                            $chg[] = $prop;
                            break;
                        case "regexp":
                            $old=psGet::getLanguageObj($obj->$prop,$prop);
                            List($s,$r)=preg_split("~/~",$val);
                            $val=preg_replace("/$s/",$r,$old);
                            self::updateLanguageObj($obj->$prop, $prop, $val);
                            $chg[] = $prop;
                            break;
                        default:
                            psOut::error("Bad operation on string value!");
                            break;
                    }
                } else {
                    switch ($op) {
                        case "set":
                            $obj->$prop = $val;
                            $chg[] = $prop;
                            break;
                        case "plus":
                            $obj->$prop += $val;
                            $chg[] = $prop;
                            break;
                        case "multiply":
                            $obj->$prop *= $val;
                            $chg[] = $prop;
                            break;
                    }
                }
            }
        }
        return(self::filterProps($obj));
    }
    
    public function filterProps($obj) {
        $name=$obj->getName();
        $dom=dom_import_simplexml($obj);
        if (array_key_exists($name, psCli::$propfeatures)) {
            foreach (psCli::$propfeatures[$name] as $p=>$v) {
                if ($v==psCli::P_BAD || $v==psCli::P_RO) {
                    if ($child = $dom->getElementsByTagName($p)->item(0)) {
                        $child->parentNode->removeChild($child);
                    }
                }
            }
        }
        if ($associations = $dom->getElementsByTagName('associations')->item(0)) {
            $associations->parentNode->removeChild( $associations );
        }
        if ($GLOBALS['object'] == 'manufacturer') {
            if ($link_rewrite = $dom->getElementsByTagName('link_rewrite')->item(0)) {
                $link_rewrite->parentNode->removeChild( $link_rewrite );
            }
        }
        return($obj);
    }

    public function updateLanguageObj($obj, $path, $value) {
        $changed=false;
        if (isset($obj->language)) {
            $i=0;
            foreach ($obj->language as $o) {
                if ((int) $o["id"] == psCli::$lang) {
                    $obj->language[$i]=$value;
                    $changed=true;
                }
                $i++;
            }
            if (!$changed) {
                psOut::error("Bad language object?");
            }
        } else {
                psOut::error("Bad language object?");
        }
    }

}

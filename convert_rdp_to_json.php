<?php
    
    // conversion JSON <> XML
    // http://www.utilities-online.info/xmltojson/#.UVhYU3FsPVM
    
    /**
     * JSON beautifier
     * @param type $json
     * @param type $ret
     * @param type $ind
     * @return string
     */
    function pretty_json($json, $ret= "\n", $ind="\t") {
        $beauty_json = '';
        $quote_state = FALSE;
        $level = 0; 
        $json_length = strlen($json);
        for ($i = 0; $i < $json_length; $i++)
        {                               
            $pre = '';
            $suf = '';
            switch ($json[$i])
            {
                case '"':                               
                    $quote_state = !$quote_state;                                                           
                    break;
                case '[':                                                           
                    $level++;               
                    break;
                case ']':
                    $level--;                   
                    $pre = $ret;
                    $pre .= str_repeat($ind, $level);       
                    break;
                case '{':
                    if ($i - 1 >= 0 && $json[$i - 1] != ',')
                    {
                        $pre = $ret;
                        $pre .= str_repeat($ind, $level);                       
                    }   
                    $level++;   
                    $suf = $ret;                                                                                                                        
                    $suf .= str_repeat($ind, $level);                                                                                                   
                    break;
                case ':':
                    $suf = ' ';
                    break;
                case ',':
                    if (!$quote_state)
                    {  
                        $suf = $ret;                                                                                                
                        $suf .= str_repeat($ind, $level);
                    }
                    break;
                case '}':
                    $level--;   

                case ']':
                    $pre = $ret;
                    $pre .= str_repeat($ind, $level);
                    break;
            }
            $beauty_json .= $pre.$json[$i].$suf;
        }
        return $beauty_json;
    }   
    
    /**
     * Convertie le nom d'une prop rdp dans une prop json
     * @param type $prop
     * @return string
     */
    function convert_prop_to_another($prop) {
        if ($prop == 'POSITION.X') {
            $prop = 'coordx';
        } else if ($prop == 'POSITION.Y') {
            $prop = 'coordy';
        } else if ($prop == 'SOURCE') {
            $prop = 'source';
        } else if ($prop == 'DEST') {
            $prop = 'dest';
        } else if ($prop == 'PLACE2TRANS') {
            $prop = 'place2trans';
        } else if ($prop == 'MARKING') {
            $prop = 'marking';
        } else if ($prop == 'VALUE') {
            $prop = 'value';
        }
        return $prop;
    }
    
    /**
     * Conversion RDP to JSON
     * @param type $file
     * @return type
     */
    function convert($file) {
		
		// max
		$MAX_X = 540;
		$MAX_Y = 340;
		
		// parse RDP
        if (($array = @parse_ini_file($file, true)) == false) {
            echo "Erreur parse_ini_file";
        }
        
		// afficher RDP
		//var_dump($array);
		
		// propriétés valides = PLACE
		$valid_prop_place = array();
		$valid_prop_place[] = 'POSITION.X';
		$valid_prop_place[] = 'POSITION.Y';
		$valid_prop_place[] = 'MARKING';
        
		// propriétés valides = TRANSITION
		$valid_prop_trans = array();
		$valid_prop_trans[] = 'POSITION.X';
		$valid_prop_trans[] = 'POSITION.Y';
		
		// propriétés valides = ARC
		$valid_prop_arc = array();
		$valid_prop_arc[] = 'SOURCE';
		$valid_prop_arc[] = 'DEST';
		$valid_prop_arc[] = 'PLACE2TRANS';
		$valid_prop_arc[] = 'VALUE';
        
		// recherche extreme Y
		$extreme_y = 9999;
		while ($element = current($array)) {
			$key = key($array);
			// parcours prop
			if ($key[0] == 'P' || $key[0] == 'T' || $key[0] == 'A') { 
				if (count($element) > 0) {
					foreach ($element as $prop=>$val) {
						if (in_array($prop, $valid_prop_place)) {
							if ($prop == 'POSITION.Y') {
								if ($val < $extreme_y) {
									$extreme_y = $val;
								}
							}
						}
					}
				}
			}     
			next($array);  
		}
		reset($array);
		// ratio y
		$ratio_y = $MAX_Y/abs($extreme_y);
			
		// recherche extreme x
		$extreme_x = -9999;
		while ($element = current($array)) {
			$key = key($array);
			// parcours prop
			if ($key[0] == 'P' || $key[0] == 'T' || $key[0] == 'A') { 
				if (count($element) > 0) {
					foreach ($element as $prop=>$val) {
						if (in_array($prop, $valid_prop_place)) {
							if ($prop == 'POSITION.X') {
								if ($val > $extreme_x) {
									$extreme_x = $val;
								}
							}
						}
					}
				}
			}
			next($array);       
		}
		reset($array);
		// ratio y
		$ratio_x = $MAX_X/abs($extreme_x);
		
		// tableau de résultats
		$good_array = array();

		$i = 0;
		$j = 0;
		$k = 0;
		$b = false;
		// parcours élements
		while ($element = current($array)) {
			$key = key($array);
			$b = false;
			// parcours prop
			if ($key[0] == 'P') { // place
				if (count($element) > 0) {
					foreach ($element as $prop=>$val) {
						if (in_array($prop, $valid_prop_place)) {
							$prop_good = convert_prop_to_another($prop);
							if ($prop_good == 'coordx') {
								$val = $val * $ratio_x;
							} else if ($prop_good == 'coordy') {
								$val = abs($val) * $ratio_y;
							}
                            if ($prop_good == 'marking') {
                                $good_array['places'][$i]['properties'][$prop_good] = $val;
                                if ($val != 1) {
                                    $good_array['places'][$i]['properties'][$prop_good] = 0;
                                }
                            } else {
                                $good_array['places'][$i][$prop_good] = $val;
                            }
                            $b = true;
						}
					}
					if ($b) {
						$i++;
					}
				}
			} else if ($key[0] == 'T') { // transition
				if (count($element) > 0) {
					foreach ($element as $prop=>$val) {
						if (in_array($prop, $valid_prop_trans)) {
							$prop_good = convert_prop_to_another($prop);
							if ($prop_good == 'coordx') {
								$val = $val * $ratio_x;
							} else if ($prop_good == 'coordy') {
								$val = abs($val) * $ratio_y;
							}
							$good_array['transitions'][$j][$prop_good] = $val;
							$b = true;
						}
					}
					if ($b) {
						$j++;
					}
				}
			} else if ($key[0] == 'A') { // arc
				if (count($element) > 0) {
					foreach ($element as $prop=>$val) {
						if (in_array($prop, $valid_prop_arc)) {
							$prop_good = convert_prop_to_another($prop);
							if ($prop_good == 'coordx') {
								$val = $val * $ratio_x;
							} else if ($prop_good == 'coordy') {
								$val = abs($val) * $ratio_y;
							}                            
                            if ($prop_good == 'value') {
                                $good_array['arcs'][$k]['properties'][$prop_good] = $val;
                                if ($val != 1) {
                                    $good_array['arcs'][$k]['properties'][$prop_good] = 0;
                                }
                            } else {
                                $good_array['arcs'][$k][$prop_good] = $val;
                            }
							$b = true;
						}
					}
					if ($b) {
						$k++;
					}
				}
			}
			next($array);
		}
		reset($array);
		
		// affichage tableau résultats
		//var_dump($good_array);    
		
		// encodage json
		$res_json = json_encode($good_array);
		$res_json = str_replace('"','', $res_json);
        //$res_json_joli = pretty_json($res_json); 
	
		// affichage json
		//echo '<b>Resultat JSON pas joli</b> : <br/><br/>'.$res_json;
		
		// affichage json joli
		//echo '<pre><b>Resultat JSON joli</b> : <br/><br/>'.$res_json_joli.'</pre>';
	
		return $res_json;
	}
?>


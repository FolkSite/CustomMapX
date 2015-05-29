class Gmarker {

	public $cache_opts = array(xPDO::OPT_CACHE_KEY => 'gmarker');
	public $lifetime = 0; // seconds cached lat/lng should live. 0 = forever
	public $json;
	public $modx;
	public $colors = array();
	
	// Google Maps URLs
	public $maps_http = 'http://maps.google.com/maps/api/js?sensor=false';
	public $maps_https = 'https://maps.google.com/maps/api/js?sensor=false';

	// Geocoding URLs
	public $geocoding_http = 'http://maps.googleapis.com/maps/api/geocode/json';
	public $geocoding_https = 'https://maps.googleapis.com/maps/api/geocode/json';

	// Static Maps URLs
	public $static_http = 'http://maps.googleapis.com/maps/api/staticmap?sensor=false';
	public $static_https = 'https://maps.googleapis.com/maps/api/staticmap?sensor=false';



	/**
	 *
	 */
	public function __construct() {
		global $modx;
		$this->modx = $modx;
	}



	/**
	 * Formats a string message for use as a Javascript alert
	 *
	 * @param string  $msg
	 * @return string
	 */
	public function alert($msg) {
		return '<script type="text/javascript"> alert('.json_encode($msg).'); </script>';
	}



	/**
	 * Generate a unique fingerprint of the input parameters that would affect
	 * the results of a lookup.  All properties passed to this should be the props
	 * that would uniquely identify an address, e.g. address, city, state, zip
	 *
	 * @param array
	 * @param unknown $props
	 * @return string
	 */
	public function fingerprint($props) {

		foreach ($props as $k => $v) {
			$v = trim($v);
			$v = preg_replace('/\./', ' ', $v); // periods are not significant
			$v = preg_replace('/\s+,/', ',', $v);
			$v = preg_replace('/,\s+/', ',', $v);
			$v = preg_replace('/\s+/', ' ', $v);
			$props[$k] = $v;
		}

		return md5(print_r($props, true));
	}



	/**
	 * Read item out of Geocoding JSON
	 *
	 * @param unknown $key
	 * @return string
	 */
	public function get($key) {

		switch ($key) {
		case 'formatted_address':
			if (isset($this->json['results'][0]['formatted_address'])) {
				return $this->json['results'][0]['formatted_address'];
			}
			break;
		case 'location.lat':
			if (isset($this->json['results'][0]['geometry']['location']['lat'])) {
				return $this->json['results'][0]['geometry']['location']['lat'];
			}
			else {
				return 0; // include a valid default.
			}
			break;
		case 'location.lng':
			if (isset($this->json['results'][0]['geometry']['location']['lng'])) {
				return $this->json['results'][0]['geometry']['location']['lng'];
			}
			else {
				return 0; // include a valid default.
			}
			break;
		case 'northeast.lat':
			if (isset($this->json['results'][0]['geometry']['viewport']['northeast']['lat'])) {
				return $this->json['results'][0]['geometry']['viewport']['northeast']['lat'];
			}
			break;
		case 'northeast.lng':
			if (isset($this->json['results'][0]['geometry']['viewport']['northeast']['lng'])) {
				return $this->json['results'][0]['geometry']['viewport']['northeast']['lng'];
			}
			break;
		case 'southwest.lat':
			if (isset($this->json['results'][0]['geometry']['viewport']['southwest']['lat'])) {
				return $this->json['results'][0]['geometry']['viewport']['southwest']['lat'];
			}
			break;
		case 'southwest.lng':
			if (isset($this->json['results'][0]['geometry']['viewport']['southwest']['lng'])) {
				return $this->json['results'][0]['geometry']['viewport']['southwest']['lng'];
			}
			break;
		case 'location_type':
			if (isset($this->json['results'][0]['geometry']['location_type'])) {
				return $this->json['results'][0]['geometry']['location_type'];
			}
			break;
		case 'status':
			if (isset($this->json['status'])) {
				return $this->json['status'];
			}
			break;
		}

		return $this->modx->lexicon('node_not_found', array('node'=>$key));
	}

	/**
	 * Gets one color for each distinct input $val.  Used when &group is set.
	 *
	 * @param string $val
	 * @param string $id (optional)
	 * @return string corresponding to HTML color code
	 */
	public function get_color($val, $id=0) {
		$color_set = array('e3947a','e32d7a','b70004','65c2f5','8e6fec', '79f863','f1ee69','BE9073','FB2FC8','90C57F','0AA87B','38BCB9','ACDA3B','39DBF0','9E6CA3','1042F1','48F6A1','5B49CD','B6DE82','5871A7','B2E5A3','D52213','AD54D7','8E3B32','4E0160','F88439','E7460B','F0EBA1','8962BF','A540D0','72E5C8','405EDB','032543','C329B3','FCF922','0FA4BD','354FA8','1019BC','2E5BE6','438921','197238','6D4134','FAB7AD','921536','1EF0E8','8576E1','5B4F2D','A8EB6E','E57611','2858F9','6E53AA','B5B6B4','9B3EDF','21BFE2','771257','22BECC','ED8099');
		if (!isset($this->colors[$val])) {
			$this->colors[$val] = $color_set[$id]; // <-- new random color here
		}

		return $this->colors[$val];
	}

	/**
	 * Get the URL for the Google Maps API service, optionally includin other keys.
	 * See https://developers.google.com/maps/documentation/javascript/tutorial#api_key
	 *
	 * @param array   (optional) any properties to append to the URL
	 * @param boolean (optional) whether or not to use the secure version of the URL
	 * @param unknown $props  (optional)
	 * @param unknown $secure (optional)
	 * @return string the URL of the service
	 */
	public function get_maps_url($props=array(), $secure=true) {
		$url = '';
		if ($secure) {
			$url = $this->maps_https;
		}
		else {
			$url = $this->maps_http;
		}

		foreach ($props as $k => $v) {
			if ($v) {
				$url .= '&'.$k.'='.trim($v);
			}
		}

		return $url;
	}



	/**
	 * Where the magic happens: JSON is loaded either from cache (when possible) or
	 * from a query to the Google Geocoding API. The JSON is then read to be queried
	 * for data via the $this->get() method.
	 *
	 * @props array $props required for a lookup
	 * @props boolean $secure 1 for https, 0 for http
	 * @props boolen $refresh 1 to ignore cache and force api query
	 * @return string JSON data
	 */
	public function lookup($props, $secure=1, $refresh=0) {
		// Fingerprint the lookup
		$fingerprint = $this->fingerprint($props);

		$json = $this->modx->cacheManager->get($fingerprint, $this->cache_opts);

		// if $refresh OR if not fingerprint is not cached, then lookup the address
		if ($refresh || empty($json)) {
			// Perform the lookup
			$json = $this->query_api($props, $secure);

			// Cache the lookup
			$this->modx->cacheManager->set($fingerprint, $json, $this->lifetime, $this->cache_opts);
		}

		$this->set_json($json);

		return $json;
	}



	/**
	 * Hit the Google GeoCoding API service: this function builds the URL
	 * See http://stackoverflow.com/questions/6976446/google-maps-geocode-api-inconsistencies
	 *
	 * @param array   $props  defining the search
	 * @param boolean $secure (optional) whether or not the lookup should use HTTPS
	 * @return string JSON result
	 */
	public function query_api($props, $secure=false) {
		$url = $this->geocoding_http;
		if ($secure) {
			$url = $this->geocoding_https;
		}
		$url .= '?sensor=false';

		// Special cleaning of the address: no extra spaces, then all spaces to +
		$props['address'] = preg_replace('/\s+/', ' ', $props['address']);
		$props['address'] = str_replace(' ', '+', $props['address']);
		
		foreach ($props as $k => $v) {
			if (!empty($v)) {
				//$url .= preg_replace('/\s+/', ' ', "&$k=".trim($v));
				$url .= "&$k=".urlencode($v);
			}
		}

		$this->modx->log(xPDO::LOG_LEVEL_DEBUG, "[Gmarker] query URL: $url");

		return file_get_contents($url);
	}

	/**
	 * Get a random HTML color
	 * 
	 * @return string
	 */
	public function rand_color() {
		$chars = "ABCDEF0123456789";
		$size = strlen( $chars );
		$str = array();

		for ( $j = 0; $j < 6; $j++ ) {
			if (isset($str[$j])) {
				$str[$j] .= $chars[ rand( 0, $size - 1 ) ];
			}
			else {
				$str[$j] = $chars[ rand( 0, $size - 1 ) ];
			}
		}

		return implode('', $str);
	}

	/**
	 * This takes a JSON string, converts it to a PHP array
	 *
	 * @param string  JSON array
	 * @param unknown $json
	 */
	public function set_json($json) {
		$this->json = json_decode($json, true);
	}

}

/*EOF*/

$Gmarker = new Gmarker(); 
$modx->lexicon->load('gmarker:default');

//------------------------------------------------------------------------------
//! Inline Custom Values

$GmarkerUrl = "http://maps.google.com/maps/api/js?sensor=false&key=API_KEY";
$lat_tv = "latitude";
$lng_tv = "longitude";
$formatting_string = "[[+address]], [[+city]], [[+state]]";

//------------------------------------------------------------------------------
//! Read inputs
//------------------------------------------------------------------------------
// Basic controls (only some...)
//$secure = (int) $modx->getOption('secure', $scriptProperties, $modx->getOption('gmarker.secure'));
$headTpl = $modx->getOption('headTpl', $scriptProperties, 'gmarkershead');
$markerTpl = $modx->getOption('markerTpl', $scriptProperties, 'gmarker');
$resultTpl = $modx->getOption('resultTpl', $scriptProperties, 'gresult');
$checkboxTpl = $modx->getOption('resultTpl', $scriptProperties, 'gcheckbox'); 
$outTpl = $modx->getOption('outTpl', $scriptProperties, 'g_out'); 
$showResults = $modx->getOption('showResults', $scriptProperties, 0);
$info = (int) $modx->getOption('info', $scriptProperties, 1);
$infoTpl = $modx->getOption('infoTpl', $scriptProperties, 'ginfo');
$tvPrefix = $modx->getOption('tvPrefix', $scriptProperties, '');
$resources = $modx->getOption('resources', $scriptProperties, '');
$shadow = $modx->getOption('shadow', $scriptProperties, 1);
$suppressLookup = $modx->getOption('suppressLookup', $scriptProperties, 0);
$tpl = $formatting_string;
$drop = $modx->getOption($formatting_string, $scriptProperties, 0);
$marker_color = $modx->getOption('marker_color',$scriptProperties,'FE7569');
$checkbox = $modx->getOption('checkbox', $scriptProperties, 0);
$group = $modx->getOption('group', $scriptProperties, null); // see http://gmap3.net/examples/tags.html
$templates = $modx->getOption('templates',$scriptProperties);
$tvName = $modx->getOption('tvName',$scriptProperties);
$tvValue = $modx->getOption('tvValue',$scriptProperties);
$groupCallback = $modx->getOption('groupCallback',$scriptProperties);
$parents = $modx->getOption('parents',$scriptProperties);

$marker_center = '%E2%80%A2';
$distinct_groups = array(); // init
$parents = (!empty($parents)) ? explode(',', $parents) : array();
$templates = (!empty($templates)) ? explode(',', $templates) : array();
array_walk($parents, 'trim');
array_walk($templates, 'trim');

$LatTV = $modx->getObject('modTemplateVar', array('name'=>$lat_tv));
$LngTV = $modx->getObject('modTemplateVar', array('name'=>$lng_tv));
if (!$LatTV) {
	$modx->log(xPDO::LOG_LEVEL_ERROR, '[Gmarker] '. $modx->lexicon('tv_not_found', array('tv'=> $lat_tv)));
	return $modx->lexicon('tv_not_found', array('tv'=> $lat_tv));
}
if (!$LngTV) {
	$modx->log(xPDO::LOG_LEVEL_ERROR, '[Gmarker] '. $modx->lexicon('tv_not_found', array('tv'=> $lng_tv)));
	return $modx->lexicon('tv_not_found', array('tv'=> $lng_tv));
}
$lat_tv_id = $LatTV->get('id');
$lng_tv_id = $LngTV->get('id');

$tv_filters = array();

// Trigger a query on the modTemplateVarResource (for performance reasons).
if ($tvName && $tvValue) {

	$tvValues = array();
	$tvValues = explode(',',$tvValue);
	array_walk($tvValues, 'trim');
	
	$tv = $modx->getObject('modTemplateVar', array('name'=>$tvName));
	
	if (!$tv) {
		$modx->log(xPDO::LOG_LEVEL_ERROR, '[Gmarker] '. $modx->lexicon('tv_not_found', array('tv'=> $tvName)));
		return $modx->lexicon('tv_not_found', array('tv'=> $tvName));
	}
	
	$criteria['tmplvarid'] = $tv->get('id');
	$criteria['value:IN'] = $tvValues;

	$criteria = $modx->newQuery('modTemplateVarResource', $criteria);
	$tvrs = $modx->getCollection('modTemplateVarResource', $criteria);
	foreach ($tvrs as $tvr) {
		$tv_filters[] = $tvr->get('contentid');
	}
}

// Props that influence the address fingerprint and the Lat/Lng cache
$goog = array();
$goog['address'] = 		$modx->getOption('address', $scriptProperties, '');
$goog['latlng'] = 		$modx->getOption('latlng', $scriptProperties, '');
$goog['bounds'] = 		$modx->getOption('bounds', $scriptProperties, '');
$goog['components'] = 	$modx->getOption('components', $scriptProperties, '');
$goog['region'] = 		$modx->getOption('region', $scriptProperties, '');
$goog['language'] = 	$modx->getOption('language', $scriptProperties, '');

// Props used in the headerTpl
$props = array();
$props['h'] = (int) $modx->getOption('height', $scriptProperties, '');
$props['w'] = (int) $modx->getOption('width', $scriptProperties, '');
$props['id'] = $modx->getOption('id', $scriptProperties, 'map');
$props['class'] = $modx->getOption('class', $scriptProperties);
$props['latlng'] = $modx->getOption('latlng', $scriptProperties, '');
$props['zoom'] = (int) $modx->getOption('zoom', $scriptProperties, 15);
$props['gmarker_url'] = $GmarkerUrl;
$props['type'] = $modx->getOption('type', $scriptProperties, 'ROADMAP');

// Used for search results
$results = '';


// Verify inputs
if (!$props['h']) {
	$props['h'] = 300;
}
if (!$props['w']) {
	$props['w'] = 500;
}

if (empty($goog['address']) && empty($goog['latlng'])) {
	$modx->log(xPDO::LOG_LEVEL_ERROR, '[Gmarker] '. $modx->lexicon('missing_center'));
	return $modx->lexicon('missing_center');
}

// build query
$criteria = array();
if ($parents) {
	$criteria['modResource.parent:IN'] = $parents;
}
if (!empty($templates)) {
	$criteria['modResource.template:IN'] = $templates;
}
if (empty($showDeleted)) {
    $criteria['deleted'] = '0';
}
if (empty($showUnpublished)) {
    $criteria['published'] = '1';
}
if (empty($showHidden)) {
    $criteria['hidemenu'] = '0';
}
if (!empty($hideContainers)) {
    $criteria['isfolder'] = '0';
}
if (!empty($tv_filters)) {
	$criteria['modResource.id:IN'] = $tv_filters;
}


$criteria = $modx->newQuery('modResource', $criteria);



// Handle resources that were specifically included, e.g. &resources=`1,2,3`
// and resources that were specifically omitted, e.g. &resources=`-4,-5,-6`
if (!empty($resources)) {
    $resources = explode(',',$resources);
    $include = array();
    $exclude = array();
    foreach ($resources as $resource) {
        $resource = (int)$resource;
        if ($resource == 0) continue;
        if ($resource < 0) {
            $exclude[] = abs($resource);
        } else {
            $include[] = $resource;
        }
    }
    if (!empty($include)) {
        $criteria->where(array('OR:modResource.id:IN' => $include), xPDOQuery::SQL_OR);
    }
    if (!empty($exclude)) {
        $criteria->where(array('modResource.id:NOT IN' => $exclude), xPDOQuery::SQL_AND, null, 1);
    }
}

if (!empty($limit)) {
	$criteria->limit($limit, $offset);
}

//$pages = $modx->getCollectionGraph('modResource', '{"TemplateVarResources":{"TemplateVar":{}}}', $criteria);
$pages = $modx->getCollection('modResource', $criteria);
//$criteria->prepare();
//return $criteria->toSQL();

// Iterate over markers
$idx = 1;
$letter = 'A';
$props['markers'] = '';
$props['marker_color'] = $marker_color;

foreach ($pages as $p) {
	$prps = $p->toArray();
	$raw_prps = $prps; // we need a version w/o prefixes for Google lookups
	$prps['idx'] = $idx;
	
	// Add TVs
	$val = json_encode($p->getTVValue($lat_tv_id));
	$prps[$tvPrefix.$lat_tv] = $val;
	$raw_prps[$lat_tv] = $val;
	$val = json_encode($p->getTVValue($lng_tv_id));
	$prps[$tvPrefix.$lng_tv] = $val;
	$raw_prps[$lng_tv] = $val;

	foreach ($p->TemplateVarResources as $tvr) {
		$tv_name = $tvr->TemplateVar->get('name');
		$val = $tvr->get('value');
		if ($tv_name == $lat_tv || $tv_name == $lng_tv) {
			$val = json_encode($val); // do this to reduce the chance of use bombing out the javascript.
		}
		$prps[$tvPrefix.$tv_name] = $val;
		$raw_prps[$tv_name] = $val;
	}

	
	if (!isset($raw_prps[$lat_tv]) || !isset($raw_prps[$lng_tv])) {
		$modx->log(xPDO::LOG_LEVEL_ERROR, '[Gmarker] '.$modx->lexicon('invalid_resource', array('id'=> $p->get('id'))));
		continue;
	}
	if ($shadow) {
		$prps['shadow'] = '"shadow": pinShadow,';
	}
	else {
		$prps['shadow'] = '"flat":true,';
	}
	if ($drop) {
		$prps['drop'] = '"animation": google.maps.Animation.DROP,';
	}
	else {
		$prps['drop'] = '';
	}
	if ($info) {
		$prps['info'] = "
		var contentString{$idx} = ".json_encode(trim($modx->getChunk($infoTpl, $prps))).";
		google.maps.event.addListener(marker{$idx}, 'click', function(e) {
			//infowindow.close();
			//infowindow.setContent(contentString{$idx});
			//infowindow.open(myMap,marker{$idx}); 
			
			//use the custom infoBox instead
			var infoBox = new InfoBox({latlng: marker{$idx}.getPosition(), map: myMap, inner: contentString{$idx}});
			
		});
		";
	}
	else {
		$prps['info'] = '';
	}

	// If there are no geocoordinates, optionally look them up
	if (!$suppressLookup && (empty($prps[$lat_tv]) ||  empty($prps[$lng_tv]))) { 
		$uniqid = uniqid();
		$chunk = $modx->newObject('modChunk', array('name' => "{geocoding_tmp}-{$uniqid}"));
		$chunk->setCacheable(false);
		$goog['address'] = $chunk->process($raw_prps, $tpl);
		$goog['bounds'] = $modx->getOption('gmarker.bounds');
		$goog['components'] = $modx->getOption('gmarker.components');
		$goog['region'] = $modx->getOption('gmarker.region');
		$goog['language'] = $modx->getOption('gmarker.language');	
		
		$json = $Gmarker->lookup($goog, $secure);
		
		if(!$p->setTVValue($lat_tv, $Gmarker->get('location.lat'))) {
			$modx->log(xPDO::LOG_LEVEL_ERROR, '[Gmarker] '.$modx->lexicon('problem_saving', array('id'=> $resource->get('id'))));
		}
		if(!$p->setTVValue($lng_tv, $Gmarker->get('location.lng'))) {
			$modx->log(xPDO::LOG_LEVEL_ERROR, '[Gmarker] '.$modx->lexicon('problem_saving', array('id'=> $resource->get('id'))));
		}	
	}


	// Set checkbox group
	$this_group = $p->getTVValue($group);
	$distinct_groups[$this_group] = 1;
	
	$prps['group_json'] = '""';
	if ($group) {
		$group_str = trim($p->getTVValue($group));
		if ($groupCallback) {
			$group_json = $modx->runSnippet($groupCallback,array('group'=>$group_str));
		}
		$prps['group_json'] = json_encode($group_str);
	}
	$prps['marker_color'] = $Gmarker->get_color($p->getTVValue($group),$idx);

	if ($showResults) {
		$prps['marker_center'] = $letter;
		$results .= $modx->getChunk($resultTpl, $prps);	
	}
	else {
		$prps['marker_center'] = $marker_center;
	}
	$props['markers'] .= $modx->getChunk($markerTpl, $prps);
	
	$idx++;
	$letter++;
}

// Get Checkbox Controls
$cb_group = array_keys($distinct_groups);
$checkboxes = '';
if($checkbox == 1 && $group != null) {
	$i = 0;
	foreach ($cb_group as $g ) {
		$props3 = array();
		$props3['group'] = $g;
		$props3['group_id'] = 'gmarker_group_'.$i;

		$group_str = trim($g);
		if ($groupCallback) {
			$group_json = $modx->runSnippet($groupCallback,array('group'=>$group_str));
		}
		$prps3['group_json'] = json_encode($group_str);
		$props3['marker_color'] = $Gmarker->get_color($g,0);
		$checkboxes .= $modx->getChunk($checkboxTpl, $props3);
		$i++;
	};
}

// Look up the map center
$json = $Gmarker->lookup($goog, $secure);

// Pull the coordinates out of the response
$props['lat'] = $Gmarker->get('location.lat');
$props['lng'] = $Gmarker->get('location.lng');

// Add some styling to hide the info-window shadows
$props['hide_shadow'] = '';
if (!$shadow) {
	$props['hide_shadow'] = 'img[src*="iws3.png"] { display: none;}';
}

// Add the stuff to the head
$modx->regClientStartupHTMLBlock($modx->getChunk($headTpl, $props));

$modx->setPlaceholder('gmarker.results',$results);
$modx->setPlaceholder('gmarker.checkboxes',$checkboxes);

return $modx->parseChunk($outTpl, $props);

/*EOF*/

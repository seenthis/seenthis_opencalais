<?php

if (!defined("_ECRIRE_INC_VERSION")) return;

class HTTPSClientCalaisPost {
    /**
     * Errors array init....
     * @var array
     */
    public $_errors = array();
 
    /**
     * Request URL init...
     * @var string
     */
    protected $_url = 'https://api.thomsonreuters.com/permid/calais';
 
    /**
     * request function info....
     *
     * @param string $accessToken .....
     * @return array / json Response array.....
     */
    public function request($accessToken,$post_content) {
        $this -> _errors = array();
 
        if( empty($accessToken) ) {
            $this -> _errors = array('Please enter unique access key as 1st parameter');
            return false;
        }
 
        // Init Header Params
        $headers = array(
            'X-AG-Access-Token: '.$accessToken,
            "Content-Type: text/raw",
            'Content-length: '.strlen($post_content),
            'outputformat: application/json'
        );
 
        // Init Curl
        $curlOptions = array (
            CURLOPT_URL => $this -> _url,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_POSTFIELDS => $post_content,
        );
 
        $ch = curl_init();
        curl_setopt_array($ch,$curlOptions);
 
        // send request and get response from api..........
        $response = curl_exec($ch);
 
        // check cURL errors............
        if (curl_errno($ch)) {
            $this -> _errors = curl_error($ch);
			spip_log($this -> _errors, 'opencalais');
            curl_close($ch);
            return false;
        } else {
            curl_close($ch);
            //print_r($response);
            $response = @json_decode($response);
            spip_log($response, 'opencalais');
            return $response;
        }
    }
}


function getOpenCalais($content) {
	$apiKey = _OPENCALAIS_APIKEY;
	
	$calais = new HTTPSClientCalaisPost();
	$response = $calais->request($apiKey, $content);
	if (!is_object($response)) return false;

	$ret = array();	
	foreach($response as $key => $val) {
		if ($key != "doc") $ret[$key] = $val;
	}

	return $ret;
	
}

/*
 * Va chercher les tags openCalais d'un contenu, et met les tags
 * correspondants dans la table :
 * - spip_me_tags pour les messages
 * - spip_oc_uri pour les textes récupérés sur des sites distants
 *
 */
function traiterOpenCalais($texte, $id, $id_tag="id_article", $lien) {

	// Effacer les liens entre le mot et l'objet
	// uniquement avec relevance > 0
	// pour ne pas effacer les spip_me_mot des hashtags (qui n'ont pas de pondération)
	
	
	$oc = getOpenCalais($texte);

	if (!is_array($oc)) {
		spip_log("erreur opencalais sur $id_tag=$id");
		return false;
	}

	// filtrer les $oc : on ne veut que les entities, et leur relevance
	$tags = array();
	foreach($oc as &$tag) {
		if ($tag->_typeGroup == 'entities') {
			$key = $tag->_type . ':' . $tag->name;
			$relevance = $tag->relevance;
			$tags[$key] = round(1000*$relevance);
		}
	}

	// NEW STYLE: spip_me_tags, pour les messages
	if ($id_tag == 'id_me') {
		// recuperer l'uuid
		$u = sql_fetsel('uuid', 'spip_me', 'id_me='.sql_quote($id));
		$uuid = $u['uuid'];
		$idoff = sql_allfetsel('tag', 'spip_me_tags', 'uuid='.sql_quote($uuid).' AND off="oui" AND class="oc"');

		sql_delete('spip_me_tags', 'uuid='.sql_quote($uuid).' AND class="oc"');
		foreach($tags as $tag => $relevance) {
			if ($relevance > 100) {
				$off = in_array(array('tag'=>$tag), $idoff);
				sql_insertq('spip_me_tags', $c = array(
					'uuid' => $uuid,
					'id_me' => $id,
					'tag' => $tag,
					'relevance' => $relevance,
					'class' => 'oc',
					'off' => $off ? 'oui' : 'non',
					'date' => 'NOW()'
				));
			}
		}
	}

	// spip_oc_uri, pour les sites
	if ($id_tag == 'id_syndic') {
		// recuperer l'uri
		$u = sql_fetsel('url_site', 'spip_syndic', 'id_syndic='.sql_quote($id));
		$uri = $u['url_site'];
		$idoff = sql_allfetsel('tag', 'spip_oc_uri', 'uri='.sql_quote($uri).' AND off="oui"');

		sql_delete('spip_oc_uri', 'uri='.sql_quote($uri));
		foreach($tags as $tag => $relevance) {
			if ($relevance > 300) {
				$off = in_array(array('tag'=>$tag), $idoff);
				sql_insertq('spip_oc_uri', $c = array(
					'uri' => $uri,
					'tag' => $tag,
					'relevance' => $relevance,
					'off' => $off ? 'oui' : 'non'
				));
			}
		}
	}

	return $tags;

}

?>
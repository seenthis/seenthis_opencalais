<?php


function getOpenCalais($content) {
	$apiKey = _OPENCALAIS_APIKEY;
	
	
	//$content = "<h1>Ceci est un essai de texte réalisé avec OpenCalais de Reuters, basé en Californie. Le Liban est un pays intéessant, et <b>Jacques Chirac</b> ne s'y est pas trompé.</h1> Je dis ça, parce que #Saad_Hariri lui a ravi la plame d'or à Cannes.";
	$content = str_replace(array("#","_"), " ", $content);

	$contentType = "text/html"; // simple text - try also text/html
	$outputFormat = "application/json"; // simple output format - try also xml/rdf and text/microformats
	
	$restURL = "http://api.opencalais.com/enlighten/rest/";
	$paramsXML = "<c:params xmlns:c=\"http://s.opencalais.com/1/pred/\" " . 
				"xmlns:rdf=\"http://www.w3.org/1999/02/22-rdf-syntax-ns#\"> " .
				"<c:processingDirectives c:contentType=\"".$contentType."\" " .
				"c:outputFormat=\"".$outputFormat."\"".
				"></c:processingDirectives> " .
				"<c:userDirectives c:allowDistribution=\"false\" " .
				"c:allowSearch=\"false\" c:externalID=\" \" " .
				"c:submitter=\"Calais REST Sample\"></c:userDirectives> " .
				"<c:externalMetadata><c:Caller>Calais REST Sample</c:Caller>" .
				"</c:externalMetadata></c:params>";
	
	// Construct the POST data string
	$data = "licenseID=".urlencode($apiKey);
	$data .= "&paramsXML=".urlencode($paramsXML);
	$data .= "&content=".rawurlencode($content); 
	
	// Invoke the Web service via HTTP POST
	 $ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $restURL);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_TIMEOUT, 60);
	$response = curl_exec($ch);
	curl_close($ch);
	
	$response = json_decode($response);
	
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
			if ($relevance > 300) {
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
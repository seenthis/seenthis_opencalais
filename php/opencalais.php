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
 * Va chercher les tags openCalais d'un contenu, et met les mots-clés
 * correspondants dans la table spip_mots (+spip_groupes_mots)
 * ainsi que dans les tables de liaison :
 * - spip_me_mot pour les messages
 * - spip_syndic_oc pour les textes récupérés sur des sites distants
 *
 * on ajoute (NEW STYLE) la table spip_me_tags, dans un premier temps
 * pour les messages uniquement
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


	//
	//  (OLD STYLE : mots-clés ; encore valable pour les spip_syndic_oc…)
	//

	// se souvenir des liens masques par l'utilisateur (off=oui)
	$off = array();
	$query = sql_select("*", "$lien", "off = 'oui' && $id_tag=".sql_quote($id));
	while($row = sql_fetch($query)) {
		$id_mot = $row["id_mot"];
		$off["$id_mot"] = "oui";
	}
	
	// effacer les éventuels liens openCalais déjà présents
	sql_delete("$lien", "relevance > 0 && $id_tag=".sql_quote($id));

	// mettre les nouveaux liens openCalais
	foreach ($oc AS $tag) {
		$typeGroup = $tag->_typeGroup;
		if ($typeGroup == "entities") {
			$groupe_mot = $tag->_type;
			$nom = $tag->name;
			$relevance = $tag->relevance;
		
			//echo "<hr>";
			//echo "<li>$groupe_mot / <b>$nom</b> ($relevance)";
			//print_r($tag);
			
			if($relevance > 0.3) {
			
				$query_groupe = sql_select("id_groupe", "spip_groupes_mots", "titre=".sql_quote($groupe_mot));
				if ($row_groupe = sql_fetch($query_groupe)) {
					$id_groupe = $row_groupe["id_groupe"];
				} else {
					$id_groupe = sql_insertq("spip_groupes_mots",
						array("titre" => $groupe_mot)
					);
				}
				
				$query_mot = sql_select("id_mot", "spip_mots", "titre=".sql_quote($nom)." AND id_groupe=$id_groupe");
				if ($row_mot = sql_fetch($query_mot)) {
					$id_mot = $row_mot["id_mot"];
				} else {
					$id_mot = sql_insertq("spip_mots",
						array(
							"id_groupe" => $id_groupe,
							"titre" => $nom
						)
					);
				}
				
				//echo "<li>$lien - $nom - $id_mot - $id_tag - $id - ".$off["$id_mot"];
				sql_insertq($lien,
					array(
						"id_mot" => $id_mot,
						"$id_tag" => $id,
						"relevance" => round($relevance * 1000),
						"off" => $off["$id_mot"]
					)
				);
				cache_mot($id_mot);
			}
			
		}	
	}

}

?>
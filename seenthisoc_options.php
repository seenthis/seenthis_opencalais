<?php

function OC_message($id_me) {
	include_spip("php/opencalais");
	
	$query = sql_select("id_me", "spip_me", "(id_me=$id_me OR id_parent=$id_me) AND statut='publi'", "id_me");
	while ($row = sql_fetch($query)) {
		$texte .= " ".texte_de_me($row["id_me"]);
		
	}

	$texte = preg_replace("/"._REG_URL."/i", " ", $texte);
	
	$tags = traiterOpenCalais($texte, $id_me, "id_me", "spip_me_tags");
	cache_me($id_me);

	return $tags;
}

function OC_site($id_syndic) {

	include_spip("php/opencalais");
	
	$query = sql_select("texte", "spip_syndic", "id_syndic=$id_syndic");
	while ($row = sql_fetch($query)) {
		$texte .= " ".$row["texte"];
	}
	if (strlen($texte) > 10) {
		$tags = traiterOpenCalais($texte, $id_syndic, "id_syndic", "spip_syndic_oc");
	}

	return $tags;
}


// fonction appelee lors d'une modif
// afin de programmer une tache de thematisation par opencalais
// [ anciennement function oc_thematiser_message($id_me) ou OC_Site(id_syndic) ]
function seenthisoc_thematiser($flux) {

	# spip_log($flux, 'debug');

	if ($id_me = $flux['id_me']) {

		$p = sql_fetsel("id_parent", "spip_me", "id_me=$id_me");
		if ($p['id_parent'] > 0) {
			job_queue_add('OC_message', 'thématiser message '.$p['id_parent'], array($p['id_parent']));
		} else {
			job_queue_add('OC_message', 'thématiser message '.$id_me, array($id_me));
		}
	} else if ($id_syndic = $flux['id_syndic']) {
		job_queue_add('OC_site', 'thématiser site '.$flux['url'], array($id_syndic));
	}
}

?>

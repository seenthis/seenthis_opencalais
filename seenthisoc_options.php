<?php

function OC_message($id_me) {
	include_spip("php/opencalais");
	
	$query = sql_select("id_me", "spip_me", "(id_me=$id_me OR id_parent=$id_me) AND statut='publi'", "id_me");
	while ($row = sql_fetch($query)) {
		$texte .= " ".texte_de_me($row["id_me"]);
		
	}

	$texte = preg_replace("/"._REG_URL."/i", " ", $texte);
	
	traiterOpenCalais($texte, $id_me, "id_me", "spip_me_mot");
	cache_me($id_me);
	//inserer_themes($id_me);
}

function OC_site($id_syndic) {
	include_spip("php/opencalais");
	
	$query = sql_select("texte", "spip_syndic", "id_syndic=$id_syndic");
	while ($row = sql_fetch($query)) {
		$texte .= " ".$row["texte"];
	}
	if (strlen($texte) > 10) {
		traiterOpenCalais($texte, $id_syndic, "id_syndic", "spip_syndic_oc");
	}

	$query = sql_select("id_mot", "spip_syndic_oc", "id_syndic=$id_syndic");
	while ($row = sql_fetch($query)) {
		$id_mot = $row["id_mot"];
		cache_mot($id_mot);
	}

	$query_syndic = sql_select("id_me", "spip_me_syndic", "id_syndic=$id_syndic");
	while ($row_syndic = sql_fetch($query_syndic)) {
		$id_me = $row_syndic["id_me"];
		//inserer_themes($id_me);
		supprimer_microcache($id_me, "noisettes/oc_message");
	}

}


$GLOBALS["oc_lies"] = array();

function reset_oc_lies($rien) {
	$GLOBALS["oc_lies"] = array();
}

function compter_oc_lies($id_mot, $relevance) {
	if ($relevance > 300 && $relevance > $GLOBALS["oc_lies"]["$id_mot"]) $GLOBALS["oc_lies"]["$id_mot"] = $relevance;	
}

function retour_oc_lies($rien) {
	arsort($GLOBALS["oc_lies"]);
	foreach($GLOBALS["oc_lies"] as $id_mot => $k) {
		if ($k > 1) {
			$ret[] = $id_mot;
		}
	}
	return $ret;
}


function stocker_rel($id_mot, $lien, $off) {
	$GLOBALS["oc_rel"]["$id_mot"] = "mot$id_mot-$lien";
	if ($off == "oui") $GLOBALS["oc_off"]["$id_mot"] = "off";
}

function afficher_rel_mot($id_mot) {
	return $GLOBALS["oc_rel"]["$id_mot"];
}

function afficher_off_mot($id_mot) {
	return $GLOBALS["oc_off"]["$id_mot"];
}

// fonction appelee lors d'une modif
// afin de programmer une tache de thematisation par opencalais
function oc_thematiser_message($id_me) {
	$p = sql_fetsel("id_parent", "spip_me", "id_me=$id_me");
	if ($p['id_parent'] > 0) {
		job_queue_add('OC_message', 'thématiser message '.$p['id_parent'], array($p['id_parent']));
	} else {
		job_queue_add('OC_message', 'thématiser message '.$id_me, array($id_me));
	}
}

?>

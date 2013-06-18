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
	supprimer_microcache($id_syndic, "noisettes/oc_site");
	
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

?>

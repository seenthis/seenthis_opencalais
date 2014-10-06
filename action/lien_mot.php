<?php


function action_lien_mot() {


	$auteur_session = $GLOBALS["auteur_session"]["id_auteur"];
	$relation = _request("relation");
	$statut = _request("statut");
	
	if ($statut == "desactiver") $statut = "oui";
	else if ($statut == "activer") $statut = "non";

	if ($auteur_session < 1) exit;
	$autoriser = false;

	if (preg_match(",mot([0-9]+|.*:.*)\-me([0-9]+)(\-syndic([0-9]+))?,", $relation, $regs)) {

		# old style, id_mot
		if (preg_match(',^\d+$,', $regs[1])) {
			$s = sql_query('SELECT m.titre, g.titre as groupe FROM spip_mots AS m LEFT JOIN spip_groupes_mots AS g ON m.id_groupe=g.id_groupe WHERE id_mot='.sql_quote($regs[1]));
			if ($t = sql_fetch($s)) {
				$tag = $t['groupe'].':'.$t['titre'];
				var_dump($tag);
			} else
				return;
		}
		else {
			$tag = $regs[1];
		}

		$id_me = intval($regs[2]);
		$id_syndic = intval($regs[4]);

		if ($id_me > 0) {
			#var_dump("ME", $id_me, $tag, $statut);

			$query = sql_select("id_auteur", "spip_me", "id_me=$id_me AND id_auteur=$auteur_session");
			if ($row = sql_fetch($query)) {
				$autoriser = true;

				sql_updateq(
					"spip_me_tags", 
					array("off"=> $statut), 
					"id_me=$id_me AND tag=".sql_quote($tag)
				);

				# old style
				if ($id_mot > 0) {
	 				sql_updateq(
					"spip_me_mot", 
					array("off"=> $statut), 
					"id_me=$id_me AND id_mot=$id_mot"
					);
				}
			}
		}

		# OLD STYLE -
		if ($id_syndic > 0 && $autoriser) {
			#echo "SYNDIC";
				sql_updateq(
					"spip_syndic_oc", 
					array("off"=> $statut), 
					"id_syndic=$id_syndic AND id_mot=$id_mot"
				);
		}
		
		if ($id_me > 0 && $autoriser) {
			cache_message($id_me);
			include_spip('inc/headers');
			redirige_par_entete(generer_url_entite($id_me, 'me'));
		}
	}

}



?>
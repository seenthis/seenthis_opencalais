<?php
/*
 * Plugin Seenthis_OpenCalais
 *
 */

if (!defined("_ECRIRE_INC_VERSION")) return;

// lire directement une declaration SQL SHOW CREATE TABLE => SPIP
function seenthisoc_lire_create_table($x) {
	$m = array('field' => array(), 'key' => array());

	foreach(explode("\n", $x) as $line) {
		$line = trim(preg_replace('/,$/', '', $line));
		if (preg_match("/^(PRIMARY KEY) \(`(.*?)`\)/", $line, $c)) {
			$m['key'][$c[1]] = $c[2];
		}
		elseif (preg_match("/^(KEY) `(.*?)`\s+\((.*?)\)/", $line, $c)) {
			$m['key'][$c[1]." ".$c[2]] = $c[3];
		}
		elseif (preg_match("/^`(.*?)`\s+(.*?)$/", $line, $c)) {
			$m['field'][$c[1]] = str_replace('`', '', $c[2]);
		}
	}

	return $m;
}

/*
function seenthisoc_declarer_tables_interfaces($interface){
	return $interface;
}
function seenthisoc_declarer_tables_objets_surnoms($interface){
	return $interface;
}
function seenthisoc_declarer_tables_principales($tables_principales){
	return $tables_principales;
}
*/

function seenthisoc_declarer_tables_auxiliaires($tables_auxiliaires){
	$tables_auxiliaires['spip_syndic_oc'] = seenthis_lire_create_table(
	"
  `id_syndic` bigint(21) NOT NULL,
  `id_mot` bigint(21) NOT NULL,
  `relevance` int(11) NOT NULL,
  `off` varchar(3) NOT NULL DEFAULT 'non',
  KEY `id_syndic` (`id_syndic`),
  KEY `id_mot` (`id_mot`)
"
	);

	return $tables_auxiliaires;

}

function seenthisoc_upgrade($nom_meta_base_version,$version_cible){
	$current_version = 0.0;
	if ((!isset($GLOBALS['meta'][$nom_meta_base_version]) )
	|| (($current_version = $GLOBALS['meta'][$nom_meta_base_version])!=$version_cible)){
		include_spip('base/abstract_sql');
		if (version_compare($current_version,"1.0.1",'<')){
			include_spip('base/serial');
			include_spip('base/auxiliaires');
			include_spip('base/create');
			creer_base();

			maj_tables(array(
				'spip_syndic_oc',
			));
			ecrire_meta($nom_meta_base_version,$current_version=$version_cible,'non');
		}

	}
}

function seenthisoc_vider_tables($nom_meta_base_version) {
	effacer_meta($nom_meta_base_version);
	sql_drop_table("spip_syndic_oc");
}


function seenthisoc_install($action,$prefix,$version_cible){
	$version_base = $GLOBALS[$prefix."_base_version"];
	switch ($action){
		case 'test':
			$ok = (isset($GLOBALS['meta'][$prefix."_base_version"])
				AND version_compare($GLOBALS['meta'][$prefix."_base_version"],$version_cible,">="));
			return $ok;
			break;
		case 'install':
			seenthisoc_upgrade($prefix."_base_version",$version_cible);
			break;
		case 'uninstall':
			seenthisoc_vider_tables($prefix."_base_version");
			break;
	}
}


?>
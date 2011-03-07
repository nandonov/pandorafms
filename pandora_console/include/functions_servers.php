<?php

// Pandora FMS - http://pandorafms.com
// ==================================================
// Copyright (c) 2005-2009 Artica Soluciones Tecnologicas
// Please see http://pandorafms.org for full contribution list

// This program is free software; you can redistribute it and/or
// modify it under the terms of the  GNU Lesser General Public License
// as published by the Free Software Foundation; version 2

// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.

/**
 * @package Include
 * @subpackage Servers
 */

/**
 * Get a server.
 *
 * @param int Server id to get.
 * @param array Extra filter.
 * @param array Fields to get.
 *
 * @return Server with the given id. False if not available.
 */
function get_server ($id_server, $filter = false, $fields = false) {
	if (empty ($id_server))
		return false;
	if (! is_array ($filter))
		$filter = array ();
	$filter['id_server'] = $id_server;
	
	return @get_db_row_filter ('tserver', $filter, $fields);
}

/**
 * Get all the server availables.
 *
 * @return All the servers available.
 */
function get_server_names () {
	$all_servers = @get_db_all_rows_filter ('tserver', false, array ('DISTINCT(name) as name'));
	if ($all_servers === false)
		return array ();
	
	$servers = array ();
	foreach ($all_servers as $server) {
		$servers[$server['name']] = $server['name'];
	}
	return $servers;
}


/**
 * This function will get several metrics from the database to get info about server performance
 * @return array with several data 
 */
function get_server_performance () {

	global $config;

	$data = array();

	// For remote modules:
	// Get total modules running

	if ($config["realtimestats"] == 1){
		$data["total_remote_modules"] =  get_db_sql ("SELECT COUNT(tagente_modulo.id_agente_modulo) FROM tagente_modulo, tagente_estado WHERE tagente_modulo.id_agente_modulo = tagente_estado.id_agente_modulo AND tagente_modulo.id_modulo != 1 AND disabled = 0 AND utimestamp > 0");
	} else {
		$data["total_remote_modules"] = get_db_sql ("SELECT SUM(my_modules) FROM tserver WHERE server_type != 0");
	}

	$data["avg_interval_remote_modules"] = get_db_sql ("SELECT AVG(module_interval) FROM tagente_modulo, tagente_estado where tagente_modulo.id_agente_modulo = tagente_estado.id_agente_modulo AND disabled = 0 AND id_modulo != 1 AND module_interval > 0 AND utimestamp > 0");

	if ($data["total_remote_modules"] == 0)
		$data["remote_modules_rate"] = 0;
	else
		$data["remote_modules_rate"] =  $data["total_remote_modules"] / $data["avg_interval_remote_modules"];

	// For local modules (ignoring local modules with custom invervals for simplicity).
	if ($config["realtimestats"] == 1){
		$data["total_local_modules"] =  get_db_sql ("SELECT COUNT(tagente_modulo.id_agente_modulo) FROM tagente_modulo, tagente_estado WHERE tagente_modulo.id_agente_modulo = tagente_estado.id_agente_modulo AND id_modulo = 1 AND disabled = 0 AND utimestamp > 0");
	} else {
		$data["total_local_modules"] = get_db_sql ("SELECT SUM(my_modules) FROM tserver WHERE server_type = 0");
	}

	$data["avg_interval_local_modules"] = get_db_sql ("SELECT AVG(tagente.intervalo) FROM tagente WHERE  disabled = 0 AND intervalo > 0");

	if ($data["avg_interval_local_modules"] > 0){
		$data["local_modules_rate"] =  $data["total_local_modules"] / $data["avg_interval_local_modules"]; 
	} else {
		$data["local_modules_rate"] = 0;
	}

	$data["total_modules"] = $data["total_local_modules"] + $data["total_remote_modules"];

	return ($data);
}



/**
 * This function will get all the server information in an array or a specific server
 *
 * @param mixed An optional integer or array of integers to select specific servers
 *
 * @return mixed False in case the server doesn't exist or an array with info.
 */
function get_server_info ($id_server = -1) {
	global $config;

	if (is_array ($id_server)) {
		$select_id = " WHERE id_server IN (".implode (",", $id_server).")";
	}
	elseif ($id_server > 0) {
		$select_id = " WHERE id_server IN (".(int) $id_server.")";
	}
	else {
		$select_id = "";
	}
	
	$sql = "SELECT * FROM tserver".$select_id . " ORDER BY server_type";
	$result = get_db_all_rows_sql ($sql);
	$time = get_system_time ();
	
	if (empty ($result)) {
		return false;
	}
	
	$return = array ();
	foreach ($result as $server) {

		switch ($server['server_type']) {
		case 0:
			$server["img"] = print_image ("images/data.png", true, array ("title" => __('Data server')));
			$server["type"] = "data";
			$id_modulo = 1;
			break;
		case 1:
			$server["img"] = print_image ("images/network.png", true, array ("title" => __('Network server')));
			$server["type"] = "network";
			$id_modulo = 2;
			break;
		case 2:
			$server["img"] = print_image ("images/snmp.png", true, array ("title" => __('SNMP server')));
			$server["type"] = "snmp";
			$id_modulo = 0;
			break;
		case 3:
			$server["img"] = print_image ("images/recon.png", true, array ("title" => __('Recon server')));
			$server["type"] = "recon";
			$id_modulo = 0;
			break;
		case 4:
			$server["img"] = print_image ("images/plugin.png", true, array ("title" => __('Plugin server')));
			$server["type"] = "plugin";
			$id_modulo = 4;
			break;
		case 5:
			$server["img"] = print_image ("images/chart_bar.png", true, array ("title" => __('Prediction server')));
			$server["type"] = "prediction";
			$id_modulo = 5;
			break;
		case 6:
			$server["img"] = print_image ("images/wmi.png", true, array ("title" => __('WMI server')));
			$server["type"] = "wmi";
			$id_modulo = 6;
			break;
		case 7:
			$server["img"] = print_image ("images/server_export.png", true, array ("title" => __('Export server')));
			$server["type"] = "export";
			$id_modulo = 0;
			break;
		case 8:
			$server["img"] = print_image ("images/page_white_text.png", true, array ("title" => __('Inventory server')));
			$server["type"] = "inventory";
			$id_modulo = 0;
			break;
		case 9:
			$server["img"] = print_image ("images/world.png", true, array ("title" => __('Web server')));
			$server["type"] = "web";
			$id_modulo = 0;
			break;
		default:
			$server["img"] = '';
			$server["type"] = "unknown";
			$id_modulo = 0;
			break;
		}

		if ($config["realtimestats"] == 0){
			// ---------------------------------------------------------------
			// Take data from database if not realtime stats
			// ---------------------------------------------------------------

			$server["lag"] = get_db_sql ("SELECT lag_time FROM tserver WHERE id_server = ".$server["id_server"]);
			$server["modulelag"] = get_db_sql ("SELECT lag_modules FROM tserver WHERE id_server = ".$server["id_server"]);
			$server["modules"] = get_db_sql ("SELECT my_modules FROM tserver WHERE id_server = ".$server["id_server"]);
			$server["modules_total"] = get_db_sql ("SELECT total_modules_running FROM tserver WHERE id_server = ".$server["id_server"]);

		}
		else {

			// ---------------------------------------------------------------
			// Take data in realtime
			// ---------------------------------------------------------------

			

			$server["module_lag"] = 0;
			$server["lag"] = 0;

			
			if ($server["server_type"] != 3) {

				// ---------------------------------------------------------------
				// Data, Plugin, WMI, Network and Others

				$server["modules"] = get_db_sql ("SELECT count(tagente_estado.id_agente_modulo) FROM tagente_estado, tagente_modulo, tagente WHERE tagente.disabled=0 AND tagente_modulo.id_agente = tagente.id_agente AND tagente_modulo.disabled = 0 AND tagente_modulo.id_agente_modulo = tagente_estado.id_agente_modulo AND tagente_estado.running_by = ".$server["id_server"]);

				$server["modules_total"] = get_db_sql ("SELECT count(tagente_estado.id_agente_modulo) FROM tserver, tagente_estado, tagente_modulo, tagente WHERE tagente.disabled=0 AND tagente_modulo.id_agente = tagente.id_agente AND tagente_modulo.disabled = 0 AND tagente_modulo.id_agente_modulo = tagente_estado.id_agente_modulo AND tagente_estado.running_by = tserver.id_server AND tserver.server_type = ".$server["server_type"]);
				
				// Remote servers LAG Calculation (server_type != 0)
				if ($server["server_type"] != 0) {
					switch ($config["dbtype"]) {
						case "mysql":
							$result = get_db_row_sql ("SELECT COUNT(tagente_modulo.id_agente_modulo) AS module_lag, AVG(UNIX_TIMESTAMP() - utimestamp - current_interval) AS lag FROM tagente_estado, tagente_modulo
								WHERE utimestamp > 0
								AND tagente_modulo.disabled = 0
								AND tagente_modulo.id_agente_modulo = tagente_estado.id_agente_modulo
								AND current_interval > 0
								AND running_by = ".$server["id_server"]."
								AND (UNIX_TIMESTAMP() - utimestamp) < ( current_interval * 10)
								AND (UNIX_TIMESTAMP() - utimestamp) > current_interval");
							break;
						case "postgresql":
							$result = get_db_row_sql ("SELECT COUNT(tagente_modulo.id_agente_modulo) AS module_lag, AVG(ceil(date_part('epoch', CURRENT_TIMESTAMP)) - utimestamp - current_interval) AS lag FROM tagente_estado, tagente_modulo
								WHERE utimestamp > 0
								AND tagente_modulo.disabled = 0
								AND tagente_modulo.id_agente_modulo = tagente_estado.id_agente_modulo
								AND current_interval > 0
								AND running_by = ".$server["id_server"]."
								AND (ceil(date_part('epoch', CURRENT_TIMESTAMP)) - utimestamp) < ( current_interval * 10)
								AND (ceil(date_part('epoch', CURRENT_TIMESTAMP)) - utimestamp) > current_interval");
							break;
					}
				}
				else {
					// Local/Dataserver server LAG calculation:
					switch ($config["dbtype"]) {
						case "mysql":
							$result = get_db_row_sql ("SELECT COUNT(tagente_modulo.id_agente_modulo) AS module_lag, AVG(UNIX_TIMESTAMP() - utimestamp - current_interval) AS lag FROM tagente_estado, tagente_modulo
								WHERE utimestamp > 0
								AND tagente_modulo.disabled = 0
								AND tagente_modulo.id_tipo_modulo < 5
								AND tagente_modulo.id_agente_modulo = tagente_estado.id_agente_modulo
								AND current_interval > 0
								AND (UNIX_TIMESTAMP() - utimestamp) < ( current_interval * 10)
								AND running_by = ".$server["id_server"]."
								AND (UNIX_TIMESTAMP() - utimestamp) > (current_interval * 1.1)");
							break;
						case "postgresql":
							$result = get_db_row_sql ("SELECT COUNT(tagente_modulo.id_agente_modulo) AS module_lag, AVG(ceil(date_part('epoch', CURRENT_TIMESTAMP)) - utimestamp - current_interval) AS lag FROM tagente_estado, tagente_modulo
								WHERE utimestamp > 0
								AND tagente_modulo.disabled = 0
								AND tagente_modulo.id_tipo_modulo < 5 
								AND tagente_modulo.id_agente_modulo = tagente_estado.id_agente_modulo
								AND current_interval > 0
								AND (ceil(date_part('epoch', CURRENT_TIMESTAMP)) - utimestamp) < ( current_interval * 10)
								AND running_by = ".$server["id_server"]."
								AND (ceil(date_part('epoch', CURRENT_TIMESTAMP)) - utimestamp) > (current_interval * 1.1)");
							break;
					}
				}

				// Lag over current_interval * 2 is not lag, it's a timed out module
		
				if (!empty ($result["lag"])) {
					$server["lag"] = $result["lag"];
				} 
					
				if (!empty ($result["module_lag"])) {
					$server["module_lag"] = $result["module_lag"];
				} 

			// ---------------------------------------------------------------
			// Recon server only
			// ---------------------------------------------------------------

			}
			elseif ($server["server_type"] == 3) {

				$server["name"] = '<a href="index.php?sec=estado_server&amp;sec2=operation/servers/view_server_detail&amp;server_id='.$server["id_server"].'">'.$server["name"].'</a>';
			
				//Total jobs running on this recon server
				$server["modules"] = get_db_sql ("SELECT COUNT(id_rt) FROM trecon_task WHERE id_recon_server = ".$server["id_server"]);
		
				//Total recon jobs (all servers)
				$server["modules_total"] = get_db_sql ("SELECT COUNT(status) FROM trecon_task");
		
				//Lag (take average active time of all active tasks)
				$server["module_lag"] = 0;

				switch ($config["dbtype"]) {
					case "mysql":
						$server["lag"] = get_db_sql ("SELECT UNIX_TIMESTAMP() - utimestamp from trecon_task WHERE UNIX_TIMESTAMP()  > (utimestamp + interval_sweep) AND id_recon_server = ".$server["id_server"]);

						$server["module_lag"] = get_db_sql ("SELECT COUNT(id_rt) FROM trecon_task WHERE UNIX_TIMESTAMP()  > (utimestamp + interval_sweep) AND id_recon_server = ".$server["id_server"]);
						break;
					case "postgresql":
						$server["lag"] = get_db_sql ("SELECT ceil(date_part('epoch', CURRENT_TIMESTAMP)) - utimestamp from trecon_task WHERE ceil(date_part('epoch', CURRENT_TIMESTAMP))  > (utimestamp + interval_sweep) AND id_recon_server = ".$server["id_server"]);

						$server["module_lag"] = get_db_sql ("SELECT COUNT(id_rt) FROM trecon_task WHERE ceil(date_part('epoch', CURRENT_TIMESTAMP))  > (utimestamp + interval_sweep) AND id_recon_server = ".$server["id_server"]);
						break;
				}
			} // recon
		} // Take data for realtime mode

		if (isset($server["module_lag"]))
			$server["lag_txt"] = ($server["lag"] == 0 ? '-' : human_time_description_raw ($server["lag"])) . " / ". $server["module_lag"];
		else
			$server["lag_txt"] = "";

		if ($server["modules_total"] > 0) {
			$server["load"] = round ($server["modules"] / $server["modules_total"] * 100); 
		} else {
			$server["load"] = 0;
		}

		//Push the raw data on the return stack
		$return[$server["id_server"]] = $server;
	} // Main foreach

	return $return;
}

?>

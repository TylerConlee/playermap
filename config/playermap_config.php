<?php
// Player map configuration
// Fork of azerothcore/playermap (2026-08-13) -- upstream ships no Docker
// setup at all (plain PHP, no Dockerfile). Added Dockerfile/docker-compose.yml
// here, and modified this file (normally a static copy of
// playermap_config.php.conf) to pull DB credentials from environment
// variables instead of committing them in plaintext, matching how secrets
// are handled elsewhere in this infrastructure (Dockhand-managed env vars,
// never committed to git).
$language         = "en";
$site_encoding    = "utf8";

$db_type          = 'MySQL';

$realm_db['addr']     = getenv('DB_HOST') . ':' . getenv('DB_PORT');
$realm_db['user']     = getenv('DB_USER');
$realm_db['pass']     = getenv('DB_PASSWORD');
$realm_db['name']     = 'acore_auth';
$realm_db['encoding'] = 'utf8';

// position in array must represent realmd ID
$world_db[1]['addr']          = getenv('DB_HOST') . ':' . getenv('DB_PORT');
$world_db[1]['user']          = getenv('DB_USER');
$world_db[1]['pass']          = getenv('DB_PASSWORD');
$world_db[1]['name']          = 'acore_world';
$world_db[1]['encoding']      = 'utf8';

// position in array must represent realmd ID
$characters_db[1]['addr']     = getenv('DB_HOST') . ':' . getenv('DB_PORT');
$characters_db[1]['user']     = getenv('DB_USER');
$characters_db[1]['pass']     = getenv('DB_PASSWORD');
$characters_db[1]['name']     = 'acore_characters';
$characters_db[1]['encoding'] = 'utf8';

//---- Game Server Configuration ----

$server_type        =  1;           // 0=MaNGOS, 1=AzerothCore/TrinityCore

// position in array must represent realmd ID, same as in $world_db
$server[1]['addr']          = getenv('DB_HOST');       // internal Docker network address is fine here, MiniManager runs server-side
$server[1]['addr_wan']      = getenv('GAME_SERVER_WAN_ADDR');
$server[1]['game_port']     =  8085;
$server[1]['rev']           = '';
$server[1]['both_factions'] =  true;


// === Player Map configuration === //

$gm_online                         = true;
$gm_online_count                   = 100;

$map_gm_show_online_only_gmoff     = 1;
$map_gm_show_online_only_gmvisible = 1;
$map_gm_add_suffix                 = 1;
$map_status_gm_include_all         = 0;

$map_show_status =  1;
$map_show_time   =  1;
$map_time        =  5;

$map_time_to_show_uptime    = 3000;
$map_time_to_show_maxonline = 3000;
$map_time_to_show_gmonline  = 3000;

$developer_test_mode =  false;

$multi_realm_mode    =  true;

?>
